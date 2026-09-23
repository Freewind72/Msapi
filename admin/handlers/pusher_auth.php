<?php

$socket_id = $_POST['socket_id'] ?? '';
$channel_name = $_POST['channel_name'] ?? '';
if (!$socket_id || !$channel_name) { http_response_code(400); exit; }
header('Content-Type: application/json');
echo pusher_auth($socket_id, $channel_name, (string)$_SESSION['admin_id'], ['username' => $_SESSION['admin_user'], 'avatar' => $_SESSION['admin_qq'] ?? '']);
exit;