<?php
declare(strict_types=1);

if (!defined('__TYPECHO_ROOT_DIR__')) exit;

$isIndex = $this->is('index');
$greyImg = lt_bool($this->options->greyImg ?? false);
?>
<section class="articles-grid">
    <?php if ($this->request->get('from') !== 'ajax'): ?>
    <section class="category-heading">
        <div class="iconfont category-icon"><?php echo lt_icon('grid'); ?></div>
        <?php if ($isIndex): ?>
            <span><?php _e('最新文章'); ?></span>
        <?php elseif ($this->is('search')): ?>
            <?php $this->need('component/search.title.php'); ?>
        <?php else: ?>
            <?php $this->archiveTitle(
                [
                    'category' => '分类：%s',
                    'tag' => '标签：%s',
                    'author' => '作者：%s',
                    'date' => '归档：%s',
                ],
                '',
                ''
            ); ?>
        <?php endif; ?>
    </section>
    <?php endif; ?>
    <?php if ($this->have()): ?>
        <div id="articleList">
            <?php
            $count = $this->length;
            $rows = (int) ceil($count / 3);
            ?>
            <?php while ($rows > 0): ?>
                <?php
                $rowNum = 3;
                if ($rows === 1) {
                    $rowNum = $count % 3;
                    if ($rowNum === 0) {
                        $rowNum = 3;
                    }
                }
                $rows--;
                ?>
                <section class="articles-row recent">
                    <?php for ($i = 1; $i <= $rowNum; $i++): ?>
                        <?php $this->next(); ?>
                        <?php
                        $permalink = lt_text($this->permalink);
                        $title = lt_text($this->title);
                        $thumb = getThumb($this, $this->options);
                        $articleDesc = lt_text($this->fields->articleDesc ?? '');
                        ?>
                        <div id="article-item-<?php $this->cid(); ?>" class="article-item" role="link" tabindex="0" data-href="<?php echo lt_esc_attr($permalink); ?>">
                            <div class="item-container">
                                <div class="item-content">
                                    <div class="item-title font-bold">
                                        <?php $this->title(); ?>
                                    </div>
                                    <div class="item-meta">
                                        <?php $this->date('Y年m月d日'); ?>
                                        <?php $this->category('  '); ?>
                                    </div>
                                    <div class="item-abstract">
                                        <?php if ($articleDesc !== ''): ?>
                                            <?php echo lt_esc_html($articleDesc); ?>
                                        <?php else: ?>
                                            <?php $this->excerpt(LT_DEFAULT_EXCERPT_LENGTH, "..."); ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="item-img <?php if ($greyImg) echo "color-filter"; ?>">
                                    <?php if ($thumb !== ''): ?>
                                    <div class="blog-background lazy-bg" data-src="<?php echo lt_esc_attr($thumb); ?>"></div>
                                    <?php else: ?>
                                    <div class="blog-background no-thumb"></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endfor; ?>
                </section>
            <?php endwhile; ?>
        </div>
    <?php endif; ?>
</section>
<?php // Bug3：文章卡片跳转由 lantern.config.js initArticleLinks 事件委托统一处理 ?>
