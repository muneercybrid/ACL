<?php

namespace App\Services\Jamb;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class JambMatriculationVerificationService
{
    public function verify(
        int $examYear,
        string $registrationNumber,
        string $examType = 'UTME'
    ): array {
        $registrationNumber = strtoupper(trim($registrationNumber));

        $options = $this->getExaminationOptions();

        $examOption = $this->findExaminationOption(
            $options,
            $examYear,
            $examType
        );

        if ($examOption === null) {
            throw new RuntimeException(
                "JAMB does not currently expose {$examYear} {$examType} on its matriculation portal."
            );
        }

        $response = $this->zyte()->post('/extract', [
            'url' => config('services.jamb.matriculation_url'),
            'browserHtml' => true,
            'actions' => [
                [
                    'action' => 'waitForSelector',
                    'selector' => [
                        'type' => 'css',
                        'value' => '#ddlExamination',
                    ],
                ],
                [
                    'action' => 'select',
                    'selector' => [
                        'type' => 'css',
                        'value' => '#ddlExamination',
                    ],
                    'values' => [$examOption['value']],
                ],
                [
                    'action' => 'type',
                    'selector' => [
                        'type' => 'css',
                        'value' => '#txtRegNumber',
                    ],
                    'text' => $registrationNumber,
                ],
                [
                    'action' => 'click',
                    'selector' => [
                        'type' => 'css',
                        'value' => '#lnkSearch',
                    ],
                ],
                [
                    'action' => 'waitForSelector',
                    'selector' => [
                        'type' => 'css',
                        'value' => '#dvdisplay .profile-info h1',
                    ],
                    'timeout' => 15,
                ],
            ],
        ]);

        if (! $response->successful()) {
            throw new RuntimeException(
                'The JAMB verification provider could not be reached.'
            );
        }

        $data = $response->json();

        $html = data_get($data, 'browserHtml');

        if (! is_string($html) || $html === '') {
            throw new RuntimeException(
                'JAMB returned an empty verification page.'
            );
        }

        $result = $this->parseResult($html);

        $result['provider_status_code'] = data_get(
            $data,
            'statusCode'
        );

        $result['provider_url'] = data_get(
            $data,
            'url'
        );

        $result['actions'] = data_get(
            $data,
            'actions',
            []
        );

        $result['jamb_exam_value'] = $examOption['value'];
        $result['jamb_exam_text'] = $examOption['text'];

        return $result;
    }

    /**
     * Retrieve the examination options currently exposed by JAMB.
     */
    private function getExaminationOptions(): array
    {
        $response = $this->zyte()->post('/extract', [
            'url' => config('services.jamb.matriculation_url'),
            'browserHtml' => true,
            'actions' => [
                [
                    'action' => 'waitForSelector',
                    'selector' => [
                        'type' => 'css',
                        'value' => '#ddlExamination',
                    ],
                ],
            ],
        ]);

        if (! $response->successful()) {
            throw new RuntimeException(
                'Unable to retrieve JAMB examination options.'
            );
        }

        $html = data_get(
            $response->json(),
            'browserHtml'
        );

        if (! is_string($html) || $html === '') {
            throw new RuntimeException(
                'JAMB returned an empty examination page.'
            );
        }

        return $this->parseExaminationOptions($html);
    }

    private function parseExaminationOptions(string $html): array
    {
        libxml_use_internal_errors(true);

        $document = new \DOMDocument();

        if (! $document->loadHTML($html)) {
            libxml_clear_errors();

            throw new RuntimeException(
                'Unable to parse the JAMB examination page.'
            );
        }

        $xpath = new \DOMXPath($document);

        $options = [];

        foreach (
            $xpath->query(
                '//select[@id="ddlExamination"]/option'
            ) as $option
        ) {
            $value = trim(
                $option->getAttribute('value')
            );

            $text = trim(
                preg_replace(
                    '/\s+/',
                    ' ',
                    $option->textContent
                )
            );

            if ($value === '' || $text === '') {
                continue;
            }

            $options[] = [
                'value' => $value,
                'text' => $text,
            ];
        }

        libxml_clear_errors();

        return $options;
    }

    private function findExaminationOption(
        array $options,
        int $examYear,
        string $examType
    ): ?array {
        $year = (string) $examYear;
        $type = strtoupper(trim($examType));

        foreach ($options as $option) {
            $text = strtoupper($option['text']);

            if (
                str_contains($text, $year)
                && str_contains($text, $type)
            ) {
                return $option;
            }
        }

        return null;
    }

    private function parseResult(string $html): array
    {
        libxml_use_internal_errors(true);

        $document = new \DOMDocument();

        if (! $document->loadHTML($html)) {
            libxml_clear_errors();

            throw new RuntimeException(
                'Unable to parse the JAMB verification response.'
            );
        }

        $xpath = new \DOMXPath($document);

        $bodyText = trim(
            preg_replace(
                '/\s+/',
                ' ',
                $xpath->evaluate('string(//body)')
            )
        );

        /*
         * JAMB's actual successful result structure is:
         *
         * #dvdisplay
         *   ├── .profile-nav
         *   │     ├── li[1] → Institution
         *   │     └── li[2] → Programme
         *   └── .profile-info h1 → Candidate name
         *
         * Use the DOM structure instead of relying on labels
         * appearing in plain body text.
         */

        $name = trim(
            $xpath->evaluate(
                'string(//*[@id="dvdisplay"]//*[contains(@class,"profile-info")]//h1)'
            )
        );

        $institution = trim(
            $xpath->evaluate(
                'string(//*[@id="dvdisplay"]//*[contains(@class,"profile-nav")]//li[1]//span)'
            )
        );

        $programme = trim(
            $xpath->evaluate(
                'string(//*[@id="dvdisplay"]//*[contains(@class,"profile-nav")]//li[2]//span)'
            )
        );

        $status = trim(
            $xpath->evaluate(
                'string(//*[@id="dvdisplay"]//*[strong[contains(normalize-space(.),"Status:")]]//b)'
            )
        );

        /*
         * If JAMB returns a failure page, preserve the actual
         * response text for the caller and audit metadata.
         */
        $lower = strtolower($bodyText);

        $failureIndicators = [
            'not found',
            'no record',
            'invalid registration',
            'invalid reg',
            'not on the matriculation list',
            'record does not exist',
            'record not found',
            'unable to fetch',
            'does not exist',
        ];

        foreach ($failureIndicators as $indicator) {
            if (str_contains($lower, $indicator)) {
                libxml_clear_errors();

                return [
                    'verified' => false,
                    'name' => null,
                    'institution' => null,
                    'programme' => null,
                    'status' => $status !== ''
                        ? $status
                        : $bodyText,
                    'raw_text' => $bodyText,
                ];
            }
        }

        $verified = $name !== ''
            && $institution !== ''
            && $programme !== '';

        libxml_clear_errors();

        return [
            'verified' => $verified,
            'name' => $verified ? $name : null,
            'institution' => $verified ? $institution : null,
            'programme' => $verified ? $programme : null,
            'status' => $status !== ''
                ? $status
                : ($verified ? 'verified' : $bodyText),
            'raw_text' => $bodyText,
        ];
    }

    private function zyte(): PendingRequest
    {
        $apiKey = config('services.zyte.api_key');

        if (! filled($apiKey)) {
            throw new RuntimeException(
                'ZYTE_API_KEY is not configured.'
            );
        }

        return Http::baseUrl(
            rtrim(
                config(
                    'services.zyte.base_url',
                    'https://api.zyte.com/v1'
                ),
                '/'
            )
        )
            ->withBasicAuth($apiKey, '')
            ->acceptJson()
            ->timeout(60)
            ->connectTimeout(15);
    }
}
