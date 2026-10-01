<?php defined('MAPI_ADMIN') or die('禁止直接访问');

// 把远程歌单歌曲写入快照
function playlist_snapshot_songs($db, array $cfg, array $pl): array {
    $ret = ['added' => 0, 'updated' => 0, 'missing' => 0, 'total' => 0];

    $plId     = (int)($pl['id'] ?? 0);
    $kid      = (int)($pl['key_id'] ?? 0);
    $server   = ($pl['server'] ?? '') ?: 'netease';
    $remoteId = trim((string)($pl['remote_id'] ?? ''));
    if (!$plId || $remoteId === '') return $ret;

    $songs = upstream_fetch_playlist_songs($cfg, $server, $remoteId);
    $ret['total'] = count($songs);
    if (!$songs) return $ret;

    $exist = [];
    $r = $db->query("SELECT id, song_id, name, artist, missing FROM mapi_songs WHERE playlist_id=$plId");
    if ($r) while ($row = $r->fetch_assoc()) $exist[(string)$row['song_id']] = $row;

    $maxOrder = 0;
    $r = $db->query("SELECT IFNULL(MAX(sort_order),0) FROM mapi_songs WHERE playlist_id=$plId");
    if ($r) $maxOrder = (int)$r->fetch_row()[0];

    $seen = [];
    foreach ($songs as $s) {
        $sid = (string)$s['song_id'];
        $seen[$sid] = true;

        if (isset($exist[$sid])) {
            $cur = $exist[$sid];
            if ((string)$cur['name'] !== $s['name'] || (string)$cur['artist'] !== $s['artist'] || (int)$cur['missing'] === 1) {
                $up = $db->prepare("UPDATE mapi_songs SET name=?, artist=?, missing=0 WHERE id=?");
                $up->bind_param('ssi', $s['name'], $s['artist'], $cur['id']);
                $up->execute();
                $ret['updated']++;
            }
            continue;
        }

        $maxOrder += 100;
        $ins = $db->prepare("INSERT INTO mapi_songs (key_id, playlist_id, song_id, name, artist, server, sort_order, missing) VALUES (?,?,?,?,?,?,?,0)");
        $ins->bind_param('iissssi', $kid, $plId, $sid, $s['name'], $s['artist'], $server, $maxOrder);
        $ins->execute();
        $ret['added']++;
    }

    foreach ($exist as $sid => $cur) {
        if (isset($seen[$sid]) || (int)$cur['missing'] === 1) continue;
        $up = $db->prepare("UPDATE mapi_songs SET missing=1 WHERE id=?");
        $up->bind_param('i', $cur['id']);
        $up->execute();
        $ret['missing']++;
    }
    return $ret;
}