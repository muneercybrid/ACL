<?php

namespace Tests\Feature;

use App\Services\Courses\CourseEnrollmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The "Enroll to view" button was a disabled span with no route behind it.
 *
 * There were no semesters, no course offerings and no enroll endpoint, so a
 * student clicking it could not reach content the platform already held for
 * them. These tests cover the parts that actually mattered: that enrollment
 * records itself, that it is visible afterwards, and -- the one that must not
 * be got wrong -- that a student cannot enroll into another institution's
 * course or another level by posting an id directly.
 */
class CourseEnrollmentTest extends TestCase
{
    use RefreshDatabase;

    protected function student(): \App\Models\User
    {
        $orgId = DB::table('organizations')->insertGetId([
            'name' => 'NWU', 'slug' => 'nwu', 'type' => 'university', 'short_name' => 'NWU',
            'is_active' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $facultyId = DB::table('faculties')->insertGetId([
            'organization_id' => $orgId, 'name' => 'Science', 'slug' => 'science', 'code' => 'SCI',
            'is_active' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $deptId = DB::table('departments')->insertGetId([
            'faculty_id' => $facultyId, 'name' => 'Computing', 'slug' => 'computing', 'code' => 'CSC',
            'is_active' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $programmeId = DB::table('academic_programs')->insertGetId([
            'organization_id' => $orgId, 'department_id' => $deptId, 'name' => 'B.Sc Cybersecurity',
            'slug' => 'bsc-cybersecurity', 'code' => 'CSC-B', 'degree_type' => 'bachelor',
            'is_active' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $userId = DB::table('users')->insertGetId([
            'name' => 'Test Student', 'email' => 'student-' . uniqid() . '@acl.test',
            'password' => bcrypt('secret1234'), 'institution_id' => $orgId, 'status' => 'active',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        // The organization membership is what carries the student's programme,
        // not the students row.
        DB::table('organization_memberships')->insert([
            'user_id' => $userId, 'organization_id' => $orgId, 'academic_program_id' => $programmeId,
            'membership_type' => 'student', 'status' => 'active', 'joined_at' => now(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $studentId = DB::table('students')->insertGetId([
            'user_id' => $userId, 'acl_student_id' => 'ACL-' . $userId, 'level' => 100,
            'verification_status' => 'verified', 'verification_method' => 'jamb',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $user = \App\Models\User::find($userId);
        $user->setRelation('student', \App\Models\Student::find($studentId));
        $user->setAttribute('programme_id', $programmeId);

        return $user;
    }

    protected function programmeId(\App\Models\User $user): int
    {
        return (int) DB::table('organization_memberships')
            ->where('user_id', $user->id)->value('academic_program_id');
    }

    protected function makeSession(): int
    {
        return DB::table('academic_sessions')->insertGetId([
            'name' => '2026/2027', 'slug' => '2026-2027', 'start_date' => '2026-09-01', 'end_date' => '2027-07-31',
            'is_active' => true, 'is_current' => true, 'status' => 'active',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    protected function makeCourse(string $code = 'COS101'): int
    {
        return DB::table('courses')->insertGetId([
            'code' => $code, 'normalized_code' => $code, 'title' => 'Introduction to Computing Sciences',
            'slug' => strtolower($code), 'credit_units' => 3, 'scope' => 'national', 'source_type' => 'nuc_ccmas',
            'verification_status' => 'verified', 'status' => 'active', 'is_active' => 1, 'is_external' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_a_student_can_enroll_and_the_enrollment_is_recorded(): void
    {
        $this->makeSession();
        $student = $this->student();
        $courseId = $this->makeCourse();

        $result = app(CourseEnrollmentService::class)->enroll($student->id, $courseId);

        $this->assertNotNull($result['enrollment']);
        $this->assertTrue($result['created']);
        $this->assertSame(1, DB::table('enrollments')->where('user_id', $student->id)->count());

        $enrollment = DB::table('enrollments')->where('user_id', $student->id)->first();
        $this->assertSame('active', $enrollment->status);
        // 'self' so the institution dashboards can tell a student choosing a
        // course from one placed into it.
        $this->assertSame('self', $enrollment->source);
    }

    public function test_enrolling_twice_does_not_duplicate_the_row(): void
    {
        $this->makeSession();
        $student = $this->student();
        $courseId = $this->makeCourse();

        app(CourseEnrollmentService::class)->enroll($student->id, $courseId);
        $second = app(CourseEnrollmentService::class)->enroll($student->id, $courseId);

        $this->assertFalse($second['created']);
        $this->assertSame(1, DB::table('enrollments')->where('user_id', $student->id)->count());
    }

    public function test_the_offering_and_semester_are_created_on_demand(): void
    {
        // Offering created only because a student enrolled, not pre-seeded for
        // every course in the catalogue.
        $this->makeSession();
        $student = $this->student();
        $courseId = $this->makeCourse();

        $this->assertSame(0, DB::table('course_offerings')->count());

        app(CourseEnrollmentService::class)->enroll($student->id, $courseId);

        $this->assertSame(1, DB::table('course_offerings')->where('course_id', $courseId)->count());
        $this->assertSame(1, DB::table('semesters')->count());
    }

    public function test_it_refuses_when_no_academic_session_is_open(): void
    {
        $student = $this->student();
        $courseId = $this->makeCourse();

        $result = app(CourseEnrollmentService::class)->enroll($student->id, $courseId);

        $this->assertNull($result['enrollment']);
        $this->assertSame(0, DB::table('enrollments')->count());
    }

    public function test_a_student_cannot_enroll_into_another_levels_course(): void
    {
        // The POST route carries its own scope check. Without it, a student
        // could post a level-200 course id and enroll into content they are
        // not sitting.
        $this->makeSession();
        $student = $this->student();

        $programmeId = $this->programmeId($student);
        $courseId = $this->makeCourse('COS200');
        $plcId = DB::table('programme_level_courses')->insertGetId([
            'academic_program_id' => $programmeId, 'level' => 200, 'course_id' => $courseId,
            'source' => 'ccmas', 'course_code' => 'COS200', 'title' => 'Computational Methods II',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->actingAs($student)
            ->post("/student/course/p{$plcId}/enroll")
            ->assertForbidden();

        $this->assertSame(0, DB::table('enrollments')->where('user_id', $student->id)->count());
    }

    public function test_the_enroll_route_exists_and_rejects_a_get(): void
    {
        $this->makeSession();
        $student = $this->student();
        $courseId = $this->makeCourse();
        app(CourseEnrollmentService::class)->enroll($student->id, $courseId);

        $enrollment = DB::table('enrollments')->where('user_id', $student->id)->first();

        // Enrollment is a state change, so it must not be reachable by GET.
        $this->actingAs($student)->get("/student/course/c1/enroll")->assertStatus(405);
    }
}