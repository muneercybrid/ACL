<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$institutions = App\Models\Organization::all();
echo "Activating ".$institutions->count()." institutions...\n";

foreach ($institutions as $org) {
    if ($org->is_active) continue;

    $org->update([
        'is_active' => true,
        'contact_email' => 'admin@' . ($org->slug ?? 'aclacademy') . '.me',
    ]);

    $email = 'admin@' . ($org->slug ?? 'aclacademy') . '.me';
    $user = App\Models\User::where('email', $email)->first();
    if (! $user) {
        $user = App\Models\User::create([
            'name' => $org->name . ' Admin',
            'email' => $email,
            'password' => \Illuminate\Support\Facades\Hash::make('AdminPass123!'),
            'provider' => 'institution',
        ]);
    }
    echo "Institution: ".$org->name." Admin: ".$email." (default pass: AdminPass123!)\n";
}
echo "Done — all institutions active with default admin logins.";
