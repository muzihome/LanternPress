<?php
declare(strict_types=1);



ob_start();








if (!defined('__TYPECHO_ROOT_DIR__')) {
    $ltRoot = realpath(__DIR__ . '/../../../');
    if ($ltRoot !== false && is_file($ltRoot . '/config.inc.php')) {
        define('__TYPECHO_ROOT_DIR__', $ltRoot);
    }
}

$ltManifest = [
    'name' => 'LanternPress',
    'short_name' => 'LanternPress',
    'lang' => 'zh-CN',
    'start_url' => '/',
    'scope' => '/',
    'display' => 'standalone',
    'background_color' => '#f9f7f1',
    'theme_color' => '#cc493d',
    'icons' => [
        [
            'src' => 'assets/img/icon.svg',
            'sizes' => 'any',
            'type' => 'image/svg+xml',
            'purpose' => 'any',
        ],
    ],
];

try {
    if (defined('__TYPECHO_ROOT_DIR__')) {
        $ltConfig = __TYPECHO_ROOT_DIR__ . '/config.inc.php';
        if (is_file($ltConfig)) {
            require $ltConfig;
        }
    }
    if (!class_exists('\Typecho\Common')) {
        $ltCommon = __TYPECHO_ROOT_DIR__ . '/var/Typecho/Common.php';
        if (defined('__TYPECHO_ROOT_DIR__') && is_file($ltCommon)) {
            require_once $ltCommon;
        }
    }
    if (class_exists('\Typecho\Common')) {
        \Typecho\Common::init();
    }
    if (class_exists('\Typecho\Widget')) {
        $ltOptions = \Typecho\Widget::widget('Widget_Options');
        $ltTitle = trim((string) $ltOptions->title);
        if ($ltTitle !== '') {
            $ltManifest['name'] = $ltTitle;
            $ltManifest['short_name'] = mb_substr($ltTitle, 0, 12, 'UTF-8');
        }
        $ltSiteUrl = rtrim((string) $ltOptions->siteUrl, '/');
        if ($ltSiteUrl !== '') {
            $ltManifest['start_url'] = $ltSiteUrl . '/';
            $ltManifest['scope'] = $ltSiteUrl . '/';
        }
        
        
        
        $ltIconUrl = rtrim((string) $ltOptions->themeUrl, '/') . '/assets/img/icon.svg';
        if ($ltIconUrl !== '') {
            $ltManifest['icons'][0]['src'] = $ltIconUrl;
        }
    }
} catch (\Throwable $e) {
    
}


header('Content-Type: application/manifest+json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: public, max-age=3600');


while (ob_get_level() > 0) {
    ob_end_clean();
}

echo json_encode(
    $ltManifest,
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_HEX_APOS
);
