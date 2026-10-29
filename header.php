<?php
declare(strict_types=1);

if (!defined('__TYPECHO_ROOT_DIR__')) exit;

use Utils\Helper;




$ltAssetVersion = LT_ASSET_VERSION;

// 海报代理图床域名白名单下发（后台「文章页组件」→ posterProxyHosts → cache/poster_hosts.php）
lt_sync_poster_hosts(lt_text(Helper::options()->posterProxyHosts ?? ''));


$themeUrlFull = Helper::options()->themeUrl(null, Helper::options()->theme);
$themeUrlParts = parse_url($themeUrlFull);
$themeUrlRelative = '/';
if (is_array($themeUrlParts) && isset($themeUrlParts['path'])) {
    $themeUrlRelative = (string) $themeUrlParts['path'];
} elseif (is_string($themeUrlFull)) {
    $themeUrlRelative = '/' . ltrim($themeUrlFull, '/');
}
$themeModeNum = (int) lt_text($this->options->themeMode ?? 0);
if ($themeModeNum < 0 || $themeModeNum > 4) {
    $themeModeNum = 0;
}
$isIndexPage = $this->is('index');
$isSearchPage = $this->is('search');
$isSingle = $this->is('post') || $this->is('page');

$isHiddenSingle = $isSingle && (bool) ($this->hidden ?? false);
$themeConfig = [
    'THEME_URL' => rtrim($themeUrlRelative, '/') . '/',
    'BLOG_TITLE' => lt_text($this->options->title ?? ''),
    'THEME_LOGO' => lt_text($this->options->logoUrl ?? ''),
    'POSTER_LOGO' => lt_text($this->options->posterLogo ?? ''),
    'TURN_PAGE_TYPE' => lt_text($this->options->turnPageType ?? 'page'),
    'THEME_MODE' => $themeModeNum,
    'ASSET_VERSION' => LT_ASSET_VERSION,
    'POST_FOLD_ENABLE' => (string) lt_text($this->options->postFoldEnable ?? '1'),
    'POST_FOLD_THRESHOLD' => (string) lt_text($this->options->postFoldThreshold ?? '2000'),
    'POST_FOLD_HEIGHT' => (string) lt_text($this->options->postFoldHeight ?? '1500'),
    'POST_FOLD_MIN_RATIO' => (string) lt_text($this->options->postFoldMinRatio ?? '20')
];
$shortcutIcon = trim(lt_text($this->options->shortcutIcon ?? ''));
$logoUrl = lt_text($this->options->logoUrl ?? '');
$siteTitle = lt_text($this->options->title ?? '');
$siteUrl = rtrim(lt_text($this->options->siteUrl), '/');


// 页面元信息统一构建（inc/meta.php）：title/canonical/description/OG 图/feed·search 路由/面包屑/JSON-LD/Prism 判定
[
    'pageTitle' => $pageTitle,
    'pageTitleMeta' => $pageTitleMeta,
    'canonicalUrl' => $canonicalUrl,
    'pageDesc' => $pageDesc,
    'needPrism' => $needPrism,
    'ogImage' => $ogImage,
    'ogImageIsDefault' => $ogImageIsDefault,
    'ogImageW' => $ogImageW,
    'ogImageH' => $ogImageH,
    'feedUrl' => $feedUrl,
    'breadcrumb' => $breadcrumb,
    'ltLdJson' => $ltLdJson,
    'ltBreadcrumbJson' => $ltBreadcrumbJson,
] = lt_meta_build($this, $themeUrlFull);

// 导航菜单树（批次24）：构建器 JSON → 老格式 名称|URL → 自动列出独立页面，统一树结构渲染
$navTree = lt_nav_tree($this->options);
if ($navTree === []) {
    $pages = $this->widget('\Widget\Contents\Page\Rows');
    while ($pages->next()) {
        $navTree[] = [
            'title' => lt_text($pages->title),
            'permalink' => lt_text($pages->permalink),
            'children' => [],
        ];
    }
}

$customCss = trim(lt_text($this->options->customCss ?? ''));
$customCssEscaped = lt_escape_inline_code($customCss);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="renderer" content="webkit">
    <meta name="viewport" content="width=device-width, initial-scale=1">
<?php
    $avatarPreconnect = trim(lt_text($this->options->avatarProxy ?? ''));
    if ($avatarPreconnect !== ''):
        $apHost = parse_url($avatarPreconnect, PHP_URL_HOST);
        if ($apHost):
    ?>
    <link rel="preconnect" href="https://<?php echo lt_esc_attr($apHost); ?>" crossorigin>
<?php
        endif;
    else:
    ?>
    <link rel="preconnect" href="https://secure.gravatar.com" crossorigin>
<?php endif; ?>
<?php if ($shortcutIcon !== '' && strlen($shortcutIcon) > 5): ?>
    <link rel="shortcut icon" href="<?php echo lt_esc_attr($shortcutIcon); ?>">
<?php else: ?>
    <link rel="icon" type="image/svg+xml" href="<?php $this->options->themeUrl('assets/img/icon.svg'); ?>?v=<?php echo $ltAssetVersion; ?>">
<?php endif; ?>
    <link rel="manifest" href="<?php $this->options->themeUrl('inc/manifest.php'); ?>">
    <link rel="alternate" type="application/rss+xml" title="RSS 2.0" href="<?php echo lt_esc_attr($feedUrl); ?>"/>
<?php if ($canonicalUrl !== '' && !$isSearchPage): ?>
    <link rel="canonical" href="<?php echo lt_esc_attr($canonicalUrl); ?>"/>
<?php endif; ?>
<?php if ($isSearchPage || $this->is('archive', '404')): ?>
    <meta name="robots" content="noindex,follow">
<?php endif; ?>
<?php if ($pageDesc !== ''): ?>
    <meta name="description" content="<?php echo lt_esc_attr($pageDesc); ?>">
<?php endif; ?>

    <meta property="og:site_name" content="<?php echo lt_esc_attr($siteTitle); ?>">
    <meta property="og:type" content="<?php echo $isSingle ? 'article' : 'website'; ?>">
    <meta property="og:title" content="<?php echo lt_esc_attr($pageTitleMeta); ?>">
    <meta property="og:url" content="<?php echo lt_esc_attr($canonicalUrl !== '' ? $canonicalUrl : $siteUrl); ?>">
<?php if ($pageDesc !== ''): ?>
    <meta property="og:description" content="<?php echo lt_esc_attr($pageDesc); ?>">
<?php endif; ?>
<?php if ($ogImage !== ''): ?>
    <meta property="og:image" content="<?php echo lt_esc_attr($ogImage); ?>">
<?php if ($ogImageW > 0 && $ogImageH > 0): ?>
    <meta property="og:image:width" content="<?php echo (int) $ogImageW; ?>">
    <meta property="og:image:height" content="<?php echo (int) $ogImageH; ?>">
<?php endif; ?>
    <meta name="theme:poster_cover" content="<?php echo $ogImageIsDefault ? '0' : '1'; ?>">
<?php endif; ?>


    <script type="application/ld+json">
<?php echo $ltLdJson . "\n"; ?>
    </script>
<?php if ($breadcrumb !== null): ?>
    <script type="application/ld+json">
<?php echo $ltBreadcrumbJson . "\n"; ?>
    </script>
<?php endif; ?>
    <link rel="stylesheet" type="text/css" media="all" href="<?php $this->options->themeUrl('assets/css/iconfont.min.css'); ?>?v=<?php echo $ltAssetVersion; ?>"/>
    <link rel="stylesheet" type="text/css" media="all" href="<?php $this->options->themeUrl('assets/css/lantern.min.css'); ?>?v=<?php echo $ltAssetVersion; ?>"/>
    <script type="application/json" id="lt-config"><?php
        // 配置以 application/json 形式输出（非可执行脚本），前端通过 DOM 读取后解析，
        // 消除内联可执行脚本对 CSP 'unsafe-inline' 的依赖（Typecho 评论系统仍需 unsafe-inline）。
        // JSON_HEX_TAG 确保 </script> 等序列被转义为 \u003C 形式，不突破 script 标签边界。
        echo json_encode($themeConfig, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
    ?></script>
<?php if ($needPrism): ?>
    <link rel="stylesheet" href="<?php $this->options->themeUrl('assets/vendor/prism/prism.min.css'); ?>?v=<?php echo $ltAssetVersion; ?>"/>
    <script type="text/javascript" defer src="<?php $this->options->themeUrl('assets/vendor/prism/prism.min.js'); ?>?v=<?php echo $ltAssetVersion; ?>"></script>
<?php endif; ?>
<?php $this->header(
        'generator=&template=&pingback=&xmlrpc=&wlw=&rss2=&rss1=&atom=&keywords=&social=0&description=&commentReply='
    ); ?>
    <title><?php echo $pageTitle; ?></title>
<?php if ($customCssEscaped !== ''): ?>
    <style nonce="<?php echo lt_esc_attr(lt_generate_csp_nonce()); ?>"><?php echo $customCssEscaped; ?></style>
<?php endif; ?>
</head>
<body id="blog_container" class="<?php echo $themeModeNum === 4 ? 'bg-auto' : 'bg' . $themeModeNum; ?>">
<script nonce="<?php echo lt_esc_attr(lt_generate_csp_nonce()); ?>">
    (function () {
        var cfgEl = document.getElementById('lt-config');
        var cfg = cfgEl ? JSON.parse(cfgEl.textContent) : {};
        var body = document.getElementById('blog_container');
        if (body && parseInt(cfg.THEME_MODE, 10) === 4 && window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
            body.className = 'bg3';
        }
    })();
</script>
<a class="skip-link" href="#main-content"><?php _e('跳到主要内容'); ?></a>
<div class="site-container">
    <nav class="navbar" id="navbar">
        <a class="navbar-logo" href="<?php $this->options->siteUrl(); ?>">
<?php if ($logoUrl): ?>
                <img class="logo" src="<?php echo lt_esc_attr($logoUrl); ?>" alt="<?php echo lt_esc_attr($siteTitle); ?>"/>
<?php else: ?>
                <span class="logo-text"><?php $this->options->title(); ?></span>
<?php endif; ?>
        </a>
        <div class="navbar-menu">
<?php echo lt_nav_render_desktop($navTree); ?>
            <button type="button" id="nav-search-btn" class="navbar-item nav-search-btn" aria-label="搜索">搜索</button>
        </div>
        <div class="navbar-mobile-menu">
            <div id="navbar-mobile-menu-icon" class="navbar-mobile-menu-icon" role="button" aria-label="菜单" aria-expanded="false" aria-controls="mobile-menu-list" tabindex="0">
                <span></span>
                <span></span>
                <span></span>
                <span></span>
            </div>
            <ul id="mobile-menu-list">
<?php $ltNavSeq = 0; echo lt_nav_render_mobile($navTree, $ltNavSeq); ?>
                <li>
                    <button type="button" id="nav-search-btn-mobile" class="nav-search-btn-mobile" aria-label="搜索">搜索</button>
                </li>
            </ul>
        </div>
    </nav>
    <div id="search-modal" class="search-modal" role="dialog" aria-modal="true" aria-label="搜索">
        <div class="search-modal-overlay" id="search-modal-overlay"></div>
        <div class="search-modal-box">
<?php

            $searchActionUrl = $siteUrl . '/';
            try {
                $resolved = \Typecho\Router::url('search', [], $this->options->index);
                if (is_string($resolved) && $resolved !== '') {
                    $searchActionUrl = $resolved;
                }
            } catch (\Throwable $e) {  }



            $modalKeyword = '';
            if ($this->is('search')) {
                if (method_exists($this, 'getArchiveKeywords')) {
                    $modalKeyword = trim((string) $this->getArchiveKeywords());
                }
                if ($modalKeyword === '') {
                    $modalKeyword = (string) $this->request->get('s', '');
                }
                $modalKeyword = mb_substr($modalKeyword, 0, 100, 'UTF-8');
            }
            ?>
            <form class="search-modal-form" action="<?php echo lt_esc_attr($searchActionUrl); ?>" method="get" role="search">
                <input type="text" name="s" id="search-modal-input" class="search-modal-input" placeholder="输入关键词，按回车搜索..." value="<?php echo lt_esc_attr($modalKeyword); ?>" aria-label="搜索关键词">
                <button type="submit" class="search-modal-submit">搜索</button>
            </form>
            <button type="button" class="search-modal-close" id="search-modal-close" aria-label="关闭">×</button>
        </div>
    </div>
    <main id="main-content" class="site-main" tabindex="-1">
