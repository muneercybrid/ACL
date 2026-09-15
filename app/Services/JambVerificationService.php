<?php

namespace App\Services;

use App\Models\StudentExternalIdentity;
use Illuminate\Support\Facades\Http;

class JambVerificationService
{
    public function normalize(?string $reg): string
    {
        return strtoupper(trim((string) $reg));
    }

    /** Permissive format gate: variable-length historical numbers allowed (6–15 alnum). */
    public function isValidFormat(string $reg): bool
    {
        return strlen($reg) >= 6 && strlen($reg) <= 15 && preg_match('/^[0-9A-Z]+$/', $reg) === 1;
    }

    public function isAlreadyRegistered(string $reg): bool
    {
        return StudentExternalIdentity::where('provider', 'jamb')
            ->where('identifier', $reg)
            ->exists();
    }

    /**
     * Verify against JAMB. Delegates to legacy JambService when present,
     * otherwise calls the configured eFacility endpoint.
     * Never throws: returns ['success' => bool, 'data'|'message' => ...].
     */
    public function verify(string $reg, int $year): array
    {
        if (class_exists(\App\Services\JambService::class) && method_exists(\App\Services\JambService::class, 'verify')) {
            try {
                $res = app(\App\Services\JambService::class)->verify($reg, $year);
                if (is_array($res)) {
                    return [
                        'success' => (bool) ($res['success'] ?? false),
                        'data' => $res['data'] ?? $res,
                        'message' => $res['message'] ?? null,
                    ];
                }
                return ['success' => false, 'message' => 'Unexpected JAMB response.'];
            } catch (\Throwable $e) {
                return ['success' => false, 'message' => 'JAMB verification service is unreachable right now. Please try again shortly.'];
            }
        }

        $key = config('services.jamb.key');
        $url = config('services.jamb.url');

        if (! $key || ! $url) {
            return ['success' => false, 'message' => 'JAMB verification is not configured on this server yet. Please use manual verification for now.'];
        }

        try {
            $res = Http::withToken($key)->timeout(20)->get($url, ['reg' => $reg, 'year' => $year]);

            if (! $res->successful()) {
                return ['success' => false, 'message' => "We couldn't verify this JAMB registration number. Please check the number and try again."];
            }

            $json = $res->json() ?? [];

            return ['success' => true, 'data' => $json['data'] ?? $json];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => 'JAMB verification service is unreachable right now. Please try again shortly.'];
        }
    }
}
