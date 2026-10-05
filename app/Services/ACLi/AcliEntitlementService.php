<?php

namespace App\Services\ACLi;

use App\Models\AcliSubscriptionSetting;
use App\Models\User;
use App\Services\EntitlementService;

/**
 * ACLi Entitlement Service
 *
 * Determines whether a user is entitled to use specific ACLi AI capabilities.
 * Separate from ACL platform entitlements (course access, etc.).
 *
 * The paid gate is a single superadmin-editable row in
 * acli_subscription_settings: enable payment, set the
 * price, choose the payment type (one-time / monthly /
 * yearly) and the grace period. With payment disabled
 * every student is entitled; with it enabled the check
 * in checkAcliEntitlement runs.
 */
class AcliEntitlementService
{
    public function __construct(
        protected EntitlementService $entitlementService,
    ) {}

    /**
     * Check if a user is entitled to a specific ACLi capability.
     *
     * @param User $user
     * @param string $capabilitySlug e.g., 'student.chat', 'student.tutor', 'student.quiz'
     * @return array ['allowed' => bool, 'reason' => string|null, 'capability' => string]
     */
    public function check(User $user, string $capabilitySlug): array
    {
        // Map capability slug to config key (slug uses dots, config uses underscores)
        $configKey = str_replace('.', '_', $capabilitySlug);
        $capabilityConfig = config("acli.capabilities.{$configKey}");

        if (! $capabilityConfig) {
            return [
                'allowed' => false,
                'reason' => "Unknown ACLi capability: {$capabilitySlug}",
                'capability' => $capabilitySlug,
            ];
        }

        // ACLi must be globally enabled
        if (! config('acli.enabled', true)) {
            return [
                'allowed' => false,
                'reason' => 'ACLi is currently disabled.',
                'capability' => $capabilitySlug,
            ];
        }

        // Check if user is a student
        $student = $user->student;

        // For student capabilities, require student role.
        // ACLi is currently free — no subscription gate. Set
        // ACLI_REQUIRE_ENTITLEMENT=true to enable the paid gate later.
        if (str_starts_with($capabilitySlug, 'student.')) {
            if (! $student) {
                return [
                    'allowed' => false,
                    'reason' => 'Only registered students can use ACLi student features.',
                    'capability' => $capabilitySlug,
                ];
            }

            if (config('acli.require_entitlement')) {
                $hasAcliEntitlement = $this->checkAcliEntitlement($user);

                if (! $hasAcliEntitlement) {
                    return [
                        'allowed' => false,
                        'reason' => 'ACLi AI features require an active ACLi subscription. Please upgrade your account to access AI tutoring, quiz generation, and other AI features.',
                        'capability' => $capabilitySlug,
                    ];
                }
            }

            return [
                'allowed' => true,
                'reason' => null,
                'capability' => $capabilitySlug,
            ];
        }

        // For academic/admin capabilities, check role/permission
        if (str_starts_with($capabilitySlug, 'academic.') || str_starts_with($capabilitySlug, 'admin.')) {
            // These require academic/admin role with appropriate permissions
            $permission = $capabilityConfig['permission'] ?? null;

            if ($permission && $user->hasPermission($permission)) {
                return ['allowed' => true, 'reason' => null, 'capability' => $capabilitySlug];
            }

            return [
                'allowed' => false,
                'reason' => 'Insufficient permissions for academic/admin ACLi features.',
                'capability' => $capabilitySlug,
            ];
        }

        // For superadmin capabilities
        if (str_starts_with($capabilitySlug, 'superadmin.')) {
            $permission = $capabilityConfig['permission'] ?? null;

            if ($permission && $user->hasPermission($permission)) {
                return ['allowed' => true, 'reason' => null, 'capability' => $capabilitySlug];
            }

            return [
                'allowed' => false,
                'reason' => 'Insufficient permissions for super-admin ACLi features.',
                'capability' => $capabilitySlug,
            ];
        }

        return [
            'allowed' => false,
            'reason' => "Capability type not recognized: {$capabilitySlug}",
            'capability' => $capabilitySlug,
        ];
    }

    /**
     * Check if user has ACLi entitlement.
     *
     * The paid gate is configurable by the superadmin:
     * payment can be enabled or disabled, the price, the
     * payment type (one-time / monthly / yearly) and the
     * grace period are all stored in the single
     * acli_subscription_settings row. While payment is
     * disabled every student is entitled, which keeps the
     * free tier working until the admin turns the gate on.
     */
    protected function checkAcliEntitlement(User $user): bool
    {
        $settings = AcliSubscriptionSetting::current();

        // Payment gate disabled: every student is entitled.
        if (! $settings->isPaymentEnabled()) {
            return true;
        }

        // Gate enabled: the student needs an active
        // entitlement. The test accounts stay entitled so
        // the paid path can be exercised without charging.
        $testEmails = [
            'student@acl.local',
            'admin@acl.local',
        ];

        if (in_array($user->email, $testEmails)) {
            return true;
        }

        // A real subscription check would live here once a
        // payment provider is wired up. Until then the gate
        // denies, which is the safe default: an unconfigured
        // paid feature must not hand out access.
        return false;
    }
}