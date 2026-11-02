<?php
declare(strict_types=1);

if (!defined('__TYPECHO_ROOT_DIR__')) exit;

// ============================================================
// core.php — 主题核心加载器：版本常量 + 模块装配 + 内容助手
// 模块装配顺序（依赖递增）：
//   format.php   文本/转义/净化（纯函数，无模块依赖）
//   security.php CSP nonce / 安全响应头 / 客户端 IP（需 LT_THEME_VERSION 已定义）
//   cache.php    统一文件缓存层 + IP 限流（需 lt_log_error）
//   fields.php   自定义字段存取（需 cache / security）
//   nav.php      导航菜单树（需 format / security）
//   content.php  正文处理管线（需 format / cache / fields）
//   meta.php     head 元信息拼装（需 format / security）
// ============================================================

define('LT_THEME_VERSION', '2.5.0');

// 资产版本：部署环境由 .build/deploy-sync.php 构建时生成 assets/version.php（产物内容哈希）；
// 源码环境无该文件，回退为资产文件 mtime+size 指纹哈希。两端均「内容变更即版本变更」，
// 替代手动递增 LT_ASSET_VERSION（避免遗漏导致浏览器/CDN 旧缓存串新资产）。
$ltAssetVersion = '';
$ltAssetVersionFile = dirname(__DIR__) . '/assets/version.php';
if (is_file($ltAssetVersionFile)) {
    $ltAssetVersion = trim((string) @include $ltAssetVersionFile);
}
if ($ltAssetVersion === '') {
    $ltAssetSig = '';
    // Windows PHP 不支持 GLOB_BRACE，保持独立 glob（含字体与图片，内容变更即版本变更）
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

// ============================================================
// 内容助手（头像 / 评论回复 / 缩略图 / 上一篇·下一篇导航）
// ============================================================

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

    // 可选同源代理（P0-2）：目标域在 poster-proxy 白名单内且非 SVG 时经本域代理加载，
    // 获得服务端缓存头与防盗链 Referer；白名单未配置 / 域不匹配 / SVG 时原样直连
    if ($proxy && $url !== $fallback) {
        $url = lt_maybe_proxy_image($url);
    }

    return lt_esc_attr($url);
}

/**
 * 白名单域判定（纯函数，P0-2）：目标 host 是否命中 cache/poster_hosts.php 白名单
 * （精确匹配 'img.example.com' 或 '.example.com' 前缀通配，与 poster-proxy 端点判定一致）。
 * 文件缺失 / 为空 / 不可解析时返回 false（安全默认：不代理，保持直连）。
 */
function lt_proxy_image_host_allowed(string $host): bool
{
    // 实时读取白名单（与 poster-proxy.php 端点每次 include 行为一致；文件为小型 PHP 数组，开销可忽略）
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

/**
 * 图片同源代理判定（P0-2，友链 logo / 邮箱头像 / 评论头像复用）
 *
 * 目标 URL 满足以下全部条件时改写为 poster-proxy 同源代理地址：
 *   1) http/https 协议且非本站同源（同源直连，无需代理）；
 *   2) 目标域命中 cache/poster_hosts.php 白名单（精确或 '.domain' 前缀通配，与 poster-proxy 判定一致）；
 *   3) 非 SVG/SVGZ（poster-proxy 禁 SVG 防内嵌脚本；<img> 上下文中 SVG 脚本本不执行，直接原样）；
 *   4) 白名单文件缺失/为空时不代理（安全默认：poster-proxy 空白名单恒 403，避免图标全挂）。
 * 已含 poster-proxy 的 URL 原样返回（幂等）。Typecho Options 读取失败（如离线/单测环境）
 * 时无法取得站点域与代理基址 → 安全降级为原样直连。
 */
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
    // 已走代理：幂等返回
    if (str_contains($url, '/inc/poster-proxy.php')) {
        return $url;
    }
    // SVG/SVGZ：poster-proxy 禁 SVG，直连原样（<img> 上下文脚本不执行）
    $ext = strtolower((string) pathinfo((string) parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));
    if (in_array($ext, ['svg', 'svgz'], true)) {
        return $url;
    }
    $host = strtolower(trim((string) parse_url($url, PHP_URL_HOST), '[]'));
    if ($host === '') {
        return $url;
    }
    // 本站同源：直连（站点自身域名的图片无需代理）；Options 不可用（离线/单测）时跳过同源判断
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
    // 白名单判定：未命中 → 直连原样
    if (!lt_proxy_image_host_allowed($host)) {
        return $url;
    }
    // 代理基址：Options 不可用时降级直连
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

/**
 * 评论父作者映射（方案1·N+1 消除）：一次性构建 coid → author 映射，
 * getReply 渲染时查映射，避免每条"回复"评论独立回查父评论作者。
 * 查询失败返回空数组，getReply 回退单条查询（兜底）。
 */
function lt_comment_parent_map(int $cid): array
{
    static $maps = [];
    if (isset($maps[$cid])) {
        return $maps[$cid];
    }
    $map = [];
    try {
        $db = \Typecho\Db::get();
        // 1) 当前文章全部回复评论的父 coid 集合（DISTINCT 去重）
        $parentRows = $db->fetchAll(
            $db->select('DISTINCT parent')
                ->from('table.comments')
                ->where('cid = ? AND parent > 0', $cid)
        );
        $parents = array_map('intval', array_unique(array_column($parentRows, 'parent')));
        // 2) 一次 IN 查询全部父评论的 author（父评论跨页同文章，IN 全量查）
        if (!empty($parents)) {
            $authorRows = $db->fetchAll(
                $db->select('coid', 'author')
                    ->from('table.comments')
                    ->where('coid IN ' . $db->quoteArray($parents))
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

    // 优先查一次性构建的父作者映射（消除 N+1）；map 未含该父（父被删 / map 构建失败）才回退单条查询
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

    return loadThumb(lt_text($archive->content ?? ''), $options, (int) ($archive->cid ?? 0));
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

function lt_prev_next(object $widget, object $options, string $direction): string
{
    try {
        $isNext = $direction === 'next';
        $cid = (int) $widget->cid;
        $type = (string) ($widget->type ?? 'post');
        // L4：上一篇/下一篇结果缓存 cache/prevnext/{cid}_{n|p}_{type}.php（空结果存 EMPTY 占位避免重复查询）
        $cacheDir = lt_cache_dir('prevnext');
        $cachePath = '';
        if ($cacheDir !== '' && $cid > 0) {
            $cachePath = $cacheDir . '/' . $cid . '_' . ($isNext ? 'n' : 'p') . '_' . md5($type) . '.php';
            $raw = lt_cache_read($cachePath);
            if ($raw !== null) {
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

        // 简化样式无需缩略图，直接渲染方向标签 + 标题
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
