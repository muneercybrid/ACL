<?php

namespace Tests\Feature;

use App\Models\AcademicProgram;
use App\Models\Department;
use App\Models\Faculty;
use App\Models\Curriculum\Programme;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\Student;
use App\Models\User;
use App\Services\StudentDashboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A student must be served the curriculum of their own programme.
 *
 * This test previously looked up student 120001 by a hard-coded primary key and
 * ran against whatever happened to be in the database. It could only ever pass
 * against the live production dataset, and in a test database — which is
 * correctly empty — Student::find(120001) returned null and the test failed on
 * its own fixture rather than on any behaviour. It now builds the records it
 * needs, so it exercises the resolution logic and not the data.
 *
 * The chain under test:
 *   Student -> OrganizationMembership -> AcademicProgram -> Programme
 * with the programme resolved by name, since the institution's programme name
 * ("Cyber Security") is a substring of the NUC programme name
 * ("B.Sc. Cyber Security") rather than identical to it.
 */
class StudentDashboardProgrammeIsolationTest extends TestCase
{
    use RefreshDatabase;

    private function studentInProgramme(string $institutionProgramme, string $nucProgramme): Student
    {
        $user = User::create([
            'name' => 'Probe Student',
            'email' => 'probe.student@acl.test',
            'password' => bcrypt('password'),
        ]);

        $student = Student::create([
            'user_id' => $user->id,
            'acl_student_id' => 'ACL-PROBE-1',
            'verification_method' => 'jamb',
            'verification_status' => 'verified',
        ]);

        $organization = Organization::create([
            'name' => 'Probe University',
            'slug' => 'probe-university',
        ]);

        $faculty = Faculty::create([
            'organization_id' => $organization->id,
            'name' => 'Faculty of Computing',
            'slug' => 'faculty-of-computing',
            'is_active' => true,
        ]);

        $department = Department::create([
            'faculty_id' => $faculty->id,
            'name' => $institutionProgramme,
            'slug' => \Illuminate\Support\Str::slug($institutionProgramme),
            'is_active' => true,
        ]);

        $academicProgram = AcademicProgram::create([
            'organization_id' => $organization->id,
            'department_id' => $department->id,
            'name' => $institutionProgramme,
            'slug' => \Illuminate\Support\Str::slug($institutionProgramme),
            'code' => 'PROBE',
            'is_active' => true,
        ]);

        OrganizationMembership::create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'membership_type' => 'student',
            'status' => 'active',
            'joined_at' => now(),
            'academic_program_id' => $academicProgram->id,
        ]);

        Programme::create([
            'name' => $nucProgramme,
            'code' => \Illuminate\Support\Str::upper(\Illuminate\Support\Str::slug($nucProgramme, '_')),
            'degree_type' => 'bachelor',
            'duration_years' => 4,
            'scope' => 'national',
            'status' => 'active',
            'is_active' => true,
        ]);

        return $student;
    }

    public function test_cybersecurity_student_receives_cybersecurity_not_other(): void
    {
        $student = $this->studentInProgramme('Cyber Security', 'B.Sc. Cyber Security');

        $service = app(StudentDashboardService::class);
        $programme = $service->curriculumProgramme($student);

        $this->assertNotNull($programme, 'Programme must resolve');
        $this->assertStringContainsString('Cyber', $programme->name, 'Cybersecurity student must receive Cybersecurity curriculum');
    }

    public function test_student_does_not_receive_an_unrelated_programme(): void
    {
        // A second, unrelated programme exists in the same curriculum. A
        // resolution that matched loosely — for example by position or by
        // picking the first active row — would hand this student the wrong one.
        $student = $this->studentInProgramme('Cyber Security', 'B.Sc. Cyber Security');

        Programme::create([
            'name' => 'B.Sc. Actuarial Science',
            'code' => 'ACTUARIAL',
            'degree_type' => 'bachelor',
            'duration_years' => 4,
            'scope' => 'national',
            'status' => 'active',
            'is_active' => true,
        ]);

        $service = app(StudentDashboardService::class);
        $programme = $service->curriculumProgramme($student);

        $this->assertNotNull($programme);
        $this->assertStringNotContainsString('Actuarial', $programme->name, 'Must not receive unrelated Actuarial Science');
    }

    public function test_mass_communication_student_receives_mass_communication(): void
    {
        $student = $this->studentInProgramme('Mass Communication', 'B.Sc. Mass Communication');

        $service = app(StudentDashboardService::class);
        $programme = $service->curriculumProgramme($student);

        $this->assertNotNull($programme);
        $this->assertStringContainsString('Mass Communication', $programme->name);
    }
}
