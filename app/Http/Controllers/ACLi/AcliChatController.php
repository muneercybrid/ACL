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
    public function page(?int $conversation = null): \Illuminate\View\View
    {
        $user = Auth::user();

        $conversations = $user
            ? $this->orchestrator->getConversations($user)
            : collect();

        // Restores a chat when it is opened by its own URL. Without this a
        // refresh landed on an empty page and the whole transcript was gone,
        // because the only entry point rendered an empty shell.
        $messages = collect();

        if ($conversation && $user) {
            $conversationModel = \App\Models\ACLi\Conversation::whereKey($conversation)
                ->where('user_id', $user->id)
                ->first();

            if (! $conversationModel) {
                // Someone else's conversation id must not disclose that it
                // exists; treat it as "no such chat".
                abort(404);
            }

            $messages = $conversationModel->messages()
                ->where('role', '!=', 'system')
                ->orderBy('created_at')
                ->get();
        }

        return view('student.acli-chat', [
            'conversations' => $conversations,
            'activeConversationId' => $conversation,
            'initialMessages' => $messages,
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
            && ! \App\Models\ACLi\Conversation::whereKey($conversationId)
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
     * Grounds ACLi in the student's programme, not one arbitrary course.
     *
     * The first version took whichever enrolled course came back first, so a
     * student saying "hi" was greeted as if they were sitting in COS101 no
     * matter what they were doing. Course is a poor unit of context here: the
     * student has a programme, and that is the thing that should shape ACLi.
     * Where the conversation is attached to a specific course -- opening the
     * chat from a course page -- that course is used, because it is a fact the
     * student supplied rather than an arbitrary pick.
     */
    protected function courseGrounding($user, array $validated): ?string
    {
        $programme = app(\App\Services\StudentDashboardService::class)
            ->curriculumProgramme($user->student ?? null);

        // A course chosen explicitly by the student wins over the programme.
        $focusCourseId = $validated['course_id'] ?? null;

        // A course chosen explicitly by the student wins.
        if (! $focusCourseId && ! empty($validated['course_id'])) {
            $focusCourseId = $validated['course_id'];
        }

        // Direct chat (no course_id) = the student's own courses, both
        // CCMAS (course_offerings) and organization-added
        // (programme_level_courses, source='institution'). Without this
        // the student sees only CCMAS courses and never their school's
        // own additions.
        if (! $focusCourseId) {
            $focusCourseId = DB::table('enrollments')
                ->join('course_offerings', 'course_offerings.id', '=', 'enrollments.course_offering_id')
                ->where('enrollments.user_id', $user->id)
                ->where('enrollments.status', 'active')
                ->orderByDesc('enrollments.updated_at')
                ->value('course_offerings.course_id');

            if (! $focusCourseId) {
                $focusCourseId = DB::table('programme_level_courses')
                    ->join('academic_programs', 'academic_programs.id', '=', 'programme_level_courses.academic_program_id')
                    ->join('organization_memberships', 'organization_memberships.academic_program_id', '=', 'academic_programs.id')
                    ->where('organization_memberships.user_id', $user->id)
                    ->where('organization_memberships.status', 'active')
                    ->where('programme_level_courses.source', 'institution')
                    ->orderByDesc('programme_level_courses.updated_at')
                    ->value('programme_level_courses.course_id');
            }
        }

        if (! $focusCourseId) {
            return null;
        }

        $course = DB::table('courses')->where('id', $focusCourseId)->first();

        if (! $course) {
            return null;
        }

        $chapters = DB::table('course_chapters')
            ->where('course_id', $course->id)
            ->where('placeholder', 0)
            ->orderBy('position')
            ->limit(30)
            ->get(['position', 'title', 'introduction']);

        if ($chapters->isEmpty()) {
            return null;
        }

        $outline = $chapters->map(fn ($c) => $c->position . '. ' . $c->title . "\n"
            . mb_substr((string) ($c->introduction ?? ''), 0, 500))->implode("\n\n");

        $scope = $programme
            ? "The student is enrolled in {$programme->name}."
            : '';

        return "You are ACLi, a tutor inside ACL. {$scope} They are currently working on "
            . "{$course->title} ({$course->code}). Its chapters are below.\n\n"
            . "Answer from this material when it is relevant, but remember the student has a "
            . "whole programme behind this one course -- answer questions from elsewhere in it too, "
            . "and do not claim this course is all they are studying.\n\n"
            . "Do not claim you lack access to the course: you have it here. If something is "
            . "outside everything above, say so plainly rather than refusing.\n\n"
            . "Chapter outline:\n" . $outline;
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