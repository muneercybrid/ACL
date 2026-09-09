<?php

namespace App\Services\ACLi\Contracts;

use App\Services\ACLi\DTO\AIRequest;
use App\Services\ACLi\DTO\AIResponse;

interface AIProvider
{
    /**
     * Send a chat request to the provider.
     */
    public function chat(AIRequest $request): AIResponse;

    /**
     * Return the provider's identifier.
     */
    public function name(): string;

    /**
     * Determine whether the provider is configured and available.
     */
    public function isAvailable(): bool;
}
