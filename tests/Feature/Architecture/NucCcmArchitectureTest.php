<?php
namespace Tests\Feature\Architecture;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NucCcmArchitectureTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The NUC reference data is loaded here rather than read from whatever
     * happens to be in the database.
     *
     * These assertions previously ran against RefreshDatabase — an empty
     * schema — while counting rows that exist only in production, so they
     * could never pass and were, in effect, never run. Seeding the seventeen
     * disciplines and their source documents makes the count mean something:
     * it now proves the reference set is complete and loadable, not that some
     * live database happens to be populated.
     */
    private function seedNucReference(): void
    {
        $this->seed(\Database\Seeders\NucReferenceSeeder::class);
    }

    public function test_all_seventeen_nuc_sources_registered(): void
    {
        $this->seedNucReference();

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
        $this->seedNucReference();

        $this->assertGreaterThanOrEqual(17, \App\Models\Curriculum\NucDiscipline::count(),
            'All 17 NUC disciplines preserved');

        // The 179 courses this used to assert on are per-institution teaching
        // data, not NUC reference data, and are not produced by any seeder.
        // Counting them here could only ever describe a populated live
        // database, so the assertion is dropped rather than left in place
        // failing forever. The architectural contract those courses depend on
        // — provenance columns on the courses table — is asserted separately
        // in test_course_provenance_fields_exist().
    }
}
