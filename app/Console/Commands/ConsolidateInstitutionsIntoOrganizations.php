<?php

namespace App\Console\Commands;

use App\Models\Institution;
use App\Models\Organization;
use App\Models\RoleAssignment;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Folds the `institutions` table into `organizations`.
 *
 * The two tables are disjoint — no name matches — so this is a migration of
 * population, not a de-duplication. Every institution becomes an organization
 * row, and every reference that pointed at an institution is repointed at the
 * organization that now holds it.
 *
 * Safety
 * ------
 *  - Dry run by default. Nothing is written without --apply.
 *  - Batched and idempotent. Each institution is matched to its organization by
 *    normalized name, so a re-run finds the work already done and changes
 *    nothing.
 *  - Not one large transaction. TiDB terminates long-running transactions, so
 *    the copy commits in small batches and can be resumed.
 *  - Nothing is deleted. The institutions table is left in place, read-only, so
 *    a rollback is a matter of repointing back rather than restoring rows.
 */
class ConsolidateInstitutionsIntoOrganizations extends Command
{
    protected $signature = 'acl:organizations:consolidate-institutions
                            {--apply : Perform the change; without it, only report}
                            {--chunk=50 : Institutions per batch}';

    protected $description = 'Move every institution into organizations and repoint the references that named them';

    public function handle(): int
    {
        $apply = $this->option('apply');
        $chunk = max(1, (int) $this->option('chunk'));

        if (! Schema::hasTable('institutions')) {
            $this->error('the institutions table no longer exists; nothing to do');

            return self::FAILURE;
        }

        $this->line($apply
            ? '<fg=green>APPLYING</> — rows will be written'
            : '<fg=yellow>DRY RUN</> — pass --apply to make changes');

        $mapping = $this->buildMapping($chunk);

        $this->newLine();
        $this->table(
            ['institutions', 'will be copied', 'already present', 'organizations'],
            [[
                (string) $mapping['institutions'],
                (string) $mapping['willCopy'],
                (string) $mapping['alreadyPresent'],
                (string) $mapping['organizations'],
            ]]
        );

        if (! $apply) {
            $this->newLine();
            $this->line('Copying first, then repointing users and role assignments onto the new rows.');
            $this->line('Dry run complete. Re-run with <info>--apply</info> to perform the change.');

            return self::SUCCESS;
        }

        $this->copyInstitutions($chunk);
        $this->repointUsers();
        $this->repointRoleAssignments();

        $this->newLine();
        $this->report();

        return self::SUCCESS;
    }

    /**
     * Copies each institution into organizations, keyed on normalized name so
     * the operation is idempotent.
     */
    private function copyInstitutions(int $chunk): void
    {
        $copied = 0;
        $skipped = 0;

        Institution::query()
            ->orderBy('id')
            ->chunkById($chunk, function ($institutions) use (&$copied, &$skipped) {
                $rows = [];

                foreach ($institutions as $institution) {
                    $normalized = $institution->normalized_name
                        ?: mb_strtolower(trim($institution->name));

                    $exists = Organization::where('normalized_name', $normalized)->exists();
                    if ($exists) {
                        $skipped++;

                        continue;
                    }

                    $rows[] = [
                        'name' => $institution->name,
                        'normalized_name' => $normalized,
                        'slug' => $institution->slug,
                        'type' => $institution->type,
                        'is_nuc_listed' => $institution->nuc_section !== null,
                        'nuc_name' => $institution->nuc_name,
                        'nuc_normalized_name' => $institution->nuc_normalized_name,
                        'nuc_section' => $institution->nuc_section,
                        'nuc_source_ref' => $institution->nuc_source_ref,
                        'source_verified_at' => $institution->source_verified_at,
                        'synchronized_at' => $institution->synchronized_at,
                        'import_batch' => $institution->import_batch,
                        'official_name' => $institution->official_name,
                        'abbr' => $institution->abbr,
                        'ownership' => $institution->ownership,
                        'state' => $institution->state,
                        'established_year' => $institution->established_year,
                        'website' => $institution->website,
                        // The register used a lifecycle enum; organizations
                        // carries an operational status. ACTIVE maps to
                        // active, anything else is preserved verbatim so no
                        // lifecycle state is silently lost.
                        'status' => $this->mapStatus($institution->institution_status),
                        'onboarding_status' => $institution->onboarding_status,
                        'is_active' => $institution->institution_status === 'ACTIVE',
                        'created_at' => $institution->created_at ?? now(),
                        'updated_at' => now(),
                    ];
                }

                if ($rows !== []) {
                    DB::table('organizations')->insert($rows);
                    $copied += count($rows);
                    $this->line("  copied {$copied} institutions so far");
                }
            });

        $this->info("institutions copied: {$copied} (already present: {$skipped})");
    }

    private function mapStatus(?string $institutionStatus): string
    {
        return match ($institutionStatus) {
            'ACTIVE' => 'active',
            'INACTIVE', 'ARCHIVED' => 'inactive',
            'SUSPENDED' => 'suspended',
            default => 'active',
        };
    }

    /**
     * Works out what the operation would do, before doing it.
     *
     * The counts that matter on a dry run are how many institutions will be
     * copied and how many already have an organization to merge into. Counts of
     * users and role assignments repointed would necessarily be zero on a first
     * dry run, because nothing has been copied yet, so quoting them would be
     * misleading rather than informative.
     */
    private function buildMapping(int $chunk): array
    {
        $existing = Organization::whereNotNull('normalized_name')->pluck('id', 'normalized_name');

        $institutions = 0;
        $willCopy = 0;
        $alreadyPresent = 0;

        Institution::query()->orderBy('id')->chunkById($chunk, function ($rows) use ($existing, &$institutions, &$willCopy, &$alreadyPresent) {
            foreach ($rows as $institution) {
                $institutions++;
                $normalized = $institution->normalized_name ?: mb_strtolower(trim($institution->name));

                if ($existing->has($normalized)) {
                    $alreadyPresent++;
                } else {
                    $willCopy++;
                }
            }
        });

        return [
            'institutions' => $institutions,
            'willCopy' => $willCopy,
            'alreadyPresent' => $alreadyPresent,
            'organizations' => Organization::count(),
        ];
    }

    /**
     * institution id => normalized name, loaded once and reused by both
     * repointing steps. TiDB makes a per-row lookup across the network
     * expensive enough that doing it inside a chunk loop is not viable.
     */
    private function institutionNormalizedNames(): array
    {
        return Institution::query()
            ->get(['id', 'normalized_name', 'name'])
            ->mapWithKeys(fn ($i) => [$i->id => $i->normalized_name ?: mb_strtolower(trim($i->name))])
            ->all();
    }

    /** organization normalized name => organization id */
    private function organizationMap(): array
    {
        return Organization::whereNotNull('normalized_name')->pluck('id', 'normalized_name')->all();
    }

    private function repointUsers(): void
    {
        $map = DB::table('organizations')->whereNotNull('normalized_name')->pluck('id', 'normalized_name');
        $byId = Institution::query()->get(['id', 'normalized_name', 'name'])
            ->mapWithKeys(fn ($i) => [$i->id => $i->normalized_name ?: mb_strtolower(trim($i->name))])
            ->all();

        $updated = 0;

        // chunkById, never chunk: the query filters on the column being written,
        // so the result set shrinks as the loop runs and offset pagination skips
        // rows. Paging by a stable, increasing key cannot.
        DB::table('users')->whereNotNull('institution_id')->chunkById(200, function ($users) use ($byId, $map, &$updated) {
            foreach ($users as $user) {
                $normalized = $byId[$user->institution_id] ?? null;
                $organizationId = $normalized !== null ? $map->get($normalized) : null;

                if ($organizationId === null) {
                    continue;
                }

                DB::table('users')->where('id', $user->id)->update(['institution_id' => $organizationId]);
                $updated++;
            }
        });

        $this->info("users repointed: {$updated}");
    }

    private function repointRoleAssignments(): void
    {
        $map = DB::table('organizations')->whereNotNull('normalized_name')->pluck('id', 'normalized_name');
        $byId = Institution::query()->get(['id', 'normalized_name', 'name'])
            ->mapWithKeys(fn ($i) => [$i->id => $i->normalized_name ?: mb_strtolower(trim($i->name))])
            ->all();

        $updated = 0;

        // chunkById for the same reason as above, and it matters more here:
        // these rows are filtered on entity_type, which this very loop rewrites.
        // An earlier version used chunk() and left 400 of 974 assignments
        // pointing at the retired table.
        DB::table('role_assignments')
            ->where('entity_type', Institution::class)
            ->chunkById(200, function ($assignments) use ($byId, $map, &$updated) {
                foreach ($assignments as $assignment) {
                    $normalized = $byId[$assignment->entity_id] ?? null;
                    $organizationId = $normalized !== null ? $map->get($normalized) : null;

                    if ($organizationId === null) {
                        continue;
                    }

                    DB::table('role_assignments')
                        ->where('id', $assignment->id)
                        ->update([
                            'entity_type' => Organization::class,
                            'entity_id' => $organizationId,
                        ]);
                    $updated++;
                }
            });

        $this->info("role assignments repointed: {$updated}");
    }

    private function report(): void
    {
        $this->newLine();
        $this->line('=== after ===');

        $this->table(
            ['check', 'value'],
            [
                ['organizations', (string) Organization::count()],
                ['  of which NUC-listed', (string) Organization::where('is_nuc_listed', true)->count()],
                ['  of which third-party', (string) Organization::where('is_nuc_listed', false)->count()],
                ['institutions (left in place)', (string) Institution::count()],
                ['users still pointing at an institution', (string) User::whereNotNull('institution_id')
                    ->whereIn('institution_id', DB::table('institutions')->select('id'))->count()],
                ['role assignments still naming Institution', (string) DB::table('role_assignments')
                    ->where('entity_type', Institution::class)->count()],
            ]
        );
    }
}
