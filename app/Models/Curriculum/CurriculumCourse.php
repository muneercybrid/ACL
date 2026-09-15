<?php

namespace App\Models\Curriculum;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class CurriculumCourse extends Model
{
    protected $table = 'curriculum_courses';

    protected $fillable = [
        'curriculum_version_id',
        'course_id',
        'level',
        'semester',
        'course_type',
        'credit_units',
        'status',
    ];

    public function curriculumVersion(): BelongsTo
    {
        return $this->belongsTo(CurriculumVersion::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Course::class);
    }

    /**
     * Get offerings for the linked course through the course relationship.
     */
    public function offerings(): HasMany
    {
        return $this->hasMany(\App\Models\CourseOffering::class, 'course_id', 'course_id');
    }
}
