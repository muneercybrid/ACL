<?php

namespace App\Services\ACLi\DTO;

final readonly class AIRequest
{
    public function __construct(
        public string $model,
        public array $messages,
        public ?float $temperature = null,
        public ?float $topP = null,
        public ?int $maxTokens = null,
        public ?int $seed = null,
        public array $options = [],
    ) {}
}
