<?php defined('MAPI_ADMIN') or die('禁止直接访问');
// keys.php — 密钥管理

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

$isAdmin = $_SESSION['admin_is_admin'] ?? 99;
$keyCount = count($keys);

$expireAt = null; $isActive = true;
if ($isAdmin > 1) {
    $r = $db->query("SELECT expire_at FROM mapi_users WHERE id=" . (int)$_SESSION['admin_id']);
    $expireAt = $r ? $r->fetch_assoc()['expire_at'] : null;
    $isActive = $expireAt && strtotime($expireAt) >= time();
}
$keyLimit = 0;
if ($isAdmin > 1) {
    $keyLimit = 1;
    $r = $db->query("SELECT config_value FROM mapi_config WHERE config_key='key_limit'");
    if ($r && $row = $r->fetch_assoc()) {
        $cfg = json_decode($row['config_value'], true);
        if (is_array($cfg) && isset($cfg['limit'])) $keyLimit = max(1, (int)$cfg['limit']);
    }
    $canCreate = $isActive && $keyCount < $keyLimit;
}
?>
<?php if ($isAdmin > 1 && !$isActive && $keyCount > 0): ?>
<div class="card" style="border:1px solid rgba(231,76,60,.3);background:rgba(231,76,60,.08);margin-bottom:12px">
  <div style="padding:12px 16px;color:#e74c3c;font-weight:600;display:flex;align-items:center;gap:8px">
    <?= svg('alert') ?> 您的账户<?= $expireAt ? '已于 ' . htmlspecialchars($expireAt) . ' 过期' : '尚未激活' ?>，现有密钥已被拦截
  </div>
</div>
<?php endif; ?>
<div class="card">
  <div class="card-header">
    <span class="card-title"><?= svg('key') ?> API 密钥<?= $isAdmin <= 1 ? ' <small style="font-weight:400;color:rgba(0,0,0,.38)">' . $keyCount . ' 个</small>' : '' ?></span>
    <?php if ($isAdmin > 1): ?><span style="font-size:12px;color:rgba(0,0,0,.38);margin-right:auto">已创建 <?= $keyCount ?> / 上限 <?= $keyLimit ?></span><?php endif; ?>
    <?php if ($isAdmin <= 1 || $canCreate): ?>
    <form method="post" action="?action=keys-create"><input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
      <button class="btn-sm"><?= svg('plus') ?> 创建密钥</button>
    </form>
    <?php endif; ?>
  </div>
  <?php if (empty($keys)): ?><div class="empty">暂无密钥，点击右上角创建</div>
  <?php else: ?>
  <div class="data-grid data-grid-2">
  <?php foreach ($keys as $key): ?>
  <div class="data-item" data-api-key="<?= htmlspecialchars($key['api_key']) ?>">
    <div class="data-body">
      <div class="data-main">
        <button class="btn-copy" onclick="var k=this.parentNode.nextElementSibling.textContent;navigator.clipboard.writeText(k);var org=this.innerHTML;this.innerHTML='<svg viewBox=&quot;0 0 24 24&quot; fill=&quot;none&quot; stroke=&quot;currentColor&quot; stroke-width=&quot;2&quot;><polyline points=&quot;20 6 9 17 4 12&quot;/></svg>';setTimeout(function(){this.innerHTML=org},1500)"><?= svg('copy') ?></button>
        <span class="data-label"><?= htmlspecialchars($key['username'] ?? '未绑定') ?></span>
        <span class="tag <?= $key['status'] ? 'tag-ok' : 'tag-off' ?>"><?= $key['status'] ? '启用' : '禁用' ?></span>
      </div>
      <div class="data-code"><?= htmlspecialchars($key['api_key']) ?></div>
      <div class="data-meta">
        <span>歌单 <?php $kid = (int)$key['id']; $pls = $playlistsByKey[$kid] ?? []; echo count($pls) > 0 ? count($pls) . ' 个' : '未设置'; ?></span>
        <span><?= substr($key['created_at'],0,10) ?></span>
      </div>
    </div>
    <div class="data-actions">
      <button class="btn-sm btn-test-load" data-key="<?= htmlspecialchars($key['api_key']) ?>" onclick="toggleTestPlayer('<?= htmlspecialchars($key['api_key']) ?>')"><?= svg('play') ?> 加载</button>
      <button class="btn-sm" onclick="copyEmbedKey('<?= htmlspecialchars($key['api_key']) ?>');showToast('嵌入代码已复制','ok')"><?= svg('copy') ?> 嵌入</button>
      <form method="post" action="?action=keys-delete" onsubmit="return confirm('确认删除此密钥？')"><input type="hidden" name="_csrf" value="<?= csrf_token() ?>"><input type="hidden" name="id" value="<?= $key['id'] ?>">
        <button class="btn-sm danger"><?= svg('trash') ?> 删除</button>
      </form>
    </div>
  </div>
  <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>