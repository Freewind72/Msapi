<?php

// $action_key 由路由层传入，值为实际的action名

if ($action_key === 'config') {
    csrf_require();
    $autoTheme = isset($_POST['auto_theme']) ? (int)$_POST['auto_theme'] : 0;
    $themeMode = isset($_POST['theme_mode']) && in_array($_POST['theme_mode'], ['light','dark']) ? $_POST['theme_mode'] : 'light';
    $lyricsDefault = isset($_POST['lyrics_default']) ? (int)$_POST['lyrics_default'] : 1;
    $autoplayDefault = isset($_POST['autoplay_default']) ? (int)$_POST['autoplay_default'] : 0;
    // 播放器首次加载位置：左右 + 垂直百分比（5~95）
    $posSide = (isset($_POST['player_pos_side']) && $_POST['player_pos_side'] === 'left') ? 'left' : 'right';
    $posY = isset($_POST['player_pos_y']) ? (int)$_POST['player_pos_y'] : 88;
    if ($posY < 5) $posY = 5;
    if ($posY > 95) $posY = 95;
    $playerPos = $posSide . ':' . $posY;
    // 老库可能没有 player_pos 列：存在性检查 + 缺列自动补（幂等）
    $hasPosCol = true;
    try { $db->query('SELECT player_pos FROM mapi_users LIMIT 1'); } catch (Throwable $e) { $hasPosCol = false; }
    if ($hasPosCol) {
        $r = $db->query('SELECT player_pos FROM mapi_users LIMIT 1');
        if ($r === false) $hasPosCol = false;
    }
    if (!$hasPosCol) {
        $db->query(stripos(get_class($db), 'sqlite') !== false
            ? "ALTER TABLE mapi_users ADD COLUMN player_pos VARCHAR(24) DEFAULT ''"
            : "ALTER TABLE mapi_users ADD COLUMN player_pos VARCHAR(24) DEFAULT ''");
    }
    $oldPos = '';
    $or = $db->query("SELECT player_pos FROM mapi_users WHERE id=" . (int)$_SESSION['admin_id']);
    if ($or && $orow = $or->fetch_assoc()) $oldPos = trim((string)($orow['player_pos'] ?? ''));
    $stmt = $db->prepare("UPDATE mapi_users SET auto_theme=?, theme_mode=?, lyrics_default=?, autoplay_default=?, player_pos=? WHERE id=?");
    $stmt->bind_param('isissi', $autoTheme, $themeMode, $lyricsDefault, $autoplayDefault, $playerPos, $_SESSION['admin_id']);
    if ($stmt->execute()) {
        $_SESSION['admin_auto_theme'] = $autoTheme;
        $_SESSION['admin_theme_mode'] = $themeMode;
        $_SESSION['admin_lyrics_default'] = $lyricsDefault;
        $_SESSION['admin_autoplay_default'] = $autoplayDefault;
        $_SESSION['admin_player_pos'] = $playerPos;
        // 位置改了：清掉本机的位置记忆，这样后台自己也能立刻看到新默认位置
        if ($oldPos !== $playerPos) {
            setcookie('mapi_pos', '', time() - 3600, '/');
        }
        flash_set('msg','配置已保存');
    } else { flash_set('err','保存失败'); }
    header('Location: ?action=config'); exit;
}

// ═══ 通行密钥注册/管理 ═══
