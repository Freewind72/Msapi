<?php

$type    = $_GET['type'] ?? $input['type'] ?? '';
$results = [];

if (!is_dir($root . '/assets')) {
    $results['assets/'] = false;
} else {
    $results['assets/'] = is_writable($root . '/assets');
}

$results['install/'] = is_writable($self);

$cfgDir = dirname($configFile);
if (!is_dir($cfgDir)) {
    $results['config/'] = is_writable(dirname($cfgDir));
} else {
    $results['config/'] = is_writable($cfgDir);
}

if ($type === 'sqlite') {
    if (!is_dir($sqlDir)) {
        @mkdir($sqlDir, 0755, true);
        $results['assets/sql/'] = is_dir($sqlDir) && is_writable($sqlDir);
    } else {
        $results['assets/sql/'] = is_writable($sqlDir);
    }
}

return ['ok' => true, 'results' => $results];