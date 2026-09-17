<?php
declare(strict_types=1);

if (!defined('__TYPECHO_ROOT_DIR__')) exit;

use Utils\Helper;




$ltAssetVersion = LT_ASSET_VERSION;


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

$pageTitleMeta = implode(' - ', array_filter(array_merge(
    $currentPage > 1 ? ['第 ' . $currentPage . ' 页'] : [],
    $archiveTitleText !== '' ? [$archiveTitleText] : [],
    $siteTitle !== '' ? [$siteTitle] : []
)));



$canonicalUrl = '';
if ($isSingle) {
    $canonicalUrl = lt_safe_url(lt_text($this->permalink ?? ''));
} elseif (!$isIndexPage && !$isSearchPage) {
    $canonicalUrl = $siteUrl . (string) $this->request->getPathInfo();
}
if ($canonicalUrl === '') {
    $canonicalUrl = $siteUrl;
}
$pageDesc = $fieldDesc;
if ($pageDesc === '' && $isSingle && !$isHiddenSingle) {
    $pageDesc = trim(lt_text($this->content ?? ''));
    $pageDesc = \Typecho\Common::subStr(strip_tags($pageDesc), 0, 200, '...');
}

if ($pageDesc === '' && !$isSingle && !$isIndexPage && !$isSearchPage && $archiveTitleText !== '') {
    $archiveDescRaw = '';
    if (method_exists($this, 'getArchiveDescription')) {
        try {
            $archiveDescRaw = trim((string) $this->getArchiveDescription());
        } catch (\Throwable $e) {
            $archiveDescRaw = '';
        }
    }
    $pageDesc = $archiveDescRaw !== ''
        ? \Typecho\Common::subStr($archiveDescRaw, 0, 200, '...')
        : \Typecho\Common::subStr($archiveTitleText, 0, 200, '...');
}

if ($pageDesc === '' && $isIndexPage) {
    $pageDesc = \Typecho\Common::subStr(trim(lt_text($this->options->description ?? '')), 0, 200, '...');
}


$needPrism = false;
if ($isSingle && !$isHiddenSingle) {
    $rawBody = lt_text($this->content ?? '');
    $needPrism = $rawBody !== '' && (
        stripos($rawBody, '<pre') !== false
        || stripos($rawBody, '<code') !== false
        || strpos($rawBody, '```') !== false
        || strpos($rawBody, '~~~') !== false
    );
}

$ogImage = '';
if ($isSingle && !$isHiddenSingle) {
    $ogImage = getThumb($this, $this->options);
} elseif ($logoUrl !== '') {
    $ogImage = $logoUrl;
}

if ($ogImage === '') {
    $ogImage = rtrim($themeUrlFull, '/') . '/assets/img/blog_bg.jpg';
}


$feedUrl = $siteUrl . '/feed/';
try {
    $resolvedFeed = \Typecho\Router::url('feed', [], $this->options->index);
    if (is_string($resolvedFeed) && $resolvedFeed !== '') {
        $feedUrl = $resolvedFeed;
    }
} catch (\Throwable $e) {  }


$searchLdTarget = $siteUrl . '/search/{search_term_string}/';
try {
    $resolvedSearch = \Typecho\Router::url('search', ['keywords' => '{search_term_string}'], $this->options->index);
    if (is_string($resolvedSearch) && $resolvedSearch !== '') {
        $searchLdTarget = $resolvedSearch;
    }
} catch (\Throwable $e) {  }


$breadcrumb = null;
if ($isSingle && !$isHiddenSingle) {
    $crumbs = [
        ['name' => $siteTitle !== '' ? $siteTitle : '首页', 'url' => $siteUrl . '/']
    ];
    try {
        $postCategories = $this->categories;
        if (is_array($postCategories)) {
            foreach (array_slice($postCategories, 0, 1) as $catRow) {
                $catUrl = lt_safe_url((string) ($catRow['permalink'] ?? ''));
                $catName = trim(lt_text($catRow['name'] ?? ''));
                if ($catUrl !== '' && $catName !== '') {
                    $crumbs[] = ['name' => $catName, 'url' => $catUrl];
                }
            }
        }
    } catch (\Throwable $e) {  }
    $crumbs[] = ['name' => lt_text($this->title ?? ''), 'url' => $canonicalUrl !== '' ? $canonicalUrl : $siteUrl];
    $breadcrumb = $crumbs;
}



$navItems = [];
$navMenuRaw = lt_filter_nav_menu(lt_text($this->options->navMenu ?? ''));
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


$customCss = trim(lt_text($this->options->customCss ?? ''));

$customCssEscaped = $customCss !== '' ? preg_replace('/<\/(script|style)>/i', '<\\/$1>', $customCss) : '';
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="renderer" content="webkit">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php ?>
    <?php $avatarPreconnect = trim(lt_text($this->options->avatarProxy ?? '')); if ($avatarPreconnect !== ''): $apHost = parse_url($avatarPreconnect, PHP_URL_HOST); if ($apHost): ?>
    <link rel="preconnect" href="https://<?php echo lt_esc_attr($apHost); ?>" crossorigin>
    <?php endif; else: ?>
    <link rel="preconnect" href="https://secure.gravatar.com" crossorigin>
    <?php endif; ?>
    <?php if ($shortcutIcon !== '' && strlen($shortcutIcon) > 5): ?>
        <link rel="shortcut icon" href="<?php echo lt_esc_attr($shortcutIcon); ?>">
    <?php else: ?>
        <?php ?>
        <link rel="icon" type="image/svg+xml" href="<?php $this->options->themeUrl('assets/img/icon.svg'); ?>?v=<?php echo $ltAssetVersion; ?>">
    <?php endif; ?>
    <link rel="manifest" href="<?php $this->options->themeUrl('manifest.php'); ?>">
    <link rel="alternate" type="application/rss+xml" title="RSS 2.0" href="<?php echo lt_esc_attr($feedUrl); ?>"/>
    <?php if ($canonicalUrl !== ''): ?>
        <link rel="canonical" href="<?php echo lt_esc_attr($canonicalUrl); ?>"/>
    <?php endif; ?>
    <?php if ($isSearchPage): ?>
        <?php ?>
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
        <meta name="twitter:card" content="summary_large_image">
        <meta name="twitter:image" content="<?php echo lt_esc_attr($ogImage); ?>">
    <?php else: ?>
        <meta name="twitter:card" content="summary">
    <?php endif; ?>
    <meta name="twitter:title" content="<?php echo lt_esc_attr($pageTitleMeta); ?>">
    <?php if ($pageDesc !== ''): ?>
        <meta name="twitter:description" content="<?php echo lt_esc_attr($pageDesc); ?>">
    <?php endif; ?>
    
    <script type="application/ld+json">
    <?php if ($isSingle): ?>
    <?php
        $publisher = ['@type' => 'Organization', 'name' => $siteTitle];
        if ($logoUrl !== '') {
            $publisher['logo'] = ['@type' => 'ImageObject', 'url' => $logoUrl];
        }
        $ldJson = [
            '@context' => 'https://schema.org',
            '@type' => 'Article',
            'headline' => $pageTitleMeta,
            'mainEntityOfPage' => ['@type' => 'WebPage', '@id' => $canonicalUrl !== '' ? $canonicalUrl : $siteUrl],
            'datePublished' => date('c', (int) $this->created),
            
            'dateModified' => date('c', (int) ((($this->modified ?? 0) > 0) ? $this->modified : $this->created)),
            'author' => ['@type' => 'Person', 'name' => lt_text($this->author->name ?? $this->author->screenName ?? '')],
            'publisher' => $publisher
        ];
        if ($ogImage !== '') {
            $ldJson['image'] = [$ogImage];
        }
        if ($pageDesc !== '') {
            $ldJson['description'] = $pageDesc;
        }
        
        echo json_encode($ldJson, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_HEX_APOS);
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
                'target' => $searchLdTarget,
                'query-input' => 'required name=search_term_string'
            ]
            
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_HEX_APOS);
    ?>
    <?php endif; ?>
    </script>
    <?php if ($breadcrumb !== null): ?>
    <script type="application/ld+json">
    <?php
        $breadcrumbItems = [];
        $position = 1;
        foreach ($breadcrumb as $crumb) {
            if ($crumb['url'] === '' || $crumb['name'] === '') {
                continue;
            }
            $breadcrumbItems[] = [
                '@type' => 'ListItem',
                'position' => $position++,
                'name' => $crumb['name'],
                'item' => $crumb['url'],
            ];
        }
        echo json_encode([
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => $breadcrumbItems,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_HEX_APOS);
    ?>
    </script>
    <?php endif; ?>
    <link rel="stylesheet" type="text/css" media="all" href="<?php $this->options->themeUrl('assets/css/font.css'); ?>?v=<?php echo $ltAssetVersion; ?>"/>
    <link rel="stylesheet" type="text/css" media="all" href="<?php $this->options->themeUrl('assets/css/lantern.min.css'); ?>?v=<?php echo $ltAssetVersion; ?>"/>
    <script>
        window.LANTERTOWN_CONFIG = <?php echo json_encode($themeConfig, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_THROW_ON_ERROR); ?>;
    </script>
    <?php ?>
    <?php if ($needPrism): ?>
        <link rel="stylesheet" href="<?php $this->options->themeUrl('assets/vendor/prism/prism.min.css'); ?>?v=<?php echo $ltAssetVersion; ?>"/>
        <script type="text/javascript" defer src="<?php $this->options->themeUrl('assets/vendor/prism/prism.min.js'); ?>?v=<?php echo $ltAssetVersion; ?>"></script>
    <?php endif; ?>
    <?php if ($fieldKeywords !== '' || $fieldDesc !== '') : ?>
        <?php $this->header('keywords=' . rawurlencode($fieldKeywords) . '&description=' . rawurlencode($fieldDesc)); ?>
    <?php else : ?>
        <?php $this->header(); ?>
    <?php endif; ?>
    <title>
        <?php echo $pageTitle; ?>
    </title>
    <?php if ($customCssEscaped !== ''): ?>
        <style><?php echo $customCssEscaped; ?></style>
    <?php endif; ?>
    <?php ?>
</head>
<body id="blog_container" class="<?php echo $themeModeNum === 4 ? 'bg-auto' : 'bg' . $themeModeNum; ?>">
<script>
    
    (function () {
        var cfg = window.LANTERTOWN_CONFIG || {};
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
            <?php foreach ($navItems as $item): ?>
                <a class="navbar-item hover-line" href="<?php echo lt_esc_attr($item['permalink']); ?>"><?php echo lt_esc_html($item['title']); ?></a>
            <?php endforeach; ?>
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
    <?php ?>
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
