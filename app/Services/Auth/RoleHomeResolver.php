<?php

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Decides which area of ACL a signed-in account belongs in.
 *
 * This exists because the redirect after login used to be "is this a student?"
 * and nothing else, so every institution administrator and every level
 * coordinator fell through to the same branch as each other and was sent to the
 * student dashboard. The answer is a property of the account's role
 * assignments, so it is resolved in one place and every caller agrees.
 *
 * The order below is precedence, not preference: the first matching role wins.
 * Platform administration is the most privileged thing an account can hold, and
 * a superadmin who also happens to be a student still administers.
 */
class RoleHomeResolver
{
    public const ROLE_STUDENT = 'student';
    public const ROLE_INSTITUTION_ADMIN = 'institution.admin';
    public const ROLE_LEVEL_COORDINATOR = 'level.coordinator';
    public const ROLE_SUPERADMIN = 'superadmin';

    /**
     * The routes a student may choose between on the sign-in screen, in the
     * order they are offered.
     */
    public const SELECTABLE = [
        self::ROLE_STUDENT,
        self::ROLE_INSTITUTION_ADMIN,
        self::ROLE_LEVEL_COORDINATOR,
    ];

    /**
     * @return array<int, string> the slugs of the roles this account holds
     */
    public function rolesFor(User $user): array
    {
        $user->loadMissing('roleAssignments.role');

        return $user->roleAssignments
            ->pluck('role')
            ->filter()
            ->pluck('slug')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * The single role slug that best describes this account, or null if it
     * holds none of the roles ACL routes.
     */
    public function primaryRole(User $user): ?string
    {
        $roles = $this->rolesFor($user);

        foreach ([self::ROLE_SUPERADMIN, self::ROLE_LEVEL_COORDINATOR, self::ROLE_INSTITUTION_ADMIN, self::ROLE_STUDENT] as $candidate) {
            if (in_array($candidate, $roles, true)) {
                return $candidate;
            }
        }

        // A student is also identified by the student record, not only by the
        // role assignment: an account created by a flow that set the record but
        // not the role is still a student and must not fall through to a
        // default that does not exist for them.
        if ($user->student) {
            return self::ROLE_STUDENT;
        }

        return null;
    }

    /**
     * Where this account should land after signing in.
     *
     * $requestedRole lets the sign-in screen honour the role the person picked.
     * It is only honoured when the account genuinely holds that role — the
     * caller is expected to have already checked that — so a mismatch here
     * falls back to the account's real primary role rather than granting it.
     */
    public function homeFor(User $user, ?string $requestedRole = null): string
    {
        $roles = $this->rolesFor($user);

        $role = ($requestedRole && in_array($requestedRole, $roles, true))
            ? $requestedRole
            : $this->primaryRole($user);

        return match ($role) {
            self::ROLE_SUPERADMIN => route('superadmin.dashboard'),
            self::ROLE_LEVEL_COORDINATOR => route('coordinator.dashboard'),
            self::ROLE_INSTITUTION_ADMIN => route('institution.dashboard'),
            default => route('student.dashboard'),
        };
    }

    public function holdsRole(User $user, string $slug): bool
    {
        return in_array($slug, $this->rolesFor($user), true);
    }
}
