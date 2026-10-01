<?php

namespace App\Http\Controllers\ACLi;

use App\Http\Controllers\Controller;
use App\Services\ACLi\AcliOrchestrator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

        // ACLi kept replying "I don't have access to your actual course
        // materials" to a student sitting on the COS101 chapter page. It was
        // true of the prompt -- no course content was ever put in front of the
        // model. The student's own enrolled chapter is supplied here, so ACLi
        // answers from the material the student is actually reading.
        $grounding = $this->courseGrounding($user, $validated);

        if ($grounding !== null) {
            array_unshift($messages, [
                'role' => 'system',
                'content' => $grounding,
            ]);
        }

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
     * Records a student's verdict on an ACLi answer.
     *
     * The content is stored against the conversation and the student, so a
     * superadmin can see which answers were useful and which were not. Only
     * the student who asked can attach feedback to their own exchange.
     */
    /**
     * Streaming variant of send(): the answer is emitted as it is produced.
     *
     * send() buffered the whole completion and returned it as one JSON body, so
     * the browser had nothing to show until the model had finished. That is what
     * produced the three bouncing dots followed by a fully-formed message, and
     * it read as a hang rather than as thinking.
     *
     * Each frame carries the text so far rather than the newest fragment: the
     * client re-renders markdown as a whole, and a half-written table row is not
     * valid markdown, so deltas would make tables flicker as they assembled.
     */
    public function stream(Request $request): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $user = Auth::user();

        if (! $user) {
            return response()->json(['success' => false, 'error' => 'Unauthenticated'], 401);
        }

        $validated = $request->validate([
            'message' => 'required|string|max:10000',
            'conversation_id' => 'nullable|exists:acli_conversations,id',
            'course_offering_id' => 'nullable|exists:course_offerings,id',
        ]);

        $messages = [['role' => 'user', 'content' => $validated['message']]];

        $grounding = $this->courseGrounding($user, $validated);

        if ($grounding !== null) {
            array_unshift($messages, ['role' => 'system', 'content' => $grounding]);
        }

        $orchestrator = $this->orchestrator;

        return response()->stream(function () use ($orchestrator, $messages, $validated) {
            $buffer = '';

            // Clear the typing indicator without waiting for the first token.
            echo 'data: ' . json_encode(['message' => '', 'status' => 'thinking']) . "\n\n";
            $this->flushBuffer();

            $result = $orchestrator->streamStudentChat(
                $messages,
                array_filter([
                    'conversation_id' => $validated['conversation_id'] ?? null,
                    'course_offering_id' => $validated['course_offering_id'] ?? null,
                ]),
                function (string $delta) use (&$buffer) {
                    $buffer .= $delta;

                    echo 'data: ' . json_encode(['message' => $buffer]) . "\n\n";
                    $this->flushBuffer();
                },
            );

            if (! $result['success']) {
                echo 'data: ' . json_encode(['error' => $result['error'] ?? 'ACLi could not answer.']) . "\n\n";
                $this->flushBuffer();

                return;
            }

            echo 'data: ' . json_encode([
                'message' => $buffer,
                'conversation_id' => $result['conversation_id'],
                'done' => true,
            ]) . "\n\n";
            $this->flushBuffer();
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache, no-transform',
            'Connection' => 'keep-alive',
            // Stops nginx buffering the stream back into one lump, which would
            // defeat the entire point of streaming.
            'X-Accel-Buffering' => 'no',
        ]);
    }

    /** Pushes bytes to the client now rather than at the end of the request. */
    private function flushBuffer(): void
    {
        if (ob_get_level() > 0) {
            @ob_flush();
        }

        @flush();
    }

    public function feedback(Request $request): JsonResponse
    {
        $user = Auth::user();

        if (! $user) {
            return response()->json(['success' => false, 'error' => 'Unauthenticated'], 401);
        }

        $validated = $request->validate([
            'conversation_id' => 'nullable|integer',
            'content' => 'nullable|string|max:4000',
            'verdict' => 'required|in:like,dislike',
        ]);

        $conversationId = $validated['conversation_id'] ?? null;

        // Never accept feedback against a conversation belonging to someone
        // else.
        if ($conversationId !== null
            && ! \App\Models\AcliConversation::whereKey($conversationId)
                ->where('user_id', $user->id)
                ->exists()) {
            return response()->json(['success' => false, 'error' => 'Not your conversation.'], 403);
        }

        DB::table('acli_message_feedback')->insert([
            'user_id' => $user->id,
            'conversation_id' => $conversationId,
            'content' => mb_substr((string) ($validated['content'] ?? ''), 0, 4000),
            'verdict' => $validated['verdict'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['success' => true]);
    }

    /**
     * Builds system context from the course the student is actually enrolled in.
     */
    protected function courseGrounding($user, array $validated): ?string
    {
        $courseId = DB::table('enrollments')
            ->join('course_offerings', 'course_offerings.id', '=', 'enrollments.course_offering_id')
            ->where('enrollments.user_id', $user->id)
            ->where('enrollments.status', 'active')
            ->select('course_offerings.course_id')
            ->distinct()
            ->pluck('course_id');

        if ($courseId->isEmpty()) {
            return null;
        }

        $course = DB::table('courses')->whereIn('id', $courseId)->first();

        if (! $course) {
            return null;
        }

        $chapters = DB::table('course_chapters')
            ->where('course_id', $course->id)
            ->where('placeholder', 0)
            ->orderBy('position')
            ->limit(40)
            ->get(['position', 'title', 'introduction']);

        if ($chapters->isEmpty()) {
            return null;
        }

        $outline = $chapters->map(function ($c) {
            return $c->position . '. ' . $c->title . "\n"
                . mb_substr((string) ($c->introduction ?? ''), 0, 600);
        })->implode("\n\n");

        return "You are ACLi, a tutor inside ACL. The student is enrolled in "
            . $course->title . " (" . $course->code . "). Its chapters are listed below.\n\n"
            . "Answer from this material when it is relevant. Do not claim you lack "
            . "access to the course -- you have it here. If a question goes beyond these "
            . "chapters, say so plainly rather than refusing.\n\n"
            . "Course outline:\n" . $outline;
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