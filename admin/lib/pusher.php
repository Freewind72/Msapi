<?php defined('MAPI_ADMIN') or die('禁止直接访问');

require __DIR__ . '/../api/relay.php';

function _pusher_config(): array {
    global $db;
    $defaults = [
        'app_id'  => '2176543',
        'key'     => '333723b6068a283d1b6b',
        'secret'  => '9d18e6721e464e848272',
        'cluster' => 'ap3',
        'channel' => 'presence-admin-online',
    ];
    if (!$db || $db->connect_error) return $defaults;
    $r = $db->query("SELECT config_value FROM mapi_config WHERE config_key='pusher'");
    if ($r && $row = $r->fetch_assoc()) {
        $saved = json_decode($row['config_value'], true);
        if (is_array($saved)) {
            if (!empty($saved['app_id']))  $defaults['app_id']  = $saved['app_id'];
            if (!empty($saved['key']))     $defaults['key']     = $saved['key'];
            if (!empty($saved['secret']))  $defaults['secret']  = $saved['secret'];
            if (!empty($saved['cluster'])) $defaults['cluster'] = $saved['cluster'];
            if (!empty($saved['channel'])) $defaults['channel'] = $saved['channel'];
        }
    }
    return $defaults;
}

define('PUSHER_APP_ID',  _pusher_config()['app_id']);
define('PUSHER_KEY',     _pusher_config()['key']);
define('PUSHER_SECRET',  _pusher_config()['secret']);
define('PUSHER_CLUSTER', _pusher_config()['cluster']);
define('PUSHER_CHANNEL', _pusher_config()['channel']);

function pusher_trigger(string $channel, string $event, array $data): bool {
    global $RELAY;
    $ch = curl_init($RELAY['api']['pusher_base'] . '/apps/' . PUSHER_APP_ID . '/events');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode([
            'name' => $event,
            'data' => json_encode($data),
            'channels' => [$channel],
        ]),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_USERPWD => PUSHER_KEY . ':' . PUSHER_SECRET,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 5,
    ]);
    $res = curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    return $http === 202;
}

/**
 * 生成 Presence Channel 鉴权签名
 */
function pusher_auth(string $socket_id, string $channel_name, string $user_id, array $user_info = []): string {
    $channel_data = json_encode([
        'user_id' => $user_id,
        'user_info' => $user_info,
    ]);
    $string_to_sign = $socket_id . ':' . $channel_name . ':' . $channel_data;
    $signature = hash_hmac('sha256', $string_to_sign, PUSHER_SECRET);
    return json_encode([
        'auth' => PUSHER_KEY . ':' . $signature,
        'channel_data' => $channel_data,
    ]);
}

/**
 * 获取当前在线用户列表（通过 Pusher HTTP API）
 */
function pusher_online_users(): array {
    global $RELAY;
    $ch = curl_init($RELAY['api']['pusher_base'] . '/apps/' . PUSHER_APP_ID . '/channels/' . PUSHER_CHANNEL . '/users');
    curl_setopt_array($ch, [
        CURLOPT_USERPWD => PUSHER_KEY . ':' . PUSHER_SECRET,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 5,
    ]);
    $res = curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($http !== 200) return [];
    $data = json_decode($res, true);
    return $data['users'] ?? [];
}