<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A person responsible for one level of one programme.
 *
 * Scoped narrowly on purpose. A 100-level coordinator is not a 200-level
 * coordinator and does not coordinate a different programme — that
 * restriction is what the level_coordinators row encodes, and the same
 * granularity is mirrored onto the role assignment so authorization and
 * record-keeping cannot drift apart.
 */
class LevelCoordinator extends Model
{
    use HasFactory;

    protected $table = 'level_coordinators';

    protected $fillable = [
        'programme_id',
        'level',
        'user_id',
        'academic_session_id',
        'appointed_date',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'level' => 'integer',
            'appointed_date' => 'date',
        ];
    }

    public function programme(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Curriculum\Programme::class, 'programme_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function academicSession(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class, 'academic_session_id');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
