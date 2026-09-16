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
    | ACLi uses OmniRoute as the AI gateway. OmniRoute handles provider
    | routing and model selection via model=auto.
    |
    */

    'gateway' => [
        'base_url' => env('ACLI_AI_BASE_URL', 'http://127.0.0.1:20128/v1'),
        'api_key' => env('ACLI_AI_API_KEY'),
        'model' => env('ACLI_AI_MODEL', 'auto'),
        'timeout' => (int) env('ACLI_REQUEST_TIMEOUT', 120),
        'connect_timeout' => (int) env('ACLI_CONNECT_TIMEOUT', 10),
        'max_retries' => (int) env('ACLI_MAX_RETRIES', 2),
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Provider (legacy - kept for backward compatibility)
    |--------------------------------------------------------------------------
    */

    'default_provider' => env('ACLI_PROVIDER', 'omniroute'),

    /*
    |--------------------------------------------------------------------------
    | Default Model (legacy - kept for backward compatibility)
    |--------------------------------------------------------------------------
    */

    'default_model' => env('ACLI_MODEL', 'auto'),

    /*
    |--------------------------------------------------------------------------
    | Model Routing (legacy - kept for backward compatibility)
    |--------------------------------------------------------------------------
    */

    'models' => [
        'primary' => env('ACLI_PRIMARY_MODEL', 'auto'),

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
        'auto' => 'omniroute',
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
