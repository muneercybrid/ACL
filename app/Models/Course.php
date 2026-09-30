<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Course extends Model
{
    use HasFactory;

    /**
     * `courses` is the platform's single catalogue. One row per course, holding
     * its identity and — through the chapters, outlines and assessments
     * relations below — its content. Schools do not author a course each; they
     * point at a row here and code it their own way, so content is written once
     * and shared by every school that runs the course.
     *
     * `scope`, `source_type`, `verification_status`, `status`, `institution_id`,
     * `nuc_discipline_id`, `source_document` and `ccmas_course_id` were made
     * writable when centralisation landed; without them a course created from a
     * NUC row could not record where it came from, and the link that stops two
     * schools each minting their own copy of the same NUC course was lost.
     */
    protected $fillable = [
        'code', 'title', 'slug', 'credit_units', 'description', 'is_active',
        'normalized_code', 'normalized_title',
        'scope', 'source_type', 'verification_status', 'status',
        'institution_id', 'nuc_discipline_id', 'source_document', 'ccmas_course_id',
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

    /**
     * The NUC row this course was taken from, if any.
     *
     * Unique on the central course, so the same NUC course cannot become two
     * central courses. That single constraint is what makes sharing happen
     * rather than merely being possible.
     */
    public function ccmasCourse(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(CcmasCourse::class);
    }

    /**
     * Every school's local code for this course.
     */
    public function localCodes(): HasMany
    {
        return $this->hasMany(OrganizationCourseCode::class);
    }

    /**
     * The schools running this course.
     */
    public function organizations(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Organization::class, 'organization_course_codes')
            ->withPivot('local_code')
            ->withTimestamps();
    }
}
