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

        // Chunked rather than offset/limit.
        //
        // The single-query version hit a TiDB syntax error on the full run, and
        // a join over 1,366 levels against 2,000ms-per-statement latency is a
        // timeout waiting to happen. Chunking also means a crash costs one
        // chunk of work rather than the whole run, which is exactly what
        // happened before: the run died partway and left 3 of 1,666 done with
        // no record of where it stopped.
        $count = DB::table('levels')->count();
        $this->line(sprintf('Levels to cover: %d', $count));

        if (! $apply) {
            $this->table(
                ['offerings', 'levels', 'coordinators to create'],
                [[
                    (string) DB::table('academic_programs')->count(),
                    (string) $count,
                    (string) $count,
                ]]
            );

            return self::SUCCESS;
        }

        $this->line($apply
            ? '<fg=green>APPLYING</> -- coordinators will be created'
            : '<fg=yellow>DRY RUN</> -- pass --apply to write');

        $this->table(
            ['offerings', 'levels', 'coordinators to create'],
            [[
                (string) DB::table('academic_programs')->count(),
                (string) DB::table('levels')->count(),
            ]]
        );

        if (! $apply) {
            return self::SUCCESS;
        }

        $created = 0;
        $skipped = 0;
        $errors = 0;
        $password = Str::random(16);
        $processed = 0;

        // A keyset loop rather than chunkById: the select aliases levels.id to
        // level_id, and chunkById needs the raw key column present in the result
        // to page on it. Keyset paging needs no offset and no group, so it is
        // also safe against TiDB's statement timeout on a large join.
        $lastId = 0;

        while (true) {
            $rows = DB::table('levels')
                ->select(
                    'levels.id as level_id',
                    'levels.code as level_code',
                    'academic_programs.id as offering_id',
                    'academic_programs.name as programme_name',
                    'academic_programs.code as programme_code'
                )
                ->join('academic_programs', 'academic_programs.id', '=', 'levels.academic_program_id')
                ->where('levels.id', '>', $lastId)
                ->orderBy('levels.id')
                ->limit(100)
                ->get();

            if ($rows->isEmpty()) {
                break;
            }

            foreach ($rows as $target) {
                $lastId = (int) $target->level_id;
                $processed++;
                $email = $this->emailFor($target->programme_code, $target->level_code);

                try {
                    $userId = DB::table('users')->where('email', $email)->value('id');

                    if (! $userId) {
                        $userId = DB::table('users')->insertGetId([
                            'name' => trim($target->programme_name . ' ' . $target->level_code . ' Coordinator'),
                            'email' => $email,
                            'password' => bcrypt($password),
                            'force_password_change' => false,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }

                    $exists = DB::table('role_assignments')
                        ->where('user_id', $userId)
                        ->where('role_id', $roleId)
                        ->where('entity_type', \App\Models\Curriculum\Programme::class)
                        ->where('entity_id', $target->offering_id)
                        ->exists();

                    if (! $exists) {
                        DB::table('role_assignments')->insert([
                            'user_id' => $userId,
                            'role_id' => $roleId,
                            // Scoped to the programme at one level. Never the
                            // organization, which would be the over-grant fixed
                            // in StaffController.
                            'entity_type' => \App\Models\Curriculum\Programme::class,
                            'entity_id' => $target->offering_id,
                            'scope_type' => 'level',
                            'scope_id' => (string) $target->level_id,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);

                        $created++;
                    } else {
                        $skipped++;
                    }

                    // level_coordinators stores `level` and `status`, not
                    // level_id/is_active.
                    DB::table('level_coordinators')->insertOrIgnore([
                        'user_id' => $userId,
                        'programme_id' => $target->offering_id,
                        'level' => (int) preg_replace('/\D/', '', (string) $target->level_code),
                        'status' => 'active',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                } catch (\Throwable $e) {
                    $errors++;
                }
            }
        }

        $this->line("  processed: {$processed}");

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
