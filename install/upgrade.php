<?php
// MSAPI 增量建表/升级 调度入口
defined('MAPI_ADMIN') or define('MAPI_ADMIN', false);

$cfg     = null;
$cfgFile = dirname(__DIR__) . '/config/config.php';
if (file_exists($cfgFile)) {
    $cfg = require $cfgFile;
}

if (!$cfg || empty($cfg['db']['type'])) {
    return;
}

require_once __DIR__ . '/lib/migrations.php';
require_once __DIR__ . '/lib/config_seeder.php';

$dbType = $cfg['db']['type'];

if ($dbType === 'sqlite') {
    require_once __DIR__ . '/lib/up_sqlite.php';
    upgrade_sqlite($cfg);
} elseif ($dbType === 'mysql' || $dbType === 'mariadb') {
    require_once __DIR__ . '/lib/up_mysql.php';
    upgrade_mysql($cfg);
}