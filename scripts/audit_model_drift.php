<?php
/**
 * READ-ONLY: cross-references each Eloquent model's $fillable/casts against the
 * columns its table actually has, derived from the migrations.
 */
$schema = json_decode(file_get_contents(__DIR__ . '/audit_schema_map.json'), true);
$modelDir = __DIR__ . '/../app/Models';

$rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($modelDir));
$models = [];
foreach ($rii as $f) {
    if (! $f->isFile() || $f->getExtension() !== 'php') { continue; }
    $models[] = $f->getPathname();
}
sort($models);

function snake(string $s): string
{
    $s = preg_replace('/(?<!^)[A-Z]/', '_$0', $s);
    return strtolower($s);
}
function pluralize(string $s): string
{
    $last = substr($s, -1);
    if ($last === 'y') {
        $prev = substr($s, -2, 1);
        if (! in_array($prev, ['a', 'e', 'i', 'o', 'u'], true)) { return substr($s, 0, -1) . 'ies'; }
    }
    if (in_array($last, ['s', 'x', 'z', 'h'], true)) { return $s . 'es'; }
    if (in_array(substr($s, -2), ['ch', 'sh', 'ss'], true)) { return $s . 'es'; }
    return $s . 's';
}
function toTable(string $src): string
{
    if (preg_match('/protected\s+\$table\s*=\s*[\'"]([a-zA-Z0-9_]+)[\'"]/', $src, $m)) { return $m[1]; }
    preg_match('/class\s+(\w+)\s+extends\s+Model/', $src, $cm);
    return pluralize(snake($cm[1]));
}

$report = [];
foreach ($models as $path) {
    $rel = str_replace(realpath(__DIR__ . '/../') . '/', '', $path);
    $rel = str_replace('\\', '/', $rel);
    $src = file_get_contents($path);
    if (! preg_match('/class\s+(\w+)\s+extends\s+Model/', $src, $cm)) { continue; }

    // $fillable
    $fillable = [];
    if (preg_match('/\$fillable\s*=\s*\[(.*?)\]/s', $src, $fm)) {
        preg_match_all('/[\'"]([a-zA-Z0-9_]+)[\'"]/', $fm[1], $cm2);
        $fillable = $cm2[1];
    }
    // casts
    $casts = [];
    if (preg_match('/protected\s+\$casts\s*=\s*\[(.*?)\]/s', $src, $km)) {
        preg_match_all('/[\'"]([a-zA-Z0-9_]+)[\'"]\s*=>/', $km[1], $cm3);
        $casts = $cm3[1];
    } elseif (preg_match('/function\s+casts\s*\(\s*\)\s*:\s*array\s*\{(.*?)\n    \}/s', $src, $km2)) {
        preg_match_all('/[\'"]([a-zA-Z0-9_]+)[\'"]\s*=>/', $km2[1], $cm3);
        $casts = $cm3[1];
    }

    $table = toTable($src);

    $exists = isset($schema[$table]);
    $cols = $exists ? array_keys($schema[$table]['columns']) : [];

    $phantom = array_values(array_diff($fillable, $cols));
    $phantomCasts = array_values(array_diff($casts, $cols));

    // NOT NULL, no default columns the model never sets
    $required = [];
    if ($exists) {
        foreach ($schema[$table]['columns'] as $c => $ci) {
            if ($ci['notnull'] && $ci['default'] === null && ! in_array($c, ['id', 'created_at', 'updated_at'], true)) {
                $required[$c] = $ci['file'];
            }
        }
    }
    $unset = array_values(array_diff(array_keys($required), $fillable));

    if ($phantom || $phantomCasts || $unset || ! $exists) {
        $report[] = [
            'model' => $rel,
            'table' => $table,
            'table_exists' => $exists,
            'fillable_not_a_column' => $phantom,
            'cast_not_a_column' => $phantomCasts,
            'notnull_no_default_not_fillable' => $unset,
        ];
    }
}

echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), "\n";
