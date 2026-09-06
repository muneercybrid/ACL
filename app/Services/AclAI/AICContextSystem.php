<?php

namespace App\Services\AclAI;

/**
 * AI Context System - provides contextual information to AI based on current UI context.
 *
 * For paid students, ACL AI should understand the current context:
 * - Current course
 * - Current chapter
 * - Current lesson
 * - Selected text/highlight
 * - Recent progress and attempt history
 *
 * This context is passed to the AI along with the user's prompt to provide
 * more relevant and accurate responses.
 *
 * Security rule: The context system must NOT expose another student's data.
 * All data retrieved through the context system must be scoped to the current user.
 */
class AICContextSystem
{
    /** @var \App\Services\AclAI\ToolAuthorizer */
    protected $toolAuthorizer;

    public function __construct()
    {
        $this->toolAuthorizer = new ToolAuthorizer();
    }

    /**
     * Get the current contextual information for AI.
     *
     * This method retrieves the context that should be included with
     * every AI request from a paid student. The context helps the AI
     * provide more relevant and accurate responses.
     *
     * @param User $user The authenticated user
     * @return array Contextual information scoped to the current user
     */
    public function getCurrentContext(User $user): array
    {
        // Build the base context
        $context = [
            'user_id' => $user->id,
            'user_name' => $user->name,
            'role' => $user->getRoleForAI ?? $this->detectUserRole($user),
            'subscription_active' => $user->subscription?->isActiveWithAIEntitlement() ?? false,
            'subscription_plan' => $user->subscription?->plan ?? null,
        ];

        // If the user is on a course page, add course context
        if (request()->has('course_offering_id')) {
            $offeringId = (int) request('course_offering_id');
            $offering = \App\Models\CourseOffering::with(['course', 'semester', 'department'])
                ->where('is_active', true)
                ->find($offeringId);

            if ($offering && $this->userHasAccessToOffering($user, $offering)) {
                $context['course'] = [
                    'id' => $offering->id,
                    'code' => $offering->course?->code,
                    'title' => $offering->course?->title,
                    'department' => $offering->department?->name,
                    'semester' => $offering->semester?->name,
                    'level' => $offering->levels->pluck('name')->implode(', '),
                ];
            }
        }

        // If the user is on a lesson page, add lesson context
        if (request()->has('lesson_id')) {
            $lessonId = (int) request('lesson_id');
            $lesson = \App\Models\Lesson::with('chapter')
                ->where('is_active', true)
                ->find($lessonId);

            if ($lesson && $this->userHasAccessToLesson($user, $lesson)) {
                $context['lesson'] = [
                    'id' => $lesson->id,
                    'title' => $lesson->title,
                    'chapter_id' => $lesson->chapter?->id,
                    'chapter_title' => $lesson->chapter?->title,
                    'position' => $lesson->position,
                ];
            }
        }

        // Get selected/highlighted text context if available
        $selectedText = $this->getSelectedText();
        if ($selectedText) {
            $context['selected_text'] = $selectedText;
        }

        // Add recent progress context for students
        if ($user->hasRole('student')) {
            $context['progress'] = $this->getStudentProgressContext($user);
        }

        return $context;
    }

    /**
     * Check if the user has access to a course offering.
     *
     * For students, this means they must have an active enrollment.
     * For tutors/admins, this means they must be authorized for that course.
     *
     * @param User $user The user
     * @param \App\Models\CourseOffering $offering The course offering
     * @return bool true if the user has access
     */
    protected function userHasAccessToOffering(User $user, \App\Models\CourseOffering $offering): bool
    {
        if ($user->hasRole('super_admin')) {
            return true;
        }

        if ($user->hasRole('institution_admin')) {
            // Check institution scope
            return true; // Institution admins have access to all courses in their institution
        }

        if ($user->hasRole('department_admin')) {
            // Check department scope
            return $offering->department?->id === $user->current_department_id;
        }

        if ($user->hasRole('student')) {
            // Students must have an active enrollment in this offering
            return $user->enrollments()
                ->where('course_offering_id', $offering->id)
                ->where('status', 'active')
                ->exists();
        }

        // Tutors and teachers
        return $user->canView($offering); // Uses policy
    }

    /**
     * Check if the user has access to a lesson.
     *
     * @param User $user The user
     * @param \App\Models\Lesson $lesson The lesson
     * @return bool true if the user has access
     */
    protected function userHasAccessToLesson(User $user, \App\Models\Lesson $lesson): bool
    {
        // Get the course offering for this lesson
        $offering = $lesson->chapter?->courseOffering;

        if (!$offering) {
            return false;
        }

        return $this->userHasAccessToOffering($user, $offering);
    }

    /**
     * Get the selected/highlighted text context.
     *
     * This would be set by the frontend when a student highlights text
     * to get an AI explanation.
     *
     * @return array|null The selected text and its context, or null
     */
    protected function getSelectedText(): ?array
    {
        // The selected text would be passed via a request parameter or
        // stored in the session by the frontend JavaScript.
        // For now, return null - the frontend should pass this information.
        $selected = request()->get('selected_text');

        if (!$selected) {
            return null;
        }

        return [
            'text' => $selected,
            'source' => request()->get('selected_source', 'unknown'),
            'selection_type' => request()->get('selection_type', 'text'), // text, equation, code
        ];
    }

    /**
     * Get student progress context for AI.
     *
     * This includes the student's attempt history, quiz results, progress,
 * and other learning data that the AI can use to provide personalized
     * assistance.
     *
     * @param User $user The student user
     * @return array Progress context scoped to the current student
     */
    protected function getStudentProgressContext(User $user): array
    {
        $progress = [];

        // Get recent lesson completions
        $recentCompletions = $user->lessonProgress()
            ->where('status', 'completed')
            ->latest('completed_at')
            ->take(10)
            ->get();

        $progress['recent_completions'] = $recentCompletions->count();

        // Get attempt history
        $attemptHistory = $user->attemptHistory()
            ->latest('attempted_at')
            ->take(20)
            ->get();

        $progress['attempt_history'] = $attemptHistory->count();

        // Get quiz results
        $quizResults = $user->quizResults()
            ->latest('taken_at')
            ->take(10)
            ->get();

        $progress['quiz_results'] = $quizResults->count();

        // Calculate overall progress across enrolled courses
        $enrolledOfferings = $user->enrollments()
            ->where('status', 'active')
            ->with(['courseOffering.course'])
            ->get();

        $totalLessons = 0;
        $completedLessons = 0;

        foreach ($enrolledOfferings as $offering) {
            $courseLessons = $offering->chapters->flatMap->lessons->where('is_active', true)->count();
            $totalLessons += $courseLessons;

            $completedInOffering = $user->lessonProgress()
                ->whereHas('lesson.chapter.courseOffering', function ($q) use ($offering) {
                    $q->where('course_offering_id', $offering->id);
                })
                ->where('status', 'completed')
                ->count();

            $completedLessons += $completedInOffering;
        }

        $progress['total_courses'] = $enrolledOfferings->count();
        $progress['total_lessons'] = $totalLessons;
        $progress['completed_lessons'] = $completedLessons;
        $progress['overall_progress'] = $totalLessons > 0
            ? round(($completedLessons / $totalLessons) * 100)
            : 0;

        return $progress;
    }

    /** Detect user role for AI purposes */
    protected function detectUserRole(User $user): string
    {
        if ($user->hasRole('super_admin')) {
            return 'super_admin';
        }

        if ($user->hasRole('institution_admin')) {
            return 'institution_admin';
        }

        if ($user->hasRole('department_admin')) {
            return 'department_admin';
        }

        if ($user->hasRole('teacher')) {
            return 'teacher';
        }

        if ($user->hasRole('tutor')) {
            return 'tutor';
        }

        if ($user->hasRole('student')) {
            return 'student';
        }

        return 'unknown';
    }
}