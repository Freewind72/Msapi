<?php defined('MAPI_ADMIN') or die('禁止直接访问');
$csrf = csrf_token();

// 密钥列表（含用户信息）
$keys = [];
if (($_SESSION['admin_is_admin'] ?? 99) === 0) {
    $r = $db->query("SELECT k.*, u.username FROM mapi_keys k LEFT JOIN mapi_users u ON k.user_id=u.id ORDER BY k.id DESC");
} else {
    $r = $db->query("SELECT k.*, u.username FROM mapi_keys k LEFT JOIN mapi_users u ON k.user_id=u.id WHERE k.user_id=" . (int)$_SESSION['admin_id'] . " ORDER BY k.id DESC");
}
if ($r) while ($row = $r->fetch_assoc()) $keys[] = $row;

// 歌单分组数据
$playlistsByKey = [];
$r = $db->query("SELECT id, key_id, name, type, remote_id, server, cover_url, cover_mode, sort_order FROM mapi_playlists ORDER BY sort_order ASC, id ASC");
if ($r) while ($row = $r->fetch_assoc()) {
    $kid = (int)$row['key_id'];
    if (!isset($playlistsByKey[$kid])) $playlistsByKey[$kid] = [];
    $playlistsByKey[$kid][] = $row;
}

// 歌曲分组数据
$songsByPlaylist = [];
$r = $db->query("SELECT id, playlist_id, song_id, name, artist, server, sort_order FROM mapi_songs ORDER BY sort_order ASC, id ASC");
if ($r) while ($row = $r->fetch_assoc()) {
    $pid = (int)$row['playlist_id'];
    if (!isset($songsByPlaylist[$pid])) $songsByPlaylist[$pid] = [];
    $songsByPlaylist[$pid][] = $row;
}

$playlistsByKid = $playlistsByKey ?? [];
$songsByPl = $songsByPlaylist ?? [];
$searchToken = '';
if (!empty($keys)) {
    $firstKey = $keys[0]['api_key'] ?? '';
    if ($firstKey) {
        $jwtSecret = $cfg['api']['jwt_secret'] ?? hash('sha256', ($cfg['db']['password'] ?? '') . ($cfg['site']['url'] ?? ''));
        $searchToken = jwt_encode(['key' => $firstKey, 'exp' => time() + 300, 'iat' => time()], $jwtSecret);
    }
}
?>
<div class="card">
  <div class="card-header"><span class="card-title"><?= svg('config') ?> 音乐配置</span></div>
  <form method="post" action="?action=config"><input type="hidden" name="_csrf" value="<?= $csrf ?>">
    <label class="form-check">
      <input type="hidden" name="auto_theme" value="0">
      <input type="checkbox" name="auto_theme" value="1"<?= $autoTheme ? ' checked' : '' ?>>
      跟随时间自动切换深色主题
    </label>
    <div style="padding:6px 0 12px;font-size:13px;color:rgba(0,0,0,.5)">
      <div style="margin-bottom:8px;font-weight:600">主题模式（自动关闭时生效）</div>
      <label class="form-radio">
        <input type="radio" name="theme_mode" value="light"<?= $themeMode === 'light' ? ' checked' : '' ?>>
        浅色
      </label>
      <label class="form-radio">
        <input type="radio" name="theme_mode" value="dark"<?= $themeMode === 'dark' ? ' checked' : '' ?>>
        深色
      </label>
    </div>
    <label class="form-check">
      <input type="hidden" name="lyrics_default" value="0">
      <input type="checkbox" name="lyrics_default" value="1"<?= $lyricsDefault ? ' checked' : '' ?>>
      默认开启歌词
    </label>
    <label class="form-check">
      <input type="hidden" name="autoplay_default" value="0">
      <input type="checkbox" name="autoplay_default" value="1"<?= $autoplayDefault ? ' checked' : '' ?>>
      自动播放
    </label>
    <button class="btn btn-primary btn-block" style="margin-top:8px">保存</button>
  </form>
</div>

<div class="card">
  <div class="card-header"><span class="card-title">密钥歌单</span></div>
  <?php if (empty($keys)): ?><div class="empty">暂无密钥</div>
  <?php else: foreach ($keys as $k):
    $kid = (int)$k['id'];
    $myPlaylists = $playlistsByKid[$kid] ?? [];
  ?>
  <div class="key-card" data-api-key="<?= htmlspecialchars($k['api_key']) ?>">
    <div class="key-card-header">
      <div class="key-card-info">
        <span class="key-card-user"><?= htmlspecialchars($k['username'] ?? '-') ?></span>
        <code class="key-card-code"><?= htmlspecialchars(mask_key($k['api_key'])) ?></code>
        <button class="btn-copy" onclick="var k=this.closest('.key-card').dataset.apiKey;navigator.clipboard.writeText(k);var org=this.innerHTML;this.innerHTML='<svg viewBox=&quot;0 0 24 24&quot; fill=&quot;none&quot; stroke=&quot;currentColor&quot; stroke-width=&quot;2&quot;><polyline points=&quot;20 6 9 17 4 12&quot;/></svg>';setTimeout(function(){this.innerHTML=org},1500)"><?= svg('copy') ?></button>
      </div>
      <div class="key-card-badges">
        <span class="key-badge"><?= count($myPlaylists) ?> 歌单</span>
      </div>
    </div>

    <div class="playlist-grid">
      <?php
      $_plCoverCache = cover_cache_read($_plCoverFile);
      $_currentUsername = $_SESSION['admin_user'] ?? 'unknown';
      $_userPlCache = $_plCoverCache[$_currentUsername] ?? [];
      foreach ($myPlaylists as $pl):
        $plId = (int)$pl['id'];
        $plSongs = $songsByPl[$plId] ?? [];
        $songCount = count($plSongs);
        $coverSrc = $pl['cover_url'] ?: '';
        $coverB64 = $_userPlCache[(string)$plId] ?? '';
        if ($coverB64) {
            $coverSrc = $coverB64;
        } elseif ($coverSrc && !preg_match('/^https?:\/\//', $coverSrc)) {
            $ab = $cfg['api']['base_url'] ?? '';
            $rs = $cfg['api']['param_server'] ?? 'server';
            $rt = $cfg['api']['param_type'] ?? 'type';
            $ri = $cfg['api']['param_id'] ?? 'id';
            if ($ab && preg_match('/[?&]' . preg_quote($ri, '/') . '=([^&]+)/', $coverSrc, $cm)) {
                $sv = $pl['server'] ?: 'netease';
                if (preg_match('/server=([^&]+)/', $coverSrc, $sm)) $sv = $sm[1];
                $coverSrc = $ab . '?' . http_build_query([$rs => $sv, $rt => 'pic', $ri => $cm[1]]);
            }
        }
        $isRemote = $pl['type'] === 'remote';
      ?>
      <?php if ($isRemote): ?>
      <div class="playlist-card playlist-card-remote" data-pl-id="<?= $plId ?>" data-pl-name="<?= htmlspecialchars($pl['name']) ?>" data-remote-id="<?= htmlspecialchars($pl['remote_id']) ?>" data-server="<?= htmlspecialchars($pl['server']) ?>" data-cover-mode="<?= htmlspecialchars($pl['cover_mode']) ?>">
      <?php else: ?>
      <a href="?action=playlist-detail&id=<?= $plId ?>" class="playlist-card">
      <?php endif; ?>
        <div class="playlist-card-cover">
          <?php if ($coverSrc): ?>
          <img src="<?= htmlspecialchars($coverSrc) ?>" alt="" loading="lazy">
          <?php else: ?>
          <div class="playlist-card-placeholder playlist-card-initial" data-need-cover="<?= $plId ?>"><?= mb_strtoupper(mb_substr($pl['name'], 0, 1, 'UTF-8')) ?></div>
          <?php endif; ?>
        </div>
        <div class="playlist-card-body">
          <span class="playlist-card-name"><?= htmlspecialchars($pl['name']) ?></span>
          <span class="playlist-card-meta"><?= $isRemote ? ($pl['server'] === 'netease' ? '网易' : 'QQ') . ' 远程' : $songCount . ' 首' ?></span>
        </div>
        <button type="button" class="playlist-card-delete danger" data-pl-id="<?= $plId ?>" title="删除">✕</button>
      <?php if ($isRemote): ?>
      </div>
      <?php else: ?>
      </a>
      <?php endif; ?>
      <?php endforeach; ?>
      <button type="button" class="playlist-card playlist-card-add" data-key-id="<?= $kid ?>">
        <div class="playlist-card-cover">
          <div class="playlist-card-placeholder"><?= svg('plus') ?></div>
        </div>
        <div class="playlist-card-body">
          <span class="playlist-card-name">添加歌单</span>
          <span class="playlist-card-meta">自建或远程</span>
        </div>
      </button>
    </div>
  </div>
  <?php endforeach; endif; ?>
</div>

<div id="createModal" class="song-modal" style="display:none">
  <div class="song-modal-backdrop"></div>
  <div class="song-modal-panel" style="max-width:400px">
    <div class="song-modal-header">
      <span class="song-modal-title">添加歌单</span>
      <button type="button" class="song-modal-close">✕</button>
    </div>
    <div style="padding:16px 20px">
      <div class="create-type-row">
        <label class="create-type-opt">
          <input type="radio" name="create_type" value="custom" checked>
          <span class="create-type-label">自建歌单</span>
          <span class="create-type-desc">搜索添加歌曲</span>
        </label>
        <label class="create-type-opt">
          <input type="radio" name="create_type" value="remote">
          <span class="create-type-label">远程歌单</span>
          <span class="create-type-desc">输入歌单 ID</span>
        </label>
      </div>

      <div id="createCustomFields">
        <label class="create-field-label">歌单名称</label>
        <input type="text" id="createName" class="form-input" placeholder="如 我喜欢的" style="margin-bottom:12px">
      </div>

      <div id="createRemoteFields" style="display:none">
        <label class="create-field-label">歌单名称</label>
        <input type="text" id="createRemoteName" class="form-input" placeholder="如 日语流行" style="margin-bottom:12px">
        <label class="create-field-label">歌单 ID</label>
        <input type="text" id="createRemoteId" class="form-input" placeholder="如 3778678" style="margin-bottom:12px">
        <label class="create-field-label">平台</label>
        <select id="createRemoteServer" class="form-input" style="margin-bottom:12px">
          <option value="netease">网易云音乐</option>
          <option value="tencent">QQ音乐</option>
        </select>
      </div>

      <label class="create-field-label">封面</label>
      <div class="create-cover-row">
        <label class="create-cover-opt">
          <input type="radio" name="create_cover" value="auto" checked>
          <span>自动</span>
        </label>
        <label class="create-cover-opt">
          <input type="radio" name="create_cover" value="first_song">
          <span>首曲封面</span>
        </label>
        <label class="create-cover-opt">
          <input type="radio" name="create_cover" value="url">
          <span>URL</span>
        </label>
      </div>
      <input type="text" id="createCoverUrl" class="form-input" placeholder="图片 URL（选 URL 时填写）" style="margin-bottom:16px;display:none">

      <button type="button" id="createSubmit" class="btn btn-primary btn-block">创建</button>
    </div>
  </div>
</div>

<div id="editRemoteModal" class="song-modal" style="display:none">
  <div class="song-modal-backdrop"></div>
  <div class="song-modal-panel" style="max-width:400px">
    <div class="song-modal-header">
      <span class="song-modal-title">编辑远程歌单</span>
      <button type="button" class="song-modal-close">✕</button>
    </div>
    <div style="padding:16px 20px">
      <input type="hidden" id="editPlId">
      <label class="create-field-label">歌单名称</label>
      <input type="text" id="editName" class="form-input" style="margin-bottom:12px">
      <label class="create-field-label">歌单 ID</label>
      <input type="text" id="editRemoteId" class="form-input" style="margin-bottom:12px">
      <label class="create-field-label">平台</label>
      <select id="editServer" class="form-input" style="margin-bottom:12px">
        <option value="netease">网易云音乐</option>
        <option value="tencent">QQ音乐</option>
      </select>
      <label class="create-field-label">封面</label>
      <div class="create-cover-row">
        <label class="create-cover-opt">
          <input type="radio" name="edit_cover" value="auto" checked>
          <span>自动</span>
        </label>
        <label class="create-cover-opt">
          <input type="radio" name="edit_cover" value="url">
          <span>URL</span>
        </label>
      </div>
      <input type="text" id="editCoverUrl" class="form-input" placeholder="图片 URL" style="margin-bottom:16px;display:none">
      <button type="button" id="editSubmit" class="btn btn-primary btn-block">保存</button>
    </div>
  </div>
</div>

<div id="config-data" data-csrf="<?= htmlspecialchars($csrf) ?>" hidden></div>