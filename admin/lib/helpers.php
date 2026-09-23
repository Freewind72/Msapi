<?php

function is_mobile()
{
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    if ($ua === '') return false;
    $mobiles = [
        'Mobile','Android','iPhone','iPad','iPod','webOS','BlackBerry',
        'IEMobile','Opera Mini','Opera Mobi','Windows Phone','Kindle','Silk',
        'Symbian','SymbianOS','PlayBook','BB10','Tablet','KFAPWI','KFOT',
        'MicroMessenger','MQQBrowser','UCBrowser','UCWEB','QQ/',
        'BaiduBrowser','baiduboxapp','MiuiBrowser','HuaweiBrowser',
        'SogouMobileBrowser','LieBaoFast','360Browser','AlipayClient',
        'DingTalk','MZBrowser','CoolPad','OppoBrowser','VivoBrowser',
        'Nokia','PlayStation','Nintendo','WAP',
    ];
    foreach ($mobiles as $m) {
        if (stripos($ua, $m) !== false) return true;
    }
    return false;
}

function flash_set($k, $v)
{
    $_SESSION['_flash'][$k] = $v;
}

function flash_get($k)
{
    $v = $_SESSION['_flash'][$k] ?? null;
    unset($_SESSION['_flash'][$k]);
    return $v;
}

function mask_key($key)
{
    $len = strlen($key);
    if ($len <= 12) return $key;
    return substr($key, 0, 8) . '········' . substr($key, -4);
}
