<?php

$host = $input['host'] ?? '127.0.0.1';
$port = (int)($input['port'] ?? 3306);
$user = $input['user'] ?? 'root';
$pass = $input['pass'] ?? '';
$db   = $input['db'] ?? '';

try {
    if ($db) {
        $dsn = "mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4";
    } else {
        $dsn = "mysql:host={$host};port={$port};charset=utf8mb4";
    }
    $m = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_TIMEOUT => 5,
    ]);
    $version = $m->getAttribute(PDO::ATTR_SERVER_VERSION);
    $ver = $m->query('SELECT VERSION() v')->fetch(PDO::FETCH_ASSOC)['v'] ?? $version;
    return ['ok' => true, 'version' => $ver];
} catch (Throwable $e) {
    return ['ok' => false, 'error' => $e->getMessage()];
}