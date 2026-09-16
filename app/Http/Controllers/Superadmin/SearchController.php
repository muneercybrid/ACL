<?php

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\Student;
use App\Models\SuperadminAuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SearchController extends Controller
{
    /**
     * Global search across permitted platform entities.
     */
    public function index(Request $request): View
    {
        $query = $request->string('q', '');
        $results = collect();

        if ($query->length() >= 2) {
            $term = '%' . $query . '%';

            // Users
            $users = User::where('name', 'like', $term)
                ->orWhere('email', 'like', $term)
                ->limit(10)
                ->get();

            $results['users'] = $users->map(fn ($u) => [
                'type' => 'User',
                'label' => $u->name . ' (' . $u->email . ')',
                'url' => route('superadmin.users.show', $u),
                'sub' => $u->getHighestRoleSlug() ?? 'No role',
            ]);

            // Institutions
            $institutions = Organization::where('name', 'like', $term)
                ->orWhere('slug', 'like', $term)
                ->orWhere('code', 'like', $term)
                ->limit(10)
                ->get();

            $results['institutions'] = $institutions->map(fn ($o) => [
                'type' => 'Institution',
                'label' => $o->name . ($o->code ? ' — ' . $o->code : ''),
                'url' => route('superadmin.institutions.show', $o),
                'sub' => $o->type . ' · ' . ($o->is_active ? 'Active' : 'Inactive'),
            ]);

            // Students (with institution context)
            $students = Student::whereHas('user', fn ($q) => $q->where('name', 'like', $term)->orWhere('email', 'like', $term))
                ->orWhereHas('user.organizationMemberships', fn ($q) => $q->where('matric_number', 'like', $term))
                ->limit(10)
                ->get();

            $results['students'] = $students->map(fn ($s) => [
                'type' => 'Student',
                'label' => $s->user?->name ?? 'Unknown',
                'url' => '#', // student profile URL not yet exposed to superadmin
                'sub' => 'Programme: ' . ($s->user?->organizationMemberships?->first()?->academicProgram?->name ?? 'N/A'),
            ]);

            // Audit events (recent, matching action/description)
            $audit = SuperadminAuditLog::where('description', 'like', $term)
                ->orWhere('action', 'like', $term)
                ->limit(5)
                ->get();

            $results['audit'] = $audit->map(fn ($a) => [
                'type' => 'Audit Event',
                'label' => $a->description ?? $a->action,
                'url' => route('superadmin.audit.show', $a),
                'sub' => $a->created_at?->format('M d, Y H:i') ?? '',
            ]);
        }

        $totalFound = collect($results)->sum(fn ($group) => $group->count());

        return view('superadmin.search.index', [
            'query' => $query,
            'results' => $results,
            'totalFound' => $totalFound,
            'hasQuery' => $query->length() >= 2,
        ]);
    }
}