<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentRegistrationVerification extends Model
{
    use HasFactory;

    protected $fillable = [
        'token',
        'user_id',
        'status',
        'jamb_exam_year',
        'jamb_exam_type',
        'jamb_exam_value',
        'jamb_registration_number',
        'jamb_registration_number_hash',
        'school_registration_number',
        'verified_name',
        'verified_institution',
        'verified_programme',
        'jamb_status',
        'organization_id',
        'academic_program_id',
        'verification_metadata',
        'verified_at',
        'expires_at',
    ];

    protected $hidden = [
        'jamb_registration_number',
        'jamb_registration_number_hash',
        'verification_metadata',
    ];

    protected function casts(): array
    {
        return [
            'jamb_registration_number' => 'encrypted',
            'verification_metadata' => 'array',
            'verified_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function academicProgram(): BelongsTo
    {
        return $this->belongsTo(AcademicProgram::class);
    }

    public function isUsable(): bool
    {
        return $this->status === 'verified'
            && $this->expires_at?->isFuture();
    }
}
