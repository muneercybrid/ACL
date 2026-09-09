<?php

return [

    /*
    |--------------------------------------------------------------------------
    | ACLi Enabled
    |--------------------------------------------------------------------------
    |
    | Allows ACLi to be disabled globally without removing the subsystem.
    |
    */

    'enabled' => (bool) env('ACLI_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Default Provider
    |--------------------------------------------------------------------------
    */

    'default_provider' => env('ACLI_PROVIDER', 'nvidia'),

    /*
    |--------------------------------------------------------------------------
    | Default Model
    |--------------------------------------------------------------------------
    */

    'default_model' => env('ACLI_MODEL', 'nvidia/nemotron-3-super-120b-a12b'),

    /*
    |--------------------------------------------------------------------------
    | Model Routing
    |--------------------------------------------------------------------------
    |
    | Models are ordered by preference. The first model is the primary
    | model and subsequent models may be used as fallbacks when routing
    | permits it.
    |
    */

    'models' => [
        'primary' => env('ACLI_PRIMARY_MODEL', 'nvidia/nemotron-3-super-120b-a12b'),

        'fallbacks' => array_values(array_filter(
            array_map(
                'trim',
                explode(',', env(
                    'ACLI_FALLBACK_MODELS',
                    'deepseek-ai/deepseek-v4-pro-0813'
                ))
            )
        )),
    ],

    /*
    |--------------------------------------------------------------------------
    | Model Providers
    |--------------------------------------------------------------------------
    |
    | Explicitly maps each ACLi model to the provider responsible for it.
    |
    */

    'model_providers' => [
        'nvidia/nemotron-3-super-120b-a12b' => 'nvidia',
        'deepseek-ai/deepseek-v4-pro-0813' => 'nvidia',
    ],

    /*
    |--------------------------------------------------------------------------
    | Request Configuration
    |--------------------------------------------------------------------------
    */

    'request' => [
        'timeout' => (int) env('ACLI_REQUEST_TIMEOUT', 60),
        'connect_timeout' => (int) env('ACLI_CONNECT_TIMEOUT', 10),
        'max_retries' => (int) env('ACLI_MAX_RETRIES', 2),
    ],

    /*
    |--------------------------------------------------------------------------
    | Context Configuration
    |--------------------------------------------------------------------------
    |
    | These limits protect the provider request from unbounded ACL content.
    |
    */

    'context' => [
        'max_lessons' => (int) env('ACLI_MAX_CONTEXT_LESSONS', 1),
        'max_blocks' => (int) env('ACLI_MAX_CONTEXT_BLOCKS', 50),
        'max_content_chars' => (int) env('ACLI_MAX_CONTEXT_CHARS', 50000),
    ],

    /*
    |--------------------------------------------------------------------------
    | Conversation Configuration
    |--------------------------------------------------------------------------
    */

    'conversation' => [
        'max_messages' => (int) env('ACLI_MAX_CONVERSATION_MESSAGES', 30),
    ],

];
