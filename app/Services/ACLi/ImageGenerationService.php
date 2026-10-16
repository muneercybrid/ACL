<?php

namespace App\Services\ACLi;

use App\Services\ACLi\Providers\PollinationsImageProvider;
use Illuminate\Support\Facades\Log;

/**
 * Image generation for ACLi.
 *
 * This is deliberately separate from the chat provider chain. A chat request
 * wants an OpenAI-shaped text completion; an image request wants image bytes
 * from a completely different kind of endpoint. Routing images through
 * UnifiedProvider would send them to a text model that cannot render, so
 * image requests get their own entry point instead of pretending the two
 * are the same problem.
 *
 * ACLi decides that a request is an image request, builds the prompt from
 * the conversation context, and calls generate(). The provider is the only
 * thing that knows the current image backend, so replacing Pollinations
 * with a funded provider later is a config change rather than a rewrite.
 */
class ImageGenerationService
{
    public function __construct(
        protected PollinationsImageProvider $provider,
    ) {}

    /**
     * Whether image generation is currently offered.
     */
    public function enabled(): bool
    {
        return $this->provider->isAvailable();
    }

    /**
     * Prompt cues that mark a request as an image request rather than chat.
     *
     * Kept narrow on purpose: a student asking "explain how photosynthesis
     * works" wants prose, and only an explicit draw/render/diagram request
     * should be answered with a picture.
     */
    public function looksLikeImageRequest(string $message): bool
    {
        $message = mb_strtolower($message);

        $patterns = [
            '/\b(draw|generate|create|render|make)\b[^.?!]{0,40}\b(image|picture|diagram|illustration|sketch|chart|graph|poster)\b/',
            '/\b(image|picture|illustration|sketch|poster|diagram)\s+of\b/',
            '/\b(show|give|send)\s+me\s+(an?\s+)?(image|picture|photo|illustration|diagram)\b/',
            '/^\s*(draw|paint|illustrate|render)\b/',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $message) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * Turn a student's request into an image prompt.
     *
     * The request itself is usually already close to a usable prompt, so it
     * is passed through with only light shaping rather than sent back to a
     * model to rewrite — that would double the latency and the quota cost of
     * a request that already contains everything needed.
     */
    public function buildPrompt(string $message, ?string $context = null): string
    {
        $prompt = trim($message);

        // Strip the instruction verb so the prompt describes the subject
        // rather than the act of asking for it.
        $prompt = preg_replace(
            '/^\s*(please\s+)?(can you\s+)?(draw|generate|create|render|make|paint|illustrate)\s+(me\s+)?(an?\s+|the\s+)?/i',
            '',
            $prompt
        ) ?? $prompt;

        if ($context) {
            $prompt .= '. Context: '.$context;
        }

        return trim($prompt) ?: 'an educational illustration';
    }

    /**
     * Generate an image.
     *
     * @return array{bytes: string, mime: string, model: string, prompt: string, elapsed: float}
     */
    public function generate(
        string $prompt,
        int $width = 512,
        int $height = 512,
        ?string $context = null,
    ): array {
        $width = (int) min($width, (int) config('acli.images.max_width', 1024));
        $height = (int) min($height, (int) config('acli.images.max_height', 1024));

        $fullPrompt = $this->buildPrompt($prompt, $context);

        $result = $this->provider->generate($fullPrompt, $width, $height);

        Log::info('acli.images.generated', [
            'model' => $result['model'],
            'bytes' => strlen($result['bytes']),
            'elapsed' => round($result['elapsed'], 2),
        ]);

        return $result + ['prompt' => $fullPrompt];
    }
}