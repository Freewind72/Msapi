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

        $migrations = get_migrations();

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

            foreach ($columns as $colName => $colDef) {
                if ($colName === 'id') {
                    continue;
                }
                if (!isset($existingCols[$colName])) {
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
                    }
                }
            }
        }

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

function create_mysql_table(PDO $db, string $table, array $columns): void {
    $defs   = [];
    $hasId = false;
    foreach ($columns as $name => $def) {
        if ($name === 'id') {
            $defs[] = "`id` {$def} PRIMARY KEY";
            $hasId = true;
        } else {
            $defs[] = "`{$name}` {$def}";
        }
    }
    if (!$hasId) {
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