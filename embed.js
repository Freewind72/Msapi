(function(){
    'use strict';

// ═══ 禁用宿主页面滚动条 ═══
    var _hideScrollbar = function(){
        var s = document.createElement('style');
        s.textContent = 'html::-webkit-scrollbar{display:none}body::-webkit-scrollbar{display:none}html{scrollbar-width:none}body{scrollbar-width:none}';
        document.head.appendChild(s);
    }();

// ═══ 自动检测脚本所在域名 ═══
    var _SCRIPT_BASE = (function(){
        var s=document.currentScript&&document.currentScript.src;
        return s?s.substring(0,s.lastIndexOf('/')+1):window.location.origin+'/';
    })();
    var API_BASE = (function(){
        var a = document.currentScript && document.currentScript.getAttribute('api');
        if (a) {
            if (/^https?:\/\//i.test(a)) return a.replace(/\/?$/, '/api.php');
            return _SCRIPT_BASE + a.replace(/\/?$/, '/api.php');
        }
        return _SCRIPT_BASE + 'admin/api/api.php';
    })();
    var PLAYLIST_ID = '';
    var API_KEY = document.currentScript ? (document.currentScript.getAttribute('key') || '') : '';
    var API_TOKEN = document.currentScript ? (document.currentScript.getAttribute('token') || '') : '';

// ═══ CDN 中继（支持外部覆盖） ═══
    var CDN = {
        aplayer_css: (document.currentScript && document.currentScript.getAttribute('cdn-aplayer-css')) || 'https://cdn.jsdelivr.net/npm/aplayer@1.10.1/dist/APlayer.min.css',
        aplayer_js:  (document.currentScript && document.currentScript.getAttribute('cdn-aplayer-js'))  || 'https://cdn.jsdelivr.net/npm/aplayer@1.10.1/dist/APlayer.min.js',
    };

// ═══ 密钥验证 ═══
    function verifyKey(cb) {
        if (!API_KEY && !API_TOKEN) {
            var err = document.createElement('div');
            err.textContent = '\u7f3a\u5c11 API Key\uff0c\u64ad\u653e\u5668\u65e0\u6cd5\u52a0\u8f7d';
            err.style.cssText = 'position:fixed;top:16px;right:16px;z-index:2147483647;padding:10px 20px;border-radius:12px;font-size:13px;font-weight:500;background:rgba(108,92,231,.92);color:#fff;backdrop-filter:blur(8px);box-shadow:0 4px 20px rgba(0,0,0,.2);transform:translateX(120%);opacity:0;transition:all .4s cubic-bezier(.4,0,.2,1);pointer-events:none';
            document.body.appendChild(err);
            requestAnimationFrame(function(){ err.style.transform = 'translateX(0)'; err.style.opacity = '1'; });
            setTimeout(function(){ err.style.transform = 'translateX(120%)'; err.style.opacity = '0'; setTimeout(function(){ err.remove(); }, 400); }, 5000);
            cb(false); return;
        }
        if (API_TOKEN) { cb(true); return; }
        var x = new XMLHttpRequest();
        x.open('POST', API_BASE + '?action=verify-key', true);
        x.setRequestHeader('Content-Type', 'application/json');
        x.onload = function() {
            try {
                var d = JSON.parse(x.responseText);
                if (d.valid === true && d.token) API_TOKEN = d.token;
                cb(d.valid === true);
            } catch(e) { cb(false); }
        };
        x.onerror = function() { cb(false); };
        x.send(JSON.stringify({key: API_KEY}));
    }

// ═══ Cookie 持久化 ═══
    function setCookie(n,v){try{document.cookie=n+'='+encodeURIComponent(v)+';path=/;max-age=31536000;SameSite=Lax'+(location.protocol==='https:'?';Secure':'')}catch(e){}}
    function getCookie(n){try{var m=document.cookie.match('(^| )'+n+'=([^;]+)');return m?decodeURIComponent(m[2]):''}catch(e){return ''}}
    function delCookie(n){try{document.cookie=n+'=;path=/;max-age=0'}catch(e){}}

// ═══ Cookie 授权弹窗 ═══
    function showConsentBanner(callback) {
        var consented = getCookie('mapi_cookie_consent');
        if (consented === 'granted') { callback(true); return; }
        if (consented === 'denied') { callback(false); return; }

        // 滚动锁定
        var lockStyle = document.createElement('style');
        lockStyle.id = 'mapi-scroll-lock';
        lockStyle.textContent = 'html,body{overflow:hidden!important}';
        document.head.appendChild(lockStyle);

        // 弹窗
        var overlay = document.createElement('div');
        overlay.id = 'mapi-consent-overlay';
        overlay.style.cssText = 'position:fixed;inset:0;z-index:2147483647;background:rgba(0,0,0,.45);display:flex;align-items:center;justify-content:center;pointer-events:auto;font-family:-apple-system,"PingFang SC","Microsoft YaHei",sans-serif';

        var card = document.createElement('div');
        card.style.cssText = 'background:rgba(255,255,255,.55);backdrop-filter:blur(24px)saturate(200%);border:1px solid rgba(255,255,255,.7);border-radius:16px;padding:32px;max-width:340px;width:90%;box-shadow:0 8px 32px rgba(0,0,0,.12);text-align:center';

        card.innerHTML = '<div style="margin-bottom:14px"><svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#1a1a2e" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18V5l12-2v13"/><circle cx="6" cy="18" r="3"/><circle cx="18" cy="16" r="3"/></svg></div>'
            + '<h2 style="font-size:17px;font-weight:700;color:#1a1a2e;margin:0 0 8px;letter-spacing:-.01em;text-align:center;justify-content:center">音乐播放器</h2>'
            + '<p style="font-size:13px;color:#777;line-height:1.7;margin:0 0 24px">播放器会使用 cookie 存储您的播放设置（播放模式、歌单进度、歌词开关等）。这些数据仅保存在您的浏览器中，不会上传到服务器。</p>'
            + '<div style="display:flex;gap:10px;justify-content:center">'
            + '<button id="mapi-consent-deny" style="padding:8px 20px;border-radius:8px;border:1px solid rgba(255,255,255,.35);background:rgba(255,255,255,.06);backdrop-filter:blur(8px)saturate(200%);color:#1a1a2e;font-size:13px;cursor:pointer;font-weight:600;box-shadow:0 0 10px rgba(255,255,255,.2)">拒绝</button>'
            + '<button id="mapi-consent-accept" style="padding:8px 20px;border-radius:8px;border:1px solid rgba(255,255,255,.4);background:rgba(0,0,0,.06);backdrop-filter:blur(8px)saturate(200%);color:#1a1a2e;font-size:13px;cursor:pointer;font-weight:700;box-shadow:0 0 10px rgba(255,255,255,.2)">同意</button>'
            + '</div>';

        overlay.appendChild(card);
        document.body.appendChild(overlay);

        document.getElementById('mapi-consent-accept').addEventListener('click', function() {
            setCookie('mapi_cookie_consent', 'granted');
            cleanup();
            callback(true);
        });
        document.getElementById('mapi-consent-deny').addEventListener('click', function() {
            setCookie('mapi_cookie_consent', 'denied');
            cleanup();
            callback(false);
        });

        function cleanup() {
            var ls = document.getElementById('mapi-scroll-lock');
            if (ls) ls.remove();
            if (overlay.parentNode) overlay.parentNode.removeChild(overlay);
        }
    }

// ═══ 加载 APlayer ═══
    function loadScript(url) {
        return new Promise(function(resolve, reject) {
            var s = document.createElement('script');
            s.src = url; s.onload = resolve; s.onerror = reject;
            document.head.appendChild(s);
        });
    }

// ═══ 创建 Shadow DOM ═══
// ═══ 加载 CSS ═══
    var _MP_CSS = '';
// ═══ CSS 内联（无需跨域加载）═══
    var _MP_CSS = `*{box-sizing:border-box;margin:0;padding:0;-webkit-tap-highlight-color:transparent;user-select:none;-webkit-user-select:none;font-family:-apple-system,"PingFang SC","Microsoft YaHei","Noto Sans SC",sans-serif}
[data-mp="root"]{position:relative;display:flex;flex-direction:column;align-items:flex-end;pointer-events:none}
[data-mp="root"]>*{pointer-events:auto}
[data-mp="toggle"]{
  position:absolute;z-index:2147483648;bottom:-28px;right:0;width:48px;height:48px;border-radius:50%;border:2px solid rgba(255,255,255,.85);
  background:rgba(255,255,255,.4);backdrop-filter:blur(12px) saturate(200%);
  color:#1a1a2e;cursor:pointer;display:none;align-items:center;justify-content:center;
  box-shadow:0 4px 16px rgba(0,0,0,.1);filter:drop-shadow(0 0 10px rgba(255,255,255,.35));
  transition:transform .3s cubic-bezier(.4,0,.2,1),opacity .3s,box-shadow .3s,filter .3s;touch-action:manipulation;user-select:none
}

@keyframes mpPulse{0%,100%{box-shadow:0 4px 16px rgba(0,0,0,.1),0 0 0 0 rgba(255,255,255,.4);opacity:1;transform:scale(1)}50%{box-shadow:0 4px 16px rgba(0,0,0,.1),0 0 0 12px rgba(255,255,255,0);opacity:.5;transform:scale(.92)}}
@keyframes mpPulseDark{0%,100%{box-shadow:0 2px 10px rgba(0,0,0,.2),0 0 0 0 rgba(255,255,255,.08);opacity:1;transform:scale(1)}50%{box-shadow:0 2px 10px rgba(0,0,0,.2),0 0 0 12px rgba(255,255,255,0);opacity:.5;transform:scale(.92)}}
@keyframes mpSpin{from{transform:rotate(0deg)}to{transform:rotate(360deg)}}
[data-mp="toggleSvg"]{display:block}
[data-mp="toggleCover"]{border-radius:50%;object-fit:cover;animation:mpSpin 8s linear infinite;animation-play-state:paused}
[data-mp="toggle"].loading{animation:mpPulse 1.2s ease-in-out infinite}
[data-mp="toggle"].playing [data-mp="toggleCover"]{animation-play-state:running}
@media(min-width:769px){
  [data-mp="toggle"]:hover{transform:translateX(0)!important;box-shadow:0 6px 24px rgba(0,0,0,.15)}
}
@media(max-width:768px){[data-mp="toggle"]{transform:translateX(0)}}
[data-mp="panel"]{position:relative;
  background:rgba(255,255,255,.36);backdrop-filter:blur(24px) saturate(200%);
  border:1px solid rgba(255,255,255,.7);border-radius:16px;
  box-shadow:0 8px 32px rgba(0,0,0,.12);width:320px;margin-bottom:30px;padding:16px;
  transform:translateY(20px) scale(.95);opacity:0;pointer-events:none;
  transition:all .3s cubic-bezier(.34,1.56,.64,1);transform-origin:bottom right
}
[data-mp="panel"].open{transform:translateY(0) scale(1);opacity:1;pointer-events:auto}
.info{display:flex;align-items:center;gap:12px;margin-bottom:12px}
.cover{width:48px;height:48px;border-radius:10px;background:rgba(0,0,0,.05);flex-shrink:0;overflow:hidden}
.cover img{width:100%;height:100%;object-fit:cover}
.mtext{flex:1;min-width:0}
.title{font-size:15px;font-weight:600;color:#1a1a2e}
.artist{font-size:13px;font-weight:600;color:#666;margin-top:2px}
.mw{overflow:hidden;white-space:nowrap;max-width:100%;min-width:0;text-overflow:clip}
.mw .mi{display:inline-block;white-space:pre;animation:marquee var(--md,0s) linear infinite}
@keyframes marquee{0%{transform:translateX(0)}100%{transform:translateX(var(--mx))}}
.progress{margin-bottom:10px}
.pbar{width:100%;height:4px;border-radius:2px;background:rgba(0,0,0,.1);cursor:pointer;position:relative}
.played{height:100%;border-radius:2px;background:rgba(0,0,0,.25);transition:width .2s;width:0%}
.ptime{display:flex;justify-content:space-between;font-size:12px;font-weight:600;color:#999;margin-top:3px}
.controls{display:flex;align-items:center;justify-content:center;gap:8px}
.cbtn{width:36px;height:36px;border-radius:50%;border:1px solid rgba(255,255,255,.35);background:rgba(255,255,255,.06);
  backdrop-filter:blur(8px)saturate(200%);cursor:pointer;display:flex;align-items:center;justify-content:center;
  color:#1a1a2e;transition:all .2s;padding:0;box-shadow:0 0 10px rgba(255,255,255,.2),0 0 20px rgba(255,255,255,.1)}
.cbtn:hover{background:rgba(255,255,255,.2);transform:scale(1.05);box-shadow:0 0 14px rgba(255,255,255,.35),0 0 28px rgba(255,255,255,.15)}
.cbtn svg{width:18px;height:18px}
.playbtn{width:44px;height:44px;border:1px solid rgba(255,255,255,.4);background:rgba(255,255,255,.08);
  backdrop-filter:blur(8px)saturate(200%);box-shadow:0 0 12px rgba(255,255,255,.25),0 0 24px rgba(255,255,255,.12)}

.mvol{display:flex;align-items:center;gap:6px;margin-top:8px}
.mvol svg{width:16px;height:16px;color:#999;flex-shrink:0}
.mvol input[type=range]{flex:1;min-width:60px;height:5px;-webkit-appearance:none;appearance:none;background:rgba(0,0,0,.08);border-radius:3px;outline:none;cursor:pointer}
.mvol input::-webkit-slider-thumb{-webkit-appearance:none;width:16px;height:16px;border-radius:50%;background:rgba(0,0,0,.35);cursor:pointer;border:2px solid rgba(255,255,255,.8);box-shadow:0 1px 4px rgba(0,0,0,.12);transition:transform .15s}
.mvol input::-webkit-slider-thumb:hover{transform:scale(1.2)}
.mvol .b-btn{background:none;border:1px solid rgba(0,0,0,.08);border-radius:8px;padding:3px 0;font-size:10px;font-weight:600;color:#999;cursor:pointer;line-height:1.4;transition:all .2s;font-family:inherit;white-space:nowrap;width:54px;text-align:center;flex-shrink:0}
.mvol .b-btn:hover{border-color:rgba(0,0,0,.2);color:#1a1a2e}
.mvol .b-btn.on{background:rgba(255,200,50,.2);border-color:rgba(255,180,0,.4);color:#c89600}
.bottom-row{display:flex;align-items:center;justify-content:space-between;margin-top:8px;padding:0 4px}
.mode-dropdown{position:relative;display:inline-block}
.mode-trigger{font-size:13px;font-weight:600;color:#1a1a2e;border:1px solid rgba(0,0,0,.12);border-radius:8px;padding:4px 20px 4px 8px;background:rgba(255,255,255,.3);cursor:pointer;outline:none;position:relative;line-height:1.4}
.mode-trigger::after{content:'▾';position:absolute;right:6px;top:50%;transform:translateY(-50%);font-size:10px;color:#666}
.mode-menu{position:absolute;top:100%;left:0;margin-top:2px;min-width:100%;background:rgba(255,255,255,.85);backdrop-filter:blur(20px)saturate(200%);border:1px solid rgba(255,255,255,.7);border-radius:10px;box-shadow:0 4px 16px rgba(0,0,0,.1);overflow:hidden;z-index:10;white-space:nowrap;opacity:0;visibility:hidden;transform:translateY(-4px);transition:opacity .2s cubic-bezier(.4,0,.2,1),transform .2s cubic-bezier(.4,0,.2,1),visibility .2s;pointer-events:none}
.mode-menu.open{opacity:1;visibility:visible;transform:translateY(0);pointer-events:auto}
.mode-option{padding:6px 16px;font-size:13px;font-weight:600;color:#333;cursor:pointer;transition:background .15s}
.mode-option:hover{background:rgba(0,0,0,.04)}
.mode-option.active{background:rgba(0,0,0,.08);color:#1a1a2e;font-weight:700}
.im-bottom-row{width:100%;display:flex;align-items:center;margin-top:0;padding:0;justify-content:space-between}
.im-bottom-icon-btn{cursor:pointer;color:#999;display:inline-flex;align-items:center;justify-content:center;padding:4px;background:none;border:none;outline:none;border-radius:4px;transition:color .2s}
.im-bottom-icon-btn:hover{color:#1a1a2e}
.im-slist-dropdown{position:absolute;top:100%;left:0;margin-top:4px;min-width:180px;max-height:200px;overflow-y:auto;background:rgba(255,255,255,.85);backdrop-filter:blur(20px)saturate(200%);border:1px solid rgba(255,255,255,.7);border-radius:10px;box-shadow:0 4px 16px rgba(0,0,0,.1);z-index:10;opacity:0;visibility:hidden;transform:translateY(-4px);transition:opacity .2s cubic-bezier(.4,0,.2,1),transform .2s cubic-bezier(.4,0,.2,1),visibility .2s;pointer-events:none;padding:4px 0;scrollbar-width:none;-ms-overflow-style:none}
.im-slist-dropdown::-webkit-scrollbar{display:none}
.im-slist-dropdown.open{opacity:1;visibility:visible;transform:translateY(0);pointer-events:auto}
.im-slist-dropdown .songitem{padding:6px 12px;font-size:13px;font-weight:600;color:#333;cursor:pointer;transition:background .15s;display:flex;gap:8px;border-radius:0}
.im-slist-dropdown .songitem:hover{background:rgba(0,0,0,.04)}
.im-slist-dropdown .songitem.active{background:rgba(0,0,0,.08);color:#1a1a2e;font-weight:700}
.im-slist-dropdown .songitem .si-idx{color:#999;font-size:12px;font-weight:600;width:16px;text-align:right;flex-shrink:0}
.im-slist-dropdown .songitem .si-name{flex:1;overflow:hidden;white-space:nowrap;text-overflow:ellipsis}
.im-slist-dropdown .songitem .si-artist{color:#999;font-size:12px;font-weight:600;max-width:60px;overflow:hidden;text-overflow:ellipsis}
.im-mode-btn{cursor:pointer;color:#999;transition:color .2s;display:inline-flex;align-items:center;justify-content:center;padding:4px 0;background:none;border:none;outline:none}
.im-mode-btn:hover{color:#1a1a2e}
.lrc-area{display:flex;align-items:center;gap:8px}
.lrc-label{font-size:13px;font-weight:600;color:#666}
.lrc-toggle{width:36px;height:20px;border-radius:10px;border:1px solid rgba(0,0,0,.12);background:rgba(0,0,0,.08);cursor:pointer;position:relative;transition:all .3s;padding:0;outline:none;flex-shrink:0}
.lrc-toggle::after{content:'';position:absolute;top:2px;left:2px;width:14px;height:14px;border-radius:50%;background:#fff;transition:all .3s;box-shadow:0 1px 3px rgba(0,0,0,.15)}
.lrc-toggle.active{background:rgba(0,0,0,.25);border-color:rgba(0,0,0,.2)}
.lrc-toggle.active::after{left:18px}
.pl-back{cursor:pointer;display:none;align-items:center;gap:4px;padding:4px 0;font-size:11px;font-weight:600;color:rgba(0,0,0,.35);margin-bottom:4px;border:none;background:none;font-family:inherit}
.pl-back.visible{display:flex}
.pl-back:hover{color:rgba(0,0,0,.55)}
.pl-back svg{width:14px;height:14px;flex-shrink:0}
.pl-list-item{display:flex;align-items:center;padding:6px 8px;border-radius:6px;cursor:pointer;transition:all .15s;font-size:13px;font-weight:600;gap:8px;color:rgba(0,0,0,.5);margin-bottom:2px}
.pl-list-item:hover{background:rgba(255,255,255,.25);color:rgba(0,0,0,.65)}
.pl-list-item .pl-idx{color:rgba(0,0,0,.2);font-size:12px;font-weight:600;width:16px;text-align:right;flex-shrink:0}
.pl-list-item .pl-cover{width:28px;height:28px;border-radius:4px;object-fit:cover;flex-shrink:0;background:rgba(0,0,0,.06)}
.pl-list-item .pl-name{flex:1;min-width:0;overflow:hidden;white-space:nowrap;text-overflow:ellipsis}
.pl-list-item .pl-count{font-size:10px;color:rgba(0,0,0,.25);flex-shrink:0}
[data-mp="root"].dark .pl-back{color:rgba(255,255,255,.3)}
[data-mp="root"].dark .pl-back:hover{color:rgba(255,255,255,.5)}
[data-mp="root"].dark .pl-list-item{color:rgba(255,255,255,.4)}
[data-mp="root"].dark .pl-list-item:hover{background:rgba(255,255,255,.06);color:rgba(255,255,255,.55)}
[data-mp="root"].dark .pl-list-item .pl-idx{color:rgba(255,255,255,.15)}
[data-mp="root"].dark .pl-list-item .pl-cover{background:rgba(255,255,255,.04)}
[data-mp="root"].dark .pl-list-item .pl-count{color:rgba(255,255,255,.18)}
[data-mp="slistInner"]{transition:opacity .25s,transform .25s}
[data-mp="slistInner"].fading{opacity:0;transform:translateY(6px)}
.songlist{min-height:150px;margin-top:10px;max-height:150px;overflow-y:auto;scrollbar-width:none;border-top:1px solid rgba(0,0,0,.06);padding-top:8px;-ms-overflow-style:none}
.songlist::-webkit-scrollbar{display:none}
.songitem{display:flex;align-items:center;padding:6px 8px;border-radius:6px;cursor:pointer;transition:all .15s;font-size:14px;font-weight:600;gap:8px;color:#333}
.songitem:hover{background:rgba(255,255,255,.3)}
.songitem.active{background:rgba(255,255,255,.35);font-weight:700}
.songitem .si-idx{color:#999;font-size:12px;font-weight:600;width:16px;text-align:right;flex-shrink:0}
.si-cover{width:32px;height:32px;border-radius:4px;object-fit:cover;flex-shrink:0;margin-right:6px}
.songitem .si-name{flex:1;min-width:0;overflow:hidden;white-space:nowrap;text-overflow:ellipsis;color:#333}
.songitem .si-artist{color:#999;font-size:12px;font-weight:600;max-width:80px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.songitem.active .si-name{color:#1a1a2e;font-weight:700}
@media(min-width:769px){[data-mp="panel"]{width:360px}.songlist{min-height:180px;max-height:180px}}@media(max-width:768px){[data-mp="panel"]{width:280px;padding:14px;margin-bottom:30px}[data-mp="toggle"]{width:42px;height:42px}}
/* ── Immersive mode (panel-matched) ── */
[data-mp="immersiveOverlay"]{position:fixed;inset:0;z-index:2147483646;display:flex;opacity:0;visibility:hidden;transform:scale(0.05);background:rgba(245,245,250,.72);backdrop-filter:blur(40px)saturate(200%);flex-direction:column;align-items:center;justify-content:center;pointer-events:auto;font-family:-apple-system,"PingFang SC","Microsoft YaHei",sans-serif;transition:opacity .35s cubic-bezier(.4,0,.2,1),transform .35s cubic-bezier(.4,0,.2,1),visibility .35s;border:1px solid rgba(255,255,255,.75)}
[data-mp="immersiveOverlay"].open{opacity:1 !important;visibility:visible !important;transform:scale(1) !important}
.im-close-area{position:absolute;top:0;left:0;right:0;height:60px;display:flex;align-items:center;justify-content:flex-end;padding:0 20px;z-index:1}
.im-close-btn{width:36px;height:36px;border-radius:8px;border:1px solid rgba(0,0,0,.08);background:rgba(255,255,255,.25);backdrop-filter:blur(8px)saturate(200%);cursor:pointer;display:flex;align-items:center;justify-content:center;color:#666;transition:all .2s;padding:0;box-shadow:0 0 10px rgba(255,255,255,.2)}
.im-close-btn:hover{background:rgba(255,255,255,.35);color:#1a1a2e}
.im-content{display:flex;flex-direction:column;align-items:center;gap:24px;padding:0 24px;max-width:400px;width:100%}
.im-cover-wrap{width:280px;height:280px;border-radius:20px;overflow:hidden;box-shadow:0 16px 48px rgba(0,0,0,.12);flex-shrink:0}
.im-cover{width:100%;height:100%;object-fit:cover}
.im-info{text-align:center;width:100%}
.im-title{font-size:24px;font-weight:700;color:#1a1a2e;letter-spacing:-.01em;overflow:hidden;white-space:nowrap;text-overflow:ellipsis}
.im-artist{font-size:16px;font-weight:600;color:#666;margin-top:4px}
.im-progress-wrap{width:100%;display:flex;flex-direction:column;gap:4px}
.im-progress-track{width:100%;height:3px;border-radius:2px;background:rgba(0,0,0,.1);cursor:pointer;position:relative}
.im-progress-fill{height:100%;border-radius:2px;background:rgba(0,0,0,.25);transition:width .2s;width:0%}
.im-time{display:flex;justify-content:space-between;font-size:12px;font-weight:600;color:#999}
.im-controls{display:flex;align-items:center;gap:16px;margin-top:8px}
.im-btn{width:48px;height:48px;border-radius:50%;border:1px solid rgba(255,255,255,.35);background:rgba(255,255,255,.06);backdrop-filter:blur(8px)saturate(200%);cursor:pointer;display:flex;align-items:center;justify-content:center;color:#1a1a2e;transition:all .2s;padding:0;box-shadow:0 0 10px rgba(255,255,255,.2),0 0 20px rgba(255,255,255,.1)}
.im-btn:hover{background:rgba(255,255,255,.2);transform:scale(1.05);box-shadow:0 0 14px rgba(255,255,255,.35),0 0 28px rgba(255,255,255,.15)}
.im-btn svg{width:22px;height:22px}
.im-playbtn{width:64px;height:64px;border:1px solid rgba(255,255,255,.4);background:rgba(255,255,255,.08);backdrop-filter:blur(8px)saturate(200%);box-shadow:0 0 12px rgba(255,255,255,.25),0 0 24px rgba(255,255,255,.12);color:#1a1a2e}
.im-playbtn:hover{background:rgba(255,255,255,.12)}
.im-vol-wrap{position:relative;display:inline-flex}
.im-vol-wrap.pc-only{display:inline-flex}
.im-vol-wrap::before{content:'';position:absolute;bottom:100%;left:-10px;right:-10px;height:10px}
.im-vol-slider{position:absolute;bottom:100%;left:50%;transform:translateX(-50%);padding:8px 4px;background:rgba(255,255,255,.85);backdrop-filter:blur(16px)saturate(200%);border:1px solid rgba(255,255,255,.7);border-radius:10px;box-shadow:0 4px 16px rgba(0,0,0,.1);margin-bottom:0;opacity:0;visibility:hidden;transition:opacity .15s,visibility .15s;pointer-events:none}
.im-vol-wrap:hover .im-vol-slider,.im-vol-slider:hover{opacity:1;visibility:visible;pointer-events:auto}
.im-vol-slider input[type=range]{writing-mode:vertical-lr;direction:rtl;height:80px;width:5px;-webkit-appearance:none;appearance:none;background:rgba(0,0,0,.08);border-radius:3px;outline:none;cursor:pointer}
.im-vol-slider input::-webkit-slider-thumb{-webkit-appearance:none;width:16px;height:16px;border-radius:50%;background:rgba(0,0,0,.35);cursor:pointer;border:2px solid rgba(255,255,255,.8);box-shadow:0 1px 4px rgba(0,0,0,.12);transition:transform .15s}
.im-vol-slider input::-webkit-slider-thumb:hover{transform:scale(1.2)}
.im-lrc{width:100%;height:150px;overflow:hidden;position:relative;text-align:center;margin:4px 0 2px;flex-shrink:0}
.im-lrc-inner{transition:transform .4s cubic-bezier(.4,0,.2,1)}
.im-lrc-line{padding:3px 0;font-size:14px;font-weight:600;line-height:1.6;color:rgba(0,0,0,.2);transition:color .25s,font-size .25s;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.im-lrc-line.active{color:#1a1a2e;font-size:16px;font-weight:700}
.im-lrc-line.prev{color:rgba(0,0,0,.35)}
.panel-action-btn{position:absolute;top:12px;right:12px;width:30px;height:30px;border-radius:8px;border:1px solid rgba(0,0,0,.08);background:rgba(255,255,255,.25);backdrop-filter:blur(8px)saturate(200%);cursor:pointer;display:flex;align-items:center;justify-content:center;color:#666;transition:all .2s;padding:0;z-index:1}
.panel-action-btn:hover{background:rgba(255,255,255,.35);color:#1a1a2e}
@media(max-width:768px){.im-cover-wrap{width:200px;height:200px}.im-title{font-size:20px}.im-artist{font-size:14px}.im-content{gap:18px}.im-lrc{height:120px}.im-lrc-line{font-size:13px}.im-lrc-line.active{font-size:15px}.im-btn{width:42px;height:42px}.im-btn svg{width:18px;height:18px}.im-playbtn{width:56px;height:56px}.pc-only{display:none!important}.im-slist-dropdown{position:fixed;top:auto;bottom:0;left:0;right:0;margin:0;min-width:auto;max-height:50vh;width:100%;border-radius:16px 16px 0 0;z-index:2147483647;transform:translateY(100%);padding:8px 0 20px;border-bottom:none;box-shadow:0 -4px 24px rgba(0,0,0,.12)}.im-slist-dropdown.open{transform:translateY(0)}}
/* ── PC immersive mode ── */
@media(min-width:1025px){
  .im-content{max-width:620px;gap:28px}
  .im-cover-wrap{width:320px;height:320px;border-radius:24px}
  .im-title{font-size:28px}
  .im-artist{font-size:18px;margin-top:6px}
  .im-lrc{height:160px}
  .im-lrc-line{font-size:15px;padding:4px 0}
  .im-lrc-line.active{font-size:17px}
  .im-btn{width:52px;height:52px}.im-btn svg{width:24px;height:24px}
  .im-playbtn{width:72px;height:72px}
  .im-controls{gap:20px}
  .im-close-btn{width:42px;height:42px;border-radius:10px}
  .im-close-btn svg{width:22px;height:22px}
}
/* ── Dark theme (auto by Beijing time, 18:00-6:00) ── */
[data-mp="root"].dark [data-mp="toggle"]{background:rgba(65,65,78,.55);border-color:rgba(255,255,255,.08);color:#d0d0d8;box-shadow:0 2px 10px rgba(0,0,0,.2)}
[data-mp="root"].dark [data-mp="toggle"]:hover{box-shadow:0 4px 16px rgba(0,0,0,.3)}

[data-mp="root"].dark [data-mp="toggle"]:hover{box-shadow:0 4px 16px rgba(0,0,0,.3)}
[data-mp="root"].dark [data-mp="panel"]{background:rgba(55,55,68,.55);border-color:rgba(255,255,255,.06);box-shadow:0 8px 32px rgba(0,0,0,.2)}
[data-mp="root"].dark .title{color:#d8d8e0}
[data-mp="root"].dark .artist{color:#999}
[data-mp="root"].dark .pbar{background:rgba(255,255,255,.08)}
[data-mp="root"].dark .played{background:rgba(255,255,255,.28)}
[data-mp="root"].dark .ptime{color:#aaa}
[data-mp="root"].dark .cbtn{border-color:rgba(255,255,255,.06);background:rgba(255,255,255,.06);color:#c0c0c8;box-shadow:none}
[data-mp="root"].dark .cbtn:hover{background:rgba(255,255,255,.12);transform:scale(1.05)}
[data-mp="root"].dark .playbtn{border-color:rgba(255,255,255,.08);background:rgba(255,255,255,.08);box-shadow:none}
[data-mp="root"].dark .mvol svg{color:#999}
[data-mp="root"].dark .mvol input[type=range]{background:rgba(255,255,255,.06)}
[data-mp="root"].dark .mvol input::-webkit-slider-thumb{background:rgba(255,255,255,.45);border:2px solid rgba(255,255,255,.2);box-shadow:0 1px 4px rgba(0,0,0,.25)}
[data-mp="root"].dark .mvol input::-webkit-slider-thumb:hover{transform:scale(1.2)}
[data-mp="root"].dark .mvol .b-btn{border-color:rgba(255,255,255,.08);color:#888;padding:3px 0}
[data-mp="root"].dark .mvol .b-btn:hover{border-color:rgba(255,255,255,.2);color:#ddd}
[data-mp="root"].dark .mvol .b-btn.on{background:rgba(255,200,50,.15);border-color:rgba(255,180,0,.3);color:#e8a800}
[data-mp="root"].dark .mode-trigger{color:#c0c0c8;border-color:rgba(255,255,255,.06);background:rgba(255,255,255,.08)}
[data-mp="root"].dark .mode-trigger::after{color:#777}
[data-mp="root"].dark .mode-menu{background:rgba(55,55,68,.75);border-color:rgba(255,255,255,.06)}
[data-mp="root"].dark .mode-option{color:#aaa}
[data-mp="root"].dark .mode-option:hover{background:rgba(255,255,255,.06)}
[data-mp="root"].dark .mode-option.active{background:rgba(255,255,255,.1);color:#d8d8e0}
[data-mp="root"].dark .lrc-label{color:#888}
[data-mp="root"].dark .lrc-toggle{border-color:rgba(255,255,255,.06);background:rgba(255,255,255,.08)}
[data-mp="root"].dark .lrc-toggle.active{background:rgba(255,255,255,.2);border-color:rgba(255,255,255,.1)}
[data-mp="root"].dark .lrc-toggle::after{background:rgba(255,255,255,.5)}
[data-mp="root"].dark .songlist{border-top-color:rgba(255,255,255,.04)}
[data-mp="root"].dark .songitem{color:#999}
[data-mp="root"].dark .songitem:hover{background:rgba(255,255,255,.06)}
[data-mp="root"].dark .songitem.active{background:rgba(255,255,255,.1)}
[data-mp="root"].dark .songitem .si-name{color:#bbb}
[data-mp="root"].dark .songitem.active .si-name{color:#d8d8e0}
[data-mp="root"].dark .songitem .si-artist{color:#aaa}
[data-mp="root"].dark .songitem .si-idx{color:#888}
[data-mp="root"].dark .panel-action-btn{border-color:rgba(255,255,255,.06);background:rgba(255,255,255,.08);color:#888}
[data-mp="root"].dark .panel-action-btn:hover{background:rgba(255,255,255,.12);color:#c0c0c8}
[data-mp="root"].dark .im-mode-btn{color:#d0d0d8}
[data-mp="root"].dark .im-mode-btn:hover{color:#fff}
[data-mp="root"].dark [data-mp="immersiveOverlay"]{background:rgba(45,45,55,.75);border-color:rgba(255,255,255,.04)}
[data-mp="root"].dark .im-close-btn{border-color:rgba(255,255,255,.06);background:rgba(255,255,255,.08);color:#888;box-shadow:none}
[data-mp="root"].dark .im-close-btn:hover{background:rgba(255,255,255,.12);color:#c0c0c8}
[data-mp="root"].dark .im-cover-wrap{box-shadow:0 12px 36px rgba(0,0,0,.25)}
[data-mp="root"].dark .im-title{color:#d8d8e0}
[data-mp="root"].dark .im-artist{color:#999}
[data-mp="root"].dark .im-progress-track{background:rgba(255,255,255,.08)}
[data-mp="root"].dark .im-progress-fill{background:rgba(255,255,255,.28)}
[data-mp="root"].dark .im-time{color:#aaa}
[data-mp="root"].dark .im-btn{border-color:rgba(255,255,255,.06);background:rgba(255,255,255,.06);color:#c0c0c8;box-shadow:none}
[data-mp="root"].dark .im-btn:hover{background:rgba(255,255,255,.12);transform:scale(1.05)}
[data-mp="root"].dark .im-playbtn{border-color:rgba(255,255,255,.08);background:rgba(255,255,255,.08);color:#d0d0d8;box-shadow:none}
[data-mp="root"].dark .im-playbtn:hover{background:rgba(255,255,255,.12)}
[data-mp="root"].dark .im-mode-btn{color:#d0d0d8}
[data-mp="root"].dark .im-mode-btn:hover{color:#fff}
[data-mp="root"].dark .im-bottom-icon-btn{color:#d0d0d8}
[data-mp="root"].dark .im-bottom-icon-btn:hover{color:#fff}
[data-mp="root"].dark .im-vol-slider{background:rgba(55,55,68,.75);border-color:rgba(255,255,255,.06)}
[data-mp="root"].dark .im-vol-slider input[type=range]{background:rgba(255,255,255,.06)}
[data-mp="root"].dark .im-vol-slider input::-webkit-slider-thumb{background:rgba(255,255,255,.45);border:2px solid rgba(255,255,255,.2);box-shadow:0 1px 4px rgba(0,0,0,.25)}
[data-mp="root"].dark .im-vol-slider input::-webkit-slider-thumb:hover{transform:scale(1.2)}
[data-mp="root"].dark .im-slist-dropdown{background:rgba(55,55,68,.75);border-color:rgba(255,255,255,.06)}
[data-mp="root"].dark .im-slist-dropdown .songitem{color:#aaa}
[data-mp="root"].dark .im-slist-dropdown .songitem:hover{background:rgba(255,255,255,.06)}
[data-mp="root"].dark .im-slist-dropdown .songitem.active{background:rgba(255,255,255,.1);color:#d8d8e0}
[data-mp="root"].dark .im-lrc-line{color:rgba(255,255,255,.12)}
[data-mp="root"].dark .im-lrc-line.active{color:#d0d0d8}
[data-mp="root"].dark .im-lrc-line.prev{color:rgba(255,255,255,.22)}
[data-mp="root"].dark [data-mp="lrc"]{color:#d0d0d8 !important;background:rgba(55,55,68,.65) !important;border-color:rgba(255,255,255,.06) !important}
`;
    function loadCSS(cb) { cb(); }

    function createWidget() {
        var host = document.createElement('div');
        host.id = 'mapi-player-' + Math.random().toString(36).slice(2, 8);
        var mr = window.innerWidth <= 768 ? '4px' : '15px';
        var mb = window.innerWidth <= 768 ? '35px' : '50px';
        var ps = 'right:' + mr;
        host.style.cssText = 'all:initial;position:fixed;bottom:' + mb + ';' + ps + ';z-index:2147483647;pointer-events:none';
        document.body.appendChild(host);
        host.addEventListener('contextmenu',function(e){e.preventDefault();});
        var shadow = host.attachShadow({mode:'closed'});
        shadow.innerHTML = '<style>' + _MP_CSS + '</style><div data-mp="root">' + getHTML() + '</div>';
        var sr = shadow.querySelector('[data-mp="root"]');
        return shadow;
    }

// ═══ HTML 模板 ═══
    function getHTML() { return `
<div data-mp="overlay" style="position:fixed;inset:0;z-index:-1;display:none;pointer-events:auto"><\/div><div data-mp="panel">
  <div class="info">
    <div class="cover" data-mp="cv"><img src="" alt=""></div>
    <div class="mtext">
      <div class="title mw" data-mp="ttl"><span class="mi">点击播放</span></div>
      <div class="artist mw" data-mp="art"><span class="mi">加载中...</span></div>
    </div>
  </div>
  <button class="panel-action-btn" data-mp="immersiveBtn" title="沉浸式播放"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8 3H5a2 2 0 0 0-2 2v3"/><path d="M21 8V5a2 2 0 0 0-2-2h-3"/><path d="M16 21h3a2 2 0 0 0 2-2v-3"/><path d="M3 16v3a2 2 0 0 0 2 2h3"/></svg></button>
  <div class="progress">
    <div class="pbar" data-mp="pbar"><div class="played" data-mp="played"></div></div>
    <div class="ptime"><span data-mp="cur">00:00</span><span data-mp="dur">00:00</span></div>
  </div>
  <div class="controls">
    <button class="cbtn" data-mp="prevBtn"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="19 20 9 12 19 4 19 20"/><line x1="5" y1="19" x2="5" y2="5"/></svg></button>
    <button class="cbtn playbtn" data-mp="playBtn"><svg data-mp="playSvg" viewBox="0 0 24 24" fill="currentColor"><polygon points="6,4 20,12 6,20"/></svg></button>
    <button class="cbtn" data-mp="nextBtn"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="5 4 15 12 5 20 5 4"/><line x1="19" y1="5" x2="19" y2="19"/></svg></button>
  </div>
  <div class="bottom-row" data-mp="bottomRow">
    <div class="mode-dropdown" data-mp="modeDropdown">
      <button class="mode-trigger" data-mp="modeTrigger">列表播放</button>
      <div class="mode-menu" data-mp="modeMenu">
        <div class="mode-option active" data-mode="list">列表播放</div>
        <div class="mode-option" data-mode="single">单曲循环</div>
        <div class="mode-option" data-mode="random">随机播放</div>
      </div>
    </div>

    <div class="lrc-area">
      <span class="lrc-label">歌词</span>
      <button class="lrc-toggle active" data-mp="lrcToggle"></button>
    </div>
  </div>
  <div class="mvol">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"/><path d="M15.54 8.46a5 5 0 0 1 0 7.07"/></svg>
    <input type="range" data-mp="vol" min="0" max="1" step="0.05" value="1">
    <button class="b-btn" data-mp="boost">增强</button>
  </div>
  <div class="songlist" data-mp="slist"><button class="pl-back" data-mp="plBack"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>返回歌单列表</button><div data-mp="slistInner"></div></div>
</div>
<button data-mp="toggle"><svg data-mp="toggleSvg" width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18V5l12-2v13"/><circle cx="6" cy="18" r="3"/><circle cx="18" cy="16" r="3"/></svg><img data-mp="toggleCover" src="" alt="" style="display:none;position:absolute;top:3px;left:3px;width:calc(100% - 6px);height:calc(100% - 6px);border-radius:50%;object-fit:cover;box-sizing:border-box"><span data-mp="toggleLoading" style="position:absolute;inset:2px;border-radius:50%;border:2px solid rgba(255,255,255,.3);border-top-color:rgba(0,0,0,.5);opacity:0;transition:opacity .3s"></span></button>
<div data-mp="apContainer" style="display:none"></div>
<div data-mp="immersiveOverlay">
  <div class="im-close-area"><button class="im-close-btn" data-mp="imClose"><svg viewBox="0 0 1024 1024" width="18" height="18" fill="currentColor"><path d="M384 128h-85.33v170.67H128V384h256zM896 384v-85.33H725.33V128H640v256zM725.33 725.33H896V640H640v256h85.33zM298.67 896H384V640H128v85.33h170.67z"/></svg></button></div>
  <div class="im-content" data-mp="imContent">
    <div class="im-cover-wrap"><img class="im-cover" data-mp="imCover" src="" alt=""></div>
    <div class="im-info">
      <div class="im-title" data-mp="imTitle">点击播放</div>
      <div class="im-artist" data-mp="imArtist">加载中...</div>
    </div>
    <div class="im-lrc"><div class="im-lrc-inner" data-mp="imLrc"></div></div>
    <div class="im-bottom-row">
      <div style="display:flex;align-items:center;gap:4px">
        <div style="position:relative"><button class="im-bottom-icon-btn" data-mp="imSlistTrigger"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg></button><div class="im-slist-dropdown" data-mp="imSlist"><button class="pl-back" data-mp="imPlBack" style="margin:0 8px"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>返回歌单列表</button><div data-mp="imSlistInner"></div></div></div>
        <div class="im-vol-wrap pc-only"><button class="im-bottom-icon-btn"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"/><path d="M15.54 8.46a5 5 0 0 1 0 7.07"/></svg></button><div class="im-vol-slider"><input type="range" data-mp="imVol" min="0" max="1" step="0.05" value="1"></div></div>
      </div>
      <button class="im-mode-btn" data-mp="imModeBtn"><svg data-mp="imModeSvg" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="17 1 21 5 17 9"/><path d="M3 11V9a4 4 0 0 1 4-4h14"/><polyline points="7 23 3 19 7 15"/><path d="M21 13v2a4 4 0 0 1-4 4H3"/></svg></button>
    </div>
    <div class="im-progress-wrap" data-mp="imPbar">
      <div class="im-progress-track"><div class="im-progress-fill" data-mp="imPfill"></div></div>
      <div class="im-time"><span data-mp="imCur">00:00</span><span data-mp="imDur">00:00</span></div>
    </div>
    <div class="im-controls">
      <button class="im-btn" data-mp="imPrev"><svg viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="currentColor" stroke-width="2"><polygon points="19 20 9 12 19 4 19 20"/><line x1="5" y1="19" x2="5" y2="5"/></svg></button>
      <button class="im-btn im-playbtn" data-mp="imPlay"><svg data-mp="imPlaySvg" viewBox="0 0 24 24" width="34" height="34" fill="currentColor"><polygon points="6,4 20,12 6,20"/></svg></button>
      <button class="im-btn" data-mp="imNext"><svg viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="currentColor" stroke-width="2"><polygon points="5 4 15 12 5 20 5 4"/><line x1="19" y1="5" x2="19" y2="19"/></svg></button>
    </div>
  </div>
`; }

// ═══ 播放器逻辑 ═══
    var MP = {
        ap: null, songs: [], playlists: [], currentPlaylistIndex: 0, mode: 'list', open: false, lrcLines: [], _side: 'right', _showLrc: true, _lrcAnimating: false, _retracted: false, _retractTimer: null, _imOpen: false, _autoTheme: true, _themeMode: 'light', _autoplayDefault: false, _autoplayTried: false, _server: 'netease', _loading: false,

        showToggle: function() {
            var el = this.$('toggle');
            if (el) { el.style.display = 'flex'; this._updateToggleTransform(); }
        },

        _updateToggleTransform: function() {
            var tog = this.$('toggle');
            if (!tog) return;
            if (!this._retracted) { tog.style.transform = ''; return; }
            var dir = this._side === 'left' ? -1 : 1;
            tog.style.transform = 'translateX(' + (dir * 70) + '%)';
        },

        _scheduleAutoHide: function() {
            var self = this;
            clearTimeout(this._retractTimer);
            this._retracted = false;
            this._updateToggleTransform();
            this._retractTimer = setTimeout(function() {
                self._retracted = true;
                self._updateToggleTransform();
            }, 3000);
        },

        _cancelAutoHide: function() {
            clearTimeout(this._retractTimer);
            this._retracted = false;
            var tog = this.$('toggle');
            if (tog) tog.style.transform = '';
        },

        applyLrcDefault: function(val) {
            if (val === 0) {
                this._showLrc = false;
                var btn = this.$('lrcToggle');
                if (btn) btn.classList.remove('active');
                var lrcEl = document.querySelector('[data-mp="lrc"]');
                if (lrcEl) {
                    lrcEl.style.overflow = 'hidden';
                    lrcEl.style.width = '0';
                    lrcEl.style.padding = '6px 0';
                    lrcEl.style.opacity = '0';
                }
            }
        },

        _showNoPlaylistNotice: function() {
            var el = document.createElement('div');
            el.textContent = '请在后台配置歌单';
            el.style.cssText = 'position:fixed;top:50px;left:50%;transform:translateX(-50%);z-index:2147483647;padding:8px 14px;border-radius:6px;font-size:12px;background:rgba(255,193,7,.12);color:#f39c12;border:1px solid rgba(255,193,7,.25);backdrop-filter:blur(12px);pointer-events:none;white-space:nowrap';
            document.body.appendChild(el);
            setTimeout(function(){el.style.transition='opacity .4s';el.style.opacity='0';setTimeout(function(){el.remove()},400)},5000);
        },

        fetchConfig: function() {
            var self = this;
            // Check window-level live override first (set by page terminal UI)
            if (window.__mszeph_config && window.__mszeph_config.auto_theme !== undefined) {
                self._autoTheme = window.__mszeph_config.auto_theme;
                if (window.__mszeph_config.theme_mode !== undefined) {
                    self._themeMode = window.__mszeph_config.theme_mode;
                }
                self.applyLrcDefault(window.__mszeph_config.lyrics_default);
                if (window.__mszeph_config.autoplay_default !== undefined) {
                    self._autoplayDefault = window.__mszeph_config.autoplay_default;
                }
                if (window.__mszeph_config.server !== undefined) {
                    self._server = window.__mszeph_config.server;
                }
                self.checkTheme();
                var pls = (window.__mszeph_config.playlists || []).map(function(p){ p.type = p.type || 'playlist'; return p; });
                if (pls && pls.length) { self.playlists = pls; self.fetchAllPlaylists(); }
                else { self._showNoPlaylistNotice(); }
                return;
            }
            // Per-key config from backend
            if (API_KEY || API_TOKEN) {
                var xhr = new XMLHttpRequest();
                xhr.open('GET', API_BASE + '?action=get-config&token=' + encodeURIComponent(API_TOKEN || API_KEY), true);
                xhr.onload = function() {
                    try {
                        var data = JSON.parse(xhr.responseText);
                        if (data.ok && data.config) {
                            if (data.config.auto_theme !== undefined) {
                                self._autoTheme = data.config.auto_theme;
                            }
                            if (data.config.theme_mode !== undefined) {
                                self._themeMode = data.config.theme_mode;
                            }
                            self.applyLrcDefault(data.config.lyrics_default);
                            if (data.config.autoplay_default !== undefined) {
                                self._autoplayDefault = data.config.autoplay_default;
                            }
                            if (data.config.server !== undefined) {
                                self._server = data.config.server;
                            }
                            self.checkTheme();
                            var pls = (data.config.playlists || []).map(function(p){ p.type = p.type || 'playlist'; return p; });
                            if (pls && pls.length) { self.playlists = pls; self.fetchAllPlaylists(); }
                            else { self._showNoPlaylistNotice(); }
                        }
                    } catch(e) {}
                };
                xhr.send();
            } else {
                // No key — nothing to configure
            }
        },

        // ─── 获取公告 ───
        fetchAnnouncement: function() {
            var self = this;
            if (!API_KEY && !API_TOKEN) return;
            var seenAt = localStorage.getItem('mapi_announcement_seen');
            fetch(API_BASE + '?action=get-announcement&token=' + encodeURIComponent(API_TOKEN || API_KEY), {credentials:'same-origin'})
                .then(function(r){return r.json()})
                .then(function(data){
                    if (data && data.enabled && data.content) {
                        if (seenAt && parseInt(seenAt) >= (data.updated_at || 0)) return;
                        self._announcement = data;
                        self.showAnnouncement(data);
                    }
                })
                .catch(function(){});
        },

        showAnnouncement: function(data) {
            if (document.querySelector('[data-mp="annWrap"]')) return;
            var wrap = document.createElement('div');
            wrap.setAttribute('data-mp', 'annWrap');
            wrap.style.cssText = 'position:fixed;inset:0;z-index:2147483647;display:flex;align-items:center;justify-content:center;background:rgba(0,0,0,.35);opacity:0;visibility:hidden;transition:opacity .3s,visibility .3s;font-family:-apple-system,"PingFang SC","Microsoft YaHei",sans-serif';
            var isDark = this.$('root') && this.$('root').classList.contains('dark');
            var boxBg = isDark ? 'rgba(55,55,68,.65)' : 'rgba(255,255,255,.5)';
            var boxBor = isDark ? 'rgba(255,255,255,.06)' : 'rgba(255,255,255,.7)';
            var tCol = isDark ? '#d8d8d0' : '#1a1a2e';
            var bCol = isDark ? '#aaa' : '#444';
            var dBor = isDark ? 'rgba(255,255,255,.06)' : 'rgba(0,0,0,.08)';
            var w = window.innerWidth <= 768 ? 280 : 400;
            wrap.innerHTML = '<div class="mapi-ann-box" onclick="event.stopPropagation()" style="background:' + boxBg + ';backdrop-filter:blur(24px)saturate(200%);border:1px solid ' + boxBor + ';border-radius:16px;box-shadow:0 8px 32px rgba(0,0,0,.12);width:' + w + 'px;max-height:400px;display:flex;flex-direction:column;pointer-events:auto">'
                + '<div class="mapi-ann-divider" style="padding:24px 16px 16px;text-align:center;border-bottom:1px solid ' + dBor + '"><span class="mapi-ann-title" style="font-size:16px;font-weight:700;color:' + tCol + '">' + escapeHtml(data.title || '系统公告') + '</span></div>'
                + '<div class="mapi-ann-content" style="flex:1;overflow-y:auto;scrollbar-width:none;-ms-overflow-style:none;padding:16px;font-size:14px;line-height:1.8;color:' + bCol + ';white-space:pre-wrap;text-align:' + (data.align === 'center' ? 'center' : 'left') + '">' + escapeHtml(data.content) + '</div>'
                + '<div class="mapi-ann-divider" style="border-top:1px solid ' + dBor + ';padding:16px 16px 24px;text-align:center">'
                + '<button id="mapi-ann-ok" class="mapi-ann-btn" style="width:100px;height:32px;border-radius:8px;border:1px solid ' + boxBor + ';background:rgba(255,255,255,.2);backdrop-filter:blur(8px)saturate(200%);cursor:pointer;font-size:13px;font-weight:600;color:' + tCol + ';padding:0;outline:none">知道了</button></div>'
                + '</div>';
            document.body.appendChild(wrap);
            // Scroll lock
            var lockStyle = document.createElement('style');
            lockStyle.id = 'mapi-ann-scroll-lock';
            lockStyle.textContent = 'html,body{overflow:hidden!important}';
            document.head.appendChild(lockStyle);
            void wrap.offsetWidth;
            wrap.style.opacity = '1';
            wrap.style.visibility = 'visible';
            wrap.addEventListener('click', function() {
                wrap.style.opacity = '0';
                wrap.style.visibility = 'hidden';
                setTimeout(function(){
                    if (wrap.parentNode) wrap.parentNode.removeChild(wrap);
                    var ls = document.getElementById('mapi-ann-scroll-lock');
                    if (ls) ls.remove();
                }, 400);
            });
            document.getElementById('mapi-ann-ok').addEventListener('click', function() {
                localStorage.setItem('mapi_announcement_seen', String(data.updated_at || Date.now()));
                wrap.style.opacity = '0';
                wrap.style.visibility = 'hidden';
                setTimeout(function(){
                    if (wrap.parentNode) wrap.parentNode.removeChild(wrap);
                    var ls = document.getElementById('mapi-ann-scroll-lock');
                    if (ls) ls.remove();
                }, 400);
            });
        },

        _initAntiDebug: function() {
            var self = this;
            var _detected = false;
            var _suspectCount = 0;
            var _ban = function() {
                if (_detected) return;
                _detected = true;
                if (self.ap && self.ap.audio) {
                    try { self.ap.audio.pause(); } catch(e) {}
                }
                document.body.innerHTML = '';
                var style = document.createElement('style');
                style.textContent = 'html,body{background:#000!important;margin:0!important;padding:0!important;overflow:hidden!important}';
                document.head.appendChild(style);
                window.location.replace('about:blank');
            };

            self._antiDbInterval = setInterval(function() {
                var t = Date.now();
                debugger;
                if (Date.now() - t > 100) {
                    _suspectCount++;
                    if (_suspectCount >= 2) _ban();
                } else {
                    _suspectCount = Math.max(0, _suspectCount - 1);
                }
            }, 500);
        },

        destroy: function() {
            var self = this;
            if (self.ap) {
                try { self.ap.destroy(); } catch(e) {}
                self.ap = null;
            }
            if (self._themeInterval) { clearInterval(self._themeInterval); self._themeInterval = null; }
            if (self._antiDbInterval) { clearInterval(self._antiDbInterval); self._antiDbInterval = null; }
            if (self._retractTimer) { clearTimeout(self._retractTimer); self._retractTimer = null; }
            if (self._domTimer) { clearTimeout(self._domTimer); self._domTimer = null; }
            if (self._domObs) { try { self._domObs.disconnect(); } catch(e) {} self._domObs = null; }
            if (self._resizeLrc) { window.removeEventListener('resize', self._resizeLrc); self._resizeLrc = null; }
            if (self._snapTmr) { clearTimeout(self._snapTmr); self._snapTmr = null; }
            if (self._autoCalibrateTmr) { clearTimeout(self._autoCalibrateTmr); self._autoCalibrateTmr = null; }
            if (self._lrcAnimTmr) { clearTimeout(self._lrcAnimTmr); self._lrcAnimTmr = null; }
            var lrcEl = document.querySelector('[data-mp="lrc"]');
            if (lrcEl) lrcEl.remove();
            var hostEl = hostRoot ? hostRoot.host : null;
            if (hostEl && hostEl.parentNode) hostEl.parentNode.removeChild(hostEl);
            self._destroyed = true;
        },

        init: function() {
            var self = this;
            self.fetchConfig();
            self._themeInterval = setInterval(function(){ self.checkTheme(); }, 60000);
            self.checkGreeting();
            self.fetchAnnouncement();
            // Listen for live config updates from page
            window.addEventListener('message', function(e) {
                if (e.data && e.data.type === 'mszeph-config-update' && e.data.config) {
                    var updated = false;
                    if (e.data.config.auto_theme !== undefined) {
                        self._autoTheme = e.data.config.auto_theme;
                        updated = true;
                    }
                    if (e.data.config.theme_mode !== undefined) {
                        self._themeMode = e.data.config.theme_mode;
                        updated = true;
                    }
                    if (e.data.config.lyrics_default !== undefined) {
                        self.applyLrcDefault(e.data.config.lyrics_default);
                    }
                    if (e.data.config.autoplay_default !== undefined) {
                        self._autoplayDefault = e.data.config.autoplay_default;
                    }
                    if (updated) self.checkTheme();
                }
            });
        },

        checkTheme: function() {
            var root = this.$('root');
            if (!root) return;
            if (this._autoTheme) {
                // Beijing time (UTC+8): dark theme 18:00-6:00
                var d = new Date();
                var utcH = d.getUTCHours();
                var bjH = (utcH + 8) % 24;
                if (bjH >= 18 || bjH < 6) {
                    root.classList.add('dark');
                } else {
                    root.classList.remove('dark');
                }
            } else {
                // Manual theme
                if (this._themeMode === 'dark') {
                    root.classList.add('dark');
                } else {
                    root.classList.remove('dark');
                }
            }
            // Sync lyrics bar outside Shadow DOM
            var lrcEl = document.querySelector('[data-mp="lrc"]');
            if (lrcEl) {
                var isDark = root.classList.contains('dark');
                if (isDark) {
                    lrcEl.style.color = '#d0d0d8';
                    lrcEl.style.background = 'rgba(55,55,68,.65)';
                    lrcEl.style.borderColor = 'rgba(255,255,255,.06)';
                } else {
                    lrcEl.style.color = '#1a1a2e';
                    lrcEl.style.background = 'rgba(255,255,255,.3)';
                    lrcEl.style.borderColor = 'rgba(255,255,255,.5)';
                }
            }
            // Sync announcement popup outside Shadow DOM
            var annWrap = document.querySelector('[data-mp="annWrap"]');
            if (annWrap) {
                var isDark = root.classList.contains('dark');
                var box = annWrap.querySelector('.mapi-ann-box');
                var title = annWrap.querySelector('.mapi-ann-title');
                var content = annWrap.querySelector('.mapi-ann-content');
                var btn = annWrap.querySelector('.mapi-ann-btn');
                var dividers = annWrap.querySelectorAll('.mapi-ann-divider');
                if (isDark) {
                    document.documentElement.classList.add('mapi-dark');
                    if (box) { box.style.background = 'rgba(55,55,68,.65)'; box.style.borderColor = 'rgba(255,255,255,.06)'; }
                    if (title) title.style.color = '#d8d8d0';
                    if (content) content.style.color = '#aaa';
                    if (btn) { btn.style.borderColor = 'rgba(255,255,255,.06)'; btn.style.color = '#d8d8d0'; }
                    dividers.forEach(function(d){ d.style.borderColor = 'rgba(255,255,255,.06)'; });
                } else {
                    document.documentElement.classList.remove('mapi-dark');
                    if (box) { box.style.background = 'rgba(255,255,255,.5)'; box.style.borderColor = 'rgba(255,255,255,.7)'; }
                    if (title) title.style.color = '#1a1a2e';
                    if (content) content.style.color = '#444';
                    if (btn) { btn.style.borderColor = 'rgba(255,255,255,.7)'; btn.style.color = '#1a1a2e'; }
                    dividers.forEach(function(d){ d.style.borderColor = 'rgba(0,0,0,.08)'; });
                }
            } else {
                // Sync document class even when popup not shown
                var isDark = root.classList.contains('dark');
                if (isDark) {
                    document.documentElement.classList.add('mapi-dark');
                } else {
                    document.documentElement.classList.remove('mapi-dark');
                }
            }
        },
        checkGreeting: function() {
            if (!this._cookieConsented) return;
            var d = new Date();
            var bjH = (d.getUTCHours() + 8) % 24;
            var period, text;
            if (bjH < 6) { period='night'; text='🌙 夜深了，注意休息'; }
            else if (bjH < 12) { period='morning'; text='☀️ 早上好'; }
            else if (bjH < 18) { period='afternoon'; text='🌤 下午好'; }
            else { period='evening'; text='🌆 晚上好'; }
            var shown = getCookie('mapi_greeting');
            if (shown === period) return;
            this._showGreetingToast(text, period);
        },

        _showGreetingToast: function(text, period) {
            var d = new Date();
            var bjH = (d.getUTCHours() + 8) % 24;
            var isDark = bjH >= 18 || bjH < 6;
            var bg = isDark ? 'rgba(45,45,55,.72)' : 'rgba(255,255,255,.55)';
            var bor = isDark ? 'rgba(255,255,255,.06)' : 'rgba(255,255,255,.75)';
            var col = isDark ? '#d0d0d8' : '#1a1a2e';
            var shd = isDark ? '0 8px 32px rgba(0,0,0,.25)' : '0 8px 32px rgba(0,0,0,.1)';
            var el = document.createElement('div');
            el.style.cssText = 'position:fixed;top:24px;left:50%;transform:translateX(-50%) scale(0.3);z-index:2147483647;background:' + bg + ';backdrop-filter:blur(24px)saturate(200%);border:1px solid ' + bor + ';border-radius:14px;padding:10px 28px;font-size:16px;font-weight:700;color:' + col + ';font-family:-apple-system,"PingFang SC","Microsoft YaHei",sans-serif;box-shadow:' + shd + ';opacity:0;transition:all .5s cubic-bezier(.34,1.56,.64,1);pointer-events:none;white-space:nowrap;max-width:90vw;text-align:center';
            el.textContent = text;
            document.body.appendChild(el);
            void el.offsetWidth;
            el.style.opacity = '1';
            el.style.transform = 'translateX(-50%) scale(1)';
            setTimeout(function(){
                el.style.opacity = '0';
                el.style.transform = 'translateX(-50%) scale(0.6)';
                setTimeout(function(){ if (el.parentNode) el.parentNode.removeChild(el); }, 500);
            }, 3000);
            setCookie('mapi_greeting', period);
        },

        fetchAllPlaylists: function() {
            var self = this;
            if (!self.playlists || self.playlists.length === 0) {
                self._showNoPlaylistNotice();
                return;
            }
            self._loading = true;
            self.$('ttl').textContent = '加载中...';
            var promises = [];
            self._allSongs = {};
            self._playlistCovers = {};
            for (var i = 0; i < self.playlists.length; i++) {
                (function(idx){
                    var pl = self.playlists[idx];
                    var plType = pl.type || 'playlist';
                    if (plType === 'custom') {
                        if (pl.songs && pl.songs.length) {
                            var songPromises = pl.songs.map(function(s) {
                                var songUrl = API_BASE + '?action=song&id=' + encodeURIComponent(s.id) + '&server=' + encodeURIComponent(s.server || 'netease') + '&token=' + encodeURIComponent(API_TOKEN);
                                return fetch(songUrl, {credentials:'same-origin'}).then(function(r){return r.json()}).then(function(data){
                                    if (Array.isArray(data) && data.length > 0) return data[0];
                                    return null;
                                }).catch(function(){ return null; });
                            });
                            promises.push(
                                Promise.all(songPromises).then(function(results){
                                    var valid = results.filter(function(r){ return r && r.url; });
                                    valid.forEach(function(s){
                                        if (s.pic && !/^https?:\/\//.test(s.pic)) s.pic = API_BASE + s.pic + '&token=' + encodeURIComponent(API_TOKEN);
                                    });
                                    self._allSongs[idx] = valid;
                                    if (pl.cover_url) {
                                        self._playlistCovers[idx] = pl.cover_url;
                                    } else if (valid.length > 0 && valid[0].pic) {
                                        self._playlistCovers[idx] = valid[0].pic;
                                    }
                                })
                            );
                        } else {
                            self._allSongs[idx] = [];
                            if (pl.cover_url) self._playlistCovers[idx] = pl.cover_url;
                            promises.push(Promise.resolve());
                        }
                        return;
                    }
                    if (!pl.id) { self._allSongs[idx] = []; promises.push(Promise.resolve()); return; }
                    var action = plType === 'song' ? 'song' : 'playlist';
                    var url = API_BASE + '?action=' + action + '&id=' + encodeURIComponent(pl.id) + '&limit=30&server=' + encodeURIComponent(pl.server || 'netease') + '&token=' + encodeURIComponent(API_TOKEN);
                    promises.push(
                        fetch(url, {credentials:'same-origin'}).then(function(r){return r.json()}).then(function(data){
                            if (!Array.isArray(data) || data.length === 0) return;
                            data.forEach(function(s){
                                if (s.pic && !/^https?:\/\//.test(s.pic)) s.pic = API_BASE + s.pic + '&token=' + encodeURIComponent(API_TOKEN);
                            });
                            self._allSongs[idx] = data;
                            if (data.length > 0 && data[0].pic) {
                                self._playlistCovers[idx] = data[0].pic;
                            }
                        }).catch(function(){})
                    );
                })(i);
            }
            Promise.all(promises).then(function(){
                self._loading = false;
                var savedIdx = self._loadSavedPlaylistIndex();
                if (savedIdx >= 0 && savedIdx < self.playlists.length && self._allSongs[savedIdx] && self._allSongs[savedIdx].length > 0) {
                    self.currentPlaylistIndex = savedIdx;
                } else {
                    var firstIdx = -1;
                    for (var i = 0; i < self.playlists.length; i++) {
                        if (self._allSongs[i] && self._allSongs[i].length > 0) { firstIdx = i; break; }
                    }
                    if (firstIdx >= 0) self.currentPlaylistIndex = firstIdx;
                    else self.currentPlaylistIndex = 0;
                }
                self.songs = self._allSongs[self.currentPlaylistIndex] || [];
                self._viewingPlaylist = (self.playlists && self.playlists.length > 1);
                self._viewingPlaylistIndex = self.currentPlaylistIndex;
                if (self.songs.length > 0) {
                    self.initPlayer(self.songs);
                } else {
                    self.renderSonglist();
                    self.$('ttl').textContent = '暂无歌曲';
                }
            }).catch(function(){
                self._loading = false;
                self.$('ttl').textContent = '加载失败';
            });
        },

        switchPlaylist: function(index) {
            var self = this;
            if (index === self.currentPlaylistIndex) return;
            if (index < 0 || index >= self.playlists.length) return;
            self.currentPlaylistIndex = index;
            var songs = self._allSongs[index] || [];
            self.songs = songs;
            if (songs.length === 0) {
                self.$('ttl').textContent = '暂无歌曲';
                self.songs = [];
                self.renderSonglist();
                self.saveState();
                return;
            }
            var audios = [];
            songs.forEach(function(s){
                if (!s.url) return;
                audios.push({name:s.name||'未知', artist:s.artist||'', url:s.url, cover:s.pic||'', _lrc:s.lrc||''});
            });
            if (audios.length === 0) return;
            if (self.ap) {
                self.ap.list.clear();
                self.ap.list.add(audios);
                self.ap.list.switch(0);
                self.ap.play();
                self.renderSonglist();
            } else {
                self.initPlayer(songs);
            }
            self.saveState();
        },

        _loadSavedPlaylistIndex: function() {
            try {
                var idx = getCookie('mapi_pl_index');
                if (idx !== null && idx !== '') {
                    var n = parseInt(idx);
                    if (!isNaN(n) && n >= 0 && n < this.playlists.length) return n;
                }
            } catch(e) {}
            return -1;
        },

        initPlayer: function(songs) {
            var self = this;
            var audios = [];
            var self = this;
            songs.forEach(function(s){
                if (!s.url) return;
                var pic = s.pic || '';
                s.pic = pic;
                audios.push({name:s.name||'未知', artist:s.artist||'', url:s.url, cover:pic, _lrc:s.lrc||''});
            });
            if (audios.length === 0) return;

            self.ap = new APlayer({
                container: self.$('apContainer'),
                audio: audios,
                mini: false, autoplay: self._autoplayDefault, theme: '#888',
                loop: 'all', order: 'list', preload: 'none', volume: 1.0, mutex: true
            });
            if (self.ap.audio) self.ap.audio.crossOrigin = 'anonymous';

            self.ap.on('play', function(){ self.updateUI(); self.renderSonglist(); self.updatePlayBtn(true); self.loadLrc(); self.syncLrc(); if(self._imOpen){self.updateImmersiveUI();self.updateImmersivePlayBtn(true);} var tg=self.$('toggle');if(tg){tg.classList.remove('loading');tg.classList.add('playing');} self.updateMediaSession(); });
            self.ap.on('pause', function(){ self.updateUI(); self.updatePlayBtn(false); if(self._imOpen){self.updateImmersivePlayBtn(false);} var tg=self.$('toggle');if(tg){tg.classList.remove('loading');tg.classList.remove('playing');} });
            self.ap.on('timeupdate', function(){ self.updateProgress(); self.syncLrc(); if(self._imOpen){self.updateImmersiveProgress();self.updateImmersiveLrc();} if(!self._ts||Date.now()-self._ts>5000){self._ts=Date.now();self.saveState();} });
            self.ap.on('ended', function(){ self.onEnded(); });
            self.ap.on('listswitch', function(){ self.onSwitch(); if(self._imOpen)self.updateImmersiveUI(); self.updateMediaSession(); });
            self.ap.on('listswitch', function(){ self.saveState(); });

            if ('mediaSession' in navigator) {
                navigator.mediaSession.setActionHandler('play', function(){ if(self.ap){ self.ap.play(); } });
                navigator.mediaSession.setActionHandler('pause', function(){ if(self.ap){ self.ap.pause(); } });
                navigator.mediaSession.setActionHandler('previoustrack', function(){ if(self.ap){ self.ap.skipBack(); setTimeout(function(){ self.onSwitch(); }, 200); } });
                navigator.mediaSession.setActionHandler('nexttrack', function(){ if(self.ap){ self.ap.skipForward(); setTimeout(function(){ self.onSwitch(); }, 200); } });
            }

            // 自动播放适配：Mac Safari/Chrome 等浏览器会拦截无用户交互的 autoplay
            if (self._autoplayDefault && self.ap && self.ap.audio) {
                var _autoplayCheck = function() {
                    if (self.ap && self.ap.audio && self.ap.audio.paused && !self._autoplayTried) {
                        self._autoplayTried = true;
                        // 播放被拦截，按钮脉冲提示用户点击
                        var tg = self.$('toggle');
                        if (tg && self.ap && self.ap.audio && self.ap.audio.paused) tg.classList.add('loading');
                        var _resume = function(e) {
                            if (self.ap && self.ap.audio) {
                                var p = self.ap.play();
                                if (p && p.catch) p.catch(function(){});
                            }
                            if (tg) tg.classList.remove('loading');
                            document.removeEventListener('click', _resume);
                            document.removeEventListener('touchstart', _resume);
                            document.removeEventListener('keydown', _resume);
                        };
                        document.addEventListener('click', _resume);
                        document.addEventListener('touchstart', _resume);
                        document.addEventListener('keydown', _resume);
                    }
                };
                // 延迟检测：给浏览器足够时间拒绝 autoplay
                setTimeout(_autoplayCheck, 200);
                // 安全兜底：APlayer 的 play 事件如果触发了就取消检测
                self.ap.on('play', function() { self._autoplayTried = true; });
            }

            // 窗口 resize 时重新检测底部元素，更新歌词条位置
            self._resizeLrc = function(){
                var el = document.querySelector('[data-mp="lrc"]');
                if (el) {
                    var px = self._getOverlapBottom();
                    el.style.bottom = px + 'px';
                }
                if (!self._destroyed) self._snap();
                var pnl = self.$('panel');
                if (pnl) {
                    var isMob = window.innerWidth <= 768;
                    pnl.style.width = isMob ? '280px' : Math.min(360, window.innerWidth - 30) + 'px';
                }
                var host = hostRoot.host;
                if (host) {
                    var mr = window.innerWidth <= 768 ? 4 : 15;
                    var mb = window.innerWidth <= 768 ? 35 : 50;
                    if (!host.style.top || host.style.top === '' || host.style.top === 'initial') {
                        host.style.bottom = mb + 'px';
                    }
                }
            };
            window.addEventListener('resize', self._resizeLrc);

            // WebView 切页面时 DOM 变化重新检测底部元素（跳过歌词条自身变更避免循环）
            self._domTimer = null;
            self._domObs = new MutationObserver(function(mutations){
                var lrc = document.querySelector('[data-mp="lrc"]');
                if (lrc) {
                    var skip = true;
                    for (var i = 0; i < mutations.length; i++) {
                        if (!lrc.contains(mutations[i].target)) { skip = false; break; }
                    }
                    if (skip) return;
                }
                clearTimeout(self._domTimer);
                self._domTimer = setTimeout(function(){
                    var el = document.querySelector('[data-mp="lrc"]');
                    if (el) {
                        var px = self._getOverlapBottom();
                        el.style.bottom = px + 'px';
                    }
                }, 500);
            });
            if (document.body) {
                self._domObs.observe(document.body, { childList: true, subtree: true });
            }

            self.renderSonglist();
            self.showToggle();
            self.updateUI();
            self.updatePlayBtn(false);
            self.$('vol').value = 1.0;
            if(self.ap) self.ap.volume(1.0);
            var _imVol = self.$('imVol');
            if(_imVol) _imVol.value = 1.0;

            /* 从 cookie 恢复状态（不走 setMode/saveState 避免连锁覆写） */
            var _songToRestore = -1;
            if (self._cookieConsented) {
                var _savedMode = getCookie('mapi_mode');
                if (_savedMode && ['list','single','random'].indexOf(_savedMode)>=0) {
                    self.mode = _savedMode;
                    var _loopMap = {single:'one', list:'all', random:'none'};
                    if (self.ap) self.ap.options.loop = _loopMap[_savedMode] || 'none';
                    var _trig = self.$('modeTrigger');
                    if (_trig) { var _labels={list:'列表播放',single:'单曲循环',random:'随机播放'}; _trig.textContent = _labels[_savedMode] || _savedMode; }
                    var _menu = self.$('modeMenu');
                    if (_menu) { _menu.querySelectorAll('.mode-option').forEach(function(o){ o.classList.toggle('active', o.getAttribute('data-mode')===_savedMode); }); }
                }
                var _savedSong = getCookie('mapi_song');
                if (_savedSong && self.ap && self.ap.list) {
                    var _n = parseInt(_savedSong);
                    if (!isNaN(_n) && _n >= 0 && _n < self.ap.list.audios.length) {
                        self.ap.list.switch(_n);
                        _songToRestore = _n;
                    }
                }
                var _savedVol = getCookie('mapi_volume');
                if (_savedVol) {
                    var _v = parseFloat(_savedVol);
                    if (!isNaN(_v) && _v >= 0 && _v <= 1) {
                        if (self.ap) self.ap.volume(_v);
                        self.$('vol').value = _v;
                        if (_imVol) _imVol.value = _v;
                    }
                }

            }

            if (!self.mode || self.mode === 'list') {
                self.mode = 'list';
                if (self.ap) self.ap.options.loop = 'all';
            }

            self._ready = true;
            /* 直接写入 cookie，不经过 saveState，避免任何中间状态干扰 */
            if (self._cookieConsented && self.ap && self.ap.list) {
                setCookie('mapi_song', _songToRestore >= 0 ? _songToRestore : self.ap.list.index);
                setCookie('mapi_pl_index', self.currentPlaylistIndex);
                setCookie('mapi_pos', self._side);
                setCookie('mapi_mode', self.mode || 'list');
                if (self.ap.audio) setCookie('mapi_volume', self.ap.audio.volume);
            }
            // 加载完成：API 返回且 APlayer 创建完毕，关掉动效
            function _clearLoading(){
                if(self._loading===false)return;
                self._loading=false;
                var _t=self.$("toggle");
                if(_t)_t.classList.remove("loading");
            }
            _clearLoading();
            if(self.ap&&self.ap.audio){
                self.ap.audio.addEventListener("canplay",_clearLoading,{once:true});
                self.ap.audio.addEventListener("error",_clearLoading,{once:true});
            }
            self._scheduleAutoHide();
        },

        bindUI: function() {
            var self = this;
            self.$('playBtn').addEventListener('click', function(){ if(self.ap) self.ap.toggle(); });
            self.$('prevBtn').addEventListener('click', function(){ if(self.ap){self.ap.skipBack();setTimeout(function(){self.onSwitch();},200);} });
            self.$('nextBtn').addEventListener('click', function(){ if(self.ap){self.ap.skipForward();setTimeout(function(){self.onSwitch();},200);} });
            self.$('pbar').addEventListener('click', function(e){
                if(!self.ap) return;
                var r = this.getBoundingClientRect();
                self.ap.seek((e.clientX - r.left) / r.width * self.ap.audio.duration);
            });
            self.$('vol').addEventListener('input', function(){ if(self.ap) self.ap.volume(this.value); });
            self.$('vol').addEventListener('change', function(){ self.saveState(); });
            // ── 音量增强 Boost ──
            self._boostOn = false;
            self._boostCtx = null;
            self._boostSource = null;
            self._boostGain = null;
            self._setupBoost = function(){
                try {
                    var AudioCtx = window.AudioContext || window.webkitAudioContext;
                    if (!AudioCtx || !self.ap || !self.ap.audio) return false;
                    if (self._boostCtx && self._boostCtx.state === 'suspended') self._boostCtx.resume();
                    // Audio element changed (APlayer switched tracks) → rebuild graph
                    if (self._boostSource && self.ap.audio !== self._boostAudioEl) {
                        try { self._boostSource.disconnect(); } catch(e) {}
                        try { self._boostGain.disconnect(); } catch(e) {}
                        self._boostSource = null;
                        self._boostGain = null;
                    }
                    if (self._boostSource) return true; // already set up
                    if (!self._boostCtx) self._boostCtx = new AudioCtx();
                    self._boostSource = self._boostCtx.createMediaElementSource(self.ap.audio);
                    self._boostGain = self._boostCtx.createGain();
                    self._boostGain.gain.value = self._boostOn ? 2.0 : 1.0;
                    self._boostSource.connect(self._boostGain);
                    self._boostGain.connect(self._boostCtx.destination);
                    self._boostAudioEl = self.ap.audio;
                    return true;
                } catch(e) {
                    console.warn('[增强] WebAudio 设置失败:', e);
                    return false;
                }
            };
            var boostBtn = self.$('boost');
            if (boostBtn) boostBtn.addEventListener('click', function(){
                if (!self._setupBoost()) return;
                // Cycle: 1x → 2x → 3x → 1x
                var levels = [1.0, 2.0, 3.0];
                var labels = ['增强', '增强2x', '增强3x'];
                var idx = levels.indexOf(self._boostGain.gain.value);
                idx = (idx + 1) % levels.length;
                self._boostGain.gain.value = levels[idx];
                self._boostOn = idx > 0;
                this.classList.toggle('on', self._boostOn);
                this.textContent = labels[idx];
            });
            self.bindModeSelect();
            self.$('overlay').addEventListener('click', function(){ self.togglePanel(); });
            self.$('overlay').addEventListener('touchend', function(e){ e.preventDefault(); self.togglePanel(); });
            var lrcBtn = self.$('lrcToggle');
            if (lrcBtn) lrcBtn.addEventListener('click', function(){ self.toggleLrc(); });
            // ── Immersive mode events ──
            var imBtn = self.$('immersiveBtn');
            if (imBtn) imBtn.addEventListener('click', function(){ self.toggleImmersive(); });
            var imClose = self.$('imClose');
            if (imClose) imClose.addEventListener('click', function(){ self.closeImmersive(); });
            var imPlay = self.$('imPlay');
            if (imPlay) imPlay.addEventListener('click', function(){ if(self.ap) self.ap.toggle(); });
            var imPrev = self.$('imPrev');
            if (imPrev) imPrev.addEventListener('click', function(){ if(self.ap){self.ap.skipBack();setTimeout(function(){self.onSwitch();if(self._imOpen)self.updateImmersiveUI();},200);} });
            var imNext = self.$('imNext');
            if (imNext) imNext.addEventListener('click', function(){ if(self.ap){self.ap.skipForward();setTimeout(function(){self.onSwitch();if(self._imOpen)self.updateImmersiveUI();},200);} });
            var imPbar = self.$('imPbar');
            if (imPbar) imPbar.addEventListener('click', function(e){
                if(!self.ap) return;
                var r = this.getBoundingClientRect();
                self.ap.seek((e.clientX - r.left) / r.width * self.ap.audio.duration);
            });
            var imVol = self.$('imVol');
            if (imVol) { imVol.addEventListener('input', function(){ if(self.ap) self.ap.volume(this.value); }); imVol.addEventListener('change', function(){ self.saveState(); }); }
            // ── Immersive songlist dropdown ──
            var slistTrigger = self.$('imSlistTrigger');
            var slistDropdown = self.$('imSlist');
            if (slistTrigger && slistDropdown) {
                slistTrigger.addEventListener('click', function(e){
                    e.stopPropagation();
                    slistDropdown.classList.toggle('open');
                });
                document.addEventListener('click', function(e){
                    if (!slistTrigger.contains(e.target) && !slistDropdown.contains(e.target)) {
                        slistDropdown.classList.remove('open');
                    }
                });
            }
            // ── Playlist back button ──
            var backBtns = [self.$('plBack'), self.$('imPlBack')].filter(function(e){return e;});
            backBtns.forEach(function(btn){
                btn.addEventListener('click', function(e){
                    e.stopPropagation();
                    self._viewingPlaylist = true;
                    self._renderWithFade();
                });
            });
            // ── Immersive mode mode cycle ──
            var imModeBtn = self.$('imModeBtn');
            if (imModeBtn) {
                imModeBtn.addEventListener('click', function(){
                    var order = ['list','single','random'];
                    var idx = order.indexOf(self.mode);
                    var next = order[(idx + 1) % order.length];
                    self.setMode(next);
                    self.updateImmersiveModeBtn();
                });
            }
            self.enableDrag();
        },

        enableDrag: function() {
            var self = this, tog = self.$('toggle'), ox = 0, oy = 0, _drag = false, _moved = false;
            var _sx = 0, _sy = 0, THRESH = 5, _actionHandled = false, _dragMinTop = -Infinity;
            function _start(cx,cy){
                _drag=true;_moved=false;_sx=cx;_sy=cy;hostRoot.host.style.transition='none';_actionHandled=false;
                var r=hostRoot.host.getBoundingClientRect();ox=cx-r.left;oy=cy-r.top;
                // Calculate drag limit: panel must not go above viewport
                _dragMinTop = -Infinity;
                if (self.open) {
                    var pnl = self.$('panel');
                    if (pnl) {
                        var pr = pnl.getBoundingClientRect();
                        _dragMinTop = 4 - (pr.top - r.top); // panel top min = 4px
                    }
                }
            }
            // ─── PC: mousedown immediate drag (threshold 5px) ───
            function _mv(e){
                var dx=e.clientX-_sx,dy=e.clientY-_sy;
                if(dx*dx+dy*dy<THRESH*THRESH)return;
                _moved=true;
                hostRoot.host.style.left=(e.clientX-ox)+'px';hostRoot.host.style.right='auto';
                var ty = e.clientY-oy;
                if (ty < _dragMinTop) ty = _dragMinTop;
                hostRoot.host.style.bottom='auto';hostRoot.host.style.top=ty+'px';
            }
            function _up(){_actionHandled=true;document.removeEventListener('mousemove',_mv);document.removeEventListener('mouseup',_up);if(!_moved){_drag=false;self.togglePanel();return;}self._snap();_drag=false;}
            // ─── Mobile: immediate drag (bind to toggle for WebView Shadow DOM compat) ───
            function _tm(e){e.preventDefault();var t=e.touches[0];
                if(!_moved){var dx=t.clientX-_sx,dy=t.clientY-_sy;if(dx*dx+dy*dy<THRESH*THRESH)return;_moved=true;}
                hostRoot.host.style.left=(t.clientX-ox)+'px';hostRoot.host.style.right='auto';
                var ty = t.clientY-oy;
                if (ty < _dragMinTop) ty = _dragMinTop;
                hostRoot.host.style.bottom='auto';hostRoot.host.style.top=ty+'px';
            }
            function _te(){_actionHandled=true;tog.removeEventListener('touchmove',_tm);tog.removeEventListener('touchend',_te);if(!_moved){_drag=false;self.togglePanel();return;}self._snap();_drag=false;}
            tog.addEventListener('touchstart',function(e){self._cancelAutoHide();_start(e.touches[0].clientX,e.touches[0].clientY);tog.addEventListener('touchmove',_tm,{passive:false});tog.addEventListener('touchend',_te);});
            // Prevent PC mousedown from also firing after touch (skip on touch devices)
            tog.addEventListener('mousedown',function(e){if(e.button===0&&!('ontouchstart'in window)){self._cancelAutoHide();_start(e.clientX,e.clientY);document.addEventListener('mousemove',_mv);document.addEventListener('mouseup',_up);}});
            // WebView fallback: click fires when touchend/mouseup lost due to Shadow DOM boundary
            tog.addEventListener('click',function(e){if(!_moved&&!_actionHandled){_actionHandled=true;self.togglePanel();}});
            // Hover reveals and cancels auto-hide; mouseleave restarts timer
            tog.addEventListener('mouseenter',function(){self._cancelAutoHide();});
            tog.addEventListener('mouseleave',function(){if(!self.open)self._scheduleAutoHide();});
        },

        _snap: function() {
            var h = hostRoot.host, mr = window.innerWidth <= 768 ? 4 : 15;
            var mb = window.innerWidth <= 768 ? 35 : 50;
            var hRect = h.getBoundingClientRect();
            var hostW = hRect.width;
            var goLeft = hRect.left + hostW / 2 < window.innerWidth / 2;
            var targetLeft = goLeft ? mr : window.innerWidth - hostW - mr;
            if (targetLeft < 2) targetLeft = 2;
            if (targetLeft + hostW > window.innerWidth - 2) targetLeft = window.innerWidth - hostW - 2;
            h.style.transition = 'left .35s cubic-bezier(.34,1.56,.64,1), top .35s cubic-bezier(.34,1.56,.64,1)';
            h.style.left = targetLeft + 'px';
            h.style.right = 'auto';
            if (!h.style.top || h.style.top === '' || h.style.top === 'initial') {
                h.style.bottom = mb + 'px';
            }
            // Clear bottom conflict if top was set by drag, and clamp within viewport
            if (h.style.top && h.style.top !== '') {
                h.style.bottom = 'auto';
                var tog = this.$('toggle');
                if (tog) {
                    var tr = tog.getBoundingClientRect();
                    var toff = tr.top - h.getBoundingClientRect().top;
                    var th = tr.bottom - tr.top;
                    var cur = parseFloat(h.style.top);
                    if (!isNaN(cur)) {
                        var minTop = mr - toff;
                        if (this.open) {
                            var pnl = this.$('panel');
                            if (pnl) {
                                var pr = pnl.getBoundingClientRect();
                                var poff = pr.top - h.getBoundingClientRect().top;
                                minTop = Math.max(minTop, 4 - poff);
                            }
                        }
                        h.style.top = Math.max(minTop, Math.min(cur, window.innerHeight - toff - th - mr)) + 'px';
                    }
                }
            }
            this._side = goLeft ? 'left' : 'right';
            var root = this.$('root');
            if (root) root.style.alignItems = goLeft ? 'flex-start' : 'flex-end';
            var tog = this.$('toggle');
            if (tog) {
                tog.style.left = goLeft ? '' : 'auto';
                tog.style.right = goLeft ? 'auto' : '';
            }
            var panel = this.$('panel');
            if (panel) panel.style.transformOrigin = 'bottom ' + (goLeft ? 'left' : 'right');
            this._updateToggleTransform();
            var self = this;
            clearTimeout(this._snapTmr);
            this._snapTmr = setTimeout(function(){ h.style.transition = 'none'; }, 400);
            if (!this.open) this._scheduleAutoHide();
            this.saveState();
        },

        saveState: function() {
            if (!this._ready) return;
            if (!this._cookieConsented) return;
            if (!this.ap || !this.ap.list) return;
            setCookie('mapi_song', this.ap.list.index);
            setCookie('mapi_pl_index', this.currentPlaylistIndex < (this.playlists?this.playlists.length:0) ? this.currentPlaylistIndex : 0);
            var _top=hostRoot.host.style.top;setCookie('mapi_pos', this._side+(_top&&_top!=='initial'&&_top!=='auto'?',t:'+_top:''));
            setCookie('mapi_mode', this.mode || 'list');
            if (this.ap && this.ap.audio) setCookie('mapi_volume', this.ap.audio.volume);
        },
        loadState: function() {
            if (!this._cookieConsented) return;

            var idx = getCookie('mapi_song'), mode = getCookie('mapi_mode');
            if (idx && this.ap && this.ap.list) {
                var n = parseInt(idx);
                if (!isNaN(n) && n >= 0 && n < this.ap.list.audios.length) {
                    this.ap.list.switch(n);
                }
            }
            var vol = getCookie('mapi_volume');
            if (vol) {
                var v = parseFloat(vol);
                if (!isNaN(v) && v >= 0 && v <= 1) {
                    if (this.ap) this.ap.volume(v);
                    this.$('vol').value = v;
                    var _imVol = this.$('imVol');
                    if (_imVol) _imVol.value = v;
                }
            }
            if (mode && ['list','single','random'].indexOf(mode)>=0) { this.setMode(mode); }
        },

        setPosition: function(pos) {
            var h = hostRoot.host;
            var mr = window.innerWidth <= 768 ? '4px' : '15px';
            h.style.left = pos === 'left' ? mr : 'auto';
            h.style.right = pos === 'right' ? mr : 'auto';
            this._side = pos;
            var root = this.$('root');
            root.style.alignItems = pos === 'left' ? 'flex-start' : 'flex-end';
            var tog = this.$('toggle');
            if (tog) {
                tog.style.left = pos === 'left' ? '' : 'auto';
                tog.style.right = pos === 'left' ? 'auto' : '';
            }
            var panel = this.$('panel');
            if (panel) panel.style.transformOrigin = 'bottom ' + (pos === 'left' ? 'left' : 'right');
            this._updateToggleTransform();
            this._scheduleAutoHide();
        },

        togglePanel: function() {
            var self = this;
            if (self._loading) {
                if (self._toastEl && self._toastEl.parentNode) return;
                // Show loading toast
                var toast = document.createElement('div');
                toast.textContent = '加载中…';
                toast.style.cssText = 'position:fixed;top:80px;left:50%;transform:translateX(-50%) scale(0.8);z-index:2147483647;background:rgba(0,0,0,.55);backdrop-filter:blur(16px)saturate(200%);color:#fff;font-size:14px;font-weight:600;padding:10px 20px;border-radius:10px;font-family:-apple-system,"PingFang SC","Microsoft YaHei",sans-serif;opacity:0;transition:all .3s cubic-bezier(.4,0,.2,1);pointer-events:none';
                document.body.appendChild(toast);
                self._toastEl = toast;
                void toast.offsetWidth;
                toast.style.opacity = '1';
                toast.style.transform = 'translateX(-50%) scale(1)';
                setTimeout(function(){
                    toast.style.opacity = '0';
                    toast.style.transform = 'translateX(-50%) scale(0.8)';
                    setTimeout(function(){ if (toast.parentNode) toast.parentNode.removeChild(toast); self._toastEl = null; }, 300);
                }, 2000);
                return;
            }
            self.open = !self.open;
            var pnl = self.$('panel');
            self.$('overlay').style.display = self.open ? 'block' : 'none';
            if (self.open) {
                // Panel opened: keep button visible, never auto-hide
                self._cancelAutoHide();
                pnl.classList.add('open');
                self.updateUI();
                // Auto-calibrate: if panel extends above viewport, push entire host down
                var pr = pnl.getBoundingClientRect();
                if (pr.top < 0) {
                    var h = hostRoot.host;
                    var hr = h.getBoundingClientRect();
                    var offset = Math.abs(pr.top) + 8;
                    h.style.transition = 'top .35s cubic-bezier(.34,1.56,.64,1), left .35s cubic-bezier(.34,1.56,.64,1)';
                    h.style.top = (hr.top + offset) + 'px';
                    h.style.bottom = 'auto';
                    var self2 = this;
                    clearTimeout(this._autoCalibrateTmr);
                    this._autoCalibrateTmr = setTimeout(function(){ h.style.transition = 'none'; }, 400);
                }
            } else {
                pnl.classList.remove('open');
                // Panel closed: resume normal auto-hide
                self._scheduleAutoHide();
            }
        },

        updatePlayBtn: function(playing) {
            var svg = this.$('playSvg');
            if (playing) {
                svg.innerHTML = '<rect x="6" y="4" width="4" height="16"/><rect x="14" y="4" width="4" height="16"/>';
            } else {
                svg.innerHTML = '<polygon points="6,4 20,12 6,20"/>';
            }
        },

        setMode: function(mode) {
            this.mode = mode;
            var labels = {list:'列表播放', single:'单曲循环', random:'随机播放'};
            var trigger = this.$('modeTrigger');
            if (trigger) { trigger.textContent = labels[mode] || mode; }
            var menu = this.$('modeMenu');
            if (menu) {
                menu.querySelectorAll('.mode-option').forEach(function(o){
                    o.classList.toggle('active', o.getAttribute('data-mode') === mode);
                });
            }
            // Let APlayer handle single/loop natively
            if (this.ap) {
                var loopMap = {single:'one', list:'all', random:'none'};
                this.ap.options.loop = loopMap[mode] || 'none';
            }
            this.saveState();
        },

        bindModeSelect: function() {
            var self = this;
            var trigger = this.$('modeTrigger');
            var menu = this.$('modeMenu');
            if (!trigger || !menu) return;
            trigger.addEventListener('click', function(e){
                e.stopPropagation();
                if (menu.classList.contains('open')) {
                    menu.classList.remove('open');
                } else {
                    menu.classList.add('open');
                }
            });
            menu.querySelectorAll('.mode-option').forEach(function(opt){
                opt.addEventListener('click', function(){
                    var mode = this.getAttribute('data-mode');
                    if (mode === self.mode) { menu.classList.remove('open'); return; }
                    self.setMode(mode);
                    menu.classList.remove('open');
                });
            });
            document.addEventListener('click', function(e){
                if (!trigger.contains(e.target) && !menu.contains(e.target)) {
                    menu.classList.remove('open');
                }
            });
        },

        toggleImmersive: function() {
            var ov = this.$('immersiveOverlay');
            var tog = this.$('toggle');
            if (!ov) return;
            // Set transform-origin to button position for tracking animation
            var btn = this.$('immersiveBtn');
            if (btn) {
                var br = btn.getBoundingClientRect();
                ov.style.transformOrigin = (br.left + br.width/2) + 'px ' + (br.top + br.height/2) + 'px';
            }
            this._imOpen = !this._imOpen;
            if (this._imOpen) {
                ov.classList.add('open');
                if (tog) { tog.style.opacity = '0'; tog.style.pointerEvents = 'none'; }
                document.body.style.overflow = 'hidden';
                document.documentElement.style.overflow = 'hidden';
                // Prevent touch scrolling on the page behind (allow songlist scroll)
                if (!ov._touchHandler) {
                    ov._touchHandler = function(e){
                        var t = e.target;
                        if (t && t.closest && t.closest('[data-mp="imSlist"]')) return;
                        e.preventDefault();
                    };
                    ov.addEventListener('touchmove', ov._touchHandler, {passive: false});
                }
                var lrcPill = document.querySelector('[data-mp="lrc"]');
                if (lrcPill) lrcPill.style.display = 'none';
                this.updateImmersiveUI(); this.updateImmersivePlayBtn(!this.ap||!this.ap.audio||this.ap.audio.paused?false:true);
                this.updateImmersiveLrc();
                this.updateImmersiveModeBtn();
                // Sync immersive vol & render songlist for both containers
                var imVol = this.$('imVol');
                if (imVol && this.ap && this.ap.audio) imVol.value = this.ap.audio.volume;
                this.renderSonglist();
            } else {
                ov.classList.remove('open');
                if (tog) { tog.style.opacity = ''; tog.style.pointerEvents = ''; }
                document.body.style.overflow = '';
                document.documentElement.style.overflow = '';
                if (ov._touchHandler) {
                    ov.removeEventListener('touchmove', ov._touchHandler);
                    ov._touchHandler = null;
                }
                var lrcPill2 = document.querySelector('[data-mp="lrc"]');
                if (lrcPill2) lrcPill2.style.display = '';
            }
        },
        closeImmersive: function() {
            var ov = this.$('immersiveOverlay');
            var tog = this.$('toggle');
            if (!ov) return;
            // Set transform-origin to button position for tracking close animation
            var btn = this.$('immersiveBtn');
            if (btn) {
                var br = btn.getBoundingClientRect();
                ov.style.transformOrigin = (br.left + br.width/2) + 'px ' + (br.top + br.height/2) + 'px';
            }
            ov.classList.remove('open');
            this._imOpen = false;
            if (tog) { tog.style.opacity = ''; tog.style.pointerEvents = ''; }
            var lrcPill = document.querySelector('[data-mp="lrc"]');
            if (lrcPill) lrcPill.style.display = '';
            document.body.style.overflow = '';
            document.documentElement.style.overflow = '';
            if (ov._touchHandler) {
                ov.removeEventListener('touchmove', ov._touchHandler);
                ov._touchHandler = null;
            }
        },
        updateImmersiveUI: function() {
            if (!this.ap || !this.ap.list) return;
            var idx = this.ap.list.index;
            var info = this.ap.list.audios[idx];
            var titleEl = this.$('imTitle');
            var artistEl = this.$('imArtist');
            var coverEl = this.$('imCover');
            if (titleEl) titleEl.textContent = info ? info.name : '未知';
            if (artistEl) artistEl.textContent = info ? (info.artist || '') : '';
            if (coverEl) { coverEl.src = info && info.cover ? info.cover : ''; }
            this.updateImmersiveProgress();
        },
        updateImmersivePlayBtn: function(playing) {
            var svg = this.$('imPlaySvg');
            if (!svg) return;
            svg.innerHTML = playing ? '<rect x="6" y="4" width="4" height="16"/><rect x="14" y="4" width="4" height="16"/>' : '<polygon points="6,4 20,12 6,20"/>';
        },
        updateImmersiveProgress: function() {
            if (!this.ap || !this.ap.audio) return;
            var a = this.ap.audio;
            var cur = a.currentTime||0, dur = a.duration||0;
            var pct = dur > 0 ? (cur/dur*100) : 0;
            var pfill = this.$('imPfill');
            if (pfill) pfill.style.width = pct + '%';
            var curEl = this.$('imCur');
            var durEl = this.$('imDur');
            if (curEl) curEl.textContent = fmt(cur);
            if (durEl) durEl.textContent = dur ? fmt(dur) : '00:00';
        },
        updateImmersiveModeBtn: function() {
            var svg = this.$('imModeSvg');
            if (!svg) return;
            var icons = {
                list: '<polyline points="17 1 21 5 17 9"/><path d="M3 11V9a4 4 0 0 1 4-4h14"/><polyline points="7 23 3 19 7 15"/><path d="M21 13v2a4 4 0 0 1-4 4H3"/>',
                single: '<polyline points="17 1 21 5 17 9"/><path d="M3 11V9a4 4 0 0 1 4-4h14"/><polyline points="7 23 3 19 7 15"/><path d="M21 13v2a4 4 0 0 1-4 4H3"/><text x="12" y="15" text-anchor="middle" font-size="10" font-weight="700">1</text>',
                random: '<polyline points="16 3 21 7 16 11"/><polyline points="8 13 3 17 8 21"/><line x1="3" y1="3" x2="21" y2="21"/>'
            };
            svg.innerHTML = icons[this.mode] || icons.list;
        },
        updateImmersiveLrc: function() {
            var container = this.$('imLrc');
            if (!container) return;
            var wrap = container.parentElement;
            if (!this.lrcLines || this.lrcLines.length === 0) {
                // Show placeholder text for pure music
                try {
                    var _a = this.ap.list.audios[this.ap.list.index];
                    if (_a && _a._lrc && _a._lrc.indexOf('此歌曲为没有填词的纯音乐') >= 0) {
                        if (wrap) wrap.style.display = '';
                        container.innerHTML = '<div class="im-lrc-line active">此歌曲为没有填词的纯音乐，请您欣赏</div>';
                        return;
                    }
                } catch(e) {}
                container.innerHTML = '';
                if (wrap) wrap.style.display = 'none';
                return;
            }
            if (wrap) wrap.style.display = '';
            var ct = this.ap ? this.ap.audio.currentTime : 0;
            var activeIdx = -1;
            for (var i = this.lrcLines.length - 1; i >= 0; i--) {
                if (ct >= this.lrcLines[i].time) { activeIdx = i; break; }
            }
            if (activeIdx < 0 && this.lrcLines.length > 0) activeIdx = 0;

            // Only rebuild DOM when lyrics content changes (new song)
            if (container.children.length !== this.lrcLines.length) {
                var html = '';
                for (var j = 0; j < this.lrcLines.length; j++) {
                    html += '<div class="im-lrc-line">' + escapeHtml(this.lrcLines[j].text) + '</div>';
                }
                container.innerHTML = html;
            }

            // Just update classes on each tick
            for (var k = 0; k < container.children.length; k++) {
                var cl = 'im-lrc-line';
                if (k === activeIdx) cl += ' active';
                else if (k === activeIdx - 1) cl += ' prev';
                container.children[k].className = cl;
            }

            // Smooth scroll to center active line
            if (activeIdx >= 0) {
                var activeEl = container.children[activeIdx];
                if (activeEl) {
                    var wrapH = wrap.offsetHeight || 150;
                    var lineH = activeEl.offsetHeight || 30;
                    var offset = activeEl.offsetTop - wrapH / 2 + lineH / 2;
                    container.style.transform = 'translateY(-' + Math.max(0, offset) + 'px)';
                }
            }
        },
        toggleLrc: function() {
            this._showLrc = !this._showLrc;
            var btn = this.$('lrcToggle');
            if (btn) btn.classList.toggle('active', this._showLrc);
            var lrcEl = document.querySelector('[data-mp="lrc"]');
            if (!lrcEl) { this.saveState(); return; }
            if (this._showLrc) {
                // Expand banner: width 0 → natural width (horizontal unfold)
                var span = lrcEl.firstElementChild;
                if (!span) { lrcEl.style.display = ''; this.saveState(); return; }
                // If no text, nothing to expand - keep hidden
                var txt = span.textContent || '';
                if (!txt.trim()) { this.saveState(); return; }
                span.style.display = 'inline-block';
                lrcEl.style.transition = 'none';
                lrcEl.style.width = '0';
                lrcEl.style.padding = '6px 0';
                lrcEl.style.height = '';
                lrcEl.style.overflow = 'hidden';
                lrcEl.style.opacity = '0';
                void lrcEl.offsetWidth;
                var fullW = span.scrollWidth + 40;
                lrcEl.style.transition = 'width .7s cubic-bezier(.4,0,.2,1),padding .7s cubic-bezier(.4,0,.2,1),opacity .5s';
                lrcEl.style.width = fullW + 'px';
                lrcEl.style.padding = '6px 20px';
                lrcEl.style.opacity = '1';
                this._lrcAnimating = true;
                var self = this;
                clearTimeout(this._lrcAnimTmr);
                this._lrcAnimTmr = setTimeout(function(){ self._lrcAnimating = false; }, 800);
            } else {
                // Collapse banner: width → 0 (horizontal fold)
                var curW = lrcEl.offsetWidth;
                if (curW <= 1) { lrcEl.style.display = 'none'; this.saveState(); return; }
                lrcEl.style.overflow = 'hidden';
                lrcEl.style.transition = 'width .6s cubic-bezier(.4,0,.2,1),padding .6s cubic-bezier(.4,0,.2,1),opacity .45s';
                lrcEl.style.width = '0';
                lrcEl.style.padding = '6px 0';
                lrcEl.style.opacity = '0';
                this._lrcAnimating = true;
                var self2 = this;
                clearTimeout(this._lrcAnimTmr);
                this._lrcAnimTmr = setTimeout(function(){ self2._lrcAnimating = false; }, 700);
            }
            this.saveState();
        },

        onEnded: function() {
            var self = this;
            // single(list) and list(all) handled natively by APlayer loop
            if (this.mode !== 'random') return;
            var total = this.ap.list.audios.length;
            var cur = this.ap.list.index;
            var next = Math.floor(Math.random() * total);
            if (next === cur && total > 1) next = (next + 1) % total;
            self.ap.list.switch(next);
            setTimeout(function(){ self.onSwitch(); }, 100);
        },

        onSwitch: function() {
            this.updateUI();
            this.lrcLines = [];
            this.loadLrc();
            if (this._imOpen) this.updateImmersiveLrc();
            // Delay songlist scroll to ensure DOM layout is ready
            var self = this;
            setTimeout(function(){ self.renderSonglist(); }, 80);
        },

        updateMediaSession: function() {
            if (!('mediaSession' in navigator) || !this.ap || !this.ap.list) return;
            var idx = this.ap.list.index;
            var info = this.ap.list.audios[idx];
            if (!info) return;
            navigator.mediaSession.metadata = new MediaMetadata({
                title: info.name || '未知',
                artist: info.artist || '',
                artwork: info.cover ? [{ src: info.cover, sizes: '512x512', type: 'image/jpeg' }] : []
            });
        },

        // ─── 歌名/作者跑马灯 ───
        applyMarquee: function(el, text) {
            if (!el) return;
            var span = el.querySelector('.mi');
            if (!span) { el.textContent = ''; span = document.createElement('span'); span.className = 'mi'; el.appendChild(span); }
            span.textContent = text;
            el.classList.add('mw');
            var parentW = el.parentElement ? el.parentElement.clientWidth : el.clientWidth;
            var singleW = span.scrollWidth;
            if (singleW > parentW) {
                // Duplicate text with visible gap
                span.textContent = text + '             ' + text;
                var totalW = span.scrollWidth;
                var gapW = totalW - singleW * 2; // spaces width
                var cycleW = singleW + gapW;     // one full cycle = text + gap
                el.style.setProperty('--mx', '-' + cycleW + 'px');
                el.style.setProperty('--md', Math.max(cycleW / 25, 5) + 's');
            } else {
                el.style.setProperty('--md', '0s');
                el.style.setProperty('--mx', '0px');
            }
        },

        updateUI: function() {
            if (!this.ap || !this.ap.list) return;
            var idx = this.ap.list.index;
            var info = this.ap.list.audios[idx];
            var cover = this.$('cv').querySelector('img');
            if (info && info.cover) { cover.src = info.cover; cover.style.display = ''; }
            else { cover.style.display = 'none'; }
            // Update toggle button cover
            var togCover = this.$('toggleCover');
            var togSvg = this.$('toggleSvg');
            if (togCover && info && info.cover) {
                if (info.cover !== this._lastToggleCover) {
                    this._lastToggleCover = info.cover;
                    var curSrc = info.cover;
                    // Always show cover element; SVG stays underneath as placeholder
                    togCover.style.display = 'block';
                    if (togSvg) togSvg.style.display = '';
                    togCover.onload = function() {
                        // Cover loaded — already showing over SVG, no-op
                    };
                    togCover.onerror = function() {
                        // Cover failed — hide it, SVG shows through
                        togCover.style.display = 'none';
                    };
                    togCover.src = info.cover;
                } else {
                    togCover.style.display = 'block';
                }
            } else if (togCover) {
                togCover.style.display = 'none';
                togCover.src = '';
                if (togSvg) togSvg.style.display = '';
                this._lastToggleCover = '';
            }
            this.applyMarquee(this.$('ttl'), info ? info.name : '未知');
            this.applyMarquee(this.$('art'), info ? (info.artist || '') : '');
            this.updateProgress();
        },

        renderSonglist: function() {
            var self = this;
            var innerEls = [this.$('slistInner'), this.$('imSlistInner')].filter(function(e){return e;});
            if (innerEls.length === 0) return;
            var hasMultiple = self.playlists && self.playlists.length > 1;
            var backEls = [this.$('plBack'), this.$('imPlBack')].filter(function(e){return e;});
            backEls.forEach(function(btn){
                if (!self._viewingPlaylist && hasMultiple) btn.classList.add('visible');
                else btn.classList.remove('visible');
            });
            if (self._viewingPlaylist && hasMultiple) {
                var html = '';
                for (var i = 0; i < self.playlists.length; i++) {
                    var pl = self.playlists[i];
                    var name = pl.name || ('歌单' + (i + 1));
                    var cover = self._playlistCovers[i] || '';
                    html += '<div class="pl-list-item" data-plidx="' + i + '">';
                    html += '<span class="pl-idx">' + (i + 1) + '</span>';
                    if (cover) {
                        html += '<img class="pl-cover" src="' + escapeHtml(cover) + '" alt="" onerror="var s=document.createElement(\'span\');s.className=\'pl-cover\';s.style.cssText=\'display:flex;align-items:center;justify-content:center;font-size:10px;background:rgba(0,0,0,.06)\';s.textContent=\'' + escapeHtml(name.charAt(0)) + '\';this.parentNode.replaceChild(s,this)">';
                    } else {
                        html += '<span class="pl-cover" style="display:flex;align-items:center;justify-content:center;font-size:10px">' + escapeHtml(name.charAt(0)) + '</span>';
                    }
                    html += '<span class="pl-name">' + escapeHtml(name) + '</span>';
                    html += '<span class="pl-count">' + (self._allSongs[i] ? self._allSongs[i].length : 0) + '首</span>';
                    html += '</div>';
                }
                innerEls.forEach(function(el){
                    el.innerHTML = html;
                    el.querySelectorAll('.pl-list-item').forEach(function(item){
                        item.addEventListener('click', function(e){
                            e.stopPropagation();
                            var plidx = parseInt(this.getAttribute('data-plidx'));
                            self._viewingPlaylist = false;
                            self._viewingPlaylistIndex = plidx;
                            self._renderWithFade();
                        });
                    });
                });
            } else {
                var viewSongs = self._allSongs[self._viewingPlaylistIndex] || [];
                if (viewSongs.length === 0) {
                    innerEls.forEach(function(el){
                        el.innerHTML = '<div style="color:#999;font-size:13px;text-align:center;padding:20px 0">无歌曲</div>';
                    });
                    return;
                }
                var isSamePL = self._viewingPlaylistIndex === self.currentPlaylistIndex;
                var idx = self.ap ? self.ap.list.index : 0;
                var html = '';
                for (var i = 0; i < viewSongs.length; i++) {
                    var s = viewSongs[i];
                    var active = (isSamePL && i === idx) ? ' active' : '';
                    html += '<div class="songitem' + active + '" data-idx="' + i + '">';
                    html += '<span class="si-idx">' + (i + 1) + '</span>';
                    var _cov = s.cover || s.pic || ''; if (_cov) html += '<img class="si-cover" src="' + escapeHtml(_cov) + '" alt="">';
                    html += '<span class="si-name">' + escapeHtml(s.name || '') + '</span>';
                    html += '<span class="si-artist">' + escapeHtml(s.artist || '') + '</span>';
                    html += '</div>';
                }
                innerEls.forEach(function(el){
                    el.innerHTML = html;
                    var activeEl = el.querySelector('.songitem.active');
                    if (activeEl) activeEl.scrollIntoView({block:'nearest',behavior:'smooth'});
                    el.querySelectorAll('.songitem').forEach(function(item){
                        item.addEventListener('click', function(e){
                            e.stopPropagation();
                            var targetIdx = parseInt(this.getAttribute('data-idx'));
                            var targetSongs = self._allSongs[self._viewingPlaylistIndex] || [];
                            var targetSong = targetSongs[targetIdx];
                            if (!targetSong || !targetSong.url) return;
                            if (self._viewingPlaylistIndex !== self.currentPlaylistIndex) {
                                self.currentPlaylistIndex = self._viewingPlaylistIndex;
                                self.songs = targetSongs;
                                self._viewingPlaylist = false;
                                self.saveState();
                                if (self.ap) {
                                    self.ap.list.clear();
                                    var audios = [];
                                    targetSongs.forEach(function(ts){
                                        if (ts.url) audios.push({name:ts.name||'未知', artist:ts.artist||'', url:ts.url, cover:ts.pic||'', _lrc:ts.lrc||''});
                                    });
                                    self.ap.list.add(audios);
                                    self.ap.list.switch(targetIdx);
                                    self.ap.play();
                                } else {
                                    self.initPlayer(targetSongs);
                                }
                            } else {
                                if (targetIdx === idx) return;
                                try { self.ap.list.switch(targetIdx); } catch(e) {}
                                setTimeout(function(){ self.onSwitch(); }, 150);
                            }
                            self.renderSonglist();
                        });
                    });
                });
            }
        },

        _renderWithFade: function() {
            var self = this;
            var innerEls = [self.$('slistInner'), self.$('imSlistInner')].filter(function(e){return e;});
            innerEls.forEach(function(el){ el.style.opacity = '0'; });
            setTimeout(function(){
                self.renderSonglist();
                innerEls.forEach(function(el){ el.style.opacity = '1'; });
            }, 150);
        },

        updateProgress: function() {
            if (!this.ap || !this.ap.audio) return;
            var a = this.ap.audio;
            var cur = a.currentTime||0, dur = a.duration||0;
            var pct = dur > 0 ? (cur/dur*100) : 0;
            this.$('played').style.width = pct + '%';
            this.$('cur').textContent = fmt(cur);
            this.$('dur').textContent = dur ? fmt(dur) : '00:00';
        },

        // ─── 歌词 ───
        loadLrc: function() {
            if (!this.ap || !this.ap.list) return;
            var idx = this.ap.list.index;
            var audio = this.ap.list.audios[idx];
            if (!audio) return;
            if (audio._lrc) { this.parseLrc(audio._lrc); return; }
            this.lrcLines = [];
        },

        parseLrc: function(str) {
            this.lrcLines = [];
            if (!str) return;
            // netease 歌词 JSON 兜底解包
            if (str.trim().charAt(0) === '{') {
                try {
                    var obj = JSON.parse(str);
                    str = obj.lyric || (obj.lrc && obj.lrc.lyric) || str;
                } catch(e) {}
            }
            var lines = str.split('\n');
            for (var i=0;i<lines.length;i++) {
                var m = lines[i].match(/\[(\d{2}):(\d{2})(?:[:.](\d+))?\](.*)/);
                if (m) {
                    var t = parseInt(m[1])*60 + parseInt(m[2]) + parseInt((m[3]||'0').substring(0,3))/1000;
                    var txt = (m[4]||'').trim();
                    if (txt) {
                        this.lrcLines.push({time:t, text:txt});
                    }
                }
            }
            this.lrcLines.sort(function(a,b){return a.time-b.time;});
        },

        syncLrc: function() {
            // If only placeholder lyrics ("纯音乐") from love72, skip display
            var PLACEHOLDER = '此歌曲为没有填词的纯音乐，请您欣赏';
            if (this.lrcLines.length === 1 && this.lrcLines[0].text === PLACEHOLDER) {
                this.lrcLines = [];
                var lrcEl = document.querySelector('[data-mp="lrc"]');
                if (lrcEl) lrcEl.style.opacity = '0';
                return;
            }
            var ct = this.ap ? this.ap.audio.currentTime : 0;
            var txt = '';
            var hasLrc = this.lrcLines.length >= 5;
            if (!hasLrc && this.lrcLines.length > 0) { this.lrcLines = []; }
            if (hasLrc) {
                var _idx = -1;
                for (var i=this.lrcLines.length-1;i>=0;i--) {
                    if (ct >= this.lrcLines[i].time) { txt = this.lrcLines[i].text; _idx = i; break; }
                }
                if (!txt && ct < 1 && this.lrcLines.length > 0) {
                    txt = this.lrcLines[0].text; _idx = 0;
                }
            }
            var isMobile = window.innerWidth <= 768;
            var lrcEl = document.querySelector('[data-mp="lrc"]');
            if (!lrcEl) {
                lrcEl = document.createElement('div');
                lrcEl.setAttribute('data-mp', 'lrc');
                var bottomPx = this._getOverlapBottom();
                var isDark = this.$('root') && this.$('root').classList.contains('dark');
                var baseStyle = 'position:fixed;bottom:' + bottomPx + 'px;left:50%;transform:translateX(-50%);z-index:2147483646;font-size:15px;font-weight:700;white-space:nowrap;pointer-events:none' +
                    (isDark ? ';color:#d0d0d8;background:rgba(55,55,68,.65);backdrop-filter:blur(16px)saturate(200%);padding:6px 20px;border-radius:20px;border:1px solid rgba(255,255,255,.06)' : ';color:#1a1a2e;background:rgba(255,255,255,.3);backdrop-filter:blur(16px)saturate(200%);padding:6px 20px;border-radius:20px;border:1px solid rgba(255,255,255,.5)');
                lrcEl.style.cssText = baseStyle + (isMobile ? ';max-width:calc(100vw - 32px);overflow:hidden' : '');
                var span = document.createElement('span');
                lrcEl.appendChild(span);
                document.body.appendChild(lrcEl);
                this._lastLrcW = 0;
                // Sync button state on first creation
                var btn = this.$('lrcToggle');
                if (btn) btn.classList.toggle('active', this._showLrc);
                // Apply collapsed state if toggled off
                if (!this._showLrc) { lrcEl.style.overflow='hidden'; lrcEl.style.width='0'; lrcEl.style.padding='6px 0'; lrcEl.style.opacity='0'; }
            }
            var span = lrcEl.firstElementChild;
            if (!span) { span = document.createElement('span'); lrcEl.appendChild(span); }
            var spanSub = !isMobile && this._hasTranslation ? lrcEl.children[1] : null;
            span.style.display = 'inline-block';

            if (this.ap && !this.ap.audio.paused) {
                if (!hasLrc && txt === '') { txt = '此歌曲为没有填词的纯音乐，请您欣赏'; }
                if (txt) {
                    var isNew = span.textContent !== txt || (spanSub && spanSub.textContent !== txtOrig);
                    span.textContent = txt;
                    if (spanSub) { spanSub.textContent = txtOrig; }
                    // If toggled off, still update text but don't touch styles
                    if (!this._showLrc || this._lrcAnimating) return;
                    lrcEl.style.opacity = '1';

                    // Width animation: use wider of the two spans
                    var newW = span.scrollWidth + 42;
                    if (spanSub) newW = Math.max(newW, spanSub.scrollWidth + 42);
                    if (isMobile) newW = Math.min(newW, window.innerWidth - 32);
                    var oldW = this._lastLrcW || newW;
                    lrcEl.style.transition = 'width .35s cubic-bezier(.4,0,.2,1),opacity .3s';
                    lrcEl.style.width = oldW + 'px';
                    void lrcEl.offsetWidth;
                    lrcEl.style.width = newW + 'px';
                    this._lastLrcW = newW;

                    // Marquee scroll when text overflows (mobile only)
                    if (isNew && isMobile) {
                        span.style.transition = 'none';
                        span.style.transform = 'translateX(0)';
                        void span.offsetWidth;
                        var boxW = newW - 42;
                        if (span.scrollWidth > boxW) {
                            var overflow = span.scrollWidth - boxW;
                            var dur = 5;
                            if (hasLrc) {
                                for (var j = 0; j < this.lrcLines.length; j++) {
                                    if (this.lrcLines[j].time > ct) {
                                        dur = Math.min(Math.max(this.lrcLines[j].time - ct, 2), 10);
                                        break;
                                    }
                                }
                            }
                            var self = this;
                            requestAnimationFrame(function(){
                                span.style.transition = 'transform ' + dur + 's linear';
                                span.style.transform = 'translateX(-' + overflow + 'px)';
                            });
                        }
                    }
                    return;
                }
            }
            lrcEl.style.opacity = '0';
            lrcEl.style.width = '';
            lrcEl.style.transition = 'opacity .3s';
        },

        // ── 检测底部固定元素，自动抬升歌词条 ──
        _getOverlapBottom: function() {
            var maxBottom = 8; // 默认 vs 底部 8px
            var vpH = window.innerHeight;
            var allEls = document.querySelectorAll('body *');
            for (var i = 0; i < allEls.length; i++) {
                var el = allEls[i];
                // 跳过播放器自身的宿主容器
                if (el.id && typeof el.id === 'string' && el.id.indexOf('mapi-player-') === 0) continue;
                // 跳过歌词条自身（position:fixed 贴底，会误检测为障碍物导致振荡）
                if (el.getAttribute && el.getAttribute('data-mp') === 'lrc') continue;
                var rect = el.getBoundingClientRect();
                var elH = rect.bottom - rect.top;
                if (elH <= 0) continue;
                // 仅在底部 60px 范围内，且元素高度不超过视口 40%（排除全屏遮罩）
                if (rect.bottom < vpH - 60 || rect.top > vpH) continue;
                if (elH > vpH * 0.4) continue;
                try {
                    var style = window.getComputedStyle(allEls[i]);
                    if (style.position !== 'fixed' && style.position !== 'sticky') continue;
                    // 跳过隐藏元素（弹窗遮罩等不可见 fixed 层）
                    if (style.visibility === 'hidden' || style.display === 'none') continue;
                } catch(e) { continue; }
                // 元素底部紧贴视口 → 歌词条抬升到该元素上方 +8px
                var offset = vpH - rect.top + 8;
                if (offset > maxBottom) maxBottom = offset;
            }
            // 限制不超过视口一半，防止推到屏幕中间
            return Math.min(maxBottom, vpH / 2);
        },
    };

    function escapeHtml(s) {
        if (!s) return '';
        return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }
    function fmt(s) {
        var m = Math.floor(s/60), sec = Math.floor(s%60);
        return (m<10?'0':'')+m+':'+(sec<10?'0':'')+sec;
    }

// ═══ 启动 ═══
    var hostRoot = null;

// Inject APlayer CSS
    var link = document.createElement('link');
    link.rel = 'stylesheet';
    link.href = CDN.aplayer_css;
    document.head.appendChild(link);

    verifyKey(function(ok){
        if(!ok)return;
        showConsentBanner(function(consented){
            MP._cookieConsented = consented;
            loadCSS(function(){
                var _bootXhr = new XMLHttpRequest();
                _bootXhr.open('GET', API_BASE + '?action=get-config&token=' + encodeURIComponent(API_TOKEN || API_KEY), true);
                _bootXhr.onload = function() {
                    var _hasPlaylist = false;
                    var _bootConfig = {};
                    try {
                        var _d = JSON.parse(_bootXhr.responseText);
                        if (_d.ok && _d.config) {
                            _bootConfig = _d.config;
                            if (_d.config.playlists && _d.config.playlists.length) _hasPlaylist = true;
                        }
                    } catch(e) {}
                    hostRoot = createWidget();
                    MP.root = hostRoot;
                    MP.$ = function(id){ return hostRoot.querySelector('[data-mp="' + id + '"]'); };
                    // 加载前先还原 cookie 中的位置
                    if (consented) {
                        (function(){
                            var savedPos = getCookie('mapi_pos');
                            if (savedPos) {
                                var savedSide = '';
                                var savedTop = '';
                                var p = savedPos.split(',');
                                for (var i = 0; i < p.length; i++) {
                                    var kv = p[i].split(':');
                                    if (kv[0] === 'left' || kv[0] === 'right') { savedSide = kv[0]; }
                                    if (kv[0] === 't' && kv[1] && kv[1] !== 'auto' && kv[1] !== 'initial') { savedTop = kv[1]; }
                                }
                                if (savedSide) {
                                    var _host = hostRoot && hostRoot.host;
                                    if (_host) {
                                        var mr = window.innerWidth <= 768 ? '4px' : '15px';
                                        if (savedSide === 'left') { _host.style.left = mr; _host.style.right = 'auto'; }
                                        else { _host.style.left = 'auto'; _host.style.right = mr; }
                                    }
                                    MP._side = savedSide;
                                    var rootEl = MP.$('root');
                                    if (rootEl) rootEl.style.alignItems = savedSide === 'left' ? 'flex-start' : 'flex-end';
                                    var togEl = MP.$('toggle');
                                    if (togEl) {
                                        togEl.style.left = savedSide === 'left' ? '' : 'auto';
                                        togEl.style.right = savedSide === 'left' ? 'auto' : '';
                                    }
                                }
                                if (savedTop) {
                                    var _host2 = hostRoot && hostRoot.host;
                                    if (_host2) { _host2.style.top = savedTop; _host2.style.bottom = 'auto'; }
                                }
                            }
                        })();
                    }
                    if (!_hasPlaylist) {
                        // 无歌单状态：悬浮弹窗提示，停止加载播放器
                        MP._loading = false;
                        MP._showNoPlaylistNotice();
                        var loadingEl = MP.$('toggleLoading');
                        if (loadingEl) loadingEl.style.display = 'none';
                        // 不显示播放器，隐藏 toggle
                        var tog = MP.$('toggle');
                        if (tog) tog.style.display = 'none';
                    } else {
                        MP.showToggle();
                        // 加载动画开始
                        MP._loading = true;
                        var tog = MP.$('toggle');
                        if (tog) tog.classList.add('loading');
                        loadScript(CDN.aplayer_js).then(function(){
                            MP.init();
                            MP.bindUI();
                            // 安全兜底：10秒后无论如何清除加载态
                            setTimeout(function(){
                                MP._loading = false;
                                var t = MP.$('toggle');
                                if (t) t.classList.remove('loading');
                            }, 10000);
                        });
                        }
                    };
                _bootXhr.send();
            });
        });
    });
    window.__mapiPlayer = MP;
})();