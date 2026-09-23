<?php defined('MAPI_ADMIN') or die('禁止直接访问');

if (!function_exists('base64url_encode')) { function base64url_encode(string $d): string { return rtrim(strtr(base64_encode($d), '+/', '-_'), '='); } }
if (!function_exists('base64url_decode')) { function base64url_decode(string $d): string { return base64_decode(strtr($d, '-_', '+/')); } }

function ec_pem_from_coords(string $x, string $y): string {
    $point = "\x04{$x}{$y}";
    $alg = "\x30\x13\x06\x07\x2a\x86\x48\xce\x3d\x02\x01\x06\x08\x2a\x86\x48\xce\x3d\x03\x01\x07";
    $bits = "\x03" . chr(strlen($point) + 1) . "\x00{$point}";
    $spki = "\x30" . chr(strlen($alg) + strlen($bits)) . $alg . $bits;
    return "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($spki), 64, "\n") . "-----END PUBLIC KEY-----";
}

function cbor_skip_value(string $d, int &$o): void {
    if ($o >= strlen($d)) return;
    $b = ord($d[$o]);
    $t = ($b >> 5) & 7;
    $a = $b & 0x1f;
    $o++;
    if ($t === 0 || $t === 1) {
        if ($a > 23) $o += match ($a) { 24 => 1, 25 => 2, 26 => 4, default => 8 };
    } elseif ($t === 2 || $t === 3) {
        $l = $a <= 23 ? $a : (function() use ($d, &$o, $a) {
            if ($a === 24) return ord($d[$o++]);
            if ($a === 25) { $v = unpack('n', substr($d, $o, 2))[1]; $o += 2; return $v; }
            return 0;
        })();
        $o += $l;
    } elseif ($t === 4) {
        $c = $a <= 23 ? $a : ($a === 24 ? ord($d[$o++]) : 0);
        for ($i = 0; $i < $c; $i++) cbor_skip_value($d, $o);
    } elseif ($t === 5) {
        $c = $a <= 23 ? $a : ($a === 24 ? ord($d[$o++]) : 0);
        for ($i = 0; $i < $c * 2; $i++) cbor_skip_value($d, $o);
    }
}

function cbor_parse_attestation(string $d): array {
    $o = 1;
    $entries = (ord($d[0]) & 0x1f) <= 23 ? (ord($d[0]) & 0x1f) : ord($d[1]);
    $o = (ord($d[0]) & 0x1f) <= 23 ? 1 : 2;
    $r = [];
    for ($e = 0; $e < $entries && $o < strlen($d); $e++) {
        $b = ord($d[$o]);
        $kt = ($b >> 5) & 7;
        $ka = $b & 0x1f;
        $o++;
        $k = null;
        if ($kt === 3) {
            $kl = $ka <= 23 ? $ka : ($ka === 24 ? ord($d[$o++]) : 0);
            $k = substr($d, $o, $kl);
            $o += $kl;
        }
        if ($o >= strlen($d)) break;
        if ($k === 'authData') {
            $b = ord($d[$o]);
            $vt = ($b >> 5) & 7;
            $va = $b & 0x1f;
            $o++;
            if ($vt === 2) {
                $vl = $va <= 23 ? $va : (
                    $va === 24 ? ord($d[$o++]) : (
                        $va === 25 ? unpack('n', substr($d, $o, 2))[1] : unpack('N', substr($d, $o, 4))[1]
                    )
                );
                $o += ($va === 25 ? 2 : ($va === 26 ? 4 : 0));
                $r['authData'] = substr($d, $o, $vl);
                $o += $vl;
            }
        } else cbor_skip_value($d, $o);
    }
    return $r;
}

function cbor_parse_cose_key(string $d): ?array {
    if ((ord($d[0]) >> 5) !== 5) return null;
    $entries = ord($d[0]) & 0x1f;
    $o = 1;
    if ($entries === 24) { $entries = ord($d[$o]); $o++; }
    $r = [];
    for ($i = 0; $i < $entries && $o < strlen($d) - 1; $i++) {
        $b = ord($d[$o]);
        $kt = ($b >> 5) & 7;
        $ka = $b & 0x1f;
        $o++;
        $k = null;
        if ($kt === 0) $k = $ka;
        elseif ($kt === 1) $k = -1 - $ka;
        if ($o >= strlen($d)) break;
        $b = ord($d[$o]);
        $vt = ($b >> 5) & 7;
        $va = $b & 0x1f;
        $o++;
        if ($vt === 0) {
            $v = match (true) {
                $va === 24 => ord($d[$o++]),
                $va === 25 => ($v = unpack('n', substr($d, $o, 2))[1]) && $o += 2 ? $v : 0,
                $va === 26 => ($v = unpack('N', substr($d, $o, 4))[1]) && $o += 4 ? $v : 0,
                default => $va,
            };
            $r[$k] = $v;
        } elseif ($vt === 1) {
            $v = match (true) {
                $va === 24 => -1 - ord($d[$o++]),
                $va === 25 => -1 - ($v = unpack('n', substr($d, $o, 2))[1]) && $o += 2 ? $v : 0,
                $va === 26 => -1 - ($v = unpack('N', substr($d, $o, 4))[1]) && $o += 4 ? $v : 0,
                default => -1 - $va,
            };
            $r[$k] = $v;
        } elseif ($vt === 2) {
            $vl = $va;
            if ($va === 24) { $vl = ord($d[$o]); $o++; }
            elseif ($va === 25) { $vl = unpack('n', substr($d, $o, 2))[1]; $o += 2; }
            elseif ($va === 26) { $vl = unpack('N', substr($d, $o, 4))[1]; $o += 4; }
            if ($k !== null) $r[$k] = substr($d, $o, $vl);
            $o += $vl;
        } else cbor_skip_value($d, $o);
    }
    return $r;
}

function webauthn_get_origin(): string {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
    return $scheme . '://' . $host;
}

function webauthn_get_rp_id(): string {
    $host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
    $rpId = explode(':', $host)[0];
    $rpId = trim($rpId, '[]');
    if (!$rpId || $rpId === '127.0.0.1' || $rpId === '::1' || $rpId === '0.0.0.0') $rpId = 'localhost';
    return $rpId;
}

function webauthn_is_valid_rp_id(string $rpId): bool {
    if ($rpId === 'localhost') return true;
    if (filter_var($rpId, FILTER_VALIDATE_IP)) return false;
    if (preg_match('/^\d+\.\d+\.\d+\.\d+$/', $rpId)) return false;
    if (strpos($rpId, '.') === false && strpos($rpId, ':') !== false) return false;
    return true;
}

function webauthn_verify_attestation(string $ao, string $cjd): array|string {
    $ch = base64_decode($_SESSION['pk_challenge'] ?? '');
    if (!$ch) return '无挑战';
    $cj = json_decode(base64url_decode($cjd), true);
    if (!$cj) return '无效clientData';
    if (!hash_equals(base64url_encode($ch), $cj['challenge'] ?? '')) return '挑战不匹配';
    if (($cj['type'] ?? '') !== 'webauthn.create') return '类型错误';
    $expectedOrigin = $_SESSION['pk_origin'] ?? webauthn_get_origin();
    if (!hash_equals($expectedOrigin, $cj['origin'] ?? '')) return 'Origin不匹配';
    $rpId = $_SESSION['pk_rp_id'] ?? '';
    if (!$rpId) return '缺少RP ID';
    $ad = cbor_parse_attestation(base64url_decode($ao));
    if (!$ad || !isset($ad['authData'])) return 'CBOR解析失败';
    $a = $ad['authData'];
    if (strlen($a) < 37) return 'authData太短';
    $rpIdHash = substr($a, 0, 32);
    if (!hash_equals(hash('sha256', $rpId, true), $rpIdHash)) return 'RP ID不匹配';
    $flags = ord($a[32]);
    $counter = unpack('N', substr($a, 33, 4))[1];
    if (!($flags & 0x40)) return '缺少AT标志';
    if (!($flags & 0x01)) return '用户未在场';
    if (strlen($a) < 55) return 'authData太短';
    $cl = unpack('n', substr($a, 53, 2))[1];
    $cid = substr($a, 55, $cl);
    if (!$cl || !$cid) return '凭证ID为空';
    $pk_data = substr($a, 55 + $cl);
    if (strlen($pk_data) < 1) return '公钥数据为空';
    $pk = cbor_parse_cose_key($pk_data);
    if (!$pk) return 'COSE解析失败';
    if (!isset($pk[-2])) return '缺少x坐标';
    if (!isset($pk[-3])) return '缺少y坐标';
    return [
        'credential_id' => base64_encode($cid),
        'public_key_pem' => ec_pem_from_coords($pk[-2], $pk[-3]),
        'counter' => $counter,
    ];
}

function webauthn_verify_assertion(string $ad, string $sig, string $cjd, string $pem, int $sc): int|false {
    $ch = base64_decode($_SESSION['pk_challenge'] ?? '');
    if (!$ch) return false;
    $cj = json_decode(base64url_decode($cjd), true);
    if (!$cj || !hash_equals(base64url_encode($ch), $cj['challenge'] ?? '')) return false;
    if (($cj['type'] ?? '') !== 'webauthn.get') return false;
    $expectedOrigin = $_SESSION['pk_origin'] ?? webauthn_get_origin();
    if (!hash_equals($expectedOrigin, $cj['origin'] ?? '')) return false;
    $rpId = $_SESSION['pk_rp_id'] ?? '';
    if (!$rpId) return false;
    $a = base64url_decode($ad);
    if (strlen($a) < 37) return false;
    $rpIdHash = substr($a, 0, 32);
    if (!hash_equals(hash('sha256', $rpId, true), $rpIdHash)) return false;
    $flags = ord($a[32]);
    if (!($flags & 0x01)) return false;
    $nc = unpack('N', substr($a, 33, 4))[1];
    if ($nc <= $sc && $sc !== 0) return false;
    $s = base64url_decode($sig);
    $h = hash('sha256', base64url_decode($cjd), true);
    $pk = openssl_pkey_get_public($pem);
    if (!$pk) return false;
    $r = openssl_verify($a . $h, $s, $pk, OPENSSL_ALGO_SHA256);
    return $r ? $nc : false;
}