<?php

namespace App\Services\Curriculum;

use App\Models\Curriculum\Course;
use Illuminate\Support\Facades\DB;

/**
 * The single read path for course content.
 *
 * Course content is authored once per course and shared by every student
 * taking it. There is deliberately no per-student content: a chapter belongs
 * to a course, not to a person. That is what makes an update land everywhere
 * at once instead of forking per student, and it is why ACLi has exactly one
 * place to look for what a course teaches.
 *
 * The reverse — a student's own generated material — is not a thing. Students
 * read the catalogue and ask ACLi questions about it.
 */
class CourseContentCatalog
{
    /**
     * A course's full content: chapters, each with its lessons.
     *
     * Returned only when the course is `published`. Draft and review chapters
     * are authoring states and must never reach a student, so they are filtered
     * out here rather than at the view layer, where one forgotten check would
     * expose unreviewed AI-written text.
     *
     * @return array{
     *     course: object|null,
     *     chapters: array<int, array<string, mixed>>,
     *     complete: bool
     * }
     */
    public function forCourse(int $courseId): array
    {
        $course = DB::table('courses')->where('id', $courseId)->first();

        if (! $course) {
            return ['course' => null, 'chapters' => [], 'complete' => false];
        }

        $chapters = DB::table('course_chapters')
            ->where('course_id', $courseId)
            ->where('status', 'published')
            ->orderBy('position')
            ->orderBy('id')
            ->get();

        if ($chapters->isEmpty()) {
            return ['course' => $course, 'chapters' => [], 'complete' => false];
        }

        $lessons = DB::table('lessons')
            ->whereIn('chapter_id', $chapters->pluck('id'))
            ->orderBy('position')
            ->orderBy('id')
            ->get()
            ->groupBy('chapter_id');

        $payload = [];

        foreach ($chapters as $chapter) {
            $payload[] = [
                'id' => $chapter->id,
                'position' => $chapter->position,
                'title' => $chapter->title,
                'slug' => $chapter->slug,
                'introduction' => $chapter->introduction,
                'summary' => $chapter->summary,
                'key_takeaways' => $chapter->key_takeaways,
                'further_reading' => $chapter->further_reading,
                'version' => $chapter->version,
                'lessons' => ($lessons[$chapter->id] ?? collect())->map(fn ($l) => [
                    'id' => $l->id,
                    'title' => $l->title,
                    'slug' => $l->slug,
                    'position' => $l->position,
                    'status' => $l->status,
                ])->values(),
            ];
        }

        return [
            'course' => $course,
            'chapters' => $payload,
            // "Complete" drives the student-facing "not yet available" state, so
            // a half-authored course never looks like a finished one.
            'complete' => count($payload) > 0,
        ];
    }

    /**
     * Every course in the catalogue with its published content count, for
     * administration and for ACLi's grounding.
     *
     * @return \Illuminate\Support\Collection<int, object>
     */
    public function catalogue(?string $disciplineCode = null)
    {
        $query = DB::table('courses')
            ->leftJoin('course_chapters', function ($join) {
                $join->on('course_chapters.course_id', '=', 'courses.id')
                    ->where('course_chapters.status', '=', 'published');
            })
            ->leftJoin('nuc_disciplines', 'nuc_disciplines.id', '=', 'courses.nuc_discipline_id')
            ->select(
                'courses.id',
                'courses.code',
                'courses.title',
                'courses.nuc_discipline_id',
                'nuc_disciplines.code as discipline_code',
                'nuc_disciplines.name as discipline_name',
                DB::raw('COUNT(course_chapters.id) as published_chapters')
            )
            ->groupBy(
                'courses.id',
                'courses.code',
                'courses.title',
                'courses.nuc_discipline_id',
                'nuc_disciplines.code',
                'nuc_disciplines.name'
            )
            ->orderBy('courses.code');

        if ($disciplineCode) {
            $query->where('nuc_disciplines.code', strtoupper($disciplineCode));
        }

        return $query->get();
    }

    /**
     * The text ACLi is allowed to reason over for a given course.
     *
     * Bounded on purpose. An unbounded dump of a whole course would blow the
     * context window and cost more per question than it is worth; the student
     * is asking about one chapter, not the entire catalogue.
     */
    public function groundingFor(int $courseId, ?string $chapterSlug = null, int $limit = 6000): string
    {
        $query = DB::table('course_chapters')
            ->where('course_id', $courseId)
            ->where('status', 'published')
            ->orderBy('position');

        if ($chapterSlug) {
            $query->where('slug', $chapterSlug);
        }

        $parts = [];

        foreach ($query->get() as $chapter) {
            $parts[] = "## {$chapter->title}\n{$chapter->introduction}\n";

            if ($chapter->summary) {
                $parts[] .= "Summary: {$chapter->summary}\n";
            }

            if ($chapter->key_takeaways) {
                $parts[] .= "Key points: {$chapter->key_takeaways}\n";
            }

            $text = implode('', $parts);

            if (strlen($text) >= $limit) {
                break;
            }
        }

        return mb_substr(implode('', $parts), 0, $limit);
    }

    /**
     * Whether a student may read this course, given what they are actually
     * enrolled in.
     *
     * This is a server-side entitlement check. The fact that a course id is
     * guessable, or that a link is known, is not authorisation.
     */
    public function studentMayRead(int $courseId, int $userId): bool
    {
        // The real path from a student to a course is:
        //   organization_memberships.academic_program_id
        //     -> curriculum_versions.programme_id
        //       -> curriculum_courses.curriculum_version_id -> course_id
        //
        // Joining curriculum_courses straight off the offering would compile but
        // be meaningless: that table has no academic_program_id column, so the
        // check would silently pass or fail against nothing and a student could
        // read a course they were never enrolled in.
        return DB::table('organization_memberships as m')
            ->join('curriculum_versions as cv', 'cv.programme_id', '=', 'm.academic_program_id')
            ->join('curriculum_courses as cc', 'cc.curriculum_version_id', '=', 'cv.id')
            ->where('m.user_id', $userId)
            ->where('m.status', 'active')
            ->where('cc.status', 'active')
            ->where('cc.course_id', $courseId)
            ->exists();
    }
}
