<?php defined('MAPI_ADMIN') or die('禁止直接访问');
// settings.php — 服务设置（仅管理员）
if ((($_SESSION['admin_is_admin'] ?? 99) > 1)) { echo '<div class="card"><div class="empty">无权限</div></div>'; return; }

$announcement = ['enabled' => false, 'title' => '', 'content' => '', 'align' => 'left'];
$ra = $db->query("SELECT config_value FROM mapi_config WHERE config_key='announcement'");
if ($ra && $rowa = $ra->fetch_assoc()) {
    $saveda = json_decode($rowa['config_value'], true);
    if (is_array($saveda)) $announcement = array_merge($announcement, $saveda);
}
$smtp = ['host'=>'','port'=>465,'user'=>'','pass'=>'','encrypt'=>'ssl','from'=>'','name'=>''];
$r = $db->query("SELECT config_value FROM mapi_config WHERE config_key='smtp'");
if ($r && $row = $r->fetch_assoc()) {
    $saved = json_decode($row['config_value'], true);
    if (is_array($saved)) $smtp = array_merge($smtp, $saved);
}
$keyLimit = ['limit' => 1];
$r = $db->query("SELECT config_value FROM mapi_config WHERE config_key='key_limit'");
if ($r && $row = $r->fetch_assoc()) {
    $saved = json_decode($row['config_value'], true);
    if (is_array($saved)) $keyLimit = array_merge($keyLimit, $saved);
}
$s3 = ['endpoint'=>'','access_key'=>'','secret_key'=>'','bucket'=>'','region'=>'auto','path_prefix'=>'','custom_domain'=>''];
$r = $db->query("SELECT config_value FROM mapi_config WHERE config_key='s3'");
if ($r && $row = $r->fetch_assoc()) {
    $saved = json_decode($row['config_value'], true);
    if (is_array($saved)) $s3 = array_merge($s3, $saved);
}
$epay = ['api_url'=>'','pid'=>'','key'=>''];
$r = $db->query("SELECT config_value FROM mapi_config WHERE config_key='epay'");
if ($r && $row = $r->fetch_assoc()) {
    $saved = json_decode($row['config_value'], true);
    if (is_array($saved)) $epay = array_merge($epay, $saved);
}
$geetest = ['captcha_id' => '', 'key' => ''];
$r = $db->query("SELECT captcha_id, `key` FROM mapi_geetest LIMIT 1");
if ($r && $row = $r->fetch_assoc()) {
    $geetest['captcha_id'] = $row['captcha_id'] ?? '';
    $geetest['key'] = $row['key'] ?? '';
}
$mapiApi = ['meting'=>'','qq_referer'=>'','qq_cover'=>'','fields'=>['title'=>'title','artist'=>'author','url'=>'url','pic'=>'pic','lrc'=>'lrc'],'param_id'=>'id','param_auth'=>'auth','req_params'=>['server'=>'server','type'=>'type','id'=>'id']];
$r = $db->query("SELECT config_value FROM mapi_config WHERE config_key='mapi_api'");
if ($r && $row = $r->fetch_assoc()) {
    $saved = json_decode($row['config_value'], true);
    if (is_array($saved)) {
        if (isset($saved['fields']) && is_array($saved['fields'])) {
            $saved['fields'] = array_merge($mapiApi['fields'], $saved['fields']);
        }
        if (isset($saved['req_params']) && is_array($saved['req_params'])) {
            $saved['req_params'] = array_merge($mapiApi['req_params'], $saved['req_params']);
        }
        $mapiApi = array_merge($mapiApi, $saved);
    }
}
$loginTheme = 'light'; $loginBg = '';
$r = $db->query("SELECT config_value FROM mapi_config WHERE config_key='login_theme'");
if ($r && $row = $r->fetch_assoc()) $loginTheme = $row['config_value'];
$r = $db->query("SELECT config_value FROM mapi_config WHERE config_key='login_bg'");
if ($r && $row = $r->fetch_assoc()) $loginBg = $row['config_value'];
$mailTemplate = ['subject' => '顺雅音乐 - 验证码邮件', 'body' => <<<'EOT'
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>顺雅音乐 - 验证码邮件</title>
    <style>
        body {
            margin: 0;
            padding: 0;
            background-color: #f5efe6;
            font-family: 'PingFang SC', 'Microsoft YaHei', -apple-system, sans-serif;
        }
        .container {
            max-width: 480px;
            margin: 40px auto;
            background: #fdfaf3;
            border-radius: 24px;
            padding: 40px 36px;
            box-shadow: 0 20px 40px rgba(180, 160, 130, 0.12), 0 4px 12px rgba(180, 160, 130, 0.08);
            border: 1px solid rgba(255, 252, 245, 0.6);
            position: relative;
            overflow: hidden;
        }
        .glass-orb-top {
            position: absolute;
            width: 180px;
            height: 180px;
            background: radial-gradient(circle at 30% 30%, rgba(255, 235, 200, 0.55), rgba(255, 210, 160, 0.18) 65%, transparent 70%);
            top: -60px;
            right: -50px;
            border-radius: 50%;
            backdrop-filter: blur(1.2px);
            pointer-events: none;
            z-index: 0;
        }
        .glass-orb-bottom {
            position: absolute;
            width: 220px;
            height: 220px;
            background: radial-gradient(circle at 40% 40%, rgba(240, 225, 205, 0.45), rgba(235, 210, 190, 0.15) 60%, transparent 70%);
            bottom: -70px;
            left: -60px;
            border-radius: 50%;
            backdrop-filter: blur(2px);
            pointer-events: none;
            z-index: 0;
        }
        .card {
            position: relative;
            z-index: 1;
            background: rgba(255, 250, 240, 0.65);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 32px 28px 36px;
            border: 1px solid rgba(255, 255, 255, 0.5);
            box-shadow: inset 0 1px 0 rgba(255,255,255,0.7), 0 8px 24px rgba(200, 180, 150, 0.10);
        }
        .brand {
            text-align: center;
            margin-bottom: 28px;
        }
        .brand-icon {
            width: 56px;
            height: 56px;
            background: rgba(255, 255, 255, 0.55);
            backdrop-filter: blur(4px);
            -webkit-backdrop-filter: blur(4px);
            border-radius: 18px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: 1.2px solid rgba(255, 255, 255, 0.85);
            box-shadow: 0 4px 12px rgba(190, 160, 130, 0.12);
            font-size: 28px;
            margin-bottom: 14px;
            letter-spacing: 2px;
        }
        .brand-name {
            font-size: 21px;
            font-weight: 600;
            color: #5b4a3f;
            letter-spacing: 4px;
            margin: 0;
            text-shadow: 0 1px 2px rgba(255, 255, 255, 0.6);
        }
        .brand-sub {
            font-size: 12px;
            color: #9b8978;
            letter-spacing: 2px;
            margin-top: 6px;
        }
        .title {
            font-size: 16px;
            color: #6b5a4e;
            text-align: center;
            margin: 0 0 6px;
            font-weight: 500;
            letter-spacing: 1px;
        }
        .welcome-text {
            font-size: 13px;
            color: #907e6d;
            text-align: center;
            margin: 0 0 30px;
            line-height: 1.6;
            letter-spacing: 0.5px;
        }
        .code-wrapper {
            background: rgba(255, 245, 233, 0.65);
            backdrop-filter: blur(6px);
            -webkit-backdrop-filter: blur(6px);
            border-radius: 16px;
            padding: 26px 20px;
            text-align: center;
            border: 1px dashed rgba(200, 175, 145, 0.4);
            box-shadow: inset 0 2px 8px rgba(255, 255, 255, 0.5), 0 4px 16px rgba(190, 160, 130, 0.10);
            margin-bottom: 24px;
        }
        .code-label {
            display: block;
            font-size: 12px;
            color: #a08a76;
            letter-spacing: 3px;
            text-transform: uppercase;
            margin-bottom: 14px;
            font-weight: 400;
        }
        .code-number {
            font-size: 42px;
            font-weight: 700;
            color: #3f3a36;
            letter-spacing: 12px;
            font-family: 'SF Mono', 'Menlo', 'Monaco', monospace;
            text-shadow: 0 2px 4px rgba(200, 170, 140, 0.12);
            margin-left: 12px;
        }
        .validity {
            font-size: 12px;
            color: #ad9a87;
            text-align: center;
            margin: 22px 0 6px;
            background: rgba(250, 244, 235, 0.65);
            backdrop-filter: blur(4px);
            -webkit-backdrop-filter: blur(4px);
            display: inline-block;
            padding: 6px 16px;
            border-radius: 30px;
            border: 1px solid rgba(255, 255, 255, 0.56);
            letter-spacing: 1px;
        }
        .validity-wrap {
            text-align: center;
        }
        .security-note {
            font-size: 12px;
            color: #b3a392;
            line-height: 1.7;
            text-align: justify;
            margin-top: 26px;
            padding-top: 20px;
            border-top: 1px solid rgba(190, 170, 145, 0.18);
            letter-spacing: 0.3px;
        }
        .footer {
            font-size: 11px;
            color: #bfae9b;
            text-align: center;
            margin-top: 28px;
            line-height: 1.5;
            letter-spacing: 0.3px;
            padding-top: 16px;
            border-top: 1px solid rgba(190, 170, 145, 0.12);
        }
        @media screen and (max-width: 520px) {
            .container {
                margin: 16px auto;
                padding: 20px 16px;
                border-radius: 18px;
            }
            .card {
                padding: 24px 16px 28px;
            }
            .code-number {
                font-size: 36px;
                letter-spacing: 8px;
                margin-left: 8px;
            }
            .brand-icon {
                width: 48px;
                height: 48px;
                font-size: 24px;
            }
        }
        .no-backdrop-blur {
            background-color: rgba(250, 245, 237, 0.94);
        }
        .no-backdrop-blur .card,
        .no-backdrop-blur .code-wrapper,
        .no-backdrop-blur .validity {
            background-color: rgba(252, 248, 242, 0.95);
        }
    </style>
</head>
<body>
    <div class="container no-backdrop-blur">
        <div class="glass-orb-top" style="background: radial-gradient(circle at 30% 30%, rgba(255,235,200,0.55), rgba(255,210,160,0.18) 65%, transparent 70%);"></div>
        <div class="glass-orb-bottom" style="background: radial-gradient(circle at 40% 40%, rgba(240,225,205,0.45), rgba(235,210,190,0.15) 60%, transparent 70%);"></div>
        <div class="card">
            <div class="brand">
                <div style="width: 58px; height: 58px; background: rgba(255,255,255,0.55); border-radius: 18px; display: inline-flex; align-items: center; justify-content: center; border: 1.2px solid rgba(255,255,255,0.85); box-shadow: 0 4px 12px rgba(190,160,130,0.12); margin-bottom: 12px;">
                    <span style="font-size: 26px; color: #5b4a3f;">🎼</span>
                </div>
                <p class="brand-name">顺雅音乐</p>
                <p class="brand-sub">Shunya Music</p>
            </div>
            <h2 class="title">欢迎注册顺雅音乐</h2>
            <p class="welcome-text">请输入以下验证码完成注册<br>随音符悦动，即刻启程</p>
            <div class="code-wrapper">
                <span class="code-label">验证码 · Verification Code</span>
                <div class="code-number">{code}</div>
            </div>
            <div class="validity-wrap">
                <span class="validity">⏳ 验证码10分钟内有效，请尽快填写</span>
            </div>
            <div class="security-note">
                ⚠️ 若您并未注册顺雅音乐，请忽略此邮件。<br>
                · 保护您的验证码与隐私，切勿转发给他人。<br>
                · 顺雅音乐工作人员不会向您索要任何验证码。
            </div>
            <div class="footer">
                顺雅音乐 · 聆享纯粹旋律<br>
                本邮件由系统自动发送，请勿直接回复
            </div>
        </div>
    </div>
</body>
</html>
EOT, 'html' => true];
$r = $db->query("SELECT config_value FROM mapi_config WHERE config_key='mail_template'");
if ($r && $row = $r->fetch_assoc()) {
    $saved = json_decode($row['config_value'], true);
    if (is_array($saved)) $mailTemplate = array_merge($mailTemplate, $saved);
}
?>

<!-- ═══ 站点内容 ═══ -->
<div class="section-header"><span class="section-header-inner"><?= svg('img') ?> 站点内容</span></div>

<div class="card">
  <div class="card-header"><span class="card-title"><?= svg('srv') ?> 公告</span></div>
  <form method="post" action="?action=settings"><input type="hidden" name="_ann_submit" value="1"><input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
    <div class="form-group">
      <label class="form-label">公告内容</label>
      <textarea name="announcement" class="form-input" placeholder="留空 = 关闭公告&#10;填写内容并保存 = 发布公告"><?= htmlspecialchars($announcement['content'] ?? '') ?></textarea>
    </div>
    <div class="form-group">
      <label class="form-label">内容对齐</label>
      <select name="ann_align" class="form-input">
        <option value="left" <?= $announcement['align'] === 'left' ? 'selected' : '' ?>>左对齐</option>
        <option value="center" <?= $announcement['align'] === 'center' ? 'selected' : '' ?>>居中</option>
      </select>
    </div>
    <button class="btn btn-primary btn-block">发布公告</button>
  </form>
</div>

<div class="card">
  <div class="card-header"><span class="card-title"><?= svg('img') ?> 登录页主题与背景</span></div>
  <form method="post" action="?action=settings"><input type="hidden" name="_login_submit" value="1"><input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
    <div class="form-group">
      <label class="form-label">主题模式</label>
      <select name="login_theme" class="form-input">
        <option value="light" <?= $loginTheme === 'light' ? 'selected' : '' ?>>浅色</option>
        <option value="dark" <?= $loginTheme === 'dark' ? 'selected' : '' ?>>深色</option>
      </select>
    </div>
    <div class="form-group">
      <label class="form-label">背景图片 URL</label>
      <input class="form-input" name="login_bg" value="<?= htmlspecialchars($loginBg) ?>" placeholder="留空 = 默认浅灰背景">
    </div>
    <button class="btn btn-primary btn-block">保存</button>
  </form>
</div>

<!-- ═══ 安全 ═══ -->
<div class="section-header"><span class="section-header-inner"><?= svg('shield') ?> 安全</span></div>

<div class="card">
  <div class="card-header"><span class="card-title"><?= svg('srv') ?> 极验验证码</span></div>
  <form method="post" action="?action=settings"><input type="hidden" name="_geetest_submit" value="1"><input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
    <div class="form-group">
      <label class="form-label">captcha_id（应用 ID）</label>
      <input class="form-input" name="geetest_captcha_id" value="<?= htmlspecialchars($geetest['captcha_id']) ?>" placeholder="从极验后台获取的 captcha_id">
    </div>
    <div class="form-group">
      <label class="form-label">key（密钥）</label>
      <input class="form-input" type="password" name="geetest_key" value="<?= htmlspecialchars($geetest['key']) ?>" placeholder="极验后台的验证密钥">
    </div>
    <small style="display:block;margin-top:-8px;margin-bottom:12px;color:rgba(0,0,0,.38);font-size:11px">留空则关闭极验验证码。修改后需刷新登录页生效。</small>
    <button class="btn btn-primary btn-block">保存</button>
  </form>
</div>

<?php if (($_SESSION['admin_is_admin'] ?? 99) === 0): ?>
<!-- ═══ 系统服务 ═══ -->
<div class="section-header"><span class="section-header-inner"><?= svg('srv') ?> 系统服务</span></div>

<div class="card">
  <div class="card-header"><span class="card-title"><?= svg('srv') ?> 邮件服务</span></div>
  <form method="post" action="?action=settings"><input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
    <div class="form-group">
      <label class="form-label">SMTP 服务器地址</label>
      <input class="form-input" name="smtp_host" value="<?= htmlspecialchars($smtp['host']) ?>" placeholder="smtp.example.com">
    </div>
    <div class="form-row">
      <div class="form-group" style="flex:1;margin-bottom:0">
        <label class="form-label">端口</label>
        <input class="form-input" name="smtp_port" value="<?= (int)$smtp['port'] ?>" placeholder="465">
      </div>
      <div class="form-group" style="flex:1;margin-bottom:0">
        <label class="form-label">加密方式</label>
        <select class="form-input" name="smtp_encrypt">
          <option value="ssl"<?= $smtp['encrypt']==='ssl'?' selected':'' ?>>SSL</option>
          <option value="tls"<?= $smtp['encrypt']==='tls'?' selected':'' ?>>TLS</option>
          <option value="none"<?= $smtp['encrypt']==='none'?' selected':'' ?>>无</option>
        </select>
      </div>
    </div>
    <div class="form-group">
      <label class="form-label">SMTP 账号</label>
      <input class="form-input" name="smtp_user" value="<?= htmlspecialchars($smtp['user']) ?>" placeholder="user@example.com">
    </div>
    <div class="form-group">
      <label class="form-label">SMTP 密码</label>
      <input class="form-input" type="password" name="smtp_pass" value="<?= htmlspecialchars($smtp['pass']) ?>" placeholder="留空不修改">
    </div>
    <div class="form-row">
      <div class="form-group" style="flex:1;margin-bottom:0">
        <label class="form-label">发件人邮箱</label>
        <input class="form-input" name="smtp_from" value="<?= htmlspecialchars($smtp['from']) ?>" placeholder="noreply@example.com">
      </div>
      <div class="form-group" style="flex:1;margin-bottom:0">
        <label class="form-label">发件人名称</label>
        <input class="form-input" name="smtp_name" value="<?= htmlspecialchars($smtp['name']) ?>" placeholder="顺雅音乐">
      </div>
    </div>
    <button class="btn btn-primary btn-block">保存</button>
  </form>
  <div style="border-top:1px solid rgba(0,0,0,.06);margin:20px 0 0;padding-top:16px">
    <div style="font-size:13px;font-weight:600;color:rgba(0,0,0,.54);margin-bottom:16px">邮件模板</div>
    <form method="post" action="?action=settings"><input type="hidden" name="_mail_tpl_submit" value="1"><input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
      <div class="form-group">
        <label class="form-label">邮件主题</label>
        <input class="form-input" name="mail_tpl_subject" value="<?= htmlspecialchars($mailTemplate['subject']) ?>" placeholder="顺雅音乐 - 验证码邮件">
      </div>
      <div class="form-group">
        <label class="form-label">邮件正文</label>
        <textarea class="form-input" name="mail_tpl_body" rows="6" placeholder="支持 HTML 格式，{code} 会被替换为验证码"><?= htmlspecialchars($mailTemplate['body']) ?></textarea>
        <small style="display:block;margin-top:6px;color:rgba(0,0,0,.38);font-size:12px"><code>{code}</code> 会被替换为实际验证码。勾选 HTML 模式后支持标签和样式。</small>
      </div>
      <div class="form-group">
        <label class="form-label">发送格式</label>
        <select name="mail_tpl_html" class="form-input">
          <option value="0"<?= empty($mailTemplate['html']) ? ' selected' : '' ?>>纯文本格式</option>
          <option value="1"<?= !empty($mailTemplate['html']) ? ' selected' : '' ?>>HTML 格式（支持标签样式）</option>
        </select>
      </div>
      <button class="btn btn-primary btn-block">保存模板</button>
    </form>
  </div>
</div>

<div class="card">
  <div class="card-header"><span class="card-title"><?= svg('key') ?> 密钥数量限制</span></div>
  <form method="post" action="?action=settings"><input type="hidden" name="_key_limit_submit" value="1"><input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
    <div class="form-group">
      <label class="form-label">每个用户最多创建密钥数量</label>
      <input class="form-input" type="number" name="key_limit" value="<?= (int)$keyLimit['limit'] ?>" min="1" max="100" required>
      <small style="display:block;margin-top:6px;color:rgba(0,0,0,.38);font-size:12px">管理员不受此限制 · 默认 1 个</small>
    </div>
    <button class="btn btn-primary btn-block">保存</button>
  </form>
</div>

<div class="card">
  <div class="card-header"><span class="card-title"><?= svg('play') ?> 音乐 API</span></div>
  <form method="post" action="?action=settings"><input type="hidden" name="_api_submit" value="1"><input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
    <div class="form-group">
      <label class="form-label">Meting API 地址</label>
      <input class="form-input" name="api_meting" value="<?= htmlspecialchars($mapiApi['meting']) ?>" placeholder="https://mapi.bmwy72.top/api">
      <small style="display:block;margin-top:4px;color:rgba(0,0,0,.38);font-size:11px">Meting 音乐 API 代理地址，用于获取歌单、封面、播放地址等</small>
    </div>
    <div class="form-row">
      <div class="form-group" style="flex:1;margin-bottom:0">
        <label class="form-label">QQ 音乐 Referer</label>
        <input class="form-input" name="api_qq_referer" value="<?= htmlspecialchars($mapiApi['qq_referer']) ?>" placeholder="https://y.qq.com/">
      </div>
      <div class="form-group" style="flex:1;margin-bottom:0">
        <label class="form-label">QQ 封面 CDN</label>
        <input class="form-input" name="api_qq_cover" value="<?= htmlspecialchars($mapiApi['qq_cover']) ?>" placeholder="https://y.gtimg.cn/music/photo_new/T002R300x300M000">
      </div>
    </div>
    <div style="padding-top:4px;margin-bottom:16px;border-top:1px solid rgba(0,0,0,.04)"></div>
    <div class="form-row">
      <div class="form-group" style="flex:1;margin-bottom:0">
        <label class="form-label">回调参数名 — ID</label>
        <input class="form-input" name="api_param_id" value="<?= htmlspecialchars($mapiApi['param_id']) ?>" placeholder="id">
      </div>
      <div class="form-group" style="flex:1;margin-bottom:0">
        <label class="form-label">回调参数名 — Auth</label>
        <input class="form-input" name="api_param_auth" value="<?= htmlspecialchars($mapiApi['param_auth']) ?>" placeholder="auth">
      </div>
    </div>
    <div style="padding-top:4px;margin-bottom:16px;border-top:1px solid rgba(0,0,0,.04)"></div>
    <div class="form-row">
      <div class="form-group" style="flex:1;margin-bottom:0">
        <label class="form-label">请求参数名 — 平台</label>
        <input class="form-input" name="api_req_server" value="<?= htmlspecialchars($mapiApi['req_params']['server']) ?>" placeholder="server">
      </div>
      <div class="form-group" style="flex:1;margin-bottom:0">
        <label class="form-label">请求参数名 — 类型</label>
        <input class="form-input" name="api_req_type" value="<?= htmlspecialchars($mapiApi['req_params']['type']) ?>" placeholder="type">
      </div>
      <div class="form-group" style="flex:1;margin-bottom:0">
        <label class="form-label">请求参数名 — 资源ID</label>
        <input class="form-input" name="api_req_id" value="<?= htmlspecialchars($mapiApi['req_params']['id']) ?>" placeholder="id">
      </div>
    </div>
    <small style="display:block;margin-top:4px;color:rgba(0,0,0,.38);font-size:11px">向 API 发起请求时的查询参数名，不同接口可能使用不同命名</small>
    <div style="padding-top:4px;margin-bottom:16px;border-top:1px solid rgba(0,0,0,.04)"></div>
    <div class="form-row">
      <div class="form-group" style="flex:1;margin-bottom:0">
        <label class="form-label">JSON 字段 — 歌名</label>
        <input class="form-input" name="api_field_title" value="<?= htmlspecialchars($mapiApi['fields']['title']) ?>" placeholder="title">
      </div>
      <div class="form-group" style="flex:1;margin-bottom:0">
        <label class="form-label">JSON 字段 — 歌手</label>
        <input class="form-input" name="api_field_artist" value="<?= htmlspecialchars($mapiApi['fields']['artist']) ?>" placeholder="author">
      </div>
    </div>
    <div class="form-row">
      <div class="form-group" style="flex:1;margin-bottom:0">
        <label class="form-label">JSON 字段 — 播放地址</label>
        <input class="form-input" name="api_field_url" value="<?= htmlspecialchars($mapiApi['fields']['url']) ?>" placeholder="url">
      </div>
      <div class="form-group" style="flex:1;margin-bottom:0">
        <label class="form-label">JSON 字段 — 封面</label>
        <input class="form-input" name="api_field_pic" value="<?= htmlspecialchars($mapiApi['fields']['pic']) ?>" placeholder="pic">
      </div>
    </div>
    <div class="form-group">
      <label class="form-label">JSON 字段 — 歌词</label>
      <input class="form-input" name="api_field_lrc" value="<?= htmlspecialchars($mapiApi['fields']['lrc']) ?>" placeholder="lrc">
    </div>
    <small style="display:block;margin-top:4px;color:rgba(0,0,0,.38);font-size:11px">如果 API 返回的 JSON 字段名不同，在此修改映射关系</small>
    <button class="btn btn-primary btn-block">保存</button>
  </form>
</div>

<div class="card">
  <div class="card-header"><span class="card-title"><?= svg('upload') ?> S3 存储</span></div>
  <form method="post" action="?action=settings"><input type="hidden" name="_s3_submit" value="1"><input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
    <div class="form-group">
      <label class="form-label">Endpoint</label>
      <input class="form-input" name="s3_endpoint" value="<?= htmlspecialchars($s3['endpoint']) ?>" placeholder="https://s3.amazonaws.com 或 https://xxx.r2.cloudflarestorage.com">
      <small style="display:block;margin-top:4px;color:rgba(0,0,0,.38);font-size:11px">S3 兼容服务的 Endpoint 地址</small>
    </div>
    <div class="form-row">
      <div class="form-group" style="flex:1;margin-bottom:0">
        <label class="form-label">Access Key</label>
        <input class="form-input" name="s3_access_key" value="<?= htmlspecialchars($s3['access_key']) ?>" placeholder="AKIA...">
      </div>
      <div class="form-group" style="flex:1;margin-bottom:0">
        <label class="form-label">Secret Key</label>
        <input class="form-input" type="password" name="s3_secret_key" value="<?= htmlspecialchars($s3['secret_key']) ?>" placeholder="留空不修改">
      </div>
    </div>
    <div class="form-row">
      <div class="form-group" style="flex:1;margin-bottom:0">
        <label class="form-label">Bucket</label>
        <input class="form-input" name="s3_bucket" value="<?= htmlspecialchars($s3['bucket']) ?>" placeholder="my-bucket">
      </div>
      <div class="form-group" style="flex:1;margin-bottom:0">
        <label class="form-label">Region</label>
        <input class="form-input" name="s3_region" value="<?= htmlspecialchars($s3['region']) ?>" placeholder="auto（R2 填 auto）">
      </div>
    </div>
    <div class="form-group">
      <label class="form-label">路径前缀（可选）</label>
      <input class="form-input" name="s3_path_prefix" value="<?= htmlspecialchars($s3['path_prefix']) ?>" placeholder="mapi/music 或留空">
    </div>
    <div class="form-group">
      <label class="form-label">自定义域名（可选）</label>
      <input class="form-input" name="s3_custom_domain" value="<?= htmlspecialchars($s3['custom_domain']) ?>" placeholder="https://cdn.example.com">
      <small style="display:block;margin-top:4px;color:rgba(0,0,0,.38);font-size:11px">留空则使用 Endpoint 拼接，填写后公开 URL 使用此域名</small>
    </div>
    <button class="btn btn-primary btn-block">保存</button>
  </form>
</div>

<div class="card">
  <div class="card-header"><span class="card-title"><?= svg('srv') ?> 易支付</span></div>
  <form method="post" action="?action=settings"><input type="hidden" name="_epay_submit" value="1"><input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
    <div class="form-group">
      <label class="form-label">接口地址</label>
      <input class="form-input" name="epay_api_url" value="<?= htmlspecialchars($epay['api_url']) ?>" placeholder="https://pay.example.com">
      <small style="display:block;margin-top:4px;color:rgba(0,0,0,.38);font-size:11px">易支付平台域名，不需要带 /submit.php</small>
    </div>
    <div class="form-row">
      <div class="form-group" style="flex:1;margin-bottom:0">
        <label class="form-label">商户 ID</label>
        <input class="form-input" name="epay_pid" value="<?= htmlspecialchars($epay['pid']) ?>" placeholder="1001">
      </div>
      <div class="form-group" style="flex:1;margin-bottom:0">
        <label class="form-label">商户密钥</label>
        <input class="form-input" type="password" name="epay_key" value="<?= htmlspecialchars($epay['key']) ?>" placeholder="商户通信密钥">
      </div>
    </div>
    <button class="btn btn-primary btn-block">保存</button>
  </form>
  <?php if ($epay['api_url'] && $epay['pid'] && $epay['key']): ?>
  <div style="margin-top:16px;padding-top:16px;border-top:1px solid rgba(0,0,0,.04)">
    <a href="?action=epay-test" class="btn btn-primary btn-block" style="background:rgba(46,204,113,.08);color:#2ecc71;border:1px solid rgba(46,204,113,.2)">测试支付 0.01 元</a>
  </div>
  <?php endif; ?>
</div>
<?php endif; ?>