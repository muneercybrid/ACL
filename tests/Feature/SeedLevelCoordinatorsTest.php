<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Regression tests for the SeedLevelCoordinators artisan command.
 *
 * The critical regression: when --limit is not supplied (or is 0), the
 * old code emitted `… ORDER BY … offset 0` without a preceding LIMIT
 * clause, which TiDB rejects as a syntax error.  These tests run the
 * command against the in-memory test database (MariaDB / SQLite) to
 * assert:
 *   1. The command exits successfully with no --limit (the OFFSET-less path).
 *   2. The command exits successfully with --limit=1 (the LIMIT + no OFFSET path).
 *   3. The SQL executed never contains "offset" without a prior "limit".
 */
class SeedLevelCoordinatorsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Seed roles, programmes, and levels so the command has data to work with.
        $this->seed();
    }

    /** The command must not crash when no --limit is given (the original bug). */
    public function test_command_succeeds_without_limit_option(): void
    {
        $this->artisan('acl:seed:level-coordinators')
            ->assertExitCode(0);
    }

    /** The command must also succeed when a limit is given. */
    public function test_command_succeeds_with_limit_option(): void
    {
        $this->artisan('acl:seed:level-coordinators --limit=1')
            ->assertExitCode(0);
    }

    /**
     * Ensure no query contains a bare OFFSET without a LIMIT.
     *
     * DB::listen() intercepts every SQL string executed during the command.
     * A bare `offset N` not preceded by `limit` on the same statement would
     * fail on TiDB; detecting it here protects against regressions.
     */
    public function test_no_bare_offset_emitted_without_limit(): void
    {
        $offendingQueries = [];

        DB::listen(function ($query) use (&$offendingQueries): void {
            $sql = strtolower($query->sql);
            // Flag any query that has "offset" but no "limit" (TiDB-invalid).
            if (str_contains($sql, 'offset') && ! str_contains($sql, 'limit')) {
                $offendingQueries[] = $query->sql;
            }
        });

        $this->artisan('acl:seed:level-coordinators')->assertExitCode(0);

        $this->assertEmpty(
            $offendingQueries,
            'The following queries contained OFFSET without LIMIT (invalid on TiDB): ' .
            implode('; ', $offendingQueries),
        );
    }

    /** Even the --apply flag must not trigger any OFFSET-without-LIMIT query. */
    public function test_apply_flag_emits_no_bare_offset(): void
    {
        $offendingQueries = [];

        DB::listen(function ($query) use (&$offendingQueries): void {
            $sql = strtolower($query->sql);
            if (str_contains($sql, 'offset') && ! str_contains($sql, 'limit')) {
                $offendingQueries[] = $query->sql;
            }
        });

        $this->artisan('acl:seed:level-coordinators --apply')->assertExitCode(0);

        $this->assertEmpty(
            $offendingQueries,
            'The following queries contained OFFSET without LIMIT (invalid on TiDB): ' .
            implode('; ', $offendingQueries),
        );
    }
}
