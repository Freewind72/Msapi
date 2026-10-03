<?php

// $action_key 由路由层传入，值为实际的action名

if ($action_key === 'config') {
    csrf_require();
    $autoTheme = isset($_POST['auto_theme']) ? (int)$_POST['auto_theme'] : 0;
    $themeMode = isset($_POST['theme_mode']) && in_array($_POST['theme_mode'], ['light','dark']) ? $_POST['theme_mode'] : 'light';
    $lyricsDefault = isset($_POST['lyrics_default']) ? (int)$_POST['lyrics_default'] : 1;
    $autoplayDefault = isset($_POST['autoplay_default']) ? (int)$_POST['autoplay_default'] : 0;
    // 播放器皮肤：服务端按清单白名单校验（拒绝伪造值），隐藏的旧皮肤不可被选中
    require_once dirname(__DIR__, 2) . '/modules/registry.php';
    $skins = msapi_skins();
    $playerSkin = (string)($_POST['player_skin'] ?? '');
    if (!isset($skins[$playerSkin]) || !empty($skins[$playerSkin]['hidden'])) {
        $playerSkin = msapi_default_skin();
    }

    // 初始位置：从「全局一份」改为「每个皮肤各一份」（pos_side_<皮肤> / pos_y_<皮肤>）。
    // 只认清单里存在的皮肤，左右枚举 + 0~100 范围都在服务端校验。
    $oldCfgRaw = '';
    try {
        $cr = $db->query('SELECT player_skin_cfg FROM mapi_users WHERE id=' . (int)$_SESSION['admin_id']);
        if ($cr && $crow = $cr->fetch_assoc()) $oldCfgRaw = (string)($crow['player_skin_cfg'] ?? '');
    } catch (Throwable $e) { /* 列不存在：当作空配置 */ }
    $skinCfg = [];
    $decodedCfg = json_decode($oldCfgRaw, true);
    if (is_array($decodedCfg)) $skinCfg = $decodedCfg;

    foreach ($skins as $skName => $skDef) {
        if (!empty($skDef['hidden'])) continue;
        $side = (isset($_POST['pos_side_' . $skName]) && $_POST['pos_side_' . $skName] === 'left') ? 'left' : 'right';
        $y = isset($_POST['pos_y_' . $skName]) ? (int)$_POST['pos_y_' . $skName] : 88;
        if ($y < 0) $y = 0;
        if ($y > 100) $y = 100;
        $skinCfg[$skName] = ['pos' => $side . ':' . $y];
    }
    $playerSkinCfg = json_encode($skinCfg, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    // 老列 player_pos 继续维护成「当前皮肤的初始位置」：老客户端与未迁移的数据都还能用
    $playerPos = isset($skinCfg[$playerSkin]['pos']) ? $skinCfg[$playerSkin]['pos'] : 'right:88';
    // 老库同样可能没有 player_skin 列：存在性检查 + 缺列自动补（幂等）
    $hasSkinCol = true;
    try { $r = $db->query('SELECT player_skin FROM mapi_users LIMIT 1'); if ($r === false) $hasSkinCol = false; }
    catch (Throwable $e) { $hasSkinCol = false; }
    if (!$hasSkinCol) {
        $db->query("ALTER TABLE mapi_users ADD COLUMN player_skin VARCHAR(32) DEFAULT ''");
    }
    // 皮肤级配置列（JSON）同样缺列自动补
    $hasCfgCol = true;
    try { $r = $db->query('SELECT player_skin_cfg FROM mapi_users LIMIT 1'); if ($r === false) $hasCfgCol = false; }
    catch (Throwable $e) { $hasCfgCol = false; }
    if (!$hasCfgCol) {
        $db->query("ALTER TABLE mapi_users ADD COLUMN player_skin_cfg VARCHAR(1000) DEFAULT ''");
    }
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
    $oldSkin = '';
    $sr = $db->query("SELECT player_skin FROM mapi_users WHERE id=" . (int)$_SESSION['admin_id']);
    if ($sr && $srow = $sr->fetch_assoc()) $oldSkin = trim((string)($srow['player_skin'] ?? ''));
    $stmt = $db->prepare("UPDATE mapi_users SET auto_theme=?, theme_mode=?, lyrics_default=?, autoplay_default=?, player_pos=?, player_skin=?, player_skin_cfg=? WHERE id=?");
    $stmt->bind_param('isiisssi', $autoTheme, $themeMode, $lyricsDefault, $autoplayDefault, $playerPos, $playerSkin, $playerSkinCfg, $_SESSION['admin_id']);
    if ($stmt->execute()) {
        $_SESSION['admin_auto_theme'] = $autoTheme;
        $_SESSION['admin_theme_mode'] = $themeMode;
        $_SESSION['admin_lyrics_default'] = $lyricsDefault;
        $_SESSION['admin_autoplay_default'] = $autoplayDefault;
        $_SESSION['admin_player_pos'] = $playerPos;
        $_SESSION['admin_player_skin'] = $playerSkin;
        $_SESSION['admin_player_skin_cfg'] = $playerSkinCfg;
        // 位置或皮肤改了：清掉本机的位置记忆，这样后台自己也能立刻看到新形态
        if ($oldPos !== $playerPos || $oldSkin !== $playerSkin) {
            setcookie('mapi_pos', '', time() - 3600, '/');
        }
        flash_set('msg','配置已保存');
    } else { flash_set('err','保存失败'); }
    header('Location: ?action=config'); exit;
}

// ═══ 通行密钥注册/管理 ═══
