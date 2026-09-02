<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class CourseOffering extends Model
{
    use HasFactory;

    protected $fillable = ['course_id', 'semester_id', 'department_id', 'custom_code', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function course(): BelongsTo { return $this->belongsTo(Course::class); }
    public function semester(): BelongsTo { return $this->belongsTo(Semester::class); }
    public function department(): BelongsTo { return $this->belongsTo(Department::class); }

    public function targets(): HasMany
    {
        return $this->hasMany(CourseOfferingTarget::class);
    }

    public function chapters(): HasMany
    {
        return $this->hasMany(Chapter::class)->orderBy('position');
    }

    /**
     * Every lesson in this offering, through its chapters.
     *
     * Required by the ->scopeBindings() call on the nested lesson route: it
     * lets Laravel resolve {lesson} within {courseOffering}, so a lesson
     * belonging to a different offering 404s at binding time.
     */
    public function lessons(): HasManyThrough
    {
        return $this->hasManyThrough(Lesson::class, Chapter::class);
    }
}
