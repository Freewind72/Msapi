<?php defined('MAPI_ADMIN') or die('禁止直接访问');

// 拼上游请求地址
function upstream_url(array $cfg, string $type, string $id, string $server): string {
    $api = $cfg['api'] ?? [];
    $base = $api['base_url'] ?? '';
    if (!$base) return '';
    return $base . '?' . http_build_query([
        $api['param_server'] ?? 'server' => $server,
        $api['param_type']   ?? 'type'   => $type,
        $api['param_id']     ?? 'id'     => $id,
    ]);
}

// 请求上游并解析 JSON
function upstream_get_json(array $cfg, string $type, string $id, string $server, int $timeout = 12) {
    $url = upstream_url($cfg, $type, $id, $server);
    if (!$url) return null;
    $ctx = stream_context_create([
        'http' => ['timeout' => $timeout],
        'ssl'  => ['verify_peer' => false, 'verify_peer_name' => false],
    ]);
    $raw = @file_get_contents($url, false, $ctx);
    if (!$raw) return null;
    $data = json_decode((string)$raw, true);
    return is_array($data) ? $data : null;
}

// 取上游歌曲的稳定 id
function upstream_song_id(array $cfg, array $item): string {
    $api = $cfg['api'] ?? [];
    foreach (['songmid', 'mid', 'song_id', $api['param_id'] ?? 'id'] as $k) {
        if (!empty($item[$k]) && is_scalar($item[$k])) return (string)$item[$k];
    }
    $pid = preg_quote((string)($api['param_id'] ?? 'id'), '/');
    foreach ([$api['field_url'] ?? 'url', $api['field_lrc'] ?? 'lrc'] as $k) {
        if (!empty($item[$k]) && preg_match('/[?&]' . $pid . '=([^&]+)/', (string)$item[$k], $m)) {
            return urldecode($m[1]);
        }
    }
    return '';
}

// 拉取远程歌单的歌曲清单
function upstream_fetch_playlist_songs(array $cfg, string $server, string $remoteId, int $limit = 500): array {
    $data = upstream_get_json($cfg, 'playlist', $remoteId, $server);
    if (!$data) return [];

    $api = $cfg['api'] ?? [];
    $ft  = $api['field_title']  ?? 'title';
    $fa  = $api['field_artist'] ?? 'author';
    $out = [];

    foreach ($data as $item) {
        if (!is_array($item)) continue;
        $id = upstream_song_id($cfg, $item);
        if ($id === '') continue;
        $name   = (string)($item[$ft] ?? $item['title'] ?? $item['name'] ?? '');
        $artist = (string)($item[$fa] ?? $item['author'] ?? $item['artist'] ?? $item['singer'] ?? '');
        $out[] = ['song_id' => $id, 'name' => $name !== '' ? $name : '未知', 'artist' => $artist];
        if (count($out) >= $limit) break;
    }
    return $out;
}