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

// ============================================================
// 内容助手（头像 / 评论回复 / 缩略图 / 上一篇·下一篇导航）
// ============================================================

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
                ->where('table.contents.created <= ?', time())
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
        return lt_render_post_link($row, $isNext);
    } catch (\Throwable $e) {
        lt_log_error('lt_prev_next failed direction=' . $direction, $e);
        return '';
    }
}
