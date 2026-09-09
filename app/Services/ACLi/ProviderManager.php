<?php

namespace App\Services\ACLi;

use App\Services\ACLi\Contracts\AIProvider;
use App\Services\ACLi\DTO\AIRequest;
use App\Services\ACLi\DTO\AIResponse;
use App\Services\ACLi\Exceptions\ProviderException;
use InvalidArgumentException;

final class ProviderManager
{
    /**
     * @var array<string, AIProvider>
     */
    private array $providers = [];

    /**
     * Register a provider under its identifier.
     */
    public function register(AIProvider $provider): void
    {
        $this->providers[$provider->name()] = $provider;
    }

    /**
     * Resolve a provider by name.
     */
    public function provider(?string $name = null): AIProvider
    {
        $name ??= config('acli.default_provider');

        if (! isset($this->providers[$name])) {
            throw new InvalidArgumentException(
                "ACLi provider [{$name}] is not registered."
            );
        }

        return $this->providers[$name];
    }

    /**
     * Send a request through the configured model route.
     *
     * The primary model is attempted first. Fallback models are attempted
     * only when the preceding provider failure is fallback-eligible.
     */
    public function chat(AIRequest $request): AIResponse
    {
        $models = $this->modelsFor($request->model);

        $lastException = null;

        foreach ($models as $model) {
            $provider = $this->providerForModel($model);

            if (! $provider->isAvailable()) {
                $lastException = new ProviderException(
                    "ACLi provider [{$provider->name()}] is not available.",
                    provider: $provider->name(),
                    retryable: true,
                );

                continue;
            }

            try {
                return $provider->chat(
                    new AIRequest(
                        model: $model,
                        messages: $request->messages,
                        temperature: $request->temperature,
                        topP: $request->topP,
                        maxTokens: $request->maxTokens,
                        seed: $request->seed,
                        options: $request->options,
                    )
                );
            } catch (ProviderException $exception) {
                $lastException = $exception;

                if (! $exception->isFallbackEligible()) {
                    throw $exception;
                }
            }
        }

        if ($lastException instanceof ProviderException) {
            throw $lastException;
        }

        throw new ProviderException(
            'ACLi has no configured models available for this request.',
            provider: config('acli.default_provider'),
            retryable: false,
        );
    }

    /**
     * Return all registered providers.
     *
     * @return array<string, AIProvider>
     */
    public function providers(): array
    {
        return $this->providers;
    }

    /**
     * Resolve the ordered model route.
     *
     * If the request explicitly specifies a model, that model is attempted
     * first and configured fallbacks follow it.
     *
     * @return list<string>
     */
    private function modelsFor(string $requestedModel): array
    {
        $primary = $requestedModel !== ''
            ? $requestedModel
            : config('acli.models.primary', config('acli.default_model'));

        $fallbacks = config('acli.models.fallbacks', []);

        return array_values(array_unique([
            $primary,
            ...$fallbacks,
        ]));
    }

    /**
     * Resolve the provider responsible for a model.
     */
    private function providerForModel(string $model): AIProvider
    {
        $providerMap = config('acli.model_providers', []);

        $providerName = $providerMap[$model]
            ?? config('acli.default_provider');

        return $this->provider($providerName);
    }
}
