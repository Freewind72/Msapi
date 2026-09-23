<?php

// $action_key 由路由层传入，值为实际的action名

if ($action_key === 'profile') {
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
    $op = $_POST['old_password'] ?? '';
    if (mb_strlen($nu) > 30) { flash_set('err','账号最多30字符'); }
    elseif (mb_strlen($np) > 60) { flash_set('err','密码最多60字符'); }
    elseif (mb_strlen($nq) > 20) { flash_set('err','QQ号最多20字符'); }
    elseif ($nu) {
        $stmt = $db->prepare("SELECT id FROM mapi_users WHERE username=? AND id!=?");
        $stmt->bind_param('si', $nu, $uid); $stmt->execute();
        if ($stmt->get_result()->fetch_assoc()) {
            flash_set('err','账号已被使用');
        } elseif ($np && !$op) {
            flash_set('err','修改密码需验证原密码');
        } elseif ($np && $op) {
            $pkr = $db->query("SELECT password FROM mapi_users WHERE id=$uid");
            $pkrw = $pkr->fetch_assoc();
            if (!$pkrw || !password_verify($op, $pkrw['password'])) {
                flash_set('err','原密码不正确');
            } else {
                $hash = password_hash($np, PASSWORD_DEFAULT);
                $stmt = $db->prepare("UPDATE mapi_users SET username=?,password=?,qq=? WHERE id=?");
                $stmt->bind_param('sssi', $nu, $hash, $nq, $uid);
                if ($stmt->execute()) {
                    $_SESSION['admin_user'] = $nu;
                    $_SESSION['admin_qq'] = $nq;
                    flash_set('msg','资料已更新');
                } else { flash_set('err','更新失败'); }
            }
        } else {
            $stmt = $db->prepare("UPDATE mapi_users SET username=?,qq=? WHERE id=?");
            $stmt->bind_param('ssi', $nu, $nq, $uid);
            if ($stmt->execute()) {
                $_SESSION['admin_user'] = $nu;
                $_SESSION['admin_qq'] = $nq;
                flash_set('msg','资料已更新');
            } else { flash_set('err','更新失败'); }
        }
    } else { flash_set('err','请填写账号'); }
    header('Location: ?action=profile'); exit;
}

// ═══ 配置管理 ═══