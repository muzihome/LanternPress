<?php
declare(strict_types=1);

if (!defined('__TYPECHO_ROOT_DIR__')) exit;













function lt_meta_build(object $widget, string $themeUrlFull): array
{
    $isIndexPage = $widget->is('index');
    $isSearchPage = $widget->is('search');
    $isSingle = $widget->is('post') || $widget->is('page');
    $isHiddenSingle = $isSingle && (bool) ($widget->hidden ?? false);
    $currentPage = (int) $widget->request->filter('int')->get('page', 1);
    
    
    $logoUrl = lt_text(\Utils\Helper::options()->logoUrl ?? '');
    $siteTitle = lt_text(\Utils\Helper::options()->title ?? '');
    $siteUrl = rtrim(lt_text(\Utils\Helper::options()->siteUrl), '/');
    
    
    
    $fieldDesc = ($isSingle && !$isHiddenSingle) ? lt_text($widget->fields->articleDesc ?? '') : '';

    
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
    $ogImageIsDefault = false; 
    if ($isSingle && !$isHiddenSingle) {
        
        $ogImage = lt_text($widget->fields->bannerUrl ?? '');
        if ($ogImage === '') {
            $ogImage = lt_content_image(lt_text($widget->content ?? ''));
        }
        if ($ogImage === '') {
            
            $ogImage = rtrim($themeUrlFull, '/') . '/assets/img/default-cover.webp';
            $ogImageIsDefault = true;
        } else {
            $ogImage = lt_safe_url($ogImage);
        }
        if (str_contains($ogImage, '/assets/img/default-cover')) {
            $ogImageIsDefault = true;
        }
    } elseif ($logoUrl !== '') {
        
        $ogImage = lt_safe_url($logoUrl);
    }

    if ($ogImage === '') {
        $ogImage = rtrim($themeUrlFull, '/') . '/assets/img/default-cover.webp';
        $ogImageIsDefault = true;
    }

    
    
    if ($ogImage !== '') {
        if (str_starts_with($ogImage, '/')) {
            $ogImage = rtrim($siteUrl, '/') . $ogImage;
        } elseif (!preg_match('#^https?://#i', $ogImage)) {
            $ogImage = rtrim($siteUrl, '/') . '/' . $ogImage;
        }
    }

    
    
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
                
                
                $imgDim = lt_cache_remember('ogdim', md5($imgLocal), 604800, static function () use ($imgLocal): array {
                    $dim = @getimagesize($imgLocal);
                    return (is_array($dim) && ($dim[0] ?? 0) > 0 && ($dim[1] ?? 0) > 0)
                        ? [(int) $dim[0], (int) $dim[1]]
                        : [0, 0];
                });
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

    
    $ltLdJson = '';
    $ltBreadcrumbJson = '';
    
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
