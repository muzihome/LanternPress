<?php
declare(strict_types=1);

if (!defined('__TYPECHO_ROOT_DIR__')) exit;

// ============================================================
// cache.php — 统一文件缓存层（原语 + 内容缓存包装 + 配置指纹 + IP 限流）
// 目录约定：主题根 cache/（限流与 remember 分组文件）、cache/content/（正文缓存）；
// 所有缓存文件以 PHP 退出守卫前缀（短开标签加 exit 语句）防直读，写入均为 tmp + rename 原子写。
// 依赖：security.php（lt_log_error）
// ============================================================

// 内容缓存结构版本：正文处理相关文件（inc/core.php / inc/format.php / inc/content.php / functions.php / post.php / sidebar.php）任一变更即自动派生新版本，
// 全站正文缓存一次性失效并重建——替代发布时手动递增 LT_CONTENT_CACHE_VERSION（避免遗漏导致旧缓存串新逻辑）
$ltContentCacheFiles = [__DIR__ . '/core.php', __DIR__ . '/format.php', __DIR__ . '/content.php', dirname(__DIR__) . '/functions.php', dirname(__DIR__) . '/post.php', dirname(__DIR__) . '/sidebar.php'];
$ltContentCacheSig = '';
foreach ($ltContentCacheFiles as $ltContentCacheFile) {
    // 指纹 = mtime + 文件大小 + 首尾 64 字节内容摘要：git checkout / rsync 保留时间戳时内容变更同样触发缓存失效
    $ltContentCacheSig .= (string) @filemtime($ltContentCacheFile);
    $ltContentCacheSig .= (string) @filesize($ltContentCacheFile);
    $ltContentCacheHead = @file_get_contents($ltContentCacheFile, false, null, 0, 64);
    $ltContentCacheTail = @file_get_contents($ltContentCacheFile, false, null, max(0, (int) @filesize($ltContentCacheFile) - 64));
    $ltContentCacheSig .= (string) $ltContentCacheHead . (string) $ltContentCacheTail;
}
define('LT_CONTENT_CACHE_VERSION', '4-' . substr(md5($ltContentCacheSig), 0, 8));

/**
 * 缓存目录解析（按子目录静态缓存结果）：不存在则创建（0755 递归），
 * 并补写 Apache 2.2/2.4 双版本兼容的全拒 .htaccess；创建失败返回 '' 并记日志。
 */
function lt_cache_dir(string $sub = ''): string
{
    static $dirs = [];
    if (isset($dirs[$sub])) {
        return $dirs[$sub];
    }
    $dir = dirname(__DIR__) . '/cache' . ($sub !== '' ? '/' . ltrim($sub, '/') : '');
    if (!is_dir($dir)) {
        if (!@mkdir($dir, 0755, true) || !is_dir($dir)) {
            lt_log_error('lt_cache_dir: cannot create cache directory: ' . $dir);
            $dirs[$sub] = '';
            return '';
        }
    }

    $htaccess = $dir . '/.htaccess';
    if (!is_file($htaccess)) {
        @file_put_contents(
            $htaccess,
            "<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nOrder allow,deny\nDeny from all\n</IfModule>\n"
        );
    }
    $dirs[$sub] = $dir;
    return $dir;
}

// 读取缓存文件原始载荷（剥退出守卫前缀）；文件缺失/不可读/前缀不符返回 null
function lt_cache_read(string $path): ?string
{
    if (!is_file($path)) {
        return null;
    }
    $data = @file_get_contents($path);
    if ($data === false || strpos($data, '<?php exit; ?>') !== 0) {
        return null;
    }
    return substr($data, strlen('<?php exit; ?>'));
}

// 原子写入缓存文件（自动加守卫前缀，tmp + rename 防半文件）；空载荷/超限/写失败返回 false
function lt_cache_write(string $path, string $payload, int $maxLen = 60000): bool
{
    if ($payload === '') {
        return false;
    }
    if (strlen($payload) > $maxLen) {
        lt_log_error('lt_cache_write: payload too large path=' . $path . ' len=' . strlen($payload));
        return false;
    }
    $tmp = dirname($path) . '/.' . basename($path) . '.tmp.' . bin2hex(random_bytes(4));
    if (@file_put_contents($tmp, '<?php exit; ?>' . $payload, LOCK_EX) === false) {
        return false;
    }
    if (!@rename($tmp, $path)) {
        @unlink($tmp);
        return false;
    }
    return true;
}

function lt_cache_delete(string $path): void
{
    if (is_file($path)) {
        @unlink($path);
    }
}

/**
 * 分组 TTL 缓存（remember 模式）：cache/{group}_{md5(key)}.php，JSON 编解码。
 * 命中且未过期（filemtime 判定）直接返回；否则执行 $load 重建，结果为数组才落盘。
 * 目录不可用时直通 $load（不缓存），与历史内联实现行为一致。
 */
function lt_cache_remember(string $group, string $key, int $ttl, callable $load): mixed
{
    $dir = lt_cache_dir();
    if ($dir === '') {
        return $load();
    }
    $path = $dir . '/' . $group . '_' . md5($key) . '.php';
    if ($ttl > 0 && is_file($path) && time() - (int) @filemtime($path) < $ttl) {
        $raw = lt_cache_read($path);
        if ($raw !== null) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }
    }
    $data = $load();
    if (is_array($data)) {
        lt_cache_write($path, (string) json_encode($data, JSON_UNESCAPED_UNICODE));
    }
    return $data;
}

function lt_cache_forget(string $group, string $key): void
{
    $dir = lt_cache_dir();
    if ($dir === '') {
        return;
    }
    lt_cache_delete($dir . '/' . $group . '_' . md5($key) . '.php');
}

// ============================================================
// 海报代理图床域名白名单下发（H2）
// 后台「文章页组件」→ posterProxyHosts 配置的域名（逗号/换行分隔）经校验后
// 原子写入 cache/poster_hosts.php（PHP 数组字面量文件，无敏感输出无需退出守卫）；
// poster-proxy.php 独立入口（不加载 Typecho 引导）每次请求 include 该文件获取白名单。
// 每次页面渲染调用（header.php），内容未变时跳过写入；域名格式非法条目直接丢弃（安全默认）。
// ============================================================
function lt_sync_poster_hosts(string $raw): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    $hosts = [];
    foreach (preg_split('/[\r\n,]+/', $raw) ?: [] as $ltLine) {
        $ltHost = strtolower(trim($ltLine));
        if ($ltHost === '' || str_starts_with($ltHost, '#')) {
            continue;
        }
        // 仅允许域名或 '.domain' 前缀通配（与 poster-proxy.php 白名单判定一致）
        if (preg_match('/^(?:\.)?(?:[a-z0-9](?:[a-z0-9-]*[a-z0-9])?\.)*[a-z0-9](?:[a-z0-9-]*[a-z0-9])?$/', $ltHost) !== 1) {
            continue;
        }
        $hosts[] = $ltHost;
    }
    $hosts = array_values(array_unique($hosts));
    $payload = "<?php\nreturn " . var_export($hosts, true) . ";\n";
    $dir = lt_cache_dir();
    if ($dir === '') {
        return;
    }
    $path = $dir . '/poster_hosts.php';
    if (@file_get_contents($path) === $payload) {
        return;
    }
    $tmp = $path . '.' . bin2hex(random_bytes(4)) . '.tmp';
    if (@file_put_contents($tmp, $payload, LOCK_EX) !== false && @rename($tmp, $path)) {
        return;
    }
    @unlink($tmp);
}

function lt_get_config_hash(): string
{
    return md5('lt-content-v' . LT_CONTENT_CACHE_VERSION);
}

// ---- 正文缓存（cache/content/{cid}.php 原始字符串载荷，统一原语的薄包装） ----

function lt_content_cache_dir(): string
{
    return lt_cache_dir('content');
}

function lt_content_cache_get(int $cid): ?string
{
    if ($cid <= 0 || ($dir = lt_content_cache_dir()) === '') {
        return null;
    }
    return lt_cache_read($dir . '/' . $cid . '.php');
}

function lt_content_cache_set(int $cid, string $payload): void
{
    if ($cid <= 0 || $payload === '' || ($dir = lt_content_cache_dir()) === '') {
        return;
    }
    // 正文处理后 HTML 上限放宽到 512KB（默认 60KB 会让长文静默失去缓存）
    lt_cache_write($dir . '/' . $cid . '.php', $payload, 512 * 1024);
}

function lt_content_cache_delete(int $cid): void
{
    if ($cid <= 0 || ($dir = lt_content_cache_dir()) === '') {
        return;
    }
    lt_cache_delete($dir . '/' . $cid . '.php');
}

// ---- IP 限流（cache/rate_limit_{md5}.php，JSON 记录窗口时间戳） ----

function lt_rate_limit_gc(string $cacheDir): void
{
    try {
        foreach ((array) glob($cacheDir . '/rate_limit_*.php') as $file) {
            if (!is_file($file)) {
                continue;
            }
            // 文件名是 md5 无法识别业务，窗口期随记录写入文件（'w' 键）：
            // 一年期窗口（如点赞 like_once）按自身窗口保留，不得被常规 2 天 GC 清理，否则清除 Cookie 后同 IP 可再次点赞
            $window = 0;
            $data = @file_get_contents($file);
            if ($data !== false && strpos($data, '<?php exit; ?>') === 0) {
                $decoded = json_decode(substr($data, strlen('<?php exit; ?>')), true);
                if (is_array($decoded) && isset($decoded['w'])) {
                    $window = (int) $decoded['w'];
                }
            }
            if (time() - (int) @filemtime($file) > max(86400 * 2, $window)) {
                @unlink($file);
            }
        }
    } catch (\Throwable $e) {

    }
}

function lt_check_rate_limit(string $key, int $maxRequests, int $windowSeconds): bool
{
    if ($maxRequests <= 0 || $windowSeconds <= 0) {
        return true;
    }
    // 无效来源 IP（lt_get_client_ip 兜底 'unknown'）无法区分用户，追加 UA 指纹作隔离维度，
    // 避免不同无效 IP 访问者共享同一限频窗口互相影响（点赞/视图/一年一次窗口均受益）
    if (str_contains($key, 'unknown')) {
        $key .= '|ua:' . md5((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''));
    }
    // cache/ 为运行时目录，部署包含根 cache/.htaccess；lt_cache_dir() 在目录缺失或 htaccess 缺失时补写（Apache 2.2/2.4 双版本兼容）
    $cacheDir = lt_cache_dir();
    if ($cacheDir === '') {
        // 目录不可用（IO/权限异常）宽松放行——与原内联实现目录创建失败后的实际行为一致
        return true;
    }
    $cacheFile = $cacheDir . '/rate_limit_' . md5($key) . '.php';
    $now = time();
    $records = [];
    $allowed = true;


    if (mt_rand(1, 100) === 1) {
        lt_rate_limit_gc($cacheDir);
    }

    try {
        $fp = @fopen($cacheFile, 'c+');
        if ($fp === false) {

            if (is_file($cacheFile)) {
                $data = @file_get_contents($cacheFile);
                if ($data !== false && strpos($data, '<?php exit; ?>') === 0) {
                    $encoded = substr($data, strlen('<?php exit; ?>'));
                    // 限流文件均为本主题 json 写入；旧 unserialize 兼容已移除（防对象注入面）
                    $decoded = json_decode($encoded, true);
                    if (is_array($decoded)) {
                        // 新格式 {'w':窗口秒数,'r':[时间戳...]}；旧格式为纯时间戳数组
                        $records = (isset($decoded['r']) && is_array($decoded['r'])) ? $decoded['r'] : $decoded;
                    }
                }
            }
            $records = array_values(array_filter($records, static fn($ts) => ($now - $ts) < $windowSeconds));
            if (count($records) >= $maxRequests) {
                return false;
            }
            $records[] = $now;
            if (is_dir($cacheDir) && is_writable($cacheDir)) {
                // 降级路径（fopen c+ 失败）无文件句柄可 flock：写回改用临时文件 + rename 原子写防半文件；
                // 竞态方向宽松（极端情况下少计数一次），且 fopen 失败多因 IO/权限异常、写回成功概率低，非安全面
                $ltTmp = $cacheFile . '.tmp.' . bin2hex(random_bytes(4));
                if (@file_put_contents($ltTmp, '<?php exit; ?>' . json_encode(['w' => $windowSeconds, 'r' => $records]), LOCK_EX) !== false) {
                    @rename($ltTmp, $cacheFile);
                }
            }
            return true;
        }


        // 非阻塞锁拿不到时重试（避免并发击穿限流窗口），最多 3 次 × 50ms；
        // 若仍拿不到则降级放行（极端竞争场景宽松降级，避免无限阻塞）
        $lockRetries = 0;
        $maxLockRetries = 3;
        while ($lockRetries < $maxLockRetries && !@flock($fp, LOCK_EX | LOCK_NB)) {
            $lockRetries++;
            usleep(50000); // 50ms
        }
        if ($lockRetries >= $maxLockRetries && !@flock($fp, LOCK_EX | LOCK_NB)) {
            @fclose($fp);
            return true; // 极端竞争下的宽松降级放行
        }


        rewind($fp);
        $data = stream_get_contents($fp);
        if ($data !== false && strpos($data, '<?php exit; ?>') === 0) {
            $encoded = substr($data, strlen('<?php exit; ?>'));
            // 限流文件均为本主题 json 写入；旧 unserialize 兼容已移除（防对象注入面）
            $decoded = json_decode($encoded, true);
            if (is_array($decoded)) {
                // 新格式 {'w':窗口秒数,'r':[时间戳...]}；旧格式为纯时间戳数组
                $records = (isset($decoded['r']) && is_array($decoded['r'])) ? $decoded['r'] : $decoded;
            }
        }


        $records = array_values(array_filter($records, static fn($ts) => ($now - $ts) < $windowSeconds));


        if (count($records) >= $maxRequests) {
            $allowed = false;
        } else {
            $records[] = $now;

            ftruncate($fp, 0);
            rewind($fp);
            @fwrite($fp, '<?php exit; ?>' . json_encode(['w' => $windowSeconds, 'r' => $records]));
        }

        @flock($fp, LOCK_UN);
        @fclose($fp);
    } catch (\Throwable $e) {

        return true;
    }

    return $allowed;
}
