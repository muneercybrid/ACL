<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A student's identity as asserted by an external authority (currently JAMB).
 *
 * This is a verification record, never proof of validity on its own: a provider
 * outage must never remove the row, and an unverified row must never grant
 * entitlement on its own.
 */
class StudentExternalIdentity extends Model
{
    use HasFactory;

    protected $table = 'student_external_identities';

    protected $fillable = [
        'student_id',
        'provider',
        'identifier',
        'metadata',
        'status',
        'verified_at',
        'payload',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'payload' => 'array',
            'verified_at' => 'datetime',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /** True only for an explicit provider confirmation. */
    public function isVerified(): bool
    {
        return $this->status === 'verified' && $this->verified_at !== null;
    }
}
