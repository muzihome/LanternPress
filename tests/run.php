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

// ---- 汇总 ----
echo "\n========================================\n";
echo "Results: $passed passed, $failed failed\n";
echo "========================================\n";

exit($failed > 0 ? 1 : 0);
