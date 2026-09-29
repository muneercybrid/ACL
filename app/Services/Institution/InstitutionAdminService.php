<?php

declare(strict_types=1);

namespace App\Services\Institution;

use App\Models\Institution;
use App\Models\Role;
use App\Models\RoleAssignment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Creates and maintains the Institution Administrator account for an
 * institution.
 *
 * Guarantees
 * ----------
 * Idempotent      Calling provision() any number of times leaves exactly one
 *                 administrator for the institution. A second call is a no-op.
 * Atomic          User creation and role assignment happen in one transaction,
 *                 so a failure cannot leave a user without a role or a role
 *                 without a user.
 * No orphans      The role is resolved BEFORE the user is created. The previous
 *                 implementation created the user and then called
 *                 Role::firstOrFail(), which threw after the insert and left
 *                 an account that could never log in.
 * No credential loss  An existing account is never overwritten. A manually
 *                 reset password, a changed name, or a deactivated account are
 *                 left exactly as the administrator set them.
 *
 * The password is generated with a cryptographic random source and returned
 * as a hash only; the plaintext is never persisted, logged, or returned.
 */
class InstitutionAdminService
{
    /**
     * The surviving institution administrator role.
     *
     * The platform previously carried a second role with the same display name
     * and a different slug ('institution_admin', no dot). It carried no
     * permissions, so all 474 provisioned accounts held authority that granted
     * nothing. Migration 2026_09_28_180000 consolidates them onto this slug,
     * which is the one RbacSeeder creates, User::getHighestRoleSlug() knows
     * about, and isInstitutionAdmin() tests for.
     */
    public const ROLE_SLUG = 'institution.admin';

    public function __construct(private readonly string $emailDomain = 'acl.local')
    {
    }

    /**
     * @return array{
     *   status: 'created'|'existing'|'role_restored'|'adopted',
     *   user_id: int,
     *   institution_id: int,
     *   password_hash_only?: true
     * }
     */
    public function provision(Institution $institution): array
    {
        // 1. Resolve the role first. If ACL has no institution_admin role there
        //    is nothing to provision, and we must not create a user first.
        $role = Role::where('slug', self::ROLE_SLUG)->first();

        if ($role === null) {
            throw new RuntimeException(
                'Role [' . self::ROLE_SLUG . '] does not exist. Refusing to create an account that cannot be granted authority.'
            );
        }

        // 2. Already has an administrator holding the role? Nothing to do.
        $holder = $this->administratorHoldingRole($institution, $role->id);
        if ($holder !== null) {
            return ['status' => 'existing', 'user_id' => $holder->id, 'institution_id' => $institution->id];
        }

        // 3. The account exists but the role is missing. This is the state a
        //    previously failed run leaves behind; repair it rather than
        //    creating a second account.
        $email = $this->emailFor($institution);
        $user = User::where('email', $email)->first();

        if ($user === null) {
            $user = $this->createUser($institution, $email, $role->id);

            return [
                'status' => 'created',
                'user_id' => $user->id,
                'institution_id' => $institution->id,
                'password_hash_only' => true,
            ];
        }

        // Existing account, missing authority. Adopt it: attach the role and
        // bind it to this institution, but never touch its credentials.
        $this->grantRole($user, $role->id, $institution);

        return ['status' => 'role_restored', 'user_id' => $user->id, 'institution_id' => $institution->id];
    }

    /**
     * Create the account and grant the role atomically.
     */
    private function createUser(Institution $institution, string $email, int $roleId): User
    {
        $password = Str::random(24) . bin2hex(random_bytes(16));

        return DB::transaction(function () use ($institution, $email, $password, $roleId) {
            $user = User::create([
                'name' => $this->accountName($institution),
                'email' => $email,
                'password' => Hash::make($password),
                'institution_id' => $institution->id,
                'email_verified_at' => null,
                // The account cannot be used until the holder sets a password.
                'force_password_change' => true,
            ]);

            $this->grantRole($user, $roleId, $institution);

            return $user;
        });
    }

    private function grantRole(User $user, int $roleId, Institution $institution): void
    {
        $already = RoleAssignment::where('user_id', $user->id)
            ->where('role_id', $roleId)
            ->where('entity_type', Institution::class)
            ->where('entity_id', $institution->id)
            ->exists();

        if ($already) {
            return;
        }

        RoleAssignment::create([
            'user_id' => $user->id,
            'role_id' => $roleId,
            'entity_type' => Institution::class,
            'entity_id' => $institution->id,
        ]);
    }

    private function administratorHoldingRole(Institution $institution, int $roleId): ?User
    {
        return User::whereExists(function ($query) use ($institution, $roleId) {
            $query->select(DB::raw(1))
                ->from('role_assignments')
                ->whereColumn('role_assignments.user_id', 'users.id')
                ->where('role_assignments.role_id', $roleId)
                ->where('role_assignments.entity_type', Institution::class)
                ->where('role_assignments.entity_id', $institution->id);
        })->first();
    }

    /**
     * Deterministic per institution, so a retry derives the same address and
     * finds the account it created last time instead of colliding with it.
     */
    private function emailFor(Institution $institution): string
    {
        return strtolower($this->emailDomain === 'acl.local'
            ? $this->localPartFor($institution) . '.' . $institution->id . '@acl.local'
            : $this->localPartFor($institution) . '.' . $institution->id . '@' . $this->emailDomain);
    }

    /**
     * Derived from the institution's own identifiers so the address is stable
     * across runs, and never from a counter that could collide.
     */
    private function localPartFor(Institution $institution): string
    {
        $abbr = trim((string) ($institution->abbr ?? ''));

        if ($abbr === '') {
            $words = preg_split('/\s+/', trim((string) $institution->name)) ?: [];
            $abbr = implode('', array_map(
                static fn (string $w) => mb_strtoupper(mb_substr(preg_replace('/[^A-Za-z]/', '', $w) ?: '', 0, 1)),
                array_slice($words, 0, 4)
            ));
        }

        $slug = preg_replace('/[^a-z0-9]+/', '', Str::lower($abbr)) ?: 'institution';

        return Str::limit($slug, 24, '');
    }

    private function accountName(Institution $institution): string
    {
        $name = trim((string) ($institution->name ?? '')) ?: 'Institution';

        return Str::limit($name . ' Administrator', 120, '');
    }
}
