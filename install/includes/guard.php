<?php

error_reporting(E_ALL);
ini_set('display_errors', '1');

$installDir = dirname(__DIR__);
$root       = dirname($installDir);
$configFile = $root . '/config/config.php';
$lockFile   = $installDir . '/install.lock';

if (file_exists($configFile) && file_exists($lockFile)) {
    $lock  = file_get_contents($lockFile);
    $lines = [];

    foreach (explode("\n", $lock) as $line) {
        $line = trim($line);
        if ($line === '' || !str_contains($line, ': ')) {
            continue;
        }
        [$key, $val] = explode(': ', $line, 2);
        $lines[trim($key)] = trim($val);
    }

    $sha256File = $installDir . '/assets/sha256';
    $expectHash = file_exists($sha256File) ? trim(file_get_contents($sha256File)) : '';
    $hashOk      = $expectHash !== '' && $expectHash === hash_file('sha256', $lockFile);
    $signatureOk = isset($lines['signature']) && $lines['signature'] === 'msapi';

    if ($hashOk && $signatureOk) {
        header('Location: ../');
        exit;
    }
}