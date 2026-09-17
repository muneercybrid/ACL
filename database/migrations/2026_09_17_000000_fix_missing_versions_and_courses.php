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
        
        // Now add common courses to all versions with 0 courses
        $commonCodes = ['GST111','GST112','GST212','GST312','ENT211','ENT312','MTH101','MTH102','PHY101','PHY102','STA111','CHM101','CHM102'];
        $courseIds = DB::table('courses')
            ->whereIn('normalized_code', $commonCodes)
            ->pluck('id', 'normalized_code');
        
        $zeroCourseVersions = DB::table('curriculum_versions')
            ->where('is_active', true)
            ->whereDoesntHave('curriculumCourses')
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