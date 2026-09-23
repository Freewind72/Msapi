<?php
declare(strict_types=1);

/**
 * api.php — 音乐 API 主入口
 * 根据 server 参数分发到对应音源 API
 * tencent → qq_api.php（302 重定向，无 CORS 问题）
 * netease → wy_api.php（服务端代理音频流，绕过 CORS）
 */
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Max-Age: 86400');
header('Timing-Allow-Origin: *');

$_current_api_key = '';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

rate_limit_check('api', 120, 60);

$CFG = require __DIR__ . '/../../config/config.php';
$jwt_secret = $CFG['api']['jwt_secret'] ?? hash('sha256', ($CFG['db']['password'] ?? '') . ($CFG['site']['url'] ?? ''));
require __DIR__ . '/../../assets/lib/db.php';
require __DIR__ . '/../../assets/lib/api_config.php';
require __DIR__ . '/../../assets/lib/helpers.php';

$db_log = db_connect();
$api    = read_mapi_api_config($db_log, $CFG);

$ua     = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36';

// 常量别名（兼容后续代码引用）
$apiBase = $api['base_url'];
$qqRef   = $api['qq_referer'];
$qqCover = $api['qq_cover'];
$fTitle  = $api['field_title'];
$fArtist = $api['field_artist'];
$fUrl    = $api['field_url'];
$fPic    = $api['field_pic'];
$fLrc    = $api['field_lrc'];
$pId     = $api['param_id'];
$pAuth   = $api['param_auth'];
$rServer = $api['param_server'];
$rType   = $api['param_type'];
$rId     = $api['param_id'];

$action = $_GET['action'] ?? '';
$id     = $_GET['id'] ?? '';
$limit  = min((int)($_GET['limit'] ?? 10), 50);

require __DIR__ . '/../../assets/lib/jwt.php';

function auth_required(): string {
    global $jwt_secret;
    $token = '';
    if (preg_match('/^Bearer\s+(.+)$/i', $_SERVER['HTTP_AUTHORIZATION'] ?? '', $m)) {
        $token = $m[1];
    }
    $token = $token ?: ($_GET['token'] ?? '');
    if (!$token) { http_response_code(401); json_exit(['error' => '未授权，请提供 token', 'code' => 401]); }
    $data = jwt_decode($token, $jwt_secret);
    if (!$data || empty($data['key'])) { http_response_code(401); json_exit(['error' => 'token 无效或已过期', 'code' => 401]); }
    $cookieSid = $_COOKIE['mapi_sid'] ?? '';
    $tokenSid  = $data['sid'] ?? '';
    if ($tokenSid && $cookieSid && hash_equals($tokenSid, $cookieSid)) {
        setcookie('mapi_sid', $cookieSid, ['expires' => time() + 86400, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax']);
    }
    return $data['key'];
}

switch ($action) {
    case 'playlist':
        $_current_api_key = auth_required();
        if (!$id) json_error('缺少 id 参数');
        $server = $_GET['server'] ?? 'tencent';
        $server = in_array($server, ['tencent', 'netease'], true) ? $server : 'tencent';
        $url = Uri\Rfc3986\Uri::parse($apiBase)->withQuery(http_build_query([$rServer => $server, $rType => 'playlist', $rId => $id]))->toString();
        $raw = http_get($url, $ua, $qqRef);
        $songs = $raw ? json_decode($raw, true) : [];
        if (!$songs || !is_array($songs)) { json_exit([]); }
        $songs = array_slice($songs, 0, $limit);
        $result = [];
        $lrcUrls = [];
        foreach ($songs as $s) {
            $mid = '';
            if (!empty($s[$fLrc]) && preg_match('/[?&]' . preg_quote($pId, '/') . '=([^&]+)/', $s[$fLrc], $m)) {
                $mid = $m[1];
            } elseif (!empty($s[$fUrl]) && preg_match('/[?&]' . preg_quote($pId, '/') . '=([^&]+)/', $s[$fUrl], $m)) {
                $mid = $m[1];
            }
            $picUrl = '';
            if (!empty($s[$fPic])) {
                if (preg_match('/^https?:\/\//', $s[$fPic])) {
                    $picUrl = $s[$fPic];
                } else {
                    $picId = '';
                    $picAuth = '';
                    if (preg_match('/[?&]' . preg_quote($pId, '/') . '=([^&]+)/', $s[$fPic], $m)) {
                        $picId = $m[1];
                    }
                    if (preg_match('/[?&]' . preg_quote($pAuth, '/') . '=([^&]+)/', $s[$fPic], $m)) {
                        $picAuth = $m[1];
                    }
                    if ($picId) {
                        $queryParams = ['action' => 'pic', 'server' => $server, $pId => $picId];
                        if ($picAuth) $queryParams[$pAuth] = $picAuth;
                        $picUrl = '?' . http_build_query($queryParams);
                    }
                }
            }
            $playUrl = '';
            if ($server === 'netease') {
                if ($mid) {
                    $playUrl = resolve_play_url($mid, $apiBase, $ua, $qqRef, $server, $rServer, $rType, $rId);
                }
            } else {
                if (!empty($s[$fUrl])) {
                    $playUrl = preg_replace('/^http:/i', 'https:', $s[$fUrl]);
                }
            }
            $result[] = [
                'id'     => $mid,
                'name'   => $s[$fTitle] ?? '未知',
                'artist' => $s[$fArtist] ?? '',
                'url'    => $playUrl,
                'pic'    => $picUrl,
                'lrc'    => '',
            ];
            if ($mid && !empty($s[$fLrc])) {
                $lrcUrls[$mid] = $s[$fLrc];
            }
        }
        if (!empty($lrcUrls)) {
            $mh = curl_multi_init();
            $channels = [];
            foreach ($lrcUrls as $mid => $lrcUrl) {
                $lrcUrl = preg_replace('/^http:/i', 'https:', $lrcUrl);
                $ch = curl_init();
                curl_setopt_array($ch, [
                    CURLOPT_URL => $lrcUrl,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT => 10,
                    CURLOPT_CONNECTTIMEOUT => 5,
                    CURLOPT_SSL_VERIFYPEER => false,
                    CURLOPT_USERAGENT => $ua,
                    CURLOPT_REFERER => $qqRef,
                    CURLOPT_FOLLOWLOCATION => true,
                ]);
                curl_multi_add_handle($mh, $ch);
                $channels[$mid] = $ch;
            }
            $running = null;
            do {
                curl_multi_exec($mh, $running);
                curl_multi_select($mh, 0.3);
            } while ($running > 0);
            foreach ($channels as $mid => $ch) {
                $raw = curl_multi_getcontent($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                if ($httpCode === 200 && $raw) {
                    // netease 歌词返回 JSON，需解包提取纯 LRC 文本
                    if ($server === 'netease') {
                        $decoded = json_decode($raw, true);
                        if (is_array($decoded)) {
                            $raw = $decoded['lyric']
                                ?? $decoded['lrc']['lyric']
                                ?? $decoded['lrc']
                                ?? $raw;
                        }
                    }
                    foreach ($result as &$r) {
                        if ($r['id'] == $mid) { $r['lrc'] = $raw; break; }
                    }
                    unset($r);
                }
                curl_multi_remove_handle($mh, $ch);
            }
            curl_multi_close($mh);
        }
        json_exit($result);
        break;

    case 'url':
        $_current_api_key = auth_required();
        if (!$id) json_error('缺少 id 参数');
        $server = $_GET['server'] ?? 'tencent';
        $server = in_array($server, ['tencent', 'netease'], true) ? $server : 'tencent';
        $src = resolve_play_url($id, $apiBase, $ua, $qqRef, $server, $rServer, $rType, $rId);
        json_exit(['url' => $src]);
        break;

    case 'search':
        $_current_api_key = auth_required();
        $keyword = $_GET['keyword'] ?? '';
        if ($keyword === '') json_error('缺少 keyword 参数');
        $server = $_GET['server'] ?? 'netease';
        $server = in_array($server, ['tencent', 'netease'], true) ? $server : 'netease';
        $searchLimit = min((int)($_GET['limit'] ?? 10), 30);
        $url = Uri\Rfc3986\Uri::parse($apiBase)->withQuery(http_build_query([$rServer => $server, $rType => 'search', $rId => $keyword]))->toString();
        $raw = http_get($url, $ua, $qqRef);
        $songs = $raw ? json_decode($raw, true) : [];
        if (!$songs || !is_array($songs)) json_exit([]);
        $songs = array_slice($songs, 0, $searchLimit);
        $result = [];
        foreach ($songs as $s) {
            $mid = '';
            if (!empty($s[$fUrl]) && preg_match('/[?&]' . preg_quote($pId, '/') . '=([^&]+)/', $s[$fUrl], $m)) {
                $mid = $m[1];
            } elseif (!empty($s[$fLrc]) && preg_match('/[?&]' . preg_quote($pId, '/') . '=([^&]+)/', $s[$fLrc], $m)) {
                $mid = $m[1];
            }
            $picUrl = '';
            if (!empty($s[$fPic])) {
                if (preg_match('/^https?:\/\//', $s[$fPic])) {
                    $picUrl = $s[$fPic];
                } else {
                    $picId = '';
                    if (preg_match('/[?&]' . preg_quote($pId, '/') . '=([^&]+)/', $s[$fPic], $m)) $picId = $m[1];
                    if ($picId) $picUrl = (string)Uri\Rfc3986\Uri::parse($apiBase)->withQuery(http_build_query([$rServer => $server, $rType => 'pic', $pId => $picId]));
                }
            }
            $result[] = [
                'id'     => $mid,
                'name'   => $s[$fTitle] ?? '未知',
                'artist' => $s[$fArtist] ?? '',
                'server' => $server,
                'pic'    => $picUrl,
            ];
        }
        json_exit($result);
        break;

    case 'song':
        $_current_api_key = auth_required();
        if (!$id) json_error('缺少 id 参数');
        $server = $_GET['server'] ?? 'tencent';
        $server = in_array($server, ['tencent', 'netease'], true) ? $server : 'tencent';
        $url = Uri\Rfc3986\Uri::parse($apiBase)->withQuery(http_build_query([$rServer => $server, $rType => 'song', $rId => $id]))->toString();
        $raw = http_get($url, $ua, $qqRef);
        $song = $raw ? json_decode($raw, true) : [];
        if (!$song || !is_array($song)) json_exit([]);
        $song = $song[0] ?? $song;
        $mid = '';
        if (!empty($song[$fUrl]) && preg_match('/[?&]' . preg_quote($pId, '/') . '=([^&]+)/', $song[$fUrl], $m)) {
            $mid = $m[1];
        }
        $picUrl = '';
        if (!empty($song[$fPic])) {
            if (preg_match('/^https?:\/\//', $song[$fPic])) {
                $picUrl = $song[$fPic];
            } else {
                $picId = '';
                if (preg_match('/[?&]' . preg_quote($pId, '/') . '=([^&]+)/', $song[$fPic], $m)) $picId = $m[1];
                if ($picId) $picUrl = '?action=pic&server=' . $server . '&' . $pId . '=' . $picId;
            }
        }
        $playUrl = '';
        if ($server === 'netease') {
            if ($mid) $playUrl = resolve_play_url($mid, $apiBase, $ua, $qqRef, $server, $rServer, $rType, $rId);
        } else {
            if (!empty($song[$fUrl])) $playUrl = preg_replace('/^http:/i', 'https:', $song[$fUrl]);
        }
        $lrc = '';
        if (!empty($song[$fLrc])) {
            $lrcUrl = preg_replace('/^http:/i', 'https:', $song[$fLrc]);
            $lrcRaw = http_get($lrcUrl, $ua, $qqRef);
            if ($lrcRaw) {
                if ($server === 'netease') {
                    $decoded = json_decode($lrcRaw, true);
                    if (is_array($decoded)) $lrcRaw = $decoded['lyric'] ?? $decoded['lrc']['lyric'] ?? $decoded['lrc'] ?? $lrcRaw;
                }
                $lrc = $lrcRaw;
            }
        }
        json_exit([[
            'id'     => $mid,
            'name'   => $song[$fTitle] ?? '未知',
            'artist' => $song[$fArtist] ?? '',
            'url'    => $playUrl,
            'pic'    => $picUrl,
            'lrc'    => $lrc,
        ]]);
        break;

    case 'pic':
        $_current_api_key = auth_required();
        if (!$id) json_error('缺少 id 参数');
        $auth = $_GET['auth'] ?? '';
        $server = $_GET['server'] ?? 'tencent';
        $server = in_array($server, ['tencent', 'netease'], true) ? $server : 'tencent';
        $params = [$rServer => $server, $rType => 'pic', $rId => $id];
        if ($auth) $params[$pAuth] = $auth;
        $src = Uri\Rfc3986\Uri::parse($apiBase)->withQuery(http_build_query($params))->toString();
        $finalUrl = resolve_final_url($src, $ua, $qqRef);
        logRequest('pic:' . $id, 0);
        header('Location: ' . ($finalUrl ?: $qqCover . $id . '.jpg'));
        exit;

    case 'verify-key':
        $input = json_decode(file_get_contents('php://input'), true);
        $k = $input['key'] ?? $_GET['key'] ?? '';
        $_current_api_key = $k;
        if (!$k) json_exit(['valid' => false, 'msg' => 'missing key']);
        $sid = bin2hex(random_bytes(16));
        $ttl = 86400;
        if (!$db_log) {
            $token = jwt_encode(['key' => $k, 'sid' => $sid, 'exp' => time() + $ttl, 'iat' => time()], $jwt_secret);
            setcookie('mapi_sid', $sid, ['expires' => time() + $ttl, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax']);
            json_exit(['valid' => true, 'token' => $token, 'msg' => 'ok (db offline)']);
        }
        $stmt = $db_log->prepare("SELECT k.id, u.expire_at FROM mapi_keys k LEFT JOIN mapi_users u ON k.user_id=u.id WHERE k.api_key=? AND k.status=1");
        $stmt->bind_param('s', $k);
        $stmt->execute();
        $r = $stmt->get_result();
        $row = $r ? $r->fetch_assoc() : null;
        if (!$row) json_exit(['valid' => false, 'msg' => 'invalid key']);
        if (!$row['expire_at'] || strtotime($row['expire_at']) < time()) {
            json_exit(['valid' => false, 'msg' => $row['expire_at'] ? '账户已过期，请续费' : '账户未激活']);
        }
        $token = jwt_encode(['key' => $k, 'sid' => $sid, 'exp' => time() + $ttl, 'iat' => time()], $jwt_secret);
        setcookie('mapi_sid', $sid, ['expires' => time() + $ttl, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax']);
        json_exit(['valid' => true, 'token' => $token, 'msg' => 'ok']);

    case 'get-config':
        $k = $_GET['key'] ?? '';
        $tk = $_GET['token'] ?? '';
        if ($tk) {
            $data = jwt_decode($tk, $jwt_secret);
            if ($data && !empty($data['key'])) {
                $csid = $_COOKIE['mapi_sid'] ?? '';
                $tsid = $data['sid'] ?? '';
                if ($tsid && $csid && hash_equals($tsid, $csid)) {
                    setcookie('mapi_sid', $csid, ['expires' => time() + 86400, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax']);
                }
                $k = $data['key'];
            }
        }
        $_current_api_key = $k;
        if (!$k) json_exit(['ok' => false, 'config' => null, 'msg' => 'missing key']);
        if (!$db_log) {
            json_exit(['ok' => true, 'config' => [
                'auto_theme' => 1, 'theme_mode' => 'light', 'lyrics_default' => 1,
                'autoplay_default' => 0, 'playlists' => []
            ]]);
        }
        $keyStmt = $db_log->prepare("SELECT user_id, id FROM mapi_keys WHERE api_key=? AND status=1");
        $keyStmt->bind_param('s', $k);
        $keyStmt->execute();
        $keyRes = $keyStmt->get_result();
        $keyRow = $keyRes->fetch_assoc();
        if (!$keyRow) json_exit(['ok' => false, 'config' => null, 'msg' => 'invalid key']);
        $userId = (int)$keyRow['user_id'];
        $userStmt = $db_log->prepare("SELECT auto_theme, theme_mode, lyrics_default, autoplay_default, expire_at FROM mapi_users WHERE id=?");
        $userStmt->bind_param('i', $userId);
        $userStmt->execute();
        $userRes = $userStmt->get_result();
        $userRow = $userRes->fetch_assoc();
        if (!$userRow || !$userRow['expire_at'] || strtotime($userRow['expire_at']) < time()) {
            json_exit(['ok' => false, 'config' => null, 'msg' => $userRow && $userRow['expire_at'] ? '账户已过期' : '账户未激活']);
        }
        $autoTheme = $userRow ? (int)$userRow['auto_theme'] : 1;
        $themeMode = $userRow ? $userRow['theme_mode'] : 'light';
        $lyricsDefault = $userRow ? (int)($userRow['lyrics_default'] ?? 1) : 1;
        $autoplayDefault = $userRow ? (int)($userRow['autoplay_default'] ?? 0) : 0;
        $keyId = (int)$keyRow['id'];
        $playlists = [];
        if ($db_log) {
            $plStmt = $db_log->prepare("SELECT id, name, type, remote_id, server, cover_url, cover_mode FROM mapi_playlists WHERE key_id=? ORDER BY sort_order ASC, id ASC");
            $plStmt->bind_param('i', $keyId);
            $plStmt->execute();
            $plRes = $plStmt->get_result();
            while ($pr = $plRes->fetch_assoc()) {
                $plItem = [
                    'name' => $pr['name'],
                    'server' => $pr['server'],
                    'cover_url' => $pr['cover_url'],
                    'cover_mode' => $pr['cover_mode'],
                ];
                if ($pr['type'] === 'remote' && $pr['remote_id']) {
                    $plItem['id'] = $pr['remote_id'];
                    $plItem['type'] = 'playlist';
                    $playlists[] = $plItem;
                } else {
                    $plId = (int)$pr['id'];
                    $songStmt = $db_log->prepare("SELECT song_id, name, artist, server FROM mapi_songs WHERE playlist_id=? ORDER BY sort_order ASC, id ASC");
                    $songStmt->bind_param('i', $plId);
                    $songStmt->execute();
                    $songRes = $songStmt->get_result();
                    $plSongs = [];
                    while ($sr = $songRes->fetch_assoc()) {
                        $plSongs[] = ['id' => $sr['song_id'], 'name' => $sr['name'], 'artist' => $sr['artist'], 'server' => $sr['server']];
                    }
                    $plItem['type'] = 'custom';
                    $plItem['songs'] = $plSongs;
                    $playlists[] = $plItem;
                }
            }
        }
        json_exit(['ok' => true, 'config' => ['auto_theme' => $autoTheme, 'theme_mode' => $themeMode, 'lyrics_default' => $lyricsDefault, 'autoplay_default' => $autoplayDefault, 'playlists' => $playlists]]);

    case 'get-announcement':
        $k = $_GET['key'] ?? '';
        $tk = $_GET['token'] ?? '';
        if ($tk) {
            $data = jwt_decode($tk, $jwt_secret);
            if ($data && !empty($data['key'])) {
                $csid = $_COOKIE['mapi_sid'] ?? '';
                $tsid = $data['sid'] ?? '';
                if ($tsid && $csid && hash_equals($tsid, $csid)) {
                    setcookie('mapi_sid', $csid, ['expires' => time() + 86400, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax']);
                }
                $k = $data['key'];
            }
        }
        $_current_api_key = $k;
        if (!$k) json_exit(['enabled' => false, 'content' => '', 'msg' => 'missing key']);
        if (!$db_log) json_exit(['enabled' => false, 'content' => '', 'msg' => 'db unavailable']);
        $keyStmt = $db_log->prepare("SELECT user_id FROM mapi_keys WHERE api_key=? AND status=1");
        $keyStmt->bind_param('s', $k);
        $keyStmt->execute();
        $keyRes = $keyStmt->get_result();
        $keyRow = $keyRes->fetch_assoc();
        if (!$keyRow) json_exit(['enabled' => false, 'content' => '', 'msg' => 'invalid key']);
        $userId = (int)$keyRow['user_id'];
        $userStmt = $db_log->prepare("SELECT expire_at FROM mapi_users WHERE id=?");
        $userStmt->bind_param('i', $userId);
        $userStmt->execute();
        $userRes = $userStmt->get_result();
        $userRow = $userRes->fetch_assoc();
        if (!$userRow || !$userRow['expire_at'] || strtotime($userRow['expire_at']) < time()) {
            json_exit(['enabled' => false, 'content' => '', 'msg' => $userRow && $userRow['expire_at'] ? '账户已过期' : '账户未激活']);
        }
        $r = $db_log->query("SELECT config_value FROM mapi_config WHERE config_key='announcement'");
        $row = $r ? $r->fetch_assoc() : null;
        if (!$row || !$row['config_value']) {
            json_exit(['enabled' => false, 'content' => '']);
        }
        $ann = json_decode($row['config_value'], true);
        if (!is_array($ann)) {
            json_exit(['enabled' => false, 'content' => '']);
        }
        json_exit([
            'enabled' => (bool)($ann['enabled'] ?? false),
            'content' => $ann['content'] ?? '',
            'title'   => $ann['title'] ?? '',
            'align'   => $ann['align'] ?? 'left',
        ]);

    default:
        json_error('不支持的操作');
}

function logRequest(string $endpoint, int $size = 0, string $apiKey = ''): void {
    global $db_log;
    if (!$db_log || $db_log->connect_error) return;
    $ip  = $_SERVER['REMOTE_ADDR'] ?? '';
    $ref = $_SERVER['HTTP_ORIGIN'] ?? $_SERVER['HTTP_REFERER'] ?? '';
    $ua  = $_SERVER['HTTP_USER_AGENT'] ?? '';

    $stmt = $db_log->prepare("INSERT INTO mapi_logs (ip, referer, endpoint, user_agent, api_key, traffic_bytes) VALUES (?, ?, ?, ?, ?, ?)");
    if ($stmt) {
        $stmt->bind_param('sssssi', $ip, $ref, $endpoint, $ua, $apiKey, $size);
        $stmt->execute();
        $stmt->close();
    }
}

#[\NoReturn]
function json_exit(mixed $data): never {
    global $_current_api_key;
    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    echo $json;
    logRequest(($_GET['action'] ?? '?') . ':' . ($_GET['id'] ?? ''), strlen($json), $_current_api_key ?? '');
    exit;
}

#[\NoReturn]
function json_error(string $msg): never {
    json_exit(['error' => $msg]);
}