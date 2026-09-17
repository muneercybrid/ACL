<?php
namespace Tests\Feature\Architecture;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NucCcmArchitectureTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_seventeen_nuc_sources_registered(): void
    {
        $this->assertGreaterThanOrEqual(17, \App\Models\SourceDocument::count(),
            'All 17 NUC CCMAS discipline source documents must be registered');
    }

    public function test_course_provenance_fields_exist(): void
    {
        $cols = \Illuminate\Support\Facades\Schema::getColumnListing('courses');
        $this->assertContains('scope', $cols);
        $this->assertContains('source_type', $cols);
        $this->assertContains('verification_status', $cols);
        $this->assertContains('institution_id', $cols);
        $this->assertContains('provenance_notes', $cols);
    }

    public function test_course_mapping_table_exists(): void
    {
        $this->assertTrue(\Illuminate\Support\Facades\Schema::hasTable('course_mappings'));
    }

    public function test_orientation_tables_exist(): void
    {
        $this->assertTrue(\Illuminate\Support\Facades\Schema::hasTable('platform_orientations'));
        $this->assertTrue(\Illuminate\Support\Facades\Schema::hasTable('course_orientations'));
    }

    public function test_existing_curriculum_data_preserved(): void
    {
        $this->assertGreaterThanOrEqual(179, \App\Models\Course::count(),
            'Existing 179 university/institution courses must be preserved');
        $this->assertGreaterThanOrEqual(17, \App\Models\Curriculum\NucDiscipline::count(),
            'All 17 NUC disciplines preserved');
    }
}
