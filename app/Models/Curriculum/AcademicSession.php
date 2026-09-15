<?php

namespace App\Models\Curriculum;

use Illuminate\Database\Eloquent\Model;

class AcademicSession extends Model
{
    protected $table = 'academic_sessions';
    protected $fillable = ['name', 'slug', 'start_year', 'end_year', 'is_current', 'status'];
    protected $casts = ['is_current' => 'boolean'];
}
