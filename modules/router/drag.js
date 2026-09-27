(function(MP){
    if (!MP) return;
    var _ = MP._;

    MP.enableDrag = function() {
        var tog = MP.$('toggle'), ox = 0, oy = 0, _drag = false, _moved = false;
        var _sx = 0, _sy = 0, THRESH = 5, _actionHandled = false, _dragMinTop = -Infinity;
        function _start(cx, cy) {
            _drag = true; _moved = false; _sx = cx; _sy = cy;
            MP._hostRoot.host.style.transition = 'none'; _actionHandled = false;
            var r = MP._hostRoot.host.getBoundingClientRect(); ox = cx - r.left; oy = cy - r.top;
            _dragMinTop = -Infinity;
            if (MP.open) {
                var pnl = MP.$('panel');
                if (pnl) {
                    var pr = pnl.getBoundingClientRect();
                    _dragMinTop = 4 - (pr.top - r.top);
                }
            }
        }
        function _mv(e) {
            var dx = e.clientX - _sx, dy = e.clientY - _sy;
            if (dx * dx + dy * dy < THRESH * THRESH) return;
            _moved = true;
            MP._hostRoot.host.style.left = (e.clientX - ox) + 'px'; MP._hostRoot.host.style.right = 'auto';
            var ty = e.clientY - oy;
            if (ty < _dragMinTop) ty = _dragMinTop;
            MP._hostRoot.host.style.bottom = 'auto'; MP._hostRoot.host.style.top = ty + 'px';
        }
        function _up() {
            _actionHandled = true;
            document.removeEventListener('mousemove', _mv); document.removeEventListener('mouseup', _up);
            if (!_moved) { _drag = false; MP.togglePanel(); return; }
            MP._snap(); _drag = false;
        }
        function _tm(e) {
            e.preventDefault(); var t = e.touches[0];
            if (!_moved) { var dx = t.clientX - _sx, dy = t.clientY - _sy; if (dx * dx + dy * dy < THRESH * THRESH) return; _moved = true; }
            MP._hostRoot.host.style.left = (t.clientX - ox) + 'px'; MP._hostRoot.host.style.right = 'auto';
            var ty = t.clientY - oy;
            if (ty < _dragMinTop) ty = _dragMinTop;
            MP._hostRoot.host.style.bottom = 'auto'; MP._hostRoot.host.style.top = ty + 'px';
        }
        function _te() {
            _actionHandled = true;
            tog.removeEventListener('touchmove', _tm); tog.removeEventListener('touchend', _te);
            if (!_moved) { _drag = false; MP.togglePanel(); return; }
            MP._snap(); _drag = false;
        }
        tog.addEventListener('touchstart', function(e) {
            MP._cancelAutoHide();
            _start(e.touches[0].clientX, e.touches[0].clientY);
            tog.addEventListener('touchmove', _tm, {passive: false});
            tog.addEventListener('touchend', _te);
        });
        tog.addEventListener('mousedown', function(e) {
            if (e.button === 0 && !('ontouchstart' in window)) {
                MP._cancelAutoHide();
                _start(e.clientX, e.clientY);
                document.addEventListener('mousemove', _mv);
                document.addEventListener('mouseup', _up);
            }
        });
        tog.addEventListener('click', function(e) {
            if (!_moved && !_actionHandled) { _actionHandled = true; MP.togglePanel(); }
        });
        tog.addEventListener('mouseenter', function() { MP._cancelAutoHide(); });
        tog.addEventListener('mouseleave', function() { if (!MP.open) MP._scheduleAutoHide(); });
    };

    MP._snap = function() {
        var h = MP._hostRoot.host, mr = window.innerWidth <= 768 ? 4 : 15;
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
        if (h.style.top && h.style.top !== '') {
            h.style.bottom = 'auto';
            var tog = MP.$('toggle');
            if (tog) {
                var tr = tog.getBoundingClientRect();
                var toff = tr.top - h.getBoundingClientRect().top;
                var th = tr.bottom - tr.top;
                var cur = parseFloat(h.style.top);
                if (!isNaN(cur)) {
                    var minTop = mr - toff;
                    if (MP.open) {
                        var pnl = MP.$('panel');
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
        MP._side = goLeft ? 'left' : 'right';
        var root = MP.$('root');
        if (root) root.style.alignItems = goLeft ? 'flex-start' : 'flex-end';
        var tog = MP.$('toggle');
        if (tog) {
            tog.style.left = goLeft ? '' : 'auto';
            tog.style.right = goLeft ? 'auto' : '';
        }
        var panel = MP.$('panel');
        if (panel) panel.style.transformOrigin = 'bottom ' + (goLeft ? 'left' : 'right');
        MP._updateToggleTransform();
        clearTimeout(MP._snapTmr);
        MP._snapTmr = setTimeout(function(){ h.style.transition = 'none'; }, 400);
        if (!MP.open) MP._scheduleAutoHide();
        MP.saveState();
    };

    MP.setPosition = function(pos) {
        var h = MP._hostRoot.host;
        var mr = window.innerWidth <= 768 ? '4px' : '15px';
        h.style.left = pos === 'left' ? mr : 'auto';
        h.style.right = pos === 'right' ? mr : 'auto';
        MP._side = pos;
        var root = MP.$('root');
        root.style.alignItems = pos === 'left' ? 'flex-start' : 'flex-end';
        var tog = MP.$('toggle');
        if (tog) {
            tog.style.left = pos === 'left' ? '' : 'auto';
            tog.style.right = pos === 'left' ? 'auto' : '';
        }
        var panel = MP.$('panel');
        if (panel) panel.style.transformOrigin = 'bottom ' + (pos === 'left' ? 'left' : 'right');
        MP._updateToggleTransform();
        MP._scheduleAutoHide();
    };

})(window.__mapiPlayer);