(function(MP){
    if (!MP) return;
    var _ = MP._;

    MP.verifyKey = function(cb) {
        if (!_.API_KEY && !_.API_TOKEN) {
            var err = document.createElement('div');
            err.textContent = '\u7f3a\u5c11 API Key\uff0c\u64ad\u653e\u5668\u65e0\u6cd5\u52a0\u8f7d';
            err.style.cssText = 'position:fixed;top:16px;right:16px;z-index:2147483647;padding:10px 20px;border-radius:12px;font-size:13px;font-weight:500;background:rgba(108,92,231,.92);color:#fff;backdrop-filter:blur(8px);box-shadow:0 4px 20px rgba(0,0,0,.2);transform:translateX(120%);opacity:0;transition:all .4s cubic-bezier(.4,0,.2,1);pointer-events:none';
            document.body.appendChild(err);
            requestAnimationFrame(function(){ err.style.transform = 'translateX(0)'; err.style.opacity = '1'; });
            setTimeout(function(){ err.style.transform = 'translateX(120%)'; err.style.opacity = '0'; setTimeout(function(){ err.remove(); }, 400); }, 5000);
            cb(false); return;
        }
        if (_.API_TOKEN) { cb(true); return; }
        var x = new XMLHttpRequest();
        x.open('POST', _.API_BASE + '?action=verify-key', true);
        x.setRequestHeader('Content-Type', 'application/json');
        x.onload = function() {
            try {
                var d = JSON.parse(x.responseText);
                if (d.valid === true && d.token) _.API_TOKEN = d.token;
                cb(d.valid === true);
            } catch(e) { cb(false); }
        };
        x.onerror = function() { cb(false); };
        x.send(JSON.stringify({key: _.API_KEY}));
    };

    MP.showConsentBanner = function(callback) {
        var consented = _.getCookie('mapi_cookie_consent');
        if (consented === 'granted') { callback(true); return; }
        if (consented === 'denied') { callback(false); return; }

        var lockStyle = document.createElement('style');
        lockStyle.id = 'mapi-scroll-lock';
        lockStyle.textContent = 'html,body{overflow:hidden!important}';
        document.head.appendChild(lockStyle);

        var overlay = document.createElement('div');
        overlay.id = 'mapi-consent-overlay';
        overlay.style.cssText = 'position:fixed;inset:0;z-index:2147483647;background:rgba(0,0,0,.45);display:flex;align-items:center;justify-content:center;pointer-events:auto;font-family:-apple-system,"PingFang SC","Microsoft YaHei",sans-serif';

        var card = document.createElement('div');
        card.style.cssText = 'background:rgba(255,255,255,.55);backdrop-filter:blur(24px)saturate(200%);border:1px solid rgba(255,255,255,.7);border-radius:16px;padding:32px;max-width:340px;width:90%;box-shadow:0 8px 32px rgba(0,0,0,.12);text-align:center';

        card.innerHTML = '<div style="margin-bottom:14px"><svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#1a1a2e" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18V5l12-2v13"/><circle cx="6" cy="18" r="3"/><circle cx="18" cy="16" r="3"/></svg></div>'
            + '<h2 style="font-size:17px;font-weight:700;color:#1a1a2e;margin:0 0 8px;letter-spacing:-.01em;text-align:center;justify-content:center">\u97f3\u4e50\u64ad\u653e\u5668</h2>'
            + '<p style="font-size:13px;color:#777;line-height:1.7;margin:0 0 24px">\u64ad\u653e\u5668\u4f1a\u4f7f\u7528 cookie \u5b58\u50a8\u60a8\u7684\u64ad\u653e\u8bbe\u7f6e\uff08\u64ad\u653e\u6a21\u5f0f\u3001\u6b4c\u5355\u8fdb\u5ea6\u3001\u6b4c\u8bcd\u5f00\u5173\u7b49\uff09\u3002\u8fd9\u4e9b\u6570\u636e\u4ec5\u4fdd\u5b58\u5728\u60a8\u7684\u6d4f\u89c8\u5668\u4e2d\uff0c\u4e0d\u4f1a\u4e0a\u4f20\u5230\u670d\u52a1\u5668\u3002</p>'
            + '<div style="display:flex;gap:10px;justify-content:center">'
            + '<button id="mapi-consent-deny" style="padding:8px 20px;border-radius:8px;border:1px solid rgba(255,255,255,.35);background:rgba(255,255,255,.06);backdrop-filter:blur(8px)saturate(200%);color:#1a1a2e;font-size:13px;cursor:pointer;font-weight:600;box-shadow:0 0 10px rgba(255,255,255,.2)">\u62d2\u7edd</button>'
            + '<button id="mapi-consent-accept" style="padding:8px 20px;border-radius:8px;border:1px solid rgba(255,255,255,.4);background:rgba(0,0,0,.06);backdrop-filter:blur(8px)saturate(200%);color:#1a1a2e;font-size:13px;cursor:pointer;font-weight:700;box-shadow:0 0 10px rgba(255,255,255,.2)">\u540c\u610f</button>'
            + '</div>';

        overlay.appendChild(card);
        document.body.appendChild(overlay);

        document.getElementById('mapi-consent-accept').addEventListener('click', function() {
            _.setCookie('mapi_cookie_consent', 'granted');
            cleanup();
            callback(true);
        });
        document.getElementById('mapi-consent-deny').addEventListener('click', function() {
            _.setCookie('mapi_cookie_consent', 'denied');
            cleanup();
            callback(false);
        });

        function cleanup() {
            var ls = document.getElementById('mapi-scroll-lock');
            if (ls) ls.remove();
            if (overlay.parentNode) overlay.parentNode.removeChild(overlay);
        }
    };

})(window.__mapiPlayer);