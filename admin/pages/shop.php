<?php defined('MAPI_ADMIN') or die('禁止直接访问');
$isAdmin = $_SESSION['admin_is_admin'] ?? 99;
$uid = (int)$_SESSION['admin_id'];

$typeLabels = ['day' => '天卡', 'month' => '月卡', 'quarter' => '季卡', 'year' => '年卡'];
$typeColors = ['day' => '#3498db', 'month' => '#9b59b6', 'quarter' => '#e67e22', 'year' => '#e74c3c'];

$r = $db->query("SELECT * FROM mapi_products WHERE status=1 ORDER BY sort_order ASC, id ASC");
$products = [];
if ($r) while ($row = $r->fetch_assoc()) $products[] = $row;

$userList = [];
if ($isAdmin <= 1) {
    $ur = $db->query("SELECT id, username FROM mapi_users ORDER BY id");
    if ($ur) while ($urow = $ur->fetch_assoc()) $userList[] = $urow;
}

$expire = null;
$er = $db->query("SELECT expire_at FROM mapi_users WHERE id=$uid");
if ($er && $erow = $er->fetch_assoc()) $expire = $erow['expire_at'];
?>

<?php if ($isAdmin <= 1): ?>
<div class="card">
  <div class="card-header"><span class="card-title">添加商品</span></div>
  <form method="post" action="?action=shop-add"><input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
    <div class="form-group">
      <label class="form-label">商品名称</label>
      <input class="form-input" name="name" required placeholder="如：基础月卡">
    </div>
    <div class="form-group">
      <label class="form-label">类型</label>
      <select class="form-input" name="type" required>
        <option value="day">天卡</option>
        <option value="month">月卡</option>
        <option value="quarter">季卡</option>
        <option value="year">年卡</option>
      </select>
    </div>
    <div class="form-group">
      <label class="form-label">价格（元）</label>
      <input class="form-input" type="number" name="price" step="0.01" min="0" required placeholder="0.00">
    </div>
    <div class="form-group">
      <label class="form-label">有效期（天）</label>
      <input class="form-input" type="number" name="duration_days" min="1" required placeholder="30">
    </div>
    <div class="form-group">
      <label class="form-label">描述</label>
      <textarea class="form-input" name="description" rows="2" placeholder="商品描述"></textarea>
    </div>
    <button class="btn btn-primary btn-block">添加</button>
  </form>
</div>
<?php endif; ?>

<?php if ($expire): ?>
<div class="card" style="border-left:3px solid <?= strtotime($expire) > time() ? '#2ecc71' : '#e74c3c' ?>">
  <div style="display:flex;align-items:center;justify-content:space-between">
    <span style="font-weight:600">账户有效期</span>
    <span style="color:<?= strtotime($expire) > time() ? '#2ecc71' : '#e74c3c' ?>;font-weight:700">
      <?php if (strtotime($expire) > time()): ?>
        至 <?= date('Y-m-d H:i', strtotime($expire)) ?>
      <?php else: ?>
        已过期 (<?= date('Y-m-d', strtotime($expire)) ?>)
      <?php endif; ?>
    </span>
  </div>
</div>
<?php else: ?>
<div class="card" style="border-left:3px solid #e74c3c">
  <span style="font-weight:600;color:#e74c3c">尚未激活，购买任意商品即可激活账户</span>
</div>
<?php endif; ?>

<?php if (empty($products)): ?>
<div class="card"><div class="empty">暂无商品</div></div>
<?php else: ?>
<div class="shop-grid">
<?php foreach ($products as $p): ?>
<div class="card shop-card">
  <div class="shop-card-header" style="background:<?= $typeColors[$p['type']] ?? '#666' ?>">
    <span class="shop-card-type"><?= $typeLabels[$p['type']] ?? $p['type'] ?></span>
    <span class="shop-card-price">¥<?= number_format($p['price'], 2) ?></span>
  </div>
  <div class="shop-card-body">
    <h3><?= htmlspecialchars($p['name']) ?></h3>
    <p class="shop-card-duration">有效期 <?= (int)$p['duration_days'] ?> 天</p>
    <?php if ($p['description']): ?>
    <p class="shop-card-desc"><?= htmlspecialchars($p['description']) ?></p>
    <?php endif; ?>
  </div>
  <div class="shop-card-actions">
    <?php if ($isAdmin <= 1): ?>
      <form method="post" action="?action=shop-edit" style="display:inline"><input type="hidden" name="_csrf" value="<?= csrf_token() ?>"><input type="hidden" name="id" value="<?= $p['id'] ?>">
        <input class="form-input" name="name" value="<?= htmlspecialchars($p['name']) ?>" style="width:100px;display:inline-block;margin-right:4px" required>
        <input class="form-input" type="number" name="price" step="0.01" min="0" value="<?= $p['price'] ?>" style="width:70px;display:inline-block;margin-right:4px" required>
        <input class="form-input" type="number" name="duration_days" min="1" value="<?= $p['duration_days'] ?>" style="width:60px;display:inline-block;margin-right:4px" required>
        <select class="form-input" name="type" style="width:80px;display:inline-block;margin-right:4px">
          <?php foreach ($typeLabels as $tv => $tl): ?>
          <option value="<?= $tv ?>" <?= $p['type'] === $tv ? 'selected' : '' ?>><?= $tl ?></option>
          <?php endforeach; ?>
        </select>
        <textarea class="form-input" name="description" style="width:100%;margin:4px 0" rows="1" placeholder="描述"><?= htmlspecialchars($p['description']) ?></textarea>
        <button class="btn btn-sm" style="background:rgba(52,152,219,.1);color:#3498db">保存</button>
      </form>
      <form method="post" action="?action=shop-delete" style="display:inline" onsubmit="return confirm('确认删除？')"><input type="hidden" name="_csrf" value="<?= csrf_token() ?>"><input type="hidden" name="id" value="<?= $p['id'] ?>">
        <button class="btn btn-sm" style="background:rgba(231,76,60,.1);color:#e74c3c">删除</button>
      </form>
      <form method="post" action="?action=shop-free-buy" style="display:inline"><input type="hidden" name="_csrf" value="<?= csrf_token() ?>"><input type="hidden" name="id" value="<?= $p['id'] ?>">
        <button class="btn btn-sm" style="background:rgba(46,204,113,.1);color:#2ecc71">免费购买</button>
      </form>
      <form method="post" action="?action=shop-gift" style="display:inline"><input type="hidden" name="_csrf" value="<?= csrf_token() ?>"><input type="hidden" name="product_id" value="<?= $p['id'] ?>">
        <select name="user_id" class="form-input" style="width:auto;display:inline-block;padding:4px 8px;font-size:12px;margin-right:2px" required>
          <option value="">选择用户</option>
          <?php foreach ($userList as $u): if ($u['id'] == $uid) continue; ?>
          <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['username']) ?></option>
          <?php endforeach; ?>
        </select>
        <button class="btn btn-sm" style="background:rgba(155,89,182,.1);color:#9b59b6">赠送</button>
      </form>
    <?php else: ?>
      <a href="?action=shop-buy&id=<?= $p['id'] ?>" class="btn btn-primary btn-block">立即购买</a>
    <?php endif; ?>
  </div>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>