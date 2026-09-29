<?php

namespace App\Models\Curriculum;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An assessment attached to a course (optionally scoped to a chapter).
 *
 * Assessments follow the Draft → Review → Approval → Publish flow, so a
 * non-published assessment must never be treated as available to a student.
 */
class Assessment extends Model
{
    use HasFactory;

    protected $table = 'assessments';

    protected $fillable = [
        'course_id',
        'chapter_id',
        'title',
        'scope',
        'description',
        'time_limit_minutes',
        'passing_score',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'time_limit_minutes' => 'integer',
            'passing_score' => 'decimal:2',
        ];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function chapter(): BelongsTo
    {
        return $this->belongsTo(CourseChapter::class);
    }

    public function isPublished(): bool
    {
        return $this->status === 'published';
    }
}
