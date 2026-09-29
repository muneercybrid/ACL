<?php

namespace Tests\Feature;

use App\Models\Curriculum\Course;
use App\Models\Curriculum\NucDiscipline;
use App\Models\Student;
use App\Services\CourseVisibilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CourseVisibilityTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The external catalogue and the NUC disciplines it hangs off are seeded
     * explicitly.
     *
     * This class previously had no database state of its own, so it either
     * skipped on missing seeder data or, in the catalogue case, passed over an
     * empty collection without asserting anything. Seeding the two seeders
     * makes both tests exercise real data instead of describing the absence of
     * it: the disciplines come from the NUC reference set, and the external
     * courses from the catalogue seeder that keys off them.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\NucReferenceSeeder::class);
        $this->seed(\Database\Seeders\ExternalCatalogueSeeder::class);
    }

    public function test_external_courses_are_scoped_to_student_discipline(): void
    {
        $svc = app(CourseVisibilityService::class);

        $cmp = NucDiscipline::where('code', 'CMP')->first();
        $med = NucDiscipline::where('code', 'MED')->first();

        $hacking = Course::where('is_external', true)->where('code', 'EXT-CMP-001')->first();
        $medical = Course::where('is_external', true)->where('code', 'EXT-MED-001')->first();

        if (! $cmp || ! $med || ! $hacking || ! $medical) {
            $this->markTestSkipped('Seeder data not present');
        }

        // Build a student whose programme is in CMP.
        //
        // The discipline is reached through programmes, not directly off
        // academic_programs: that table carries nuc_programme_id, and it is
        // programmes that hold nuc_discipline_id. The previous query filtered
        // on academic_programs.nuc_discipline_id, a column that does not exist
        // in the schema, so this test always died on a SQL error instead of
        // reaching its assertions.
        //
        // Membership is reached through the user, matching how
        // StudentDashboardService resolves a student's programme.
        $student = Student::whereHas('user.organizationMemberships.academicProgram', fn ($q) =>
            $q->whereHas('nucProgramme', fn ($q2) =>
                $q2->where('nuc_discipline_id', $cmp->id)))->first();

        if (! $student) {
            $this->markTestSkipped('No CMP student available');
        }

        $visible = $svc->visibleExternalForStudent($student)->pluck('id');

        $this->assertTrue($visible->contains($hacking->id), 'CMP student must see hacking course');
        $this->assertFalse($visible->contains($medical->id), 'CMP student must NOT see medical external');
    }

    public function test_catalogue_returns_only_external_courses(): void
    {
        $svc = app(CourseVisibilityService::class);
        $all = $svc->catalogue('CMP');

        // Assert the collection is non-empty first. Without this the loop below
        // never executes and the test passes without checking anything, which
        // is why it was reported risky — an empty catalogue is exactly the case
        // where "everything returned is external" is trivially true.
        $this->assertNotEmpty($all, 'the catalogue must return courses to be checked');

        foreach ($all as $c) {
            $this->assertTrue((bool) $c->is_external, 'the catalogue must not leak internal courses');
        }
    }
}
