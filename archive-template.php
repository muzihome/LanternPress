<?php
/**
 * 归档页
 *
 * @package custom
 */
declare(strict_types=1);

if (!defined('__TYPECHO_ROOT_DIR__')) exit;

$this->need('header.php');


// 归档缓存走统一缓存层 lt_cache_remember（cache/archive_*.php，TTL 3600s），
// 发布/更新文章时由 finishArticle 钩子（inc/hooks.php）清除；
// 一次性遗留清理：旧版固定命名缓存文件（统一缓存层前的 archive-cache.php）
$ltLegacyArchiveCache = __DIR__ . '/cache/archive-cache.php';
if (is_file($ltLegacyArchiveCache)) {
    @unlink($ltLegacyArchiveCache);
}

$groups = lt_cache_remember('archive', 'archive', 3600, function () {
    $archiveRows = [];
    try {
        $db = \Typecho\Db::get();
        $archiveRows = $db->fetchAll(
            $db->select('cid', 'title', 'created', 'slug')
                ->from('table.contents')
                ->where('type = ?', 'post')
                ->where('status = ?', 'publish')
                ->where('created <= ?', time())
                ->where('password IS NULL OR password = ?', '')
                ->order('table.contents.created', \Typecho\Db::SORT_DESC)
        );
    } catch (\Throwable $e) {
        $archiveRows = [];
    }

    $groups = [];
    foreach ($archiveRows as $row) {
        $year = date('Y', (int) $row['created']);
        $month = date('m', (int) $row['created']);
        $groups[$year][$month][] = $row;
    }
    krsort($groups);
    return $groups;
});
?>
<div class="post">
    <div class="post-container archive-page">
        <div class="post-title"><?php _e('归档'); ?></div>
<?php if (empty($groups)): ?>
            <p><?php _e('暂无文章'); ?></p>
<?php endif; ?>
<?php foreach ($groups as $year => $months): ?>
<?php
            $yearCount = 0;
            foreach ($months as $m => $mRows) {
                $yearCount += count($mRows);
            }
            krsort($months);
            ?>
            <div class="archive-year"><?php printf(_t('%d 年（共 %d 篇）'), $year, $yearCount); ?></div>
<?php foreach ($months as $month => $monthRows): ?>
                <ul class="archive-list">
<?php foreach ($monthRows as $row): ?>
<?php $permalink = \Typecho\Router::url('post', $row, $this->options->index); ?>
                        <li>
                            <a href="<?php echo lt_esc_attr($permalink); ?>"><?php echo lt_esc_html($row['title']); ?></a>
                            <time><?php echo date('Y-m-d', (int) $row['created']); ?></time>
                        </li>
<?php endforeach; ?>
                </ul>
<?php endforeach; ?>
<?php endforeach; ?>
    </div>
</div>
<?php $this->need('footer.php'); ?>
