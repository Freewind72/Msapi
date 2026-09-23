<?php

// $action_key 由路由层传入，值为实际的action名

if ($action_key === 'song-add') {
    header('Content-Type: application/json; charset=utf-8');
    csrf_require();
    $kid = (int)($_POST['key_id'] ?? 0);
    $plId = (int)($_POST['playlist_id'] ?? 0);
    $songId = trim($_POST['song_id'] ?? '');
    $name = trim($_POST['name'] ?? '');
    $artist = trim($_POST['artist'] ?? '');
    $server = in_array($_POST['server'] ?? '', ['tencent', 'netease']) ? $_POST['server'] : 'netease';
    if (!$plId || !$songId || !$name) { echo json_encode(['ok' => false, 'msg' => '参数不完整']); exit; }
    $ownerCheck = ($_SESSION['admin_is_admin'] ?? 99) <= 1
        ? $db->query("SELECT id, key_id FROM mapi_playlists WHERE id=$plId")
        : $db->query("SELECT id, key_id FROM mapi_playlists WHERE id=$plId AND key_id IN (SELECT id FROM mapi_keys WHERE user_id=" . (int)$_SESSION['admin_id'] . ")");
    if (!$ownerCheck || !($plRow = $ownerCheck->fetch_assoc())) { echo json_encode(['ok' => false, 'msg' => '无权限']); exit; }
    $plKeyId = (int)$plRow['key_id'];
    $dup = $db->query("SELECT id FROM mapi_songs WHERE playlist_id=$plId AND song_id='" . $db->real_escape_string($songId) . "' AND server='" . $db->real_escape_string($server) . "'");
    if ($dup && $dup->fetch_assoc()) { echo json_encode(['ok' => false, 'msg' => '歌曲已存在']); exit; }
    $maxOrder = $db->query("SELECT IFNULL(MAX(sort_order),0) FROM mapi_songs WHERE playlist_id=$plId");
    $nextOrder = $maxOrder ? (int)$maxOrder->fetch_row()[0] + 1 : 1;
    $stmt = $db->prepare("INSERT INTO mapi_songs (key_id, playlist_id, song_id, name, artist, server, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param('iissssi', $plKeyId, $plId, $songId, $name, $artist, $server, $nextOrder);
    $ok = $stmt->execute();
    if ($ok) {
        $apiBase = $cfg['api']['base_url'] ?? '';
        $rServer = $cfg['api']['param_server'] ?? 'server';
        $rType = $cfg['api']['param_type'] ?? 'type';
        $pId = $cfg['api']['param_id'] ?? 'id';
        if ($apiBase) {
            $songPicUrl = $apiBase . '?' . http_build_query([$rServer => $server, $rType => 'pic', $pId => $songId]);
            $cUser = cover_resolve_user($db, $plId);
            cover_fetch_and_cache($songPicUrl, $_songCoverFile, $cUser, $server . '_' . $songId);
        }
        if ($nextOrder === 1) {
            $coverCheck = $db->query("SELECT cover_mode, cover_url FROM mapi_playlists WHERE id=$plId");
            if ($coverCheck) {
                $coverRow = $coverCheck->fetch_assoc();
                if ($coverRow && in_array($coverRow['cover_mode'], ['auto', 'first_song']) && empty($coverRow['cover_url'])) {
                    $fPic = $cfg['api']['field_pic'] ?? 'pic';
                    $rId = $cfg['api']['param_id'] ?? 'id';
                    if ($apiBase) {
                        $fetchUrl = $apiBase . '?' . http_build_query([$rServer => $server, $rType => 'song', $rId => $songId]);
                        $raw = @file_get_contents($fetchUrl);
                        if ($raw) {
                            $songData = json_decode($raw, true);
                            $songData = is_array($songData) ? ($songData[0] ?? $songData) : null;
                            if ($songData && !empty($songData[$fPic])) {
                                $coverUrl = resolve_cover_url($songData[$fPic], $apiBase, $server, $pId, $rServer, $rType);
                                if ($coverUrl) {
                                    $picEsc = $db->real_escape_string($coverUrl);
                                    $db->query("UPDATE mapi_playlists SET cover_url='$picEsc' WHERE id=$plId");
                                    cover_fetch_and_cache($coverUrl, $_plCoverFile, $cUser ?? cover_resolve_user($db, $plId), (string)$plId);
                                }
                            }
                        }
                    }
                }
            }
        }
    }
    echo json_encode(['ok' => $ok, 'msg' => $ok ? '已添加' : '添加失败']);
    exit;
}

if ($action_key === 'song-remove') {
    header('Content-Type: application/json; charset=utf-8');
    csrf_require();
    $sid = (int)($_POST['song_row_id'] ?? 0);
    if (!$sid) { echo json_encode(['ok' => false, 'msg' => '参数不完整']); exit; }
    if (($_SESSION['admin_is_admin'] ?? 99) <= 1) {
        $songInfo = $db->query("SELECT song_id, server FROM mapi_songs WHERE id=$sid");
        $stmt = $db->prepare("DELETE FROM mapi_songs WHERE id=?");
        $stmt->bind_param('i', $sid);
    } else {
        $songInfo = $db->query("SELECT song_id, server FROM mapi_songs WHERE id=$sid AND playlist_id IN (SELECT id FROM mapi_playlists WHERE key_id IN (SELECT id FROM mapi_keys WHERE user_id=" . (int)$_SESSION['admin_id'] . "))");
        $stmt = $db->prepare("DELETE FROM mapi_songs WHERE id=? AND playlist_id IN (SELECT id FROM mapi_playlists WHERE key_id IN (SELECT id FROM mapi_keys WHERE user_id=?))");
        $stmt->bind_param('ii', $sid, $_SESSION['admin_id']);
    }
    $ok = $stmt->execute();
    if ($ok && $songInfo && ($si = $songInfo->fetch_assoc())) {
        $cacheKey = ($si['server'] ?? 'netease') . '_' . ($si['song_id'] ?? '');
        $plOwner = $db->query("SELECT u.username FROM mapi_songs s LEFT JOIN mapi_playlists p ON s.playlist_id=p.id LEFT JOIN mapi_keys k ON p.key_id=k.id LEFT JOIN mapi_users u ON k.user_id=u.id WHERE s.id=$sid");
        $cUser = ($plOwner && $po = $plOwner->fetch_assoc()) ? ($po['username'] ?? 'unknown') : 'unknown';
        if ($cacheKey) cover_cache_unset($_songCoverFile, $cUser, $cacheKey);
    }
    echo json_encode(['ok' => $ok, 'msg' => $ok ? '已删除' : '删除失败']);
    exit;
}

