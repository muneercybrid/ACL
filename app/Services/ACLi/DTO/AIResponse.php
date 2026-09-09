<?php

namespace App\Services\ACLi\DTO;

final readonly class AIResponse
{
    public function __construct(
        public string $content,
        public ?string $provider = null,
        public ?string $model = null,
        public ?int $inputTokens = null,
        public ?int $outputTokens = null,
        public ?int $totalTokens = null,
        public array $metadata = [],
    ) {}
}
