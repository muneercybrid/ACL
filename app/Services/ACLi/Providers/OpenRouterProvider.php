<?php
namespace App\Services\ACLi\Providers;

use App\Services\ACLi\Contracts\AIProvider;
use App\Services\ACLi\DTO\AIRequest;
use App\Services\ACLi\DTO\AIResponse;
use App\Services\ACLi\Exceptions\ProviderException;
use Illuminate\Support\Facades\Http;

final class OpenRouterProvider implements AIProvider
{
    public function chat(AIRequest $request): AIResponse
    {
        if (!$this->isAvailable()) {
            throw new ProviderException('OpenRouter provider is not configured.', provider: $this->name(), retryable: false);
        }

        $payload = [
            'model' => $request->model,
            'messages' => $request->messages,
            'stream' => false,
        ];

        if ($request->temperature !== null) $payload['temperature'] = $request->temperature;
        if ($request->topP !== null) $payload['top_p'] = $request->topP;
        if ($request->maxTokens !== null) $payload['max_tokens'] = $request->maxTokens;
        if ($request->seed !== null) $payload['seed'] = $request->seed;
        if ($request->options !== []) $payload = array_replace_recursive($payload, $request->options);

        try {
            $response = Http::withToken(config('acli.providers.openrouter.api_key', config('acli.gateway.api_key')))
                ->acceptJson()
                ->timeout((int) config('acli.providers.openrouter.timeout', config('acli.gateway.timeout', 120)))
                ->connectTimeout((int) config('acli.providers.openrouter.connect_timeout', config('acli.gateway.connect_timeout', 10)))
                ->post(config('acli.providers.openrouter.base_url', config('acli.gateway.base_url', 'https://openrouter.ai/api/v1')) . '/chat/completions', $payload);
        } catch (\Exception $e) {
            throw new ProviderException('OpenRouter API connection failed.', provider: $this->name(), retryable: true, previous: $e);
        }

        if ($response->failed()) {
            throw new ProviderException("OpenRouter API failed with HTTP status {$response->status()}.", provider: $this->name(), status: $response->status(), retryable: $response->status() >= 400);
        }

        $data = $response->json();
        $content = data_get($data, 'choices.0.message.content');
        if (!is_string($content)) {
            throw new ProviderException('OpenRouter API returned invalid chat completion.', provider: $this->name(), retryable: true);
        }

        $usage = data_get($data, 'usage', []);
        return new AIResponse(
            content: $content,
            provider: $this->name(),
            model: data_get($data, 'model', $request->model),
            inputTokens: $this->nullableInt(data_get($usage, 'prompt_tokens')),
            outputTokens: $this->nullableInt(data_get($usage, 'completion_tokens')),
            totalTokens: $this->nullableInt(data_get($usage, 'total_tokens')),
        );
    }

    public function name(): string { return 'openrouter'; }
    public function isAvailable(): bool { return filled(config('acli.providers.openrouter.api_key', config('acli.gateway.api_key'))); }
    private function nullableInt(mixed $value): ?int { return is_numeric($value) ? (int) $value : null; }
}
