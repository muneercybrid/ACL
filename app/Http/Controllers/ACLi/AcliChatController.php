<?php

namespace App\Http\Controllers\ACLi;

use App\Http\Controllers\Controller;
use App\Services\ACLi\AcliOrchestrator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AcliChatController extends Controller
{
    public function __construct(protected AcliOrchestrator $orchestrator) {}

    /**
     * Show the ACLi chat page.
     */
    public function page(): \Illuminate\View\View
    {
        $user = Auth::user();

        $conversations = $user
            ? $this->orchestrator->getConversations($user)
            : collect();

        return view('student.acli-chat', [
            'conversations' => $conversations,
            'activeConversationId' => old('conversation_id', null),
        ]);
    }

    /**
     * Send a chat message and get AI response.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function send(Request $request): JsonResponse
    {
        $user = Auth::user();

        if (! $user) {
            return response()->json([
                'success' => false,
                'error' => 'Unauthenticated',
            ], 401);
        }

        $validated = $request->validate([
            'message' => 'required|string|max:10000',
            'conversation_id' => 'nullable|exists:acli_conversations,id',
            'course_offering_id' => 'nullable|exists:course_offerings,id',
            'chapter_id' => 'nullable|exists:chapters,id',
            'lesson_id' => 'nullable|exists:lessons,id',
            'context' => 'nullable|array',
        ]);

        $messages = [
            ['role' => 'user', 'content' => $validated['message']],
        ];

        // If context is provided (e.g., highlighted text), add as system context
        if (! empty($validated['context']['selected_text'])) {
            array_unshift($messages, [
                'role' => 'system',
                'content' => "The user has highlighted the following text from their course material:\n\n" .
                    $validated['context']['selected_text'] .
                    "\n\nPlease address this specific content in your response.",
            ]);
        }

        $options = [
            'conversation_id' => $validated['conversation_id'] ?? null,
            'course_offering_id' => $validated['course_offering_id'] ?? null,
            'chapter_id' => $validated['chapter_id'] ?? null,
            'lesson_id' => $validated['lesson_id'] ?? null,
        ];

        $result = $this->orchestrator->studentChat($messages, $options);

        if (! $result['success']) {
            $status = $result['code'] === 'ENTITLEMENT_DENIED' ? 403 : 503;
            return response()->json($result, $status);
        }

        return response()->json($result);
    }

    /**
     * Get user's conversations list.
     */
    public function conversations(Request $request): JsonResponse
    {
        $user = Auth::user();

        if (! $user) {
            return response()->json([
                'success' => false,
                'error' => 'Unauthenticated',
            ], 401);
        }

        $conversations = $this->orchestrator->getConversations($user);

        return response()->json([
            'success' => true,
            'conversations' => $conversations->map(fn ($c) => [
                'id' => $c->id,
                'title' => $c->title,
                'status' => $c->status,
                'messages_count' => $c->messages_count,
                'course_offering_id' => $c->course_offering_id,
                'chapter_id' => $c->chapter_id,
                'lesson_id' => $c->lesson_id,
                'updated_at' => $c->updated_at?->toISOString(),
            ]),
        ]);
    }

    /**
     * Get a specific conversation with messages.
     */
    public function conversation(Request $request, int $conversationId): JsonResponse
    {
        $user = Auth::user();

        if (! $user) {
            return response()->json([
                'success' => false,
                'error' => 'Unauthenticated',
            ], 401);
        }

        $conversation = $this->orchestrator->getConversation($user, $conversationId);

        if (! $conversation) {
            return response()->json([
                'success' => false,
                'error' => 'Conversation not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'conversation' => [
                'id' => $conversation->id,
                'title' => $conversation->title,
                'status' => $conversation->status,
                'course_offering_id' => $conversation->course_offering_id,
                'chapter_id' => $conversation->chapter_id,
                'lesson_id' => $conversation->lesson_id,
                'messages' => $conversation->messages->map(fn ($m) => [
                    'id' => $m->id,
                    'role' => $m->role,
                    'content' => $m->content,
                    'metadata' => $m->metadata,
                    'created_at' => $m->created_at?->toISOString(),
                ]),
            ],
        ]);
    }

    /**
     * Create a new conversation.
     */
    public function newConversation(Request $request): JsonResponse
    {
        $user = Auth::user();

        if (! $user) {
            return response()->json([
                'success' => false,
                'error' => 'Unauthenticated',
            ], 401);
        }

        $validated = $request->validate([
            'title' => 'nullable|string|max:255',
            'course_offering_id' => 'nullable|exists:course_offerings,id',
            'chapter_id' => 'nullable|exists:chapters,id',
            'lesson_id' => 'nullable|exists:lessons,id',
        ]);

        $conversation = \App\Models\ACLi\Conversation::create([
            'user_id' => $user->id,
            'title' => $validated['title'] ?? 'New conversation',
            'course_offering_id' => $validated['course_offering_id'] ?? null,
            'chapter_id' => $validated['chapter_id'] ?? null,
            'lesson_id' => $validated['lesson_id'] ?? null,
            'status' => 'active',
        ]);

        return response()->json([
            'success' => true,
            'conversation' => [
                'id' => $conversation->id,
                'title' => $conversation->title,
                'status' => $conversation->status,
            ],
        ]);
    }

    /**
     * Delete/close a conversation.
     */
    public function closeConversation(Request $request, int $conversationId): JsonResponse
    {
        $user = Auth::user();

        if (! $user) {
            return response()->json([
                'success' => false,
                'error' => 'Unauthenticated',
            ], 401);
        }

        $conversation = \App\Models\ACLi\Conversation::where('id', $conversationId)
            ->where('user_id', $user->id)
            ->first();

        if (! $conversation) {
            return response()->json([
                'success' => false,
                'error' => 'Conversation not found',
            ], 404);
        }

        $conversation->update(['status' => 'closed']);

        return response()->json([
            'success' => true,
            'message' => 'Conversation closed',
        ]);
    }
}