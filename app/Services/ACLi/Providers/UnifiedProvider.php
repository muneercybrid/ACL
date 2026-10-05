<?php

namespace App\Services\ACLi\Providers;

use App\Services\ACLi\Contracts\AIProvider;
use App\Services\ACLi\DTO\AIRequest;
use App\Services\ACLi\DTO\AIResponse;
use App\Services\ACLi\Exceptions\ProviderException;
use Illuminate\Support\Facades\Log;

/**
 * Unified ACLi provider: a failover chain.
 *
 * One ACLi request fans out over several backing AI providers in
 * priority order -- Token Harbor first (free tier, no observed
 * rate limit, 1M context), then Cloudflare Workers AI (free
 * tier, ~0.5s, no observed rate limit), then OmniRoute (the
 * local proxy) last. The first provider that returns a usable
 * answer wins; each provider's own retry and truncation handling
 * runs before the chain moves on.
 *
 * Why a chain rather than a single provider: the three backends
 * have different failure modes. Cloudflare's free tier has no
 * rate limit but a small model set; Token Harbor has a large
 * model set and free ":free" slugs but needs a funded balance
 * for some models; OmniRoute is local and always reachable but
 * shares its credentials with the chapter generator and can 429.
 * Failing over means a student's chat keeps working through any
 * single outage, and it lets the superadmin reorder or retire
 * backends without touching application code.
 *
 * The chain and each backend's model are configured under
 * config('acli.unified.chain'). A provider that is not
 * configured (missing key/account) is skipped silently, so the
 * same code runs in environments where only some backends exist.
 */
final class UnifiedProvider implements AIProvider
{
    use StreamsViaBufferedCall;

    /**
     * The ordered providers in the chain.
     *
     * @var array<AIProvider>
     */
    private array $chain;

    /** @var array<string, string> model slug per provider name */
    private array $models;

    public function __construct()
    {
        $this->chain = $this->buildChain();
        $this->models = $this->buildModels();
    }

    public function chat(AIRequest $request): AIResponse
    {
        $last = null;
        $attempted = [];

        foreach ($this->chain as $provider) {
            $name = $provider->name();

            // Skip backends that are not configured in this
            // environment (no key, no account) and providers
            // that the admin has explicitly removed from the
            // chain. Skipping is silent on purpose: an absent
            // backend is not an error condition for the student.
            if (! $provider->isAvailable()) {
                continue;
            }

            $attempted[] = $name;

            try {
                // The chain's model for this provider overrides
                // the request model, so a request built for the
                // unified gateway still lands on each backend's
                // own model slug.
                $routed = $this->route($request, $provider);
                $response = $provider->chat($routed);

                // A successful response from a later provider is
                // recorded so the metadata shows the path taken.
                $metadata = $response->metadata;
                $metadata['unified_chain'] = $attempted;
                $metadata['unified_served_by'] = $name;

                return new AIResponse(
                    content: $response->content,
                    provider: $this->name(),
                    model: $response->model,
                    inputTokens: $response->inputTokens,
                    outputTokens: $response->outputTokens,
                    totalTokens: $response->totalTokens,
                    metadata: $metadata,
                );
            } catch (ProviderException $exception) {
                $last = $exception;

                // A non-retryable failure means the request itself
                // is bad for this backend (unknown model, plan
                // gating). Keep going: another backend may still
                // serve it. Only a fully exhausted chain surfaces
                // an error to the caller.
                Log::warning('acli.unified.provider_failed', [
                    'provider' => $name,
                    'status' => $exception->status,
                    'retryable' => $exception->retryable,
                    'message' => $exception->getMessage(),
                ]);

                continue;
            } catch (\Throwable $exception) {
                $last = new ProviderException(
                    'Unexpected failure in '.$name.': '.$exception->getMessage(),
                    provider: $name,
                    retryable: true,
                    previous: $exception,
                );

                continue;
            }
        }

        $message = $last !== null
            ? 'All ACLi providers failed. Last: '.$last->getMessage()
            : 'No ACLi provider is configured.';

        throw new ProviderException(
            $message,
            provider: $this->name(),
            status: $last?->status,
            retryable: true,
            previous: $last,
        );
    }

    public function name(): string
    {
        return 'unified';
    }

    public function isAvailable(): bool
    {
        foreach ($this->chain as $provider) {
            if ($provider->isAvailable()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Build the provider chain in configured priority order.
     *
     * @return array<AIProvider>
     */
    private function buildChain(): array
    {
        $available = [
            'tokenharbor' => fn () => new TokenHarborProvider(),
            'cloudflare' => fn () => new CloudflareProvider(),
            'omniroute' => fn () => new OmniRouteProvider(),
        ];

        $order = (array) config('acli.unified.chain', ['tokenharbor', 'cloudflare', 'omniroute']);

        $chain = [];
        foreach ($order as $name) {
            $name = strtolower(trim((string) $name));
            if (isset($available[$name])) {
                $chain[] = $available[$name]();
            }
        }

        return $chain;
    }

    /**
     * Resolve each provider's own model slug.
     *
     * @return array<string, string>
     */
    private function buildModels(): array
    {
        $models = (array) config('acli.unified.models', []);

        return [
            'tokenharbor' => (string) ($models['tokenharbor']
                ?? config('acli.tokenharbor.model', 'mimo-v2.6-flash:free')),
            'cloudflare' => (string) ($models['cloudflare']
                ?? config('acli.cloudflare.model', '@cf/meta/llama-3.2-1b-instruct')),
            'omniroute' => (string) ($models['omniroute']
                ?? config('acli.gateway.model', 'deepseek/deepseek-v4-flash-0731')),
        ];
    }

    /**
     * Route the request onto a provider's own model slug.
     */
    private function route(AIRequest $request, AIProvider $provider): AIRequest
    {
        $slug = $this->models[$provider->name()] ?? $request->model;

        // Only override the model when the caller did not pin
        // one explicitly; a request that already names a model
        // (e.g. a superadmin tool test) keeps its choice.
        if ($request->model !== ($this->models[$provider->name()] ?? null)
            && filled($request->model)
            && ! str_starts_with($request->model, 'unified')) {
            // The unified gateway is the default caller and
            // always uses the provider's own slug.
            if ($request->options['unified_default'] ?? true) {
                $slug = $this->models[$provider->name()] ?? $request->model;
            }
        }

        return new AIRequest(
            model: $slug,
            messages: $request->messages,
            temperature: $request->temperature,
            topP: $request->topP,
            maxTokens: $request->maxTokens,
            seed: $request->seed,
            options: $request->options,
        );
    }
}