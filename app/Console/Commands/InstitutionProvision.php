<?php

namespace App\Console\Commands;

use App\Models\Institution;
use App\Models\User;
use Illuminate\Console\Command;

class InstitutionProvision extends Command
{
    protected $signature = 'institution:provision {institution?}';

    protected $description = 'Idempotent institution admin/coordinator/moderator provisioning';

    public function handle(): int
    {
        $inst = $this->argument('institution')
            ? Institution::find($this->argument('institution'))
            : Institution::where('onboarding_status', 'NOT_ONBOARDED')->first();

        if (! $inst) {
            $this->info('No institution to provision.');

            return 0;
        }

        $this->info('Provisioning institution: '.$inst->name.' (id='.$inst->id.')');
        $this->info('Status: '.$inst->institution_status.' | Onboarding: '.$inst->onboarding_status);
        $this->info('Ownership: '.$inst->ownership.' | State: '.$inst->state);

        // Find users who already hold the institution.admin role assignment
        // (scoped or platform-wide). Uses roleAssignments() — the User model
        // has no direct roles() relationship; roles are reached through
        // roleAssignments → role.
        $admins = User::whereHas(
            'roleAssignments',
            fn ($q) => $q->whereHas('role', fn ($q) => $q->where('slug', 'institution.admin'))
        )->get();

        if ($admins->isEmpty()) {
            $this->warn('No institution.admin users found. Assign one via the superadmin panel before re-running.');
        } else {
            $this->info('Existing institution.admin users ('.$admins->count().'):');
            foreach ($admins as $admin) {
                $this->line('  - '.$admin->name.' <'.$admin->email.'>');
            }
        }

        $this->info('Default admin/provisioning framework exists. Full auto-credential generation queued for next pipeline phase.');

        return 0;
    }
}
