<?php

namespace App\Services\ACLi\Providers;

use App\Services\ACLi\Contracts\AIProvider;
use App\Services\ACLi\DTO\AIRequest;
use App\Services\ACLi\DTO\AIResponse;
use App\Services\ACLi\Exceptions\ProviderException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Token Harbor provider (OpenAI-compatible gateway).
 *
 * Exposes an OpenAI-shaped /v1/chat/completions endpoint that fronts
 * multiple vendors (Claude, GPT, Gemini, Grok, Kimi, DeepSeek, Qwen,
 * GLM, MiMo). Free-tier models carry a ":free" suffix and cost nothing,
 * which makes Token Harbor the preferred primary for ACLi.
 *
 * Both the ":free" models and the paid tiers share this adapter — the
 * only difference is the model slug and the key.
 */
final class TokenHarborProvider implements AIProvider
{
    use StreamsViaBufferedCall;

    private const MAX_TRUNCATION_RETRIES = 4;
    private const MAX_QUOTA_RETRIES = 6;
    private const MAX_TOKEN_CEILING = 32000;

    public function chat(AIRequest $request): AIResponse
    {
        if (! $this->isAvailable()) {
            throw new ProviderException(
                'Token Harbor provider is not configured.',
                provider: $this->name(),
                retryable: false,
            );
        }

        $budget = $request->maxTokens;
        $truncations = 0;
        $quotaWaits = 0;

        while (true) {
            try {
                $response = $this->http()->post('/v1/chat/completions', $this->payload($request, $budget));
            } catch (ConnectionException $exception) {
                throw new ProviderException(
                    'Token Harbor API connection failed.',
                    provider: $this->name(),
                    retryable: true,
                    previous: $exception,
                );
            }

            if ($response->failed()) {
                $status = $response->status();

                // 429 rate limit, 402 balance exhausted, 5xx transient
                // — all recoverable with a backoff.
                if (in_array($status, [402, 408, 425, 429, 500, 502, 503, 504], true)
                    && $quotaWaits < self::MAX_QUOTA_RETRIES) {
                    $quotaWaits++;
                    $this->waitForCoolDown($response, $quotaWaits);
                    continue;
                }

                throw new ProviderException(
                    "Token Harbor API request failed with HTTP status {$status}: ".$this->errorMessage($response),
                    provider: $this->name(),
                    status: $status,
                    retryable: $status >= 500 || $status === 429,
                );
            }

            $data = $response->json();
            $content = data_get($data, 'choices.0.message.content');
            $finishReason = data_get($data, 'choices.0.finish_reason');

            // Empty content with finish_reason "length" means the
            // model hit its token ceiling mid-answer — widen and retry.
            if (! is_string($content) && $finishReason === 'length') {
                $truncations++;
                if ($truncations > self::MAX_TRUNCATION_RETRIES) {
                    throw new ProviderException(
                        'Token Harbor model exhausted its token budget before producing an answer.',
                        provider: $this->name(),
                        retryable: false,
                    );
                }
                $budget = $this->growBudget($budget);
                continue;
            }

            if (! is_string($content)) {
                throw new ProviderException(
                    'Token Harbor API returned an invalid chat completion response: '.$this->errorMessage($response),
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
                    'finish_reason' => $finishReason,
                    'token_budget' => $budget,
                    'truncation_retries' => $truncations,
                    'quota_waits' => $quotaWaits,
                ],
            );
        }
    }

    public function name(): string
    {
        return 'tokenharbor';
    }

    public function isAvailable(): bool
    {
        return filled(config('acli.tokenharbor.api_key'))
            && filled(config('acli.tokenharbor.base_url'));
    }

    /**
     * @param  array<string, mixed>  $request
     */
    private function payload(AIRequest $request, ?int $budget): array
    {
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

        if ($budget !== null) {
            $payload['max_tokens'] = $budget;
        }

        if ($request->seed !== null) {
            $payload['seed'] = $request->seed;
        }

        if ($request->options !== []) {
            $payload = array_replace_recursive($payload, $request->options);
        }

        return $payload;
    }

    /**
     * Wait out a rate-limit / balance cool-down, honouring Retry-After.
     */
    private function waitForCoolDown(Response $response, int $attempt): void
    {
        $retryAfter = $response->header('Retry-After');

        if (is_numeric($retryAfter) && (int) $retryAfter > 0) {
            $seconds = min((int) $retryAfter, 60);
        } else {
            $seconds = min(2 ** $attempt + random_int(0, 2), 30);
        }

        sleep($seconds);
    }

    private function growBudget(?int $budget): int
    {
        $grown = $budget === null || $budget <= 0
            ? 2000
            : $budget * 2;

        return min($grown, self::MAX_TOKEN_CEILING);
    }

    /**
     * Pull a human-readable message out of an OpenAI-style error body.
     */
    private function errorMessage(Response $response): string
    {
        $data = $response->json();

        return (string) (data_get($data, 'error.message')
            ?? data_get($data, 'message')
            ?? 'no error detail');
    }

    private function http(): PendingRequest
    {
        return Http::baseUrl(rtrim((string) config('acli.tokenharbor.base_url'), '/'))
            ->withToken((string) config('acli.tokenharbor.api_key'))
            ->acceptJson()
            ->timeout((int) config('acli.tokenharbor.timeout', 120))
            ->connectTimeout((int) config('acli.tokenharbor.connect_timeout', 10));
    }

    private function nullableInt(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }
}