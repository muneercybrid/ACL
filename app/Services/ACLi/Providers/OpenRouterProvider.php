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

    /**
     * Streams a chat completion, invoking $onChunk with the text of each delta.
     *
     * The non-streaming path buffers the entire answer before returning it, which
     * is why a student watched three bouncing dots for the whole wait and then
     * saw the finished message appear in one block. Nothing about this changes
     * what is generated -- only when the words become available.
     *
     * @param  callable(string): void  $onChunk
     */
    public function streamChat(AIRequest $request, callable $onChunk): AIResponse
    {
        if (!$this->isAvailable()) {
            throw new ProviderException('OpenRouter provider is not configured.', provider: $this->name(), retryable: false);
        }

        $payload = [
            'model' => $request->model,
            'messages' => $request->messages,
            'stream' => true,
        ];

        if ($request->temperature !== null) $payload['temperature'] = $request->temperature;
        if ($request->topP !== null) $payload['top_p'] = $request->topP;
        if ($request->maxTokens !== null) $payload['max_tokens'] = $request->maxTokens;
        if ($request->options !== []) $payload = array_replace_recursive($payload, $request->options);

        $url = config('acli.providers.openrouter.base_url', config('acli.gateway.base_url', 'https://openrouter.ai/api/v1')) . '/chat/completions';
        $key = config('acli.providers.openrouter.api_key', config('acli.gateway.api_key'));

        $buffer = '';
        $full = '';
        $handle = curl_init($url);

        curl_setopt_array($handle, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $key,
                'Content-Type: application/json',
                'Accept: text/event-stream',
            ],
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_TIMEOUT => (int) config('acli.providers.openrouter.timeout', config('acli.gateway.timeout', 120)),
            CURLOPT_WRITEFUNCTION => function ($ch, $data) use (&$buffer, &$full, $onChunk) {
                $buffer .= $data;

                // Server-sent events are separated by a blank line.
                while (($pos = strpos($buffer, "\n\n")) !== false) {
                    $frame = substr($buffer, 0, $pos);
                    $buffer = substr($buffer, $pos + 2);

                    if (!str_starts_with(trim($frame), 'data:')) {
                        continue;
                    }

                    $line = trim(substr(trim($frame), 5));

                    if ($line === '' || $line === '[DONE]') {
                        continue;
                    }

                    $decoded = json_decode($line, true);
                    $delta = data_get($decoded, 'choices.0.delta.content');

                    if (is_string($delta) && $delta !== '') {
                        $full .= $delta;
                        $onChunk($delta);
                    }
                }

                return strlen($data);
            },
        ]);

        $ok = curl_exec($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        curl_close($handle);

        if ($ok === false || $status >= 400) {
            throw new ProviderException("OpenRouter streaming failed with HTTP status {$status}.", provider: $this->name(), status: $status, retryable: $status >= 400);
        }

        // The accumulated text is returned as well as emitted, so the caller
        // can persist exactly what the student was shown.
        return new AIResponse(
            content: $full,
            provider: $this->name(),
            model: $request->model,
        );
    }
}
