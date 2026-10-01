# 表结构与自动补齐（MSAPI）

## 唯一来源：`install/sql/mysql.sql`

改结构**只改这一个文件**，PHP 不用管：

- **全新安装**：`install/handlers/init_mysql.php` / `init_sqlite.php` → `schema_sync_*()`
- **每次访问**：`install/upgrade.php`（首页与后台都会 require）→ `schema_sync_*()`
- **SQLite**：结构由 `mysql.sql` + 类型映射自动派生；`install/sql/sqlite.sql` 只是给人看的参考，**不会被读取**

## 一、自动生效（不用写任何指令）

| 你在 SQL 里的改动 | 系统行为 |
|---|---|
| 新增 `CREATE TABLE` | 自动建表（含主键、索引） |
| 表里新增一列 | 自动 `ALTER TABLE ADD COLUMN`（位置按 SQL 里的先后顺序） |
| 新增 `KEY` / `UNIQUE KEY` | 自动建索引；唯一键遇重复值会**降级为普通索引**，不让升级失败 |
| 索引的列或唯一性改了 | **自动重建**该索引（DROP + CREATE，不动数据） |
| 给没有主键的表加 `PRIMARY KEY` | 自动 `ADD PRIMARY KEY` |

## 二、需要写指令（默认只增不删）

写在 `mysql.sql` 的注释里，以 `-- @` 开头。**示例都用 `@@`，把 `@@` 改成 `@` 即生效**（避免示例被误执行）：

| 指令 | 含义 |
|---|---|
| `-- @sync <表>` | 该表**完全收敛**：补列 + 改列定义 + **删掉库里多出来的列** |
| `-- @sync-keep-extra <表>` | 同上，但保留库里多出来的列（只补 + 改） |
| `-- @modify <表>.<列> <定义>` | 显式改一列，例如 `-- @modify mapi_users.theme_mode VARCHAR(20) DEFAULT 'light'` |
| `-- @drop-column <表>.<列>` | 显式删列 |
| `-- @drop-index <表>.<索引名>` | 显式删索引 |
| `-- @drop-table <表>` | 显式删表 |

要点：

- 指令**只对点名的表/列/索引生效**；没有指令的表一律只增不删。
- 所有操作都幂等：执行过一次后，那条指令继续留着也不会重复动作（存在性检查 / 定义指纹）。
- `@modify` 与 `@sync` 的"改列定义"用 **定义指纹** 防抖：同一条指令不会被反复 `ALTER`（指纹存在 `mapi_config` 的 `_schema_def_<表>.<列>`）。

## 三、跳过标记（重要）

某次补列 / 补索引 / 改列**失败**时（典型：唯一键遇重复值、列定义非法），系统会往 `mapi_config` 记一条标记并**不再重试**（避免每次请求刷日志）。修好数据后想让它重试，删掉相应标记即可：

```sql
-- 补列/补索引失败标记
DELETE FROM mapi_config WHERE config_key LIKE '\_mig\_skip\_%';
-- 改列定义指纹（删掉后下次会重新按声明 ALTER）
DELETE FROM mapi_config WHERE config_key LIKE '\_schema\_def\_%';
-- SQLite 的整表重建签名
DELETE FROM mapi_config WHERE config_key LIKE '\_schema\_sync\_sqlite\_%';
```

## 四、解析器支持范围

`install/lib/schema.php` 只认下面这些常见形态：

- `CREATE TABLE [IF NOT EXISTS] name ( ... ) ENGINE=...`
- 列定义：`` `name` TYPE ... DEFAULT ... ``
- 表级 `PRIMARY KEY (...)` / `UNIQUE KEY name (...)` / `KEY name (...)`
- 行注释 `-- ...`（会被忽略）

**不支持**：`FULLTEXT KEY`、`CONSTRAINT ... FOREIGN KEY`、`CHECK (...)` 等——它们会被误当成列定义，导致那次 `ALTER` 失败并记一条跳过标记（**不会报错，但也不生效**）。真要用这些，先告诉我，我给解析器加支持或加忽略清单。

## 五、SQLite 差异

- 列类型按映射表转换（BIGINT/TINYINT/INT → INTEGER，DECIMAL/FLOAT → REAL，DATETIME/DATE/TEXT → TEXT，BLOB → BLOB，其它 → TEXT）
- 建表时**不保留 `DEFAULT`**（沿用原有行为）
- SQLite 不支持 `MODIFY` / `DROP COLUMN`，所以 `@sync` / `@modify` / `@drop-column` 统一用**重建表**实现（按列名把数据搬过去）
- 主键按 SQL 声明建（`id` → `INTEGER PRIMARY KEY AUTOINCREMENT`；自然主键 → `PRIMARY KEY (...)`）

## 六、安全建议

1. **默认只增不删**；删列/删表必须先写指令——系统不会"自己猜"你要删什么。
2. 删列/改列前**先备份**：MySQL 上 `ALTER` 缩小长度（如 `VARCHAR(200)` → `VARCHAR(50)`）可能截断数据。
3. 大表上的 `ALTER` / 重建会锁表，建议低峰操作。
4. 老库存在历史偏差时，唯一的例外清单在 `install/lib/schema.php` 的 `schema_legacy_skip_columns()`（目前只有 `mapi_stats.id`：早期代码建的表没有 id，且无任何代码使用）。
