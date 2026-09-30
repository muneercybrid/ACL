<?php

namespace Tests\Feature;

use App\Models\AcademicProgram;
use App\Models\Curriculum\CcmasCourse;
use App\Models\Curriculum\Programme;
use App\Models\LevelCoordinator;
use App\Models\Organization;
use App\Models\ProgrammeLevelCourse;
use App\Models\Role;
use App\Models\RoleAssignment;
use App\Models\User;
use App\Services\Auth\LevelCoordinatorScope;
use App\Services\Auth\RoleHomeResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A level coordinator choosing courses for their programme and level.
 *
 * The tests that matter are the scope ones. A coordinator's authority is one
 * school, one programme, one level, and every id in the form is
 * attacker-controlled. The feature rests on those ids being re-checked against
 * the appointment rather than trusted, so each way of reaching past it has a
 * test of its own.
 */
class CoordinatorCourseSelectionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * An organization, a curriculum programme and the school offering that
     * links them — the chain a coordinator's appointment is matched against.
     */
    private function offering(string $schoolName, string $programmeName = 'B.Sc Cybersecurity'): array
    {
        $organization = Organization::create([
            'name' => $schoolName, 'slug' => str($schoolName)->slug(), 'is_active' => true,
        ]);

        $programme = Programme::create([
            'name' => $programmeName, 'code' => 'CYB', 'duration_years' => 4,
        ]);

        // academic_programs.department_id is NOT NULL, and a department in turn
        // needs a faculty, so the whole chain is built rather than skipped.
        $faculty = \App\Models\Faculty::create([
            'organization_id' => $organization->id,
            'name' => 'Faculty of Science',
            'slug' => str($schoolName.'-science')->slug(),
            'is_active' => true,
        ]);

        $department = \App\Models\Department::create([
            'faculty_id' => $faculty->id,
            'name' => 'Department of Computer Science',
            'slug' => str($schoolName.'-cs')->slug(),
            'is_active' => true,
        ]);

        $academicProgram = AcademicProgram::create([
            'name' => $programmeName,
            'slug' => str($schoolName.'-'.$programmeName)->slug(),
            'organization_id' => $organization->id,
            'nuc_programme_id' => $programme->id,
            'department_id' => $department->id,
            'is_active' => true,
        ]);

        return [$organization, $programme, $academicProgram];
    }

    /**
     * A signed-in coordinator appointed to one offering at one level.
     */
    private function coordinator(array $offering, int $level = 100): User
    {
        [$organization, $programme] = $offering;

        $user = User::factory()->create(['must_complete_onboarding' => false]);

        RoleAssignment::create([
            'user_id' => $user->id,
            'role_id' => Role::where('slug', RoleHomeResolver::ROLE_LEVEL_COORDINATOR)->value('id'),
            'entity_type' => Organization::class,
            'entity_id' => $organization->id,
        ]);

        LevelCoordinator::create([
            'organization_id' => $organization->id,
            'programme_id' => $programme->id,
            'level' => $level,
            'user_id' => $user->id,
            'status' => 'active',
            'appointed_date' => now()->toDateString(),
        ]);

        return $user->fresh();
    }

    private function central(string $title = 'Introduction to Computing', string $code = 'CSC 101'): \App\Models\Course
    {
        return \App\Models\Course::create([
            'code' => $code,
            'title' => $title,
            'slug' => str($code)->slug(),
            'normalized_code' => strtoupper($code),
            'normalized_title' => mb_strtolower($title),
            'credit_units' => 3,
            'scope' => 'university',
            'status' => 'active',
            'is_active' => true,
        ]);
    }

    private function ccmas(string $code = 'COS 101', string $title = 'Introduction to Computing Sciences'): CcmasCourse
    {
        return CcmasCourse::create([
            'source_document' => 'NUC CCMAS 2023',
            'source_file' => 'computing.txt',
            'source_line' => 1,
            'discipline_code' => 'CSC',
            'course_code' => $code,
            'title' => $title,
            'credit_units' => 3,
            'level' => 100,
            'is_active' => true,
        ]);
    }

    public function test_a_coordinator_sees_the_offerings_they_are_appointed_to(): void
    {
        $user = $this->coordinator($this->offering('Bayero University Kano'));

        $this->actingAs($user)->get(route('coordinator.courses.index'))
            ->assertOk()
            ->assertSee('B.Sc Cybersecurity');
    }

    public function test_an_account_with_no_coordinator_role_is_refused(): void
    {
        $this->offering('Bayero University Kano');

        $stranger = User::factory()->create(['must_complete_onboarding' => false]);

        $this->actingAs($stranger)
            ->get(route('coordinator.courses.index'))
            ->assertForbidden();
    }

    public function test_a_shared_course_can_be_added_with_the_schools_own_code(): void
    {
        $offering = $this->offering('Bayero University Kano');
        $user = $this->coordinator($offering);

        // A course that already exists centrally, perhaps written by another
        // school. The coordinator picks it and gives it their own code.
        $shared = app(\App\Services\Courses\CentralCourseService::class)
            ->centralForCcmas($this->ccmas());

        $this->actingAs($user)->post(route('coordinator.courses.store.shared'), [
            'academic_program_id' => $offering[2]->id,
            'level' => 100,
            'course_id' => $shared->id,
            'course_code' => 'BAY-CYB101',
        ])->assertRedirect();

        $this->assertDatabaseHas('programme_level_courses', [
            'academic_program_id' => $offering[2]->id,
            'level' => 100,
            'course_id' => $shared->id,
            'course_code' => 'BAY-CYB101',
        ]);

        // The code is registered against the shared course, so the school owns
        // the name without owning the content.
        $this->assertDatabaseHas('organization_course_codes', [
            'organization_id' => $offering[0]->id,
            'course_id' => $shared->id,
            'local_code' => 'BAY-CYB101',
        ]);
    }

    public function test_a_course_can_be_entered_by_hand_and_becomes_central(): void
    {
        $offering = $this->offering('Bayero University Kano');
        $user = $this->coordinator($offering);

        $this->actingAs($user)->post(route('coordinator.courses.store.manual'), [
            'academic_program_id' => $offering[2]->id,
            'level' => 100,
            'course_code' => 'BKC 101',
            'title' => 'Local Studies',
            'credit_units' => 3,
        ])->assertRedirect();

        $this->assertDatabaseHas('programme_level_courses', [
            'academic_program_id' => $offering[2]->id,
            'course_code' => 'BKC 101',
            'source' => 'manual',
        ]);

        // Created centrally rather than kept private, so the next school to
        // need it finds it instead of writing it again.
        $this->assertDatabaseHas('courses', [
            'title' => 'Local Studies',
            'source_type' => 'institution',
            'ccmas_course_id' => null,
        ]);
    }

    public function test_a_course_cannot_be_added_to_a_level_they_do_not_hold(): void
    {
        $offering = $this->offering('Bayero University Kano');
        $user = $this->coordinator($offering, level: 100);

        $this->actingAs($user)->post(route('coordinator.courses.store.manual'), [
            'academic_program_id' => $offering[2]->id,
            'level' => 400,
            'course_code' => 'BKC 401',
            'title' => 'Not My Level',
        ])->assertSessionHasErrors('course');

        $this->assertDatabaseMissing('programme_level_courses', ['course_code' => 'BKC 401']);
    }

    public function test_a_course_cannot_be_added_to_another_schools_programme(): void
    {
        // The cross-school case. Both ids in the form are valid primary keys;
        // only the appointment decides whether the pair is allowed.
        $mine = $this->offering('Bayero University Kano');
        $theirs = $this->offering('University of Lagos');
        $user = $this->coordinator($mine);

        $this->actingAs($user)->post(route('coordinator.courses.store.manual'), [
            'academic_program_id' => $theirs[2]->id,
            'level' => 100,
            'course_code' => 'XXX 101',
            'title' => 'Intrusion',
        ])->assertSessionHasErrors('course');

        $this->assertDatabaseMissing('programme_level_courses', ['course_code' => 'XXX 101']);
    }

    public function test_a_coordinator_cannot_remove_another_schools_course(): void
    {
        $mine = $this->offering('Bayero University Kano');
        $theirs = $this->offering('University of Lagos');
        $user = $this->coordinator($mine);

        $theirsCourse = ProgrammeLevelCourse::create([
            'academic_program_id' => $theirs[2]->id,
            'level' => 100,
            'course_id' => $this->central('Their Course', 'LOS 101')->id,
            'course_code' => 'LOS 101',
            'title' => 'Their Course',
        ]);

        $this->actingAs($user)
            ->delete(route('coordinator.courses.destroy', $theirsCourse->id))
            ->assertSessionHasErrors('course');

        $this->assertDatabaseHas('programme_level_courses', ['course_code' => 'LOS 101']);
    }

    public function test_two_schools_keep_independent_lists_for_the_same_programme(): void
    {
        // The reason this table exists rather than curriculum_courses: a course
        // chosen at one school must not appear at another.
        $bayo = $this->offering('Bayero University Kano');
        $lagos = $this->offering('University of Lagos');

        app(LevelCoordinatorScope::class)->addCourse(
            $this->coordinator($bayo),
            $bayo[2]->id,
            100,
            $this->central('Kano Only', 'BAY 101'),
            'BAY 101',
        );

        $this->assertSame(1, ProgrammeLevelCourse::where('academic_program_id', $bayo[2]->id)->count());
        $this->assertSame(0, ProgrammeLevelCourse::where('academic_program_id', $lagos[2]->id)->count());
    }

    public function test_the_same_shared_course_cannot_be_added_twice_to_one_level(): void
    {
        $offering = $this->offering('Bayero University Kano');
        $user = $this->coordinator($offering);
        $course = $this->central('Introduction to Computing', 'CSC 101');

        $payload = [
            'academic_program_id' => $offering[2]->id,
            'level' => 100,
            'course_id' => $course->id,
            'course_code' => 'COS 101',
        ];

        $this->actingAs($user)->post(route('coordinator.courses.store.shared'), $payload);
        $this->actingAs($user)->post(route('coordinator.courses.store.shared'), $payload)
            ->assertSessionHasErrors('course');

        $this->assertSame(1, ProgrammeLevelCourse::where('course_id', $course->id)->count());
    }

    public function test_the_same_code_may_appear_at_a_different_level(): void
    {
        $offering = $this->offering('Bayero University Kano');
        $user = $this->coordinator($offering, level: 200);

        $this->actingAs($user)->post(route('coordinator.courses.store.shared'), [
            'academic_program_id' => $offering[2]->id,
            'level' => 200,
            'course_id' => $this->central('Introduction to Computing', 'CSC 101')->id,
            'course_code' => 'COS 101',
        ])->assertSessionHasNoErrors();

        $this->assertSame(1, ProgrammeLevelCourse::where('course_code', 'COS 101')->where('level', 200)->count());
    }

    public function test_a_course_may_be_removed_from_own_level(): void
    {
        $offering = $this->offering('Bayero University Kano');
        $user = $this->coordinator($offering);

        $course = ProgrammeLevelCourse::create([
            'academic_program_id' => $offering[2]->id,
            'level' => 100,
            'course_id' => $this->central()->id,
            'course_code' => 'COS 101',
            'title' => 'Introduction to Computing',
        ]);

        $this->actingAs($user)
            ->delete(route('coordinator.courses.destroy', $course->id))
            ->assertRedirect();

        $this->assertSame(0, ProgrammeLevelCourse::where('course_code', 'COS 101')->count());
    }

    public function test_search_finds_a_shared_course_by_its_catalogue_code(): void
    {
        $user = $this->coordinator($this->offering('Bayero University Kano'));
        $this->central('Introduction to Computing', 'CSC 101');

        $response = $this->actingAs($user)->getJson(route('coordinator.courses.search', ['q' => 'CSC 101']));

        $response->assertOk();
        $this->assertSame('CSC 101', $response->json('results.0.code'));
    }

    public function test_search_finds_a_shared_course_by_title(): void
    {
        $user = $this->coordinator($this->offering('Bayero University Kano'));
        $this->central('Introduction to Computing', 'CSC 101');

        $response = $this->actingAs($user)->getJson(route('coordinator.courses.search', ['q' => 'Introduction to Computing']));

        $response->assertOk();
        $this->assertCount(1, $response->json('results'));
    }

    public function test_search_reports_a_course_another_school_already_shares(): void
    {
        // The point of centralising: a coordinator can tell that a course is
        // already shared before adding it, rather than writing a second copy.
        $offering = $this->offering('Bayero University Kano');
        $user = $this->coordinator($offering);
        $course = $this->central('Introduction to Malware and Social Engineering', 'CSC 111');

        app(\App\Services\Courses\CentralCourseService::class)->registerLocalCode(
            $this->offering('Northwest University Kano')[0],
            $course,
            'NUK-CYB101',
        );

        $response = $this->actingAs($user)->getJson(
            route('coordinator.courses.search', ['q' => 'Malware'])
        );

        $response->assertOk();
        $this->assertSame(1, $response->json('results.0.shared_with'));
    }

    public function test_a_student_cannot_reach_the_course_area(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('coordinator.courses.index'))
            ->assertForbidden();
    }

    public function test_a_guest_is_sent_to_sign_in(): void
    {
        $this->get(route('coordinator.courses.index'))->assertRedirect(route('login'));
    }

    public function test_a_non_numeric_credit_unit_is_reported_plainly(): void
    {
        $offering = $this->offering('Bayero University Kano');
        $user = $this->coordinator($offering);

        $this->actingAs($user)->post(route('coordinator.courses.store.manual'), [
            'academic_program_id' => $offering[2]->id,
            'level' => 100,
            'course_code' => 'COS 101',
            'title' => 'Introduction to Computing',
            'credit_units' => 'three',
        ])->assertSessionHasErrors('credit_units');
    }
}
