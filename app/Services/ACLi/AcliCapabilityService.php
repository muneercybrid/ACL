<?php

namespace App\Services\ACLi;

use App\Models\ACLi\Capability;
use Illuminate\Support\Facades\Auth;

/**
 * ACLi Capability Service
 *
 * Manages ACLi capabilities and their permission mappings.
 * Maps capabilities to ACL permissions for RBAC integration.
 */
class AcliCapabilityService
{
    /**
     * Get all registered capabilities from config.
     */
    public function getAllCapabilities(): array
    {
        return config('acli.capabilities', []);
    }

    /**
     * Get a capability by slug.
     *
     * Config keys the capability list by PHP array key ('student_chat') while the
     * capability slug -- the identifier the rest of ACLi passes around, and the one
     * stored against requests in the database -- is 'student.chat'. Matching on the
     * array key alone made every lookup miss, so permission checks silently
     * returned false for every capability. Match the slug first, then fall back to
     * the array key for callers that pass either form.
     */
    public function getCapability(string $slug): ?array
    {
        $capabilities = $this->getAllCapabilities();

        if (isset($capabilities[$slug])) {
            return $capabilities[$slug];
        }

        foreach ($capabilities as $key => $capability) {
            if (($capability['slug'] ?? $key) === $slug) {
                return $capability;
            }
        }

        return null;
    }

    /**
     * Get the permission slug for a capability.
     */
    public function getPermissionForCapability(string $capabilitySlug): ?string
    {
        $capability = $this->getCapability($capabilitySlug);
        return $capability['permission'] ?? null;
    }

    /**
     * Check if a user has permission for a capability.
     */
    public function userHasCapability($user, string $capabilitySlug): bool
    {
        $permission = $this->getPermissionForCapability($capabilitySlug);

        if (! $permission) {
            return false;
        }

        // Check platform-scoped permission (no entity)
        if ($user->hasPermission($permission)) {
            return true;
        }

        return false;
    }

    /**
     * Sync capabilities from config to database.
     */
    public function syncCapabilities(): int
    {
        $configCapabilities = $this->getAllCapabilities();
        $created = 0;

        foreach ($configCapabilities as $slug => $data) {
            $capability = Capability::updateOrCreate(
                ['slug' => $slug],
                [
                    'name' => $data['name'],
                    'description' => $data['description'] ?? null,
                    'is_active' => true,
                ]
            );

            if ($capability->wasRecentlyCreated) {
                $created++;
            }
        }

        return $created;
    }
}