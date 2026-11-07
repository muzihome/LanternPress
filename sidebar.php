<?php
declare(strict_types=1);

if (!defined('__TYPECHO_ROOT_DIR__')) exit;








$sidebarMemos = trim(lt_text($this->options->sidebarMemos ?? ''));
$sidebarAdCode = trim(lt_safe_ad_html(lt_text($this->options->sidebarAdCode ?? '')));

$directoryField = lt_text($this->fields->directoryStatus ?? '');
$directoryOn = ($directoryField !== '' ? $directoryField : lt_text($this->options->directoryStatus ?? 'off')) === 'on';
$siteUrl = rtrim(lt_text($this->options->siteUrl), '/');

$sidebarSearchAction = $siteUrl . '/';





$sidebarAllModules = ['catalog', 'search', 'memos', 'ad', 'category', 'recent', 'hot', 'comments', 'tag', 'siteinfo'];
if (isset($sidebarForceModules) && is_array($sidebarForceModules)) {
    $sidebarActiveModules = array_values(array_intersect($sidebarAllModules, $sidebarForceModules));
} else {
    $sidebarModulesOpt = null;
    if ($this->is('page')) {
        $sidebarModulesOpt = $this->options->sidebarPageModules ?? null;
        if (!is_array($sidebarModulesOpt)) {
            $sidebarModulesOpt = $this->options->sidebarPostModules ?? null;
        }
    } else {
        $sidebarModulesOpt = $this->options->sidebarPostModules ?? null;
    }
    if (is_array($sidebarModulesOpt)) {
        $sidebarActiveModules = array_values(array_intersect($sidebarAllModules, array_map('strval', $sidebarModulesOpt)));
    } else {
        $sidebarActiveModules = $sidebarAllModules;
    }
}
try {
    $resolvedSearch = \Typecho\Router::url('search', [], $this->options->index);
    if (is_string($resolvedSearch) && $resolvedSearch !== '') {
        $sidebarSearchAction = $resolvedSearch;
    }
} catch (\Throwable $e) { }






?>


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

<div class="sidebar-widget widget-search">
    <form class="sidebar-search-form" action="<?php echo lt_esc_attr($sidebarSearchAction); ?>" method="get" role="search">
        <input type="text" name="s" class="sidebar-search-input" placeholder="<?php echo lt_esc_attr(_t('输入关键词搜索...')); ?>" aria-label="<?php echo lt_esc_attr(_t('搜索关键词')); ?>">
        <button type="submit" class="sidebar-search-btn"><?php _e('搜索'); ?></button>
    </form>
</div>

<?php endif; ?>

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


<?php if ($sidebarAdCode !== '' && in_array('ad', $sidebarActiveModules, true)): ?>
<div class="sidebar-widget widget-ad">
    <h3 class="widget-title"><?php _e('推荐'); ?></h3>
    <div class="sidebar-ad-content"><?php echo lt_add_nonce_to_scripts($sidebarAdCode, lt_generate_csp_nonce()); ?></div>
</div>
<?php endif; ?>

<?php



$sidebarCategoriesHtml = '';
if (in_array('category', $sidebarActiveModules, true)) {
    $sidebarCategoriesHtml = lt_cache_remember('sidebar', 'category12', 600, function () {
        $rows = null;
        $this->widget('Widget_Metas_Category_List@sidebarCategory', ['limit' => 12])->to($rows);
        if (!($rows instanceof \Widget\Metas\Category\Rows) || !$rows->have()) {
            return '';
        }
        ob_start();
        while ($rows->next()) {
            echo '<li><a href="';
            $rows->permalink();
            echo '">';
            $rows->name();
            echo '<span class="widget-count">';
            $rows->count();
            echo '</span></a></li>';
        }
        return (string) ob_get_clean();
    });
}
if (is_string($sidebarCategoriesHtml) && $sidebarCategoriesHtml !== ''): ?>

<div class="sidebar-widget widget-category">
    <h3 class="widget-title"><?php _e('分类目录'); ?></h3>
    <ul class="widget-list"><?php echo $sidebarCategoriesHtml; ?></ul>
</div>

<?php endif; ?>
<?php

$sidebarRecentHtml = '';
if (in_array('recent', $sidebarActiveModules, true)) {
    $sidebarRecentHtml = lt_cache_remember('sidebar', 'recent5', 300, function () {
        $rows = null;
        $this->widget('Widget_Contents_Post_Recent@sidebarRecent', ['pageSize' => 5])->to($rows);
        if (!($rows instanceof \Widget\Contents\Post\Recent) || !$rows->have()) {
            return '';
        }
        ob_start();
        while ($rows->next()) {
            echo '<li><a href="';
            $rows->permalink();
            echo '">';
            $rows->title();
            echo '</a>';
            echo "\n                ";
            echo '<span class="widget-date">';
            $rows->date('m-d');
            echo '</span></li>';
        }
        return (string) ob_get_clean();
    });
}
if (is_string($sidebarRecentHtml) && $sidebarRecentHtml !== ''): ?>

<div class="sidebar-widget widget-recent">
    <h3 class="widget-title"><?php _e('最新文章'); ?></h3>
    <ul class="widget-list"><?php echo $sidebarRecentHtml; ?></ul>
</div>

<?php endif; ?>
<?php
$sidebarHot = [];
if (in_array('hot', $sidebarActiveModules, true)) {
    $sidebarHot = lt_cache_remember('sidebar', 'hot_top5', 300, function () {
        $rows = [];
        try {
            $db = \Typecho\Db::get();
            $sidebarHotRows = $db->fetchAll(
                $db->select('table.contents.cid', 'table.contents.type', 'table.contents.title', 'table.contents.created', 'table.contents.slug', 'table.fields.int_value')
                    ->from('table.contents')
                    ->join('table.fields', "table.contents.cid = table.fields.cid AND table.fields.name = 'ltViews'", \Typecho\Db::LEFT_JOIN)
                    ->where('table.contents.type = ? AND table.contents.status = ?', 'post', 'publish')
                    ->where('table.contents.created <= ?', time())
                    ->where('table.contents.password IS NULL OR table.contents.password = ?', '')
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



$sidebarCommentsHtml = '';
if (in_array('comments', $sidebarActiveModules, true)) {
    $sidebarCommentsHtml = lt_cache_remember('sidebar', 'comments3', 120, function () {
        try {
            $rows = null;
            $this->widget('Widget_Comments_Recent@sidebarComments', ['pageSize' => 3])->to($rows);
        } catch (\Throwable $e) {
            lt_log_error('lt_sidebar comments widget failed', $e);
            return '';
        }
        if (!is_object($rows) || !$rows->have()) {
            return '';
        }
        ob_start();
        while ($rows->next()) {
            
            
            if (str_starts_with((string) $rows->text, '[flink-apply]')) {
                continue;
            }
            
            
            try {
                $commentPermalink = (string) $rows->permalink;
            } catch (\Throwable $e) {
                continue;
            }
            $commentTitle = (string) $rows->title;
            echo '<li><span class="comment-user">';
            $rows->author();
            echo '</span><span class="comment-text">';
            $rows->excerpt(32);
            echo '</span><a href="' . lt_esc_attr($commentPermalink) . '" class="comment-on">' . lt_esc_html($commentTitle) . '</a></li>';
        }
        return (string) ob_get_clean();
    });
}
if (is_string($sidebarCommentsHtml) && $sidebarCommentsHtml !== ''): ?>

<div class="sidebar-widget widget-comments">
    <h3 class="widget-title"><?php _e('最新评论'); ?></h3>
    <ul class="widget-comment-list"><?php echo $sidebarCommentsHtml; ?></ul>
</div>

<?php endif; ?>
<?php

$sidebarTagRows = [];
if (in_array('tag', $sidebarActiveModules, true)) {
    $sidebarTagRows = lt_cache_remember('sidebar', 'tag24', 600, function () {
        $rows = [];
        try {
            $tags = null;
            $this->widget('Widget_Metas_Tag_Cloud@sidebarTag', ['ignoreZeroCount' => 1, 'limit' => 24])->to($tags);
        } catch (\Throwable $e) {
            lt_log_error('lt_sidebar tagcloud widget failed', $e);
            return [];
        }
        if (is_object($tags) && $tags->have()) {
            while ($tags->next()) {
                $rows[] = [
                    'permalink' => lt_text($tags->permalink),
                    'name' => lt_text($tags->name),
                    'count' => (int) $tags->count
                ];
            }
        }
        return $rows;
    });
    if (!is_array($sidebarTagRows)) {
        $sidebarTagRows = [];
    }
}
if (!empty($sidebarTagRows)):
    $sidebarTagMax = 1;
    foreach ($sidebarTagRows as $sidebarTagRow) {
        $sidebarTagMax = max($sidebarTagMax, (int) ($sidebarTagRow['count'] ?? 0));
    }
    ?>

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

<div class="sidebar-widget widget-siteinfo">
    <h3 class="widget-title"><?php _e('站点信息'); ?></h3>
<?php
    $sidebarStats = lt_cache_remember('sidebar', 'siteinfo', 600, function () {
        $stats = ['posts' => 0, 'comments' => 0, 'categories' => 0, 'tags' => 0, 'days' => 0, 'updated' => 0];
        try {
            $db = \Typecho\Db::get();
            $sidebarStatObj = $db->fetchObject($db->select(['COUNT(cid)' => 'num'])->from('table.contents')->where('type = ? AND status = ?', 'post', 'publish'));
            $stats['posts'] = $sidebarStatObj ? (int) $sidebarStatObj->num : 0;
            $sidebarStatObj = $db->fetchObject($db->select(['COUNT(cid)' => 'num'])->from('table.comments')->where('status = ? AND text NOT LIKE ?', 'approved', '[flink-apply]%'));
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