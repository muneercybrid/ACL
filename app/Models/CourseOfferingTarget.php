<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CourseOfferingTarget extends Model
{
    use HasFactory;

    protected $fillable = ['course_offering_id', 'academic_program_id', 'level_id', 'is_mandatory'];

    protected function casts(): array
    {
        return ['is_mandatory' => 'boolean'];
    }

    public function courseOffering(): BelongsTo { return $this->belongsTo(CourseOffering::class); }
    public function academicProgram(): BelongsTo { return $this->belongsTo(AcademicProgram::class); }
    public function level(): BelongsTo { return $this->belongsTo(Level::class); }
}
