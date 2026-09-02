<?php

namespace Tests\Feature\Authorization;

use App\Models\Chapter;
use App\Models\CourseOffering;
use App\Models\Lesson;
use App\Models\User;
use App\Services\EntitlementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Server-side authorization for the course viewer.
 *
 * The student seeded by DevelopmentSeeder is entitled to CSC101 only; CYB101
 * targets the Cybersecurity programme and is used throughout as the offering
 * they must never reach.
 */
class CourseAccessTest extends TestCase
{
    use RefreshDatabase;

    private User $student;
    private User $admin;
    private CourseOffering $enrolledOffering;
    private CourseOffering $foreignOffering;
    private Lesson $enrolledLesson;
    private Lesson $foreignLesson;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->student = User::where('email', 'student@acl.local')->firstOrFail();
        $this->admin = User::where('email', 'admin@acl.local')->firstOrFail();

        app(EntitlementService::class)->syncInstitutionalEnrollments($this->student);

        $this->enrolledOffering = CourseOffering::whereRelation('course', 'code', 'CSC101')->firstOrFail();
        $this->foreignOffering = CourseOffering::whereRelation('course', 'code', 'CYB101')->firstOrFail();

        $this->enrolledLesson = Lesson::whereRelation('chapter', 'course_offering_id', $this->enrolledOffering->id)
            ->firstOrFail();

        // The seeder authors content for CSC101 only, so CYB101 needs a lesson
        // of its own to stand in for content the student must not reach.
        $this->foreignLesson = $this->makeLesson($this->foreignOffering, 'published');
    }

    private function makeLesson(CourseOffering $offering, string $status): Lesson
    {
        $chapter = Chapter::create([
            'course_offering_id' => $offering->id,
            'title' => 'Chapter for '.$offering->id.'-'.$status,
            'slug' => 'chapter-'.$offering->id.'-'.$status,
            'position' => 99,
        ]);

        return Lesson::create([
            'chapter_id' => $chapter->id,
            'title' => 'Lesson '.$status,
            'slug' => 'lesson-'.$offering->id.'-'.$status,
            'position' => 1,
            'status' => $status,
        ]);
    }

    // --- Baseline: the happy path still works -----------------------------

    public function test_enrolled_student_can_view_their_offering(): void
    {
        $this->actingAs($this->student)
            ->get(route('courses.show', $this->enrolledOffering))
            ->assertOk();
    }

    public function test_enrolled_student_can_view_a_published_lesson(): void
    {
        $this->actingAs($this->student)
            ->get(route('courses.lessons.show', [$this->enrolledOffering, $this->enrolledLesson]))
            ->assertOk()
            ->assertSee($this->enrolledLesson->title);
    }

    public function test_enrolled_student_can_complete_a_published_lesson(): void
    {
        $this->actingAs($this->student)
            ->post(route('lessons.complete', $this->enrolledLesson))
            ->assertOk();

        $this->assertDatabaseHas('lesson_progress', [
            'user_id' => $this->student->id,
            'lesson_id' => $this->enrolledLesson->id,
            'status' => 'completed',
        ]);
    }

    // --- Enrollment boundaries --------------------------------------------

    public function test_student_cannot_view_an_offering_they_are_not_enrolled_in(): void
    {
        $this->actingAs($this->student)
            ->get(route('courses.show', $this->foreignOffering))
            ->assertForbidden();
    }

    public function test_student_cannot_view_a_lesson_of_an_offering_they_are_not_enrolled_in(): void
    {
        $this->actingAs($this->student)
            ->get(route('courses.lessons.show', [$this->foreignOffering, $this->foreignLesson]))
            ->assertForbidden();
    }

    /**
     * The original bug: enrollment was checked against the offering in the URL
     * while the lesson was bound independently, so pairing an enrolled
     * offering with a foreign lesson served content across the boundary.
     *
     * Route scoping now rejects the mismatched pair at binding time, which is
     * a 404 rather than a 403 -- it does not confirm the lesson exists.
     */
    public function test_student_cannot_reach_a_foreign_lesson_through_an_enrolled_offering(): void
    {
        $this->actingAs($this->student)
            ->get(route('courses.lessons.show', [$this->enrolledOffering, $this->foreignLesson]))
            ->assertNotFound();
    }

    public function test_student_cannot_complete_a_lesson_they_are_not_enrolled_in(): void
    {
        $this->actingAs($this->student)
            ->post(route('lessons.complete', $this->foreignLesson))
            ->assertForbidden();

        $this->assertDatabaseMissing('lesson_progress', [
            'user_id' => $this->student->id,
            'lesson_id' => $this->foreignLesson->id,
        ]);
    }

    // --- Enrollment lifecycle ---------------------------------------------

    public function test_expired_enrollment_denies_access(): void
    {
        $this->student->enrollments()
            ->where('course_offering_id', $this->enrolledOffering->id)
            ->update(['expires_at' => now()->subDay()]);

        $this->actingAs($this->student)
            ->get(route('courses.show', $this->enrolledOffering))
            ->assertForbidden();
    }

    public function test_withdrawn_enrollment_denies_access(): void
    {
        $this->student->enrollments()
            ->where('course_offering_id', $this->enrolledOffering->id)
            ->update(['status' => 'withdrawn']);

        $this->actingAs($this->student)
            ->get(route('courses.show', $this->enrolledOffering))
            ->assertForbidden();
    }

    public function test_enrollment_without_an_expiry_still_grants_access(): void
    {
        $this->student->enrollments()
            ->where('course_offering_id', $this->enrolledOffering->id)
            ->update(['expires_at' => null]);

        $this->actingAs($this->student)
            ->get(route('courses.show', $this->enrolledOffering))
            ->assertOk();
    }

    // --- Draft content -----------------------------------------------------

    public function test_draft_lessons_are_not_viewable_even_when_enrolled(): void
    {
        $draft = $this->makeLesson($this->enrolledOffering, 'draft');

        $this->actingAs($this->student)
            ->get(route('courses.lessons.show', [$this->enrolledOffering, $draft]))
            ->assertForbidden();
    }

    public function test_draft_lessons_cannot_be_completed_even_when_enrolled(): void
    {
        $draft = $this->makeLesson($this->enrolledOffering, 'draft');

        $this->actingAs($this->student)
            ->post(route('lessons.complete', $draft))
            ->assertForbidden();

        $this->assertDatabaseMissing('lesson_progress', [
            'user_id' => $this->student->id,
            'lesson_id' => $draft->id,
        ]);
    }

    // --- Administrator bypass ----------------------------------------------

    public function test_platform_admin_can_view_any_offering_without_enrolling(): void
    {
        $this->assertSame(0, $this->admin->enrollments()->count());

        $this->actingAs($this->admin)
            ->get(route('courses.show', $this->foreignOffering))
            ->assertOk();
    }

    public function test_platform_admin_can_view_a_draft_lesson(): void
    {
        $draft = $this->makeLesson($this->enrolledOffering, 'draft');

        $this->actingAs($this->admin)
            ->get(route('courses.lessons.show', [$this->enrolledOffering, $draft]))
            ->assertOk();
    }

    // --- Authentication ----------------------------------------------------

    public function test_guests_cannot_reach_course_content(): void
    {
        $this->get(route('courses.show', $this->enrolledOffering))->assertRedirect(route('login'));
        $this->post(route('lessons.complete', $this->enrolledLesson))->assertRedirect(route('login'));
    }
}
