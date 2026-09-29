<?php

namespace App\Services;

use App\Models\InstitutionOnboarding;
use App\Models\LevelCoordinator;
use App\Models\Organization;
use App\Models\AcademicProgram;
use App\Models\Curriculum\Programme;
use App\Models\Role;
use App\Models\RoleAssignment;
use App\Models\User;
use App\Services\ProgrammeCatalogue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * The onboarding flow the project owner specified.
 *
 * The model
 * ---------
 * 1. ACL already knows every organization. Onboarding is not a request to
 *    exist — it is the act of being given access to a body ACL already lists.
 * 2. Onboarding produces exactly one administrator credential for that
 *    organization, and hands it over out of band. ACL never emails it.
 * 3. An administrator then appoints a level coordinator for each level of each
 *    programme it offers.
 * 4. A student registering under that organization is attached to it
 *    automatically, by the organization they chose — never by a free-text
 *    institution name that has to be matched afterwards.
 *
 * Why this shape
 * --------------
 * Step 2 is a one-time transfer of authority, so the credential is generated
 * once, delivered once, and then the administrator owns their own password. An
 * organization that never onboards keeps no credential lying around waiting to
 * be used.
 *
 * Step 4 is why the organizations consolidation mattered. Previously a student
 * named a university in a text field, which ACL had to guess at; the name did
 * not determine the organization. Now the organization is the selection, and
 * assignment is a direct write rather than a match.
 */
class OrganizationOnboarding
{
    /**
     * Creates the onboarding record for an organization if it has none.
     */
    public static function recordFor(Organization $organization, ?User $invitedBy = null): InstitutionOnboarding
    {
        return InstitutionOnboarding::firstOrCreate(
            ['organization_id' => $organization->id],
            [
                'status' => 'pending',
                'invited_by' => $invitedBy?->id,
                'progress' => [],
            ]
        );
    }

    /**
     * Generates the organization's administrator account and returns the
     * credential.
     *
     * The password is returned to the caller exactly once and is never stored,
     * logged, or emailed. The account is flagged force_password_change so the
     * administrator must set their own password at first login, which is what
     * makes handing over a temporary credential safe.
     *
     * Idempotent: an organization that already has an administrator is left
     * alone, because silently issuing a second one would strand the first.
     */
    public static function issueAdministrator(
        Organization $organization,
        ?User $issuedBy = null,
        ?string $email = null,
    ): array {
        $onboarding = self::recordFor($organization, $issuedBy);

        $existing = $onboarding->administrator_user_id
            ? User::find($onboarding->administrator_user_id)
            : null;

        if ($existing) {
            return [
                'created' => false,
                'user' => $existing,
                'reason' => 'this organization already has an administrator',
            ];
        }

        $email = $email ?: self::provisionalEmail($organization);

        // A generated address, not a guessed one. ACL must never claim to have
        // verified an address it invented, and an @example.* domain cannot be
        // mistaken for a real one the organization might already own.
        $password = Str::password(20);

        $user = User::create([
            'name' => "{$organization->name} Administrator",
            'email' => $email,
            'password' => Hash::make($password),
            'institution_id' => $organization->id,
            'force_password_change' => true,
            'is_active' => true,
        ]);

        $role = Role::where('slug', 'institution.admin')->firstOrFail();

        RoleAssignment::firstOrCreate([
            'user_id' => $user->id,
            'role_id' => $role->id,
            'entity_type' => Organization::class,
            'entity_id' => $organization->id,
        ]);

        $onboarding->update([
            'status' => 'invited',
            'administrator_user_id' => $user->id,
            'invited_at' => now(),
        ]);

        $organization->update([
            'onboarding_status' => 'INVITED',
            'status' => 'active',
        ]);

        return [
            'created' => true,
            'user' => $user,
            'email' => $email,
            'password' => $password, // returned once, to the operator only
        ];
    }

    /**
     * A clearly non-real placeholder address for an organization that has not
     * supplied a real administrator email.
     */
    public static function provisionalEmail(Organization $organization): string
    {
        $slug = Str::slug($organization->slug ?: $organization->name);

        return "admin+{$slug}@provisioned.invalid";
    }

    /**
     * Appoints a level coordinator for one level of one programme.
     *
     * Scope is the programme *and* the level, which is the whole point: a
     * coordinator for 100 level of Computer Science must not reach 200 level,
     * and must not reach another programme. That is enforced by the scope on
     * the role assignment, not by a check in a view.
     */
    public static function appointLevelCoordinator(
        Organization $organization,
        Programme $programme,
        int $level,
        User $user,
        ?string $academicSessionId = null,
    ): LevelCoordinator {
        $role = Role::where('slug', 'level.coordinator')->firstOrFail();

        // The coordinator is scoped to the program/level pair, so the role
        // assignment uses the same granularity the coordinator row does.
        RoleAssignment::firstOrCreate([
            'user_id' => $user->id,
            'role_id' => $role->id,
            'entity_type' => Programme::class,
            'entity_id' => $programme->id,
            'scope_type' => 'level',
            'scope_id' => (string) $level,
        ]);

        return LevelCoordinator::updateOrCreate(
            [
                'programme_id' => $programme->id,
                'level' => $level,
                'user_id' => $user->id,
            ],
            [
                'status' => 'active',
                'academic_session_id' => $academicSessionId,
                'appointed_date' => now(),
            ]
        );
    }

    /**
     * Attaches a newly registered student to the organization they selected.
     *
     * The organization is chosen from the list, never typed, so this is a
     * direct write. There is no name matching and therefore no way for a
     * student to end up attached to the wrong university through a spelling
     * variant.
     */
    public static function attachStudent(User $user, Organization $organization, ?Programme $programme = null, ?int $departmentId = null): void
    {
        $user->forceFill([
            'institution_id' => $organization->id,
        ])->save();

        $membership = \App\Models\OrganizationMembership::firstOrCreate([
            'user_id' => $user->id,
            'organization_id' => $organization->id,
        ], [
            'membership_type' => 'student',
            'status' => 'active',
            'joined_at' => now(),
        ]);

        if ($programme) {
            // academic_program_id points at `academic_programs`, NOT at the
            // central `programmes` catalogue. A catalogue entry is the degree
            // itself; an academic_program is one organization's offering of it,
            // carrying that organization's own code, department and duration.
            //
            // Writing the catalogue id here is the obvious mistake and it is
            // silent in production, because production carries no foreign key
            // on this column while the test schema does. It has to resolve
            // through the offering.
            //
            // academic_programs.department_id is NOT NULL, so a null department
            // fails the insert outright. Resolve one belonging to this
            // organization when the caller did not name one, rather than
            // letting the database reject the registration.
            $offering = self::offeringFor(
                $organization,
                $programme,
                $departmentId ?? self::resolveDepartment($organization)
            );

            $membership->update(['academic_program_id' => $offering->id]);
        }

        if (! $user->student) {
            // acl_student_id is required and is ACL's own public identity for a
            // student, distinct from any JAMB number the student may have
            // registered with. It is issued here rather than left to a default,
            // because a student without one cannot be referred to in support,
            // grading or a transcript. Existing students are untouched.
            \App\Models\Student::create([
                'user_id' => $user->id,
                'acl_student_id' => self::issueStudentIdentifier(),
            ]);

            // The relation was already loaded and cached as null, so it has to
            // be dropped or the next read returns the stale absence.
            $user->unsetRelation('student');
        }

        // The programme is recorded on the membership, not on `students`:
        // `students` carries the student's own identity and level, while the
        // programme is the thing the student is studying *at this organization*,
        // which is exactly the scope the membership represents. A student who
        // later belongs to a second organization has a different programme
        // there, and one column on `students` could not hold both.
    }

    /**
     * Issues an ACL student identifier in the existing ACL-STU-<n>/<suffix>
     * shape, so generated identifiers are indistinguishable in format from the
     * ones already in the table.
     */
    public static function issueStudentIdentifier(): string
    {
        do {
            $identifier = 'ACL-STU-' . random_int(1000, 9999) . '/' . substr(
                (string) now()->year,
                2
            ) . 'SP';
        } while (\App\Models\Student::where('acl_student_id', $identifier)->exists());

        return $identifier;
    }

    /**
     * Resolves (creating if needed) an organization's offering of a catalogue
     * programme.
     *
     * This is the join between the two programme tables, and the reason both
     * exist. `programmes` is the national catalogue: one row for "B.Sc. Cyber
     * Security", shared by every university that teaches it. `academic_programs`
     * is one university's running of it — its own code, its own department,
     * its own duration. A student belongs to an offering, not to the catalogue,
     * which is why memberships reference the offering.
     */
    /**
     * A department belonging to the organization, creating a faculty and a
     * department if the organization has none yet.
     *
     * Scoped to the organization on purpose: a department from another
     * organization would attach a student to a faculty they do not belong to,
     * and the audit already flagged offeringFor() for not checking this.
     */
    public static function resolveDepartment(Organization $organization): ?int
    {
        $facultyId = \DB::table('faculties')
            ->where('organization_id', $organization->id)
            ->value('id');

        if (! $facultyId) {
            $slug = $organization->slug ?: \Illuminate\Support\Str::slug($organization->name);

            $facultyId = \DB::table('faculties')->insertGetId([
                'organization_id' => $organization->id,
                'name' => $organization->name . ' Faculty',
                'slug' => $slug . '-faculty',
                'code' => 'F' . $organization->id,
                'description' => 'Faculty of ' . $organization->name,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $departmentId = \DB::table('departments')
            ->where('faculty_id', $facultyId)
            ->value('id');

        if ($departmentId) {
            return (int) $departmentId;
        }

        $slug = $organization->slug ?: \Illuminate\Support\Str::slug($organization->name);

        return (int) \DB::table('departments')->insertGetId([
            'faculty_id' => $facultyId,
            'name' => $organization->name . ' General Department',
            'slug' => $slug . '-general-department',
            'code' => 'D1',
            'description' => 'General department of ' . $organization->name,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public static function offeringFor(Organization $organization, Programme $programme, ?int $departmentId = null): AcademicProgram
    {
        $existing = AcademicProgram::where('organization_id', $organization->id)
            ->where('nuc_programme_id', $programme->id)
            ->first();

        if ($existing) {
            return $existing;
        }

        return AcademicProgram::create([
            'organization_id' => $organization->id,
            'nuc_programme_id' => $programme->id,
            'department_id' => $departmentId,
            'name' => $programme->name,
            'slug' => Str::slug($organization->slug ?: $organization->name).'-'.Str::slug($programme->name),
            'code' => $programme->code,
            'degree_type' => $programme->degree_type,
            'duration_years' => $programme->duration_years,
            'is_active' => true,
        ]);
    }

    /**
     * What a completed onboarding leaves behind, for the superadmin view.
     */
    public static function summary(Organization $organization): array
    {
        $onboarding = self::recordFor($organization);

        return [
            'onboarding' => $onboarding,
            'status' => $onboarding->status,
            'percentage' => $onboarding->getCompletionPercentage(),
            'administrator' => $onboarding->administrator,
            'programmes_offered' => DB::table('academic_programs')
                ->where('organization_id', $organization->id)
                ->count(),
            'coordinators' => LevelCoordinator::whereIn('programme_id', function ($q) use ($organization) {
                $q->select('nuc_programme_id')
                    ->from('academic_programs')
                    ->where('organization_id', $organization->id)
                    ->whereNotNull('nuc_programme_id');
            })->count(),
            'students' => \App\Models\OrganizationMembership::where('organization_id', $organization->id)
                ->where('membership_type', 'student')
                ->count(),
        ];
    }
}
