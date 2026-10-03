# 播放器皮肤架构方案

> 目标：保留 `modules/<skin>/` 目录 + `?route=<skin>` 这种索引结构，但**新增一个皮肤只需要新建目录和一份清单**，不改内核。

---

## 一、现状：已经是雏形，但有 6 处硬编码

| # | 位置 | 内容 | 加皮肤时必须改吗 |
|---|---|---|---|
| 1 | `modules/api.php` L37-40 | `$routes` 路由表（route → 目录） | ✅ 必改 |
| 2 | `modules/api.php` L51 | `$allowed` 资源白名单（硬编码 10 个文件名） | ✅ 必改 |
| 3 | `modules/api.php` L380 | `$moduleFiles` 模块加载顺序 | ✅ 必改 |
| 4 | `modules/api.php` L74-97 | `if ($route === 'chiropractic')` —— **内核里的皮肤专属分支** | ⚠️ 新增带 wrapper 的皮肤就要改 |
| 5 | `admin/api/relay.php` L39 | `'embed_js' => '/modules/api.php?route=router'` | ⚠️ 换默认皮肤要改 |
| 6 | `admin/assets/{pc,mobile}/js/{keys,base}.js` | `RELAY.embed_js \|\| '/modules/api.php?route=router'` 兜底串，四处重复 | ⚠️ 同上 |

只要这 6 处还在，"加皮肤"就不是加目录，而是**改内核**——迟早会改漏一处（比如忘了加进 `$allowed`，资源就 404，而且只在运行时才发现）。

---

## 二、目标形态

```
modules/
├── api.php                  # 内核分发器：只认清单，不含任何皮肤专属代码
├── core/                    # 共享内核（全站唯一一份）
│   ├── core.json            #   { "version": "1.0.0", "modules": [...] }
│   ├── auth.js              #   鉴权 + 授权卡片
│   ├── state.js             #   Cookie 状态读写
│   ├── player.js            #   APlayer 内核 + 播放控制 + 生命周期
│   └── lyrics.js            #   LRC 解析与同步
└── skins/
    ├── router/              # 皮肤 = 只放"长得不一样"的部分
    │   ├── skin.json        #   ← 新增皮肤 = 加目录 + 这个文件
    │   ├── widget.js        #   DOM 结构 + shadow root 内联样式
    │   ├── ui.js            #   面板交互
    │   ├── drag.js          #   拖拽与停靠
    │   ├── immersive.js
    │   └── theme.js
    ├── chiropractic/        # 旧版单体：清单里声明 mode=monolith，先原样兼容
    │   ├── skin.json
    │   └── embed.js
    └── _template/           # 新皮肤脚手架，复制即用
        ├── skin.json
        └── widget.js
```

### `skin.json` 示例

```json
{
  "name": "router",
  "displayName": "顺雅 · 玻璃",
  "version": "2.1.0",
  "apiVersion": 1,
  "mode": "modules",
  "modules": ["widget.js", "ui.js", "drag.js", "immersive.js", "theme.js"],
  "capabilities": { "drag": true, "immersive": true, "lyrics": true, "announcement": true }
}
```

```json
{
  "name": "chiropractic",
  "version": "1.0.0",
  "apiVersion": 1,
  "mode": "monolith",
  "entry": "embed.js"
}
```

内核按 `mode` 分派：`monolith` 走现在的 wrapper 分支，`modules` 走"core 顺序加载 + 皮肤模块追加"。**皮肤专属逻辑从 `api.php` 消失**（第 4 处硬编码消失）。

`api.php` 的改造点：

```php
$skin = msapi_load_skin($route);   // 读 modules/skins/<route>/skin.json
// 白名单 = core 清单 + 皮肤清单（不再是硬编码数组）
// 模块顺序 = core 顺序 + 皮肤顺序（皮肤写错顺序也打乱不了内核）
```

---

## 三、三条硬规矩（都是这几轮踩出来的）

### 规矩 1：皮肤不许碰宿主页

皮肤能碰的只有自己那个 shadow host。**不得**注入宿主 `<head>` 样式、**不得**改宿主的滚动条 / `overflow` / `<meta>`。

> 反面教材就在本仓库里：旧版往宿主页注入 `html::-webkit-scrollbar{display:none}` 和 `html,body{overflow:hidden!important}`，还往 head 塞 APlayer 的样式表。这些在"嵌在别人博客里"的前提下全是净损失，刚刚才清掉。以后新皮肤一旦有类似冲动，直接判定为设计错误。

### 规矩 2：幂等与生命周期归内核，皮肤不许各写一份

下面这些**必须只有一份实现**：

- 重复执行去重（swup / Pjax / Turbo 会重执行 head 脚本）
- 视口尺寸变化后的重排
- 停靠夹取（可见范围不越界）
- 销毁与残留清理（`destroy()`）
- 鉴权、状态持久化、歌词解析

> 这几轮修的 4 个 bug 里有 3 个属于内核职责。如果每个皮肤各写一份，同样的坑要重踩 N 遍。

### 规矩 3：皮肤只写"看起来不一样的东西"

皮肤目录里应该只有：**DOM 结构、CSS、交互绑定、皮肤特有动效**。
一旦发现自己在皮肤里写 `XMLHttpRequest`、`APlayer`、Cookie 读写，说明这段该往上提到 core。

---

## 四、皮肤契约（要固化下来）

### 必须提供

| 接口 | 说明 |
|---|---|
| `MP.getHTML()` | 返回面板 DOM 字符串（塞进 shadow root 的 `[data-mp="root"]` 内） |
| `MP._css` | shadow root 内联样式（**唯一**允许的样式载体） |
| `MP.mount(root)` | 可选：核心创建 shadow host 后由皮肤接管挂载 |

### DOM 契约

`data-mp="..."` 的取值就是 core ↔ 皮肤之间的接口名（core 的 `MP.$()` 按它取元素）。
建议在 `docs/skin-api.md` 里冻结一个**最小必需集合**（`toggle` / `panel` / `playBtn` / `prevBtn` / `nextBtn` / `pbar` / `vol`），其余名字皮肤自由发挥。

### 生命周期钩子

```
onInit → onConfig → onMount → onOpen / onClose
       → onPlay / onPause / onSwitch
       → onResize / onDockChange
       → onDestroy
```

钩子由 core 统一广播，皮肤按需实现。**不要**让皮肤监听 DOM 事件去反推播放状态。

### 版本协商

`skin.json` 的 `apiVersion` 与 `core.json` 的不匹配时，core 直接 `console.error` 并拒绝启动 —— 宁可明显坏掉，也不要"皮肤静默半坏"。

---

## 五、分发与缓存

| 项 | 现状 | 建议 |
|---|---|---|
| `api.php`（入口） | `no-cache, must-revalidate` | 保持不变，入口必须最新 |
| 模块资源 | ETag + `no-cache` → **每次页面加载对 9 个模块发条件请求** | URL 带内容哈希 `?v=<hash>` + `Cache-Control: max-age=31536000, immutable`，只在文件变化时换 URL |
| `skin.json` | — | 参与 ETag，随入口一起校验 |

对宿主博客是实打实的首屏收益：从"9 次条件请求"降到 0 次。

---

## 六、安全

1. **白名单从"硬编码文件名"改成"清单里必须列出"** —— 天然多一道门（`basename()` 之外的第二层）。
2. 皮肤目录名限制 `^[a-z0-9][a-z0-9-]{0,31}$`；清单里的文件名同样校验，禁止 `..` 与 `/`。
3. 未知 route 继续返回一行 `console.error` 的 JS（已有），不要 500。

---

## 七、后台与接入

- `relay.php` 的 `embed_js` 改成"**默认皮肤**"配置项：后台加一个下拉，从 `modules/skins/*/skin.json` 枚举（显示 displayName），生成嵌入代码时用它。
- 嵌入标签仍可显式 `?route=<skin>` 覆盖默认皮肤。
- 第 6 处硬编码（四处重复的兜底串）统一收敛到后端注入的一个变量，不要在前端各写一份。

---

## 八、落地顺序（三步，每步都能独立上线）

### 第 1 步：抽清单（零行为变化）
把现有 `$routes` / `$allowed` / `$moduleFiles` / chiropractic 特判挪进两份 `skin.json`，`api.php` 改成读清单。
**产出**：功能与现在完全等价，可先用现有回归脚本验证，再上线。

### 第 2 步：抽内核
把 `auth.js` / `state.js` / `player.js` / `lyrics.js` 提到 `modules/core/`；`router` 皮肤只留 `widget/ui/drag/immersive/theme`。
`chiropractic` 暂不拆（150KB 单体，改造风险大），清单里声明 `mode=monolith` 原样兼容。
**产出**：两个皮肤共享同一份内核，修一次 bug 两边都好。

### 第 3 步：固化契约 + 脚手架
写 `docs/skin-api.md`（DOM 契约 + 钩子 + 禁止事项），加 `modules/skins/_template/`，后台加默认皮肤下拉。
**产出**：以后"设计新皮肤"= 复制 `_template` + 写 DOM/CSS。

---

## 九、建议不要做的事

| 不做 | 原因 |
|---|---|
| 皮肤动态上传 / 在线安装 | 皮肤是开发者产物，不是用户产物；引入沙箱与安全成本 |
| iframe 隔离 | shadow DOM 已够；iframe 会和拖拽、全屏、宿主滚动打架 |
| 每个皮肤自带一份内核 | 版本分叉是维护灾难，`chiropractic/embed.js`（150KB 单体）就是现成教训 |
| 皮肤自行管理生命周期 | 见规矩 2 |
| 皮肤往宿主页注入任何东西 | 见规矩 1 |

---

## 十、一句话总结

> **内核管"怎么活"（加载、去重、鉴权、状态、音频、生命周期），皮肤只管"长什么样"（DOM + CSS + 交互）。**
> 判断一个东西该放哪边，问一句：**换皮肤时它要不要跟着变？** 不变就是内核。
