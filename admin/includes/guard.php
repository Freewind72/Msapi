<?php

// 安装验证
$installLock = dirname(dirname(__DIR__)) . '/install/install.lock';
$configFile = dirname(dirname(__DIR__)) . '/config/config.php';

if (!file_exists($configFile)) {
    header('Location: ../install/');
    exit;
}

if (file_exists($installLock)) {
    $lockLines = [];
    foreach (explode("\n", file_get_contents($installLock)) as $l) {
        $l = trim($l); if ($l === '') continue;
        if (strpos($l, ': ') !== false) {
            [$k, $v] = explode(': ', $l, 2);
            $lockLines[trim($k)] = trim($v);
        }
    }
    $sha256File = dirname(dirname(__DIR__)) . '/install/assets/sha256';
    $lockHash = file_exists($sha256File) ? trim(file_get_contents($sha256File)) : '';
    $hashOk = $lockHash !== '' && $lockHash === hash_file('sha256', $installLock);
    $sigOk = isset($lockLines['signature']) && $lockLines['signature'] === 'msapi';
    $typeOk = isset($lockLines['install_type']) && in_array($lockLines['install_type'], ['sqlite', 'mysql', 'mariadb']);
    if (!$hashOk || !$sigOk || !$typeOk) {
        header('Location: ../install/');
        exit;
    }
} else {
    header('Location: ../install/');
    exit;
}

require dirname(dirname(__DIR__)) . '/install/upgrade.php';