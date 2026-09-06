<?php
declare(strict_types=1);

if (!defined('__TYPECHO_ROOT_DIR__')) exit;

use Utils\Helper;

lt_send_security_headers();

// 资源版本号：改版时递增即可让浏览器拉取新资源（N5）
$ltAssetVersion = '1.0.4';

$themeUrlFull = Helper::options()->themeUrl(null, Helper::options()->theme);
$themeUrlRelative = str_replace(
    '//usr',
    '/usr',
    str_replace(
        Helper::options()->siteUrl,
        Helper::options()->rootUrl . '/',
        $themeUrlFull
    )
);
$themeModeNum = (int) lt_text($this->options->themeMode ?? 0);
if ($themeModeNum < 0 || $themeModeNum > 4) {
    $themeModeNum = 0;
}
$isIndexPage = $this->is('index');
$isSearchPage = $this->is('search');
$isSingle = $this->is('post') || $this->is('page');
$themeConfig = [
    'THEME_URL' => rtrim($themeUrlRelative, '/') . '/',
    'BLOG_TITLE' => lt_text($this->options->title ?? ''),
    'THEME_LOGO' => lt_text($this->options->logoUrl ?? ''),
    'TURN_PAGE_TYPE' => lt_text($this->options->turnPageType ?? 'page'),
    'THEME_MODE' => $themeModeNum
];
$shortcutIcon = trim(lt_text($this->options->shortcutIcon ?? ''));
$fieldKeywords = lt_text($this->fields->keywords ?? '');
$fieldDesc = lt_text($this->fields->desc ?? '');
$currentPage = (int) $this->request->filter('int')->get('page', 1);
$logoUrl = lt_text($this->options->logoUrl ?? '');
$siteTitle = lt_text($this->options->title ?? '');
$siteUrl = rtrim(lt_text($this->options->siteUrl), '/');

// 页面标题（供 <title> / OG / JSON-LD 复用）
$pageTitleParts = [];
if ($currentPage > 1) {
    $pageTitleParts[] = '第 ' . $currentPage . ' 页';
}
ob_start();
$this->archiveTitle(
    [
        'category' => '分类 %s 下的文章',
        'search' => '包含关键词 %s 的文章',
        'tag' => '标签 %s 下的文章',
        'author' => '%s 发布的文章',
    ],
    '',
    ' - '
);
$archiveTitleText = trim((string) ob_get_clean());
if ($archiveTitleText !== '') {
    $pageTitleParts[] = $archiveTitleText;
}
$pageTitleParts[] = lt_esc_html($siteTitle);
$pageTitle = implode(' - ', array_filter($pageTitleParts));
$pageTitleMeta = html_entity_decode($pageTitle, ENT_QUOTES, 'UTF-8');

// SEO：canonical / 描述 / 封面
$canonicalUrl = $siteUrl;
if (!$isIndexPage && !$isSearchPage) {
    $canonicalUrl .= (string) $this->request->getPathInfo();
}
$pageDesc = $fieldDesc;
if ($pageDesc === '' && $isSingle) {
    $pageDesc = trim(lt_text($this->content ?? ''));
    $pageDesc = \Typecho\Common::subStr(strip_tags($pageDesc), 0, 200, '...');
}
$ogImage = '';
if ($isSingle) {
    $ogImage = getThumb($this, $this->options);
} elseif ($logoUrl !== '') {
    $ogImage = $logoUrl;
}

// B1：导航菜单——优先使用后台自定义配置，留空则自动显示所有独立页面
$navItems = [];
$navMenuRaw = trim(lt_text($this->options->navMenu ?? ''));
if ($navMenuRaw !== '') {
    foreach (lt_lines($navMenuRaw) as $line) {
        $parts = array_map('trim', explode('|', $line, 2));
        if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
            continue;
        }
        $url = lt_safe_url($parts[1]);
        if ($url === '') {
            continue;
        }
        $navItems[] = [
            'permalink' => $url,
            'title' => $parts[0]
        ];
    }
}
if (empty($navItems)) {
    $pages = $this->widget('\Widget\Contents\Page\Rows');
    while ($pages->next()) {
        $navItems[] = [
            'permalink' => lt_text($pages->permalink),
            'title' => lt_text($pages->title)
        ];
    }
}

// 自定义 CSS（E12）
$customCss = trim(lt_text($this->options->customCss ?? ''));
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="renderer" content="webkit">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <?php // 2. 资源预加载：关键脚本提前下载，Gravatar 提前建连，提升首屏交互速度 ?>
    <?php // lantern.config.js 在 footer 加载，不 preload（避免 preload 与实际加载时间差过大导致浏览器警告） ?>
    <link rel="preload" href="<?php $this->options->themeUrl('libs/jquery/jquery.min.js'); ?>?v=<?php echo $ltAssetVersion; ?>" as="script">
    <?php $avatarPreconnect = trim(lt_text($this->options->avatarProxy ?? '')); if ($avatarPreconnect !== ''): $apHost = parse_url($avatarPreconnect, PHP_URL_HOST); if ($apHost): ?>
    <link rel="preconnect" href="https://<?php echo lt_esc_attr($apHost); ?>" crossorigin>
    <?php endif; else: ?>
    <link rel="preconnect" href="https://secure.gravatar.com" crossorigin>
    <?php endif; ?>
    <?php if ($shortcutIcon !== '' && strlen($shortcutIcon) > 5): ?>
        <link rel="shortcut icon" href="<?php echo lt_esc_attr($shortcutIcon); ?>">
    <?php endif; ?>
    <link rel="alternate" type="application/rss+xml" title="RSS 2.0" href="<?php echo lt_esc_attr($siteUrl); ?>/feed/"/>
    <?php if ($canonicalUrl !== ''): ?>
        <link rel="canonical" href="<?php echo lt_esc_attr($canonicalUrl); ?>"/>
    <?php endif; ?>
    <?php if ($pageDesc !== ''): ?>
        <meta name="description" content="<?php echo lt_esc_attr($pageDesc); ?>">
    <?php endif; ?>
    <!-- Open Graph / Twitter Card -->
    <meta property="og:site_name" content="<?php echo lt_esc_attr($siteTitle); ?>">
    <meta property="og:type" content="<?php echo $isSingle ? 'article' : 'website'; ?>">
    <meta property="og:title" content="<?php echo lt_esc_attr($pageTitleMeta); ?>">
    <meta property="og:url" content="<?php echo lt_esc_attr($canonicalUrl !== '' ? $canonicalUrl : $siteUrl); ?>">
    <?php if ($pageDesc !== ''): ?>
        <meta property="og:description" content="<?php echo lt_esc_attr($pageDesc); ?>">
    <?php endif; ?>
    <?php if ($ogImage !== ''): ?>
        <meta property="og:image" content="<?php echo lt_esc_attr($ogImage); ?>">
        <meta name="twitter:card" content="summary_large_image">
        <meta name="twitter:image" content="<?php echo lt_esc_attr($ogImage); ?>">
    <?php else: ?>
        <meta name="twitter:card" content="summary">
    <?php endif; ?>
    <meta name="twitter:title" content="<?php echo lt_esc_attr($pageTitleMeta); ?>">
    <?php if ($pageDesc !== ''): ?>
        <meta name="twitter:description" content="<?php echo lt_esc_attr($pageDesc); ?>">
    <?php endif; ?>
    <!-- JSON-LD 结构化数据 -->
    <script type="application/ld+json">
    <?php if ($isSingle): ?>
    <?php
        $ldJson = [
            '@context' => 'https://schema.org',
            '@type' => 'Article',
            'headline' => $pageTitleMeta,
            'mainEntityOfPage' => ['@type' => 'WebPage', '@id' => $canonicalUrl !== '' ? $canonicalUrl : $siteUrl],
            'datePublished' => date('c', (int) $this->created),
            // Bug2：modified 为0或无效时回退使用 created，避免输出1970年日期
            'dateModified' => date('c', (int) ((($this->modified ?? 0) > 0) ? $this->modified : $this->created)),
            'author' => ['@type' => 'Person', 'name' => lt_text($this->author->name ?? $this->author->screenName ?? '')],
            'publisher' => ['@type' => 'Organization', 'name' => $siteTitle]
        ];
        if ($ogImage !== '') {
            $ldJson['image'] = [$ogImage];
        }
        if ($pageDesc !== '') {
            $ldJson['description'] = $pageDesc;
        }
        echo json_encode($ldJson, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    ?>
    <?php else: ?>
    <?php
        echo json_encode([
            '@context' => 'https://schema.org',
            '@type' => 'WebSite',
            'name' => $siteTitle,
            'url' => $siteUrl,
            'potentialAction' => [
                '@type' => 'SearchAction',
                'target' => $siteUrl . '/search/{search_term_string}/',
                'query-input' => 'required name=search_term_string'
            ]
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    ?>
    <?php endif; ?>
    </script>
    <script type="text/javascript" src="<?php $this->options->themeUrl('libs/jquery/jquery.min.js'); ?>?v=<?php echo $ltAssetVersion; ?>"></script>
    <script type="text/javascript" src="<?php $this->options->themeUrl('libs/headroom/headroom.min.js'); ?>?v=<?php echo $ltAssetVersion; ?>"></script>
    <link rel="stylesheet" type="text/css" media="all" href="<?php $this->options->themeUrl('assets/css/font.css'); ?>?v=<?php echo $ltAssetVersion; ?>"/>
    <link rel="stylesheet" type="text/css" media="all" href="<?php $this->options->themeUrl('assets/css/lantern.min.css'); ?>?v=<?php echo $ltAssetVersion; ?>"/>
    <?php if ($isIndexPage): ?>
        <link rel="stylesheet" href="<?php $this->options->themeUrl('libs/swiper/swiper-bundle.min.css'); ?>?v=<?php echo $ltAssetVersion; ?>"/>
        <script type="text/javascript" src="<?php $this->options->themeUrl('libs/swiper/swiper-bundle.min.js'); ?>?v=<?php echo $ltAssetVersion; ?>"></script>
    <?php endif; ?>
    <script>
        window.LANTERTOWN_CONFIG = <?php echo json_encode($themeConfig, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_THROW_ON_ERROR); ?>;
    </script>
    <?php if ($isSingle): ?>
        <link rel="stylesheet" href="<?php $this->options->themeUrl('libs/prism/prism.min.css'); ?>?v=<?php echo $ltAssetVersion; ?>"/>
        <script type="text/javascript" defer src="<?php $this->options->themeUrl('libs/prism/prism.min.js'); ?>?v=<?php echo $ltAssetVersion; ?>"></script>
        <script type="text/javascript" defer src="<?php $this->options->themeUrl('libs/clipboard/clipboard.min.js'); ?>?v=<?php echo $ltAssetVersion; ?>"></script>
        <link rel="stylesheet" href="<?php $this->options->themeUrl('libs/fancybox/jquery.fancybox.min.css'); ?>?v=<?php echo $ltAssetVersion; ?>"/>
        <script type="text/javascript" defer src="<?php $this->options->themeUrl('libs/fancybox/jquery.fancybox.min.js'); ?>?v=<?php echo $ltAssetVersion; ?>"></script>
    <?php endif; ?>
    <?php if ($fieldKeywords !== '' || $fieldDesc !== '') : ?>
        <?php $this->header('keywords=' . rawurlencode($fieldKeywords) . '&description=' . rawurlencode($fieldDesc)); ?>
    <?php else : ?>
        <?php $this->header(); ?>
    <?php endif; ?>
    <title>
        <?php echo $pageTitle; ?>
    </title>
    <?php if ($customCss !== ''): ?>
        <style><?php echo $customCss; ?></style>
    <?php endif; ?>
    <?php // B8：主题内置样式（点赞按钮） ?>
    <style>
        .lt-like-btn { cursor: pointer; transition: all .2s; }
        .lt-like-btn:hover { color: #cc493d; }
        .lt-like-btn.liked, .lt-like-btn:disabled { color: #cc493d; cursor: default; opacity: 1; }
        .lt-like-icon { font-size: 16px; margin-right: 2px; }
        .lt-like-count { font-weight: bold; }
        /* B1：移动端目录树浮动按钮 + 面板 */
        /* F3：按钮移到右侧中部，避免与右下角返回顶部按钮重叠 */
        .catalog-float-btn { display: none; position: fixed; right: 12px; bottom: 50%; transform: translateY(50%); width: 40px; height: 40px; border-radius: 50%; background: #cc493d; color: #fff; border: none; font-size: 16px; cursor: pointer; z-index: 9998; box-shadow: 0 2px 8px rgba(0,0,0,0.3); }
        .catalog-container.mobile-panel { position: fixed; top: 0; right: -280px; width: 280px; height: 100%; background: #fff; z-index: 9999; overflow-y: auto; transition: right 0.3s ease; padding: 20px 16px; box-shadow: -2px 0 8px rgba(0,0,0,0.1); }
        .catalog-container.mobile-panel.open { right: 0; }
        .catalog-container.mobile-panel .catalog-directory { opacity: 1 !important; }
        .catalog-container.mobile-panel .catalog-directory ul { list-style: none; padding: 0; margin: 0; }
        .catalog-container.mobile-panel .catalog-directory li { margin: 8px 0; }
        .catalog-container.mobile-panel .catalog-directory a { color: #333; text-decoration: none; font-size: 14px; display: block; padding: 4px 0; }
        .catalog-container.mobile-panel .catalog-directory a.current { color: #cc493d; font-weight: bold; }
        /* O2：移动端目录面板标题栏（桌面端隐藏） */
        .catalog-panel-header { display: none; }
        .catalog-container.mobile-panel .catalog-panel-header { display: flex; justify-content: space-between; align-items: center; padding: 0 0 12px; margin-bottom: 12px; border-bottom: 1px solid #eee; }
        .catalog-panel-title { font-size: 16px; font-weight: bold; color: #333; }
        .catalog-panel-close { background: none; border: none; font-size: 22px; color: #999; cursor: pointer; line-height: 1; padding: 0 4px; }
        .catalog-panel-close:hover { color: #333; }
        /* Bug3：移动端目录面板遮罩层 */
        .catalog-mask { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 9997; }
        .catalog-mask.show { display: block; }
        /* 搜索模态框 */
        .nav-search-btn { background: none; border: none; cursor: pointer; font-size: 14px; color: inherit; padding: 0 8px; }
        .nav-search-btn:hover { color: #cc493d; }
        .nav-search-btn-mobile { background-color: #eee; border: none; cursor: pointer; padding: 8px 25px; font-size: 15px; line-height: 2.2; height: 49px; display: block; width: 140px; margin: 0; color: inherit; text-align: left; }
        .nav-search-btn-mobile:hover { background-color: #e0e0e0; }
        .nav-search-btn-mobile:active { color: #cc493d; }
        .search-modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; z-index: 10000; align-items: center; justify-content: center; }
        .search-modal.show { display: flex; }
        .search-modal-overlay { position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); }
        .search-modal-box { position: relative; width: 90%; max-width: 560px; background: #fff; border-radius: 8px; padding: 32px 24px 24px; box-shadow: 0 8px 32px rgba(0,0,0,0.2); animation: searchModalIn 0.2s ease; }
        @keyframes searchModalIn { from { opacity: 0; transform: translateY(-20px) scale(0.96); } to { opacity: 1; transform: translateY(0) scale(1); } }
        .search-modal-form { display: flex; gap: 12px; align-items: center; }
        .search-modal-input { flex: 1; padding: 12px 16px; border: 2px solid #e0e0e0; border-radius: 6px; font-size: 16px; outline: none; transition: border-color 0.2s; }
        .search-modal-input:focus { border-color: #2c2c2c; }
        .search-modal-submit { padding: 12px 28px; background: #2c2c2c; color: #fff; border: none; border-radius: 6px; font-size: 15px; cursor: pointer; white-space: nowrap; transition: background 0.2s; }
        .search-modal-submit:hover { background: #1a1a1a; }
        .search-modal-close { position: absolute; top: 8px; right: 12px; background: none; border: none; font-size: 24px; color: #999; cursor: pointer; line-height: 1; padding: 4px 8px; }
        .search-modal-close:hover { color: #333; }
        @media (max-width: 480px) {
            .search-modal-box { padding: 24px 16px 16px; }
            .search-modal-form { flex-direction: column; gap: 10px; }
            .search-modal-input { width: 100%; }
            .search-modal-submit { width: 100%; }
        }
        /* 文章正文段首缩进2个字符（仅直接子段落，引用块/列表内段落不缩进） */
        #post-content > p { text-indent: 2em; }
        /* O2：评论分页 prev/next 中的 SVG 图标尺寸对齐 */
        .pagination-container .prev svg, .pagination-container .next svg { width: 16px; height: 16px; vertical-align: middle; }
        /* Bug3：推荐文章轮播样式（从 index.recommend.php 内联 style 迁移至此） */
        .recommend-slider .swiper { width: 100%; height: 400px; }
        .recommend-slider .swiper-slide { height: auto; }
        @media (max-width: 768px) {
            .recommend-slider .swiper { height: 500px; margin-bottom: 15px; }
        }
    </style>
</head>
<body id="blog_container" class="<?php echo $themeModeNum === 4 ? 'bg-auto' : 'bg' . $themeModeNum; ?>">
<script>
    // E9：跟随系统模式下，首帧即按系统偏好设置明/暗，避免深色用户看到白闪
    (function () {
        var cfg = window.LANTERTOWN_CONFIG || {};
        var body = document.getElementById('blog_container');
        if (body && parseInt(cfg.THEME_MODE, 10) === 4 && window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
            body.className = 'bg3';
        }
    })();
</script>
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
            <?php foreach ($navItems as $item): ?>
                <a class="navbar-item hover-line" href="<?php echo lt_esc_attr($item['permalink']); ?>"><?php echo lt_esc_html($item['title']); ?></a>
            <?php endforeach; ?>
            <button type="button" id="nav-search-btn" class="navbar-item nav-search-btn" aria-label="搜索">搜索</button>
        </div>
        <div class="navbar-mobile-menu">
            <div id="navbar-mobile-menu-icon" class="navbar-mobile-menu-icon" role="button" aria-label="菜单" aria-expanded="false" aria-controls="mobile-menu-list" onclick="showMobileMenu()">
                <span></span>
                <span></span>
                <span></span>
                <span></span>
            </div>
            <ul id="mobile-menu-list">
                <?php foreach ($navItems as $item): ?>
                    <li>
                        <a href="<?php echo lt_esc_attr($item['permalink']); ?>"><?php echo lt_esc_html($item['title']); ?></a>
                    </li>
                <?php endforeach; ?>
                <li>
                    <button type="button" id="nav-search-btn-mobile" class="nav-search-btn-mobile" aria-label="搜索">搜索</button>
                </li>
            </ul>
        </div>
    </nav>
    <?php // 搜索模态框（点击导航"搜索"按钮弹出） ?>
    <div id="search-modal" class="search-modal" role="dialog" aria-modal="true" aria-label="搜索">
        <div class="search-modal-overlay" id="search-modal-overlay"></div>
        <div class="search-modal-box">
            <form class="search-modal-form" action="<?php echo lt_esc_attr($siteUrl); ?>/" method="get" role="search">
                <input type="text" name="s" id="search-modal-input" class="search-modal-input" placeholder="输入关键词，按回车搜索..." value="<?php echo lt_esc_attr($this->request->get('s', '')); ?>" aria-label="搜索关键词">
                <button type="submit" class="search-modal-submit">搜索</button>
            </form>
            <button type="button" class="search-modal-close" id="search-modal-close" aria-label="关闭">×</button>
        </div>
    </div>
    <script type="text/javascript">
        (function() {
            var navNode = document.getElementById('navbar');
            if (navNode && typeof Headroom === 'function') {
                var header = new Headroom(navNode, {
                    tolerance: 0,
                    offset: 70,
                    classes: {
                        initial: 'animated',
                        pinned: 'slideDown',
                        unpinned: 'slideUp'
                    }
                });
                header.init();
            }
        })();
        var showMobileMenu = function () {
            var obj = document.getElementById("navbar-mobile-menu-icon");
            var ul = document.getElementById("mobile-menu-list");
            if (!obj || !ul) return;
            if (obj.classList.contains("open")) {
                obj.classList.remove("open");
                ul.style.display = "none";
                obj.setAttribute("aria-expanded", "false");
            } else {
                obj.classList.add("open");
                ul.style.display = "block";
                obj.setAttribute("aria-expanded", "true");
            }
        };
    </script>
