<?php

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use App\Models\StudentRegistrationVerification;
use Illuminate\Http\Request;
use Illuminate\View\View;

class JambController extends Controller
{
    /**
     * JAMB verification monitoring page.
     *
     * Provider outages are surfaced as provider/system failures, never merged
     * into "invalid student" counts — the platform's verification contract.
     */
    public function index(Request $request): View
    {
        $query = StudentRegistrationVerification::query();

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        if ($request->filled('from')) {
            $query->where('created_at', '>=', $request->date('from')->startOfDay());
        }

        if ($request->filled('to')) {
            $query->where('created_at', '<=', $request->date('to')->endOfDay());
        }

        $requests = $query->orderBy('created_at', 'desc')->paginate(20)->withQueryString();

        // Aggregate summary with explicit provider/system buckets.
        $total = StudentRegistrationVerification::count();
        $summary = [
            'total_requests' => $total,
            'verified' => StudentRegistrationVerification::where('status', 'verified')->count(),
            'not_found' => StudentRegistrationVerification::where('status', 'not_found')->count(),
            'invalid_input' => StudentRegistrationVerification::where('status', 'invalid_input')->count(),
            'manual_required' => StudentRegistrationVerification::where('status', 'manual_verification_required')->count(),
            'pending' => StudentRegistrationVerification::where('status', 'pending')->count(),
            'provider_failures' => StudentRegistrationVerification::whereIn('status', ['provider_timeout', 'provider_unavailable', 'temporary_failure'])->count(),
        ];

        $successRate = $total > 0 ? round(($summary['verified'] / $total) * 100, 1) : 0;

        // Status breakdown for the chart
        $statuses = StudentRegistrationVerification::query()
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->orderByDesc('count')
            ->get();

        return view('superadmin.jamb.index', [
            'requests' => $requests,
            'summary' => $summary,
            'successRate' => $successRate,
            'statuses' => $statuses,
            'filters' => $request->only(['status', 'from', 'to']),
        ]);
    }

    /**
     * Registration monitoring — all student registrations regardless of JAMB state.
     */
    public function registrations(Request $request): View
    {
        $query = StudentRegistrationVerification::with(['user']);

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        if ($request->filled('q')) {
            $term = $request->string('q');
            $query->where(function ($q) use ($term) {
                $q->where('jamb_registration_number', 'like', "%{$term}%")
                    ->orWhere('verified_name', 'like', "%{$term}%")
                    ->orWhereHas('user', fn ($uq) => $uq->where('email', 'like', "%{$term}%"));
            });
        }

        $registrations = $query->orderBy('created_at', 'desc')->paginate(20)->withQueryString();

        return view('superadmin.jamb.registrations', [
            'registrations' => $registrations,
            'filters' => $request->only(['status', 'q']),
        ]);
    }
}