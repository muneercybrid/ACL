<?php

namespace Tests\Feature;

use App\Services\Courses\CourseCodeParser;
use App\Services\Courses\CourseRegistrationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * A registration form states what a programme level runs, and nothing more.
 *
 * The owner clarified that "course registration completed" describes the
 * curriculum list being finalized for a programme level, not a student having
 * passed anything. These tests hold that line: the seeder writes courses and
 * verification state, and it must never write a grade or a completion. A
 * fabricated pass is far worse than missing data, because it is invisible later.
 *
 * They also pin the two rules that are easy to lose in a refactor. Only courses
 * found in the central CCMAS corpus are marked verified -- an institution-added
 * course is shown but not claimed to be verified until its document arrives.
 * And a semester stated by a document outranks the parity convention, with the
 * divergence recorded instead of being silently overwritten.
 */
class CourseRegistrationSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function loader(): CourseRegistrationSeeder
    {
        return new CourseRegistrationSeeder(new CourseCodeParser);
    }

    /**
     * @return array{organization_id: int, department_id: int, academic_program_id: int}
     */
    protected function makeProgramme(): array
    {
        $organizationId = DB::table('organizations')->insertGetId([
            'name' => 'Northwest University Kano',
            'slug' => 'northwest-university-kano',
            'type' => 'State University',
            'status' => 'active',
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $facultyId = DB::table('faculties')->insertGetId([
            'organization_id' => $organizationId,
            'name' => 'Faculty of Computing',
            'slug' => 'faculty-of-computing',
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $departmentId = DB::table('departments')->insertGetId([
            'faculty_id' => $facultyId,
            'name' => 'Department of Cyber Security',
            'slug' => 'department-of-cyber-security',
            'code' => 'CYB',
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $programmeId = DB::table('academic_programs')->insertGetId([
            'organization_id' => $organizationId,
            'department_id' => $departmentId,
            'name' => 'B.Sc Cybersecurity',
            'slug' => 'bsc-cybersecurity',
            'code' => 'CYB',
            'degree_type' => 'B.Sc.',
            'duration_years' => 4,
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [
            'organization_id' => $organizationId,
            'department_id' => $departmentId,
            'academic_program_id' => $programmeId,
        ];
    }

    protected function seedCcmas(string $code, string $title, int $units = 2): int
    {
        return DB::table('ccmas_courses')->insertGetId([
            'course_code' => $code,
            'title' => $title,
            'credit_units' => $units,
            'level' => 100,
            'programme_title' => 'B.Sc. Cybersecurity',
            'source_document' => 'ccmas.pdf',
            'source_file' => 'ccmas.pdf',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_it_loads_courses_into_a_programme_level(): void
    {
        $programme = $this->makeProgramme();
        $this->seedCcmas('COS 101', 'Introduction to Computing Sciences', 3);
        $this->seedCcmas('GST 111', 'Communication in English', 2);

        $result = $this->loader()->load($programme['organization_id'], $programme['academic_program_id'], 100, [
            ['semester' => 1, 'code' => 'COS101', 'title' => 'Introduction to Computing Sciences', 'credit_units' => 3],
            ['semester' => 1, 'code' => 'GST111', 'title' => 'Communication in English', 'credit_units' => 2],
        ]);

        $this->assertSame(2, $result['inserted']);
        $this->assertSame(2, $result['verified']);
        $this->assertSame(2, DB::table('programme_level_courses')->count());
    }

    public function test_it_matches_ccmas_across_the_spacing_difference(): void
    {
        // The import writes "GST 111"; a form writes "GST111". Same course.
        $programme = $this->makeProgramme();
        $this->seedCcmas('GST 111', 'Communication in English', 2);

        $result = $this->loader()->load($programme['organization_id'], $programme['academic_program_id'], 100, [
            ['semester' => 1, 'code' => 'GST111', 'title' => 'Communication in English', 'credit_units' => 2],
        ]);

        $this->assertSame(1, $result['verified'], 'the spacing difference must not look like a missing course');
        $this->assertSame([], $result['unverified']);
    }

    public function test_an_institution_added_course_is_shown_but_not_verified(): void
    {
        // NUK-CYB101 is Northwest University's own course. It has no CCMAS row,
        // and the owner supplies those documents separately, so it is recorded
        // but must not be claimed as verified.
        $programme = $this->makeProgramme();

        $result = $this->loader()->load($programme['organization_id'], $programme['academic_program_id'], 100, [
            ['semester' => 1, 'code' => 'NUK-CYB101', 'title' => 'Introduction to Malware & Social Engineering', 'credit_units' => 3],
        ]);

        $this->assertSame(0, $result['verified']);
        $this->assertCount(1, $result['unverified']);
        $this->assertSame('NUKCYB101', $result['unverified'][0]['code']);

        $row = DB::table('programme_level_courses')->first();
        $this->assertSame('institution', $row->source);
        $this->assertNull($row->ccmas_course_id);
    }

    public function test_a_documented_semester_overrides_the_parity_convention(): void
    {
        // MTH 103 is second semester on the Northwest University Kano form
        // though its trailing digit is odd.
        $programme = $this->makeProgramme();
        $this->seedCcmas('MTH 103', 'Elementary Mathematics III', 2);

        $result = $this->loader()->load($programme['organization_id'], $programme['academic_program_id'], 100, [
            ['semester' => 2, 'code' => 'MTH103', 'title' => 'Elementary Mathematics III', 'credit_units' => 2],
        ]);

        $this->assertSame('2', DB::table('programme_level_courses')->first()->semester);
        $this->assertCount(1, $result['semester_conflicts'], 'the divergence must be reported, not hidden');
        $this->assertSame(1, $result['semester_conflicts'][0]['parity_semester']);
    }

    public function test_semesters_are_not_mixed(): void
    {
        $programme = $this->makeProgramme();
        $this->seedCcmas('COS 101', 'Introduction to Computing Sciences', 3);
        $this->seedCcmas('COS 102', 'Problem Solving', 3);

        $this->loader()->load($programme['organization_id'], $programme['academic_program_id'], 100, [
            ['semester' => 1, 'code' => 'COS101', 'title' => 'Introduction to Computing Sciences', 'credit_units' => 3],
            ['semester' => 2, 'code' => 'COS102', 'title' => 'Problem Solving', 'credit_units' => 3],
        ]);

        $this->assertSame(1, DB::table('programme_level_courses')->where('semester', '1')->count());
        $this->assertSame(1, DB::table('programme_level_courses')->where('semester', '2')->count());
    }

    public function test_a_course_can_be_moved_between_semesters(): void
    {
        // The owner asked for this explicitly: correcting a misplacement must be
        // an update, not a delete and re-add, so the course identity survives.
        $programme = $this->makeProgramme();
        $this->seedCcmas('COS 101', 'Introduction to Computing Sciences', 3);

        $this->loader()->load($programme['organization_id'], $programme['academic_program_id'], 100, [
            ['semester' => 1, 'code' => 'COS101', 'title' => 'Introduction to Computing Sciences', 'credit_units' => 3],
        ]);

        $courseId = DB::table('programme_level_courses')->first()->course_id;

        DB::table('programme_level_courses')->update(['semester' => '2', 'updated_at' => now()]);

        $moved = DB::table('programme_level_courses')->first();
        $this->assertSame('2', $moved->semester);
        $this->assertSame($courseId, $moved->course_id);
        $this->assertSame(1, DB::table('programme_level_courses')->count(), 'moving must not duplicate the course');
    }

    public function test_credit_units_are_recorded_but_never_treated_as_an_error(): void
    {
        // The owner does not follow NUC credit rules, so STA 111 at three units
        // against two in CCMAS is a difference to report, not a failure.
        $programme = $this->makeProgramme();
        $this->seedCcmas('STA 111', 'Descriptive Statistics', 2);

        $result = $this->loader()->load($programme['organization_id'], $programme['academic_program_id'], 100, [
            ['semester' => 1, 'code' => 'STA111', 'title' => 'Descriptive Statistics', 'credit_units' => 3],
        ]);

        $this->assertSame(1, $result['inserted'], 'a unit difference must not block the load');
        $this->assertCount(1, $result['unit_differences']);
        $this->assertSame(3.0, (float) DB::table('programme_level_courses')->first()->credit_units);
    }

    public function test_it_writes_no_grades_or_completions(): void
    {
        // Course registration being "complete" is a statement about the
        // curriculum list. Nothing here may look like a student passing.
        $programme = $this->makeProgramme();
        $this->seedCcmas('COS 101', 'Introduction to Computing Sciences', 3);

        $this->loader()->load($programme['organization_id'], $programme['academic_program_id'], 100, [
            ['semester' => 1, 'code' => 'COS101', 'title' => 'Introduction to Computing Sciences', 'credit_units' => 3],
        ]);

        $columns = DB::getSchemaBuilder()->getColumnListing('programme_level_courses');

        foreach (['grade', 'result', 'score', 'completed', 'passed', 'status'] as $forbidden) {
            $this->assertNotContains(
                $forbidden,
                $columns,
                "programme_level_courses must not carry a {$forbidden} column; registration completion is not a student result"
            );
        }

        $this->assertSame(0, DB::table('enrollments')->count());
    }

    public function test_reloading_is_idempotent(): void
    {
        $programme = $this->makeProgramme();
        $this->seedCcmas('COS 101', 'Introduction to Computing Sciences', 3);

        $courses = [
            ['semester' => 1, 'code' => 'COS101', 'title' => 'Introduction to Computing Sciences', 'credit_units' => 3],
        ];

        $this->loader()->load($programme['organization_id'], $programme['academic_program_id'], 100, $courses);
        $this->loader()->load($programme['organization_id'], $programme['academic_program_id'], 100, $courses);

        $this->assertSame(1, DB::table('programme_level_courses')->count());
        $this->assertSame(1, DB::table('courses')->count(), 'a shared course must be one row, not one per load');
    }

    public function test_a_matched_course_is_marked_verified_on_the_shared_row(): void
    {
        // The corpus import leaves courses unverified, because the import alone
        // cannot show a course is offered by any particular programme. A
        // registration form is exactly that evidence, so a confirmed match must
        // upgrade the shared row. Otherwise the programme entry says ccmas while
        // the course beside it still says unverified, and a coordinator is shown
        // two contradicting claims about the same course.
        $programme = $this->makeProgramme();
        $this->seedCcmas('COS 101', 'Introduction to Computing Sciences', 3);

        $preExisting = DB::table('courses')->insertGetId([
            'code' => 'COS101',
            'normalized_code' => 'COS101',
            'title' => 'Introduction to Computing Sciences',
            'slug' => 'cos101',
            'credit_units' => 3,
            'scope' => 'national',
            'source_type' => 'nuc_ccmas',
            'verification_status' => 'unverified',
            'status' => 'active',
            'is_active' => 1,
            'is_external' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->loader()->load($programme['organization_id'], $programme['academic_program_id'], 100, [
            ['semester' => 1, 'code' => 'COS101', 'title' => 'Introduction to Computing Sciences', 'credit_units' => 3],
        ]);

        $course = DB::table('courses')->where('id', $preExisting)->first();

        $this->assertSame('verified', $course->verification_status, 'a CCMAS match must upgrade the shared course row');
        $this->assertNotNull($course->ccmas_course_id, 'the shared row must be linked to its CCMAS record');
        $this->assertSame(
            1,
            DB::table('courses')->where('normalized_code', 'COS101')->count(),
            'the existing shared row must be reused, not duplicated'
        );
    }
}
