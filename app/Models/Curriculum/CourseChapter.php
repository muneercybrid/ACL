<?php

namespace App\Models\Curriculum;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CourseChapter extends Model
{
    use HasFactory;

    protected $table = 'course_chapters';

    protected $fillable = [
        'course_id',
        'position',
        'title',
        'slug',
        'introduction',
        'summary',
        'key_takeaways',
        'further_reading',
        'version',
        'status',
        'generated_by',
        'reviewed_by',
        'approved_by',
        'generated_at',
        'reviewed_at',
        'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'status' => 'string',
            'generated_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'approved_at' => 'datetime',
        ];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }
}