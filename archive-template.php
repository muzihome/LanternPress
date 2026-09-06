<?php
/**
 * 归档
 *
 * @package custom
 */
declare(strict_types=1);

if (!defined('__TYPECHO_ROOT_DIR__')) exit;

$this->need('public/header.php');

// F3：仅查询所需列（不拉正文全文），并用轻量文件缓存降低重复访问开销；缓存不可用则直接查询
$cacheDir = __DIR__ . '/cache';
$cacheFile = $cacheDir . '/archive-cache.php';
$cachePrefix = '<?php exit; ?>';
$cacheTtl = 3600; // A2：缓存有效期 1 小时，避免文章更新后归档长期不刷新

$groups = null;
if (is_file($cacheFile)) {
    $data = @file_get_contents($cacheFile);
    if ($data !== false && strpos($data, $cachePrefix) === 0) {
        $payload = substr($data, strlen($cachePrefix));
        // A2：兼容带时间戳的新格式（时间戳|序列化数据）与旧格式（仅序列化数据）
        $sepPos = strpos($payload, '|');
        if ($sepPos !== false && is_numeric(substr($payload, 0, $sepPos))) {
            $timestamp = (int) substr($payload, 0, $sepPos);
            $serialized = substr($payload, $sepPos + 1);
            if (time() - $timestamp <= $cacheTtl) {
                $decoded = @unserialize($serialized);
                if (is_array($decoded)) {
                    $groups = $decoded;
                }
            }
        } else {
            // 旧格式无时间戳，直接使用（下次写入会升级为新格式）
            $decoded = @unserialize($payload);
            if (is_array($decoded)) {
                $groups = $decoded;
            }
        }
    }
}

if ($groups === null) {
    $archiveRows = [];
    try {
        $db = \Typecho\Db::get();
        $archiveRows = $db->fetchAll(
            $db->select('cid', 'title', 'created', 'slug')
                ->from('table.contents')
                ->where('type = ?', 'post')
                ->where('status = ?', 'publish')
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

    // 写入缓存（exit 守卫 + 时间戳 + 目录权限兜底，失败不影响渲染）
    try {
        if (!is_dir($cacheDir)) {
            @mkdir($cacheDir, 0755, true);
        }
        if (is_dir($cacheDir) && is_writable($cacheDir)) {
            @file_put_contents($cacheFile, $cachePrefix . time() . '|' . serialize($groups));
        }
    } catch (\Throwable $e) {
        // 忽略缓存写入失败
    }
}
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
<?php $this->need('public/footer.php'); ?>
