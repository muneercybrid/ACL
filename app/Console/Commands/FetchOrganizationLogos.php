<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Organization;
use App\Services\Organization\OrganizationLogoFetcher;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Promise\EachPromise;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Downloads each organization's official logo from its own website.
 *
 * The logos exist so a staff login page can show the institution a member
 * actually belongs to, rather than a generic placeholder. That means the
 * image has to be the real mark: a favicon, a sprite sheet or a social icon
 * bar stored as a logo is worse than no logo at all, because a login page
 * will happily render a 32px square at 64px and look broken.
 *
 * Safety properties this command is built around:
 *
 *   Dry run by default  Writing to `organizations.logo_path` and to the web
 *                       root is opt-in via --apply, matching the existing
 *                       acl:institutions:reconcile convention in this repo.
 *                       Running it twice with no --apply changes nothing.
 *   Bounded politeness  Every request carries a real User-Agent, a per-host
 *                       delay, a hard timeout and a response size cap. No
 *                       host ever has more than one request in flight.
 *   Bytes, not headers  A logo is only stored when its leading bytes are a
 *                       real png/jpg/webp/gif/svg of plausible size. The
 *                       Content-Type is recorded but never trusted.
 *   No single point of  One dead university server ends one organization, not
 *   failure             the run. Nothing here throws out of handle().
 *   Auditable           Every organization lands in a JSON report with a
 *                       reason, so the failures can be researched by hand.
 *
 * Usage:
 *   php artisan acl:fetch-organization-logos                       # dry run
 *   php artisan acl:fetch-organization-logos --dry-run --limit=5    # dry run, 5 orgs
 *   php artisan acl:fetch-organization-logos --apply --limit=5      # write, 5 orgs
 *   php artisan acl:fetch-organization-logos --slug=futa --apply    # one org
 */
class FetchOrganizationLogos extends Command
{
    protected $signature = 'acl:fetch-organization-logos
        {--apply : Actually write files and update logo_path. Without this the command is a dry run.}
        {--dry-run : Explicitly report only. This is already the default; the flag exists so scripts can be explicit.}
        {--limit= : Process at most this many organizations.}
        {--id= : Process a single organization by primary key.}
        {--slug= : Process a single organization by slug.}
        {--force : Re-fetch organizations that already have a logo_path.}
        {--concurrency=5 : How many organizations to work on at once.}
        {--delay=1.5 : Minimum seconds between two requests to the same host.}
        {--timeout=15 : Hard per-request timeout in seconds.}
        {--ignore-robots : Do not fetch or honour robots.txt.}
        {--allow-invalid-tls : Accept expired or mismatched certificates instead of failing.}
        {--report= : Where to write the JSON report. Defaults to storage/logs/organization-logos.json.}';

    protected $description = 'Download each organization\'s official logo from its website (dry run unless --apply)';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $force = (bool) $this->option('force');
        $concurrency = max(1, min(32, (int) $this->option('concurrency')));
        $hostDelay = max(0.0, (float) $this->option('delay'));
        $timeout = max(1, (int) $this->option('timeout'));

        if ($apply && $this->option('dry-run')) {
            $this->warn('--apply and --dry-run were both passed; writing because --apply wins.');
        }

        $organizations = $this->selectOrganizations();

        if ($organizations->isEmpty()) {
            $this->components->error('No organizations matched the selection.');

            return self::FAILURE;
        }

        if (! $this->preflight($apply)) {
            return self::FAILURE;
        }
        $this->components->info(sprintf(
            '%s %d organization(s); concurrency=%d, host delay=%.1fs, timeout=%ds',
            $apply ? 'Processing' : 'Dry-run for',
            $organizations->count(),
            $concurrency,
            $hostDelay,
            $timeout,
        ));

        if (! $apply) {
            $this->components->warn('DRY RUN — no file is written and logo_path is not updated. Pass --apply to write.');
        }

        $fetcher = new OrganizationLogoFetcher(
            timeout: $timeout,
            connectTimeout: min(8, $timeout),
            hostDelay: $hostDelay,
            verifyTls: ! (bool) $this->option('allow-invalid-tls'),
            respectRobots: ! (bool) $this->option('ignore-robots'),
            // A dry run must write nothing at all — not the database, and not
            // a stray file left behind in the web root.
            write: $apply,
        );

        $results = $this->processAll($organizations, $fetcher, $concurrency, $apply, $force);
        $summary = $this->summarise($results);

        $this->writeReport($results, $summary, $apply, $concurrency, $hostDelay, $timeout);
        $this->render($results, $summary, $apply);

        // A per-organization failure is an expected outcome of crawling 189
        // independent third-party servers, not a failure of the command. The
        // report carries the detail; a non-zero exit would make the run look
        // broken in cron and hide a genuinely broken run.
        return self::SUCCESS;
    }

    // ---------------------------------------------------------------------
    // Selection
    // ---------------------------------------------------------------------

    /** @return Collection<int, Organization> */
    private function selectOrganizations(): Collection
    {
        $id = $this->option('id');
        $slug = $this->option('slug');
        $limit = $this->option('limit');
        $force = (bool) $this->option('force');

        $query = Organization::query()->orderBy('id');

        if ($id !== null && $id !== '') {
            $query->whereKey((int) $id);
        }

        if ($slug !== null && $slug !== '') {
            $query->where('slug', $slug);
        }

        if (! $force) {
            $query->where(function ($q): void {
                $q->whereNull('logo_path')->orWhere('logo_path', '');
            });
        }

        if ($limit !== null && $limit !== '') {
            $query->limit(max(1, (int) $limit));
        }

        return $query->get(['id', 'name', 'slug', 'website', 'logo_path']);
    }

    /**
     * Fail loudly and early if the destination cannot be written.
     *
     * Discovering this on organization 140 of 189 would leave a half-finished
     * run and a report full of misleading store_failed rows.
     */
    private function preflight(bool $apply): bool
    {
        $disk = Storage::disk(OrganizationLogoFetcher::DISK);
        $root = $disk->path(OrganizationLogoFetcher::DIRECTORY);

        if (! is_dir($root)) {
            $this->components->warn(sprintf(
                'Logo directory %s does not exist yet; the command will create it.',
                OrganizationLogoFetcher::DIRECTORY,
            ));

            return true;
        }

        if (! is_writable($root)) {
            $this->components->error(sprintf(
                'Logo directory %s is not writable by %s. Every fetch would fail.%s  Fix with:%s    sudo chown ubuntu:www-data %s && sudo chmod 2775 %s',
                $root,
                get_current_user(),
                PHP_EOL,
                PHP_EOL,
                $root,
                $root,
            ));

            return false;
        }

        if ($apply) {
            // Illuminate\Console\View\Components\Task::render() is declared
            // `@return void`, so components->task() hands back null. The
            // verdict has to be computed here or the command exits 1 with no
            // explanation at all.
            $this->components->task(
                'Writable: '.$root,
                fn (): bool => is_dir($root) && is_writable($root),
            );
        }

        return true;
    }

    // ---------------------------------------------------------------------
    // Execution
    // ---------------------------------------------------------------------

    /**
     * Drive the fetches with bounded parallelism.
     *
     * Concurrency is bounded at the *organization* level, and organizations
     * are grouped by host first, so two organizations sharing a website can
     * never be in flight together. That grouping is what turns the per-host
     * delay from a best-effort timer into a guarantee.
     *
     * @param  \Illuminate\Support\Collection<int, Organization>  $organizations
     * @return array<int, array<string, mixed>>
     */
    private function processAll(
        Collection $organizations,
        OrganizationLogoFetcher $fetcher,
        int $concurrency,
        bool $apply,
        bool $force,
    ): array {
        $byHost = [];

        foreach ($organizations as $organization) {
            $home = OrganizationLogoFetcher::normalizeWebsiteUrl($organization->website) ?? 'none:'.$organization->id;
            $byHost[$home][] = $organization;
        }

        $groups = array_values($byHost);

        $results = [];
        $processed = 0;
        $total = $organizations->count();

        $record = function (array $row) use (&$results, &$processed, $total): void {
            $results[] = $row;
            $processed++;
            $this->renderProgress($row, $processed, $total);
        };

        // A generator, so the pool only pulls the next host group when a slot
        // actually frees, rather than materialising every closure up front.
        $work = (function () use ($groups, $fetcher, $apply, $force, $record): \Generator {
            foreach ($groups as $group) {
                yield $this->processGroup($group, $fetcher, $apply, $force)
                    ->then(
                        function (array $batch) use ($record): array {
                            foreach ($batch as $row) {
                                $record($row);
                            }

                            return $batch;
                        },
                        // processGroup() never rejects; this is belt and braces
                        // so a bug in one group cannot end the whole run.
                        function (Throwable $e) use ($record, $group): array {
                            $fallback = [];
                            foreach ($group as $organization) {
                                $row = [
                                    'organization_id' => $organization->id,
                                    'slug' => $organization->slug,
                                    'name' => $organization->name,
                                    'website' => $organization->website,
                                    'status' => 'failed',
                                    'reason' => 'exception',
                                    'detail' => $e->getMessage(),
                                    'logo_path' => null,
                                ];
                                $record($row);
                                $fallback[] = $row;
                            }

                            return $fallback;
                        }
                    );
            }
        })();

        $each = new EachPromise($work, [
            'concurrency' => $concurrency,
            'fulfilled' => static function (): void {
                // Side effects happen in the then() above; this exists only to
                // drive settlement.
            },
        ]);

        $each->promise()->wait();

        return $results;
    }

    /**
     * Run one host's organizations strictly in sequence.
     *
     * @param  array<int, Organization>  $group
     * @return PromiseInterface<array<int, array<string, mixed>>>
     */
    private function processGroup(array $group, OrganizationLogoFetcher $fetcher, bool $apply, bool $force): PromiseInterface
    {
        return $this->processIndex($group, $fetcher, $apply, $force, 0, []);
    }

    /**
     * @param  array<int, Organization>  $group
     * @param  array<int, array<string, mixed>>  $collected
     * @return PromiseInterface<array<int, array<string, mixed>>>
     */
    private function processIndex(array $group, OrganizationLogoFetcher $fetcher, bool $apply, bool $force, int $index, array $collected): PromiseInterface
    {
        if (! isset($group[$index])) {
            return Create::promiseFor($collected);
        }

        $organization = $group[$index];

        return $fetcher->fetchAsync($organization)
            ->then(function (array $result) use ($group, $fetcher, $apply, $force, $index, $collected, $organization): array|PromiseInterface {
                $result = $this->applyResult($organization, $result, $apply, $force);
                $collected[] = $result;

                return $this->processIndex($group, $fetcher, $apply, $force, $index + 1, $collected);
            })
            ->otherwise(function (Throwable $e) use ($group, $fetcher, $apply, $force, $index, $collected, $organization): PromiseInterface {
                $collected[] = [
                    'organization_id' => $organization->id,
                    'slug' => $organization->slug,
                    'name' => $organization->name,
                    'website' => $organization->website,
                    'status' => 'failed',
                    'reason' => 'exception',
                    'detail' => $e->getMessage(),
                    'logo_path' => null,
                ];

                return $this->processIndex($group, $fetcher, $apply, $force, $index + 1, $collected);
            });
    }

    /**
     * Persist (or deliberately discard) a successful result.
     *
     * @param  array<string, mixed>  $result
     * @return array<string, mixed>
     */
    private function applyResult(Organization $organization, array $result, bool $apply, bool $force): array
    {
        $result['mode'] = $apply ? 'apply' : 'dry-run';

        if ($result['status'] !== 'fetched') {
            return $result;
        }

        if (! $apply) {
            $result['status'] = 'would_fetch';

            return $result;
        }

        if (! $force && $organization->logo_path) {
            $result['status'] = 'skipped';
            $result['reason'] = 'already_has_logo';
            $result['logo_path'] = null;

            return $result;
        }

        try {
            $updated = Organization::whereKey($organization->id)->update([
                'logo_path' => $result['logo_path'],
            ]);

            $result['db_updated'] = (bool) $updated;
        } catch (Throwable $e) {
            $result['status'] = 'failed';
            $result['reason'] = 'db_write_failed';
            $result['detail'] = $e->getMessage();
            $result['logo_path'] = null;
        }

        return $result;
    }

    // ---------------------------------------------------------------------
    // Output
    // ---------------------------------------------------------------------

    private function renderProgress(array $row, int $processed, int $total): void
    {
        $label = sprintf('[%d/%d] %s', $processed, $total, $row['slug'] ?? ('#'.($row['organization_id'] ?? '?')));

        match ($row['status']) {
            'fetched' => $this->components->twoColumnDetail($label, '<fg=green>fetched</> '.$row['logo_path']),
            'would_fetch' => $this->components->twoColumnDetail($label, '<fg=yellow>would fetch</> '.$row['logo_path']),
            'skipped' => $this->components->twoColumnDetail($label, '<fg=gray>skipped: '.($row['reason'] ?? 'unknown').'</>'),
            default => $this->components->twoColumnDetail($label, '<fg=red>failed: '.($row['reason'] ?? 'unknown').'</>'),
        };
    }

    /**
     * @param  array<int, array<string, mixed>>  $results
     * @param  array<string, int>  $summary
     */
    private function render(array $results, array $summary, bool $apply): void
    {
        $this->newLine();
        $this->components->info('Summary');

        $this->components->twoColumnDetail('Selected', (string) $summary['selected']);
        $this->components->twoColumnDetail('Fetched', '<fg=green>'.$summary['fetched'].'</>');
        $this->components->twoColumnDetail('Would fetch (dry run)', (string) $summary['would_fetch']);
        $this->components->twoColumnDetail('Skipped', (string) $summary['skipped']);
        $this->components->twoColumnDetail('Failed', $summary['failed'] > 0 ? '<fg=red>'.$summary['failed'].'</>' : '0');

        if ($summary['with_website'] > 0) {
            $rate = $summary['fetched'] / $summary['with_website'] * 100;
            $this->components->twoColumnDetail(
                'Success rate (of those with a website)',
                number_format($rate, 1).'%  ('.$summary['fetched'].'/'.$summary['with_website'].')',
            );
        }

        if ($summary['reasons'] !== []) {
            $this->newLine();
            $this->components->info('Outcomes by reason');
            arsort($summary['reasons']);
            foreach ($summary['reasons'] as $reason => $count) {
                $this->components->twoColumnDetail((string) $reason, (string) $count);
            }
        }

        $failures = array_values(array_filter($results, static fn (array $r): bool => $r['status'] === 'failed'));

        if ($failures !== []) {
            $this->newLine();
            $this->components->warn(sprintf('%d organization(s) could not be resolved:', count($failures)));
            foreach ($failures as $row) {
                $this->components->twoColumnDetail(
                    $row['slug'].' ('.($row['website'] ?: 'no website').')',
                    $row['reason'].(isset($row['detail']) ? ' — '.$row['detail'] : ''),
                );
            }
        }

        if (! $apply) {
            $this->newLine();
            $this->components->warn('Dry run. Nothing was written. Re-run with --apply to store these logos.');
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $results
     * @return array<string, mixed>
     */
    private function summarise(array $results): array
    {
        $summary = [
            'selected' => count($results),
            'with_website' => 0,
            'fetched' => 0,
            'would_fetch' => 0,
            'skipped' => 0,
            'failed' => 0,
        ];

        $reasons = [];

        foreach ($results as $row) {
            if (! empty($row['website'])) {
                $summary['with_website']++;
            }

            $key = $row['status'];
            if (isset($summary[$key])) {
                $summary[$key]++;
            }

            $reason = $row['reason'] ?? null;
            if ($reason !== null && $row['status'] !== 'fetched') {
                $reasons[$reason] = ($reasons[$reason] ?? 0) + 1;
            }
        }

        $summary['reasons'] = $reasons;

        return $summary;
    }

    /**
     * @param  array<int, array<string, mixed>>  $results
     * @param  array<string, mixed>  $summary
     */
    private function writeReport(array $results, array $summary, bool $apply, int $concurrency, float $delay, int $timeout): void
    {
        $path = $this->option('report') ?: storage_path('logs/organization-logos.json');

        $payload = [
            'command' => 'acl:fetch-organization-logos',
            'generated_at' => now()->toIso8601String(),
            'mode' => $apply ? 'apply' : 'dry-run',
            'options' => [
                'limit' => $this->option('limit'),
                'id' => $this->option('id'),
                'slug' => $this->option('slug'),
                'force' => (bool) $this->option('force'),
                'concurrency' => $concurrency,
                'host_delay_seconds' => $delay,
                'timeout_seconds' => $timeout,
                'respect_robots' => ! (bool) $this->option('ignore-robots'),
                'verify_tls' => ! (bool) $this->option('allow-invalid-tls'),
                'user_agent' => OrganizationLogoFetcher::USER_AGENT,
            ],
            'summary' => $summary,
            'results' => $results,
        ];

        $directory = dirname($path);

        if (! is_dir($directory)) {
            @mkdir($directory, 0755, true);
        }

        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        // The report holds one row per organization, including URLs scraped
        // from third-party sites. 0644 rather than 0664 keeps it off the
        // www-data write path.
        if (@file_put_contents($path, $json) === false) {
            $this->components->error('Could not write the JSON report to '.$path);

            return;
        }

        @chmod($path, 0644);

        $this->components->info('Report written to '.$path);
    }
}
