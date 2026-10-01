<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The dashboard is a hero and a set of counts, not the course catalogue.
 *
 * Two things are pinned here. The counts must be real: the "Courses Active"
 * card used to hold a literal 13, so a student with no courses and one with
 * forty both read 13, and nothing on the page could reveal it. And the merged
 * two-layer list must survive rendering, including the prefixed route reference
 * the detail link depends on -- the bare id that used to be passed there is
 * ambiguous now that placements come from two tables.
 */
class StudentDashboardCoursesTest extends TestCase
{
    use RefreshDatabase;

    protected function seedStudentWithCourses(): User
    {
        $orgA = DB::table('organizations')->insertGetId([
            'name' => 'Northwest University Kano', 'slug' => 'nwu', 'abbr' => 'NWU',
            'type' => 'State University', 'status' => 'active', 'is_active' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $faculty = DB::table('faculties')->insertGetId([
            'organization_id' => $orgA, 'name' => 'Computing', 'slug' => 'f',
            'is_active' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $dept = DB::table('departments')->insertGetId([
            'faculty_id' => $faculty, 'name' => 'Cyber Security', 'slug' => 'd',
            'code' => 'CYB', 'is_active' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $programme = DB::table('academic_programs')->insertGetId([
            'organization_id' => $orgA, 'department_id' => $dept, 'name' => 'B.Sc Cybersecurity',
            'slug' => 'cyb', 'code' => 'CYB', 'degree_type' => 'B.Sc.', 'duration_years' => 4,
            'is_active' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('academic_sessions')->insert([
            'name' => '2025/2026', 'slug' => '2025-2026', 'start_date' => '2025-09-01',
            'end_date' => '2026-07-31', 'is_active' => 1, 'is_current' => 1, 'status' => 'active',
            'start_year' => 2025, 'end_year' => 2026, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $nationalProgramme = DB::table('programmes')->insertGetId([
            'organization_id' => null, 'nuc_discipline_id' => 1, 'name' => 'B.Sc Cybersecurity',
            'normalized_name' => 'cybersecurity', 'code' => 'CYB', 'degree_type' => 'B.Sc.',
            'duration_years' => 4, 'scope' => 'national', 'verification_status' => 'verified',
            'source_type' => 'nuc_ccmas', 'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $version = DB::table('curriculum_versions')->insertGetId([
            'programme_id' => $nationalProgramme, 'academic_session_id' => 1, 'version_label' => 'V1',
            'slug' => 'v1', 'scope' => 'national', 'verification_status' => 'verified',
            'is_active' => 1, 'ccmas_baseline_percentage' => 70, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $shared = DB::table('courses')->insertGetId([
            'code' => 'COS101', 'normalized_code' => 'COS101', 'title' => 'Introduction to Computing Sciences',
            'slug' => 'cos101', 'credit_units' => 3, 'scope' => 'national', 'source_type' => 'nuc_ccmas',
            'verification_status' => 'verified', 'status' => 'active', 'is_active' => 1, 'is_external' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('curriculum_courses')->insert([
            'curriculum_version_id' => $version, 'course_id' => $shared, 'level' => 100, 'semester' => 1,
            'course_type' => 'core', 'credit_units' => 3, 'status' => 'active', 'is_mandatory' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $added = DB::table('courses')->insertGetId([
            'code' => 'NUKCYB101', 'normalized_code' => 'NUKCYB101',
            'title' => 'Introduction to Malware & Social Engineering', 'slug' => 'nukcyb101',
            'credit_units' => 3, 'scope' => 'university', 'source_type' => 'institution',
            'verification_status' => 'verified', 'status' => 'active', 'is_active' => 1, 'is_external' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('programme_level_courses')->insert([
            'academic_program_id' => $programme, 'level' => 100, 'course_id' => $added,
            'course_code' => 'NUKCYB101', 'title' => 'Introduction to Malware & Social Engineering',
            'credit_units' => 3, 'source' => 'institution', 'semester' => '1', 'is_mandatory' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $user = User::factory()->create();

        DB::table('students')->insert([
            'user_id' => $user->id, 'acl_student_id' => 'ACL-1', 'verification_method' => 'jamb',
            'verification_status' => 'verified', 'level' => 100, 'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('organization_memberships')->insert([
            'organization_id' => $orgA, 'user_id' => $user->id, 'academic_program_id' => $programme,
            'membership_type' => 'student', 'status' => 'active', 'joined_at' => now(),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return $user;
    }

    public function test_the_dashboard_shows_a_hero_and_counts_rather_than_the_course_list(): void
    {
        $user = $this->seedStudentWithCourses();

        $response = $this->actingAs($user)->get(route('student.dashboard'));

        $response->assertOk()
            ->assertSee('Anyone Can Learn.')
            ->assertSee('Enrolled Courses');

        // The whole point of the redesign: the catalogue is not spelled out on
        // the dashboard. The full list has its own page.
        $response->assertDontSee('Introduction to Malware &amp; Social Engineering');
    }

    public function test_the_dashboard_counts_are_derived_from_real_data(): void
    {
        $user = $this->seedStudentWithCourses();

        $counts = app(\App\Http\Controllers\StudentDashboardController::class);

        $method = new \ReflectionMethod($counts, 'dashboardCounts');
        $method->setAccessible(true);

        $controller = app()->make(\App\Http\Controllers\StudentDashboardController::class);

        $result = $method->invoke($controller, app(\App\Services\Courses\ProgrammeCourseResolver::class)
            ->forStudent($user->student), []);

        $this->assertSame(2, $result['available'], 'one national plus one added');
        $this->assertSame(1, $result['national']);
        $this->assertSame(1, $result['institution']);
        $this->assertSame(0, $result['enrolled'], 'nothing is enrolled without an offering');
    }

    public function test_my_courses_lists_both_layers_with_working_detail_links(): void
    {
        $user = $this->seedStudentWithCourses();

        $response = $this->actingAs($user)->get(route('student.my-courses'));

        $response->assertOk()
            ->assertSee('Introduction to Computing Sciences')
            ->assertSee('Introduction to Malware');

        // Links must carry the prefixed reference. A bare id would be ambiguous
        // across the two tables the entries come from.
        $response->assertSee('/student/course/c', false);
        $response->assertSee('/student/course/p', false);
    }

    public function test_an_empty_student_gets_a_working_page_and_a_honest_zero(): void
    {
        $user = User::factory()->create();

        DB::table('students')->insert([
            'user_id' => $user->id, 'acl_student_id' => 'ACL-EMPTY', 'verification_method' => 'jamb',
            'verification_status' => 'verified', 'level' => 100, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->actingAs($user)
            ->get(route('student.dashboard'))
            ->assertOk()
            ->assertSee('Anyone Can Learn.')
            ->assertSee('No courses are mapped to your programme and level yet.');
    }
}
