<?php

function upgrade_mysql(array $cfg): void {
    try {
        $c     = $cfg['db'];
        $hosts = $c['hosts'] ?? ['127.0.0.1'];
        $host  = is_array($hosts) ? $hosts[0] : $hosts;
        $dsn   = "mysql:host={$host};port={$c['port']};dbname={$c['database']};charset=utf8mb4";
        $db    = new PDO($dsn, $c['user'], $c['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 3,
        ]);

        // 结构（表 / 列 / 索引）统一同步：安装器与"每次访问的增量"走同一个函数
        schema_sync_mysql($db);

        seed_config($db, 'mysql');

        try {
            $st = $db->query("SHOW COLUMNS FROM `mapi_songs` LIKE 'key_id'");
            if ($st && $st->fetch()) {
                $db->exec("ALTER TABLE `mapi_songs` MODIFY `key_id` INT NOT NULL DEFAULT 0");
            }
        } catch (Throwable $e) {
            error_log("MAPI upgrade: failed to modify mapi_songs.key_id: " . $e->getMessage());
        }

        $dropCols = ['playlist_id', 'server', 'playlists'];
        foreach ($dropCols as $dc) {
            try {
                $st = $db->query("SHOW COLUMNS FROM `mapi_keys` LIKE '{$dc}'");
                if ($st && $st->fetch()) {
                    $db->exec("ALTER TABLE `mapi_keys` DROP COLUMN `{$dc}`");
                }
            } catch (Throwable $e) {
                error_log("MAPI upgrade: failed to drop mapi_keys.{$dc}: " . $e->getMessage());
            }
        }

        $db = null;
    } catch (Throwable $e) {
        error_log("MAPI upgrade error: " . $e->getMessage());
    }
}

// 同步表结构（安装与每次访问共用）
function schema_sync_mysql(PDO $db): void {
    $migrations = get_migrations();
    $dir        = schema_directives();

    // 按指令删除表
    foreach ($dir['drop_table'] as $t) {
        if (!preg_match('/^\w+$/', $t)) continue;
        try {
            $st = $db->query("SHOW TABLES LIKE " . $db->quote($t));
            if ($st && $st->fetch()) {
                $db->exec("DROP TABLE `{$t}`");
                error_log("MAPI schema: 按 @drop-table 删除表 {$t}");
            }
        } catch (Throwable $e) {
            error_log("MAPI schema: drop table {$t} failed: " . $e->getMessage());
        }
    }
    foreach ($dir['drop_index'] as $d) {
        if (!preg_match('/^\w+$/', $d['table']) || !preg_match('/^\w+$/', $d['index'])) continue;
        try {
            $has = false;
            foreach ($db->query("SHOW INDEX FROM `{$d['table']}`") as $r) {
                if ($r['Key_name'] === $d['index']) { $has = true; break; }
            }
            if ($has) {
                $db->exec("DROP INDEX `{$d['index']}` ON `{$d['table']}`");
                error_log("MAPI schema: 按 @drop-index 删除 {$d['table']}.{$d['index']}");
            }
        } catch (Throwable $e) {
            error_log("MAPI schema: drop index {$d['table']}.{$d['index']} failed: " . $e->getMessage());
        }
    }
    foreach ($dir['drop_column'] as $d) {
        if (!preg_match('/^\w+$/', $d['table']) || !preg_match('/^\w+$/', $d['col'])) continue;
        try {
            $has = false;
            foreach ($db->query("SHOW COLUMNS FROM `{$d['table']}`") as $r) {
                if ($r['Field'] === $d['col']) { $has = true; break; }
            }
            if ($has) {
                $db->exec("ALTER TABLE `{$d['table']}` DROP COLUMN `{$d['col']}`");
                error_log("MAPI schema: 按 @drop-column 删除 {$d['table']}.{$d['col']}");
            }
        } catch (Throwable $e) {
            error_log("MAPI schema: drop column {$d['table']}.{$d['col']} failed: " . $e->getMessage());
        }
    }

    // 同步表与列
    foreach ($migrations as $table => $columns) {
        $st = $db->query("SHOW TABLES LIKE '{$table}'");
        $tableExists = $st && $st->fetch();

        if (!$tableExists) {
            create_mysql_table($db, $table, $columns);
            continue;
        }

        $st = $db->query("SHOW COLUMNS FROM `{$table}`");
        $existingCols = [];
        while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
            $existingCols[$row['Field']] = true;
        }

        // 补列
        foreach ($columns as $colName => $colDef) {
            if ($colName === 'id') {
                continue;
            }
            if (!isset($existingCols[$colName])) {
                // 该列曾补列失败
                if (migration_skipped($db, $table, 'col_' . $colName)) continue;
                $def = adapt_col_def($colDef);
                $after = '';
                $keys = array_keys($columns);
                $idx = array_search($colName, $keys);
                if ($idx > 1) {
                    $prev = $keys[$idx - 1];
                    if ($prev !== 'id') {
                        $after = " AFTER `{$prev}`";
                    }
                }
                try {
                    $db->exec("ALTER TABLE `{$table}` ADD COLUMN `{$colName}` {$def}{$after}");
                } catch (Throwable $e) {
                    error_log("MAPI upgrade: failed to add {$table}.{$colName}: " . $e->getMessage());
                    migration_mark_skipped($db, $table, 'col_' . $colName);
                }
            }
        }

        // 按 @modify 改列
        foreach ($dir['modify'] as $m) {
            if ($m['table'] === $table) mysql_modify_column($db, $table, $m['col'], $m['def']);
        }

        // 按 @sync 收敛表
        $mode = schema_table_sync_mode($table);
        if ($mode !== '') {
            foreach ($columns as $colName => $colDef) {
                if ($colName === 'id' || !isset($existingCols[$colName])) continue;
                mysql_modify_column($db, $table, $colName, $colDef);
            }
            if ($mode === 'full') {
                $legacySkip = schema_legacy_skip_columns()[$table] ?? [];
                foreach ($existingCols as $colName => $_) {
                    if (isset($columns[$colName]) || in_array($colName, $legacySkip, true)) continue;
                    try {
                        $db->exec("ALTER TABLE `{$table}` DROP COLUMN `{$colName}`");
                        error_log("MAPI schema: @sync {$table} 删除多余列 {$colName}");
                    } catch (Throwable $e) {
                        error_log("MAPI schema: @sync {$table} drop {$colName} failed: " . $e->getMessage());
                    }
                }
            }
        }
    }

    // 同步索引与主键
    apply_mysql_indexes($db, get_migration_indexes());
}

// 按声明修改一列
function mysql_modify_column(PDO $db, string $table, string $col, string $def): void {
    if (!preg_match('/^\w+$/', $table) || !preg_match('/^\w+$/', $col)) return;
    $def  = adapt_col_def($def);
    $key  = '_schema_def_' . $table . '.' . $col;
    $hash = md5($def);
    if (schema_kv_get($db, $key) === $hash) return;
    try {
        $exists = false;
        foreach ($db->query("SHOW COLUMNS FROM `{$table}`") as $r) {
            if ($r['Field'] === $col) { $exists = true; break; }
        }
        if (!$exists) return;                                   // 列不存在时交给"补列"逻辑
        $db->exec("ALTER TABLE `{$table}` MODIFY COLUMN `{$col}` {$def}");
        schema_kv_set($db, $key, $hash);
        error_log("MAPI schema: 已按声明更新列 {$table}.{$col} => {$def}");
    } catch (Throwable $e) {
        error_log("MAPI schema: modify {$table}.{$col} failed: " . $e->getMessage());
        schema_kv_set($db, $key, $hash);                        // 记下已尝试，避免刷日志（要重试就删该 config_key）
    }
}

// 读取结构标记
function schema_kv_get(PDO $db, string $key): ?string {
    try {
        $q = $db->prepare("SELECT config_value FROM mapi_config WHERE config_key=? LIMIT 1");
        $q->execute([$key]);
        $v = $q->fetchColumn();
        return $v === false ? null : (string)$v;
    } catch (Throwable $e) {
        return null;
    }
}

function schema_kv_set(PDO $db, string $key, string $value): void {
    try {
        $q = $db->prepare("SELECT COUNT(*) FROM mapi_config WHERE config_key=?");
        $q->execute([$key]);
        if ((int)$q->fetchColumn() > 0) {
            $db->prepare("UPDATE mapi_config SET config_value=? WHERE config_key=?")->execute([$value, $key]);
        } else {
            $db->prepare("INSERT INTO mapi_config (config_key, config_value) VALUES (?, ?)")->execute([$key, $value]);
        }
    } catch (Throwable $e) {
    }
}

// 建表
function create_mysql_table(PDO $db, string $table, array $columns, ?array $primary = null): void {
    if ($primary === null) $primary = get_migration_primary_keys()[$table] ?? null;
    $defs    = [];
    $hasId   = false;
    foreach ($columns as $name => $def) {
        if ($name === 'id') {
            $defs[] = "`id` {$def}" . ($primary ? '' : ' PRIMARY KEY');
            $hasId = true;
        } else {
            $defs[] = "`{$name}` {$def}";
        }
    }
    if ($primary) {
        $defs[] = 'PRIMARY KEY (' . implode(',', array_map(function ($c) { return "`{$c}`"; }, $primary)) . ')';
    } elseif (!$hasId) {
        $defs[] = "`id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY";
    }
    $sql = "CREATE TABLE IF NOT EXISTS `{$table}` (" . implode(",\n", $defs) . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    try {
        $db->exec($sql);
    } catch (Throwable $e) {
        error_log("MAPI upgrade: failed to create {$table}: " . $e->getMessage());
    }
}

function adapt_col_def(string $def): string {
    $def = preg_replace('/\s+AUTO_INCREMENT/i', '', $def);
    return $def;
}

// 同步主键与索引
function apply_mysql_indexes(PDO $db, array $indexes): void {
    $pkMap  = get_migration_primary_keys();
    $tables = array_values(array_unique(array_merge(array_keys($indexes), array_keys($pkMap))));
    foreach ($tables as $table) {
        $keys = $indexes[$table] ?? [];
        try {
            $st = $db->query("SHOW TABLES LIKE " . $db->quote($table));
            if (!$st || !$st->fetch()) continue;
            $have = [];
            foreach ($db->query("SHOW INDEX FROM `{$table}`") as $r) { $have[$r['Key_name']] = true; }
        } catch (Throwable $e) {
            continue;
        }

        // 补主键
        $primary = $pkMap[$table] ?? null;
        if ($primary && !isset($have['PRIMARY']) && !migration_skipped($db, $table, 'pk')) {
            $pkCols = '`' . implode('`,`', $primary) . '`';
            try {
                $db->exec("ALTER TABLE `{$table}` ADD PRIMARY KEY ({$pkCols})");
            } catch (Throwable $e) {
                error_log("MAPI upgrade: add primary key {$table} failed: " . $e->getMessage());
                migration_mark_skipped($db, $table, 'pk');
            }
        }

        foreach ($keys as $name => $k) {
            if (isset($have[$name])) {
                // 已存在：比对列/唯一性，定义漂移就重建（改索引不动数据）
                if (!mysql_index_drifted($db, $table, $name, $k)) continue;
                try {
                    $db->exec("DROP INDEX `{$name}` ON `{$table}`");
                    error_log("MAPI schema: 重建索引 {$table}.{$name}（定义与声明不一致）");
                } catch (Throwable $e) {
                    error_log("MAPI schema: drop index {$table}.{$name} failed: " . $e->getMessage());
                    migration_mark_skipped($db, $table, $name);
                    continue;
                }
            }
            if (migration_skipped($db, $table, $name)) continue;
            $cols = '`' . implode('`,`', $k['cols']) . '`';

            // 清理封面缓存重复行
            if (!empty($k['unique']) && $table === 'mapi_song_covers') {
                try {
                    $db->exec("DELETE c1 FROM `mapi_song_covers` c1 INNER JOIN `mapi_song_covers` c2 ON c1.song_ref=c2.song_ref AND c1.id < c2.id");
                } catch (Throwable $e) { /* 忽略：下面按结果处理 */ }
            }

            $done = false;
            try {
                $db->exec("CREATE " . (!empty($k['unique']) ? 'UNIQUE ' : '') . "INDEX `{$name}` ON `{$table}` ({$cols})");
                $done = true;
            } catch (Throwable $e) {
                error_log("MAPI upgrade: index {$table}.{$name} failed: " . $e->getMessage());
            }
            if (!$done && !empty($k['unique'])) {
                // 唯一索引失败时改普通索引
                try {
                    $db->exec("CREATE INDEX `{$name}` ON `{$table}` ({$cols})");
                    $done = true;
                    error_log("MAPI upgrade: {$table}.{$name} 唯一索引失败（可能有重复值），已降级为普通索引");
                } catch (Throwable $e2) {
                    error_log("MAPI upgrade: index {$table}.{$name} fallback failed: " . $e2->getMessage());
                }
            }
            if (!$done) migration_mark_skipped($db, $table, $name);
        }
    }
}

// 判断索引定义是否不同
function mysql_index_drifted(PDO $db, string $table, string $name, array $want): bool {
    try {
        $cols = [];
        $uniq = null;
        foreach ($db->query("SHOW INDEX FROM `{$table}`") as $r) {
            if ($r['Key_name'] !== $name) continue;
            $uniq = ((int)$r['Non_unique'] === 0);
            $cols[(int)$r['Seq_in_index']] = $r['Column_name'];
        }
        if ($uniq === null) return false;
        ksort($cols);
        if (array_values($cols) !== $want['cols']) return true;
        return $uniq !== !empty($want['unique']);
    } catch (Throwable $e) {
        return false;
    }
}

// 判断是否已记录跳过
function migration_skipped(PDO $db, string $table, string $name): bool {
    try {
        $q = $db->prepare("SELECT COUNT(*) FROM mapi_config WHERE config_key=?");
        $q->execute(['_mig_skip_' . $table . '_' . $name]);
        return (int)$q->fetchColumn() > 0;
    } catch (Throwable $e) {
        return false;
    }
}

function migration_mark_skipped(PDO $db, string $table, string $name): void {
    $key = '_mig_skip_' . $table . '_' . $name;
    try {
        $q = $db->prepare("SELECT COUNT(*) FROM mapi_config WHERE config_key=?");
        $q->execute([$key]);
        if ((int)$q->fetchColumn() > 0) {
            $db->prepare("UPDATE mapi_config SET config_value='1' WHERE config_key=?")->execute([$key]);
        } else {
            $db->prepare("INSERT INTO mapi_config (config_key, config_value) VALUES (?, '1')")->execute([$key]);
        }
    } catch (Throwable $e) {
    }
}
