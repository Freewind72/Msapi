<?php
// modules/api.php — 路由分发器 / 播放器模块输出
header('Content-Type: application/javascript; charset=utf-8');
header('Access-Control-Allow-Origin: *');

/** 输出 JS：统一处理 ETag / Last-Modified / 304 */
function msapi_send_js(string $body, ?int $mtime = null): void {
    $etag = '"' . md5($body) . '"';
    header('Cache-Control: no-cache, must-revalidate, max-age=0');
    header('ETag: ' . $etag);
    if ($mtime !== null) {
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $mtime) . ' GMT');
    }

    $inm = trim((string)($_SERVER['HTTP_IF_NONE_MATCH'] ?? ''));
    $ims = (int)strtotime((string)($_SERVER['HTTP_IF_MODIFIED_SINCE'] ?? ''));
    $unchanged = ($inm !== '' && ($inm === $etag || $inm === 'W/' . $etag))
        || ($inm === '' && $mtime !== null && $ims > 0 && $mtime <= $ims);

    if ($unchanged) {
        http_response_code(304);
        exit;
    }
    echo $body;
    exit;
}

/** 出错时输出一行 console.error（错误响应不做缓存） */
function msapi_send_error(string $msg, int $code = 404): void {
    http_response_code($code);
    header('Cache-Control: no-store');
    echo 'console.error("[Msapi] ' . addslashes($msg) . '");' . "\n";
    exit;
}

$route = $_GET['route'] ?? '';
$routes = [
    'router'       => 'modules/router/',
    'chiropractic' => 'modules/chiropractic/',
];

if (!isset($routes[$route])) {
    msapi_send_error('Unknown route: ' . (string)$route, 400);
}

$MODULE_DIR = $routes[$route];

// ═══ 模块资源输出: 经 PHP 读出并做 ETag 校验 ═══
if (isset($_GET['asset'])) {
    $asset    = basename((string)$_GET['asset']);   // basename 防目录穿越
    $allowed  = ['auth.js', 'widget.js', 'state.js', 'player.js', 'ui.js', 'drag.js', 'immersive.js', 'lyrics.js', 'theme.js', 'embed.js'];
    $assetAbs = dirname(__DIR__) . '/' . $MODULE_DIR . $asset;

    if (!in_array($asset, $allowed, true) || !is_file($assetAbs)) {
        msapi_send_error('module not found: ' . $asset);
    }
    msapi_send_js((string)file_get_contents($assetAbs), (int)filemtime($assetAbs));
}

// compute site root URL (strip subdirectories so _SCRIPT_BASE points to root)
$script_base = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
    . '://' . $_SERVER['HTTP_HOST']
    . rtrim(dirname($_SERVER['SCRIPT_NAME']), '/') . '/';
$script_base = preg_replace('#/modules/$#', '/', $script_base);

// 本文件相对站点根目录的路径（兼容子目录部署），用于把模块请求也导回本文件
$basePath = rtrim((string)parse_url($script_base, PHP_URL_PATH), '/');
$selfRel  = ltrim(substr((string)$_SERVER['SCRIPT_NAME'], strlen($basePath)), '/');

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
    msapi_send_js(str_replace('_PHP_EMBED_SRC_', json_encode($script_base . $selfRel . '?route=chiropractic&asset=embed.js'), $chiropractic_wrapper));
}

// ═══ Router 路由：模块化播放器启动脚本 ═══
$esc_script_base = json_encode($script_base);

$router_js = <<<'JS'
(function(){
    'use strict';

    // ═══ 幂等启动: 同一页面重复加载脚本时, 先让上一个实例失效并清理残留 DOM ═══
    (function(){
        var all = window.__mapiPlayers || [];
        for (var i = all.length - 1; i >= 0; i--) {
            if ((all[i]._.INSTANCE_ID || '') !== INSTANCE_ID) continue;   // 别的实例不动
            var prev = all[i];
            all.splice(i, 1);
            try { prev._destroyed = true; } catch(e) {}
            if (typeof prev.destroy === 'function') { try { prev.destroy(); } catch(e) {} }
            try {
                var host = prev._hostRoot && prev._hostRoot.host;
                if (host && host.parentNode) host.parentNode.removeChild(host);
            } catch(e) {}
        }
    })();

    // ═══ 强制 UTF-8 编码 ═══
    (function(){
        var m = document.createElement('meta');
        m.setAttribute('charset', 'utf-8');
        document.head.appendChild(m);
    })();

    // ═══ 禁用宿主页面滚动条 ═══
    (function(){
        var s = document.createElement('style');
        s.id = 'mapi-scrollbar-style';
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
    // 播放器实例标识：同页多个播放器各自独立记忆（脚本标签 data-player-id / id，缺省按脚本顺序编号）
    var INSTANCE_ID = (function(){
        var t = document.currentScript;
        if (!t) return '';
        var id = t.getAttribute('data-player-id') || t.getAttribute('player-id') || t.id || '';
        if (id) return String(id).replace(/[^A-Za-z0-9_-]/g, '');
        var list = document.querySelectorAll('script[src*="route=router"],script[src*="router/embed.js"]');
        if (list.length <= 1) return '';
        for (var i = 0; i < list.length; i++) { if (list[i] === t) return 'p' + (i + 1); }
        return '';
    })();

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
    window.__mapiPlayers = window.__mapiPlayers || [];
    var MP = {
        ap: null, songs: [], playlists: [], currentPlaylistIndex: 0, mode: 'list',
        open: false, lrcLines: [], _side: 'right', _showLrc: true, _lrcAnimating: false,
        _retracted: false, _retractTimer: null, _imOpen: false, _autoTheme: true,
        _themeMode: 'light', _autoplayDefault: false, _autoplayTried: false, _server: 'netease',
        _loading: false, _css: '',
        _hostRoot: null,
        _allSongs: {}, _playlistCovers: {}, _loadingPlaylists: {}, _loadFailed: {}, _timeoutFailed: {},
        _destroyed: false, _bootFailed: false, _abort: null, _bootXhr: null, _configXhr: null,
        _annTimer: null, _loadFallbackTimer: null,
    };

    // ═══ 共享工具引用（所有模块通过 MP._ 访问） ═══
    window.__mapiPlayers.push(MP);

    MP._ = {
        SCRIPT_BASE: _SCRIPT_BASE,
        INSTANCE_ID: INSTANCE_ID,
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

    // ═══ 脚本加载器（标记为播放器资源，卸载时统一清理，避免 head 无限增长） ═══
    function loadScript(url, isAsset) {
        return new Promise(function(resolve, reject) {
            var s = document.createElement('script');
            s.src = url; s.onload = resolve; s.onerror = reject;
            if (isAsset) s.setAttribute('data-mapi-asset', '1');
            document.head.appendChild(s);
        });
    }

    // ═══ 模块列表（PHP 注入，每项带 ?v=文件修改时间，避免浏览器命中旧缓存） ═══
    var MODULES = [
_PHP_MODULES_
    ];

    // ═══ 模块加载器（顺序加载，失败跳过） ═══
    function loadModules(urls, cb) {
        var i = 0, base = _SCRIPT_BASE;
        function next() {
            if (i >= urls.length) return cb();
            loadScript(base + urls[i], true).then(function() { i++; next(); }).catch(function() { i++; next(); });
        }
        next();
    }

    // ═══ 启动 ═══
    var _selfTag = document.currentScript;
    function _bootAlive() {
        if (MP._destroyed) return false;
        if (_selfTag && !_selfTag.parentNode) return false;
        return true;
    }

    // Inject APlayer CSS（重复加载时复用同一个 link，不重复插入）
    if (!document.getElementById('mapi-aplayer-css')) {
        var link = document.createElement('link');
        link.rel = 'stylesheet';
        link.id = 'mapi-aplayer-css';
        link.href = CDN.aplayer_css;
        document.head.appendChild(link);
    }

    // 多实例：模块加载期间 window.__mapiPlayer 必须指向本实例，故各实例启动串行
    window.__mapiBootChain = (window.__mapiBootChain || Promise.resolve()).then(function(){
        window.__mapiPlayer = MP;
        return new Promise(function(bootDone) {
            loadModules(MODULES, function() { bootDone(); });
        });
    }).then(function() {
        window.__mapiPlayer = MP;               // 兼容旧宿主页：指向最近加载完模块的实例
        if (!_bootAlive()) return;                      // 加载中途被卸载
        MP.verifyKey(function(ok) {
            if (!_bootAlive()) return;
            if (!ok) { MP._bootFailed = true; return; }   // 密钥无效/被限流：告知宿主页按钮可回到“加载”
            MP.showConsentBanner(function(consented) {
                if (!_bootAlive()) return;
                MP._cookieConsented = consented;
                MP.loadCSS(function() {
                    if (!_bootAlive()) return;
                    var _bootXhr = new XMLHttpRequest();
                    MP._bootXhr = _bootXhr;
                    _bootXhr.open('GET', API_BASE + '?action=get-config&token=' + encodeURIComponent(API_TOKEN || API_KEY), true);
                    _bootXhr.timeout = 20000;
                    _bootXhr.ontimeout = function() {
                        MP._bootFailed = true;                 // 配置请求超时：播放器无法启动
                        MP._loading = false;
                        if (typeof MP.$ === 'function') { var t2 = MP.$('toggle'); if (t2) t2.classList.remove('loading'); }
                    };
                    _bootXhr.onerror = function() {
                        MP._bootFailed = true;                 // 配置拉取失败：播放器无法启动
                        MP._loading = false;
                        if (typeof MP.$ === 'function') { var t0 = MP.$('toggle'); if (t0) t0.classList.remove('loading'); }
                    };
                    _bootXhr.onload = function() {
                        if (!_bootAlive()) return;
                        MP._bootXhr = null;
                        var _hasPlaylist = false;
                        try {
                            var _d = JSON.parse(_bootXhr.responseText);
                            if (_d.ok && _d.config) {
                                // 复用启动配置：避免播放器再发一次 get-config（单线程服务器上每个请求都会拖慢页面切换）
                                window.__mszeph_config = _d.config;
                                if (_d.config.playlists && _d.config.playlists.length) _hasPlaylist = true;
                            } else {
                                MP._bootFailed = true;
                            }
                        } catch(e) { MP._bootFailed = true; }
                        MP._hostRoot = MP.createWidget();
                        MP.root = MP._hostRoot;
                        MP.$ = function(id) { return MP._hostRoot ? MP._hostRoot.querySelector('[data-mp="' + id + '"]') : null; };

                        // 加载前先还原本实例的位置记忆
                        if (consented) {
                            if (typeof MP._applyDefaultPos === 'function') MP._applyDefaultPos(true, MP._readPos());
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
                            var toggleBtn2 = MP.$('toggle');
                            if (toggleBtn2) toggleBtn2.classList.add('loading');
                            loadScript(CDN.aplayer_js, true).then(function() {
                                if (!_bootAlive()) return;      // 加载中途被卸载
                                MP.init();
                                MP.bindUI();
                                // 兜底：不打断“呼吸动效”，只有后台预加载停下来后才允许清除加载态
                                if (MP._loadFallbackTimer) clearTimeout(MP._loadFallbackTimer);
                                MP._loadFallbackTimer = setTimeout(function tick() {
                                    MP._loadFallbackTimer = null;
                                    if (MP._destroyed) return;
                                    MP._fallbackRounds = (MP._fallbackRounds || 0) + 1;
                                    if (MP._preloadRunning && MP._fallbackRounds < 6) {
                                        MP._loadFallbackTimer = setTimeout(tick, 10000);
                                        return;
                                    }
                                    MP._loading = false;
                                    var t = MP.$('toggle');
                                    if (t) t.classList.remove('loading');
                                }, 10000);
                            }).catch(function() {
                                // APlayer 资源加载失败（CDN 不可达等）：清掉加载态，让按钮回到“加载”可重试
                                MP._bootFailed = true;
                                var t = MP.$('toggle');
                                if (t) t.classList.remove('loading');
                                MP._loading = false;
                                console.warn('[Msapi] APlayer 资源加载失败，播放器未启动');
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

// 模块清单：统一经本文件输出（no-store 强制不缓存），因此不再需要 ?v= 版本号
$moduleFiles = ['auth.js', 'widget.js', 'state.js', 'player.js', 'ui.js', 'drag.js', 'immersive.js', 'lyrics.js', 'theme.js'];
$moduleLines = [];
foreach ($moduleFiles as $mf) {
    $moduleLines[] = "        '" . $selfRel . '?route=' . rawurlencode($route) . '&asset=' . $mf . "',";
}
$esc_modules = implode("\n", $moduleLines);

msapi_send_js(str_replace(
    ['_PHP_SCRIPT_BASE_', '_PHP_MODULES_'],
    [$esc_script_base, $esc_modules],
    $router_js
));