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
     * programme. Matches by exact programme name first, then falls back
     * to a direct id match for data that uses aligned identifiers.
     */
    public function curriculumProgramme(Student $student): ?Programme
    {
        $academic = $this->academicProgramme($student);

        if (! $academic) {
            return null;
        }

        $byName = Programme::where('status', 'active')
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($academic->name)])
            ->first();

        if ($byName) {
            return $byName;
        }

        // Fallback: aligned identifiers (legacy/demo datasets).
        return Programme::where('status', 'active')
            ->whereKey($academic->id)
            ->first();
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
     * distinct course, enriched with chapters, outline and the first
     * active course offering.
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
            ->groupBy('course_id')
            ->map(function ($items) {
                $first = $items->first();

                // First active offering of the linked course, if any.
                $offering = $first->course->offerings()
                    ->where('is_active', true)
                    ->with(['semester', 'semester.academicSession'])
                    ->first();

                $first->current_offering = $offering;

                return $first;
            })
            ->values();
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