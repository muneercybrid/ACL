<?php

namespace App\Services\Courses;

use App\Models\CourseOffering;
use App\Models\Enrollment;
use Illuminate\Support\Facades\DB;

/**
 * Self-enrollment: the student clicks Enroll, and the enrollment is recorded.
 *
 * The "Enroll to view" control on the course page was never a working button.
 * It rendered as a disabled span because the page had nothing to enroll into:
 * there were no semesters, no course offerings, and no enroll route. A
 * student therefore could not reach content the platform already had for
 * them, which is the one journey AGENTS.md section 5 calls essential.
 *
 * The offering is resolved lazily rather than pre-seeded for every course in
 * the catalogue. Pre-seeding would mean inventing a semester and an offering
 * for thousands of courses nobody has enrolled in, and every one of those rows
 * would be a scheduling decision dressed up as a convenience. Creating it on
 * demand keeps the record honest: an offering exists because a student is in it.
 *
 * Enrollment is recorded with source 'self' so the institution dashboards can
 * distinguish a student choosing a course from one placed into it.
 */
class CourseEnrollmentService
{
    /**
     * Enrolls a student in a course, creating the semester and offering if this
     * is the first enrollment for it.
     *
     * @return array{enrollment: ?Enrollment, created: bool, message: string}
     */
    public function enroll(int $userId, int $courseId): array
    {
        $session = $this->currentSession();

        if ($session === null) {
            return ['enrollment' => null, 'created' => false, 'message' => 'No academic session is currently open for enrollment.'];
        }

        $offering = $this->resolveOffering($courseId, $session);

        if ($offering === null) {
            return ['enrollment' => null, 'created' => false, 'message' => 'This course cannot be offered right now.'];
        }

        $existing = $this->activeEnrollment($userId, (int) $offering->id);

        if ($existing !== null) {
            return ['enrollment' => $existing, 'created' => false, 'message' => 'You are already enrolled in this course.'];
        }

        // A student who re-enrolled after completing should get their earlier
        // record back rather than a second parallel row, so a non-active
        // enrollment is reopened instead of duplicated.
        $reopened = Enrollment::where('user_id', $userId)
            ->where('course_offering_id', $offering->id)
            ->exists();

        $enrollment = Enrollment::create([
            'user_id' => $userId,
            'course_offering_id' => $offering->id,
            'source' => 'self',
            'status' => 'active',
            'enrolled_at' => now(),
        ]);

        return [
            'enrollment' => $enrollment,
            'created' => ! $reopened,
            'message' => 'Enrolled. The course content is now available to you.',
        ];
    }

    public function activeEnrollment(int $userId, int $offeringId): ?Enrollment
    {
        return Enrollment::where('user_id', $userId)
            ->where('course_offering_id', $offeringId)
            ->where('status', 'active')
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->first();
    }

    /**
     * Finds the offering for a course in the current session, creating the
     * semester and the offering the first time a student needs them.
     */
    protected function resolveOffering(int $courseId, object $session): ?CourseOffering
    {
        $existing = CourseOffering::where('course_id', $courseId)
            ->whereIn('semester_id', DB::table('semesters')->where('academic_session_id', $session->id)->pluck('id'))
            ->where('is_active', true)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        $semester = $this->currentSemester($session);

        if ($semester === null) {
            return null;
        }

        return CourseOffering::create([
            'course_id' => $courseId,
            'semester_id' => $semester->id,
            'is_active' => true,
        ]);
    }

    /**
     * The current session's first active semester, created if the session has
     * none. Only one is created: a second semester is a scheduling decision
     * for the institution, not something enrollment should decide.
     */
    protected function currentSemester(object $session): ?object
    {
        $existing = DB::table('semesters')
            ->where('academic_session_id', $session->id)
            ->where('is_active', true)
            ->orderBy('id')
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        $id = DB::table('semesters')->insertGetId([
            'academic_session_id' => $session->id,
            'name' => 'First Semester',
            'slug' => 'first-semester',
            'start_date' => $session->start_date,
            'end_date' => $session->end_date,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return DB::table('semesters')->where('id', $id)->first();
    }

    protected function currentSession(): ?object
    {
        return DB::table('academic_sessions')
            ->where('is_current', true)
            ->orWhere('is_active', true)
            ->orderByDesc('is_current')
            ->orderByDesc('id')
            ->first();
    }
}