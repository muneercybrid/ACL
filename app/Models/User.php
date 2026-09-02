<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = ['name', 'email', 'password'];
    protected $hidden = ['password', 'remember_token'];

    /** Memoised result of {@see isPlatformAdministrator()} for this instance. */
    protected ?bool $platformAdministrator = null;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function organizationMemberships(): HasMany
    {
        return $this->hasMany(OrganizationMembership::class);
    }

    public function roleAssignments(): HasMany
    {
        return $this->hasMany(RoleAssignment::class);
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    /**
     * Enterprise-grade scoped permission check.
     * Platform roles (entity_type = null) apply everywhere.
     * Scoped roles only apply to their specific entity.
     */
    public function hasPermission(string $permissionSlug, $entity = null): bool
    {
        $query = $this->roleAssignments()
            ->whereHas('role.permissions', fn($q) => $q->where('slug', $permissionSlug));

        if ($entity) {
            $query->where(function($q) use ($entity) {
                $q->whereNull('entity_type')
                  ->orWhere(function($q2) use ($entity) {
                      $q2->where('entity_type', get_class($entity))
                         ->where('entity_id', $entity->id);
                  });
            });
        } else {
            $query->whereNull('entity_type');
        }

        return $query->exists();
    }

    public function hasRole(string $roleSlug, $entity = null): bool
    {
        $query = $this->roleAssignments()
            ->whereHas('role', fn($q) => $q->where('slug', $roleSlug));

        if ($entity) {
            $query->where(function($q) use ($entity) {
                $q->whereNull('entity_type')
                  ->orWhere(function($q2) use ($entity) {
                      $q2->where('entity_type', get_class($entity))
                         ->where('entity_id', $entity->id);
                  });
            });
        } else {
            $query->whereNull('entity_type');
        }

        return $query->exists();
    }

    /**
     * Whether this user holds a platform-wide administrative role.
     *
     * Consumed by the Gate::before() bypass, so it runs on every
     * authorization check -- memoised to keep that to one pair of queries
     * per user instance rather than one pair per check.
     */
    public function isPlatformAdministrator(): bool
    {
        return $this->platformAdministrator ??= (
            $this->hasRole('super.admin') || $this->hasRole('platform.admin')
        );
    }
}
