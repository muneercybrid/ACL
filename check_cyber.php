<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$org = App\Models\Organization::where('name', 'like', '%Northwest%')->first();
$cyber = App\Models\AcademicProgram::where('organization_id', $org->id)->where('name', 'like', '%Cyber%')->first();
echo 'Cyber: ' . ($cyber ? $cyber->name . ' id=' . $cyber->id : 'NONE') . PHP_EOL;
echo 'All programmes at org: ' . PHP_EOL;
$all = App\Models\AcademicProgram::where('organization_id', $org->id)->get();
foreach ($all as $p) {
    echo '- ' . $p->name . ' (id: ' . $p->id . ')' . PHP_EOL;
}