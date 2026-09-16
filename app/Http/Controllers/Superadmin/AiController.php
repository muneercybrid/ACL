<?php

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use App\Models\ACLi\Request as AcliRequest;
use App\Models\ACLi\Conversation;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AiController extends Controller
{
    /**
     * ACLi AI activity oversight: usage, costs, providers, models.
     */
    public function index(Request $request): View
    {
        $query = AcliRequest::with(['user', 'conversation']);

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        $requests = $query->orderBy('created_at', 'desc')->paginate(25)->withQueryString();

        // Aggregates
        $summary = [
            'total_requests' => AcliRequest::count(),
            'successful' => AcliRequest::where('status', 'success')->count(),
            'failed' => AcliRequest::where('status', '!=', 'success')->count(),
            'total_tokens' => AcliRequest::sum('total_tokens') ?: 0,
            'input_tokens' => AcliRequest::sum('input_tokens') ?: 0,
            'output_tokens' => AcliRequest::sum('output_tokens') ?: 0,
            'estimated_cost' => round(AcliRequest::sum('estimated_cost') ?: 0, 4),
            'avg_latency_ms' => round(AcliRequest::avg('latency_ms') ?: 0, 0),
            'conversations' => Conversation::count(),
            'active_conversations' => Conversation::where('status', 'active')->count(),
        ];

        $providers = AcliRequest::query()
            ->selectRaw('provider, COUNT(*) as count')
            ->groupBy('provider')
            ->orderByDesc('count')
            ->get();

        $models = AcliRequest::query()
            ->selectRaw('model, COUNT(*) as count')
            ->whereNotNull('model')
            ->groupBy('model')
            ->orderByDesc('count')
            ->get();

        return view('superadmin.ai.index', [
            'requests' => $requests,
            'summary' => $summary,
            'providers' => $providers,
            'models' => $models,
            'filters' => $request->only(['status']),
        ]);
    }
}