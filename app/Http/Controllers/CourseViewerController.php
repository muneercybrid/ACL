<?php

namespace App\Http\Controllers;

use App\Models\CourseOffering;
use App\Models\Lesson;
use App\Models\LessonProgress;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class CourseViewerController extends Controller
{
    public function show(CourseOffering $courseOffering): View
    {
        $this->authorize('view', $courseOffering);

        $this->loadPublishedContent($courseOffering);

        return view('courses.show', [
            'offering' => $courseOffering,
            'activeLesson' => $courseOffering->chapters->flatMap->lessons->first(),
            'completedLessonIds' => $this->completedLessonIds($courseOffering),
        ]);
    }

    public function showLesson(CourseOffering $courseOffering, Lesson $lesson): View
    {
        // Authorising the lesson rather than the offering is deliberate: the
        // policy resolves the owning offering from the lesson itself, so a
        // mismatched {courseOffering} in the URL cannot grant access.
        $this->authorize('view', $lesson);

        $this->loadPublishedContent($courseOffering);
        $lesson->load(['blocks', 'chapter']);

        return view('courses.show', [
            'offering' => $courseOffering,
            'activeLesson' => $lesson,
            'completedLessonIds' => $this->completedLessonIds($courseOffering),
        ]);
    }

    public function completeLesson(Lesson $lesson): JsonResponse
    {
        $this->authorize('complete', $lesson);

        LessonProgress::updateOrCreate(
            [
                'user_id' => Auth::id(),
                'lesson_id' => $lesson->id,
            ],
            [
                'status' => 'completed',
                'completed_at' => now(),
            ]
        );

        return response()->json(['success' => true, 'message' => 'Lesson completed!']);
    }

    private function loadPublishedContent(CourseOffering $courseOffering): void
    {
        $courseOffering->load([
            'course',
            'semester',
            'chapters.lessons' => fn ($query) => $query
                ->where('status', 'published')
                ->orderBy('position'),
        ]);
    }

    /**
     * IDs of the lessons the current user has completed in this offering.
     *
     * Resolved once per request instead of through a per-lesson relationship,
     * which both removes an N+1 in the sidebar and keeps the current user out
     * of the model layer.
     */
    private function completedLessonIds(CourseOffering $courseOffering): Collection
    {
        return LessonProgress::query()
            ->where('user_id', Auth::id())
            ->where('status', 'completed')
            ->whereIn('lesson_id', $courseOffering->chapters->flatMap->lessons->pluck('id'))
            ->pluck('lesson_id');
    }
}
