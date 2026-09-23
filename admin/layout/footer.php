<?php defined('MAPI_ADMIN') or die('禁止直接访问'); ?>
</div>

<div id="toast"></div>
<?php if ($isMobile): ?>
<div class="bottom-nav" id="bottomNav">
  <div class="nav-pill" id="navPill"></div>
  <a href="?action=dashboard" class="nav-item<?= $action === 'dashboard' ? ' active' : '' ?>"><?= svg('dash') ?><span>仪表盘</span></a>
  <a href="?action=keys" class="nav-item<?= $action === 'keys' ? ' active' : '' ?>"><?= svg('key') ?><span>密钥</span></a>
  <a href="?action=users" class="nav-item<?= $action === 'users' ? ' active' : '' ?>"><?= svg('user') ?><span>人员</span></a>
  <a href="?action=config" class="nav-item<?= $action === 'config' ? ' active' : '' ?>"><?= svg('config') ?><span>配置</span></a>
  <a href="?action=settings" class="nav-item<?= $action === 'settings' ? ' active' : '' ?>"><?= svg('srv') ?><span>设置</span></a>
</div>
<?php endif; ?>
<?php $device = $isMobile ? 'mobile' : 'pc'; ?>
<script src="<?= str_replace('{device}', $device, $RELAY['page']['base_js']) ?>"></script>
<?php $pageJsKey = $action . '_js'; if (isset($RELAY['page'][$pageJsKey])): ?>
<script src="<?= str_replace('{device}', $device, $RELAY['page'][$pageJsKey]) ?>"></script>
<?php endif; ?>
</body>
</html>