<?php
declare(strict_types=1);

if (!defined('__TYPECHO_ROOT_DIR__')) exit;

// ============================================================
// meta.php — head 元信息统一构建：页面标题 / canonical / description /
// OG 图（选择·绝对化·本地尺寸探测）/ feed·search 路由 / 面包屑 / JSON-LD / Prism 需求判定
// 依赖：format.php（lt_text/lt_safe_url/lt_esc_html/lt_content_image）
// ============================================================

/**
 * 页面元信息统一构建（header.php 专用）：逻辑集中在此，模板区仅保留 echo。
 * 返回关联数组：pageTitle（HTML 转义版 <title> 用）/ pageTitleMeta（原文版 meta·JSON-LD 用）/
 * canonicalUrl / pageDesc / needPrism / ogImage / ogImageIsDefault / ogImageW / ogImageH /
 * feedUrl / breadcrumb(null|array) / ltLdJson / ltBreadcrumbJson。
 */
function lt_meta_build(object $widget, string $themeUrlFull): array
{
    $isIndexPage = $widget->is('index');
    $isSearchPage = $widget->is('search');
    $isSingle = $widget->is('post') || $widget->is('page');
    $isHiddenSingle = $isSingle && (bool) ($widget->hidden ?? false);
    $currentPage = (int) $widget->request->filter('int')->get('page', 1);
    // 注意：options 在 Typecho 1.3 为 Widget 受保护属性，类外（本函数）经 $widget->options
    // 访问会触发 __get 返回 null（PHP 8 Warning），统一改用全局单例 \Utils\Helper::options()
    $logoUrl = lt_text(\Utils\Helper::options()->logoUrl ?? '');
    $siteTitle = lt_text(\Utils\Helper::options()->title ?? '');
    $siteUrl = rtrim(lt_text(\Utils\Helper::options()->siteUrl), '/');
    $fieldDesc = lt_text($widget->fields->articleDesc ?? '');

    // 页面标题：<title> 用 HTML 转义版，meta/JSON-LD 用原文版；两组共用同一拼接结构（消除重复逻辑）
    $pageTitleParts = [];
    $pageTitleMetaParts = [];
    if ($currentPage > 1) {
        $pageTitleParts[] = '第 ' . $currentPage . ' 页';
        $pageTitleMetaParts[] = '第 ' . $currentPage . ' 页';
    }
    ob_start();
    $widget->archiveTitle(
        [
            'category' => '分类 %s 下的文章',
            'search' => '包含关键词 %s 的文章',
            'tag' => '标签 %s 下的文章',
            'author' => '%s 发布的文章',
        ],
        '',
        ''
    );
    $archiveTitleText = trim((string) ob_get_clean());
    if ($archiveTitleText !== '') {
        $pageTitleParts[] = lt_esc_html($archiveTitleText);
        $pageTitleMetaParts[] = $archiveTitleText;
    }
    if ($siteTitle !== '') {
        $pageTitleParts[] = lt_esc_html($siteTitle);
        $pageTitleMetaParts[] = $siteTitle;
    }
    $pageTitle = implode(' - ', array_filter($pageTitleParts));
    $pageTitleMeta = implode(' - ', array_filter($pageTitleMetaParts));

    $canonicalUrl = '';
    if ($isSingle) {
        $canonicalUrl = lt_safe_url(lt_text($widget->permalink ?? ''));
    } elseif (!$isIndexPage && !$isSearchPage) {
        $canonicalUrl = $siteUrl . (string) $widget->request->getPathInfo();
    }
    if ($canonicalUrl === '') {
        $canonicalUrl = $siteUrl;
    }
    $pageDesc = $fieldDesc;
    if ($pageDesc === '' && $isSingle && !$isHiddenSingle) {
        $pageDesc = trim(lt_text($widget->content ?? ''));
        $pageDesc = \Typecho\Common::subStr(strip_tags($pageDesc), 0, 200, '...');
    }

    if ($isHiddenSingle) {
        // 密码保护文章：不向 meta description 输出正文摘要（正文区已由密码表单拦截，元信息同步置空）
        $pageDesc = '';
    } elseif ($pageDesc === '' && !$isSingle && !$isIndexPage && !$isSearchPage && $archiveTitleText !== '') {
        $archiveDescRaw = '';
        if (method_exists($widget, 'getArchiveDescription')) {
            try {
                $archiveDescRaw = trim((string) $widget->getArchiveDescription());
            } catch (\Throwable $e) {
                $archiveDescRaw = '';
            }
        }
        $pageDesc = $archiveDescRaw !== ''
            ? \Typecho\Common::subStr($archiveDescRaw, 0, 200, '...')
            : \Typecho\Common::subStr($archiveTitleText, 0, 200, '...');
    }

    if ($pageDesc === '' && $isIndexPage) {
        $pageDesc = \Typecho\Common::subStr(trim(lt_text(\Utils\Helper::options()->description ?? '')), 0, 200, '...');
    }

    $needPrism = false;
    if ($isSingle && !$isHiddenSingle) {
        $rawBody = lt_text($widget->content ?? '');
        $needPrism = $rawBody !== '' && (
            stripos($rawBody, '<pre') !== false
            || stripos($rawBody, '<code') !== false
            || strpos($rawBody, '```') !== false
            || strpos($rawBody, '~~~') !== false
        );
    }

    $ogImage = '';
    $ogImageIsDefault = false; // true = 站点默认图（blog_bg），供海报功能识别"文章无封面"
    if ($isSingle && !$isHiddenSingle) {
        // 文章主图（banner 字段）→ 正文首图 → 默认图；不走 indexThumbs 轮换（轮换图仅供列表缩略图，语义与具体文章不符）
        $ogImage = lt_text($widget->fields->bannerUrl ?? '');
        if ($ogImage === '') {
            $ogImage = lt_content_image(lt_text($widget->content ?? ''));
        }
        if ($ogImage === '') {
            $ogImage = rtrim($themeUrlFull, '/') . '/assets/img/blog_bg.jpg';
            $ogImageIsDefault = true;
        } else {
            $ogImage = lt_safe_url($ogImage);
        }
        if (str_contains($ogImage, '/assets/img/blog_bg.jpg')) {
            $ogImageIsDefault = true;
        }
    } elseif ($logoUrl !== '') {
        // 与文章图分支同一口径走协议白名单（非法协议返回空串，回落下方默认图）
        $ogImage = lt_safe_url($logoUrl);
    }

    if ($ogImage === '') {
        $ogImage = rtrim($themeUrlFull, '/') . '/assets/img/blog_bg.jpg';
        $ogImageIsDefault = true;
    }

    // og:image 绝对化：相对路径（/uploads/… 或 uploads/…）拼接站点域名，
    // 保证外站分享（微信/QQ/微博）与爬虫可解析完整图片 URL
    if ($ogImage !== '') {
        if (str_starts_with($ogImage, '/')) {
            $ogImage = rtrim($siteUrl, '/') . $ogImage;
        } elseif (!preg_match('#^https?://#i', $ogImage)) {
            $ogImage = rtrim($siteUrl, '/') . '/' . $ogImage;
        }
    }

    // og:image 尺寸（防御式）：仅当图片映射到本地文件且可读时输出 width/height，
    // 远程图床（如 CDN）无法低成本探测则跳过，不影响分享解析
    $ogImageW = 0;
    $ogImageH = 0;
    if ($ogImage !== '') {
        $sitePath = (string) parse_url($siteUrl, PHP_URL_PATH);
        $imgRel = $ogImage;
        if ($sitePath !== '' && str_starts_with($imgRel, $sitePath)) {
            $imgRel = substr($imgRel, strlen($sitePath));
        }
        if (str_starts_with($imgRel, '/') && defined('__TYPECHO_ROOT_DIR__')) {
            $imgLocal = rtrim((string) __TYPECHO_ROOT_DIR__, '/') . $imgRel;
            if (is_file($imgLocal) && is_readable($imgLocal)) {
                $imgDim = @getimagesize($imgLocal);
                if (is_array($imgDim) && ($imgDim[0] ?? 0) > 0 && ($imgDim[1] ?? 0) > 0) {
                    $ogImageW = (int) $imgDim[0];
                    $ogImageH = (int) $imgDim[1];
                }
            }
        }
    }

    $feedUrl = $siteUrl . '/feed/';
    try {
        $resolvedFeed = \Typecho\Router::url('feed', ['feed' => ''], \Utils\Helper::options()->index);
        if (is_string($resolvedFeed) && $resolvedFeed !== '' && strpos($resolvedFeed, '{') === false) {
            $feedUrl = $resolvedFeed;
        }
    } catch (\Throwable $e) {  }

    $searchLdTarget = $siteUrl . '/search/{search_term_string}/';
    try {
        $resolvedSearch = \Typecho\Router::url('search', ['keywords' => '{search_term_string}'], \Utils\Helper::options()->index);
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
            // 复用 post.php 视觉面包屑的多级分类层级链（根 -> 叶子），保证
            // JSON-LD BreadcrumbList 与实际导航一致（BreadcrumbList 条目要求有 URL，无 URL 项跳过）
            $postCategories = $widget->categories;
            foreach (lt_post_breadcrumb_chain($postCategories, (string) (\Utils\Helper::options()->index ?? $siteUrl . '/')) as $ltCrumb) {
                if ($ltCrumb['url'] !== '') {
                    $crumbs[] = ['name' => $ltCrumb['name'], 'url' => $ltCrumb['url']];
                }
            }
        } catch (\Throwable $e) {  }
        $crumbs[] = ['name' => lt_text($widget->title ?? ''), 'url' => $canonicalUrl !== '' ? $canonicalUrl : $siteUrl];
        $breadcrumb = $crumbs;
    }

    // JSON-LD 结构化数据：逻辑统一前置，HTML 输出区仅保留 echo，保证 view-source 干净
    $ltLdJson = '';
    $ltBreadcrumbJson = '';
    // 密码保护文章跳过 Article 类型（date/author 元信息不应对外泄露），与同文件其他安全判断保持一致
    if ($isSingle && !$isHiddenSingle) {
        $publisher = ['@type' => 'Organization', 'name' => $siteTitle];
        if ($logoUrl !== '') {
            $publisher['logo'] = ['@type' => 'ImageObject', 'url' => $logoUrl];
        }
        $ldJson = [
            '@context' => 'https://schema.org',
            '@type' => 'Article',
            'headline' => $pageTitleMeta,
            'mainEntityOfPage' => ['@type' => 'WebPage', '@id' => $canonicalUrl !== '' ? $canonicalUrl : $siteUrl],
            'datePublished' => date('c', (int) $widget->created),
            'dateModified' => date('c', (int) ((($widget->modified ?? 0) > 0) ? $widget->modified : $widget->created)),
            'author' => ['@type' => 'Person', 'name' => lt_text($widget->author->name ?? $widget->author->screenName ?? '')],
            'publisher' => $publisher
        ];
        if ($ogImage !== '') {
            $ldJson['image'] = [$ogImage];
        }
        if ($pageDesc !== '') {
            $ldJson['description'] = $pageDesc;
        }
        $ltLdJson = json_encode($ldJson, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_HEX_APOS);
    } else {
        $ltLdJson = json_encode([
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
    }
    if ($breadcrumb !== null) {
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
        $ltBreadcrumbJson = json_encode([
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => $breadcrumbItems,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_HEX_APOS);
    }

    return [
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
    ];
}
