<?php
/**
 * 标签云
 *
 * @package custom
 */
declare(strict_types=1);

if (!defined('__TYPECHO_ROOT_DIR__')) exit;

$this->need('public/header.php');

// E-c：查询全部标签及其文章数
$tagCloud = [];
try {
    $db = \Typecho\Db::get();
    $rows = $db->fetchAll(
        $db->select('table.metas.mid', 'table.metas.name', 'table.metas.slug', 'COUNT(table.relationships.cid) AS cnt')
            ->from('table.metas')
            ->join('table.relationships', 'table.metas.mid = table.relationships.mid', \Typecho\Db::JOIN_LEFT)
            ->where('table.metas.type = ?', 'tag')
            ->group('table.metas.mid')
            ->order('table.metas.mid', \Typecho\Db::SORT_DESC)
    );
    foreach ($rows as $row) {
        $slug = trim(lt_text($row['slug'] ?? ''));
        $name = trim(lt_text($row['name'] ?? ''));
        $tagCloud[] = [
            'name' => $name,
            'slug' => $slug,
            'count' => max(0, (int) ($row['cnt'] ?? 0)),
            'url' => ($name !== '' && $slug !== '')
                ? \Typecho\Router::url('archive', ['type' => 'tag', 'slug' => $slug], $this->options->index)
                : ''
        ];
    }
} catch (\Throwable $e) {
    $tagCloud = [];
}

$maxCount = 1;
foreach ($tagCloud as $t) {
    $maxCount = max($maxCount, $t['count']);
}
?>
<div class="post">
    <div class="post-container tagcloud-page">
        <div class="post-title"><?php _e('标签云'); ?></div>
        <?php if (empty($tagCloud)): ?>
            <p><?php _e('暂无标签'); ?></p>
        <?php else: ?>
            <div class="tagcloud">
                <?php foreach ($tagCloud as $t): ?>
                    <?php if ($t['url'] === '') { continue; } ?>
                    <?php $level = (int) max(1, min(5, (int) ceil(($t['count'] / $maxCount) * 5))); ?>
                    <a class="tag-item tag-lv<?php echo $level; ?>" href="<?php echo lt_esc_attr($t['url']); ?>"><?php echo lt_esc_html($t['name']); ?><span class="tag-count"><?php echo $t['count']; ?></span></a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php $this->need('public/footer.php'); ?>
