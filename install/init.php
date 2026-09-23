<?php
/**
 * MSAPI 安装后台 API 路由
 * 处理 AJAX 请求：环境检测、权限检查、连接测试、初始化
 */
error_reporting(E_ALL);
ini_set('display_errors', '0');

$root       = dirname(__DIR__);
$self       = __DIR__;
$configFile = $root . '/config/config.php';
$lockFile   = $self . '/install.lock';
$sqlDir     = $root . '/assets/sql';
$dbFile     = $sqlDir . '/msapi.db';

$input  = json_decode(file_get_contents('php://input'), true) ?: [];
$action = $_GET['action'] ?? $input['action'] ?? '';

require_once __DIR__ . '/lib/functions.php';

if (in_array($action, ['init_sqlite', 'init_mysql'], true)) {
    ini_set('output_buffering', 'Off');
    ini_set('zlib.output_compression', 'Off');
    while (ob_get_level()) ob_end_clean();
    header('Content-Type: application/x-ndjson; charset=utf-8');
    header('Cache-Control: no-cache');
    header('X-Accel-Buffering: no');
    header('Access-Control-Allow-Origin: *');
    require __DIR__ . '/handlers/' . $action . '.php';
    exit;
}

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

$result = match ($action) {
    'check_env'      => require __DIR__ . '/handlers/check_env.php',
    'check_writable' => require __DIR__ . '/handlers/check_writable.php',
    'test_mysql'     => require __DIR__ . '/handlers/test_mysql.php',
    default          => ['ok' => false, 'error' => '未知操作: ' . $action],
};

echo json_encode($result, JSON_UNESCAPED_UNICODE);