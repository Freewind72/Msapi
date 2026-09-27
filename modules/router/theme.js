(function(MP){
    if (!MP) return;
    var _ = MP._;

    MP.checkTheme = function() {
        var root = MP.$('root');
        if (!root) return;
        if (MP._autoTheme) {
            var d = new Date();
            var utcH = d.getUTCHours();
            var bjH = (utcH + 8) % 24;
            if (bjH >= 18 || bjH < 6) {
                root.classList.add('dark');
            } else {
                root.classList.remove('dark');
            }
        } else {
            if (MP._themeMode === 'dark') {
                root.classList.add('dark');
            } else {
                root.classList.remove('dark');
            }
        }
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
            var isDark = root.classList.contains('dark');
            if (isDark) {
                document.documentElement.classList.add('mapi-dark');
            } else {
                document.documentElement.classList.remove('mapi-dark');
            }
        }
    };

    MP.checkGreeting = function() {
        if (!MP._cookieConsented) return;
        var d = new Date();
        var bjH = (d.getUTCHours() + 8) % 24;
        var period, text;
        if (bjH < 6) { period = 'night'; text = '\ud83c\udf19 \u591c\u6df1\u4e86\uff0c\u6ce8\u610f\u4f11\u606f'; }
        else if (bjH < 12) { period = 'morning'; text = '\u2600\ufe0f \u65e9\u4e0a\u597d'; }
        else if (bjH < 18) { period = 'afternoon'; text = '\ud83c\udf24 \u4e0b\u5348\u597d'; }
        else { period = 'evening'; text = '\ud83c\udf06 \u665a\u4e0a\u597d'; }
        var shown = _.getCookie('mapi_greeting');
        if (shown === period) return;
        MP._showGreetingToast(text, period);
    };

    MP._showGreetingToast = function(text, period) {
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
        _.setCookie('mapi_greeting', period);
    };

    MP.fetchAnnouncement = function() {
        if (!_.API_KEY && !_.API_TOKEN) return;
        var seenAt = localStorage.getItem('mapi_announcement_seen');
        fetch(_.API_BASE + '?action=get-announcement&token=' + encodeURIComponent(_.API_TOKEN || _.API_KEY), {credentials:'same-origin'})
            .then(function(r){return r.json()})
            .then(function(data){
                if (data && data.enabled && data.content) {
                    if (seenAt && parseInt(seenAt) >= (data.updated_at || 0)) return;
                    MP._announcement = data;
                    MP.showAnnouncement(data);
                }
            })
            .catch(function(){});
    };

    MP.showAnnouncement = function(data) {
        if (document.querySelector('[data-mp="annWrap"]')) return;
        var wrap = document.createElement('div');
        wrap.setAttribute('data-mp', 'annWrap');
        wrap.style.cssText = 'position:fixed;inset:0;z-index:2147483647;display:flex;align-items:center;justify-content:center;background:rgba(0,0,0,.35);opacity:0;visibility:hidden;transition:opacity .3s,visibility .3s;font-family:-apple-system,"PingFang SC","Microsoft YaHei",sans-serif';
        var isDark = MP.$('root') && MP.$('root').classList.contains('dark');
        var boxBg = isDark ? 'rgba(55,55,68,.65)' : 'rgba(255,255,255,.5)';
        var boxBor = isDark ? 'rgba(255,255,255,.06)' : 'rgba(255,255,255,.7)';
        var tCol = isDark ? '#d8d8d0' : '#1a1a2e';
        var bCol = isDark ? '#aaa' : '#444';
        var dBor = isDark ? 'rgba(255,255,255,.06)' : 'rgba(0,0,0,.08)';
        var w = window.innerWidth <= 768 ? 280 : 400;
        wrap.innerHTML = '<div class="mapi-ann-box" onclick="event.stopPropagation()" style="background:' + boxBg + ';backdrop-filter:blur(24px)saturate(200%);border:1px solid ' + boxBor + ';border-radius:16px;box-shadow:0 8px 32px rgba(0,0,0,.12);width:' + w + 'px;max-height:400px;display:flex;flex-direction:column;pointer-events:auto">'
            + '<div class="mapi-ann-divider" style="padding:24px 16px 16px;text-align:center;border-bottom:1px solid ' + dBor + '"><span class="mapi-ann-title" style="font-size:16px;font-weight:700;color:' + tCol + '">' + _.escapeHtml(data.title || '\u7cfb\u7edf\u516c\u544a') + '</span></div>'
            + '<div class="mapi-ann-content" style="flex:1;overflow-y:auto;scrollbar-width:none;-ms-overflow-style:none;padding:16px;font-size:14px;line-height:1.8;color:' + bCol + ';white-space:pre-wrap;text-align:' + (data.align === 'center' ? 'center' : 'left') + '">' + _.escapeHtml(data.content) + '</div>'
            + '<div class="mapi-ann-divider" style="border-top:1px solid ' + dBor + ';padding:16px 16px 24px;text-align:center">'
            + '<button id="mapi-ann-ok" class="mapi-ann-btn" style="width:100px;height:32px;border-radius:8px;border:1px solid ' + boxBor + ';background:rgba(255,255,255,.2);backdrop-filter:blur(8px)saturate(200%);cursor:pointer;font-size:13px;font-weight:600;color:' + tCol + ';padding:0;outline:none">\u77e5\u9053\u4e86</button></div>'
            + '</div>';
        document.body.appendChild(wrap);
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
    };

})(window.__mapiPlayer);