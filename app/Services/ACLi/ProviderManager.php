<?php

namespace App\Services\ACLi;

use App\Services\ACLi\Contracts\AIProvider;
use InvalidArgumentException;

final class ProviderManager
{
    /**
     * @var array<string, AIProvider>
     */
    private array $providers = [];

    /**
     * Register a provider under its identifier.
     */
    public function register(AIProvider $provider): void
    {
        $this->providers[$provider->name()] = $provider;
    }

    /**
     * Resolve a provider by name.
     */
    public function provider(?string $name = null): AIProvider
    {
        $name ??= config('acli.default_provider');

        if (! isset($this->providers[$name])) {
            throw new InvalidArgumentException(
                "ACLi provider [{$name}] is not registered."
            );
        }

        return $this->providers[$name];
    }

    /**
     * Return all registered providers.
     *
     * @return array<string, AIProvider>
     */
    public function providers(): array
    {
        return $this->providers;
    }
}
