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
$directoryStatus = lt_text($this->fields->directoryStatus ?? 'off');
$authorName = lt_text($this->author->name ?? $this->author->screenName ?? '');
$authorMail = lt_text($this->author->mail ?? '');
$cid = (int) ($this->cid ?? 0);



if ($isPost && $cid > 0 && !$this->hidden) {
    lt_increment_views($cid);
}
$viewsNum = lt_get_views($cid);

$likesNum = lt_get_likes($cid);
$hasLiked = lt_has_liked($cid);

$ltLikeToken = $this->widget('\Widget\Security')->getToken($this->request->getRequestUrl());


$readingMinutes = 1;
$wordCount = 0;
if ($isPost && !$this->hidden) {
    $_postText = strip_tags(lt_text($this->content ?? ''));
    $_charCount = mb_strlen($_postText, 'UTF-8');
    $wordCount = $_charCount;
    $readingMinutes = max(1, (int) ceil($_charCount / 400));
}
?>
<?php $this->need('partials/header.php'); ?>
<?php if ($isPost): ?>
<div id="reading-progress" aria-hidden="true"></div>
<?php endif; ?>
<div class="post">
    <div class="post-container">
        <h1 class="post-title"><?php $this->title(); ?></h1>
        <div class="post-meta">
            <a href="<?php $this->author->permalink() ?>"><?php $this->author() ?></a>
            • <?php $this->date('Y年m月d日') ?>
            <?php if ($isPost): ?>
                • <?php $this->category(' , '); ?>
            <?php endif; ?>
            <?php if ($this->user->hasLogin()): ?>
                •
                <?php if ($this->is('page')): ?>
                    <a href="<?php echo lt_esc_attr($this->options->adminUrl . 'write-page.php?cid=' . (int) $this->cid); ?>" target="_blank" rel="noopener noreferrer"><?php _e('编辑'); ?></a>
                <?php else: ?>
                    <a href="<?php echo lt_esc_attr($this->options->adminUrl . 'write-post.php?cid=' . (int) $this->cid); ?>" target="_blank" rel="noopener noreferrer"><?php _e('编辑'); ?></a>
                <?php endif; ?>
            <?php endif; ?>
            <?php ?>
            <?php if ($isPost && $viewsNum >= LT_MIN_VIEWS_DISPLAY): ?>
                • <?php _e('阅读'); ?>: <?php echo $viewsNum; ?>
            <?php endif; ?>
            <?php if ($isPost): ?>
                • <?php printf(_t('约 %d 分钟读完'), $readingMinutes); ?> · <?php printf(_t('全文 %d 字'), $wordCount); ?>
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
                
                
                $ltCid = (int) $this->cid;
                $ltConfigHash = lt_get_config_hash();
                $ltCachedRaw = $ltCid > 0 ? lt_content_cache_get($ltCid) : null;
                $ltCacheHit = false;
                if ($ltCachedRaw !== null && $ltCachedRaw !== '' && strpos($ltCachedRaw, '||') !== false) {
                    list($ltCachedHash, $ltCachedContent) = explode('||', $ltCachedRaw, 2);
                    
                    if (stripos($ltCachedContent, '此内容被密码保护') === 0) {
                        lt_content_cache_delete($ltCid);
                        
                        lt_delete_field($ltCid, 'ltProcessedContent');
                        $ltCachedRaw = null;
                    } elseif ($ltCachedHash === $ltConfigHash) {
                        echo $ltCachedContent;
                        $ltCacheHit = true;
                    }
                }
                if (!$ltCacheHit) {
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
                    lt_content_cache_set($ltCid, $ltConfigHash . '||' . $content);
                }
                echo $content;
                }
                ?>
            <?php else: ?>
                <?php $this->content(); ?>
            <?php endif; ?>
        </div>

        <?php if (!$this->hidden): ?>
            <div class="post-tags"><?php $this->tags('', true, ''); ?></div>
        <?php endif; ?>

        <?php if ($isPost): ?>
            <?php if ($showWriterIntro): ?>
                <div class="article-writer">
                    <img src="<?php echo lt_parse_avatar($this->author->mail) ?>" alt="<?php echo lt_esc_attr($authorName); ?>">
                    <div class="right">
                        <div class="intro">
                            <span class="name"><a href="<?php $this->author->permalink() ?>"><?php $this->author() ?></a></span>
                            <span class="sign">
                                <?php if ($selfIntro !== ''): ?>
                                    <?php echo lt_esc_html($selfIntro); ?>
                                <?php else: ?>
                                    这个人很懒，什么也没有留下
                                <?php endif; ?>
                            </span>
                        </div>
                        <div class="social-link">
                            <a href="#lt-social" role="button" class="iconfont author-email" data-email="<?php echo lt_esc_attr(base64_encode($authorMail)); ?>" title="发邮件"><?php echo lt_icon('mail'); ?></a>
                            <?php foreach ($socialList as $item): ?>
                                <?php if ($item['type'] === 'qr'): ?>
                                    <a href="#lt-social" role="button" class="iconfont" data-qr="<?php echo lt_esc_attr($item['link']); ?>" data-title="<?php echo lt_esc_attr($item['name']); ?>"><?php echo lt_icon($item['name']); ?></a>
                                <?php else: ?>
                                    <a href="<?php echo lt_esc_attr($item['link']); ?>" target="_blank" rel="noopener noreferrer" class="iconfont"><?php echo lt_icon($item['name']); ?></a>
                                <?php endif; ?>
                            <?php endforeach; ?>
                            <?php if (!empty($rewardUrls)): ?>
                                <a href="#lt-social" role="button" data-reward="1" class="iconfont"><?php echo lt_icon('reward'); ?></a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($showCopyright): ?>
                <div class="post-copyright">
                    <div><div>版权属于: </div><div><a href="<?php $this->options->siteUrl(); ?>"><?php $this->options->title(); ?></a>的博客</div></div>
                    <div><div>本文链接: </div><div><?php $this->permalink(); ?></div></div>
                    <div><div>作品采用: </div><div>本作品采用<a href="https://creativecommons.org/licenses/by-nc-sa/4.0/deed.zh" target="_blank" rel="noopener noreferrer">知识共享署名-非商业性使用-相同方式共享 4.0 国际许可协议</a>进行许可</div></div>
                </div>
            <?php endif; ?>

            <?php ?>
            <?php
            $prevLink = lt_prev_next($this, $this->options, 'prev');
            $nextLink = lt_prev_next($this, $this->options, 'next');
            ?>
            <?php if ($prevLink !== '' || $nextLink !== ''): ?>
                <h2>推荐阅读</h2>
                <div class="post-prev-next">
                    <?php echo $prevLink; ?>
                    <?php echo $nextLink; ?>
                </div>
            <?php endif; ?>

            <?php ?>
            <?php
            try {
                $this->related(3)->to($relatedPosts);
            } catch (\Throwable $e) {
                $relatedPosts = null;
            }
            ?>
            <?php if (!empty($relatedPosts) && $relatedPosts->have()): ?>
                <h2><?php _e('相关文章'); ?></h2>
                <div class="related-posts">
                    <?php while ($relatedPosts->next()): ?>
                        <a href="<?php $relatedPosts->permalink(); ?>"><?php $relatedPosts->title(); ?></a>
                    <?php endwhile; ?>
                </div>
            <?php endif; ?>

            <?php ?>
            <div class="post-share">
                <button type="button" class="share-btn share-copy" data-url="<?php echo lt_esc_attr($this->permalink); ?>"><?php echo lt_icon('link'); ?> <span class="share-btn-text"><?php _e('复制链接'); ?></span></button>
                <a class="share-btn" target="_blank" rel="noopener noreferrer" href="https://service.weibo.com/share/share.php?url=<?php echo rawurlencode($this->permalink); ?>&amp;title=<?php echo rawurlencode(lt_text($this->title)); ?>"><?php echo lt_icon('weibo'); ?> <?php _e('微博分享'); ?></a>
                <button type="button" class="share-btn lt-like-btn <?php if ($hasLiked) echo 'liked'; ?>" data-cid="<?php echo $cid; ?>" data-token="<?php echo lt_esc_attr($ltLikeToken); ?>" <?php if ($hasLiked) echo 'disabled'; ?>>
                    <span class="lt-like-icon"><?php echo $hasLiked ? '♥' : '♡'; ?></span>
                    <span class="lt-like-count"><?php echo $likesNum; ?></span>
                </button>
            </div>
        <?php endif; ?>

        <div><?php $this->need('partials/comments.php'); ?></div>
    </div>

    <?php if ($directoryStatus === 'on'): ?>
        <div class="catalog-container" data-cid="<?php echo $cid; ?>">
            <?php ?>
            <div class="catalog-panel-header">
                <span class="catalog-panel-title"><?php _e('目录'); ?></span>
                <button type="button" class="catalog-panel-close" aria-label="<?php echo lt_esc_attr(_t('关闭目录')); ?>">×</button>
            </div>
            <div class="catalog-directory" id="catalog-directory"></div>
        </div>
        <?php ?>
        <button type="button" id="catalog-float-btn" class="catalog-float-btn" aria-label="目录">☰</button>
        <?php ?>
        <div class="catalog-mask" id="catalog-mask"></div>
        <?php ?>
    <?php endif; ?>
</div>

<div id="background-layer" data-reward="<?php echo lt_esc_attr(json_encode($rewardUrls, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)); ?>">
    <div class="popup-container" id="social-pop" role="dialog" aria-modal="true" aria-label="弹窗内容">
        <div class="popup-header"><i class="iconfont" id="social-pop-close" role="button" aria-label="关闭" tabindex="0"><?php echo lt_icon('close'); ?></i></div>
        <div class="popup-body" id="popup-body"></div>
    </div>
</div>

<?php $this->need('partials/footer.php'); ?>
<?php ?>
