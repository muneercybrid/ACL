<?php

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use App\Models\InstitutionOnboarding;
use App\Models\Organization;
use App\Models\SuperadminAuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuditController extends Controller
{
    public function index(Request $request): View
    {
        $query = SuperadminAuditLog::with(['actor', 'organization', 'targetUser']);

        // Filters
        if ($request->filled('q')) {
            $term = $request->string('q');
            $query->where(function ($q) use ($term) {
                $q->where('description', 'like', "%{$term}%")
                    ->orWhere('action', 'like', "%{$term}%");
            });
        }

        if ($request->filled('action')) {
            $query->where('action', 'like', "%{$request->string('action')}%");
        }

        if ($request->filled('severity')) {
            $query->where('severity', $request->string('severity'));
        }

        if ($request->filled('organization_id')) {
            $query->where('organization_id', $request->integer('organization_id'));
        }

        if ($request->filled('actor_id')) {
            $query->where('actor_id', $request->integer('actor_id'));
        }

        if ($request->filled('from')) {
            $query->where('created_at', '>=', $request->date('from')->startOfDay());
        }

        if ($request->filled('to')) {
            $query->where('created_at', '<=', $request->date('to')->endOfDay());
        }

        $logs = $query->orderBy('created_at', 'desc')->paginate(30)->withQueryString();

        $organizations = Organization::orderBy('name')->get();
        $actors = User::whereHas('superadminAuditLogsAsActor')->orderBy('name')->get();

        // Distinct action prefixes for the filter dropdown
        $actions = SuperadminAuditLog::query()
            ->distinct()
            ->pluck('action')
            ->map(fn ($a) => explode('.', $a)[0])
            ->unique()
            ->sort()
            ->values();

        return view('superadmin.audit.index', [
            'logs' => $logs,
            'organizations' => $organizations,
            'actors' => $actors,
            'actions' => $actions,
            'filters' => $request->only(['q', 'action', 'severity', 'organization_id', 'actor_id', 'from', 'to']),
        ]);
    }

    public function show(SuperadminAuditLog $log): View
    {
        $log->load(['actor', 'actingAs', 'organization', 'targetUser']);

        return view('superadmin.audit.show', ['log' => $log]);
    }
}