(function(MP){
    if (!MP) return;
    var _ = MP._;

    MP.saveState = function() {
        if (!MP._ready) return;
        if (!MP._cookieConsented) return;
        if (!MP.ap || !MP.ap.list) return;
        _.setCookie('mapi_song', MP.ap.list.index);
        _.setCookie('mapi_pl_index', MP.currentPlaylistIndex < (MP.playlists?MP.playlists.length:0) ? MP.currentPlaylistIndex : 0);
        var hostRoot = MP._hostRoot;
        var _top = hostRoot && hostRoot.host ? hostRoot.host.style.top : '';
        _.setCookie('mapi_pos', MP._side + (_top && _top !== 'initial' && _top !== 'auto' ? ',t:' + _top : ''));
        _.setCookie('mapi_mode', MP.mode || 'list');
        if (MP.ap && MP.ap.audio) _.setCookie('mapi_volume', MP.ap.audio.volume);
    };

    MP.loadState = function() {
        if (!MP._cookieConsented) return;
        var idx = _.getCookie('mapi_song'), mode = _.getCookie('mapi_mode');
        if (idx && MP.ap && MP.ap.list) {
            var n = parseInt(idx);
            if (!isNaN(n) && n >= 0 && n < MP.ap.list.audios.length) {
                MP.ap.list.switch(n);
            }
        }
        var vol = _.getCookie('mapi_volume');
        if (vol) {
            var v = parseFloat(vol);
            if (!isNaN(v) && v >= 0 && v <= 1) {
                if (MP.ap) MP.ap.volume(v);
                MP.$('vol').value = v;
                var _imVol = MP.$('imVol');
                if (_imVol) _imVol.value = v;
            }
        }
        if (mode && ['list','single','random'].indexOf(mode) >= 0) { MP.setMode(mode); }
    };

    MP.setMode = function(mode) {
        MP.mode = mode;
        var labels = {list:'\u5217\u8868\u64ad\u653e', single:'\u5355\u66f2\u5faa\u73af', random:'\u968f\u673a\u64ad\u653e'};
        var trigger = MP.$('modeTrigger');
        if (trigger) { trigger.textContent = labels[mode] || mode; }
        var menu = MP.$('modeMenu');
        if (menu) {
            menu.querySelectorAll('.mode-option').forEach(function(o){
                o.classList.toggle('active', o.getAttribute('data-mode') === mode);
            });
        }
        if (MP.ap) {
            var loopMap = {single:'one', list:'all', random:'none'};
            MP.ap.options.loop = loopMap[mode] || 'none';
        }
        MP.saveState();
    };

    MP.bindModeSelect = function() {
        var trigger = MP.$('modeTrigger');
        var menu = MP.$('modeMenu');
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
                if (mode === MP.mode) { menu.classList.remove('open'); return; }
                MP.setMode(mode);
                menu.classList.remove('open');
            });
        });
        document.addEventListener('click', function(e){
            if (!trigger.contains(e.target) && !menu.contains(e.target)) {
                menu.classList.remove('open');
            }
        });
    };

    MP._loadSavedPlaylistIndex = function() {
        try {
            var idx = _.getCookie('mapi_pl_index');
            if (idx !== null && idx !== '') {
                var n = parseInt(idx);
                if (!isNaN(n) && n >= 0 && n < MP.playlists.length) return n;
            }
        } catch(e) {}
        return -1;
    };

})(window.__mapiPlayer);