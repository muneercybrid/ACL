<?php

namespace Database\Seeders;

use App\Models\Curriculum\Course;
use App\Models\Curriculum\CurriculumCourse;
use App\Models\Curriculum\CurriculumVersion;
use App\Models\Curriculum\NucDiscipline;
use App\Models\Curriculum\Programme;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CcmasAdminCurriculumSeeder extends Seeder
{
    public function run(): void
    {
        $session = \App\Models\Curriculum\AcademicSession::where('is_current', true)->first();
        $disc = NucDiscipline::where('code', 'ADM')->first();
        if (! $disc) { $this->command->error('ADM discipline missing'); return; }

        // NUC CCMAS Administration & Management: common 100-level core
        $common = [
            ['GST111','Communication in English',2], ['GST112','Nigerian Peoples and Culture',2],
            ['AMS101','Principles of Management',2], ['AMS102','Basic Mathematics',2],
            ['AMS103','Introduction to Computing',2], ['AMS104','Principles of Project Management',2],
        ];

        // Programme-specific 100-level courses (NUC CCMAS Administration & Management)
        $specific = [
            'B.Sc. Accounting' => [['ACC101','Introduction to Financial Accounting I',3],['ACC102','Introduction to Financial Accounting II',3]],
            'B.Sc. Actuarial Science' => [['ACS101','Introduction to Actuarial Science',3],['ACS102','Basic Mathematics for Actuarial Science',3],['ACS104','Elements of Actuarial Statistics',3]],
            'B.Sc. Aviation Management' => [['AVM101','Introduction to Aviation',2],['AVM102','Basic Concepts in Economics',3],['AVM103','Aviation Statistics',2],['AVM104','Introduction to Aviation Security',3],['AVM105','Principles of Flight',3],['AVM106','Airport Management',3],['AVM107','Computer Applications in Aviation',3]],
            'B.Sc. Business Information Technology' => [['BIT111','Introduction to Business Information Technology',2],['BIT112','Fundamentals of Business Information Storage & Retrieval',2],['BIT121','Elements of Digital Economy & Web Based Systems',2],['BIT122','E-Commerce and E-Business',2]],
            'B.Sc. Business Administration' => [['BUA101','Introduction to Business I',2],['BUA102','Introduction to Business II',2]],
            'B.Sc. Cooperatives and Rural Development' => [['CRD101','Introduction to Cooperatives',2],['CRD102','Organization and Management of Cooperatives',2],['CRD103','Rural Development I',2],['CRD104','Rural Development II',2],['CRD105','Nigerian Agriculture',2],['CRD106','Cooperative Education',2]],
            'B.Sc. Employment Relations and Human Resource Management' => [['EHR101','Introduction to Human Resource Management',2],['EHR102','Introduction to Employment Relations',2]],
            'B.Sc. Entrepreneurship' => [['ENT121','Introduction to Entrepreneurship & Venture Creation',3],['ENT122','The Nigerian Entrepreneurial Environment',2],['ENT124','Basic Financial Literacy',3],['ENT125','Business Statistics',2]],
            'B.Sc. Hospitality and Tourism Management' => [['HTM101','The Travel Concept',2],['HTM102','Nigeria Culture and Tourism',2],['HTM103','Earth and Environment',2],['HTM104','Fundamentals of Leisure and Recreation',2],['HTM105','Tourism Movements and Historical Developments',2]],
            'B.Sc Information Resources Management' => [['IRM102','Introduction to Information Science',3],['IRM108','Introduction to Reference Sources & Services',3],['IRM106','Introduction to Records and Information Management',3],['IRM107','Fundamentals of Information Resources Management',3]],
            'B.Sc Insurance' => [['INS101','Introduction to Insurance',3],['INS102','Principles and Practice of Insurance',3]],
            'B.Sc Local Government and Development Studies' => [['LGD101','Elements of Government',2],['LGD102','Introduction to Local Government',2],['LGD103','Introduction to Sociology',2],['LGD104','Introduction to Legal Studies',2],['LGD105','Principles of Economics',2],['LGD106','Elements of Administration',2]],
            'B.Sc. Marketing' => [['MKT111','Elements of Marketing',2],['MKT121','Marketing of Financial Services',2]],
            'B.Sc. Office and Information Management' => [['OIM111','Introduction to Computer Keyboarding & Shorthand Writing',2]],
            'B.SC Petroleum Information Management' => [['PIM101','Foundation of Information Management I',2],['PIM102','Foundations of Information Management II',2],['PIM103','Information Literacy, Organisation and Society',2],['PIM104','Information Life Cycle and Behaviour',2]],
            'B.Sc. Project Management' => [['PMG111','Business Analysis',2],['PMG112','Project Brief',2],['PMG121','Introduction to Project Methodologies',2],['PMG122','Introduction to Requirement Engineering',2]],
            'B.Sc. Public Administration' => [['PAD101','Elements of Public Administration',3]],
            'B.Sc Securities and Investments Management' => [['SIM111','Fundamentals of Securities and Investment I',2],['SIM112','Banking Technology',2],['SIM121','Fundamentals of Securities and Investment II',2]],
            'B.Sc. Taxation' => [['ACC101','Introduction to Financial Accounting I',3],['ACC102','Introduction to Financial Accounting II',3]],
            'B.Sc. Transport Management' => [['TPM101','Principles of Transport',2],['TPM102','Fundamental of Tourism',2],['TPM103','Introduction to Logistics Management',2],['TPM104','Introduction to Supply Chain Management',2]],
        ];

        $courseCache = [];
        $ensureCourse = function (string $code, string $title, int $units) use (&$courseCache) {
            $norm = strtoupper(preg_replace('/[^A-Z0-9]/', '', $code));
            if (isset($courseCache[$norm])) return $courseCache[$norm];
            $course = Course::firstOrCreate(
                ['normalized_code' => $norm],
                [
                    'code' => $code, 'title' => $title, 'normalized_title' => strtolower($title),
                    'credit_units' => $units, 'status' => 'active', 'is_external' => false,
                    'verification_status' => 'verified', 'source_type' => 'nuc_ccmas',
                    'source_document' => 'NUC CCMAS 2023 — Administration and Management',
                    'slug' => Str::slug($title),
                ]
            );
            $courseCache[$norm] = $course;
            return $course;
        };

        $programmes = Programme::where('nuc_discipline_id', $disc->id)->get();
        $linked = 0;

        foreach ($programmes as $programme) {
            $version = CurriculumVersion::firstOrCreate(
                ['programme_id' => $programme->id, 'version_label' => 'CCMAS-2023'],
                [
                    'academic_session_id' => $session?->id,
                    'slug' => Str::slug($programme->name) . '-ccmas-2023',
                    'scope' => 'nuc_baseline', 'verification_status' => 'verified',
                    'source_type' => 'nuc_ccmas',
                    'source_document' => 'NUC CCMAS 2023 — Administration and Management',
                    'is_active' => true,
                ]
            );

            $rows = array_merge($common, $specific[$programme->name] ?? []);
            foreach ($rows as $i => [$code, $title, $units]) {
                $course = $ensureCourse($code, $title, $units);
                $sem = $i % 2 === 0 ? 1 : 2;
                CurriculumCourse::firstOrCreate(
                    ['curriculum_version_id' => $version->id, 'course_id' => $course->id, 'semester' => $sem],
                    [
                        'level' => 100,
                        'course_type' => str_starts_with($code, 'GST') ? 'general_studies'
                            : (str_starts_with($code, 'AMS') || str_starts_with($code, 'ENT') ? 'core' : 'core'),
                        'credit_units' => $units, 'status' => 'active',
                    ]
                );
                $linked++;
            }
        }

        $this->command->info("CCMAS Admin curriculum linked: {$linked} curriculum_courses rows");
    }
}
