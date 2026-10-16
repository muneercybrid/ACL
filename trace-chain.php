<?php
require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\ACLi\DTO\AIRequest;

$t0 = microtime(true);

// Trace the chain manually
echo "Building chain...\n";
$chain = [
    'tokenharbor' => new App\Services\ACLi\Providers\TokenHarborProvider(),
    'cloudflare' => new App\Services\ACLi\Providers\CloudflareProvider(),
    'omniroute' => new App\Services\ACLi\Providers\OmniRouteProvider(),
];

foreach ($chain as $name => $provider) {
    echo "Trying {$name}... ";
    if (! $provider->isAvailable()) {
        echo "not available\n";
        continue;
    }
    $t1 = microtime(true);
    try {
        $r = $provider->chat(new AIRequest(
            model: 'mimo-v2.6-flash:free',
            messages: [['role' => 'user', 'content' => 'Reply READY']],
            maxTokens: 20,
        ));
        echo "OK in ".round(microtime(true) - $t1, 2)."s\n";
        break;
    } catch (\Throwable $e) {
        echo "FAIL in ".round(microtime(true) - $t1, 2)."s: ".substr($e->getMessage(), 0, 80)."\n";
    }
}

echo "Total: ".round(microtime(true) - $t0, 2)."s\n";