<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$org = App\Models\Organization::where('name', 'like', '%Northwest%')->first();
echo 'Org: ' . ($org ? $org->name : 'NOT FOUND') . PHP_EOL;
if ($org) {
    $programs = App\Models\AcademicProgram::where('organization_id', $org->id)->get();
    echo 'Programs: ' . $programs->count() . PHP_EOL;
    foreach ($programs as $p) {
        echo '- ' . $p->name . ' (id: ' . $p->id . ', level: ' . $p->level . ')' . PHP_EOL;
    }
}

$u = App\Models\User::find(2000001);
$s = $u ? $u->student : null;
echo 'Student admission_year: ' . ($s ? $s->admission_year : 'NONE') . PHP_EOL;
echo 'Student verification_status: ' . ($s ? $s->verification_status : 'NONE') . PHP_EOL;
<?php require "vendor/autoload.php"; $app=require_once "bootstrap/app.php"; $app->make("Illuminate\Contracts\Console\Kernel")->bootstrap(); $org=App\Models\Organization::where("name","like","%Northwest%")->first(); $cyber=App\Models\AcademicProgram::where("organization_id",$org->id)->where("name","like","%Cyber%")->first(); echo "Cyber: ".($cyber?$cyber->name." id=".$cyber->id:"NONE").PHP_EOL;
