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
// B3：删除被 lt_get_views 覆盖的死代码 $viewsNum = (int)($this->viewsNum ?? 0)
$showWriterIntro = lt_bool($this->options->writerIntro ?? false);
$showCopyright = lt_bool($this->options->showCopyright ?? false);
$selfIntro = trim(lt_text($this->options->selfIntro ?? ''));
$directoryStatus = lt_text($this->fields->directoryStatus ?? 'off');
$authorName = lt_text($this->author->name ?? $this->author->screenName ?? '');
$authorMail = lt_text($this->author->mail ?? '');
$cid = (int) ($this->cid ?? 0);
// B4：主题内置阅读量统计（cookie 防刷，24 小时内同一文章只计一次）
$viewsNum = lt_get_views($cid);
// B8：文章点赞数
$likesNum = lt_get_likes($cid);
$hasLiked = lt_has_liked($cid);
// B4：文章页加载时增加阅读量（必须在输出前调用，以便设置 cookie）
if ($isPost && $cid > 0) {
    lt_increment_views($cid);
}
// F1：文章阅读时长估算 + 字数统计（中文字符 ÷ 400 字/分钟，至少1分钟）
$readingMinutes = 1;
$wordCount = 0;
if ($isPost) {
    $_postText = strip_tags(lt_text($this->content ?? ''));
    $_charCount = mb_strlen($_postText, 'UTF-8');
    $wordCount = $_charCount;
    $readingMinutes = max(1, (int) ceil($_charCount / 400));
}
?>
<?php $this->need('public/header.php'); ?>
<?php if ($isPost): ?>
<div id="reading-progress" aria-hidden="true"></div>
<?php endif; ?>
<div class="post">
    <div class="post-container">
        <div class="post-title"><?php $this->title(); ?></div>
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
            <?php // O3：阅读量仅在文章页显示，独立页面不显示 ?>
            <?php if ($isPost && $viewsNum >= LT_MIN_VIEWS_DISPLAY): ?>
                • <?php _e('阅读'); ?>: <?php echo $viewsNum; ?>
            <?php endif; ?>
            <?php if ($isPost): ?>
                • <?php printf(_t('约 %d 分钟读完'), $readingMinutes); ?> · <?php printf(_t('全文 %d 字'), $wordCount); ?>
            <?php endif; ?>
        </div>

        <div id="post-content" class="post-content line-numbers">
            <?php if ($isPost): ?>
                <?php
                // M1：文章内容处理结果数据库缓存，加入配置哈希检测，配置变更后自动失效
                $ltCid = (int) $this->cid;
                $ltConfigHash = lt_get_config_hash();
                $ltCachedRaw = $ltCid > 0 ? lt_get_field_str($ltCid, 'ltProcessedContent') : null;
                $ltCacheHit = false;
                if ($ltCachedRaw !== null && $ltCachedRaw !== '' && strpos($ltCachedRaw, '||') !== false) {
                    list($ltCachedHash, $ltCachedContent) = explode('||', $ltCachedRaw, 2);
                    if ($ltCachedHash === $ltConfigHash) {
                        echo $ltCachedContent;
                        $ltCacheHit = true;
                    }
                }
                if (!$ltCacheHit) {
                ob_start();
                $this->content();
                $content = (string) ob_get_clean();

                // 1) 先保护已处于 <a> 内的图片，避免二次包裹产生嵌套锚点
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
                $content = $_tmp !== null ? $_tmp : $content; // A3：失败回退

                // 2) 为独立的 <img> 添加 fancybox 灯箱链接，保留原属性与原 alt
                $_tmp = preg_replace_callback(
                    '/<img\b[^>]*>/i',
                    function (array $matches) use ($title): string {
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
                        // B1：健壮的属性插入：识别 > 或 /> 闭合符，在其前插入，避免格式异常时错位
                        $insertAttr = static function (string $tag, string $attr): string {
                            if (preg_match('/\/>\s*$/', $tag)) {
                                return preg_replace('/\/>\s*$/', ' ' . $attr . ' />', $tag);
                            }
                            if (preg_match('/>\s*$/', $tag)) {
                                return preg_replace('/>\s*$/', ' ' . $attr . '>', $tag);
                            }
                            return $tag . ' ' . $attr; // 格式异常时直接追加
                        };
                        if (!preg_match('/\salt=/i', $newImg)) {
                            $newImg = $insertAttr($newImg, 'alt="' . lt_esc_attr($alt) . '"');
                        }
                        // F1：为图片添加原生 loading="lazy"，减少首屏外图片下载（已有则不重复添加）
                        if (!preg_match('/\sloading=/i', $newImg)) {
                            $newImg = $insertAttr($newImg, 'loading="lazy"');
                        }
                        return '<a href="' . lt_esc_attr($src) . '" class="fancybox" data-fancybox="gallery">' . $newImg . '</a>';
                    },
                    $content
                );
                $content = $_tmp !== null ? $_tmp : $content; // A3：失败回退

                // 3) 还原被保护的锚点
                $content = strtr($content, $ltAnchors);

                // 4) 为代码块添加 Prism line-numbers 行号类
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
                $content = $_tmp !== null ? $_tmp : $content; // A3：失败回退

                // M1：缓存处理后的内容到数据库，前缀配置哈希，配置变更后自动失效
                if ($ltCid > 0) {
                    lt_set_field_str($ltCid, 'ltProcessedContent', $ltConfigHash . '||' . $content);
                }
                echo $content;
                }
                ?>
            <?php else: ?>
                <?php $this->content(); ?>
            <?php endif; ?>
        </div>

        <div class="post-tags"><?php $this->tags('', true, ''); ?></div>

        <?php if ($isPost): ?>
            <?php if ($showWriterIntro): ?>
                <div class="article-writer">
                    <img src="<?php echo parseAvatar($this->author->mail) ?>" alt="<?php echo lt_esc_attr($authorName); ?>">
                    <div class="right">
                        <div class="intro">
                            <span class="name"><a href="<?php $this->author->permalink() ?>"><?php $this->author() ?></a></span>
                            <span class="sign">
                                <?php if ($selfIntro !== ''): ?>
                                    <?php echo lt_esc_html($selfIntro); ?>
                                <?php else: ?>
                                    <?php _e('这个人很懒，什么也没有留下'); ?>
                                <?php endif; ?>
                            </span>
                        </div>
                        <div class="social-link">
                            <a href="javascript:;" class="iconfont author-email" data-email="<?php echo lt_esc_attr(base64_encode($authorMail)); ?>" title="发邮件"><?php echo lt_icon('mail'); ?></a>
                            <?php foreach ($socialList as $item): ?>
                                <?php if ($item['type'] === 'qr'): ?>
                                    <a href="javascript:;" class="iconfont" data-qr="<?php echo lt_esc_attr($item['link']); ?>" data-title="<?php echo lt_esc_attr($item['name']); ?>"><?php echo lt_icon($item['name']); ?></a>
                                <?php else: ?>
                                    <a href="<?php echo lt_esc_attr($item['link']); ?>" target="_blank" rel="noopener noreferrer" class="iconfont"><?php echo lt_icon($item['name']); ?></a>
                                <?php endif; ?>
                            <?php endforeach; ?>
                            <?php if (!empty($rewardUrls)): ?>
                                <a href="javascript:;" data-reward="1" class="iconfont"><?php echo lt_icon('reward'); ?></a>
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

            <h2>推荐阅读</h2>
            <div class="post-prev-next">
                <?php thePrev($this, $this->options); ?>
                <?php theNext($this, $this->options); ?>
            </div>

            <?php // E-b：相关文章（同标签 TOP 3，无标签时静默跳过） ?>
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

            <?php // E-e：文章分享 + B8：点赞按钮 ?>
            <div class="post-share">
                <button type="button" class="share-btn share-copy" data-url="<?php echo lt_esc_attr($this->permalink); ?>"><?php echo lt_icon('link'); ?> <?php _e('复制链接'); ?></button>
                <a class="share-btn" target="_blank" rel="noopener noreferrer" href="https://service.weibo.com/share/share.php?url=<?php echo rawurlencode($this->permalink); ?>&amp;title=<?php echo rawurlencode(lt_text($this->title)); ?>"><?php echo lt_icon('weibo'); ?> <?php _e('微博分享'); ?></a>
                <button type="button" class="share-btn lt-like-btn <?php if ($hasLiked) echo 'liked'; ?>" data-cid="<?php echo $cid; ?>" <?php if ($hasLiked) echo 'disabled'; ?>>
                    <span class="lt-like-icon"><?php echo $hasLiked ? '♥' : '♡'; ?></span>
                    <span class="lt-like-count"><?php echo $likesNum; ?></span>
                </button>
            </div>
        <?php endif; ?>

        <div><?php $this->need('public/comments.php'); ?></div>
    </div>

    <?php if ($directoryStatus === 'on'): ?>
        <div class="catalog-container">
            <?php // O2：移动端目录面板标题栏（含关闭按钮），桌面端隐藏 ?>
            <div class="catalog-panel-header">
                <span class="catalog-panel-title"><?php _e('目录'); ?></span>
                <button type="button" class="catalog-panel-close" aria-label="<?php echo lt_esc_attr(_t('关闭目录')); ?>">×</button>
            </div>
            <div class="catalog-directory" id="catalog-directory"></div>
        </div>
        <?php // B1：移动端目录树浮动按钮（轻量版，点击切换面板） ?>
        <button type="button" id="catalog-float-btn" class="catalog-float-btn" aria-label="目录">☰</button>
        <?php // Bug3：移动端目录面板遮罩层，点击关闭面板 ?>
        <div class="catalog-mask" id="catalog-mask"></div>
        <script>
            (function () {
                if (typeof jQuery !== 'function') return;
                var $ = jQuery; // A1：局部绑定 $，避免与其他库全局冲突
                var postContent = document.getElementById('post-content');
                var mount = document.getElementById('catalog-directory');
                if (!postContent || !mount) return;
                var titles = postContent.querySelectorAll('h1,h2,h3,h4,h5,h6');
                if (!titles.length) return;

                // E3：按标题层级生成多级嵌套目录（h1/h2 为一级，h3/h4 依次下沉）
                var root = document.createElement('ul');
                var stack = [root];   // ul 栈
                var levels = [0];     // 对应标题层级
                titles.forEach(function (node, index) {
                    if (!node.id) node.id = 'menu-index-' + (index + 1);
                    var level = parseInt(node.tagName.charAt(1), 10) || 1;
                    var li = document.createElement('li');
                    var a = document.createElement('a');
                    a.href = '#' + node.id;
                    a.textContent = node.textContent || '';
                    li.appendChild(a);
                    while (levels.length > 1 && levels[levels.length - 1] >= level) {
                        levels.pop();
                        stack.pop();
                    }
                    stack[stack.length - 1].appendChild(li);
                    var childUl = document.createElement('ul');
                    li.appendChild(childUl);
                    stack.push(childUl);
                    levels.push(level);
                });
                mount.appendChild(root);

                // Bug1：清理没有子元素的空 ul（每个 li 都预创建了 childUl，无子级标题时需移除）
                var allUls = mount.querySelectorAll('ul');
                allUls.forEach(function (ul) {
                    if (ul.children.length === 0 && ul.parentNode) {
                        ul.parentNode.removeChild(ul);
                    }
                });

                // Bug1：为有子级的目录项添加展开/收起按钮（默认全部展开）
                // F3：展开状态记忆到 localStorage，按文章 cid + 节点索引存储
                var catalogCid = '<?php echo $cid; ?>';
                var allLis = mount.querySelectorAll('li');
                allLis.forEach(function (li, idx) {
                    var subUl = null;
                    for (var i = 0; i < li.children.length; i++) {
                        if (li.children[i].tagName === 'UL') { subUl = li.children[i]; break; }
                    }
                    if (!subUl || subUl.children.length === 0) return;
                    var storageKey = 'lt_catalog_' + catalogCid + '_' + idx;
                    // F3：从 localStorage 读取展开状态，默认展开
                    var expanded = true;
                    try {
                        if (localStorage.getItem(storageKey) === '0') expanded = false;
                    } catch (e) {}
                    if (!expanded) {
                        subUl.style.display = 'none';
                    }
                    var toggle = document.createElement('span');
                    toggle.className = 'catalog-toggle';
                    toggle.textContent = expanded ? '▾' : '▸';
                    toggle.setAttribute('role', 'button');
                    toggle.setAttribute('aria-label', '展开/收起');
                    toggle.style.cssText = 'cursor:pointer;display:inline-block;width:12px;margin-right:2px;font-size:10px;color:#999;user-select:none;';
                    li.insertBefore(toggle, li.firstChild);
                    toggle.addEventListener('click', function (e) {
                        e.stopPropagation();
                        expanded = !expanded;
                        subUl.style.display = expanded ? '' : 'none';
                        toggle.textContent = expanded ? '▾' : '▸';
                        // F3：保存展开状态到 localStorage
                        try {
                            localStorage.setItem(storageKey, expanded ? '1' : '0');
                        } catch (e) {}
                    });
                });

                var isMobile = window.innerWidth <= 768;
                var floatBtn = document.getElementById('catalog-float-btn');
                var catalogContainer = document.querySelector('.catalog-container');

                // B1：移动端：浮动按钮切换目录面板，点击链接后自动关闭
                if (isMobile) {
                    if (floatBtn) floatBtn.style.display = 'block';
                    if (catalogContainer) catalogContainer.classList.add('mobile-panel');
                    var mask = document.getElementById('catalog-mask');
                    var togglePanel = function () {
                        if (catalogContainer) catalogContainer.classList.toggle('open');
                        if (mask) mask.classList.toggle('show');
                    };
                    var closePanel = function () {
                        if (catalogContainer) catalogContainer.classList.remove('open');
                        if (mask) mask.classList.remove('show');
                    };
                    if (floatBtn) floatBtn.addEventListener('click', togglePanel);
                    // O2：面板关闭按钮
                    var closeBtn = catalogContainer ? catalogContainer.querySelector('.catalog-panel-close') : null;
                    if (closeBtn) closeBtn.addEventListener('click', closePanel);
                    // Bug3：点击遮罩层关闭面板
                    if (mask) mask.addEventListener('click', closePanel);
                    mount.addEventListener('click', function (e) {
                        if (e.target.tagName === 'A') {
                            setTimeout(closePanel, 300);
                        }
                    });
                    return; // 移动端不执行桌面端的滚动高亮
                }

                var anchors = Array.prototype.slice.call(mount.querySelectorAll('a'));
                var updateActive = function () {
                    var top = window.pageYOffset || document.documentElement.scrollTop || 0;
                    var active = 0;
                    titles.forEach(function (node, idx) {
                        if (top + 10 > $(node).offset().top) active = idx;
                    });
                    anchors.forEach(function (a, i) {
                        if (i === active) { a.classList.add('current'); } else { a.classList.remove('current'); }
                    });
                    var inRange = top > 70 && top < (70 + $('#post-content').outerHeight(true));
                    $('#catalog-directory').css('opacity', inRange ? 1 : 0);
                };
                // E-d：锚点平滑滚动 + 地址栏更新
                $(mount).on('click', 'a', function (e) {
                    var href = this.getAttribute('href');
                    if (!href || href.charAt(0) !== '#') return;
                    var target = document.querySelector(href);
                    if (!target) return;
                    e.preventDefault();
                    target.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    if (window.history && window.history.replaceState) {
                        window.history.replaceState(null, '', href);
                    }
                });
                $(window).on('scroll', updateActive);
                updateActive();
            })();
        </script>
    <?php endif; ?>
</div>

<div id="background-layer">
    <div class="popup-container" id="social-pop" role="dialog" aria-modal="true" aria-label="弹窗内容">
        <div class="popup-header"><i class="iconfont" id="social-pop-close" role="button" aria-label="关闭" tabindex="0"><?php echo lt_icon('close'); ?></i></div>
        <div class="popup-body" id="popup-body"></div>
    </div>
</div>

<?php $this->need('public/footer.php'); ?>
<script>
    (function () {
        if (typeof jQuery !== 'function') return;
        var rewardUrls = <?php echo json_encode($rewardUrls, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
        var bg = $('#background-layer');
        var body = $('#popup-body');
        var lastFocused = null;
        var open = function (images) {
            body.html('');
            images.forEach(function (src) {
                body.append('<img src="' + $('<div/>').text(src).html() + '"/>');
            });
            lastFocused = document.activeElement;
            bg.css('display', 'flex');
            bg.css('opacity', 1);
            var closeBtn = document.getElementById('social-pop-close');
            if (closeBtn) closeBtn.focus();
        };
        var close = function () {
            bg.css('display', 'none');
            bg.css('opacity', 0);
            body.html('');
            if (lastFocused && typeof lastFocused.focus === 'function') {
                lastFocused.focus();
            }
            lastFocused = null;
        };
        // 点击遮罩（非弹窗内部）时关闭
        bg.on('click', function (e) {
            if (e.target === bg[0]) {
                close();
            }
        });
        // Esc 关闭
        $(document).on('keydown', function (e) {
            if (e.key === 'Escape' && bg.is(':visible')) {
                close();
            }
        });
        // 关闭按钮支持键盘 Enter
        $('#social-pop-close').on('click', close).on('keydown', function (e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                close();
            }
        });
        $('[data-reward="1"]').on('click', function () {
            if (rewardUrls.length) open(rewardUrls);
        });
        $('[data-qr]').on('click', function () {
            var src = $(this).data('qr');
            if (src) open([src]);
        });
        // E11：作者邮箱防爬——点击时用 base64 解码后拼接 mailto
        $('.author-email').on('click', function () {
            var encoded = $(this).attr('data-email') || '';
            var mail = '';
            try {
                mail = atob(encoded);
            } catch (e) {
                return;
            }
            if (mail && /^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(mail)) {
                window.location.href = 'mailto:' + mail;
            }
        });
        // E-e：复制文章链接（优先 Clipboard API，回退 execCommand）
        $('.share-copy').on('click', function () {
            var url = $(this).data('url') || window.location.href;
            var btn = this;
            var done = function (ok) {
                var old = btn.textContent;
                btn.textContent = ok ? '已复制' : '复制失败';
                setTimeout(function () { btn.textContent = old; }, 1500);
            };
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(url).then(function () { done(true); }, function () { done(false); });
            } else {
                var ta = document.createElement('textarea');
                ta.value = url;
                ta.style.position = 'fixed';
                ta.style.opacity = '0';
                document.body.appendChild(ta);
                ta.select();
                try { done(document.execCommand('copy')); } catch (e) { done(false); }
                document.body.removeChild(ta);
            }
        });
    })();
</script>
