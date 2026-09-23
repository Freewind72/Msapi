<?php

try {
    if (!is_dir($sqlDir)) {
        @mkdir($sqlDir, 0755, true);
        stream_line('[建立] assets/sql/ 目录');
    }

    $db = new SQLite3($dbFile);
    $db->enableExceptions(true);
    $db->exec('PRAGMA journal_mode=WAL');
    $db->exec('PRAGMA foreign_keys=ON');
    stream_line('[建立] 数据库文件 assets/sql/msapi.db');

    $ourTables = array_keys(get_table_definitions('sqlite'));
    $existing  = $db->query("SELECT name FROM sqlite_master WHERE type='table'");
    $cleaned   = 0;
    while ($row = $existing->fetchArray(SQLITE3_ASSOC)) {
        $tbl = $row['name'];
        if (!in_array($tbl, $ourTables) && strpos($tbl, 'mapi_') === 0) {
            $db->exec("DROP TABLE IF EXISTS \"{$tbl}\"");
            stream_line("[清理] 删除冗余表 {$tbl}");
            $cleaned++;
        }
    }
    if ($cleaned === 0) {
        stream_line('[清理] 无冗余');
    }

    $tables = get_table_definitions('sqlite');
    foreach ($tables as $name => $sql) {
        $db->exec($sql);
        stream_line("[建表] {$name}");
    }

    $username = $input['username'] ?? 'admin';
    $password = password_hash($input['password'], PASSWORD_BCRYPT);
    $qq    = $input['qq'] ?? '';
    $email = $input['email'] ?? '';

    $stmt = $db->prepare("INSERT OR REPLACE INTO mapi_users (id, username, password, qq, email, is_admin, expire_at) VALUES (1, ?, ?, ?, ?, 0, '2099-12-31 23:59:59')");
    $stmt->bindValue(1, $username, SQLITE3_TEXT);
    $stmt->bindValue(2, $password, SQLITE3_TEXT);
    $stmt->bindValue(3, $qq, SQLITE3_TEXT);
    $stmt->bindValue(4, $email, SQLITE3_TEXT);
    $stmt->execute();
    stream_line('[用户] 管理员创建完成');

    $defaultKeys = [
        'key_limit'   => json_encode(['limit' => 1]),
        'login_bg'    => '',
        'login_theme' => 'light',
    ];
    foreach ($defaultKeys as $k => $v) {
        $db->exec("INSERT OR IGNORE INTO mapi_config (config_key, config_value) VALUES ('{$k}', '{$v}')");
    }
    stream_line('[配置] 默认配置已写入');

    @mkdir(dirname($configFile), 0755, true);
    $config = [
        'db' => [
            'type' => 'sqlite',
            'path' => 'assets/sql/msapi.db',
        ],
        'site' => [
            'name' => 'MSAPI',
            'url'  => get_site_url(),
        ],
        'admin' => [
            'rate_limit'  => 5,
            'rate_window' => 60,
        ],
        'api' => [
            'qq_referer' => 'https://y.qq.com',
            'qq_cover'   => 'https://y.qq.com',
        ],
    ];
    $configContent = "<?php\nreturn " . var_export($config, true) . ";\n";
    file_put_contents($configFile, $configContent);
    stream_line('[写入] config/config.php');

    $lockContent = "install_time: " . date('Y-m-d H:i:s') . "\n"
        . "install_type: sqlite\n"
        . "php_version: " . PHP_VERSION . "\n"
        . "signature: msapi\n";
    file_put_contents($lockFile, $lockContent);
    @mkdir($self . '/assets', 0755, true);
    file_put_contents($self . '/assets/sha256', hash_file('sha256', $lockFile));
    stream_line('[写入] install/install.lock');

    stream_done();
} catch (Throwable $e) {
    stream_error($e->getMessage());
}