<?php defined('MAPI_ADMIN') or die('禁止直接访问');

function s3_config(): array {
    global $db;
    $r = $db->query("SELECT config_value FROM mapi_config WHERE config_key='s3'");
    if ($r && $row = $r->fetch_assoc()) {
        $saved = json_decode($row['config_value'], true);
        if (is_array($saved)) {
            return [
                'endpoint' => $saved['endpoint'] ?? '',
                'access_key' => $saved['access_key'] ?? '',
                'secret_key' => $saved['secret_key'] ?? '',
                'bucket' => $saved['bucket'] ?? '',
                'region' => $saved['region'] ?? 'auto',
                'path_prefix' => $saved['path_prefix'] ?? '',
                'custom_domain' => $saved['custom_domain'] ?? '',
            ];
        }
    }
    return [
        'endpoint' => '',
        'access_key' => '',
        'secret_key' => '',
        'bucket' => '',
        'region' => 'auto',
        'path_prefix' => '',
        'custom_domain' => '',
    ];
}

function s3_available(): bool {
    $cfg = s3_config();
    return !empty($cfg['endpoint']) && !empty($cfg['bucket']) && !empty($cfg['access_key']) && !empty($cfg['secret_key']);
}

function s3_full_key(string $key): string {
    $cfg = s3_config();
    $prefix = trim($cfg['path_prefix'] ?? '', '/');
    return $prefix ? $prefix . '/' . ltrim($key, '/') : $key;
}

function s3_get_url(string $key): string {
    $cfg = s3_config();
    if (!empty($cfg['custom_domain'])) {
        return rtrim($cfg['custom_domain'], '/') . '/' . ltrim(s3_full_key($key), '/');
    }
    return rtrim($cfg['endpoint'], '/') . '/' . $cfg['bucket'] . '/' . ltrim(s3_full_key($key), '/');
}

function s3_sign(string $method, string $uri, string $body, array $headers, string $date): string {
    $cfg = s3_config();
    $region = $cfg['region'];
    $service = 's3';
    $scope = "$date/$region/$service/aws4_request";
    $signed = [];
    foreach ($headers as $k => $v) $signed[strtolower($k)] = trim($v);
    $signed['host'] = Uri\Rfc3986\Uri::parse($cfg['endpoint'])->getHost();
    ksort($signed);
    $ch = '';
    foreach ($signed as $k => $v) $ch .= "$k:$v\n";
    $signedHeaderNames = implode(';', array_keys($signed));
    $canonicalRequest = strtolower($method) . "\n" . ($uri ?: '/') . "\n" . '' . "\n$ch\n" . $signedHeaderNames . "\n" . hash('sha256', $body);
    $stringToSign = "AWS4-HMAC-SHA256\n" . ($signed['x-amz-date'] ?? '') . "\n$scope\n" . hash('sha256', $canonicalRequest);
    $kDate = hash_hmac('sha256', $date, 'AWS4' . $cfg['secret_key'], true);
    $kRegion = hash_hmac('sha256', $region, $kDate, true);
    $kService = hash_hmac('sha256', $service, $kRegion, true);
    $kSigning = hash_hmac('sha256', 'aws4_request', $kService, true);
    return hash_hmac('sha256', $stringToSign, $kSigning);
}

function s3_put(string $key, string $data, string $content_type = 'application/octet-stream'): bool {
    $cfg = s3_config();
    if (!s3_available()) return false;
    $date = gmdate('Ymd');
    $ts = gmdate('Ymd\THis\Z');
    $payload_hash = hash('sha256', $data);
    $headers = [
        'Host' => Uri\Rfc3986\Uri::parse($cfg['endpoint'])->getHost(),
        'Content-Type' => $content_type,
        'x-amz-content-sha256' => $payload_hash,
        'x-amz-date' => $ts,
    ];
    $sig = s3_sign('PUT', '/' . $cfg['bucket'] . '/' . s3_full_key($key), $data, $headers, $date);
    $headers['Authorization'] = "AWS4-HMAC-SHA256 Credential={$cfg['access_key']}/$date/{$cfg['region']}/s3/aws4_request, SignedHeaders=" . implode(';', array_keys(array_change_key_case($headers))) . ", Signature=$sig";
    $h = [];
    foreach ($headers as $k => $v) $h[] = "$k: $v";
    $ctx = stream_context_create(['http' => [
        'method' => 'PUT',
        'header' => implode("\r\n", $h),
        'content' => $data,
        'ignore_errors' => true,
    ]]);
    $r = @file_get_contents($cfg['endpoint'] . '/' . $cfg['bucket'] . '/' . s3_full_key($key), false, $ctx);
    if ($r === false) return false;
    return !str_contains($http_response_header[0] ?? '', ' 40');
}

function s3_delete(string $key): bool {
    $cfg = s3_config();
    if (!s3_available()) return false;
    $date = gmdate('Ymd');
    $ts = gmdate('Ymd\THis\Z');
    $payload_hash = hash('sha256', '');
    $headers = [
        'Host' => Uri\Rfc3986\Uri::parse($cfg['endpoint'])->getHost(),
        'x-amz-content-sha256' => $payload_hash,
        'x-amz-date' => $ts,
    ];
    $sig = s3_sign('DELETE', '/' . $cfg['bucket'] . '/' . s3_full_key($key), '', $headers, $date);
    $headers['Authorization'] = "AWS4-HMAC-SHA256 Credential={$cfg['access_key']}/$date/{$cfg['region']}/s3/aws4_request, SignedHeaders=" . implode(';', array_keys(array_change_key_case($headers))) . ", Signature=$sig";
    $h = [];
    foreach ($headers as $k => $v) $h[] = "$k: $v";
    $ctx = stream_context_create(['http' => [
        'method' => 'DELETE',
        'header' => implode("\r\n", $h),
        'ignore_errors' => true,
    ]]);
    $r = @file_get_contents($cfg['endpoint'] . '/' . $cfg['bucket'] . '/' . s3_full_key($key), false, $ctx);
    if ($r === false) return false;
    return !str_contains($http_response_header[0] ?? '', ' 40');
}

function s3_presigned_put_url(string $key, string $content_type = 'application/octet-stream', int $expires = 300): array {
    $cfg = s3_config();
    if (!s3_available()) return ['ok' => false, 'error' => 'S3 未配置'];
    $key = s3_full_key($key);

    $endpoint = rtrim($cfg['endpoint'], '/');
    $host = Uri\Rfc3986\Uri::parse($endpoint)->getHost();
    $region = $cfg['region'];
    $service = 's3';
    $algorithm = 'AWS4-HMAC-SHA256';

    $amzDate = gmdate('Ymd\THis\Z');
    $dateStamp = gmdate('Ymd');
    $credentialScope = "$dateStamp/$region/$service/aws4_request";

    $canonicalUri = '/' . $cfg['bucket'] . '/' . $key;
    $canonicalQuerystring = http_build_query([
        'X-Amz-Algorithm' => $algorithm,
        'X-Amz-Credential' => $cfg['access_key'] . '/' . $credentialScope,
        'X-Amz-Date' => $amzDate,
        'X-Amz-Expires' => $expires,
        'X-Amz-SignedHeaders' => 'host',
    ]);

    $canonicalHeaders = "host:$host\n";
    $signedHeaders = 'host';

    $payloadHash = 'UNSIGNED-PAYLOAD';

    $canonicalRequest = "PUT\n$canonicalUri\n$canonicalQuerystring\n$canonicalHeaders\n$signedHeaders\n$payloadHash";

    $stringToSign = "$algorithm\n$amzDate\n$credentialScope\n" . hash('sha256', $canonicalRequest);

    $signingKey = hash_hmac('sha256', 'aws4_request',
        hash_hmac('sha256', $service,
            hash_hmac('sha256', $region,
                hash_hmac('sha256', $dateStamp, 'AWS4' . $cfg['secret_key'], true),
            true),
        true),
    true);

    $signature = hash_hmac('sha256', $stringToSign, $signingKey);

    $url = "$endpoint/{$cfg['bucket']}/$key?$canonicalQuerystring&X-Amz-Signature=$signature";

    return ['ok' => true, 'url' => $url];
}