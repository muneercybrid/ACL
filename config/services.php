<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'jamb' => [
        'matriculation_url' => env(
            'JAMB_MATRICULATION_URL',
            'https://efacility2.jamb.gov.ng/CheckMatriculationList'
        ),
        'minimum_year' => (int) env('JAMB_MINIMUM_EXAM_YEAR', 1995),
        'exam_type' => env('JAMB_EXAM_TYPE', 'UTME'),

        // Direct HTTP connection (no browser automation).
        'connect_timeout' => (float) env('JAMB_CONNECT_TIMEOUT', 5),
        'request_timeout' => (float) env('JAMB_REQUEST_TIMEOUT', 30),
    ],

    'nvidia' => [
        'api_key' => env('NVIDIA_API_KEY'),
        'base_url' => env('NVIDIA_BASE_URL', 'https://integrate.api.nvidia.com/v1'),
    ],

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI', 'https://app.aclacademy.me/auth/google/callback'),
    ],

];
