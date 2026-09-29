<?php
/**
 * READ-ONLY. For every model, find columns that are NOT NULL with no default
 * and that the model neither lists in $fillable nor assigns anywhere in its own
 * source (booted/saving/creating hooks, or a literal in the class body).
 * These are the ones where a plain `Model::create([...])` can fail.
 */
$schema = json_decode(file_get_contents(__DIR__ . '/audit_schema_map.json'), true);

$rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__ . '/../app/Models'));
$rows = [];
foreach ($rii as $f) {
    if (! $f->isFile() || $f->getExtension() !== 'php') { continue; }
    $path = $f->getPathname();
    $src = file_get_contents($path);
    if (! preg_match('/class\s+(\w+)\s+extends\s+(\w+)/', $src, $cm)) { continue; }
    if (preg_match('/protected\s+\$table\s*=\s*[\'"]([a-zA-Z0-9_]+)[\'"]/', $src, $tm)) {
        $table = $tm[1];
    } else {
        $s = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $cm[1]));
        $last = substr($s, -1);
        if ($last === 'y' && ! in_array(substr($s, -2, 1), ['a', 'e', 'i', 'o', 'u'], true)) {
            $s = substr($s, 0, -1) . 'ies';
        } elseif (in_array($last, ['s', 'x', 'z', 'h'], true) || in_array(substr($s, -2), ['ch', 'sh', 'ss'], true)) {
            $s .= 'es';
        } else {
            $s .= 's';
        }
        $table = $s;
    }
    if (! isset($schema[$table])) { continue; }

    $fillable = [];
    if (preg_match('/\$fillable\s*=\s*\[(.*?)\]/s', $src, $fm)) {
        preg_match_all('/[\'"]([a-zA-Z0-9_]+)[\'"]/', $fm[1], $x);
        $fillable = $x[1];
    }

    foreach ($schema[$table]['columns'] as $col => $ci) {
        if (! $ci['notnull'] || $ci['default'] !== null) { continue; }
        if (in_array($col, ['id', 'created_at', 'updated_at'], true)) { continue; }
        if (in_array($col, $fillable, true)) { continue; }
        // assigned anywhere in this file (boot hook, forceFill, attribute)
        if (preg_match('/[\'"(]' . preg_quote($col, '/') . '[\'")\s]*=>/', $src)) { continue; }
        $rows[] = [
            'model' => str_replace([realpath(__DIR__ . '/../') . '/', '\\'], ['', '/'], $path),
            'table' => $table,
            'column' => $col,
            'type' => $ci['type'],
            'declared_in' => $ci['file'],
        ];
    }
}

foreach ($rows as $r) {
    printf("%-42s %-22s %-20s (%s, from %s)\n", $r['model'], $r['table'], $r['column'], $r['type'], $r['declared_in']);
}
echo "\nTOTAL: " . count($rows) . "\n";
