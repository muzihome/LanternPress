<?php
declare(strict_types=1);

if (!defined('__TYPECHO_ROOT_DIR__')) exit;

// ============================================================
// content.php — 正文处理管线：纯文本统计（字数/阅读时长）、内容缓存载荷编解码、
// 渲染后处理（锚点保护 / 图片懒加载与灯箱 / 代码块行号）
// 依赖：format.php（lt_text/lt_safe_url/lt_esc_attr）、cache.php（lt_content_cache_*）、
//      fields.php（lt_delete_field）
// ============================================================

/**
 * 正文纯文本统计：去标签/实体解码/空白折叠后计数，返回 text/chars/minutes。
 * post-meta 的「约 X 分钟读完 · 全文 N 字」与缓存载荷共用此实现。
 */
function lt_content_plain_stats(string $html): array
{
    $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $tmp = preg_replace('/[\s\x{00A0}]+/u', ' ', $text);
    if ($tmp === null) {
        // /u 正则遇非法 UTF-8 会返回 null，回退为字符级空白替换
        $tmp = str_replace(["\r\n", "\r", "\n", "\t"], ' ', $text);
        $tmp = str_replace("\xC2\xA0", ' ', $tmp);
    }
    $text = trim($tmp);
    $chars = mb_strlen($text, 'UTF-8');
    if ($chars === 0 && trim(strip_tags($html)) !== '') {
        // 兜底：正文非空而计数为 0 时，退回基础去标签计数，避免显示「全文 0 字」
        $text = trim(strip_tags($html));
        $chars = mb_strlen($text, 'UTF-8');
    }
    return ['text' => $text, 'chars' => $chars, 'minutes' => max(1, (int) ceil($chars / 400))];
}

/**
 * 内容缓存载荷编码：hash||chars||summary||content 四段（摘要内的 || 替换为全角防解析错位）。
 */
function lt_content_cache_payload(string $configHash, int $chars, string $plainText, string $content): string
{
    $summary = str_replace('||', '｜', \Typecho\Common::subStr($plainText, 0, 100, '...'));
    return $configHash . '||' . $chars . '||' . $summary . '||' . $content;
}

/**
 * 内容缓存读取与解析：命中返回 ['chars','summary','content']，未命中/哈希不符返回 null。
 * 旧格式（hash||content 两段）或 chars=0 时用缓存正文补算字数并升级为四段；
 * 检出密码保护占位内容（历史脏缓存）时连同 ltProcessedContent 字段一并清除。
 */
function lt_content_cache_load(int $cid, string $configHash): ?array
{
    $raw = lt_content_cache_get($cid);
    if ($raw === null || $raw === '' || strpos($raw, '||') === false) {
        return null;
    }
    // 缓存格式：hash||chars||summary||content（旧格式 hash||content 自动降级补算）。
    // 先分离 hash 段再判段数：旧格式正文自身含 '||' 时不会被误判为四段格式导致截断。
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
    // 旧格式缓存或 chars=0：用缓存正文补算字数并升级缓存为四段，避免显示「全文 0 字」
    $stats = lt_content_plain_stats($content);
    lt_content_cache_set($cid, lt_content_cache_payload($configHash, $stats['chars'], $stats['text'], $content));
    return ['chars' => $stats['chars'], 'summary' => $stats['text'], 'content' => $content];
}

/**
 * 正文渲染后处理管线（post.php 未命中缓存时执行一次，结果入内容缓存）：
 * 1) 暂存站内锚点 <a> 块，避免后续 img 正则误伤链接内嵌图；
 * 2) <img> 补 alt、首图 eager+fetchpriority、其余 lazy，外包 fancybox 灯箱链接；
 * 3) <pre> 补 line-numbers class（Prism 行号插件）。
 */
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

// ============================================================
// 文章分类面包屑层级链（根 -> 叶子），与 post.php 视觉面包屑一致（M2）
// 输入文章分类数组（$this->categories，含 mid/name/slug/parent），沿最深分类的 parent 链
// 构建多级面包屑（补查父分类，上限 4 轮批量查询、链上限 10 级防脏数据死循环）。
// meta.php JSON-LD BreadcrumbList 与本函数共用，保证结构化数据与实际导航一致。
// ============================================================
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
        // 沿 parent 链补查父分类（多轮批量查询，防脏数据死循环上限 4 轮）
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
        // 查询失败回退为仅用文章已带分类（不阻断页面渲染）
    }
    // 选择层级最深的主路径分类（同深度取文章顺序首个）
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
    // 沿主路径 parent 链构建层级（根 -> 叶子）
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
        // url 可能为空（分类无 slug 且 permalink 缺失）：渲染端自行决定链接或纯文本
        $chain[] = ['name' => $ltRow['name'], 'url' => $ltUrl];
    }
    return $chain;
}
