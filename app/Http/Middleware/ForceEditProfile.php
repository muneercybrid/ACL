<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Confines a user who must set a fresh password to the profile page.
 *
 * What this replaces
 * ------------------
 * The previous implementation asked `$user->roles()`, a method ACL does not
 * have — ACL's RBAC is Authentication -> Role -> Permission -> Scope ->
 * Capability, expressed through `roleAssignments()` and the `hasRole()` helper,
 * not a `roles()` accessor. It then filtered on the substring 'institution.',
 * which matches neither the seeded slug `institution.admin` nor the provisioning
 * slug `institution_admin`, so the guard could never fire. Finally it decided
 * whether to intervene from `session('profile_edited')`, which is per-browser
 * and resets on a new device, so a user who never completed the change was let
 * back in on their next visit.
 *
 * The decision now comes from data that survives the session: the
 * `users.force_password_change` column, combined with the account actually
 * having an institution-scoped role.
 */
class ForceEditProfile
{
    /**
     * Routes the user is allowed to reach while the change is outstanding.
     * Anything else redirects to the profile page.
     *
     * @var list<string>
     */
    private const ALLOWED_ROUTES = [
        'password.set',
        'password.set.store',
        'logout',
        // The coordinator activation link is the way in, so it has to be
        // reachable while the gate is up. It is already signed and can only
        // reach an account that has not yet onboarded.
        'coordinator.activate',
        'coordinator.activate.store',
    ];

    /** Where the user is sent to satisfy the requirement. */
    private const TARGET_ROUTE = 'password.set';

    /**
     * Where a coordinator is sent. The activation link is the onboarding form:
     * there is no separate page, and there cannot be one, because a coordinator
     * who has not onboarded has no password and so cannot authenticate to reach
     * a route behind the auth middleware.
     */
    private const COORDINATOR_TARGET_ROUTE = 'coordinator.activate';

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return $next($request);
        }

        // A coordinator is routed to the full onboarding form rather than the
        // password-only page, because a coordinator has to establish a name, a
        // contact number and their own address as well as a password.
        if ($this->mustOnboard($user)) {
            return $this->confine($request, $next, self::COORDINATOR_TARGET_ROUTE, [
                'user' => $user->id,
            ]);
        }

        if (! $this->mustSetNewPassword($request, $user)) {
            return $next($request);
        }

        return $this->confine($request, $next, self::TARGET_ROUTE);
    }

    /**
     * Keep the user on the satisfying page until they are done.
     */
    private function confine(Request $request, Closure $next, string $target, array $parameters = []): Response
    {
        $routeName = $request->route()?->getName();

        // Already on an allowed page: let it render, otherwise the user would
        // be redirected to themselves forever.
        if ($routeName !== null && in_array($routeName, self::ALLOWED_ROUTES, true)) {
            return $next($request);
        }

        // A plain GET is redirected; a form POST is answered with a redirect
        // too, so a stale tab cannot submit a write elsewhere.
        return redirect()
            ->route($target, $parameters)
            ->with('status', 'Please complete your account setup before continuing.');
    }

    /**
     * Whether this account still owes ACL its own details.
     *
     * Applies to anyone flagged for onboarding, which is set when a level
     * coordinator is appointed. It is checked before the institution-admin
     * branch because a coordinator has no `institution_id` and would otherwise
     * never be asked.
     */
    private function mustOnboard(\App\Models\User $user): bool
    {
        return (bool) $user->must_complete_onboarding;
    }

    /**
     * The guard applies only to an institutional account that has not yet
     * completed its first-login password change. Students, superadmins and
     * externally-registered users are not institutional administrators and are
     * never trapped by this.
     */
    private function mustSetNewPassword(Request $request, \App\Models\User $user): bool
    {
        if (! $user->institution_id) {
            return false;
        }

        // isInstitutionAdmin(), not hasRole('institution.admin'): an
        // institution administrator's role is held against the institution
        // they administer, so hasRole() with no entity — which requires an
        // unscoped assignment — reports false for every real administrator.
        if (! $user->isInstitutionAdmin()) {
            return false;
        }

        return (bool) $user->force_password_change;
    }
}
