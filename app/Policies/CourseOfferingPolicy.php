<?php

namespace App\Policies;

use App\Models\CourseOffering;
use App\Models\User;

class CourseOfferingPolicy
{
    /**
     * Course content is visible only to users holding a live enrollment.
     */
    public function view(User $user, CourseOffering $courseOffering): bool
    {
        return $user->enrollments()
            ->active()
            ->where('course_offering_id', $courseOffering->id)
            ->exists();
    }
}
