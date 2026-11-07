<?php
declare(strict_types=1);

if (!defined('__TYPECHO_ROOT_DIR__')) exit;





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





function lt_safe_comment_html(string $text): string
{
    
    $text = (string) preg_replace('#<(script|style|iframe|object|embed|form|noscript|template)\b[^>]*>.*?</\1\s*>#is', '', $text);
    
    $text = strip_tags($text, '<p><br><a><strong><em><u><s><code><pre><blockquote><ul><ol><li><img><span><h1><h2><h3><h4><h5><h6>');
    
    
    
    $text = (string) preg_replace('/(?<=[ \t\r\n\/"\'])(on[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+))/i', '', $text);
    $text = (string) preg_replace('/(?<=[ \t\r\n\/"\'])(style\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+))/i', '', $text);
    
    
    
    $ltAllowedAttrs = ['href', 'src', 'alt', 'title', 'target', 'rel', 'width', 'height', 'class', 'loading'];
    $text = (string) preg_replace_callback(
        '/<(\w+)\b([^>]*)>/',
        static function (array $m) use ($ltAllowedAttrs): string {
            $attrs = (string) preg_replace_callback(
                '/(?<=[ \t\r\n\/"\'])([a-zA-Z_:][-a-zA-Z0-9_:.]*)(\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s>]+))?/',
                static function (array $am) use ($ltAllowedAttrs): string {
                    return in_array(strtolower($am[1]), $ltAllowedAttrs, true) ? $am[0] : '';
                },
                $m[2]
            );
            
            $attrs = (string) preg_replace('/\s{2,}/', ' ', $attrs);
            return '<' . $m[1] . rtrim($attrs) . '>';
        },
        $text
    );
    
    
    $ltSafeProto = static function (string $url): bool {
        return preg_match('~^(?:https?://|mailto:|#|/|\.{1,2}/)[^"\']*$~i', $url) === 1;
    };
    
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






function lt_safe_ad_html(string $html): string
{
    if ($html === '') {
        return '';
    }
    
    $html = (string) preg_replace('/(?<=[ \t\r\n\/"\'])(on[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+))/i', '', $html);
    
    $html = (string) preg_replace('/(?<=[ \t\r\n\/"\'])(style\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+))/i', '', $html);
    
    
    $html = (string) preg_replace_callback(
        '/(?<=[ \t\r\n\/"\'])((?:href|src|action)\s*=\s*)(["\']?)([^"\'>\s]+)\2/i',
        static function (array $m): string {
            $rawUrl = (string) ($m[3] ?? '');
            $url = trim((string) html_entity_decode($rawUrl, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            if ($url === '') {
                return $m[0];
            }
            if (preg_match('~^(?:javascript|data|vbscript):~i', $url) === 1) {
                return $m[1] . $m[2] . '#' . $m[2];
            }
            return $m[0];
        },
        $html
    );
    
    
    
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
