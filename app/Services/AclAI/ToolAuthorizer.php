<?php

namespace App\Services\AclAI;

use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * Tool Authorizer - authorizes AI tool calls based on role and entitlement.
 *
 * Every tool that ACL AI can call (get_student_profile, get_course_progress,
 * etc.) goes through this authorizer before the tool is executed.
 *
 * Security rule: The frontend must NOT be responsible for enforcing the paid
 * requirement. This authorizer runs server-side on every AI request.
 */
class ToolAuthorizer
{
    /** Check if user has AI entitlement for a capability */
    public function hasAIEntitlement(User $user, string $capability): bool
    {
        // Check role assignments and their permissions
        $roleAssignments = $user->roleAssignments()
            ->whereHas('role.permissions', fn($q) => $q->where('slug', 'ai:' . $capability));

        // Platform admins bypass via Gate::before()
        if ($user->isPlatformAdministrator()) {
            return true;
        }

        return $roleAssignments->exists();
    }

    /**
     * Authorize a tool call for a specific user and capability.
     *
     * This is the server-side gate that must be checked BEFORE any AI provider call.
     * The frontend must never be responsible for this check.
     *
     * @param User $user The user making the request
     * @param string $toolName The name of the AI tool to call
     * @param string $capability The capability key (e.g., 'student.ask')
     * @return bool true if the tool call is authorized, false otherwise
     */
    public function authorizeToolCall(User $user, string $toolName, string $capability): bool
    {
        // First check the capability entitlement
        $hasEntitlement = $this->hasAIEntitlement($user, $capability);
        if (!$hasEntitlement) {
            Log::warning("AI tool authorization denied: {$capability} for user {$user->id}", [
                'user_role' => $user->role,
                'tool' => $toolName,
                'reason' => 'No AI entitlement',
            ]);

            return false;
        }

        // For students, additionally check that the tool is appropriate for their scope
        // Students can only access their own data through AI tools
        if ($user->hasRole('student')) {
            return $this->checkStudentToolScope($user, $toolName);
        }

        // For other roles (tutor, teacher, department admin, etc.), check role-based permissions
        // Non-student roles have access determined by permissions and subscription/organization plan
        return true;
    }

    /**
     * Check that a student-only tool is being used within the student's scope.
     *
     * Students can only access their own data through AI tools.
     * The tool itself must reject requests for another student's data.
     *
     * @param User $user The student user
     * @param string $toolName The name of the AI tool
     * @return bool true if the tool call is within the student's scope
     */
    protected function checkStudentToolScope(User $user, string $toolName): bool
    {
        // The actual scope enforcement is done at the tool implementation level.
        // This method is a placeholder for the policy check.
        // Example tools that students can use:
        // - get_student_profile(self)
        // - get_learning_progress(self)
        // - get_attempt_history(self)
        // - get_quiz_results(self)
        //
        // Tools that must be rejected:
        // - get_student_profile(other_student_id)
        // - get_learning_progress(other_student_id)
        //
        // The tool implementation must check: is the requested user_id the current user's id?
        return true;
    }

    /**
     * Log an AI authorization attempt for audit purposes.
     *
     * @param User $user The user whose access was checked
     * @param string $capability The capability being checked
     * @param string $toolName The tool being requested
     * @param bool $authorized true if authorized, false otherwise
     * @param string $reason Optional reason if not authorized
     */
    public function logAuthorizationAttempt(User $user, string $capability, string $toolName, bool $authorized, string $reason = ''): void
    {
        \Log::channel('ai_authorization')->info(
            "AI authorization: {$capability} by user {$user->id} ({$user->email})",
            [
                'user_role' => $user->role,
                'tool' => $toolName,
                'authorized' => $authorized,
                'reason' => $reason,
                'timestamp' => now()->toISOString(),
            ]
        );
    }
}