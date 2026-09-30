<?php

namespace App\Models;

use App\Models\Curriculum\CcmasCourse;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A course one school has chosen to run for one of its programme offerings at
 * one level.
 *
 * Scoped to the school through `academic_programs.organization_id`, which is
 * what keeps two universities running the same programme independent of each
 * other. See the migration for why this is not written into
 * `curriculum_courses`.
 */
class ProgrammeLevelCourse extends Model
{
    use HasFactory;

    /**
     * `course_id` is the central course this offering shares content with, and
     * it is the reason this row is not a private copy. It was missing here when
     * the column was added, so every write silently dropped it and failed on a
     * NOT NULL column far from the cause — the same trap
     * `must_complete_onboarding` fell into on the users table.
     *
     * `ccmas_course_id` is retained and still written. It records which NUC
     * row seeded this offering, which is provenance rather than identity: the
     * identity is the central course, and that link lives on `courses`.
     */
    protected $fillable = [
        'academic_program_id',
        'level',
        'course_id',
        'ccmas_course_id',
        'course_code',
        'title',
        'credit_units',
        'source',
        'semester',
        'course_type',
        'is_mandatory',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'level' => 'integer',
            'credit_units' => 'float',
            'is_mandatory' => 'boolean',
        ];
    }

    /**
     * The shared course. Its content — chapters, outlines, assessments — is
     * reached through this one relationship, which is why a school adding a
     * course is reusing a platform's work rather than starting its own.
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function academicProgram(): BelongsTo
    {
        return $this->belongsTo(AcademicProgram::class);
    }

    /**
     * The NUC row this was taken from, when it was taken from the list.
     *
     * Nullable by design: a course the coordinator typed in themselves has no
     * CCMAS row, and that is a supported outcome rather than a defect.
     */
    public function ccmasCourse(): BelongsTo
    {
        return $this->belongsTo(CcmasCourse::class);
    }
}
