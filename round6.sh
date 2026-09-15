#!/bin/bash
echo "=============================================="
echo "🩺 REPAIR ROUND 6: Verify Curriculum Models & Final Smoke Test"
echo "=============================================="

echo "--- Clearing Caches ---"
php artisan optimize:clear >/dev/null 2>&1

echo "--- Re-running Curriculum Seeder (now that Programme.php is fixed) ---"
php artisan db:seed --class=CcmasAdminCurriculumSeeder --force 2>&1 | tail -5 || true

echo "--- Full Smoke Test ---"
# Testing core routes including catalogue which relies on Curriculum models
for u in / /register /register/student /privacy /terms /catalogue /login /forgot-password; do
    code=$(curl -s -o /dev/null -w "%{http_code}" "http://127.0.0.1:8000$u" 2>/dev/null || echo n/a)
    echo "  $u → $code"
done

echo ""
echo "--- Checking for recent 500/Fatal errors in logs ---"
grep -iE "ReflectionException|SyntaxError|ErrorException|Fatal error" storage/logs/laravel.log 2>/dev/null | tail -3 || echo "  ✅ No critical syntax or reflection errors found in recent logs."

echo ""
echo "=============================================="
echo "✅  ROUND 6 COMPLETE — paste the output"
echo "=============================================="
