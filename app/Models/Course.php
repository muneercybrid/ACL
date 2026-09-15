<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Course extends Model
{
    use HasFactory;

    protected $fillable = ['code', 'title', 'slug', 'credit_units', 'description', 'is_active'];

    protected function casts(): array
    {
        return [
            'credit_units' => 'integer',
            'is_active' => 'boolean',
        ];
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
