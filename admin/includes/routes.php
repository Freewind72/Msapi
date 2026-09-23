<?php

// 无需登录的 handler（action => handler 文件）
$preLoginHandlers = [
    'js-log'      => 'handlers/js_log.php',
];

// 登录相关 action（未登录时可访问）
$loginActions = ['', 'register', 'get-avatar', 'pk-login-begin', 'pk-login-complete'];

// action 白名单
$allowed = [
    'dashboard','keys','keys-create','keys-delete','playlist-detail',
    'playlist-create','playlist-delete','playlist-update','playlist-update-cover','playlist-fetch-cover',
    'song-add','song-remove',
    'users','user-delete','user-admin',
    'profile','config','settings',
    'pk-begin','pk-complete','pk-delete',
    'bg-presign','bg-confirm','bg-url-save',
    'pusher-auth','pusher-online-users','debug-toggle','clear-logs',
    'logout',
];

// 已登录 API handler 路由表（action => [文件, 是否需要POST]）
$apiHandlers = [
    'logout'               => ['handlers/logout.php', false],
    'pusher-auth'          => ['handlers/pusher_auth.php', false],
    'pusher-online-users'  => ['handlers/pusher_online.php', false],
    'pk-begin'             => ['handlers/passkeys.php', false],

    'keys-create'          => ['handlers/keys.php', true],
    'keys-delete'          => ['handlers/keys.php', true],

    'playlist-create'      => ['handlers/playlists.php', true],
    'playlist-delete'      => ['handlers/playlists.php', true],
    'playlist-update'      => ['handlers/playlists.php', true],
    'playlist-update-cover'=> ['handlers/playlists.php', true],
    'playlist-fetch-cover' => ['handlers/playlists.php', true],

    'song-add'             => ['handlers/songs.php', true],
    'song-remove'          => ['handlers/songs.php', true],

    'user-delete'          => ['handlers/users_mgmt.php', true],
    'user-admin'           => ['handlers/users_mgmt.php', true],

    'bg-presign'            => ['handlers/background.php', true],
    'bg-confirm'            => ['handlers/background.php', true],
    'bg-url-save'           => ['handlers/background.php', true],

    'profile'              => ['handlers/profile.php', true],
    'config'               => ['handlers/config_user.php', true],
    'settings'             => ['handlers/settings.php', true],

    'pk-complete'          => ['handlers/passkeys.php', true],
    'pk-delete'            => ['handlers/passkeys.php', true],

    'debug-toggle'         => ['handlers/system.php', true],
    'clear-logs'           => ['handlers/system.php', true],
];

// 页面模板映射（action => 页面文件）
$pageMap = [
    'dashboard'       => 'pages/dashboard.php',
    'keys'            => 'pages/keys.php',
    'users'           => 'pages/users.php',
    'profile'         => 'pages/profile.php',
    'config'          => 'pages/config.php',
    'settings'        => 'pages/settings.php',
    'playlist-detail' => 'pages/playlist-detail.php',
];