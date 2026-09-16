<?php

namespace App\Services\ACLi;

use App\Models\ACLi\Capability;
use App\Models\ACLi\Conversation;
use App\Models\ACLi\Message;
use App\Models\ACLi\Request as AcliRequest;
use App\Models\User;
use App\Services\ACLi\DTO\AIRequest;
use App\Services\ACLi\Exceptions\ProviderException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * ACLi Orchestrator
 *
 * Central service for ACLi AI operations. Handles:
 * - Authentication and authorization
 * - Capability/entitlement checks
 * - Conversation management
 * - Provider requests via ProviderManager
 * - Request logging and token accounting
 */
class AcliOrchestrator
{
    public function __construct(
        protected ProviderManager $providerManager,
        protected AcliCapabilityService $capabilityService,
        protected AcliEntitlementService $entitlementService,
    ) {}

    /**
     * Process a student chat request.
     *
     * @param array $messages Array of message arrays with 'role' and 'content'
     * @param array $options Additional options (conversation_id, course_offering_id, etc.)
     * @return array Result with 'success', 'message', 'conversation_id', etc.
     */
    public function studentChat(array $messages, array $options = []): array
    {
        $user = Auth::user();

        if (! $user) {
            return ['success' => false, 'error' => 'Unauthenticated'];
        }

        // Check ACLi entitlement
        $entitlement = $this->entitlementService->check($user, 'student.chat');
        if (! $entitlement['allowed']) {
            return [
                'success' => false,
                'error' => $entitlement['reason'] ?? 'ACLi chat not available for your account.',
                'code' => 'ENTITLEMENT_DENIED',
            ];
        }

        // Get or create conversation
        $conversation = $this->getOrCreateConversation($user, $options);

        // Store user message
        $this->storeMessage($conversation, 'user', end($messages)['content'], [
            'capability' => 'student.chat',
        ]);

        // Prepare messages for provider (include conversation history)
        $providerMessages = $this->buildProviderMessages($conversation, $messages);

        // Execute provider request
        $result = $this->executeProviderRequest(
            $user,
            $conversation,
            'student.chat',
            $providerMessages,
        );

        if (! $result['success']) {
            return $result;
        }

        // Store assistant response
        $this->storeMessage($conversation, 'assistant', $result['content'], [
            'capability' => 'student.chat',
            'provider' => $result['provider'],
            'model' => $result['model'],
            'tokens' => $result['tokens'] ?? [],
        ]);

        return [
            'success' => true,
            'message' => $result['content'],
            'conversation_id' => $conversation->id,
            'provider' => $result['provider'],
            'model' => $result['model'],
        ];
    }

    /**
     * Execute a provider request with logging.
     */
    protected function executeProviderRequest(
        User $user,
        Conversation $conversation,
        string $capabilitySlug,
        array $messages,
        array $options = []
    ): array {
        $capability = Capability::where('slug', $capabilitySlug)->first();
        $startTime = microtime(true);

        try {
            $aiRequest = new AIRequest(
                model: config('acli.gateway.model', 'auto'),
                messages: $messages,
                temperature: $options['temperature'] ?? 0.7,
                maxTokens: $options['max_tokens'] ?? 2000,
            );

            $response = $this->providerManager->chat($aiRequest);

            $latencyMs = (int) ((microtime(true) - $startTime) * 1000);

            // Log the request
            AcliRequest::create([
                'user_id' => $user->id,
                'conversation_id' => $conversation->id,
                'capability_id' => $capability?->id,
                'provider' => $response->provider,
                'model' => $response->model,
                'status' => 'completed',
                'input_tokens' => $response->inputTokens,
                'output_tokens' => $response->outputTokens,
                'total_tokens' => $response->totalTokens,
                'latency_ms' => $latencyMs,
            ]);

            return [
                'success' => true,
                'content' => $response->content,
                'provider' => $response->provider,
                'model' => $response->model,
                'tokens' => [
                    'input' => $response->inputTokens,
                    'output' => $response->outputTokens,
                    'total' => $response->totalTokens,
                ],
            ];

        } catch (ProviderException $e) {
            $latencyMs = (int) ((microtime(true) - $startTime) * 1000);

            AcliRequest::create([
                'user_id' => $user->id,
                'conversation_id' => $conversation->id,
                'capability_id' => $capability?->id,
                'provider' => $e->getProvider(),
                'model' => config('acli.gateway.model', 'auto'),
                'status' => 'failed',
                'latency_ms' => $latencyMs,
                'error_message' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => 'AI service temporarily unavailable. Please try again.',
                'code' => 'PROVIDER_ERROR',
            ];
        }
    }

    /**
     * Get or create a conversation for the user.
     */
    protected function getOrCreateConversation(User $user, array $options = []): Conversation
    {
        if (! empty($options['conversation_id'])) {
            $conversation = Conversation::where('id', $options['conversation_id'])
                ->where('user_id', $user->id)
                ->first();

            if ($conversation) {
                return $conversation;
            }
        }

        return Conversation::create([
            'user_id' => $user->id,
            'course_offering_id' => $options['course_offering_id'] ?? null,
            'chapter_id' => $options['chapter_id'] ?? null,
            'lesson_id' => $options['lesson_id'] ?? null,
            'title' => $options['title'] ?? 'New conversation',
            'status' => 'active',
        ]);
    }

    /**
     * Build provider messages including conversation history.
     */
    protected function buildProviderMessages(Conversation $conversation, array $newMessages): array
    {
        $maxMessages = config('acli.conversation.max_messages', 30);

        // Get recent messages from conversation
        $history = $conversation->messages()
            ->orderBy('created_at', 'desc')
            ->limit($maxMessages - count($newMessages))
            ->get()
            ->reverse()
            ->map(fn (Message $m) => [
                'role' => $m->role,
                'content' => $m->content,
            ])
            ->toArray();

        // System message for ACLi
        $systemMessage = [
            'role' => 'system',
            'content' => 'You are ACLi, an educational AI assistant for ACL (Anyone Can Learn). ' .
                'You help students learn by explaining concepts, answering questions, and providing guidance. ' .
                'Be helpful, accurate, and concise. Always encourage learning and critical thinking.',
        ];

        // Combine: system + history + new messages
        return array_merge([$systemMessage], $history, $newMessages);
    }

    /**
     * Store a message in the conversation.
     */
    protected function storeMessage(Conversation $conversation, string $role, string $content, array $metadata = []): Message
    {
        return Message::create([
            'conversation_id' => $conversation->id,
            'role' => $role,
            'content' => $content,
            'metadata' => $metadata,
        ]);
    }

    /**
     * Get user's conversations.
     */
    public function getConversations(User $user, int $limit = 20): \Illuminate\Database\Eloquent\Collection
    {
        return Conversation::where('user_id', $user->id)
            ->where('status', 'active')
            ->withCount('messages')
            ->latest('updated_at')
            ->limit($limit)
            ->get();
    }

    /**
     * Get a specific conversation with messages.
     */
    public function getConversation(User $user, int $conversationId): ?Conversation
    {
        return Conversation::where('id', $conversationId)
            ->where('user_id', $user->id)
            ->with(['messages' => fn ($q) => $q->orderBy('created_at')])
            ->first();
    }
}