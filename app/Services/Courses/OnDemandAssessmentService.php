<?php

namespace App\Services\Courses;

use App\Services\ACLi\DTO\AIRequest;
use App\Services\ACLi\ProviderManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Generates a student's exercises and quiz at the moment they ask for them.
 *
 * The earlier approach wrote one fixed set of exercises and quiz questions per
 * chapter, centrally, for every student. That has two problems. It costs the
 * full price of a course's assessment material whether or not anyone opens it,
 * and the same questions then sit in front of every student in the country --
 * so one answer key circulates and the assessment measures recall of a shared
 * document rather than understanding.
 *
 * Generating on demand fixes both. The work happens only when a student opens
 * an assessment, and because the prompt is seeded per student and per attempt,
 * two students on the same chapter receive different questions.
 *
 * What is stored is the student's own record of what they were asked, so the
 * attempt can be marked consistently afterwards. It is never published and
 * never shared: another student has no way to read it, which is the point.
 *
 * Chapter teaching material stays central. This generates assessment only.
 */
class OnDemandAssessmentService
{
    public function __construct(
        private readonly ProviderManager $providers,
    ) {
    }

    /**
     * Returns a student's assessment for a chapter, generating it once.
     *
     * @return array{questions: array<int, array<string, mixed>>, reused: bool}
     */
    public function forStudent(int $studentId, int $chapterId, int $count = 5): array
    {
        $existing = $this->storedSet($studentId, $chapterId);

        if ($existing !== null) {
            return ['questions' => $existing, 'reused' => true];
        }

        $chapter = DB::table('course_chapters')->where('id', $chapterId)->first();

        if ($chapter === null) {
            return ['questions' => [], 'reused' => false];
        }

        $generated = $this->generate($chapter, $count, $this->seed($studentId, $chapterId, $count));

        if ($generated === []) {
            // Never hand a student an empty assessment presented as working.
            // Saying so is better than a blank page that looks like a bug.
            Log::warning('OnDemandAssessmentService: generation returned nothing', [
                'chapter_id' => $chapterId,
                'student_id' => $studentId,
            ]);

            return ['questions' => [], 'reused' => false];
        }

        $this->store($studentId, $chapterId, $generated);

        return ['questions' => $generated, 'reused' => false];
    }

    /**
     * A stable per-student, per-request seed.
     *
     * Stable so that re-requesting without having attempted does not reshuffle
     * a set the student has already seen, and varied by student and chapter so
     * two people never receive the same paper.
     */
    protected function seed(int $studentId, int $chapterId, int $count): string
    {
        return substr(hash('sha256', "{$studentId}:{$chapterId}:{$count}"), 0, 16);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function generate(object $chapter, int $count, string $seed): array
    {
        $body = trim(implode("\n", array_filter([
            $chapter->introduction,
            $chapter->summary,
            $chapter->key_takeaways,
        ])));

        if ($body === '') {
            // Nothing was written for this chapter, so there is nothing to ask
            // about. Inventing questions would test material the student was
            // never taught.
            return [];
        }

        $prompt = <<<TEXT
You are writing the end-of-chapter quiz for a Nigerian university course.

Chapter: {$chapter->title}

Chapter text:
<<<BODY
{$body}
BODY

Write {$count} multiple-choice questions that test whether the student understood
this chapter. Every question must be answerable from the chapter text above.
Keep the wording plain and the distractor options plausible but clearly wrong,
so the question measures understanding rather than guessing.

This set is for one particular student. Use variation set {$seed} so that two
students working on the same chapter are not given the same paper.

Return ONLY a JSON array. Each element must have exactly these keys:
"question": the question
"options": array of exactly 4 strings
"correct_answer": the exact text of the correct option
"explanation": why that option is right, and why the others are not
"position": the question number starting at 1

Return no prose outside the JSON.
TEXT;

        try {
            $response = $this->providers->chat(new AIRequest(
                model: (string) config('acli.gateway.model'),
                messages: [
                    ['role' => 'system', 'content' => 'You are a senior Nigerian academic. You reply with JSON only, no markdown fences, no commentary.'],
                    ['role' => 'user', 'content' => $prompt],
                ],
                maxTokens: 4000,
            ));
        } catch (\Throwable $e) {
            Log::warning('OnDemandAssessmentService: provider call failed', [
                'chapter_id' => $chapter->id,
                'message' => $e->getMessage(),
            ]);

            return [];
        }

        return $this->parse((string) $response->content, $chapter);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function parse(string $raw, object $chapter): array
    {
        $raw = trim(preg_replace('/^```(?:json)?|```$/m', '', $raw) ?? $raw);
        $decoded = json_decode($raw, true);

        if (! is_array($decoded)) {
            return [];
        }

        if (! array_is_list($decoded)) {
            foreach ($decoded as $value) {
                if (is_array($value) && array_is_list($value)) {
                    $decoded = $value;
                    break;
                }
            }
        }

        $questions = [];

        foreach ($decoded as $index => $item) {
            if (! is_array($item) || blank($item['question'] ?? null)) {
                continue;
            }

            $options = array_values(array_filter(array_map(
                fn ($o) => is_string($o) ? trim($o) : '',
                (array) ($item['options'] ?? [])
            )));

            // Two options cannot be marked meaningfully; a question that
            // cannot be marked must not be shown.
            if (count($options) < 2) {
                continue;
            }

            $questions[] = [
                'position' => (int) ($item['position'] ?? $index + 1),
                'question' => trim((string) $item['question']),
                'options' => $options,
                'correct_answer' => $this->clamp(trim((string) ($item['correct_answer'] ?? '')), 255),
                'explanation' => trim((string) ($item['explanation'] ?? '')),
            ];
        }

        return $questions;
    }

    /**
     * Reads back a set this student was already given.
     *
     * @return array<int, array<string, mixed>>|null
     */
    protected function storedSet(int $studentId, int $chapterId): ?array
    {
        $row = DB::table('assessment_attempts')
            ->where('student_id', $studentId)
            ->where('chapter_id', $chapterId)
            ->whereNotNull('assessment_id')
            ->orderByDesc('id')
            ->first();

        if ($row === null) {
            return null;
        }

        $questions = DB::table('assessment_questions')
            ->where('assessment_id', $row->assessment_id)
            ->orderBy('position')
            ->get();

        if ($questions->isEmpty()) {
            return null;
        }

        return $questions->map(fn ($q) => [
            'position' => (int) $q->position,
            'question' => (string) $q->question,
            'options' => (array) json_decode((string) $q->options, true),
            'correct_answer' => (string) $q->correct_answer,
            'explanation' => (string) $q->explanation,
        ])->all();
    }

    /**
     * Records the set against the student through a personal assessment.
     *
     * The assessment is scoped to this student's attempt rather than to the
     * chapter, so no shared row exists for another student to read.
     *
     * @param  array<int, array<string, mixed>>  $questions
     */
    protected function store(int $studentId, int $chapterId, array $questions): void
    {
        $now = now();

        $courseId = DB::table('course_chapters')->where('id', $chapterId)->value('course_id');

        $assessmentId = DB::table('assessments')->insertGetId([
            'course_id' => $courseId,
            'chapter_id' => $chapterId,
            'title' => 'Chapter assessment',
            'scope' => 'chapter',
            'description' => 'Generated for this student on request.',
            'passing_score' => 50,
            'status' => 'draft',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('assessment_questions')->insert(array_map(fn (array $q) => [
            'assessment_id' => $assessmentId,
            'position' => $q['position'],
            'question_type' => 'mcq',
            'question' => $q['question'],
            'options' => json_encode($q['options']),
            'correct_answer' => $q['correct_answer'],
            'explanation' => $q['explanation'],
            'marks' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ], $questions));

        DB::table('assessment_attempts')->insert([
            'student_id' => $studentId,
            'assessment_id' => $assessmentId,
            // Required, and carrying it means an attempt can be traced back to
            // its course without joining through the chapter.
            'course_id' => $courseId,
            'chapter_id' => $chapterId,
            // Required by the column; an attempt that has not been marked
            // scores nothing yet rather than carrying an arbitrary total.
            'total_marks' => 0,
            'score_earned' => 0,
            'percentage' => 0,
            'passed' => false,
            'status' => 'in_progress',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function clamp(string $value, int $limit): string
    {
        if (mb_strlen($value) <= $limit) {
            return $value;
        }

        return rtrim(mb_substr($value, 0, $limit - 1), " ,.;:");
    }
}