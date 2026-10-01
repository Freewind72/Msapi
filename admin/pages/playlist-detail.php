<?php defined('MAPI_ADMIN') or die('禁止直接访问');

require_once __DIR__ . '/../service/playlist_detail.php';

// 取页面所需数据
$plData = playlist_detail_load($db, $cfg, $_SESSION, $_GET);
if (empty($plData['ok'])) {
    echo '<div class="empty">' . htmlspecialchars((string)$plData['error'], ENT_QUOTES, 'UTF-8') . '</div>';
    return;
}

$pl           = $plData['pl'];
$songs        = $plData['songs'];
$songCount    = $plData['songCount'];
$missingCount = $plData['missingCount'];
$isRemote     = $plData['isRemote'];
?>
<div id="playlist-detail-data" data-page='<?= htmlspecialchars(json_encode($plData['pageData']), ENT_QUOTES, 'UTF-8') ?>' style="display:none"></div>
<div class="card">
  <div class="card-header">
    <a href="?action=config" class="btn-sm" style="margin-right:8px">&larr;</a>
    <span class="card-title"><?= htmlspecialchars($pl['name'], ENT_QUOTES, 'UTF-8') ?></span>
    <div style="margin-left:auto;display:flex;gap:6px;align-items:center">
      <span class="key-badge"><?= $isRemote ? '远程' : '自建' ?></span>
      <span class="key-badge"><?= $pl['server'] === 'netease' ? '网易' : 'QQ' ?></span>
      <span class="key-badge"><?= $songCount ?> 首</span>
    </div>
  </div>

  <div class="key-card-info" style="margin-bottom:12px">
    <span class="key-card-user"><?= htmlspecialchars($pl['username'] ?? '-', ENT_QUOTES, 'UTF-8') ?></span>
    <code class="key-card-code"><?= htmlspecialchars($pl['api_key'] ?? '', ENT_QUOTES, 'UTF-8') ?></code>
    <?php if (!$isRemote): ?>
    <button type="button" class="btn-sm" id="openEditPlBtn" style="margin-left:auto;font-size:10px;padding:3px 10px">编辑</button>
    <?php endif; ?>
  </div>

  <?php if ($isRemote): ?>
  <div style="padding:8px 0;font-size:12px;color:rgba(0,0,0,.4)">
    远程歌单 ID：<strong><?= htmlspecialchars($pl['remote_id'], ENT_QUOTES, 'UTF-8') ?></strong>
    · 本地快照 <strong><?= $songCount ?></strong> 首<?= $missingCount ? '（' . $missingCount . ' 首上游已下架，播放器自动跳过）' : '' ?>
  </div>
  <div class="key-card-actions" style="margin:8px 0 12px">
    <button type="button" class="btn-sm" id="syncPlBtn">从上游同步</button>
    <span class="key-section-hint" style="margin-left:8px">同步只追加新歌、保留你排好的顺序</span>
  </div>
  <?php if (!$songs): ?>
  <div class="custom-song-empty">尚未同步到本地快照（点上面「从上游同步」，或直接让播放器实时拉取）</div>
  <?php endif; ?>
  <?php else: ?>

  <div class="key-section-label" style="margin-bottom:8px">
    <span>歌曲列表</span>
    <span class="key-section-hint">仅网易</span>
  </div>
  <?php endif; ?>

  <?php if (!$isRemote || $songs): ?>
  <div class="song-card-grid">
    <?php foreach ($songs as $song): ?>
    <div class="song-card<?= $song['isMissing'] ? ' is-missing' : '' ?>" data-row-id="<?= $song['id'] ?>">
      <?php if (!$isRemote): ?>
      <div class="song-card-order">
        <button type="button" class="song-ord-btn" data-row-id="<?= $song['id'] ?>" data-dir="top" title="置顶">⇤</button>
        <button type="button" class="song-ord-btn" data-row-id="<?= $song['id'] ?>" data-dir="up" title="上移">↑</button>
        <button type="button" class="song-ord-btn" data-row-id="<?= $song['id'] ?>" data-dir="down" title="下移">↓</button>
      </div>
      <?php endif; ?>
      <div class="song-card-cover">
        <?php if ($song['pic']): ?>
        <img src="<?= htmlspecialchars($song['pic'], ENT_QUOTES, 'UTF-8') ?>" alt="" loading="lazy" onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
        <div class="song-card-placeholder" style="display:none"><?= svg('play') ?></div>
        <?php else: ?>
        <div class="song-card-placeholder"><?= svg('play') ?></div>
        <?php endif; ?>
      </div>
      <div class="song-card-body">
        <span class="song-card-name" title="<?= htmlspecialchars($song['name'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($song['name'], ENT_QUOTES, 'UTF-8') ?></span>
        <span class="song-card-artist" title="<?= htmlspecialchars($song['artist'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($song['artist'], ENT_QUOTES, 'UTF-8') ?></span>
        <?php if ($song['isMissing']): ?><span class="song-card-tag">已下架</span><?php endif; ?>
      </div>
      <?php if (!$isRemote): ?>
      <button type="button" class="song-card-remove" data-row-id="<?= $song['id'] ?>" title="删除">✕</button>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <?php if (!$isRemote): ?>
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
    <div class="song-modal-body">
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
    <div class="song-modal-body">
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