<?php
declare(strict_types=1);

if (!defined('__TYPECHO_ROOT_DIR__')) exit;

// ============================================================
// admin-panel.php — 后台「设置外观」增强：配置项 TAB 分组 + 导航菜单构建器注入
// （admin/footer.php 钩子；LT_ASSET_VERSION / LT_NAV_MAX_DEPTH / lt_esc_attr 经 core.php 加载链可用）
// ============================================================

// ===== 后台「设置外观」配置分组（TAB 选项卡）=====
// 原理：Typecho 表单元素渲染为平铺 ul.typecho-option，保存端按 name 平铺取参（DOM 位置不影响保存）；
// 通过 admin/footer.php 插件钩子在「设置外观」页注入脚本，将表单项按组重组为 TAB 选项卡（零内核改动、渐进增强）。
\Typecho\Plugin::factory('admin/footer.php')->end = static function (): void {
    $ltUri = (string) ($_SERVER['REQUEST_URI'] ?? '');
    if (strpos($ltUri, 'options-theme.php') === false) {
        return;
    }

    // 分组映射（组名 => 字段名，顺序与 themeConfig 定义一致）
    $ltGroups = [
        '站点基础信息' => ['logoUrl', 'posterLogo', 'shortcutIcon', 'recordNum', 'policeRecordNum', 'avatarProxy'],
        '首页展示' => ['navMenu', 'navMenuData', 'cIdRecommend', 'indexThumbs', 'greyImg', 'turnPageType', 'themeMode'],
        '文章阅读体验' => ['postLayout', 'postFoldEnable', 'postFoldThreshold', 'postFoldHeight', 'postFoldMinRatio'],
        '侧边栏' => ['sidebarPostModules', 'sidebarPageModules', 'sidebarIndexModules', 'sidebarCategoryModules', 'sidebarMemos', 'sidebarAdCode'],
        '文章页组件' => ['showCopyright', 'writerIntro', 'selfIntro', 'rewardUrl', 'postAdSlot', 'socialLink', 'posterProxyHosts'],
        '页脚与代码' => ['footerText', 'customCss', 'customJs', 'statisticsCode'],
    ];
    $ltJson = json_encode($ltGroups, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    echo '<style id="lt-cfg-group-css">'
        . '.lt-tabs{display:flex;flex-wrap:wrap;gap:4px;margin:0 0 -1px;padding:0 0 0 6px;}'
        . '.lt-tab{-webkit-appearance:none;appearance:none;border:1px solid #d9d2c7;border-bottom:none;background:#faf7f0;color:#555;font-size:14px;font-weight:600;padding:9px 18px;cursor:pointer;border-radius:6px 6px 0 0;margin-right:4px;}'
        . '.lt-tab:hover{background:#f3ede1;color:#333;}'
        . '.lt-tab.lt-active{background:#fff;color:#a65b4a;border-bottom-color:#fff;position:relative;top:1px;}'
        . '.lt-tab-panel{display:none;padding:6px 16px 14px;border:1px solid #d9d2c7;border-radius:0 6px 6px 6px;background:#fff;margin:0 0 16px;}'
        . '.lt-tab-panel.lt-active{display:block;}'
        . '.lt-tab-panel ul.typecho-option{margin-left:0;}'
        . '</style>'
        . '<script id="lt-cfg-group-js">'
        . '(function () {'
        . 'var groups = ' . $ltJson . ';'
        . 'var form = document.querySelector("form[action*=\"themes-edit\"]");'
        . 'if (!form) { return; }'
        . 'var nameToGroup = {};'
        . 'Object.keys(groups).forEach(function (g) { groups[g].forEach(function (n) { nameToGroup[n] = g; }); });'
        . 'var order = Object.keys(groups);'
        . 'var panels = {};'
        . 'var tabbar = document.createElement("div");'
        . 'tabbar.className = "lt-tabs";'
        . 'tabbar.setAttribute("role", "tablist");'
        . 'form.insertBefore(tabbar, form.firstChild);'
        . 'var anchor = tabbar;'
        . 'order.forEach(function (g, gi) {'
        . 'var panel = document.createElement("div");'
        . 'panel.className = "lt-tab-panel";'
        . 'panel.id = "lt-cfg-panel-" + gi;'
        . 'panel.style.display = "none";'
        . 'panel.setAttribute("role", "tabpanel");'
        . 'form.insertBefore(panel, anchor.nextSibling);'
        . 'anchor = panel;'
        . 'panels[g] = panel;'
        . 'var tab = document.createElement("button");'
        . 'tab.type = "button";'
        . 'tab.className = "lt-tab";'
        . 'tab.textContent = g;'
        . 'tab.setAttribute("role", "tab");'
        . 'tab.setAttribute("aria-selected", "false");'
        . 'tab.setAttribute("aria-controls", "lt-cfg-panel-" + gi);'
        . 'tabbar.appendChild(tab);'
        . 'tab.addEventListener("click", function () {'
        . 'order.forEach(function (o) {'
        . 'var on = (o === g);'
        . 'panels[o].style.display = on ? "" : "none";'
        . 'panels[o].classList.toggle("lt-active", on);'
        . '});'
        . 'tabbar.querySelectorAll(".lt-tab").forEach(function (t) {'
        . 't.classList.toggle("lt-active", t === tab);'
        . 't.setAttribute("aria-selected", t === tab ? "true" : "false");'
        . '});'
        . '});'
        . '});'
        . 'tabbar.addEventListener("keydown", function (e) {'
        . 'if (e.key !== "ArrowRight" && e.key !== "ArrowLeft") { return; }'
        . 'var tabsArr = Array.prototype.slice.call(tabbar.querySelectorAll(".lt-tab"));'
        . 'var idx = tabsArr.indexOf(document.activeElement);'
        . 'if (idx === -1) { return; }'
        . 'e.preventDefault();'
        . 'var next = e.key === "ArrowRight" ? (idx + 1) % tabsArr.length : (idx - 1 + tabsArr.length) % tabsArr.length;'
        . 'tabsArr[next].click();'
        . 'tabsArr[next].focus();'
        . '});'
        . 'var uls = form.querySelectorAll("ul.typecho-option");'
        . 'uls.forEach(function (ul) {'
        . 'var input = ul.querySelector("input[name], select[name], textarea[name]");'
        . 'if (!input) { return; }'
        . 'var name = input.getAttribute("name");'
        . 'if (!name) { return; }'
        . 'name = name.replace(/\[\]$/, "");'
        . 'var g = nameToGroup[name];'
        . 'if (!g) { return; }'
        . 'panels[g].appendChild(ul);'
        . '});'
        . 'var firstTab = tabbar.querySelector(".lt-tab");'
        . 'if (firstTab) { firstTab.click(); }'
        . '})();'
        . '</script>';

    // ===== 批次24：导航菜单构建器注入 =====
    // 与 TAB 分组共用同一钩子闭包（Plugin 赋值式注册二次赋值会覆盖前一个）。
    // 数据源：后台直查全部分类/独立页面（沿用 sidebar.php 已验证的兼容写法），
    // 查询失败时降级为「仅自定义链接」模式；构建器 JS 读写隐藏字段 navMenuData。
    $ltNavSources = ['cats' => [], 'pages' => []];
    try {
        $ltBuilderCats = \Typecho\Widget::widget('Widget_Metas_Category_List@ltNavBuilder');
        if ($ltBuilderCats instanceof \Widget\Metas\Category\Rows) {
            while ($ltBuilderCats->next()) {
                $ltNavSources['cats'][] = [
                    'mid' => (int) $ltBuilderCats->mid,
                    'name' => (string) $ltBuilderCats->name,
                    'parent' => (int) $ltBuilderCats->parent,
                ];
            }
        }
    } catch (\Throwable $e) {
        // 数据源不可用时不阻断后台页面，构建器自动降级
    }
    try {
        $ltBuilderPages = \Typecho\Widget::widget('\Widget\Contents\Page\Rows@ltNavBuilder');
        if ($ltBuilderPages instanceof \Widget\Contents\Page\Rows) {
            while ($ltBuilderPages->next()) {
                $ltNavSources['pages'][] = [
                    'cid' => (int) $ltBuilderPages->cid,
                    'title' => (string) $ltBuilderPages->title,
                ];
            }
        }
    } catch (\Throwable $e) {
    }
    $ltThemeUrl = '';
    try {
        $ltThemeUrl = (string) \Helper::options()->themeUrl;
    } catch (\Throwable $e) {
    }
    if ($ltThemeUrl !== '') {
        $ltNavEnv = json_encode(['sources' => $ltNavSources, 'themeUrl' => $ltThemeUrl, 'maxDepth' => LT_NAV_MAX_DEPTH], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        // 防 </script> 提前闭合（JSON 内 \/ 转义等价）
        $ltNavEnv = str_replace('</', '<\/', (string) $ltNavEnv);
        echo '<style id="lt-navbuilder-css">'
            . '.lt-nb{border:1px solid #d9d2c7;border-radius:6px;background:#faf7f0;padding:12px 14px;margin:0 0 16px;}'
            . '.lt-nb-title{font-weight:600;margin:0 0 6px;color:#333;}'
            . '.lt-nb-desc,.lt-nb-subtitle{font-size:13px;color:#777;margin:0 0 8px;}'
            . '.lt-nb-subtitle{margin-top:10px;font-weight:600;color:#555;}'
            . '.lt-nb-empty{font-size:13px;color:#999;margin:0;}'
            . '.lt-nb-tabs{display:flex;gap:6px;margin-bottom:8px;}'
            . '.lt-nb-tab{appearance:none;border:1px solid #d9d2c7;background:#fff;border-radius:4px;font-size:13px;padding:4px 12px;cursor:pointer;}'
            . '.lt-nb-tab.lt-on{background:#a65b4a;border-color:#a65b4a;color:#fff;}'
            . '.lt-nb-src{max-height:200px;overflow:auto;border:1px solid #e5ddcd;background:#fff;border-radius:4px;padding:4px 0;}'
            . '.lt-nb-src-item{display:flex;align-items:center;gap:8px;padding:3px 8px;font-size:13px;}'
            . '.lt-nb-src-item span:first-child{flex:1;}'
            . '.lt-nb-src-item input{width:180px;font-size:13px;padding:2px 6px;}'
            . '.lt-nb-node{display:flex;align-items:center;gap:4px;padding:4px 6px;border-bottom:1px dashed #e8e0d0;font-size:13px;background:#fff;}'
            . '.lt-nb-badge{font-size:11px;padding:0 6px;border-radius:3px;background:#f0e9dc;border:1px solid #ddd2bd;color:#8a7a5c;flex:none;}'
            . '.lt-nb-name{overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:260px;}'
            . '.lt-nb-auto{font-size:11px;color:#b3a68a;}'
            . '.lt-nb-op{appearance:none;cursor:pointer;border:1px solid #d9d2c7;background:#fff;border-radius:3px;font-size:12px;line-height:1.6;padding:0 6px;color:#555;flex:none;}'
            . '.lt-nb-op:hover{background:#f3ede1;color:#a65b4a;border-color:#a65b4a;}'
            . '.lt-nb-op:disabled{opacity:.35;cursor:default;}'
            . '</style>'
            . '<script id="lt-navbuilder-data">window.LT_NAV_BUILDER=' . $ltNavEnv . ';</script>'
            . '<script src="' . lt_esc_attr($ltThemeUrl) . '/assets/js/lantern.navbuilder.js?v=' . LT_ASSET_VERSION . '" defer></script>';
    }
};
