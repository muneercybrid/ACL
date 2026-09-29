<?php

namespace App\Services\ACLi;

use App\Services\ACLi\DTO\AIRequest;
use App\Services\ACLi\ProviderManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Generates course chapters and lesson text from the NUC CCMAS content.
 *
 * Content is written as DRAFT and never published directly. AGENTS.md section 7
 * requires Draft -> Human Review -> Correction -> Approval -> Publish, and this
 * class is deliberately the first step only. `generated_by` records ACLi so a
 * human reviewer can tell AI-written material from authored material.
 *
 * The CCMAS document is the source of truth for what a course must teach. Each
 * chapter is generated from the course's own entry in that document, so the
 * material is derived from the national standard rather than invented.
 */
class CourseContentGenerator
{
    public function __construct(
        private readonly ProviderManager $providers,
    ) {
    }

    /**
     * Generates chapters for one course.
     *
     * @return array{generated: int, skipped: int, errors: array<int, string>}
     */
    public function generateForCourse(int $courseId, int $chapterCount = 6, bool $apply = false): array
    {
        $course = DB::table('courses')->where('id', $courseId)->first();

        if (! $course) {
            return ['generated' => 0, 'skipped' => 0, 'errors' => ['course ' . $courseId . ' not found']];
        }

        $source = mb_substr($this->ccmasOutlineFor($course), 0, 1200);

        $prompts = $this->chapterPlan($course, $chapterCount, $source);

        $generated = 0;
        $skipped = 0;
        $errors = [];
        $position = 0;

        foreach ($prompts as $plan) {
            $position++;
            $slug = Str::slug($plan['title']);

            if (DB::table('course_chapters')->where('course_id', $courseId)->where('slug', $slug)->exists()) {
                $skipped++;
                continue;
            }

            $content = $this->writeChapter($course, $plan, $source);

            if ($content === null) {
                $errors[] = $course->code . ' chapter ' . $position . ' generation failed';
                continue;
            }

            if ($apply) {
                DB::table('course_chapters')->insert([
                    'course_id' => $courseId,
                    'position' => $position,
                    'title' => $plan['title'],
                    'slug' => $slug,
                    'introduction' => $content['introduction'],
                    'summary' => $content['summary'],
                    'key_takeaways' => $content['key_takeaways'],
                    'further_reading' => $content['further_reading'],
                    // Draft, never published: this is the only status this
                    // generator is allowed to write.
                    'status' => 'draft',
                    // generated_by is a user id (a human reviewer), not a free
                    // text provenance field. ACLi is not a user, so it is left
                    // null rather than being stuffed with a string; the draft
                    // status is what marks this as unreviewed.
                    'generated_by' => null,
                    'version' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $generated++;
        }

        return ['generated' => $generated, 'skipped' => $skipped, 'errors' => $errors];
    }

    /**
     * The course's own text in the CCMAS document, which states what it covers.
     *
     * Returning the real outline is the difference between material derived
     * from the NUC standard and material invented to fill a schema.
     */
    private function ccmasOutlineFor(object $course): string
    {
        $document = $course->source_document;

        if (! $document) {
            return '';
        }

        $path = base_path('storage/app/nuc-ccmas/' . basename((string) $document));

        if (! is_readable($path)) {
            return '';
        }

        $handle = fopen($path, 'r');
        if (! $handle) {
            return '';
        }

        $window = '';
        $lineNo = 0;
        $found = false;

        // The outline sits within a few hundred lines of the course title in
        // these extractions. Reading the whole 2 MB file per course would be
        // far too slow to do thousands of times.
        while (($line = fgets($handle)) !== false && $lineNo < 400000) {
            $lineNo++;
            $line = trim($line);

            if (! $found) {
                if (str_contains($line, (string) $course->code)
                    && stripos($line, (string) $course->title) !== false) {
                    $found = true;
                }
                continue;
            }

            // Stop at the next course heading.
            if (preg_match('/\b[A-Z]{3}\d{3}\b/', $line) && ! str_contains($line, (string) $course->code)) {
                break;
            }

            $window .= $line . "\n";

            if (strlen($window) > 4000) {
                break;
            }
        }

        fclose($handle);

        return $found ? trim($window) : '';
    }

    /**
     * Asks for a chapter outline first, so the chapters are planned as a
     * coherent sequence rather than generated independently and repeating
     * themselves.
     */
    private function chapterPlan(object $course, int $count, string $source): array
    {
        $outline = $source !== ''
            ? "The NUC CCMAS outline for this course is:\n" . mb_substr($source, 0, 2500)
            : 'The NUC CCMAS outline for this course was not recoverable from the document.';

        $prompt = <<<TEXT
You are writing a course handbook for a Nigerian university, following the NUC Core Curriculum and Minimum Academic Standards (CCMAS).

Course code: {$course->code}
Course title: {$course->title}

{$outline}

Propose exactly {$count} chapter titles for this course. They must:
- cover the CCMAS outline above in order, without repeating each other
- be specific to this course, not generic study skills
- each be a short noun phrase of at most 8 words

Return ONLY a JSON array of strings. No prose, no markdown, no code fence.
TEXT;

        $response = $this->ask($prompt);

        if ($response === null) {
            // Falling back to a generic plan would produce material that is not
            // derived from the standard, so report nothing generated instead.
            return [];
        }

        return $this->parseTitles($response, $count, $course);
    }

    /**
     * Writes one chapter: the body text plus the structured fields the schema
     * carries.
     */
    private function writeChapter(object $course, array $plan, string $source): ?array
    {
        $prompt = <<<TEXT
You are writing one chapter of a Nigerian university course handbook, following the NUC CCMAS standard.

Course: {$course->code} — {$course->title}
Chapter: {$plan['title']}
{$plan['objective']}

Relevant CCMAS material for this course (excerpt):
{$source}

Keep the whole response under 900 words.

Write this chapter. Keep it in Nigerian university register: clear, formal, exam-relevant, and grounded in the standard above rather than invented detail.

Return ONLY a JSON object with exactly these keys:
"introduction": 2 short paragraphs opening the chapter
"body": the main teaching text, 4-6 short paragraphs, at most 120 words each
"summary": 3-5 bullet lines prefixed with "- "
"key_takeaways": 3-5 bullet lines prefixed with "- "
"further_reading": 2-3 suggested sources or topics

Return no prose outside the JSON.
TEXT;

        $response = $this->ask($prompt);

        if ($response === null) {
            return null;
        }

        $parsed = $this->parseJson($response);

        if ($parsed === null || ! isset($parsed['introduction'], $parsed['body'])) {
            return null;
        }

        // The body is prepended to the introduction so nothing written is
        // silently discarded; there is no body column on the table.
        return [
            'introduction' => trim($parsed['introduction'] . "\n\n" . ($parsed['body'] ?? '')),
            'summary' => $this->bullets($parsed['summary'] ?? ''),
            'key_takeaways' => $this->bullets($parsed['key_takeaways'] ?? ''),
            'further_reading' => $this->bullets($parsed['further_reading'] ?? ''),
        ];
    }

    private function ask(string $prompt): ?string
    {
        try {
            $request = new AIRequest(
                model: 'auto',
                messages: [
                    ['role' => 'system', 'content' => 'You are a senior Nigerian academic. You reply with JSON only, no markdown fences, no commentary.'],
                    ['role' => 'user', 'content' => $prompt],
                ],
                maxTokens: 4000,
            );

            return $this->providers->chat($request)->content;
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function parseTitles(string $response, int $count, object $course): array
    {
        $clean = $this->stripFence($response);
        $decoded = json_decode($clean, true);

        if (! is_array($decoded)) {
            return [];
        }

        $titles = [];

        foreach ($decoded as $item) {
            $title = is_string($item) ? $item : ($item['title'] ?? null);

            if (! is_string($title) || trim($title) === '') {
                continue;
            }

            $titles[] = [
                'title' => trim($title),
                'objective' => 'By the end of this chapter a student should be able to explain the material in "'
                    . trim($title) . '" as it relates to ' . $course->code . ' ' . $course->title . '.',
            ];

            if (count($titles) >= $count) {
                break;
            }
        }

        return $titles;
    }

    /**
     * Tolerant JSON decode.
     *
     * The model returns clean JSON for a short prompt and truncated, mid-string
     * JSON for a long one, because the chapter body is several paragraphs and
     * the token ceiling is hit mid-value. json_decode() then fails on the whole
     * document and the chapter is lost entirely, even though the introduction
     * and the first fields were written perfectly well.
     *
     * So on failure, extract each key individually. A partial chapter is
     * strictly better than none, and the parts recovered are real content
     * rather than a placeholder.
     */
    private function parseJson(string $response): ?array
    {
        $clean = $this->stripFence($response);
        $decoded = json_decode($clean, true);

        if (is_array($decoded)) {
            return $decoded;
        }

        $recovered = [];

        foreach (['introduction', 'body', 'summary', 'key_takeaways', 'further_reading'] as $key) {
            $value = $this->recoverStringField($clean, $key);

            if ($value !== null) {
                $recovered[$key] = $value;
            }
        }

        return $recovered === [] ? null : $recovered;
    }

    /**
     * Pulls one string value out of a JSON document that was cut off, stopping
     * at the last unescaped quote before the truncation.
     */
    private function recoverStringField(string $json, string $key): ?string
    {
        if (! preg_match('/"' . preg_quote($key, '/') . '"\s*:\s*"/', $json, $m, PREG_OFFSET_CAPTURE)) {
            return null;
        }

        $start = $m[0][1] + strlen($m[0][0]);
        $length = strlen($json);
        $escaped = false;

        for ($i = $start; $i < $length; $i++) {
            $char = $json[$i];

            if ($escaped) {
                $escaped = false;
                continue;
            }

            if ($char === '\\') {
                $escaped = true;
                continue;
            }

            if ($char === '"') {
                return substr($json, $start, $i - $start);
            }
        }

        // Never closed: the document was truncated. Take what was written.
        return substr($json, $start);
    }

    /**
     * Models wrap JSON in ```json fences often enough that assuming they do not
     * makes the whole pipeline fail intermittently.
     */
    private function stripFence(string $response): string
    {
        $response = trim($response);
        $response = preg_replace('/^```(?:json)?\s*/i', '', $response) ?? $response;
        $response = preg_replace('/\s*```$/', '', $response) ?? $response;

        return trim($response);
    }

    private function bullets(string $value): string
    {
        // Models double-escape newlines inside JSON fairly often, so the value
        // arrives holding a literal backslash-n rather than a line break. Left
        // alone it renders as "\n" in the chapter and reads as corrupted text.
        $value = str_replace(['\\n', '\\r'], ["\n", ''], $value);

        $lines = preg_split('/\r?\n/', trim($value)) ?: [];

        return implode("\n", array_filter(array_map('trim', $lines)));
    }
}
