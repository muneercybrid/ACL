<?php
namespace App\Services\ACLi\Providers;
use App\Services\ACLi\Contracts\AIProvider;
use App\Services\ACLi\DTO\AIRequest;
use App\Services\ACLi\DTO\AIResponse;
use Illuminate\Support\Facades\Http;

final class DeepSeekProvider implements AIProvider
{
    public function name(): string { return 'deepseek'; }

    public function isAvailable(): bool { return true; }

    public function chat(AIRequest $request): AIResponse
    {
        $key = env('DEEPSEEK_API_KEY', config('acli.gateway.api_key'));
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $key,
            'Content-Type' => 'application/json',
        ])->post('https://api.deepseek.com/chat/completions', [
            'model' => $request->model ?: 'deepseek-chat',
            'messages' => $request->messages,
            'temperature' => $request->temperature ?? 0.7,
            'max_tokens' => $request->maxTokens ?? 2000,
        ]);

        if (! $response->successful()) {
            throw new \App\Services\ACLi\Exceptions\ProviderException(
                'DeepSeek API error: ' . $response->body(),
                provider: 'deepseek',
                retryable: true
            );
        }

        $data = $response->json();
        $content = $data['choices'][0]['message']['content'] ?? '';

        return new AIResponse(
            content: $content,
            provider: 'deepseek',
            model: $data['model'] ?? 'deepseek-chat',
            tokens: $data['usage'] ?? null
        );
    }
}