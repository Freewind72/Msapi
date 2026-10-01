<?php

// CSRF 与闪存消息
csrf_token();
$msg = flash_get('msg') ?? '';
$err = flash_get('err') ?? '';

// 用户配置
$autoTheme = (int)($_SESSION['admin_auto_theme'] ?? 1);
// 播放器主题模式（音乐配置页使用）
$themeMode = $_SESSION['admin_theme_mode'] ?? 'light';
$lyricsDefault = (int)($_SESSION['admin_lyrics_default'] ?? 1);
$autoplayDefault = (int)($_SESSION['admin_autoplay_default'] ?? 0);
// 播放器首次加载位置（side:pct，如 right:88）；空值用内置默认（右侧 88% 高度）
$playerPos = trim((string)($_SESSION['admin_player_pos'] ?? ''));
if (!preg_match('/^(left|right):\d{1,3}$/', $playerPos)) $playerPos = 'right:88';
list($playerPosSide, $playerPosY) = explode(':', $playerPos);
$isAdmin = $_SESSION['admin_is_admin'] ?? 99;

// Pusher 上线通知
if ($_SESSION['admin_id']) {
    $lastPing = intval($_SESSION['_pusher_ping'] ?? 0);
    if (time() - $lastPing > 60) {
        $_SESSION['_pusher_ping'] = time();
        pusher_trigger(PUSHER_CHANNEL, 'user-online', [
            'user_id'  => (string)$_SESSION['admin_id'],
            'username' => $_SESSION['admin_user'],
        ]);
    }
}

// 初始化数据版本
$r = $db->query("SELECT COUNT(*) as cnt FROM mapi_config WHERE config_key='data_version'");
if ($r) {
    $row = $r->fetch_assoc();
    if ((int)$row['cnt'] === 0) {
        $db->query("INSERT INTO mapi_config (config_key, config_value) VALUES ('data_version', '1.0.0')");
    }
}