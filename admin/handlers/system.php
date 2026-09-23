<?php

// $action_key 由路由层传入，值为实际的action名

if ($action_key === 'debug-toggle') {
    $debug = (int)($_POST['debug'] ?? 0);
    $db->query("DELETE FROM mapi_super_settings WHERE setting_key='debug_mode'");
    $db->query("INSERT INTO mapi_super_settings (setting_key, setting_value) VALUES ('debug_mode', '" . ($debug ? '1' : '0') . "')");
    echo 'ok';
    exit;
}

// 清空调用记录（仅超管和管理员）
if ($action_key === 'clear-logs') {
    csrf_require();
    $db->query("DELETE FROM mapi_logs");
    flash_set('msg', '调用记录已清空');
    header('Location: ?action=dashboard'); exit;
}