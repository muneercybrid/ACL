<?php

namespace App\Models\Curriculum;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CourseOutline extends Model
{
    use HasFactory;

    protected $table = 'course_outlines';

    protected $fillable = [
        'course_id',
        'version',
        'description',
        'learning_outcomes',
        'recommended_resources',
        'status',
        'created_by',
        'approved_by',
        'approved_at',
        'is_locked',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'learning_outcomes' => 'array',
            'recommended_resources' => 'array',
            'status' => 'string',
            'is_locked' => 'boolean',
            'approved_at' => 'datetime',
        ];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }
}