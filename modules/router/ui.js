(function(MP){
    if (!MP) return;
    var _ = MP._;

    MP.showToggle = function() {
        var el = MP.$('toggle');
        if (el) { el.style.display = 'flex'; MP._updateToggleTransform(); }
    };

    MP._updateToggleTransform = function() {
        var tog = MP.$('toggle');
        if (!tog) return;
        if (!MP._retracted) { tog.style.transform = ''; return; }
        var dir = MP._side === 'left' ? -1 : 1;
        tog.style.transform = 'translateX(' + (dir * 70) + '%)';
    };

    MP._scheduleAutoHide = function() {
        clearTimeout(MP._retractTimer);
        MP._retracted = false;
        MP._updateToggleTransform();
        MP._retractTimer = setTimeout(function() {
            MP._retracted = true;
            MP._updateToggleTransform();
        }, 3000);
    };

    MP._cancelAutoHide = function() {
        clearTimeout(MP._retractTimer);
        MP._retracted = false;
        var tog = MP.$('toggle');
        if (tog) tog.style.transform = '';
    };

    MP._showNoPlaylistNotice = function() {
        var el = document.createElement('div');
        el.textContent = '\u8bf7\u5728\u540e\u53f0\u914d\u7f6e\u6b4c\u5355';
        el.style.cssText = 'position:fixed;top:50px;left:50%;transform:translateX(-50%);z-index:2147483647;padding:8px 14px;border-radius:6px;font-size:12px;background:rgba(255,193,7,.12);color:#f39c12;border:1px solid rgba(255,193,7,.25);backdrop-filter:blur(12px);pointer-events:none;white-space:nowrap';
        document.body.appendChild(el);
        setTimeout(function(){el.style.transition='opacity .4s';el.style.opacity='0';setTimeout(function(){el.remove()},400)},5000);
    };

    MP.applyMarquee = function(el, text) {
        if (!el) return;
        var span = el.querySelector('.mi');
        if (!span) { el.textContent = ''; span = document.createElement('span'); span.className = 'mi'; el.appendChild(span); }
        span.textContent = text;
        el.classList.add('mw');
        var parentW = el.parentElement ? el.parentElement.clientWidth : el.clientWidth;
        var singleW = span.scrollWidth;
        if (singleW > parentW) {
            span.textContent = text + '             ' + text;
            var totalW = span.scrollWidth;
            var gapW = totalW - singleW * 2;
            var cycleW = singleW + gapW;
            el.style.setProperty('--mx', '-' + cycleW + 'px');
            el.style.setProperty('--md', Math.max(cycleW / 25, 5) + 's');
        } else {
            el.style.setProperty('--md', '0s');
            el.style.setProperty('--mx', '0px');
        }
    };

    MP.updateUI = function() {
        if (!MP.ap || !MP.ap.list) return;
        var idx = MP.ap.list.index;
        var info = MP.ap.list.audios[idx];
        var cover = MP.$('cv').querySelector('img');
        if (info && info.cover) { cover.src = info.cover; cover.style.display = ''; }
        else { cover.style.display = 'none'; }
        var togCover = MP.$('toggleCover');
        var togSvg = MP.$('toggleSvg');
        if (togCover && info && info.cover) {
            if (info.cover !== MP._lastToggleCover) {
                MP._lastToggleCover = info.cover;
                togCover.style.display = 'block';
                if (togSvg) togSvg.style.display = '';
                togCover.onload = function() {};
                togCover.onerror = function() {
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
            MP._lastToggleCover = '';
        }
        MP.applyMarquee(MP.$('ttl'), info ? info.name : '\u672a\u77e5');
        MP.applyMarquee(MP.$('art'), info ? (info.artist || '') : '');
        MP.updateProgress();
    };

    MP.renderSonglist = function() {
        var innerEls = [MP.$('slistInner'), MP.$('imSlistInner')].filter(function(e){return e;});
        if (innerEls.length === 0) return;
        var hasMultiple = MP.playlists && MP.playlists.length > 1;
        var backEls = [MP.$('plBack'), MP.$('imPlBack')].filter(function(e){return e;});
        backEls.forEach(function(btn){
            if (!MP._viewingPlaylist && hasMultiple) btn.classList.add('visible');
            else btn.classList.remove('visible');
        });
        if (MP._viewingPlaylist && hasMultiple) {
            var html = '';
            for (var i = 0; i < MP.playlists.length; i++) {
                var pl = MP.playlists[i];
                var name = pl.name || ('\u6b4c\u5355' + (i + 1));
                var cover = MP._playlistCovers[i] || '';
                html += '<div class="pl-list-item" data-plidx="' + i + '">';
                html += '<span class="pl-idx">' + (i + 1) + '</span>';
                if (cover) {
                    html += '<img class="pl-cover" src="' + _.escapeHtml(cover) + '" alt="" onerror="var s=document.createElement(\'span\');s.className=\'pl-cover\';s.style.cssText=\'display:flex;align-items:center;justify-content:center;font-size:10px;background:rgba(0,0,0,.06)\';s.textContent=\'' + _.escapeHtml(name.charAt(0)) + '\';this.parentNode.replaceChild(s,this)">';
                } else {
                    html += '<span class="pl-cover" style="display:flex;align-items:center;justify-content:center;font-size:10px">' + _.escapeHtml(name.charAt(0)) + '</span>';
                }
                html += '<span class="pl-name">' + _.escapeHtml(name) + '</span>';
                html += '<span class="pl-count">' + (MP._allSongs[i] ? MP._allSongs[i].length : 0) + '\u9996</span>';
                html += '</div>';
            }
            innerEls.forEach(function(el){
                el.innerHTML = html;
                el.querySelectorAll('.pl-list-item').forEach(function(item){
                    item.addEventListener('click', function(e){
                        e.stopPropagation();
                        var plidx = parseInt(this.getAttribute('data-plidx'));
                        MP._viewingPlaylist = false;
                        MP._viewingPlaylistIndex = plidx;
                        MP._renderWithFade();
                    });
                });
            });
        } else {
            var viewSongs = MP._allSongs[MP._viewingPlaylistIndex] || [];
            if (viewSongs.length === 0) {
                innerEls.forEach(function(el){
                    el.innerHTML = '<div style="color:#999;font-size:13px;text-align:center;padding:20px 0">\u65e0\u6b4c\u66f2</div>';
                });
                return;
            }
            var isSamePL = MP._viewingPlaylistIndex === MP.currentPlaylistIndex;
            var idx = MP.ap ? MP.ap.list.index : 0;
            var html = '';
            for (var i = 0; i < viewSongs.length; i++) {
                var s = viewSongs[i];
                var active = (isSamePL && i === idx) ? ' active' : '';
                html += '<div class="songitem' + active + '" data-idx="' + i + '">';
                html += '<span class="si-idx">' + (i + 1) + '</span>';
                var _cov = s.cover || s.pic || ''; if (_cov) html += '<img class="si-cover" src="' + _.escapeHtml(_cov) + '" alt="">';
                html += '<span class="si-name">' + _.escapeHtml(s.name || '') + '</span>';
                html += '<span class="si-artist">' + _.escapeHtml(s.artist || '') + '</span>';
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
                        var targetSongs = MP._allSongs[MP._viewingPlaylistIndex] || [];
                        var targetSong = targetSongs[targetIdx];
                        if (!targetSong || !targetSong.url) return;
                        if (MP._viewingPlaylistIndex !== MP.currentPlaylistIndex) {
                            MP.currentPlaylistIndex = MP._viewingPlaylistIndex;
                            MP.songs = targetSongs;
                            MP._viewingPlaylist = false;
                            MP.saveState();
                            if (MP.ap) {
                                MP.ap.list.clear();
                                var audios = [];
                                targetSongs.forEach(function(ts){
                                    if (ts.url) audios.push({name:ts.name||'\u672a\u77e5', artist:ts.artist||'', url:ts.url, cover:ts.pic||'', _lrc:ts.lrc||''});
                                });
                                MP.ap.list.add(audios);
                                MP.ap.list.switch(targetIdx);
                                MP.ap.play();
                            } else {
                                MP.initPlayer(targetSongs);
                            }
                        } else {
                            if (targetIdx === idx) return;
                            try { MP.ap.list.switch(targetIdx); } catch(e) {}
                            setTimeout(function(){ MP.onSwitch(); }, 150);
                        }
                        MP.renderSonglist();
                    });
                });
            });
        }
    };

    MP._renderWithFade = function() {
        var innerEls = [MP.$('slistInner'), MP.$('imSlistInner')].filter(function(e){return e;});
        innerEls.forEach(function(el){ el.style.opacity = '0'; });
        setTimeout(function(){
            MP.renderSonglist();
            innerEls.forEach(function(el){ el.style.opacity = '1'; });
        }, 150);
    };

    MP.updateProgress = function() {
        if (!MP.ap || !MP.ap.audio) return;
        var a = MP.ap.audio;
        var cur = a.currentTime||0, dur = a.duration||0;
        var pct = dur > 0 ? (cur/dur*100) : 0;
        MP.$('played').style.width = pct + '%';
        MP.$('cur').textContent = _.fmt(cur);
        MP.$('dur').textContent = dur ? _.fmt(dur) : '00:00';
    };

    MP.updatePlayBtn = function(playing) {
        var svg = MP.$('playSvg');
        if (playing) {
            svg.innerHTML = '<rect x="6" y="4" width="4" height="16"/><rect x="14" y="4" width="4" height="16"/>';
        } else {
            svg.innerHTML = '<polygon points="6,4 20,12 6,20"/>';
        }
    };

    MP.togglePanel = function() {
        if (MP._loading) {
            if (MP._toastEl && MP._toastEl.parentNode) return;
            var toast = document.createElement('div');
            toast.textContent = '\u52a0\u8f7d\u4e2d\u2026';
            toast.style.cssText = 'position:fixed;top:80px;left:50%;transform:translateX(-50%) scale(0.8);z-index:2147483647;background:rgba(0,0,0,.55);backdrop-filter:blur(16px)saturate(200%);color:#fff;font-size:14px;font-weight:600;padding:10px 20px;border-radius:10px;font-family:-apple-system,"PingFang SC","Microsoft YaHei",sans-serif;opacity:0;transition:all .3s cubic-bezier(.4,0,.2,1);pointer-events:none';
            document.body.appendChild(toast);
            MP._toastEl = toast;
            void toast.offsetWidth;
            toast.style.opacity = '1';
            toast.style.transform = 'translateX(-50%) scale(1)';
            setTimeout(function(){
                toast.style.opacity = '0';
                toast.style.transform = 'translateX(-50%) scale(0.8)';
                setTimeout(function(){ if (toast.parentNode) toast.parentNode.removeChild(toast); MP._toastEl = null; }, 300);
            }, 2000);
            return;
        }
        MP.open = !MP.open;
        var pnl = MP.$('panel');
        MP.$('overlay').style.display = MP.open ? 'block' : 'none';
        if (MP.open) {
            MP._cancelAutoHide();
            pnl.classList.add('open');
            MP.updateUI();
            var pr = pnl.getBoundingClientRect();
            if (pr.top < 0) {
                var h = MP._hostRoot.host;
                var hr = h.getBoundingClientRect();
                var offset = Math.abs(pr.top) + 8;
                h.style.transition = 'top .35s cubic-bezier(.34,1.56,.64,1), left .35s cubic-bezier(.34,1.56,.64,1)';
                h.style.top = (hr.top + offset) + 'px';
                h.style.bottom = 'auto';
                clearTimeout(MP._autoCalibrateTmr);
                MP._autoCalibrateTmr = setTimeout(function(){ h.style.transition = 'none'; }, 400);
            }
        } else {
            pnl.classList.remove('open');
            MP._scheduleAutoHide();
        }
    };

})(window.__mapiPlayer);