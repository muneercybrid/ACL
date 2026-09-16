<?php

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use App\Models\SuperadminAuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SecurityController extends Controller
{
    /**
     * Authentication & security observability.
     *
     * Sources: superadmin audit logs (login/suspension/permission events) plus
     * whatever auth events the platform records. Never exposes credentials.
     */
    public function index(Request $request): View
    {
        $eventTypes = [
            'login' => 'login',
            'user.suspended' => 'user.suspended',
            'user.restored' => 'user.restored',
            'user.roles_updated' => 'user.roles_updated',
            'staff.role_assigned' => 'staff.role_assigned',
            'institution.admin_assigned' => 'institution.admin_assigned',
            'role' => 'roles/permissions changes',
        ];

        $query = SuperadminAuditLog::with(['actor', 'targetUser']);

        if ($request->filled('event')) {
            $event = $request->string('event');
            if ($event === 'login') {
                $query->where('action', 'like', '%login%');
            } else {
                $query->where('action', $event);
            }
        }

        if ($request->filled('user_id')) {
            $query->where(fn ($q) => $q->where('actor_id', $request->integer('user_id'))->orWhere('target_user_id', $request->integer('user_id')));
        }

        if ($request->filled('from')) {
            $query->where('created_at', '>=', $request->date('from')->startOfDay());
        }

        if ($request->filled('to')) {
            $query->where('created_at', '<=', $request->date('to')->endOfDay());
        }

        $events = $query->orderBy('created_at', 'desc')->paginate(25)->withQueryString();

        // Security posture summary
        $summary = [
            'suspended_accounts' => User::whereNotNull('suspended_at')->count(),
            'high_severity_events_30d' => SuperadminAuditLog::where('severity', 'high')->where('created_at', '>=', now()->subDays(30))->count(),
            'administrative_actions_30d' => SuperadminAuditLog::where('created_at', '>=', now()->subDays(30))->count(),
        ];

        $users = User::orderBy('name')->limit(50)->get();

        return view('superadmin.security.index', [
            'events' => $events,
            'eventTypes' => $eventTypes,
            'summary' => $summary,
            'users' => $users,
            'filters' => $request->only(['event', 'user_id', 'from', 'to']),
        ]);
    }
}