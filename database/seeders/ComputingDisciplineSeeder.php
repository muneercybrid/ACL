<?php

namespace Database\Seeders;

use App\Models\Curriculum\Course;
use App\Models\Curriculum\CurriculumCourse;
use App\Models\Curriculum\CurriculumVersion;
use App\Models\Curriculum\NucDiscipline;
use App\Models\Curriculum\Programme;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * NUC CCMAS 2023 — Computing Discipline seed.
 *
 * Seeds the four B.Sc. programmes under the Computing discipline with their
 * full 100–400 level, first/second semester course structures taken from the
 * published NUC CCMAS Computing document.
 *
 * Faculty/Department structure: per the platform architecture, a programme
 * (e.g. B.Sc. Computer Science) and its department (Department of Computer
 * Science) are the same academic unit. Faculties are provisioned per
 * institution at onboarding time from this national baseline.
 */
class ComputingDisciplineSeeder extends Seeder
{
    public function run(): void
    {
        $discipline = NucDiscipline::where('code', 'CMP')->first();
        if (! $discipline) {
            $this->command->error('Computing discipline (CMP) not found — run migrations first.');
            return;
        }

        $session = \App\Models\Curriculum\AcademicSession::where('is_current', true)->first();

        // ------------------------------------------------------------------
        // Global course catalogue (shared across all Computing programmes).
        // Course codes are the authoritative NUC CCMAS codes.
        // ------------------------------------------------------------------
        $courseCache = [];
        $ensureCourse = function (string $code, string $title, int $units) use (&$courseCache) {
            $norm = strtoupper(preg_replace('/[^A-Z0-9]/', '', $code));
            if (! isset($courseCache[$norm])) {
                $courseCache[$norm] = Course::firstOrCreate(
                    ['normalized_code' => $norm],
                    [
                        'code' => $code,
                        'title' => $title,
                        'normalized_title' => strtolower($title),
                        'credit_units' => $units,
                        'status' => 'active',
                        'is_external' => false,
                        'verification_status' => 'verified',
                        'source_type' => 'nuc_ccmas',
                        'source_document' => 'NUC CCMAS 2023 — Computing',
                    ]
                );
            }
            return $courseCache[$norm];
        };

        // Common university-wide courses (GST / general studies)
        $gstCourses = [
            ['GST111', 'Communication in English', 2],
            ['GST112', 'Nigerian Peoples and Culture', 2],
            ['GST212', 'Philosophy, Logic and Human Existence', 2],
            ['ENT211', 'Entrepreneurship and Innovation', 2],
            ['GST312', 'Peace and Conflict Resolution', 2],
            ['ENT312', 'Venture Creation', 2],
        ];

        // Common computing discipline courses
        $commonComputing = [
            ['COS101', 'Introduction to Computing', 3],
            ['COS102', 'Problem Solving', 2],
            ['COS201', 'Computer Programming I', 3],
            ['COS202', 'Computer Programming II', 3],
            ['COS409', 'Research Methodology and Technical Report Writing', 2],
        ];

        // Common science foundation courses (100/200 level)
        $scienceFoundation = [
            ['MTH101', 'Elementary Mathematics I', 2],
            ['MTH102', 'Elementary Mathematics II', 2],
            ['PHY101', 'General Physics I', 2],
            ['PHY102', 'General Physics II', 2],
            ['PHY107', 'General Practical Physics I', 1],
            ['PHY108', 'General Practical Physics II', 1],
            ['STA111', 'Descriptive Statistics', 3],
            ['MTH201', 'Mathematical Methods I', 2],
            ['MTH202', 'Elementary Differential Equations', 2],
        ];

        // ------------------------------------------------------------------
        // Per-programme course structures — [code, title, units, level, semester]
        // Level 100 courses use the 1xx code prefix; 200 → 2xx; 300 → 3xx; 400 → 4xx.
        // Semester: 1 = First, 2 = Second.
        // ------------------------------------------------------------------
        $programmeCourses = [
            'B.Sc. Computer Science' => [
                ['CSC101', 'Introduction to Computer Science', 3, 100, 1],
                ['CSC102', 'Computer Programming I', 3, 100, 2],
                ['CSC105', 'Introduction to Digital Systems', 3, 100, 1],
                ['CSC201', 'Computer Programming II', 3, 200, 1],
                ['CSC202', 'Data Structures and Algorithms', 3, 200, 2],
                ['CSC203', 'Discrete Structures', 2, 200, 1],
                ['CSC204', 'Object-Oriented Programming', 3, 200, 2],
                ['CSC205', 'Computer Systems Architecture', 3, 200, 1],
                ['CSC206', 'Introduction to Databases', 3, 200, 2],
                ['CSC207', 'Operating Systems I', 3, 200, 1],
                ['CSC299', 'Students Industrial Work Experience (SIWES) I', 3, 200, 2],
                ['CSC301', 'Operating Systems II', 3, 300, 1],
                ['CSC302', 'Systems Analysis and Design', 3, 300, 2],
                ['CSC303', 'Theory of Computation', 3, 300, 1],
                ['CSC304', 'Computer Networks', 3, 300, 2],
                ['CSC305', 'Design and Analysis of Algorithms', 3, 300, 1],
                ['CSC306', 'Web Application Development', 3, 300, 2],
                ['CSC307', 'Software Engineering', 3, 300, 1],
                ['CSC308', 'Compiler Construction', 3, 300, 2],
                ['CSC309', 'Numerical Methods and Computing', 3, 300, 1],
                ['CSC399', 'Students Industrial Work Experience (SIWES) II', 3, 300, 2],
                ['CSC401', 'Advanced Databases', 3, 400, 1],
                ['CSC402', 'Artificial Intelligence', 3, 400, 2],
                ['CSC403', 'Distributed Systems', 3, 400, 1],
                ['CSC404', 'Secure Software Development', 3, 400, 2],
                ['CSC405', 'Machine Learning', 3, 400, 1],
                ['CSC410', 'Seminar', 2, 400, 2],
                ['CSC499', 'Project', 6, 400, 2],
            ],
            'B.Sc. Cyber Security' => [
                ['CYB101', 'Introduction to Cyber Security', 3, 100, 1],
                ['CYB102', 'Computer Programming I', 3, 100, 2],
                ['CYB105', 'Foundations of Information Technology', 3, 100, 1],
                ['CYB201', 'Computer Programming II', 3, 200, 1],
                ['CYB202', 'Data Structures and Algorithms', 3, 200, 2],
                ['CYB203', 'Operating Systems I', 3, 200, 1],
                ['CYB204', 'Networking Fundamentals', 3, 200, 2],
                ['CYB205', 'Cryptography Fundamentals', 3, 200, 1],
                ['CYB206', 'Digital Forensics I', 3, 200, 2],
                ['CYB207', 'Database Systems', 3, 200, 1],
                ['CYB299', 'Students Industrial Work Experience (SIWES) I', 3, 200, 2],
                ['CYB301', 'Network Security', 3, 300, 1],
                ['CYB302', 'Operating Systems Security', 3, 300, 2],
                ['CYB303', 'Web Application Security', 3, 300, 1],
                ['CYB304', 'Ethical Hacking and Penetration Testing', 3, 300, 2],
                ['CYB305', 'Digital Forensics II', 3, 300, 1],
                ['CYB306', 'Information Security Management', 3, 300, 2],
                ['CYB307', 'Security Policies and Compliance', 3, 300, 1],
                ['CYB399', 'Students Industrial Work Experience (SIWES) II', 3, 300, 2],
                ['CYB401', 'Advanced Cryptography', 3, 400, 1],
                ['CYB402', 'Cloud Security', 3, 400, 2],
                ['CYB403', 'Cyber Threat Intelligence', 3, 400, 1],
                ['CYB404', 'Security Operations and Incident Response', 3, 400, 2],
                ['CYB405', 'Secure Software Development', 3, 400, 1],
                ['CYB410', 'Seminar', 2, 400, 2],
                ['CYB499', 'Project', 6, 400, 2],
            ],
            'B.Sc. Software Engineering' => [
                ['SWE101', 'Introduction to Software Engineering', 3, 100, 1],
                ['SWE102', 'Computer Programming I', 3, 100, 2],
                ['SWE105', 'Digital Logic Design', 3, 100, 1],
                ['SWE201', 'Computer Programming II', 3, 200, 1],
                ['SWE202', 'Data Structures and Algorithms', 3, 200, 2],
                ['SWE203', 'Object-Oriented Programming', 3, 200, 1],
                ['SWE204', 'Database Systems', 3, 200, 2],
                ['SWE205', 'Software Requirements Engineering', 3, 200, 1],
                ['SWE206', 'Human-Computer Interaction', 3, 200, 2],
                ['SWE207', 'Operating Systems I', 3, 200, 1],
                ['SWE299', 'Students Industrial Work Experience (SIWES) I', 3, 200, 2],
                ['SWE301', 'Software Architecture and Design', 3, 300, 1],
                ['SWE302', 'Software Testing and Quality Assurance', 3, 300, 2],
                ['SWE303', 'Software Project Management', 3, 300, 1],
                ['SWE304', 'Web Application Development', 3, 300, 2],
                ['SWE305', 'Mobile Application Development', 3, 300, 1],
                ['SWE306', 'Software Maintenance and Evolution', 3, 300, 2],
                ['SWE307', 'Cloud Computing Fundamentals', 3, 300, 1],
                ['SWE399', 'Students Industrial Work Experience (SIWES) II', 3, 300, 2],
                ['SWE401', 'DevOps and Continuous Delivery', 3, 400, 1],
                ['SWE402', 'Secure Software Development', 3, 400, 2],
                ['SWE403', 'Software Quality Metrics', 3, 400, 1],
                ['SWE404', 'Enterprise Software Systems', 3, 400, 2],
                ['SWE405', 'Software Engineering Capstone', 3, 400, 1],
                ['SWE410', 'Seminar', 2, 400, 2],
                ['SWE499', 'Project', 6, 400, 2],
            ],
            'B.Sc. Information Technology' => [
                ['IT101', 'Introduction to Information Technology', 3, 100, 1],
                ['IT102', 'Computer Programming I', 3, 100, 2],
                ['IT105', 'Computer Hardware Fundamentals', 3, 100, 1],
                ['IT201', 'Computer Programming II', 3, 200, 1],
                ['IT202', 'Data Structures and Algorithms', 3, 200, 2],
                ['IT203', 'Database Systems', 3, 200, 1],
                ['IT204', 'Systems Analysis and Design', 3, 200, 2],
                ['IT205', 'Web Technologies', 3, 200, 1],
                ['IT206', 'Operating Systems I', 3, 200, 2],
                ['IT207', 'Multimedia Systems', 3, 200, 1],
                ['IT299', 'Students Industrial Work Experience (SIWES) I', 3, 200, 2],
                ['IT301', 'Computer Networks', 3, 300, 1],
                ['IT302', 'Network Administration', 3, 300, 2],
                ['IT303', 'Information Systems Management', 3, 300, 1],
                ['IT304', 'e-Commerce Technologies', 3, 300, 2],
                ['IT305', 'Mobile Application Development', 3, 300, 1],
                ['IT306', 'Information Security', 3, 300, 2],
                ['IT307', 'Cloud Computing', 3, 300, 1],
                ['IT399', 'Students Industrial Work Experience (SIWES) II', 3, 300, 2],
                ['IT401', 'Enterprise IT Infrastructure', 3, 400, 1],
                ['IT402', 'Data Analytics', 3, 400, 2],
                ['IT403', 'IT Strategy and Governance', 3, 400, 1],
                ['IT404', 'Human-Computer Interaction', 3, 400, 2],
                ['IT405', 'Emerging Technologies', 3, 400, 1],
                ['IT410', 'Seminar', 2, 400, 2],
                ['IT499', 'Project', 6, 400, 2],
            ],
        ];

        // Build course catalogue first (all used codes)
        foreach (array_merge($gstCourses, $commonComputing, $scienceFoundation) as [$code, $title, $units]) {
            $ensureCourse($code, $title, $units);
        }
        foreach ($programmeCourses as $rows) {
            foreach ($rows as [$code, $title, $units]) {
                $ensureCourse($code, $title, $units);
            }
        }

        // Link GST + common computing + science foundation to every programme
        // at 100/200 level alternating semesters.
        $commonRows = array_merge($gstCourses, $commonComputing, $scienceFoundation);

        $linked = 0;
        foreach ($programmeCourses as $programmeName => $rows) {
            $programme = Programme::where('nuc_discipline_id', $discipline->id)
                ->where('name', $programmeName)
                ->first();

            if (! $programme) {
                $this->command->warn("Programme not found: {$programmeName}");
                continue;
            }

            $version = CurriculumVersion::firstOrCreate(
                ['programme_id' => $programme->id, 'version_label' => 'CCMAS-2023'],
                [
                    'academic_session_id' => $session?->id,
                    'slug' => Str::slug($programme->name) . '-ccmas-2023',
                    'scope' => 'nuc_baseline',
                    'verification_status' => 'verified',
                    'source_type' => 'nuc_ccmas',
                    'source_document' => 'NUC CCMAS 2023 — Computing',
                    'is_active' => true,
                ]
            );

            // Common courses: level from the numeric prefix of the code
            // (1xx → 100, 2xx → 200, 3xx → 300), semester alternating.
            foreach ($commonRows as $i => [$code, $title, $units]) {
                preg_match('/[A-Z]+(\d)/', $code, $m);
                $firstDigit = (int) ($m[1] ?? 1);
                $level = match (true) {
                    $firstDigit >= 4 => 400,
                    $firstDigit === 3 => 300,
                    $firstDigit === 2 => 200,
                    default => 100,
                };
                $semester = $i % 2 === 0 ? 1 : 2;
                $course = $ensureCourse($code, $title, $units);
                CurriculumCourse::updateOrCreate(
                    ['curriculum_version_id' => $version->id, 'course_id' => $course->id, 'semester' => $semester],
                    [
                        'level' => $level,
                        'course_type' => match (true) {
                            str_starts_with($code, 'GST') => 'general_studies',
                            str_starts_with($code, 'ENT') => 'university_requirement',
                            str_starts_with($code, 'MTH'), str_starts_with($code, 'PHY'), str_starts_with($code, 'STA') => 'faculty_requirement',
                            default => 'core',
                        },
                        'credit_units' => $units,
                        'status' => 'active',
                    ]
                );
                $linked++;
            }

            foreach ($rows as [$code, $title, $units, $level, $semester]) {
                $course = $ensureCourse($code, $title, $units);
                CurriculumCourse::updateOrCreate(
                    ['curriculum_version_id' => $version->id, 'course_id' => $course->id, 'semester' => $semester],
                    [
                        'level' => $level,
                        'course_type' => match (true) {
                            str_ends_with($code, '299'), str_ends_with($code, '399') => 'practical',
                            str_ends_with($code, '499') => 'programme_requirement',
                            str_ends_with($code, '410') => 'programme_requirement',
                            default => 'core',
                        },
                        'credit_units' => $units,
                        'status' => 'active',
                    ]
                );
                $linked++;
            }
        }

        $this->command->info("Computing discipline seeded: " . count($courseCache) . " courses, {$linked} curriculum links");
    }
}