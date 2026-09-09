<?php

namespace App\Services\ACLi\Providers;

use App\Services\ACLi\Contracts\AIProvider;
use App\Services\ACLi\DTO\AIRequest;
use App\Services\ACLi\DTO\AIResponse;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class NvidiaProvider implements AIProvider
{
    public function chat(AIRequest $request): AIResponse
    {
        if (! $this->isAvailable()) {
            throw new RuntimeException('NVIDIA provider is not configured.');
        }

        $payload = [
            'model' => $request->model,
            'messages' => $request->messages,
            'stream' => false,
        ];

        if ($request->temperature !== null) {
            $payload['temperature'] = $request->temperature;
        }

        if ($request->topP !== null) {
            $payload['top_p'] = $request->topP;
        }

        if ($request->maxTokens !== null) {
            $payload['max_tokens'] = $request->maxTokens;
        }

        if ($request->seed !== null) {
            $payload['seed'] = $request->seed;
        }

        if ($request->options !== []) {
            $payload = array_replace_recursive($payload, $request->options);
        }

        $response = $this->http()->post('/chat/completions', $payload);

        if ($response->failed()) {
            $status = $response->status();

            throw new RuntimeException(
                "NVIDIA API request failed with HTTP status {$status}."
            );
        }

        $data = $response->json();

        $content = data_get($data, 'choices.0.message.content');

        if (! is_string($content)) {
            throw new RuntimeException(
                'NVIDIA API returned an invalid chat completion response.'
            );
        }

        $usage = data_get($data, 'usage', []);

        return new AIResponse(
            content: $content,
            provider: $this->name(),
            model: data_get($data, 'model', $request->model),
            inputTokens: $this->nullableInt(data_get($usage, 'prompt_tokens')),
            outputTokens: $this->nullableInt(data_get($usage, 'completion_tokens')),
            totalTokens: $this->nullableInt(data_get($usage, 'total_tokens')),
            metadata: [
                'id' => data_get($data, 'id'),
                'finish_reason' => data_get($data, 'choices.0.finish_reason'),
            ],
        );
    }

    public function name(): string
    {
        return 'nvidia';
    }

    public function isAvailable(): bool
    {
        return filled(config('services.nvidia.api_key'))
            && filled(config('services.nvidia.base_url'));
    }

    private function http(): PendingRequest
    {
        return Http::baseUrl(rtrim(config('services.nvidia.base_url'), '/'))
            ->withToken(config('services.nvidia.api_key'))
            ->acceptJson()
            ->timeout((int) config('acli.request.timeout', 60))
            ->connectTimeout((int) config('acli.request.connect_timeout', 10))
            ->retry((int) config('acli.request.max_retries', 2), 250);
    }

    private function nullableInt(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }
}
