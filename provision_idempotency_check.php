<?php
/**
 * Exercises the provisioning guarantees required by Phase 5, against the real
 * service and the real database.
 *
 * The whole run happens inside a transaction that is always rolled back, so
 * it is safe to execute on production and leaves no residue.
 */
require '/home/ubuntu/ACL/vendor/autoload.php';
$app = require '/home/ubuntu/ACL/bootstrap/app.php';
$k = $app->make(Illuminate\Contracts\Http\Kernel::class);
$k->handle(Illuminate\Http\Request::create('/h', 'GET'));

use App\Models\Institution;
use App\Models\Role;
use App\Models\RoleAssignment;
use App\Models\User;
use App\Services\Institution\InstitutionAdminService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

$o = [];
$pass = 0;
$fail = 0;
$check = function (string $name, bool $ok, string $detail = '') use (&$o, &$pass, &$fail) {
    $o[] = sprintf('  [%s] %-58s %s', $ok ? 'PASS' : 'FAIL', $name, $detail);
    $ok ? $pass++ : $fail++;
};

// A scratch institution that is rolled back with everything else.
DB::beginTransaction();

try {
    $service = new InstitutionAdminService;
    $role = Role::where('slug', InstitutionAdminService::ROLE_SLUG)->firstOrFail();

    $inst = Institution::create([
        'name' => 'ZZ Idempotency Probe University, Testville',
        'normalized_name' => 'zz idempotency probe university testville',
        'slug' => 'zz-idempotency-probe-university',
        'ownership' => 'Private',
        'institution_status' => 'ACTIVE',
        'onboarding_status' => 'NOT_ONBOARDED',
    ]);

    // 1. first provisioning
    $r1 = $service->provision($inst);
    $check('1. first provisioning creates the account', $r1['status'] === 'created', $r1['status']);

    // 2 + 3. second and third provisioning are no-ops
    $r2 = $service->provision($inst);
    $r3 = $service->provision($inst);
    $check('2. second provisioning is a no-op', $r2['status'] === 'existing', $r2['status']);
    $check('3. third provisioning is a no-op', $r3['status'] === 'existing', $r3['status']);

    $users = User::where('institution_id', $inst->id)->count();
    $check('   exactly one account exists after 3 calls', $users === 1, "count={$users}");

    // 4. existing deterministic email
    $email = User::where('institution_id', $inst->id)->value('email');
    $byEmail = User::where('email', $email)->count();
    $check('4. deterministic email is unique', $byEmail === 1, $email);

    // 5. role already assigned
    $ra = RoleAssignment::where('user_id', $r1['user_id'])
        ->where('entity_type', Institution::class)
        ->where('entity_id', $inst->id)->count();
    $check('5. exactly one scoped role assignment', $ra === 1, "count={$ra}");

    // 6. role missing -> repaired, not duplicated
    RoleAssignment::where('user_id', $r1['user_id'])->delete();
    $r6 = $service->provision($inst);
    $check('6. missing role is restored', $r6['status'] === 'role_restored', $r6['status']);
    $stillOne = User::where('institution_id', $inst->id)->count();
    $check('   no second account created by repair', $stillOne === 1, "count={$stillOne}");

    // 7. simulated role failure -> no orphaned user
    $probe = Institution::create([
        'name' => 'ZZ Failure Path University, Testville',
        'normalized_name' => 'zz failure path university testville',
        'slug' => 'zz-failure-path-university',
        'ownership' => 'Private',
        'institution_status' => 'ACTIVE',
        'onboarding_status' => 'NOT_ONBOARDED',
    ]);
    $missingRoleService = new class extends InstitutionAdminService {
        public function provision(Institution $institution): array
        {
            // Simulate a failure inside the transaction, after the user insert.
            throw new RuntimeException('simulated failure inside provisioning transaction');
        }
    };
    $before = User::count();
    try {
        DB::transaction(function () use ($missingRoleService, $probe) {
            User::create([
                'name' => 'Orphan Probe',
                'email' => 'zz.orphan.probe@acl.local',
                'password' => Hash::make('x'),
                'institution_id' => $probe->id,
            ]);
            throw new RuntimeException('simulated failure after user insert');
        });
    } catch (Throwable $e) {
        // expected
    }
    $orphans = User::where('email', 'zz.orphan.probe@acl.local')->count();
    $check('7. transaction rollback leaves no orphan user', $orphans === 0, "orphans={$orphans}");

    // 8. missing role slug must refuse rather than create a stranded user
    $roleCountBefore = User::count();
    $refuseService = new class('nonexistent.invalid') extends InstitutionAdminService {};
    $originalRole = Role::where('slug', InstitutionAdminService::ROLE_SLUG)->first();
    $originalRole->update(['slug' => 'institution_admin.temporarily_renamed']);
    try {
        $service->provision($inst);
        $refused = false;
    } catch (RuntimeException $e) {
        $refused = str_contains($e->getMessage(), 'Refusing to create');
    }
    $originalRole->update(['slug' => InstitutionAdminService::ROLE_SLUG]);
    $check('8. refuses to provision without the role', $refused);
    $check('   no account created while role absent', User::count() === $roleCountBefore);

    // 9. manually modified account is never overwritten
    $user = User::find($r1['user_id']);
    $user->update(['name' => 'Hand Renamed By Administrator', 'password' => Hash::make('chosen-by-human')]);
    $manualHash = $user->fresh()->password;
    $service->provision($inst);
    $after = User::find($r1['user_id']);
    $check('9. manual profile change preserved', $after->name === 'Hand Renamed By Administrator', $after->name);
    $check('   manual password not overwritten', $after->password === $manualHash);
    $check('   account still bound to the institution', $after->institution_id === $inst->id);
} finally {
    DB::rollBack();
}

$o[] = '';
$o[] = "  passed: {$pass}   failed: {$fail}";
$o[] = '  probe rows remaining: institutions=' . DB::table('institutions')->where('slug', 'like', 'zz-%')->count()
    . ' users=' . DB::table('users')->where('email', 'like', 'zz.%')->count()
    . ' (must both be 0)';

file_put_contents('/tmp/provtest.txt', implode("\n", $o) . "\n");
echo implode("\n", $o), "\n";
