<?php

// CSRF 与闪存消息
csrf_token();
$msg = flash_get('msg') ?? '';
$err = flash_get('err') ?? '';

// 用户配置
$autoTheme = (int)($_SESSION['admin_auto_theme'] ?? 1);
$themeMode = $_SESSION['admin_theme_mode'] ?? 'light';
$lyricsDefault = (int)($_SESSION['admin_lyrics_default'] ?? 1);
$autoplayDefault = (int)($_SESSION['admin_autoplay_default'] ?? 0);
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