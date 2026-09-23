<?php

$_coverCacheDir  = __DIR__ . '/../assets';
$_plCoverFile    = $_coverCacheDir . '/playlist_covers.json';
$_songCoverFile  = $_coverCacheDir . '/song_covers.json';

function cover_cache_read(string $file): array
{
    if (!file_exists($file)) return [];
    $raw = @file_get_contents($file);
    if (!$raw) return [];
    $data = json_decode($raw, true);
    if (!is_array($data)) return [];
    $migrated = false;
    foreach ($data as $k => $v) {
        if (!is_array($v)) { unset($data[$k]); $migrated = true; }
    }
    if ($migrated) cover_cache_write($file, $data);
    return $data;
}

function cover_cache_write(string $file, array $data): void
{
    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    @file_put_contents($file, $json, LOCK_EX);
}

function cover_cache_set(string $file, string $user, string $key, string $b64): void
{
    $data = cover_cache_read($file);
    if (!isset($data[$user]) || !is_array($data[$user])) $data[$user] = [];
    $data[$user][$key] = $b64;
    cover_cache_write($file, $data);
}

function cover_cache_get(string $file, string $user, string $key): string
{
    $data = cover_cache_read($file);
    return $data[$user][$key] ?? '';
}

function cover_cache_get_user(string $file, string $user): array
{
    $data = cover_cache_read($file);
    return $data[$user] ?? [];
}

function cover_cache_unset(string $file, string $user, string $key): void
{
    $data = cover_cache_read($file);
    unset($data[$user][$key]);
    if (isset($data[$user]) && empty($data[$user])) unset($data[$user]);
    cover_cache_write($file, $data);
}

function fetch_image_b64(string $url, int $maxSize = 524288): string
{
    if (!$url) return '';
    $ctx = stream_context_create([
        'http' => ['timeout' => 8, 'follow_location' => true, 'max_redirects' => 3],
        'ssl'  => ['verify_peer' => false],
    ]);
    $raw = @file_get_contents($url, false, $ctx);
    if (!$raw || strlen($raw) > $maxSize || strlen($raw) < 100) return '';
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->buffer($raw);
    if (!in_array($mime, ['image/jpeg','image/png','image/gif','image/webp','image/svg+xml'], true)) {
        if (substr($raw, 0, 3) === "\xFF\xD8\xFF") $mime = 'image/jpeg';
        elseif (substr($raw, 0, 4) === "\x89PNG") $mime = 'image/png';
        elseif (substr($raw, 0, 4) === 'GIF8') $mime = 'image/gif';
        elseif (substr($raw, 0, 4) === 'RIFF') $mime = 'image/webp';
        else return '';
    }
    return 'data:' . $mime . ';base64,' . base64_encode($raw);
}

function cover_fetch_and_cache(string $url, string $cacheFile, string $user, string $cacheKey): string
{
    if (!$url) return '';
    $cached = cover_cache_get($cacheFile, $user, $cacheKey);
    if ($cached) return $cached;
    $b64 = fetch_image_b64($url);
    if ($b64) cover_cache_set($cacheFile, $user, $cacheKey, $b64);
    return $b64;
}

function cover_resolve_user($db, $playlistId): string
{
    $r = $db->query("SELECT u.username FROM mapi_playlists p LEFT JOIN mapi_keys k ON p.key_id=k.id LEFT JOIN mapi_users u ON k.user_id=u.id WHERE p.id=" . (int)$playlistId);
    if ($r && $row = $r->fetch_assoc()) return $row['username'] ?? 'unknown';
    return 'unknown';
}