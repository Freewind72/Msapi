<?php

header('Content-Type: application/json; charset=utf-8');

if ($action_key === 'playlist-create') {
    $input = json_decode(file_get_contents('php://input'), true);
    $_POST['_csrf'] = $input['_csrf'] ?? '';
    csrf_require();
    $kid = (int)($input['key_id'] ?? 0);
    $plName = trim($input['name'] ?? '');
    $plType = in_array($input['type'] ?? '', ['custom', 'remote']) ? $input['type'] : 'custom';
    $remoteId = trim($input['remote_id'] ?? '');
    $server = in_array($input['server'] ?? '', ['tencent', 'netease']) ? $input['server'] : 'netease';
    $coverUrl = trim($input['cover_url'] ?? '');
    $coverMode = in_array($input['cover_mode'] ?? '', ['auto', 'url', 'first_song']) ? $input['cover_mode'] : 'auto';
    if (!$kid || !$plName) { echo json_encode(['ok' => false, 'msg' => '参数不完整']); exit; }
    $ownerCheck = ($_SESSION['admin_is_admin'] ?? 99) <= 1
        ? $db->query("SELECT id FROM mapi_keys WHERE id=$kid")
        : $db->query("SELECT id FROM mapi_keys WHERE id=$kid AND user_id=" . (int)$_SESSION['admin_id']);
    if (!$ownerCheck || !$ownerCheck->fetch_assoc()) { echo json_encode(['ok' => false, 'msg' => '无权限']); exit; }
    $maxOrder = $db->query("SELECT IFNULL(MAX(sort_order),0) FROM mapi_playlists WHERE key_id=$kid");
    $nextOrder = $maxOrder ? (int)$maxOrder->fetch_row()[0] + 1 : 1;
    $stmt = $db->prepare("INSERT INTO mapi_playlists (key_id, name, type, remote_id, server, cover_url, cover_mode, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param('issssssi', $kid, $plName, $plType, $remoteId, $server, $coverUrl, $coverMode, $nextOrder);
    $ok = $stmt->execute();
    $insertId = $ok ? $stmt->insert_id : 0;
    if ($ok && $plType === 'remote' && $coverMode === 'auto' && !$coverUrl && $remoteId) {
        $apiBase = $cfg['api']['base_url'] ?? '';
        $rServer = $cfg['api']['param_server'] ?? 'server';
        $rType = $cfg['api']['param_type'] ?? 'type';
        $rId = $cfg['api']['param_id'] ?? 'id';
        $fPic = $cfg['api']['field_pic'] ?? 'pic';
        $pId = $cfg['api']['param_id'] ?? 'id';
        if ($apiBase) {
            $fetchUrl = $apiBase . '?' . http_build_query([$rServer => $server, $rType => 'playlist', $rId => $remoteId]);
            $raw = @file_get_contents($fetchUrl);
            if ($raw) {
                $plData = json_decode($raw, true);
                $firstSong = is_array($plData) ? ($plData[0] ?? null) : null;
                if ($firstSong && !empty($firstSong[$fPic])) {
                    $coverUrl = resolve_cover_url($firstSong[$fPic], $apiBase, $server, $pId, $rServer, $rType);
                    if ($coverUrl) {
                        $picEsc = $db->real_escape_string($coverUrl);
                        $db->query("UPDATE mapi_playlists SET cover_url='$picEsc' WHERE id=$insertId");
                        $cUser = cover_resolve_user($db, $insertId);
                        cover_fetch_and_cache($coverUrl, $_plCoverFile, $cUser, (string)$insertId);
                    }
                }
                $cUser = $cUser ?? cover_resolve_user($db, $insertId);
                foreach (is_array($plData) ? $plData : [] as $idx => $s) {
                    if ($idx >= 30) break;
                    if (empty($s[$fPic])) continue;
                    $sPicUrl = resolve_cover_url($s[$fPic], $apiBase, $server, $pId, $rServer, $rType);
                    if ($sPicUrl) {
                        $sId = '';
                        if (!empty($s[$cfg['api']['field_url'] ?? 'url']) && preg_match('/[?&]' . preg_quote($pId, '/') . '=([^&]+)/', $s[$cfg['api']['field_url'] ?? 'url'], $m)) $sId = $m[1];
                        if (!$sId && !empty($s[$cfg['api']['field_lrc'] ?? 'lrc']) && preg_match('/[?&]' . preg_quote($pId, '/') . '=([^&]+)/', $s[$cfg['api']['field_lrc'] ?? 'lrc'], $m)) $sId = $m[1];
                        if ($sId) cover_fetch_and_cache($sPicUrl, $_songCoverFile, $cUser, $server . '_' . $sId);
                    }
                }
            }
        }
    }
    echo json_encode(['ok' => $ok, 'msg' => $ok ? '已创建' : '创建失败', 'id' => $insertId]);
    exit;
}

if ($action_key === 'playlist-delete') {
    $input = json_decode(file_get_contents('php://input'), true);
    $_POST['_csrf'] = $input['_csrf'] ?? '';
    csrf_require();
    $pid = (int)($input['id'] ?? 0);
    if (!$pid) { echo json_encode(['ok' => false, 'msg' => '参数不完整']); exit; }
    if (($_SESSION['admin_is_admin'] ?? 99) <= 1) {
        $db->query("DELETE FROM mapi_songs WHERE playlist_id=$pid");
        $stmt = $db->prepare("DELETE FROM mapi_playlists WHERE id=?");
        $stmt->bind_param('i', $pid);
    } else {
        $db->query("DELETE FROM mapi_songs WHERE playlist_id=$pid AND playlist_id IN (SELECT id FROM mapi_playlists WHERE key_id IN (SELECT id FROM mapi_keys WHERE user_id=" . (int)$_SESSION['admin_id'] . "))");
        $stmt = $db->prepare("DELETE FROM mapi_playlists WHERE id=? AND key_id IN (SELECT id FROM mapi_keys WHERE user_id=?)");
        $stmt->bind_param('ii', $pid, $_SESSION['admin_id']);
    }
    $ok = $stmt->execute();
    if ($ok) {
        $cUser = cover_resolve_user($db, $pid);
        cover_cache_unset($_plCoverFile, $cUser, (string)$pid);
    }
    echo json_encode(['ok' => $ok, 'msg' => $ok ? '已删除' : '删除失败']);
    exit;
}

if ($action_key === 'playlist-update') {
    $input = json_decode(file_get_contents('php://input'), true);
    $_POST['_csrf'] = $input['_csrf'] ?? '';
    csrf_require();
    $pid = (int)($input['id'] ?? 0);
    $plName = trim($input['name'] ?? '');
    $remoteId = trim($input['remote_id'] ?? '');
    $server = in_array($input['server'] ?? '', ['tencent', 'netease']) ? $input['server'] : 'netease';
    $coverUrl = trim($input['cover_url'] ?? '');
    $coverMode = in_array($input['cover_mode'] ?? '', ['auto', 'url', 'first_song']) ? $input['cover_mode'] : 'auto';
    if (!$pid || !$plName) { echo json_encode(['ok' => false, 'msg' => '参数不完整']); exit; }
    if (($_SESSION['admin_is_admin'] ?? 99) <= 1) {
        $stmt = $db->prepare("UPDATE mapi_playlists SET name=?, remote_id=?, server=?, cover_url=?, cover_mode=? WHERE id=?");
        $stmt->bind_param('sssssi', $plName, $remoteId, $server, $coverUrl, $coverMode, $pid);
    } else {
        $stmt = $db->prepare("UPDATE mapi_playlists SET name=?, remote_id=?, server=?, cover_url=?, cover_mode=? WHERE id=? AND key_id IN (SELECT id FROM mapi_keys WHERE user_id=?)");
        $uid = (int)$_SESSION['admin_id'];
        $stmt->bind_param('ssssiii', $plName, $remoteId, $server, $coverUrl, $coverMode, $pid, $uid);
    }
    $ok = $stmt->execute();
    if ($ok && $coverUrl) {
        $apiBase = $cfg['api']['base_url'] ?? '';
        $resolvedUrl = $coverUrl;
        if (!preg_match('/^https?:\/\//', $coverUrl) && $apiBase) {
            $rServer2 = $cfg['api']['param_server'] ?? 'server';
            $rType2 = $cfg['api']['param_type'] ?? 'type';
            $pId2 = $cfg['api']['param_id'] ?? 'id';
            if (preg_match('/[?&]' . preg_quote($pId2, '/') . '=([^&]+)/', $coverUrl, $m)) {
                $sv = $server;
                if (preg_match('/server=([^&]+)/', $coverUrl, $sm)) $sv = $sm[1];
                $resolvedUrl = $apiBase . '?' . http_build_query([$rServer2 => $sv, $rType2 => 'pic', $pId2 => $m[1]]);
            }
        }
        cover_fetch_and_cache($resolvedUrl, $_plCoverFile, cover_resolve_user($db, $pid), (string)$pid);
    }
    echo json_encode(['ok' => $ok, 'msg' => $ok ? '已保存' : '保存失败']);
    exit;
}

if ($action_key === 'playlist-update-cover') {
    $input = json_decode(file_get_contents('php://input'), true);
    $_POST['_csrf'] = $input['_csrf'] ?? '';
    csrf_require();
    $pid = (int)($input['id'] ?? 0);
    $coverUrl = trim($input['cover_url'] ?? '');
    $coverMode = in_array($input['cover_mode'] ?? '', ['auto', 'url', 'first_song']) ? $input['cover_mode'] : 'auto';
    if (!$pid) { echo json_encode(['ok' => false, 'msg' => '参数不完整']); exit; }
    if (($_SESSION['admin_is_admin'] ?? 99) <= 1) {
        $stmt = $db->prepare("UPDATE mapi_playlists SET cover_url=?, cover_mode=? WHERE id=?");
        $stmt->bind_param('ssi', $coverUrl, $coverMode, $pid);
    } else {
        $stmt = $db->prepare("UPDATE mapi_playlists SET cover_url=?, cover_mode=? WHERE id=? AND key_id IN (SELECT id FROM mapi_keys WHERE user_id=?)");
        $stmt->bind_param('ssii', $coverUrl, $coverMode, $pid, $_SESSION['admin_id']);
    }
    $ok = $stmt->execute();
    echo json_encode(['ok' => $ok, 'msg' => $ok ? '已更新' : '更新失败']);
    exit;
}

if ($action_key === 'playlist-fetch-cover') {
    $input = json_decode(file_get_contents('php://input'), true);
    $_POST['_csrf'] = $input['_csrf'] ?? '';
    csrf_require();
    $pid = (int)($input['id'] ?? 0);
    if (!$pid) { echo json_encode(['ok' => false, 'msg' => '参数不完整']); exit; }
    $ownerCheck = ($_SESSION['admin_is_admin'] ?? 99) <= 1
        ? $db->query("SELECT id, type, remote_id, server, cover_mode, cover_url FROM mapi_playlists WHERE id=$pid")
        : $db->query("SELECT id, type, remote_id, server, cover_mode, cover_url FROM mapi_playlists WHERE id=$pid AND key_id IN (SELECT id FROM mapi_keys WHERE user_id=" . (int)$_SESSION['admin_id'] . ")");
    if (!$ownerCheck || !($pl = $ownerCheck->fetch_assoc())) { echo json_encode(['ok' => false, 'msg' => '无权限']); exit; }
    if (!empty($pl['cover_url'])) {
        $cUser = cover_resolve_user($db, $pid);
        $b64 = cover_cache_get($_plCoverFile, $cUser, (string)$pid);
        echo json_encode(['ok' => true, 'cover_url' => $pl['cover_url'], 'cover_b64' => $b64, 'msg' => '已有封面']);
        exit;
    }
    $apiBase = $cfg['api']['base_url'] ?? '';
    $rServer = $cfg['api']['param_server'] ?? 'server';
    $rType = $cfg['api']['param_type'] ?? 'type';
    $rId = $cfg['api']['param_id'] ?? 'id';
    $fPic = $cfg['api']['field_pic'] ?? 'pic';
    $pId = $cfg['api']['param_id'] ?? 'id';
    $server = $pl['server'] ?: 'netease';
    $coverUrl = '';
    if ($apiBase) {
        if ($pl['type'] === 'remote' && $pl['remote_id']) {
            $fetchUrl = $apiBase . '?' . http_build_query([$rServer => $server, $rType => 'playlist', $rId => $pl['remote_id']]);
            $raw = @file_get_contents($fetchUrl);
            if ($raw) {
                $data = json_decode($raw, true);
                $first = is_array($data) ? ($data[0] ?? null) : null;
                if ($first && !empty($first[$fPic])) {
                    $coverUrl = resolve_cover_url($first[$fPic], $apiBase, $server, $pId, $rServer, $rType);
                }
            }
        } else {
            $firstSong = $db->query("SELECT song_id, server FROM mapi_songs WHERE playlist_id=$pid ORDER BY sort_order ASC, id ASC LIMIT 1");
            if ($firstSong && ($fs = $firstSong->fetch_assoc())) {
                $fsServer = $fs['server'] ?: $server;
                $fetchUrl = $apiBase . '?' . http_build_query([$rServer => $fsServer, $rType => 'song', $rId => $fs['song_id']]);
                $raw = @file_get_contents($fetchUrl);
                if ($raw) {
                    $data = json_decode($raw, true);
                    $data = is_array($data) ? ($data[0] ?? $data) : null;
                    if ($data && !empty($data[$fPic])) {
                        $coverUrl = resolve_cover_url($data[$fPic], $apiBase, $fsServer, $pId, $rServer, $rType);
                    }
                }
            }
        }
    }
    if ($coverUrl) {
        $picEsc = $db->real_escape_string($coverUrl);
        $db->query("UPDATE mapi_playlists SET cover_url='$picEsc' WHERE id=$pid");
        $cUser = cover_resolve_user($db, $pid);
        $b64 = cover_fetch_and_cache($coverUrl, $_plCoverFile, $cUser, (string)$pid);
        echo json_encode(['ok' => true, 'cover_url' => $coverUrl, 'cover_b64' => $b64, 'msg' => '已获取封面']);
    } else {
        echo json_encode(['ok' => false, 'msg' => '未获取到封面']);
    }
    exit;
}