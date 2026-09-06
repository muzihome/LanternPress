<?php
declare(strict_types=1);

if (!defined('__TYPECHO_ROOT_DIR__')) exit;
?>
<?php // Bug3：推荐轮播样式已迁移至 public/header.php 主样式块 ?>

<?php if ($this->is('index')): ?>
    <?php
    $recommend = lt_text($this->options->cIdRecommend ?? '');
    $recommendCounts = array_values(
        array_filter(
            array_map('trim', explode("||", $recommend)),
            static fn(string $cid): bool => ctype_digit($cid)
        )
    );

    $recommendItems = [];
    if (!empty($recommendCounts)) {
        try {
            $db = \Typecho\Db::get();
            // 单次内容查询：仅保留已发布、未加密的文章，避免空 slide / 错位（N2 批量优化，替代逐项 Archive Widget）
            $rows = $db->fetchAll(
                $db->select()
                    ->from('table.contents')
                    ->where('type = ?', 'post')
                    ->where('status = ?', 'publish')
                    ->where('password IS NULL OR password = ?', '')
                    ->where('cid IN ?', array_map('intval', $recommendCounts))
                    ->order('table.contents.created', \Typecho\Db::SORT_DESC)
            );
            $cids = [];
            foreach ($rows as $row) {
                $cids[] = (int) $row['cid'];
            }

            // 单次字段查询：批量读取 bannerUrl / articleDesc，避免逐项触发字段查询
            $fieldMap = [];
            if (!empty($cids)) {
                $fieldRows = $db->fetchAll(
                    $db->select()->from('table.fields')->where('cid IN ?', $cids)
                );
                foreach ($fieldRows as $fr) {
                    $fcid = (int) $fr['cid'];
                    if (!isset($fieldMap[$fcid])) {
                        $fieldMap[$fcid] = [];
                    }
                    $val = (string) ($fr['str_value'] ?? '');
                    if ($val === '') {
                        $val = (string) ($fr['int_value'] ?? '');
                    }
                    if ($val === '') {
                        $val = (string) ($fr['float_value'] ?? '');
                    }
                    $fieldMap[$fcid][(string) $fr['name']] = $val;
                }
            }

            // 按配置书写顺序稳定排列
            $orderMap = array_flip($recommendCounts);
            usort($rows, static function (array $a, array $b) use ($orderMap): int {
                return ($orderMap[(int) $a['cid']] ?? PHP_INT_MAX) <=> ($orderMap[(int) $b['cid']] ?? PHP_INT_MAX);
            });

            $indexThumbs = lt_lines($this->options->indexThumbs ?? '');
            $thumbCount = count($indexThumbs);
            $defaultThumb = rtrim((string) $this->options->themeUrl, '/') . '/assets/img/blog_bg.jpg';

            foreach ($rows as $row) {
                $cid = (int) $row['cid'];
                $fields = $fieldMap[$cid] ?? [];

                $banner = trim(lt_text($fields['bannerUrl'] ?? ''));
                if ($banner !== '') {
                    $thumb = lt_safe_url($banner);
                } elseif ($thumbCount > 0) {
                    $thumb = lt_safe_url($indexThumbs[$cid % $thumbCount]);
                } else {
                    $img = lt_content_image(lt_text($row['text'] ?? ''));
                    $thumb = $img !== '' ? $img : lt_safe_url($defaultThumb);
                }

                $desc = trim(lt_text($fields['articleDesc'] ?? ''));
                if ($desc === '') {
                    $desc = \Typecho\Common::subStr(strip_tags((string) ($row['text'] ?? '')), 0, 80, '...');
                }

                $recommendItems[] = [
                    'permalink' => \Typecho\Router::url('post', $row, $this->options->index),
                    'title' => lt_text($row['title'] ?? ''),
                    'desc' => $desc,
                    'thumb' => $thumb
                ];
            }
        } catch (\Throwable $e) {
            $recommendItems = [];
        }
    }
    $number = count($recommendItems);
    ?>
    <?php if ($number >= 1): ?>
        <section class="articles-grid">
            <div class="articles-row">
                <div class="recommend-slider">
                    <?php if ($number >= 2): ?>
                        <div class="slider-btn iconfont slider-btn-prev"><?php echo lt_icon('left'); ?></div>
                    <?php endif; ?>
                    <div class="swiper">
                        <div class="swiper-wrapper">
                            <?php foreach ($recommendItems as $item): ?>
                                <div class="swiper-slide">
                                    <div data-href="<?php echo lt_esc_attr($item['permalink']); ?>" role="link" tabindex="0" class="article-item single-article-item">
                                        <div class="item-container">
                                            <div class="item-content single-item-content">
                                                <div class="item-title font-bold">
                                                    <?php echo lt_esc_html($item['title']); ?>
                                                </div>
                                                <div class="item-abstract">
                                                    <?php echo lt_esc_html($item['desc']); ?>
                                                </div>
                                            </div>
                                            <div class="item-img single-item-img <?php if (lt_bool($this->options->greyImg ?? false)) echo "color-filter"; ?>">
                                                <div class="blog-background lazy-bg" data-src="<?php echo lt_esc_attr($item['thumb']); ?>"></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php if ($number >= 2): ?>
                        <div class="slider-btn iconfont slider-btn-next"><?php echo lt_icon('right'); ?></div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="hr_2px"></div>
        </section>
        <?php if ($number >= 2): ?>
        <script type="text/javascript">
            (function() {
                var slideCount = <?php echo $number; ?>;
                if (typeof Swiper !== 'function') return;
                new Swiper('.recommend-slider .swiper', {
                    loop: slideCount >= 3,
                    loopAdditionalSlides: slideCount >= 3 ? 1 : 0,
                    autoplay: {
                        delay: 5000,
                        disableOnInteraction: false
                    },
                    observer: true,
                    observeParents: true,
                    navigation: {
                        nextEl: '.slider-btn-next',
                        prevEl: '.slider-btn-prev'
                    }
                });
            })();
        </script>
        <?php endif; ?>
    <?php endif; ?>
<?php endif; ?>
