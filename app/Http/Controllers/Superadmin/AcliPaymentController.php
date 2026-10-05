<?php

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use App\Models\AcliSubscriptionSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;

class AcliPaymentController extends Controller
{
    /**
     * Display the ACLi payment settings page.
     */
    public function index(Request $request)
    {
        $settings = AcliSubscriptionSetting::current();
        $subscriptionService = app(\App\Services\ACLi\AcliSubscriptionService::class);

        $plans = $subscriptionService->listPlans();

        return view('superadmin.acli-payment.index', [
            'settings' => $settings,
            'plans' => $plans,
        ]);
    }

    /**
     * Update the ACLi payment settings.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'payment_enabled' => 'boolean',
            'payment_type' => 'in:one_time,monthly,yearly',
            'price' => 'numeric|min:0',
            'currency' => 'string|size:3',
            'grace_period_days' => 'integer|min:0|max:365',
            'description' => 'nullable|string|max:500',
        ]);

        $settings = AcliSubscriptionSetting::current();
        $settings->update($data);

        return back()->with('success', 'ACLi payment settings updated successfully.');
    }
}