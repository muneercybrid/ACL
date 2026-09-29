<?php

namespace App\Console\Commands;

use App\Models\Curriculum\Programme;
use App\Models\Organization;
use App\Models\User;
use App\Services\OrganizationOnboarding;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Seeds faculties, departments, programme offerings, levels, and one test
 * student per programme per level.
 *
 * The academic structure tables were all empty, so nothing could resolve
 * "level 200 of a programme" -- which blocked the student matrix, the level
 * coordinators, and the per-level test accounts.
 *
 * Students are spread across organizations rather than all attached to one, so
 * testing an account also exercises organization scoping.
 */
class SeedTestAccounts extends Command
{
    protected $signature = 'acl:seed:test-accounts
        {--apply : Write. Without it, only report.}
        {--levels=100 : Levels to seed}
        {--per-programme=2 : Students per programme per level}
        {--departments=1 : Departments per organization}
        {--chunk=25 : Rows per insert batch}
        {--only-students : Skip structure seeding; cheaper once it exists.}';

    protected $description = 'Seed the academic structure and the test student matrix';

    public function handle(): int
    {
        $apply = $this->option('apply');
        $levels = array_map('intval', explode(',', (string) $this->option('levels')));
        $perProgramme = max(1, (int) $this->option('per-programme'));
        $departmentsPerOrg = max(1, (int) $this->option('departments'));

        $this->line($apply
            ? '<fg=green>APPLYING</> -- rows will be written'
            : '<fg=yellow>DRY RUN</> -- pass --apply to write');

        $programmes = Programme::where('status', 'active')->orderBy('id')->get();
        $organizations = Organization::orderBy('id')->get();

        $this->table(
            ['programmes', 'levels', 'organizations', 'departments/org', 'students'],
            [[
                (string) $programmes->count(),
                (string) count($levels),
                (string) $organizations->count(),
                (string) $departmentsPerOrg,
                (string) ($programmes->count() * count($levels) * $perProgramme),
            ]]
        );

        if (! $apply) {
            $this->line('Dry run complete. Re-run with <info>--apply</info> to write.');

            return self::SUCCESS;
        }

        $onlyStudents = (bool) $this->option('only-students');

        // The organizations were dealt round-robin in index order, so pairing
        // programme n with organization n mod count reproduces the same
        // assignment. Reading the offerings back avoids re-issuing a query per
        // programme when the structure is already in place.
        $offerings = $onlyStudents
            ? $this->loadOfferings()
            : $this->seedOfferings($programmes, $organizations, $this->seedDepartments($organizations, $departmentsPerOrg));

        if (! $onlyStudents) {
            $this->seedLevels($offerings, $levels);
        }

        $students = $this->seedStudents($programmes, $organizations, $levels, $offerings, $perProgramme);

        $this->newLine();
        $this->info('faculties   : ' . DB::table('faculties')->count());
        $this->info('departments : ' . DB::table('departments')->count());
        $this->info('offerings   : ' . DB::table('academic_programs')->count());
        $this->info('levels      : ' . DB::table('levels')->count());
        $this->info('students    : ' . $students);
        $this->info('users       : ' . DB::table('users')->count());

        return self::SUCCESS;
    }

    /**
     * academic_programs.department_id is NOT NULL, so an offering cannot exist
     * without a department. faculties and departments were both empty.
     *
     * @return array<int, int> organization id => a usable department id
     */
    private function seedDepartments($organizations, int $perOrg): array
    {
        $map = [];

        foreach ($organizations as $organization) {
            $slug = $organization->slug ?: Str::slug($organization->name);

            $facultyId = DB::table('faculties')
                ->where('organization_id', $organization->id)
                ->value('id');

            if (! $facultyId) {
                $facultyId = DB::table('faculties')->insertGetId([
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

            for ($i = 1; $i <= $perOrg; $i++) {
                $code = 'D' . $i;
                $existing = DB::table('departments')
                    ->where('faculty_id', $facultyId)
                    ->where('code', $code)
                    ->value('id');

                if ($existing) {
                    $map[$organization->id] ??= $existing;
                    continue;
                }

                $id = DB::table('departments')->insertGetId([
                    'faculty_id' => $facultyId,
                    'name' => $organization->name . ' Department ' . $i,
                    'slug' => $slug . '-dept-' . $i,
                    'code' => $code,
                    'description' => 'Department ' . $i . ' of ' . $organization->name,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $map[$organization->id] ??= $id;
            }
        }

        return $map;
    }

    /**
     * An offering links an organization to a catalogue programme. Organizations
     * are dealt round-robin so every university ends up offering something,
     * rather than one organization holding every programme.
     */
    private function seedOfferings($programmes, $organizations, array $departmentIds): array
    {
        $offerings = [];
        $index = 0;

        foreach ($programmes as $programme) {
            $organization = $organizations[$index % $organizations->count()];
            $index++;

            $departmentId = $departmentIds[$organization->id] ?? null;

            if ($departmentId === null) {
                $this->warn('  no department for ' . $organization->name . ', skipping ' . $programme->name);
                continue;
            }

            try {
                $offering = OrganizationOnboarding::offeringFor($organization, $programme, $departmentId);
                $offerings[$programme->id] = [
                    'organization_id' => $organization->id,
                    'offering_id' => $offering->id,
                ];
            } catch (\Throwable $e) {
                $this->warn('  offering failed for ' . $programme->name . ': ' . $e->getMessage());
            }
        }

        return $offerings;
    }

    /**
     * Levels hang off an offering (levels.academic_program_id), because a
     * programme offered at two universities is two offerings and each has its
     * own level rows. They cannot be seeded before the offerings exist.
     */
    private function seedLevels(array $offerings, array $levels): void
    {
        $rows = [];

        foreach ($offerings as $offering) {
            foreach ($levels as $level) {
                $existing = DB::table('levels')
                    ->where('academic_program_id', $offering['offering_id'])
                    ->where('name', $this->levelName($level))
                    ->value('id');

                if ($existing) {
                    continue;
                }

                $rows[] = [
                    'academic_program_id' => $offering['offering_id'],
                    'name' => $this->levelName($level),
                    'code' => 'L' . $level,
                    'sequence' => (int) ($level / 100),
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        foreach (array_chunk($rows, 200) as $batch) {
            DB::table('levels')->insert($batch);
        }
    }

    /**
     * One student per programme per level, attached to a different organization
     * each time so accounts are scattered across the register.
     */
    private function seedStudents($programmes, $organizations, array $levels, array $offerings, int $perProgramme): int
    {
        $created = 0;
        $index = 0;
        $password = 'testaccount';

        // Only level 100, 2 students per programme per university: male + female
        $onlyLevel = [100];

        foreach ($programmes as $programme) {
            if (! isset($offerings[$programme->id])) {
                continue;
            }

            $slug = $this->programmeSlug($programme->name);

            foreach ($onlyLevel as $level) {
                // 1 male + 1 female per programme at level 100
                $names = [
                    $programme->name . ' ' . $level . ' Male Student',
                    $programme->name . ' ' . $level . ' Female Student',
                ];

                for ($n = 0; $n < 2; $n++) {
                    $organization = $organizations[$index % $organizations->count()];
                    $index++;

                    $suffix = $n === 0 ? 'testmale' : 'testfemale';
                    $email = "{$slug}{$suffix}@aclacademy.me";

                    if (User::where('email', $email)->exists()) {
                        continue;
                    }

                    try {
                        $user = new User([
                            'name' => $names[$n],
                            'email' => $email,
                            'password' => bcrypt($password),
                        ]);
                        $user->force_password_change = false;
                        $user->save();

                        OrganizationOnboarding::attachStudent($user, $organization, $programme);

                        DB::table('students')->where('user_id', $user->id)
                            ->update(['level' => $level]);

                        $levelRow = DB::table('levels')
                            ->where('academic_program_id', $offerings[$programme->id]['offering_id'])
                            ->where('name', $this->levelName($level))
                            ->first();

                        if ($levelRow) {
                            DB::table('organization_memberships')
                                ->where('user_id', $user->id)
                                ->update(['current_level_id' => $levelRow->id]);
                        }

                        $created++;
                    } catch (\Throwable $e) {
                        $this->warn('  student failed for ' . $email . ': ' . $e->getMessage());
                    }
                }
            }
        }

        $this->line("  shared test password: {$password}");
        return $created;
    }

    /**
     * @return array<int, array{organization_id: int, offering_id: int}>
     */
    private function loadOfferings(): array
    {
        $rows = DB::table('academic_programs')
            ->whereNotNull('nuc_programme_id')
            ->get(['nuc_programme_id', 'organization_id', 'id']);

        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row->nuc_programme_id] ??= [
                'organization_id' => (int) $row->organization_id,
                'offering_id' => (int) $row->id,
            ];
        }

        return $map;
    }

    private function levelName(int $level): string
    {
        return match (true) {
            $level === 100 => 'First Year',
            $level === 200 => 'Second Year',
            $level === 300 => 'Third Year',
            $level === 400 => 'Fourth Year',
            $level === 500 => 'Fifth Year',
            $level === 600 => 'Sixth Year',
            $level === 700 => 'Postgraduate Year',
            default => 'Level ' . $level,
        };
    }

    /**
     * "B.Sc. Medicine and Surgery" -> "medicine", so the address reads
     * medicinelvl100@aclacademy.me and the programme is identifiable from the
     * address alone.
     */
    private function programmeSlug(string $name): string
    {
        $name = preg_replace(
            '/\b(B\.?Sc\.?|M\.?Sc\.?|B\.?A\.?|M\.?B\.?A\.?|B\.?Eng\.?|M\.?Eng\.?|B\.?Pharm\.?|D\.?V\.?M\.?|LL\.?B\.?|B\.?NSc\.?|B\.?MLS\.?|B\.?Agric\.?|Ph\.?D\.?|M\.?Phil\.?|M\.?Tech\.?|B\.?Ed\.?|M\.?Ed\.?)\.?/iu',
            '',
            $name
        ) ?? $name;

        $name = str_replace(['&', 'and', '/', '-', ',', '(', ')'], ' ', $name);
        $words = preg_split('/\s+/', trim($name)) ?: [];
        $slug = mb_strtolower(implode('', array_slice($words, 0, 2)));
        $slug = preg_replace('/[^a-z0-9]/', '', $slug) ?? '';

        return $slug !== '' ? $slug : 'programme';
    }
}
