/* rose 皮肤 —— 行为层
 *
 * 本皮肤不拖动：位置完全由后台「播放器初始位置」决定（内核 _applyDefaultPos 管），
 * 且忽略访客的拖动记忆（不可拖动的皮肤不该被别处拖出来的记忆带偏）。
 * 职责：抽屉开合、环形进度、封面转动、音量呼出、歌曲列表弹窗、模式轮转。
 */

(function (MP) {
  if (!MP) return;
  'use strict';

  // ① 不可拖动
  MP.enableDrag = function () {};

  // ② 抽屉开合。内核在同一把柄上也绑了自己的处理，两边会互相覆盖 ——
  //    算出目标状态后明确写死，并用观察器把这一次点击的意图钉住一小段时间。
  MP.togglePanel = function () {
    var root = MP.$('root');
    if (!root) return;
    var wantOpen = root.classList.contains('collapsed');   // 当前收起 → 目标是展开
    MP.open = wantOpen;
    if (!wantOpen) closeModal();                            // 收起时顺手关掉弹窗
    root.classList.toggle('collapsed', !wantOpen);

    var until = Date.now() + 500;
    var mo = new MutationObserver(function () {
      if (Date.now() > until) { mo.disconnect(); return; }
      if (root.classList.contains('collapsed') === wantOpen) root.classList.toggle('collapsed', !wantOpen);
    });
    try {
      mo.observe(root, { attributes: true, attributeFilter: ['class'] });
      setTimeout(function () { mo.disconnect(); }, 600);
    } catch (e) {}
  };

  // ③-b 内核在「配置到达 / 窗口尺寸变化」时会重新摆位。摆完必须立刻重算侧别与把柄位置，
  //      否则 data-side 停在旧值 → 收起位移方向反掉 → 把柄被推出屏幕。
  function hookReposition() {
    if (MP.__rsPosHooked) return;
    MP.__rsPosHooked = true;
    var orig = MP._applyDefaultPos;
    if (typeof orig !== 'function') return;
    MP._applyDefaultPos = function () {
      var r = orig.apply(this, arguments);
      try { syncSide(); } catch (e) {}
      return r;
    };
  }

  // ③ 停靠方向：永远听后台设置（override 绕开访客拖动记忆）
  function applyBackendSide() {
    var raw = (typeof MP._posDefault === 'function') ? MP._posDefault() : (MP._playerPos || '');
    var m = /^(left|right):\d{1,3}$/.exec(String(raw));
    if (m && typeof MP._applyDefaultPos === 'function') MP._applyDefaultPos(true, { side: m[1] });
  }

  // ④ 侧别标记 + 把柄位置
  //    侧别一律以宿主【实际的屏幕位置】为准，不信 MP._side：
  //    切左右后的首屏，配置到达时内核会把宿主挪到另一边，而 data-side 还停在旧值，
  //    收起位移就会朝反方向，把柄被推出屏幕（表现为"把柄闪一下就不见了"）。
  function syncSide() {
    var root = MP.$('root');
    var hostEl = MP._hostRoot && MP._hostRoot.host;
    var side = MP._side === 'left' ? 'left' : 'right';
    if (hostEl) {
      var vw = window.innerWidth || document.documentElement.clientWidth || 0;
      if (vw > 0) {
        var hr0 = hostEl.getBoundingClientRect();
        // ⚠ 只有几何「可用」时才反推停靠侧：宿主尚未布局完时 rect 是 0×0，
        //   中点 0 必然 < vw/2 → 一律误判成 left。切皮肤时这会让靠右的抽屉反向飞出屏幕
        //   （连把柄一起消失、再也点不开）—— 这就是那个概率性 bug 的根因。
        if (hr0.width > 0) {
          side = (hr0.left + hr0.width / 2) < vw / 2 ? 'left' : 'right';
        }
        // 宽度为 0：保留 MP._side（内核 _applyDefaultPos 写入的权威值），不做猜测
      }
    }
    if (root) {
      root.setAttribute('data-side', side);
      // 宿主离屏幕边的距离，与内核 _applyDefaultPos 的断点保持一致。
      // CSS 用它推导收起位移，否则窄屏会把把柄推出屏幕。
      root.style.setProperty('--rs-mr', (window.innerWidth <= 768 ? '4px' : '15px'));
    }
    var chev = MP.$('toggle');
    if (!chev) return;
    chev.setAttribute('data-side', side);
    // 内核会反复给把柄加 translateX 位移（它那套半隐藏逻辑），本皮肤不需要
    chev.style.setProperty('transform', 'translateY(-50%)', 'important');

    var card = MP.$('panel');
    var host = MP._hostRoot && MP._hostRoot.host;
    if (!card || !host) return;
    if (root && root.classList.contains('collapsed')) {
      // 收起时整屉在位移中，实测几何会带上位移量 → 交回 CSS（锚在 root 内缘）
      chev.style.removeProperty('left');
      chev.style.removeProperty('right');
      // 兜底自愈：方向若判反，整屉（含把柄）会全部飞出屏幕 —— 收到即时检测，翻面重来。
      // 收起态下把柄本应恰好留在屏幕边缘（CSS 留了 20px），量不到就说明方向错了。
      var cb = chev.getBoundingClientRect();
      var vw2 = window.innerWidth || document.documentElement.clientWidth || 0;
      if (vw2 > 0 && (cb.right < 2 || cb.left > vw2 - 2)) {
        var flip = (side === 'left') ? 'right' : 'left';
        root.setAttribute('data-side', flip);
        MP._side = flip;
        try { MP._applyDefaultPos(true, { side: flip }); } catch (e) {}
      }
      return;
    }
    var cr = card.getBoundingClientRect(), hr = host.getBoundingClientRect();
    var w = chev.offsetWidth || 20;
    if (side === 'left') {
      chev.style.setProperty('left', 'auto', 'important');
      chev.style.setProperty('right', Math.max(0, Math.round(hr.right - cr.right - w)) + 'px', 'important');
    } else {
      chev.style.setProperty('left', Math.max(0, Math.round(cr.left - hr.left - w)) + 'px', 'important');
      chev.style.setProperty('right', 'auto', 'important');
    }
  }

  var _showPlaylists = false;   // 皮肤自己记录「是否在看全部歌单」

  function syncPlName() {
    var nm = '\u6b4c\u5355';
    if (_showPlaylists && MP.playlists && MP.playlists.length > 1) {
      nm = '\u5168\u90e8\u6b4c\u5355';
    } else {
      var idx = MP._viewingPlaylistIndex;
      if (typeof idx !== 'number' || idx < 0) idx = MP.currentPlaylistIndex || 0;
      var pl = (MP.playlists || [])[idx];
      if (pl && pl.name) nm = pl.name;
    }
    var el = MP.$('plName');
    if (el) el.textContent = nm;
  }

  // ⑤ 封面环形进度（半径 55，viewBox 120）
  var RING_C = 2 * Math.PI * 55;
  function bindRing() {
    var fg = MP.$('ringFg');
    if (fg) {
      fg.style.strokeDasharray = RING_C;
      fg.style.strokeDashoffset = RING_C;
    }
    // APlayer 是异步加载的：挂载这一刻 MP.ap.audio 往往还不存在。
    // 以前这里直接 return，导致旋转与环形进度【一起】没挂上 —— 必须等到就绪再挂。
    attachAudio();
    var tries = 0;
    var t = setInterval(function () {
      if ((MP.ap && MP.ap.audio && !MP.ap.audio.__rsBound) || ++tries > 80) {
        clearInterval(t);
        attachAudio();
      }
    }, 250);
  }

  function attachAudio() {
    var a = MP.ap && MP.ap.audio;
    if (!a || a.__rsBound) return;
    a.__rsBound = true;
    var fg = MP.$('ringFg');
    function tick() {
      if (!fg) return;
      var d = a.duration || 0;
      var p = d ? Math.min(1, Math.max(0, a.currentTime / d)) : 0;
      fg.style.strokeDashoffset = RING_C * (1 - p);
    }
    a.addEventListener('timeupdate', tick);
    a.addEventListener('loadedmetadata', tick);
    a.addEventListener('durationchange', tick);
    // 播放/暂停实时反映到封面转动：只切换 animation-play-state（冻结角度），
    // 绝不能移除动画 —— 那会让封面转回 0 度（回正）。
    a.addEventListener('play', syncPlayState);
    a.addEventListener('playing', syncPlayState);
    a.addEventListener('pause', syncPlayState);
    a.addEventListener('ended', syncPlayState);
    tick();
    syncPlayState();
  }

  // ⑥ 播放中封面顺时针转动
  function syncPlayState() {
    var cv = MP.$('cv');
    var a = MP.ap && MP.ap.audio;
    if (cv) cv.classList.toggle('playing', !!(a && !a.paused));
  }

  // ⑦ 音量：点按钮才呼出滑杆（经典皮肤那种）
  function bindVolume() {
    var wrap = MP.$('rsVolWrap'), btn = MP.$('rsVolBtn');
    if (!wrap || !btn) return;
    btn.addEventListener('click', function (e) {
      e.stopPropagation();
      wrap.classList.toggle('open');
    });
    document.addEventListener('click', function () { wrap.classList.remove('open'); });
  }

  // ⑧ 歌曲列表弹窗（水平居中，最上层）
  function openModal() {
    var m = MP.$('rsModal'), s = MP.$('rsScrim');
    if (m) {
      // 让菜单从「列表按钮」的位置长出来（关闭时再缩回那里）。
      // 稳妥点一：不假设播放器靠左还是靠右 —— 每次打开都实测按钮位置。
      // 稳妥点二：面板收起时是 scale(.05)，getBoundingClientRect() 会缩成一个点、量不到真实盒子，
      //            所以盒子尺寸用 offsetWidth/offsetHeight（不受 transform 影响）。
      // 面板是 left:50%/top:50% + translate(-50%,-50%)，所以它的中心恒等于视口中心。
      if (window.innerWidth > 768) {
        var b = MP.$('rsList');
        if (b) {
          var br = b.getBoundingClientRect();
          var W = m.offsetWidth || 510, H = m.offsetHeight || 640;
          var ox = W / 2 + (br.left + br.width / 2 - window.innerWidth / 2);
          var oy = H / 2 + (br.top + br.height / 2 - window.innerHeight / 2);
          // 夹在盒子内（按钮被拖到屏幕边缘时，原点落在盒子外会让缩放方向很怪）
          ox = Math.max(0, Math.min(W, ox));
          oy = Math.max(0, Math.min(H, oy));
          m.style.transformOrigin = ox + 'px ' + oy + 'px';
        }
      } else {
        m.style.transformOrigin = '';   // 移动端是底部滑出、不缩放，清掉避免影响
      }
      m.classList.add('open');
      // 入场动画只在这一刻挂上（.enter 状态，约 460ms 后摘掉）。
      // 内核每次换歌都会 renderSonglist() 重建整个列表；动画若常驻在行上，
      // 每次重建都会重放一遍 —— 表现就是「选歌时列表闪几下」。
      m.classList.add('enter');
      clearTimeout(m.__rsEnterTmr);
      m.__rsEnterTmr = setTimeout(function () { m.classList.remove('enter'); }, 460);
    }
    if (s) s.classList.add('open');
    syncPlName();
  }
  function closeModal() {
    var m = MP.$('rsModal'), s = MP.$('rsScrim');
    if (m) m.classList.remove('open');
    if (s) s.classList.remove('open');
  }
  function bindModal() {
    var btn = MP.$('rsList'), close = MP.$('rsModalClose'), scrim = MP.$('rsScrim');
    if (btn) btn.addEventListener('click', openModal);
    if (close) close.addEventListener('click', closeModal);
    if (scrim) scrim.addEventListener('click', closeModal);
    // 点弹窗内部不关（防误触）
    var m = MP.$('rsModal');
    if (m) m.addEventListener('click', function (e) { e.stopPropagation(); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closeModal(); });
  }

  // ⑨-b 音量增强：内核负责增益（1x→2x→3x）并写入「增强2x/3x」文案与 .on 类；
  //      本皮肤改用颜色表达档位，所以要把文案归一成「增强」，再按增益打档位类。
  //      本处理器注册在内核之后（bindUI 先调内核的），因此跑在内核处理器之后，能覆盖它的写入。
  function bindBoost() {
    var b = MP.$('boost');
    if (!b) return;
    b.addEventListener('click', function () {
      var g = (MP._boostGain && MP._boostGain.gain) ? MP._boostGain.gain.value : 1;
      b.textContent = '\u589e\u5f3a';
      b.classList.toggle('b2', g === 2);
      b.classList.toggle('b3', g >= 3);
    });
  }

  // ⑨-c 歌词框换皮（rose 版，完全覆盖原样式）
  //   歌词框由内核创建、挂在【宿主页面】的 body 上（不在 shadow root 里），MP._css 够不着 ——
  //   只能由皮肤直接写内联样式。
  //   内核会在主题切换/重建时重写它（原样式有浅色、深色两套），所以不能只上样一次：
  //   用属性观察器盯着 style，一旦被改回就立刻补上。
  var _rsSkinAt = 0;
  function styleLrc(el) {
    if (!el) return;
    var now = Date.now();
    if (now - _rsSkinAt < 250) return;        // 防自触发回环：观察器是自己写入触发的
    _rsSkinAt = now;

    el.style.color = '#fff';                                     // 白字
    el.style.fontWeight = '700';
    el.style.fontSize = '15px';
    el.style.letterSpacing = '.2px';
    el.style.background = 'linear-gradient(160deg,#c2185b 0%,#b3123f 42%,#8c0f33 100%)';
    el.style.border = '1px solid rgba(255,255,255,.22)';
    el.style.borderRadius = '20px';
    el.style.backdropFilter = 'none';                            // 去掉原来那层毛玻璃
    el.style.webkitBackdropFilter = 'none';
    // 立体感 = 内高光 + 贴地阴影 + 主投影 + 环境辉光，四层叠出来
    el.style.boxShadow =
        'inset 0 1px 0 rgba(255,255,255,.30),'
      + ' 0 2px 6px rgba(0,0,0,.30),'
      + ' 0 10px 26px rgba(90,6,36,.45),'
      + ' 0 20px 46px rgba(194,24,91,.26)';
    el.style.textShadow = '0 1px 2px rgba(0,0,0,.38)';
  }

  // 歌词框是懒创建的（首次歌词刷新才出现），且可能被重建 —— 扫描 + 逐个挂观察器
  function watchLrc() {
    function attach(el) {
      if (!el || el.__rsWatched) return;
      el.__rsWatched = 1;
      styleLrc(el);
      try {
        new MutationObserver(function () { styleLrc(el); })
          .observe(el, { attributes: true, attributeFilter: ['style'] });
      } catch (e) {}
    }
    function scan() { attach(document.querySelector('[data-mp="lrc"]')); }
    scan();
    try { new MutationObserver(scan).observe(document.body, { childList: true, subtree: true }); } catch (e) {}
    setInterval(scan, 1500);   // 兜底：元素被换新时重新挂观察器
  }

  // ⑩ 播放模式：点一下切一种（顺序 → 单曲循环 → 随机），不要下拉菜单
  function bindMode() {
    var btn = MP.$('modeTrigger');
    if (!btn) return;
    var ORDER = ['list', 'single', 'random'];
    btn.addEventListener('click', function (e) {
      e.stopPropagation();
      var i = ORDER.indexOf(String(MP.mode || 'list'));
      MP.setMode(ORDER[(i < 0 ? 0 : i + 1) % ORDER.length]);
    });
  }

  // ⑨-d 抽屉拉伸：移动端拖头部（把手那条）改变高度。
  //   向上拖 → 全屏；向下拖 → 关闭；松手吸附到最近档位。跟手期间关过渡，松手才插值。
  function bindSheetDrag() {
    var m = MP.$('rsModal');
    if (!m) return;
    var hd = m.querySelector('.rs-modal-hd');
    if (!hd) return;
    var dragging = false, startY = 0, startH = 0, moved = false;

    hd.addEventListener('touchstart', function (e) {
      if (window.innerWidth > 768 || !m.classList.contains('open')) return;
      dragging = true; moved = false;
      startY = e.touches[0].clientY;
      startH = m.getBoundingClientRect().height;
      m.classList.add('dragging');
    }, { passive: true });

    hd.addEventListener('touchmove', function (e) {
      if (!dragging) return;
      var dy = e.touches[0].clientY - startY;
      if (Math.abs(dy) > 4 && !moved) moved = true;
      var H = window.innerHeight;
      // 往上拖 dy 为负 → 高度变大；夹在 26%~94% 之间（不能拖出屏幕外）
      var h = Math.max(H * 0.26, Math.min(H * 0.94, startH - dy));
      m.style.height = h + 'px';
      if (e.cancelable) e.preventDefault();   // 别让页面跟着一起滚
    }, { passive: false });

    function settle() {
      if (!dragging) return;
      dragging = false;
      m.classList.remove('dragging');
      var h = m.getBoundingClientRect().height;
      var H = window.innerHeight;
      m.style.height = '';                    // 交还给 CSS 类控制，由过渡吸附
      m.classList.remove('full');
      if (h < H * 0.30) { closeModal(); }      // 拖太低 → 关
      else if (h > H * 0.66) { m.classList.add('full'); }   // 拖够高 → 全屏
      // 其余回落半屏
    }
    hd.addEventListener('touchend', settle);
    hd.addEventListener('touchcancel', settle);

    // 双击把手在半屏/全屏之间切换（控制中心那种快捷操作）
    hd.addEventListener('dblclick', function () {
      if (window.innerWidth > 768) return;
      m.classList.toggle('full');
    });
  }

  // ⑩ 在内核绑定之外，补上本皮肤自己的交互
  var _origBindUI = MP.bindUI;
  MP.bindUI = function () {
    _origBindUI();

    var chev = MP.$('toggle');
    if (chev) chev.addEventListener('click', function (e) { e.stopPropagation(); MP.togglePanel(); });

    bindVolume();
    bindBoost();
    bindModal();
    bindSheetDrag();
    watchLrc();
    // 移动端：点歌后自动收起抽屉。行是内核用 innerHTML 重建的，逐行绑会随重建失效，
    // 所以在容器（常驻节点）上做事件委托。
    (function () {
      var box = MP.$('slistInner');
      if (!box) return;
      box.addEventListener('click', function (e) {
        var t = e.target;
        var row = (t && t.closest) ? t.closest('.songitem') : null;
        if (!row) return;
        if (window.innerWidth <= 768) setTimeout(function () { try { closeModal(); } catch (err) {} }, 140);
      });
    })();
    bindMode();

    // 歌单视图：默认看当前歌单的歌曲；点弹窗头部的返回箭头才切到「全部歌单」
    var _origRender = MP.renderSonglist;
    MP.renderSonglist = function () {
      if (!MP._viewingPlaylist) _showPlaylists = false;
      MP._viewingPlaylist = _showPlaylists && (MP.playlists || []).length > 1;
      _origRender();
      syncPlName();
    };
    var backBtn = MP.$('plBack');
    if (backBtn) backBtn.addEventListener('click', function () { _showPlaylists = true; });

    var _onSwitch = MP.onSwitch;
    MP.onSwitch = function () {
      _onSwitch.apply(this, arguments);
      syncPlayState();
      syncPlName();
    };
    var _toggleLrc = MP.toggleLrc;
    MP.toggleLrc = function () {
      _toggleLrc.apply(this, arguments);
      var b = MP.$('lrcToggle');
      if (b) b.classList.toggle('active', !(MP._showLrc === false));
    };

    // 默认收起：屏幕上只露把柄，点它才把抽屉拉出来。
    // （__rsTestOpen 只给本地测试页用，生产环境不存在）
    MP.open = !!window.__rsTestOpen;
    var rootEl = MP.$('root');
    if (rootEl && !MP.open) rootEl.classList.add('collapsed');
    hookReposition();
    applyBackendSide();
    syncSide();
    // 配置是异步到的：几个时间点各补一次，彻底消除首屏竞态
    setTimeout(function () { applyBackendSide(); syncSide(); }, 60);
    setTimeout(function () { applyBackendSide(); syncSide(); }, 600);
    setTimeout(function () { applyBackendSide(); syncSide(); }, 1800);

    if (MP._viewingPlaylistIndex === undefined || MP._viewingPlaylistIndex === null) {
      MP._viewingPlaylistIndex = MP.currentPlaylistIndex || 0;
    }
    MP.renderSonglist();
    syncPlName();
    bindRing();
    syncPlayState();

    window.addEventListener('resize', function () { applyBackendSide(); syncSide(); });
  };

  // ⑪ 本皮肤没有沉浸模式
  MP.enterImmersive = function () {};
  MP.exitImmersive = function () {};
  MP.toggleImmersive = function () {};
  MP.isImmersive = function () { return false; };
  MP.syncImmersive = function () {};
  MP.buildImmersive = function () {};
})(window.MP);
