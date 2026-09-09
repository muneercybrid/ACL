<?php

namespace App\Services\ACLi\Exceptions;

use RuntimeException;
use Throwable;

final class ProviderException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $provider,
        public readonly ?int $status = null,
        public readonly bool $retryable = false,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    /**
     * Determine whether this failure should allow model fallback.
     */
    public function isFallbackEligible(): bool
    {
        return $this->retryable;
    }
}
