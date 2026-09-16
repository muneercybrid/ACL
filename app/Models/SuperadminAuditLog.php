<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class SuperadminAuditLog extends Model
{
    use HasFactory;

    protected $table = 'superadmin_audit_logs';

    protected $fillable = [
        'actor_id',
        'actor_role',
        'acting_as_id',
        'action',
        'resource_type',
        'resource_id',
        'organization_id',
        'target_user_id',
        'ip_address',
        'user_agent',
        'request_id',
        'old_values',
        'new_values',
        'metadata',
        'severity',
        'result',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
            'metadata' => 'array',
            'resource_id' => 'integer',
            'target_user_id' => 'integer',
        ];
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function actingAs(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acting_as_id');
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function targetUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'target_user_id');
    }

    /**
     * Log a superadmin action.
     */
    public static function log(array $data): self
    {
        $request = request();

        return self::create(array_merge([
            'actor_id' => auth()->id(),
            'actor_role' => auth()->user()?->getHighestRoleSlug(),
            'acting_as_id' => session('impersonating_user_id'),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'request_id' => $request->header('X-Request-ID') ?? Str::uuid()->toString(),
        ], $data));
    }

    /**
     * Scope: filter by organization
     */
    public function scopeForOrganization($query, $organizationId)
    {
        return $query->where('organization_id', $organizationId);
    }

    /**
     * Scope: filter by date range
     */
    public function scopeDateRange($query, $from, $to)
    {
        return $query->whereBetween('created_at', [$from, $to]);
    }

    /**
     * Scope: filter by severity
     */
    public function scopeSeverity($query, $severity)
    {
        return $query->where('severity', $severity);
    }

    /**
     * Scope: filter by action
     */
    public function scopeAction($query, $action)
    {
        return $query->where('action', $action);
    }
}