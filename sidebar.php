<?php
declare(strict_types=1);

if (!defined('__TYPECHO_ROOT_DIR__')) exit;

// ============================================================
// 文章页侧边栏（post.php / page.php 经 post-layout-wrapper 引入）
// 10 个模块：文章目录 / 搜索 / 微言 / 广告 / 分类 / 最新 / 热门 / 最新评论 / 标签云 / 站点信息
// 对齐设计稿 sidebar.html 类名；目录模块复用主题 initCatalog()（#catalog-directory 挂载）
// ============================================================

$sidebarMemos = trim(lt_text($this->options->sidebarMemos ?? ''));
$sidebarAdCode = trim(lt_text($this->options->sidebarAdCode ?? ''));
// 目录树开关：后台主题面板配置优先，文章自定义字段可单独覆盖（字段为空时回退后台配置）
$directoryField = lt_text($this->fields->directoryStatus ?? '');
$directoryOn = ($directoryField !== '' ? $directoryField : lt_text($this->options->directoryStatus ?? 'off')) === 'on';
$siteUrl = rtrim(lt_text($this->options->siteUrl), '/');

$sidebarSearchAction = $siteUrl . '/';

// 文章页侧边栏模块配置（后台「文章页侧边栏模块」多选；未配置或非数组时默认显示全部模块）
$sidebarPostModules = $this->options->sidebarPostModules ?? null;
$sidebarAllModules = ['catalog', 'search', 'memos', 'ad', 'category', 'recent', 'hot', 'comments', 'tag', 'siteinfo'];
if (is_array($sidebarPostModules)) {
    $sidebarActiveModules = array_values(array_intersect($sidebarAllModules, array_map('strval', $sidebarPostModules)));
} else {
    $sidebarActiveModules = $sidebarAllModules;
}
try {
    $resolvedSearch = \Typecho\Router::url('search', [], $this->options->index);
    if (is_string($resolvedSearch) && $resolvedSearch !== '') {
        $sidebarSearchAction = $resolvedSearch;
    }
} catch (\Throwable $e) { }

// ============================================================
// 侧边栏静态缓存（文件缓存，避免每页重复聚合查询）
// 热门文章 TTL 300s；站点信息 TTL 600s；缓存文件位于主题根目录 cache/sidebar_*.php，
// 内容以 PHP 退出守卫前缀（短开标签加 exit 语句）防直读；如需强制刷新可删除对应缓存文件
// ============================================================
$sidebarCacheDir = __DIR__ . '/cache';
$ltSidebarCache = static function (string $key, int $ttl, callable $load) use ($sidebarCacheDir) {
    $cacheFile = $sidebarCacheDir . '/sidebar_' . md5($key) . '.php';
    if (is_file($cacheFile) && time() - (int) @filemtime($cacheFile) < $ttl) {
        $raw = @file_get_contents($cacheFile);
        if ($raw !== false && strpos($raw, '<?php exit; ?>') === 0) {
            $decoded = json_decode(substr($raw, strlen('<?php exit; ?>')), true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }
    }
    $data = $load();
    if (is_array($data)) {
        if (!is_dir($sidebarCacheDir)) {
            @mkdir($sidebarCacheDir, 0755, true);
        }
        if (is_dir($sidebarCacheDir) && is_writable($sidebarCacheDir)) {
            $tmp = $cacheFile . '.tmp.' . bin2hex(random_bytes(4));
            if (@file_put_contents($tmp, '<?php exit; ?>' . json_encode($data, JSON_UNESCAPED_UNICODE), LOCK_EX) !== false) {
                @rename($tmp, $cacheFile);
            }
        }
    }
    return $data;
};
?>

<!-- 模块 1：文章目录 -->
<?php if ($directoryOn && in_array('catalog', $sidebarActiveModules, true)): ?>
<div class="sidebar-widget widget-catalog">
    <h3 class="widget-title"><?php _e('文章目录'); ?></h3>
    <div class="catalog-container" id="catalog">
        <div class="catalog-panel-header">
            <span class="catalog-panel-title"><?php _e('文章目录'); ?></span>
            <button type="button" class="catalog-panel-close" aria-label="<?php echo lt_esc_attr(_t('关闭目录')); ?>">×</button>
        </div>
        <div class="catalog-directory" id="catalog-directory"></div>
    </div>
</div>
<?php endif; ?>

<?php if (in_array('search', $sidebarActiveModules, true)): ?>
<!-- 模块 2：搜索框 -->
<div class="sidebar-widget widget-search">
    <form class="sidebar-search-form" action="<?php echo lt_esc_attr($sidebarSearchAction); ?>" method="get" role="search">
        <input type="text" name="s" class="sidebar-search-input" placeholder="<?php echo lt_esc_attr(_t('输入关键词搜索...')); ?>" aria-label="<?php echo lt_esc_attr(_t('搜索关键词')); ?>">
        <button type="submit" class="sidebar-search-btn"><?php _e('搜索'); ?></button>
    </form>
</div>

<?php endif; ?>
<!-- 模块 3：微言时间轴 -->
<?php if ($sidebarMemos !== '' && in_array('memos', $sidebarActiveModules, true)): ?>
<div class="sidebar-widget widget-timeline">
    <h3 class="widget-title"><?php _e('微言'); ?></h3>
    <ul class="timeline-list">
<?php foreach (lt_lines($sidebarMemos) as $memoLine): ?>
<?php
            $memoDate = '';
            $memoText = trim($memoLine);
            if ($memoText === '') {
                continue;
            }
            if (str_contains($memoText, '|')) {
                $memoParts = explode('|', $memoText, 2);
                $memoDate = trim($memoParts[0]);
                $memoText = trim($memoParts[1]);
            }
            if ($memoText === '') {
                continue;
            }
            ?>
            <li>
<?php if ($memoDate !== ''): ?>
                    <div class="timeline-date"><?php echo lt_esc_html($memoDate); ?></div>
<?php endif; ?>
                <div class="timeline-content"><?php echo lt_esc_html($memoText); ?></div>
            </li>
<?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<!-- 模块 4：广告位（配置 sidebarAdCode 后显示） -->
<?php if ($sidebarAdCode !== '' && in_array('ad', $sidebarActiveModules, true)): ?>
<div class="sidebar-widget widget-ad">
    <h3 class="widget-title"><?php _e('推荐'); ?></h3>
    <div class="sidebar-ad-content"><?php echo $sidebarAdCode; ?></div>
</div>
<?php endif; ?>

<?php
$sidebarCategories = null;
if (in_array('category', $sidebarActiveModules, true)) {
    $this->widget('Widget_Metas_Category_List@sidebarCategory', ['limit' => 12])->to($sidebarCategories);
}
if ($sidebarCategories instanceof \Widget\Contents\Category\Rows && $sidebarCategories->have()): ?>
<!-- 模块 5：分类目录 -->
<div class="sidebar-widget widget-category">
    <h3 class="widget-title"><?php _e('分类目录'); ?></h3>
    <ul class="widget-list">
<?php while ($sidebarCategories->next()): ?>
            <li>
                <a href="<?php $sidebarCategories->permalink(); ?>"><?php $sidebarCategories->name(); ?><span class="widget-count"><?php $sidebarCategories->count(); ?></span></a>
            </li>
<?php endwhile; ?>
    </ul>
</div>

<?php endif; ?>
<?php
$sidebarRecentPosts = null;
if (in_array('recent', $sidebarActiveModules, true)) {
    $this->widget('Widget_Contents_Post_Recent@sidebarRecent', ['pageSize' => 5])->to($sidebarRecentPosts);
}
if ($sidebarRecentPosts instanceof \Widget\Contents\Post\Recent && $sidebarRecentPosts->have()): ?>
<!-- 模块 6：最新文章 -->
<div class="sidebar-widget widget-recent">
    <h3 class="widget-title"><?php _e('最新文章'); ?></h3>
    <ul class="widget-list">
<?php while ($sidebarRecentPosts->next()): ?>
            <li>
                <a href="<?php $sidebarRecentPosts->permalink(); ?>"><?php $sidebarRecentPosts->title(); ?></a>
                <span class="widget-date"><?php $sidebarRecentPosts->date('m-d'); ?></span>
            </li>
<?php endwhile; ?>
    </ul>
</div>

<?php endif; ?>
<?php
$sidebarHot = [];
if (in_array('hot', $sidebarActiveModules, true)) {
    $sidebarHot = $ltSidebarCache('hot_top5', 300, function () {
        $rows = [];
        try {
            $db = \Typecho\Db::get();
            $sidebarHotRows = $db->fetchAll(
                $db->select('table.contents.cid', 'table.contents.type', 'table.contents.title', 'table.contents.created', 'table.contents.slug', 'table.fields.int_value')
                    ->from('table.contents')
                    ->join('table.fields', "table.contents.cid = table.fields.cid AND table.fields.name = 'ltViews'", \Typecho\Db::LEFT_JOIN)
                    ->where('table.contents.type = ? AND table.contents.status = ?', 'post', 'publish')
                    ->order('table.fields.int_value', \Typecho\Db::SORT_DESC)
                    ->order('table.contents.created', \Typecho\Db::SORT_DESC)
                    ->limit(5)
            );
            foreach ((array) $sidebarHotRows as $sidebarHotRow) {
                $sidebarHotRow = $this->filter($sidebarHotRow);
                $sidebarHotRow['permalink'] = \Typecho\Router::url(($sidebarHotRow['type'] ?? 'post') === 'page' ? 'page' : 'post', $sidebarHotRow, $this->options->index);
                $rows[] = $sidebarHotRow;
            }
        } catch (\Throwable $e) {
            $rows = [];
        }
        return $rows;
    });
}
if (!empty($sidebarHot)): ?>
<!-- 模块 7：热门文章（按阅读量 ltViews 排序，带排名） -->
<div class="sidebar-widget widget-hot">
    <h3 class="widget-title"><?php _e('热门文章'); ?></h3>
    <ul class="widget-hot-list">
<?php foreach ($sidebarHot as $sidebarHotIndex => $sidebarHotItem): ?>
<?php
            $sidebarHotRank = $sidebarHotIndex + 1;
            $sidebarHotViews = (int) ($sidebarHotItem['int_value'] ?? 0);
            $sidebarHotViewsText = $sidebarHotViews >= 10000
                ? number_format($sidebarHotViews / 10000, 1) . 'w'
                : ($sidebarHotViews >= 1000 ? number_format($sidebarHotViews / 1000, 1) . 'k' : (string) $sidebarHotViews);
            ?>
            <li>
                <span class="hot-rank <?php echo $sidebarHotRank <= 3 ? 'rank-top' : ''; ?>"><?php echo $sidebarHotRank; ?></span>
                <a href="<?php echo lt_esc_attr($sidebarHotItem['permalink'] ?? '#'); ?>"><?php echo lt_esc_html($sidebarHotItem['title'] ?? ''); ?></a>
                <span class="hot-views"><?php echo lt_esc_html($sidebarHotViewsText); ?></span>
            </li>
<?php endforeach; ?>
    </ul>
</div>

<?php endif; ?>
<?php
$sidebarComments = null;
if (in_array('comments', $sidebarActiveModules, true)) {
    try {
        $this->widget('Widget_Comments_Recent@sidebarComments', ['pageSize' => 3])->to($sidebarComments);
    } catch (\Throwable $e) {
        lt_log_error('lt_sidebar comments widget failed', $e);
        $sidebarComments = null;
    }
}
if (is_object($sidebarComments) && $sidebarComments->have()): ?>
<!-- 模块 8：最新评论 -->
<div class="sidebar-widget widget-comments">
    <h3 class="widget-title"><?php _e('最新评论'); ?></h3>
    <ul class="widget-comment-list">
<?php while ($sidebarComments->next()): ?>
<?php
            // 防御：评论所属文章不可用时（如导入无评论段备份后旧评论残留、cid 错配），
            // parentContent 为空 Contents，permalink 生成会抛 Router::url(null) TypeError，
            // 这里跳过该条而非让整个页面 500（Typecho 1.3.0 导入机制不清空备份外表的已知缺口）
            try {
                $commentPermalink = (string) $sidebarComments->permalink;
            } catch (\Throwable $e) {
                continue;
            }
            $commentTitle = (string) $sidebarComments->title;
            ?>
            <li>
                <span class="comment-user"><?php $sidebarComments->author(); ?></span>
                <span class="comment-text"><?php $sidebarComments->excerpt(32); ?></span>
                <a href="<?php echo lt_esc_attr($commentPermalink); ?>" class="comment-on"><?php echo lt_esc_html($commentTitle); ?></a>
            </li>
<?php endwhile; ?>
    </ul>
</div>

<?php endif; ?>
<?php
$sidebarTagRows = [];
if (in_array('tag', $sidebarActiveModules, true)) {
    try {
        $this->widget('Widget_Metas_Tag_Cloud@sidebarTag', ['ignoreZeroCount' => 1, 'limit' => 24])->to($sidebarTags);
    } catch (\Throwable $e) {
        lt_log_error('lt_sidebar tagcloud widget failed', $e);
        $sidebarTags = null;
    }
    if (is_object($sidebarTags) && $sidebarTags->have()) {
        while ($sidebarTags->next()) {
            $sidebarTagRows[] = [
                'permalink' => lt_text($sidebarTags->permalink),
                'name' => lt_text($sidebarTags->name),
                'count' => (int) $sidebarTags->count
            ];
        }
    }
    $sidebarTagMax = 1;
    foreach ($sidebarTagRows as $sidebarTagRow) {
        $sidebarTagMax = max($sidebarTagMax, $sidebarTagRow['count']);
    }
}
if (!empty($sidebarTagRows)): ?>
<!-- 模块 9：标签云 -->
<div class="sidebar-widget widget-tag">
    <h3 class="widget-title"><?php _e('标签云'); ?></h3>
    <div class="tagcloud">
<?php foreach ($sidebarTagRows as $sidebarTagRow): ?>
<?php
            $sidebarTagRatio = $sidebarTagMax > 0 ? $sidebarTagRow['count'] / $sidebarTagMax : 0;
            if ($sidebarTagRatio >= 0.8) {
                $sidebarTagLevel = 5;
            } elseif ($sidebarTagRatio >= 0.6) {
                $sidebarTagLevel = 4;
            } elseif ($sidebarTagRatio >= 0.4) {
                $sidebarTagLevel = 3;
            } elseif ($sidebarTagRatio >= 0.2) {
                $sidebarTagLevel = 2;
            } else {
                $sidebarTagLevel = 1;
            }
            ?>
            <a class="tag-item tag-lv<?php echo $sidebarTagLevel; ?>" href="<?php echo lt_esc_attr($sidebarTagRow['permalink']); ?>"><?php echo lt_esc_html($sidebarTagRow['name']); ?><span class="tag-count"><?php echo $sidebarTagRow['count']; ?></span></a>
<?php endforeach; ?>
    </div>
</div>

<?php endif; ?>
<?php if (in_array('siteinfo', $sidebarActiveModules, true)): ?>
<!-- 模块 10：站点信息 -->
<div class="sidebar-widget widget-siteinfo">
    <h3 class="widget-title"><?php _e('站点信息'); ?></h3>
<?php
    $sidebarStats = $ltSidebarCache('siteinfo', 600, function () {
        $stats = ['posts' => 0, 'comments' => 0, 'categories' => 0, 'tags' => 0, 'days' => 0, 'updated' => 0];
        try {
            $db = \Typecho\Db::get();
            $sidebarStatObj = $db->fetchObject($db->select(['COUNT(cid)' => 'num'])->from('table.contents')->where('type = ? AND status = ?', 'post', 'publish'));
            $stats['posts'] = $sidebarStatObj ? (int) $sidebarStatObj->num : 0;
            $sidebarStatObj = $db->fetchObject($db->select(['COUNT(cid)' => 'num'])->from('table.comments')->where('status = ?', 'approved'));
            $stats['comments'] = $sidebarStatObj ? (int) $sidebarStatObj->num : 0;
            $sidebarStatObj = $db->fetchObject($db->select(['COUNT(mid)' => 'num'])->from('table.metas')->where('type = ?', 'category'));
            $stats['categories'] = $sidebarStatObj ? (int) $sidebarStatObj->num : 0;
            $sidebarStatObj = $db->fetchObject($db->select(['COUNT(mid)' => 'num'])->from('table.metas')->where('type = ?', 'tag'));
            $stats['tags'] = $sidebarStatObj ? (int) $sidebarStatObj->num : 0;
            $sidebarFirst = $db->fetchRow($db->select('created')->from('table.contents')->where('type = ? AND status = ?', 'post', 'publish')->order('created', \Typecho\Db::SORT_ASC)->limit(1));
            $sidebarFirstCreated = (int) ($sidebarFirst['created'] ?? 0);
            $stats['days'] = $sidebarFirstCreated > 0 ? max(1, (int) floor((time() - $sidebarFirstCreated) / 86400)) : 0;
            $sidebarLast = $db->fetchRow($db->select('modified')->from('table.contents')->where('type = ? AND status = ?', 'post', 'publish')->order('modified', \Typecho\Db::SORT_DESC)->limit(1));
            $stats['updated'] = (int) ($sidebarLast['modified'] ?? 0);
        } catch (\Throwable $e) { }
        return $stats;
    });
    ?>
    <ul class="widget-siteinfo-list">
        <li><span class="info-label"><?php _e('文章总数'); ?></span><span class="info-value"><?php echo $sidebarStats['posts']; ?> <?php _e('篇'); ?></span></li>
        <li><span class="info-label"><?php _e('评论总数'); ?></span><span class="info-value"><?php echo $sidebarStats['comments']; ?> <?php _e('条'); ?></span></li>
        <li><span class="info-label"><?php _e('分类数目'); ?></span><span class="info-value"><?php echo $sidebarStats['categories']; ?> <?php _e('个'); ?></span></li>
        <li><span class="info-label"><?php _e('标签数目'); ?></span><span class="info-value"><?php echo $sidebarStats['tags']; ?> <?php _e('个'); ?></span></li>
        <li><span class="info-label"><?php _e('运行天数'); ?></span><span class="info-value"><?php echo number_format($sidebarStats['days']); ?> <?php _e('天'); ?></span></li>
        <li><span class="info-label"><?php _e('最后更新'); ?></span><span class="info-value"><?php echo $sidebarStats['updated'] > 0 ? date('Y-m-d', $sidebarStats['updated']) : '—'; ?></span></li>
    </ul>
</div>

<?php endif; ?>