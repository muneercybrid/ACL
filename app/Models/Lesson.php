<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Lesson extends Model
{
    use HasFactory;

    protected $fillable = ['chapter_id', 'title', 'slug', 'position', 'status'];

    public function chapter(): BelongsTo
    {
        return $this->belongsTo(Chapter::class);
    }

    public function blocks(): HasMany
    {
        return $this->hasMany(LessonBlock::class)->orderBy('position');
    }

    public function progress(): HasMany
    {
        return $this->hasMany(LessonProgress::class);
    }
}
