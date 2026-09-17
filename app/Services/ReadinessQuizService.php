<?php
namespace App\Services;
use App\Models\Course;
use App\Models\Curriculum\CourseOutline;
use App\Models\OutlineQuestion;

class ReadinessQuizService
{
    /**
     * Build readiness quiz from course outline questions (existing table structure).
     * Per spec Phase 11: course orientation + readiness assessment.
     */
    public function buildQuiz(Course $course, int $userId): array
    {
        $outline = CourseOutline::where('course_id', $course->id)->where('is_locked', false)->first();
        $questions = $outline ? $outline->questions() ?? collect() : collect();
        return [
            'course_id' => $course->id,
            'course_title' => $course->title,
            'outline_version' => $outline?->version ?? 1,
            'questions' => $questions->map(fn ($q) => [
                'id' => $q->id,
                'text' => $q->question_text,
                'options' => $q->options ?? [],
            ])->toArray(),
            'user_id' => $userId,
            'status' => 'pending',
        ];
    }
}
