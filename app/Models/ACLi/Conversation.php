<?php

namespace App\Models\ACLi;

use App\Models\Chapter;
use App\Models\CourseOffering;
use App\Models\Lesson;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Conversation extends Model
{
    protected $table = 'acli_conversations';

    protected $fillable = [
        'user_id',
        'course_offering_id',
        'chapter_id',
        'lesson_id',
        'title',
        'status',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function courseOffering(): BelongsTo
    {
        return $this->belongsTo(CourseOffering::class);
    }

    public function chapter(): BelongsTo
    {
        return $this->belongsTo(Chapter::class);
    }

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function requests(): HasMany
    {
        return $this->hasMany(Request::class);
    }
}
