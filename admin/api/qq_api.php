<?php
declare(strict_types=1);

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Max-Age: 86400');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

$CFG = require __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../assets/lib/db.php';
require_once __DIR__ . '/../../assets/lib/api_config.php';
require_once __DIR__ . '/../../assets/lib/helpers.php';

$ua     = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36';

$db    = db_connect();
$api   = read_mapi_api_config($db, $CFG);
$pId   = $api['param_id'];
$pAuth = $api['param_auth'];
$rServer = $api['param_server'];
$rType   = $api['param_type'];
$rId     = $api['param_id'];

$action = $_GET['action'] ?? '';
$mid    = $_GET[$pId] ?? '';
$auth   = $_GET[$pAuth] ?? '';

match ($action) {
    'url' => (function() use ($mid, $api, $ua): void {
        if (!$mid) { http_response_code(400); echo json_encode(['error' => '缺少 id 参数']); exit; }
        $src = resolve_play_url($mid, $api['base_url'], $ua, $api['qq_referer'], 'tencent', $api['param_server'], $api['param_type'], $api['param_id']);
        if ($src) {
            header('Location: ' . $src, true, 302);
        } else {
            http_response_code(404);
            echo json_encode(['error' => '无法获取播放地址']);
        }
    })(),
    default => (function(): void {
        http_response_code(400);
        echo json_encode(['error' => '不支持的操作']);
    })(),
};