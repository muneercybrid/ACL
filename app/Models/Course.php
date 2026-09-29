<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Course extends Model
{
    use HasFactory;

    protected $fillable = [
        'code', 'title', 'slug', 'credit_units', 'description', 'is_active',
        'normalized_code', 'normalized_title',
    ];

    protected function casts(): array
    {
        return [
            'credit_units' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * `normalized_code` and `normalized_title` are NOT NULL in the courses table
     * but were never written by application code, so anything that created a
     * Course without spelling them out failed on a missing default. Deriving
     * them here means the invariant holds for every write, not just the ones
     * that remember to.
     *
     * An explicitly supplied value is never overwritten, so an import that has
     * already normalised correctly keeps its own value.
     */
    protected static function booted(): void
    {
        static::saving(function (self $course): void {
            if (blank($course->normalized_code) && filled($course->code)) {
                $course->normalized_code = mb_strtoupper(trim($course->code));
            }

            if (blank($course->normalized_title) && filled($course->title)) {
                $course->normalized_title = mb_strtolower(trim($course->title));
            }
        });
    }

    public function offerings(): HasMany
    {
        return $this->hasMany(CourseOffering::class);
    }
    public function curriculumCourses(): \Illuminate\Database\Eloquent\Relations\HasMany { return $this->hasMany(\App\Models\Curriculum\CurriculumCourse::class); }
    public function chapters(): \Illuminate\Database\Eloquent\Relations\HasMany { return $this->hasMany(\App\Models\Curriculum\CourseChapter::class)->orderBy('position'); }
    public function outlines(): \Illuminate\Database\Eloquent\Relations\HasMany { return $this->hasMany(\App\Models\Curriculum\CourseOutline::class); }
    public function assessments(): \Illuminate\Database\Eloquent\Relations\HasMany { return $this->hasMany(\App\Models\Curriculum\Assessment::class); }
    public function disciplines(): \Illuminate\Database\Eloquent\Relations\BelongsToMany { return $this->belongsToMany(\App\Models\Curriculum\NucDiscipline::class, 'course_disciplines'); }
}
