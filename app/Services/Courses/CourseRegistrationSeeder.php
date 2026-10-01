<?php

namespace App\Services\Courses;

use App\Models\Course;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Loads a course registration form into a programme's level.
 *
 * The project's owner confirmed that "course registration completed" describes
 * the curriculum list for a programme level being finalized -- not a student
 * having passed anything. That distinction is the whole reason this service
 * writes no grades, no results and no completions. It writes courses, and it
 * records whether that list has been verified against a document yet.
 *
 * Three things it is careful about:
 *
 * Verified only. A course is only marked verified when it is found in the CCMAS
 * corpus, matched on normalized code so that "GST111" on a form and "GST 111"
 * in the import are recognized as one course. Institution-added courses such as
 * NUK-CYB101 are recorded but stay unverified, because the owner supplies those
 * documents separately; showing them is fine, claiming they are verified is not.
 *
 * Semester placement is not inferred when a document states it. The parity
 * convention is a good default and is used where the form is silent, but where
 * the form places a course, the form wins. MTH 103 sits in second semester on
 * the Northwest University Kano form despite a trailing 3; that placement is
 * kept and the divergence is reported rather than overwritten.
 *
 * Credit units are informational. The owner is explicit that ACL is not bound by
 * NUC credit rules, so a unit that differs from CCMAS is recorded and never
 * treated as an error.
 */
class CourseRegistrationSeeder
{
    public function __construct(protected CourseCodeParser $parser) {}

    /**
     * Load a registration form.
     *
     * @param  array<int, array{semester: int, code: string, title: string, credit_units: int|float|null}>  $courses
     * @return array{programme_id: int, inserted: int, skipped: int, verified: int, unverified: array, semester_conflicts: array, unit_differences: array}
     */
    public function load(
        int $organizationId,
        int $programmeId,
        int $level,
        array $courses,
        ?int $completedBy = null,
    ): array {
        $inserted = 0;
        $skipped = 0;
        $verified = 0;
        $unverified = [];
        $conflicts = [];
        $unitDifferences = [];

        foreach ($courses as $entry) {
            $semester = (int) ($entry['semester'] ?? 0);
            $code = trim((string) ($entry['code'] ?? ''));
            $title = trim((string) ($entry['title'] ?? ''));
            $units = $entry['credit_units'] ?? null;

            if ($code === '' || $title === '' || $semester < 1 || $semester > 2) {
                $skipped++;

                continue;
            }

            $normalized = $this->parser->normalize($code);
            $resolution = $this->parser->resolveSemester($code, $semester);

            if ($resolution['conflicts_with_parity']) {
                $conflicts[] = [
                    'code' => $normalized,
                    'documented_semester' => $semester,
                    'parity_semester' => $resolution['parity_predicted'],
                ];
            }

            $match = $this->findCcmasCourse($normalized, $code);

            if ($match !== null) {
                $verified++;
            } else {
                $unverified[] = ['code' => $normalized, 'title' => $title];
            }

            if ($match !== null && $units !== null && $match->credit_units !== null
                && (float) $units !== (float) $match->credit_units) {
                // Recorded, never treated as an error: the owner does not follow
                // NUC credit rules, so a difference is information rather than a
                // failure.
                $unitDifferences[] = [
                    'code' => $normalized,
                    'form_units' => $units,
                    'ccmas_units' => $match->credit_units,
                ];
            }

            $course = $this->resolveCourse($match, $normalized, $title, $units, $level);

            DB::table('programme_level_courses')->updateOrInsert(
                [
                    'academic_program_id' => $programmeId,
                    'level' => $level,
                    'course_code' => $normalized,
                ],
                [
                    'course_id' => $course->id,
                    'title' => $title,
                    'semester' => (string) $semester,
                    'credit_units' => $units,
                    'ccmas_course_id' => $match->id ?? null,
                    'source' => $match !== null ? 'ccmas' : 'institution',
                    'is_mandatory' => 1,
                    'created_by' => $completedBy,
                    'updated_by' => $completedBy,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );

            $inserted++;
        }

        return [
            'programme_id' => $programmeId,
            'inserted' => $inserted,
            'skipped' => $skipped,
            'verified' => $verified,
            'unverified' => $unverified,
            'semester_conflicts' => $conflicts,
            'unit_differences' => $unitDifferences,
        ];
    }

    /**
     * Find the CCMAS row for a code, tolerating the spacing difference between
     * the import ("GST 111") and a registration form ("GST111").
     */
    protected function findCcmasCourse(?string $normalized, string $rawCode): ?object
    {
        if ($normalized === null) {
            return null;
        }

        $exact = DB::table('ccmas_courses')
            ->where('course_code', $rawCode)
            ->first();

        if ($exact !== null) {
            return $exact;
        }

        // Fall back to a normalized scan when the raw spelling is absent.
        return DB::table('ccmas_courses')
            ->get()
            ->first(fn ($row) => $this->parser->normalize($row->course_code) === $normalized);
    }

    /**
     * Find or create the canonical course row a programme_level_courses entry
     * points at. A shared course is one row reused across programmes; that is
     * what keeps "Communication in English" from being re-entered 177 times.
     *
     * An existing row is reused, but reused rows still get linked to the CCMAS
     * record they came from. The corpus import leaves courses unverified because
     * it has no evidence they are offered by any particular programme; a
     * registration form is that evidence. Without this, a course confirmed
     * against CCMAS stays marked unverified forever while the programme entry
     * beside it says ccmas -- the same disagreement this method exists to
     * remove, just moved a column across.
     */
    protected function resolveCourse(?object $ccmas, string $normalized, string $title, $units, int $level): Course
    {
        $existing = Course::query()->where('normalized_code', $normalized)->first();

        if ($existing !== null) {
            if ($ccmas !== null) {
                $existing->ccmas_course_id = $existing->ccmas_course_id ?? $ccmas->id;
                $existing->source_type = 'nuc_ccmas';
                $existing->scope = $existing->scope ?: 'national';
                $existing->verification_status = 'verified';
                $existing->source_document = $existing->source_document ?? ($ccmas->source_document ?? null);
                $existing->save();
            }

            return $existing;
        }

        $course = new Course;
        $course->code = $normalized;
        $course->normalized_code = $normalized;
        $course->title = $title;
        $course->slug = Str::slug($normalized.'-'.$title);
        $course->credit_units = (int) ($units ?? $ccmas->credit_units ?? 1);
        $course->scope = $ccmas !== null ? 'national' : 'university';
        $course->source_type = $ccmas !== null ? 'ccmas' : 'institution';
        $course->verification_status = $ccmas !== null ? 'verified' : 'unverified';
        $course->status = 'active';
        $course->is_active = true;
        $course->is_external = false;
        $course->ccmas_course_id = $ccmas->id ?? null;
        $course->source_document = $ccmas->source_document ?? null;
        $course->save();

        return $course;
    }
}
