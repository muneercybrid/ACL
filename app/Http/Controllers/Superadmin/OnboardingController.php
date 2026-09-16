<?php

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use App\Models\InstitutionOnboarding;
use App\Models\Organization;
use App\Models\SuperadminAuditLog;
use Illuminate\View\View;

class OnboardingController extends Controller
{
    public function index(): View
    {
        $onboardings = InstitutionOnboarding::with(['organization', 'administrator'])
            ->orderByRaw("FIELD(status, 'pending', 'invited', 'started', 'partially_completed', 'awaiting_review', 'completed', 'suspended')")
            ->orderBy('updated_at', 'desc')
            ->paginate(20);

        return view('superadmin.onboarding.index', ['onboardings' => $onboardings]);
    }

    public function show(Organization $organization): View
    {
        $onboarding = InstitutionOnboarding::firstOrCreate(
            ['organization_id' => $organization->id],
            ['status' => 'pending', 'invited_by' => auth()->id(), 'progress' => []]
        );

        $onboarding->load(['organization', 'administrator', 'invitedBy']);

        return view('superadmin.onboarding.show', compact('onboarding'));
    }
}