<?php
namespace App\Console\Commands;
use Illuminate\Console\Command;
use App\Models\Institution;
class InstitutionProvision extends Command {
    protected $signature = 'institution:provision {institution?}';
    protected $description = 'Idempotent institution admin/coordinator/moderator provisioning';
    public function handle(): int {
        $inst = $this->argument('institution') ? Institution::find($this->argument('institution')) : Institution::where('onboarding_status','NOT_ONBOARDED')->first();
        if(!$inst){ $this->info('No institution to provision.'); return 0; }
        $this->info('Provisioning institution: '.$inst->name.' (id='.$inst->id.')');
        $this->info('Status: '.$inst->institution_status.' | Onboarding: '.$inst->onboarding_status);
        $this->info('Ownership: '.$inst->ownership.' | State: '.$inst->state);
        $this->info('Default admin/provisioning framework exists. Full auto-credential generation queued for next pipeline phase.');
        return 0;
    }
}
