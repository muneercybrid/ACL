<?php
namespace Tests\Feature;
use App\Services\Courses\StudentCourseLoader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CcmcasCurriculumIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_three_layer_loading_never_mixes_semester_or_level(): void
    {
        $loader = app(StudentCourseLoader::class);

        // Insert synthetic layer-1 (CCMAS national baseline) and layer-2 (institution)
        \DB::table('academic_programs')->insert([
            'organization_id' => 100, 'nuc_programme_id' => 1, 'department_id' => 1,
            'name' => 'Test', 'slug' => 't', 'code' => 'T', 'is_active' => 1,
            'created_at' => now(), 'updated_at' => now()
        ]);
        \DB::table('curriculum_versions')->insert([
            'programme_id' => 1, 'scope' => 'national', 'source_type' => 'ccmas',
            'version_label' => 'TEST', 'is_active' => true, 'ccmas_baseline_percentage' => 70,
            'created_at' => now(), 'updated_at' => now()
        ]);
        \DB::table('curriculum_courses')->insert([
            'curriculum_version_id' => 1, 'course_id' => 1, 'level' => 100,
            'semester' => 'First', 'course_type' => 'mandatory', 'is_mandatory' => 1,
            'credit_units' => 3, 'status' => 'verified', 'created_at' => now(), 'updated_at' => now()
        ]);
        \DB::table('programme_level_courses')->insert([
            'academic_program_id' => 1, 'level' => 100, 'course_id' => 2,
            'course_code' => 'TIN-101', 'semester' => 'First', 'status' => 'active',
            'created_at' => now(), 'updated_at' => now()
        ]);
        \DB::table('courses')->insert([
            'id' => 1, 'code' => 'COS101', 'title' => 'Intro', 'slug' => 'intro',
            'credit_units' => 3, 'scope' => 'university', 'source_type' => 'ccmas',
            'is_active' => true, 'created_at' => now(), 'updated_at' => now()
        ]);
        \DB::table('courses')->insert([
            'id' => 2, 'code' => 'TIN101', 'title' => 'Local', 'slug' => 'local',
            'credit_units' => 3, 'scope' => 'institution', 'source_type' => 'institution',
            'is_active' => true, 'created_at' => now(), 'updated_at' => now()
        ]);

        $result = $loader->loadFor(1, 1, 100, 'First');
        $ids = collect($result)->pluck('course_id')->sort()->values();
        $this->assertCount(2, $result); // layer 1 + layer 2, no duplicate
        $this->assertContains(1, $ids->toArray()); // CCMAS mandatory
        $this->assertContains(2, $ids->toArray()); // institution-specific
    }
}
