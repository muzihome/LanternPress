<?php
declare(strict_types=1);

if (!defined('__TYPECHO_ROOT_DIR__')) exit;

// ============================================================
// nav.php — 导航菜单树：构建器 JSON 解析 / 分类·页面引用解析 / 桌面与移动端渲染
// 依赖：format.php（lt_text / lt_lines / lt_safe_url / lt_filter_nav_menu / lt_esc_html / lt_esc_attr）、
//      security.php（lt_log_error）
// ============================================================

// 导航菜单树最大层级（1 级顶栏 + 最多 2 级下拉/手风琴）
define('LT_NAV_MAX_DEPTH', 3);

/**
 * 导航菜单树解析（批次24）：构建器 JSON（navMenuData 配置）→ 受控树结构。
 * 节点格式：{t:custom|cat|page, n:显示名（可空，系统名兜底）, u:custom 链接, id:cat/page 目标 ID, c:子级数组}。
 * 严格白名单解析：类型白名单、深度与节点数受限、custom 链接过 lt_safe_url，异常数据静默丢弃。
 */
function lt_nav_tree_from_json(string $raw): array
{
    $raw = trim($raw);
    if ($raw === '' || strlen($raw) > 65536) {
        return [];
    }
    $data = json_decode($raw, true, 16);
    if (!is_array($data)) {
        return [];
    }
    $count = 0;
    return lt_nav_tree_nodes($data, 1, $count);
}

function lt_nav_tree_nodes(array $nodes, int $depth, int &$count): array
{
    if ($depth > LT_NAV_MAX_DEPTH || $count >= 100) {
        return [];
    }
    $tree = [];
    foreach ($nodes as $node) {
        if (!is_array($node) || $count >= 100) {
            continue;
        }
        $type = $node['t'] ?? '';
        $title = lt_text((string) ($node['n'] ?? ''));
        $item = null;
        if ($type === 'custom') {
            $url = lt_safe_url((string) ($node['u'] ?? ''));
            if ($url === '' || $title === '') {
                continue;
            }
            $item = ['title' => $title, 'permalink' => $url, 'ref' => ''];
        } elseif ($type === 'cat' || $type === 'page') {
            $id = (int) ($node['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $item = ['title' => $title, 'permalink' => '', 'ref' => $type . ':' . $id];
        } else {
            continue;
        }
        $count++;
        $item['children'] = lt_nav_tree_nodes(
            isset($node['c']) && is_array($node['c']) ? $node['c'] : [],
            $depth + 1,
            $count
        );
        $tree[] = $item;
    }
    return $tree;
}

/**
 * 批量解析分类/独立页面真实链接与名称（各一条全量 SQL，请求内静态缓存）。
 * 返回 ['cat' => [mid => ['title'=>..,'permalink'=>..]], 'page' => [cid => [...]]]。
 */
function lt_nav_resolve_targets(): array
{
    static $resolved = null;
    if (is_array($resolved)) {
        return $resolved;
    }
    $resolved = ['cat' => [], 'page' => []];
    try {
        // 类名与实例判断沿用 sidebar.php 已验证的 Typecho 1.2/1.3 兼容写法
        $cats = \Typecho\Widget::widget('Widget_Metas_Category_List@ltNavCats');
        if ($cats instanceof \Widget\Metas\Category\Rows) {
            while ($cats->next()) {
                $resolved['cat'][(int) $cats->mid] = [
                    'title' => lt_text($cats->name),
                    'permalink' => lt_text($cats->permalink),
                ];
            }
        }
    } catch (\Throwable $e) {
        lt_log_error('nav resolve categories failed', $e);
    }
    try {
        $pages = \Typecho\Widget::widget('\Widget\Contents\Page\Rows@ltNavPages');
        if ($pages instanceof \Widget\Contents\Page\Rows) {
            while ($pages->next()) {
                $resolved['page'][(int) $pages->cid] = [
                    'title' => lt_text($pages->title),
                    'permalink' => lt_text($pages->permalink),
                ];
            }
        }
    } catch (\Throwable $e) {
        lt_log_error('nav resolve pages failed', $e);
    }
    return $resolved;
}

/**
 * 树中是否存在 cat/page 引用节点（决定是否需要查询分类/页面数据）。
 */
function lt_nav_tree_has_refs(array $tree): bool
{
    foreach ($tree as $node) {
        if (($node['ref'] ?? '') !== '') {
            return true;
        }
        if (!empty($node['children']) && lt_nav_tree_has_refs($node['children'])) {
            return true;
        }
    }
    return false;
}

/**
 * 将树中的 cat/page 引用节点落到真实链接/名称；目标已被删除的节点连同其子树一并跳过。
 * 仅存在引用节点时才触发分类/页面查询（纯自定义链接树零额外 SQL）；空链接/空名称节点统一过滤。
 */
function lt_nav_resolve_tree(array $tree): array
{
    $targets = lt_nav_tree_has_refs($tree) ? lt_nav_resolve_targets() : [];
    $resolved = [];
    foreach ($tree as $node) {
        $ref = $node['ref'] ?? '';
        if ($ref !== '') {
            [$type, $id] = explode(':', $ref, 2);
            $hit = $targets[$type][(int) $id] ?? null;
            if ($hit === null) {
                continue;
            }
            $node['permalink'] = $hit['permalink'];
            if ($node['title'] === '') {
                $node['title'] = $hit['title'];
            }
        }
        if ($node['title'] === '' || $node['permalink'] === '') {
            continue;
        }
        $node['children'] = lt_nav_resolve_tree(is_array($node['children'] ?? null) ? $node['children'] : []);
        $resolved[] = $node;
    }
    return $resolved;
}

/**
 * 导航菜单主入口：主题配置 → 可渲染菜单树（已解析链接，请求内静态缓存）。
 * 数据源优先级：navMenuData（构建器 JSON 树）→ navMenu（名称|URL 平铺）→ 空树（header.php 回退自动列出独立页面）。
 */
function lt_nav_tree(object $options): array
{
    static $cache = null;
    if (is_array($cache)) {
        return $cache;
    }
    $tree = lt_nav_tree_from_json(lt_text($options->navMenuData ?? ''));
    if ($tree !== []) {
        $tree = lt_nav_resolve_tree($tree);
    }
    if ($tree === []) {
        $tree = [];
        foreach (lt_lines(lt_filter_nav_menu(lt_text($options->navMenu ?? ''))) as $line) {
            $parts = array_map('trim', explode('|', $line, 2));
            if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
                continue;
            }
            $url = lt_safe_url($parts[1]);
            if ($url === '') {
                continue;
            }
            $tree[] = ['title' => $parts[0], 'permalink' => $url, 'ref' => '', 'children' => []];
        }
    }
    $cache = $tree;
    return $cache;
}

/**
 * 桌面导航渲染：一级项平铺；有子级的项包 wrapper 并输出下拉子树（最多 LT_NAV_MAX_DEPTH 级）。
 * 有子级的一级/二级项在标题后输出箭头指示符（▾/▸），样式见 lantern.css 导航多级菜单块。
 */
function lt_nav_render_desktop(array $tree): string
{
    if ($tree === []) {
        return '';
    }
    $html = '';
    foreach ($tree as $node) {
        $children = is_array($node['children'] ?? null) ? $node['children'] : [];
        if ($children !== []) {
            $html .= '<div class="navbar-item-wrap has-sub">'
                . '<a class="navbar-item hover-line" href="' . lt_esc_attr($node['permalink']) . '" aria-haspopup="true">'
                . lt_esc_html($node['title'])
                . '<svg class="nav-arrow nav-arrow-down" viewBox="0 0 10 6" width="9" height="6" aria-hidden="true">'
                . '<path d="M1 1l4 4 4-4" fill="none" stroke="currentColor" stroke-width="1.6"/></svg>'
                . '</a>'
                . lt_nav_render_sub($children, 2)
                . '</div>';
        } else {
            $html .= '<a class="navbar-item hover-line" href="' . lt_esc_attr($node['permalink']) . '">'
                . lt_esc_html($node['title']) . '</a>';
        }
    }
    return $html;
}

function lt_nav_render_sub(array $nodes, int $depth): string
{
    if ($nodes === [] || $depth > LT_NAV_MAX_DEPTH) {
        return '';
    }
    $html = '<ul class="navbar-sub">';
    foreach ($nodes as $node) {
        $children = is_array($node['children'] ?? null) ? $node['children'] : [];
        $hasSub = $children !== [];
        $html .= '<li' . ($hasSub ? ' class="has-sub"' : '') . '>'
            . '<a href="' . lt_esc_attr($node['permalink']) . '">' . lt_esc_html($node['title'])
            . ($hasSub
                ? '<svg class="nav-arrow nav-arrow-right" viewBox="0 0 6 10" width="6" height="9" aria-hidden="true">'
                    . '<path d="M1 1l4 4-4 4" fill="none" stroke="currentColor" stroke-width="1.5"/></svg>'
                : '')
            . '</a>'
            . ($hasSub ? lt_nav_render_sub($children, $depth + 1) : '')
            . '</li>';
    }
    return $html . '</ul>';
}

/**
 * 移动端导航渲染：输出 <li> 序列（外层 ul#mobile-menu-list 由模板提供）；
 * 有子级的项追加展开按钮 + 嵌套 ul（手风琴），展开态由 lantern.core.js 管控 aria-expanded。
 */
function lt_nav_render_mobile(array $tree, int &$seq, int $depth = 1): string
{
    $html = '';
    foreach ($tree as $node) {
        $children = is_array($node['children'] ?? null) ? $node['children'] : [];
        $hasSub = $children !== [] && $depth < LT_NAV_MAX_DEPTH;
        $html .= '<li' . ($hasSub ? ' class="has-sub"' : '') . '>'
            . '<a href="' . lt_esc_attr($node['permalink']) . '">' . lt_esc_html($node['title']) . '</a>';
        if ($hasSub) {
            $seq++;
            $id = 'lt-mm-sub-' . $seq;
            $html .= '<button type="button" class="mobile-sub-toggle" data-target="' . $id . '"'
                . ' aria-expanded="false" aria-controls="' . $id . '" aria-label="展开子菜单">'
                . '<svg viewBox="0 0 10 6" width="10" height="6" aria-hidden="true"><path d="M1 1l4 4 4-4" fill="none" stroke="currentColor" stroke-width="1.5"/></svg>'
                . '</button>'
                . '<ul class="mobile-sub" id="' . $id . '">' . lt_nav_render_mobile($children, $seq, $depth + 1) . '</ul>';
        }
        $html .= '</li>';
    }
    return $html;
}
