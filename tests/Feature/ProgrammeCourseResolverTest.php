<?php

namespace Tests\Feature;

use App\Models\AcademicProgram;
use App\Models\Student;
use App\Models\User;
use App\Services\Courses\ProgrammeCourseResolver;
use App\Services\StudentDashboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Two layers, one list: national CCMAS courses shared by everyone, and the
 * courses a school added for itself and nobody else.
 *
 * The owner drew the line explicitly. A course on the CCMAS document is the same
 * course for every programme carrying it and every organization; anything a
 * school adds on top belongs to that school, at that school, at that level.
 *
 * These tests hold both halves. The national layer must reach a student with no
 * institution at all. The institution layer must reach only its own school, and
 * only at the level it was added for. And because the two layers are keyed by
 * different tables with unrelated ids, the route reference has to keep them
 * apart -- otherwise a link built from one resolves to a row in the other.
 */
class ProgrammeCourseResolverTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Build two universities that both run B.Sc Cybersecurity, with a national
     * curriculum and a student at each.
     *
     * @return array{orgA: int, orgB: int, programmeId: int, programmeB: int, userA: int, userB: int, studentA: int, studentB: int, versionId: int, courseId: int}
     */
    protected function world(): array
    {
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

        $facultyId = DB::table('faculties')->insertGetId([
            'organization_id' => $orgA, 'name' => 'Computing', 'slug' => 'comp-a',
            'is_active' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $departmentId = DB::table('departments')->insertGetId([
            'faculty_id' => $facultyId, 'name' => 'Cyber Security', 'slug' => 'cyb-a',
            'code' => 'CYB', 'is_active' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);

        // University B needs its own faculty and department too.
        // academic_programs is unique on (department_id, code), so two
        // universities cannot share one department for the same programme code.
        $facultyB = DB::table('faculties')->insertGetId([
            'organization_id' => $orgB, 'name' => 'Computing', 'slug' => 'comp-b',
            'is_active' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $departmentB = DB::table('departments')->insertGetId([
            'faculty_id' => $facultyB, 'name' => 'Cyber Security', 'slug' => 'cyb-b',
            'code' => 'CYB', 'is_active' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);

        // Institution programmes, one per university.
        $programmeId = DB::table('academic_programs')->insertGetId([
            'organization_id' => $orgA, 'department_id' => $departmentId,
            'name' => 'B.Sc Cybersecurity', 'slug' => 'bsc-cyb-a', 'code' => 'CYB',
            'degree_type' => 'B.Sc.', 'duration_years' => 4, 'is_active' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $programmeB = DB::table('academic_programs')->insertGetId([
            'organization_id' => $orgB, 'department_id' => $departmentB,
            'name' => 'B.Sc Cybersecurity', 'slug' => 'bsc-cyb-b', 'code' => 'CYB',
            'degree_type' => 'B.Sc.', 'duration_years' => 4, 'is_active' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        // One national curriculum programme, shared by both universities.
        $nationalId = DB::table('programmes')->insertGetId([
            'organization_id' => null, 'nuc_discipline_id' => 1, 'name' => 'B.Sc Cybersecurity',
            'normalized_name' => 'cybersecurity', 'code' => 'CYB', 'degree_type' => 'B.Sc.',
            'duration_years' => 4, 'scope' => 'national', 'verification_status' => 'verified',
            'source_type' => 'nuc_ccmas', 'status' => 'active',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('academic_sessions')->insert([
            'name' => '2025/2026', 'slug' => '2025-2026', 'start_date' => '2025-09-01',
            'end_date' => '2026-07-31', 'is_active' => 1, 'is_current' => 1, 'status' => 'active',
            'start_year' => 2025, 'end_year' => 2026, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $versionId = DB::table('curriculum_versions')->insertGetId([
            'programme_id' => $nationalId, 'academic_session_id' => 1, 'version_label' => 'V1',
            'slug' => 'nat-cyb-v1', 'scope' => 'national', 'verification_status' => 'verified',
            'is_active' => 1, 'ccmas_baseline_percentage' => 70,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        // A shared national course on the curriculum.
        $courseId = DB::table('courses')->insertGetId([
            'code' => 'COS101', 'normalized_code' => 'COS101',
            'title' => 'Introduction to Computing Sciences', 'slug' => 'cos101',
            'credit_units' => 3, 'scope' => 'national', 'source_type' => 'nuc_ccmas',
            'verification_status' => 'verified', 'status' => 'active', 'is_active' => 1,
            'is_external' => 0, 'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('curriculum_courses')->insert([
            'curriculum_version_id' => $versionId, 'course_id' => $courseId,
            'level' => 100, 'semester' => 1, 'course_type' => 'core',
            'credit_units' => 3, 'status' => 'active', 'is_mandatory' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        // An institution course, added by university A only.
        $institutionCourseId = DB::table('courses')->insertGetId([
            'code' => 'NUKCYB101', 'normalized_code' => 'NUKCYB101',
            'title' => 'Introduction to Malware & Social Engineering', 'slug' => 'nukcyb101',
            'credit_units' => 3, 'scope' => 'university', 'source_type' => 'institution',
            'verification_status' => 'verified', 'status' => 'active', 'is_active' => 1,
            'is_external' => 0, 'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('programme_level_courses')->insert([
            'academic_program_id' => $programmeId, 'level' => 100, 'course_id' => $institutionCourseId,
            'course_code' => 'NUKCYB101', 'title' => 'Introduction to Malware & Social Engineering',
            'credit_units' => 3, 'source' => 'institution', 'semester' => '1', 'is_mandatory' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        // The same institution course at Level 400, to prove level isolation.
        DB::table('programme_level_courses')->insert([
            'academic_program_id' => $programmeId, 'level' => 400, 'course_id' => $institutionCourseId,
            'course_code' => 'NUKCYB401', 'title' => 'Advanced Malware Analysis',
            'credit_units' => 3, 'source' => 'institution', 'semester' => '1', 'is_mandatory' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $studentA = DB::table('students')->insertGetId([
            'user_id' => $userA->id, 'acl_student_id' => 'ACL-A', 'verification_method' => 'jamb',
            'verification_status' => 'verified', 'level' => 100,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $studentB = DB::table('students')->insertGetId([
            'user_id' => $userB->id, 'acl_student_id' => 'ACL-B', 'verification_method' => 'jamb',
            'verification_status' => 'verified', 'level' => 100,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('organization_memberships')->insert([
            ['organization_id' => $orgA, 'user_id' => $userA->id, 'academic_program_id' => $programmeId,
             'membership_type' => 'student', 'status' => 'active', 'joined_at' => now(),
             'created_at' => now(), 'updated_at' => now()],
            ['organization_id' => $orgB, 'user_id' => $userB->id, 'academic_program_id' => $programmeB,
             'membership_type' => 'student', 'status' => 'active', 'joined_at' => now(),
             'created_at' => now(), 'updated_at' => now()],
        ]);

        return compact('orgA', 'orgB', 'programmeId', 'programmeB', 'userA', 'userB', 'studentA', 'studentB', 'versionId', 'courseId')
            + ['institutionCourseId' => $institutionCourseId];
    }

    protected function entriesFor(int $studentId)
    {
        return app(ProgrammeCourseResolver::class)->forStudent(Student::find($studentId));
    }

    public function test_a_student_sees_both_the_shared_and_the_added_course(): void
    {
        $w = $this->world();
        $codes = $this->entriesFor($w['studentA'])->pluck('code')->all();

        $this->assertContains('COS101', $codes, 'the national course must be listed');
        $this->assertContains('NUKCYB101', $codes, 'the school\'s own course must be listed too');
    }

    public function test_an_added_course_is_isolated_to_its_own_university(): void
    {
        // University B runs the same programme. It must not inherit A's course.
        $w = $this->world();
        $codesB = $this->entriesFor($w['studentB'])->pluck('code')->all();

        $this->assertContains('COS101', $codesB, 'the national course is shared by everyone');
        $this->assertNotContains(
            'NUKCYB101',
            $codesB,
            'another university\'s added course must never appear'
        );
    }

    public function test_an_added_course_is_isolated_to_its_own_level(): void
    {
        $w = $this->world();
        $student = Student::find($w['studentA']);

        $at100 = app(ProgrammeCourseResolver::class)->levelFor($student);
        $this->assertSame(100, $at100);

        $codes = $this->entriesFor($w['studentA'])->pluck('code')->all();
        $this->assertNotContains('NUKCYB401', $codes, 'a course added for Level 400 must not reach a Level 100 student');

        $student->level = 400;
        $student->save();

        $codes400 = $this->entriesFor($w['studentA'])->pluck('code')->all();
        $this->assertContains('NUKCYB401', $codes400);
        $this->assertNotContains('NUKCYB101', $codes400);
    }

    public function test_an_added_course_remains_verified(): void
    {
        // "Still verified" is part of the rule: isolation is about who sees it,
        // not about demoting it to an unverified draft.
        $w = $this->world();

        $entry = $this->entriesFor($w['studentA'])->firstWhere('code', 'NUKCYB101');

        $this->assertNotNull($entry);
        $this->assertSame('institution', $entry->kind);
        $this->assertSame('verified', $entry->course->verification_status);
    }

    public function test_route_references_keep_the_two_id_spaces_apart(): void
    {
        // curriculum_courses and programme_level_courses have unrelated id
        // sequences. A bare id in a link could resolve to the wrong table.
        $w = $this->world();

        foreach ($this->entriesFor($w['studentA']) as $entry) {
            $this->assertMatchesRegularExpression(
                '/^[cp]\d+$/',
                $entry->route_ref,
                'each entry must carry a prefixed route reference'
            );
        }

        $national = $this->entriesFor($w['studentA'])->firstWhere('code', 'COS101');
        $institution = $this->entriesFor($w['studentA'])->firstWhere('code', 'NUKCYB101');

        $this->assertStringStartsWith('c', $national->route_ref);
        $this->assertStringStartsWith('p', $institution->route_ref);
    }

    public function test_a_school_override_replaces_rather_than_duplicates_the_national_course(): void
    {
        // If a school also records a CCMAS course in its own layer, that row is
        // the one carrying the school's semester placement and units, so it
        // wins and the national row does not appear twice.
        $w = $this->world();

        DB::table('programme_level_courses')->insert([
            'academic_program_id' => $w['programmeId'], 'level' => 100, 'course_id' => $w['courseId'],
            'course_code' => 'COS101', 'title' => 'Introduction to Computing Sciences',
            'credit_units' => 4, 'source' => 'institution', 'semester' => '2', 'is_mandatory' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $entries = $this->entriesFor($w['studentA']);

        $this->assertSame(
            1,
            $entries->where('code', 'COS101')->count(),
            'the same course must not appear twice'
        );

        $override = $entries->firstWhere('code', 'COS101');
        $this->assertSame(2, $override->semester, 'the school placement wins');
        $this->assertSame(4.0, (float) $override->credit_units);
    }

    public function test_a_student_with_no_institution_still_sees_the_national_layer(): void
    {
        // The national curriculum is organization agnostic, so a student who has
        // not been attached to a university yet still gets the shared courses.
        $w = $this->world();

        DB::table('organization_memberships')->where('user_id', $w['userA']->id)->delete();
        DB::table('student_registration_verifications')->insert([
            'token' => (string) Str::uuid(), 'user_id' => $w['userA']->id, 'status' => 'verified',
            'jamb_exam_value' => '0234', 'jamb_exam_year' => 2026, 'jamb_exam_type' => 'UTME',
            'jamb_registration_number' => '20261234ABCD',
            'jamb_registration_number_hash' => hash('sha256', '20261234ABCD-'.$w['userA']),
            'verified_programme' => 'Cyber Security',
            'verified_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);

        $codes = $this->entriesFor($w['studentA'])->pluck('code')->all();

        $this->assertContains('COS101', $codes);
    }

    public function test_an_academic_programme_is_never_borrowed_from_another_university(): void
    {
        // Name matching alone returned the first university running a programme.
        // With a membership, only that university's programme may be used.
        $w = $this->world();
        $student = Student::find($w['studentA']);

        $resolved = app(StudentDashboardService::class)->academicProgramme($student);

        $this->assertNotNull($resolved);
        $this->assertSame(
            (int) $w['orgA'],
            (int) $resolved->organization_id,
            'the programme must belong to the student\'s own institution'
        );
    }
}
