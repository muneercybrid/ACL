<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Enrollment extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'course_offering_id',
        'source',
        'status',
        'enrolled_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'enrolled_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    /**
     * Enrollments that currently grant access.
     *
     * An enrollment row existing is not enough: it must still be active and
     * must not have expired. Authorization checks must always go through here
     * rather than testing for mere existence.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query
            ->where('status', 'active')
            ->where(fn (Builder $q) => $q
                ->whereNull('expires_at')
                ->orWhere('expires_at', '>', now()));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function courseOffering(): BelongsTo
    {
        return $this->belongsTo(CourseOffering::class);
    }
}
