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

<script>
(function(){
  var csrf = '<?= $csrf ?>';
  var playlistId = <?= $plId ?>;
  var searchToken = '<?= $searchToken ?>';
  var apiBase = '<?= addslashes($apiBase) ?>';
  var pId = '<?= addslashes($pId) ?>';
  var pServer = '<?= addslashes($cfg['api']['param_server'] ?? 'server') ?>';
  var pType = '<?= addslashes($cfg['api']['param_type'] ?? 'type') ?>';

  var _previewAudio = null;
  var _previewPlayingId = null;
  function stopPreview() {
    if (_previewAudio) { _previewAudio.pause(); _previewAudio = null; }
    _previewPlayingId = null;
    document.querySelectorAll('.song-result-pic.playing').forEach(function(el) { el.classList.remove('playing'); });
  }
  function previewSong(songId, server, imgEl) {
    if (_previewPlayingId === songId) {
      stopPreview();
      return;
    }
    stopPreview();
    if (!searchToken) { toast('未获取到 Token', false); return; }
    imgEl.classList.add('playing');
    _previewPlayingId = songId;
    var url = '/admin/api/api.php?action=url&server=' + encodeURIComponent(server || 'netease') + '&id=' + encodeURIComponent(songId) + '&token=' + encodeURIComponent(searchToken);
    var xhr = new XMLHttpRequest();
    xhr.open('GET', url, true);
    xhr.onload = function() {
      try {
        var d = JSON.parse(xhr.responseText);
        var audioUrl = d.url || '';
        if (!audioUrl) { toast('未获取到音频', false); stopPreview(); return; }
        _previewAudio = new Audio(audioUrl);
        _previewAudio.play().catch(function() { toast('播放失败', false); });
        _previewAudio.addEventListener('ended', function() { stopPreview(); });
        _previewAudio.addEventListener('error', function() { toast('音频加载失败', false); stopPreview(); });
      } catch(e) { toast('解析失败', false); stopPreview(); }
    };
    xhr.onerror = function() { toast('请求失败', false); stopPreview(); };
    xhr.send();
  }

  function post(action, data, cb) {
    var fd = new FormData();
    fd.append('_csrf', csrf);
    for (var k in data) fd.append(k, data[k]);
    var xhr = new XMLHttpRequest();
    xhr.open('POST', '?action=' + action, true);
    xhr.onload = function() {
      try { cb(JSON.parse(xhr.responseText)); } catch(e) { cb({ok:false, msg:'解析失败: ' + xhr.responseText.substring(0, 200)}); }
    };
    xhr.onerror = function() { cb({ok:false, msg:'网络错误'}); };
    xhr.send(fd);
  }

  function toast(msg, ok) {
    var t = document.getElementById('toast');
    if (!t) return;
    t.textContent = msg;
    t.style.opacity = '1';
    t.style.transform = 'translateX(-50%) translateY(0)';
    t.style.background = ok ? 'rgba(40,200,64,.12)' : 'rgba(255,95,87,.12)';
    t.style.color = ok ? '#28c840' : '#ff5f57';
    t.style.borderColor = ok ? 'rgba(40,200,64,.2)' : 'rgba(255,95,87,.2)';
    clearTimeout(t._tid);
    t._tid = setTimeout(function() { t.style.opacity = '0'; t.style.transform = 'translateX(-50%) translateY(-20px)'; }, 2000);
  }

  document.querySelectorAll('.song-card-remove').forEach(function(btn) {
    btn.addEventListener('click', function(e) {
      e.stopPropagation();
      var rowId = this.dataset.rowId;
      if (!confirm('确定删除此歌曲？')) return;
      post('song-remove', { song_row_id: rowId }, function(r) {
        toast(r.msg, r.ok);
        if (r.ok) {
          var card = btn.closest('.song-card');
          if (card) {
            card.style.opacity = '0';
            card.style.transform = 'scale(.85)';
            card.style.transition = 'all .25s';
            setTimeout(function() { if(typeof navigateTo==='function')navigateTo(location.href,false);else location.reload(); }, 300);
          }
        }
      });
    });
  });

  var songModal = document.getElementById('songModal');
  var manualModal = document.getElementById('manualModal');

  document.getElementById('openSearchBtn').addEventListener('click', function() {
    document.getElementById('songSearchInput').value = '';
    document.getElementById('songSearchResults').innerHTML = '<div class="song-modal-hint">输入关键词搜索网易云音乐</div>';
    songModal.style.display = 'flex';
    setTimeout(function() { document.getElementById('songSearchInput').focus(); }, 100);
  });

  document.getElementById('openManualBtn').addEventListener('click', function() {
    document.getElementById('manualSongId').value = '';
    document.getElementById('manualSongName').value = '';
    document.getElementById('manualSongArtist').value = '';
    manualModal.style.display = 'flex';
    setTimeout(function() { document.getElementById('manualSongId').focus(); }, 100);
  });

  songModal.querySelector('.song-modal-backdrop').addEventListener('click', function() { stopPreview(); songModal.style.display = 'none'; });
  songModal.querySelector('.song-modal-close').addEventListener('click', function() { stopPreview(); songModal.style.display = 'none'; });
  manualModal.querySelector('.song-modal-backdrop').addEventListener('click', function() { manualModal.style.display = 'none'; });
  manualModal.querySelector('.song-modal-close').addEventListener('click', function() { manualModal.style.display = 'none'; });

  var editPlModal = document.getElementById('editPlModal');
  if (editPlModal) {
    var editPlName = document.getElementById('editPlName');
    var editPlCoverUrl = document.getElementById('editPlCoverUrl');
    document.getElementById('openEditPlBtn').addEventListener('click', function() {
      editPlName.value = <?= json_encode($pl['name']) ?>;
      editPlCoverUrl.value = '';
      var cm = <?= json_encode($pl['cover_mode']) ?>;
      document.querySelector('input[name="edit_pl_cover"][value="auto"]').checked = cm !== 'url';
      document.querySelector('input[name="edit_pl_cover"][value="url"]').checked = cm === 'url';
      editPlCoverUrl.style.display = cm === 'url' ? '' : 'none';
      editPlModal.style.display = 'flex';
      setTimeout(function() { editPlName.focus(); }, 100);
    });
    editPlModal.querySelector('.song-modal-backdrop').addEventListener('click', function() { editPlModal.style.display = 'none'; });
    editPlModal.querySelector('.song-modal-close').addEventListener('click', function() { editPlModal.style.display = 'none'; });
    document.querySelectorAll('input[name="edit_pl_cover"]').forEach(function(r) {
      r.addEventListener('change', function() {
        editPlCoverUrl.style.display = document.querySelector('input[name="edit_pl_cover"][value="url"]').checked ? '' : 'none';
      });
    });
    document.getElementById('editPlSubmit').addEventListener('click', function() {
      var name = editPlName.value.trim();
      var coverMode = document.querySelector('input[name="edit_pl_cover"]:checked').value;
      var coverUrl = editPlCoverUrl.value.trim();
      if (!name) { toast('名称必填', false); return; }
      var payload = { _csrf: csrf, id: playlistId, name: name, remote_id: '', server: 'netease', cover_mode: coverMode, cover_url: coverUrl };
      var xhr = new XMLHttpRequest();
      xhr.open('POST', '?action=playlist-update', true);
      xhr.setRequestHeader('Content-Type', 'application/json');
      xhr.onload = function() {
        try { var r = JSON.parse(xhr.responseText); toast(r.msg, r.ok); if (r.ok) { editPlModal.style.display = 'none'; setTimeout(function() { if(typeof navigateTo==='function')navigateTo(location.href,false);else location.reload(); }, 500); } } catch(e) { toast('解析失败', false); }
      };
      xhr.onerror = function() { toast('网络错误', false); };
      xhr.send(JSON.stringify(payload));
    });
  }

  document.getElementById('songSearchBtn').addEventListener('click', doSearch);
  document.getElementById('songSearchInput').addEventListener('keydown', function(e) { if (e.key === 'Enter') doSearch(); });

  function animatePanelHeight(panel, cb) {
    var oldH = panel.offsetHeight;
    panel.style.transition = 'none';
    panel.style.height = oldH + 'px';
    panel.style.overflow = 'hidden';
    if (cb) cb();
    var newH = panel.scrollHeight;
    if (newH > parseFloat(getComputedStyle(panel).maxHeight)) newH = parseFloat(getComputedStyle(panel).maxHeight);
    panel.style.transition = 'height .3s cubic-bezier(.4,0,.2,1)';
    panel.offsetHeight;
    panel.style.height = newH + 'px';
    var done = function() { panel.style.height = ''; panel.style.transition = ''; panel.style.overflow = ''; panel.removeEventListener('transitionend', done); };
    panel.addEventListener('transitionend', done);
    setTimeout(done, 350);
  }

  function doSearch() {
    var kw = document.getElementById('songSearchInput').value.trim();
    if (!kw) return;
    var results = document.getElementById('songSearchResults');
    var panel = songModal.querySelector('.song-modal-panel');
    results.innerHTML = '<div class="song-modal-hint">搜索中…</div>';
    var url = '/admin/api/api.php?action=search&keyword=' + encodeURIComponent(kw) + '&server=netease&limit=20&token=' + encodeURIComponent(searchToken);
    var xhr = new XMLHttpRequest();
    xhr.open('GET', url, true);
    xhr.onload = function() {
      try {
        var list = JSON.parse(xhr.responseText);
        if (!list.length) { results.innerHTML = '<div class="song-modal-hint">未找到结果</div>'; return; }
        animatePanelHeight(panel, function() {
          results.innerHTML = '';
          list.forEach(function(s) {
            var div = document.createElement('div');
            div.className = 'song-result-item';
            var picHtml = '';
            if (s.pic) {
              picHtml = '<div class="song-result-pic-wrap"><img class="song-result-pic" src="' + esc(s.pic) + '" alt="" loading="lazy" onerror="this.style.display=\'none\'"><span class="song-result-play-icon"></span></div>';
            }
            div.innerHTML = picHtml + '<div class="song-result-info"><span class="song-result-name">' + esc(s.name) + '</span><span class="song-result-artist">' + esc(s.artist) + '</span></div><button class="btn-sm song-result-add" style="flex-shrink:0;font-size:10px;padding:3px 10px">添加</button>';
            div.querySelector('.song-result-add').addEventListener('click', function() {
              post('song-add', { playlist_id: playlistId, song_id: s.id, name: s.name, artist: s.artist, server: 'netease' }, function(r) {
                toast(r.msg, r.ok);
                if (r.ok) {
                  div.querySelector('.song-result-add').textContent = '✓';
                  div.querySelector('.song-result-add').style.pointerEvents = 'none';
                  div.querySelector('.song-result-add').style.opacity = '.5';
                  setTimeout(function() { if(typeof navigateTo==='function')navigateTo(location.href,false);else location.reload(); }, 800);
                }
              });
            });
            var picWrap = div.querySelector('.song-result-pic-wrap');
            if (picWrap) {
              picWrap.addEventListener('click', function(e) {
                e.stopPropagation();
                previewSong(String(s.id), s.server || 'netease', picWrap.querySelector('.song-result-pic'));
              });
            }
            results.appendChild(div);
          });
        });
      } catch(e) { results.innerHTML = '<div class="song-modal-hint">搜索失败</div>'; }
    };
    xhr.onerror = function() { results.innerHTML = '<div class="song-modal-hint">网络错误</div>'; };
    xhr.send();
  }

  document.getElementById('manualSongSubmit').addEventListener('click', function() {
    var sid = document.getElementById('manualSongId').value.trim();
    var sn = document.getElementById('manualSongName').value.trim();
    var sa = document.getElementById('manualSongArtist').value.trim();
    var sv = document.getElementById('manualSongServer').value;
    if (!sid || !sn) { toast('ID 和名称必填', false); return; }
    post('song-add', { playlist_id: playlistId, song_id: sid, name: sn, artist: sa, server: sv }, function(r) {
      toast(r.msg, r.ok);
      if (r.ok) { manualModal.style.display = 'none'; setTimeout(function() { if(typeof navigateTo==='function')navigateTo(location.href,false);else location.reload(); }, 500); }
    });
  });

  function esc(s) { var d = document.createElement('span'); d.textContent = s; return d.innerHTML; }
})();
</script>