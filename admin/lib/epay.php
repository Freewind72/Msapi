<?php defined('MAPI_ADMIN') or die('禁止直接访问');

/**
 * 易支付对接库
 * 支持彩虹易支付 / 任意易支付兼容接口
 */

function epay_config() {
    global $db;
    $r = $db->query("SELECT config_value FROM mapi_config WHERE config_key='epay'");
    if ($r && $row = $r->fetch_assoc()) {
        $cfg = json_decode($row['config_value'], true);
        if (is_array($cfg)) return $cfg;
    }
    return null;
}

function epay_available() {
    $cfg = epay_config();
    return $cfg && !empty($cfg['api_url']) && !empty($cfg['pid']) && !empty($cfg['key']);
}

/**
 * 生成易支付签名（MD5）
 */
function epay_sign($params, $key) {
    ksort($params);
    reset($params);
    $str = '';
    foreach ($params as $k => $v) {
        if ($v === '' || $k === 'sign' || $k === 'sign_type') continue;
        $str .= $k . '=' . $v . '&';
    }
    $str = rtrim($str, '&');
    return md5($str . $key);
}

/**
 * 验证回调签名
 */
function epay_verify($params, $key) {
    if (!isset($params['sign'])) return false;
    $sign = $params['sign'];
    return epay_sign($params, $key) === $sign;
}

/**
 * 构建支付提交参数
 * @param array $order 订单信息：out_trade_no, name, money, notify_url, return_url
 * @return array ['ok' => bool, 'url' => string, 'params' => array, 'error' => string]
 */
function epay_submit($order) {
    $cfg = epay_config();
    if (!$cfg) return ['ok' => false, 'error' => '易支付未配置'];

    $params = [
        'pid' => $cfg['pid'],
        'type' => $order['type'] ?? 'alipay',
        'out_trade_no' => $order['out_trade_no'],
        'notify_url' => $order['notify_url'],
        'return_url' => $order['return_url'],
        'name' => $order['name'],
        'money' => $order['money'],
    ];

    $params['sign'] = epay_sign($params, $cfg['key']);
    $params['sign_type'] = 'MD5';

    // 构建 GET 跳转 URL
    $apiUrl = rtrim($cfg['api_url'], '/');
    $url = Uri\Rfc3986\Uri::parse($apiUrl . '/submit.php')->withQuery(http_build_query($params))->toString();

    return ['ok' => true, 'url' => $url, 'params' => $params];
}

/**
 * 查询订单支付状态
 * @param string $outTradeNo 商户订单号
 * @return array ['ok' => bool, 'status' => int, 'trade_no' => string, 'error' => string]
 *   status: 0=未支付, 1=已支付
 */
function epay_query($outTradeNo) {
    $cfg = epay_config();
    if (!$cfg) return ['ok' => false, 'error' => '易支付未配置'];

    $params = [
        'pid' => $cfg['pid'],
        'out_trade_no' => $outTradeNo,
    ];

    $params['sign'] = epay_sign($params, $cfg['key']);
    $params['sign_type'] = 'MD5';

    $apiUrl = rtrim($cfg['api_url'], '/');
    $url = Uri\Rfc3986\Uri::parse($apiUrl . '/api.php')->withQuery('act=order&' . http_build_query($params))->toString();

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
    ]);
    $response = curl_exec($ch);
    $error = curl_error($ch);

    if ($error) return ['ok' => false, 'error' => $error];

    $data = json_decode($response, true);
    if (!$data || !isset($data['code'])) {
        return ['ok' => false, 'error' => '接口返回异常'];
    }

    if ((int)$data['code'] !== 1) {
        return ['ok' => false, 'error' => $data['msg'] ?? '查询失败'];
    }

    // status: 0=未支付, 1=已支付
    $status = (int)($data['status'] ?? 0);
    return [
        'ok' => true,
        'status' => $status,
        'trade_no' => $data['trade_no'] ?? '',
        'money' => $data['money'] ?? '0.00',
        'name' => $data['name'] ?? '',
    ];
}