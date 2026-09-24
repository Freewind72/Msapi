<?php defined('MAPI_ADMIN') or die('禁止直接访问');
$csrf = csrf_token();
$plId = (int)($_GET['id'] ?? 0);
if (!$plId) { echo '<div class="empty">参数错误</div>'; return; }

$pl = null;
$r = $db->query("SELECT p.*, k.api_key, k.user_id as key_user_id, u.username FROM mapi_playlists p LEFT JOIN mapi_keys k ON p.key_id=k.id LEFT JOIN mapi_users u ON k.user_id=u.id WHERE p.id=$plId");
if ($r) $pl = $r->fetch_assoc();
if (!$pl) { echo '<div class="empty">歌单不存在</div>'; return; }

$isOwner = ($_SESSION['admin_is_admin'] ?? 99) <= 1 || (int)$pl['key_user_id'] === (int)$_SESSION['admin_id'];
if (!$isOwner) { echo '<div class="empty">无权限</div>'; return; }

$songs = [];
$r = $db->query("SELECT id, song_id, name, artist, server, sort_order FROM mapi_songs WHERE playlist_id=$plId ORDER BY sort_order ASC, id ASC");
if ($r) while ($row = $r->fetch_assoc()) $songs[] = $row;

$isRemote = $pl['type'] === 'remote';
$coverSrc = $pl['cover_url'] ?: '';
$_plCoverCache = cover_cache_read($_plCoverFile);
$_songCoverCache = cover_cache_read($_songCoverFile);
$_currentUsername = cover_resolve_user($db, $plId);
$_userPlCache = $_plCoverCache[$_currentUsername] ?? [];
$_userSongCache = $_songCoverCache[$_currentUsername] ?? [];
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

$searchToken = '';
$firstKey = $pl['api_key'] ?? '';
if ($firstKey) {
    $jwtSecret = $cfg['api']['jwt_secret'] ?? hash('sha256', ($cfg['db']['password'] ?? '') . ($cfg['site']['url'] ?? ''));
    $searchToken = jwt_encode(['key' => $firstKey, 'exp' => time() + 300, 'iat' => time()], $jwtSecret);
}

$apiBase = $cfg['api']['base_url'] ?? '';
$pId = $cfg['api']['param_id'] ?? 'id';
$rServer = $cfg['api']['param_server'] ?? 'server';
$rType = $cfg['api']['param_type'] ?? 'type';
?>
<div id="playlist-detail-data" data-page='<?= htmlspecialchars(json_encode([
    'csrf' => $csrf,
    'playlistId' => $plId,
    'searchToken' => $searchToken,
    'apiBase' => $apiBase,
    'pId' => $pId,
    'pServer' => $pl['server'] ?? 'netease',
    'pType' => $pl['type'] ?? '',
    'plName' => $pl['name'],
    'plCoverMode' => $pl['cover_mode'] ?? 'auto',
]), ENT_QUOTES) ?>' style="display:none"></div>
<div class="card">
  <div class="card-header">
    <a href="?action=config" class="btn-sm" style="margin-right:8px">&larr;</a>
    <span class="card-title"><?= htmlspecialchars($pl['name']) ?></span>
    <div style="margin-left:auto;display:flex;gap:6px;align-items:center">
      <span class="key-badge"><?= $isRemote ? '远程' : '自建' ?></span>
      <span class="key-badge"><?= $pl['server'] === 'netease' ? '网易' : 'QQ' ?></span>
      <span class="key-badge"><?= count($songs) ?> 首</span>
    </div>
  </div>

  <div class="key-card-info" style="margin-bottom:12px">
    <span class="key-card-user"><?= htmlspecialchars($pl['username'] ?? '-') ?></span>
    <code class="key-card-code"><?= htmlspecialchars($pl['api_key'] ?? '') ?></code>
    <?php if (!$isRemote): ?>
    <button type="button" class="btn-sm" id="openEditPlBtn" style="margin-left:auto;font-size:10px;padding:3px 10px">编辑</button>
    <?php endif; ?>
  </div>

  <?php if ($isRemote): ?>
  <div style="padding:8px 0;font-size:12px;color:rgba(0,0,0,.4)">
    远程歌单 ID：<strong><?= htmlspecialchars($pl['remote_id']) ?></strong>
  </div>
  <?php else: ?>

  <div class="key-section-label" style="margin-bottom:8px">
    <span>歌曲列表</span>
    <span class="key-section-hint">仅网易</span>
  </div>

  <div class="song-card-grid">
    <?php if (empty($songs)): ?>
    <div class="custom-song-empty">暂无歌曲，点击下方搜索添加</div>
    <?php else: foreach ($songs as $song):
      $songCacheKey = $song['server'] . '_' . $song['song_id'];
      $songPicB64 = $_userSongCache[$songCacheKey] ?? '';
      $songPicUrl = '';
      if ($songPicB64) {
          $songPicUrl = $songPicB64;
      } elseif ($apiBase && $song['song_id']) {
          $songPicUrl = $apiBase . '?' . http_build_query([$rServer => $song['server'], $rType => 'pic', $pId => $song['song_id']]);
      }
    ?>
    <div class="song-card" data-row-id="<?= (int)$song['id'] ?>">
      <div class="song-card-cover">
        <?php if ($songPicUrl): ?>
        <img src="<?= htmlspecialchars($songPicUrl) ?>" alt="" loading="lazy" onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
        <div class="song-card-placeholder" style="display:none"><?= svg('play') ?></div>
        <?php else: ?>
        <div class="song-card-placeholder"><?= svg('play') ?></div>
        <?php endif; ?>
      </div>
      <div class="song-card-body">
        <span class="song-card-name" title="<?= htmlspecialchars($song['name']) ?>"><?= htmlspecialchars($song['name']) ?></span>
        <span class="song-card-artist" title="<?= htmlspecialchars($song['artist']) ?>"><?= htmlspecialchars($song['artist']) ?></span>
      </div>
      <button type="button" class="song-card-remove" data-row-id="<?= (int)$song['id'] ?>" title="删除">✕</button>
    </div>
    <?php endforeach; endif; ?>
  </div>

  <div class="key-card-actions" style="margin-top:12px">
    <button type="button" class="btn-sm" id="openSearchBtn">搜索添加</button>
    <button type="button" class="btn-sm" id="openManualBtn">手动添加</button>
  </div>
  <?php endif; ?>
</div>

<div id="songModal" class="song-modal" style="display:none">
  <div class="song-modal-backdrop"></div>
  <div class="song-modal-panel">
    <div class="song-modal-header">
      <span class="song-modal-title">搜索歌曲 · 网易云</span>
      <button type="button" class="song-modal-close">✕</button>
    </div>
    <div class="song-modal-search">
      <input type="text" id="songSearchInput" class="form-input" placeholder="输入歌曲名或歌手…" style="flex:1">
      <button type="button" id="songSearchBtn" class="btn-sm" style="flex-shrink:0">搜索</button>
    </div>
    <div id="songSearchResults" class="song-modal-results">
      <div class="song-modal-hint">输入关键词搜索网易云音乐</div>
    </div>
  </div>
</div>

<div id="manualModal" class="song-modal" style="display:none">
  <div class="song-modal-backdrop"></div>
  <div class="song-modal-panel" style="max-width:380px">
    <div class="song-modal-header">
      <span class="song-modal-title">手动添加歌曲</span>
      <button type="button" class="song-modal-close">✕</button>
    </div>
    <div style="padding:16px 20px">
      <label class="create-field-label">歌曲 ID</label>
      <input type="text" id="manualSongId" class="form-input" placeholder="如 473403185" style="margin-bottom:12px">
      <label class="create-field-label">歌曲名称</label>
      <input type="text" id="manualSongName" class="form-input" placeholder="如 晴天" style="margin-bottom:12px">
      <label class="create-field-label">歌手</label>
      <input type="text" id="manualSongArtist" class="form-input" placeholder="如 周杰伦" style="margin-bottom:12px">
      <label class="create-field-label">平台</label>
      <select id="manualSongServer" class="form-input" style="margin-bottom:16px">
        <option value="netease">网易云音乐</option>
        <option value="tencent">QQ音乐</option>
      </select>
      <button type="button" id="manualSongSubmit" class="btn btn-primary btn-block">添加</button>
    </div>
  </div>
</div>

<?php if (!$isRemote): ?>
<div id="editPlModal" class="song-modal" style="display:none">
  <div class="song-modal-backdrop"></div>
  <div class="song-modal-panel" style="max-width:400px">
    <div class="song-modal-header">
      <span class="song-modal-title">编辑歌单</span>
      <button type="button" class="song-modal-close">✕</button>
    </div>
    <div style="padding:16px 20px">
      <label class="create-field-label">歌单名称</label>
      <input type="text" id="editPlName" class="form-input" style="margin-bottom:12px">
      <label class="create-field-label">封面</label>
      <div class="create-cover-row">
        <label class="create-cover-opt">
          <input type="radio" name="edit_pl_cover" value="auto" checked>
          <span>自动</span>
        </label>
        <label class="create-cover-opt">
          <input type="radio" name="edit_pl_cover" value="url">
          <span>URL</span>
        </label>
      </div>
      <input type="text" id="editPlCoverUrl" class="form-input" placeholder="图片 URL（选 URL 时填写）" style="margin-bottom:16px;display:none">
      <button type="button" id="editPlSubmit" class="btn btn-primary btn-block">保存</button>
    </div>
  </div>
</div>
<?php endif; ?>