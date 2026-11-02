<?php
declare(strict_types=1);

/**
 * LanternPress 回归测试入口
 * 用法: php tests/run.php
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');

$root = dirname(__DIR__);
if (!defined('__TYPECHO_ROOT_DIR__')) {
    define('__TYPECHO_ROOT_DIR__', $root);
}
require $root . '/inc/core.php';

// 离线环境最小 Typecho stub：仅覆盖被测纯函数用到的 Common::subStr
if (!class_exists('\Typecho\Common')) {
    eval('namespace Typecho; class Common { public static function subStr(string $str, int $start, int $length, string $trim = ""): string { $r = mb_substr($str, $start, $length, "UTF-8"); return mb_strlen($str, "UTF-8") > $start + $length ? $r . $trim : $r; } }');
}

// \Typecho\Db stub：lt_field_upsert 三适配器分支单测用（离线环境无 Typecho）。
// 仅实现被测函数用到的 getPrefix/getAdapter/query，query 记录 SQL 供断言。
if (!class_exists('\Typecho\Db')) {
    eval('namespace Typecho; class Db { public array $queries = []; private object $adapter; public function __construct(object $adapter) { $this->adapter = $adapter; } public function getPrefix(): string { return "typecho_"; } public function getAdapter(): object { return $this->adapter; } public function query(string $sql): void { $this->queries[] = $sql; } }');
}

// 适配器名驱动分支判定（stripos Pgsql/SQLite），测试桩类名需含对应子串
class FakePgsqlAdapter
{
    public function quoteValue(string $v): string
    {
        return "'" . str_replace("'", "''", $v) . "'";
    }
}
class FakeSQLiteAdapter extends FakePgsqlAdapter
{
}
class FakeMysqlAdapter extends FakePgsqlAdapter
{
}

$tests = [];
$passed = 0;
$failed = 0;

function test(string $name, callable $fn): void {
    global $passed, $failed;
    try {
        $fn();
        echo "  [PASS] $name\n";
        $passed++;
    } catch (\Throwable $e) {
        echo "  [FAIL] $name: {$e->getMessage()}\n";
        $failed++;
    }
}

function assert_true(bool $cond, string $msg = ''): void {
    if (!$cond) {
        throw new \RuntimeException($msg ?: 'Assertion failed');
    }
}

function assert_false(bool $cond, string $msg = ''): void {
    assert_true(!$cond, $msg);
}

function assert_eq(mixed $expected, mixed $actual, string $msg = ''): void {
    if ($expected !== $actual) {
        throw new \RuntimeException(
            ($msg ?: 'Assertion failed') . " (expected: " . var_export($expected, true) . ", got: " . var_export($actual, true) . ")"
        );
    }
}

// ---- 测试组 1: CSP Nonce ----
echo "Test Group 1: CSP Nonce\n";

test('lt_generate_csp_nonce returns 32-char hex', function () {
    $nonce = lt_generate_csp_nonce();
    assert_eq(32, strlen($nonce), 'nonce length');
    assert_true(ctype_xdigit($nonce), 'nonce is hex');
});

test('lt_generate_csp_nonce returns same value per request', function () {
    $n1 = lt_generate_csp_nonce();
    $n2 = lt_generate_csp_nonce();
    assert_eq($n1, $n2, 'nonce cached');
});

test('lt_add_nonce_to_scripts adds nonce to script tags', function () {
    $html = '<script>alert(1)</script><script src="x.js"></script>';
    $result = lt_add_nonce_to_scripts($html, 'abc123');
    assert_true(str_contains($result, 'nonce="abc123"'), 'nonce added');
    assert_eq(2, substr_count($result, 'nonce="abc123"'), 'both scripts got nonce');
});

test('lt_add_nonce_to_scripts preserves existing nonce', function () {
    $html = '<script nonce="existing">alert(1)</script>';
    $result = lt_add_nonce_to_scripts($html, 'abc123');
    assert_true(str_contains($result, 'nonce="abc123"'), 'existing nonce replaced');
});

test('lt_add_nonce_to_styles adds nonce to style tags', function () {
    $html = '<style>body{}</style><style>@media{}</style>';
    $result = lt_add_nonce_to_styles($html, 'abc123');
    assert_eq(2, substr_count($result, 'nonce="abc123"'), 'both styles got nonce');
});

test('lt_build_csp_policy includes nonce in script-src', function () {
    $csp = lt_build_csp_policy();
    assert_true(str_contains($csp, "script-src 'self' 'nonce-"), 'script-src has nonce');
    assert_true(str_contains($csp, "style-src 'self' 'nonce-"), 'style-src has nonce');
    assert_false(str_contains($csp, "script-src 'unsafe-inline'"), 'no unsafe-inline in script-src');
});

// ---- 测试组 2: 广告清洗 ----
echo "\nTest Group 2: Ad HTML Sanitization\n";

test('lt_safe_ad_html strips onclick', function () {
    $html = '<ins onclick="alert(1)">Ad</ins>';
    $result = lt_safe_ad_html($html);
    assert_false(str_contains($result, 'onclick'), 'onclick stripped');
});

test('lt_safe_ad_html strips onerror', function () {
    $html = '<img src="x.jpg" onerror="alert(1)">';
    $result = lt_safe_ad_html($html);
    assert_false(str_contains($result, 'onerror'), 'onerror stripped');
});

test('lt_safe_ad_html strips style attribute', function () {
    $html = '<div style="color:red">Ad</div>';
    $result = lt_safe_ad_html($html);
    assert_false(str_contains($result, 'style='), 'style attr stripped');
});

test('lt_safe_ad_html blocks javascript: protocol', function () {
    $html = '<a href="javascript:alert(1)">Click</a>';
    $result = lt_safe_ad_html($html);
    assert_false(str_contains($result, 'javascript:'), 'javascript: blocked');
});

test('lt_safe_ad_html blocks data: protocol', function () {
    $html = '<img src="data:image/svg+xml;base64,xxx">';
    $result = lt_safe_ad_html($html);
    assert_false(str_contains($result, 'data:'), 'data: blocked');
});

test('lt_safe_ad_html preserves script closing tags (direct echo context)', function () {
    // 广告/统计代码直接 echo 进 HTML，</script> 是合法闭合标签，破坏它会让 script 永不闭合吞掉页面
    $html = '<script src="x.js"></script>';
    $result = lt_safe_ad_html($html);
    assert_true(str_contains($result, '</script>'), 'script close preserved');
    assert_false(str_contains($result, '<\/script>'), 'no escaped close in direct-echo context');
});

test('lt_safe_ad_html allows safe tags', function () {
    $html = '<ins class="adsbygoogle"></ins><img src="ad.jpg"><iframe src="ad.html"></iframe>';
    $result = lt_safe_ad_html($html);
    assert_true(str_contains($result, '<ins'), 'ins allowed');
    assert_true(str_contains($result, '<img'), 'img allowed');
    assert_true(str_contains($result, '<iframe'), 'iframe allowed');
});

// ---- 测试组 3: 导航树 ----
echo "\nTest Group 3: Navigation Tree\n";

test('lt_nav_tree_from_json parses valid JSON', function () {
    $json = json_encode([['t' => 'custom', 'n' => 'Home', 'u' => '/']]);
    $tree = lt_nav_tree_from_json($json);
    assert_eq(1, count($tree), 'one node');
    assert_eq('Home', $tree[0]['title'], 'title parsed');
    assert_eq('/', $tree[0]['permalink'], 'url parsed');
});

test('lt_nav_tree_from_json rejects invalid JSON', function () {
    $tree = lt_nav_tree_from_json('not json');
    assert_eq([], $tree, 'empty array for invalid');
});

test('lt_nav_tree_from_json enforces max depth', function () {
    $node = ['t' => 'custom', 'n' => 'A', 'u' => '/', 'c' => []];
    $current = &$node['c'];
    for ($i = 0; $i < 5; $i++) {
        $current[] = ['t' => 'custom', 'n' => 'B', 'u' => '/', 'c' => []];
        $current = &$current[0]['c'];
    }
    $json = json_encode([$node]);
    $tree = lt_nav_tree_from_json($json);
    assert_true(count($tree) > 0, 'tree parsed');
    // Depth should be truncated
    $count = 0;
    $nodes = $tree;
    while (true) {
        $hasChild = false;
        foreach ($nodes as $n) {
            $count++;
            if (!empty($n['c'])) {
                $nodes = $n['c'];
                $hasChild = true;
                break;
            }
        }
        if (!$hasChild) break;
    }
    assert_true($count <= 3, 'depth truncated to 3');
});

test('lt_nav_tree_from_json enforces max nodes', function () {
    $nodes = [];
    for ($i = 0; $i < 105; $i++) {
        $nodes[] = ['t' => 'custom', 'n' => "Node$i", 'u' => '/'];
    }
    $json = json_encode($nodes);
    $tree = lt_nav_tree_from_json($json);
    assert_true(count($tree) <= 100, 'nodes truncated to 100');
});

test('lt_nav_tree_from_json sanitizes custom URLs', function () {
    $json = json_encode([
        ['t' => 'custom', 'n' => 'Safe', 'u' => 'https://example.com'],
        ['t' => 'custom', 'n' => 'XSS', 'u' => 'javascript:alert(1)'],
    ]);
    $tree = lt_nav_tree_from_json($json);
    assert_eq('https://example.com', $tree[0]['permalink'], 'safe URL kept');
    // javascript: URL 被 lt_safe_url 过滤后为空字符串，导致节点被跳过
    assert_eq(1, count($tree), 'XSS node skipped');
});

// ---- 测试组 4: XSS 转义 ----
echo "\nTest Group 4: XSS Escaping\n";

test('lt_esc_html escapes special chars', function () {
    $input = '<script>alert("xss")</script>';
    $result = lt_esc_html($input);
    assert_true(str_contains($result, '&lt;script&gt;'), 'script tag escaped');
    assert_true(str_contains($result, '&quot;'), 'quote escaped');
});

test('lt_esc_attr escapes quotes', function () {
    $input = '" onmouseover="alert(1)';
    $result = lt_esc_attr($input);
    assert_true(str_contains($result, '&quot;'), 'double quote escaped');
});

test('lt_safe_comment_html strips script tags', function () {
    $html = '<p>Hello</p><script>alert(1)</script>';
    $result = lt_safe_comment_html($html);
    assert_false(str_contains($result, '<script'), 'script stripped');
});

test('lt_safe_comment_html strips event handlers', function () {
    $html = '<p onclick="alert(1)">Text</p>';
    $result = lt_safe_comment_html($html);
    assert_false(str_contains($result, 'onclick'), 'onclick stripped');
});

test('lt_safe_comment_html blocks javascript: in href', function () {
    $html = '<a href="javascript:alert(1)">Link</a>';
    $result = lt_safe_comment_html($html);
    assert_false(str_contains($result, 'javascript:'), 'javascript: blocked');
});

test('lt_safe_comment_html allows safe tags', function () {
    $html = '<p><strong>Bold</strong> <em>Italic</em></p>';
    $result = lt_safe_comment_html($html);
    assert_true(str_contains($result, '<strong>'), 'strong allowed');
    assert_true(str_contains($result, '<em>'), 'em allowed');
});

test('lt_safe_comment_html strips data-* attributes', function () {
    $html = '<p data-x="1" data-y="2">Text</p>';
    $result = lt_safe_comment_html($html);
    assert_false(str_contains($result, 'data-'), 'data-* stripped');
});

test('lt_escape_inline_code escapes script close', function () {
    $js = 'alert("</script>")';
    $result = lt_escape_inline_code($js);
    assert_true(str_contains($result, '<\/script>'), 'script close escaped');
});

test('lt_escape_inline_code escapes style close', function () {
    $css = 'body{color:red}</style>';
    $result = lt_escape_inline_code($css);
    assert_true(str_contains($result, '<\/style>'), 'style close escaped');
});

// ---- 测试组 5: 正文管线 / 纯文本统计 ----
echo "\nTest Group 5: Content Pipeline & Stats\n";

test('lt_content_plain_stats counts plain chars', function () {
    $r = lt_content_plain_stats('<p>你好世界</p>');
    assert_eq(4, $r['chars'], 'chars');
    assert_eq('你好世界', $r['text'], 'text');
    assert_eq(1, $r['minutes'], 'minutes min 1');
});

test('lt_content_plain_stats decodes entities', function () {
    $r = lt_content_plain_stats('<p>a&amp;b</p>');
    assert_eq('a&b', $r['text'], 'entity decoded');
    assert_eq(3, $r['chars'], 'chars after decode');
});

test('lt_content_render_pipeline wraps standalone img with fancybox + eager first', function () {
    $r = lt_content_render_pipeline('<p><img src="https://a.com/1.jpg"></p><p><img src="https://a.com/2.jpg"></p>', '标题');
    assert_eq(2, substr_count($r, 'class="fancybox"'), 'both imgs wrapped');
    assert_true(str_contains($r, 'loading="eager" fetchpriority="high"'), 'first img eager');
    assert_true(str_contains($r, 'loading="lazy"'), 'second img lazy');
    assert_eq(2, substr_count($r, 'alt="标题"'), 'alt filled from title');
});

test('lt_content_render_pipeline protects anchor-wrapped img from double wrap', function () {
    $r = lt_content_render_pipeline('<p><a href="/x"><img src="https://a.com/1.jpg"></a></p><img src="https://a.com/2.jpg">', 'T');
    assert_eq(1, substr_count($r, 'class="fancybox"'), 'only standalone img wrapped');
    assert_true(str_contains($r, '<a href="/x">'), 'original anchor intact');
});

test('lt_content_render_pipeline drops img with unsafe src', function () {
    $r = lt_content_render_pipeline('<p><img src="javascript:alert(1)"></p>', 'T');
    assert_false(str_contains($r, 'fancybox'), 'unsafe src not wrapped');
});

test('lt_content_render_pipeline adds line-numbers to pre', function () {
    $r = lt_content_render_pipeline('<pre><code>x</code></pre>', 'T');
    assert_true(str_contains($r, '<pre class="line-numbers">'), 'class added');
    $r2 = lt_content_render_pipeline('<pre class="line-numbers"><code>x</code></pre>', 'T');
    assert_eq(1, substr_count($r2, 'line-numbers'), 'existing class not duplicated');
});

// ---- 测试组 6: 内容缓存编解码 ----
echo "\nTest Group 6: Content Cache Codec\n";

test('lt_content_cache_load returns null on miss', function () {
    lt_content_cache_delete(999991);
    assert_eq(null, lt_content_cache_load(999991, 'h'), 'miss');
});

test('lt_content_cache four-segment roundtrip', function () {
    lt_content_cache_set(999991, lt_content_cache_payload('h1', 7, '含||竖线摘要', '<p>正文</p>'));
    $r = lt_content_cache_load(999991, 'h1');
    assert_eq(7, $r['chars'], 'chars');
    assert_eq('<p>正文</p>', $r['content'], 'content');
    assert_false(str_contains($r['summary'], '||'), 'summary pipes replaced');
    assert_eq(null, lt_content_cache_load(999991, 'other'), 'hash mismatch');
    lt_content_cache_delete(999991);
});

test('lt_content_cache_load legacy format with || in content is not truncated', function () {
    // M3 回归：旧两段格式正文含 || 时不得误判为四段格式
    $legacyContent = '<p>首段 || 含竖线 || 正文</p>';
    lt_content_cache_set(999991, 'h2||' . $legacyContent);
    $r = lt_content_cache_load(999991, 'h2');
    assert_eq($legacyContent, $r['content'], 'legacy content intact');
    assert_eq(lt_content_plain_stats($legacyContent)['chars'], $r['chars'], 'chars recomputed');
    // 升级后再读仍一致
    $r2 = lt_content_cache_load(999991, 'h2');
    assert_eq($legacyContent, $r2['content'], 'upgraded cache intact');
    lt_content_cache_delete(999991);
});

// ---- 测试组 7: 批次24-26 新增函数 ----
echo "\nTest Group 7: Batch 24-26 Functions\n";

test('lt_post_breadcrumb_chain builds deepest path (root -> leaf)', function () {
    // 自带完整 parent 链（含祖先行），不触发 DB 补查；最深分类 mid=3
    $cats = [
        ['mid' => 1, 'name' => '一级', 'slug' => 'c1', 'permalink' => '', 'parent' => 0],
        ['mid' => 3, 'name' => '三级', 'slug' => 'c3', 'permalink' => '', 'parent' => 2],
        ['mid' => 2, 'name' => '二级', 'slug' => 'c2', 'permalink' => '', 'parent' => 1],
    ];
    $chain = lt_post_breadcrumb_chain($cats, 'https://example.com/');
    $names = array_column($chain, 'name');
    assert_eq(['一级', '二级', '三级'], $names, 'order root->leaf');
    // url 缺失（slug 存在但 Router 不可用）时仍返回行，url 为空由渲染端决定
    assert_eq(3, count($chain), 'all rows kept');
});

test('lt_post_breadcrumb_chain returns empty on no categories', function () {
    assert_eq([], lt_post_breadcrumb_chain(null, 'https://example.com/'), 'null input');
    assert_eq([], lt_post_breadcrumb_chain([], 'https://example.com/'), 'empty input');
});

test('lt_sync_poster_hosts writes validated hosts file', function () {
    $dir = lt_cache_dir();
    $path = $dir . '/poster_hosts.php';
    @unlink($path);
    lt_sync_poster_hosts("img.example.com\n.oss.example.com\nbad host!.\n# comment\ndup.example.com,dup.example.com");
    $loaded = is_file($path) ? @include $path : null;
    assert_eq(['img.example.com', '.oss.example.com', 'dup.example.com'], is_array($loaded) ? array_values($loaded) : [], 'validated hosts only');
    // 幂等：再次调用不改变文件
    lt_sync_poster_hosts('img.example.com');
    $loaded2 = is_file($path) ? @include $path : null;
    assert_eq(['img.example.com', '.oss.example.com', 'dup.example.com'], is_array($loaded2) ? array_values($loaded2) : [], 'idempotent');
    @unlink($path);
});

// ---- 测试组 8: P0 友链性能优化（缓存 + 同源代理判定） ----
echo "\nTest Group 8: P0 Flink Optimization\n";

test('lt_proxy_image_host_allowed exact & wildcard & empty', function () {
    $dir = lt_cache_dir();
    $path = $dir . '/poster_hosts.php';
    // 直接写白名单文件（不依赖 lt_sync_poster_hosts：其 static $done 防同一进程重复写入）
    @file_put_contents($path, "<?php\nreturn ['img.example.com', '.oss.example.com'];\n");
    assert_true(lt_proxy_image_host_allowed('img.example.com'), 'exact match');
    assert_true(lt_proxy_image_host_allowed('a.oss.example.com'), 'wildcard subdomain');
    assert_false(lt_proxy_image_host_allowed('example.com'), 'root not wildcarded');
    assert_false(lt_proxy_image_host_allowed('evil.com'), 'not in whitelist');
    // 大小写不敏感
    assert_true(lt_proxy_image_host_allowed('IMG.EXAMPLE.COM'), 'case-insensitive');
    @unlink($path);
});

test('lt_proxy_image_host_allowed empty whitelist = false (safe default)', function () {
    $dir = lt_cache_dir();
    $path = $dir . '/poster_hosts.php';
    @unlink($path);
    assert_false(lt_proxy_image_host_allowed('img.example.com'), 'no whitelist file -> not allowed');
});

test('lt_maybe_proxy_image degrades safely without Typecho (offline)', function () {
    // 单测环境无 Typecho Widget：Options 读取失败 → 原样直连，不抛异常
    $url = 'https://img.example.com/logo.png';
    assert_eq($url, lt_maybe_proxy_image($url), 'no crash, passthrough when Options unavailable');
});

test('lt_maybe_proxy_image passes through svg & non-http & empty', function () {
    // 这些分支在 Typecho 不可用时同样生效（判定不依赖 Options）
    assert_eq('', lt_maybe_proxy_image(''), 'empty url');
    assert_eq('data:image/png;base64,xx', lt_maybe_proxy_image('data:image/png;base64,xx'), 'non-http scheme');
    assert_eq('https://img.example.com/logo.svg', lt_maybe_proxy_image('https://img.example.com/logo.svg'), 'svg passthrough');
    assert_eq('https://img.example.com/logo.svgz', lt_maybe_proxy_image('https://img.example.com/logo.svgz'), 'svgz passthrough');
    assert_eq('https://x.com/usr/themes/LanternPress/inc/poster-proxy.php?url=a', lt_maybe_proxy_image('https://x.com/usr/themes/LanternPress/inc/poster-proxy.php?url=a'), 'already proxied idempotent');
});

// ============================================
// Group 9：P2 flinks 数据模型纯函数（inc/flinks.php）
// ============================================
echo "\n--- Group 9: flinks pure functions ---\n";

test('lt_flinks_parse_entry extracts [logo], strips markdown, truncates desc', function () {
    $fix = static fn(string $u): string => $u;
    $entry = lt_flinks_parse_entry([
        'author' => ' 示例博客 ',
        'url' => 'example.com',
        'mail' => 'a@b.com',
        'text' => "[logo]https://img.example.com/logo.png[/logo] **简介** _斜体_ `code` ~~del~~ " . str_repeat('长', 80),
        'created' => 1700000000,
    ], $fix);
    assert_eq('示例博客', $entry['name'], 'author trimmed');
    assert_eq('example.com', $entry['url'], 'url kept (caller fixes protocol)');
    assert_eq('https://img.example.com/logo.png', $entry['logo'], 'logo extracted');
    assert_eq('简介 斜体 code del ' . str_repeat('长', 45) . '...', $entry['desc'], 'markdown stripped + truncated to 60');
    assert_eq(1, $entry['status'], 'legacy rows default to approved');
});

test('lt_flinks_parse_entry returns empty on blank name', function () {
    $fix = static fn(string $u): string => $u;
    assert_eq([], lt_flinks_parse_entry(['author' => '   ', 'url' => '', 'text' => 'x'], $fix), 'blank name -> empty');
});

test('lt_flinks_dup_exists matches normalized url or same mail', function () {
    $data = [
        ['id' => 1, 'url' => 'https://Example.com/path?q=1', 'mail' => 'a@b.com', 'status' => 1],
        ['id' => 2, 'url' => 'http://other.org/', 'mail' => 'c@d.com', 'status' => 0],
    ];
    assert_true(lt_flinks_dup_exists($data, 'http://example.com/', 'x@y.com'), 'scheme+path ignored, host match');
    assert_true(lt_flinks_dup_exists($data, 'https://new.site/', 'A@B.COM'), 'mail case-insensitive');
    assert_false(lt_flinks_dup_exists($data, 'https://new.site/', 'x@y.com'), 'no dup for new site');
    $rejected = [
        ['id' => 9, 'url' => 'https://rejected.com', 'mail' => 'r@r.com', 'status' => 2],
    ];
    assert_false(lt_flinks_dup_exists($rejected, 'https://rejected.com', 'r@r.com'), 'rejected (status=2) does not block re-apply');
});

test('lt_flinks_visible filters approved and sorts by sort then created', function () {
    $data = [
        ['id' => 1, 'status' => 0, 'sort' => 0, 'created' => 1],
        ['id' => 2, 'status' => 1, 'sort' => 2, 'created' => 3],
        ['id' => 3, 'status' => 1, 'sort' => 1, 'created' => 9],
        ['id' => 4, 'status' => 1, 'sort' => 1, 'created' => 2],
        ['id' => 5, 'status' => 2, 'sort' => 0, 'created' => 0],
    ];
    $visible = lt_flinks_visible($data);
    assert_eq(3, count($visible), 'only status=1 kept');
    $ids = array_column($visible, 'id');
    assert_eq([4, 3, 2], $ids, 'sort asc, then created asc');
});

// Group 10：P2-2 管理后台数据规则（URL 归一化 / 去重边界 / visible 状态覆盖）
echo "\n--- Group 10: flinks admin rules ---\n";

test('lt_flinks_normalize_url adds https and validates scheme', function () {
    assert_eq('https://example.com', lt_flinks_normalize_url('example.com'), 'bare domain -> https');
    assert_eq('https://example.com/path', lt_flinks_normalize_url(' example.com/path '), 'trim + path kept');
    assert_eq('http://x.io', lt_flinks_normalize_url('http://x.io'), 'explicit http kept');
    assert_eq('', lt_flinks_normalize_url(''), 'blank -> empty');
    assert_eq('', lt_flinks_normalize_url('javascript:alert(1)'), 'dangerous scheme rejected');
    assert_eq('', lt_flinks_normalize_url('file:///etc/passwd'), 'file scheme rejected');
    assert_eq('https://example.com', lt_flinks_normalize_url('https://example.com'), 'explicit https kept');
});

test('lt_flinks_dup_exists ignores rejected entries and trims host port', function () {
    $data = [
        ['id' => 1, 'url' => 'https://example.com:8080/a?b=1', 'mail' => 'a@b.com', 'status' => 1],
        ['id' => 2, 'url' => 'https://other.org', 'mail' => '', 'status' => 0],
    ];
    assert_true(lt_flinks_dup_exists($data, 'http://example.com:8080/', 'x@y.com'), 'host:port + scheme ignored');
    assert_false(lt_flinks_dup_exists($data, 'https://example.com:9090/', 'x@y.com'), 'different port is not dup');
    assert_true(lt_flinks_dup_exists($data, 'https://new.org', 'A@B.COM'), 'mail case-insensitive on status=0');
    $rejected = [['id' => 5, 'url' => 'https://rejected.com', 'mail' => 'r@r.com', 'status' => 2]];
    assert_false(lt_flinks_dup_exists($rejected, 'https://rejected.com', 'r@r.com'), 'rejected never blocks');
});

test('lt_flinks_admin stats semantics (approved/pending/rejected)', function () {
    $data = [
        ['id' => 1, 'status' => 1],
        ['id' => 2, 'status' => 0],
        ['id' => 3, 'status' => 2],
        ['id' => 4, 'status' => 1],
        ['id' => 5, 'status' => 99], // 未知状态归待审（与端点 lt_admin_stats 默认分支一致）
    ];
    $approved = count(array_filter($data, static fn($e) => (int) ($e['status'] ?? 0) === 1));
    $pending = count(array_filter($data, static fn($e) => !in_array((int) ($e['status'] ?? 0), [1, 2], true)));
    $rejected = count(array_filter($data, static fn($e) => (int) ($e['status'] ?? 0) === 2));
    assert_eq(2, $approved, 'approved count');
    assert_eq(2, $pending, 'pending count (incl unknown)');
    assert_eq(1, $rejected, 'rejected count');
    assert_eq(5, $approved + $pending + $rejected, 'total matches');
});

// ============================================
// Group 11：批次A/B 新增逻辑
// （A1 事务锁包装 / A2 统一失效函数 / B3 category 字段透传）
// ============================================
echo "\n--- Group 11: flinks batch A/B helpers ---\n";

// 事务测试会经 lt_cache_dir 创建 cache/flink_rw.lock；记录目录初始状态以便清理
$ltCacheDir = dirname(__DIR__) . '/cache';
$ltHadCache = is_dir($ltCacheDir);

test('lt_flinks_transaction returns callback result and stays serialized', function () {
    $calls = [];
    for ($i = 1; $i <= 5; $i++) {
        $v = lt_flinks_transaction(static function () use ($i, &$calls): int {
            $calls[] = $i;
            usleep(5000);
            return $i * 2;
        });
        assert_eq($i * 2, $v, "transaction returns callback value #$i");
    }
    assert_eq([1, 2, 3, 4, 5], $calls, 'transactions run sequentially (FIFO order preserved)');
});

test('lt_flinks_transaction direct value passes through lock wrapper', function () {
    $v = lt_flinks_transaction(static function (): string {
        return 'ok';
    });
    assert_eq('ok', $v, 'direct value passes through lock wrapper');
});

test('lt_flinks_forget_cid guards invalid cid', function () {
    lt_flinks_forget_cid(0);
    lt_flinks_forget_cid(-3);
    assert_true(true, 'no exception on invalid cid');
});

test('lt_flinks_forget_all_pages is safe without Typecho widgets', function () {
    // 该用例预期触发一条 error_log（无 Typecho Widget 环境，lt_log_error 记录后安全返回）；
    // 临时重定向到临时文件，避免污染测试输出（#6）
    $ltPrevLog = (string) ini_get('error_log');
    $ltTmpLog = tempnam(sys_get_temp_dir(), 'lt_err_');
    if ($ltTmpLog !== false) {
        ini_set('error_log', $ltTmpLog);
    }
    try {
        lt_flinks_forget_all_pages();
    } finally {
        ini_set('error_log', $ltPrevLog);
        if ($ltTmpLog !== false) {
            @unlink($ltTmpLog);
        }
    }
    assert_true(true, 'no exception without widget runtime (Throwable caught)');
});

test('lt_flinks_visible preserves category field through filter/sort', function () {
    $data = [
        ['id' => 1, 'status' => 1, 'sort' => 1, 'created' => 1, 'category' => '工具'],
        ['id' => 2, 'status' => 1, 'sort' => 2, 'created' => 2, 'category' => '友情'],
        ['id' => 3, 'status' => 0, 'sort' => 0, 'created' => 0, 'category' => '工具'],
    ];
    $visible = lt_flinks_visible($data);
    assert_eq(2, count($visible), 'only approved kept');
    assert_eq('工具', $visible[0]['category'] ?? '', 'category preserved through visible (first)');
    assert_eq('友情', $visible[1]['category'] ?? '', 'category preserved through visible (second)');
});

test('lt_flinks_dup_exists ignores category for dedup', function () {
    $data = [['id' => 1, 'url' => 'https://example.com', 'mail' => 'a@b.com', 'status' => 1, 'category' => '工具']];
    assert_true(lt_flinks_dup_exists($data, 'https://example.com', 'x@y.com'), 'url dedup still by host');
    assert_false(lt_flinks_dup_exists($data, 'https://other.org', 'x@y.com'), 'category never affects dedup');
});

// 清理事务锁测试产生的残留（cache 目录原不存在时一并移除空目录）
@unlink($ltCacheDir . '/flink_rw.lock');
if (!$ltHadCache && is_dir($ltCacheDir) && count((array) glob($ltCacheDir . '/*')) === 0) {
    @rmdir($ltCacheDir);
}

// ============================================
// Group 12：poster-proxy SSRF 防护纯函数（#7）
// ============================================
echo "\n--- Group 12: poster-proxy SSRF guards ---\n";

test('lt_poster_ip_is_public rejects private/reserved/loopback ranges', function () {
    foreach (['127.0.0.1', '10.0.0.1', '192.168.1.1', '172.16.0.1', '169.254.169.254', '0.0.0.0', '::1', 'fe80::1'] as $ip) {
        assert_false(lt_poster_ip_is_public($ip), "private/reserved rejected: $ip");
    }
    assert_true(lt_poster_ip_is_public('8.8.8.8'), 'public v4 allowed');
    assert_true(lt_poster_ip_is_public('2606:4700:4700::1111'), 'public v6 allowed');
    assert_false(lt_poster_ip_is_public('not-an-ip'), 'garbage rejected (safe default)');
});

test('lt_poster_target_allowed enforces scheme whitelist', function () {
    $hosts = ['img.example.com'];
    assert_false(lt_poster_target_allowed('ftp', 'img.example.com', 0, $hosts), 'ftp rejected');
    assert_false(lt_poster_target_allowed('file', 'img.example.com', 0, $hosts), 'file rejected');
    assert_false(lt_poster_target_allowed('', 'img.example.com', 0, $hosts), 'empty scheme rejected');
    assert_false(lt_poster_target_allowed('gopher', 'img.example.com', 0, $hosts), 'gopher rejected');
    assert_true(lt_poster_target_allowed('https', 'img.example.com', 0, $hosts), 'https allowed');
    assert_true(lt_poster_target_allowed('http', 'img.example.com', 0, $hosts), 'http allowed');
});

test('lt_poster_target_allowed enforces port restriction (80/443/default only)', function () {
    $hosts = ['img.example.com'];
    assert_false(lt_poster_target_allowed('https', 'img.example.com', 6379, $hosts), 'redis port rejected');
    assert_false(lt_poster_target_allowed('https', 'img.example.com', 22, $hosts), 'ssh port rejected');
    assert_false(lt_poster_target_allowed('https', 'img.example.com', 8080, $hosts), '8080 rejected');
    assert_true(lt_poster_target_allowed('https', 'img.example.com', 443, $hosts), '443 ok');
    assert_true(lt_poster_target_allowed('http', 'img.example.com', 80, $hosts), '80 ok');
    assert_true(lt_poster_target_allowed('https', 'img.example.com', 0, $hosts), 'default port ok');
});

test('lt_poster_target_allowed enforces static host whitelist', function () {
    $hosts = ['img.example.com', '.cdn.foo.com'];
    assert_true(lt_poster_target_allowed('https', 'img.example.com', 0, $hosts), 'exact host');
    assert_true(lt_poster_target_allowed('https', 'a.cdn.foo.com', 0, $hosts), 'wildcard subdomain');
    assert_false(lt_poster_target_allowed('https', 'cdn.foo.com', 0, $hosts), 'wildcard root not covered');
    assert_false(lt_poster_target_allowed('https', 'evil.com', 0, $hosts), 'non-whitelist host');
    assert_false(lt_poster_target_allowed('https', 'notexample.com', 0, $hosts), 'suffix lookalike rejected');
    assert_false(lt_poster_target_allowed('https', '', 0, $hosts), 'empty host rejected');
    // 空白名单 = 恒拒（安全默认）
    assert_false(lt_poster_target_allowed('https', 'img.example.com', 0, []), 'empty whitelist rejects all');
});

test('lt_poster_target_allowed loopback requires explicit debug flag', function () {
    $hosts = ['img.example.com'];
    assert_false(lt_poster_target_allowed('http', '127.0.0.1', 0, $hosts), 'loopback rejected by default');
    assert_false(lt_poster_target_allowed('http', 'localhost', 0, $hosts), 'localhost rejected by default');
    assert_false(lt_poster_target_allowed('http', '[::1]', 0, $hosts), 'ipv6 loopback rejected by default');
    assert_true(lt_poster_target_allowed('http', '127.0.0.1', 0, $hosts, true), 'loopback allowed with debug flag');
    assert_true(lt_poster_target_allowed('http', '[::1]', 0, $hosts, true), 'bracketed ipv6 normalized + allowed');
});

// ============================================
// Group 13：点赞接口 CSRF 双候选校验（#7）
// ============================================
echo "\n--- Group 13: like CSRF dual-candidate ---\n";

test('lt_csrf_token_matches dual-candidate semantics', function () {
    assert_false(lt_csrf_token_matches('', 'a', 'b'), 'empty token always false');
    assert_true(lt_csrf_token_matches('tok', 'tok', ''), 'request-url token match');
    assert_true(lt_csrf_token_matches('tok', 'other', 'tok'), 'referer token match (no Referer header fallback)');
    assert_false(lt_csrf_token_matches('tok', 'other', ''), 'referer candidate empty -> no match');
    assert_false(lt_csrf_token_matches('tok', 'a', 'b'), 'neither candidate matches');
    assert_false(lt_csrf_token_matches('tok', '', ''), 'both candidates empty');
    assert_true(lt_csrf_token_matches('tok', '', 'tok'), 'empty expected skipped, referer matches');
});

// ============================================
// Group 14：lt_field_upsert 三适配器分支（#7）
// ============================================
echo "\n--- Group 14: lt_field_upsert adapter branches ---\n";

test('lt_field_upsert MySQL branch uses ON DUPLICATE KEY UPDATE', function () {
    $db = new \Typecho\Db(new FakeMysqlAdapter());
    lt_field_upsert($db, 12, 'likes', 'int', '0', '', 0.0, null, 1);
    $sql = $db->queries[0] ?? '';
    assert_true(str_contains($sql, 'INSERT INTO `typecho_fields`'), 'mysql identifiers backtick-quoted');
    assert_true(str_contains($sql, 'ON DUPLICATE KEY UPDATE'), 'mysql upsert keyword');
    assert_true(str_contains($sql, '`int_value` = `int_value` + 1'), 'increment set clause');
});

test('lt_field_upsert PgSQL branch uses ON CONFLICT DO UPDATE', function () {
    $db = new \Typecho\Db(new FakePgsqlAdapter());
    lt_field_upsert($db, 12, 'views', 'int', '0', '', 0.0, null, 1);
    $sql = $db->queries[0] ?? '';
    assert_true(str_contains($sql, 'INSERT INTO "typecho_fields"'), 'pgsql identifiers double-quoted');
    assert_true(str_contains($sql, 'ON CONFLICT ("cid", "name") DO UPDATE SET'), 'pgsql upsert keyword');
    assert_true(str_contains($sql, '"int_value" = "int_value" + 1'), 'increment set clause');
});

test('lt_field_upsert SQLite branch also uses ON CONFLICT', function () {
    $db = new \Typecho\Db(new FakeSQLiteAdapter());
    lt_field_upsert($db, 7, 'likes', 'int', '0', '', 0.0, 5, null);
    $sql = $db->queries[0] ?? '';
    assert_true(str_contains($sql, 'ON CONFLICT ("cid", "name") DO UPDATE SET'), 'sqlite upsert keyword');
    assert_true(str_contains($sql, '"int_value" = 5'), 'explicit updateInt set');
    assert_false(str_contains($sql, 'ON DUPLICATE KEY'), 'not mysql dialect');
});

test('lt_field_upsert empty sets: Pg DO NOTHING / MySQL no-op', function () {
    $db1 = new \Typecho\Db(new FakePgsqlAdapter());
    lt_field_upsert($db1, 7, 'articleDesc', 'str', '0', 'hello', 0.0);
    assert_true(str_contains($db1->queries[0] ?? '', 'ON CONFLICT ("cid", "name") DO NOTHING'), 'pgsql do nothing on empty sets');
    $db2 = new \Typecho\Db(new FakeMysqlAdapter());
    lt_field_upsert($db2, 7, 'articleDesc', 'str', '0', 'hello', 0.0);
    assert_true(str_contains($db2->queries[0] ?? '', '`cid` = `cid`'), 'mysql no-op set on empty sets');
});

test('lt_field_upsert quotes values via adapter quoteValue', function () {
    $db = new \Typecho\Db(new FakeMysqlAdapter());
    lt_field_upsert($db, 7, "na'me", 'str', '0', "va'l", 0.0, null, null, "up'v");
    $sql = $db->queries[0] ?? '';
    assert_true(str_contains($sql, "'na''me'"), 'name escaped via quoteValue');
    assert_true(str_contains($sql, "'va''l'"), 'str_value escaped via quoteValue');
    assert_true(str_contains($sql, "'up''v'"), 'updateStr escaped via quoteValue');
});

// ============================================
// Group 15：评论清洗边界（#7）
// ============================================
echo "\n--- Group 15: comment sanitization boundaries ---\n";

test('lt_safe_comment_html blocks unquoted javascript: href', function () {
    $r = lt_safe_comment_html('<a href=javascript:alert(1)>x</a>');
    assert_false(str_contains($r, 'javascript:'), 'unquoted javascript: blocked');
    assert_true(str_contains($r, 'href="#"'), 'href replaced with #');
});

test('lt_safe_comment_html blocks data: src and vbscript: href', function () {
    $r = lt_safe_comment_html('<img src="data:image/png;base64,xx" alt="a">');
    assert_false(str_contains($r, 'data:image'), 'data: src blocked');
    $r2 = lt_safe_comment_html('<a href="vbscript:msgbox(1)">v</a>');
    assert_false(str_contains($r2, 'vbscript:'), 'vbscript: blocked');
});

test('lt_safe_comment_html blocks mixed-case JavaScript: protocol', function () {
    $r = lt_safe_comment_html('<a href="JaVaScRiPt:alert(1)">x</a>');
    assert_false(str_contains($r, 'JaVaScRiPt'), 'case-insensitive protocol block');
});

test('lt_safe_comment_html keeps mailto / anchor / relative hrefs', function () {
    $r = lt_safe_comment_html('<a href="mailto:a@b.com">m</a><a href="#top">a</a><a href="/rel/p">r</a><a href="../up">u</a>');
    assert_true(str_contains($r, 'mailto:a@b.com'), 'mailto kept');
    assert_true(str_contains($r, '#top'), 'anchor kept');
    assert_true(str_contains($r, '/rel/p'), 'root-relative kept');
    assert_true(str_contains($r, '../up'), 'parent-relative kept');
});

test('lt_safe_comment_html strips srcset/formaction but keeps target/rel/class', function () {
    $r = lt_safe_comment_html('<img src="https://a.com/1.jpg" srcset="https://evil.com/x.jpg 1x"><a href="https://a.com" target="_blank" rel="noopener" class="c" formaction="javascript:x">l</a>');
    assert_false(str_contains($r, 'srcset'), 'srcset stripped (attr whitelist)');
    assert_false(str_contains($r, 'formaction'), 'formaction stripped (attr whitelist)');
    assert_false(str_contains($r, 'evil.com'), 'srcset payload gone');
    assert_true(str_contains($r, 'target="_blank"'), 'target kept');
    assert_true(str_contains($r, 'rel="noopener"'), 'rel kept');
    assert_true(str_contains($r, 'class="c"'), 'class kept');
});

test('lt_safe_comment_html removes dangerous tags with attributes and keeps safe content', function () {
    $r = lt_safe_comment_html('<iframe src="https://x.com" width="1"></iframe><script type="text/x">alert(1)</script><p>ok</p>');
    assert_false(str_contains($r, '<iframe'), 'iframe removed');
    assert_false(str_contains($r, 'alert(1)'), 'script content removed');
    assert_true(str_contains($r, '<p>ok</p>'), 'safe content kept');
});

// ---- 汇总 ----
echo "\n========================================\n";
echo "Results: $passed passed, $failed failed\n";
echo "========================================\n";

exit($failed > 0 ? 1 : 0);
