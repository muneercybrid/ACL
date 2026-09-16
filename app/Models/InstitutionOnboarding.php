<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InstitutionOnboarding extends Model
{
    use HasFactory;

    protected $table = 'institution_onboardings';

    protected $fillable = [
        'organization_id',
        'status',
        'invited_by',
        'administrator_user_id',
        'invited_at',
        'started_at',
        'completed_at',
        'progress',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'progress' => 'array',
            'notes' => 'array',
            'invited_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    public function administrator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'administrator_user_id');
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function markStarted(): void
    {
        $this->update([
            'status' => 'started',
            'started_at' => $this->started_at ?? now(),
        ]);
    }

    public function markStepCompleted(string $step): void
    {
        $progress = $this->progress ?? [];
        $progress[$step] = true;
        $this->update(['progress' => $progress]);

        // Check if all required steps are done
        $requiredSteps = ['institution_info', 'admin_profile', 'academic_structure', 'staff_setup', 'content_setup'];
        $allDone = collect($requiredSteps)->every(fn ($step) => !empty($progress[$step]));

        if ($allDone && $this->status !== 'completed') {
            $this->complete();
        }
    }

    public function complete(): void
    {
        $this->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);
    }

    public function getCompletionPercentage(): int
    {
        $requiredSteps = ['institution_info', 'admin_profile', 'academic_structure', 'staff_setup', 'content_setup'];
        $progress = $this->progress ?? [];
        $done = collect($requiredSteps)->count(fn ($step) => !empty($progress[$step]));
        return (int) round(($done / count($requiredSteps)) * 100);
    }
}