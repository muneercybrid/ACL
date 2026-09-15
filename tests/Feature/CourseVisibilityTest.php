<?php

namespace Tests\Feature;

use App\Models\Curriculum\Course;
use App\Models\Curriculum\NucDiscipline;
use App\Models\Student;
use App\Services\CourseVisibilityService;
use Illuminate\Foundation\Testing\TestCase;
use Tests\CreatesApplication;

class CourseVisibilityTest extends TestCase
{
    use CreatesApplication;

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

        // Build a student whose programme is in CMP
        $student = Student::whereHas('institutionRecords.academicProgram', fn ($q) =>
            $q->where('nuc_discipline_id', $cmp->id))->first();

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
        foreach ($all as $c) {
            $this->assertTrue((bool) $c->is_external);
        }
    }
}
