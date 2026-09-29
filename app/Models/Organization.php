<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Organization extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'normalized_name',
        'slug',
        'type',
        'code',
        'description',
        'is_active',
        'is_nuc_listed',
        'nuc_name',
        'nuc_normalized_name',
        'nuc_section',
        'nuc_source_ref',
        'source_verified_at',
        'synchronized_at',
        'import_batch',
        'official_name',
        'abbr',
        'ownership',
        'state',
        'established_year',
        'website',
        'short_name',
        'address',
        'logo_path',
        'status',
        'onboarded_at',
        'email',
        'onboarding_status',
        'canonical_organization_id',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_nuc_listed' => 'boolean',
        ];
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(OrganizationMembership::class);
    }

    public function faculties(): HasMany
    {
        return $this->hasMany(Faculty::class);
    }

    public function onboarding(): HasOne
    {
        return $this->hasOne(InstitutionOnboarding::class);
    }

    /**
     * Role assignments scoped to this organization.
     *
     * ACL's RBAC is Authentication -> Role -> Permission -> Scope ->
     * Capability, and scope is carried by a polymorphic row in
     * `role_assignments`. Since the consolidation these rows name
     * Organization, not Institution, so the relation has to live here too —
     * `User::administeredOrganizations()` filters on it and would otherwise
     * fatal with "Call to undefined method Organization::roles()".
     */
    public function roleAssignments(): HasMany
    {
        return $this->hasMany(RoleAssignment::class, 'entity_id')
            ->where('entity_type', self::class);
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(
            Role::class,
            'role_assignments',
            'entity_id',
            'role_id'
        )->where('role_assignments.entity_type', self::class);
    }

    /**
     * Accounts holding a role scoped to this organization, optionally
     * restricted to one role slug.
     */
    public function roleHolders(?string $roleSlug = null): HasMany
    {
        $holders = $this->roleAssignments()->whereHas(
            'user',
            fn ($q) => $q->whereNotNull('users.id')
        );

        if ($roleSlug !== null) {
            $holders->whereHas('role', fn ($q) => $q->where('slug', $roleSlug));
        }

        return $holders;
    }

    /** Whether this body appears on the NUC register. */
    public function isNucListed(): bool
    {
        return (bool) $this->is_nuc_listed;
    }
}
