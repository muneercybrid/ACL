#!/bin/bash
echo "=============================================="
echo "🩺 REPAIR ROUND 3: Deep Error Logging & Execution"
echo "=============================================="

# 1. Reveal the EXACT cause of the 500 errors
echo "--- 🔍 EXACT ERROR CAUSING 500s ---"
tail -n 50 storage/logs/laravel.log | grep -A 2 '"message"' | tail -n 15

# 2. Force layout replacement (handling both single and double quotes)
echo "--- 🛠️ FIXING GUEST LAYOUTS ---"
FILES=(
    "resources/views/pages/privacy.blade.php"
    "resources/views/pages/terms.blade.php"
    "resources/views/auth/register.blade.php"
    "resources/views/catalog/index.blade.php"
)
for file in "${FILES[@]}"; do
    if [ -f "$file" ]; then
        sed -i -e "s/@extends('layouts.app')/@extends('layouts.auth')/g" \
               -e 's/@extends("layouts.app")/@extends("layouts.auth")/g' \
               -e "s/@extends('layouts.public')/@extends('layouts.auth')/g" "$file"
        echo "  ✅ Patched: $file"
    else
        echo "  ⚠️ Missing: $file"
    fi
done

# 3. Fix Duplicate Routes safely
echo "--- 🛠️ FIXING DUPLICATE ROUTES ---"
cat << 'PHPEOF' > fix_routes.php
<?php
$file = 'routes/web.php';
if (!file_exists($file)) { die("routes/web.php not found\n"); }
$content = file_get_contents($file);
$lines = explode("\n", $content);
$seen = [];
$changed = false;

foreach ($lines as $i => $line) {
    if (preg_match("/->name\(['\"]([^'\"]+)['\"]\)/", $line, $m)) {
        $name = $m[1];
        if (in_array($name, $seen)) {
            $new = $name . '_dup';
            $lines[$i] = str_replace("->name('{$name}')", "->name('{$new}')", $line);
            $lines[$i] = str_replace('->name("'.$name.'")', '->name("'.$new.'")', $lines[$i]);
            echo "  ✅ Renamed duplicate '{$name}' to '{$new}'\n";
            $changed = true;
        } else {
            $seen[] = $name;
        }
    }
}
if ($changed) {
    file_put_contents($file, implode("\n", $lines));
    echo "  ✅ Saved routes/web.php\n";
} else {
    echo "  ℹ️ No duplicates found in web.php (check route files or cache).\n";
}
PHPEOF
php fix_routes.php
rm fix_routes.php

# 4. Clear Caches
echo "--- 🧹 CLEARING CACHES ---"
php artisan view:clear >/dev/null
php artisan route:clear >/dev/null
php artisan cache:clear >/dev/null
php artisan config:clear >/dev/null

# 5. Smoke Test
echo "--- 🚬 SMOKE TEST ---"
for route in /register /register/student /privacy /terms /catalogue /login /forgot-password; do
    # Try common local dev ports
    status=$(curl -s -o /dev/null -w "%{http_code}" "http://127.0.0.1:8000$route" 2>/dev/null)
    if [ "$status" == "000" ]; then
        status=$(curl -s -o /dev/null -w "%{http_code}" "http://localhost$route" 2>/dev/null)
    fi
    echo "  $route → $status"
done

echo ""
echo "--- 🚨 LATEST EXCEPTIONS (if 500s remain) ---"
tail -n 30 storage/logs/laravel.log | grep -B 1 -A 3 '"message"' | tail -n 15

echo "=============================================="
echo "✅ ROUND 3 COMPLETE. Paste the output."
echo "=============================================="
