<?php

$errMsg = '';
try {
    $c = $cfg['db'];
    $errMsg = $c['type'] === 'sqlite'
        ? '无法连接数据库文件：' . ($c['path'] ?? '')
        : '无法连接数据库：' . (($c['hosts'][0] ?? '') . ':' . ($c['port'] ?? 3306));
} catch (Throwable $e) {
    $errMsg = '数据库配置错误';
}
?><!DOCTYPE html>
<html lang="zh-CN">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>数据库连接失败 — MSAPI</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
html,body{width:100%;height:100%;display:flex;align-items:center;justify-content:center;font-family:-apple-system,'PingFang SC','Microsoft YaHei',sans-serif;background:#0a0a0f;color:rgba(255,255,255,.8)}
.card{background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.08);border-radius:16px;padding:40px;max-width:440px;text-align:center}
.card .ico{width:48px;height:48px;display:block;margin:0 auto 16px}
.card h2{font-size:18px;font-weight:700;margin-bottom:8px}
.card p{font-size:13px;color:rgba(255,255,255,.5);line-height:1.6;margin-bottom:24px;word-break:break-all}
.card .btn{display:inline-block;padding:10px 24px;border-radius:8px;font-size:13px;font-weight:600;color:#fff;background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.1);text-decoration:none;cursor:pointer;transition:background .2s}
.card .btn:hover{background:rgba(255,255,255,.12)}
.card .btn-p{background:#6c5ce7;border-color:rgba(108,92,231,.3)}
.card .btn-p:hover{background:#5a4bd1}
</style>
</head>
<body>
<div class="card">
  <svg class="ico" viewBox="0 0 24 24" fill="none" stroke="#ff5252" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
  <h2>数据库连接失败</h2>
  <p><?=htmlspecialchars($errMsg)?><br><br>请检查数据库配置或服务状态后重试。</p>
  <a class="btn btn-p" onclick="location.reload()">刷新重试</a>
  <a class="btn" href="/install/">重新安装</a>
</div>
</body>
</html><?php
exit;