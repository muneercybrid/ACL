<?php
/**
 * READ-ONLY static analysis of database/migrations/*.php
 * Parses Schema::create / Schema::table calls to build:
 *   - tables -> columns (with nullability, defaults, type)
 *   - foreign keys declared
 * No database connection is made.
 */
$dir = __DIR__ . '/../database/migrations';
$files = glob($dir . '/*.php');
sort($files);

$tables = [];   // table => ['cols'=>[name=>info], 'fks'=>[...], 'order'=>int]
$order = 0;

function addCol(array &$t, string $table, string $col, array $info): void
{
    if (! isset($t[$table])) { $t[$table] = ['cols' => [], 'fks' => [], 'order' => 0]; }
    $info['added_in_order'] = $GLOBALS['order'];
    // later migrations override earlier info for the same column
    if (isset($t[$table]['cols'][$col]) && $t[$table]['cols'][$col]['added_in_order'] > $info['added_in_order']) {
        return;
    }
    $t[$table]['cols'][$col] = $info;
}

foreach ($files as $file) {
    $order++;
    $src = file_get_contents($file);
    $base = basename($file);

    // Only the up() body defines the schema; down() tears it down and would
    // otherwise erase the columns we just recorded.
    $upPos = strpos($src, 'function up');
    $downPos = strpos($src, 'function down');
    if ($upPos === false) { continue; }
    if ($downPos !== false && $downPos > $upPos) { $src = substr($src, $upPos, $downPos - $upPos); }
    else { $src = substr($src, $upPos); }

    // Find Schema::create('x', function (Blueprint $table) { ... });  and Schema::table('x', ...)
    // We match the statement up to the matching close. Use a brace counter.
    // Accept both `function (Blueprint $t)` and untyped `function ($t)`.
    $pattern = '/Schema::(create|table|dropIfExists)\(\s*([\'"])([a-zA-Z0-9_]+)\2\s*,\s*function\s*\(\s*(?:Blueprint\s+)?\$[a-zA-Z_]+\s*\)\s*\{/';
    if (! preg_match_all($pattern, $src, $m, PREG_OFFSET_CAPTURE | PREG_SET_ORDER)) {
        continue;
    }
    foreach ($m as $match) {
        $kind = $match[1][0];
        $table = $match[3][0];
        $start = $match[0][1] + strlen($match[0][0]) - 1;
        // brace matching from $start
        $depth = 0; $end = null;
        for ($i = $start; $i < strlen($src); $i++) {
            if ($src[$i] === '{') { $depth++; }
            elseif ($src[$i] === '}') { $depth--; if ($depth === 0) { $end = $i; break; } }
        }
        if ($end === null) { continue; }
        $body = substr($src, $start + 1, $end - $start - 1);

        // Strip comments so a doc line cannot be glued onto the statement that
        // follows it (which would hide that statement from the parser).
        $lines = explode("\n", $body);
        foreach ($lines as $li => $line) {
            $t = trim($line);
            if ($t === '' || $t[0] === '/' || $t[0] === '*') { $lines[$li] = ''; continue; }
            $q = substr_count($line, "'") + substr_count($line, '"');
            if ($q % 2 === 0 && preg_match('#\s//#', $line)) {
                $lines[$li] = preg_replace('#\s//.*$#', '', $line);
            }
        }
        $body = implode("\n", $lines);

        // Only track columns when a create/table actually runs. In down() blocks
        // we skip dropColumn etc. (we only parse add* / column declarations).
        if (! isset($tables[$table])) { $tables[$table] = ['cols' => [], 'fks' => [], 'order' => 0, 'created_in' => $base]; }

        // Split body into statements on ");" at nesting level
        $parts = [];
        $buf = ''; $d = 0;
        for ($i = 0; $i < strlen($body); $i++) {
            $ch = $body[$i];
            $buf .= $ch;
            if ($ch === '(') { $d++; }
            if ($ch === ')') { $d--; }
            if ($ch === ';' && $d === 0) { $parts[] = trim($buf); $buf = ''; }
        }
        if (trim($buf) !== '') { $parts[] = trim($buf); }

        $lastCol = null;
        foreach ($parts as $stmt) {
            // ignore drops
            if (preg_match('/drop(Column|Index|Unique|Foreign|IfExists)/i', $stmt)) { continue; }
            if (preg_match('/^\$[a-zA-Z_]+\s*->\s*(index|unique|primaryKey|fullText|spatialIndex)\s*\(/i', $stmt)) {
                // record a unique on a column list for reference
                if (preg_match('/unique\s*\(\s*\[?([^\]]*)\]?\s*[,)]/i', $stmt, $um)) {
                    foreach (explode(',', $um[1]) as $c) {
                        $c = trim(trim($c), "'\"");
                        if ($c !== '') { $tables[$table]['uniques'][] = $c; }
                    }
                }
                if (preg_match('/primary\s*\(\s*\[?([^\]]*)\]?/i', $stmt, $um)) {
                    foreach (explode(',', $um[1]) as $c) {
                        $c = trim(trim($c), "'\"");
                        if ($c !== '') { $tables[$table]['uniques'][] = $c; }
                    }
                }
                continue;
            }
            // column declarations (the blueprint variable is often $table, sometimes $t)
            if (! preg_match('/^\$[a-zA-Z_]+\s*->\s*([a-zA-Z]+)\s*\(\s*([\'"])([a-zA-Z0-9_]+)\2(.*)$/s', $stmt, $cm)) { continue; }
            $type = $cm[1];
            $col = $cm[3];
            $rest = $cm[4];

            $drop = fn(string $frag) => (bool) preg_match('/->' . $frag . '\s*\(/i', $rest);
            $val = fn(string $frag) => (function () use ($rest, $frag) {
                if (preg_match('/->' . $frag . '\s*\(\s*(.*?)\s*\)\s*(?:->|;|,|$)/si', $rest, $m2)) { return trim($m2[1]); }
                return null;
            })();

            $dropping = $drop('dropColumn');

            $info = [
                'type' => $type,
                'notnull' => $drop('nullable') ? false : true,
                'default' => $val('default'),
                'length' => $val('length'),
                'file' => $base,
            ];

            if ($type === 'foreignId') {
                // foreignId('x')->constrained() implies FK to table x
                $refTable = null;
                if (preg_match('/->constrained\s*\(\s*([\'"])([a-zA-Z0-9_]+)\1/i', $rest, $fm)) {
                    $refTable = $fm[2];
                } elseif (preg_match('/->constrained\s*\(\s*\)/i', $rest)) {
                    // Laravel: Str::plural(Str::snake(Str::beforeLast($col, '_id')))
                    $base = preg_replace('/_id$/', '', $col);
                    $refTable = Str::plural($base);
                } elseif (preg_match('/->constrained\s*\(\s*\$table,\s*([\'"])([a-zA-Z0-9_]+)\1/i', $rest, $fm)) {
                    $refTable = $fm[2];
                } else {
                    $refTable = preg_replace('/_id$/', '', $col);
                }
                $tables[$table]['fks'][] = ['column' => $col, 'on' => $refTable, 'file' => $base, 'style' => 'foreignId->constrained'];
                $info['type'] = 'bigint(unsigned)';
            }
            if (preg_match('/->references\s*\(\s*([\'"])([a-zA-Z0-9_]+)\1\s*\)\s*->on\s*\(\s*([\'"])([a-zA-Z0-9_]+)\3/i', $rest, $fm)) {
                $tables[$table]['fks'][] = ['column' => $col, 'refcol' => $fm[2], 'on' => $fm[4], 'file' => $base, 'style' => 'references()->on()'];
                $info['type'] = $info['type'] === 'foreignId' ? 'bigint(unsigned)' : $info['type'];
            }
            if (preg_match('/->foreign\s*\(/i', $rest)) {
                $tables[$table]['fks'][] = ['column' => $col, 'on' => '?', 'file' => $base, 'style' => 'foreign()'];
            }
            if (preg_match('/^enum/i', $rest) || str_contains($rest, 'enum(')) {
                if (preg_match('/enum\s*\(\s*\[(.*?)\]/s', $rest, $em)) {
                    $vals = array_map(fn($v) => trim(trim($v), "'\""), explode(',', $em[1]));
                    $info['enum'] = $vals;
                }
            }
            if (! $dropping) {
                addCol($tables, $table, $col, $info);
            } else {
                unset($tables[$table]['cols'][$col]);
            }
            $lastCol = $col;
        }
    }
}

class Str {
    public static function plural(string $s): string
    {
        if ($s === '') { return $s; }
        $last = substr($s, -1);
        $rules = [
            's' => 'ses', 'x' => 'xes', 'z' => 'zes', 'h' => 'hes', 'y' => 'ies',
        ];
        if ($last === 'y') {
            $prev = substr($s, -2, 1);
            if (! in_array($prev, ['a', 'e', 'i', 'o', 'u'], true)) { return substr($s, 0, -1) . 'ies'; }
        }
        if (isset($rules[$last])) { return $s . $rules[$last]; }
        if (substr($s, -2) === 'ch' || substr($s, -2) === 'sh' || substr($s, -2) === 'ss') { return $s . 'es'; }
        return $s . 's';
    }
}

$out = [];
foreach ($tables as $name => $t) {
    $out[$name] = [
        'created_in' => $t['created_in'] ?? '?',
        'columns' => $t['cols'],
        'foreign_keys' => $t['fks'],
        'unique_columns' => array_values(array_unique($t['uniques'] ?? [])),
    ];
}
file_put_contents(__DIR__ . '/audit_schema_map.json', json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
echo "tables: " . count($out) . "\n";
echo "wrote scripts/audit_schema_map.json\n";
