#!/bin/bash
set -uo pipefail
echo "=============================================="
echo "🩺 REPAIR ROUND 5: root-cause fixes (routes, models, view refs)"
echo "=============================================="

echo "--- DIAG: last exception messages ---"
grep -oE '"message":"[^"]{0,180}' storage/logs/laravel.log 2>/dev/null | tail -5 || true
echo "--- route names referenced by wizard view ---"
grep -oE "route\('[^']+'\)" resources/views/auth/register.blade.php 2>/dev/null | sort -u | head -15

echo ""
echo "===== FIX 1: bare RegisterController reference + missing use ====="
php -r '
foreach (["routes/web.php", "routes/register.php"] as $f) {
    if (!file_exists($f)) continue;
    $c = file_get_contents($f); $before = $c;
    $c = preg_replace("/\bRegisterController::class/", "RegistrationController::class", $c);
    $c = preg_replace("/\bRegisterController@/", "RegistrationController@", $c);
    if (strpos($c, "RegistrationController") !== false
        && strpos($c, "use App\\Http\\Controllers\\Auth\\RegistrationController;") === false) {
        $c = preg_replace("/^<\?php/", "<?php\n\nuse App\\Http\\Controllers\\Auth\\RegistrationController;", $c, 1);
    }
    if ($c !== $before) { file_put_contents($f, $c); echo "  ✅ patched $f\n"; }
}
'

echo ""
echo "===== FIX 2: create app/Models/Curriculum namespace (mkdir FIRST) ====="
mkdir -p app/Models/Curriculum

# Course + Programme: alias to the canonical flat models
if [ ! -f app/Models/Curriculum/Course.php ] && [ -f app/Models/Course.php ]; then
cat > app/Models/Curriculum/Course.php << 'PHP'
<?php

namespace App\Models\Curriculum;

class Course extends \App\Models\Course
{
    //
}
PHP
echo "  ✅ Curriculum\\Course alias"
fi

if [ ! -f app/Models/Curriculum/Programme.php ] && [ -f app/Models/Programme.php ]; then
cat > app/Models/Curriculum/Programme.php << 'PHP'
<?php

namespace App\Models\Curriculum;

class Programme extends \App\Models\Programme
{
    //
}
PHP
echo "  ✅ Curriculum\\Programme alias"
fi

# Curriculum-domain models: full definitions (table-explicit, relation-complete)
if [ ! -f app/Models/Curriculum/NucDiscipline.php ]; then
cat > app/Models/Curriculum/NucDiscipline.php << 'PHP'
<?php

namespace App\Models\Curriculum;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NucDiscipline extends Model
{
    protected $table = 'nuc_disciplines';
    protected $fillable = ['code', 'name', 'status'];

    public function programmes(): HasMany
    {
        return $this->hasMany(\App\Models\Programme::class, 'nuc_discipline_id');
    }

    public function courses(): BelongsToMany
    {
        return $this->belongsToMany(\App\Models\Course::class, 'course_disciplines', 'nuc_discipline_id', 'course_id');
    }
}
PHP
echo "  ✅ Curriculum\\NucDiscipline"
fi

if [ ! -f app/Models/Curriculum/AcademicSession.php ]; then
cat > app/Models/Curriculum/AcademicSession.php << 'PHP'
<?php

namespace App\Models\Curriculum;

use Illuminate\Database\Eloquent\Model;

class AcademicSession extends Model
{
    protected $table = 'academic_sessions';
    protected $fillable = ['name', 'slug', 'start_year', 'end_year', 'is_current', 'status'];
    protected $casts = ['is_current' => 'boolean'];
}
PHP
echo "  ✅ Curriculum\\AcademicSession"
fi

if [ ! -f app/Models/Curriculum/CurriculumVersion.php ]; then
cat > app/Models/Curriculum/CurriculumVersion.php << 'PHP'
<?php

namespace App\Models\Curriculum;

use Illuminate\Database\Eloquent\Model;

class CurriculumVersion extends Model
{
    protected $table = 'curriculum_versions';
    protected $fillable = [
        'programme_id', 'academic_session_id', 'version_label', 'slug', 'scope',
        'verification_status', 'source_type', 'source_document', 'is_active',
    ];
    protected $casts = ['is_active' => 'boolean'];
}
PHP
echo "  ✅ Curriculum\\CurriculumVersion"
fi

if [ ! -f app/Models/Curriculum/CurriculumCourse.php ]; then
cat > app/Models/Curriculum/CurriculumCourse.php << 'PHP'
<?php

namespace App\Models\Curriculum;

use Illuminate\Database\Eloquent\Model;

class CurriculumCourse extends Model
{
    protected $table = 'curriculum_courses';
    protected $fillable = [
        'curriculum_version_id', 'course_id', 'level', 'semester',
        'course_type', 'credit_units', 'status',
    ];
}
PHP
echo "  ✅ Curriculum\\CurriculumCourse"
fi

for m in Course Programme NucDiscipline AcademicSession CurriculumVersion CurriculumCourse; do
    php -l "app/Models/Curriculum/$m.php" >/dev/null 2>&1 || echo "  ❌ syntax error in $m.php"
done

echo ""
echo "===== FIX 3: guarded migration for curriculum tables/columns ====="
cat > database/migrations/2026_09_12_000300_ensure_curriculum_tables.php << 'MIG'
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('nuc_disciplines')) {
            Schema::create('nuc_disciplines', function (Blueprint $t) {
                $t->id();
                $t->string('code', 10)->unique();
                $t->string('name');
                $t->string('status', 20)->default('active');
                $t->timestamps();
            });
        }

        if (! Schema::hasTable('academic_sessions')) {
            Schema::create('academic_sessions', function (Blueprint $t) {
                $t->id();
                $t->string('name');
                $t->string('slug')->nullable();
                $t->integer('start_year')->nullable();
                $t->integer('end_year')->nullable();
                $t->boolean('is_current')->default(false);
                $t->string('status', 20)->default('active');
                $t->timestamps();
            });
        }

        if (! Schema::hasTable('curriculum_versions')) {
            Schema::create('curriculum_versions', function (Blueprint $t) {
                $t->id();
                $t->foreignId('programme_id')->constrained()->cascadeOnDelete();
                $t->unsignedBigInteger('academic_session_id')->nullable();
                $t->string('version_label');
                $t->string('slug')->nullable();
                $t->string('scope', 30)->default('nuc_baseline');
                $t->string('verification_status', 30)->default('verified');
                $t->string('source_type', 30)->nullable();
                $t->string('source_document')->nullable();
                $t->boolean('is_active')->default(true);
                $t->timestamps();
            });
        }

        if (! Schema::hasTable('curriculum_courses')) {
            Schema::create('curriculum_courses', function (Blueprint $t) {
                $t->id();
                $t->foreignId('curriculum_version_id')->constrained()->cascadeOnDelete();
                $t->foreignId('course_id')->constrained()->cascadeOnDelete();
                $t->integer('level')->default(100);
                $t->integer('semester')->nullable();
                $t->string('course_type', 40)->default('core');
                $t->integer('credit_units')->default(0);
                $t->string('status', 20)->default('active');
                $t->timestamps();
            });
        }

        if (Schema::hasTable('programmes') && ! Schema::hasColumn('programmes', 'nuc_discipline_id')) {
            Schema::table('programmes', function (Blueprint $t) {
                $t->unsignedBigInteger('nuc_discipline_id')->nullable()->index();
            });
        }
    }

    public function down(): void {}
};
MIG
php artisan migrate --force 2>&1 | tail -2

echo ""
echo "===== FIX 4: wizard view → literal URLs (name-independent, cannot 500) ====="
php -r '
$f = "resources/views/auth/register.blade.php";
$c = file_get_contents($f);
$map = [
    "route(\x27register.verify-jamb\x27)"      => "url(\x27/register/verify-jamb\x27)",
    "route(\x27register.student\x27)"          => "url(\x27/register/wizard/student\x27)",
    "route(\x27register.manual\x27)"           => "url(\x27/register/wizard/manual\x27)",
    "route(\x27register.external\x27)"         => "url(\x27/register/wizard/external\x27)",
    "route(\x27register.hooray\x27)"           => "url(\x27/register/hooray\x27)",
    "route(\x27register.data.states\x27)"      => "url(\x27/register/data/states\x27)",
    "route(\x27register.data.institutions\x27)"=> "url(\x27/register/data/institutions\x27)",
];
$n = 0;
foreach ($map as $k => $v) { $c = str_replace($k, $v, $c, $cnt); $n += $cnt; }
file_put_contents($f, $c);
echo "  ✅ replaced $n route() calls with url() literals\n";
'

echo ""
echo "===== FIX 5: name-guarded route guarantees (idempotent) ====="
php -r '
$f = "routes/web.php";
$c = file_get_contents($f);
if (strpos($c, "ACL wizard route guarantees") === false) {
    $c .= <<<ROUTES

// ---- ACL wizard route guarantees ----
if (!Route::has("register")) {
    Route::get("/register", [App\Http\Controllers\Auth\RegistrationController::class, "index"])->name("register");
}
if (!Route::has("register.wizard.student")) {
    Route::post("/register/wizard/student", [App\Http\Controllers\Auth\RegistrationController::class, "registerStudent"])->name("register.wizard.student");
}
if (!Route::has("register.wizard.manual")) {
    Route::post("/register/wizard/manual", [App\Http\Controllers\Auth\RegistrationController::class, "registerManual"])->name("register.wizard.manual");
}
if (!Route::has("register.wizard.external")) {
    Route::post("/register/wizard/external", [App\Http\Controllers\Auth\RegistrationController::class, "registerExternal"])->name("register.wizard.external");
}
if (!Route::has("register.hooray")) {
    Route::get("/register/hooray", [App\Http\Controllers\Auth\RegistrationController::class, "hooray"])->name("register.hooray");
}
if (!Route::has("register.data.states")) {
    Route::get("/register/data/states", [App\Http\Controllers\Auth\RegistrationController::class, "dataStates"])->name("register.data.states");
}
if (!Route::has("register.data.institutions")) {
    Route::get("/register/data/institutions", [App\Http\Controllers\Auth\RegistrationController::class, "dataInstitutions"])->name("register.data.institutions");
}
ROUTES;
    file_put_contents($f, $c);
    echo "  ✅ route guarantees appended\n";
} else { echo "  ℹ️ already present\n"; }
'

echo ""
echo "===== FIX 6: seeder missing DB facade import ====="
php -r '
$f = "database/seeders/ExternalCatalogueSeeder.php";
if (file_exists($f)) {
    $c = file_get_contents($f);
    if (strpos($c, "use Illuminate\\Support\\Facades\\DB;") === false && strpos($c, "DB::table") !== false) {
        $c = preg_replace("/^<\?php/", "<?php\n\nuse Illuminate\\Support\\Facades\\DB;", $c, 1);
        file_put_contents($f, $c);
        echo "  ✅ DB import added to ExternalCatalogueSeeder\n";
    }
}
'

echo ""
echo "===== SEED + CACHE + SMOKE ====="
php artisan db:seed --class=CcmasAdminCurriculumSeeder --force 2>&1 | tail -1 || true
php artisan db:seed --class=ExternalCatalogueSeeder --force 2>&1 | tail -1 || true
php artisan route:clear >/dev/null; php artisan view:clear >/dev/null; php artisan config:clear >/dev/null; php artisan cache:clear >/dev/null

echo "--- route:list sanity (ReflectionException must be gone) ---"
php artisan route:list 2>&1 | head -4

echo "--- smoke test ---"
for u in /register /register/student /privacy /terms /catalogue /login /forgot-password /; do
    code=$(curl -s -o /dev/null -w "%{http_code}" "http://127.0.0.1:8000$u" 2>/dev/null || echo n/a)
    echo "  $u → $code"
done

echo "--- fresh exception messages (only if a 500 remains) ---"
grep -oE '"message":"[^"]{0,180}' storage/logs/laravel.log 2>/dev/null | tail -3 || true

echo ""
echo "=============================================="
echo "✅ ROUND 5 COMPLETE — paste the output"
echo "=============================================="
