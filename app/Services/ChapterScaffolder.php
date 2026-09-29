<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Creates empty chapter placeholders for a course.
 *
 * The catalogue needs enough chapters to carry a course from its first
 * principle to its end goal, which is dozens per course. Those are created
 * empty; the content is filled in later, per chapter, by a coordinator using
 * ACLi, a paste, or an uploaded resource.
 *
 * Placeholders are marked with `placeholder = true` and carry no body. That
 * matters: an unmarked empty chapter would be indistinguishable from content
 * that was written and then lost, and the two need opposite handling -- one is
 * a task waiting to be done, the other is a bug.
 */
class ChapterScaffolder
{
    /**
     * The default chapter arc for a course.
     *
     * This is a standard higher-education structure, not course content: a
     * course opens with orientation, develops the core concepts, applies them,
     * and closes with synthesis and review. It is deliberately generic because
     * these are placeholders -- the real titles come from the course's CCMAS
     * outline when someone fills the chapter in.
     *
     * @return array<int, string>
     */
    public function defaultArc(int $count): array
    {
        $arc = [
            'Course Orientation and Learning Outcomes',
            'Prerequisites and Prior Knowledge',
            'Core Concepts and Definitions',
            'Theoretical Foundations',
            'Key Principles in Practice',
            'Methods and Techniques',
            'Tools, Materials and Resources',
            'Worked Examples',
            'Common Errors and Pitfalls',
            'Case Studies and Applications',
            'Practical Skills and Procedures',
            'Data, Evidence and Analysis',
            'Contemporary Issues and Developments',
            'Ethical and Professional Considerations',
            'Technology in the Field',
            'Comparison and Contrast of Approaches',
            'Problem-Solving Exercises',
            'Critical Analysis',
            'Research Methods and Literature',
            'Semester Project or Assignment',
            'Revision and Consolidation',
            'Practice Questions and Solutions',
            'Summary of Key Learning Points',
            'Assessment and Examination Guidance',
        ];

        if ($count <= count($arc)) {
            return array_slice($arc, 0, $count);
        }

        // Pad rather than duplicate: repeating a title in one course looks like
        // a bug and makes the chapter list useless to navigate.
        $out = $arc;
        $part = 2;

        while (count($out) < $count) {
            foreach ($arc as $title) {
                if (count($out) >= $count) {
                    break;
                }
                $out[] = $title . ' (Part ' . $part . ')';
            }
            $part++;
        }

        return $out;
    }

    /**
     * @return array{courses: int, created: int, skipped: int}
     */
    public function scaffold(int $perCourse, int $limit, int $offset, int $chunk): array
    {
        $arc = $this->defaultArc($perCourse);

        $created = 0;
        $skipped = 0;
        $coursesDone = 0;
        $lastId = 0;

        if ($offset > 0) {
            $lastId = (int) DB::table('courses')
                ->orderBy('id')
                ->offset($offset - 1)
                ->limit(1)
                ->value('id');
        }

        while (true) {
            $query = DB::table('courses')
                ->select('id')
                ->where('id', '>', $lastId)
                ->orderBy('id')
                ->limit($chunk);

            if ($limit > 0) {
                $query->limit(min($chunk, $limit - $coursesDone));
            }

            $courses = $query->get();

            if ($courses->isEmpty()) {
                break;
            }

            $rows = [];

            foreach ($courses as $course) {
                $lastId = (int) $course->id;
                $coursesDone++;

                // Two separate checks, and both are needed:
                // - slug: re-running must not duplicate a chapter
                // - (course_id, position): the unique constraint rejects it,
                //   and it aborts the whole run on the first collision if it is
                //   not caught first. The slug check alone is not enough, since
                //   two different titles can produce the same slug.
                $highest = DB::table('course_chapters')
                    ->where('course_id', $course->id)
                    ->max('position');

                $existing = DB::table('course_chapters')
                    ->where('course_id', $course->id)
                    ->pluck('slug')
                    ->flip()
                    ->all();

                $position = (int) $highest;

                foreach ($arc as $title) {
                    $position++;
                    $slug = Str::slug($title);

                    if (isset($existing[$slug])) {
                        $skipped++;
                        continue;
                    }

                    $rows[] = [
                        'course_id' => $course->id,
                        'position' => $position,
                        'title' => $title,
                        'slug' => $slug,
                        // No body. A placeholder is a slot, not a chapter.
                        'introduction' => null,
                        'summary' => null,
                        'key_takeaways' => null,
                        'further_reading' => null,
                        'status' => 'draft',
                        'placeholder' => true,
                        'version' => 1,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
            }

            if ($rows !== []) {
                foreach (array_chunk($rows, 100) as $batch) {
                    DB::table('course_chapters')->insert($batch);
                    $created += count($batch);
                }
            }

            if ($limit > 0 && $coursesDone >= $limit) {
                break;
            }
        }

        return ['courses' => $coursesDone, 'created' => $created, 'skipped' => $skipped];
    }
}
