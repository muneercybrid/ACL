<?php
namespace App\Services\Institution;

use App\Models\Institution;
use App\Models\User;
use App\Models\Role;
use App\Models\RoleAssignment;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class InstitutionAdminService
{
    public function provisionAdmin(Institution $institution): array
    {
        $existing = User::whereHas('roles', fn($q) => $q->where('slug','institution_admin'))
            ->where('institution_id', $institution->id)->first();
        if ($existing) {
            return ['status'=>'existing','user_id'=>$existing->id];
        }
        $password = Str::random(16).bin2hex(random_bytes(8));
        $user = User::create([
            'name' => $institution->official_name.' Institution Admin',
            'email' => $institution->abbr.'admin@acl.local',
            'password' => Hash::make($password),
            'institution_id' => $institution->id,
            'email_verified_at' => null,
            'force_password_change' => true,
        ]);
        $role = Role::where('slug','institution_admin')->firstOrFail();
        RoleAssignment::create([
            'user_id' => $user->id,
            'role_id' => $role->id,
            'entity_type' => Institution::class,
            'entity_id' => $institution->id,
        ]);
        return ['status'=>'created','user_id'=>$user->id,'temp_credential_generated'=>true,'password_hash_only'=>true];
    }
}
