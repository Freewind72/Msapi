<?php

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