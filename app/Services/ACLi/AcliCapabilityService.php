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
     */
    public function getCapability(string $slug): ?array
    {
        $capabilities = $this->getAllCapabilities();
        return $capabilities[$slug] ?? null;
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