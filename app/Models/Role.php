<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'slug', 'scope_level', 'description', 'is_system'];

    protected function casts(): array
    {
        return ['is_system' => 'boolean'];
    }

    /**
     * `scope_level` is NOT NULL with no default, so a role created without it
     * fails at the database rather than at the point of the mistake.
     *
     * Defaulting to 'organization' is the narrowest scope in use — the safest
     * posture, because a role can only gain reach by asking for it. Platform
     * and level scopes stay explicit in production, where the existing roles
     * carry 'platform', 'organization' and 'level'.
     */
    protected static function booted(): void
    {
        static::creating(function (self $role): void {
            if (blank($role->scope_level)) {
                $role->scope_level = 'organization';
            }
        });
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'role_permissions');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(RoleAssignment::class);
    }
}
