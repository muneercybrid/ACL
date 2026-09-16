<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class InstitutionStaffInvitation extends Model
{
    use HasFactory;

    protected $table = 'institution_staff_invitations';

    protected $fillable = [
        'organization_id',
        'email',
        'role_slug',
        'scope',
        'invited_by',
        'token',
        'expires_at',
        'accepted_at',
        'accepted_user_id',
    ];

    protected function casts(): array
    {
        return [
            'scope' => 'array',
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $invitation) {
            $invitation->token ??= Str::random(64);
            $invitation->expires_at ??= now()->addDays(7);
        });
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    public function acceptedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'accepted_user_id');
    }

    public function role(): BelongsTo
    {
        return Role::where('slug', $this->role_slug)->first();
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isAccepted(): bool
    {
        return !is_null($this->accepted_at);
    }

    public function accept(User $user): void
    {
        $this->update([
            'accepted_at' => now(),
            'accepted_user_id' => $user->id,
        ]);
    }

    public function getScopeDescription(): string
    {
        $scope = $this->scope ?? [];

        $parts = [];

        if (!empty($scope['academic_program_id'])) {
            $program = AcademicProgram::find($scope['academic_program_id']);
            if ($program) $parts[] = "Programme: {$program->name}";
        }

        if (!empty($scope['level_id'])) {
            $level = Level::find($scope['level_id']);
            if ($level) $parts[] = "Level: {$level->name}";
        }

        if (!empty($scope['faculty_id'])) {
            $faculty = Faculty::find($scope['faculty_id']);
            if ($faculty) $parts[] = "Faculty: {$faculty->name}";
        }

        if (!empty($scope['department_id'])) {
            $department = Department::find($scope['department_id']);
            if ($department) $parts[] = "Department: {$department->name}";
        }

        return $parts ? implode(' · ', $parts) : 'Institution-wide';
    }
}