<?php
declare(strict_types=1);

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, Range');
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
        $src = resolve_play_url($mid, $api['base_url'], $ua, $api['qq_referer'], 'netease', $api['param_server'], $api['param_type'], $api['param_id']);
        if (!$src) { http_response_code(404); echo json_encode(['error' => '无法获取播放地址']); exit; }
        proxy_audio($src, $ua);
    })(),
    default => (function(): void {
        http_response_code(400);
        echo json_encode(['error' => '不支持的操作']);
    })(),
};

function proxy_audio(string $url, string $ua): void {
    $url = preg_replace('/^http:/i', 'https:', $url);

    $respHeaders = [];
    $rangeOffset = 0;
    $rangeEnd = 0;
    $isRange = false;
    $totalSize = 0;

    if (!empty($_SERVER['HTTP_RANGE'])) {
        if (preg_match('/bytes=(\d+)-(\d*)/', $_SERVER['HTTP_RANGE'], $m)) {
            $rangeOffset = (int)$m[1];
            $rangeEnd = $m[2] !== '' ? (int)$m[2] : 0;
            $isRange = true;
        }
    }

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_USERAGENT => $ua,
        CURLOPT_REFERER => 'https://music.163.com/',
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HEADERFUNCTION => function($ch, string $line) use (&$respHeaders): int {
            $len = strlen($line);
            $parts = explode(':', $line, 2);
            if (count($parts) === 2) {
                $respHeaders[strtolower(trim($parts[0]))] = trim($parts[1]);
            } elseif (preg_match('/^HTTP\//i', $line)) {
                $respHeaders['_http'] = trim($line);
            }
            return $len;
        },
        CURLOPT_WRITEFUNCTION => function($ch, string $data) use (&$respHeaders, &$isRange, &$rangeOffset, &$rangeEnd, &$totalSize): int {
            static $sent = false;
            if (!$sent) {
                $sent = true;
                $httpCode = 200;
                if (!empty($respHeaders['_http']) && preg_match('/\s(\d{3})\s/', $respHeaders['_http'], $m)) {
                    $httpCode = (int)$m[1];
                }
                if ($httpCode !== 200 && $httpCode !== 206) {
                    http_response_code(502);
                    exit('upstream error: ' . $httpCode);
                }
                // 206 时 Content-Length 是分片大小，需从 Content-Range 取完整文件大小
                $totalSize = (int)($respHeaders['content-length'] ?? 0);
                if ($httpCode === 206 && preg_match('/bytes\s+\d+-\d+\/(\d+)/i', $respHeaders['content-range'] ?? '', $rm)) {
                    $totalSize = (int)$rm[1];
                }
                if ($isRange && $totalSize > 0) {
                    if ($rangeEnd <= 0) $rangeEnd = $totalSize - 1;
                    http_response_code(206);
                    header('Content-Range: bytes ' . $rangeOffset . '-' . $rangeEnd . '/' . $totalSize);
                    header('Content-Length: ' . ($rangeEnd - $rangeOffset + 1));
                } else {
                    http_response_code(200);
                    if ($totalSize > 0) header('Content-Length: ' . $totalSize);
                }
                header('Content-Type: ' . ($respHeaders['content-type'] ?? 'audio/mpeg'));
                header('Cache-Control: no-cache, no-store, must-revalidate');
                header('Pragma: no-cache');
                header('Expires: 0');
                header('Accept-Ranges: bytes');
            }
            echo $data;
            flush();
            return strlen($data);
        },
    ]);

    if ($isRange) {
        curl_setopt($ch, CURLOPT_RANGE, $rangeOffset . '-' . ($rangeEnd > 0 ? $rangeEnd : ''));
    }

    curl_exec($ch);
    exit;
}