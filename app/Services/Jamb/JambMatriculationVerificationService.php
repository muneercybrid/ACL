<?php

namespace App\Services\Jamb;

use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use DOMDocument;
use DOMXPath;

class JambMatriculationVerificationService
{
    /**
     * Verify a JAMB registration number against the JAMB Matriculation List.
     *
     * Uses the direct ASP.NET Web Forms POST workflow against JAMB's
     * CheckMatriculationList endpoint. No browser automation required.
     */
    public function verify(
        int $examYear,
        string $registrationNumber,
        string $examType = 'UTME'
    ): array {
        $registrationNumber = strtoupper(trim($registrationNumber));

        // Resolve the JAMB examination option value for the requested year.
        try {
            $options = $this->getExaminationOptions();
        } catch (RuntimeException $e) {
            return $this->providerFailure('provider_unavailable', 'JAMB could not be reached. Please try again shortly.');
        }

        $examOption = $this->findExaminationOption($options, $examYear, $examType);

        if ($examOption === null) {
            return [
                'verified'             => false,
                'status'               => 'invalid_input',
                'message'              => "JAMB does not currently expose {$examYear} {$examType} on its matriculation portal.",
                'name'                 => null,
                'institution'          => null,
                'programme'            => null,
                'jamb_exam_value'      => null,
                'jamb_exam_text'       => null,
                'provider_status_code' => null,
                'provider_url'         => config('services.jamb.matriculation_url'),
                'actions'              => [],
                'raw_text'             => 'no_examination_option_for_year',
            ];
        }

        $matriculationUrl = config('services.jamb.matriculation_url');

        // Fresh per-request session: a new cookie jar isolates this
        // verification from every other candidate's flow.
        $jar = new CookieJar();

        try {
            // Step 1: Fresh GET -> session cookies + ASP.NET hidden state.
            $state = $this->getFreshState($matriculationUrl, $jar);

            // Step 2: POST the search using the same session.
            $result = $this->postSearch($matriculationUrl, $state, $jar, $examOption['value'], $registrationNumber);
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            // cURL error 28 = operation timed out. Everything else is a
            // network/DNS/TLS/provider reachability failure.
            $isTimeout = str_contains(strtolower($e->getMessage()), 'timed out')
                || str_contains($e->getMessage(), 'cURL error 28');

            return $this->providerFailure(
                $isTimeout ? 'provider_timeout' : 'provider_unavailable',
                $isTimeout
                    ? 'JAMB took too long to respond. Please try again shortly.'
                    : 'JAMB could not be reached. Please try again shortly.'
            );
        } catch (\Illuminate\Http\Client\RequestException $e) {
            return $this->providerFailure('provider_unavailable', 'JAMB could not be reached. Please try again shortly.');
        } catch (RuntimeException $e) {
            return $this->providerFailure('temporary_failure', 'JAMB verification could not be completed right now. Please try again shortly.');
        }

        $result['jamb_exam_value'] = $examOption['value'];
        $result['jamb_exam_text'] = $examOption['text'];

        return $result;
    }

    /**
     * GET the CheckMatriculationList page and extract the ASP.NET Web Forms
     * state fields (__VIEWSTATE, __VIEWSTATEGENERATOR, __EVENTVALIDATION)
     * together with the session cookies.
     */
    private function getFreshState(string $url, CookieJar $jar): array
    {
        $start = microtime(true);

        $response = $this->http($jar)->get($url);

        if (! $response->successful()) {
            throw new RuntimeException('JAMB returned an unexpected HTTP status.');
        }

        $html = $response->body();

        if (! is_string($html) || $html === '') {
            throw new RuntimeException('JAMB returned an empty page.');
        }

        $state = $this->parseHiddenFields($html);

        if (
            $state['__VIEWSTATE'] === null
            || $state['__VIEWSTATEGENERATOR'] === null
            || $state['__EVENTVALIDATION'] === null
        ) {
            throw new RuntimeException('JAMB returned a page missing required ASP.NET state fields.');
        }

        return [
            'viewstate'          => $state['__VIEWSTATE'],
            'viewstategenerator' => $state['__VIEWSTATEGENERATOR'],
            'eventvalidation'    => $state['__EVENTVALIDATION'],
            'elapsed'            => round((microtime(true) - $start) * 1000),
        ];
    }

    /**
     * POST the search form to JAMB using the fresh ASP.NET state.
     *
     * __EVENTTARGET=lnkSearch triggers the server-side postback equivalent
     * to clicking the "Fetch My Details" link.
     */
    private function postSearch(
        string $url,
        array $state,
        CookieJar $jar,
        string $examValue,
        string $registrationNumber
    ): array {
        $start = microtime(true);

        $payload = [
            '__VIEWSTATE'          => $state['viewstate'],
            '__VIEWSTATEGENERATOR' => $state['viewstategenerator'],
            '__EVENTVALIDATION'    => $state['eventvalidation'],
            '__EVENTTARGET'        => 'lnkSearch',
            '__EVENTARGUMENT'      => '',
            '__SCROLLPOSITIONX'    => '0',
            '__SCROLLPOSITIONY'    => '0',
            'ddlExamination'       => $examValue,
            'txtRegNumber'         => $registrationNumber,
        ];

        $response = $this->http($jar)
            ->asForm()
            ->withHeaders(['Referer' => $url])
            ->post($url, $payload);

        if (! $response->successful()) {
            throw new RuntimeException('JAMB returned an unexpected HTTP status after verification.');
        }

        $html = $response->body();

        if (! is_string($html) || $html === '') {
            throw new RuntimeException('JAMB returned an empty verification response.');
        }

        $result = $this->parseResult($html);

        $result['elapsed_ms'] = round((microtime(true) - $start) * 1000);

        return $result;
    }

    /**
     * Parse hidden ASP.NET Web Forms fields from raw HTML.
     */
    private function parseHiddenFields(string $html): array
    {
        $doc = $this->loadDom($html);
        $xpath = new DOMXPath($doc);

        $fields = [
            '__VIEWSTATE'          => null,
            '__VIEWSTATEGENERATOR' => null,
            '__EVENTVALIDATION'    => null,
        ];

        foreach (array_keys($fields) as $name) {
            $nodes = $xpath->query("//input[@name='{$name}']");
            if ($nodes && $nodes->length > 0) {
                $fields[$name] = $nodes->item(0)->getAttribute('value');
            }
        }

        return $fields;
    }

    /**
     * Parse the JAMB verification result HTML into a normalized ACL result.
     */
    private function parseResult(string $html): array
    {
        $doc = $this->loadDom($html);
        $xpath = new DOMXPath($doc);
        $text = $this->extractNormalizedText($doc);

        // -------- Success --------
        if (str_contains($text, 'Congratulations, you are on the Matriculation List')) {
            return [
                'verified'             => true,
                'name'                 => $this->extractName($xpath),
                'institution'          => $this->extractField($xpath, 'Institution'),
                'programme'            => $this->extractField($xpath, 'Programme'),
                'status'               => $this->extractStatus($xpath),
                'provider_status_code' => 200,
                'provider_url'         => config('services.jamb.matriculation_url'),
                'actions'              => [],
                'raw_text'             => 'jamb_matriculation_success',
            ];
        }

        // -------- Deterministic negative: candidate not registered --------
        if (str_contains($text, 'You Did not Register for this Examination')) {
            return [
                'verified'             => false,
                'name'                 => null,
                'institution'          => null,
                'programme'            => null,
                'status'               => 'not_found',
                'message'              => 'This registration number was not found for the selected examination year. Please confirm the year and registration number and try again.',
                'provider_status_code' => 200,
                'provider_url'         => config('services.jamb.matriculation_url'),
                'actions'              => [],
                'raw_text'             => 'jamb_candidate_not_registered',
            ];
        }

        // -------- Ambiguous: page received but not interpretable --------
        return [
            'verified'             => false,
            'name'                 => null,
            'institution'          => null,
            'programme'            => null,
            'status'               => 'ambiguous',
            'message'              => 'JAMB returned an unexpected response. Please try again shortly.',
            'provider_status_code' => 200,
            'provider_url'         => config('services.jamb.matriculation_url'),
            'actions'              => [],
            'raw_text'             => 'jamb_response_uninterpretable',
        ];
    }

    /**
     * Extract the candidate name from the profile section.
     *
     * Known structure: <div class="profile-info">...<h1>Candidate Name</h1>
     */
    private function extractName(DOMXPath $xpath): ?string
    {
        $nodes = $xpath->query("//div[contains(concat(' ', normalize-space(@class), ' '), ' profile-info ')]//h1");
        if ($nodes && $nodes->length > 0) {
            $name = trim($nodes->item(0)->textContent);
            if ($name !== '' && $name !== 'JAMB Matriculation List') {
                return $name;
            }
        }

        return null;
    }

    /**
     * Extract a labelled field (Institution, Programme) from the result page.
     *
     * JAMB uses: <a>Institution: <span>value</span></a>
     */
    private function extractField(DOMXPath $xpath, string $label): ?string
    {
        $nodes = $xpath->query("//a[contains(normalize-space(text()), '{$label}:')]/span");
        if ($nodes && $nodes->length > 0) {
            $value = trim($nodes->item(0)->textContent);
            if ($value !== '') {
                return $value;
            }
        }

        // Broader fallback: any <span> whose preceding bold/label is the label.
        $nodes = $xpath->query("//a[contains(normalize-space(.), '{$label}')]");
        if ($nodes && $nodes->length > 0) {
            $value = trim($nodes->item(0)->textContent);
            $value = str_replace($label . ':', '', $value);
            $value = trim($value);
            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }

    /**
     * Extract the JAMB verification status from the normalized page text.
     */
    private function extractStatus(DOMXPath $xpath): ?string
    {
        // Try <b> elements near "Status:" label.
        $nodes = $xpath->query("//strong[contains(text(), 'Status')]/following-sibling::*[1]//text()");
        if ($nodes && $nodes->length > 0) {
            $status = trim($nodes->item(0)->textContent);
            if ($status !== '' && $status !== 'Status:') {
                return $status;
            }
        }

        // Try <b> elements that contain the success message.
        $nodes = $xpath->query("//b[contains(normalize-space(text()), 'Congratulations')]");
        if ($nodes && $nodes->length > 0) {
            return trim($nodes->item(0)->textContent);
        }

        return null;
    }

    /**
     * Extract text content from the DOM, normalizing whitespace.
     */
    private function extractNormalizedText(DOMDocument $doc): string
    {
        $text = $doc->textContent ?? '';
        return preg_replace('/\s+/', ' ', trim($text));
    }

    /**
     * Build a safe provider-level failure result.
     */
    private function providerFailure(string $status, string $message): array
    {
        return [
            'verified'             => false,
            'name'                 => null,
            'institution'          => null,
            'programme'            => null,
            'status'               => $status,
            'message'              => $message,
            'provider_status_code' => null,
            'provider_url'         => config('services.jamb.matriculation_url'),
            'actions'              => [],
            'raw_text'             => $status,
        ];
    }

    /**
     * Retrieve the examination options currently exposed by JAMB.
     * Cached for 1 hour to avoid an extra round-trip per verification.
     */
    private function getExaminationOptions(): array
    {
        return Cache::remember('jamb_exam_options', 3600, function () {
            $url = config('services.jamb.matriculation_url');

            // A fresh, independent session for options retrieval.
            $jar = new CookieJar();

            $response = $this->http($jar)->get($url);

            if (! $response->successful()) {
                throw new RuntimeException('JAMB returned an unexpected HTTP status.');
            }

            $html = $response->body();

            if (! is_string($html) || $html === '') {
                throw new RuntimeException('JAMB returned an empty examination options page.');
            }

            return $this->extractOptionsFromHtml($html);
        });
    }

    /**
     * Parse examination <option> values from the JAMB page HTML.
     */
    private function extractOptionsFromHtml(string $html): array
    {
        $doc = $this->loadDom($html);
        $xpath = new DOMXPath($doc);

        $options = [];
        $nodes = $xpath->query("//select[@id='ddlExamination']/option");

        if (! $nodes || $nodes->length === 0) {
            throw new RuntimeException('JAMB returned a page without an examination dropdown.');
        }

        foreach ($nodes as $node) {
            $value = trim((string) $node->getAttribute('value'));
            $text = trim($node->textContent);

            if ($value === '' || $text === '') {
                continue;
            }

            $options[] = ['value' => $value, 'text' => $text];
        }

        return $options;
    }

    /**
     * Find the examination option matching the requested year and type.
     */
    private function findExaminationOption(
        array $options,
        int $examYear,
        string $examType
    ): ?array {
        foreach ($options as $option) {
            $text = $option['text'];

            if (str_contains($text, (string) $examYear)
                && str_contains($text, $examType)) {
                return $option;
            }
        }

        return null;
    }

    /**
     * Create an HTTP client configured for JAMB requests.
     *
     * @param  CookieJar  $jar  Per-request isolated cookie jar.
     */
    private function http(CookieJar $jar)
    {
        $connectTimeout = (float) config('services.jamb.connect_timeout', 5);
        $requestTimeout = (float) config('services.jamb.request_timeout', 30);

        return Http::withHeaders([
            'User-Agent'      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) '
                . 'AppleWebKit/537.36 (KHTML, like Gecko) '
                . 'Chrome/120.0.0.0 Safari/537.36',
            'Accept'          => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language' => 'en-US,en;q=0.5',
        ])
            ->timeout($requestTimeout)
            ->connectTimeout($connectTimeout)
            ->withOptions(['cookies' => $jar]);
    }

    /**
     * Load HTML into a DOMDocument, suppressing warnings from malformed HTML.
     */
    private function loadDom(string $html): DOMDocument
    {
        $previous = libxml_use_internal_errors(true);

        $doc = new DOMDocument();
        $doc->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return $doc;
    }
}