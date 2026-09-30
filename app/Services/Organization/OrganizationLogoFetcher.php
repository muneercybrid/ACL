<?php

declare(strict_types=1);

namespace App\Services\Organization;

use App\Models\Organization;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;
use GuzzleHttp\TransferStats;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Fetches the official logo for a single organization from its own website.
 *
 * This is a crawler, so its behaviour is shaped by two constraints that pull
 * in opposite directions: it must not write junk into `organizations.logo_path`,
 * and it must not be a nuisance to the 189 university web servers it visits.
 *
 * Everything is validated from the bytes actually received. Content-Type is
 * recorded as evidence but never trusted — a broken site answering 200 with an
 * HTML error page is the single most common way a naive logo scraper poisons a
 * database with unusable paths.
 *
 * Failure is data, not an exception. Every public entry point resolves to a
 * result array describing what happened; a dead site returns a result, it does
 * not throw, because the caller is batching 481 of these and one death must not
 * end the run.
 */
class OrganizationLogoFetcher
{
    /**
     * A real, contactable User-Agent. Nigerian university web servers are
     * frequently maintained by a single IT officer; a crawler that cannot be
     * identified and complained to is indistinguishable from an attack.
     */
    public const USER_AGENT = 'ACL-OrganizationLogoFetcher/1.0 (+https://app.aclacademy.me; contact: noreply@aclacademy.me)';

    /** Where the files land. Matches the `public` disk (storage/app/public, symlinked from public/storage). */
    public const DISK = 'public';

    public const DIRECTORY = 'organization-logos';

    /** A homepage larger than this is a sign we have wandered onto something that is not a homepage. */
    private const MAX_HTML_BYTES = 2_000_000;

    /** Hard ceiling on a logo download. Above this it is not a logo. */
    private const MAX_IMAGE_BYTES = 5_000_000;

    /**
     * Below this it is a placeholder, a 1x1 tracking pixel or an empty stub.
     * SVG is exempt because a small, hand-written vector logo is legitimate.
     */
    private const MIN_IMAGE_BYTES = 500;

    /**
     * Dimensions at or above which an image is too big to be a header logo.
     * The spec asks for "plausible width/height under 600px".
     */
    private const MAX_LOGO_DIMENSION = 600;

    /** Exactly these are favicons, not logos. */
    private const FAVICON_DIMENSIONS = [16, 32, 48];

    private Client $client;

    /**
     * host => unix timestamp of the last request we issued to it.
     *
     * Bounded by the number of distinct hosts (a few hundred at most), so this
     * cannot grow without limit during a full run.
     *
     * @var array<string, float>
     */
    private array $lastRequestAt = [];

    /**
     * host => parsed robots.txt rules, or false when robots.txt was
     * unavailable (absent, 4xx) which under the standard means "allow all".
     *
     * @var array<string, array<int, string>|false>
     */
    private array $robotsCache = [];

    /**
     * @param  bool  $write  When false the crawler still performs every network
     *                        request and every byte-level validation, but stops
     *                        short of writing a file. That is what makes a dry
     *                        run a real rehearsal of the apply run rather than a
     *                        narrower, less faithful one.
     * @param  array{timeout?: int, connect_timeout?: int, delay?: float, verify_tls?: bool, respect_robots?: bool}  $options
     */
    public function __construct(
        private readonly int $timeout = 15,
        private readonly int $connectTimeout = 8,
        private readonly float $hostDelay = 1.5,
        private readonly bool $verifyTls = true,
        private readonly bool $respectRobots = true,
        private readonly bool $write = true,
    ) {
        $this->client = new Client([
            'http_errors' => true,
            'headers' => [
                'User-Agent' => self::USER_AGENT,
                'Accept' => 'text/html,application/xhtml+xml,image/png,image/jpeg,image/webp,image/svg+xml;q=0.9,*/*;q=0.8',
                'Accept-Language' => 'en,*;q=0.5',
            ],
        ]);
    }

    // ---------------------------------------------------------------------
    // Entry point
    // ---------------------------------------------------------------------

    /**
     * Resolve a logo for one organization.
     *
     * Always resolves. Never rejects.
     */
    public function fetchAsync(Organization $organization): PromiseInterface
    {
        try {
            $homepage = self::normalizeWebsiteUrl($organization->website);

            if ($homepage === null) {
                return Create::promiseFor($this->result($organization, 'skipped', 'no_website'));
            }

            if ($homepage === '') {
                return Create::promiseFor($this->result($organization, 'skipped', 'invalid_website'));
            }

            return $this->robotsPermitsAsync($homepage)
                ->then(function (bool $permitted) use ($organization, $homepage): array|PromiseInterface {
                    if (! $permitted) {
                        return $this->result($organization, 'skipped', 'robots_disallowed', [
                            'website_url' => $homepage,
                        ]);
                    }

                    return $this->fetchPageAsync($homepage)
                        ->then(function (array $response) use ($organization, $homepage): array|PromiseInterface {
                            if (isset($response['error'])) {
                                return $this->result($organization, 'failed', $response['error'], [
                                    'website_url' => $homepage,
                                    'detail' => $response['detail'] ?? null,
                                    'used_http_fallback' => (bool) ($response['http_fallback'] ?? false),
                                ]);
                            }

                            $candidates = self::extractLogoCandidates($response['body'], $response['url']);

                            if ($candidates === []) {
                                return $this->result($organization, 'failed', 'no_logo_candidate', [
                                    'website_url' => $homepage,
                                    'page_url' => $response['url'],
                                ]);
                            }

                            return $this->tryCandidates($organization, $homepage, $candidates, 0, []);
                        });
                })
                ->otherwise(function (Throwable $e) use ($organization, $homepage): array {
                    return $this->result($organization, 'failed', classify($e), [
                        'website_url' => $homepage,
                        'detail' => $e->getMessage(),
                    ]);
                });
        } catch (Throwable $e) {
            return Create::promiseFor($this->result($organization, 'failed', 'exception', [
                'detail' => $e->getMessage(),
            ]));
        }
    }

    // ---------------------------------------------------------------------
    // Candidate selection
    // ---------------------------------------------------------------------

    /**
     * Try each candidate in preference order until one yields a valid image.
     *
     * @param  array<int, array{url: string, source: string, hint_width: ?int, hint_height: ?int}>  $candidates
     * @param  array<int, string>  $rejections
     */
    private function tryCandidates(Organization $organization, string $homepage, array $candidates, int $index, array $rejections): PromiseInterface
    {
        if (! isset($candidates[$index])) {
            return Create::promiseFor($this->result($organization, 'failed', 'all_candidates_rejected', [
                'website_url' => $homepage,
                'rejections' => $rejections,
            ]));
        }

        $candidate = $candidates[$index];

        return $this->fetchImageAsync($candidate['url'])
            ->then(function (array $response) use ($organization, $homepage, $candidates, $index, $rejections, $candidate): array|PromiseInterface {
                if (isset($response['error'])) {
                    $rejections[] = $candidate['url'].' -> '.$response['error'];

                    return $this->tryCandidates($organization, $homepage, $candidates, $index + 1, $rejections);
                }

                $verdict = $this->validateImage($response['body'], $candidate);

                if ($verdict !== null) {
                    $rejections[] = $candidate['url'].' -> '.$verdict;

                    return $this->tryCandidates($organization, $homepage, $candidates, $index + 1, $rejections);
                }

                $detected = self::detectImageType($response['body']);
                $relative = self::DIRECTORY.'/'.self::safeSlug($organization).'.'.self::extensionFor($detected);

                if ($this->write) {
                    try {
                        $relative = $this->store($organization, $response['body'], self::extensionFor($detected));
                    } catch (Throwable $e) {
                        return $this->result($organization, 'failed', 'store_failed', [
                            'website_url' => $homepage,
                            'source_url' => $candidate['url'],
                            'detail' => $e->getMessage(),
                        ]);
                    }
                }

                return $this->result($organization, 'fetched', null, [
                    'website_url' => $homepage,
                    'logo_path' => $relative,
                    'logo_url' => Storage::disk(self::DISK)->url($relative),
                    'source' => $candidate['source'],
                    'source_url' => $candidate['url'],
                    'image_type' => $detected,
                    'bytes' => strlen($response['body']),
                    'width' => self::imageDimensions($response['body'], $detected)[0],
                    'height' => self::imageDimensions($response['body'], $detected)[1],
                    'content_type' => $response['content_type'],
                    'rejections' => $rejections,
                ]);
            })
            ->otherwise(function (Throwable $e) use ($organization, $homepage, $candidates, $index, $rejections, $candidate): PromiseInterface {
                $rejections[] = $candidate['url'].' -> '.classify($e);

                return $this->tryCandidates($organization, $homepage, $candidates, $index + 1, $rejections);
            });
    }

    /**
     * Decide whether downloaded bytes are a usable logo.
     *
     * Returns null when acceptable, otherwise a short machine-readable reason.
     * Callers must have already established that this is a real image.
     *
     * @param  array{url: string, source: string, hint_width: ?int, hint_height: ?int}  $candidate
     */
    private function validateImage(string $bytes, array $candidate): ?string
    {
        $size = strlen($bytes);
        $type = self::detectImageType($bytes);

        if ($type === null) {
            return 'not_an_image';
        }

        if ($size < self::MIN_IMAGE_BYTES && $type !== 'svg') {
            return 'too_small';
        }

        [$width, $height] = self::imageDimensions($bytes, $type);

        if ($width !== null && $height !== null) {
            // A 1x1 (or 2x2) PNG is a tracking pixel or an empty placeholder.
            if ($width <= 2 && $height <= 2) {
                return 'tracking_pixel';
            }

            if (in_array($width, self::FAVICON_DIMENSIONS, true) && in_array($height, self::FAVICON_DIMENSIONS, true)) {
                return 'favicon_sized';
            }

            if (self::looksLikeSprite($width, $height, $candidate)) {
                return 'sprite_sheet';
            }

            if ($width > self::MAX_LOGO_DIMENSION || $height > self::MAX_LOGO_DIMENSION) {
                return 'too_large';
            }
        }

        return null;
    }

    /**
     * A sprite sheet is a wide, short strip of small tiles — a social icon bar
     * or icon font sprite, never a single logo.
     */
    private static function looksLikeSprite(int $width, int $height, array $candidate): bool
    {
        // Square icons exported in a row.
        if ($width >= $height * 3 && $height <= 64) {
            return true;
        }

        // Explicit sprite/background-image hints on the element.
        $haystack = strtolower($candidate['url'].' '.$candidate['source']);
        foreach (['sprite', 'iconsprite', 'social-icon', 'icon-bar'] as $needle) {
            if (str_contains($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Pull logo candidates out of a homepage, in the order the spec requires.
     *
     * Deliberately a small purpose-built scanner rather than a DOM parse: we
     * only need a handful of elements, and these pages are frequently malformed
     * enough that libxml bails out on the whole document.
     *
     * @return array<int, array{url: string, source: string, hint_width: ?int, hint_height: ?int}>
     */
    public static function extractLogoCandidates(string $html, string $pageUrl): array
    {
        /**
         * Preference order. The spec's order is link icon, then header <img>
         * that says "logo", then any header <img> of plausible size.
         *
         * One deliberate departure: a favicon is demoted below a real logo.
         * The spec's literal ordering would let `<link rel="icon" sizes="32x32">`
         * win over the actual masthead on every site that ships both, and
         * storing a 32px square as a staff-login logo is exactly the outcome
         * this command exists to avoid.
         */
        $tiers = [
            'link-icon' => 1,
            'img-logo' => 2,
            'img-header-logo' => 3,
            'link-icon-small' => 4,
            'img-header-sized' => 5,
        ];

        $candidates = [];
        $seen = [];

        $add = function (string $href, string $source, ?int $w, ?int $h) use (&$candidates, &$seen, $pageUrl, $tiers): void {
            $href = trim(html_entity_decode($href, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            if ($href === '' || str_starts_with($href, 'data:')) {
                return;
            }

            $absolute = self::resolveUrl($pageUrl, $href);
            if ($absolute === null) {
                return;
            }

            if (isset($seen[$absolute])) {
                return;
            }

            $seen[$absolute] = true;
            $candidates[] = [
                'url' => $absolute,
                'source' => $source,
                'hint_width' => $w,
                'hint_height' => $h,
                'tier' => $tiers[$source] ?? 9,
            ];
        };

        // --- Tier 1: declared icons -------------------------------------
        if (preg_match_all('#<link\b[^>]*>#i', $html, $m)) {
            foreach ($m[0] as $tag) {
                if (! preg_match('#\brel\s*=\s*(["\']?)([^\"\'>]*)#i', $tag, $rel)) {
                    continue;
                }

                $rel = strtolower(trim($rel[2]));

                // The rel token is matched as a whole word, but
                // `apple-touch-icon` ends in a hyphen rather than a space, so
                // the hyphenated spellings are listed explicitly. Anchoring on
                // whitespace alone silently drops every apple-touch-icon, which
                // is usually the largest real logo a university site publishes.
                $isIcon = (bool) preg_match(
                    '#(^|\s)(shortcut\s+icon|icon|apple-touch-icon|apple-touch-icon-precomposed|apple-touch-startup-image|mask-icon|fluid-icon)(\s|$)#',
                    $rel
                );

                if (! $isIcon) {
                    continue;
                }

                if (! preg_match('#\bhref\s*=\s*(["\'])(.*?)\1#is', $tag, $href)) {
                    continue;
                }

                // A file the site itself calls a favicon is one, whatever
                // resolution it happens to be. Drupal and WordPress both ship
                // a proper masthead logo *and* a `rel="icon"` pointing at a
                // /favicon.png that can be 64px or 68px — large enough to slip
                // past a size check while still being far too small for a login
                // page header. Ranking it by name keeps the real mark reachable
                // while still falling back to the icon when it is all there is.
                //
                // apple-touch-icon is deliberately NOT in this list: it is
                // normally a 180px rendering of the real mark and is one of the
                // best logos a site publishes.
                $sizes = preg_match('#\bsizes\s*=\s*(["\'])(.*?)\1#is', $tag, $sz) ? $sz[2] : '';
                $declaredSmall = (bool) preg_match('/\b(1[6-9]|2\d|3\d|48)x/i', $sizes);
                $namedLikeIcon = (bool) preg_match(
                    '#(favicon|site-icon|shortcut-icon|msapplication-|browserconfig|icon\.ico)#i',
                    $href[2]
                );

                $isIcon = $declaredSmall || $namedLikeIcon;

                $add($href[2], $isIcon ? 'link-icon-small' : 'link-icon', null, null);
            }
        }

        // --- Tier 2: header images that look like a logo -----------------
        //
        // PREG_OFFSET_CAPTURE is what makes "is this image in the header?"
        // answerable. Matching the tag twice — once for the HTML, once with
        // strpos to locate it — silently mislabels every page where the same
        // <img> markup appears twice, which is most of them.
        $headerRegions = self::headerRegions($html);

        if (preg_match_all('#<img\b[^>]*>#i', $html, $matches, PREG_OFFSET_CAPTURE)) {
            foreach ($matches[0] as [$tag, $offset]) {
                $src = self::firstAttribute($tag, ['src', 'data-src', 'data-lazy-src', 'data-original']);
                if ($src === null || trim($src) === '') {
                    continue;
                }

                $alt = self::firstAttribute($tag, ['alt', 'title']) ?? '';
                $classId = strtolower(
                    (self::firstAttribute($tag, ['class', 'id']) ?? '').' '.
                    (self::firstAttribute($tag, ['id', 'class']) ?? '')
                );

                $width = self::intAttribute($tag, ['width']);
                $height = self::intAttribute($tag, ['height']);

                $isHeader = self::offsetInRegions($offset, $headerRegions);
                $looksLikeLogo = self::mentionsLogo($classId) || self::mentionsLogo(strtolower($src)) || self::mentionsLogo(strtolower($alt));

                // Anything the page itself calls a logo, header or brand.
                if (self::mentionsLogo($classId) || preg_match('#\b(brand|header|site-?logo|navbar-?logo)\b#', $classId)) {
                    $add($src, 'img-logo', $width, $height);

                    continue;
                }

                if ($isHeader && $looksLikeLogo) {
                    $add($src, 'img-header-logo', $width, $height);

                    continue;
                }

                // Tier 3: a header image with plausible logo dimensions and no
                // signal that it is decorative (sprite, icon, avatar).
                if ($isHeader && $width !== null && $height !== null) {
                    $plausible = $width > 48 && $width <= self::MAX_LOGO_DIMENSION
                        && $height > 24 && $height <= self::MAX_LOGO_DIMENSION;

                    $decorative = preg_match('#\b(icon|sprite|social|avatar|flag|background|spinner|loader|pixel|banner|hero|slider|carousel)\b#', $classId.' '.strtolower($src));

                    if ($plausible && ! $decorative) {
                        $add($src, 'img-header-sized', $width, $height);
                    }
                }
            }
        }

        // usort has been stable since PHP 8.0, so equal tiers keep document order.
        usort($candidates, static fn (array $a, array $b): int => $a['tier'] <=> $b['tier']);

        // The tier is an internal ordering hint, not part of the contract.
        return array_map(static function (array $c): array {
            unset($c['tier']);

            return $c;
        }, $candidates);
    }

    private static function firstAttribute(string $tag, array $names): ?string
    {
        foreach ($names as $name) {
            if (preg_match('#\b'.preg_quote($name, '#').'\s*=\s*(["\'])(.*?)\1#is', $tag, $m)) {
                return $m[2];
            }

            if (preg_match('#\b'.preg_quote($name, '#').'\s*=\s*([^\s"\'>]+)#is', $tag, $m)) {
                return $m[1];
            }
        }

        return null;
    }

    private static function intAttribute(string $tag, array $names): ?int
    {
        $raw = self::firstAttribute($tag, $names);
        if ($raw === null || ! preg_match('/^\s*(\d{1,5})/', $raw, $m)) {
            return null;
        }

        $value = (int) $m[1];

        return ($value > 0 && $value <= 100000) ? $value : null;
    }

    /**
     * Does this text refer to a logo?
     *
     * A plain \blogo\b is not enough. "logo_2.png", "logo-dark.svg" and
     * "logo2024.png" are how universities actually name their masthead files,
     * and because "_" and digits are word characters \blogo\b matches none of
     * them — Drupal's own logo_2.png was being missed because of it. At the
     * same time a bare /logo/ would fire on "/blog/". So the test is "logo"
     * not preceded by another letter, which keeps /blog/ out and logo_2 in.
     */
    private static function mentionsLogo(string $haystack): bool
    {
        return (bool) preg_match('#(?<![a-z])logo#', $haystack);
    }

    /**
     * Byte ranges of the page that count as "the header".
     *
     * Covers <header>/<nav> elements and any container whose class or id
     * reads as masthead, navbar, topbar or branding — which is how the
     * overwhelming majority of WordPress and hand-rolled university sites
     * actually mark up their logo.
     *
     * @return array<int, array{0: int, 1: int}>
     */
    private static function headerRegions(string $html): array
    {
        $regions = [];

        if (preg_match_all('#<header\b[^>]*>(.*?)</header\s*>#is', $html, $m, PREG_OFFSET_CAPTURE)) {
            foreach ($m[0] as [$match, $offset]) {
                $regions[] = [$offset, $offset + strlen($match)];
            }
        }

        if (preg_match_all('#<nav\b[^>]*>(.*?)</nav\s*>#is', $html, $m, PREG_OFFSET_CAPTURE)) {
            foreach ($m[0] as [$match, $offset]) {
                $regions[] = [$offset, $offset + strlen($match)];
            }
        }

        // A single-level container scan. University homepages are shallow, so
        // one level of nesting is enough and avoids a full parse.
        if (preg_match_all(
            '#<div\b[^>]*\b(?:class|id)\s*=\s*(["\'])[^"\']*\b(header|navbar|nav|topbar|masthead|branding|site-header|main-header)\b[^"\']*\1[^>]*>(.*?)</div\s*>#is',
            $html,
            $m,
            PREG_OFFSET_CAPTURE
        )) {
            foreach ($m[0] as [$match, $offset]) {
                $regions[] = [$offset, $offset + strlen($match)];
            }
        }

        return $regions;
    }

    /**
     * @param  array<int, array{0: int, 1: int}>  $regions
     */
    private static function offsetInRegions(int $offset, array $regions): bool
    {
        foreach ($regions as [$start, $end]) {
            if ($offset >= $start && $offset <= $end) {
                return true;
            }
        }

        return false;
    }

    // ---------------------------------------------------------------------
    // Image inspection
    // ---------------------------------------------------------------------

    /**
     * Identify an image purely from its leading bytes.
     *
     * Content-Type is attacker- and misconfiguration-controlled; the magic
     * number is not. Returns null when the bytes are not a supported image.
     */
    public static function detectImageType(string $bytes): ?string
    {
        if (strlen($bytes) < 12) {
            return null;
        }

        // An HTML document served with a 200 is the classic false positive.
        $head = strtolower(substr($bytes, 0, 1024));
        if (str_contains($head, '<!doctype html') || str_contains($head, '<html')) {
            return null;
        }

        if (str_starts_with($bytes, "\x89PNG\r\n\x1a\n")) {
            return 'png';
        }

        if (str_starts_with($bytes, "\xFF\xD8\xFF")) {
            return 'jpeg';
        }

        if (str_starts_with($bytes, 'GIF87a') || str_starts_with($bytes, 'GIF89a')) {
            return 'gif';
        }

        if (str_starts_with($bytes, 'RIFF') && substr($bytes, 8, 4) === 'WEBP') {
            return 'webp';
        }

        if (str_starts_with($bytes, "\x00\x00\x01\x00")) {
            return 'ico';
        }

        // SVG is text. Require the root element to actually be present, so an
        // XML error page is not mistaken for a vector logo.
        if (str_contains($head, '<svg') || preg_match('/<svg[\s>]/i', substr($bytes, 0, 4096))) {
            // A script inside an SVG is a stored-XSS vector once it is served
            // from our own origin. Refuse rather than sanitise.
            if (preg_match('#<script[\s>]#i', $bytes)) {
                return null;
            }

            return 'svg';
        }

        return null;
    }

    /**
     * @return array{0: ?int, 1: ?int} [width, height]; null where unknown
     */
    public static function imageDimensions(string $bytes, string $type): array
    {
        return match ($type) {
            'png' => self::pngDimensions($bytes),
            'gif' => self::gifDimensions($bytes),
            'jpeg' => self::jpegDimensions($bytes),
            'webp' => self::webpDimensions($bytes),
            'svg' => self::svgDimensions($bytes),
            default => [null, null],
        };
    }

    /** @return array{0: ?int, 1: ?int} */
    private static function pngDimensions(string $bytes): array
    {
        // IHDR is always the first chunk: 8-byte signature, 4 length, 4 type.
        if (strlen($bytes) < 24 || substr($bytes, 12, 4) !== 'IHDR') {
            return [null, null];
        }

        $parts = unpack('Nwidth/Nheight', substr($bytes, 16, 8));

        return $parts ? [(int) $parts['width'], (int) $parts['height']] : [null, null];
    }

    /** @return array{0: ?int, 1: ?int} */
    private static function gifDimensions(string $bytes): array
    {
        if (strlen($bytes) < 10) {
            return [null, null];
        }

        $parts = unpack('vwidth/vheight', substr($bytes, 6, 4));

        return $parts ? [(int) $parts['width'], (int) $parts['height']] : [null, null];
    }

    /**
     * Walk the JPEG marker chain to the first SOFn, which carries the size.
     *
     * @return array{0: ?int, 1: ?int}
     */
    private static function jpegDimensions(string $bytes): array
    {
        $len = strlen($bytes);
        $i = 2;

        while ($i + 9 < $len) {
            if ($bytes[$i] !== "\xFF") {
                $i++;

                continue;
            }

            $marker = ord($bytes[$i + 1]);

            // Standalone markers carry no length.
            if ($marker === 0xD8 || $marker === 0x01 || ($marker >= 0xD0 && $marker <= 0xD7)) {
                $i += 2;

                continue;
            }

            if ($marker === 0xD9 || $marker === 0xDA) {
                break; // end of image / start of scan
            }

            $segment = unpack('n', substr($bytes, $i + 2, 2));
            if (! $segment) {
                break;
            }

            $segLen = (int) $segment[1];

            // SOF0..SOF15, excluding the non-frame markers DHT (C4), JPG (C8) and DAC (CC).
            if ($marker >= 0xC0 && $marker <= 0xCF && ! in_array($marker, [0xC4, 0xC8, 0xCC], true)) {
                $parts = unpack('nheight/nwidth', substr($bytes, $i + 5, 4));
                if ($parts) {
                    return [(int) $parts['width'], (int) $parts['height']];
                }
            }

            $i += 2 + $segLen;
        }

        return [null, null];
    }

    /** @return array{0: ?int, 1: ?int} */
    private static function webpDimensions(string $bytes): array
    {
        if (strlen($bytes) < 30) {
            return [null, null];
        }

        $chunk = substr($bytes, 12, 4);

        if ($chunk === 'VP8 ') {
            $parts = unpack('vwidth/vheight', substr($bytes, 26, 4));

            return $parts ? [(int) $parts['width'] & 0x3FFF, (int) $parts['height'] & 0x3FFF] : [null, null];
        }

        if ($chunk === 'VP8L') {
            $bits = unpack('V', substr($bytes, 21, 4))[1] ?? 0;

            return [($bits & 0x3FFF) + 1, (($bits >> 14) & 0x3FFF) + 1];
        }

        if ($chunk === 'VP8X') {
            $w = ord($bytes[24]) | (ord($bytes[25]) << 8) | (ord($bytes[26]) << 16);
            $h = ord($bytes[27]) | (ord($bytes[28]) << 8) | (ord($bytes[29]) << 16);

            return [$w + 1, $h + 1];
        }

        return [null, null];
    }

    /** @return array{0: ?int, 1: ?int} */
    private static function svgDimensions(string $bytes): array
    {
        $head = substr($bytes, 0, 4096);

        $width = null;
        $height = null;

        if (preg_match('#<svg\b[^>]*\bwidth\s*=\s*(["\']?)\s*(\d+(?:\.\d+)?)\s*(?:px)?\1#i', $head, $m)) {
            $width = (int) round((float) $m[2]);
        }

        if (preg_match('#<svg\b[^>]*\bheight\s*=\s*(["\']?)\s*(\d+(?:\.\d+)?)\s*(?:px)?\1#i', $head, $m)) {
            $height = (int) round((float) $m[2]);
        }

        // A viewBox is a better answer than a missing attribute: a responsive
        // logo has no width/height but does declare its aspect and size.
        if (($width === null || $height === null)
            && preg_match('#<svg\b[^>]*\bviewBox\s*=\s*(["\'])\s*[\d.\-]+\s+[\d.\-]+\s+([\d.]+)\s+([\d.]+)\s*\1#i', $head, $m)) {
            $width ??= (int) round((float) $m[2]);
            $height ??= (int) round((float) $m[3]);
        }

        return [$width, $height];
    }

    private static function extensionFor(string $type): string
    {
        return match ($type) {
            'jpeg' => 'jpg',
            'svg' => 'svg',
            'webp' => 'webp',
            'gif' => 'gif',
            default => 'png',
        };
    }

    // ---------------------------------------------------------------------
    // URL handling
    // ---------------------------------------------------------------------

    /**
     * Turn a stored `website` value into an absolute homepage URL.
     *
     * Returns null when there is no website, and '' when there is one but it
     * cannot be turned into an HTTP URL — a separate outcome, because "this
     * organization has no site" and "this row holds junk" are different facts.
     */
    public static function normalizeWebsiteUrl(?string $website): ?string
    {
        $website = trim((string) $website);

        if ($website === '') {
            return null;
        }

        // Strip a zero-width or non-breaking character a paste can leave behind.
        $website = str_replace(["\xC2\xA0", "\xE2\x80\x8B"], ' ', $website);
        $website = trim($website);

        // Refuse anything that is obviously not a web address. These are the
        // shapes that appear in hand-entered data: an email, a phone-ish blob,
        // a bare "n/a", a Facebook handle, a stray path.
        if (preg_match('#^(mailto|tel|javascript|data|ftp|sms|whatsapp):#i', $website)) {
            return '';
        }

        if (str_contains($website, '@')) {
            return '';
        }

        if (str_contains($website, ' ')) {
            return '';
        }

        if (in_array(strtolower($website), ['n/a', 'na', 'none', 'nil', 'null', '-', '--', 'unknown', 'tbd', 'www', 'http://', 'https://'], true)) {
            return '';
        }

        $hasScheme = (bool) preg_match('#^(https?)://#i', $website);

        if (! $hasScheme) {
            $website = 'https://'.$website;
        }

        $parts = parse_url($website);

        if ($parts === false || empty($parts['host'])) {
            return '';
        }

        $host = strtolower($parts['host']);

        if (! preg_match('/^[a-z0-9]([a-z0-9\-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9\-]*[a-z0-9])?)+$/', $host)) {
            return '';
        }

        $url = strtolower($parts['scheme']).'://'.$host;

        if (! empty($parts['port'])) {
            $url .= ':'.$parts['port'];
        }

        $url .= $parts['path'] ?? '/';

        if (! empty($parts['query'])) {
            $url .= '?'.$parts['query'];
        }

        return $url;
    }

    /** Resolve a possibly-relative href against the page it was found on. */
    public static function resolveUrl(string $base, string $href): ?string
    {
        $href = trim($href);

        if ($href === '' || str_starts_with($href, '#') || str_starts_with($href, 'javascript:') || str_starts_with($href, 'data:')) {
            return null;
        }

        try {
            $resolved = (string) UriResolver::resolve(new Uri($base), new Uri($href));
        } catch (Throwable) {
            return null;
        }

        $parts = parse_url($resolved);
        $scheme = strtolower($parts['scheme'] ?? '');

        // Never chase a link off HTTP: no FTP, no file://, no gopher.
        if (! in_array($scheme, ['http', 'https'], true)) {
            return null;
        }

        return $resolved;
    }

    // ---------------------------------------------------------------------
    // robots.txt
    // ---------------------------------------------------------------------

    /**
     * @return PromiseInterface<bool>
     */
    private function robotsPermitsAsync(string $url): PromiseInterface
    {
        if (! $this->respectRobots) {
            return Create::promiseFor(true);
        }

        $host = (string) parse_url($url, PHP_URL_HOST);
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        $robotsUrl = $scheme.'://'.$host.'/robots.txt';

        if (array_key_exists($host, $this->robotsCache)) {
            return Create::promiseFor(self::robotsAllows($this->robotsCache[$host], $url));
        }

        return $this->getAsync($robotsUrl, 512 * 1024)
            ->then(function (array $response) use ($host, $url): bool {
                if (isset($response['error']) || ($response['status'] ?? 0) >= 400) {
                    // No robots.txt is not a prohibition.
                    $this->robotsCache[$host] = false;

                    return true;
                }

                $rules = self::parseRobots((string) $response['body']);
                $this->robotsCache[$host] = $rules;

                return self::robotsAllows($rules, $url);
            })
            ->otherwise(function () use ($host): bool {
                $this->robotsCache[$host] = false;

                return true;
            });
    }

    /**
     * Parse the `User-agent: *` group of a robots.txt.
     *
     * A full RFC 9309 implementation is overkill here; this reads the wildcard
     * group, which is what governs a crawler with no declared identity, and
     * keeps the longest-match-wins rule for Allow vs Disallow.
     *
     * @return array<int, string> ['Allow' => '/path', ...]
     */
    public static function parseRobots(string $body): array
    {
        $wildcard = [];
        $first = [];
        $current = [];
        $currentIsWildcard = false;
        $lastWasAgent = false;

        foreach (preg_split('/\r\n|\r|\n/', $body) ?: [] as $line) {
            $line = trim(explode('#', $line, 2)[0]);

            if ($line === '') {
                // A blank line terminates a group. Without this the rules of
                // `User-agent: BadBot` leak into the `User-agent: *` group and
                // a site that disallows nothing for us ends up fully blocked.
                self::keepRobotsGroup($wildcard, $first, $current, $currentIsWildcard);
                $current = [];
                $lastWasAgent = false;

                continue;
            }

            $parts = explode(':', $line, 2);
            if (count($parts) !== 2) {
                continue;
            }

            $field = strtolower(trim($parts[0]));
            $value = trim($parts[1]);

            if ($field === 'user-agent') {
                // A User-agent line right after another User-agent line joins
                // the group already being built; one that follows a rule starts
                // a new group.
                if (! $lastWasAgent) {
                    self::keepRobotsGroup($wildcard, $first, $current, $currentIsWildcard);
                    $current = [];
                }

                $currentIsWildcard = strcasecmp($value, '*') === 0;
                $lastWasAgent = true;

                continue;
            }

            $lastWasAgent = false;

            if (in_array($field, ['allow', 'disallow'], true) && $value !== '') {
                $current[] = ucfirst($field).' '.$value;
            }
        }

        if ($current !== []) {
            self::keepRobotsGroup($wildcard, $first, $current, $currentIsWildcard);
        }

        // The wildcard group governs a crawler with no declared identity. Only
        // if the file names no wildcard at all do we fall back to the first.
        return $wildcard !== [] ? $wildcard : $first;
    }

    /**
     * @param  array<int, string>  $wildcard
     * @param  array<int, string>  $first
     * @param  array<int, string>  $group
     */
    private static function keepRobotsGroup(array &$wildcard, array &$first, array $group, bool $isWildcard): void
    {
        if ($isWildcard && $wildcard === []) {
            $wildcard = $group;

            return;
        }

        if (! $isWildcard && $first === []) {
            $first = $group;
        }
    }

    /** @param array<int, string>|false $rules */
    public static function robotsAllows(array|false $rules, string $url): bool
    {
        if ($rules === false || $rules === []) {
            return true;
        }

        $path = (string) (parse_url($url, PHP_URL_PATH) ?: '/');
        $path .= (string) (parse_url($url, PHP_URL_QUERY) ? '?'.parse_url($url, PHP_URL_QUERY) : '');

        $best = null;      // [length, allowed]
        $bestLength = -1;

        foreach ($rules as $rule) {
            [$directive, $pattern] = array_pad(explode(' ', $rule, 2), 2, '');
            $pattern = rtrim(trim($pattern), '$');

            if ($pattern === '') {
                continue;
            }

            if (self::robotsPatternMatches($pattern, $path)) {
                $length = strlen($pattern);

                if ($length > $bestLength) {
                    $bestLength = $length;
                    $best = strcasecmp($directive, 'allow') === 0;
                }
            }
        }

        return $best ?? true;
    }

    /** robots.txt wildcards: * (any run) and $ (end anchor). */
    private static function robotsPatternMatches(string $pattern, string $path): bool
    {
        $regex = '';
        $len = strlen($pattern);

        for ($i = 0; $i < $len; $i++) {
            $char = $pattern[$i];

            if ($char === '*') {
                $regex .= '.*';
            } elseif ($char === '$') {
                $regex .= '$';
            } else {
                $regex .= preg_quote($char, '#');
            }
        }

        return (bool) preg_match('#^'.$regex.'#', $path);
    }

    // ---------------------------------------------------------------------
    // HTTP
    // ---------------------------------------------------------------------

    /**
     * @return PromiseInterface<array{status?: int, content_type?: string, body?: string, url?: string, error?: string, detail?: string}>
     */
    private function fetchPageAsync(string $url): PromiseInterface
    {
        return $this->getAsync($url, self::MAX_HTML_BYTES)->then(
            function (array $response) use ($url): array {
                if (isset($response['error'])) {
                    // Several university sites have a broken certificate on
                    // their canonical host. One retry over plain HTTP is a
                    // proportionate response for a logo, and it is recorded.
                    $fallback = self::toHttp($url);

                    if ($fallback !== null && $response['error'] === 'tls_error') {
                        return $this->getAsync($fallback, self::MAX_HTML_BYTES)->then(
                            function (array $r) use ($url): array {
                                if (isset($r['error'])) {
                                    return $r + ['http_fallback' => true];
                                }

                                $r['http_fallback'] = true;

                                return $r;
                            }
                        );
                    }

                    return $response;
                }

                // A 200 that is actually HTML error content.
                $head = strtolower(substr($response['body'], 0, 1024));
                if (str_contains($head, '<!doctype html') && ! str_contains($head, '<html')) {
                    $response['error'] = 'html_not_page';
                }

                return $response;
            }
        );
    }

    private static function toHttp(string $url): ?string
    {
        if (str_starts_with(strtolower($url), 'http://')) {
            return null;
        }

        $http = preg_replace('#^https://#i', 'http://', $url, 1);

        return is_string($http) ? $http : null;
    }

    /**
     * One polite GET. Never rejects; failures come back as an `error` key.
     *
     * The per-host delay is applied through Guzzle's `delay` option, which the
     * curl multi handler honours without blocking the event loop — a sleeping
     * worker would have stalled every other host in flight.
     *
     * @return PromiseInterface<array<string, mixed>>
     */
    private function getAsync(string $url, int $maxBytes): PromiseInterface
    {
        $host = strtolower((string) (parse_url($url, PHP_URL_HOST) ?: ''));
        $delayMs = $this->reserveHostSlot($host);

        // The post-redirect URL lives on TransferStats, not on the Response —
        // Guzzle only exposes it to an on_stats callback. It matters because
        // relative logo hrefs have to resolve against where the page actually
        // ended up, which is rarely the URL we asked for.
        $effectiveUrl = $url;

        try {
            return $this->client->getAsync($url, [
                'timeout' => $this->timeout,
                'connect_timeout' => $this->connectTimeout,
                'delay' => $delayMs,
                'verify' => $this->verifyTls,
                'http_errors' => true,
                'stream' => true,
                'on_stats' => function (TransferStats $stats) use (&$effectiveUrl): void {
                    $effective = $stats->getEffectiveUri();

                    if ($effective !== null) {
                        $effectiveUrl = (string) $effective;
                    }
                },
                'allow_redirects' => [
                    'max' => 5,
                    'strict' => false,
                    'referer' => true,
                    'protocols' => ['http', 'https'],
                ],
                'on_headers' => function ($response) use ($maxBytes): void {
                    $length = $response->getHeaderLine('Content-Length');
                    if ($length !== '' && (int) $length > $maxBytes) {
                        throw new RuntimeException('response_too_large');
                    }
                },
            ])->then(
                function ($response) use ($maxBytes, &$effectiveUrl): array {
                    $body = $response->getBody();
                    $bytes = $body->read($maxBytes + 1);

                    if (strlen($bytes) > $maxBytes) {
                        return [
                            'error' => 'response_too_large',
                            'detail' => '>'.($maxBytes / 1024).'KB',
                        ];
                    }

                    return [
                        'status' => $response->getStatusCode(),
                        'content_type' => trim(explode(';', $response->getHeaderLine('Content-Type'))[0]),
                        'body' => $bytes,
                        'url' => $effectiveUrl,
                    ];
                },
                function (Throwable $e) use ($url): array {
                    return [
                        'error' => classify($e),
                        'detail' => self::describe($e),
                        'url' => $url,
                    ];
                }
            );
        } catch (Throwable $e) {
            return Create::promiseFor([
                'error' => classify($e),
                'detail' => self::getMessage($e),
                'url' => $url,
            ]);
        }
    }

    /** @return PromiseInterface<array<string, mixed>> */
    private function fetchImageAsync(string $url): PromiseInterface
    {
        return $this->getAsync($url, self::MAX_IMAGE_BYTES);
    }

    /**
     * Book this host's next slot and return the wait in milliseconds.
     *
     * Each host is serialised against itself, so the crawler can never have
     * more than one request in flight to any single server.
     */
    private function reserveHostSlot(string $host): int
    {
        $now = microtime(true);
        $last = $this->lastRequestAt[$host] ?? null;
        $waitMs = 0;

        if ($last !== null) {
            $elapsed = $now - $last;
            if ($elapsed < $this->hostDelay) {
                $waitMs = (int) ceil(($this->hostDelay - $elapsed) * 1000);
            }
        }

        $this->lastRequestAt[$host] = $now + ($waitMs / 1000);

        return $waitMs;
    }

    /** Short, non-leaking description of a transport failure. */
    private static function describe(Throwable $e): string
    {
        $message = self::getMessage($e);

        // Strip anything URL-shaped: query strings on these sites routinely
        // carry tracking tokens and session ids.
        $message = preg_replace('#https?://\S+#', '<url>', $message) ?? $message;

        return mb_substr(trim($message), 0, 200);
    }

    private static function getMessage(Throwable $e): string
    {
        if ($e instanceof RequestException && $e->hasResponse()) {
            return 'HTTP '.$e->getResponse()->getStatusCode();
        }

        if ($e instanceof ConnectException) {
            return self::cleanHandlerMessage($e);
        }

        return $e->getMessage();
    }

    private static function cleanHandlerMessage(Throwable $e): string
    {
        $message = $e->getMessage();

        if (preg_match('/cURL error \d+:?\s*([^(]*)/i', $message, $m)) {
            $message = trim($m[1]);
        }

        return $message !== '' ? $message : $e->getMessage();
    }

    // ---------------------------------------------------------------------
    // Storage
    // ---------------------------------------------------------------------

    /**
     * Persist the bytes and return the path stored in `logo_path`.
     *
     * Returns a disk-relative path such as `organization-logos/<slug>.png`.
     */
    private function store(Organization $organization, string $bytes, string $extension): string
    {
        $slug = self::safeSlug($organization);
        $relative = self::DIRECTORY.'/'.$slug.'.'.$extension;

        $disk = Storage::disk(self::DISK);

        if (! $disk->exists(self::DIRECTORY)) {
            $disk->makeDirectory(self::DIRECTORY, 0755, true);
        }

        $disk->put($relative, $bytes, 'public');

        // The command runs as the deploy user while php-fpm serves as
        // www-data, so permissions are set explicitly rather than inherited
        // from a umask nobody has checked.
        $absolute = $disk->path($relative);
        @chmod($absolute, 0644);
        @chmod(dirname($absolute), 0755);

        clearstatcache(true, $absolute);

        if (! is_file($absolute) || ! is_readable($absolute)) {
            throw new RuntimeException('stored file is not readable: '.$relative);
        }

        return $relative;
    }

    /**
     * A filesystem-safe, collision-free base name for the logo file.
     *
     * Slugs are unique in the table today, but nothing in the schema enforces
     * that, and two organizations sharing a slug must not overwrite each
     * other's logo — so the id is folded in.
     */
    private static function safeSlug(Organization $organization): string
    {
        $base = $organization->slug ?: Str($organization->name)->slug()->toString();
        $base = strtolower(preg_replace('/[^A-Za-z0-9_-]+/', '-', $base) ?? '');
        $base = trim($base, '-');

        if ($base === '') {
            $base = 'organization';
        }

        $base = mb_substr($base, 0, 180);

        return $base.'-'.$organization->id;
    }

    // ---------------------------------------------------------------------
    // Result shaping
    // ---------------------------------------------------------------------

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function result(Organization $organization, string $status, ?string $reason, array $extra = []): array
    {
        return array_merge([
            'organization_id' => $organization->id,
            'slug' => $organization->slug,
            'name' => $organization->name,
            'website' => $organization->website,
            'status' => $status,
            'reason' => $reason,
            'logo_path' => null,
        ], array_filter($extra, static fn ($v) => $v !== null));
    }
}

/**
 * Map a transport failure to a stable, machine-readable reason.
 *
 * These strings land in storage/logs/organization-logos.json and are the
 * input to the manual triage that follows, so they must group by cause rather
 * than by exception class.
 */
function classify(Throwable $e): string
{
    $message = $e->getMessage();
    $lower = strtolower($message);

    if (str_contains($lower, 'response_too_large')) {
        return 'response_too_large';
    }

    if (str_contains($lower, 'certificate') || str_contains($lower, 'ssl') || str_contains($lower, 'tls')) {
        return 'tls_error';
    }

    if (str_contains($lower, 'timed out') || str_contains($lower, 'timeout')) {
        return str_contains($lower, 'connect') || $e instanceof ConnectException ? 'connect_timeout' : 'timeout';
    }

    if (str_contains($lower, 'could not resolve') || str_contains($lower, 'name or service not known')
        || str_contains($lower, 'getaddrinfo') || str_contains($lower, 'nodename nor servname')) {
        return 'dns_failure';
    }

    if (str_contains($lower, 'connection refused')) {
        return 'connection_refused';
    }

    if (str_contains($lower, 'network is unreachable') || str_contains($lower, 'no route to host')) {
        return 'network_unreachable';
    }

    if ($e instanceof RequestException) {
        $status = $e->getResponse()?->getStatusCode() ?? 0;

        if (in_array($status, [401, 403], true)) {
            return 'forbidden';
        }

        if (in_array($status, [404, 410], true)) {
            return 'not_found';
        }

        if ($status >= 500) {
            return 'server_error';
        }

        return 'http_error_'.$status;
    }

    if ($e instanceof ConnectException) {
        return 'connect_error';
    }

    return 'exception';
}
