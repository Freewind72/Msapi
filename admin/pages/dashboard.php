<?php defined('MAPI_ADMIN') or die('禁止直接访问');
// dashboard.php — 仪表盘
$dataVersion = '1.0.0';
$r = $db->query("SELECT VERSION() AS version");
if ($r && ($row = $r->fetch_assoc())) {
    $v = $row['version'];
    // "11.8.8-MariaDB-ubu2404" → "MariaDB-11.8.8"
    if (preg_match('/^([\d.]+)-(\w+)/', $v, $m)) {
        $dataVersion = $m[2] . '-' . $m[1];
    } else {
        $dataVersion = $v;
    }
} else {
    $dataVersion = '?';
}
?>
<div class="stats-panel">
<?php if (($_SESSION['admin_is_admin'] ?? 99) <= 1): ?>
  <div class="stats-section-title">系统配置</div>
  <div class="stats-system">
    <div class="stat-item"><div class="stat-num"><?= PHP_VERSION ?></div><div class="stat-label">PHP 版本</div></div>
    <div class="stat-item"><div class="stat-num"><?= htmlspecialchars($dataVersion) ?></div><div class="stat-label">数据库</div></div>
  </div>
  <div class="stats-divider"></div>
<?php endif; ?>
  <div class="stats-section-title">播放器状态</div>
  <div class="stats-usage">
    <div class="stat-item"><div class="stat-num"><?= number_format($totalCalls) ?></div><div class="stat-label">总调用</div></div>
    <div class="stat-item"><div class="stat-num"><?= number_format($todayCalls) ?></div><div class="stat-label">今日</div></div>
    <div class="stat-item"><div class="stat-num"><?= formatBytes($totalTraffic) ?></div><div class="stat-label">总流量</div></div>
    <div class="stat-item"><div class="stat-num"><?= count($keys) ?></div><div class="stat-label">密钥</div></div>
  </div>
</div>

<div class="card">
  <div class="card-header">
    <span class="card-title">最近调用</span>
    <?php if (($_SESSION['admin_is_admin'] ?? 99) === 0): ?>
    <form method="post" action="?action=clear-logs" onsubmit="return confirm('确认清空所有调用记录？')" style="margin:0"><input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
      <button class="btn-sm danger">清空记录</button>
    </form>
    <?php endif; ?>
  </div>
  <div class="log-list">
  <?php if (empty($logs)): ?><div class="empty">暂无调用记录</div>
  <?php else: ?>
  <?php foreach ($logs as $log): ?>
  <div class="log-item">
    <div class="log-row">
      <span class="log-time"><?= substr($log['created_at'],5,11) ?></span>
      <span class="log-ip"><?= htmlspecialchars($log['ip']) ?></span>
      <span class="log-ref"><?= htmlspecialchars(preg_replace('#^https?://([^/]+).*#', '$1', $log['referer'] ?: '-')) ?></span>
    </div>
  </div>
  <?php endforeach; ?>
  <?php endif; ?>
  </div>
</div>