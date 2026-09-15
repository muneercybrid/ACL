<?php

use Illuminate\Support\Facades\DB;

namespace Database\Seeders;

use App\Models\Curriculum\Course;
use App\Models\Curriculum\NucDiscipline;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ExternalCatalogueSeeder extends Seeder
{
    public function run(): void
    {
        $catalogue = [
            'CMP' => [
                ['EXT-CMP-001','Ethical Hacking for Beginners','beginner'],
                ['EXT-CMP-002','Penetration Testing Fundamentals','intermediate'],
                ['EXT-CMP-003','Red Team Operations','advanced'],
                ['EXT-CMP-004','Malware Analysis','advanced'],
                ['EXT-CMP-005','Digital Forensics','intermediate'],
                ['EXT-CMP-006','Threat Hunting','advanced'],
                ['EXT-CMP-007','SOC Operations','intermediate'],
                ['EXT-CMP-008','Cloud Security','intermediate'],
                ['EXT-CMP-009','Bug Bounty Basics','intermediate'],
                ['EXT-CMP-010','Advanced Network Security','advanced'],
            ],
            'MED' => [
                ['EXT-MED-001','Medical Research Methods','intermediate'],
                ['EXT-MED-002','Advanced Clinical Research','advanced'],
                ['EXT-MED-003','Medical AI','advanced'],
                ['EXT-MED-004','Health Informatics','intermediate'],
            ],
            'ADM' => [
                ['EXT-ADM-001','Financial Modelling','intermediate'],
                ['EXT-ADM-002','Digital Marketing','beginner'],
                ['EXT-ADM-003','Project Management Professional Prep','intermediate'],
                ['EXT-ADM-004','Data Analysis for Business','intermediate'],
            ],
            'ENG' => [
                ['EXT-ENG-001','CAD Fundamentals','beginner'],
                ['EXT-ENG-002','Robotics and Automation Basics','intermediate'],
            ],
            'LAW' => [
                ['EXT-LAW-001','Legal Research and Writing','beginner'],
                ['EXT-LAW-002','Data Protection Law','intermediate'],
            ],
            'EDU' => [
                ['EXT-EDU-001','Educational Technology','beginner'],
                ['EXT-EDU-002','Instructional Design','intermediate'],
            ],
            'SCI' => [
                ['EXT-SCI-001','Laboratory QA/QC','intermediate'],
                ['EXT-SCI-002','Data Science with Python','intermediate'],
            ],
        ];

        $n = 0;
        foreach ($catalogue as $code => $courses) {
            $disc = NucDiscipline::where('code', $code)->first();
            if (! $disc) continue;
            foreach ($courses as [$code2, $title, $difficulty]) {
                $course = Course::firstOrCreate(
                    ['normalized_code' => strtoupper(preg_replace('/[^A-Z0-9]/', '', $code2))],
                    [
                        'code' => $code2, 'title' => $title, 'normalized_title' => strtolower($title),
                        'credit_units' => 0, 'status' => 'active', 'is_external' => true,
                        'difficulty' => $difficulty, 'verification_status' => 'verified',
                        'source_type' => 'platform', 'source_document' => 'ACL Professional Catalogue',
                        'slug' => Str::slug($title),
                    ]
                );
                DB::table('course_disciplines')->updateOrInsert(
                    ['course_id' => $course->id, 'nuc_discipline_id' => $disc->id],
                    ['course_id' => $course->id, 'nuc_discipline_id' => $disc->id]
                );
                $n++;
            }
        }
        $this->command->info("External catalogue courses: {$n}");
    }
}
