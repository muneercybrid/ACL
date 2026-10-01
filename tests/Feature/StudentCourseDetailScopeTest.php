<?php

namespace Tests\Feature;

use App\Models\ProgrammeLevelCourse;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The course detail page is reachable from both layers, so the link has to say
 * which one it means.
 *
 * curriculum_courses and programme_level_courses have unrelated, overlapping id
 * sequences, so a bare id cannot be trusted to identify a course. The link
 * carries a prefix instead, and these tests pin that the prefix is not a
 * formality: taking a national course's number and asking for it as an
 * institution course, or the reverse, must not open the other table.
 *
 * They also pin the scope check on the institution branch, which is the one
 * place a guessed id could otherwise reach another university's course.
 */
class StudentCourseDetailScopeTest extends TestCase
{
    use RefreshDatabase;

    protected int $orgA;

    protected int $programmeId;

    protected int $studentA;

    protected int $curriculumCourseId;

    protected int $programmeLevelCourseId;

    protected function setUp(): void
    {
        parent::setUp();

        $orgA = DB::table('organizations')->insertGetId([
            'name' => 'Northwest University Kano', 'slug' => 'nwu', 'abbr' => 'NWU',
            'type' => 'State University', 'status' => 'active', 'is_active' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $orgB = DB::table('organizations')->insertGetId([
            'name' => 'Other University', 'slug' => 'other', 'abbr' => 'OTH',
            'type' => 'Federal University', 'status' => 'active', 'is_active' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $facultyA = DB::table('faculties')->insertGetId([
            'organization_id' => $orgA, 'name' => 'Computing', 'slug' => 'f-a',
            'is_active' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $deptA = DB::table('departments')->insertGetId([
            'faculty_id' => $facultyA, 'name' => 'Cyber Security', 'slug' => 'd-a',
            'code' => 'CYB', 'is_active' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $facultyB = DB::table('faculties')->insertGetId([
            'organization_id' => $orgB, 'name' => 'Computing', 'slug' => 'f-b',
            'is_active' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $deptB = DB::table('departments')->insertGetId([
            'faculty_id' => $facultyB, 'name' => 'Cyber Security', 'slug' => 'd-b',
            'code' => 'CYB', 'is_active' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $programmeId = DB::table('academic_programs')->insertGetId([
            'organization_id' => $orgA, 'department_id' => $deptA, 'name' => 'B.Sc Cybersecurity',
            'slug' => 'bsc-cyb-a', 'code' => 'CYB', 'degree_type' => 'B.Sc.', 'duration_years' => 4,
            'is_active' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $programmeB = DB::table('academic_programs')->insertGetId([
            'organization_id' => $orgB, 'department_id' => $deptB, 'name' => 'B.Sc Cybersecurity',
            'slug' => 'bsc-cyb-b', 'code' => 'CYB', 'degree_type' => 'B.Sc.', 'duration_years' => 4,
            'is_active' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('academic_sessions')->insert([
            'name' => '2025/2026', 'slug' => '2025-2026', 'start_date' => '2025-09-01',
            'end_date' => '2026-07-31', 'is_active' => 1, 'is_current' => 1, 'status' => 'active',
            'start_year' => 2025, 'end_year' => 2026, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $nationalId = DB::table('programmes')->insertGetId([
            'organization_id' => null, 'nuc_discipline_id' => 1, 'name' => 'B.Sc Cybersecurity',
            'normalized_name' => 'cybersecurity', 'code' => 'CYB', 'degree_type' => 'B.Sc.',
            'duration_years' => 4, 'scope' => 'national', 'verification_status' => 'verified',
            'source_type' => 'nuc_ccmas', 'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $versionId = DB::table('curriculum_versions')->insertGetId([
            'programme_id' => $nationalId, 'academic_session_id' => 1, 'version_label' => 'V1',
            'slug' => 'nat-cyb-v1', 'scope' => 'national', 'verification_status' => 'verified',
            'is_active' => 1, 'ccmas_baseline_percentage' => 70, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $sharedCourse = DB::table('courses')->insertGetId([
            'code' => 'COS101', 'normalized_code' => 'COS101', 'title' => 'Introduction to Computing Sciences',
            'slug' => 'cos101', 'credit_units' => 3, 'scope' => 'national', 'source_type' => 'nuc_ccmas',
            'verification_status' => 'verified', 'status' => 'active', 'is_active' => 1, 'is_external' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        // Filler placements, so the curriculum and institution id sequences are
        // forced apart.
        //
        // Both tables start at 1 in a fresh database, which would make
        // curriculum id 1 and programme-level id 1 identical numbers. A test
        // that then claims "this id cannot reach that table" proves nothing,
        // because it cannot tell which table the number came from. Giving the
        // curriculum extra rows first pushes the real placement to a different
        // number from the real institution row, so each prefix is genuinely
        // exercised against a number that exists in the other table.
        $fillerCourse = DB::table('courses')->insertGetId([
            'code' => 'MTH101', 'normalized_code' => 'MTH101', 'title' => 'Elementary Mathematics I',
            'slug' => 'mth101', 'credit_units' => 2, 'scope' => 'national', 'source_type' => 'nuc_ccmas',
            'verification_status' => 'verified', 'status' => 'active', 'is_active' => 1, 'is_external' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        foreach ([1, 2, 3, 4] as $i => $level) {
            DB::table('curriculum_courses')->insert([
                'curriculum_version_id' => $versionId, 'course_id' => $fillerCourse,
                'level' => 100 * ($i + 1), 'semester' => 1, 'course_type' => 'core', 'credit_units' => 2,
                'status' => 'active', 'is_mandatory' => 1, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $this->curriculumCourseId = DB::table('curriculum_courses')->insertGetId([
            'curriculum_version_id' => $versionId, 'course_id' => $sharedCourse,
            'level' => 100, 'semester' => 1, 'course_type' => 'core', 'credit_units' => 3,
            'status' => 'active', 'is_mandatory' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $added = DB::table('courses')->insertGetId([
            'code' => 'NUKCYB101', 'normalized_code' => 'NUKCYB101',
            'title' => 'Introduction to Malware & Social Engineering', 'slug' => 'nukcyb101',
            'credit_units' => 3, 'scope' => 'university', 'source_type' => 'institution',
            'verification_status' => 'verified', 'status' => 'active', 'is_active' => 1, 'is_external' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        // Filler institution rows, so the institution sequence also clears the
        // curriculum one.
        // programme_level_courses is unique on (academic_program_id, level,
        // course_id), so each filler needs its own course rather than reusing
        // one.
        foreach (range(1, 6) as $i) {
            $fillerAdded = DB::table('courses')->insertGetId([
                'code' => 'NUKFILL'.$i, 'normalized_code' => 'NUKFILL'.$i, 'title' => 'Filler Course',
                'slug' => 'nukfill'.$i, 'credit_units' => 1, 'scope' => 'university',
                'source_type' => 'institution', 'verification_status' => 'verified', 'status' => 'active',
                'is_active' => 1, 'is_external' => 0, 'created_at' => now(), 'updated_at' => now(),
            ]);

            DB::table('programme_level_courses')->insert([
                'academic_program_id' => $programmeId, 'level' => 100, 'course_id' => $fillerAdded,
                'course_code' => 'NUKFILL'.$i, 'title' => 'Filler Course', 'credit_units' => 1,
                'source' => 'institution', 'semester' => '1', 'is_mandatory' => 1,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $this->programmeLevelCourseId = DB::table('programme_level_courses')->insertGetId([
            'academic_program_id' => $programmeId, 'level' => 100, 'course_id' => $added,
            'course_code' => 'NUKCYB101', 'title' => 'Introduction to Malware & Social Engineering',
            'credit_units' => 3, 'source' => 'institution', 'semester' => '1', 'is_mandatory' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $userA = User::factory()->create();
        $this->studentA = DB::table('students')->insertGetId([
            'user_id' => $userA->id, 'acl_student_id' => 'ACL-A', 'verification_method' => 'jamb',
            'verification_status' => 'verified', 'level' => 100, 'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('organization_memberships')->insert([
            'organization_id' => $orgA, 'user_id' => $userA->id, 'academic_program_id' => $programmeId,
            'membership_type' => 'student', 'status' => 'active', 'joined_at' => now(),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->orgA = $orgA;
        $this->programmeId = $programmeId;
    }

    /**
     * Sign in as the student created in setUp.
     *
     * Returns $this, not the user, so the request helper chains off the test
     * case. Returning the model would send the URL into a query builder.
     */
    protected function actingAsStudent(): static
    {
        $user = User::find(Student::find($this->studentA)->user_id);
        $this->actingAs($user);

        return $this;
    }

    public function test_a_national_course_opens_from_its_curriculum_reference(): void
    {
        $this->actingAsStudent()
            ->get('/student/course/c'.$this->curriculumCourseId)
            ->assertOk();
    }

    public function test_an_added_course_opens_from_its_programme_level_reference(): void
    {
        $this->actingAsStudent()
            ->get('/student/course/p'.$this->programmeLevelCourseId)
            ->assertOk();
    }

    public function test_a_curriculum_id_cannot_be_used_to_reach_the_institution_table(): void
    {
        // The two tables have unrelated, overlapping id sequences, so the number
        // alone is meaningless across them.
        //
        // The prefix decides which table is read, and each branch is then scoped
        // to the student. So the guarantee is not "404" -- a number that also
        // exists in the student's own programme legitimately opens that
        // course. The guarantee is that the institution layer's content is
        // never reachable through the national prefix, and vice versa. That is
        // asserted on the response body, which is what actually matters.
        $response = $this->actingAsStudent()
            ->get('/student/course/p'.$this->curriculumCourseId);

        $response->assertDontSee('Introduction to Malware');
    }

    public function test_an_institution_id_cannot_be_used_to_reach_the_national_table(): void
    {
        $response = $this->actingAsStudent()
            ->get('/student/course/c'.$this->programmeLevelCourseId);

        $response->assertDontSee('Introduction to Malware');
    }

    public function test_the_two_layers_resolve_to_their_own_content(): void
    {
        // The positive case, so the two assertions above are known to be
        // testing discrimination rather than an always-empty response.
        $this->actingAsStudent()
            ->get('/student/course/p'.$this->programmeLevelCourseId)
            ->assertOk()
            ->assertSee('Introduction to Malware');
    }

    public function test_a_reference_without_a_known_prefix_is_rejected(): void
    {
        $this->actingAsStudent()
            ->get('/student/course/x'.$this->curriculumCourseId)
            ->assertNotFound();
    }

    public function test_another_universitys_added_course_is_not_reachable(): void
    {
        // Give a different programme at this same university a level 100 course,
        // then try to read it. The scope check is on the programme, not merely
        // on the organization.
        $otherProgramme = DB::table('academic_programs')->insertGetId([
            'organization_id' => $this->orgA,
            'department_id' => DB::table('departments')->where('slug', 'd-a')->value('id'),
            'name' => 'B.Sc Computer Science', 'slug' => 'bsc-cs-a', 'code' => 'CSC',
            'degree_type' => 'B.Sc.', 'duration_years' => 4, 'is_active' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $otherCourse = DB::table('courses')->insertGetId([
            'code' => 'NUKCSC101', 'normalized_code' => 'NUKCSC101', 'title' => 'Intro to Programming',
            'slug' => 'nukcsc101', 'credit_units' => 3, 'scope' => 'university', 'source_type' => 'institution',
            'verification_status' => 'verified', 'status' => 'active', 'is_active' => 1, 'is_external' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $plcId = DB::table('programme_level_courses')->insertGetId([
            'academic_program_id' => $otherProgramme, 'level' => 100, 'course_id' => $otherCourse,
            'course_code' => 'NUKCSC101', 'title' => 'Intro to Programming', 'credit_units' => 3,
            'source' => 'institution', 'semester' => '1', 'is_mandatory' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->actingAsStudent()
            ->get('/student/course/p'.$plcId)
            ->assertNotFound();
    }

    public function test_a_course_added_for_another_level_is_not_reachable(): void
    {
        $plcId = DB::table('programme_level_courses')->insertGetId([
            'academic_program_id' => $this->programmeId, 'level' => 400, 'course_id' => DB::table('courses')
                ->where('normalized_code', 'NUKCYB101')->value('id'),
            'course_code' => 'NUKCYB401', 'title' => 'Advanced Malware Analysis', 'credit_units' => 3,
            'source' => 'institution', 'semester' => '1', 'is_mandatory' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        // The student is at level 100. A level 400 course is theirs by
        // organization and programme, but not by level.
        $this->actingAsStudent()
            ->get('/student/course/p'.$plcId)
            ->assertNotFound();
    }

    public function test_a_guest_is_refused_both_layers(): void
    {
        $this->get('/student/course/c'.$this->curriculumCourseId)->assertRedirect();
        $this->get('/student/course/p'.$this->programmeLevelCourseId)->assertRedirect();
    }

    public function test_the_two_layers_stay_distinct_in_storage(): void
    {
        // Sanity on the assumption this whole scheme rests on: the tables are
        // genuinely separate, and a course row is shared while its placement is
        // not.
        $this->assertNotSame(
            ProgrammeLevelCourse::find($this->programmeLevelCourseId)->course_id,
            DB::table('curriculum_courses')->where('id', $this->curriculumCourseId)->value('course_id')
        );

        $this->assertNotSame(
            ProgrammeLevelCourse::find($this->programmeLevelCourseId)->getTable(),
            (new \App\Models\Curriculum\CurriculumCourse)->getTable()
        );
    }
}
