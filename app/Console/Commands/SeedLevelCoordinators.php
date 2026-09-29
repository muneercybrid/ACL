<?php

namespace App\Console\Commands;

use App\Services\OrganizationOnboarding;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Creates a level coordinator for every level of every offered programme.
 *
 * Scope: a coordinator is written against the Programme entity with
 * scope_type='level' and scope_id set to the level. This is deliberate and is
 * the whole point of the account type. Writing the assignment against the
 * Organization instead would grant authority over every programme and level in
 * the university, which is the over-grant fixed in StaffController.
 *
 * One coordinator per programme-per-level nationally, not one per university:
 * 481 organizations x 238 programmes x 7 levels is roughly 800,000 accounts.
 * Level structure is a national property of a programme, so a national
 * coordinator per level covers every combination that a university can offer
 * while staying a number a database can actually carry.
 */
class SeedLevelCoordinators extends Command
{
    protected $signature = 'acl:seed:level-coordinators
        {--apply : Write. Without it, only report.}
        {--limit=0 : Maximum coordinators to create; 0 means all}
        {--offset=0 : Skip this many, for running in slices}';

    protected $description = 'Create a level coordinator for each level of each offered programme';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $limit = max(0, (int) $this->option('limit'));
        $offset = max(0, (int) $this->option('offset'));

        $roleId = DB::table('roles')->where('slug', 'level.coordinator')->value('id');

        if (! $roleId) {
            $this->error('role level.coordinator not found');

            return self::FAILURE;
        }

        // One row per (offering, level). The offering is the university's
        // version of a programme, so this yields every university/level pair.
        $targets = DB::table('levels as l')
            ->join('academic_programs as ap', 'ap.id', '=', 'l.academic_program_id')
            ->select('l.id as level_id', 'l.name as level_name', 'l.code as level_code',
                'ap.id as offering_id', 'ap.name as programme_name', 'ap.code as programme_code',
                'ap.organization_id')
            ->orderBy('ap.id')
            ->orderBy('l.sequence')
            ->offset($offset);

        if ($limit > 0) {
            $targets->limit($limit);
        }

        $targets = $targets->get();

        $this->line($apply
            ? '<fg=green>APPLYING</> -- coordinators will be created'
            : '<fg=yellow>DRY RUN</> -- pass --apply to write');

        $this->table(
            ['offerings', 'levels', 'coordinators to create'],
            [[
                (string) DB::table('academic_programs')->count(),
                (string) DB::table('levels')->count(),
                (string) $targets->count(),
            ]]
        );

        if (! $apply) {
            return self::SUCCESS;
        }

        $created = 0;
        $skipped = 0;
        $password = Str::random(16);
        $errors = 0;

        foreach ($targets as $target) {
            $email = $this->emailFor($target->programme_code, $target->level_code);

            $exists = DB::table('role_assignments')
                ->where('user_id', DB::table('users')->where('email', $email)->value('id'))
                ->where('role_id', $roleId)
                ->exists();

            if ($exists) {
                $skipped++;
                continue;
            }

            try {
                $userId = DB::table('users')->where('email', $email)->value('id');

                if (! $userId) {
                    $userId = DB::table('users')->insertGetId([
                        'name' => trim($target->programme_name . ' ' . $target->level_name . ' Coordinator'),
                        'email' => $email,
                        'password' => bcrypt($password),
                        'force_password_change' => false,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                DB::table('role_assignments')->insert([
                    'user_id' => $userId,
                    'role_id' => $roleId,
                    // Scoped to the programme, not the organization: a level
                    // coordinator's authority is one programme at one level.
                    'entity_type' => \App\Models\Curriculum\Programme::class,
                    'entity_id' => $target->offering_id,
                    'scope_type' => 'level',
                    'scope_id' => (string) $target->level_id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                // level_coordinators has `level` (the numeric level, e.g. 100)
                // and `status`, not level_id/is_active.
                DB::table('level_coordinators')->insertOrIgnore([
                    'user_id' => $userId,
                    'programme_id' => $target->offering_id,
                    'level' => (int) preg_replace('/\D/', '', (string) $target->level_code),
                    'status' => 'active',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $created++;
            } catch (\Throwable $e) {
                $errors++;
                $this->warn('  ' . $email . ': ' . $e->getMessage());
            }
        }

        $this->newLine();
        $this->info('created  : ' . $created);
        $this->info('skipped  : ' . $skipped);
        $this->info('errors   : ' . $errors);
        $this->line("  shared test password: {$password}");

        return self::SUCCESS;
    }

    /**
     * acc101lvl100coordinator@aclacademy.me — the same addressing the student
     * matrix uses, so a coordinator is identifiable by eye.
     */
    private function emailFor(?string $programmeCode, ?string $levelCode): string
    {
        $programme = Str::lower(preg_replace('/[^A-Za-z0-9]/', '', (string) $programmeCode) ?: 'programme');
        $level = Str::lower(preg_replace('/[^A-Za-z0-9]/', '', (string) $levelCode) ?: 'level');

        return "{$programme}{$level}coordinator@aclacademy.me";
    }
}
