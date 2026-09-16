<?php

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use App\Models\SystemAlert;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AlertController extends Controller
{
    public function index(Request $request): View
    {
        $query = SystemAlert::with(['organization', 'acknowledgedBy']);

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        } else {
            $query->whereIn('status', ['active', 'acknowledged']);
        }

        if ($request->filled('severity')) {
            $query->where('severity', $request->string('severity'));
        }

        if ($request->filled('source')) {
            $query->where('source', $request->string('source'));
        }

        $alerts = $query->orderBy('created_at', 'desc')->paginate(20)->withQueryString();

        $counts = [
            'active' => SystemAlert::where('status', 'active')->count(),
            'acknowledged' => SystemAlert::where('status', 'acknowledged')->count(),
            'resolved' => SystemAlert::where('status', 'resolved')->count(),
        ];

        return view('superadmin.alerts.index', [
            'alerts' => $alerts,
            'counts' => $counts,
            'filters' => $request->only(['status', 'severity', 'source']),
        ]);
    }

    public function acknowledge(SystemAlert $alert): RedirectResponse
    {
        $alert->acknowledge(auth()->user());

        return back()->with('success', 'Alert acknowledged.');
    }

    public function resolve(SystemAlert $alert): RedirectResponse
    {
        $alert->resolve();

        return back()->with('success', 'Alert resolved.');
    }
}