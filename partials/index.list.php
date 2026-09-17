<?php
declare(strict_types=1);

if (!defined('__TYPECHO_ROOT_DIR__')) exit;

$isIndex = $this->is('index');
$greyImg = lt_bool($this->options->greyImg ?? false);

$ltFirstScreen = $this->request->get('from') !== 'ajax'
    && (int) $this->request->filter('int')->get('page', 1) <= 1;
$ltEagerRendered = false;
?>
<section class="articles-grid">
    <?php if ($this->request->get('from') !== 'ajax'): ?>
    <section class="category-heading">
        <div class="iconfont category-icon"><?php echo lt_icon('grid'); ?></div>
        <?php if ($isIndex): ?>
            <h1 class="category-heading-title"><?php _e('最新文章'); ?></h1>
        <?php elseif ($this->is('search')): ?>
            <?php $this->need('partials/search.title.php'); ?>
        <?php else: ?>
            <h1 class="category-heading-title"><?php $this->archiveTitle(
                [
                    'category' => '分类：%s',
                    'tag' => '标签：%s',
                    'author' => '作者：%s',
                    'date' => '归档：%s',
                ],
                '',
                ''
            ); ?></h1>
        <?php endif; ?>
    </section>
    <?php endif; ?>
    <?php if ($this->have()): ?>
        <div id="articleList">
            <?php
            
            
            $ltBatchFields = [];
            if ($this->have()) {
                $ltCids = [];
                while ($this->next()) {
                    $ltCids[] = (int) $this->cid;
                }
                if ($ltCids !== []) {
                    try {
                        $ltDb = \Typecho\Db::get();
                        $ltRows = $ltDb->fetchAll(
                            $ltDb->select()
                                ->from('table.fields')
                                ->where('cid IN ?', $ltCids)
                        );
                        foreach ($ltRows as $ltRow) {
                            $ltCid = (int) ($ltRow['cid'] ?? 0);
                            $ltBatchFields[$ltCid] = $ltBatchFields[$ltCid] ?? ['articleDesc' => '', 'bannerUrl' => ''];
                            if (($ltRow['name'] ?? '') === 'articleDesc') {
                                $ltBatchFields[$ltCid]['articleDesc'] = (string) ($ltRow['str_value'] ?? '');
                            } elseif (($ltRow['name'] ?? '') === 'bannerUrl') {
                                $ltBatchFields[$ltCid]['bannerUrl'] = (string) ($ltRow['str_value'] ?? '');
                            }
                        }
                    } catch (\Throwable $e) {
                        lt_log_error('batch fields prefetch failed', $e);
                    }
                }
            }
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
                        $isHidden = (bool) ($this->hidden ?? false);
                        
                        if ($isHidden) {
                            $thumb = '';
                            $articleDesc = '';
                        } else {
                            $ltFieldMap = $ltBatchFields[(int) $this->cid] ?? ['articleDesc' => '', 'bannerUrl' => ''];
                            $thumb = getThumb($this, $this->options, $ltFieldMap['bannerUrl']);
                            $articleDesc = lt_text($ltFieldMap['articleDesc']);
                        }
                        ?>
                        <div id="article-item-<?php $this->cid(); ?>" class="article-item<?php if ($isHidden) echo ' article-locked'; ?>" role="link" tabindex="0" data-href="<?php echo lt_esc_attr($permalink); ?>">
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
                                        <?php if ($isHidden): ?>
                                            <span class="locked-hint"><?php _e('此内容被密码保护'); ?></span>
                                        <?php elseif ($articleDesc !== ''): ?>
                                            <?php echo lt_esc_html($articleDesc); ?>
                                        <?php else: ?>
                                            <?php $this->excerpt(LT_DEFAULT_EXCERPT_LENGTH, "..."); ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="item-img <?php if ($greyImg) echo "color-filter"; ?>">
                                    <?php if ($thumb !== '' && $ltFirstScreen && !$ltEagerRendered): ?>
                                    <?php
                                    
                                    $ltBgCss = "background-image:url('" . str_replace("'", "\\'", $thumb) . "')";
                                    $ltEagerRendered = true;
                                    ?>
                                    <div class="blog-background loaded" style="<?php echo lt_esc_attr($ltBgCss); ?>"></div>
                                    <?php elseif ($thumb !== ''): ?>
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
<?php ?>
