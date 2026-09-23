<?php

$phpVersion   = PHP_VERSION;
$phpOk        = version_compare(PHP_VERSION, '8.4.0', '>=');
$requiredExts = ['pdo', 'mbstring', 'openssl', 'fileinfo', 'gd', 'curl', 'xml'];
$extStatus    = [];

foreach ($requiredExts as $ext) {
    $extStatus[$ext] = extension_loaded($ext);
}

$allPass = $phpOk && !in_array(false, $extStatus, true);

return [
    'ok'         => true,
    'php_ok'     => $phpOk,
    'php_version'=> $phpVersion,
    'exts'       => $requiredExts,
    'ext_status' => $extStatus,
    'all_pass'   => $allPass,
];