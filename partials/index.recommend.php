<?php
declare(strict_types=1);

if (!defined('__TYPECHO_ROOT_DIR__')) exit;
?>
<?php ?>

<?php if ($this->is('index')): ?>
    <?php
    
    $recommend = lt_filter_cid_recommend(lt_text($this->options->cIdRecommend ?? ''));
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

            
            
            $fieldMap = [];
            if (!empty($cids)) {
                $fieldRows = $db->fetchAll(
                    $db->select('cid', 'name', 'str_value', 'int_value', 'float_value')
                        ->from('table.fields')
                        ->where('cid IN ?', $cids)
                        ->where('name IN ?', ['bannerUrl', 'articleDesc'])
                );
                foreach ($fieldRows as $fr) {
                    $fcid = (int) $fr['cid'];
                    if (!isset($fieldMap[$fcid])) {
                        $fieldMap[$fcid] = [];
                    }
                    
                    $val = (string) ($fr['str_value'] ?? '');
                    if ($val === '' && (int) ($fr['int_value'] ?? 0) !== 0) {
                        $val = (string) $fr['int_value'];
                    }
                    if ($val === '' && (float) ($fr['float_value'] ?? 0) != 0.0) {
                        $val = (string) $fr['float_value'];
                    }
                    $fieldMap[$fcid][(string) $fr['name']] = $val;
                }
            }

            
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
                        <button type="button" class="slider-btn iconfont slider-btn-prev" aria-label="上一组"><?php echo lt_icon('left'); ?></button>
                    <?php endif; ?>
                    <div class="lt-slider">
                        <div class="lt-slider-track">
                            <?php foreach ($recommendItems as $item): ?>
                                <div class="lt-slider-slide">
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
                        <button type="button" class="slider-btn iconfont slider-btn-next" aria-label="下一组"><?php echo lt_icon('right'); ?></button>
                    <?php endif; ?>
                </div>
            </div>
            <div class="hr_2px"></div>
        </section>
    <?php endif; ?>
<?php endif; ?>
