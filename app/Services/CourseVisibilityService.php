<?php

namespace App\Services;

use App\Models\Curriculum\Course;
use App\Models\Curriculum\Programme;
use App\Models\Student;
use Illuminate\Database\Eloquent\Builder;

class CourseVisibilityService
{
    /**
     * Discipline IDs the student's programme belongs to.
     * This is the single source of truth for relevance.
     */
    public function studentDisciplineIds(Student $student): array
    {
        $record = $student->institutionRecords()->first();
        $programmeId = $record?->academic_program_id;

        if (! $programmeId) {
            return [];
        }

        return Programme::whereKey($programmeId)
            ->whereNotNull('nuc_discipline_id')
            ->pluck('nuc_discipline_id')
            ->all();
    }

    /**
     * External (professional) courses relevant to the student's discipline.
     * A Medicine student can NEVER receive Computing externals here.
     */
    public function visibleExternalForStudent(Student $student)
    {
        $ids = $this->studentDisciplineIds($student);

        return Course::where('is_external', true)
            ->where('is_active', true)
            ->whereHas('disciplines', fn (Builder $q) => $q->whereIn('nuc_disciplines.id', $ids))
            ->orderBy('difficulty')
            ->orderBy('code')
            ->get();
    }

    /**
     * Catalogue = external courses ONLY, filterable.
     */
    public function catalogue(?string $disciplineCode = null, ?string $difficulty = null, ?string $search = null)
    {
        return Course::where('is_external', true)
            ->where('is_active', true)
            ->when($disciplineCode, fn (Builder $q, $code) =>
                $q->whereHas('disciplines', fn (Builder $q2) => $q2->where('code', $code)))
            ->when($difficulty, fn (Builder $q, $d) => $q->where('difficulty', $d))
            ->when($search, fn (Builder $q, $s) => $q->where('title', 'like', "%{$s}%"))
            ->orderBy('code')
            ->paginate(24);
    }

    /**
     * My Courses = curriculum courses (existing service) + relevant externals.
     * Curriculum portion remains owned by StudentCurriculumService.
     */
    public function myCoursesExtras(Student $student)
    {
        return [
            'external_recommendations' => $this->visibleExternalForStudent($student),
            'discipline_ids' => $this->studentDisciplineIds($student),
        ];
    }
}
