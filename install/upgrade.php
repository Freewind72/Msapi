<?php
/**
 * MSAPI 增量建表/升级 调度入口
 *
 * 每次访问首页/后台时 require 此文件。
 * 检测缺失的表和列，自动补齐。
 * 后续迭代只需在 lib/migrations.php 追加新定义。
 */
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