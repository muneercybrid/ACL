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
    | ACLi Entitlement Gate
    |--------------------------------------------------------------------------
    |
    | ACLi is currently free for all students. Set ACLI_REQUIRE_ENTITLEMENT
    | to true when paid student subscriptions ship to re-enable the paid
    | gate (see AcliEntitlementService).
    |
    */

    'require_entitlement' => (bool) env('ACLI_REQUIRE_ENTITLEMENT', false),

    /*
    |--------------------------------------------------------------------------
    | AI Gateway
    |--------------------------------------------------------------------------
    |
    | The gateway is OpenRouter (see ACLI_AI_BASE_URL). The model must be a
    | real OpenRouter model slug: names like 'auto' are OmniRoute route names
    | and do not exist here, so they fail on the primary and only appear to
    | work because the fallback answers.
    |
    */

    'gateway' => [
        'base_url' => env('ACLI_AI_BASE_URL', 'http://127.0.0.1:20128/v1'),
        'api_key' => env('ACLI_AI_API_KEY'),
        'model' => env('ACLI_AI_MODEL', 'deepseek/deepseek-v4-flash-0731'),
        'timeout' => (int) env('ACLI_REQUEST_TIMEOUT', 120),
        'connect_timeout' => (int) env('ACLI_CONNECT_TIMEOUT', 10),
        'max_retries' => (int) env('ACLI_MAX_RETRIES', 2),
    ],

    /*
    |--------------------------------------------------------------------------
    | Unified Failover Chain
    |--------------------------------------------------------------------------
    |
    | ACLi talks to a chain of backing AI providers in priority order.
    | The first backend that returns a usable answer serves the request;
    | a backend that is unconfigured is skipped silently, so the same
    | code runs in environments where only some backends exist.
    |
    | Order matters. Token Harbor leads because its ":free" models cost
    | nothing and showed no rate limit under load; Cloudflare is the
    | second free tier; the local OmniRoute proxy is last because it
    | shares credentials with background chapter generation and can
    | return 429s. Each backend has its own model slug below.
    |
    */

    'unified' => [
        'chain' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('ACLI_UNIFIED_CHAIN', 'tokenharbor,cloudflare,omniroute'))
        ))),

        'models' => [
            'tokenharbor' => env('ACLI_TOKENHARBOR_MODEL', 'mimo-v2.6-flash:free'),
            'cloudflare' => env('ACLI_CLOUDFLARE_MODEL', '@cf/meta/llama-3.2-1b-instruct'),
            'omniroute' => env('ACLI_OMNIROUTE_MODEL', env('ACLI_AI_MODEL', 'deepseek/deepseek-v4-flash-0731')),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Token Harbor (OpenAI-compatible gateway)
    |--------------------------------------------------------------------------
    |
    | Multi-vendor gateway: Claude, GPT, Gemini, Grok, Kimi, DeepSeek,
    | Qwen, GLM, MiMo. Models suffixed ":free" cost nothing. Requires
    | the account's email to be verified or every call returns 403
    | email_verification_required.
    |
    */

    'tokenharbor' => [
        'base_url' => env('ACLI_TOKENHARBOR_BASE_URL', 'https://tokenharbor.ai'),
        'api_key' => env('ACLI_TOKENHARBOR_API_KEY'),
        'model' => env('ACLI_TOKENHARBOR_MODEL', 'mimo-v2.6-flash:free'),
        'timeout' => (int) env('ACLI_TOKENHARBOR_TIMEOUT', 120),
        'connect_timeout' => (int) env('ACLI_TOKENHARBOR_CONNECT_TIMEOUT', 10),
    ],

    /*
    |--------------------------------------------------------------------------
    | Cloudflare Workers AI
    |--------------------------------------------------------------------------
    |
    | Uses the /client/v4/accounts/{account}/ai/run/{model} endpoint,
    | which is not OpenAI-shaped — the provider adapter translates it.
    | Free plan covers the "@cf/meta/llama-3.2-1b-instruct" class of
    | models; image models (Stable Diffusion) return "No route for that
    | URI" and need a Workers Paid plan.
    |
    */

    'cloudflare' => [
        'api_token' => env('ACLI_CLOUDFLARE_API_TOKEN'),
        'account_id' => env('ACLI_CLOUDFLARE_ACCOUNT_ID'),
        'model' => env('ACLI_CLOUDFLARE_MODEL', '@cf/meta/llama-3.2-1b-instruct'),
        'timeout' => (int) env('ACLI_CLOUDFLARE_TIMEOUT', 120),
        'connect_timeout' => (int) env('ACLI_CLOUDFLARE_CONNECT_TIMEOUT', 10),
    ],

    /*
    |--------------------------------------------------------------------------
    | Image Generation
    |--------------------------------------------------------------------------
    |
    | Text and image generation are separate problems here. None of the
    | configured text backends can produce images on a free plan:
    | Cloudflare's Stable Diffusion routes return "No route for that URI",
    | Gemini's image models return 429 until billing is enabled, and Token
    | Harbor's /v1/images/generations returns 402 until topped up.
    |
    | Pollinations needs no credentials at all, so it is the image backend
    | that actually works without payment. Its anonymous tier is throttled
    | to roughly one image per 20-30 seconds, which suits on-demand
    | illustration but not bulk rendering. Swap in a funded provider later
    | by pointing ACLI_IMAGE_MODEL at it.
    |
    */

    'images' => [
        'enabled' => (bool) env('ACLI_IMAGES_ENABLED', true),
        'base_url' => env('ACLI_IMAGE_BASE_URL', 'https://image.pollinations.ai'),
        'model' => env('ACLI_IMAGE_MODEL', 'sana'),
        'timeout' => (int) env('ACLI_IMAGE_TIMEOUT', 90),
        'max_width' => (int) env('ACLI_IMAGE_MAX_WIDTH', 1024),
        'max_height' => (int) env('ACLI_IMAGE_MAX_HEIGHT', 1024),
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Provider (legacy - kept for backward compatibility)
    |--------------------------------------------------------------------------
    */

    'default_provider' => env('ACLI_PROVIDER', 'openrouter'),

    /*
    |--------------------------------------------------------------------------
    | Default Model (legacy - kept for backward compatibility)
    |--------------------------------------------------------------------------
    */

    'default_model' => env('ACLI_MODEL', env('ACLI_AI_MODEL', 'deepseek/deepseek-v4-flash-0731')),

    /*
    |--------------------------------------------------------------------------
    | Model Routing (legacy - kept for backward compatibility)
    |--------------------------------------------------------------------------
    */

    'models' => [
        // Chains to the gateway model rather than naming a legacy route, so
        // an unset ACLI_PRIMARY_MODEL cannot reintroduce a dead model name.
        'primary' => env('ACLI_PRIMARY_MODEL', env('ACLI_AI_MODEL', 'deepseek/deepseek-v4-flash-0731')),

        'fallbacks' => array_values(array_filter(
            array_map(
                'trim',
                explode(',', env(
                    'ACLI_FALLBACK_MODELS',
                    ''
                ))
            )
        )),
    ],

    /*
    |--------------------------------------------------------------------------
    | Model Providers (legacy - kept for backward compatibility)
    |--------------------------------------------------------------------------
    */

    'model_providers' => [
        // Left in place so an old omniroute route name still resolves to a
        // provider, but these must not be sent to the gateway as a model.
        'auto' => 'omniroute',
        'omniroute/auto' => 'omniroute',
        'omniroute/fast' => 'omniroute',
    ],

    /*
    |--------------------------------------------------------------------------
    | Request Configuration
    |--------------------------------------------------------------------------
    */

    'request' => [
        'timeout' => (int) env('ACLI_REQUEST_TIMEOUT', 120),
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

    /*
    |--------------------------------------------------------------------------
    | Capability Configuration
    |--------------------------------------------------------------------------
    */

    'capabilities' => [
        'student_chat' => [
            'name' => 'Student Chat',
            'slug' => 'student.chat',
            'description' => 'General AI chat for students',
            'permission' => 'acli.student.chat',
        ],
        'student_tutor' => [
            'name' => 'Student Tutor',
            'slug' => 'student.tutor',
            'description' => 'Course-aware tutoring for students',
            'permission' => 'acli.student.tutor',
        ],
        'student_quiz' => [
            'name' => 'Student Quiz Generation',
            'slug' => 'student.quiz',
            'description' => 'Generate practice quizzes from course content',
            'permission' => 'acli.student.quiz',
        ],
        'student_flashcard' => [
            'name' => 'Student Flashcard Generation',
            'slug' => 'student.flashcard',
            'description' => 'Generate flashcards from course content',
            'permission' => 'acli.student.flashcard',
        ],
        'student_study_plan' => [
            'name' => 'Student Study Plan',
            'slug' => 'student.study_plan',
            'description' => 'Generate personalized study plans',
            'permission' => 'acli.student.study_plan',
        ],
        'academic_content_generate' => [
            'name' => 'Academic Content Generation',
            'slug' => 'academic.content_generate',
            'description' => 'Generate academic content drafts (chapters, lessons, etc.)',
            'permission' => 'acli.academic.content_generate',
        ],
        'academic_quiz_generate' => [
            'name' => 'Academic Quiz Generation',
            'slug' => 'academic.quiz_generate',
            'description' => 'Generate quiz questions for question banks',
            'permission' => 'acli.academic.quiz_generate',
        ],
        'academic_assessment_generate' => [
            'name' => 'Academic Assessment Generation',
            'slug' => 'academic.assessment_generate',
            'description' => 'Generate assessment drafts',
            'permission' => 'acli.academic.assessment_generate',
        ],
        'admin_analytics' => [
            'name' => 'Admin Analytics',
            'slug' => 'admin.analytics',
            'description' => 'Institution-level analytics queries',
            'permission' => 'acli.admin.analytics',
        ],
        'superadmin_platform' => [
            'name' => 'Super Admin Platform Statistics',
            'slug' => 'superadmin.platform',
            'description' => 'Platform-wide operational queries',
            'permission' => 'acli.superadmin.platform',
        ],
    ],

];
