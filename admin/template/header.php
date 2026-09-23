<?php defined('MAPI_ADMIN') or die('禁止直接访问'); ?>
<?php require __DIR__ . '/../api/relay.php'; ?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>MAPI 管理后台</title>
<link href="<?= $RELAY['cdn']['bootstrap_css'] ?>" rel="stylesheet">
<link href="<?= $RELAY['cdn']['bootstrap_icons'] ?>" rel="stylesheet">
<style>
body { padding-top: 56px; }
.sidebar { position: fixed; top: 56px; bottom: 0; left: 0; z-index: 100; padding: 0; box-shadow: inset -1px 0 0 rgba(0,0,0,.1); }
.sidebar .nav-link { color: #333; }
.sidebar .nav-link.active { color: #0d6efd; }
.sidebar .nav-link:hover { background-color: #f8f9fa; }
</style>
</head>
<body>
<nav class="navbar navbar-dark bg-dark fixed-top">
<div class="container-fluid">
<a class="navbar-brand" href="?">MAPI</a>
<div class="d-flex">
<?php if (isset($_SESSION['admin_id'])): ?>
<span class="navbar-text me-3"><?= htmlspecialchars($_SESSION['admin_user'] ?? '', ENT_QUOTES) ?></span>
<a href="?action=logout" class="btn btn-outline-light btn-sm">退出/a>
<?php endif; ?>
</div>
</div>
</nav>