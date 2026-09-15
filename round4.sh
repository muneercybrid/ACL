#!/bin/bash
echo "=============================================="
echo "🩺 REPAIR ROUND 4: Log Extraction & Side-Effect Check"
echo "=============================================="

echo "--- 🔒 CHECKING STORAGE PERMISSIONS ---"
# If Laravel can't write to the log or compile views, it throws a 500 and logs nothing!
ls -ld storage/logs
ls -ld storage/framework/views
echo ""

echo "--- 📂 LOCATING ACTIVE LOG FILE ---"
# Check for daily logs first, then fallback to standard laravel.log
LATEST_LOG=$(ls -t storage/logs/laravel-*.log storage/logs/laravel.log 2>/dev/null | head -n 1)

if [ -z "$LATEST_LOG" ]; then
    echo "❌ No Laravel log files found in storage/logs/."
else
    echo "✅ Active log: $LATEST_LOG"
    echo ""
    echo "--- 🚨 LAST 40 LINES OF LOG (Unfiltered) ---"
    tail -n 40 "$LATEST_LOG"
fi

echo ""
echo "--- 🔍 HUNTING FOR BROKEN ROUTE LINKS ---"
# Renaming routes to _dup might have broken Blade templates that still call the old names
echo "Checking for old route names in Blade templates..."
grep -rn "route('register.student')" resources/views/ 2>/dev/null
grep -rn "route('register.external')" resources/views/ 2>/dev/null
grep -rn "route('register')" resources/views/ 2>/dev/null | grep -v "register.student" | head -n 5
echo "(If any files are listed above, they are likely causing the 500 on /register)"

echo ""
echo "--- 🛣️ ROUTE LIST VERIFICATION ---"
echo "Register routes:"
php artisan route:list --name=register 2>&1 | head -n 15
echo ""
echo "Catalogue routes:"
php artisan route:list --path=catalogue 2>&1 | head -n 10

echo "=============================================="
echo "✅ ROUND 4 COMPLETE. Please paste the output."
echo "=============================================="
