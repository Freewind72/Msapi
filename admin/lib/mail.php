<?php defined('MAPI_ADMIN') or die('禁止直接访问');

/**
 * 发送邮件 - 从数据库读取 SMTP 配置
 */
function sendMail(string $to, string $subj, string $body, bool $isHtml = false): string {
    global $db;

    if (!$db || $db->connect_error) {
        return '数据库不可用';
    }

    $r = $db->query("SELECT config_value FROM mapi_config WHERE config_key='smtp'");
    if (!$r) return 'SMTP 未配置';

    $row = $r->fetch_assoc();
    if (!$row) return 'SMTP 未配置';

    $cfg = json_decode($row['config_value'], true);
    if (!$cfg || empty($cfg['host'])) return 'SMTP 配置无效';

    return mail_send_raw(
        $cfg['host'],
        (int)($cfg['port'] ?? 465),
        $cfg['encrypt'] ?? 'ssl',
        $cfg['user'] ?? '',
        $cfg['pass'] ?? '',
        $cfg['from'] ?? '',
        $cfg['name'] ?? '',
        $to,
        $subj,
        $body,
        $isHtml
    );
}

/**
 * 底层 SMTP 发送 - 使用原生 socket 通信
 */
function mail_send_raw(
    string $host,
    int $port,
    string $encrypt,
    string $user,
    string $pass,
    string $from,
    string $name,
    string $to,
    string $subject,
    string $body,
    bool $isHtml = false
): string {
    if (!$host || !$from || !$pass) {
        return 'SMTP 发件人配置不完整';
    }

    $ctx = stream_context_create([
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
        ],
    ]);

    $prefix = ($encrypt === 'tls') ? '' : 'ssl://';
    $errno = 0;
    $errstr = '';

    try {
        $fp = @stream_socket_client(
            $prefix . $host . ':' . $port,
            $errno,
            $errstr,
            15,
            STREAM_CLIENT_CONNECT,
            $ctx
        );
    } catch (Throwable $e) {
        return 'SMTP 连接异常: ' . $e->getMessage();
    }

    if (!$fp) {
        return "SMTP 连接失败: $errstr ($errno)";
    }

    stream_set_timeout($fp, 10);
    stream_set_blocking($fp, true);

    $readLine = function ($fp, int $timeout = 10): string {
        $result = '';
        $start = time();
        while (($line = fgets($fp, 512)) !== false) {
            $result .= $line;
            if (isset($line[3]) && $line[3] === ' ') break;
            if (time() - $start > $timeout) break;
        }
        return $result;
    };

    $writeCmd = function ($fp, string $cmd): void {
        fwrite($fp, $cmd . "\r\n");
    };

    $expect = function ($fp, string $prefix, string $errMsg): ?string {
        $response = $GLOBALS['_mail_readLine']($fp);
        if (!str_starts_with($response, $prefix)) {
            $GLOBALS['_mail_lastErr'] = $errMsg . ': ' . trim($response);
            return null;
        }
        return $response;
    };

    $GLOBALS['_mail_readLine'] = $readLine;

    $response = $readLine($fp);
    if (!str_starts_with($response, '220')) {
        fclose($fp);
        return 'SMTP 服务器未就绪: ' . trim($response);
    }

    $writeCmd($fp, "EHLO mapi");
    $readLine($fp);

    if ($encrypt === 'tls') {
        $writeCmd($fp, "STARTTLS");
        $tlsResp = $readLine($fp);
        if (!str_starts_with($tlsResp, '220')) {
            fclose($fp);
            return 'STARTTLS 失败: ' . trim($tlsResp);
        }
        $cryptoResult = @stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
        if (!$cryptoResult) {
            fclose($fp);
            return 'TLS 握手失败';
        }
        $writeCmd($fp, "EHLO mapi");
        $readLine($fp);
    }

    $writeCmd($fp, "AUTH LOGIN");
    $readLine($fp);
    $writeCmd($fp, base64_encode($user));
    $readLine($fp);
    $writeCmd($fp, base64_encode($pass));
    $authResp = $readLine($fp);
    if (!str_starts_with($authResp, '235')) {
        fwrite($fp, "QUIT\r\n");
        fclose($fp);
        return 'SMTP 认证失败: ' . trim($authResp);
    }

    $writeCmd($fp, "MAIL FROM:<$from>");
    $mailResp = $readLine($fp);
    if (!str_starts_with($mailResp, '250')) {
        fwrite($fp, "QUIT\r\n");
        fclose($fp);
        return '发件人拒绝: ' . trim($mailResp);
    }

    $writeCmd($fp, "RCPT TO:<$to>");
    $rcptResp = $readLine($fp);
    if (!str_starts_with($rcptResp, '250')) {
        fwrite($fp, "QUIT\r\n");
        fclose($fp);
        return '收件人拒绝: ' . trim($rcptResp);
    }

    $writeCmd($fp, "DATA");
    $dataResp = $readLine($fp);
    if (!str_starts_with($dataResp, '354')) {
        fwrite($fp, "QUIT\r\n");
        fclose($fp);
        return 'DATA 命令失败: ' . trim($dataResp);
    }

    $fromName = $name ?: '顺雅音乐';
    $headers = "From: =?UTF-8?B?" . base64_encode($fromName) . "?= <$from>\r\n";
    $headers .= "To: <$to>\r\n";
    $headers .= "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: " . ($isHtml ? 'text/html' : 'text/plain') . "; charset=UTF-8\r\n";
    $headers .= "Content-Transfer-Encoding: base64\r\n\r\n";

    $encodedBody = chunk_split(base64_encode($body));
    fwrite($fp, $headers . $encodedBody . "\r\n.\r\n");

    $sendResp = $readLine($fp);
    $writeCmd($fp, "QUIT");
    fclose($fp);

    if (!str_starts_with($sendResp, '250')) {
        return '邮件发送失败: ' . trim($sendResp);
    }

    return '';
}

unset($GLOBALS['_mail_readLine']);