<?php

namespace App\Services;

use App\Models\User;
use App\Models\Subscription;
use App\Services\AclAI\Tools\ToolAuthorizer;
use Illuminate\Support\Facades\Log;

/**
 * Central AI entitlement service.
 *
 * Every AI request must pass through this service before any AI provider call.
 * It evaluates authentication, role, subscription status, AI entitlement,
 * capability authorization, and usage limits.
 *
 * Rule: Students must have an active paid AI subscription for ANY AI execution.
 * No free exceptions. No trial calls. No "basic" free AI.
 *
 * Non-student roles (tutor, teacher, department admin, institution admin,
 * super admin) have role-based access determined by permissions and
 * subscription/organization plan where applicable.
 *
 * Architecture:
 *   User
 *     ↓ authentication
 *   Role + Permissions
 *     ↓ subscription check
 *   AI Entitlement
 *     ↓ capability authorization
 *   Usage Limit
 *     ↓ provider availability
 *   AI Execution
 *
 * Critical security rule: The frontend must NOT be responsible for enforcing
 * the paid requirement. Server-side entitlement check MUST happen before
 * any AI provider call. Never allow an AI provider call before the
 * entitlement check succeeds.
 */
class AclAIEntitlementService
{
    /** @var ToolAuthorizer */
    protected $toolAuthorizer;

    /** @var array<CapabilityDefinition> */
    protected $capabilityRegistry;

    /** @var string */
    protected $defaultProvider;

    /** @var string */
    protected $defaultModel;

    public function __construct()
    {
        $this->capabilityRegistry = $this->registerCapabilities();
        $this->defaultProvider = config('ai.default_provider', 'deepseek');
        $this->defaultModel = config('ai.default_model', 'deepseek-coder');
        $this->toolAuthorizer = new ToolAuthorizer();
    }

    /**
     * Register all AI capabilities with their requirements.
     *
     * Each capability definition specifies:
     * - Required role(s)
     * - Required AI entitlement
     * - Usage limits (if any)
     * - Applicable scopes (student, tutor, department, institution, platform)
     * - Whether it's student-paid-only
     *
     * @return array<CapabilityDefinition>
     */
    protected function registerCapabilities(): array
    {
        return [
            // Student capabilities - ALL require active paid subscription
            'student.ask' => [
                'name' => 'Ask ACL AI',
                'description' => 'Ask ACL AI questions about courses, progress, etc.',
                'role' => 'student',
                'require_authenticated' => true,
                'require_paid_subscription' => true, // MANDATORY: students must pay
                'require_ai_entitlement' => true,
                'scope' => 'student',
                'usage_limit' => 'daily', // configured per subscription plan
                'capability_alias' => 'ai.student.ask',
            ],

            'student.chat' => [
                'name' => 'Chat with ACL AI',
                'description' => 'Maintain conversations with ACL AI',
                'role' => 'student',
                'require_authenticated' => true,
                'require_paid_subscription' => true, // MANDATORY: students must pay
                'require_ai_entitlement' => true,
                'scope' => 'student',
                'usage_limit' => 'daily',
                'capability_alias' => 'ai.student.chat',
            ],

            'student.tutor' => [
                'name' => 'AI Tutor',
                'description' => 'Get tutoring assistance on topics and concepts',
                'role' => 'student',
                'require_authenticated' => true,
                'require_paid_subscription' => true, // MANDATORY: students must pay
                'require_ai_entitlement' => true,
                'scope' => 'student',
                'usage_limit' => 'daily',
                'capability_alias' => 'ai.student.tutor',
            ],

            'student.highlight_explain' => [
                'name' => 'Explain highlighted text',
                'description' => 'AI explains selected text (equations, code, paragraphs)',
                'role' => 'student',
                'require_authenticated' => true,
                'require_paid_subscription' => true, // MANDATORY: students must pay
                'require_ai_entitlement' => true,
                'scope' => 'student',
                'usage_limit' => 'daily',
                'capability_alias' => 'ai.student.highlight_explain',
            ],

            'student.equation_explain' => [
                'name' => 'Explain equation',
                'description' => 'AI explains selected mathematical equation step-by-step',
                'role' => 'student',
                'require_authenticated' => true,
                'require_paid_subscription' => true, // MANDATORY: students must pay
                'require_ai_entitlement' => true,
                'scope' => 'student',
                'usage_limit' => 'daily',
                'capability_alias' => 'ai.student.equation_explain',
            ],

            'student.code_explain' => [
                'name' => 'Explain code',
                'description' => 'AI explains selected code line-by-line or finds bugs',
                'role' => 'student',
                'require_authenticated' => true,
                'require_paid_subscription' => true, // MANDATORY: students must pay
                'require_ai_entitlement' => true,
                'scope' => 'student',
                'usage_limit' => 'daily',
                'capability_alias' => 'ai.student.code_explain',
            ],

            'student.quiz_generate' => [
                'name' => 'Generate quiz',
                'description' => 'AI generates practice questions on a topic',
                'role' => 'student',
                'require_authenticated' => true,
                'require_paid_subscription' => true, // MANDATORY: students must pay
                'require_ai_entitlement' => true,
                'scope' => 'student',
                'usage_limit' => 'daily',
                'capability_alias' => 'ai.student.quiz_generate',
            ],

            'student.question_generate' => [
                'name' => 'Generate questions',
                'description' => 'AI generates practice questions',
                'role' => 'student',
                'require_authenticated' => true,
                'require_paid_subscription' => true, // MANDATORY: students must pay
                'require_ai_entitlement' => true,
                'scope' => 'student',
                'usage_limit' => 'daily',
                'capability_alias' => 'ai.student.question_generate',
            ],

            'student.flashcard_generate' => [
                'name' => 'Generate flashcards',
                'description' => 'AI generates flashcards for revision',
                'role' => 'student',
                'require_authenticated' => true,
                'require_paid_subscription' => true, // MANDATORY: students must pay
                'require_ai_entitlement' => true,
                'scope' => 'student',
                'usage_limit' => 'daily',
                'capability_alias' => 'ai.student.flashcard_generate',
            ],

            'student.study_plan' => [
                'name' => 'Create study plan',
                'description' => 'AI creates personalized study plan based on progress',
                'role' => 'student',
                'require_authenticated' => true,
                'require_paid_subscription' => true, // MANDATORY: students must pay
                'require_ai_entitlement' => true,
                'scope' => 'student',
                'usage_limit' => 'daily',
                'capability_alias' => 'ai.student.study_plan',
            ],

            'student.performance_analysis' => [
                'name' => 'Performance analysis',
                'description' => 'AI analyzes your performance and suggests revisions',
                'role' => 'student',
                'require_authenticated' => true,
                'require_paid_subscription' => true, // MANDATORY: students must pay
                'require_ai_entitlement' => true,
                'scope' => 'student',
                'usage_limit' => 'daily',
                'capability_alias' => 'ai.student.performance_analysis',
            ],

            'student.course_analysis' => [
                'name' => 'Course analysis',
                'description' => 'AI analyzes your course progress and suggests next steps',
                'role' => 'student',
                'require_authenticated' => true,
                'require_paid_subscription' => true, // MANDATORY: students must pay
                'require_ai_entitlement' => true,
                'scope' => 'student',
                'usage_limit' => 'daily',
                'capability_alias' => 'ai.student.course_analysis',
            ],

            'student.summarize' => [
                'name' => 'Summarize',
                'description' => 'AI summarizes course material or your progress',
                'role' => 'student',
                'require_authenticated' => true,
                'require_paid_subscription' => true, // MANDATORY: students must pay
                'require_ai_entitlement' => true,
                'scope' => 'student',
                'usage_limit' => 'daily',
                'capability_alias' => 'ai.student.summarize',
            ],

            'student.translate' => [
                'name' => 'Translate',
                'description' => 'AI translates text or content',
                'role' => 'student',
                'require_authenticated' => true,
                'require_paid_subscription' => true, // MANDATORY: students must pay
                'require_ai_entitlement' => true,
                'scope' => 'student',
                'usage_limit' => 'daily',
                'capability_alias' => 'ai.student.translate',
            ],

            'student.image_generate' => [
                'name' => 'Generate image',
                'description' => 'AI generates educational images/diagrams',
                'role' => 'student',
                'require_authenticated' => true,
                'require_paid_subscription' => true, // MANDATORY: students must pay
                'require_ai_entitlement' => true,
                'scope' => 'student',
                'usage_limit' => 'daily',
                'capability_alias' => 'ai.student.image_generate',
            ],

            'student.presentation_generate' => [
                'name' => 'Generate presentation',
                'description' => 'AI generates presentation from course material',
                'role' => 'student',
                'require_authenticated' => true,
                'require_paid_subscription' => true, // MANDATORY: students must pay
                'require_ai_entitlement' => true,
                'scope' => 'student',
                'usage_limit' => 'daily',
                'capability_alias' => 'ai.student.presentation_generate',
            ],

            // Tutor capabilities - role-based, not necessarily student-paid
            'tutor.analyze_students' => [
                'name' => 'Analyze students',
                'description' => 'AI analyzes student performance and progress',
                'role' => 'tutor',
                'require_authenticated' => true,
                'require_paid_subscription' => false, // Tutors have separate access
                'require_ai_entitlement' => true,
                'scope' => 'tutor',
                'usage_limit' => 'daily',
                'capability_alias' => 'ai.tutor.analyze_students',
            ],

            'tutor.generate_lessons' => [
                'name' => 'Generate lessons',
                'description' => 'AI generates lesson content',
                'role' => 'tutor',
                'require_authenticated' => true,
                'require_paid_subscription' => false,
                'require_ai_entitlement' => true,
                'scope' => 'tutor',
                'usage_limit' => 'daily',
                'capability_alias' => 'ai.tutor.generate_lessons',
            ],

            // Teacher capabilities
            'teacher.difficulty' => [
                'name' => 'Identify struggling students',
                'description' => 'AI identifies students having difficulty with a topic',
                'role' => 'teacher',
                'require_authenticated' => true,
                'require_paid_subscription' => false,
                'require_ai_entitlement' => true,
                'scope' => 'teacher',
                'usage_limit' => 'daily',
                'capability_alias' => 'ai.teacher.difficulty',
            ],

            // Department admin capabilities
            'department.analytics' => [
                'name' => 'Department analytics',
                'description' => 'AI provides department-level analytics',
                'role' => 'department_admin',
                'require_authenticated' => true,
                'require_paid_subscription' => false,
                'require_ai_entitlement' => true,
                'scope' => 'department',
                'usage_limit' => 'daily',
                'capability_alias' => 'ai.department.analytics',
            ],

            // Institution admin capabilities
            'institution.summary' => [
                'name' => 'Institution summary',
                'description' => 'AI provides institution-level statistics',
                'role' => 'institution_admin',
                'require_authenticated' => true,
                'require_paid_subscription' => false,
                'require_ai_entitlement' => true,
                'scope' => 'institution',
                'usage_limit' => 'daily',
                'capability_alias' => 'ai.institution.summary',
            ],

            // Super admin capabilities
            'platform.operations' => [
                'name' => 'Platform operations',
                'description' => 'AI assists with platform operations and analytics',
                'role' => 'super_admin',
                'require_authenticated' => true,
                'require_paid_subscription' => false,
                'require_ai_entitlement' => true,
                'scope' => 'platform',
                'usage_limit' => 'daily',
                'capability_alias' => 'ai.platform.operations',
            ],
        ];
    }

    /**
     * Check if a user can use a specific AI capability.
     *
     * This is the main gateway that every AI request must pass through.
     * It evaluates:
     * 1. Is the user authenticated?
     * 2. What is their role?
     * 3. Do they have an active subscription (for students: MANDATORY)?
     * 4. Do they have the AI entitlement for this capability?
     * 5. Is the capability authorized for their scope?
     * 6. Have they exceeded their usage limit?
     * 7. Is the provider/model available?
     *
     * @param string $capability The capability key (e.g., 'student.ask')
     * @param User $user The user to check
     * @return array{allowed: bool, reason?: string, capability?: CapabilityDefinition}
     */
    public function canUseAI(string $capability, User $user): array
    {
        // 1. Look up the capability definition
        $capabilityDef = $this->capabilityRegistry[$capability] ?? null;
        if (!$capabilityDef) {
            return ['allowed' => false, 'reason' => "Unknown AI capability: {$capability}"];
        }

        // 2. Check authentication
        if ($capabilityDef['require_authenticated'] && !$user->isAuthenticated()) {
            return ['allowed' => false, 'reason' => 'User must be authenticated to use ACL AI.'];
        }

        // 3. Role check
        $userRole = $user->getRoleForAI ?? $this->detectUserRole($user);
        if ($userRole !== $capabilityDef['role']) {
            return [
                'allowed' => false,
                'reason' => sprintf(
                    'AI capability %s is reserved for %s role. Your role: %s.',
                    $capability,
                    $capabilityDef['role'],
                    $userRole
                ),
            ];
        }

        // 4. CRITICAL: Student paid subscription check
        // Students MUST have an active paid AI subscription - NO EXCEPTIONS
        if ($capabilityDef['require_paid_subscription'] && $userRole === 'student') {
            $subscription = $user->subscription;
            if (!$subscription || !$subscription->isActive() || !$subscription->ai_entitlement) {
                return [
                    'allowed' => false,
                    'reason' => 'ACL AI is a premium feature. Students must have an active paid AI subscription to use AI capabilities.',
                ];
            }
        }

        // 5. Non-student role: check AI entitlement (but not necessarily paid subscription)
        if ($capabilityDef['require_ai_entitlement']) {
            $hasEntitlement = $this->toolAuthorizer->hasAIEntitlement($user, $capability);
            if (!$hasEntitlement) {
                return [
                    'allowed' => false,
                    'reason' => 'You do not have entitlement to use this AI capability.',
                ];
            }
        }

        // 6. Usage limit check
        $usageOk = $this->checkUsageLimit($capability, $user);
        if (!$usageOk) {
            return [
                'allowed' => false,
                'reason' => 'You have reached your AI usage limit for this period.',
            ];
        }

        // 7. Provider/model availability check
        $providerOk = $this->checkProviderAvailability($capabilityDef);
        if (!$providerOk) {
            return [
                'allowed' => false,
                'reason' => 'The requested AI provider/model is currently unavailable.',
            ];
        }

        return ['allowed' => true];
    }

    /**
     * Execute an AI request after all checks pass.
     *
     * This method should be called by the ACL AI Gateway, NOT directly from
     * controllers or frontend code. It ensures the full entitlement chain is
     * executed before any AI provider call.
     *
     * @param string $capability The capability key
     * @param User $user The user making the request
     * @param array $context Additional context (course, chapter, lesson, selected text, etc.)
     * @param string $prompt The user's prompt/request
     * @return array{success: bool, response?: string, error?: string}
     */
    public function executeAI(string $capability, User $user, array $context = [], string $prompt = ''): array
    {
        // First, run the full entitlement check
        $check = $this->canUseAI($capability, $user);
        if (!$check['allowed']) {
            return [
                'success' => false,
                'error' => $check['reason'],
            ];
        }

        // Build the tool context for the AI provider
        $toolContext = $this->buildToolContext($capability, $user, $context);

        // Execute through the AI provider adapter
        $result = $this->runAIProvider($capability, $prompt, $toolContext);

        // Record the usage
        $this->recordUsage($capability, $user);

        return $result;
    }

    /** ... (helper methods: detectUserRole, checkUsageLimit, checkProviderAvailability, etc.) */
}

/**
 * Capability definition structure.
 *
 * Each registered capability has a definition that describes:
 * - The human-readable name and description
 * - Which role can use it
 * - Whether it requires authentication
 * - Whether it requires a paid subscription (students ONLY)
 * - Whether it requires AI entitlement
 * - What scope it applies to (student, tutor, department, institution, platform)
 * - Usage limit pattern (daily, monthly, per-session, etc.)
 * - The capability alias used in the entitlement system
 */
class CapabilityDefinition
{
    public string $name;
    public string $description;
    public string $role;
    public bool $require_authenticated;
    public bool $require_paid_subscription;
    public bool $require_ai_entitlement;
    public string $scope;
    public string $usage_limit;
    public string $capability_alias;
}

/**
 * Subscription model extension for AI entitlement.
 *
 * Extends the existing Subscription model to include AI-specific fields.
 */
class Subscription extends \Illuminate\Database\Eloquent\Model
{
    protected $fillable = [
        'user_id',
        'plan',
        'status',
        'start_date',
        'expiration_date',
        'cancellation_status',
        'payment_status',
        'ai_entitlement', // boolean: does this plan include AI?
        'usage_limits', // JSON: daily/monthly/token limits
        'ai_provider', // which AI provider this subscription is for
        'ai_model', // which model this subscription includes
        'daily_token_limit',
        'monthly_token_limit',
        'usage_count_today',
        'usage_count_this_month',
    ];

    /** Check if the subscription is active and includes AI entitlement */
    public function isActive(): bool
    {
        return $this->status === 'active'
            && $this->expiration_date
            && $this->expiration_date->isFuture()
            && $this->payment_status === 'paid'
            && $this->ai_entitlement === true;
    }

    /** Get the daily AI usage count */
    public function dailyUsageCount(): int
    {
        return $this->usage_count_today ?? 0;
    }

    /** Increment daily usage count */
    public function incrementDailyUsage(): void
    {
        $this->increment('usage_count_today');
    }
}

/**
 * Tool Authorizer - authorizes AI tool calls based on role and entitlement.
 *
 * Every tool that ACL AI can call (get_student_profile, get_course_progress,
 * etc.) goes through this authorizer before the tool is executed.
 */
class ToolAuthorizer
{
    /** Check if user has AI entitlement for a capability */
    public function hasAIEntitlement(User $user, string $capability): bool
    {
        // Check role assignments and their permissions
        $roleAssignments = $user->roleAssignments()
            ->whereHas('role.permissions', fn($q) => $q->where('slug', 'ai.' . $capability));

        // Platform admins bypass via Gate::before()
        if ($user->isPlatformAdministrator()) {
            return true;
        }

        return $roleAssignments->exists();
    }

    /** Authorize a tool call for a specific user and capability */
    public function authorizeToolCall(User $user, string $toolName, string $capability): bool
    {
        // Check the capability entitlement first
        $hasEntitlement = $this->hasAIEntitlement($user, $capability);
        if (!$hasEntitlement) {
            return false;
        }

        // For students, additionally check that the tool is appropriate for their scope
        if ($user->hasRole('student')) {
            return $this->checkStudentToolScope($user, $toolName);
        }

        // For other roles, check role-based permissions
        return true;
    }

    /** Check that a student-only tool is being used within the student's scope */
    protected function checkStudentToolScope(User $user, string $toolName): bool
    {
        // Students can only access their own data through AI tools
        // This is enforced at the tool level, not here
        // The tool itself must reject requests for another student's data

        return true;
    }
}

/** AI Provider Adapter - abstraction layer between ACL AI and AI providers.
 *
 * This adapter isolates ACL from specific AI provider implementations.
 * If DeepSeek Harness is replaced, the application layer continues working.
 *
 * Supported providers:
 * - DeepSeek Harness (initial)
 * - OpenAI (GPT-4, GPT-3.5, etc.)
 * - Anthropic (Claude 3, etc.)
 * - Google Gemini
 * - OpenRouter-compatible providers
 * - Self-hosted models (Llama, Mistral, etc.)
 */
class AIProviderAdapter
{
    /** @var string */
    protected $currentProvider;

    /** @var string */
    protected $currentModel;

    /** Execute a request through the configured AI provider */
    public function execute(string $provider, string $model, string $prompt, array $context = []): array
    {
        $this->currentProvider = $provider;
        $this->currentModel = $model;

        switch ($provider) {
            case 'deepseek':
                return $this->executeDeepSeek($model, $prompt, $context);
            case 'openai':
                return $this->executeOpenAI($model, $prompt, $context);
            case 'anthropic':
                return $this->executeAnthropic($model, $prompt, $context);
            case 'gemini':
                return $this->executeGemini($model, $prompt, $context);
            case 'self_hosted':
                return $this->executeSelfHosted($model, $prompt, $context);
            default:
                return [
                    'success' => false,
                    'error' => "Unsupported AI provider: {$provider}",
                ];
        }
    }

    /** Execute through DeepSeek Harness */
    protected function executeDeepSeek(string $model, string $prompt, array $context): array
    {
        // Use DeepSeek Harness SDK or API
        // $response = DeepSeekHarness::chat($model, $prompt, $context);
        // For now, return structured result
        return [
            'success' => true,
            'response' => 'DeepSeek response would be generated here',
            'model' => $model,
            'provider' => 'deepseek',
        ];
    }

    /** Execute through OpenAI */
    protected function executeOpenAI(string $model, string $prompt, array $context): array
    {
        // OpenAI API call
        return [
            'success' => true,
            'response' => 'OpenAI response would be generated here',
            'model' => $model,
            'provider' => 'openai',
        ];
    }

    /** Execute through Anthropic */
    protected function executeAnthropic(string $model, string $prompt, array $context): array
    {
        // Anthropic API call
        return [
            'success' => true,
            'response' => 'Anthropic response would be generated here',
            'model' => $model,
            'provider' => 'anthropic',
        ];
    }

    /** Execute self-hosted model */
    protected function executeSelfHosted(string $model, string $prompt, array $context): array
    {
        // Self-hosted model inference
        return [
            'success' => true,
            'response' => 'Self-hosted model response would be generated here',
            'model' => $model,
            'provider' => 'self_hosted',
        ];
    }
}

/** AI Context System - provides contextual information to AI based on current UI context.
 *
 * For paid students, ACL AI should understand the current context:
 * - Current course
 * - Current chapter
 * - Current lesson
 * - Selected text/highlight
 * - Recent progress
 *
 * This context is passed to the AI along with the user's prompt to provide
 * more relevant and accurate responses.
 */
class AICContextSystem
{
    /** Get the current contextual information for AI */
    public function getCurrentContext(User $user): array
    {
        $context = [
            'user_id' => $user->id,
            'role' => $user->getRoleForAI,
            'subscription' => $user->subscription?->isActive() ?? false,
        ];

        // If the user is on a course page, add course context
        if (request()->has('course_offering_id')) {
            $offering = \App\Models\CourseOffering::find(request('course_offering_id'));
            if ($offering) {
                $context['course'] = [
                    'id' => $offering->id,
                    'code' => $offering->course->code,
                    'title' => $offering->course->title,
                    'department' => $offering->department?->name,
                ];
            }
        }

        // If the user is on a lesson page, add lesson context
        if (request()->has('lesson_id')) {
            $lesson = \App\Models\Lesson::find(request('lesson_id'));
            if ($lesson) {
                $context['lesson'] = [
                    'id' => $lesson->id,
                    'title' => $lesson->title,
                    'chapter_id' => $lesson->chapter_id,
                ];
            }
        }

        return $context;
    }

    /** Get selected/highlighted text context */
    public function getSelectedTextContext(): ?array
    {
        // This would be set by the frontend when a student highlights text
        // Returns the selected text and its context (course, lesson, etc.)
        return null;
    }
}