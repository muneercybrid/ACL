<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\ACLi\AcliContextService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ACLi knows the student as a record, and only that student.
 *
 * A student asking what they are registered for previously bounced to the
 * dashboard, because the orchestrator never supplied the student\'s own academic
 * context. AcliContextService fixes that by attaching the authenticated user\'s
 * own record to the request.
 *
 * The tests below are mostly about the other half of that change. Granting ACLi
 * knowledge of the student is only safe if the knowledge cannot be aimed at
 * someone else, so the snapshot has to be structurally scoped: it is derived from
 * the User the service is handed, with no parameter anywhere that names a
 * different student. A student asking about a classmate has to get a refusal, and
 * the refusal has to come from the scope rather than from the model choosing to
 * behave.
 */
class AcliStudentContextTest extends TestCase
{
    use RefreshDatabase;

    protected function contextFor(User $user): array
    {
        $service = app(AcliContextService::class);

        return [
            'snapshot' => $service->forUser($user),
            'rendered' => $service->render($service->forUser($user)),
        ];
    }

    public function test_the_snapshot_reports_the_students_own_record(): void
    {
        $user = User::factory()->create(['email' => 'own-record@acl.local']);

        ['rendered' => $rendered] = $this->contextFor($user);

        $this->assertStringContainsString('own-record@acl.local', $rendered);
        $this->assertStringContainsString("student's OWN record", $rendered);
    }

    public function test_the_snapshot_never_contains_another_students_email(): void
    {
        $one = User::factory()->create(['email' => 'first@acl.local']);
        $two = User::factory()->create(['email' => 'second@acl.local']);

        ['rendered' => $oneView] = $this->contextFor($one);
        ['rendered' => $twoView] = $this->contextFor($two);

        $this->assertStringContainsString('first@acl.local', $oneView);
        $this->assertStringNotContainsString('second@acl.local', $oneView);

        $this->assertStringContainsString('second@acl.local', $twoView);
        $this->assertStringNotContainsString('first@acl.local', $twoView);
    }

    public function test_the_snapshot_tells_the_model_it_has_no_cross_student_visibility(): void
    {
        $user = User::factory()->create();

        ['rendered' => $rendered] = $this->contextFor($user);

        $this->assertStringContainsString(
            'no visibility into any other student',
            $rendered,
            'the boundary has to be stated, so a refusal is the expected answer to a cross-student question'
        );
    }

    public function test_a_student_with_no_enrollments_is_told_so_rather_than_left_to_guess(): void
    {
        $user = User::factory()->create();

        ['rendered' => $rendered] = $this->contextFor($user);

        $this->assertStringContainsString('none found in the system', $rendered);
    }

    public function test_the_context_service_exposes_no_way_to_name_a_different_student(): void
    {
        // Structural scoping, asserted against the signature. If a future change
        // adds a student identifier parameter, this fails -- that parameter is
        // precisely the hole this design is built to not have.
        $parameters = collect(
            (new \ReflectionClass(AcliContextService::class))->getMethods(\ReflectionMethod::IS_PUBLIC)
        )->flatMap(fn ($method) => collect($method->getParameters())->map(
            fn ($parameter) => $parameter->getName()
        ));

        $this->assertNotContains(
            'studentId',
            $parameters->all(),
            'AcliContextService must not accept a student identifier; scope comes from the authenticated user'
        );

        $this->assertNotContains(
            'student_id',
            $parameters->all(),
            'AcliContextService must not accept a student identifier; scope comes from the authenticated user'
        );
    }

    public function test_the_orchestrator_forbids_authoring_content_still(): void
    {
        // The context change must not have weakened the existing boundary.
        $source = file_get_contents(
            (new \ReflectionClass(\App\Services\ACLi\AcliOrchestrator::class))->getFileName()
        );

        $this->assertStringContainsString('You do NOT author course content', $source);
    }

    public function test_the_orchestrator_resolves_from_the_container(): void
    {
        // AppServiceProvider builds AcliOrchestrator by hand rather than letting
        // the container autowire it. That hand-written argument list silently
        // drifted from the constructor when AcliContextService was added, and
        // every chat request died with an ArgumentCountError while the unit tests
        // on the context service kept passing. Resolving it here keeps the two
        // lists honest.
        $orchestrator = app(\App\Services\ACLi\AcliOrchestrator::class);

        $this->assertInstanceOf(
            \App\Services\ACLi\AcliOrchestrator::class,
            $orchestrator,
            'AcliOrchestrator must resolve from the container; a manual constructor call in AppServiceProvider must supply every argument'
        );
    }
}