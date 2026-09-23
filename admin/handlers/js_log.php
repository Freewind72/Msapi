<?php

if ($_SERVER['REQUEST_METHOD'] !== 'POST') exit;

$log = '[' . date('H:i:s') . '] ' . ($_POST['name'] ?? '') . ': ' . ($_POST['message'] ?? '') . ' | UA:' . ($_SERVER['HTTP_USER_AGENT'] ?? '') . PHP_EOL;
@file_put_contents(__DIR__ . '/../assets/js-error.log', $log, FILE_APPEND | LOCK_EX);
exit;