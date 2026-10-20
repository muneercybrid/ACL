<?php
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

function tableCount(string $table): int
{
    try {
        return DB::table($table)->count();
    } catch (\Throwable $e) {
        return -1;
    }
}

$stats = [
    'Courses (catalogue)' => tableCount('courses'),
    'Real chapters' => DB::table('course_chapters')->where('placeholder', 0)->count(),
    'Total chapter rows' => tableCount('course_chapters'),
    'Full 20-chapter courses' => DB::table('course_chapters')
        ->where('placeholder', 0)
        ->groupBy('course_id')
        ->havingRaw('COUNT(*) >= 20')
        ->count(DB::raw('DISTINCT course_id')),
    'Curriculum courses (CCMAS)' => tableCount('curriculum_courses'),
    'Academic programmes' => tableCount('academic_programs'),
    'Organizations' => tableCount('organizations'),
    'Users' => tableCount('users'),
    'Enrollments' => tableCount('enrollments'),
    'Lesson blocks' => tableCount('lesson_blocks'),
    'Migrations applied' => tableCount('migrations'),
    'Roles' => tableCount('roles'),
    'Role assignments' => tableCount('role_assignments'),
];

foreach ($stats as $label => $value) {
    echo str_pad($label, 30) . number_format($value) . "\n";
}

echo "\n-- roles --\n";
try {
    foreach (DB::table('roles')->get() as $r) {
        $count = DB::table('role_assignments')->where('role_id', $r->id)->count();
        echo str_pad($r->name, 20) . $count . "\n";
    }
} catch (\Throwable $e) {
    echo "n/a\n";
}
