<?php
/**
 * modules/api.php — 路由分发器
 * 访问方式：
 *   /modules/api.php?route=router       → 分工式音乐播放器
 *   /modules/api.php?route=chiropractic  → 脊椎健康播放器（旧版）
 */
header('Content-Type: application/javascript; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

$route = $_GET['route'] ?? '';
$routes = [
    'router'       => 'modules/router/',
    'chiropractic' => 'modules/chiropractic/',
];

if (!isset($routes[$route])) {
    echo 'console.error("[Msapi] Unknown route: ' . addslashes($route) . '");' . "\n";
    exit;
}

$MODULE_DIR = $routes[$route];

// compute site root URL (strip subdirectories so _SCRIPT_BASE points to root)
$script_base = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
    . '://' . $_SERVER['HTTP_HOST']
    . rtrim(dirname($_SERVER['SCRIPT_NAME']), '/') . '/';
$script_base = preg_replace('#/modules/$#', '/', $script_base);

// legacy-compat: embed.js variants served via GET param (e.g. api.php?embed=1)
$embed_mode = isset($_GET['embed']);

// ═══ Chiropractic 路由：直接加载单体 embed.js（非模块化） ═══
if ($route === 'chiropractic') {
    $chiropractic_wrapper = <<<'JS'
(function(){
'use strict';
var cur = document.currentScript;
var s = document.createElement('script');
s.src = _PHP_EMBED_SRC_;
if (cur) {
  var key = cur.getAttribute('key');
  var token = cur.getAttribute('token');
  var api = cur.getAttribute('api');
  var cdn_aplayer_css = cur.getAttribute('cdn-aplayer-css');
  var cdn_aplayer_js = cur.getAttribute('cdn-aplayer-js');
  if (key) s.setAttribute('key', key);
  if (token) s.setAttribute('token', token);
  if (api) s.setAttribute('api', api);
  if (cdn_aplayer_css) s.setAttribute('cdn-aplayer-css', cdn_aplayer_css);
  if (cdn_aplayer_js) s.setAttribute('cdn-aplayer-js', cdn_aplayer_js);
}
document.head.appendChild(s);
})();
JS;
    echo str_replace('_PHP_EMBED_SRC_', json_encode($script_base . $MODULE_DIR . 'embed.js'), $chiropractic_wrapper);
    exit;
}

// ═══ Router 路由：模块化播放器启动脚本 ═══
$esc_script_base = json_encode($script_base);

$router_js = <<<'JS'
(function(){
    'use strict';

    // ═══ 强制 UTF-8 编码 ═══
    (function(){
        var m = document.createElement('meta');
        m.setAttribute('charset', 'utf-8');
        document.head.appendChild(m);
    })();

    // ═══ 禁用宿主页面滚动条 ═══
    (function(){
        var s = document.createElement('style');
        s.textContent = 'html::-webkit-scrollbar{display:none}body::-webkit-scrollbar{display:none}html{scrollbar-width:none}body{scrollbar-width:none}';
        document.head.appendChild(s);
    })();

    // ═══ 站点根路径（PHP 注入，避免因 api.php 位于子目录导致路径偏移） ═══
    var _SCRIPT_BASE = _PHP_SCRIPT_BASE_;
    var API_BASE = (function(){
        var a = document.currentScript && document.currentScript.getAttribute('api');
        if (a) {
            if (/^https?:\/\//i.test(a)) return a.replace(/\/?$/, '/api.php');
            return _SCRIPT_BASE + a.replace(/\/?$/, '/api.php');
        }
        return _SCRIPT_BASE + 'admin/api/api.php';
    })();
    var API_KEY = document.currentScript ? (document.currentScript.getAttribute('key') || '') : '';
    var API_TOKEN = document.currentScript ? (document.currentScript.getAttribute('token') || '') : '';

    // ═══ CDN 中继（支持外部覆盖） ═══
    var CDN = {
        aplayer_css: (document.currentScript && document.currentScript.getAttribute('cdn-aplayer-css')) || 'https://cdn.jsdelivr.net/npm/aplayer@1.10.1/dist/APlayer.min.css',
        aplayer_js:  (document.currentScript && document.currentScript.getAttribute('cdn-aplayer-js'))  || 'https://cdn.jsdelivr.net/npm/aplayer@1.10.1/dist/APlayer.min.js',
    };

    // ═══ Cookie 持久化 ═══
    function setCookie(n, v) { try { document.cookie = n + '=' + encodeURIComponent(v) + ';path=/;max-age=31536000;SameSite=Lax' + (location.protocol === 'https:' ? ';Secure' : ''); } catch(e) {} }
    function getCookie(n) { try { var m = document.cookie.match('(^| )' + n + '=([^;]+)'); return m ? decodeURIComponent(m[2]) : ''; } catch(e) { return ''; } }
    function delCookie(n) { try { document.cookie = n + '=;path=/;max-age=0'; } catch(e) {} }

    // ═══ 工具函数 ═══
    function escapeHtml(s) {
        if (!s) return '';
        return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }
    function fmt(s) {
        var m = Math.floor(s / 60), sec = Math.floor(s % 60);
        return (m < 10 ? '0' : '') + m + ':' + (sec < 10 ? '0' : '') + sec;
    }

    // ═══ 创建共享命名空间 ═══
    var MP = window.__mapiPlayer = {
        ap: null, songs: [], playlists: [], currentPlaylistIndex: 0, mode: 'list',
        open: false, lrcLines: [], _side: 'right', _showLrc: true, _lrcAnimating: false,
        _retracted: false, _retractTimer: null, _imOpen: false, _autoTheme: true,
        _themeMode: 'light', _autoplayDefault: false, _autoplayTried: false, _server: 'netease',
        _loading: false, _css: '',
        _hostRoot: null,
    };

    // ═══ 共享工具引用（所有模块通过 MP._ 访问） ═══
    MP._ = {
        SCRIPT_BASE: _SCRIPT_BASE,
        API_BASE: API_BASE,
        API_KEY: API_KEY,
        API_TOKEN: API_TOKEN,
        CDN: CDN,
        setCookie: setCookie,
        getCookie: getCookie,
        delCookie: delCookie,
        escapeHtml: escapeHtml,
        fmt: fmt,
    };

    // ═══ 脚本加载器 ═══
    function loadScript(url) {
        return new Promise(function(resolve, reject) {
            var s = document.createElement('script');
            s.src = url; s.onload = resolve; s.onerror = reject;
            document.head.appendChild(s);
        });
    }

    // ═══ 模块列表 ═══
    var MODULES = [
        '_PHP_MODULE_DIR_auth.js',
        '_PHP_MODULE_DIR_widget.js',
        '_PHP_MODULE_DIR_state.js',
        '_PHP_MODULE_DIR_player.js',
        '_PHP_MODULE_DIR_ui.js',
        '_PHP_MODULE_DIR_drag.js',
        '_PHP_MODULE_DIR_immersive.js',
        '_PHP_MODULE_DIR_lyrics.js',
        '_PHP_MODULE_DIR_theme.js',
    ];

    // ═══ 模块加载器（顺序加载，失败跳过） ═══
    function loadModules(urls, cb) {
        var i = 0, base = _SCRIPT_BASE;
        function next() {
            if (i >= urls.length) return cb();
            loadScript(base + urls[i]).then(function() { i++; next(); }).catch(function() { i++; next(); });
        }
        next();
    }

    // ═══ 启动 ═══

    // Inject APlayer CSS
    var link = document.createElement('link');
    link.rel = 'stylesheet';
    link.href = CDN.aplayer_css;
    document.head.appendChild(link);

    loadModules(MODULES, function() {
        MP.verifyKey(function(ok) {
            if (!ok) return;
            MP.showConsentBanner(function(consented) {
                MP._cookieConsented = consented;
                MP.loadCSS(function() {
                    var _bootXhr = new XMLHttpRequest();
                    _bootXhr.open('GET', API_BASE + '?action=get-config&token=' + encodeURIComponent(API_TOKEN || API_KEY), true);
                    _bootXhr.onload = function() {
                        var _hasPlaylist = false;
                        try {
                            var _d = JSON.parse(_bootXhr.responseText);
                            if (_d.ok && _d.config) {
                                if (_d.config.playlists && _d.config.playlists.length) _hasPlaylist = true;
                            }
                        } catch(e) {}
                        MP._hostRoot = MP.createWidget();
                        MP.root = MP._hostRoot;
                        MP.$ = function(id) { return MP._hostRoot.querySelector('[data-mp="' + id + '"]'); };

                        // 加载前先还原 cookie 中的位置
                        if (consented) {
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
                                    var _host = MP._hostRoot && MP._hostRoot.host;
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
                                    var _host2 = MP._hostRoot && MP._hostRoot.host;
                                    if (_host2) { _host2.style.top = savedTop; _host2.style.bottom = 'auto'; }
                                }
                            }
                        }

                        if (!_hasPlaylist) {
                            MP._loading = false;
                            MP._showNoPlaylistNotice();
                            var loadingEl = MP.$('toggleLoading');
                            if (loadingEl) loadingEl.style.display = 'none';
                            var toggleBtn = MP.$('toggle');
                            if (toggleBtn) toggleBtn.style.display = 'none';
                        } else {
                            MP.showToggle();
                            MP._loading = true;
                            toggleBtn = MP.$('toggle');
                            if (toggleBtn) toggleBtn.classList.add('loading');
                            loadScript(CDN.aplayer_js).then(function() {
                                MP.init();
                                MP.bindUI();
                                // 安全兜底：10秒后无论如何清除加载态
                                setTimeout(function() {
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
    });

})();
JS;

echo str_replace(
    ['_PHP_SCRIPT_BASE_', '_PHP_MODULE_DIR_'],
    [$esc_script_base, $MODULE_DIR],
    $router_js
);