<?php
namespace Database\Seeders;
use App\Models\Curriculum\Course;
use App\Models\Curriculum\CurriculumCourse;
use App\Models\Curriculum\CurriculumVersion;
use Illuminate\Database\Seeder;

class SeedAllZeroCourseVersions extends Seeder
{
    public function run(): void
    {
        $commonCodes = ['GST111','GST112','GST212','GST312','ENT211','ENT312','MTH101','MTH102','PHY101','PHY102','STA111','CHM101','CHM102'];
        $courses = Course::whereIn('normalized_code', $commonCodes)->get()->keyBy('normalized_code');
        
        $versions = CurriculumVersion::where('is_active', true)->get();
        $linked = 0;
        $fixed = 0;
        
        foreach ($versions as $v) {
            $existing = CurriculumCourse::where('curriculum_version_id', $v->id)->count();
            if ($existing === 0) {
                $fixed++;
                foreach ($commonCodes as $i => $code) {
                    $c = $courses->get($code); 
                    if (!$c) continue;
                    $level = in_array($code, ['MTH201','MTH202','ENT312','GST312']) ? 200 : 100;
                    $semester = $i % 2 === 0 ? 1 : 2;
                    CurriculumCourse::updateOrCreate(
                        ['curriculum_version_id' => $v->id, 'course_id' => $c->id, 'semester' => $semester],
                        ['level' => $level, 'course_type' => 'general_studies', 'credit_units' => $c->credit_units ?? 2, 'status' => 'active']
                    );
                    $linked++;
                }
            }
        }
        $this->command->info("Fixed {$fixed} zero-course versions, created {$linked} links.");
    }
}