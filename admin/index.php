<?php
define('MAPI_ADMIN', true);

// -- 安装验证 --
$installLock = dirname(__DIR__) . '/install/install.lock';
$configFile = dirname(__DIR__) . '/config/config.php';

if (!file_exists($configFile)) {
    header('Location: ../install/');
    exit;
}

if (file_exists($installLock)) {
    $lockLines = [];
    foreach (explode("\n", file_get_contents($installLock)) as $l) {
        $l = trim($l); if ($l === '') continue;
        if (strpos($l, ': ') !== false) {
            [$k, $v] = explode(': ', $l, 2);
            $lockLines[trim($k)] = trim($v);
        }
    }
    $hashOk = isset($lockLines['config_hash']) && $lockLines['config_hash'] === hash_file('sha256', $configFile);
    $sigOk = isset($lockLines['signature']) && $lockLines['signature'] === 'msapi';
    $typeOk = isset($lockLines['install_type']) && in_array($lockLines['install_type'], ['sqlite', 'mysql', 'mariadb']);
    if (!$hashOk || !$sigOk || !$typeOk) {
        header('Location: ../install/');
        exit;
    }
} else {
    header('Location: ../install/');
    exit;
}

// 增量建表
require dirname(__DIR__) . '/install/upgrade.php';

// -- 初始化检测 --
$configFile = dirname(__DIR__) . '/config/config.php';
if (!file_exists($configFile)) {
    header('Location: ../install/');
    exit;
}
$cfg = require $configFile;
date_default_timezone_set('Asia/Shanghai');
require __DIR__ . '/../assets/lib/jwt.php';
if (empty($cfg['db']['type'])) {
    header('Location: ../install/');
    exit;
}
session_start();

// 会话有效期：30分钟无操作自动登出
if (!empty($_SESSION['admin_id'])) {
    $sessionTimeout = 1800;
    $lastActivity = $_SESSION['_last_activity'] ?? 0;
    if ($lastActivity > 0 && time() - $lastActivity > $sessionTimeout) {
        session_destroy();
        header('Location: ?');
        exit;
    }
    $_SESSION['_last_activity'] = time();
}

function is_mobile() {
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    if ($ua === '') return false;
    $mobiles = [
        'Mobile','Android','iPhone','iPad','iPod','webOS','BlackBerry',
        'IEMobile','Opera Mini','Opera Mobi','Windows Phone','Kindle','Silk',
        'Symbian','SymbianOS','PlayBook','BB10','Tablet','KFAPWI','KFOT',
        'MicroMessenger','MQQBrowser','UCBrowser','UCWEB','QQ/',
        'BaiduBrowser','baiduboxapp','MiuiBrowser','HuaweiBrowser',
        'SogouMobileBrowser','LieBaoFast','360Browser','AlipayClient',
        'DingTalk','MZBrowser','CoolPad','OppoBrowser','VivoBrowser',
        'Nokia','PlayStation','Nintendo','WAP',
    ];
    foreach ($mobiles as $m) {
        if (stripos($ua, $m) !== false) return true;
    }
    return false;
}
function flash_set($k,$v){ $_SESSION['_flash'][$k]=$v; }
function flash_get($k){ $v=$_SESSION['_flash'][$k]??null; unset($_SESSION['_flash'][$k]); return $v; }
$_coverCacheDir  = __DIR__ . '/assets';
$_plCoverFile    = $_coverCacheDir . '/playlist_covers.json';
$_songCoverFile  = $_coverCacheDir . '/song_covers.json';

function cover_cache_read(string $file): array {
    if (!file_exists($file)) return [];
    $raw = @file_get_contents($file);
    if (!$raw) return [];
    $data = json_decode($raw, true);
    if (!is_array($data)) return [];
    $migrated = false;
    foreach ($data as $k => $v) {
        if (!is_array($v)) { unset($data[$k]); $migrated = true; }
    }
    if ($migrated) cover_cache_write($file, $data);
    return $data;
}

function cover_cache_write(string $file, array $data): void {
    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    @file_put_contents($file, $json, LOCK_EX);
}

function cover_cache_set(string $file, string $user, string $key, string $b64): void {
    $data = cover_cache_read($file);
    if (!isset($data[$user]) || !is_array($data[$user])) $data[$user] = [];
    $data[$user][$key] = $b64;
    cover_cache_write($file, $data);
}

function cover_cache_get(string $file, string $user, string $key): string {
    $data = cover_cache_read($file);
    return $data[$user][$key] ?? '';
}

function cover_cache_get_user(string $file, string $user): array {
    $data = cover_cache_read($file);
    return $data[$user] ?? [];
}

function cover_cache_unset(string $file, string $user, string $key): void {
    $data = cover_cache_read($file);
    unset($data[$user][$key]);
    if (isset($data[$user]) && empty($data[$user])) unset($data[$user]);
    cover_cache_write($file, $data);
}

function fetch_image_b64(string $url, int $maxSize = 524288): string {
    if (!$url) return '';
    $ctx = stream_context_create(['http' => ['timeout' => 8, 'follow_location' => true, 'max_redirects' => 3], 'ssl' => ['verify_peer' => false]]);
    $raw = @file_get_contents($url, false, $ctx);
    if (!$raw || strlen($raw) > $maxSize || strlen($raw) < 100) return '';
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->buffer($raw);
    if (!in_array($mime, ['image/jpeg','image/png','image/gif','image/webp','image/svg+xml'], true)) {
        if (substr($raw, 0, 3) === "\xFF\xD8\xFF") $mime = 'image/jpeg';
        elseif (substr($raw, 0, 4) === "\x89PNG") $mime = 'image/png';
        elseif (substr($raw, 0, 4) === 'GIF8') $mime = 'image/gif';
        elseif (substr($raw, 0, 4) === 'RIFF') $mime = 'image/webp';
        else return '';
    }
    return 'data:' . $mime . ';base64,' . base64_encode($raw);
}

function cover_fetch_and_cache(string $url, string $cacheFile, string $user, string $cacheKey): string {
    if (!$url) return '';
    $cached = cover_cache_get($cacheFile, $user, $cacheKey);
    if ($cached) return $cached;
    $b64 = fetch_image_b64($url);
    if ($b64) cover_cache_set($cacheFile, $user, $cacheKey, $b64);
    return $b64;
}

function cover_resolve_user($db, $playlistId): string {
    $r = $db->query("SELECT u.username FROM mapi_playlists p LEFT JOIN mapi_keys k ON p.key_id=k.id LEFT JOIN mapi_users u ON k.user_id=u.id WHERE p.id=" . (int)$playlistId);
    if ($r && $row = $r->fetch_assoc()) return $row['username'] ?? 'unknown';
    return 'unknown';
}

require __DIR__ . '/../assets/lib/api_config.php';
require __DIR__ . '/../assets/lib/helpers.php';
require __DIR__ . '/lib/db.php';
require __DIR__ . '/lib/auth.php';
require __DIR__ . '/lib/mail.php';
require __DIR__ . '/lib/webauthn.php';
require __DIR__ . '/lib/s3.php';
require __DIR__ . '/lib/epay.php';
require __DIR__ . '/lib/pusher.php';

if (!empty($db) && empty($db->connect_error) && empty($db->_error)) {
    $apiCfg = read_mapi_api_config($db, $cfg);
    $cfg['api'] = array_merge($cfg['api'] ?? [], $apiCfg);
}

if (empty($db) || !empty($db->connect_error) || !empty($db->_error)) {
    $errMsg = '';
    try {
        $c = $cfg['db'];
        $errMsg = $c['type'] === 'sqlite' ? '无法连接数据库文件：' . ($c['path']??'') : '无法连接数据库：' . (($c['hosts'][0]??'').':'.($c['port']??3306));
    } catch (Throwable $e) { $errMsg = '数据库配置错误'; }
    ?><!DOCTYPE html>
<html lang="zh-CN">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>数据库连接失败 — MSAPI</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
html,body{width:100%;height:100%;display:flex;align-items:center;justify-content:center;font-family:-apple-system,'PingFang SC','Microsoft YaHei',sans-serif;background:#0a0a0f;color:rgba(255,255,255,.8)}
.card{background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.08);border-radius:16px;padding:40px;max-width:440px;text-align:center}
.card .ico{width:48px;height:48px;display:block;margin:0 auto 16px}
.card h2{font-size:18px;font-weight:700;margin-bottom:8px}
.card p{font-size:13px;color:rgba(255,255,255,.5);line-height:1.6;margin-bottom:24px;word-break:break-all}
.card .btn{display:inline-block;padding:10px 24px;border-radius:8px;font-size:13px;font-weight:600;color:#fff;background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.1);text-decoration:none;cursor:pointer;transition:background .2s}
.card .btn:hover{background:rgba(255,255,255,.12)}
.card .btn-p{background:#6c5ce7;border-color:rgba(108,92,231,.3)}
.card .btn-p:hover{background:#5a4bd1}
</style>
</head>
<body>
<div class="card">
  <svg class="ico" viewBox="0 0 24 24" fill="none" stroke="#ff5252" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
  <h2>数据库连接失败</h2>
  <p><?=htmlspecialchars($errMsg)?><br><br>请检查数据库配置或服务状态后重试。</p>
  <a class="btn btn-p" onclick="location.reload()">刷新重试</a>
  <a class="btn" href="/install/">重新安装</a>
</div>
</body>
</html><?php
    exit;
}

$action = $_GET['action'] ?? '';
$isMobile = is_mobile();

// 每次请求从数据库重新读取权限组，不缓存
if (!empty($_SESSION['admin_id'])) {
    $r = $db->query("SELECT is_admin FROM mapi_users WHERE id=" . (int)$_SESSION['admin_id']);
    if ($r && $row = $r->fetch_assoc()) {
        $_SESSION['admin_is_admin'] = (int)$row['is_admin'];
    }
}

// ═══ 易支付回调（无需登录） ═══
if ($action === 'epay-notify') {
    $cfg = epay_config();
    if (!$cfg) { http_response_code(500); exit('fail'); }
    // ponytail: 合并 GET+POST，剔除 URL 的 action 参数防验签失败
    $params = array_merge($_GET, $_POST);
    unset($params['action']);
    if (epay_verify($params, $cfg['key'])) {
        $tradeStatus = strval($params['trade_status'] ?? '');
        $tradeNo = strval($params['out_trade_no'] ?? '');
        // ponytail: 兼容 TRADE_SUCCESS / 1 / success 三种状态值
        if ($tradeStatus === 'TRADE_SUCCESS' || $tradeStatus === '1' || $tradeStatus === 'success') {
            $stmt = $db->prepare("SELECT id, user_id, product_id, duration_days, status FROM mapi_orders WHERE trade_no=?");
            $stmt->bind_param('s', $tradeNo);
            $stmt->execute();
            $r = $stmt->get_result();
            if ($r && $order = $r->fetch_assoc()) {
                if ($order['status'] == 0) {
                    $db->query("UPDATE mapi_orders SET status=1, paid_at=NOW() WHERE id=" . (int)$order['id']);
                    $db->query("UPDATE mapi_users SET expire_at=IF(expire_at IS NULL OR expire_at < NOW(), DATE_ADD(NOW(), INTERVAL " . (int)$order['duration_days'] . " DAY), DATE_ADD(expire_at, INTERVAL " . (int)$order['duration_days'] . " DAY)) WHERE id=" . (int)$order['user_id']);
                }
            }
            exit('success');
        }
    }
    exit('fail');
}
if ($action === 'epay-return') {
    session_start();
    $_SESSION['_flash']['msg'] = '支付完成，请到订单记录查看状态';
    session_write_close();
    header('Location: ?action=orders'); exit;
}

// ═══ JS 错误日志上报（不需要登录） ═══
if ($action === 'js-log' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $log = '[' . date('H:i:s') . '] ' . ($_POST['name'] ?? '') . ': ' . ($_POST['message'] ?? '') . ' | UA:' . ($_SERVER['HTTP_USER_AGENT'] ?? '') . PHP_EOL;
    @file_put_contents(__DIR__ . '/assets/js-error.log', $log, FILE_APPEND | LOCK_EX);
    exit;
}

// ═══ 未登录：处理登录相关动作或跳转 ═══
$loginActions = ['', 'register', 'get-avatar', 'pk-login-begin', 'pk-login-complete'];
if (empty($_SESSION['admin_id'])) {
    if (in_array($action, $loginActions)) {
        require __DIR__ . '/pages/login.php';
        exit;
    }
    header('Location: /admin/');
    exit;
}

// ═══ 已登录 ═══
if ($action === 'logout') {
    pusher_trigger(PUSHER_CHANNEL, 'user-offline', ['user_id' => (string)$_SESSION['admin_id'], 'username' => $_SESSION['admin_user']]);
    session_destroy();
    header('Location: ?');
    exit;
}

$allowed = ['dashboard','keys','keys-create','keys-delete','playlist-create','playlist-delete','playlist-update','playlist-detail','playlist-update-cover','playlist-fetch-cover','song-add','song-remove','users','user-delete','user-admin','profile','config','settings','pk-begin','pk-complete','pk-delete','bg-presign','bg-confirm','bg-url-save','epay-test','shop','orders','shop-add','shop-edit','shop-delete','shop-buy','shop-free-buy','shop-gift','order-cancel','order-clear','js-log','pusher-auth','pusher-online-users','debug-toggle','clear-logs'];
$action = in_array($action, $allowed) ? $action : 'dashboard';

if ((($_SESSION['admin_is_admin'] ?? 99) > 1) && in_array($action, ['users','settings'])) {
    $action = 'dashboard';
}

// ═══ Pusher 鉴权端点 ═══
if ($action === 'pusher-auth') {
    $socket_id = $_POST['socket_id'] ?? '';
    $channel_name = $_POST['channel_name'] ?? '';
    if (!$socket_id || !$channel_name) { http_response_code(400); exit; }
    header('Content-Type: application/json');
    echo pusher_auth($socket_id, $channel_name, (string)$_SESSION['admin_id'], ['username' => $_SESSION['admin_user'], 'avatar' => $_SESSION['admin_qq'] ?? '']);
    exit;
}

// ═══ 初始化数据版本 ═══
$r = $db->query("SELECT COUNT(*) as cnt FROM mapi_config WHERE config_key='data_version'");
if ($r) {
    $row = $r->fetch_assoc();
    if ((int)$row['cnt'] === 0) {
        $db->query("INSERT INTO mapi_config (config_key, config_value) VALUES ('data_version', '1.0.0')");
    }
}

// ═══ Pusher 上线通知（每次访问触发，服务端推给所有人） ═══
if ($_SESSION['admin_id']) {
    // 限制频率：每 60 秒内只触发一次，避免刷屏
    $lastPing = intval($_SESSION['_pusher_ping'] ?? 0);
    if (time() - $lastPing > 60) {
        $_SESSION['_pusher_ping'] = time();
        pusher_trigger(PUSHER_CHANNEL, 'user-online', [
            'user_id' => (string)$_SESSION['admin_id'],
            'username' => $_SESSION['admin_user'],
        ]);
    }
}

// ═══ Pusher 在线用户列表 ═══
if ($action === 'pusher-online-users') {
    header('Content-Type: application/json');
    echo json_encode(pusher_online_users());
    exit;
}

csrf_token();
$msg = flash_get('msg') ?? '';
$err = flash_get('err') ?? '';

// ═══ 用户配置 ═══
$autoTheme = (int)($_SESSION['admin_auto_theme'] ?? 1);
$themeMode = $_SESSION['admin_theme_mode'] ?? 'light';
$lyricsDefault = (int)($_SESSION['admin_lyrics_default'] ?? 1);
$autoplayDefault = (int)($_SESSION['admin_autoplay_default'] ?? 0);
$userKeys = [];
$isAdmin = $_SESSION['admin_is_admin'] ?? 99;
if ($isAdmin === 0) {
    $r = $db->query("SELECT id, user_id, api_key FROM mapi_keys ORDER BY id DESC");
} else {
    $r = $db->query("SELECT id, api_key FROM mapi_keys WHERE user_id=" . (int)$_SESSION['admin_id'] . " ORDER BY id DESC");
}
if ($r) while ($row = $r->fetch_assoc()) $userKeys[] = $row;

// ═══ 密钥管理 ═══
if ($action === 'keys-create' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    $isAdmin = $_SESSION['admin_is_admin'] ?? 99;
    if ($isAdmin > 1) {
        $r = $db->query("SELECT expire_at FROM mapi_users WHERE id=" . (int)$_SESSION['admin_id']);
        $expireAt = $r ? $r->fetch_assoc()['expire_at'] : null;
        if (!$expireAt || strtotime($expireAt) < time()) {
            flash_set('err', '账户未激活或已过期，无法创建密钥');
            header('Location: ?action=keys'); exit;
        }
        $limit = 1;
        $r = $db->query("SELECT config_value FROM mapi_config WHERE config_key='key_limit'");
        if ($r && $row = $r->fetch_assoc()) {
            $limitCfg = json_decode($row['config_value'], true);
            if (is_array($limitCfg) && isset($limitCfg['limit'])) $limit = max(1, (int)$limitCfg['limit']);
        }
        $cntR = $db->query("SELECT COUNT(*) as cnt FROM mapi_keys WHERE user_id=" . (int)$_SESSION['admin_id']);
        $cnt = $cntR ? (int)$cntR->fetch_assoc()['cnt'] : 0;
        if ($cnt >= $limit) {
            flash_set('err', '已达到密钥创建上限（' . $limit . ' 个），请联系管理员');
            header('Location: ?action=keys'); exit;
        }
    }
    $k = bin2hex(random_bytes(16));
    $stmt = $db->prepare("INSERT INTO mapi_keys (user_id, api_key, status) VALUES (?, ?, 1)");
    $stmt->bind_param('is', $_SESSION['admin_id'], $k);
    if ($stmt->execute()) flash_set('msg','密钥已创建');
    else flash_set('err','创建失败');
    header('Location: ?action=keys'); exit;
}
if ($action === 'keys-delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    $kid = (int)($_POST['id'] ?? 0);
    if (($_SESSION['admin_is_admin'] ?? 99) === 0) {
        $stmt = $db->prepare("DELETE FROM mapi_keys WHERE id=?");
        $stmt->bind_param('i', $kid);
    } else {
        $stmt = $db->prepare("DELETE FROM mapi_keys WHERE id=? AND user_id=?");
        $stmt->bind_param('ii', $kid, $_SESSION['admin_id']);
    }
    $stmt->execute();
    if ($stmt->affected_rows > 0) flash_set('msg','密钥已删除');
    else flash_set('err','无权限');
    header('Location: ?action=keys'); exit;
}
if ($action === 'playlist-create' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    $input = json_decode(file_get_contents('php://input'), true);
    $_POST['_csrf'] = $input['_csrf'] ?? '';
    csrf_require();
    $kid = (int)($input['key_id'] ?? 0);
    $plName = trim($input['name'] ?? '');
    $plType = in_array($input['type'] ?? '', ['custom', 'remote']) ? $input['type'] : 'custom';
    $remoteId = trim($input['remote_id'] ?? '');
    $server = in_array($input['server'] ?? '', ['tencent', 'netease']) ? $input['server'] : 'netease';
    $coverUrl = trim($input['cover_url'] ?? '');
    $coverMode = in_array($input['cover_mode'] ?? '', ['auto', 'url', 'first_song']) ? $input['cover_mode'] : 'auto';
    if (!$kid || !$plName) { echo json_encode(['ok' => false, 'msg' => '参数不完整']); exit; }
    $ownerCheck = ($_SESSION['admin_is_admin'] ?? 99) <= 1
        ? $db->query("SELECT id FROM mapi_keys WHERE id=$kid")
        : $db->query("SELECT id FROM mapi_keys WHERE id=$kid AND user_id=" . (int)$_SESSION['admin_id']);
    if (!$ownerCheck || !$ownerCheck->fetch_assoc()) { echo json_encode(['ok' => false, 'msg' => '无权限']); exit; }
    $maxOrder = $db->query("SELECT IFNULL(MAX(sort_order),0) FROM mapi_playlists WHERE key_id=$kid");
    $nextOrder = $maxOrder ? (int)$maxOrder->fetch_row()[0] + 1 : 1;
    $stmt = $db->prepare("INSERT INTO mapi_playlists (key_id, name, type, remote_id, server, cover_url, cover_mode, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param('issssssi', $kid, $plName, $plType, $remoteId, $server, $coverUrl, $coverMode, $nextOrder);
    $ok = $stmt->execute();
    $insertId = $ok ? $stmt->insert_id : 0;
    if ($ok && $plType === 'remote' && $coverMode === 'auto' && !$coverUrl && $remoteId) {
        $apiBase = $cfg['api']['base_url'] ?? '';
        $rServer = $cfg['api']['param_server'] ?? 'server';
        $rType = $cfg['api']['param_type'] ?? 'type';
        $rId = $cfg['api']['param_id'] ?? 'id';
        $fPic = $cfg['api']['field_pic'] ?? 'pic';
        $pId = $cfg['api']['param_id'] ?? 'id';
        if ($apiBase) {
            $fetchUrl = $apiBase . '?' . http_build_query([$rServer => $server, $rType => 'playlist', $rId => $remoteId]);
            $raw = @file_get_contents($fetchUrl);
            if ($raw) {
                $plData = json_decode($raw, true);
                $firstSong = is_array($plData) ? ($plData[0] ?? null) : null;
                if ($firstSong && !empty($firstSong[$fPic])) {
                    $coverUrl = resolve_cover_url($firstSong[$fPic], $apiBase, $server, $pId, $rServer, $rType);
                    if ($coverUrl) {
                        $picEsc = $db->real_escape_string($coverUrl);
                        $db->query("UPDATE mapi_playlists SET cover_url='$picEsc' WHERE id=$insertId");
                        $cUser = cover_resolve_user($db, $insertId);
                        cover_fetch_and_cache($coverUrl, $_plCoverFile, $cUser, (string)$insertId);
                    }
                }
                $cUser = $cUser ?? cover_resolve_user($db, $insertId);
                foreach (is_array($plData) ? $plData : [] as $idx => $s) {
                    if ($idx >= 30) break;
                    if (empty($s[$fPic])) continue;
                    $sPicUrl = resolve_cover_url($s[$fPic], $apiBase, $server, $pId, $rServer, $rType);
                    if ($sPicUrl) {
                        $sId = '';
                        if (!empty($s[$fUrl]) && preg_match('/[?&]' . preg_quote($pId, '/') . '=([^&]+)/', $s[$fUrl], $m)) $sId = $m[1];
                        if (!$sId && !empty($s[$fLrc]) && preg_match('/[?&]' . preg_quote($pId, '/') . '=([^&]+)/', $s[$fLrc], $m)) $sId = $m[1];
                        if ($sId) cover_fetch_and_cache($sPicUrl, $_songCoverFile, $cUser, $server . '_' . $sId);
                    }
                }
            }
        }
    }
    echo json_encode(['ok' => $ok, 'msg' => $ok ? '已创建' : '创建失败', 'id' => $insertId]);
    exit;
}

if ($action === 'playlist-delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    $input = json_decode(file_get_contents('php://input'), true);
    $_POST['_csrf'] = $input['_csrf'] ?? '';
    csrf_require();
    $pid = (int)($input['id'] ?? 0);
    if (!$pid) { echo json_encode(['ok' => false, 'msg' => '参数不完整']); exit; }
    if (($_SESSION['admin_is_admin'] ?? 99) <= 1) {
        $db->query("DELETE FROM mapi_songs WHERE playlist_id=$pid");
        $stmt = $db->prepare("DELETE FROM mapi_playlists WHERE id=?");
        $stmt->bind_param('i', $pid);
    } else {
        $db->query("DELETE FROM mapi_songs WHERE playlist_id=$pid AND playlist_id IN (SELECT id FROM mapi_playlists WHERE key_id IN (SELECT id FROM mapi_keys WHERE user_id=" . (int)$_SESSION['admin_id'] . "))");
        $stmt = $db->prepare("DELETE FROM mapi_playlists WHERE id=? AND key_id IN (SELECT id FROM mapi_keys WHERE user_id=?)");
        $stmt->bind_param('ii', $pid, $_SESSION['admin_id']);
    }
    $ok = $stmt->execute();
    if ($ok) {
        $cUser = cover_resolve_user($db, $pid);
        cover_cache_unset($_plCoverFile, $cUser, (string)$pid);
    }
    echo json_encode(['ok' => $ok, 'msg' => $ok ? '已删除' : '删除失败']);
    exit;
}

if ($action === 'playlist-update' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    $input = json_decode(file_get_contents('php://input'), true);
    $_POST['_csrf'] = $input['_csrf'] ?? '';
    csrf_require();
    $pid = (int)($input['id'] ?? 0);
    $plName = trim($input['name'] ?? '');
    $remoteId = trim($input['remote_id'] ?? '');
    $server = in_array($input['server'] ?? '', ['tencent', 'netease']) ? $input['server'] : 'netease';
    $coverUrl = trim($input['cover_url'] ?? '');
    $coverMode = in_array($input['cover_mode'] ?? '', ['auto', 'url', 'first_song']) ? $input['cover_mode'] : 'auto';
    if (!$pid || !$plName) { echo json_encode(['ok' => false, 'msg' => '参数不完整']); exit; }
    if (($_SESSION['admin_is_admin'] ?? 99) <= 1) {
        $stmt = $db->prepare("UPDATE mapi_playlists SET name=?, remote_id=?, server=?, cover_url=?, cover_mode=? WHERE id=?");
        $stmt->bind_param('sssssi', $plName, $remoteId, $server, $coverUrl, $coverMode, $pid);
    } else {
        $stmt = $db->prepare("UPDATE mapi_playlists SET name=?, remote_id=?, server=?, cover_url=?, cover_mode=? WHERE id=? AND key_id IN (SELECT id FROM mapi_keys WHERE user_id=?)");
        $uid = (int)$_SESSION['admin_id'];
        $stmt->bind_param('ssssiii', $plName, $remoteId, $server, $coverUrl, $coverMode, $pid, $uid);
    }
    $ok = $stmt->execute();
    if ($ok && $coverUrl) {
        $apiBase = $cfg['api']['base_url'] ?? '';
        $resolvedUrl = $coverUrl;
        if (!preg_match('/^https?:\/\//', $coverUrl) && $apiBase) {
            $rServer2 = $cfg['api']['param_server'] ?? 'server';
            $rType2 = $cfg['api']['param_type'] ?? 'type';
            $pId2 = $cfg['api']['param_id'] ?? 'id';
            if (preg_match('/[?&]' . preg_quote($pId2, '/') . '=([^&]+)/', $coverUrl, $m)) {
                $sv = $server;
                if (preg_match('/server=([^&]+)/', $coverUrl, $sm)) $sv = $sm[1];
                $resolvedUrl = $apiBase . '?' . http_build_query([$rServer2 => $sv, $rType2 => 'pic', $pId2 => $m[1]]);
            }
        }
        cover_fetch_and_cache($resolvedUrl, $_plCoverFile, cover_resolve_user($db, $pid), (string)$pid);
    }
    echo json_encode(['ok' => $ok, 'msg' => $ok ? '已保存' : '保存失败']);
    exit;
}

if ($action === 'playlist-update-cover' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    $input = json_decode(file_get_contents('php://input'), true);
    $_POST['_csrf'] = $input['_csrf'] ?? '';
    csrf_require();
    $pid = (int)($input['id'] ?? 0);
    $coverUrl = trim($input['cover_url'] ?? '');
    $coverMode = in_array($input['cover_mode'] ?? '', ['auto', 'url', 'first_song']) ? $input['cover_mode'] : 'auto';
    if (!$pid) { echo json_encode(['ok' => false, 'msg' => '参数不完整']); exit; }
    if (($_SESSION['admin_is_admin'] ?? 99) <= 1) {
        $stmt = $db->prepare("UPDATE mapi_playlists SET cover_url=?, cover_mode=? WHERE id=?");
        $stmt->bind_param('ssi', $coverUrl, $coverMode, $pid);
    } else {
        $stmt = $db->prepare("UPDATE mapi_playlists SET cover_url=?, cover_mode=? WHERE id=? AND key_id IN (SELECT id FROM mapi_keys WHERE user_id=?)");
        $stmt->bind_param('ssii', $coverUrl, $coverMode, $pid, $_SESSION['admin_id']);
    }
    $ok = $stmt->execute();
    echo json_encode(['ok' => $ok, 'msg' => $ok ? '已更新' : '更新失败']);
    exit;
}

if ($action === 'playlist-fetch-cover' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    $input = json_decode(file_get_contents('php://input'), true);
    $_POST['_csrf'] = $input['_csrf'] ?? '';
    csrf_require();
    $pid = (int)($input['id'] ?? 0);
    if (!$pid) { echo json_encode(['ok' => false, 'msg' => '参数不完整']); exit; }
    $ownerCheck = ($_SESSION['admin_is_admin'] ?? 99) <= 1
        ? $db->query("SELECT id, type, remote_id, server, cover_mode, cover_url FROM mapi_playlists WHERE id=$pid")
        : $db->query("SELECT id, type, remote_id, server, cover_mode, cover_url FROM mapi_playlists WHERE id=$pid AND key_id IN (SELECT id FROM mapi_keys WHERE user_id=" . (int)$_SESSION['admin_id'] . ")");
    if (!$ownerCheck || !($pl = $ownerCheck->fetch_assoc())) { echo json_encode(['ok' => false, 'msg' => '无权限']); exit; }
    if (!empty($pl['cover_url'])) {
        $cUser = cover_resolve_user($db, $pid);
        $b64 = cover_cache_get($_plCoverFile, $cUser, (string)$pid);
        echo json_encode(['ok' => true, 'cover_url' => $pl['cover_url'], 'cover_b64' => $b64, 'msg' => '已有封面']);
        exit;
    }
    $apiBase = $cfg['api']['base_url'] ?? '';
    $rServer = $cfg['api']['param_server'] ?? 'server';
    $rType = $cfg['api']['param_type'] ?? 'type';
    $rId = $cfg['api']['param_id'] ?? 'id';
    $fPic = $cfg['api']['field_pic'] ?? 'pic';
    $pId = $cfg['api']['param_id'] ?? 'id';
    $server = $pl['server'] ?: 'netease';
    $coverUrl = '';
    if ($apiBase) {
        if ($pl['type'] === 'remote' && $pl['remote_id']) {
            $fetchUrl = $apiBase . '?' . http_build_query([$rServer => $server, $rType => 'playlist', $rId => $pl['remote_id']]);
            $raw = @file_get_contents($fetchUrl);
            if ($raw) {
                $data = json_decode($raw, true);
                $first = is_array($data) ? ($data[0] ?? null) : null;
                if ($first && !empty($first[$fPic])) {
                    $coverUrl = resolve_cover_url($first[$fPic], $apiBase, $server, $pId, $rServer, $rType);
                }
            }
        } else {
            $firstSong = $db->query("SELECT song_id, server FROM mapi_songs WHERE playlist_id=$pid ORDER BY sort_order ASC, id ASC LIMIT 1");
            if ($firstSong && ($fs = $firstSong->fetch_assoc())) {
                $fsServer = $fs['server'] ?: $server;
                $fetchUrl = $apiBase . '?' . http_build_query([$rServer => $fsServer, $rType => 'song', $rId => $fs['song_id']]);
                $raw = @file_get_contents($fetchUrl);
                if ($raw) {
                    $data = json_decode($raw, true);
                    $data = is_array($data) ? ($data[0] ?? $data) : null;
                    if ($data && !empty($data[$fPic])) {
                        $coverUrl = resolve_cover_url($data[$fPic], $apiBase, $fsServer, $pId, $rServer, $rType);
                    }
                }
            }
        }
    }
    if ($coverUrl) {
        $picEsc = $db->real_escape_string($coverUrl);
        $db->query("UPDATE mapi_playlists SET cover_url='$picEsc' WHERE id=$pid");
        $cUser = cover_resolve_user($db, $pid);
        $b64 = cover_fetch_and_cache($coverUrl, $_plCoverFile, $cUser, (string)$pid);
        echo json_encode(['ok' => true, 'cover_url' => $coverUrl, 'cover_b64' => $b64, 'msg' => '已获取封面']);
    } else {
        echo json_encode(['ok' => false, 'msg' => '未获取到封面']);
    }
    exit;
}

if ($action === 'song-add' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    csrf_require();
    $kid = (int)($_POST['key_id'] ?? 0);
    $plId = (int)($_POST['playlist_id'] ?? 0);
    $songId = trim($_POST['song_id'] ?? '');
    $name = trim($_POST['name'] ?? '');
    $artist = trim($_POST['artist'] ?? '');
    $server = in_array($_POST['server'] ?? '', ['tencent', 'netease']) ? $_POST['server'] : 'netease';
    if (!$plId || !$songId || !$name) { echo json_encode(['ok' => false, 'msg' => '参数不完整']); exit; }
    $ownerCheck = ($_SESSION['admin_is_admin'] ?? 99) <= 1
        ? $db->query("SELECT id, key_id FROM mapi_playlists WHERE id=$plId")
        : $db->query("SELECT id, key_id FROM mapi_playlists WHERE id=$plId AND key_id IN (SELECT id FROM mapi_keys WHERE user_id=" . (int)$_SESSION['admin_id'] . ")");
    if (!$ownerCheck || !($plRow = $ownerCheck->fetch_assoc())) { echo json_encode(['ok' => false, 'msg' => '无权限']); exit; }
    $plKeyId = (int)$plRow['key_id'];
    $dup = $db->query("SELECT id FROM mapi_songs WHERE playlist_id=$plId AND song_id='" . $db->real_escape_string($songId) . "' AND server='" . $db->real_escape_string($server) . "'");
    if ($dup && $dup->fetch_assoc()) { echo json_encode(['ok' => false, 'msg' => '歌曲已存在']); exit; }
    $maxOrder = $db->query("SELECT IFNULL(MAX(sort_order),0) FROM mapi_songs WHERE playlist_id=$plId");
    $nextOrder = $maxOrder ? (int)$maxOrder->fetch_row()[0] + 1 : 1;
    $stmt = $db->prepare("INSERT INTO mapi_songs (key_id, playlist_id, song_id, name, artist, server, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param('iissssi', $plKeyId, $plId, $songId, $name, $artist, $server, $nextOrder);
    $ok = $stmt->execute();
    if ($ok) {
        $apiBase = $cfg['api']['base_url'] ?? '';
        $rServer = $cfg['api']['param_server'] ?? 'server';
        $rType = $cfg['api']['param_type'] ?? 'type';
        $pId = $cfg['api']['param_id'] ?? 'id';
        if ($apiBase) {
            $songPicUrl = $apiBase . '?' . http_build_query([$rServer => $server, $rType => 'pic', $pId => $songId]);
            $cUser = cover_resolve_user($db, $plId);
            cover_fetch_and_cache($songPicUrl, $_songCoverFile, $cUser, $server . '_' . $songId);
        }
        if ($nextOrder === 1) {
            $coverCheck = $db->query("SELECT cover_mode, cover_url FROM mapi_playlists WHERE id=$plId");
            if ($coverCheck) {
                $coverRow = $coverCheck->fetch_assoc();
                if ($coverRow && in_array($coverRow['cover_mode'], ['auto', 'first_song']) && empty($coverRow['cover_url'])) {
                    $fPic = $cfg['api']['field_pic'] ?? 'pic';
                    $rId = $cfg['api']['param_id'] ?? 'id';
                    if ($apiBase) {
                        $fetchUrl = $apiBase . '?' . http_build_query([$rServer => $server, $rType => 'song', $rId => $songId]);
                        $raw = @file_get_contents($fetchUrl);
                        if ($raw) {
                            $songData = json_decode($raw, true);
                            $songData = is_array($songData) ? ($songData[0] ?? $songData) : null;
                            if ($songData && !empty($songData[$fPic])) {
                                $coverUrl = resolve_cover_url($songData[$fPic], $apiBase, $server, $pId, $rServer, $rType);
                                if ($coverUrl) {
                                    $picEsc = $db->real_escape_string($coverUrl);
                                    $db->query("UPDATE mapi_playlists SET cover_url='$picEsc' WHERE id=$plId");
                                    cover_fetch_and_cache($coverUrl, $_plCoverFile, $cUser ?? cover_resolve_user($db, $plId), (string)$plId);
                                }
                            }
                        }
                    }
                }
            }
        }
    }
    echo json_encode(['ok' => $ok, 'msg' => $ok ? '已添加' : '添加失败']);
    exit;
}

if ($action === 'song-remove' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    csrf_require();
    $sid = (int)($_POST['song_row_id'] ?? 0);
    if (!$sid) { echo json_encode(['ok' => false, 'msg' => '参数不完整']); exit; }
    if (($_SESSION['admin_is_admin'] ?? 99) <= 1) {
        $songInfo = $db->query("SELECT song_id, server FROM mapi_songs WHERE id=$sid");
        $stmt = $db->prepare("DELETE FROM mapi_songs WHERE id=?");
        $stmt->bind_param('i', $sid);
    } else {
        $songInfo = $db->query("SELECT song_id, server FROM mapi_songs WHERE id=$sid AND playlist_id IN (SELECT id FROM mapi_playlists WHERE key_id IN (SELECT id FROM mapi_keys WHERE user_id=" . (int)$_SESSION['admin_id'] . "))");
        $stmt = $db->prepare("DELETE FROM mapi_songs WHERE id=? AND playlist_id IN (SELECT id FROM mapi_playlists WHERE key_id IN (SELECT id FROM mapi_keys WHERE user_id=?))");
        $stmt->bind_param('ii', $sid, $_SESSION['admin_id']);
    }
    $ok = $stmt->execute();
    if ($ok && $songInfo && ($si = $songInfo->fetch_assoc())) {
        $cacheKey = ($si['server'] ?? 'netease') . '_' . ($si['song_id'] ?? '');
        $plOwner = $db->query("SELECT u.username FROM mapi_songs s LEFT JOIN mapi_playlists p ON s.playlist_id=p.id LEFT JOIN mapi_keys k ON p.key_id=k.id LEFT JOIN mapi_users u ON k.user_id=u.id WHERE s.id=$sid");
        $cUser = ($plOwner && $po = $plOwner->fetch_assoc()) ? ($po['username'] ?? 'unknown') : 'unknown';
        if ($cacheKey) cover_cache_unset($_songCoverFile, $cUser, $cacheKey);
    }
    echo json_encode(['ok' => $ok, 'msg' => $ok ? '已删除' : '删除失败']);
    exit;
}

if ($action === 'user-delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if ((($_SESSION['admin_is_admin'] ?? 99) > 1)) { flash_set('err','无权限'); header('Location: ?action=users'); exit; }
    $uid = (int)($_POST['id'] ?? 0);
    $curLevel = (int)($_SESSION['admin_is_admin'] ?? 99);
    if ($uid === (int)$_SESSION['admin_id']) { flash_set('err','不能删除自己'); header('Location: ?action=users'); exit; }
    $tr = $db->query("SELECT is_admin FROM mapi_users WHERE id=$uid");
    $tgtLevel = $tr ? (int)$tr->fetch_assoc()['is_admin'] : 99;
    if ($curLevel >= $tgtLevel) { flash_set('err','无权操作同级或更高权限的用户'); header('Location: ?action=users'); exit; }
    $stmt = $db->prepare("DELETE FROM mapi_users WHERE id=?");
    $stmt->bind_param('i', $uid);
    $stmt->execute();
    flash_set('msg','用户已删除');
    header('Location: ?action=users'); exit;
}
if ($action === 'user-admin' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if ((($_SESSION['admin_is_admin'] ?? 99) > 1)) { flash_set('err','无权限'); header('Location: ?action=users'); exit; }
    $uid = (int)($_POST['id'] ?? 0);
    $is = (int)($_POST['is_admin'] ?? 2);
    $curLevel = (int)($_SESSION['admin_is_admin'] ?? 99);
    if ($uid === (int)$_SESSION['admin_id']) { flash_set('err','不能修改自己'); header('Location: ?action=users'); exit; }
    // 只有超级管理员(is_admin=0)才能创建超级管理员
    if ($is < 1 && $curLevel > 0) { flash_set('err','无权限'); header('Location: ?action=users'); exit; }
    // 同级不能修改同级，只能上级修改下级
    $tr = $db->query("SELECT is_admin FROM mapi_users WHERE id=$uid");
    $tgtLevel = $tr ? (int)$tr->fetch_assoc()['is_admin'] : 99;
    if ($curLevel >= $tgtLevel) { flash_set('err','无权操作同级或更高权限的用户'); header('Location: ?action=users'); exit; }
    $stmt = $db->prepare("UPDATE mapi_users SET is_admin=? WHERE id=?");
    $stmt->bind_param('ii', $is, $uid);
    $stmt->execute();
    flash_set('msg','角色已更新');
    header('Location: ?action=users'); exit;
}

// ═══ 背景图预签名上传 URL（直传 S3，不经过服务器中转） ═══
if ($action === 'bg-presign') {
    header('Content-Type: application/json; charset=utf-8');
    if (!s3_available()) { http_response_code(400); echo json_encode(['ok' => false, 'error' => 'S3 未配置']); exit; }
    $input = json_decode(file_get_contents('php://input'), true);
    $_POST['_csrf'] = $input['_csrf'] ?? ''; csrf_require();
    $mime = $input['mime'] ?? '';
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
    if (!isset($allowed[$mime])) { http_response_code(400); echo json_encode(['ok' => false, 'error' => '不支持的图片格式']); exit; }
    $ext = $allowed[$mime];
    $safeName = preg_replace('/[^a-zA-Z0-9_\x{4e00}-\x{9fa5}-]/u', '_', $_SESSION['admin_user']);
    $key = 'backgrounds/' . $safeName . '.' . $ext;
    $result = s3_presigned_put_url($key, $mime, 300);
    if ($result['ok']) {
        echo json_encode(['ok' => true, 'url' => $result['url'], 'key' => $key]);
    } else {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => $result['error'] ?? '生成签名失败']);
    }
    exit;
}

// ═══ 背景图上传确认（客户端直传完成后调用） ═══
if ($action === 'bg-confirm') {
    header('Content-Type: application/json; charset=utf-8');
    $input = json_decode(file_get_contents('php://input'), true);
    $_POST['_csrf'] = $input['_csrf'] ?? ''; csrf_require();
    $key = $input['key'] ?? '';
    if (!$key) { http_response_code(400); echo json_encode(['ok' => false, 'error' => '缺少 key']); exit; }
    $uid = (int)$_SESSION['admin_id'];
    // 删除旧背景图
    $oldBg = $_SESSION['admin_background'] ?? '';
    if ($oldBg && $oldBg !== $key) s3_delete($oldBg);
    $stmt = $db->prepare("UPDATE mapi_users SET background=? WHERE id=?");
    $stmt->bind_param('si', $key, $uid);
    $stmt->execute();
    $_SESSION['admin_background'] = $key;
    echo json_encode(['ok' => true, 'url' => s3_get_url($key)]);
    exit;
}

// ═══ 动态壁纸 URL 保存 ═══
if ($action === 'bg-url-save') {
    header('Content-Type: application/json; charset=utf-8');
    $input = json_decode(file_get_contents('php://input'), true);
    $_POST['_csrf'] = $input['_csrf'] ?? ''; csrf_require();
    $url = trim($input['url'] ?? '');
    $uid = (int)$_SESSION['admin_id'];
    if ($url && !filter_var($url, FILTER_VALIDATE_URL)) {
        http_response_code(400); echo json_encode(['ok' => false, 'error' => 'URL 格式不正确']); exit;
    }
    if (mb_strlen($url) > 500) {
        http_response_code(400); echo json_encode(['ok' => false, 'error' => 'URL 过长']); exit;
    }
    $colCheck = $db->query("SHOW COLUMNS FROM mapi_users LIKE 'background_url'");
    if (!$colCheck || $colCheck->num_rows === 0) {
        $db->query("ALTER TABLE mapi_users ADD COLUMN background_url VARCHAR(500) DEFAULT '' AFTER background");
    }
    $stmt = $db->prepare("UPDATE mapi_users SET background_url=? WHERE id=?");
    $stmt->bind_param('si', $url, $uid);
    $stmt->execute();
    $_SESSION['admin_background_url'] = $url;
    setcookie('mapi_bg', $url, time()+31536000, '/');
    echo json_encode(['ok' => true, 'url' => $url]);
    exit;
}

// ═══ 个人资料 ═══
if ($action === 'profile' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    $uid = (int)$_SESSION['admin_id'];

    // 主题切换
    if (isset($_POST['_theme_toggle'])) {
        $mode = in_array($_POST['mode'] ?? '', ['light', 'dark']) ? $_POST['mode'] : 'light';
        $stmt = $db->prepare("UPDATE mapi_users SET theme_mode=? WHERE id=?");
        $stmt->bind_param('si', $mode, $uid);
        $stmt->execute();
        $_SESSION['admin_theme_mode'] = $mode;
        exit;
    }

    // 背景图删除
    if (isset($_POST['_bg_delete'])) {
        $oldBg = $_SESSION['admin_background'] ?? '';
        if ($oldBg) {
            s3_delete($oldBg);
        }
        $stmt = $db->prepare("UPDATE mapi_users SET background='',background_url='' WHERE id=?");
        $stmt->bind_param('i', $uid);
        $stmt->execute();
        $_SESSION['admin_background'] = '';
        $_SESSION['admin_background_url'] = '';
        flash_set('msg', '背景图已删除');
        header('Location: ?action=profile'); exit;
    }

    // 个人资料更新
    $nu = trim($_POST['username'] ?? '');
    $nq = trim($_POST['qq'] ?? '');
    $np = $_POST['password'] ?? '';
    if (mb_strlen($nu) > 30) { flash_set('err','账号最多30字符'); }
    elseif (mb_strlen($np) > 60) { flash_set('err','密码最多60字符'); }
    elseif (mb_strlen($nq) > 20) { flash_set('err','QQ号最多20字符'); }
    elseif ($nu && $nq) {
        $stmt = $db->prepare("SELECT id FROM mapi_users WHERE username=? AND id!=?");
        $stmt->bind_param('si', $nu, $uid); $stmt->execute();
        if ($stmt->get_result()->fetch_assoc()) {
            flash_set('err','账号已被使用');
        } else {
            if ($np) {
                $hash = password_hash($np, PASSWORD_DEFAULT);
                $stmt = $db->prepare("UPDATE mapi_users SET username=?,password=?,qq=? WHERE id=?");
                $stmt->bind_param('sssi', $nu, $hash, $nq, $uid);
            } else {
                $stmt = $db->prepare("UPDATE mapi_users SET username=?,qq=? WHERE id=?");
                $stmt->bind_param('ssi', $nu, $nq, $uid);
            }
            if ($stmt->execute()) {
                $_SESSION['admin_user'] = $nu;
                $_SESSION['admin_qq'] = $nq;
                flash_set('msg','资料已更新');
            } else { flash_set('err','更新失败'); }
        }
    } else { flash_set('err','请填写完整'); }
    header('Location: ?action=profile'); exit;
}

// ═══ 配置管理 ═══
if ($action === 'config' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['auto_theme'])) {
    csrf_require();
    $autoTheme = isset($_POST['auto_theme']) ? (int)$_POST['auto_theme'] : 0;
    $themeMode = isset($_POST['theme_mode']) && in_array($_POST['theme_mode'], ['light','dark']) ? $_POST['theme_mode'] : 'light';
    $lyricsDefault = isset($_POST['lyrics_default']) ? (int)$_POST['lyrics_default'] : 1;
    $autoplayDefault = isset($_POST['autoplay_default']) ? (int)$_POST['autoplay_default'] : 0;
    $stmt = $db->prepare("UPDATE mapi_users SET auto_theme=?, theme_mode=?, lyrics_default=?, autoplay_default=? WHERE id=?");
    $stmt->bind_param('isiii', $autoTheme, $themeMode, $lyricsDefault, $autoplayDefault, $_SESSION['admin_id']);
    if ($stmt->execute()) {
        $_SESSION['admin_auto_theme'] = $autoTheme;
        $_SESSION['admin_theme_mode'] = $themeMode;
        $_SESSION['admin_lyrics_default'] = $lyricsDefault;
        $_SESSION['admin_autoplay_default'] = $autoplayDefault;
        flash_set('msg','配置已保存');
    } else { flash_set('err','保存失败'); }
    header('Location: ?action=config'); exit;
}

// ═══ 通行密钥注册/管理 ═══
if ($action === 'pk-begin' && $_SESSION['admin_id']) {
    $rpId = webauthn_get_rp_id();
    if (!webauthn_is_valid_rp_id($rpId)) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'WebAuthn不支持IP地址访问，请使用域名或localhost访问']);
        exit;
    }
    $challenge = random_bytes(32);
    $_SESSION['pk_challenge'] = base64_encode($challenge);
    $uid = (int)$_SESSION['admin_id'];
    $stmt = $db->prepare("SELECT credential_id FROM mapi_passkeys WHERE user_id=?");
    $stmt->bind_param('i', $uid); $stmt->execute();
    $excluded = [];
    $r = $stmt->get_result();
    while ($row = $r->fetch_assoc()) $excluded[] = base64url_encode(base64_decode($row['credential_id']));
    $_SESSION['pk_rp_id'] = $rpId;
    $_SESSION['pk_origin'] = webauthn_get_origin();
    header('Content-Type: application/json');
    echo json_encode(['challenge' => base64url_encode($challenge), 'rpId' => $rpId, 'rpName' => '顺雅音乐', 'userId' => $uid, 'userName' => $_SESSION['admin_user'], 'excludeCredentials' => $excluded, 'timeout' => 300000, '_csrf' => csrf_token()]);
    exit;
}
if ($action === 'pk-complete' && $_SERVER['REQUEST_METHOD'] === 'POST' && $_SESSION['admin_id']) {
    csrf_require();
    $ao = $_POST['attestationObject'] ?? ''; $cjd = $_POST['clientDataJSON'] ?? '';
    $info = webauthn_verify_attestation($ao, $cjd);
    if (!is_array($info)) { header('Content-Type: application/json'); echo json_encode(['ok'=>false,'err'=>$info ?: '验证失败']); exit; }
    $cid = $info['credential_id']; $pem = $info['public_key_pem']; $cnt = $info['counter'];
    $st = $db->prepare("SELECT id FROM mapi_passkeys WHERE credential_id=?");
    $st->bind_param('s', $cid); $st->execute();
    if ($st->get_result()->fetch_assoc()) { header('Content-Type: application/json'); echo json_encode(['ok'=>false,'err'=>'通行密钥已存在']); exit; }
    $st2 = $db->prepare("INSERT INTO mapi_passkeys (user_id, credential_id, public_key_pem, counter) VALUES (?,?,?,?)");
    $st2->bind_param('issi', $_SESSION['admin_id'], $cid, $pem, $cnt);
    if ($st2->execute()) {
        unset($_SESSION['pk_challenge'], $_SESSION['pk_rp_id'], $_SESSION['pk_origin']);
        header('Content-Type: application/json'); echo json_encode(['ok'=>true]);
    } else { header('Content-Type: application/json'); echo json_encode(['ok'=>false,'err'=>'存储失败']); }
    exit;
}
if ($action === 'pk-delete' && $_SERVER['REQUEST_METHOD'] === 'POST' && $_SESSION['admin_id']) {
    csrf_require();
    $pid = (int)($_POST['id'] ?? 0);
    $st = $db->prepare("DELETE FROM mapi_passkeys WHERE id=? AND user_id=?");
    $st->bind_param('ii', $pid, $_SESSION['admin_id']);
    $st->execute();
    if ($st->affected_rows > 0) flash_set('msg','通行密钥已删除');
    else flash_set('err','无权限');
    header('Location: ?action=profile'); exit;
}

// ═══ 服务设置 ═══
if ($action === 'settings' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    if (isset($_POST['_geetest_submit'])) {
        $geetestCaptchaId = trim($_POST['geetest_captcha_id'] ?? '');
        $geetestKey = trim($_POST['geetest_key'] ?? '');
        $r = $db->query("SELECT COUNT(*) as cnt FROM mapi_geetest");
        $exists = $r && (int)$r->fetch_assoc()['cnt'] > 0;
        if ($exists) {
            $st = $db->prepare("UPDATE mapi_geetest SET captcha_id=?, `key`=?");
            $st->bind_param('ss', $geetestCaptchaId, $geetestKey);
        } else {
            $st = $db->prepare("INSERT INTO mapi_geetest (captcha_id, `key`) VALUES (?, ?)");
            $st->bind_param('ss', $geetestCaptchaId, $geetestKey);
        }
        if ($st->execute()) flash_set('msg', '极验配置已保存');
        else flash_set('err', '保存失败');
    }
    if (isset($_POST['_ann_submit'])) {
        $annContent = trim($_POST['announcement'] ?? '');
        $annSave = [
            'enabled' => $annContent !== '',
            'title' => '系统公告',
            'content' => $annContent,
            'align' => in_array($_POST['ann_align'] ?? '', ['left','center']) ? $_POST['ann_align'] : 'left',
            'updated_at' => time(),
        ];
        $annJson = json_encode($annSave, JSON_UNESCAPED_UNICODE);
        $annSt = $db->prepare("REPLACE INTO mapi_config (config_key, config_value) VALUES ('announcement', ?)");
        $annSt->bind_param('s', $annJson);
        if ($annSt->execute()) flash_set('msg', '公告已' . ($annContent !== '' ? '发布' : '关闭'));
        else flash_set('err', '公告保存失败');
    }
    if (isset($_POST['_login_submit'])) {
        $loginTheme = in_array($_POST['login_theme'] ?? '', ['light','dark']) ? $_POST['login_theme'] : 'light';
        $loginBg = mb_substr(trim($_POST['login_bg'] ?? ''), 0, 500);
        $st = $db->prepare("REPLACE INTO mapi_config (config_key, config_value) VALUES ('login_theme', ?)");
        $st->bind_param('s', $loginTheme); $st->execute();
        $st = $db->prepare("REPLACE INTO mapi_config (config_key, config_value) VALUES ('login_bg', ?)");
        $st->bind_param('s', $loginBg); $st->execute();
        flash_set('msg', '登录页配置已保存');
    }
    if (isset($_POST['_key_limit_submit']) && ($_SESSION['admin_is_admin'] ?? 99) === 0) {
        $limit = max(1, min(100, (int)($_POST['key_limit'] ?? 1)));
        $json = json_encode(['limit' => $limit], JSON_UNESCAPED_UNICODE);
        $st = $db->prepare("REPLACE INTO mapi_config (config_key, config_value) VALUES ('key_limit', ?)");
        $st->bind_param('s', $json);
        if ($st->execute()) flash_set('msg', '密钥限制已更新为 ' . $limit . ' 个');
        else flash_set('err', '保存失败');
    }
    if (isset($_POST['_s3_submit']) && ($_SESSION['admin_is_admin'] ?? 99) === 0) {
        $s3Save = [
            'endpoint' => trim($_POST['s3_endpoint'] ?? ''),
            'access_key' => trim($_POST['s3_access_key'] ?? ''),
            'secret_key' => trim($_POST['s3_secret_key'] ?? ''),
            'bucket' => trim($_POST['s3_bucket'] ?? ''),
            'region' => trim($_POST['s3_region'] ?? 'auto'),
            'path_prefix' => trim($_POST['s3_path_prefix'] ?? ''),
            'custom_domain' => trim($_POST['s3_custom_domain'] ?? ''),
        ];
        $json = json_encode($s3Save, JSON_UNESCAPED_UNICODE);
        $st = $db->prepare("REPLACE INTO mapi_config (config_key, config_value) VALUES ('s3', ?)");
        $st->bind_param('s', $json);
        if ($st->execute()) flash_set('msg', 'S3 配置已保存');
        else flash_set('err', '保存失败');
    }
    if (isset($_POST['_epay_submit']) && ($_SESSION['admin_is_admin'] ?? 99) === 0) {
        $epaySave = [
            'api_url' => trim($_POST['epay_api_url'] ?? ''),
            'pid' => trim($_POST['epay_pid'] ?? ''),
            'key' => trim($_POST['epay_key'] ?? ''),
        ];
        $json = json_encode($epaySave, JSON_UNESCAPED_UNICODE);
        $st = $db->prepare("REPLACE INTO mapi_config (config_key, config_value) VALUES ('epay', ?)");
        $st->bind_param('s', $json);
        if ($st->execute()) flash_set('msg', '易支付配置已保存');
        else flash_set('err', '保存失败');
    }
    if (isset($_POST['_api_submit']) && ($_SESSION['admin_is_admin'] ?? 99) === 0) {
        $apiSave = [
            'meting' => trim($_POST['api_meting'] ?? ''),
            'qq_referer' => trim($_POST['api_qq_referer'] ?? ''),
            'qq_cover' => trim($_POST['api_qq_cover'] ?? ''),
            'param_id' => trim($_POST['api_param_id'] ?? 'id') ?: 'id',
            'param_auth' => trim($_POST['api_param_auth'] ?? 'auth') ?: 'auth',
            'req_params' => [
                'server' => trim($_POST['api_req_server'] ?? 'server') ?: 'server',
                'type' => trim($_POST['api_req_type'] ?? 'type') ?: 'type',
                'id' => trim($_POST['api_req_id'] ?? 'id') ?: 'id',
            ],
            'fields' => [
                'title' => trim($_POST['api_field_title'] ?? 'title') ?: 'title',
                'artist' => trim($_POST['api_field_artist'] ?? 'author') ?: 'author',
                'url' => trim($_POST['api_field_url'] ?? 'url') ?: 'url',
                'pic' => trim($_POST['api_field_pic'] ?? 'pic') ?: 'pic',
                'lrc' => trim($_POST['api_field_lrc'] ?? 'lrc') ?: 'lrc',
            ],
        ];
        $json = json_encode($apiSave, JSON_UNESCAPED_UNICODE);
        $st = $db->prepare("REPLACE INTO mapi_config (config_key, config_value) VALUES ('mapi_api', ?)");
        $st->bind_param('s', $json);
        if ($st->execute()) flash_set('msg', '音乐 API 配置已保存');
        else flash_set('err', '保存失败');

        $cfgData = require $configFile;
        if (!isset($cfgData['api'])) $cfgData['api'] = [];
        $cfgData['api']['base_url']   = $apiSave['meting'];
        $cfgData['api']['qq_referer'] = $apiSave['qq_referer'];
        $cfgData['api']['qq_cover']   = $apiSave['qq_cover'];
        $newContent = "<?php\nreturn " . var_export($cfgData, true) . ";\n";
        if (file_put_contents($configFile, $newContent) !== false && file_exists($installLock)) {
            $lockLines = [];
            foreach (explode("\n", file_get_contents($installLock)) as $l) {
                $l = trim($l); if ($l === '') continue;
                if (strpos($l, ': ') !== false) {
                    [$k, $v] = explode(': ', $l, 2);
                    $lockLines[trim($k)] = trim($v);
                }
            }
            $lockLines['config_hash'] = hash_file('sha256', $configFile);
            $newLock = '';
            foreach ($lockLines as $k => $v) $newLock .= "{$k}: {$v}\n";
            file_put_contents($installLock, $newLock);
        }
    }
    if (isset($_POST['smtp_host']) && ($_SESSION['admin_is_admin'] ?? 99) === 0) {
        $smtpSave = [
            'host' => trim($_POST['smtp_host'] ?? ''),
            'port' => (int)($_POST['smtp_port'] ?? 465),
            'user' => trim($_POST['smtp_user'] ?? ''),
            'pass' => trim($_POST['smtp_pass'] ?? ''),
            'encrypt' => in_array($_POST['smtp_encrypt'] ?? '', ['ssl','tls','none']) ? $_POST['smtp_encrypt'] : 'ssl',
            'from' => trim($_POST['smtp_from'] ?? ''),
            'name' => trim($_POST['smtp_name'] ?? ''),
        ];
        $json = json_encode($smtpSave, JSON_UNESCAPED_UNICODE);
        $st = $db->prepare("REPLACE INTO mapi_config (config_key, config_value) VALUES ('smtp', ?)");
        $st->bind_param('s', $json);
        if ($st->execute()) flash_set('msg', 'SMTP 配置已保存');
        else flash_set('err', '保存失败');
    }
    if (isset($_POST['_mail_tpl_submit']) && ($_SESSION['admin_is_admin'] ?? 99) === 0) {
        $tplSave = [
            'subject' => trim($_POST['mail_tpl_subject'] ?? '') ?: '顺雅音乐 - 验证码邮件',
            'body' => trim($_POST['mail_tpl_body'] ?? '') ?: '',
            'html' => !empty($_POST['mail_tpl_html']),
        ];
        $json = json_encode($tplSave, JSON_UNESCAPED_UNICODE);
        $st = $db->prepare("REPLACE INTO mapi_config (config_key, config_value) VALUES ('mail_template', ?)");
        $st->bind_param('s', $json);
        if ($st->execute()) flash_set('msg', '邮件模板已保存');
        else flash_set('err', '保存失败');
    }
    header('Location: ?action=settings'); exit;
}

// ═══ 易支付测试 ═══
if ($action === 'epay-test') {
    if (!epay_available()) {
        flash_set('err', '请先在设置中配置易支付参数');
        header('Location: ?action=settings'); exit;
    }
    $tradeNo = date('YmdHis') . rand(1000, 9999);
    $base = ($_SERVER['HTTPS'] ?? '') === 'on' ? 'https://' : 'http://';
    $host = $base . $_SERVER['HTTP_HOST'] . dirname($_SERVER['SCRIPT_NAME']);
    $order = [
        'out_trade_no' => $tradeNo,
        'name' => '测试支付 - 0.01元',
        'money' => '0.01',
        'type' => 'alipay',
        'notify_url' => $host . '/?action=epay-notify',
        'return_url' => $host . '/?action=epay-return',
    ];
    $result = epay_submit($order);
    if (!$result['ok']) {
        flash_set('err', '支付下单失败：' . ($result['error'] ?? '未知错误'));
        header('Location: ?action=settings'); exit;
    }
    // 输出 POST 自动提交表单，确保易支付正确生成支付二维码
    $apiUrl = rtrim(epay_config()['api_url'], '/') . '/submit.php';
    ?><!DOCTYPE html>
<html><head><meta charset="utf-8"><title>跳转支付</title></head>
<body onload="document.getElementById('payForm').submit()">
<form id="payForm" method="post" action="<?= htmlspecialchars($apiUrl) ?>">
<?php foreach ($result['params'] as $k => $v): ?>
<input type="hidden" name="<?= htmlspecialchars($k) ?>" value="<?= htmlspecialchars($v) ?>">
<?php endforeach; ?>
</form>
</body></html>
<?php
    exit;
}

// ═══ 商店 - 添加商品 ═══
if ($action === 'shop-add') {
    if ((($_SESSION['admin_is_admin'] ?? 99) > 1)) { header('Location: ?action=shop'); exit; }
    csrf_require();
    $name = trim($_POST['name'] ?? '');
    $type = $_POST['type'] ?? 'month';
    $price = floatval($_POST['price'] ?? 0);
    $duration = (int)($_POST['duration_days'] ?? 30);
    $desc = trim($_POST['description'] ?? '');
    if (!$name || $price < 0 || $duration < 1) {
        flash_set('err', '请填写完整信息');
    } else {
        $stmt = $db->prepare("INSERT INTO mapi_products (name, type, price, duration_days, description) VALUES (?,?,?,?,?)");
        if ($stmt) {
            $stmt->bind_param('ssdis', $name, $type, $price, $duration, $desc);
            if ($stmt->execute()) flash_set('msg', '商品已添加');
            else flash_set('err', '添加失败');
        } else flash_set('err', '数据库错误');
    }
    header('Location: ?action=shop'); exit;
}

// ═══ 商店 - 编辑商品 ═══
if ($action === 'shop-edit') {
    if ((($_SESSION['admin_is_admin'] ?? 99) > 1)) { header('Location: ?action=shop'); exit; }
    csrf_require();
    $id = (int)($_POST['id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $type = $_POST['type'] ?? 'month';
    $price = floatval($_POST['price'] ?? 0);
    $duration = (int)($_POST['duration_days'] ?? 30);
    $desc = trim($_POST['description'] ?? '');
    if (!$id || !$name || $price < 0 || $duration < 1) {
        flash_set('err', '请填写完整信息');
    } else {
        $stmt = $db->prepare("UPDATE mapi_products SET name=?, type=?, price=?, duration_days=?, description=? WHERE id=?");
        if ($stmt) {
            $stmt->bind_param('ssdisi', $name, $type, $price, $duration, $desc, $id);
            if ($stmt->execute()) flash_set('msg', '商品已更新');
            else flash_set('err', '更新失败');
        } else flash_set('err', '数据库错误');
    }
    header('Location: ?action=shop'); exit;
}

// ═══ 商店 - 删除商品 ═══
if ($action === 'shop-delete') {
    if ((($_SESSION['admin_is_admin'] ?? 99) > 1)) { header('Location: ?action=shop'); exit; }
    csrf_require();
    $id = (int)($_POST['id'] ?? 0);
    if ($id) {
        $db->query("DELETE FROM mapi_products WHERE id=$id");
        flash_set('msg', '商品已删除');
    }
    header('Location: ?action=shop'); exit;
}

// ═══ 商店 - 普通用户购买 ═══
if ($action === 'shop-buy') {
    if (!epay_available()) {
        flash_set('err', '支付系统暂不可用');
        header('Location: ?action=shop'); exit;
    }
    $pid = (int)($_GET['id'] ?? 0);
    $r = $db->query("SELECT * FROM mapi_products WHERE id=$pid AND status=1");
    if (!$r || !$product = $r->fetch_assoc()) {
        flash_set('err', '商品不存在');
        header('Location: ?action=shop'); exit;
    }
    $uid = (int)$_SESSION['admin_id'];
    $tradeNo = date('YmdHis') . rand(1000, 9999);
    $base = ($_SERVER['HTTPS'] ?? '') === 'on' ? 'https://' : 'http://';
    $host = $base . $_SERVER['HTTP_HOST'] . dirname($_SERVER['SCRIPT_NAME']);
    $stmt = $db->prepare("INSERT INTO mapi_orders (user_id, product_id, product_name, type, price, duration_days, trade_no, status) VALUES (?,?,?,?,?,?,?,0)");
    if ($stmt) {
        $stmt->bind_param('iissdis', $uid, $product['id'], $product['name'], $product['type'], $product['price'], $product['duration_days'], $tradeNo);
        $stmt->execute();
    }
    $order = [
        'out_trade_no' => $tradeNo,
        'name' => $product['name'],
        'money' => number_format($product['price'], 2),
        'type' => 'alipay',
        'notify_url' => $host . '/?action=epay-notify',
        'return_url' => $host . '/?action=epay-return',
    ];
    $result = epay_submit($order);
    if (!$result['ok']) {
        flash_set('err', '下单失败：' . ($result['error'] ?? '未知错误'));
        header('Location: ?action=shop'); exit;
    }
    $apiUrl = rtrim(epay_config()['api_url'], '/') . '/submit.php';
    ?><!DOCTYPE html>
<html><head><meta charset="utf-8"><title>跳转支付</title></head>
<body onload="document.getElementById('payForm').submit()">
<form id="payForm" method="post" action="<?= htmlspecialchars($apiUrl) ?>">
<?php foreach ($result['params'] as $k => $v): ?>
<input type="hidden" name="<?= htmlspecialchars($k) ?>" value="<?= htmlspecialchars($v) ?>">
<?php endforeach; ?>
</form>
</body></html>
<?php
    exit;
}

// ═══ 商店 - 管理员免费购买 ═══
if ($action === 'shop-free-buy') {
    if ((($_SESSION['admin_is_admin'] ?? 99) > 1)) { header('Location: ?action=shop'); exit; }
    csrf_require();
    $pid = (int)($_POST['id'] ?? 0);
    $r = $db->query("SELECT * FROM mapi_products WHERE id=$pid AND status=1");
    if (!$r || !$product = $r->fetch_assoc()) {
        flash_set('err', '商品不存在');
        header('Location: ?action=shop'); exit;
    }
    $uid = (int)$_SESSION['admin_id'];
    $tradeNo = 'FREE_' . date('YmdHis') . rand(1000, 9999);
    $stmt = $db->prepare("INSERT INTO mapi_orders (user_id, product_id, product_name, type, price, duration_days, trade_no, status, paid_at) VALUES (?,?,?,?,?,?,?,1,NOW())");
    $price = 0.00;
    if ($stmt) {
        $stmt->bind_param('iissdis', $uid, $product['id'], $product['name'], $product['type'], $price, $product['duration_days'], $tradeNo);
        $stmt->execute();
    }
    $db->query("UPDATE mapi_users SET expire_at=IF(expire_at IS NULL OR expire_at < NOW(), DATE_ADD(NOW(), INTERVAL " . (int)$product['duration_days'] . " DAY), DATE_ADD(expire_at, INTERVAL " . (int)$product['duration_days'] . " DAY)) WHERE id=$uid");
    flash_set('msg', '已免费激活 ' . htmlspecialchars($product['name']));
    header('Location: ?action=shop'); exit;
}

// ═══ 商店 - 管理员赠送 ═══
if ($action === 'shop-gift') {
    if ((($_SESSION['admin_is_admin'] ?? 99) > 1)) { header('Location: ?action=shop'); exit; }
    csrf_require();
    $pid = (int)($_POST['product_id'] ?? 0);
    $targetUid = (int)($_POST['user_id'] ?? 0);
    if (!$targetUid) { flash_set('err', '请选择目标用户'); header('Location: ?action=shop'); exit; }
    $r = $db->query("SELECT * FROM mapi_products WHERE id=$pid AND status=1");
    if (!$r || !$product = $r->fetch_assoc()) {
        flash_set('err', '商品不存在');
        header('Location: ?action=shop'); exit;
    }
    $r2 = $db->query("SELECT id, username FROM mapi_users WHERE id=$targetUid");
    if (!$r2 || !$target = $r2->fetch_assoc()) {
        flash_set('err', '目标用户不存在');
        header('Location: ?action=shop'); exit;
    }
    $tradeNo = 'GIFT_' . date('YmdHis') . rand(1000, 9999);
    $stmt = $db->prepare("INSERT INTO mapi_orders (user_id, product_id, product_name, type, price, duration_days, trade_no, status, paid_at) VALUES (?,?,?,?,?,?,?,1,NOW())");
    $price = 0.00;
    if ($stmt) {
        $stmt->bind_param('iissdis', $targetUid, $product['id'], $product['name'], $product['type'], $price, $product['duration_days'], $tradeNo);
        $stmt->execute();
    }
    $db->query("UPDATE mapi_users SET expire_at=IF(expire_at IS NULL OR expire_at < NOW(), DATE_ADD(NOW(), INTERVAL " . (int)$product['duration_days'] . " DAY), DATE_ADD(expire_at, INTERVAL " . (int)$product['duration_days'] . " DAY)) WHERE id=$targetUid");
    flash_set('msg', '已赠送 ' . htmlspecialchars($product['name']) . ' 给 ' . htmlspecialchars($target['username']));
    header('Location: ?action=shop'); exit;
}

// ═══ 订单 - 取消未支付订单 ═══
if ($action === 'order-cancel' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    $oid = (int)($_POST['id'] ?? 0);
    $uid = (int)$_SESSION['admin_id'];
    $isAdmin = $_SESSION['admin_is_admin'] ?? 99;
    $cond = $isAdmin <= 1 ? "id=$oid" : "id=$oid AND user_id=$uid";
    $r = $db->query("SELECT id, status FROM mapi_orders WHERE $cond");
    $order = $r ? $r->fetch_assoc() : null;
    if (!$order) {
        flash_set('err', '订单不存在');
    } elseif ($order['status'] == 1) {
        flash_set('err', '已支付订单无法取消');
    } else {
        $db->query("DELETE FROM mapi_orders WHERE id=$oid");
        flash_set('msg', '订单已取消');
    }
    header('Location: ?action=orders'); exit;
}

// ═══ 订单 - 清空订单记录 ═══
if ($action === 'order-clear' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    $isAdmin = $_SESSION['admin_is_admin'] ?? 99;
    $uid = (int)$_SESSION['admin_id'];
    if ($isAdmin <= 1) {
        $db->query("DELETE FROM mapi_orders");
    } else {
        $db->query("DELETE FROM mapi_orders WHERE user_id=$uid");
    }
    flash_set('msg', '订单记录已清空');
    header('Location: ?action=orders'); exit;
}

// ═══ 调试模式开关（仅超管） ═══
if ($action === 'debug-toggle' && ($_SESSION['admin_is_admin'] ?? 99) === 0) {
    $debug = (int)($_POST['debug'] ?? 0);
    $db->query("DELETE FROM mapi_super_settings WHERE setting_key='debug_mode'");
    $db->query("INSERT INTO mapi_super_settings (setting_key, setting_value) VALUES ('debug_mode', '" . ($debug ? '1' : '0') . "')");
    echo 'ok';
    exit;
}

// ═══ 清空调用记录（仅超管和管理员） ═══
if ($action === 'clear-logs' && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_SESSION['admin_is_admin'] ?? 99) <= 1) {
    csrf_require();
    $db->query("DELETE FROM mapi_logs");
    flash_set('msg', '调用记录已清空');
    header('Location: ?action=dashboard'); exit;
}

// ═══ 获取共享数据 ═══
$totalCalls = 0; $todayCalls = 0; $totalTraffic = 0;
$r = $db->query("SELECT COUNT(*) as c, COALESCE(SUM(traffic_bytes), 0) as tb FROM mapi_logs");
if ($r) { $row = $r->fetch_assoc(); $totalCalls = (int)$row['c']; $totalTraffic = (int)$row['tb']; }
$r = $db->query("SELECT COUNT(*) as c FROM mapi_logs WHERE DATE(created_at)='" . date('Y-m-d') . "'");
if ($r) $todayCalls = (int)$r->fetch_assoc()['c'];

$users = [];
$r = $db->query("SELECT id,username,qq,is_admin,expire_at,created_at FROM mapi_users WHERE id<>" . (int)$_SESSION['admin_id'] . " ORDER BY id");
if ($r) while ($row = $r->fetch_assoc()) $users[] = $row;

$keys = [];
if (($_SESSION['admin_is_admin'] ?? 99) === 0) {
    $r = $db->query("SELECT k.*, u.username FROM mapi_keys k LEFT JOIN mapi_users u ON k.user_id=u.id ORDER BY k.id DESC");
} else {
    $r = $db->query("SELECT k.*, u.username FROM mapi_keys k LEFT JOIN mapi_users u ON k.user_id=u.id WHERE k.user_id=" . (int)$_SESSION['admin_id'] . " ORDER BY k.id DESC");
}
if ($r) while ($row = $r->fetch_assoc()) $keys[] = $row;

$passkeys = [];
$r = $db->query("SELECT id, credential_id, created_at FROM mapi_passkeys WHERE user_id=" . (int)$_SESSION['admin_id'] . " ORDER BY id DESC");
if ($r) while ($row = $r->fetch_assoc()) $passkeys[] = $row;

$songsByKey = [];
$songsByPlaylist = [];
$r = $db->query("SELECT id, playlist_id, song_id, name, artist, server, sort_order FROM mapi_songs ORDER BY sort_order ASC, id ASC");
if ($r) while ($row = $r->fetch_assoc()) {
    $pid = (int)$row['playlist_id'];
    if (!isset($songsByPlaylist[$pid])) $songsByPlaylist[$pid] = [];
    $songsByPlaylist[$pid][] = $row;
}

$playlistsByKey = [];
$r = $db->query("SELECT id, key_id, name, type, remote_id, server, cover_url, cover_mode, sort_order FROM mapi_playlists ORDER BY sort_order ASC, id ASC");
if ($r) while ($row = $r->fetch_assoc()) {
    $kid = (int)$row['key_id'];
    if (!isset($playlistsByKey[$kid])) $playlistsByKey[$kid] = [];
    $playlistsByKey[$kid][] = $row;
}

$logs = [];
// 读取调试模式状态
$debugMode = false;
$r = $db->query("SELECT setting_value FROM mapi_super_settings WHERE setting_key='debug_mode'");
if ($r && $row = $r->fetch_assoc()) {
    $debugMode = ($row['setting_value'] === '1');
}
// 永久过滤公开端点和预检请求
$filterEndpoints = ['verify-key', 'get-config', 'get-announcement', 'pic:'];
$endpointFilter = '';
foreach ($filterEndpoints as $ep) {
    $endpointFilter .= " AND endpoint NOT LIKE '%" . $db->real_escape_string($ep) . "%'";
}
// 预检请求过滤
$endpointFilter .= " AND endpoint NOT LIKE '%OPTIONS%'";
// 构建查询：调试模式关闭时过滤本机/回环地址
$filterIP = '';
if (!$debugMode) {
    $filterIP = " AND ip NOT IN ('127.0.0.1','::1') AND (referer='' OR (referer NOT LIKE '%localhost%' AND referer NOT LIKE '%127.0.0.1%'))";
}
$r = $db->query("SELECT ip,referer,endpoint,api_key,created_at FROM mapi_logs WHERE 1=1" . $filterIP . $endpointFilter . " ORDER BY id DESC LIMIT 20");
if ($r) while ($row = $r->fetch_assoc()) $logs[] = $row;

// ═══ 渲染页面 ═══
require __DIR__ . '/layout/header.php';

$pageFile = __DIR__ . '/pages/' . $action . '.php';
if (file_exists($pageFile)) {
    require $pageFile;
} else {
    require __DIR__ . '/pages/dashboard.php';
}

require __DIR__ . '/layout/footer.php';