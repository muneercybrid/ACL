<?php

namespace App\Services\ACLi;

use App\Services\ACLi\DTO\AIRequest;
use App\Services\ACLi\ProviderManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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
    /**
     * The floor on chapters per course.
     *
     * The CCMAS Course Contents paragraphs list far more distinct topics than
     * six. Six chapters compress a full syllabus into headings that repeat each
     * other, which is what produced near-duplicate titles: the plan had too few
     * slots for the material, so the model reached for the same topic twice.
     * Twenty is the floor rather than the target -- a course with less to teach
     * gets fewer, and the near-duplicate guard still applies.
     */
    public const MIN_CHAPTERS = 20;

    public function __construct(
        private readonly ProviderManager $providers,
    ) {
    }

    /**
     * Generates chapters for one course.
     *
     * @return array{generated: int, skipped: int, errors: array<int, string>}
     */
    public function generateForCourse(int $courseId, int $chapterCount = self::MIN_CHAPTERS, bool $apply = false): array
    {
        $course = DB::table('courses')->where('id', $courseId)->first();

        if (! $course) {
            return ['generated' => 0, 'skipped' => 0, 'errors' => ['course ' . $courseId . ' not found']];
        }

        $source = mb_substr($this->ccmasContentFor($course), 0, 4000);

        $prompts = $this->chapterPlan($course, $chapterCount, $source);

        $generated = 0;
        $skipped = 0;
        $errors = [];

        // Positions already taken for this course. The unique constraint is on
        // (course_id, position), not on the slug, so resuming has to start after
        // the highest existing position rather than checking titles -- otherwise
        // a re-run collides on position 1 and aborts the whole batch.
        $nextPosition = ((int) DB::table('course_chapters')
            ->where('course_id', $courseId)
            ->max('position')) + 1;

        // Titles already stored for this course count as seen too, so a re-run
        // cannot reintroduce a chapter the course already had.
        $seenTitles = DB::table('course_chapters')
            ->where('course_id', $courseId)
            ->pluck('title')
            ->map(fn ($t) => (string) $t)
            ->all();

        foreach ($prompts as $plan) {
            $position = $nextPosition;
            $nextPosition++;
            $slug = Str::slug($plan['title']);

            // Exact slug equality is not enough. A chapter plan came back with
            // both "History and Evolution of Computing Systems" and "History
            // and Evolution of Computing" -- different slugs, the same
            // chapter -- and both were stored, so a student opened a course
            // and found the same topic listed twice. Near-duplicates are
            // detected against every title already planned in this run as
            // well as against what is stored.
            if ($this->isNearDuplicate($plan['title'], $seenTitles)
                || DB::table('course_chapters')
                    ->where('course_id', $courseId)
                    ->where('slug', $slug)
                    ->exists()) {
                $nextPosition--;
                $skipped++;
                continue;
            }

            $seenTitles[] = (string) $plan['title'];

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
    /**
     * The course's CCMAS content: learning outcomes and course contents.
     *
     * Read from ccmas_course_content, which ccmas:import-content populates
     * from the seventeen discipline documents. That table is the source rather
     * than the documents directly, because the files are build-time artefacts
     * and the whole catalogue depends on this text.
     *
     * The document scan is kept as a fallback so a course whose content has
     * not been imported yet still gets something better than its title. A
     * course with neither is generated from its title and flagged, rather than
     * being quietly given confident-sounding invented content.
     */
    private function ccmasContentFor(object $course): string
    {
        $code = strtoupper((string) $course->normalized_code);

        if ($code !== '') {
            $row = DB::table('ccmas_course_content')->where('code', $code)->first();

            if ($row !== null) {
                return "Learning Outcomes\n" . $row->learning_outcomes
                    . "\n\nCourse Contents\n" . $row->course_contents;
            }
        }

        return $this->ccmasOutlineFor($course);
    }

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
     * Generates the end-of-chapter material: exercises, a chapter quiz, and
     * revision flashcards.
     *
     * All of it is derived from the chapter's own text, so a question can only
     * be about something the chapter actually taught. Everything is written as
     * draft and nothing is attached to a student: exercises carry their
     * solutions, the quiz questions carry their explanations, and the
     * assessment that holds the questions stays in draft until a person
     * approves it.
     *
     * @return array{exercises: int, questions: int, assessment: ?int, flashcards: int, errors: array<int, string>}
     */
    public function generateChapterMaterial(int $chapterId, bool $apply = false): array
    {
        $chapter = DB::table('course_chapters')->where('id', $chapterId)->first();

        if (! $chapter) {
            return ['exercises' => 0, 'questions' => 0, 'assessment' => null, 'flashcards' => 0, 'errors' => ['chapter ' . $chapterId . ' not found']];
        }

        $course = DB::table('courses')->where('id', $chapter->course_id)->first();
        $body = trim((string) $chapter->introduction . "\n" . (string) $chapter->summary . "\n" . (string) $chapter->key_takeaways);

        if ($course === null || $body === '') {
            // Without the chapter's own text there is nothing to base questions
            // on, and inventing them would put material in front of students
            // that the chapter never taught.
            return ['exercises' => 0, 'questions' => 0, 'assessment' => null, 'flashcards' => 0, 'errors' => ['chapter ' . $chapterId . ' has no generated text to build questions from']];
        }

        $exercises = $this->askFor($this->exercisePrompt($course, $chapter, $body), ['questions', 'exercises']);
        $quiz = $this->askFor($this->quizPrompt($course, $chapter, $body), ['questions']);
        $cards = $this->askFor($this->flashcardPrompt($course, $chapter, $body), ['cards', 'flashcards']);

        $exerciseRows = $this->exerciseRows($exercises);
        $questionRows = $this->questionRows($quiz);
        $cardRows = $this->flashcardRows($cards);

        $errors = [];

        if ($apply) {
            if ($exerciseRows !== []) {
                $this->storeExercises($chapter, $exerciseRows);
            }

            if ($questionRows !== []) {
                $this->storeQuiz($chapter, $course, $questionRows);
            }

            if ($cardRows !== []) {
                $this->storeFlashcards($chapter, $cardRows);
            }
        }

        if ($exerciseRows === [] && $questionRows === []) {
            $errors[] = 'no material generated for chapter ' . $chapterId;
        }

        return [
            'exercises' => count($exerciseRows),
            'questions' => count($questionRows),
            'assessment' => $apply && $questionRows !== [] ? $chapterId : null,
            'flashcards' => count($cardRows),
            'errors' => $errors,
        ];
    }

    /**
     * Exercises ask the student to work something out, not just recall it. The
     * solution and the explanation are stored with the question so the same
     * chapter can be marked without a second pass over the material.
     */
    private function exercisePrompt(object $course, object $chapter, string $body): string
    {
        return <<<TEXT
You are writing the end-of-chapter exercises for a Nigerian university course.

Course: {$course->code} - {$course->title}
Chapter: {$chapter->title}

Chapter text:
<<<BODY
{$body}
BODY

Write 3 exercises a student works through after reading this chapter. Vary the
difficulty (beginner, intermediate, advanced) and prefer practical, applied
questions over recall. Make each one understandable on its own: a student
should never need to guess what is being asked.

Return ONLY a JSON array. Each element must have exactly these keys:
"question": what is asked, stated clearly
"exercise_type": "application" | "problem_solving" | "analysis"
"difficulty": "beginner" | "intermediate" | "advanced"
"options": array of 4 strings, or an empty array if it is not multiple choice
"correct_answer": the answer itself
"solution": how to arrive at it, step by step
"explanation": why that is the answer, in one or two sentences
"title": a short label for the exercise

Return no prose outside the JSON.
TEXT;
    }

    private function quizPrompt(object $course, object $chapter, string $body): string
    {
        return <<<TEXT
You are writing the end-of-chapter quiz for a Nigerian university course.

Course: {$course->code} - {$course->title}
Chapter: {$chapter->title}

Chapter text:
<<<BODY
{$body}
BODY

Write 5 multiple-choice questions that test whether a student understood this
chapter. Every question must be answerable from the chapter text above. Keep the
wording plain and the distractor options plausible but clearly wrong, so the
question measures understanding rather than guessing.

Return ONLY a JSON array. Each element must have exactly these keys:
"question": the question
"options": array of exactly 4 strings
"correct_answer": the exact text of the correct option
"explanation": why that option is right, and why the others are not
"position": the question number starting at 1

Return no prose outside the JSON.
TEXT;
    }

    private function flashcardPrompt(object $course, object $chapter, string $body): string
    {
        return <<<TEXT
You are writing revision flashcards for a Nigerian university course.

Course: {$course->code} - {$course->title}
Chapter: {$chapter->title}

Chapter text:
<<<BODY
{$body}
BODY

Write 8 flashcards for revising this chapter. The front is a short prompt a
student can answer in one or two sentences; the back states the answer plainly
and completely enough to revise from.

Return ONLY a JSON array. Each element must have exactly these keys:
"front": the question side
"back": the answer side

Return no prose outside the JSON.
TEXT;
    }

    /**
     * @return array<string, mixed>
     */
    private function askFor(string $prompt, array $keys): array
    {
        $response = $this->askWithRetry($prompt);

        if ($response === null) {
            return [];
        }

        $parsed = $this->parseJson($this->stripFence($response));

        if (! is_array($parsed)) {
            return [];
        }

        // The model sometimes wraps the array in a single key such as
        // {"questions": [...]}. Unwrap it rather than discarding the content.
        foreach ($keys as $key) {
            if (isset($parsed[$key]) && is_array($parsed[$key])) {
                return $parsed[$key];
            }
        }

        if (array_is_list($parsed)) {
            return $parsed;
        }

        // Unwrap whatever single list the model did return, rather than
        // discarding content just because it used a different key name.
        foreach ($parsed as $value) {
            if (is_array($value) && $value !== [] && array_is_list($value)) {
                return $value;
            }
        }

        return [];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function exerciseRows(array $items): array
    {
        $rows = [];

        foreach ($items as $index => $item) {
            if (! is_array($item) || blank($item['question'] ?? null)) {
                continue;
            }

            $rows[] = [
                'position' => $index + 1,
                'title' => $this->clamp($this->plain($item['title'] ?? ('Exercise ' . ($index + 1))), 255),
                // The columns are enums with their own vocabulary, which is
                // not the vocabulary the prompt asks for. Writing the prompt's
                // words straight in truncated the value and lost the exercise
                // entirely, so the two vocabularies are mapped explicitly.
                'difficulty' => match (strtolower((string) ($item['difficulty'] ?? ''))) {
                    'beginner', 'basic', 'easy' => 'basic',
                    'advanced', 'hard' => 'advanced',
                    default => 'intermediate',
                },
                'exercise_type' => match (strtolower((string) ($item['exercise_type'] ?? ''))) {
                    'application' => 'scenario',
                    'analysis' => 'discussion',
                    'multiple_choice' => 'multiple_choice',
                    default => in_array(
                        strtolower((string) ($item['exercise_type'] ?? '')),
                        ['short_answer', 'multiple_choice', 'fill_blank', 'scenario', 'problem_solving', 'practical', 'discussion'],
                        true
                    ) ? strtolower((string) $item['exercise_type']) : 'short_answer',
                },
                'question' => $this->plain($item['question']),
                'solution' => $this->plain($item['solution'] ?? ''),
                'explanation' => $this->plain($item['explanation'] ?? ''),
                'options' => $this->stringList($item['options'] ?? []),
                // title and correct_answer are varchar(255). A model that
                // restates the correct option as a full sentence overruns it,
                // and TiDB rejects the whole insert rather than truncating,
                // so the batch was lost even though the generation succeeded.
                'correct_answer' => $this->clamp($this->plain($item['correct_answer'] ?? ''), 255),
            ];
        }

        return $rows;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function questionRows(array $items): array
    {
        $rows = [];

        foreach ($items as $index => $item) {
            if (! is_array($item) || blank($item['question'] ?? null)) {
                continue;
            }

            $options = $this->stringList($item['options'] ?? []);

            if (count($options) < 2) {
                // A question with fewer than two options cannot be marked.
                continue;
            }

            $rows[] = [
                'position' => (int) ($item['position'] ?? $index + 1),
                'question' => $this->plain($item['question']),
                'options' => $options,
                'correct_answer' => $this->clamp($this->plain($item['correct_answer'] ?? ''), 255),
                'explanation' => $this->plain($item['explanation'] ?? ''),
            ];
        }

        return $rows;
    }

    /**
     * Clamps a string to a column's width without cutting mid-word.
     */
    private function clamp(string $value, int $limit): string
    {
        if (mb_strlen($value) <= $limit) {
            return $value;
        }

        $trimmed = mb_substr($value, 0, $limit - 1);
        $lastSpace = mb_strrpos($trimmed, ' ');

        if ($lastSpace !== false && $lastSpace > $limit * 0.6) {
            $trimmed = mb_substr($trimmed, 0, $lastSpace);
        }

        return rtrim($trimmed, " ,.;:");
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function flashcardRows(array $items): array
    {
        $rows = [];

        foreach ($items as $item) {
            if (! is_array($item) || blank($item['front'] ?? null) || blank($item['back'] ?? null)) {
                continue;
            }

            $rows[] = [
                'front' => $this->plain($item['front']),
                'back' => $this->plain($item['back']),
            ];
        }

        return $rows;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function storeExercises(object $chapter, array $rows): void
    {
        $now = now();

        // Same partial-run protection as the flashcards: (chapter_id, position)
        // is unique, so a re-run must continue past what is already there.
        if ($rows === []) {
            return;
        }

        $start = ((int) DB::table('exercises')->where('chapter_id', $chapter->id)->max('position'));

        DB::table('exercises')->insert(array_map(fn (array $row) => [
            'chapter_id' => $chapter->id,
            'position' => $start + $row['position'],
            'title' => $row['title'],
            'difficulty' => $row['difficulty'],
            'exercise_type' => $row['exercise_type'],
            'question' => $row['question'],
            'solution' => $row['solution'],
            'explanation' => $row['explanation'],
            'options' => json_encode($row['options']),
            'correct_answer' => $row['correct_answer'],
            'created_at' => $now,
            'updated_at' => $now,
        ], $rows));
    }

    /**
     * The quiz is a draft assessment on the chapter. It is created once and
     * reused, so a re-run adds questions to the existing quiz rather than
     * stacking duplicate assessments on the same chapter.
     *
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function storeQuiz(object $chapter, object $course, array $rows): void
    {
        $now = now();

        $assessmentId = DB::table('assessments')
            ->where('chapter_id', $chapter->id)
            ->where('scope', 'chapter')
            ->value('id');

        if (! $assessmentId) {
            $assessmentId = DB::table('assessments')->insertGetId([
                'course_id' => $chapter->course_id,
                'chapter_id' => $chapter->id,
                'title' => 'Chapter ' . $chapter->position . ' quiz: ' . $chapter->title,
                'scope' => 'chapter',
                'description' => 'End-of-chapter quiz for ' . $course->code . '.',
                'passing_score' => 50,
                // Draft. A generated quiz is not marked until a person has
                // read it, so it must not be servable yet.
                'status' => 'draft',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $start = ((int) DB::table('assessment_questions')->where('assessment_id', $assessmentId)->max('position')) + 1;

        DB::table('assessment_questions')->insert(array_map(function (array $row, int $offset) use ($assessmentId, $start, $now) {
            return [
                'assessment_id' => $assessmentId,
                'position' => $start + $offset,
                'question_type' => 'mcq',
                'question' => $row['question'],
                'options' => json_encode($row['options']),
                'correct_answer' => $row['correct_answer'],
                'explanation' => $row['explanation'],
                'marks' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }, $rows, array_keys($rows)));
    }

    /**
     * @param  array<int, array<string, string>>  $rows
     */
    private function storeFlashcards(object $chapter, array $rows): void
    {
        $now = now();

        // Resume from the highest position already stored. The unique index is
        // on (chapter_id, position), so re-running after a partial failure --
        // which is the normal case, since the three writes are sequential --
        // would otherwise collide on position 1 and abort before writing
        // anything.
        if ($rows === []) {
            return;
        }

        $start = ((int) DB::table('chapter_flashcards')->where('chapter_id', $chapter->id)->max('position'));

        DB::table('chapter_flashcards')->insert(array_map(function (array $row, int $offset) use ($chapter, $now, $start) {
            return [
                'chapter_id' => $chapter->id,
                'position' => $start + $offset + 1,
                'front' => $row['front'],
                'back' => $row['back'],
                'status' => 'draft',
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }, $rows, array_keys($rows)));
    }

    private function plain($value): string
    {
        if (is_array($value)) {
            $value = implode(', ', array_filter($value, 'is_string'));
        }

        if (! is_string($value)) {
            return '';
        }

        $value = str_replace(['\\r\\n', '\\n', '\\r'], ["\n", "\n", ''], $value);

        return trim($value);
    }

    /**
     * @return array<int, string>
     */
    private function stringList($value): array
    {
        if (is_string($value)) {
            $value = array_map('trim', explode(',', $value));
        }

        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter(array_map(
            fn ($v) => is_string($v) ? $this->plain($v) : '',
            $value
        )));
    }

    /**
     * Asks for a chapter outline first, so the chapters are planned as a
     * coherent sequence rather than generated independently and repeating
     * themselves.
     */
    private function chapterPlan(object $course, int $count, string $source): array
    {
        $outline = $source !== ''
            ? "The NUC CCMAS document states the following for this course. "
                ."Every learning outcome must be covered by at least one chapter, and the "
                ."chapters must follow the course contents in the order given.\n\n" . mb_substr($source, 0, 4000)
            : 'The NUC CCMAS document for this course was not recoverable. Write the chapter titles from the course title alone and keep them broad.';

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
            'introduction' => trim($this->bullets($parsed['introduction'] ?? '') . "\n\n" . $this->bullets($parsed['body'] ?? '')),
            'summary' => $this->bullets($parsed['summary'] ?? ''),
            'key_takeaways' => $this->bullets($parsed['key_takeaways'] ?? ''),
            'further_reading' => $this->bullets($parsed['further_reading'] ?? ''),
        ];
    }

    private function ask(string $prompt): ?string
    {
        try {
            $request = new AIRequest(
                // Resolved from config, never hardcoded. "auto" is an omniroute
                // route name that was left here when the base URL moved to
                // OpenRouter, where no model of that name exists -- every
                // request failed on the primary and silently succeeded on the
                // first fallback, which is why it went unnoticed.
                model: (string) config('acli.gateway.model'),
                messages: [
                    ['role' => 'system', 'content' => 'You are a senior Nigerian academic. You reply with JSON only, no markdown fences, no commentary.'],
                    ['role' => 'user', 'content' => $prompt],
                ],
                // Three exercises, each with four options, a worked solution
                // and an explanation, run past 4000 tokens and came back
                // truncated and unparseable.
                maxTokens: 6000,
            );

            return $this->providers->chat($request)->content;
        } catch (\Throwable $e) {
            // Swallowing this silently is what made both the dead provider and
            // the bad model name look like "the model had nothing to say". A
            // failed generation must be visible, not indistinguishable from a
            // model declining to answer.
            Log::warning('CourseContentGenerator: generation call failed', [
                'message' => $e->getMessage(),
                'model' => (string) config('acli.gateway.model'),
                'exception' => $e::class,
            ]);

            return null;
        }
    }

    /**
     * Whether two chapter titles describe the same chapter.
     *
     * Word-set overlap, not string distance: the duplicates that actually
     * occurred differ only by a trailing word ("...Computing Systems" vs
     * "...Computing"), which a length-normalised distance would call
     * different. Requiring most of the significant words to be shared
     * catches that, and shared stop words are ignored so "Introduction to
     * Cyber Security" and "Introduction to Data Protection" are not treated
     * as the same chapter.
     */
    private function isNearDuplicate(string $title, array $seen): bool
    {
        $words = $this->significantWords($title);

        if ($words === []) {
            return false;
        }

        foreach ($seen as $other) {
            $otherWords = $this->significantWords((string) $other);

            if ($otherWords === []) {
                continue;
            }

            $shared = count(array_intersect($words, $otherWords));
            $smaller = min(count($words), count($otherWords));

            if ($smaller > 0 && $shared / $smaller >= 0.8) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<int, string>
     */
    private function significantWords(string $title): array
    {
        $stop = ['and', 'the', 'of', 'in', 'to', 'for', 'a', 'an', 'with', 'on', 'its'];

        $words = preg_split('/[^a-z0-9]+/i', mb_strtolower($title)) ?: [];
        $words = array_filter($words, fn ($w) => $w !== '' && ! in_array($w, $stop, true));

        return array_values(array_unique($words));
    }

    /**
     * Asks, retrying an empty answer.
     *
     * The provider intermittently returns an empty body -- a transient fault
     * rather than a refusal -- and the previous behaviour took that as the
     * answer and silently produced a chapter with no exercises. Two attempts
     * make that rare enough not to matter, and a still-empty result is
     * reported rather than passed off as "nothing to generate".
     */
    private function askWithRetry(string $prompt, int $attempts = 2): ?string
    {
        for ($i = 0; $i < $attempts; $i++) {
            $response = $this->ask($prompt);

            if (is_string($response) && trim($response) !== '') {
                return $response;
            }
        }

        return null;
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

    /**
     * Normalises a bullet field to newline-separated text.
     *
     * The model is inconsistent about these fields: sometimes a single string
     * with "- " lines, sometimes a JSON array of strings, and it double-escapes
     * newlines often enough that a literal backslash-n reaches us and renders
     * as corrupted text. All three shapes are accepted because the content is
     * still valid; only the packaging differs.
     *
     * @param  mixed  $value
     */
    private function bullets($value): string
    {
        if (is_array($value)) {
            $parts = [];

            foreach ($value as $item) {
                if (is_string($item)) {
                    $parts[] = $item;
                } elseif (is_array($item) && isset($item['text']) && is_string($item['text'])) {
                    $parts[] = $item['text'];
                }
            }

            $value = implode("\n", $parts);
        }

        if (! is_string($value)) {
            return '';
        }

        // Undo double-escaping: a literal backslash-n here would render as
        // "\n" in the chapter and read as corrupted text.
        $value = str_replace(['\\r\\n', '\\n', '\\r'], ["\n", "\n", ''], $value);

        $lines = preg_split('/\r?\n/', trim($value)) ?: [];
        $lines = array_filter(array_map('trim', $lines));

        return implode("\n", $lines);
    }
}
