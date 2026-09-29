<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\ACLi\AcliOrchestrator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A student's ACLi access is to ask questions about material they already
 * have. It is not an authoring surface.
 *
 * Course content is produced by CourseContentGenerator, which is a console
 * command reached by no web route, and is written as a draft for human review.
 * A student must not be able to obtain course material through chat, and must
 * not be able to reach the generator at all.
 */
class AcliStudentCannotAuthorCourseContentTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_generator_is_not_reachable_from_the_web(): void
    {
        // The generator lives on the console only. If a route is ever added for
        // it, this fails rather than quietly exposing authorship to students.
        $webRoutes = collect(app('router')->getRoutes()->getRoutes())
            ->filter(fn ($route) => in_array('web', $route->gatherMiddleware(), true))
            ->map(fn ($route) => $route->uri())
            ->values();

        $this->assertFalse(
            $webRoutes->contains(fn ($uri) => str_contains($uri, 'generate-content')),
            'course content generation must not be exposed on any web route'
        );
    }

    public function test_the_student_system_prompt_forbids_authoring(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $source = file_get_contents(
            (new \ReflectionClass(AcliOrchestrator::class))->getFileName()
        );

        $this->assertStringContainsString(
            'You do NOT author course content',
            $source,
            'the student ACLi system prompt must forbid authoring course content'
        );

        // The refusal has to name the boundary, not just say no vaguely.
        $this->assertMatchesRegularExpression(
            '/do NOT author course content.*chapters/s',
            $source
        );
    }
}
