<?php

namespace App\Services\ACLi\Providers;

use App\Services\ACLi\Exceptions\ProviderException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Pollinations image generation provider.
 *
 * Pollinations serves image generation with NO authentication at all — the
 * prompt is in the URL path. That makes it the only image backend reachable
 * without a paid plan or a funded balance: Cloudflare's Stable Diffusion
 * routes return "No route for that URI" on the free Workers plan, Gemini's
 * image models return 429 until billing is enabled, and Token Harbor's
 * /v1/images/generations returns 402 balance_zero until topped up.
 *
 * The tradeoff is throughput. The anonymous tier is throttled to roughly one
 * image every 20-30 seconds and returns HTTP 402 with a payment challenge
 * when called faster, so this provider backs off and retries rather than
 * hammering the endpoint. It is suitable for on-demand illustration (a
 * student asking ACLi to draw something), not for bulk generation.
 *
 * Only the "sana" model is available anonymously; flux/kontext/turbo are
 * behind the payment challenge.
 */
final class PollinationsImageProvider
{
    /**
     * Anonymous free-tier cooldown: seconds to wait after a 402.
     */
    private const COOLDOWN_SECONDS = 25;

    /**
     * How many times to wait out a throttle before giving up.
     */
    private const MAX_THROTTLE_RETRIES = 4;

    private const DEFAULT_MODEL = 'sana';
    private const MIN_EDGE = 256;
    private const MAX_EDGE = 1024;

    public function name(): string
    {
        return 'pollinations';
    }

    /**
     * Pollinations needs no credentials, so it is always available.
     * The app-level switch still governs whether images are offered.
     */
    public function isAvailable(): bool
    {
        return (bool) config('acli.images.enabled', true);
    }

    /**
     * Generate an image and return the raw bytes plus its MIME type.
     *
     * @return array{bytes: string, mime: string, model: string, elapsed: float}
     *
     * @throws ProviderException when every attempt is throttled or the
     *                           upstream returns a non-image error.
     */
    public function generate(
        string $prompt,
        int $width = 512,
        int $height = 512,
        ?string $model = null,
    ): array {
        $width = $this->clampDimension($width);
        $height = $this->clampDimension($height);
        $model = $model ?: (string) config('acli.images.model', self::DEFAULT_MODEL);

        $url = rtrim((string) config('acli.images.base_url'), '/')
            .'/prompt/'.rawurlencode($prompt);

        $query = http_build_query([
            'width' => $width,
            'height' => $height,
            'model' => $model,
            'nologo' => 'true',
        ]);

        $throttles = 0;

        while (true) {
            $started = microtime(true);

            $response = Http::timeout((int) config('acli.images.timeout', 90))
                ->connectTimeout(15)
                ->accept('image/*,application/octet-stream,*/*')
                ->withOptions(['verify' => false])
                ->get($url.'?'.$query);

            // 402 is Pollinations' anonymous throttle: it returns a payment
            // challenge rather than an image. Backing off and retrying is the
            // only way to get the free tier to serve the request.
            if ($response->status() === 402 && $throttles < self::MAX_THROTTLE_RETRIES) {
                $throttles++;
                Log::info('acli.images.throttled', [
                    'attempt' => $throttles,
                    'wait_seconds' => self::COOLDOWN_SECONDS,
                ]);
                sleep(self::COOLDOWN_SECONDS);
                continue;
            }

            if ($response->failed()) {
                throw new ProviderException(
                    "Pollinations image request failed with HTTP {$response->status()}.",
                    provider: $this->name(),
                    status: $response->status(),
                    retryable: $response->serverError(),
                );
            }

            $bytes = $response->body();
            $mime = $response->header('Content-Type') ?: 'image/jpeg';

            // A throttle can also arrive as a 200 with a JSON payment
            // challenge instead of image bytes, so confirm we really
            // received an image before returning it.
            if (! str_starts_with($mime, 'image/') || strlen($bytes) < 1024) {
                if ($throttles < self::MAX_THROTTLE_RETRIES) {
                    $throttles++;
                    sleep(self::COOLDOWN_SECONDS);
                    continue;
                }

                throw new ProviderException(
                    'Pollinations returned a non-image response.',
                    provider: $this->name(),
                    retryable: true,
                );
            }

            return [
                'bytes' => $bytes,
                'mime' => $mime,
                'model' => $model,
                'elapsed' => microtime(true) - $started,
            ];
        }
    }

    /**
     * Keep dimensions inside what the anonymous tier will actually render.
     */
    private function clampDimension(int $value): int
    {
        $value = max(self::MIN_EDGE, min(self::MAX_EDGE, $value));

        // Pollinations renders best on multiples of 64.
        return (int) (round($value / 64) * 64);
    }
}