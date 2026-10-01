<?php

namespace Tests\Feature;

use App\Services\Courses\CcmasProgrammePublisher;
use App\Services\Courses\CourseCodeParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * A course on the CCMAS document is the same course everywhere.
 *
 * The owner was explicit: it is not a matter of school curriculum. If a course
 * appears on CCMAS, every programme carrying it, at every organization, carries
 * that same course. Only what a school adds on top is isolated to that school.
 *
 * These tests hold both halves of that rule. Publishing must be national and
 * idempotent -- publishing twice cannot fork the catalogue -- and two
 * organizations running the same programme must end up pointing at the same
 * course rows, not at private copies that drift apart.
 */
class CcmasProgrammePublisherTest extends TestCase
{
    use RefreshDatabase;

    protected function publisher(): CcmasProgrammePublisher
    {
        return new CcmasProgrammePublisher(new CourseCodeParser);
    }

    /**
     * @return array{programme_id: int, version_id: int, organization_id: int}
     */
    protected function makeNationalProgramme(string $name = 'B.Sc. Cybersecurity'): array
    {
        $organizationId = DB::table('organizations')->insertGetId([
            'name' => 'Some University', 'slug' => 'some-university',
            'type' => 'Federal University', 'status' => 'active', 'is_active' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $programmeId = DB::table('programmes')->insertGetId([
            'organization_id' => null,
            'nuc_discipline_id' => 1,
            'name' => $name,
            'normalized_name' => strtolower(str_replace('.', '', $name)),
            'code' => 'CYB',
            'degree_type' => 'B.Sc.',
            'duration_years' => 4,
            'scope' => 'national',
            'verification_status' => 'verified',
            'source_type' => 'nuc_ccmas',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('academic_sessions')->insert([
            'name' => '2025/2026', 'slug' => '2025-2026',
            'start_date' => '2025-09-01', 'end_date' => '2026-07-31',
            'is_active' => 1, 'is_current' => 1, 'status' => 'active',
            'start_year' => 2025, 'end_year' => 2026,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $versionId = DB::table('curriculum_versions')->insertGetId([
            'programme_id' => $programmeId,
            'academic_session_id' => 1,
            'version_label' => 'Version 1',
            'slug' => 'national-'.$programmeId.'-v1',
            'scope' => 'national',
            'verification_status' => 'verified',
            'is_active' => 1,
            'ccmas_baseline_percentage' => 70,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return compact('programmeId', 'versionId', 'organizationId') + [
            'programme_id' => $programmeId,
            'version_id' => $versionId,
            'organization_id' => $organizationId,
        ];
    }

    protected function seedCcmas(string $code, string $title, string $programme, int $level, int $units = 2): int
    {
        return DB::table('ccmas_courses')->insertGetId([
            'course_code' => $code,
            'title' => $title,
            'credit_units' => $units,
            'level' => $level,
            'semester' => null,
            'programme_title' => $programme,
            'source_document' => 'ccmas.pdf',
            'source_file' => 'ccmas.pdf',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_it_publishes_ccmas_courses_into_the_national_version(): void
    {
        $programme = $this->makeNationalProgramme();
        $this->seedCcmas('COS 101', 'Introduction to Computing Sciences', 'B.Sc. Cybersecurity', 100, 3);
        $this->seedCcmas('COS 102', 'Problem Solving', 'B.Sc. Cybersecurity', 100, 3);

        $result = $this->publisher()->publishProgramme($programme['programme_id']);

        $this->assertSame(2, $result['published']);
        $this->assertSame(2, DB::table('curriculum_courses')->count());
    }

    public function test_semesters_come_from_the_code_convention(): void
    {
        // CCMAS carries no semester column at all, so placement is derived from
        // the code: odd trailing digit first semester, even second.
        $programme = $this->makeNationalProgramme();
        $this->seedCcmas('COS 101', 'Introduction to Computing Sciences', 'B.Sc. Cybersecurity', 100);
        $this->seedCcmas('COS 102', 'Problem Solving', 'B.Sc. Cybersecurity', 100);

        $this->publisher()->publishProgramme($programme['programme_id']);

        $this->assertSame(1, DB::table('curriculum_courses')
            ->join('courses', 'courses.id', '=', 'curriculum_courses.course_id')
            ->where('courses.normalized_code', 'COS101')->value('semester'));

        $this->assertSame(2, DB::table('curriculum_courses')
            ->join('courses', 'courses.id', '=', 'curriculum_courses.course_id')
            ->where('courses.normalized_code', 'COS102')->value('semester'));
    }

    public function test_two_organizations_share_one_course_row(): void
    {
        // The whole point of the national layer. If publishing made a private
        // copy per organization, the same course would exist 481 times and they
        // would drift apart within a single semester.
        $programme = $this->makeNationalProgramme();
        $this->seedCcmas('GST 111', 'Communication in English', 'B.Sc. Cybersecurity', 100);
        $this->seedCcmas('GST 111', 'Communication in English', 'B.Sc. Accounting', 100);

        $accountingId = DB::table('programmes')->insertGetId([
            'organization_id' => null, 'nuc_discipline_id' => 1, 'name' => 'B.Sc. Accounting',
            'normalized_name' => 'bsc accounting', 'code' => 'ACC', 'degree_type' => 'B.Sc.',
            'duration_years' => 4, 'scope' => 'national', 'verification_status' => 'verified',
            'source_type' => 'nuc_ccmas', 'status' => 'active',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('curriculum_versions')->insert([
            'programme_id' => $accountingId, 'academic_session_id' => 1, 'version_label' => 'Version 1',
            'slug' => 'national-'.$accountingId.'-v1', 'scope' => 'national',
            'verification_status' => 'verified', 'is_active' => 1, 'ccmas_baseline_percentage' => 70,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->publisher()->publishProgramme($programme['programme_id']);
        $this->publisher()->publishProgramme($accountingId);

        $this->assertSame(
            2,
            DB::table('curriculum_courses')->count(),
            'two programmes, two curriculum placements'
        );

        $this->assertSame(
            1,
            DB::table('courses')->where('normalized_code', 'GST111')->count(),
            'the shared course itself must exist once nationally, not once per programme'
        );

        $this->assertSame(
            1,
            DB::table('curriculum_courses')->distinct()->count('course_id'),
            'both programmes must reference the same course row'
        );
    }

    public function test_publishing_is_idempotent(): void
    {
        $programme = $this->makeNationalProgramme();
        $this->seedCcmas('COS 101', 'Introduction to Computing Sciences', 'B.Sc. Cybersecurity', 100);
        $this->seedCcmas('COS 102', 'Problem Solving', 'B.Sc. Cybersecurity', 100);

        $this->publisher()->publishProgramme($programme['programme_id']);
        $this->publisher()->publishProgramme($programme['programme_id']);

        $this->assertSame(2, DB::table('curriculum_courses')->count(), 'republishing must not duplicate placements');
        $this->assertSame(2, DB::table('courses')->count(), 'republishing must not fork the shared catalogue');
    }

    public function test_the_published_version_is_national(): void
    {
        $programme = $this->makeNationalProgramme();
        $this->seedCcmas('COS 101', 'Introduction to Computing Sciences', 'B.Sc. Cybersecurity', 100);

        $this->publisher()->publishProgramme($programme['programme_id']);

        $version = DB::table('curriculum_versions')->where('id', $programme['version_id'])->first();

        $this->assertSame('national', $version->scope, 'CCMAS courses must publish to a national version');

        // The version carries a baseline percentage expressing that CCMAS is the
        // floor for a programme and whatever an institution adds sits on top.
        // Publishing must not rewrite it -- the national layer is the baseline,
        // not a wholesale replacement of the programme.
        $this->assertSame(70, (int) $version->ccmas_baseline_percentage);
    }

    public function test_a_programme_with_no_active_version_publishes_nothing(): void
    {
        DB::table('programmes')->insert([
            'organization_id' => null, 'nuc_discipline_id' => 1, 'name' => 'B.Sc. Ghost',
            'normalized_name' => 'bsc ghost', 'code' => 'GHO', 'degree_type' => 'B.Sc.',
            'duration_years' => 4, 'scope' => 'national', 'verification_status' => 'verified',
            'source_type' => 'nuc_ccmas', 'status' => 'active',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $programmeId = DB::table('programmes')->where('name', 'B.Sc. Ghost')->value('id');
        $this->seedCcmas('GHO 101', 'Ghost Course', 'B.Sc. Ghost', 100);

        $result = $this->publisher()->publishProgramme((int) $programmeId);

        $this->assertNull($result['version_id']);
        $this->assertSame(0, $result['published']);
        $this->assertSame(0, DB::table('curriculum_courses')->count());
    }

    public function test_institution_added_courses_are_not_part_of_the_national_layer(): void
    {
        // NUK-CYB101 is Northwest University's own course. It must never be
        // published nationally, or every other university would inherit it.
        $programme = $this->makeNationalProgramme();
        $this->seedCcmas('COS 101', 'Introduction to Computing Sciences', 'B.Sc. Cybersecurity', 100);

        $this->publisher()->publishProgramme($programme['programme_id']);

        $this->assertSame(0, DB::table('courses')->where('normalized_code', 'NUKCYB101')->count());
        $this->assertSame(
            0,
            DB::table('curriculum_courses')->join('courses', 'courses.id', '=', 'curriculum_courses.course_id')
                ->where('courses.normalized_code', 'NUKCYB101')->count(),
            'an institution course must not leak into the shared national layer'
        );
    }
}
