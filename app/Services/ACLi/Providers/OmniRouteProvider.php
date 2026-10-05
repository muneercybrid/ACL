<?php

namespace App\Services\ACLi\Providers;

use App\Services\ACLi\Contracts\AIProvider;
use App\Services\ACLi\DTO\AIRequest;
use App\Services\ACLi\DTO\AIResponse;
use App\Services\ACLi\Exceptions\ProviderException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

final class OmniRouteProvider implements AIProvider
{
    use StreamsViaBufferedCall;

    /**
     * How many times a request may be re-sent after the model ran
     * out of tokens mid-answer. Each retry doubles the budget, so
     * four retries cover 1x -> 2x -> 4x -> 8x -> 16x.
     */
    private const MAX_TRUNCATION_RETRIES = 4;

    /**
     * How many times a request may be re-sent while the shared
     * credentials are cooling down (HTTP 429). The proxy throttles
     * per credential pool, and the chat shares that pool with any
     * background work, so a short patient wait recovers it.
     */
    private const MAX_QUOTA_RETRIES = 6;

    /**
     * Hard ceiling on the token budget. Beyond this the request is
     * genuinely too large rather than merely under-budgeted.
     */
    private const MAX_TOKEN_CEILING = 32000;

    public function chat(AIRequest $request): AIResponse
    {
        if (! $this->isAvailable()) {
            throw new ProviderException(
                'OmniRoute provider is not configured.',
                provider: $this->name(),
                retryable: false,
            );
        }

        $budget = $request->maxTokens;
        $truncations = 0;
        $quotaWaits = 0;

        while (true) {
            $payload = $this->payload($request, $budget);

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

                // 429 means every credential for the model is cooling
                // down. That is a wait, not a failure: pause (honouring
                // Retry-After when the proxy supplies it) and re-send.
                if ($status === 429 && $quotaWaits < self::MAX_QUOTA_RETRIES) {
                    $quotaWaits++;
                    $this->waitForCoolDown($response, $quotaWaits);
                    continue;
                }

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
            $finishReason = data_get($data, 'choices.0.finish_reason');

            // A reasoning model can spend its whole token budget on the
            // internal reasoning pass and come back with an empty
            // content and finish_reason "length". That is a truncation,
            // not an invalid response: the answer exists but did not
            // fit. Give it a larger budget and ask again rather than
            // failing the caller.
            if (! is_string($content) && $finishReason === 'length') {
                $truncations++;
                if ($truncations > self::MAX_TRUNCATION_RETRIES) {
                    throw new ProviderException(
                        'OmniRoute model exhausted its token budget before producing an answer.',
                        provider: $this->name(),
                        retryable: false,
                    );
                }
                $budget = $this->growBudget($budget);
                continue;
            }

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
        return 'omniroute';
    }

    public function isAvailable(): bool
    {
        return filled(config('acli.gateway.base_url'))
            && filled(config('acli.gateway.api_key'));
    }

    /**
     * Build the request body for one attempt.
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
     * Wait out a 429 cool-down.
     *
     * The proxy sends Retry-After when it knows how long the pool
     * stays cold; when it does not, back off exponentially with a
     * little jitter so a herd of workers does not all come back at
     * the same instant and trip the limit again.
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

    /**
     * Double the token budget, capped.
     */
    private function growBudget(?int $budget): int
    {
        $grown = $budget === null || $budget <= 0
            ? 2000
            : $budget * 2;

        return min($grown, self::MAX_TOKEN_CEILING);
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