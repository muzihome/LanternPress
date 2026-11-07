<?php
declare(strict_types=1);

if (!defined('__TYPECHO_ROOT_DIR__')) exit;













define('LT_THEME_VERSION', '2.5.0');




$ltAssetVersion = '';
$ltAssetVersionFile = dirname(__DIR__) . '/assets/version.php';
if (is_file($ltAssetVersionFile)) {
    $ltAssetVersion = trim((string) @include $ltAssetVersionFile);
}
if ($ltAssetVersion === '') {
    $ltAssetSig = '';
    
    foreach (['/assets/css/*.css', '/assets/js/*.js', '/assets/vendor/*/*.*', '/assets/fonts/*.*', '/assets/img/*.*'] as $ltAssetPattern) {
        foreach ((array) glob(dirname(__DIR__) . $ltAssetPattern) as $ltAssetFile) {
            $ltAssetSig .= basename($ltAssetFile) . ':' . (string) @filemtime($ltAssetFile) . ':' . (string) @filesize($ltAssetFile) . ';';
        }
    }
    $ltAssetVersion = substr(md5($ltAssetSig), 0, 8);
}
define('LT_ASSET_VERSION', $ltAssetVersion);

require_once __DIR__ . '/format.php';
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/cache.php';
require_once __DIR__ . '/fields.php';
require_once __DIR__ . '/nav.php';
require_once __DIR__ . '/content.php';
require_once __DIR__ . '/meta.php';
require_once __DIR__ . '/flinks.php';





function lt_parse_avatar(mixed $mail, bool $proxy = false): string
{
    static $themeBase = null;
    static $proxyCfg = null;
    if ($themeBase === null) {
        $themeBase = rtrim((string) \Typecho\Widget::widget('\Widget\Options')->themeUrl, '/');
    }
    $fallback = $themeBase . '/assets/img/avatar.svg';
    $mail = trim(lt_text($mail));
    $url = '';

    if ($mail !== '') {
        if ($proxyCfg === null) {
            $proxyCfg = trim(lt_text(\Typecho\Widget::widget('\Widget\Options')->avatarProxy ?? ''));
        }
        if ($proxyCfg !== '') {

            $hash = md5(strtolower($mail));
            $candidate = rtrim($proxyCfg, '/') . '/' . $hash . '?s=100&r=G&d=mm';
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

    
    
    if ($proxy && $url !== $fallback) {
        $url = lt_maybe_proxy_image($url);
    }

    return lt_esc_attr($url);
}






function lt_proxy_image_host_allowed(string $host): bool
{
    
    $hostsFile = dirname(__DIR__) . '/cache/poster_hosts.php';
    $hosts = is_file($hostsFile) ? (array) @include $hostsFile : [];
    $host = strtolower(trim($host));
    if ($host === '') {
        return false;
    }
    foreach ($hosts as $eh) {
        $eh = strtolower(trim((string) $eh));
        if ($eh === '') {
            continue;
        }
        if ($eh === $host || (str_starts_with($eh, '.') && str_ends_with($host, $eh))) {
            return true;
        }
    }
    return false;
}












function lt_maybe_proxy_image(string $url): string
{
    static $proxyBase = null;
    static $siteHost = null;

    $url = trim($url);
    if ($url === '') {
        return $url;
    }
    $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
    if (!in_array($scheme, ['http', 'https'], true)) {
        return $url;
    }
    
    if (str_contains($url, '/inc/poster-proxy.php')) {
        return $url;
    }
    
    $ext = strtolower((string) pathinfo((string) parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));
    if (in_array($ext, ['svg', 'svgz'], true)) {
        return $url;
    }
    $host = strtolower(trim((string) parse_url($url, PHP_URL_HOST), '[]'));
    if ($host === '') {
        return $url;
    }
    
    if ($siteHost === null) {
        try {
            $siteHost = strtolower(trim((string) parse_url(rtrim((string) \Typecho\Widget::widget('\Widget\Options')->siteUrl, '/'), PHP_URL_HOST)));
        } catch (\Throwable $e) {
            $siteHost = '';
        }
    }
    if ($siteHost !== '' && $host === $siteHost) {
        return $url;
    }
    
    if (!lt_proxy_image_host_allowed($host)) {
        return $url;
    }
    
    if ($proxyBase === null) {
        try {
            $proxyBase = rtrim((string) \Typecho\Widget::widget('\Widget\Options')->themeUrl, '/') . '/inc/poster-proxy.php';
        } catch (\Throwable $e) {
            $proxyBase = '';
        }
    }
    if ($proxyBase === '') {
        return $url;
    }
    return $proxyBase . '?url=' . rawurlencode($url);
}






function lt_comment_parent_map(int $cid): array
{
    static $maps = [];
    if (isset($maps[$cid])) {
        return $maps[$cid];
    }
    $map = [];
    try {
        $db = \Typecho\Db::get();
        
        $parentRows = $db->fetchAll(
            $db->select('DISTINCT parent')
                ->from('table.comments')
                ->where('cid = ? AND parent > 0', $cid)
        );
        $parents = array_map('intval', array_unique(array_column($parentRows, 'parent')));
        
        
        
        if (!empty($parents)) {
            $authorRows = $db->fetchAll(
                $db->select('coid', 'author')
                    ->from('table.comments')
                    ->where('coid IN ?', $parents)
            );
            foreach ($authorRows as $row) {
                $map[(int) $row['coid']] = (string) $row['author'];
            }
        }
    } catch (\Throwable $e) {
        $map = [];
    }
    $maps[$cid] = $map;
    return $map;
}

function getReply(int $parent, string $content): string
{

    $contentText = nl2br(lt_safe_comment_html(trim($content)), false);

    if ($parent <= 0) {
        return $contentText;
    }

    
    $author = '';
    $map = $GLOBALS['lt_comment_parent_map'] ?? [];
    if (isset($map[$parent])) {
        $author = trim(lt_text($map[$parent]));
    } elseif ($parent > 0) {
        try {
            $db = \Typecho\Db::get();
            $commentInfo = $db->fetchRow(
                $db->select('author')
                    ->from('table.comments')
                    ->where('coid = ?', $parent)
                    ->limit(1)
            );
            $author = trim(lt_text($commentInfo['author'] ?? ''));
        } catch (\Throwable $e) {
            $author = '';
        }
    }

    if ($author === '') {
        return $contentText;
    }

    return '<p><span>@' . lt_esc_html($author) . '</span> ' . $contentText . '</p>';
}

function getThumb(object $archive, object $options, ?string $bannerOverride = null): string
{

    $banner = lt_text($bannerOverride ?? ($archive->fields->bannerUrl ?? ''));
    if ($banner !== '') {
        return lt_safe_url($banner);
    }

    
    $thumbs = lt_lines($options->indexThumbs ?? '');
    if (!empty($thumbs)) {
        return lt_safe_url($thumbs[(int) ($archive->cid ?? 0) % count($thumbs)]);
    }

    
    
    
    $rawText = lt_text($archive->text ?? '');
    if ($rawText !== '' && str_contains($rawText, '<img')) {
        $thumb = lt_content_image($rawText);
        if ($thumb !== '') {
            return $thumb;
        }
    }

    return loadThumb(lt_text($archive->content ?? ''), $options, (int) ($archive->cid ?? 0));
}

function loadThumb(string $content, object $options, int $cid = 0, string $banner = ''): string
{
    
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

    $defaultThumb = rtrim((string) $options->themeUrl, '/') . '/assets/img/default-cover.avif';
    return lt_safe_url($defaultThumb);
}

function lt_render_post_link(array $content, bool $isNext = false): string
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






function lt_prevnext_flush(): void
{
    $dir = lt_cache_dir('prevnext');
    if ($dir === '') {
        return;
    }
    foreach ((array) glob($dir . '/*.php') as $ltFile) {
        if (is_file($ltFile)) {
            @unlink($ltFile);
        }
    }
}

function lt_prev_next(object $widget, object $options, string $direction): string
{
    try {
        $isNext = $direction === 'next';
        $cid = (int) $widget->cid;
        $type = (string) ($widget->type ?? 'post');
        
        
        
        $cacheDir = lt_cache_dir('prevnext');
        $cachePath = '';
        if ($cacheDir !== '' && $cid > 0) {
            $cachePath = $cacheDir . '/' . $cid . '_' . ($isNext ? 'n' : 'p') . '_' . md5($type) . '.php';
            $raw = lt_cache_read($cachePath);
            if ($raw !== null && time() - (int) @filemtime($cachePath) < 3600) {
                return $raw === 'EMPTY' ? '' : $raw;
            }
        }

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
                ->where('table.contents.type = ?', $type)
                ->where('table.contents.created <= ?', time())
                ->where('table.contents.password IS NULL OR table.contents.password = ?', '')
                ->order('table.contents.created', $isNext ? \Typecho\Db::SORT_ASC : \Typecho\Db::SORT_DESC)
                ->order('table.contents.cid', $isNext ? \Typecho\Db::SORT_ASC : \Typecho\Db::SORT_DESC)
                ->limit(1)
        );
        if (!$row) {
            if ($cachePath !== '') {
                lt_cache_write($cachePath, 'EMPTY');
            }
            return '';
        }

        $row = $widget->filter($row);
        $row['permalink'] = \Typecho\Common::url(
            \Typecho\Router::url(($row['type'] ?? 'post') === 'page' ? 'page' : 'post', $row),
            (string) $options->index
        );

        
        $html = lt_render_post_link($row, $isNext);
        if ($cachePath !== '') {
            lt_cache_write($cachePath, $html);
        }
        return $html;
    } catch (\Throwable $e) {
        lt_log_error('lt_prev_next failed direction=' . $direction, $e);
        return '';
    }
}
