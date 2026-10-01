<?php

require_once __DIR__ . '/schema_parse.php';
require_once __DIR__ . '/schema_directives.php';

// 取期望的表与列
function get_migrations(): array {
    $tables = schema_tables();
    foreach (schema_legacy_skip_columns() as $table => $cols) {
        if (!isset($tables[$table])) continue;
        foreach ($cols as $c) unset($tables[$table][$c]);
    }
    return $tables;
}

// 取期望的索引
function get_migration_indexes(): array {
    return schema_indexes();
}

// 取期望的主键
function get_migration_primary_keys(): array {
    $pks    = schema_primary_keys();
    $tables = get_migrations();
    foreach ($pks as $table => $cols) {
        if (!is_array($cols) || !$cols) { unset($pks[$table]); continue; }
        $have = $tables[$table] ?? [];
        foreach ($cols as $c) {
            if (!isset($have[$c])) { unset($pks[$table]); break; }
        }
    }
    return $pks;
}