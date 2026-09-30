<?php

namespace App\Models;


use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = ['name', 'email', 'password', 'jamb_registration_number_hash', 'provider', 'provider_id', 'avatar_path', 'institution_id', 'force_password_change', 'email_verified_at', 'must_complete_onboarding', 'onboarding_completed_at', 'phone'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            // Stored as tinyint(1). Without this cast the attribute hydrates as
            // 0 or 1 rather than a real boolean, so strict checks such as
            // assertFalse($user->force_password_change) fail even though the
            // stored value is correct.
            'force_password_change' => 'boolean',
            // Same reasoning as force_password_change above: the column is a
            // 0/1 tinyint, and the onboarding gate compares it as a boolean.
            'must_complete_onboarding' => 'boolean',
            'onboarding_completed_at' => 'datetime',
        ];
    }

    public function organizationMemberships(): HasMany
    {
        return $this->hasMany(OrganizationMembership::class);
    }

    /**
     * The institution this account belongs to.
     *
     * The column has existed on users since registration but had no relation,
     * so every caller had to hand-roll the lookup and some reached for the
     * Organization fork instead. Institutions is the authoritative record.
     */
    /**
     * The organization this account belongs to.
     *
     * The column is still called `institution_id`, but since the consolidation
     * it holds an `organizations` id: every body ACL works with now lives in
     * that one table, NUC-registered or not. The column is left as-is so the
     * rename does not become a schema migration touching a hot table; the
     * relation below is what defines its meaning.
     */
    public function institution(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'institution_id');
    }

    /** Unambiguous alias. Prefer this where the old name would mislead. */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'institution_id');
    }

    /**
     * The institutions this account administers, via its scoped role
     * assignments. A user may administer more than one.
     */
    public function administeredInstitutions(): BelongsToMany
    {
        return $this->belongsToMany(
            Organization::class,
            'role_assignments',
            'user_id',
            'entity_id'
        )
            ->where('role_assignments.entity_type', Organization::class)
            ->whereHas('roles', fn ($q) => $q->where('slug', 'institution.admin'));
    }

    /** The organizations this account administers. Same data, current name. */
    public function administeredOrganizations(): BelongsToMany
    {
        return $this->administeredInstitutions();
    }

    public function roleAssignments(): HasMany
    {
        return $this->hasMany(RoleAssignment::class);
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    public function student(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Student::class);
    }

    public function superadminAuditLogsAsActor(): HasMany
    {
        return $this->hasMany(SuperadminAuditLog::class, 'actor_id');
    }

    public function superadminAuditLogsAsTarget(): HasMany
    {
        return $this->hasMany(SuperadminAuditLog::class, 'target_user_id');
    }

    /**
     * Enterprise-grade scoped permission check.
     *
     * Scope rule (pinned by RbacScopingTest): a role assignment with a null
     * entity_type is platform-wide and applies everywhere; an assignment
     * carrying an entity applies only to that exact entity -- and asking
     * without an entity asks about platform scope only, which a scoped
     * assignment must never satisfy.
     */
    public function hasPermission(string $permissionSlug, $entity = null): bool
    {
        $query = $this->roleAssignments()
            ->whereHas('role.permissions', fn ($q) => $q->where('slug', $permissionSlug));

        if ($entity) {
            $query->where(function ($q) use ($entity) {
                $q->whereNull('entity_type')
                    ->orWhere(function ($q2) use ($entity) {
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
            ->whereHas('role', fn ($q) => $q->where('slug', $roleSlug));

        if ($entity) {
            $query->where(function ($q) use ($entity) {
                $q->whereNull('entity_type')
                    ->orWhere(function ($q2) use ($entity) {
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
     * Get the highest priority role slug for the user.
     * Platform-wide roles (null entity) take precedence.
     * Priority: superadmin > institution.admin > moderator > level.coordinator > tutor > student > external.learner
     */
    public function getHighestRoleSlug(): ?string
    {
        $priority = [
            'superadmin' => 100,
            'institution.admin' => 90,
            'moderator' => 80,
            'level.coordinator' => 70,
            'tutor' => 60,
            'student' => 50,
            'external.learner' => 40,
        ];

        $assignments = $this->roleAssignments()
            ->with('role')
            ->get()
            ->sortBy(fn ($a) => -($priority[$a->role->slug] ?? 0))
            ->first();

        return $assignments?->role?->slug;
    }

    /**
     * Check if user is a Superadmin (platform-wide).
     */
    public function isSuperadmin(): bool
    {
        return $this->hasRole('superadmin');
    }

    /**
     * Check if the user is an Institution Administrator.
     *
     * With an entity, the check is scoped to that entity: holding the role
     * against one institution is not evidence of authority over another, which
     * is the scope-isolation rule in AGENTS.md §6.
     *
     * With no entity, the question is "is this person an institution
     * administrator at all?" and the answer looks across every institution the
     * user is scoped to.
     *
     * The previous implementation required `entity_type IS NULL` in the
     * no-entity case, which meant it returned false for every genuine
     * institution administrator, because an institution administrator's role
     * is always held against the institution they administer. The role is
     * inherently scoped, so requiring an unscoped assignment described a
     * situation ACL does not create and never should.
     */
    public function isInstitutionAdmin($entity = null): bool
    {
        if ($entity !== null) {
            return $this->hasRole('institution.admin', $entity);
        }

        return $this->roleAssignments()
            ->whereHas('role', fn ($q) => $q->where('slug', 'institution.admin'))
            ->exists();
    }
}
