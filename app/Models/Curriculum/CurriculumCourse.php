<?php

namespace App\Models\Curriculum;

use Illuminate\Database\Eloquent\Model;

class CurriculumCourse extends Model
{
    protected $table = 'curriculum_courses';
    protected $fillable = [
        'curriculum_version_id', 'course_id', 'level', 'semester',
        'course_type', 'credit_units', 'status',
    ];
}
