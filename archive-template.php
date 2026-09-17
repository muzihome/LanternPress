<?php





declare(strict_types=1);

if (!defined('__TYPECHO_ROOT_DIR__')) exit;

$this->need('partials/header.php');


$cacheDir = __DIR__ . '/cache';
$cacheFile = $cacheDir . '/archive-cache.php';
$cachePrefix = '<?php exit; ?>';
$cacheTtl = 3600; 

$groups = null;
if (is_file($cacheFile)) {
    $data = @file_get_contents($cacheFile);
    if ($data !== false && strpos($data, $cachePrefix) === 0) {
        $payload = substr($data, strlen($cachePrefix));
        
        
        $sepPos = strpos($payload, '|');
        if ($sepPos !== false && is_numeric(substr($payload, 0, $sepPos))) {
            $timestamp = (int) substr($payload, 0, $sepPos);
            $encoded = substr($payload, $sepPos + 1);
            if (time() - $timestamp <= $cacheTtl) {
                $decoded = json_decode($encoded, true);
                if (!is_array($decoded)) {
                    
                    $decoded = @unserialize($encoded);
                }
                if (is_array($decoded)) {
                    $groups = $decoded;
                }
            }
        } else {
            
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

    
    try {
        if (!is_dir($cacheDir)) {
            @mkdir($cacheDir, 0755, true);
        }
        if (is_dir($cacheDir) && is_writable($cacheDir)) {
            @file_put_contents($cacheFile, $cachePrefix . time() . '|' . json_encode($groups, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        }
    } catch (\Throwable $e) {
        
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
<?php $this->need('partials/footer.php'); ?>
