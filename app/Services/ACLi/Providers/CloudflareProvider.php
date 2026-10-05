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
 * Cloudflare Workers AI provider.
 *
 * Cloudflare's /ai/run/{model} endpoint is NOT OpenAI-shaped: it
 * accepts {messages, max_tokens} and returns
 * {success, result: {response, ...}}. This adapter translates both
 * directions so the rest of ACLi sees the same AIResponse contract
 * as every other provider.
 *
 * Cloudflare's free tier exposes a small set of models
 * (@cf/meta/llama-3.2-1b-instruct) with no observed rate limit
 * under ordinary load. Image models require a Workers Paid plan.
 */
final class CloudflareProvider implements AIProvider
{
    use StreamsViaBufferedCall;

    private const MAX_TRUNCATION_RETRIES = 4;
    private const MAX_QUOTA_RETRIES = 6;
    private const MAX_TOKEN_CEILING = 32000;

    public function chat(AIRequest $request): AIResponse
    {
        if (! $this->isAvailable()) {
            throw new ProviderException(
                'Cloudflare provider is not configured.',
                provider: $this->name(),
                retryable: false,
            );
        }

        $accountId = (string) config('acli.cloudflare.account_id');
        $model = $request->model;
        $budget = $request->maxTokens;
        $truncations = 0;
        $quotaWaits = 0;

        while (true) {
            $url = $this->endpoint($accountId, $model);

            try {
                $response = $this->http()->post($url, $this->payload($request, $budget));
            } catch (ConnectionException $exception) {
                throw new ProviderException(
                    'Cloudflare API connection failed.',
                    provider: $this->name(),
                    retryable: true,
                    previous: $exception,
                );
            }

            if ($response->failed()) {
                $status = $response->status();

                if ($status === 429 && $quotaWaits < self::MAX_QUOTA_RETRIES) {
                    $quotaWaits++;
                    $this->waitForCoolDown($response, $quotaWaits);
                    continue;
                }

                throw new ProviderException(
                    "Cloudflare API request failed with HTTP status {$status}.",
                    provider: $this->name(),
                    status: $status,
                    retryable: $status === 408
                        || $status === 425
                        || $status === 429
                        || $status >= 500,
                );
            }

            $data = $response->json();

            // Cloudflare signals failure inside a 200 body via
            // success=false plus an errors array.
            if (($data['success'] ?? null) === false) {
                $errors = $data['errors'] ?? [];
                $code = $errors[0]['code'] ?? null;
                $message = $errors[0]['message'] ?? 'Unknown Cloudflare error';

                // 7000-series errors can be plan gating (image routes
                // require Workers Paid). Not retryable without a plan
                // change, so surface it rather than looping.
                throw new ProviderException(
                    "Cloudflare model error: {$message}",
                    provider: $this->name(),
                    status: $response->status(),
                    retryable: false,
                );
            }

            $result = $data['result'] ?? [];
            $content = $result['response'] ?? null;

            if (! is_string($content)) {
                // An empty response usually means the model ran out
                // of room; widen the budget and ask again.
                $truncations++;
                if ($truncations > self::MAX_TRUNCATION_RETRIES) {
                    throw new ProviderException(
                        'Cloudflare model returned an empty response.',
                        provider: $this->name(),
                        retryable: false,
                    );
                }
                $budget = $this->growBudget($budget);
                continue;
            }

            return new AIResponse(
                content: $content,
                provider: $this->name(),
                model: $model,
                inputTokens: $this->nullableInt($result['usage']['prompt_tokens'] ?? null),
                outputTokens: $this->nullableInt($result['usage']['completion_tokens'] ?? null),
                totalTokens: $this->nullableInt($result['usage']['total_tokens'] ?? null),
                metadata: [
                    'id' => $result['id'] ?? null,
                    'finish_reason' => $result['finish_reason'] ?? null,
                    'token_budget' => $budget,
                    'truncation_retries' => $truncations,
                    'quota_waits' => $quotaWaits,
                ],
            );
        }
    }

    public function name(): string
    {
        return 'cloudflare';
    }

    public function isAvailable(): bool
    {
        return filled(config('acli.cloudflare.api_token'))
            && filled(config('acli.cloudflare.account_id'))
            && filled(config('acli.cloudflare.model'));
    }

    /**
     * Build the per-request URL for the configured account and model.
     */
    private function endpoint(string $accountId, string $model): string
    {
        return '/client/v4/accounts/'.$accountId.'/ai/run/'.ltrim($model, '/');
    }

    /**
     * @param  array<string, mixed>  $request
     */
    private function payload(AIRequest $request, ?int $budget): array
    {
        $payload = [
            'messages' => $request->messages,
        ];

        if ($budget !== null) {
            $payload['max_tokens'] = $budget;
        }

        if ($request->temperature !== null) {
            $payload['temperature'] = $request->temperature;
        }

        // Cloudflare accepts raw provider options last so a caller
        // can add model-specific fields without a code change.
        if ($request->options !== []) {
            $payload = array_replace_recursive($payload, $request->options);
        }

        return $payload;
    }

    /**
     * Wait out a 429 cool-down, honouring Retry-After when supplied.
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

    private function http(): PendingRequest
    {
        return Http::baseUrl('https://api.cloudflare.com')
            ->withToken((string) config('acli.cloudflare.api_token'))
            ->acceptJson()
            ->timeout((int) config('acli.cloudflare.timeout', 120))
            ->connectTimeout((int) config('acli.cloudflare.connect_timeout', 10));
    }

    private function nullableInt(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }
}