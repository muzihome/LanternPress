<?php
declare(strict_types=1);

if (!defined('__TYPECHO_ROOT_DIR__')) exit;

define('LT_MIN_VIEWS_DISPLAY', 1);
define('LT_MAX_SOCIAL_LINKS', 10);
define('LT_MAX_REWARD_IMAGES', 2);
define('LT_DEFAULT_EXCERPT_LENGTH', 130);
define('LT_MAX_URL_LENGTH', 2048);

define('LT_CSP_POLICY', "default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline' https:; img-src 'self' data: blob: https:; font-src 'self' data: https:; connect-src 'self' https:; media-src 'self' https:; object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'self'");

define('LT_THEME_VERSION', '2.4.0');

define('LT_ASSET_VERSION', '1.4.16');



// 内容缓存结构版本：正文处理相关文件（core.php / functions.php / post.php / sidebar.php）任一变更即自动派生新版本，
// 全站正文缓存一次性失效并重建——替代发布时手动递增 LT_CONTENT_CACHE_VERSION（避免遗漏导致旧缓存串新逻辑）
$ltContentCacheFiles = [__FILE__, dirname(__DIR__) . '/functions.php', dirname(__DIR__) . '/post.php', dirname(__DIR__) . '/sidebar.php'];
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
    // a[href] / img[src] 仅允许 http(s) / mailto / # 锚点 / 相对路径，其余危险协议替换为 "#"
    $text = (string) preg_replace_callback(
        '/(<(?:a|img)\b[^>]*?(?:href|src)\s*=\s*)(["\'])(.*?)\2/i',
        static function (array $m): string {
            $url = trim((string) ($m[3] ?? ''));
            if ($url !== '' && !preg_match('~^(?:https?://|mailto:|#|/|\.{1,2}/)[^"\']*$~i', $url)) {
                return $m[1] . '"#"';
            }
            return $m[0];
        },
        $text
    );
    return $text;
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

function lt_parse_avatar(mixed $mail): string
{
    static $themeBase = null;
    static $proxy = null;
    if ($themeBase === null) {
        $themeBase = rtrim((string) \Typecho\Widget::widget('\Widget\Options')->themeUrl, '/');
    }
    $fallback = $themeBase . '/assets/img/avatar.svg';
    $mail = trim(lt_text($mail));
    $url = '';

    if ($mail !== '') {
        if ($proxy === null) {
            $proxy = trim(lt_text(\Typecho\Widget::widget('\Widget\Options')->avatarProxy ?? ''));
        }
        if ($proxy !== '') {

            $hash = md5(strtolower($mail));
            $candidate = rtrim($proxy, '/') . '/' . $hash . '?s=100&r=G&d=mm';
            $url = lt_safe_url($candidate);
        }
        if ($url === '') {
            $gravatar = \Typecho\Common::gravatarUrl($mail, 100, 'G', null, true);
            $url = lt_safe_url(lt_text($gravatar));
        }
    }

    if ($url === '') {
        $url = $fallback;
    }


    return lt_esc_attr($url);
}

function getReply(int $parent, string $content): string
{

    $contentText = nl2br(lt_safe_comment_html(trim($content)), false);

    if ($parent <= 0) {
        return $contentText;
    }

    try {
        $db = \Typecho\Db::get();
        $commentInfo = $db->fetchRow(
            $db->select('author')
                ->from('table.comments')
                ->where('coid = ?', $parent)
                ->limit(1)
        );

        $author = trim(lt_text($commentInfo['author'] ?? ''));
        if ($author === '') {
            return $contentText;
        }

        return '<p><span>@' . lt_esc_html($author) . '</span> ' . $contentText . '</p>';
    } catch (\Throwable $e) {
        return $contentText;
    }
}

function getThumb(object $archive, object $options, ?string $bannerOverride = null): string
{

    $banner = lt_text($bannerOverride ?? ($archive->fields->bannerUrl ?? ''));
    if ($banner !== '') {
        return lt_safe_url($banner);
    }

    return loadThumb(lt_text($archive->content ?? ''), $options, (int) ($archive->cid ?? 0));
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

function loadThumb(string $content, object $options, int $cid = 0, string $banner = ''): string
{
    // banner 优先（文章自定义字段主图），与列表页 getThumb 同一套缩略图选择链
    if ($banner !== '') {
        return lt_safe_url($banner);
    }
    $thumbs = lt_lines($options->indexThumbs ?? '');
    if (!empty($thumbs)) {

        $thumb = $thumbs[$cid % count($thumbs)];
        return lt_safe_url($thumb);
    }

    $thumb = lt_content_image($content);
    if ($thumb !== '') {
        return $thumb;
    }

    $defaultThumb = rtrim((string) $options->themeUrl, '/') . '/assets/img/blog_bg.jpg';
    return lt_safe_url($defaultThumb);
}

function lt_render_post_link(array $content, object $options, string $text, string $thumb, bool $isNext = false): string
{
    $permalink = lt_safe_url(lt_text($content['permalink'] ?? ''));
    $title = lt_text($content['title'] ?? '');

    if ($permalink === '' || $title === '') {
        return '';
    }

    return '<a href="' . lt_esc_attr($permalink) . '" class="nav-card ' . ($isNext ? 'nav-next' : 'nav-prev') . '" title="' . lt_esc_attr($title) . '">'
        . '<span class="nav-dir">' . ($isNext ? '下一篇' : '上一篇') . '</span>'
        . '<span class="nav-title">' . lt_esc_html($title) . '</span></a>';
}






function lt_prev_next(object $widget, object $options, string $direction): string
{
    try {
        $isNext = $direction === 'next';
        $db = \Typecho\Db::get();

        $select = $db->select()->from('table.contents');
        if ($isNext) {
            $select->where('(table.contents.created > ? OR (table.contents.created = ? AND table.contents.cid > ?))', (int) $widget->created, (int) $widget->created, (int) $widget->cid);
        } else {
            $select->where('(table.contents.created < ? OR (table.contents.created = ? AND table.contents.cid < ?))', (int) $widget->created, (int) $widget->created, (int) $widget->cid);
        }
        $row = $db->fetchRow(
            $select
                ->where('table.contents.status = ?', 'publish')
                ->where('table.contents.type = ?', $widget->type)
                ->where('table.contents.password IS NULL OR table.contents.password = ?', '')
                ->order('table.contents.created', $isNext ? \Typecho\Db::SORT_ASC : \Typecho\Db::SORT_DESC)
                ->order('table.contents.cid', $isNext ? \Typecho\Db::SORT_ASC : \Typecho\Db::SORT_DESC)
                ->limit(1)
        );
        if (!$row) {
            return '';
        }

        $row = $widget->filter($row);
        $row['permalink'] = \Typecho\Common::url(
            \Typecho\Router::url(($row['type'] ?? 'post') === 'page' ? 'page' : 'post', $row),
            (string) $options->index
        );

        // 简化样式无需缩略图，直接渲染方向标签 + 标题
        return lt_render_post_link($row, $options, $isNext ? '下一篇' : '上一篇', '', $isNext);
    } catch (\Throwable $e) {
        lt_log_error('lt_prev_next failed direction=' . $direction, $e);
        return '';
    }
}

function lt_send_security_headers(): void
{
    if (headers_sent()) {
        return;
    }
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');

    header('Content-Security-Policy: ' . LT_CSP_POLICY);

    if (lt_is_https()) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}





function lt_is_https(): bool
{
    if ((!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')
        || (int) ($_SERVER['SERVER_PORT'] ?? 0) === 443) {
        return true;
    }
    // 转发协议头仅在可信代理白名单内采信（与 lt_get_client_ip 同源策略），
    // 防止无代理的 HTTP 站点被伪造 X-Forwarded-Proto 导致 Secure Cookie / HSTS 行为异常
    if (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower((string) $_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https') {
        $ltRemoteAddr = !empty($_SERVER['REMOTE_ADDR']) ? trim((string) $_SERVER['REMOTE_ADDR']) : '';
        $ltTrustedProxies = defined('LT_TRUSTED_PROXY_IPS') ? LT_TRUSTED_PROXY_IPS : null;
        $ltProxyList = is_array($ltTrustedProxies)
            ? $ltTrustedProxies
            : (is_string($ltTrustedProxies) && $ltTrustedProxies !== ''
                ? array_map('trim', explode(',', $ltTrustedProxies))
                : []);
        if (in_array($ltRemoteAddr, $ltProxyList, true)) {
            return true;
        }
    }
    return false;
}



function lt_log_error(string $message, ?\Throwable $e = null): void
{
    try {
        $logMsg = '[LanternPress] ' . $message;
        if ($e !== null) {
            $logMsg .= ' | ' . get_class($e) . ': ' . $e->getMessage() . ' | File: ' . $e->getFile() . ':' . $e->getLine();
        }
        error_log($logMsg);
    } catch (\Throwable $e) {

    }
}




function lt_field_cache(int $cid, string $name, mixed $value = null, bool $delete = false): mixed
{
    static $cache = [];
    $key = $cid . '|' . $name;
    if ($delete) {
        unset($cache[$key]);
        return null;
    }
    if ($value !== null) {
        $cache[$key] = $value;
        return $value;
    }
    return $cache[$key] ?? null;
}



















function lt_field_upsert(
    \Typecho\Db $db,
    int $cid,
    string $name,
    string $type,
    string $intValueRaw,
    string $strValue,
    float $floatValue,
    mixed $updateInt = null,
    ?int $incrementBy = null,
    ?string $updateStr = null
): void {
    $prefix = $db->getPrefix();
    $table = $prefix . 'fields';
    $adapter = $db->getAdapter();
    $adapterClass = get_class($adapter);
    $hasQuote = method_exists($adapter, 'quote');
    $nameQuoted = $hasQuote ? $adapter->quote($name) : "'" . addslashes($name) . "'";
    $strQuoted = $hasQuote ? $adapter->quote($strValue) : "'" . addslashes($strValue) . "'";
    $intValueQuoted = (string) (int) $intValueRaw;


    $isPgOrSQLite = stripos($adapterClass, 'Pgsql') !== false
        || stripos($adapterClass, 'SQLite') !== false;

    if ($isPgOrSQLite) {
        $sql = "INSERT INTO \"{$table}\" (\"cid\", \"name\", \"type\", \"int_value\", \"str_value\", \"float_value\") "
             . "VALUES ({$cid}, {$nameQuoted}, '{$type}', {$intValueQuoted}, {$strQuoted}, {$floatValue}) "
             . "ON CONFLICT (\"cid\", \"name\") DO UPDATE SET ";

        $sets = [];
        if ($incrementBy !== null) {
            $sets[] = "\"int_value\" = \"int_value\" + " . (int) $incrementBy;
        } elseif ($updateInt !== null) {
            $sets[] = "\"int_value\" = " . (int) $updateInt;
        }
        if ($updateStr !== null) {
            $updateStrQuoted = $hasQuote ? $adapter->quote($updateStr) : "'" . addslashes($updateStr) . "'";
            $sets[] = "\"str_value\" = {$updateStrQuoted}";
        }
        if (empty($sets)) {

            $sql = "INSERT INTO \"{$table}\" (\"cid\", \"name\", \"type\", \"int_value\", \"str_value\", \"float_value\") "
                 . "VALUES ({$cid}, {$nameQuoted}, '{$type}', {$intValueQuoted}, {$strQuoted}, {$floatValue}) "
                 . "ON CONFLICT (\"cid\", \"name\") DO NOTHING";
        } else {
            $sql .= implode(', ', $sets);
        }
    } else {

        $sql = "INSERT INTO `{$table}` (`cid`, `name`, `type`, `int_value`, `str_value`, `float_value`) "
             . "VALUES ({$cid}, {$nameQuoted}, '{$type}', {$intValueQuoted}, {$strQuoted}, {$floatValue}) "
             . "ON DUPLICATE KEY UPDATE ";

        $sets = [];
        if ($incrementBy !== null) {
            $sets[] = "`int_value` = `int_value` + " . (int) $incrementBy;
        } elseif ($updateInt !== null) {
            $sets[] = "`int_value` = " . (int) $updateInt;
        }
        if ($updateStr !== null) {
            $updateStrQuoted = $hasQuote ? $adapter->quote($updateStr) : "'" . addslashes($updateStr) . "'";
            $sets[] = "`str_value` = {$updateStrQuoted}";
        }
        if (empty($sets)) {
            $sets[] = "`cid` = `cid`";
        }
        $sql .= implode(', ', $sets);
    }

    $db->query($sql);
}

function lt_get_field_int(int $cid, string $name): int
{
    if ($cid <= 0 || $name === '') {
        return 0;
    }

    $cached = lt_field_cache($cid, $name);
    if ($cached !== null) {
        return $cached;
    }
    try {
        $db = \Typecho\Db::get();
        $row = $db->fetchRow(
            $db->select('int_value')
                ->from('table.fields')
                ->where('cid = ? AND name = ?', $cid, $name)
                ->limit(1)
        );
        $value = $row ? (int) ($row['int_value'] ?? 0) : 0;
        lt_field_cache($cid, $name, $value);
        return $value;
    } catch (\Throwable $e) {
        lt_log_error('lt_get_field_int query failed cid=' . $cid . ' name=' . $name, $e);
        return 0;
    }
}

function lt_set_field_int(int $cid, string $name, int $value): void
{
    if ($cid <= 0 || $name === '') {
        return;
    }
    try {
        $db = \Typecho\Db::get();

        lt_field_upsert($db, $cid, $name, 'int', (string) $value, '0', 0, $value);

        lt_field_cache($cid, $name, $value);
    } catch (\Throwable $e) {
        lt_log_error('lt_set_field_int upsert failed cid=' . $cid . ' name=' . $name . ' value=' . $value, $e);
    }
}



function lt_atomic_increment_field(int $cid, string $name, int $step = 1): int
{
    if ($cid <= 0 || $name === '' || $step === 0) {
        return 0;
    }
    try {
        $db = \Typecho\Db::get();

        lt_field_upsert($db, $cid, $name, 'int', (string) $step, '', 0, null, $step, null);

        $row = $db->fetchRow(
            $db->select('int_value')
                ->from('table.fields')
                ->where('cid = ? AND name = ?', $cid, $name)
                ->limit(1)
        );
        $value = $row ? (int) ($row['int_value'] ?? 0) : $step;
        lt_field_cache($cid, $name, $value);
        return $value;
    } catch (\Throwable $e) {
        lt_log_error('lt_atomic_increment_field failed cid=' . $cid . ' name=' . $name . ' step=' . $step, $e);
        return 0;
    }
}



function lt_get_field_str(int $cid, string $name): ?string
{


    static $queried = [];
    $cacheKey = $cid . '|' . $name;

    if ($cid <= 0 || $name === '') {
        return null;
    }


    if (isset($queried[$cacheKey])) {
        $cached = lt_field_cache($cid, $name);
        return is_string($cached) ? $cached : null;
    }

    $cached = lt_field_cache($cid, $name);
    if ($cached !== null) {
        $queried[$cacheKey] = true;
        return is_string($cached) ? $cached : null;
    }
    try {
        $db = \Typecho\Db::get();
        $row = $db->fetchRow(
            $db->select('str_value')
                ->from('table.fields')
                ->where('cid = ? AND name = ?', $cid, $name)
                ->limit(1)
        );
        $value = $row ? ($row['str_value'] ?? null) : null;
        lt_field_cache($cid, $name, $value);
        $queried[$cacheKey] = true;
        return $value;
    } catch (\Throwable $e) {
        lt_log_error('lt_get_field_str query failed cid=' . $cid . ' name=' . $name, $e);
        return null;
    }
}

function lt_set_field_str(int $cid, string $name, string $value): void
{
    if ($cid <= 0 || $name === '') {
        return;
    }


    if (strlen($value) > 60000) {
        lt_log_error('lt_set_field_str value too large cid=' . $cid . ' name=' . $name . ' length=' . strlen($value), null);
        return;
    }
    try {
        $db = \Typecho\Db::get();

        lt_field_upsert($db, $cid, $name, 'str', '0', $value, 0.0, null, null, $value);
        lt_field_cache($cid, $name, $value);
    } catch (\Throwable $e) {
        lt_log_error('lt_set_field_str upsert failed cid=' . $cid . ' name=' . $name, $e);
    }
}

function lt_delete_field(int $cid, string $name): void
{
    if ($cid <= 0 || $name === '') {
        return;
    }
    try {
        $db = \Typecho\Db::get();
        $db->query(
            $db->delete('table.fields')
                ->where('cid = ? AND name = ?', $cid, $name)
        );
        lt_field_cache($cid, $name, null, true);
    } catch (\Throwable $e) {
        lt_log_error('lt_delete_field failed cid=' . $cid . ' name=' . $name, $e);
    }
}









function lt_get_config_hash(): string
{
    return md5('lt-content-v' . LT_CONTENT_CACHE_VERSION);
}




function lt_content_cache_dir(): string
{
    static $dir = null;
    if ($dir !== null) {
        return $dir;
    }
    $dir = __DIR__ . '/cache/content';
    if (!is_dir($dir)) {
        if (!@mkdir($dir, 0755, true) || !is_dir($dir)) {
            lt_log_error('lt_content_cache_dir: cannot create cache directory: ' . $dir);
            $dir = '';
            return $dir;
        }
    }

    $htaccess = $dir . '/.htaccess';
    if (!is_file($htaccess)) {
        @file_put_contents(
            $htaccess,
            "<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nOrder allow,deny\nDeny from all\n</IfModule>\n"
        );
    }
    return $dir;
}




function lt_content_cache_get(int $cid): ?string
{
    if ($cid <= 0 || ($dir = lt_content_cache_dir()) === '') {
        return null;
    }
    $path = $dir . '/' . $cid . '.php';
    if (!is_file($path)) {
        return null;
    }
    $data = @file_get_contents($path);
    if ($data === false || strpos($data, '<?php exit; ?>') !== 0) {
        return null;
    }
    return substr($data, strlen('<?php exit; ?>'));
}




function lt_content_cache_set(int $cid, string $payload): void
{
    if ($cid <= 0 || $payload === '' || ($dir = lt_content_cache_dir()) === '') {
        return;
    }
    if (strlen($payload) > 60000) {
        lt_log_error('lt_content_cache_set: payload too large cid=' . $cid . ' len=' . strlen($payload));
        return;
    }
    $path = $dir . '/' . $cid . '.php';
    $tmp = $dir . '/.' . $cid . '.tmp.' . bin2hex(random_bytes(4));
    if (@file_put_contents($tmp, '<?php exit; ?>' . $payload, LOCK_EX) === false) {
        return;
    }
    if (!@rename($tmp, $path)) {
        @unlink($tmp);
    }
}




function lt_content_cache_delete(int $cid): void
{
    if ($cid <= 0 || ($dir = lt_content_cache_dir()) === '') {
        return;
    }
    $path = $dir . '/' . $cid . '.php';
    if (is_file($path)) {
        @unlink($path);
    }
}



function lt_get_views(int $cid): int
{
    return lt_get_field_int($cid, 'ltViews');
}

function lt_increment_views(int $cid): void
{
    if ($cid <= 0) {
        return;
    }
    $cookieName = 'lt_views_' . $cid;
    if (!empty($_COOKIE[$cookieName])) {
        return;
    }
    // IP 限频：同一 IP 60 秒内仅计一次，防清除 cookie 后反复刷量
    if (!lt_check_rate_limit('views_' . $cid . '_' . lt_get_client_ip(), 1, 60)) {
        return;
    }

    lt_atomic_increment_field($cid, 'ltViews');
    if (!headers_sent()) {


        setcookie($cookieName, '1', time() + 86400, '/', '', lt_is_https(), true);
    }
}



function lt_get_client_ip(): string
{
    $remoteAddr = !empty($_SERVER['REMOTE_ADDR']) ? trim((string) $_SERVER['REMOTE_ADDR']) : '';



    $trustedProxies = defined('LT_TRUSTED_PROXY_IPS') ? LT_TRUSTED_PROXY_IPS : null;
    $isTrustedProxy = false;

    if ($trustedProxies !== null) {
        if (is_array($trustedProxies)) {
            $isTrustedProxy = in_array($remoteAddr, $trustedProxies, true);
        } elseif (is_string($trustedProxies) && $trustedProxies !== '') {
            $list = array_map('trim', explode(',', $trustedProxies));
            $isTrustedProxy = in_array($remoteAddr, $list, true);
        }
    } else {


        $isTrustedProxy = false;
    }

    $ip = '';
    if ($isTrustedProxy) {
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = trim(explode(',', (string) $_SERVER['HTTP_X_FORWARDED_FOR'])[0]);
        } elseif (!empty($_SERVER['HTTP_X_REAL_IP'])) {
            $ip = trim((string) $_SERVER['HTTP_X_REAL_IP']);
        }
    }


    if ($ip === '') {
        $ip = $remoteAddr;
    }

    return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : 'unknown';
}






function lt_rate_limit_gc(string $cacheDir): void
{
    try {
        foreach ((array) glob($cacheDir . '/rate_limit_*.php') as $file) {
            // 一年一次的窗口（like_once_*）不得被常规 2 天 GC 清理，否则清除 Cookie 后同 IP 可再次点赞
            if (str_starts_with(basename($file), 'rate_limit_like_once_')) {
                continue;
            }
            if (is_file($file) && time() - (int) @filemtime($file) > 86400 * 2) {
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
    $cacheDir = __DIR__ . '/cache';
    $cacheFile = $cacheDir . '/rate_limit_' . md5($key) . '.php';
    $now = time();
    $records = [];
    $allowed = true;


    if (mt_rand(1, 100) === 1) {
        lt_rate_limit_gc($cacheDir);
    }

    try {
        if (!is_dir($cacheDir)) {
            @mkdir($cacheDir, 0755, true);
        }
        // inc/cache 为运行时目录，部署包不含 .htaccess；创建目录时补写防护文件（Apache 2.2/2.4 双版本兼容，与内容缓存目录一致）
        if (is_dir($cacheDir) && !is_file($cacheDir . '/.htaccess')) {
            @file_put_contents(
                $cacheDir . '/.htaccess',
                "<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nOrder allow,deny\nDeny from all\n</IfModule>\n"
            );
        }

        $fp = @fopen($cacheFile, 'c+');
        if ($fp === false) {

            if (is_file($cacheFile)) {
                $data = @file_get_contents($cacheFile);
                if ($data !== false && strpos($data, '<?php exit; ?>') === 0) {
                    $encoded = substr($data, strlen('<?php exit; ?>'));
                    // 限流文件均为本主题 json 写入；旧 unserialize 兼容已移除（防对象注入面）
                    $decoded = json_decode($encoded, true);
                    if (is_array($decoded)) {
                        $records = $decoded;
                    }
                }
            }
            $records = array_values(array_filter($records, static fn($ts) => ($now - $ts) < $windowSeconds));
            if (count($records) >= $maxRequests) {
                return false;
            }
            $records[] = $now;
            if (is_dir($cacheDir) && is_writable($cacheDir)) {
                @file_put_contents($cacheFile, '<?php exit; ?>' . json_encode($records));
            }
            return true;
        }


        if (!@flock($fp, LOCK_EX | LOCK_NB)) {
            @fclose($fp);
            return true;
        }


        rewind($fp);
        $data = stream_get_contents($fp);
        if ($data !== false && strpos($data, '<?php exit; ?>') === 0) {
            $encoded = substr($data, strlen('<?php exit; ?>'));
            // 限流文件均为本主题 json 写入；旧 unserialize 兼容已移除（防对象注入面）
            $decoded = json_decode($encoded, true);
            if (is_array($decoded)) {
                $records = $decoded;
            }
        }


        $records = array_values(array_filter($records, static fn($ts) => ($now - $ts) < $windowSeconds));


        if (count($records) >= $maxRequests) {
            $allowed = false;
        } else {
            $records[] = $now;

            ftruncate($fp, 0);
            rewind($fp);
            @fwrite($fp, '<?php exit; ?>' . json_encode($records));
        }

        @flock($fp, LOCK_UN);
        @fclose($fp);
    } catch (\Throwable $e) {

        return true;
    }

    return $allowed;
}



function lt_get_likes(int $cid): int
{
    return lt_get_field_int($cid, 'ltLikes');
}

function lt_has_liked(int $cid): bool
{
    return $cid > 0 && !empty($_COOKIE['lt_liked_' . $cid]);
}

function lt_increment_likes(int $cid): int
{
    if ($cid <= 0) {
        return 0;
    }
    $cookieName = 'lt_liked_' . $cid;
    if (!empty($_COOKIE[$cookieName])) {
        return lt_get_likes($cid);
    }
    // IP 一次去重（1 年窗口）：清 cookie 后同 IP 不可重复刷赞
    $ltLikeIp = lt_get_client_ip();
    if ($ltLikeIp !== '' && !lt_check_rate_limit('like_once_' . $cid . '_' . $ltLikeIp, 1, 31536000)) {
        return lt_get_likes($cid);
    }

    $newValue = lt_atomic_increment_field($cid, 'ltLikes');
    if (!headers_sent()) {


        setcookie($cookieName, '1', time() + 31536000, '/', '', lt_is_https(), true);
    }
    return $newValue;
}


