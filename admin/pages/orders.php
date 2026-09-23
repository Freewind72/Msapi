<?php defined('MAPI_ADMIN') or die('禁止直接访问');
$isAdmin = $_SESSION['admin_is_admin'] ?? 99;
$uid = (int)$_SESSION['admin_id'];

$typeLabels = ['day' => '天卡', 'month' => '月卡', 'quarter' => '季卡', 'year' => '年卡'];

if ($isAdmin <= 1) {
    $r = $db->query("SELECT o.*, u.username FROM mapi_orders o LEFT JOIN mapi_users u ON o.user_id=u.id ORDER BY o.id DESC LIMIT 200");
} else {
    $r = $db->query("SELECT o.*, u.username FROM mapi_orders o LEFT JOIN mapi_users u ON o.user_id=u.id WHERE o.user_id=$uid ORDER BY o.id DESC LIMIT 200");
}
$orders = [];
if ($r) while ($row = $r->fetch_assoc()) $orders[] = $row;
?>

<div class="card">
  <div class="card-header">
    <span class="card-title">购买记录</span>
    <?php if (($_SESSION['admin_is_admin'] ?? 99) === 0): ?>
    <div style="display:flex;align-items:center;gap:6px;flex-shrink:0">
      <form method="post" action="?action=order-clear" onsubmit="return confirm('确认清空所有订单记录？此操作不可恢复。')" style="margin:0"><input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
        <button class="btn-sm danger">清空记录</button>
      </form>
    </div>
    <?php endif; ?>
  </div>
  <?php if (empty($orders)): ?>
  <div class="empty">暂无购买记录</div>
  <?php else: ?>
  <div class="data-grid">
  <?php foreach ($orders as $o): ?>
  <div class="data-item">
    <div class="data-body">
      <div class="data-main">
        <span class="data-label"><?= htmlspecialchars($o['product_name']) ?></span>
        <span class="tag <?= $o['type'] === 'year' ? 'tag-admin' : ($o['type'] === 'quarter' ? 'tag-ok' : 'tag-user') ?>"><?= $typeLabels[$o['type']] ?? $o['type'] ?></span>
        <span class="tag <?= $o['status'] ? 'tag-ok' : 'tag-off' ?>"><?= $o['status'] ? '已支付' : '待支付' ?></span>
      </div>
      <div class="data-code" style="font-family:monospace;font-size:11px;opacity:.6"><?= htmlspecialchars($o['trade_no']) ?></div>
      <div class="data-meta">
        <?php if ($isAdmin <= 1): ?><span>用户: <?= htmlspecialchars($o['username'] ?? '-') ?></span><?php endif; ?>
        <span>¥<?= number_format($o['price'], 2) ?></span>
        <span><?= $o['status'] ? ($o['paid_at'] ?? $o['created_at']) : $o['created_at'] ?></span>
      </div>
    </div>
    <?php if (!$o['status']): ?>
    <div class="data-actions">
      <form method="post" action="?action=order-cancel" onsubmit="return confirm('确认取消此订单？')"><input type="hidden" name="_csrf" value="<?= csrf_token() ?>"><input type="hidden" name="id" value="<?= $o['id'] ?>">
        <button class="btn-sm danger">取消</button>
      </form>
    </div>
    <?php endif; ?>
  </div>
  <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>