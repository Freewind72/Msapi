<?php

// $action_key 由路由层传入，值为实际的action名

if ($action_key === 'config') {
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
