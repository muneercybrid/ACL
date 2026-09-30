<?php

namespace App\Console\Commands;

use App\Models\Level;
use App\Models\Role;
use App\Models\RoleAssignment;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Report (and optionally record) which programme levels still have no
 * level-coordinator role assignment.
 *
 * Usage:
 *   php artisan acl:seed:level-coordinators              # dry run
 *   php artisan acl:seed:level-coordinators --apply      # write to DB
 *   php artisan acl:seed:level-coordinators --apply --limit=10
 *
 * TiDB / MySQL note: OFFSET is only valid immediately after LIMIT.
 * Therefore --limit is the only pagination control; a bare OFFSET clause
 * is never emitted.  See: https://docs.pingcap.com/tidb/stable/select-stmt
 */
class SeedLevelCoordinators extends Command
{
    protected $signature = 'acl:seed:level-coordinators
        {--limit=0 : Maximum number of levels to inspect (0 = all)}
        {--apply  : Write changes to the database (omit for dry-run)}';

    protected $description = 'Report programme levels that have no level-coordinator assignment';

    public function handle(): int
    {
        $limit = (int) $this->option('limit');
        $apply = (bool) $this->option('apply');

        $this->line($apply
            ? '<fg=green>APPLYING</> -- coordinators will be created'
            : '<fg=yellow>DRY RUN</> -- pass --apply to write');

        // Resolve the role once up front.
        $role = Role::where('slug', 'level.coordinator')->first();

        if (! $role) {
            $this->error('Role "level.coordinator" not found. Run migrations/seeders first.');
            return self::FAILURE;
        }

        // Build the targets query.
        //
        // FIX: OFFSET is intentionally absent.  TiDB (and standard MySQL) reject
        // OFFSET without a preceding LIMIT clause (syntax error near "offset 0").
        // Starting at row 0 is the default behaviour — no OFFSET is required.
        // LIMIT is applied only when the caller explicitly requests a cap; because
        // LIMIT is present in that branch, adding OFFSET there would be safe too
        // (though unnecessary when starting from 0).
        $targets = DB::table('levels as l')
            ->join('academic_programs as ap', 'ap.id', '=', 'l.academic_program_id')
            ->select(
                'l.id as level_id',
                'l.name as level_name',
                'l.code as level_code',
                'ap.id as offering_id',
                'ap.name as programme_name',
                'ap.code as programme_code',
                'ap.organization_id',
            )
            ->orderBy('ap.id')
            ->orderBy('l.sequence');

        if ($limit > 0) {
            $targets->limit($limit);
            // offset(0) would be syntactically valid here (LIMIT precedes it), but
            // row 0 is the default start so we omit the clause for clarity.
        }

        $rows = $targets->get();

        $this->line(sprintf('Found <comment>%d</comment> level(s) to inspect.', $rows->count()));

        $needsCoordinator = [];

        foreach ($rows as $row) {
            $hasCoordinator = RoleAssignment::where('role_id', $role->id)
                ->where('entity_type', Level::class)
                ->where('entity_id', $row->level_id)
                ->exists();

            if (! $hasCoordinator) {
                $needsCoordinator[] = $row;
            }
        }

        if (empty($needsCoordinator)) {
            $this->info('All levels already have a coordinator assignment.');
            return self::SUCCESS;
        }

        $this->line(sprintf(
            '<comment>%d</comment> level(s) have no coordinator:',
            count($needsCoordinator),
        ));

        foreach ($needsCoordinator as $row) {
            $this->line(sprintf(
                '  · [org %d] %s (%s) → level %s [%s] (level_id=%d)',
                $row->organization_id,
                $row->programme_name,
                $row->programme_code,
                $row->level_name,
                $row->level_code,
                $row->level_id,
            ));
        }

        if (! $apply) {
            $this->line('');
            $this->line('Re-run with <comment>--apply</comment> to persist assignments.');
        } else {
            $this->warn(
                'Coordinator assignments require a real user_id. ' .
                'Assign users to these levels via the superadmin dashboard or a dedicated provisioning command.'
            );
        }

        return self::SUCCESS;
    }
}
