<?php
declare(strict_types=1);

if (!defined('__TYPECHO_ROOT_DIR__')) exit;












function lt_content_plain_stats(string $html): array
{
    $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $tmp = preg_replace('/[\s\x{00A0}]+/u', ' ', $text);
    if ($tmp === null) {
        
        $tmp = str_replace(["\r\n", "\r", "\n", "\t"], ' ', $text);
        $tmp = str_replace("\xC2\xA0", ' ', $tmp);
    }
    $text = trim($tmp);
    $chars = mb_strlen($text, 'UTF-8');
    if ($chars === 0 && trim(strip_tags($html)) !== '') {
        
        $text = trim(strip_tags($html));
        $chars = mb_strlen($text, 'UTF-8');
    }
    return ['text' => $text, 'chars' => $chars, 'minutes' => max(1, (int) ceil($chars / 400))];
}




function lt_content_cache_payload(string $configHash, int $chars, string $plainText, string $content): string
{
    $summary = str_replace('||', '｜', \Typecho\Common::subStr($plainText, 0, 100, '...'));
    return $configHash . '||' . $chars . '||' . $summary . '||' . $content;
}






function lt_content_cache_load(int $cid, string $configHash): ?array
{
    $raw = lt_content_cache_get($cid);
    if ($raw === null || $raw === '' || strpos($raw, '||') === false) {
        return null;
    }
    
    
    [$cachedHash, $rest] = explode('||', $raw, 2);
    $chars = null;
    $summary = '';
    $content = $rest;
    $segments = explode('||', $rest, 3);
    if (count($segments) === 3 && preg_match('/^\d+$/', $segments[0]) === 1) {
        $chars = (int) $segments[0];
        $summary = $segments[1];
        $content = $segments[2];
    }
    if (stripos($content, '此内容被密码保护') === 0) {
        lt_content_cache_delete($cid);
        lt_delete_field($cid, 'ltProcessedContent');
        return null;
    }
    if ($cachedHash !== $configHash) {
        return null;
    }
    if ($chars !== null && $chars > 0) {
        return ['chars' => $chars, 'summary' => $summary, 'content' => $content];
    }
    
    $stats = lt_content_plain_stats($content);
    lt_content_cache_set($cid, lt_content_cache_payload($configHash, $stats['chars'], $stats['text'], $content));
    return ['chars' => $stats['chars'], 'summary' => $stats['text'], 'content' => $content];
}







function lt_content_render_pipeline(string $content, string $title): string
{
    $ltImgIndex = 0;

    $ltAnchors = [];
    $_tmp = preg_replace_callback(
        '/<a\b[^>]*>.*?<\/a>/is',
        function (array $matches) use (&$ltAnchors): string {
            $key = '@@LT_ANCHOR_' . count($ltAnchors) . '@@';
            $ltAnchors[$key] = $matches[0];
            return $key;
        },
        $content
    );
    $content = $_tmp !== null ? $_tmp : $content;

    $_tmp = preg_replace_callback(
        '/<img\b[^>]*>/i',
        function (array $matches) use ($title, &$ltImgIndex): string {
            $tag = $matches[0];
            $src = '';
            if (preg_match('/(?<![-\w])src=(["\'])(.*?)\1/i', $tag, $sm)) {
                $src = lt_safe_url((string) ($sm[2] ?? ''));
            }
            if ($src === '') {
                return $tag;
            }
            $alt = '';
            if (preg_match('/(?<![-\w])alt=(["\'])(.*?)\1/i', $tag, $am)) {
                $alt = trim((string) ($am[2] ?? ''));
            }
            if ($alt === '') {
                $alt = $title;
            }
            $newImg = preg_replace_callback(
                '/(?<![-\w])src=(["\'])(.*?)\1/i',
                function (array $m) use ($src): string {
                    return 'src="' . lt_esc_attr($src) . '"';
                },
                $tag
            );

            $insertAttr = static function (string $tag, string $attr): string {
                if (preg_match('/\/>\s*$/', $tag)) {
                    return preg_replace('/\/>\s*$/', ' ' . $attr . ' />', $tag);
                }
                if (preg_match('/>\s*$/', $tag)) {
                    return preg_replace('/>\s*$/', ' ' . $attr . '>', $tag);
                }
                return $tag . ' ' . $attr;
            };
            if (!preg_match('/(?<![-\w])alt=/i', $newImg)) {
                $newImg = $insertAttr($newImg, 'alt="' . lt_esc_attr($alt) . '"');
            }

            if (!preg_match('/\sloading=/i', $newImg)) {
                if ($ltImgIndex === 0) {
                    $newImg = $insertAttr($newImg, 'loading="eager" fetchpriority="high"');
                } else {
                    $newImg = $insertAttr($newImg, 'loading="lazy"');
                }
            }
            $ltImgIndex++;
            return '<a href="' . lt_esc_attr($src) . '" class="fancybox" data-fancybox="gallery">' . $newImg . '</a>';
        },
        $content
    );
    $content = $_tmp !== null ? $_tmp : $content;

    $content = strtr($content, $ltAnchors);

    $_tmp = preg_replace_callback(
        '/<pre\b([^>]*)>/i',
        function (array $matches): string {
            $attrs = $matches[1];
            if (preg_match('/\bclass=["\'][^"\']*line-numbers/i', $attrs)) {
                return $matches[0];
            }
            if (preg_match('/\bclass=["\']([^"\']*)["\']/i', $attrs, $cm)) {
                $attrs = str_replace($cm[0], 'class="' . trim($cm[1]) . ' line-numbers"', $attrs);
            } else {
                $attrs .= ' class="line-numbers"';
            }
            return '<pre' . $attrs . '>';
        },
        $content
    );
    $content = $_tmp !== null ? $_tmp : $content;

    return $content;
}







function lt_post_breadcrumb_chain(?array $categories, string $index): array
{
    $chain = [];
    if (!is_array($categories) || $categories === []) {
        return $chain;
    }
    $ltCatMap = [];
    foreach ($categories as $ltCatRow) {
        $ltMid = (int) ($ltCatRow['mid'] ?? 0);
        if ($ltMid <= 0) {
            continue;
        }
        $ltCatMap[$ltMid] = [
            'mid'       => $ltMid,
            'name'      => trim(lt_text($ltCatRow['name'] ?? '')),
            'slug'      => trim((string) ($ltCatRow['slug'] ?? '')),
            'permalink' => lt_safe_url(trim((string) ($ltCatRow['permalink'] ?? ''))),
            'parent'    => (int) ($ltCatRow['parent'] ?? 0),
        ];
    }
    if ($ltCatMap === []) {
        return $chain;
    }
    try {
        $ltDb = \Typecho\Db::get();
        
        $ltNeed = [];
        foreach ($ltCatMap as $ltRow) {
            if ($ltRow['parent'] > 0) {
                $ltNeed[$ltRow['parent']] = true;
            }
        }
        $ltGuard = 0;
        while (!empty($ltNeed) && $ltGuard++ < 4) {
            $ltIds = array_keys($ltNeed);
            $ltNeed = [];
            $ltRows = $ltDb->fetchAll($ltDb->select('mid', 'name', 'slug', 'parent')
                ->from('table.metas')
                ->where('type = ?', 'category')
                ->where('mid IN ?', $ltIds));
            foreach ($ltRows as $ltR) {
                $ltRmid = (int) $ltR['mid'];
                $ltCatMap[$ltRmid] = [
                    'mid'       => $ltRmid,
                    'name'      => trim(lt_text($ltR['name'] ?? '')),
                    'slug'      => trim((string) ($ltR['slug'] ?? '')),
                    'permalink' => '',
                    'parent'    => (int) $ltR['parent'],
                ];
                if ((int) $ltR['parent'] > 0 && !isset($ltCatMap[(int) $ltR['parent']])) {
                    $ltNeed[(int) $ltR['parent']] = true;
                }
            }
        }
    } catch (\Throwable $e) {
        
    }
    
    $ltBest = null;
    $ltBestDepth = -1;
    foreach ($ltCatMap as $ltRow) {
        $ltDepth = 0;
        $ltP = $ltRow['parent'];
        $ltVisited = [];
        while ($ltP > 0 && !isset($ltVisited[$ltP]) && $ltDepth < 10) {
            $ltVisited[$ltP] = true;
            if (isset($ltCatMap[$ltP])) {
                $ltDepth++;
                $ltP = $ltCatMap[$ltP]['parent'];
            } else {
                break;
            }
        }
        if ($ltDepth > $ltBestDepth) {
            $ltBestDepth = $ltDepth;
            $ltBest = $ltRow['mid'];
        }
    }
    
    $ltChainRows = [];
    if ($ltBest !== null) {
        $ltCur = $ltBest;
        $ltVisited = [];
        $ltGuard2 = 0;
        while ($ltCur > 0 && !isset($ltVisited[$ltCur]) && $ltGuard2++ < 10) {
            $ltVisited[$ltCur] = true;
            if (!isset($ltCatMap[$ltCur])) {
                break;
            }
            $ltChainRows[] = $ltCatMap[$ltCur];
            $ltCur = $ltCatMap[$ltCur]['parent'];
        }
        $ltChainRows = array_reverse($ltChainRows);
    }
    foreach ($ltChainRows as $ltRow) {
        if ($ltRow['name'] === '') {
            continue;
        }
        $ltUrl = $ltRow['permalink'];
        if ($ltUrl === '' && $ltRow['slug'] !== '') {
            try {
                $ltUrl = (string) \Typecho\Router::url('category', ['slug' => $ltRow['slug']], $index);
            } catch (\Throwable $e) {
                $ltUrl = '';
            }
            $ltUrl = lt_safe_url($ltUrl);
        }
        
        $chain[] = ['name' => $ltRow['name'], 'url' => $ltUrl];
    }
    return $chain;
}
