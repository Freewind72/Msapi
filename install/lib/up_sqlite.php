<?php

function upgrade_sqlite(array $cfg): void {
    try {
        $path = $cfg['db']['path'] ?? '';
        if (!$path) {
            return;
        }

        $path = $path[0] === '/' || preg_match('#^[A-Z]:#i', $path) ? $path : dirname(__DIR__, 2) . '/' . $path;
        if (!file_exists($path)) {
            return;
        }
        $db   = new SQLite3($path);
        $db->enableExceptions(true);

        $migrations = get_migrations();

        foreach ($migrations as $table => $columns) {
            $st = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='{$table}'");
            $tableExists = $st && $st->fetchArray();

            if (!$tableExists) {
                create_sqlite_table($db, $table, $columns);
                continue;
            }

            $st = $db->query("PRAGMA table_info({$table})");
            $existingCols = [];
            while ($row = $st->fetchArray(SQLITE3_ASSOC)) {
                $existingCols[$row['name']] = true;
            }

            $needsRebuild = false;
            foreach ($columns as $colName => $colDef) {
                if (!isset($existingCols[$colName])) {
                    $needsRebuild = true;
                    break;
                }
            }
            if (!$needsRebuild) {
                $dropCols = ['playlist_id', 'server', 'playlists'];
                if ($table === 'mapi_keys') {
                    foreach ($dropCols as $dc) {
                        if (isset($existingCols[$dc])) {
                            $needsRebuild = true;
                            break;
                        }
                    }
                }
            }

            if ($needsRebuild) {
                rebuild_sqlite_table($db, $table, $columns, $existingCols);
            }
        }

        seed_config($db, 'sqlite');
        $db->close();
    } catch (Throwable $e) {
        error_log("MAPI upgrade (SQLite): " . $e->getMessage());
    }
}

function create_sqlite_table(SQLite3 $db, string $table, array $columns): void {
    $defs = [];
    foreach ($columns as $name => $def) {
        $sqliteDef = mysql_to_sqlite_type($def);
        if ($name === 'id' && stripos($def, 'AUTO_INCREMENT') !== false) {
            $defs[] = "id INTEGER PRIMARY KEY AUTOINCREMENT";
        } else {
            $defs[] = "\"{$name}\" {$sqliteDef}";
        }
    }
    $sql = "CREATE TABLE IF NOT EXISTS \"{$table}\" (\n  " . implode(",\n  ", $defs) . "\n)";
    $db->exec($sql);
}

function rebuild_sqlite_table(SQLite3 $db, string $table, array $desired, array $existing): void {
    $temp   = "{$table}_migrate_" . time();
    $columns    = [];
    $commonCols = [];
    foreach ($desired as $name => $def) {
        $sqliteDef = mysql_to_sqlite_type($def);
        if ($name === 'id' && stripos($def, 'AUTO_INCREMENT') !== false) {
            $columns[] = "\"{$name}\" INTEGER PRIMARY KEY AUTOINCREMENT";
        } else {
            $columns[] = "\"{$name}\" {$sqliteDef}";
        }
        if (isset($existing[$name])) {
            $commonCols[] = "\"{$name}\"";
        }
    }
    $colNames = implode(', ', $commonCols);

    try {
        $db->exec("CREATE TABLE \"{$temp}\" (\n  " . implode(",\n  ", $columns) . "\n)");
        $db->exec("INSERT INTO \"{$temp}\" ({$colNames}) SELECT {$colNames} FROM \"{$table}\"");
        $db->exec("DROP TABLE \"{$table}\"");
        $db->exec("ALTER TABLE \"{$temp}\" RENAME TO \"{$table}\"");
    } catch (Throwable $e) {
        error_log("MAPI upgrade: failed to rebuild {$table}: " . $e->getMessage());
        @$db->exec("DROP TABLE IF EXISTS \"{$temp}\"");
    }
}

function mysql_to_sqlite_type(string $def): string {
    $def = strtoupper($def);
    if (strpos($def, 'BIGINT') !== false)   { return 'INTEGER'; }
    if (strpos($def, 'TINYINT') !== false)  { return 'INTEGER'; }
    if (strpos($def, 'INT') !== false)      { return 'INTEGER'; }
    if (strpos($def, 'DECIMAL') !== false)  { return 'REAL'; }
    if (strpos($def, 'FLOAT') !== false)    { return 'REAL'; }
    if (strpos($def, 'DOUBLE') !== false)   { return 'REAL'; }
    if (strpos($def, 'DATETIME') !== false) { return 'TEXT'; }
    if (strpos($def, 'DATE') !== false)     { return 'TEXT'; }
    if (strpos($def, 'TEXT') !== false)     { return 'TEXT'; }
    if (strpos($def, 'BLOB') !== false)     { return 'BLOB'; }
    return 'TEXT';
}