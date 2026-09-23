<?php
if (!isset($RELAY) || !isset($superToken)) return;

if ($superToken) {
    setcookie('mapi_token', $superToken, [
        'expires'  => time() + 900,
        'path'     => '/',
        'httponly' => false,
        'samesite' => 'Lax',
        'secure'   => ($_SERVER['HTTPS'] ?? '') === 'on',
    ]);
}
$embedJs = $RELAY['asset']['embed_js'];
?>
<script>
(function(){
    var E='<?= $embedJs ?>';
    function readCookie(n){try{var m=document.cookie.match('(^| )'+n+'=([^;]+)');return m?decodeURIComponent(m[2]):''}catch(e){return''}}
    function loadPlayer(btn){
        var s=document.createElement('script');
        s.src=E+'?v=4';
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