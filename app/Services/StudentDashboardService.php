<?php

namespace App\Services;

use App\Models\AcademicProgram;
use App\Models\Curriculum\CurriculumCourse;
use App\Models\Curriculum\Programme;
use App\Models\Enrollment;
use App\Models\OrganizationMembership;
use App\Models\Student;
use App\Models\StudentRegistrationVerification;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Data-driven student dashboard access.
 *
 * A student's programme is resolved from their institution record
 * (organization_memberships.academic_program_id), mapped onto the
 * NUC curriculum programme list by name, and courses come from the
 * active curriculum version of that programme. Nothing here trusts
 * frontend claims -- entitlements are resolved server-side only.
 */
class StudentDashboardService
{
    /**
     * The student's latest verified JAMB record, if any.
     */
    public function latestVerification(Student $student): ?StudentRegistrationVerification
    {
        return StudentRegistrationVerification::where('user_id', $student->user_id)
            ->where('status', 'verified')
            ->latest('verified_at')
            ->first();
    }

    /**
     * First active institution record for the student's user.
     */
    public function institutionRecord(Student $student): ?OrganizationMembership
    {
        return OrganizationMembership::where('user_id', $student->user_id)
            ->where('status', 'active')
            ->orderBy('joined_at', 'desc')
            ->first();
    }

    /**
     * The institution-level academic programme the student belongs to.
     */
    public function academicProgramme(Student $student): ?AcademicProgram
    {
        $record = $this->institutionRecord($student);

        return $record?->academicProgram;
    }

    /**
     * The NUC curriculum programme that matches the student's academic
     * programme.
     *
     * Resolution chain (in order):
     *  1. Institution record's academic_program_id, name-matched against
     *     the NUC programme list.
     *  2. JAMB verified programme from the latest verification, matched
     *     loosely (normalised name) so "Mass Communication" resolves to
     *     "B.Sc. Mass Communication".
     *  3. Direct identifier match for datasets with aligned ids.
     *
     * Returns null only when no linkage exists yet.
     */
    public function curriculumProgramme(Student $student): ?Programme
    {
        $academic = $this->academicProgramme($student);

        if ($academic) {
            $byName = Programme::where('status', 'active')
                ->whereRaw('LOWER(name) = ?', [mb_strtolower($academic->name)])
                ->first();

            if ($byName) {
                return $byName;
            }

            // Aligned identifiers (legacy/demo datasets).
            $byId = Programme::where('status', 'active')
                ->whereKey($academic->id)
                ->first();

            if ($byId) {
                return $byId;
            }
        }

        // JAMB verified programme, matched loosely by normalised name.
        $verification = $this->latestVerification($student);

        if ($verification?->verified_programme) {
            $needle = $this->normaliseName($verification->verified_programme);

            return Programme::where('status', 'active')
                ->get()
                ->filter(function (Programme $p) use ($needle) {
                    $haystack = $this->normaliseName($p->name);
                    // "mass communication" ─⊂─ "b sc mass communication"
                    return $needle !== ''
                        && (str_contains($haystack, $needle) || str_contains($needle, $haystack));
                })
                ->first();
        }

        return null;
    }

    /**
     * Normalise a programme name for loose comparison: lowercase, strip
     * degree prefixes and punctuation, collapse whitespace.
     */
    private function normaliseName(string $name): string
    {
        $prefixes = [
            'b.sc.',
            'b.sc',
            'b.a.',
            'b.a',
            'b.eng.',
            'b.eng',
            'b.ed.',
            'b.ed',
            'b.agr.',
            'b.agr',
            'b.mls.',
            'b.nsc.',
            'b.sc.ed.',
            'b.sc.ed',
            'b.pharm.',
            'b.pharm',
            'll.b.',
            'll.b',
            'm.b.b.s.',
            'd.v.m.',
            'b.tech.',
            'b.tech',
        ];

        $normalised = mb_strtolower(trim($name));

        foreach ($prefixes as $prefix) {
            if (str_starts_with($normalised, $prefix)) {
                $normalised = trim(substr($normalised, strlen($prefix)));
                break;
            }
        }

        // Strip punctuation and collapse whitespace.
        $normalised = preg_replace('/[^a-z0-9 ]+/u', ' ', $normalised);
        $normalised = preg_replace('/\s+/u', ' ', $normalised);

        return trim($normalised);
    }

    /**
     * Active curriculum version for a programme.
     */
    public function activeCurriculumVersion(Programme $programme)
    {
        return $programme->curriculumVersions()
            ->where('is_active', true)
            ->latest()
            ->first();
    }

    /**
     * All curriculum courses for the student's programme, one row per
     * (course, level, semester) placement, enriched with chapters,
     * outline and the first active course offering.
     *
     * Each row keeps its `level` (100/200/...) and `semester` (1/2) so
     * the views can group courses by level → semester (7–8 per semester).
     */
    public function programmeCourses(Student $student): Collection
    {
        $programme = $this->curriculumProgramme($student);

        if (! $programme) {
            return collect();
        }

        $version = $programme->curriculumVersions()
            ->where('is_active', true)
            ->latest()
            ->first();

        if (! $version) {
            return collect();
        }

        return CurriculumCourse::with([
            'course.chapters',
            'course.outlines',
            'curriculumVersion.programme',
        ])
            ->where('curriculum_version_id', $version->id)
            ->where('status', 'active')
            ->get()
            ->map(function (CurriculumCourse $cc) {
                // First active offering of the linked course, if any.
                $offering = $cc->course->offerings()
                    ->where('is_active', true)
                    ->with(['semester', 'semester.academicSession'])
                    ->first();

                $cc->current_offering = $offering;

                return $cc;
            })
            ->values();
    }

    /**
     * Group programme curriculum courses by level → semester.
     *
     * Returns a collection keyed by level whose values are collections
     * keyed by semester (1|2), each holding the semester's courses.
     */
    public function programmeCoursesBySemester(Student $student): Collection
    {
        return $this->programmeCourses($student)
            ->groupBy(fn (CurriculumCourse $cc) => $cc->level)
            ->map(function (Collection $levelCourses) {
                return $levelCourses->groupBy(fn (CurriculumCourse $cc) => $cc->semester);
            });
    }

    /**
     * Enrollments that currently grant access, newest first.
     */
    public function activeEnrollments(User $user): Collection
    {
        return $user->enrollments()
            ->with(['courseOffering.course', 'courseOffering.semester'])
            ->where('status', 'active')
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->orderByDesc('enrolled_at')
            ->get();
    }

    /**
     * Curriculum courses the user is actively enrolled in (by offering).
     */
    public function enrolledCourseIds(User $user): array
    {
        return $user->enrollments()
            ->where('status', 'active')
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->pluck('course_offering_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }
}