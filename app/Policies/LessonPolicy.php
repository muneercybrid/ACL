<?php

namespace App\Policies;

use App\Models\Lesson;
use App\Models\User;

class LessonPolicy
{
    /**
     * A lesson is readable when it is published and the user holds a live
     * enrollment in the offering that owns it.
     *
     * Resolving the offering through the lesson's own chapter -- rather than
     * trusting an offering supplied in the URL -- is what prevents a user
     * enrolled in one offering from reading another offering's lessons.
     */
    public function view(User $user, Lesson $lesson): bool
    {
        if ($lesson->status !== 'published') {
            return false;
        }

        return $user->enrollments()
            ->active()
            ->where('course_offering_id', $lesson->chapter->course_offering_id)
            ->exists();
    }

    /**
     * Recording progress requires the same access as reading the lesson.
     */
    public function complete(User $user, Lesson $lesson): bool
    {
        return $this->view($user, $lesson);
    }
}
