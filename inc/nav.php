<?php
declare(strict_types=1);

if (!defined('__TYPECHO_ROOT_DIR__')) exit;








define('LT_NAV_MAX_DEPTH', 3);






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





function lt_nav_resolve_targets(): array
{
    static $resolved = null;
    if (is_array($resolved)) {
        return $resolved;
    }
    $resolved = ['cat' => [], 'page' => []];
    try {
        
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
