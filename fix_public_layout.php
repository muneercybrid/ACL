<?php
// Detect the layout used by the working login view (guest-safe by proof of 200)
$login = 'resources/views/auth/login.blade.php';
$L = '';
if (file_exists($login) && preg_match("/@extends\(\s*['\"]([^'\"]+)['\"]/", file_get_contents($login), $m)) {
    $L = $m[1];
}
echo "Guest-safe layout detected: " . ($L ?: '(none)') . "\n";

// If login itself uses layouts.app (or nothing detected), build a standalone public layout
if ($L === '' || $L === 'layouts.app') {
    $L = 'layouts.public';
    $pub = <<<'BLADE'
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', config('app.name')) — {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-bg text-text antialiased">
<a href="#main" class="sr-only focus:not-sr-only focus:absolute focus:z-50 focus:m-2 focus:rounded focus:bg-primary focus:px-3 focus:py-2 focus:text-primary-fg">Skip to content</a>
<header class="border-b border-border bg-surface">
    <div class="mx-auto flex max-w-6xl items-center justify-between px-4 py-3">
        <a href="{{ url('/') }}" class="font-editorial text-lg font-bold">ACL</a>
        <nav class="flex items-center gap-4 text-sm">
            <a href="{{ url('/catalogue') }}" class="hover:text-primary">Catalogue</a>
            <a href="{{ url('/privacy') }}" class="hover:text-primary">Privacy</a>
            <a href="{{ url('/terms') }}" class="hover:text-primary">Terms</a>
            @auth
                <a href="{{ url('/dashboard') }}" class="rounded-lg bg-primary px-3 py-1.5 font-semibold text-primary-fg">Dashboard</a>
            @else
                <a href="{{ url('/login') }}" class="hover:text-primary">Sign in</a>
                <a href="{{ url('/register') }}" class="rounded-lg bg-primary px-3 py-1.5 font-semibold text-primary-fg">Get started</a>
            @endauth
        </nav>
    </div>
</header>
<main id="main">@yield('content')</main>
<footer class="border-t border-border py-6 text-center text-xs text-muted">
    © {{ date('Y') }} ACL — Anyone Can Learn.
    <a href="{{ url('/privacy') }}" class="underline">Privacy</a> ·
    <a href="{{ url('/terms') }}" class="underline">Terms</a>
</footer>
</body>
</html>
BLADE;
    if (!is_dir('resources/views/layouts')) mkdir('resources/views/layouts', 0775, true);
    file_put_contents('resources/views/layouts/public.blade.php', $pub);
    echo "✅ Created standalone resources/views/layouts/public.blade.php\n";
}

// Repoint guest-facing views to the guest-safe layout
$targets = [
    'resources/views/pages/privacy.blade.php',
    'resources/views/pages/terms.blade.php',
    'resources/views/auth/register.blade.php',
    'resources/views/auth/register-student.blade.php',
    'resources/views/catalog/index.blade.php',
    'resources/views/catalog/faculty.blade.php',
    'resources/views/catalog/department.blade.php',
];
foreach ($targets as $f) {
    if (!file_exists($f)) continue;
    $c = file_get_contents($f);
    $new = preg_replace("/@extends\(\s*['\"][^'\"]+['\"]\s*\)/", "@extends('{$L}')", $c, 1);
    if ($new !== $c) { file_put_contents($f, $new); echo "  repointed: $f → {$L}\n"; }
}

echo ""
echo "=============================================="
echo "FIX 2: defensive view-variable defaults in register-student view"
echo "=============================================="
$f = 'resources/views/auth/register-student.blade.php';
if (file_exists($f)) {
    $c = file_get_contents($f);
    if (strpos($c, '$years = $years ??') === false) {
        $defaults = <<<'BLADE'
@php
    $years        = $years ?? range((int) date('Y'), 1990);
    $selectedYear = $selectedYear ?? old('examination_year', (string) date('Y'));
    $states       = $states ?? [];
    $lgas         = $lgas ?? [];
    $selectedState = $selectedState ?? old('state');
    $selectedLga  = $selectedLga ?? old('lga');
    $nationalities = $nationalities ?? ['Nigerian'];
    $faculties    = $faculties ?? [];
    $departments  = $departments ?? [];
    $programmes   = $programmes ?? [];
@endphp

BLADE;
        file_put_contents($f, $defaults . $c);
        echo "✅ Injected null-safe defaults at top of register-student.blade.php\n";
    } else {
        echo "ℹ️ Defaults already present\n";
    }
} else {
    echo "ℹ️ register-student.blade.php not found (nothing to patch)\n";
}

echo ""
echo "=============================================="
echo "FIX 3: remove duplicate route NAMES (register.student / register.external)"
echo "=============================================="
cat > fix_dup_routes.php << 'PATCH'
<?php
$f = 'routes/web.php';
$c = file_get_contents($f);
$before = $c;

// The legacy RegistrationController POST duplicates names owned by the
// StudentRegistrationController / ExternalLearnerRegistrationController flows.
// Keep the routes reachable but drop the colliding NAMES.
$c = preg_replace(
    "/(Route::post\('\/register\/student',\s*\[RegistrationController::class,\s*'registerStudent'\]\))\s*->name\('register\.student'\);/",
    "$1;",
    $c
);
$c = preg_replace(
    "/(Route::post\('\/register\/external',\s*\[RegistrationController::class,\s*'registerExternal'\]\))\s*->name\('register\.external'\);/",
    "$1;",
    $c
);

if ($c !== $before) { file_put_contents($f, $c); echo "✅ Duplicate route names removed\n"; }
else { echo "ℹ️ No duplicate named register routes matched (check manually if dups persist)\n"; }
