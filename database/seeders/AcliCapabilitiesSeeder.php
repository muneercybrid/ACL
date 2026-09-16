<?php

namespace Database\Seeders;

use App\Models\ACLi\Capability;
use Illuminate\Database\Seeder;

class AcliCapabilitiesSeeder extends Seeder
{
    public function run(): void
    {
        $configCapabilities = config('acli.capabilities', []);
        $created = 0;

        foreach ($configCapabilities as $slug => $data) {
            $capability = Capability::updateOrCreate(
                ['slug' => $slug],
                [
                    'name' => $data['name'],
                    'description' => $data['description'] ?? null,
                    'is_active' => true,
                ]
            );

            if ($capability->wasRecentlyCreated) {
                $created++;
            }
        }

        $this->command->info("ACLi capabilities seeded: {$created} created");
    }
}
