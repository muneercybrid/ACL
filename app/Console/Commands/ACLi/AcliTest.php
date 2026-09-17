<?php
namespace App\Console\Commands\ACLi;

use Illuminate\Console\Command;
use App\Services\ACLi\AcliOrchestrator;

class AcliTest extends Command
{
    protected $signature = 'acli:test {message?}';
    protected $description = 'ACL CLI AI test harness';

    public function handle(AcliOrchestrator $orch): int
    {
        $msg = $this->argument('message') ?: 'What is recursion in simple terms?';

        // Phase 2: diagnostic metadata only — never expose secret
        $this->line('ACLi CLI test');
        $this->line('Model: ' . config('acli.model', config('app.acli_model', 'auto')));
        $this->line('Base URL configured: ' . (config('acli.omni_route_base_url') ? 'yes' : 'no'));
        $this->line('API key present: ' . (config('acli.omni_route_api_key') ? 'yes' : 'no'));

        try {
            $resp = $orch->studentChat([['role'=>'user','content'=>$msg]]);
            $this->line('Response: ' . ($resp['message']['content'] ?? json_encode($resp)));
            $this->line('Status: OK');
        } catch (\Throwable $e) {
            $this->error('ACLi error: ' . $e->getMessage());
            return 1;
        }
        return 0;
    }
}
