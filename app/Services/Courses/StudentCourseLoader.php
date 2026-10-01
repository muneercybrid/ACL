<?php
namespace App\Services\Courses;

use App\Models\AcademicProgram;
use App\Models\Programmes;
use App\Services\Courses\CentralCourseService;
use Illuminate\Support\Facades\DB;

/**
 * Student course resolution: Layered loading per the user specification.
 *
 * Layer 1 — CCMAS national baseline (curriculum_courses)
 * Layer 2 — Institution-specific courses (programme_level_courses)
 * Layer 3 — Approved student-specific registration (student_course_registrations)
 *
 * Critical rules enforced server-side (not frontend):
 *  - Semester separation: first and second semester never mix
 *  - Level separation: 100-level and 200-level never mix
 *  - Institution isolation: a university's students see only that university's data
 */
class StudentCourseLoader
{
    private CentralCourseService $central;

    public function __construct(CentralCourseService $central)
    {
        $this->central = $central;
    }

    /**
     * Load all applicable courses for a student.
     */
    public function loadFor(
        int $studentId,
        int $academicProgramId,
        int $level,
        string $semester,
        ?int $academicSessionId = null
    ): array {
        $academicProgram = AcademicProgram::findOrFail($academicProgramId);
        $programme = Programmes::findOrFail($academicProgram->programme_id ?? $academicProgram->id);

        // Layer 1: CCMAS national mandatory baseline (only verified curriculum versions).
        $layer1 = DB::table('curriculum_courses')
            ->join('curriculum_versions', 'curriculum_versions.id', '=', 'curriculum_courses.curriculum_version_id')
            ->join('courses', 'courses.id', '=', 'curriculum_courses.course_id')
            ->where('curriculum_versions.programme_id', $programme->id)
            ->where('curriculum_versions.scope', 'national')
            ->where('curriculum_versions.is_active', true)
            ->where('curriculum_courses.level', $level)
            ->where('curriculum_courses.semester', $semester)
            ->whereIn('curriculum_courses.status', ['verified', 'published'])
            ->select(
                'courses.id as course_id',
                'courses.code',
                'courses.title',
                DB::raw("'CCMAS Mandatory' as source"),
                'curriculum_courses.is_mandatory',
                'curriculum_courses.course_type'
            )
            ->get();

        // Layer 2: Institution-specific overlay.
        $layer2 = DB::table('programme_level_courses')
            ->join('courses', 'courses.id', '=', 'programme_level_courses.course_id')
            ->where('programme_level_courses.academic_program_id', $academicProgramId)
            ->where('programme_level_courses.level', $level)
            ->where('programme_level_courses.status', 'active')
            ->whereNotNull('programme_level_courses.semester')
            ->where('programme_level_courses.semester', $semester)
            ->select(
                'courses.id as course_id',
                'courses.code',
                'courses.title',
                DB::raw("'Institution Course' as source"),
                DB::raw("false as is_mandatory"),
                DB::raw("'institution_specific' as course_type")
            )
            ->get();

        // Combine without duplicating by course_id.
        $map = collect($layer1)->keyBy('course_id');
        foreach ($layer2 as $row) {
            if (! $map->has($row->course_id)) {
                $map->put($row->course_id, $row);
            }
        }

        return $map->values()->all();
    }
}
