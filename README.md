# Shunya（顺雅）—— 全息音乐播放器

> 你的网站，自带旋律。一行代码，零冲突。

基于 **Shadow DOM** 隔离技术的轻量级嵌入式音乐播放器。只需在任意网页中放入一行 `<script>` 标签，即可为访客带来完整的音乐播放体验——悬浮、拖拽、自动适配暗色模式，且与宿主页面 CSS 完全隔离。

**支持平台**：QQ 音乐、网易云音乐  
**播放引擎**：[APlayer](https://github.com/DIYgod/APlayer)

---

## 特性

- **Shadow DOM 隔离** — 通过 `attachShadow({ mode: 'closed' })` 封装全部样式与 DOM，与宿主页面零 CSS 冲突。
- **一行嵌入** — 仅需 `<script src=".../embed.js" key="YOUR_KEY"></script>` 即可完成集成。
- **多歌单切换** — 同时支持 QQ 音乐和网易云音乐歌单，一键切换。
- **沉浸模式** — 全屏播放体验，大尺寸封面、滚动歌词、完整控制栏。
- **歌词同步** — 实时 LRC 解析，逐字精准高亮；桌面端支持双语歌词。
- **暗色主题** — 基于北京时间（18:00–06:00）自动切换深色/浅色模式，也可手动固定。
- **自由拖拽** — 悬浮按钮可拖至屏幕任意位置，松手自动吸附至屏幕边缘。
- **音量增强** — 基于 Web Audio API 的三档增益调节：1 倍 / 2 倍 / 3 倍。
- **播放模式** — 列表循环、单曲循环、随机播放。
- **Cookie 持久化** — 跨会话记忆音量、歌单、播放进度和主题偏好。
- **隐私合规** — 内置 Cookie 授权弹窗，用户明确同意后才存储数据。
- **管理后台** — 在 `/admin/` 中管理 API 密钥、歌单、公告，查看调用统计。

---

## 快速开始

### 环境要求

| 组件     | 最低版本              |
|----------|-----------------------|
| PHP      | 8.5+                  |
| 数据库   | MySQL 5.7+ 或 SQLite 3 |
| Web 服务器 | Apache / Nginx / PHP 内置服务器 |

### 安装步骤

1. 将项目**克隆或解压**到 Web 服务器根目录（如 `/var/www/msapi` 或 `C:\xampp\htdocs\msapi`）。

2. 将 Web 服务器**指向**项目目录，并确保已启用 `mod_rewrite`（Apache）或等效的 URL 重写功能。

3. 在浏览器中访问 `/install/`，安装向导将引导你完成：
   - 数据库配置（MySQL 或 SQLite）
   - 管理员账户创建
   - 初始 API 密钥设置

4. 安装完成后，建议**删除或限制** `/install/` 目录的访问权限。

> 如果 `config/config.php` 已存在且配置正确，安装程序会自动跳过。

### PHP 内置服务器（快速测试）

```bash
php -S 127.0.0.1:8080 -t /path/to/Msapi
```

然后打开 `http://127.0.0.1:8080/install/` 开始配置。

---

## 使用方式

### 嵌入播放器

在任意页面的 `</body>` 之前添加以下 `<script>` 标签：

```html
<script src="https://your-domain.com/embed.js" key="YOUR_API_KEY"></script>
```

播放器将以悬浮音符按钮的形式出现在页面右下角，点击即可展开控制面板。

### 自定义参数

嵌入脚本支持在 `<script>` 标签上设置以下属性：

| 属性    | 说明                             | 默认值       |
|---------|----------------------------------|--------------|
| `key`   | API 密钥（必填）                 | —            |
| `api`   | 自定义 API 端点 URL              | 自动检测     |
| `token` | 预验证的授权令牌（跳过密钥校验） | —            |

### 通过管理后台配置

登录 `/admin/` 后可配置以下项：

- **歌单** — 添加 QQ 音乐或网易云音乐歌单 ID
- **主题** — 自动（按时间）或强制浅色/深色
- **自动播放** — 页面加载时是否自动播放
- **歌词** — 默认歌词显示开关
- **服务器** — 默认音乐源（`tencent` 或 `netease`）
- **公告** — 向所有嵌入播放器推送广播消息

---

## 项目结构

```
Msapi/
├── index.php              # 首页（顺雅 · 声波宇宙）
├── embed.js               # 嵌入式播放器脚本（Shadow DOM + APlayer）
├── embed.css              # 播放器样式（构建时内联）
├── config/
│   └── config.php         # 数据库与站点配置
├── install/
│   ├── index.php          # 安装向导入口
│   ├── init.php           # 安装逻辑
│   ├── upgrade.php        # 数据库迁移
│   ├── sql/
│   │   ├── mysql.sql      # MySQL 数据库结构
│   │   └── sqlite.sql     # SQLite 数据库结构
│   └── install.lock       # 防止重复安装
├── admin/
│   ├── index.php          # 管理后台首页
│   ├── dashboard.php      # 统计概览
│   ├── keys.php           # API 密钥管理
│   ├── playlists.php      # 歌单配置
│   ├── settings.php       # 全局设置
│   ├── users.php          # 用户管理
│   ├── shop.php           # 订阅 / 商城
│   ├── orders.php         # 订单管理
│   ├── login.php          # 管理员登录
│   ├── profile.php        # 个人资料
│   └── api/
│       └── api.php        # 管理后台 API 端点
├── api/
│   ├── api.php            # 公开 API（歌单获取、密钥校验）
│   ├── qq_api.php         # QQ 音乐代理
│   └── wy_api.php         # 网易云音乐代理
├── lib/
│   ├── db.php             # 数据库抽象层
│   ├── auth.php           # 认证工具
│   ├── config.php         # 配置加载器
│   ├── mail.php           # 邮件服务
│   ├── epay.php           # 支付集成
│   ├── pusher.php         # 实时通知
│   ├── s3.php             # 对象存储
│   └── webauthn.php       # WebAuthn 支持
├── layout/
│   ├── header.php         # 后台布局头部
│   └── footer.php         # 后台布局底部
├── template/
│   ├── header.php         # 前台模板头部
│   └── footer.php         # 前台模板底部
└── assets/
    ├── css/
    │   ├── pc.css         # 桌面端首页样式
    │   └── mobile.css     # 移动端首页样式
    ├── js/
    │   ├── pc.js          # 桌面端交互
    │   └── mobile.js      # 移动端交互
    └── lib/
        └── db.php         # 统计数据库连接
```

---

## API 端点

| 端点                                           | 方法 | 说明                                   |
|------------------------------------------------|------|----------------------------------------|
| `/admin/api/api.php?action=verify-key`         | POST | 校验 API 密钥，返回 Token              |
| `/admin/api/api.php?action=get-config`         | GET  | 获取歌单与主题配置                     |
| `/admin/api/api.php?action=get-announcement`   | GET  | 获取当前公告                           |
| `/admin/api/api.php?action=playlist`           | GET  | 获取歌单歌曲（参数：`id`、`server`、`limit`） |

---

## 技术栈

- **前端**：原生 JavaScript（ES5+）、Shadow DOM、APlayer 1.10.1
- **后端**：PHP、MySQL / SQLite
- **音乐 API**：QQ 音乐与网易云音乐代理转发
- **CDN 依赖**：APlayer CSS 与 JS 通过 jsDelivr 加载

---

## 开源许可

[MIT](LICENSE)

---

*献给每一张值得拥有背景音乐的网页。*
