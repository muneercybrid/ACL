<?php

namespace App\Services\ACLi\Providers;

use App\Services\ACLi\Contracts\AIProvider;
use App\Services\ACLi\DTO\AIRequest;
use App\Services\ACLi\DTO\AIResponse;
use App\Services\ACLi\Exceptions\ProviderException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

final class OmniRouteProvider implements AIProvider
{
    public function chat(AIRequest $request): AIResponse
    {
        if (! $this->isAvailable()) {
            throw new ProviderException(
                'OmniRoute provider is not configured.',
                provider: $this->name(),
                retryable: false,
            );
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

        try {
            $response = $this->http()->post('/chat/completions', $payload);
        } catch (ConnectionException $exception) {
            throw new ProviderException(
                'OmniRoute API connection failed.',
                provider: $this->name(),
                retryable: true,
                previous: $exception,
            );
        }

        if ($response->failed()) {
            $status = $response->status();

            throw new ProviderException(
                "OmniRoute API request failed with HTTP status {$status}.",
                provider: $this->name(),
                status: $status,
                retryable: $status === 408
                    || $status === 425
                    || $status === 429
                    || $status >= 500,
            );
        }

        $data = $response->json();

        $content = data_get($data, 'choices.0.message.content');

        if (! is_string($content)) {
            throw new ProviderException(
                'OmniRoute API returned an invalid chat completion response.',
                provider: $this->name(),
                retryable: true,
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
        return 'omniroute';
    }

    public function isAvailable(): bool
    {
        return filled(config('acli.gateway.base_url'))
            && filled(config('acli.gateway.api_key'));
    }

    private function http(): PendingRequest
    {
        return Http::baseUrl(rtrim(config('acli.gateway.base_url'), '/'))
            ->withToken(config('acli.gateway.api_key'))
            ->acceptJson()
            ->timeout((int) config('acli.gateway.timeout', 120))
            ->connectTimeout((int) config('acli.gateway.connect_timeout', 10))
            ->retry((int) config('acli.gateway.max_retries', 2), 250);
    }

    private function nullableInt(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }
}