<?php
if (!isset($RELAY)) return;
$embedJs = $RELAY['asset']['embed_js'];
// 跨域兼容：相对路径补全为绝对URL，确保嵌入方页面跨域时也能正确加载
if ($embedJs && $embedJs[0] === '/') {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $embedJs = $scheme . '://' . $_SERVER['HTTP_HOST'] . $embedJs;
}
?>
<script>
(function(){
    var E=<?= json_encode($embedJs) ?>;
    // 后台预加载并发：多进程服务器（Nginx/Apache + PHP-FPM）用 3，单线程 php -S 用 1（否则切页会被排队拖慢）
    window.__mapiPrefetchConcurrency=<?= (strpos((string)($_SERVER['SERVER_SOFTWARE'] ?? ''), 'Development Server') !== false) ? 1 : 3 ?>;
    function readCookie(n){try{var m=document.cookie.match('(^| )'+n+'=([^;]+)');return m?decodeURIComponent(m[2]):''}catch(e){return''}}
    function loadPlayer(btn){
        var s=document.createElement('script');
        s.src=E+(E.indexOf('?')>=0?'&':'?')+'v=4';
        var t=readCookie('mapi_token');
        if(t)s.setAttribute('token',t);
        document.body.appendChild(s);
        if(btn){btn.textContent='✓ 已加载';btn.style.pointerEvents='none';btn.style.opacity='.5';}
    }
    window.mapiLoadPlayer=loadPlayer;
    var auto=document.querySelector('[data-mapi-auto]');
    if(auto)loadPlayer(auto);
})();
</script>