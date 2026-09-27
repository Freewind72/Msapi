(function(MP){
    if (!MP) return;
    var _ = MP._;

    MP.init = function() {
        MP.fetchConfig();
        MP._themeInterval = setInterval(function(){ MP.checkTheme(); }, 60000);
        MP.checkGreeting();
        MP.fetchAnnouncement();
        window.addEventListener('message', function(e) {
            if (e.data && e.data.type === 'mszeph-config-update' && e.data.config) {
                var updated = false;
                if (e.data.config.auto_theme !== undefined) {
                    MP._autoTheme = e.data.config.auto_theme;
                    updated = true;
                }
                if (e.data.config.theme_mode !== undefined) {
                    MP._themeMode = e.data.config.theme_mode;
                    updated = true;
                }
                if (e.data.config.lyrics_default !== undefined) {
                    MP.applyLrcDefault(e.data.config.lyrics_default);
                }
                if (e.data.config.autoplay_default !== undefined) {
                    MP._autoplayDefault = e.data.config.autoplay_default;
                }
                if (updated) MP.checkTheme();
            }
        });
    };

    MP.fetchConfig = function() {
        if (window.__mszeph_config && window.__mszeph_config.auto_theme !== undefined) {
            MP._autoTheme = window.__mszeph_config.auto_theme;
            if (window.__mszeph_config.theme_mode !== undefined) {
                MP._themeMode = window.__mszeph_config.theme_mode;
            }
            MP.applyLrcDefault(window.__mszeph_config.lyrics_default);
            if (window.__mszeph_config.autoplay_default !== undefined) {
                MP._autoplayDefault = window.__mszeph_config.autoplay_default;
            }
            if (window.__mszeph_config.server !== undefined) {
                MP._server = window.__mszeph_config.server;
            }
            MP.checkTheme();
            var pls = (window.__mszeph_config.playlists || []).map(function(p){ p.type = p.type || 'playlist'; return p; });
            if (pls && pls.length) { MP.playlists = pls; MP.fetchAllPlaylists(); }
            else { MP._showNoPlaylistNotice(); }
            return;
        }
        if (_.API_KEY || _.API_TOKEN) {
            var xhr = new XMLHttpRequest();
            xhr.open('GET', _.API_BASE + '?action=get-config&token=' + encodeURIComponent(_.API_TOKEN || _.API_KEY), true);
            xhr.onload = function() {
                try {
                    var data = JSON.parse(xhr.responseText);
                    if (data.ok && data.config) {
                        if (data.config.auto_theme !== undefined) {
                            MP._autoTheme = data.config.auto_theme;
                        }
                        if (data.config.theme_mode !== undefined) {
                            MP._themeMode = data.config.theme_mode;
                        }
                        MP.applyLrcDefault(data.config.lyrics_default);
                        if (data.config.autoplay_default !== undefined) {
                            MP._autoplayDefault = data.config.autoplay_default;
                        }
                        if (data.config.server !== undefined) {
                            MP._server = data.config.server;
                        }
                        MP.checkTheme();
                        var pls = (data.config.playlists || []).map(function(p){ p.type = p.type || 'playlist'; return p; });
                        if (pls && pls.length) { MP.playlists = pls; MP.fetchAllPlaylists(); }
                        else { MP._showNoPlaylistNotice(); }
                    }
                } catch(e) {}
            };
            xhr.send();
        }
    };

    MP.fetchAllPlaylists = function() {
        if (!MP.playlists || MP.playlists.length === 0) {
            MP._showNoPlaylistNotice();
            return;
        }
        MP._loading = true;
        MP.$('ttl').textContent = '\u52a0\u8f7d\u4e2d...';
        var promises = [];
        MP._allSongs = {};
        MP._playlistCovers = {};
        for (var i = 0; i < MP.playlists.length; i++) {
            (function(idx){
                var pl = MP.playlists[idx];
                var plType = pl.type || 'playlist';
                if (plType === 'custom') {
                    if (pl.songs && pl.songs.length) {
                        var songPromises = pl.songs.map(function(s) {
                            var songUrl = _.API_BASE + '?action=song&id=' + encodeURIComponent(s.id) + '&server=' + encodeURIComponent(s.server || 'netease') + '&token=' + encodeURIComponent(_.API_TOKEN);
                            return fetch(songUrl, {credentials:'same-origin'}).then(function(r){return r.json()}).then(function(data){
                                if (Array.isArray(data) && data.length > 0) return data[0];
                                return null;
                            }).catch(function(){ return null; });
                        });
                        promises.push(
                            Promise.all(songPromises).then(function(results){
                                var valid = results.filter(function(r){ return r && r.url; });
                                valid.forEach(function(s){
                                    if (s.pic && !/^https?:\/\//.test(s.pic)) s.pic = _.API_BASE + s.pic + '&token=' + encodeURIComponent(_.API_TOKEN);
                                });
                                MP._allSongs[idx] = valid;
                                if (pl.cover_url) {
                                    MP._playlistCovers[idx] = pl.cover_url;
                                } else if (valid.length > 0 && valid[0].pic) {
                                    MP._playlistCovers[idx] = valid[0].pic;
                                }
                            })
                        );
                    } else {
                        MP._allSongs[idx] = [];
                        if (pl.cover_url) MP._playlistCovers[idx] = pl.cover_url;
                        promises.push(Promise.resolve());
                    }
                    return;
                }
                if (!pl.id) { MP._allSongs[idx] = []; promises.push(Promise.resolve()); return; }
                var action = plType === 'song' ? 'song' : 'playlist';
                var url = _.API_BASE + '?action=' + action + '&id=' + encodeURIComponent(pl.id) + '&limit=30&server=' + encodeURIComponent(pl.server || 'netease') + '&token=' + encodeURIComponent(_.API_TOKEN);
                promises.push(
                    fetch(url, {credentials:'same-origin'}).then(function(r){return r.json()}).then(function(data){
                        if (!Array.isArray(data) || data.length === 0) return;
                        data.forEach(function(s){
                            if (s.pic && !/^https?:\/\//.test(s.pic)) s.pic = _.API_BASE + s.pic + '&token=' + encodeURIComponent(_.API_TOKEN);
                        });
                        MP._allSongs[idx] = data;
                        if (data.length > 0 && data[0].pic) {
                            MP._playlistCovers[idx] = data[0].pic;
                        }
                    }).catch(function(){})
                );
            })(i);
        }
        Promise.all(promises).then(function(){
            MP._loading = false;
            var savedIdx = MP._loadSavedPlaylistIndex();
            if (savedIdx >= 0 && savedIdx < MP.playlists.length && MP._allSongs[savedIdx] && MP._allSongs[savedIdx].length > 0) {
                MP.currentPlaylistIndex = savedIdx;
            } else {
                var firstIdx = -1;
                for (var i = 0; i < MP.playlists.length; i++) {
                    if (MP._allSongs[i] && MP._allSongs[i].length > 0) { firstIdx = i; break; }
                }
                if (firstIdx >= 0) MP.currentPlaylistIndex = firstIdx;
                else MP.currentPlaylistIndex = 0;
            }
            MP.songs = MP._allSongs[MP.currentPlaylistIndex] || [];
            MP._viewingPlaylist = (MP.playlists && MP.playlists.length > 1);
            MP._viewingPlaylistIndex = MP.currentPlaylistIndex;
            if (MP.songs.length > 0) {
                MP.initPlayer(MP.songs);
            } else {
                MP.renderSonglist();
                MP.$('ttl').textContent = '\u6682\u65e0\u6b4c\u66f2';
            }
        }).catch(function(){
            MP._loading = false;
            MP.$('ttl').textContent = '\u52a0\u8f7d\u5931\u8d25';
        });
    };

    MP.switchPlaylist = function(index) {
        if (index === MP.currentPlaylistIndex) return;
        if (index < 0 || index >= MP.playlists.length) return;
        MP.currentPlaylistIndex = index;
        var songs = MP._allSongs[index] || [];
        MP.songs = songs;
        if (songs.length === 0) {
            MP.$('ttl').textContent = '\u6682\u65e0\u6b4c\u66f2';
            MP.songs = [];
            MP.renderSonglist();
            MP.saveState();
            return;
        }
        var audios = [];
        songs.forEach(function(s){
            if (!s.url) return;
            audios.push({name:s.name||'\u672a\u77e5', artist:s.artist||'', url:s.url, cover:s.pic||'', _lrc:s.lrc||''});
        });
        if (audios.length === 0) return;
        if (MP.ap) {
            MP.ap.list.clear();
            MP.ap.list.add(audios);
            MP.ap.list.switch(0);
            MP.ap.play();
            MP.renderSonglist();
        } else {
            MP.initPlayer(songs);
        }
        MP.saveState();
    };

    MP.initPlayer = function(songs) {
        var audios = [];
        songs.forEach(function(s){
            if (!s.url) return;
            var pic = s.pic || '';
            s.pic = pic;
            audios.push({name:s.name||'\u672a\u77e5', artist:s.artist||'', url:s.url, cover:pic, _lrc:s.lrc||''});
        });
        if (audios.length === 0) return;

        MP.ap = new APlayer({
            container: MP.$('apContainer'),
            audio: audios,
            mini: false, autoplay: MP._autoplayDefault, theme: '#888',
            loop: 'all', order: 'list', preload: 'none', volume: 1.0, mutex: true
        });
        if (MP.ap.audio) MP.ap.audio.crossOrigin = 'anonymous';

        MP.ap.on('play', function(){ MP.updateUI(); MP.renderSonglist(); MP.updatePlayBtn(true); MP.loadLrc(); MP.syncLrc(); if(MP._imOpen){MP.updateImmersiveUI();MP.updateImmersivePlayBtn(true);} var tg=MP.$('toggle');if(tg){tg.classList.remove('loading');tg.classList.add('playing');} MP.updateMediaSession(); });
        MP.ap.on('pause', function(){ MP.updateUI(); MP.updatePlayBtn(false); if(MP._imOpen){MP.updateImmersivePlayBtn(false);} var tg=MP.$('toggle');if(tg){tg.classList.remove('loading');tg.classList.remove('playing');} });
        MP.ap.on('timeupdate', function(){ MP.updateProgress(); MP.syncLrc(); if(MP._imOpen){MP.updateImmersiveProgress();MP.updateImmersiveLrc();} if(!MP._ts||Date.now()-MP._ts>5000){MP._ts=Date.now();MP.saveState();} });
        MP.ap.on('ended', function(){ MP.onEnded(); });
        MP.ap.on('listswitch', function(){ MP.onSwitch(); if(MP._imOpen)MP.updateImmersiveUI(); MP.updateMediaSession(); });
        MP.ap.on('listswitch', function(){ MP.saveState(); });

        if ('mediaSession' in navigator) {
            navigator.mediaSession.setActionHandler('play', function(){ if(MP.ap){ MP.ap.play(); } });
            navigator.mediaSession.setActionHandler('pause', function(){ if(MP.ap){ MP.ap.pause(); } });
            navigator.mediaSession.setActionHandler('previoustrack', function(){ if(MP.ap){ MP.ap.skipBack(); setTimeout(function(){ MP.onSwitch(); }, 200); } });
            navigator.mediaSession.setActionHandler('nexttrack', function(){ if(MP.ap){ MP.ap.skipForward(); setTimeout(function(){ MP.onSwitch(); }, 200); } });
        }

        if (MP._autoplayDefault && MP.ap && MP.ap.audio) {
            var _autoplayCheck = function() {
                if (MP.ap && MP.ap.audio && MP.ap.audio.paused && !MP._autoplayTried) {
                    MP._autoplayTried = true;
                    var tg = MP.$('toggle');
                    if (tg && MP.ap && MP.ap.audio && MP.ap.audio.paused) tg.classList.add('loading');
                    var _resume = function(e) {
                        if (MP.ap && MP.ap.audio) {
                            var p = MP.ap.play();
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
            setTimeout(_autoplayCheck, 200);
            MP.ap.on('play', function() { MP._autoplayTried = true; });
        }

        MP._resizeLrc = function(){
            var el = document.querySelector('[data-mp="lrc"]');
            if (el) {
                var px = MP._getOverlapBottom();
                el.style.bottom = px + 'px';
            }
            if (!MP._destroyed) MP._snap();
            var pnl = MP.$('panel');
            if (pnl) {
                var isMob = window.innerWidth <= 768;
                pnl.style.width = isMob ? '280px' : Math.min(360, window.innerWidth - 30) + 'px';
            }
            var host = MP._hostRoot.host;
            if (host) {
                var mr = window.innerWidth <= 768 ? 4 : 15;
                var mb = window.innerWidth <= 768 ? 35 : 50;
                if (!host.style.top || host.style.top === '' || host.style.top === 'initial') {
                    host.style.bottom = mb + 'px';
                }
            }
        };
        window.addEventListener('resize', MP._resizeLrc);

        MP._domTimer = null;
        MP._domObs = new MutationObserver(function(mutations){
            var lrc = document.querySelector('[data-mp="lrc"]');
            if (lrc) {
                var skip = true;
                for (var i = 0; i < mutations.length; i++) {
                    if (!lrc.contains(mutations[i].target)) { skip = false; break; }
                }
                if (skip) return;
            }
            clearTimeout(MP._domTimer);
            MP._domTimer = setTimeout(function(){
                var el = document.querySelector('[data-mp="lrc"]');
                if (el) {
                    var px = MP._getOverlapBottom();
                    el.style.bottom = px + 'px';
                }
            }, 500);
        });
        if (document.body) {
            MP._domObs.observe(document.body, { childList: true, subtree: true });
        }

        MP.renderSonglist();
        MP.showToggle();
        MP.updateUI();
        MP.updatePlayBtn(false);
        MP.$('vol').value = 1.0;
        if(MP.ap) MP.ap.volume(1.0);
        var _imVol = MP.$('imVol');
        if(_imVol) _imVol.value = 1.0;

        var _songToRestore = -1;
        if (MP._cookieConsented) {
            var _savedMode = _.getCookie('mapi_mode');
            if (_savedMode && ['list','single','random'].indexOf(_savedMode)>=0) {
                MP.mode = _savedMode;
                var _loopMap = {single:'one', list:'all', random:'none'};
                if (MP.ap) MP.ap.options.loop = _loopMap[_savedMode] || 'none';
                var _trig = MP.$('modeTrigger');
                if (_trig) { var _labels={list:'\u5217\u8868\u64ad\u653e',single:'\u5355\u66f2\u5faa\u73af',random:'\u968f\u673a\u64ad\u653e'}; _trig.textContent = _labels[_savedMode] || _savedMode; }
                var _menu = MP.$('modeMenu');
                if (_menu) { _menu.querySelectorAll('.mode-option').forEach(function(o){ o.classList.toggle('active', o.getAttribute('data-mode')===_savedMode); }); }
            }
            var _savedSong = _.getCookie('mapi_song');
            if (_savedSong && MP.ap && MP.ap.list) {
                var _n = parseInt(_savedSong);
                if (!isNaN(_n) && _n >= 0 && _n < MP.ap.list.audios.length) {
                    MP.ap.list.switch(_n);
                    _songToRestore = _n;
                }
            }
            var _savedVol = _.getCookie('mapi_volume');
            if (_savedVol) {
                var _v = parseFloat(_savedVol);
                if (!isNaN(_v) && _v >= 0 && _v <= 1) {
                    if (MP.ap) MP.ap.volume(_v);
                    MP.$('vol').value = _v;
                    if (_imVol) _imVol.value = _v;
                }
            }
        }

        if (!MP.mode || MP.mode === 'list') {
            MP.mode = 'list';
            if (MP.ap) MP.ap.options.loop = 'all';
        }

        MP._ready = true;
        if (MP._cookieConsented && MP.ap && MP.ap.list) {
            _.setCookie('mapi_song', _songToRestore >= 0 ? _songToRestore : MP.ap.list.index);
            _.setCookie('mapi_pl_index', MP.currentPlaylistIndex);
            _.setCookie('mapi_pos', MP._side);
            _.setCookie('mapi_mode', MP.mode || 'list');
            if (MP.ap.audio) _.setCookie('mapi_volume', MP.ap.audio.volume);
        }
        function _clearLoading(){
            if(MP._loading===false)return;
            MP._loading=false;
            var _t=MP.$("toggle");
            if(_t)_t.classList.remove("loading");
        }
        _clearLoading();
        if(MP.ap&&MP.ap.audio){
            MP.ap.audio.addEventListener("canplay",_clearLoading,{once:true});
            MP.ap.audio.addEventListener("error",_clearLoading,{once:true});
        }
        MP._scheduleAutoHide();
    };

    MP._initAntiDebug = function() {
        var _detected = false;
        var _suspectCount = 0;
        var _ban = function() {
            if (_detected) return;
            _detected = true;
            if (MP.ap && MP.ap.audio) {
                try { MP.ap.audio.pause(); } catch(e) {}
            }
            document.body.innerHTML = '';
            var style = document.createElement('style');
            style.textContent = 'html,body{background:#000!important;margin:0!important;padding:0!important;overflow:hidden!important}';
            document.head.appendChild(style);
            window.location.replace('about:blank');
        };

        MP._antiDbInterval = setInterval(function() {
            var t = Date.now();
            debugger;
            if (Date.now() - t > 100) {
                _suspectCount++;
                if (_suspectCount >= 2) _ban();
            } else {
                _suspectCount = Math.max(0, _suspectCount - 1);
            }
        }, 500);
    };

    MP.destroy = function() {
        if (MP.ap) {
            try { MP.ap.destroy(); } catch(e) {}
            MP.ap = null;
        }
        if (MP._themeInterval) { clearInterval(MP._themeInterval); MP._themeInterval = null; }
        if (MP._antiDbInterval) { clearInterval(MP._antiDbInterval); MP._antiDbInterval = null; }
        if (MP._retractTimer) { clearTimeout(MP._retractTimer); MP._retractTimer = null; }
        if (MP._domTimer) { clearTimeout(MP._domTimer); MP._domTimer = null; }
        if (MP._domObs) { try { MP._domObs.disconnect(); } catch(e) {} MP._domObs = null; }
        if (MP._resizeLrc) { window.removeEventListener('resize', MP._resizeLrc); MP._resizeLrc = null; }
        if (MP._snapTmr) { clearTimeout(MP._snapTmr); MP._snapTmr = null; }
        if (MP._autoCalibrateTmr) { clearTimeout(MP._autoCalibrateTmr); MP._autoCalibrateTmr = null; }
        if (MP._lrcAnimTmr) { clearTimeout(MP._lrcAnimTmr); MP._lrcAnimTmr = null; }
        var lrcEl = document.querySelector('[data-mp="lrc"]');
        if (lrcEl) lrcEl.remove();
        var hostEl = MP._hostRoot ? MP._hostRoot.host : null;
        if (hostEl && hostEl.parentNode) hostEl.parentNode.removeChild(hostEl);
        MP._destroyed = true;
    };

    MP.bindUI = function() {
        MP.$('playBtn').addEventListener('click', function(){ if(MP.ap) MP.ap.toggle(); });
        MP.$('prevBtn').addEventListener('click', function(){ if(MP.ap){MP.ap.skipBack();setTimeout(function(){MP.onSwitch();},200);} });
        MP.$('nextBtn').addEventListener('click', function(){ if(MP.ap){MP.ap.skipForward();setTimeout(function(){MP.onSwitch();},200);} });
        MP.$('pbar').addEventListener('click', function(e){
            if(!MP.ap) return;
            var r = this.getBoundingClientRect();
            MP.ap.seek((e.clientX - r.left) / r.width * MP.ap.audio.duration);
        });
        MP.$('vol').addEventListener('input', function(){ if(MP.ap) MP.ap.volume(this.value); });
        MP.$('vol').addEventListener('change', function(){ MP.saveState(); });

        MP._boostOn = false;
        MP._boostCtx = null;
        MP._boostSource = null;
        MP._boostGain = null;
        MP._setupBoost = function(){
            try {
                var AudioCtx = window.AudioContext || window.webkitAudioContext;
                if (!AudioCtx || !MP.ap || !MP.ap.audio) return false;
                if (MP._boostCtx && MP._boostCtx.state === 'suspended') MP._boostCtx.resume();
                if (MP._boostSource && MP.ap.audio !== MP._boostAudioEl) {
                    try { MP._boostSource.disconnect(); } catch(e) {}
                    try { MP._boostGain.disconnect(); } catch(e) {}
                    MP._boostSource = null;
                    MP._boostGain = null;
                }
                if (MP._boostSource) return true;
                if (!MP._boostCtx) MP._boostCtx = new AudioCtx();
                MP._boostSource = MP._boostCtx.createMediaElementSource(MP.ap.audio);
                MP._boostGain = MP._boostCtx.createGain();
                MP._boostGain.gain.value = MP._boostOn ? 2.0 : 1.0;
                MP._boostSource.connect(MP._boostGain);
                MP._boostGain.connect(MP._boostCtx.destination);
                MP._boostAudioEl = MP.ap.audio;
                return true;
            } catch(e) {
                console.warn('[\u589e\u5f3a] WebAudio \u8bbe\u7f6e\u5931\u8d25:', e);
                return false;
            }
        };
        var boostBtn = MP.$('boost');
        if (boostBtn) boostBtn.addEventListener('click', function(){
            if (!MP._setupBoost()) return;
            var levels = [1.0, 2.0, 3.0];
            var labels = ['\u589e\u5f3a', '\u589e\u5f3a2x', '\u589e\u5f3a3x'];
            var idx = levels.indexOf(MP._boostGain.gain.value);
            idx = (idx + 1) % levels.length;
            MP._boostGain.gain.value = levels[idx];
            MP._boostOn = idx > 0;
            this.classList.toggle('on', MP._boostOn);
            this.textContent = labels[idx];
        });

        MP.bindModeSelect();
        MP.$('overlay').addEventListener('click', function(){ MP.togglePanel(); });
        MP.$('overlay').addEventListener('touchend', function(e){ e.preventDefault(); MP.togglePanel(); });

        var lrcBtn = MP.$('lrcToggle');
        if (lrcBtn) lrcBtn.addEventListener('click', function(){ MP.toggleLrc(); });

        var imBtn = MP.$('immersiveBtn');
        if (imBtn) imBtn.addEventListener('click', function(){ MP.toggleImmersive(); });
        var imClose = MP.$('imClose');
        if (imClose) imClose.addEventListener('click', function(){ MP.closeImmersive(); });
        var imPlay = MP.$('imPlay');
        if (imPlay) imPlay.addEventListener('click', function(){ if(MP.ap) MP.ap.toggle(); });
        var imPrev = MP.$('imPrev');
        if (imPrev) imPrev.addEventListener('click', function(){ if(MP.ap){MP.ap.skipBack();setTimeout(function(){MP.onSwitch();if(MP._imOpen)MP.updateImmersiveUI();},200);} });
        var imNext = MP.$('imNext');
        if (imNext) imNext.addEventListener('click', function(){ if(MP.ap){MP.ap.skipForward();setTimeout(function(){MP.onSwitch();if(MP._imOpen)MP.updateImmersiveUI();},200);} });
        var imPbar = MP.$('imPbar');
        if (imPbar) imPbar.addEventListener('click', function(e){
            if(!MP.ap) return;
            var r = this.getBoundingClientRect();
            MP.ap.seek((e.clientX - r.left) / r.width * MP.ap.audio.duration);
        });
        var imVol = MP.$('imVol');
        if (imVol) { imVol.addEventListener('input', function(){ if(MP.ap) MP.ap.volume(this.value); }); imVol.addEventListener('change', function(){ MP.saveState(); }); }

        var slistTrigger = MP.$('imSlistTrigger');
        var slistDropdown = MP.$('imSlist');
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

        var backBtns = [MP.$('plBack'), MP.$('imPlBack')].filter(function(e){return e;});
        backBtns.forEach(function(btn){
            btn.addEventListener('click', function(e){
                e.stopPropagation();
                MP._viewingPlaylist = true;
                MP._renderWithFade();
            });
        });

        var imModeBtn = MP.$('imModeBtn');
        if (imModeBtn) {
            imModeBtn.addEventListener('click', function(){
                var order = ['list','single','random'];
                var idx = order.indexOf(MP.mode);
                var next = order[(idx + 1) % order.length];
                MP.setMode(next);
                MP.updateImmersiveModeBtn();
            });
        }

        MP.enableDrag();
    };

    MP.onEnded = function() {
        if (MP.mode !== 'random') return;
        var total = MP.ap.list.audios.length;
        var cur = MP.ap.list.index;
        var next = Math.floor(Math.random() * total);
        if (next === cur && total > 1) next = (next + 1) % total;
        MP.ap.list.switch(next);
        setTimeout(function(){ MP.onSwitch(); }, 100);
    };

    MP.onSwitch = function() {
        MP.updateUI();
        MP.lrcLines = [];
        MP.loadLrc();
        if (MP._imOpen) MP.updateImmersiveLrc();
        setTimeout(function(){ MP.renderSonglist(); }, 80);
    };

    MP.updateMediaSession = function() {
        if (!('mediaSession' in navigator) || !MP.ap || !MP.ap.list) return;
        var idx = MP.ap.list.index;
        var info = MP.ap.list.audios[idx];
        if (!info) return;
        navigator.mediaSession.metadata = new MediaMetadata({
            title: info.name || '\u672a\u77e5',
            artist: info.artist || '',
            artwork: info.cover ? [{ src: info.cover, sizes: '512x512', type: 'image/jpeg' }] : []
        });
    };

})(window.__mapiPlayer);