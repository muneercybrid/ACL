<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Institution extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'normalized_name',
        'ownership',
        'state',
        'established_year',
        'website',
        'nuc_source_ref',
        'nuc_section',
        'institution_status',
        'onboarding_status',
        'slug',
        'import_batch',
        'synchronized_at',
        'type',
        'source_verified_at',
        'canonical_institution_id',
    ];

    protected $casts = [
        'established_year' => 'integer',
        'synchronized_at' => 'datetime',
        'source_verified_at' => 'datetime',
        'canonical_institution_id' => 'integer',
    ];

    /**
     * A row is canonical when it does not point at another institution.
     * Duplicates (canonical_institution_id set) are a transitional state: the
     * NUC import recorded 146 institutions twice, and the duplicates cannot be
     * deleted yet because role_assignments still point at them.
     */
    public function isCanonical(): bool
    {
        return $this->canonical_institution_id === null;
    }

    public function isDuplicate(): bool
    {
        return ! $this->isCanonical();
    }

    public function canonicalInstitution(): self
    {
        return $this->belongsTo(self::class, 'canonical_institution_id');
    }

    public function duplicates(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(self::class, 'canonical_institution_id');
    }

    /**
     * Role assignments scoped to this institution.
     *
     * This is the "Scope" step of ACL's authorization chain
     * (Authentication -> Role -> Permission -> Scope -> Capability). The scope
     * is expressed by the polymorphic entity on the assignment, so there is no
     * role column on users and no hidden platform-wide bypass.
     */
    public function roleAssignments(): HasMany
    {
        return $this->hasMany(RoleAssignment::class, 'entity_id')
            ->where('entity_type', self::class);
    }

    /**
     * Roles held against this institution as the scope.
     */
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
     * Users who hold a role scoped to this institution.
     */
    public function roleHolders(): BelongsToMany
    {
        return $this->belongsToMany(
            User::class,
            'role_assignments',
            'entity_id',
            'user_id'
        )->where('role_assignments.entity_type', self::class);
    }
}
