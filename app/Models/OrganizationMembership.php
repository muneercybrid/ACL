<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrganizationMembership extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id', 'user_id', 'membership_type', 'status', 
        'joined_at', 'left_at', 'academic_program_id', 'current_level_id', 'matric_number',
    ];

    protected function casts(): array
    {
        return [
            'joined_at' => 'datetime',
            'left_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo { return $this->belongsTo(Organization::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function academicProgram(): BelongsTo { return $this->belongsTo(AcademicProgram::class); }
    public function currentLevel(): BelongsTo { return $this->belongsTo(Level::class); }
}
