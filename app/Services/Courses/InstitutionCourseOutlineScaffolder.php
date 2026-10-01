<?php

namespace App\Services\Courses;

/**
 * Creates reviewable draft outlines for courses a school added itself.
 *
 * The national curriculum arrived with content structure already attached:
 * 140,000-odd chapter placeholders across the CCMAS catalogue, all marked
 * status=draft and placeholder=1. Courses a school adds are not in that
 * catalogue, so they reach the student with nothing at all -- an empty outline
 * and an empty chapter list, which reads as a broken course rather than an
 * unwritten one.
 *
 * This gives those courses the same starting point, following the same
 * convention, so a coordinator opens them and fills them in.
 *
 * Nothing here is published. Every row is a draft with placeholder set, and
 * none carries an approver or an approval timestamp. That is deliberate and is
 * the whole point: this produces something for a human to correct, never
 * course material a student would be taught from unreviewed. The chapter titles
 * are the same generic structural headings the CCMAS placeholders use for the
 * same reason -- they name a slot to be written, not a syllabus.
 */
class InstitutionCourseOutlineScaffolder
{
    /**
     * The structural headings the CCMAS placeholders already use. Reusing them
     * keeps a coordinator's experience identical whether a course came from
     * the national catalogue or was added by their school.
     *
     * @var list<string>
     */
    public const CHAPTER_HEADINGS = [
        'Course Orientation and Learning Outcomes',
        'Prerequisites and Prior Knowledge',
        'Core Concepts and Definitions',
        'Theoretical Foundations',
        'Key Principles in Practice',
        'Methods and Techniques',
        'Applications and Case Studies',
        'Tools, Technologies and Resources',
        'Common Pitfalls and Challenges',
        'Assessment and Review',
    ];

    /**
     * Scaffold one course. Idempotent: a course that already has an outline or
     * chapters is left alone, so re-running never duplicates or overwrites
     * work a coordinator has begun.
     */
    public function scaffoldForCourse(int $courseId): array
    {
        $course = \App\Models\Course::find($courseId);

        if ($course === null) {
            return ['course_id' => $courseId, 'outline' => 'missing course', 'chapters' => 0];
        }

        $outlineResult = $this->ensureOutline($course);
        $chapters = $this->ensureChapters($course);

        return [
            'course_id' => $courseId,
            'code' => $course->code,
            'title' => $course->title,
            'outline' => $outlineResult,
            'chapters' => $chapters,
        ];
    }

    /**
     * Scaffold every institution-sourced course that has no outline yet.
     */
    public function scaffoldPending(?int $limit = null): array
    {
        $pending = \App\Models\Course::query()
            ->where('source_type', 'institution')
            ->whereDoesntHave('outlines')
            ->when($limit, fn ($q) => $q->limit($limit))
            ->get();

        $results = [];

        foreach ($pending as $course) {
            $results[] = $this->scaffoldForCourse((int) $course->id);
        }

        return [
            'scaffolded' => count($results),
            'chapters' => array_sum(array_column($results, 'chapters')),
            'results' => $results,
        ];
    }

    protected function ensureOutline(\App\Models\Course $course): string
    {
        $existing = \App\Models\Curriculum\CourseOutline::where('course_id', $course->id)
            ->where('version', 1)
            ->first();

        if ($existing !== null) {
            return 'already present';
        }

        \App\Models\Curriculum\CourseOutline::create([
            'course_id' => $course->id,
            'version' => 1,
            'description' => $this->descriptionFor($course),
            'learning_outcomes' => $this->outcomesFor($course),
            'recommended_resources' => [],
            // Draft, and never approved. This row is a starting point for a
            // coordinator to correct, not material to teach from.
            'status' => 'draft',
            'is_locked' => false,
        ]);

        return 'created';
    }

    protected function ensureChapters(\App\Models\Course $course): int
    {
        $existing = \DB::table('course_chapters')
            ->where('course_id', $course->id)
            ->count();

        if ($existing > 0) {
            return 0;
        }

        $now = now();
        $rows = [];

        foreach (self::CHAPTER_HEADINGS as $index => $heading) {
            $rows[] = [
                'course_id' => $course->id,
                'position' => $index + 1,
                'title' => $heading,
                'slug' => \Illuminate\Support\Str::slug($heading).'-'.($index + 1),
                'introduction' => null,
                'summary' => '',
                'key_takeaways' => null,
                'further_reading' => null,
                'version' => 1,
                'status' => 'draft',
                'generated_by' => null,
                'placeholder' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        \DB::table('course_chapters')->insert($rows);

        return count($rows);
    }

    /**
     * A neutral description that states the course's subject and its unfinished
     * state, rather than asserting content that has not been written.
     */
    protected function descriptionFor(\App\Models\Course $course): string
    {
        return sprintf(
            '%s. This outline is a draft prepared for review by the school that added the course. '
            .'It has not been approved or published, and no chapters have been written yet.',
            trim((string) $course->title)
        );
    }

    /**
     * Placeholder outcomes derived from the course's own title.
     *
     * These deliberately describe the shape of the course rather than its
     * substance. A real outcome statement is a curriculum decision for the
     * school to make and approve, and inventing one here would put unreviewed
     * academic content in front of students.
     *
     * @return list<string>
     */
    protected function outcomesFor(\App\Models\Course $course): array
    {
        return [
            sprintf('Explain the core ideas covered in %s.', trim((string) $course->title)),
            sprintf('Apply the principles of %s to practical situations.', trim((string) $course->title)),
            sprintf('Evaluate common problems encountered in %s.', trim((string) $course->title)),
            'To be refined and approved by the school before publication.',
        ];
    }
}
