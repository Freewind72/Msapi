<?php

// $action_key 由路由层传入，值为实际的action名

if ($action_key === 'bg-presign') {
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
if ($action_key === 'bg-confirm') {
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
if ($action_key === 'bg-url-save') {
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
