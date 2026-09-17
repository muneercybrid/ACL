<?php
namespace App\Console\Commands\ACLi;

use Illuminate\Console\Command;

class AcliDiagnose extends Command
{
    protected $signature = 'acli:diagnose';
    protected $description = 'ACL AI diagnostic report';

    public function handle(): int
    {
        $this->line('=== ACLi Diagnose ===');
        $this->line('1 Config: ' . (config('acli.omni_route_base_url') ? 'set' : 'MISSING'));
        $this->line('2 Key present: ' . (config('acli.omni_route_api_key') ? 'yes' : 'NO'));
        $this->line('3 Model: ' . config('acli.model', 'auto'));
        $this->line('4 Orchestrator class: ' . (class_exists(\App\Services\ACLi\AcliOrchestrator::class) ? 'exists' : 'MISSING'));
        $this->line('5 Gateway provider: ' . (class_exists(\App\Services\ACLi\Providers\OmniRouteProvider::class) ? 'exists' : 'MISSING'));
        $this->line('6 DeepSeek provider: ' . (class_exists(\App\Services\ACLi\Providers\DeepSeekProvider::class) ? 'exists' : 'MISSING'));
        return 0;
    }
}
