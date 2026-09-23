"use strict";

const I = {
    check:  'M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z',
    close:  'M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z',
};

function svgIcon(d, cls) {
    return '<svg class="' + (cls || '') + '" viewBox="0 0 24 24"><path d="' + d + '"/></svg>';
}

const $  = id => document.getElementById(id);
const Q  = (s, p) => (p || document).querySelector(s);
const QA = (s, p) => (p || document).querySelectorAll(s);

async function api(action, data) {
    const url  = data ? 'init.php' : 'init.php?action=' + encodeURIComponent(action);
    const init = data
        ? { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(data) }
        : {};
    const r = await fetch(url, init);
    return r.json();
}

function toast(text, cls) {
    const el = $('toast');
    if (!el) return;
    el.textContent = text;
    el.className  = cls + ' show';
    clearTimeout(el._t);
    el._t = setTimeout(() => el.classList.remove('show'), 3000);
}

function escapeHtml(s) {
    const d = document.createElement('div');
    d.appendChild(document.createTextNode(s));
    return d.innerHTML;
}

let step      = 1;
let dbType    = '';
let mysqlOk   = false;
let mysqlData = {};

window.addEventListener('beforeunload', e => {
    if (step >= 2 && step < 5) {
        e.preventDefault();
        e.returnValue = '';
    }
});

function movePill() {
    const act = Q('.step-dot.act');
    if (!act) return;
    const nav = $('nav');
    const pr  = nav.getBoundingClientRect();
    const ar  = act.getBoundingClientRect();
    const p   = $('pill');
    p.style.left  = (ar.left - pr.left) + 'px';
    p.style.width = ar.width + 'px';
}

function navStep(n, direction) {
    if (n < 1 || n > 5 || n > step + 1) return;
    if (n === 4 && step === 3 && dbType === 'mysql' && !mysqlOk) return;

    const prev = step;
    const dir  = direction || (n > prev ? 'forward' : 'backward');

    const oldCard = $('r' + prev);
    const newCard = $('r' + n);

    if (oldCard && newCard && prev !== n) {
        const outClass = dir === 'forward' ? 'slide-out-left' : 'slide-out-right';
        const inClass  = dir === 'forward' ? 'slide-in-right'  : 'slide-in-left';

        oldCard.classList.add(outClass);
        oldCard.addEventListener('animationend', function handler() {
            oldCard.removeEventListener('animationend', handler);
            oldCard.classList.add('hide');
            oldCard.classList.remove(outClass);
        }, { once: true });

        newCard.classList.remove('hide');
        void newCard.offsetWidth;
        newCard.classList.add(inClass);
        newCard.addEventListener('animationend', function handler() {
            newCard.removeEventListener('animationend', handler);
            newCard.classList.remove(inClass);
        }, { once: true });
    }

    step = n;

    QA('.step-dot').forEach(el => {
        el.classList.toggle('act', parseInt(el.dataset.i) === n);
    });

    movePill();

    if (n === 3) {
        const isSql = dbType === 'sqlite';
        $('r3mysql').classList.toggle('hide', isSql);
        $('r3sqlite').classList.toggle('hide', !isSql);
        $('r3actions_mysql').classList.toggle('hide', isSql);
        $('r3actions_sqlite').classList.toggle('hide', !isSql);
        $('p4t_text').textContent = isSql ? '管理员' : 'MySQL 连接';
        $('p4d').textContent      = isSql ? '设置系统管理员' : '填入数据库连接信息';
    }

    if (n === 4) {
        $('r4mysql').classList.toggle('hide', dbType !== 'mysql');
    }
}

QA('.step-dot').forEach(el => {
    el.addEventListener('click', function () {
        const i = parseInt(this.dataset.i);
        const blocked =
            (step === 1 && $('s1next').disabled) ||
            (step === 2 && !dbType) ||
            (step === 3 && dbType === 'mysql' && !mysqlOk);
        if (i > step && blocked) return;
        if (i > step + 1) return;
        const dir = i > step ? 'forward' : 'backward';
        navStep(i, dir);
    });
});

async function chkEnv() {
    $('envBox').innerHTML = '<div class="env-chip skeleton">检测中…</div>';
    const d = await api('check_env');
    let h  = '';
    let ok = d.php_ok;

    d.exts.forEach(e => {
        const s = d.ext_status[e];
        if (!s) ok = false;
        h += '<div class="env-chip ' + (s ? 'pass' : 'fail') + '">' +
            svgIcon(s ? I.check : I.close) + e + '</div>';
    });

    h = '<div class="env-chip ' + (d.php_ok ? 'pass' : 'fail') + '">' +
        svgIcon(d.php_ok ? I.check : I.close) +
        'PHP ' + d.php_version +
        '<span class="v">' + (d.php_ok ? '\u22658.4' : '<8.4') + '</span></div>' + h;

    $('envBox').innerHTML = h;

    QA('.env-chip:not(.skeleton)', $('envBox')).forEach((el, i) => {
        el.style.animationDelay = (i * 0.06) + 's';
    });

    return ok;
}

async function chkPerm() {
    const d = await api('check_writable', { action: 'check_writable', type: dbType });
    let h   = '';
    let all = true;

    for (const [p, ok] of Object.entries(d.results || {})) {
        h += '<div class="perm-chip ' + (ok ? 'ok' : 'err') + '">' +
            svgIcon(ok ? I.check : I.close) + p + '</div>';
        if (!ok) all = false;
    }

    $('permRes').innerHTML = h;

    QA('.perm-chip', $('permRes')).forEach((el, i) => {
        el.style.animationDelay = (i * 0.07) + 's';
    });

    return all;
}

(async function () {
    const e = await chkEnv();
    const p = await chkPerm();
    $('s1next').disabled = !(e && p);
    movePill();
})();

function selDb(t) {
    dbType = t;
    QA('.db-card').forEach(c => c.classList.remove('sel'));
    $(t === 'sqlite' ? 'dbS' : 'dbM').classList.add('sel');
    $('s2next').disabled = false;
}

async function testMysql() {
    $('s3test').disabled = true;
    const d = {
        action: 'test_mysql',
        host:   $('myH').value,
        port:   $('myP').value,
        user:   $('myU').value,
        pass:   $('myPw').value,
        db:     $('myDb').value,
    };
    const r = await api('', d);
    if (r.ok) {
        toast('MySQL ' + (r.version || '') + ' \u8fde\u63a5\u6210\u529f', 'ok');
        mysqlOk    = true;
        mysqlData  = d;
        $('s3next').disabled = false;
    } else {
        toast(r.error || '\u8fde\u63a5\u5931\u8d25', 'err');
        mysqlOk = false;
    }
    $('s3test').disabled = false;
}

['sqlU', 'sqlQ', 'sqlE', 'sqlP', 'sqlP2'].forEach(id => {
    const el = $(id);
    if (!el) return;
    el.addEventListener('input', () => {
        const p = $('sqlP').value;
        $('s3sqnext').disabled = !(p && p.length >= 4 && p === $('sqlP2').value && $('sqlU').value);
    });
});

async function startInstall() {
    let data;

    if (dbType === 'sqlite') {
        const p = $('sqlP').value;
        if (!p || p.length < 4) { toast('\u5bc6\u7801\u81f3\u5c114\u4f4d', 'err'); return; }
        if (p !== $('sqlP2').value) { toast('\u4e24\u6b21\u5bc6\u7801\u4e0d\u4e00\u81f4', 'err'); return; }
        data = {
            action:   'init_sqlite',
            username: $('sqlU').value || 'admin',
            qq:       $('sqlQ').value,
            email:    $('sqlE').value,
            password: p,
        };
    } else {
        const p = $('mysqlPw').value;
        if (!p || p.length < 4) { toast('\u5bc6\u7801\u81f3\u5c114\u4f4d', 'err'); return; }
        if (p !== $('mysqlPw2').value) { toast('\u4e24\u6b21\u5bc6\u7801\u4e0d\u4e00\u81f4', 'err'); return; }
        data = Object.assign({}, mysqlData, {
            action:   'init_mysql',
            username: $('mysqlU').value || 'admin',
            qq:       $('mysqlQ').value,
            email:    $('mysqlE').value,
            password: p,
        });
    }

    navStep(5);
    const t = $('termBox');
    t.innerHTML = '<div class="line">\u8fde\u63a5\u4e2d...</div>';
    $('doneArea').classList.remove('show');

    try {
        const resp = await fetch('init.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data),
        });

        if (!resp.ok) throw new Error('HTTP ' + resp.status);

        if (!resp.body) {
            const r = await resp.json();
            if (r.error) throw new Error(r.error);
            throw new Error('\u670d\u52a1\u5668\u672a\u8fd4\u56de\u6d41\u5f0f\u54cd\u5e94');
        }

        t.innerHTML = '';
        const reader = resp.body.getReader();
        const decoder = new TextDecoder();
        let buffer = '';

        while (true) {
            const { done, value } = await reader.read();
            buffer += decoder.decode(value || new Uint8Array(), { stream: !done });

            const lines = buffer.split('\n');
            buffer = lines.pop() || '';

            for (const line of lines) {
                if (!line.trim()) continue;
                let msg;
                try { msg = JSON.parse(line); } catch (e) { continue; }

                if (msg.error) {
                    t.innerHTML += '<div class="line err">' +
                        svgIcon(I.close) + ' ' + escapeHtml(msg.error) + '</div>';
                    t.innerHTML += '<span class="cursor"></span>';
                    t.scrollTop = t.scrollHeight;
                    return;
                }

                if (msg.done) {
                    t.innerHTML += '\n\n<span class="line ok">' +
                        svgIcon(I.check) + ' \u5b89\u88c5\u5b8c\u6210</span>';
                    t.innerHTML += '<span class="cursor"></span>';
                    t.scrollTop = t.scrollHeight;
                    $('doneArea').classList.add('show');
                    return;
                }

                if (msg.line) {
                    const cls = msg.line.indexOf('\u2718') !== -1 ? 'err' :
                                 msg.line.indexOf('\u2714') !== -1 ? 'ok' : '';
                    t.innerHTML += '<div class="line ' + cls + '">' + escapeHtml(msg.line) + '</div>';
                    t.scrollTop = t.scrollHeight;
                }
            }
        }
    } catch (e) {
        t.innerHTML += '<div class="line err">' +
            svgIcon(I.close) + ' ' + escapeHtml(e.message) + '</div>';
        t.innerHTML += '<span class="cursor"></span>';
        t.scrollTop = t.scrollHeight;
    }
}

if (window.matchMedia) {
    const mq = window.matchMedia('(prefers-color-scheme:dark)');
    const fn = e => document.body.classList.toggle('dark', e.matches);
    mq.addEventListener('change', fn);
    fn(mq);
}