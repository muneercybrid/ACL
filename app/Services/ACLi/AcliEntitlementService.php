<?php

namespace App\Services\ACLi;

use App\Models\User;
use App\Services\EntitlementService;

/**
 * ACLi Entitlement Service
 *
 * Determines whether a user is entitled to use specific ACLi AI capabilities.
 * Separate from ACL platform entitlements (course access, etc.).
 *
 * Current model: ACLi is FREE for all students. The paid gate can be
 * re-enabled later by setting ACLI_REQUIRE_ENTITLEMENT=true in .env
 * (and then implementing the subscription check in checkAcliEntitlement).
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
     * This is a paid capability separate from course enrollment.
     * Currently checks for a simple flag on the user - in production
     * this would check subscription/payment status.
     */
    protected function checkAcliEntitlement(User $user): bool
    {
        // TODO: Replace with actual subscription/entitlement check
        // For now, check if user has the 'acli_entitled' flag or is a test user
        // In production: check payment/subscription service

        // Allow test accounts for development
        $testEmails = [
            'student@acl.local',
            'admin@acl.local',
        ];

        if (in_array($user->email, $testEmails)) {
            return true;
        }

        // Check for ACLi entitlement flag (to be added to users table)
        // return (bool) $user->acli_entitled;

        // For now, deny by default - user must be granted entitlement
        return false;
    }
}