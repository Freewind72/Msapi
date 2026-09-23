<?php

function get_site_url(): string {
    $proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host  = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return "{$proto}://{$host}";
}

function get_table_definitions(string $type): array {
    $file = __DIR__ . '/../sql/' . ($type === 'sqlite' ? 'sqlite.sql' : 'mysql.sql');
    if (!file_exists($file)) {
        return [];
    }
    $content = file_get_contents($file);
    if ($type !== 'sqlite') {
        $content = preg_replace('/^\s*--.*$/m', '', $content);
    }
    $tables = [];
    $blocks = preg_split('/;\s*/', $content);
    foreach ($blocks as $block) {
        $block = trim($block);
        if (!$block || strpos($block, 'CREATE TABLE') === false) {
            continue;
        }
        if (preg_match('/CREATE TABLE (?:IF NOT EXISTS )?`?(\w+)`?/i', $block, $m)) {
            $tables[$m[1]] = $block . ';';
        }
    }
    return $tables;
}

function stream_line(string $msg, int $delay = 100000): void {
    echo json_encode(['line' => $msg], JSON_UNESCAPED_UNICODE) . "\n";
    if (ob_get_level()) ob_flush();
    flush();
    usleep($delay);
}

function stream_done(): void {
    echo json_encode(['done' => true]) . "\n";
    if (ob_get_level()) ob_flush();
    flush();
}

function stream_error(string $msg): void {
    echo json_encode(['error' => $msg], JSON_UNESCAPED_UNICODE) . "\n";
    if (ob_get_level()) ob_flush();
    flush();
}