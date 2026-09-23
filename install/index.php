<?php require __DIR__ . '/includes/guard.php'; ?><!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title>安装 — MSAPI</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/install.css">
</head>
<body>

<div class="topbar">
    <div class="topbar-brand">
        <img class="topbar-logo" src="../favicon.ico?v=<?= filemtime(dirname(__DIR__) . '/favicon.ico') ?>" alt="MSAPI">
        <span class="topbar-title">MSAPI</span>
        <span class="topbar-sub">安装向导</span>
    </div>
    <nav class="steps" id="nav">
        <div class="step-track" id="pill"></div>
        <button class="step-dot act" data-i="1"><span>1</span><em>环境</em></button>
        <button class="step-dot" data-i="2"><span>2</span><em>选库</em></button>
        <button class="step-dot" data-i="3"><span>3</span><em>连接</em></button>
        <button class="step-dot" data-i="4"><span>4</span><em>确认</em></button>
        <button class="step-dot" data-i="5"><span>5</span><em>安装</em></button>
    </nav>
</div>

<div class="scene" id="scene">

<!-- ====== 步骤 1：环境检测 ====== -->
<section class="card" id="r1">
    <header class="card-hd">
        <div class="card-icon env-icon"><svg viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg></div>
        <div><h2>环境检测</h2><p>确认 PHP 版本与依赖扩展</p></div>
    </header>
    <div class="card-bd">
        <div class="env-grid" id="envBox"><div class="env-chip skeleton">检测中…</div></div>
        <div class="perm-section">
            <h3 class="perm-title">目录权限</h3>
            <div class="perm-list" id="permRes"></div>
        </div>
    </div>
    <footer class="card-ft">
        <button class="btn btn-primary" id="s1next" onclick="navStep(2)" disabled>继续<svg viewBox="0 0 24 24"><path d="M10 6L8.59 7.41 13.17 12l-4.58 4.59L10 18l6-6z"/></svg></button>
    </footer>
</section>

<!-- ====== 步骤 2：选择数据库 ====== -->
<section class="card hide" id="r2">
    <header class="card-hd">
        <div class="card-icon db-icon"><svg viewBox="0 0 24 24"><path d="M12 3C7.58 3 4 4.79 4 7v10c0 2.21 3.58 4 8 4s8-1.79 8-4V7c0-2.21-3.58-4-8-4zm0 2c3.87 0 6 1.5 6 2s-2.13 2-6 2-6-1.5-6-2 2.13-2 6-2zM6 17v-2.42c1.61.78 3.68 1.42 6 1.42s4.39-.64 6-1.42V17c0 .5-2.13 2-6 2s-6-1.5-6-2zm0-5v-2.42c1.61.78 3.68 1.42 6 1.42s4.39-.64 6-1.42V12c0 .5-2.13 2-6 2s-6-1.5-6-2z"/></svg></div>
        <div><h2>选择数据库</h2><p>选择一种存储引擎</p></div>
    </header>
    <div class="card-bd">
        <div class="db-grid">
            <div class="db-card" onclick="selDb('sqlite')" id="dbS">
                <svg class="db-card-ico" viewBox="0 0 24 24"><path d="M14 2H6c-1.1 0-2 .9-2 2v16c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V8l-6-6zM6 20V4h7v5h5v11H6z"/></svg>
                <h3>SQLite</h3>
                <p>单文件·零配置</p>
            </div>
            <div class="db-card" onclick="selDb('mysql')" id="dbM">
                <svg class="db-card-ico" viewBox="0 0 24 24"><path d="M12 3C7.58 3 4 4.79 4 7v10c0 2.21 3.58 4 8 4s8-1.79 8-4V7c0-2.21-3.58-4-8-4zm0 2c3.87 0 6 1.5 6 2s-2.13 2-6 2-6-1.5-6-2 2.13-2 6-2zM6 17v-2.42c1.61.78 3.68 1.42 6 1.42s4.39-.64 6-1.42V17c0 .5-2.13 2-6 2s-6-1.5-6-2zm0-5v-2.42c1.61.78 3.68 1.42 6 1.42s4.39-.64 6-1.42V12c0 .5-2.13 2-6 2s-6-1.5-6-2z"/></svg>
                <h3>MySQL</h3>
                <p>高性能·并发</p>
            </div>
        </div>
    </div>
    <footer class="card-ft">
        <button class="btn" onclick="navStep(1)"><svg viewBox="0 0 24 24"><path d="M15.41 7.41L14 6l-6 6 6 6 1.41-1.41L10.83 12z"/></svg>上一步</button>
        <button class="btn btn-primary" id="s2next" onclick="navStep(3)" disabled>继续<svg viewBox="0 0 24 24"><path d="M10 6L8.59 7.41 13.17 12l-4.58 4.59L10 18l6-6z"/></svg></button>
    </footer>
</section>

<!-- ====== 步骤 3：连接 ====== -->
<section class="card hide" id="r3">
    <header class="card-hd">
        <div class="card-icon conn-icon"><svg viewBox="0 0 24 24"><path d="M16 18H6V8h3v4.4L14.39 7.01 17.6 10.22 12.21 15.6H16v2.4zM4 2h12l4 4v14c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2zm2 2v14h14V7.83L15.17 4H6z"/></svg></div>
        <div><h2 id="p4t_text">MySQL 连接</h2><p id="p4d">填入数据库连接信息</p></div>
    </header>
    <div class="card-bd">
        <div id="r3mysql">
            <div class="form-row">
                <div class="form-g"><label>主机</label><input type="text" id="myH" value="127.0.0.1"></div>
                <div class="form-g"><label>端口</label><input type="number" id="myP" value="3306"></div>
            </div>
            <div class="form-row">
                <div class="form-g"><label>数据库</label><input type="text" id="myDb" placeholder="msapi"></div>
                <div class="form-g"><label>用户</label><input type="text" id="myU" placeholder="msapi"></div>
            </div>
            <div class="form-g"><label>密码</label><input type="password" id="myPw"></div>
        </div>
        <div id="r3sqlite" class="hide">
            <div class="form-g"><label>用户名</label><input type="text" id="sqlU" value="admin"></div>
            <div class="form-row">
                <div class="form-g"><label>QQ</label><input type="text" id="sqlQ"></div>
                <div class="form-g"><label>邮箱</label><input type="email" id="sqlE"></div>
            </div>
            <div class="form-row">
                <div class="form-g"><label>密码</label><input type="password" id="sqlP"></div>
                <div class="form-g"><label>确认</label><input type="password" id="sqlP2"></div>
            </div>
        </div>
    </div>
    <footer class="card-ft" id="r3actions_mysql">
        <button class="btn" onclick="navStep(2)"><svg viewBox="0 0 24 24"><path d="M15.41 7.41L14 6l-6 6 6 6 1.41-1.41L10.83 12z"/></svg>上一步</button>
        <button class="btn btn-accent" id="s3test" onclick="testMysql()"><svg viewBox="0 0 24 24"><path d="M16.01 7.01L10.48 12.54 8.41 10.47 7 11.88l3.48 3.48 6.94-6.94zM17 2H7C5.9 2 5 2.9 5 4v16c0 1.1.9 2 2 2h10c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm0 18H7V4h10v16z"/></svg>测试连接</button>
        <button class="btn btn-primary" id="s3next" onclick="navStep(4)" disabled>继续<svg viewBox="0 0 24 24"><path d="M10 6L8.59 7.41 13.17 12l-4.58 4.59L10 18l6-6z"/></svg></button>
    </footer>
    <footer class="card-ft hide" id="r3actions_sqlite">
        <button class="btn" onclick="navStep(2)"><svg viewBox="0 0 24 24"><path d="M15.41 7.41L14 6l-6 6 6 6 1.41-1.41L10.83 12z"/></svg>上一步</button>
        <button class="btn btn-primary" id="s3sqnext" onclick="navStep(4)" disabled>继续<svg viewBox="0 0 24 24"><path d="M10 6L8.59 7.41 13.17 12l-4.58 4.59L10 18l6-6z"/></svg></button>
    </footer>
</section>

<!-- ====== 步骤 4：确认安装 ====== -->
<section class="card hide" id="r4">
    <header class="card-hd">
        <div class="card-icon gear-icon"><svg viewBox="0 0 24 24"><path d="M19.43 12.98c.04-.32.07-.64.07-.98s-.03-.66-.07-.98l2.11-1.65c.19-.15.24-.42.12-.64l-2-3.46c-.12-.22-.39-.3-.61-.22l-2.49 1c-.52-.4-1.08-.73-1.69-.98l-.38-2.65C14.46 2.18 14.25 2 14 2h-4c-.25 0-.46.18-.49.42l-.38 2.65c-.61.25-1.17.59-1.69.98l-2.49-1c-.23-.09-.49 0-.61.22l-2 3.46c-.13.22-.07.49.12.64l2.11 1.65c-.04.32-.07.65-.07.98s.03.66.07.98l-2.11 1.65c-.19.15-.24.42-.12.64l2 3.46c.12.22.39.3.61.22l2.49-1c.52.4 1.08.73 1.69.98l.38 2.65c.03.24.24.42.49.42h4c.25 0 .46-.18.49-.42l.38-2.65c.61-.25 1.17-.59 1.69-.98l2.49 1c.23.09.49 0 .61-.22l2-3.46c.12-.22.07-.49-.12-.64l-2.11-1.65zM12 15.5c-1.93 0-3.5-1.57-3.5-3.5s1.57-3.5 3.5-3.5 3.5 1.57 3.5 3.5-1.57 3.5-3.5 3.5z"/></svg></div>
        <div><h2>确认安装</h2><p>确认信息后开始初始化</p></div>
    </header>
    <div class="card-bd">
        <div id="r4mysql" class="hide">
            <div class="form-g"><label>用户名</label><input type="text" id="mysqlU" value="admin"></div>
            <div class="form-row">
                <div class="form-g"><label>QQ</label><input type="text" id="mysqlQ"></div>
                <div class="form-g"><label>邮箱</label><input type="email" id="mysqlE"></div>
            </div>
            <div class="form-row">
                <div class="form-g"><label>密码</label><input type="password" id="mysqlPw"></div>
                <div class="form-g"><label>确认</label><input type="password" id="mysqlPw2"></div>
            </div>
        </div>
    </div>
    <footer class="card-ft" id="s4actions">
        <button class="btn" onclick="navStep(3)"><svg viewBox="0 0 24 24"><path d="M15.41 7.41L14 6l-6 6 6 6 1.41-1.41L10.83 12z"/></svg>上一步</button>
        <button class="btn btn-success" id="s5install" onclick="startInstall()"><svg viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg>开始安装</button>
    </footer>
</section>

<!-- ====== 步骤 5：安装执行 ====== -->
<section class="card hide" id="r5">
    <header class="card-hd">
        <div class="card-icon gear-icon spinning"><svg viewBox="0 0 24 24"><path d="M19.43 12.98c.04-.32.07-.64.07-.98s-.03-.66-.07-.98l2.11-1.65c.19-.15.24-.42.12-.64l-2-3.46c-.12-.22-.39-.3-.61-.22l-2.49 1c-.52-.4-1.08-.73-1.69-.98l-.38-2.65C14.46 2.18 14.25 2 14 2h-4c-.25 0-.46.18-.49.42l-.38 2.65c-.61.25-1.17.59-1.69.98l-2.49-1c-.23-.09-.49 0-.61.22l-2 3.46c-.13.22-.07.49.12.64l2.11 1.65c-.04.32-.07.65-.07.98s.03.66.07.98l-2.11 1.65c-.19.15-.24.42-.12.64l2 3.46c.12.22.39.3.61.22l2.49-1c.52.4 1.08.73 1.69.98l.38 2.65c.03.24.24.42.49.42h4c.25 0 .46-.18.49-.42l.38-2.65c.61-.25 1.17-.59 1.69-.98l2.49 1c.23.09.49 0 .61-.22l2-3.46c.12-.22.07-.49-.12-.64l-2.11-1.65zM12 15.5c-1.93 0-3.5-1.57-3.5-3.5s1.57-3.5 3.5-3.5 3.5 1.57 3.5 3.5-1.57 3.5-3.5 3.5z"/></svg></div>
        <div><h2>正在安装</h2><p>建表写入数据中…</p></div>
    </header>
    <div class="card-bd">
        <div class="term" id="termBox"></div>
    </div>
    <footer class="card-ft done-area" id="doneArea">
        <a href="../" class="btn btn-primary"><svg viewBox="0 0 24 24"><path d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z"/></svg>进入首页</a>
        <a href="../admin/" class="btn"><svg viewBox="0 0 24 24"><path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zm-6 9c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm3.1-9H8.9V6c0-1.71 1.39-3.1 3.1-3.1 1.71 0 3.1 1.39 3.1 3.1v2z"/></svg>登录后台</a>
    </footer>
</section>

</div>

<div id="toast"></div>

<script src="assets/install.js"></script>
</body>
</html>