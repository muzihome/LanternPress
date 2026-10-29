<?php
declare(strict_types=1);

if (!defined('__TYPECHO_ROOT_DIR__')) exit;

// ============================================================
// format.php — 文本/转义/净化/格式化工具函数（纯函数，无模块依赖，最先加载）
// ============================================================

define('LT_MIN_VIEWS_DISPLAY', 1);
define('LT_MAX_SOCIAL_LINKS', 10);
define('LT_MAX_REWARD_IMAGES', 2);
define('LT_DEFAULT_EXCERPT_LENGTH', 130);
define('LT_MAX_URL_LENGTH', 2048);

function lt_text(mixed $value, string $default = ''): string
{
    if ($value === null) {
        return $default;
    }

    if (is_scalar($value)) {
        return (string) $value;
    }

    if (is_object($value) && method_exists($value, '__toString')) {
        return (string) $value;
    }

    return $default;
}

function lt_bool(mixed $value): bool
{
    if (is_bool($value)) {
        return $value;
    }

    $text = strtolower(trim(lt_text($value)));
    return in_array($text, ['1', 'true', 'on', 'yes'], true);
}

function lt_lines(mixed $value): array
{
    $text = trim(lt_text($value));
    if ($text === '') {
        return [];
    }

    $lines = preg_split('/\R/u', $text);
    if ($lines === false) {
        return [];
    }

    $result = [];
    foreach ($lines as $line) {
        $line = trim((string) $line);
        if ($line !== '') {
            $result[] = $line;
        }
    }

    return $result;
}

function lt_comment_require_url(object $options): bool
{
    if (isset($options->commentsRequireUrl)) {
        return lt_bool($options->commentsRequireUrl);
    }

    if (isset($options->commentsRequireURL)) {
        return lt_bool($options->commentsRequireURL);
    }

    return false;
}

function lt_comment_require_mail(object $options): bool
{
    if (isset($options->commentsRequireMail)) {
        return lt_bool($options->commentsRequireMail);
    }

    if (isset($options->commentsRequireMailbox)) {
        return lt_bool($options->commentsRequireMailbox);
    }

    return false;
}

function lt_safe_url(string $url): string
{
    $url = trim($url);
    if ($url === '') {
        return '';
    }

    if (strlen($url) > LT_MAX_URL_LENGTH) {
        return '';
    }

    // 拦截协议相对 URL（//evil.com）——当前页面是 https 时会跳转到 https://evil.com，属于开放重定向
    if (str_starts_with($url, '//')) {
        return '';
    }

    $parts = parse_url($url);
    if ($parts === false) {
        return '';
    }

    $scheme = strtolower((string) ($parts['scheme'] ?? ''));
    if ($scheme !== '' && !in_array($scheme, ['http', 'https', 'mailto'], true)) {
        return '';
    }


    $stripped = preg_replace('/[\x00-\x1F\x7F]/u', '', $url);

    return $stripped === null ? '' : $stripped;
}

/**
 * 内联 <script>/<style> 内容转义：防止管理员自定义 CSS/JS 中的闭合标签突破父级元素。
 * HTML5 解析器在 script/style 数据态下仅识别 </script>/</style>（大小写不敏感）为闭合边界，
 * 故只需转义这两种闭合序列；<!-- 与 <script 在 script/style 上下文中不构成突破向量。
 * 转义产物 <\/script> 在 JS 中为合法转义（等价于 </script> 字符串），在 CSS 中亦无害。
 */
function lt_escape_inline_code(string $content): string
{
    if ($content === '') {
        return '';
    }
    return (string) preg_replace('/<\/(script|style)>/i', '<\\/$1>', $content);
}

function lt_filter_nav_menu(string $value): string
{
    $value = str_replace(["\r\n", "\r"], "\n", trim($value));
    if ($value === '') {
        return '';
    }
    $kept = [];
    foreach (preg_split('/\n+/', $value) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || !str_contains($line, '|')) {
            continue;
        }
        $parts = array_map('trim', explode('|', $line, 2));
        if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
            continue;
        }
        if (lt_safe_url($parts[1]) === '') {
            continue;
        }
        $kept[] = $parts[0] . '|' . $parts[1];
    }
    return $kept === [] ? '' : implode("\n", $kept);
}

function lt_filter_cid_recommend(string $value): string
{
    if (preg_match_all('/\d+/', $value, $m) === 0) {
        return '';
    }
    $cids = [];
    foreach ($m[0] as $num) {
        $cid = (int) ltrim($num, '0') ?: 0;
        if ($cid > 0 && !in_array($cid, $cids, true)) {
            $cids[] = $cid;
        }
    }
    return implode('||', $cids);
}

function lt_esc_html(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8', true);
}

/**
 * 评论内容安全渲染：白名单保留常用 HTML，清洗事件属性 / style / 危险协议。
 * 替代全量转义（lt_esc_html），使 Typecho 允许的安全标签正常渲染且不引入 XSS。
 */
function lt_safe_comment_html(string $text): string
{
    // 整块移除危险标签及其内容（script/style/iframe/object/embed/form/noscript/template）
    $text = (string) preg_replace('#<(script|style|iframe|object|embed|form|noscript|template)\b[^>]*>.*?</\1\s*>#is', '', $text);
    // 白名单保留常用 HTML 标签（Typecho 允许的安全标签范围）
    $text = strip_tags($text, '<p><br><a><strong><em><u><s><code><pre><blockquote><ul><ol><li><img><span><h1><h2><h3><h4><h5><h6>');
    // 清洗事件属性 on*（onclick / onerror / onload 等）与 style
    $text = (string) preg_replace('/\son[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $text);
    $text = (string) preg_replace('/\sstyle\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $text);
    // 属性白名单（纵深防御）：仅保留安全属性，移除 data-*/form*/xlink:*/action*/srcset 等
    //   可加载外部资源的非 src 属性（如 srcset）一律剥离，避免绕过协议白名单；
    //   data-* 可能被第三方脚本读取利用，form* 系列属表单覆盖属性，均不在白名单内。
    $ltAllowedAttrs = ['href', 'src', 'alt', 'title', 'target', 'rel', 'width', 'height', 'class', 'loading'];
    $text = (string) preg_replace_callback(
        '/<(\w+)\b([^>]*)>/',
        static function (array $m) use ($ltAllowedAttrs): string {
            $attrs = (string) preg_replace_callback(
                '/\s([a-zA-Z_:][-a-zA-Z0-9_:.]*)(\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s>]+))?/',
                static function (array $am) use ($ltAllowedAttrs): string {
                    return in_array(strtolower($am[1]), $ltAllowedAttrs, true) ? $am[0] : '';
                },
                $m[2]
            );
            // 清理属性移除后遗留的多余空白，保持标签整洁
            $attrs = (string) preg_replace('/\s{2,}/', ' ', $attrs);
            return '<' . $m[1] . rtrim($attrs) . '>';
        },
        $text
    );
    // a[href] / img[src] 仅允许 http(s) / mailto / # 锚点 / 相对路径，其余危险协议替换为 "#"
    // 同时覆盖带引号 (href="url") 和无引号 (href=url) 两种属性值形式
    $ltSafeProto = static function (string $url): bool {
        return preg_match('~^(?:https?://|mailto:|#|/|\.{1,2}/)[^"\']*$~i', $url) === 1;
    };
    // 带引号形式：href="..." 或 href='...'
    $text = (string) preg_replace_callback(
        '/(<(?:a|img)\b[^>]*?(?:href|src)\s*=\s*)(["\'])(.*?)\2/i',
        static function (array $m) use ($ltSafeProto): string {
            $url = trim((string) ($m[3] ?? ''));
            if ($url !== '' && !$ltSafeProto($url)) {
                return $m[1] . '"#"';
            }
            return $m[0];
        },
        $text
    );
    // 无引号形式：href=valuevalue 不含引号且以 \s 或 > 结尾
    $text = (string) preg_replace_callback(
        '/(<(?:a|img)\b[^>]*?(?:href|src)\s*=\s*)([^\s"\'>]+)/i',
        static function (array $m) use ($ltSafeProto): string {
            $url = trim((string) ($m[2] ?? ''));
            if ($url !== '' && !$ltSafeProto($url)) {
                return $m[1] . '"#"';
            }
            return $m[0];
        },
        $text
    );
    return $text;
}

/**
 * 后台广告位 HTML 清洗（批次25）：允许广告常用标签（<ins class="adsbygoogle"> / <script src> / <img> / <iframe> 等），
 * 但剥离事件属性 on*、危险协议（javascript:/data:）、style 属性。
 * 与 lt_safe_comment_html（评论场景、白名单更窄）分离，避免广告标签被 strip_tags 误杀。
 */
function lt_safe_ad_html(string $html): string
{
    if ($html === '') {
        return '';
    }
    // 剥离事件属性 on*（onclick / onerror / onload 等）
    $html = (string) preg_replace('/\son[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html);
    // 剥离 style 属性（CSP 默认 unsafe-inline 下，style 也可能被滥用）
    $html = (string) preg_replace('/\sstyle\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html);
    // 剥离危险协议 javascript:/data:/vbscript:（href/src 里）
    $html = (string) preg_replace_callback(
        '/\s(?:href|src|action)\s*=\s*(["\']?)([^"\'>\s]+)\1/i',
        static function (array $m): string {
            $url = trim((string) ($m[2] ?? ''));
            if ($url === '') {
                return $m[0];
            }
            if (preg_match('~^(?:javascript|data|vbscript):~i', $url) === 1) {
                return str_replace($m[2], '#', $m[0]);
            }
            return $m[0];
        },
        $html
    );
    // 注意：不得转义 </script>/</style> 闭合标签——广告/统计代码是直接 echo 进 HTML 的
    // （并非嵌入 script 元素内部），破坏闭合会让 script 元素永不闭合、吞掉页面剩余内容。
    // 执行控制由输出处统一注入 CSP nonce（lt_add_nonce_to_scripts）完成。
    return $html;
}

function lt_esc_attr(string $value): string
{

    return lt_esc_html($value);
}

function lt_icon(string $name): string
{

    static $icons = null;
    if ($icons === null) {
        $icons = [
            'grid' => 'category',
            'search' => 'search',
            'left' => 'arrowleft',
            'right' => 'arrowright',
            'mail' => 'email',
            'close' => 'close',
            'weixin' => 'weixin',
            'weibo' => 'weibo',
            'bilibili' => 'bilibili',
            'github' => 'github',
            'zhihu' => 'zhihu',
            'link' => 'copylink',
            'reward' => 'reward',
            'backtop' => 'arrowup',
        ];
    }

    static $aliasMap = ['wechat' => 'weixin', 'email' => 'mail'];
    $key = strtolower(trim($name));
    $key = $aliasMap[$key] ?? $key;
    $cls = $icons[$key] ?? $icons['link'];
    return '<i class="lt-icon icon-' . $cls . '" aria-hidden="true"></i>';
}

function lt_content_image(string $content): string
{


    if (preg_match('/<img[^>]+src=["\']([^"\']+)["\'][^>]*>/i', $content, $matches)) {
        $url = lt_safe_url((string) ($matches[1] ?? ''));
        if ($url !== '') {
            return $url;
        }
    }

    if (preg_match('/!\[[^\]]*\]\(([^)\s]+)(?:\s+"[^"]*")?\)/i', $content, $matches)) {
        return lt_safe_url((string) ($matches[1] ?? ''));
    }

    return '';
}
