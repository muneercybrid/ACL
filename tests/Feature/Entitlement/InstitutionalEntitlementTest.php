<?php

namespace Tests\Feature\Entitlement;

use App\Models\User;
use App\Services\EntitlementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InstitutionalEntitlementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_student_is_entitled_to_courses_targeting_their_program_and_level(): void
    {
        $service = app(EntitlementService::class);
        $student = User::where('email', 'student@acl.local')->first();

        $offerings = $service->resolveEntitledOfferings($student);

        $this->assertCount(1, $offerings);
        $this->assertSame('CSC101', $offerings->first()->course->code);
    }

    public function test_student_is_not_entitled_to_other_program_courses(): void
    {
        $service = app(EntitlementService::class);
        $student = User::where('email', 'student@acl.local')->first();

        $codes = $service->resolveEntitledOfferings($student)->pluck('course.code');

        // CYB101 is offered and active, but targeted at Cybersecurity only.
        $this->assertNotContains('CYB101', $codes);
    }

    public function test_staff_members_receive_no_institutional_entitlement(): void
    {
        $service = app(EntitlementService::class);
        $lecturer = User::where('email', 'lecturer@acl.local')->first();

        $this->assertCount(0, $service->resolveEntitledOfferings($lecturer));
    }

    public function test_enrollment_sync_grants_free_institutional_access(): void
    {
        $service = app(EntitlementService::class);
        $student = User::where('email', 'student@acl.local')->first();

        $created = $service->syncInstitutionalEnrollments($student);

        $this->assertSame(1, $created);
        $this->assertDatabaseHas('enrollments', [
            'user_id' => $student->id,
            'source' => 'institutional_free',
            'status' => 'active',
        ]);
    }

    public function test_enrollment_sync_is_idempotent(): void
    {
        $service = app(EntitlementService::class);
        $student = User::where('email', 'student@acl.local')->first();

        $first = $service->syncInstitutionalEnrollments($student);
        $second = $service->syncInstitutionalEnrollments($student);

        $this->assertSame(1, $first);
        $this->assertSame(0, $second);
        $this->assertDatabaseCount('enrollments', 1);
    }
}
