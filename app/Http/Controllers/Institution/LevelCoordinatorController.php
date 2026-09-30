<?php

namespace App\Http\Controllers\Institution;

use App\Http\Controllers\Controller;
use App\Models\Curriculum\Programme;
use App\Models\LevelCoordinator;
use App\Models\Organization;
use App\Models\User;
use App\Services\Auth\LevelCoordinatorAppointer;
use App\Services\Auth\RoleHomeResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Institution administrators appoint their own level coordinators.
 *
 * The school is never taken on trust from the request. An administrator is
 * confined to the organizations their own role assignment names, so submitting
 * another school's id changes nothing — the id is resolved against that allowed
 * set and anything outside it is refused. This is ACL's scope rule applied at
 * the point where it would otherwise be easiest to skip.
 */
class LevelCoordinatorController extends Controller
{
    public function __construct(
        private readonly LevelCoordinatorAppointer $appointer,
        private readonly RoleHomeResolver $roles,
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $this->authorizeInstitutionStaff($user);
        $organizations = $this->permittedOrganizations($user);

        $organization = null;
        if ($organizations->isNotEmpty()) {
            $requested = $request->query('organization');

            // A requested school is honoured only if it is one of the
            // administrator's own; otherwise they see their first.
            $organization = $organizations->firstWhere('id', (int) $requested) ?? $organizations->first();
        }

        return view('institution.coordinators', [
            'organizations' => $organizations,
            'organization' => $organization,
            'appointments' => $organization ? $this->appointmentsFor($organization) : collect(),
            'programmes' => Programme::orderBy('name')->get(['id', 'name', 'code']),
            'levels' => LevelCoordinatorAppointer::LEVELS,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeInstitutionStaff($request->user());
        $organizations = $this->permittedOrganizations($request->user());

        if ($organizations->isEmpty()) {
            return back()->withErrors([
                'organization_id' => 'Your account is not scoped to any institution yet.',
            ]);
        }

        $validated = $request->validate([
            'organization_id' => ['required', 'integer'],
            'programme_id' => ['required', 'integer', 'exists:programmes,id'],
            'level' => ['required', 'integer', 'in:'.implode(',', LevelCoordinatorAppointer::LEVELS)],
            'name' => ['required', 'string', 'min:2', 'max:120'],
        ]);

        // The scope check. Without it this form could appoint a coordinator at
        // any of the 481 schools in the system.
        $organization = $organizations->firstWhere('id', (int) $validated['organization_id']);

        if (! $organization) {
            return back()->withErrors([
                'organization_id' => 'You can only appoint coordinators at your own institution.',
            ])->withInput();
        }

        $programme = Programme::findOrFail($validated['programme_id']);

        try {
            $result = $this->appointer->appoint(
                $organization,
                $programme,
                (int) $validated['level'],
                ['name' => $validated['name']],
            );
        } catch (\Throwable $e) {
            return back()->withErrors(['level' => $e->getMessage()])->withInput();
        }

        // Shown once, here, for the administrator to pass to the person
        // appointed. It is not emailed and not stored: the account holds an
        // unusable password until this link is used, so losing it means
        // appointing again rather than recovering it from anywhere.
        return redirect()
            ->route('institution.coordinators', ['organization' => $organization->id])
            ->with('activation', [
                'email' => $result['user']->email,
                'url' => $result['activation_url'],
            ]);
    }

    /**
     * Refuse anyone who does not administer an institution.
     *
     * Checked before any query runs. Without it a student or an external
     * learner reached this page and received a 200: they hold no institution
     * scope, so permittedOrganizations() returned an empty set and the view
     * rendered its "no institution assigned" branch. The page itself leaks the
     * shape of the appointment feature to accounts that have no business
     * knowing it exists.
     */
    private function authorizeInstitutionStaff(User $user): void
    {
        if ($this->roles->holdsRole($user, RoleHomeResolver::ROLE_INSTITUTION_ADMIN)) {
            return;
        }

        if ($this->roles->holdsRole($user, RoleHomeResolver::ROLE_SUPERADMIN)) {
            return;
        }

        abort(403, 'This account is not an institution administrator.');
    }

    /**
     * The organizations this administrator may act on.
     */
    private function permittedOrganizations(User $user)
    {
        if ($this->roles->holdsRole($user, RoleHomeResolver::ROLE_SUPERADMIN)) {
            return Organization::where('is_active', true)->orderBy('name')
                ->get(['id', 'name', 'short_name', 'slug', 'state']);
        }

        return $this->roles->organizationsFor($user);
    }

    private function appointmentsFor(Organization $organization)
    {
        return LevelCoordinator::where('organization_id', $organization->id)
            ->with(['programme' => fn ($q) => $q->select('id', 'name', 'code')])
            ->with('user')
            ->orderBy('programme_id')
            ->orderBy('level')
            ->get();
    }
}
