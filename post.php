<?php
declare(strict_types=1);

if (!defined('__TYPECHO_ROOT_DIR__')) exit;

$title = lt_text($this->title ?? '');
$isPost = $this->is('post');
$rewardUrls = array_slice(
    array_values(
        array_filter(
            lt_lines($this->options->rewardUrl ?? ''),
            static fn(string $url): bool => lt_safe_url($url) !== ''
        )
    ),
    0,
    LT_MAX_REWARD_IMAGES
);
$socialRaw = lt_lines($this->options->socialLink ?? '');
$socialList = [];
foreach ($socialRaw as $line) {
    $parts = explode(':', $line, 3);
    if (count($parts) !== 3) {
        continue;
    }
    $name = trim($parts[0]);
    $type = strtolower(trim($parts[1]));
    $link = trim($parts[2]);
    if ($name === '' || $type === '' || $link === '') {
        continue;
    }
    if (count($socialList) >= LT_MAX_SOCIAL_LINKS) {
        break;
    }
    if ($type === 'url') {
        $safe = lt_safe_url($link);
        if ($safe === '') {
            continue;
        }
        $socialList[] = ['name' => $name, 'type' => 'url', 'link' => $safe];
    } elseif ($type === 'qr') {
        $safe = lt_safe_url($link);
        if ($safe === '') {
            continue;
        }
        $socialList[] = ['name' => $name, 'type' => 'qr', 'link' => $safe];
    }
}

$showWriterIntro = lt_bool($this->options->writerIntro ?? false);
$showCopyright = lt_bool($this->options->showCopyright ?? false);
$selfIntro = trim(lt_text($this->options->selfIntro ?? ''));
$authorName = lt_text($this->author->name ?? $this->author->screenName ?? '');
$authorMail = lt_text($this->author->mail ?? '');
$cid = (int) ($this->cid ?? 0);

$postLayout = lt_text($this->options->postLayout ?? 'right');
if (!in_array($postLayout, ['right', 'left', 'none'], true)) {
    $postLayout = 'right';
}
$layoutClass = $postLayout === 'none' ? 'no-sidebar' : 'sidebar-' . $postLayout;
$commentsNum = (int) ($this->commentsNum ?? 0);
$postAdSlot = trim(lt_text($this->options->postAdSlot ?? ''));

if ($isPost && $cid > 0 && !$this->hidden) {
    lt_increment_views($cid);
}
$viewsNum = lt_get_views($cid);

$likesNum = lt_get_likes($cid);
$hasLiked = lt_has_liked($cid);

$ltLikeToken = $this->widget('\Widget\Security')->getToken($this->request->getRequestUrl());

$readingMinutes = 1;
$wordCount = 0;
$_postPlainText = '';

// ===== 字数 / 阅读时长前置计算（必须在 post-meta 输出之前完成，否则显示恒为初始 1/0）=====
$ltCid = (int) $this->cid;
$ltConfigHash = lt_get_config_hash();
$ltCachedRaw = ($ltCid > 0 && $isPost && !$this->hidden) ? lt_content_cache_get($ltCid) : null;
$ltCacheHit = false;
$ltCachedContent = '';
$ltCachedChars = null;
$ltCachedSummary = '';
if ($ltCachedRaw !== null && $ltCachedRaw !== '' && strpos($ltCachedRaw, '||') !== false) {
    // 缓存格式：hash||chars||summary||content（旧格式 hash||content 自动降级补算）
    $ltCacheParts = explode('||', $ltCachedRaw, 4);
    $ltCachedHash = $ltCacheParts[0];
    if (count($ltCacheParts) === 4) {
        $ltCachedChars = (int) $ltCacheParts[1];
        $ltCachedSummary = $ltCacheParts[2];
        $ltCachedContent = $ltCacheParts[3];
    } else {
        $ltCachedContent = $ltCacheParts[1];
    }
    if (stripos($ltCachedContent, '此内容被密码保护') === 0) {
        lt_content_cache_delete($ltCid);
        lt_delete_field($ltCid, 'ltProcessedContent');
        $ltCachedRaw = null;
    } elseif ($ltCachedHash === $ltConfigHash) {
        $ltCacheHit = true;
        if ($ltCachedChars !== null && $ltCachedChars > 0) {
            $wordCount = $ltCachedChars;
            $readingMinutes = max(1, (int) ceil($ltCachedChars / 400));
            $_postPlainText = $ltCachedSummary;
        } else {
            // 旧格式缓存（hash||content 两段，chars 缺失）或 chars=0：
            // 用缓存正文补算字数并升级缓存为四段，避免显示「全文 0 字」
            $ltCachedText = html_entity_decode(strip_tags((string) $ltCachedContent), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $ltTmpText = preg_replace('/[\s\x{00A0}]+/u', ' ', $ltCachedText);
            if ($ltTmpText === null) {
                $ltTmpText = str_replace(["\r\n", "\r", "\n", "\t"], ' ', $ltCachedText);
                $ltTmpText = str_replace("\xC2\xA0", ' ', $ltTmpText);
            }
            $ltCachedText = trim($ltTmpText);
            $wordCount = mb_strlen($ltCachedText, 'UTF-8');
            if ($wordCount === 0 && trim((string) strip_tags((string) $ltCachedContent)) !== '') {
                $ltCachedText = trim((string) strip_tags((string) $ltCachedContent));
                $wordCount = mb_strlen($ltCachedText, 'UTF-8');
            }
            $readingMinutes = max(1, (int) ceil($wordCount / 400));
            $_postPlainText = $ltCachedText;
            lt_content_cache_set($ltCid, $ltConfigHash . '||' . $wordCount . '||' . \Typecho\Common::subStr($ltCachedText, 0, 100, '...') . '||' . $ltCachedContent);
        }
    }
}
if (!$ltCacheHit && $ltCid > 0 && $isPost && !$this->hidden) {
    // 未命中：直接以渲染后正文计算字数（content 属性经 Typecho ___content() 缓存，正文输出不重复渲染）
    $_rawPlain = html_entity_decode(strip_tags(lt_text($this->content ?? '')), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $_tmpPlain = preg_replace('/[\s\x{00A0}]+/u', ' ', $_rawPlain);
    if ($_tmpPlain === null) {
        // /u 正则遇非法 UTF-8 会返回 null，回退为字符级空白替换
        $_tmpPlain = str_replace(["\r\n", "\r", "\n", "\t"], ' ', $_rawPlain);
        $_tmpPlain = str_replace("\xC2\xA0", ' ', $_tmpPlain);
    }
    $_postPlainText = trim($_tmpPlain);
    $_charCount = mb_strlen($_postPlainText, 'UTF-8');
    if ($_charCount === 0 && trim((string) strip_tags(lt_text($this->content ?? ''))) !== '') {
        // 兜底：正文非空而计数为 0 时，退回基础去标签计数，避免显示「全文 0 字」
        $_postPlainText = trim((string) strip_tags(lt_text($this->content ?? '')));
        $_charCount = mb_strlen($_postPlainText, 'UTF-8');
    }
    $wordCount = $_charCount;
    $readingMinutes = max(1, (int) ceil($_charCount / 400));
}
?>
<?php $this->need('header.php'); ?>
<?php if ($isPost): ?>
<div id="reading-progress" aria-hidden="true"></div>
<?php endif; ?>
<div class="post">
    <div class="post-layout-wrapper <?php echo lt_esc_attr($layoutClass); ?>" id="postLayoutWrapper">
        <div class="post-container">
            <div class="post-breadcrumb">
                <a href="<?php $this->options->siteUrl(); ?>">
                    <i class="lt-icon icon-home breadcrumb-icon" aria-hidden="true"></i><?php _e('首页'); ?>
                </a>
<?php
                // 面包屑分类：无分类（独立页面/未分类文章）时不输出中间段，避免「首页 -> -> 标题」
                $breadCatHtml = '';
                if (is_array($this->categories ?? null) && !empty($this->categories)) {
                    $breadCatLinks = [];
                    foreach (array_slice($this->categories, 0, 2) as $breadCatRow) {
                        $breadCatUrl = lt_safe_url((string) ($breadCatRow['permalink'] ?? ''));
                        $breadCatName = trim(lt_text($breadCatRow['name'] ?? ''));
                        if ($breadCatUrl !== '' && $breadCatName !== '') {
                            $breadCatLinks[] = '<a href="' . lt_esc_attr($breadCatUrl) . '">' . lt_esc_html($breadCatName) . '</a>';
                        }
                    }
                    if (!empty($breadCatLinks)) {
                        $breadCatHtml = implode(' , ', $breadCatLinks);
                    }
                }
                ?>
<?php if ($breadCatHtml !== ''): ?>
                <span class="breadcrumb-sep">-&gt;</span>
<?php echo $breadCatHtml . "\n"; ?>
<?php endif; ?>
                <span class="breadcrumb-sep">-&gt;</span>
                <span class="breadcrumb-current"><?php $this->title(); ?></span>
            </div>
            <h1 class="post-title"><?php $this->title(); ?></h1>
            <div class="post-meta">
                <a href="<?php $this->author->permalink() ?>"><?php $this->author() ?></a>
                • <?php $this->date('Y年m月d日'); echo "\n"; ?>
<?php if ($isPost): ?>
                    • <?php $this->category(' , '); echo "\n"; ?>
<?php endif; ?>
<?php if ($this->user->hasLogin()): ?>
                    •
<?php if ($this->is('page')): ?>
                        <a href="<?php echo lt_esc_attr($this->options->adminUrl . 'write-page.php?cid=' . (int) $this->cid); ?>" target="_blank" rel="noopener noreferrer"><?php _e('编辑'); ?></a>
<?php else: ?>
                        <a href="<?php echo lt_esc_attr($this->options->adminUrl . 'write-post.php?cid=' . (int) $this->cid); ?>" target="_blank" rel="noopener noreferrer"><?php _e('编辑'); ?></a>
<?php endif; ?>
<?php endif; ?>
<?php if ($isPost && !$this->hidden): ?>
                    • <?php printf(_t('约 %d 分钟读完'), $readingMinutes); ?> · <?php printf(_t('全文 %d 字'), $wordCount); echo "\n"; ?>
<?php endif; ?>
            </div>

            <div id="post-content" class="post-content line-numbers">
<?php if ($isPost && $this->hidden): ?>
<?php
                    $pwdSecurity = $this->widget('\Widget\Security');
                    $pwdAction = $pwdSecurity->getTokenUrl($this->permalink);
                    ?>
                    <form class="post-password-form" action="<?php echo lt_esc_attr($pwdAction); ?>" method="post">
                        <p class="post-password-title"><?php _e('此内容被密码保护，请输入密码查看。'); ?></p>
                        <div class="post-password-row">
                            <input type="password" class="post-password-input" name="protectPassword" placeholder="<?php _e('输入密码'); ?>" required />
                            <input type="hidden" name="protectCID" value="<?php echo (int) $this->cid; ?>" />
                            <button type="submit" class="post-password-submit"><?php _e('提交'); ?></button>
                        </div>
                    </form>
<?php elseif ($isPost): ?>
<?php
                    if ($ltCacheHit) {
                        echo $ltCachedContent;
                    } else {
                        ob_start();
                        $this->content();
                        $content = (string) ob_get_clean();

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
                            if (preg_match('/\bsrc=(["\'])(.*?)\1/i', $tag, $sm)) {
                                $src = lt_safe_url((string) ($sm[2] ?? ''));
                            }
                            if ($src === '') {
                                return $tag;
                            }
                            $alt = '';
                            if (preg_match('/\balt=(["\'])(.*?)\1/i', $tag, $am)) {
                                $alt = trim((string) ($am[2] ?? ''));
                            }
                            if ($alt === '') {
                                $alt = $title;
                            }
                            $newImg = preg_replace_callback(
                                '/\bsrc=(["\'])(.*?)\1/i',
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
                            if (!preg_match('/\salt=/i', $newImg)) {
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

                    if ($ltCid > 0) {
                        // 写缓存：chars 与摘要由上方前置计算块产出（post-meta 输出前已算好），此处不再重复计算
                        lt_content_cache_set($ltCid, $ltConfigHash . '||' . $wordCount . '||' . \Typecho\Common::subStr($_postPlainText, 0, 100, '...') . '||' . $content);
                    }
                    echo $content;
                    }
                    ?>
<?php else: ?>
<?php $this->content(); ?>
<?php endif; ?>
            </div>

<?php if (!$this->hidden): ?>
<?php if ($isPost): ?>
                    <!-- 第 1 层：来源 / 版权声明框 -->
<?php if ($showCopyright): ?>
                    <div class="post-copyright">
<?php printf(_t('本文为 %s 原创文章，如若转载，请注明出处：'), lt_esc_html(lt_text($this->options->title ?? ''))); ?>
                        <a href="<?php echo lt_esc_attr($this->permalink); ?>"><?php echo lt_esc_html($this->permalink); ?></a>
                    </div>
<?php endif; ?>
                    <!-- 第 2 层：居中 赞 / 打赏 -->
                    <div class="post-actions">
                        <button type="button" class="action-btn action-like lt-like-btn <?php if ($hasLiked) echo 'liked'; ?>" id="likeBtn" data-cid="<?php echo $cid; ?>" data-token="<?php echo lt_esc_attr($ltLikeToken); ?>" <?php if ($hasLiked) echo 'disabled'; ?>>
                            <i class="lt-icon icon-upvote action-icon" aria-hidden="true"></i>
                            <span class="action-label"><?php _e('赞'); ?></span>
                            <span class="action-count"><span class="lt-like-count"><?php echo $likesNum; ?></span></span>
                        </button>
<?php if (!empty($rewardUrls)): ?>
                            <button type="button" class="action-btn action-reward" id="rewardBtn" data-reward="1">
                                <i class="lt-icon icon-reward action-icon" aria-hidden="true"></i>
                                <span class="action-label"><?php _e('打赏'); ?></span>
                            </button>
<?php endif; ?>
                    </div>
<?php endif; ?>

                <!-- 标签：位于工具栏分割线上方，左对齐 -->
                <div class="post-tags"><?php $this->tags('', true, ''); ?></div>

<?php if ($isPost): ?>
<?php
                    // 分享外链参数（O1）：摘要与分享图，供微博分享卡片使用
                    $shareSummary = \Typecho\Common::subStr($_postPlainText, 0, 100, '...');
                    $sharePic = lt_text($this->fields->bannerUrl ?? '');
                    if ($sharePic === '') {
                        $sharePic = lt_content_image(lt_text($this->content ?? ''));
                    }
                    if ($sharePic !== '') {
                        $siteUrlShare = rtrim(lt_text($this->options->siteUrl ?? ''), '/');
                        if (str_starts_with($sharePic, '/')) {
                            $sharePic = $siteUrlShare . $sharePic;
                        } elseif (!preg_match('#^https?://#i', $sharePic)) {
                            $sharePic = $siteUrlShare . '/' . $sharePic;
                        }
                    }
                    ?>
                    <!-- 第 3 层：底部分享 / 数据工具栏 -->
                    <div class="post-toolbar">
                        <div class="toolbar-left">
                            <!-- 生成海报 -->
                            <button type="button" class="tool-icon share-poster" id="sharePosterBtn" title="<?php echo lt_esc_attr(_t('生成海报')); ?>">
                                <i class="lt-icon icon-poster"></i><span><?php _e('生成海报'); ?></span>
                            </button>
                            <!-- 微信好友（hover 弹出扫码二维码） -->
                            <span class="share-wechat-wrap">
                                <button type="button" class="tool-icon share-wechat" title="<?php echo lt_esc_attr(_t('微信扫码分享')); ?>" aria-label="<?php echo lt_esc_attr(_t('微信扫码分享')); ?>" data-share-url="<?php echo lt_esc_attr($this->permalink); ?>">
                                    <i class="lt-icon icon-weixin"></i>
                                </button>
                                <span class="share-wx-wrap" role="tooltip">
                                    <span class="j-share-qrcode"></span>
                                    <span><?php _e('微信扫码分享'); ?></span>
                                </span>
                            </span>
                            <!-- 新浪微博 -->
                            <a class="tool-icon share-weibo" target="_blank" rel="noopener noreferrer" href="https://service.weibo.com/share/share.php?url=<?php echo rawurlencode($this->permalink); ?>&amp;title=<?php echo rawurlencode(lt_text($this->title)); ?>&amp;summary=<?php echo rawurlencode($shareSummary); ?><?php if ($sharePic !== ''): ?>&amp;pic=<?php echo rawurlencode($sharePic); ?><?php endif; ?>" title="<?php echo lt_esc_attr(_t('微博分享')); ?>">
                                <svg class="share-svg" viewBox="0 0 1024 1024" aria-hidden="true"><path d="M747.712 485.44c53.888 16.768 113.92 57.024 113.92 128.192 0 117.888-169.856 266.24-425.152 266.24-194.752 0-393.792-94.464-393.792-249.728 0-81.152 51.328-175.04 139.776-263.552C300.8 248.384 438.656 194.496 490.496 246.464c22.912 22.912 25.088 62.464 10.432 109.696-7.616 23.808 22.4 10.688 22.4 10.688 95.552-40 179.008-42.432 209.408 1.152 16.256 23.168 14.656 55.68-0.32 93.44-6.976 17.344 2.048 20.096 15.36 24zM437.12 824.192c155.328-15.424 273.088-110.592 263.104-212.608-10.112-102.016-144.32-172.288-299.648-156.864-155.328 15.36-273.216 110.464-263.104 212.48 10.112 102.144 144.256 172.288 299.648 156.992zM262.848 616c32.128-65.152 115.712-101.952 189.696-82.752 76.48 19.776 115.456 91.904 84.288 162.112-31.744 71.808-122.944 110.016-200.32 85.056-74.752-24.128-106.368-97.92-73.664-164.416z m61.696 110.4c24.192 10.944 56.512 0.576 71.488-23.488 14.72-24.192 6.976-51.904-17.344-62.208-24.064-10.112-55.104 0.32-70.016 23.552-15.104 23.36-8.064 51.328 15.872 62.144z m94.08-77.12c9.344 3.904 21.376-0.512 26.816-9.856 5.248-9.408 1.92-19.904-7.616-23.424-9.216-3.584-20.736 0.768-26.112 9.92-5.248 9.152-2.304 19.52 6.912 23.36z m429.312-243.968a30.912 30.912 0 1 1-58.816-19.2 58.88 58.88 0 0 0-68.672-76.032 30.912 30.912 0 0 1-12.928-60.416 120.96 120.96 0 0 1 140.416 155.648zM680.832 124.8a248.768 248.768 0 0 1 288.576 320 35.776 35.776 0 0 1-45.248 23.04 36.032 36.032 0 0 1-23.104-45.376 176.768 176.768 0 0 0-205.12-227.264 36.032 36.032 0 0 1-15.104-70.4z"/></svg>
                            </a>
                            <!-- QQ 好友 -->
                            <a class="tool-icon share-qq" target="_blank" rel="noopener noreferrer" href="https://connect.qq.com/widget/shareqq/index.html?url=<?php echo rawurlencode($this->permalink); ?>&amp;title=<?php echo rawurlencode(lt_text($this->title)); ?>&amp;summary=<?php echo rawurlencode($shareSummary); ?><?php if ($sharePic !== ''): ?>&amp;pics=<?php echo rawurlencode($sharePic); ?><?php endif; ?>" title="<?php echo lt_esc_attr(_t('QQ 分享')); ?>">
                                <svg class="share-svg" viewBox="0 0 1024 1024" aria-hidden="true"><path d="M849.6 619.904a1364.352 1364.352 0 0 0-28.8-80.448L782.08 443.52c0-1.088 0.512-19.968 0.512-29.696 0-163.84-78.208-328.576-270.528-328.576-192.32 0-270.528 164.736-270.528 328.576 0 9.728 0.512 28.608 0.512 29.696L203.2 539.52c-10.624 27.392-21.12 55.936-28.8 80.448-36.736 116.8-24.832 165.12-15.744 166.208 19.392 2.304 75.52-87.936 75.52-87.936 0 52.224 27.2 120.448 86.016 169.664-21.952 6.72-48.896 17.024-66.304 29.632-15.552 11.392-13.568 23.04-10.752 27.776 12.288 20.48 211.392 13.056 268.928 6.656 57.472 6.4 256.576 13.824 268.864-6.72 2.816-4.672 4.8-16.32-10.816-27.712-17.344-12.608-44.288-22.912-66.304-29.696 58.816-49.152 86.016-117.376 86.016-169.6 0 0 56.128 90.24 75.52 87.872 9.088-1.088 20.928-49.344-15.744-166.144z"/></svg>
                            </a>
                            <!-- QQ 空间 -->
                            <a class="tool-icon share-qzone" target="_blank" rel="noopener noreferrer" href="https://sns.qzone.qq.com/cgi-bin/qzshare/cgi_qzshare_onekey?url=<?php echo rawurlencode($this->permalink); ?>&amp;title=<?php echo rawurlencode(lt_text($this->title)); ?>&amp;summary=<?php echo rawurlencode($shareSummary); ?><?php if ($sharePic !== ''): ?>&amp;pic=<?php echo rawurlencode($sharePic); ?><?php endif; ?>" title="<?php echo lt_esc_attr(_t('QQ 空间分享')); ?>">
                                <svg class="share-svg" viewBox="0 0 1024 1024" aria-hidden="true"><path d="M312.26 658.78666667c22.08-24.6 305.52-182.4 305.52-182.4l-370.14-49.2 495-5.82s17.64 14.46 0 34.8c-17.58 20.22-280.5 198.3-280.5 198.3l298.92 23.64-9.06-52.32 240-230.58-331.68-47.46L512 51.46666667 363.68 347.74666667 32 395.20666667l240 230.58L215.36 951.46666667 512 797.74666667 808.64 951.46666667l-46.5-267.36-449.88 19.62s-22.02-20.28 0-44.94z m439.74-10.8l1.26 6.96 58.74-2.46-60-4.5z"/></svg>
                            </a>
                            <!-- 原生分享（系统分享面板，不支持时前端自动隐藏） -->
                            <button type="button" class="tool-icon share-native" title="<?php echo lt_esc_attr(_t('分享')); ?>" aria-label="<?php echo lt_esc_attr(_t('分享')); ?>" data-share-url="<?php echo lt_esc_attr($this->permalink); ?>" data-share-title="<?php echo lt_esc_attr(lt_text($this->title)); ?>">
                                <i class="share-native-icon" aria-hidden="true"></i>
                            </button>
                            <!-- 复制链接 -->
                            <button type="button" class="tool-icon share-copy" data-url="<?php echo lt_esc_attr($this->permalink); ?>" title="<?php echo lt_esc_attr(_t('复制链接')); ?>" aria-label="<?php echo lt_esc_attr(_t('复制链接')); ?>">
                                <i class="lt-icon icon-copylink"></i>
                            </button>
                        </div>
                        <div class="toolbar-right">
                            <span class="tool-stat" title="<?php echo lt_esc_attr(_t('阅读量')); ?>">
                                <i class="lt-icon icon-views"></i>
                                <span><?php echo $viewsNum; ?></span>
                            </span>
                            <span class="tool-stat" title="<?php echo lt_esc_attr(_t('评论数')); ?>">
                                <i class="lt-icon icon-comments"></i>
                                <span><?php echo $commentsNum; ?></span>
                            </span>
                            <button type="button" class="tool-stat share-fav" title="<?php echo lt_esc_attr(_t('添加到浏览器书签')); ?>" aria-label="<?php echo lt_esc_attr(_t('添加到浏览器书签')); ?>">
                                <i class="lt-icon icon-favorite"></i>
                                <span class="fav-label"><?php _e('收藏'); ?></span>
                            </button>
                        </div>
                    </div>
<?php endif; ?>

<?php if ($postAdSlot !== ''): ?>
                    <div class="post-ad-slot">
<?php echo $postAdSlot; ?>
                    </div>
<?php endif; ?>

<?php
                    $prevLink = lt_prev_next($this, $this->options, 'prev');
                    $nextLink = lt_prev_next($this, $this->options, 'next');
                    ?>
<?php if ($prevLink !== '' || $nextLink !== ''): ?>
                        <div class="post-nav-cards">
<?php echo $prevLink . "\n"; ?>
<?php echo $nextLink . "\n"; ?>
                        </div>
<?php endif; ?>

<?php
                    // 相关文章：优先标签相关（related），无结果时 fallback 同分类最新文章
                    $relatedRows = [];
                    try {
                        $this->related(3)->to($relatedPosts);
                        if (!empty($relatedPosts) && $relatedPosts->have()) {
                            $relatedRows = $relatedPosts->stack;
                        }
                    } catch (\Throwable $e) {
                        $relatedPosts = null;
                    }
                    if (empty($relatedRows) && $isPost) {
                        try {
                            $ltDb = \Typecho\Db::get();
                            $ltRelMid = 0;
                            if (is_array($this->categories ?? null)) {
                                foreach ($this->categories as $ltRelCat) {
                                    $ltRelMid = (int) ($ltRelCat['mid'] ?? 0);
                                    if ($ltRelMid > 0) {
                                        break;
                                    }
                                }
                            }
                            if ($ltRelMid > 0) {
                                $relatedRows = $ltDb->fetchAll(
                                    $ltDb->select('table.contents.cid', 'table.contents.type', 'table.contents.title', 'table.contents.slug', 'table.contents.created')
                                        ->from('table.contents')
                                        ->join('table.relationships', 'table.contents.cid = table.relationships.cid', \Typecho\Db::JOIN_INNER)
                                        ->where('table.relationships.mid = ?', $ltRelMid)
                                        ->where('table.contents.type = ?', 'post')
                                        ->where('table.contents.status = ?', 'publish')
                                        ->where('table.contents.cid <> ?', $cid)
                                        ->limit(3)
                                        ->order('table.contents.created', \Typecho\Db::SORT_DESC)
                                );
                            }
                        } catch (\Throwable $e) {
                            $relatedRows = [];
                        }
                    }
                    $relatedRows = array_map(function (array $ltRow): array {
                        $ltRow = $this->filter($ltRow);
                        $ltRow['permalink'] = \Typecho\Router::url('post', $ltRow, $this->options->index);
                        return $ltRow;
                    }, (array) $relatedRows);
                    ?>
<?php if (!empty($relatedRows)): ?>
                        <h2><?php _e('相关文章'); ?></h2>
                        <div class="related-posts">
<?php foreach ($relatedRows as $ltRelatedRow): ?>
                                <a href="<?php echo lt_esc_attr(lt_safe_url((string) ($ltRelatedRow['permalink'] ?? ''))); ?>"><?php echo lt_esc_html(lt_text($ltRelatedRow['title'] ?? '')); ?></a>
<?php endforeach; ?>
                        </div>
<?php endif; ?>
<?php
                    // 相关推荐：读取后台「首页推荐阅读」(cIdRecommend) 配置的文章，无内容不显示
                    $ltRecommendRows = [];
                    if ($isPost) {
                        try {
                            $ltRecommendRaw = lt_filter_cid_recommend(lt_text($this->options->cIdRecommend ?? ''));
                            $ltRecommendCids = array_values(array_unique(array_filter(
                                array_map('trim', explode('||', $ltRecommendRaw)),
                                // 排除当前文章（不推荐自己）
                                static fn(string $v): bool => $v !== '' && ctype_digit($v) && (int) $v !== $cid
                            )));
                            if (!empty($ltRecommendCids)) {
                                $ltRecommendCids = array_map('intval', $ltRecommendCids);
                                $ltDb = \Typecho\Db::get();
                                $ltRecommendRows = $ltDb->fetchAll(
                                    $ltDb->select('cid', 'type', 'title', 'slug', 'created')
                                        ->from('table.contents')
                                        ->where('cid IN ? AND type = ? AND status = ?', $ltRecommendCids, 'post', 'publish')
                                );
                                $ltOrderMap = array_flip($ltRecommendCids);
                                usort($ltRecommendRows, static fn(array $a, array $b): int => ($ltOrderMap[(int) ($a['cid'] ?? 0)] ?? 999) <=> ($ltOrderMap[(int) ($b['cid'] ?? 0)] ?? 999));
                                foreach ($ltRecommendRows as &$ltRecommendRow) {
                                    $ltRecommendRow = $this->filter($ltRecommendRow);
                                    $ltRecommendRow['permalink'] = \Typecho\Router::url('post', $ltRecommendRow, $this->options->index);
                                }
                                unset($ltRecommendRow);
                            }
                        } catch (\Throwable $e) {
                            $ltRecommendRows = [];
                        }
                    }
                    ?>
<?php if (!empty($ltRecommendRows)): ?>
                        <h2><?php _e('相关推荐'); ?></h2>
                        <div class="recommend-posts">
<?php foreach ($ltRecommendRows as $ltRecommendRow): ?>
                                <a href="<?php echo lt_esc_attr($ltRecommendRow['permalink']); ?>"><?php echo lt_esc_html($ltRecommendRow['title']); ?></a>
<?php endforeach; ?>
                        </div>
<?php endif; ?>
<?php endif; ?>
            <div><?php $this->need('inc/comments.php'); ?></div>
        </div>

<?php if ($postLayout !== 'none'): ?>
        <aside class="sidebar post-sidebar" id="postSidebar">
<?php $this->need('sidebar.php'); ?>
        </aside>
<?php endif; ?>
    </div>
</div>

<div id="background-layer" data-reward="<?php echo lt_esc_attr(json_encode($rewardUrls, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)); ?>">
    <div class="popup-container" id="social-pop" role="dialog" aria-modal="true" aria-label="弹窗内容">
        <div class="popup-header"><i class="lt-icon icon-close" id="social-pop-close" role="button" aria-label="关闭" tabindex="0"></i></div>
        <div class="popup-body" id="popup-body"></div>
    </div>
</div>

<?php $this->need('footer.php'); ?>
