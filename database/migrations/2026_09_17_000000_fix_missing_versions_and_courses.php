<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // Add missing curriculum versions for Pharmacy & Social Sciences
        $versionsToCreate = [
            // Pharmacy
            ['programme_id' => 28, 'version_label' => 'CCMAS-2023'],
            
            // Social Sciences
            ['programme_id' => 33, 'version_label' => 'CCMAS-2023'],
            ['programme_id' => 34, 'version_label' => 'CCMAS-2023'],
            ['programme_id' => 35, 'version_label' => 'CCMAS-2023'],
        ];
        
        // Only create a version for a programme that actually exists.
        //
        // These rows are keyed to hard-coded programme ids, which is fine when
        // the programmes table is populated but invalid on a database where it
        // is not — the foreign key then rejects the insert and aborts the whole
        // migration batch. A schema migration must not assume prior data, so a
        // missing programme is skipped rather than fatal.
        $existingProgrammeIds = DB::table('programmes')
            ->whereIn('id', array_column($versionsToCreate, 'programme_id'))
            ->pluck('id')
            ->all();

        $versionsToCreate = array_values(array_filter(
            $versionsToCreate,
            fn ($v) => in_array($v['programme_id'], $existingProgrammeIds, true)
        ));

        foreach ($versionsToCreate as $v) {
            $exists = DB::table('curriculum_versions')
                ->where('programme_id', $v['programme_id'])
                ->where('version_label', $v['version_label'])
                ->exists();
            
            if (! $exists) {
                DB::table('curriculum_versions')->insert([
                    'programme_id' => $v['programme_id'],
                    'version_label' => $v['version_label'],
                    'slug' => 'ccmas-2023',
                    'scope' => 'nuc_baseline',
                    'verification_status' => 'verified',
                    'source_type' => 'nuc_ccmas',
                    'source_document' => 'NUC CCMAS 2023',
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
        
        // Now add common courses to all versions with 0 courses.
        //
        // "Has no courses" is expressed with whereNotExists rather than
        // whereDoesntHave: that method only exists on the Eloquent builder, and
        // on the query builder it is forwarded as a dynamic `where`, which
        // silently produces `where doesnt_have = <first argument>` and fails at
        // the database with a confusing unknown-column error.
        $commonCodes = ['GST111','GST112','GST212','GST312','ENT211','ENT312','MTH101','MTH102','PHY101','PHY102','STA111','CHM101','CHM102'];
        $courseIds = DB::table('courses')
            ->whereIn('normalized_code', $commonCodes)
            ->pluck('id', 'normalized_code');

        $zeroCourseVersions = DB::table('curriculum_versions')
            ->where('is_active', true)
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('curriculum_courses')
                    ->whereColumn('curriculum_courses.curriculum_version_id', 'curriculum_versions.id');
            })
            ->pluck('id');
        
        foreach ($zeroCourseVersions as $versionId) {
            foreach ($commonCodes as $i => $code) {
                $courseId = $courseIds->get($code);
                if (! $courseId) continue;
                
                $level = in_array($code, ['MTH201','MTH202','ENT312','GST312']) ? 200 : 100;
                $semester = $i % 2 === 0 ? 1 : 2;
                
                DB::table('curriculum_courses')->updateOrInsert(
                    ['curriculum_version_id' => $versionId, 'course_id' => $courseId, 'semester' => $semester],
                    [
                        'level' => $level,
                        'course_type' => 'general_studies',
                        'credit_units' => 2,
                        'status' => 'active',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
            }
        }
    }
    
    public function down(): void {}
};