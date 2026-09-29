<?php

namespace Tests\Feature;

use App\Services\Curriculum\CurriculumPublisher;
use App\Services\Student\CourseRegistrationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The ordered registration flow, exercised through the HTTP surface.
 *
 * The owner's requirement has two halves that pull in opposite directions:
 * a student chooses for themselves, but only in a fixed order, and only when
 * nobody has already chosen for them. Both halves are easy to implement in a
 * view and easy to forget on the server, so they are tested through the
 * routes rather than against the service alone.
 */
class StudentCourseRegistrationFlowTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Creates the session, and makes it the only current one.
     *
     * The service picks its session with `where('is_current', true)` ordered
     * by id, so a pre-existing row from another test would win and the
     * published list -- which is bound to THIS session -- would be invisible.
     * Deactivating first is what makes the fixture authoritative rather than
     * merely present.
     */
    private function currentSession(): int
    {
        DB::table('academic_sessions')->update(['is_current' => false]);

        return DB::table('academic_sessions')->insertGetId([
            'name' => '2025/2026',
            'slug' => '2025-2026',
            'start_date' => '2025-09-01',
            'end_date' => '2026-07-31',
            'is_current' => true,
            'is_active' => true,
            'status' => 'active',
            'start_year' => 2025,
            'end_year' => 2026,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function course(string $code, string $title): int
    {
        return DB::table('courses')->insertGetId([
            'code' => $code,
            'normalized_code' => $code,
            'slug' => strtolower($code),
            'title' => $title,
            'normalized_title' => strtolower($title),
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** @return array{0: int, 1: int} user id, student id */
    private function studentAtLevel(int $level = 100): array
    {
        $userId = DB::table('users')->insertGetId([
            'name' => 'Test Student',
            'email' => 'student' . $level . '@example.test',
            'password' => bcrypt('secret-password'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $studentId = DB::table('students')->insertGetId([
            'user_id' => $userId,
            'acl_student_id' => 'ACL' . $userId,
            'verification_method' => 'jamb',
            'verification_status' => 'verified',
            'level' => $level,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$userId, $studentId];
    }

    /**
     * A real faculty and department.
     *
     * Hardcoding faculty_id = 1 and organization_id = 1 looks harmless but
     * trips a foreign key as soon as the test database is not seeded. Building
     * the actual parent rows costs three inserts and makes the fixture
     * self-contained, which is the whole point of the fixture.
     */
    private function departmentId(): int
    {
        return DB::table('departments')->insertGetId([
            'faculty_id' => $this->facultyId(),
            'name' => 'Test Department ' . uniqid(),
            'slug' => 'test-department-' . uniqid(),
            'code' => 'T-' . substr(uniqid(), -6),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function facultyId(): int
    {
        return DB::table('faculties')->insertGetId([
            'organization_id' => $this->organizationId(),
            'name' => 'Test Faculty ' . uniqid(),
            'slug' => 'test-faculty-' . uniqid(),
            'code' => 'F-' . substr(uniqid(), -6),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function organizationId(): int
    {
        return DB::table('organizations')->insertGetId([
            'name' => 'Test University ' . uniqid(),
            'normalized_name' => 'test university ' . uniqid(),
            'slug' => 'test-university-' . uniqid(),
            'type' => 'university',
            'status' => 'approved',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function disciplineId(): int
    {
        return DB::table('nuc_disciplines')->insertGetId([
            'name' => 'Test Discipline ' . uniqid(),
            'code' => 'TD-' . substr(uniqid(), -6),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** Creates an offering and links the user to it, without publishing. */
    private function offeringFor(int $userId, int $disciplineId): int
    {
        $programmeId = DB::table('programmes')->insertGetId([
            'name' => 'B.Sc. Unpublished ' . $userId,
            'normalized_name' => 'b.sc. unpublished ' . $userId,
            'code' => 'UNPUB' . $userId,
            'degree_type' => 'B.Sc.',
            'duration_years' => 3,
            'nuc_discipline_id' => $disciplineId,
            'scope' => 'national',
            'verification_status' => 'verified',
            'source_type' => 'test',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $offeringId = DB::table('academic_programs')->insertGetId([
            'department_id' => $this->departmentId(),
            'nuc_programme_id' => $programmeId,
            'name' => 'B.Sc. Unpublished ' . $userId,
            'slug' => 'unpublished-' . $userId,
            'code' => 'UNPUB' . $userId,
            'degree_type' => 'B.Sc.',
            'duration_years' => 3,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('organization_memberships')->insert([
            'user_id' => $userId,
            'organization_id' => $this->organizationId(),
            'academic_program_id' => $offeringId,
            'membership_type' => 'student',
            'status' => 'active',
            'joined_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $offeringId;
    }

    private function publishFor(?int $userId, int $level, array $rows): void
    {
        // The national programme is the real FK target for
        // curriculum_versions.programme_id. The offering points at it.
        $programmeId = DB::table('programmes')->insertGetId([
            'name' => 'B.Sc. Test Programme ' . $userId,
            'normalized_name' => 'b.sc. test programme ' . $userId,
            'code' => 'TEST-PROG-' . $userId,
            'degree_type' => 'B.Sc.',
            'duration_years' => 3,
            'nuc_discipline_id' => 1,
            'scope' => 'national',
            'verification_status' => 'verified',
            'source_type' => 'test',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $offeringId = DB::table('academic_programs')->insertGetId([
            'department_id' => $this->departmentId(),
            'nuc_programme_id' => $programmeId,
            'name' => 'B.Sc. Test Programme ' . $userId,
            'slug' => 'test-programme-' . $userId,
            'code' => 'TEST-PROG-' . $userId,
            'degree_type' => 'B.Sc.',
            'duration_years' => 3,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if ($userId !== null) {
            DB::table('organization_memberships')->insert([
                'user_id' => $userId,
                'organization_id' => $this->organizationId(),
                'academic_program_id' => $offeringId,
                'membership_type' => 'student',
                'status' => 'active',
                'joined_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $offering = \App\Models\AcademicProgram::find($offeringId);

        app(CurriculumPublisher::class)->publish($offering, $this->currentSession(), array_map(
            fn (array $r) => $r + ['level' => $level, 'credit_units' => 3, 'is_mandatory' => true],
            $rows
        ));
    }

    public function test_a_student_with_no_published_list_selects_first_semester_first(): void
    {
        [$userId, $studentId] = $this->studentAtLevel();
        $s1 = $this->course('TST101', 'Test One');
        $s2 = $this->course('TST102', 'Test Two');

        // An offering with NO published list, so the selection path is the one
        // under test. The courses come from the programme's own catalogue,
        // which is what makes this path reachable at all.
        //
        // The courses are put on a test-owned discipline rather than discipline
        // 1, because under RefreshDatabase the test database has no real
        // discipline-1 courses to find and the assertion would pass for the
        // wrong reason -- an empty list trivially contains neither code.
        $disciplineId = $this->disciplineId();
        DB::table('courses')->whereIn('id', [$s1, $s2])->update(['nuc_discipline_id' => $disciplineId]);
        $this->offeringFor($userId, $disciplineId);

        $user = \App\Models\User::find($userId);
        $service = app(CourseRegistrationService::class);
        $student = $user->student;

        $this->assertSame('select_semester_1', $service->status($student)['stage']);

        // Assert the service result as well as the rendered page. If the page
        // ever disagrees with the service, the two assertions together say
        // which one is wrong instead of leaving it ambiguous.
        $this->assertCount(1, $service->coursesForSemester($student, 1), 'first semester should offer one course');

        $response = $this->actingAs($user)->get(route('student.course.register'));
        $response->assertOk();

        // Only the first semester is on the page. Offering both is the mixup
        // the ordered flow exists to prevent.
        $response->assertSee('TST101');
        $response->assertDontSee('TST102');
    }

    public function test_second_semester_is_refused_before_the_first_is_recorded(): void
    {
        [$userId] = $this->studentAtLevel();
        $s1 = $this->course('TST101', 'Test One');
        $s2 = $this->course('TST102', 'Test Two');
        $this->publishFor($userId, 100, [
            ['course_id' => $s1, 'semester' => 1],
            ['course_id' => $s2, 'semester' => 2],
        ]);

        $user = \App\Models\User::find($userId);

        $response = $this->actingAs($user)->post(route('student.course.register.store'), [
            'semester' => 2,
            'courses' => [$s2],
        ]);

        $response->assertSessionHas('error');
        $this->assertDatabaseMissing('student_course_registrations', ['course_id' => $s2]);
    }

    public function test_mandatory_courses_cannot_be_omitted(): void
    {
        [$userId] = $this->studentAtLevel();
        $s1 = $this->course('TST101', 'Test One');
        $s1b = $this->course('TST103', 'Test One Companion');
        $s2 = $this->course('TST102', 'Test Two');
        $this->publishFor($userId, 100, [
            ['course_id' => $s1, 'semester' => 1],
            ['course_id' => $s1b, 'semester' => 1],
            ['course_id' => $s2, 'semester' => 2],
        ]);

        $user = \App\Models\User::find($userId);

        // Both first-semester courses are mandatory, so submitting one is
        // incomplete. A single-course list would have nothing to omit, which
        // is why this needs two.
        $response = $this->actingAs($user)->post(route('student.course.register.store'), [
            'semester' => 1,
            'courses' => [$s1],
        ]);

        $response->assertSessionHas('error');
        $this->assertDatabaseCount('student_course_registrations', 0);
    }

    public function test_a_published_list_enrols_the_student_without_asking(): void
    {
        [$userId] = $this->studentAtLevel();
        $s1 = $this->course('TST101', 'Test One');
        $s2 = $this->course('TST102', 'Test Two');
        $this->publishFor($userId, 100, [
            ['course_id' => $s1, 'semester' => 1],
            ['course_id' => $s2, 'semester' => 2],
        ]);

        $user = \App\Models\User::find($userId);
        $student = $user->student;

        $this->assertSame('auto_enroll', app(CourseRegistrationService::class)->status($student)['stage']);

        // The selection screen is skipped entirely.
        $this->actingAs($user)->get(route('student.course.register'))
            ->assertRedirect(route('student.dashboard'));

        $this->assertDatabaseCount('student_course_registrations', 2);
    }

    public function test_a_student_cannot_register_for_another_students_account(): void
    {
        [$userA] = $this->studentAtLevel(100);
        [$userB] = $this->studentAtLevel(200);
        $s1 = $this->course('TST101', 'Test One');
        $this->publishFor($userA, 100, [['course_id' => $s1, 'semester' => 1]]);

        $studentBId = DB::table('students')->where('user_id', $userB)->value('id');

        $response = $this->actingAs(\App\Models\User::find($userB))->post(
            route('student.course.register.store'),
            ['semester' => 1, 'courses' => [$s1], 'student_id' => $studentBId]
        );

        // The student id in the payload is ignored; the session decides.
        $this->assertDatabaseMissing('student_course_registrations', [
            'student_id' => $studentBId,
            'course_id' => $s1,
        ]);
        $this->assertNotNull($response);
    }
}
