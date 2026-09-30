<?php

namespace App\Console\Commands;

use App\Models\Curriculum\Programme;
use App\Models\Organization;
use App\Services\Auth\LevelCoordinatorAppointer;
use Illuminate\Console\Command;

/**
 * Appoint a level coordinator and issue their activation link.
 */
class AppointLevelCoordinator extends Command
{
    protected $signature = 'acl:appoint-level-coordinator
        {--organization= : Name, short name or slug of the school}
        {--programme= : Programme name or code}
        {--level= : Level, e.g. 100}
        {--name= : The person being appointed}
        {--email-domain= : Domain for the generated address}';

    protected $description = 'Appoint a level coordinator and print their single-use activation link';

    public function handle(LevelCoordinatorAppointer $appointer): int
    {
        $organization = $this->resolveOrganization();
        $programme = $this->resolveProgramme();
        $level = (int) $this->option('level');
        $person = trim((string) $this->option('name'));

        if (! $person) {
            $this->error('--name is required: say who is being appointed.');

            return self::FAILURE;
        }

        if (! $level) {
            $this->error('--level is required, for example --level=100');

            return self::FAILURE;
        }

        try {
            $result = $appointer->appoint(
                $organization,
                $programme,
                $level,
                ['name' => $person],
                $this->option('email-domain') ?: 'aclacademy.me',
            );
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->line('');
        $this->info('Coordinator appointed.');
        $this->table(
            ['School', 'Programme', 'Level'],
            [[$organization->name, $programme->name, (string) $level]]
        );
        $this->line('  Generated address: '.$result['user']->email);
        $this->line('');
        $this->comment('  Activation link — give this to the person appointed. It works once,');
        $this->comment('  expires in 14 days, and is not recoverable:');
        $this->line('');
        $this->line('  '.$result['activation_url']);
        $this->line('');

        return self::SUCCESS;
    }

    private function resolveOrganization(): Organization
    {
        $needle = trim((string) $this->option('organization'));

        if (! $needle) {
            throw new \InvalidArgumentException('--organization is required.');
        }

        $organization = Organization::where('slug', $needle)
            ->orWhere('short_name', $needle)
            ->orWhere('code', $needle)
            ->first()
            ?? Organization::where('name', 'like', "%{$needle}%")->first();

        if (! $organization) {
            throw new \InvalidArgumentException("No school matched \"{$needle}\".");
        }

        return $organization;
    }

    private function resolveProgramme(): Programme
    {
        $needle = trim((string) $this->option('programme'));

        if (! $needle) {
            throw new \InvalidArgumentException('--programme is required.');
        }

        $programme = Programme::where('code', $needle)
            ->first()
            ?? Programme::where('name', 'like', "%{$needle}%")->first();

        if (! $programme) {
            throw new \InvalidArgumentException("No programme matched \"{$needle}\".");
        }

        return $programme;
    }
}
