<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Curriculum\CourseOutline;

class OutlineQuestion extends Model
{
    use HasFactory;

    protected $table = 'outline_questions';

    protected $fillable = [
        'course_outline_id',
        'question_text',
        'answer_key',
        'question_type',
        'options',
        'order',
    ];

    protected function casts(): array
    {
        return [
            'options' => 'array',
            'order' => 'integer',
        ];
    }

    public function courseOutline(): BelongsTo
    {
        return $this->belongsTo(CourseOutline::class);
    }
}
