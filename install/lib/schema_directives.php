<?php

// 解析结构文件中的声明式变更指令
function schema_directives(string $type = 'mysql'): array {
    static $cache = [];
    if (isset($cache[$type])) return $cache[$type];

    $out = ['sync' => [], 'sync_keep_extra' => [], 'modify' => [], 'drop_column' => [], 'drop_index' => [], 'drop_table' => []];
    $file = schema_sql_file($type);
    if (!is_file($file)) return $cache[$type] = $out;

    foreach (preg_split('/\r?\n/', (string)file_get_contents($file)) as $line) {
        if (!preg_match('/^\s*--\s*@([A-Za-z][\w-]*)\s*(.*)$/', $line, $m)) continue;

        $cmd = strtolower(str_replace('-', '_', $m[1]));
        $arg = trim($m[2]);
        if ($arg === '') continue;

        switch ($cmd) {
            case 'sync':
            case 'sync_keep_extra':
                foreach (preg_split('/[\s,]+/', $arg) as $t) { if ($t !== '') $out[$cmd][] = $t; }
                break;
            case 'modify':
                if (preg_match('/^(\w+)\.(\w+)\s+(.+)$/', $arg, $k)) {
                    $out['modify'][] = ['table' => $k[1], 'col' => $k[2], 'def' => trim($k[3])];
                }
                break;
            case 'drop_column':
                if (preg_match('/^(\w+)\.(\w+)$/', $arg, $k)) {
                    $out['drop_column'][] = ['table' => $k[1], 'col' => $k[2]];
                }
                break;
            case 'drop_index':
                if (preg_match('/^(\w+)\.(\w+)$/', $arg, $k)) {
                    $out['drop_index'][] = ['table' => $k[1], 'index' => $k[2]];
                }
                break;
            case 'drop_table':
                foreach (preg_split('/[\s,]+/', $arg) as $t) { if ($t !== '') $out['drop_table'][] = $t; }
                break;
        }
    }
    return $cache[$type] = $out;
}

// 取某表的收敛模式
function schema_table_sync_mode(string $table, string $type = 'mysql'): string {
    $d = schema_directives($type);
    if (in_array($table, $d['sync'], true)) return 'full';
    if (in_array($table, $d['sync_keep_extra'], true)) return 'keep-extra';
    return '';
}

// 取列定义中的类型
function schema_def_type(string $def): string {
    $part = preg_split('/\b(NOT|NULL|DEFAULT|AUTO_INCREMENT|COMMENT|ON|PRIMARY|UNIQUE|KEY)\b/i', trim($def), 2);
    return strtoupper(trim(preg_replace('/\s+/', ' ', $part[0] ?? '')));
}

// 取列定义中的默认值
function schema_def_default(string $def): ?string {
    if (!preg_match('/\bDEFAULT\s+(\'[^\']*\'|"[^"]*"|[^\s,]+)/i', $def, $m)) return null;
    return trim($m[1], "'\"");
}