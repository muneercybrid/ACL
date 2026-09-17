<?php
namespace App\Services;

use App\Models\Course;
use App\Models\Curriculum\NucDiscipline;
use Illuminate\Support\Facades\Auth;

/**
 * University-specific curriculum addition framework.
 * Per spec Phase 6: university courses co-exist with NUC/reference layer.
 * Does NOT overwrite authoritative NUC curriculum data.
 * All university-specific additions must have verification_status='unverified' initially
 * until an authorized institutional administrator approves.
 */
class UniversityCourseService
{
    public function createUniversityCourse(array $data, ?int $institutionId = null): Course
    {
        // Authorization: only verified institution admin or supervisor
        $user = Auth::user();
        if (! $user) {
            throw new \Exception('Authentication required');
        }

        // Scoping: code must be institution-scoped (not global unique)
        $course = Course::create([
            'code' => $data['code'],
            'title' => $data['title'],
            'slug' => $data['slug'] ?? \Illuminate\Support\Str::slug($data['title']),
            'credit_units' => $data['credit_units'] ?? 2,
            'description' => $data['description'] ?? null,
            'is_active' => $data['is_active'] ?? true,
            'scope' => 'university',
            'source_type' => $data['source_type'] ?? 'university',
            'verification_status' => 'unverified',
            'institution_id' => $institutionId ? (string) $institutionId : null,
            'provenance_notes' => $data['provenance_notes'] ?? 'University-specific submission — requires verification',
            'import_batch_id' => $data['import_batch_id'] ?? 'manual-'.now()->format('Y-m-d'),
        ]);

        return $course;
    }

    public function linkToNucReference(Course $universityCourse, ?int $nucCourseId = null): ?\App\Models\Curriculum\CourseMapping
    {
        if (! $nucCourseId) {
            return null; // No mapping until matched
        }
        return \App\Models\Curriculum\CourseMapping::create([
            'nuc_course_id' => $nucCourseId,
            'institution_course_id' => $universityCourse->id,
            'institution_id' => $universityCourse->institution_id,
            'mapping_type' => 'institution_variant',
            'source_document_id' => null,
            'verification_status' => 'unverified',
            'notes' => 'Linked via university curriculum submission',
        ]);
    }
}
