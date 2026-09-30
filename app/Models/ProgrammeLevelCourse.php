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

    protected $fillable = [
        'academic_program_id',
        'level',
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
