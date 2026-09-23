<?php defined('MAPI_ADMIN') or die('禁止直接访问');
function csrf_token() {
    if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
    return $_SESSION['csrf_token'];
}
function csrf_require() {
    $t = $_POST['_csrf'] ?? '';
    if (!$t || !hash_equals($_SESSION['csrf_token'] ?? '', $t)) {
        http_response_code(403);
        if (strpos(implode('', headers_list()), 'application/json') !== false) {
            die(json_encode(['ok' => false, 'msg' => 'CSRF 验证失败']));
        }
        die('CSRF 验证失败');
    }
}

function login_rl(): void {
    require_once __DIR__ . '/../../assets/lib/helpers.php';
    login_rate_limit();
}

function require_login() {
    if (empty($_SESSION['admin_id'])) { header('Location: ?'); exit; }
}

function require_admin() {
    if ((($_SESSION['admin_is_admin'] ?? 99) > 1)) { header('Location: ?action=dashboard'); exit; }
}
function require_super_admin() {
    if ((($_SESSION['admin_is_admin'] ?? 99) > 0)) { header('Location: ?action=dashboard'); exit; }
}