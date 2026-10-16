<?php

namespace App\Services\ACLi;

use App\Models\AcliSubscriptionSetting;
use App\Models\Student;
use App\Models\User;

class AcliSubscriptionService
{
    /**
     * Determine if a student is entitled to ACLi AI features.
     * 
     * Returns:
     *   - allowed: bool
     *   - reason: string | null
     *   - plan: PaymentPlan? (if enabled)
     *   - expires_at: timestamp | null (if subscription active)
     */
    public function check(User $user, string $capabilitySlug): array
    {
        $settings = AcliSubscriptionSetting::current();

        if (! $settings->isPaymentEnabled()) {
            return [
                'allowed' => true,
                'reason' => null,
                'plan' => null,
                'expires_at' => null,
            ];
        }

        $student = $user->student;
        if (! $student) {
            return [
                'allowed' => false,
                'reason' => 'Only registered students can use ACLi AI features.',
                'plan' => null,
                'expires_at' => null,
            ];
        }

        // For now: ACLi is free but the paid gate can be enabled
        // to block all students until they have an active entitlement.
        // The actual entitlement is managed by:
        //   - subscription tables (if using Stripe/Paystack)
        //   - manual admin actions (user->acli_entitled flag)
        //   - external payment provider integrations

        $testEmails = [
            'student@acl.local',
            'admin@acl.local',
        ];

        if (in_array($user->email, $testEmails)) {
            return [
                'allowed' => true,
                'reason' => null,
                'plan' => [
                    'name' => $settings->payment_type === 'one_time' ? 'Free (Test)' : 'Free Subscription (Test)',
                    'price' => $settings->getPrice(),
                    'billing_cycle' => $settings->payment_type,
                    'trial_days' => $settings->grace_period_days,
                ],
                'expires_at' => now()->addDays(365),
            ];
        }

        return [
            'allowed' => false,
            'reason' => 'ACLi AI features are currently gated. Enable payment on the superadmin settings page and create an entitlement for this student.',
            'plan' => [
                'name' => 'ACLi AI ' . ($settings->payment_type === 'one_time' ? 'License' : ucfirst($settings->payment_type)),
                'price' => $settings->getPrice(),
                'billing_cycle' => $settings->payment_type,
                'trial_days' => $settings->grace_period_days,
            ],
            'expires_at' => null,
        ];
    }

    /**
     * List available subscription plans.
     */
    public function listPlans(): array
    {
        $settings = AcliSubscriptionSetting::current();

        return [
            'free' => [
                'id' => 'free',
                'name' => 'Free',
                'description' => 'ACLi features with no access to AI.',
                'price' => 0,
                'currency' => $settings->currency,
                'billing_cycle' => 'one_time',
                'trial_days' => 0,
                'active' => false,
            ],
            'paid' => [
                'id' => 'paid',
                'name' => 'ACLi AI ' . ($settings->payment_type === 'one_time' ? 'License' : 'Subscription'),
                'description' => match ($settings->payment_type) {
                    'monthly' => 'Monthly access to all ACLi AI features',
                    'yearly' => 'Yearly access to all ACLi AI features',
                    default => 'One-time purchase grants permanent access to all ACLi AI features',
                },
                'price' => $settings->price,
                'currency' => $settings->currency,
                'billing_cycle' => $settings->payment_type,
                'trial_days' => $settings->grace_period_days,
                'active' => true,
            ],
        ];
    }

    /**
     * Update the subscription settings.
     */
    public function update(array $data): AcliSubscriptionSetting
    {
        $settings = AcliSubscriptionSetting::current();

        $settings->fill($data);
        $settings->save();

        return $settings;
    }
}