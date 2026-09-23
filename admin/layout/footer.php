<?php defined('MAPI_ADMIN') or die('禁止直接访问'); ?>
</div>

<nav class="bottom-nav mobile-only" id="bottomNav">
<?php $i = 0; foreach ($navItems as $k => $item): if (($k === 'settings' || $k === 'users') && (($_SESSION['admin_is_admin'] ?? 99) > 1)) continue; $act = $k === $action ? ' active' : ''; ?>
<a href="?action=<?= $k ?>" class="nav-item<?= $act ?>"><?= $item['icon'] ?><span><?= $item['label'] ?></span></a>
<?php $i++; endforeach; ?>
<div class="nav-pill" id="navPill"></div>
</nav>

<div id="toast"></div>
<style>
.float-toast{position:fixed;top:60px;right:16px;z-index:9999;background:rgba(108,92,231,.92);color:#fff;padding:10px 20px;border-radius:12px;font-size:13px;font-weight:500;backdrop-filter:blur(8px);box-shadow:0 4px 20px rgba(0,0,0,.2);transform:translateX(120%);opacity:0;transition:all .4s cubic-bezier(.4,0,.2,1);pointer-events:none}
.float-toast.show{transform:translateX(0);opacity:1}
</style>
<script src="<?= $RELAY['page']['admin_js'] ?>"></script>
<script>
/* ═══Pusher 实时通知（服务端推送模式） ═══*/
(function(){
  var curUser = (document.querySelector('.topbar-username') || {}).textContent || '';
  var pusher = new Pusher('333723b6068a283d1b6b', { cluster: 'ap3', channelAuthorization: { endpoint: '?action=pusher-auth', transport: 'ajax' } });
  var channel = pusher.subscribe('presence-admin-online');

  // 服务端推送：用户上线通知
  channel.bind('user-online', function(data) {
    if(data.username && data.username !== curUser) showFloatingToast(data.username + ' 上线了);
  });

  // 服务端推送：用户下线通知
  channel.bind('user-offline', function(data) {
    if(data.username && data.username !== curUser) showFloatingToast(data.username + ' 离线了);
  });

  // 悬浮通知
  function showFloatingToast(msg){
    var el = document.createElement('div');
    el.className = 'float-toast';
    el.textContent = msg;
    document.body.appendChild(el);
    requestAnimationFrame(function(){ el.classList.add('show'); });
    setTimeout(function(){ el.classList.remove('show'); setTimeout(function(){ el.remove(); }, 400); }, 3000);
  }
  window.showFloatingToast = showFloatingToast;

  // ═══在线用户追踪（服务端推送） ═══  window.pusherOnlineIds = {};
  window.pusherOnlineReady = false;

  // 收到上线通知 →记录在线 + 更新页面绿点
  channel.bind('user-online', function(data) {
    if(data.user_id){
      window.pusherOnlineIds[data.user_id] = true;
      window.pusherOnlineReady = true;
      var el = document.querySelector('.online-dot[data-uid="'+data.user_id+'"]');
      if(el){ el.style.background='#27ae60'; el.title='在线'; }
    }
  });

  // 收到下线通知 →移除在线
  channel.bind('user-offline', function(data) {
    if(data.user_id){
      delete window.pusherOnlineIds[data.user_id];
      var el = document.querySelector('.online-dot[data-uid="'+data.user_id+'"]');
      if(el){ el.style.background='#b2bec3'; el.title='离线'; }
    }
  });
})();
</script>
</body>
</html>