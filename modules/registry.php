<?php
/**
 * modules/registry.php — 皮肤与内核的清单装载（唯一事实来源）
 *
 * api.php（前台分发器）与后台（皮肤选择器）都从这里取清单，
 * 避免"后台能选但分发器不认"这类两处清单不同步的问题。
 *
 * 清单文件：
 *   modules/core/core.json      内核模块（顺序固定）
 *   modules/<skin>/skin.json    皮肤（目录名必须等于 name）
 *
 * 任何一份坏清单只跳过它自己并记日志，不影响其它皮肤与整站。
 */

/** 内核模块清单 */
function msapi_core(): array {
    static $cache = null;
    if ($cache !== null) return $cache;

    $fallback = ['modules' => [], 'version' => '0', 'apiVersion' => 1];
    $file = __DIR__ . '/core/core.json';
    $raw  = @file_get_contents($file);
    $s    = $raw === false ? null : json_decode($raw, true);
    if (!is_array($s)) {
        error_log('[msapi] core.json 缺失或非法: ' . $file);
        return $cache = $fallback;
    }

    $modules = [];
    foreach ((array)($s['modules'] ?? []) as $m) {
        $m = basename((string)$m);
        if ($m !== '' && preg_match('/^[A-Za-z0-9_.-]+\.js$/', $m) && is_file(__DIR__ . '/core/' . $m)) {
            $modules[] = $m;
        }
    }
    return $cache = [
        'modules'    => $modules,
        'version'    => (string)($s['version'] ?? '0'),
        'apiVersion' => (int)($s['apiVersion'] ?? 1),
    ];
}

/** 皮肤名合法性：目录名即路由名 */
function msapi_skin_name_ok(string $name): bool {
    return (bool)preg_match('/^[a-z0-9][a-z0-9-]{0,31}$/', $name);
}

/** 全部皮肤清单（按名称排序） */
function msapi_skins(): array {
    static $cache = null;
    if ($cache !== null) return $cache;

    $out = [];
    foreach (glob(__DIR__ . '/*/skin.json') ?: [] as $file) {
        $name = basename(dirname($file));
        if (!msapi_skin_name_ok($name)) { error_log('[msapi] 皮肤目录名非法，已跳过: ' . $name); continue; }

        $raw = @file_get_contents($file);
        $s   = $raw === false ? null : json_decode($raw, true);
        if (!is_array($s)) { error_log('[msapi] skin.json 解析失败: ' . $file); continue; }

        // 目录名必须与清单里的 name 一致：防止手工改名后路由与清单错位
        $declared = (string)($s['name'] ?? '');
        if ($declared !== $name) { error_log('[msapi] skin.json 的 name 与目录不符: ' . $file); continue; }

        $mode = (($s['mode'] ?? 'modules') === 'monolith') ? 'monolith' : 'modules';

        $modules = [];
        if ($mode === 'modules') {
            foreach ((array)($s['modules'] ?? []) as $m) {
                $m = basename((string)$m);
                if ($m === '' || !preg_match('/^[A-Za-z0-9_.-]+\.js$/', $m)) continue;
                if (!is_file(__DIR__ . '/' . $name . '/' . $m)) {
                    error_log('[msapi] 皮肤模块缺失，清单已跳过: ' . $name . '/' . $m);
                    continue 2;
                }
                $modules[] = $m;
            }
        }

        $entry = basename((string)($s['entry'] ?? 'embed.js'));
        if ($mode === 'monolith' && !is_file(__DIR__ . '/' . $name . '/' . $entry)) {
            error_log('[msapi] 单体皮肤入口缺失，已跳过: ' . $name . '/' . $entry);
            continue;
        }

        $out[$name] = [
            'name'        => $name,
            'displayName' => (string)($s['displayName'] ?? $name),
            'description' => (string)($s['description'] ?? ''),
            'version'     => (string)($s['version'] ?? '0'),
            'apiVersion'  => (int)($s['apiVersion'] ?? 1),
            'mode'        => $mode,
            'modules'     => $modules,
            'entry'       => $entry,
            'hidden'      => !empty($s['hidden']),
        ];
    }

    ksort($out);
    return $cache = $out;
}

/** 单个皮肤；不存在返回 null */
function msapi_skin(string $name): ?array {
    $all = msapi_skins();
    return $all[$name] ?? null;
}

/** 默认皮肤名（清单为空时兜底 router） */
function msapi_default_skin(): string {
    $all = msapi_skins();
    if (isset($all['router'])) return 'router';
    foreach ($all as $n => $s) { if (empty($s['hidden'])) return $n; }
    return 'router';
}

/**
 * 某皮肤可对外提供的模块文件白名单（asset 请求用）。
 * 命中内核清单或皮肤清单才允许输出 —— 白名单来自清单，不再硬编码文件名数组。
 */
function msapi_skin_assets(string $name): array {
    $skin = msapi_skin($name);
    if (!$skin) return [];
    $list = msapi_core()['modules'];
    if ($skin['mode'] === 'monolith') {
        $list[] = $skin['entry'];
    } else {
        $list = array_merge($list, $skin['modules']);
    }
    return $list;
}
