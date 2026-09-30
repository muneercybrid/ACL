<?php

namespace App\Http\Controllers\Institution;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Services\Auth\RoleHomeResolver;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The institution administrator's area.
 *
 * Every organization the account administers is listed, and the count is taken
 * from the account's own scoped assignments. Nothing here reads an
 * organization id from the request, so an administrator cannot reach another
 * institution's figures by editing a URL.
 */
class DashboardController extends Controller
{
    public function __construct(private readonly RoleHomeResolver $roles) {}

    public function index(Request $request): View
    {
        $user = $request->user();

        if (! $this->roles->holdsRole($user, RoleHomeResolver::ROLE_INSTITUTION_ADMIN)
            && ! $this->roles->holdsRole($user, RoleHomeResolver::ROLE_SUPERADMIN)) {
            abort(403, 'This account is not an institution administrator.');
        }

        $organizations = Organization::whereIn(
            'id',
            $user->administeredOrganizations()->select('organizations.id')
        )->orderBy('name')->get();

        return view('institution.dashboard', [
            'organizations' => $organizations,
            'organizationCount' => $organizations->count(),
        ]);
    }
}
