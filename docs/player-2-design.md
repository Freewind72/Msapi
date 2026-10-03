# 播放器 2.0：key 入口重写 + 皮肤体系 · 设计方案

> 决议：① 皮肤按配置页设置（存用户名下），由 key 解析到人；② 重写入口为根目录 `/api.php?key=密钥`；
> ③ 后台为自用设计，不做过度的多租户抽象；④ 样式一律写在 JS 里（`MP._css`），不落独立 CSS 文件。

---

## 一、新入口契约：`GET /api.php`

| 参数 | 必需 | 说明 |
|---|---|---|
| `key` | ✅ | API 密钥。服务端据此解析 → 用户 → 该用户配置的皮肤 |
| `route` | — | 临时覆盖皮肤（预览 / 调试 / 同站多皮肤） |
| `asset` | — | 输出指定模块文件（静态，**不查库**） |
| `skin` | — | 与 `asset` 搭配；由 bootstrap 生成的 URL 自带 |

**两种响应**

1. **无 `asset`** → 启动脚本（bootstrap）：解析 key → 皮肤 → 生成模块清单
2. **有 `asset`** → 模块文件 + ETag（皮肤目录优先，找不到落 `modules/core/`）

**为什么 `asset` 不带 key**：一次页面加载要取 ~9 个模块文件，若每个都带 key 就是 9 次数据库查询。
bootstrap 已经解析过一次，把结果（皮肤名）写进模块 URL 即可：
`/api.php?skin=rose&asset=widget.js`。模块文件不是机密，公开可访问无所谓；
真正需要鉴权的是 `get-config`（歌单数据），那本来就已经校验 key。

**嵌入代码从这样**

```html
<script src="https://msapi/modules/api.php?route=router" key="32783c..." ></script>
```

**变成这样**

```html
<script src="https://msapi.bmwy72.top/api.php?key=32783c..." defer></script>
```

> 附带收益：`key` 从**自定义属性**变成**查询参数**。前者可能被 Halo / WordPress 的编辑器或
> HTML 净化器剥掉（播放器静默失效），后者剥不掉。这一步本身就提升了嵌入健壮性。

---

## 二、key → 皮肤 的解析

```sql
SELECT u.id, u.player_skin
FROM mapi_keys k JOIN mapi_users u ON u.id = k.user_id
WHERE k.api_key = ? AND k.status = 1
LIMIT 1
```

- `mapi_keys.api_key` 上已有 `idx_api_key` 索引，`mapi_users.id` 是主键 ⇒ 一次索引查询，成本可忽略；
- **DB 不可用时 fail-open**：回落 `router` 并照常输出 bootstrap。这与现有 `verify-key` 的
  `ok (db offline)` 行为一致 —— 数据库抖动不该让所有嵌入站点的播放器一起消失；
- **key 无效 / 被停用**：输出一行 `console.error('[Msapi] 密钥无效或已停用')` 并**停止**（不再加载 9 个模块），
  比现在"加载完再报错"省一整轮请求。

---

## 三、皮肤按「配置页 → 用户」存

- 配置页（`?action=config`）选皮肤 → 存 `mapi_users.player_skin`
  （沿用 `player_pos` 的**缺列自动补**迁移模式，零停机、零手工 SQL）；
- 该用户名下**所有 key** 渲染同一套皮肤 —— 这就是"配置页负责选择和应用"；
- 密钥页每个 key 提供**「预览」** → 打开 `/?preview=<key>`，落地页按该 key 加载播放器，
  看到的就是这个 key 实际渲染的效果 —— 这就是"密钥负责浏览已配置的播放器效果"。

> 若将来要"一个账号下不同 key 用不同皮肤"，只需把 `player_skin` 从 `mapi_users` 挪到 `mapi_keys`，
> 解析 SQL 加一个 `COALESCE(k.player_skin, u.player_skin)` 即可 —— 现在不做，留好这个口子。

---

## 四、目录结构（`modules/` 只放功能板块）

```
/api.php                 ← 唯一对外入口（原 modules/api.php 重写后移上来）
/index.php               ← 落地页（支持 ?preview=<key>）
/modules/
  registry.php           ← 皮肤清单装载，被 api.php 与后台共用
  core/                  ← 引擎：auth / state / player / ui / lyrics / theme
  router/  rose/  chiropractic/   ← 皮肤（各含 skin.json）
  api.php                ← 兼容壳（见第五节），老嵌入全部下线后可删
/config/
  config.php             ← 数据库配置
  player.php             ← （可选）皮肤解析缓存，减少热点查询
```

**`skin.json`**（三大皮肤各一份）

```json
{
  "name": "rose",
  "displayName": "玫瑰玻璃",
  "description": "侧边常驻卡片，玫瑰渐变玻璃质感；不可拖动，只贴左右边，点贴边箭头收起",
  "version": "1.0.0",
  "apiVersion": 1,
  "mode": "modules",
  "modules": ["widget.js", "skin.js"]
}
```

`chiropractic` 声明 `"mode":"monolith","entry":"embed.js","hidden":true`（旧版保留可达，不进后台选择器）。

---

## 五、兼容：老嵌入零改动

`/modules/api.php` 保留为一个**转发壳**（约 10 行 JS）：

```js
(function () {
  var cur = document.currentScript;
  var key = cur && cur.getAttribute('key');            // 老标签：key 在属性上
  if (!key) { console.error('[Msapi] 请改用 /api.php?key=你的密钥'); return; }
  var route = '';
  try { route = new URL(cur.src).searchParams.get('route') || ''; } catch (e) {}
  var s = document.createElement('script');
  s.src = _BASE_ + '/api.php?key=' + encodeURIComponent(key) + (route ? '&route=' + route : '');
  document.head.appendChild(s);
})();
```

**效果**：已经嵌出去的老代码**一行都不用改**，自动走新入口、自动拿到按 key 配置的皮肤。
这是"要求稳"最关键的一环 —— 否则一次重写就会让所有存量站点同时失效。

---

## 六、后台改动

| 位置 | 改什么 |
|---|---|
| `admin/pages/config.php` | 新增「播放器皮肤」区块：**单选卡片**（不是下拉，皮肤是"看"出来的）+ 每个卡片一个「预览 ↗」 |
| `admin/handlers/config_user.php` | 保存 `player_skin`：**服务端用 registry 白名单校验**（拒绝伪造值）→ 缺列自动补 → 落库 → 换皮肤时清 `mapi_pos` 记忆（复用现有"位置改了清记忆"的写法） |
| `admin/pages/keys.php` + `admin/assets/{pc,mobile}/js/keys.js` | 每个 key 加「预览」按钮 → `/?preview=<key>` |
| `admin/api/relay.php` | `embed_js` 由 `/modules/api.php?route=router` 改为 `/api.php`（裸路径，key 由生成器拼） |
| `admin/assets/{pc,mobile}/js/{keys,base}.js` | 生成代码改为 `<script src="…/api.php?key=' + key + '" defer></script>`；**删掉四处重复的 `|| '…?route=router'` 兜底串** |
| `assets/lib/widget.php` + `index.php` | 支持 `?preview=<key>`：按 key 生成嵌入地址并自动加载 |
| `install/` | `mapi_users.player_skin` 列加入建表语句与迁移定义（与 `player_pos` 同处） |

---

## 七、失败与边界

| 情况 | 期望行为 |
|---|---|
| 数据库不可用 | **fail-open**：按 `router` 输出 bootstrap，播放器照常出现（与 `verify-key` 的 db offline 一致） |
| `key` 缺失 | 输出 `console.error` 并停止（提示正确用法） |
| `key` 无效 / 停用 | 输出 `console.error` 并停止 |
| `route` 指向不存在的皮肤 | 回落该 key 配置的皮肤，并 `console.error` |
| `skin.json` 缺失 / 字段非法 | registry **跳过该皮肤**并记日志；若正是当前皮肤 → 回落 `router` |
| 皮肤模块文件缺失 | 回落 `router`，不要让嵌入方白屏 |
| 皮肤目录名与 `name` 不符 | registry 拒绝装载（防手工改名造成的错位） |
| 老嵌入（带 key 属性） | 转发壳接管，零改动可用 |
| 老嵌入的 `?route=` | 透传给新入口，行为不变 |
| 皮肤 `apiVersion` 与内核不符 | `console.error` 并拒绝启动（宁可明显坏，不要静默半坏） |

---

## 八、落地顺序（每批都能独立上线 + 回归）

**第 1 批 · 清单化（零行为变化）**
新增三份 `skin.json` + `modules/registry.php`，`modules/api.php` 改为读清单。
验收：`?route=router` / `?route=rose` / `?route=chiropractic` 三条路由行为与现在**逐项一致**。

**第 2 批 · 新入口 + 兼容壳**
根目录 `api.php`（新契约：key 解析、fail-open、asset 静态输出）+ `modules/api.php` 转成转发壳。
验收：新式标签可用；**老式标签零改动仍可用**；DB 停掉时仍能出播放器；无效 key 只报一行错。

**第 3 批 · 后台与生成器**
配置页皮肤选择器 + 密钥页预览 + `relay.php` / 生成器改造 + `install` 迁移列。
验收：配置页换皮肤 → 嵌入页立即变化；伪造皮肤值被拒；密钥页预览打开即见真实效果。

**第 4 批 · 收尾**
更新 README 与两份 docs；确认存量站点全部迁移后，删除转发壳。

---

## 九、明确不做

| 不做 | 原因 |
|---|---|
| 皮肤带独立 CSS 文件 | 决议④：样式唯一载体是 `MP._css`（内联 shadow root），顺带避开跨域与阻塞渲染 |
| 为多租户做权限/配额抽象 | 决议③：后台自用，等真需要再说 |
| 皮肤在线安装 / 热插拔 | 引入上传与沙箱是另一个量级的安全面 |
| 让 `asset` 请求也带 key | 每页 9 次多余查询，且模块文件本就非机密 |
| 现在就把皮肤挪到 `mapi_keys` | 先按用户存、留好 `COALESCE` 口子；等真有"同账号多皮肤"需求再迁 |
