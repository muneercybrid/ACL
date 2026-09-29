<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Institution;
use App\Services\Institution\InstitutionAdminService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Ensures every institution has an Institution Administrator.
 *
 * Idempotent: running it repeatedly creates no additional accounts. A
 * credential that an administrator has already changed is never overwritten.
 */
class InstitutionProvisionAdmins extends Command
{
    protected $signature = 'acl:institutions:provision-admins
        {--institution= : Provision a single institution by id}
        {--only-missing : Skip institutions that already have an administrator}
        {--chunk=100 : Institutions per query}';

    protected $description = 'Provision an Institution Administrator for every institution (idempotent)';

    public function handle(InstitutionAdminService $service): int
    {
        $chunkSize = max(1, (int) $this->option('chunk'));

        if ($id = $this->option('institution')) {
            $institution = Institution::find($id);
            if ($institution === null) {
                $this->error("Institution {$id} not found.");

                return self::FAILURE;
            }

            $this->line(sprintf('  #%d %-56s %s', $institution->id, $institution->name, $service->provision($institution)['status']));

            return self::SUCCESS;
        }

        $created = $restored = $existing = 0;
        $failed = [];

        $query = Institution::query()->whereNull('canonical_institution_id')->orderBy('id');

        if ($this->option('only-missing')) {
            $query->whereDoesntHave('roles', function ($q) {
                $q->where('slug', InstitutionAdminService::ROLE_SLUG);
            });
        }

        // chunkById, never chunk().
        //
        // The query filters on "has no administrator", and provisioning an
        // institution makes it stop matching that filter. Offset-based
        // pagination then skips rows on every page, because the result set
        // shrinks underneath the cursor — which is exactly how 72 institutions
        // were left without an administrator. Keyset pagination by id is
        // unaffected by the result set changing under it.
        $query->chunkById($chunkSize, function ($institutions) use ($service, &$created, &$restored, &$existing, &$failed) {
            foreach ($institutions as $institution) {
                try {
                    $result = $service->provision($institution);

                    match ($result['status']) {
                        'created' => $created++,
                        'role_restored' => $restored++,
                        default => $existing++,
                    };
                } catch (\Throwable $e) {
                    // One institution must never stop the run: a partial
                    // provision is retried next time and is safe to retry.
                    $failed[] = ['id' => $institution->id, 'name' => $institution->name, 'error' => $e->getMessage()];

                    $this->error(sprintf('  #%d %s — %s', $institution->id, $institution->name, $e->getMessage()));
                }
            }
        });

        $this->newLine();
        $this->info(sprintf(
            'Administrators: %d created, %d role restored, %d already present, %d failed.',
            $created,
            $restored,
            $existing,
            count($failed)
        ));

        $total = DB::table('users')->where('email', 'like', '%@acl.local')->count();
        $this->line('Accounts on the platform: ' . $total);

        Log::info('acl:institutions:provision-admins', [
            'created' => $created,
            'role_restored' => $restored,
            'existing' => $existing,
            'failed' => $failed,
        ]);

        return $failed === [] ? self::SUCCESS : self::FAILURE;
    }
}
