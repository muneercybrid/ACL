<?php
namespace App\Services\ACLi;
use App\Models\Student;
use App\Services\StudentDashboardService;

class CurriculumContextService
{
    public function __construct(protected StudentDashboardService $dash) {}

    public function forStudent(Student $student): array
    {
        $prog = $this->dash->curriculumProgramme($student);
        $version = $prog ? $prog->curriculumVersions()->where('is_active',true)->latest()->first() : null;
        $courses = $this->dash->programmeCourses($student);
        return [
            'student_id' => $student->id,
            'university_id' => $student->institution_id ?? null,
            'programme_id' => $prog?->id,
            'programme_name' => $prog?->name,
            'curriculum_version_id' => $version?->id,
            'level' => $student->level ?? 100,
            'semester_first' => $courses->where('semester',1)->pluck('course.code'),
            'semester_second' => $courses->where('semester',2)->pluck('course.code'),
            'source_documents' => $courses->pluck('curriculumVersion.source_document_id')->unique()->filter()->values(),
            'grounded_in_db' => true,
            'ai_must_not_invent' => true,
        ];
    }
}
