<?php
namespace App\Services\Curriculum;

use App\Models\Curriculum\CurriculumVersion;
use App\Models\Curriculum\CcmasCourse;
use App\Models\Curriculum\Programme;
use Illuminate\Support\Facades\DB;

/**
 * Builds national curriculum versions from CCMAS source data.
 * The CCMAS document specifies level explicitly but does NOT specify
 * semester structurally (verified against storage/app/nuc-ccmas/*.txt).
 * Semester is assigned by convention (provisional) and must be verified
 * by a national-curriculum architect before the curriculum is published.
 */
class NationalCurriculumBuilder
{
    // Default semester convention by level (NOT inferred from code numbering,
    // but from standard NUC curriculum structure: lower levels = first semester,
    // upper levels = second semester, unless the programme duration indicates
    // otherwise). This is a provisional default — architect must confirm.
    private function defaultSemesterForLevel(int $level): ?string
    {
        return match ($level) {
            100, 200 => 'First',
            300, 400 => 'Second',
            500, 600, 800 => 'First', // Graduate: architect verifies
            default => null,
        };
    }

    /**
     * Build/update a national curriculum version for a NUC programme.
     */
    public function buildForProgramme(Programme $programme): CurriculumVersion
    {
        $version = CurriculumVersion::firstOrCreate([
            'programme_id' => $programme->id,
            'scope' => 'national',
            'source_type' => 'ccmas',
            'version_label' => 'NUC CCMAS ' . now()->year,
            'is_active' => false, // provisional until architect verifies semester/mandatory
        ], [
            'source_document' => 'nuc-ccmas-import',
            'verification_status' => 'candidate',
            'ccmas_baseline_percentage' => 70,
            'description' => 'Provisional national baseline derived from CCMAS source; semester and mandatory status require architect review.',
        ]);

        return $version;
    }

    /**
     * Import CCMAS courses for a programme into the national curriculum.
     */
    public function importForProgramme(int $programmeId, int $disciplineId): void
    {
        $programme = Programme::findOrFail($programmeId);
        $version = $this->buildForProgramme($programme);

        $courses = CcmasCourse::where('nuc_discipline_id', $disciplineId)
            ->where('status', 'imported')
            ->get();

        foreach ($courses as $ccmas) {
            $canonicalId = $this->canonicalCourseIdForCcmas($ccmas);
            // Only import CCMAS courses that have a canonical content link.
            // This prevents broken references; consolidation creates the links.
            if (! $canonicalId) {
                continue;
            }

            // Create/update national curriculum entry for this course.
            // Semester assigned provisionally; architect must verify.
            DB::table('curriculum_courses')->updateOrInsert(
                [
                    'curriculum_version_id' => $version->id,
                    'course_id' => $this->canonicalCourseIdForCcmas($ccmas),
                    'level' => $ccmas->level,
                    'semester' => DB::raw("'" . $this->defaultSemesterForLevel($ccmas->level) . "'"),
                    'course_type' => 'mandatory', // provisional; architect verifies
                    'is_mandatory' => true,
                ],
                [
                    'credit_units' => $ccmas->credit_units,
                    'status' => 'candidate',
                    'updated_at' => now(),
                ],
                [
                    'curriculum_version_id',
                    'course_id',
                    'level',
                    'semester',
                    'course_type',
                ]
            );
        }
    }

    private function canonicalCourseIdForCcmas(CcmasCourse $ccmas): ?int
    {
        // Return existing canonical content if linked; else null.
        // This is the content-sharing layer.
        return DB::table('courses')
            ->where('ccmas_course_id', $ccmas->id)
            ->value('id');
    }
}
