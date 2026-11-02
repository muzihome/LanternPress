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
        '缓存设置' => ['ltEdgeoneEnable', 'ltEdgeoneZoneId', 'ltEdgeoneSecretId', 'ltEdgeoneSecretKey', 'ltEdgeoneMode'],
    ];
    $ltJson = json_encode($ltGroups, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    echo '<style id="lt-cfg-group-css">'
        . '.lt-tabs{display:flex;flex-wrap:nowrap;gap:4px;margin:0 0 -1px;padding:0 0 6px 6px;overflow-x:auto;-webkit-overflow-scrolling:touch;scrollbar-width:thin;white-space:nowrap;}'
        . '.lt-tab{-webkit-appearance:none;appearance:none;border:1px solid #d9d2c7;border-bottom:none;background:#faf7f0;color:#555;font-size:14px;font-weight:600;padding:9px 18px;cursor:pointer;border-radius:6px 6px 0 0;margin-right:4px;flex:0 0 auto;white-space:nowrap;}'
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

    // ===== P2-2：友链管理入口（设置外观页）=====
    // 本页为后台唯一加载主题 functions.php 的页面：仅当 LanternPress 启用且目录存在时
    // 该闭包才执行（Widget_Themes_Config 按 missingTheme 守卫 require），入口随之显示；
    // 主题禁用/切换/文件缺失时本钩子不输出，入口自动移除。
    // 入口两处（批次35 起）：① 页面顶部「友链管理」菜单项（追加至 typecho-option-tabs 末尾，
    // 与「可以使用的外观 / 编辑当前外观 / 设置外观」同级）；② 表单顶部入口条（保留，可应需移除）。
    if ($ltThemeUrl !== '') {
        echo '<style id="lt-flinks-entry-css">'
            . '.lt-flinks-entry{display:flex;align-items:center;gap:10px;border:1px dashed #c9a49a;background:#fdf6f3;border-radius:6px;padding:10px 14px;margin:0 0 16px;font-size:13px;}'
            . '.lt-flinks-entry a{color:#a65b4a;font-weight:700;text-decoration:none;}'
            . '.lt-flinks-entry a:hover{text-decoration:underline;}'
            . '.lt-flinks-entry .lt-flinks-note{color:#8a7a5c;}'
            . '</style>'
            . '<script id="lt-flinks-entry-js">'
            . '(function(){'
            . 'var flinkUrl="' . lt_esc_attr(rtrim($ltThemeUrl, '/') . '/inc/flinks-admin.php') . '";'
            . 'var tabs=document.querySelector("ul.typecho-option-tabs");'
            . 'if(tabs){'
            . 'var li=document.createElement("li");'
            . 'var a=document.createElement("a");'
            . 'a.href=flinkUrl;'
            . 'a.target="_blank";'
            . 'a.textContent="友链管理";'
            . 'li.appendChild(a);'
            . 'tabs.appendChild(li);'
            . '}'
            . 'var form=document.querySelector("form[action*=\"themes-edit\"]");'
            . 'if(!form){return;}'
            . 'var box=document.createElement("div");'
            . 'box.className="lt-flinks-entry";'
            . 'var a2=document.createElement("a");'
            . 'a2.href=flinkUrl;'
            . 'a2.target="_blank";'
            . 'a2.textContent="进入「友链管理」";'
            . 'box.appendChild(a2);'
            . 'var note=document.createElement("span");'
            . 'note.className="lt-flinks-note";'
            . 'note.textContent="（审核 / 编辑 / 排序 / 迁移 / 清理友链申请与数据）";'
            . 'box.appendChild(note);'
            . 'form.insertBefore(box, form.firstChild);'
            . '})();'
            . '</script>';

        // ===== 批次37：EdgeOne 缓存自动刷新 —— 「测试清除缓存」按钮 =====
        // 读取当前表单的 CSRF token，POST 到本页 action=lt_edgeone_purge（由 inc/edgeone-cache.php 拦截处理并返回 JSON）。
        echo '<script id="lt-edgeone-test-js">'
            . '(function(){'
            . 'var form=document.querySelector("form[action*=\"themes-edit\"]");'
            . 'if(!form){return;}'
            . 'function fmtRange(m){return m==="dynamic"?"仅动态页":"整站";}'
            . 'function fmtTs(t){var d=new Date(t*1000);return d.getFullYear()+"-"+("0"+(d.getMonth()+1)).slice(-2)+"-"+("0"+d.getDate()).slice(-2)+" "+("0"+d.getHours()).slice(-2)+":"+("0"+d.getMinutes()).slice(-2)+":"+("0"+d.getSeconds()).slice(-2);}'
            . 'function getToken(){'
            . 'var el=form.querySelector("input[name=\"_\"]");'
            . 'if(el&&el.value){return el.value;}'
            . 'var a=form.getAttribute("action")||"";'
            . 'var m=a.match(/[?&]_=([^&#]+)/);'
            . 'if(m){return decodeURIComponent(m[1]);}'
            . 'return "";'
            . '}'
            . 'var box2=document.createElement("div");'
            . 'box2.className="lt-flinks-entry";box2.style.marginTop="6px";'
            . 'var t2=document.createElement("span");'
            . 't2.textContent="最近清除记录：";t2.style.color="#8a7a5c";'
            . 'var logArea=document.createElement("span");'
            . 'logArea.style.marginLeft="8px";logArea.style.color="#8a7a5c";'
            . 'box2.appendChild(t2);box2.appendChild(logArea);'
            . 'function loadLog(){'
            . 'var base=location.pathname+location.search;'
            . 'base+=(base.indexOf("?")>=0?"&":"?")+"action=lt_edgeone_log";'
            . 'fetch(base,{method:"GET"}).then(function(r){return r.json();}).then(function(d){'
            . 'if(!d||!d.success){logArea.textContent="无法读取记录";return;}'
            . 'var it=d.items||[];'
            . 'if(it.length===0){logArea.textContent="（暂无记录，发布/更新文章后会自动或手动刷新，并在此留痕）";return;}'
            . 'var frag=document.createDocumentFragment();'
            . 'it.forEach(function(o){'
            . 'var d=document.createElement("div");'
            . 'd.style.cssText="font-size:12px;line-height:1.7;color:"+(o.ok?"#3a7d44":"#a65b4a");'
            . 'd.textContent=fmtTs(o.t)+" ｜ "+fmtRange(o.mode)+" ｜ "+(o.ok?"成功":"失败")+" ｜ "+(o.detail||"");'
            . 'frag.appendChild(d);'
            . '});'
            . 'logArea.textContent="";'
            . 'logArea.appendChild(frag);'
            . '}).catch(function(){logArea.textContent="读取记录失败";});'
            . '}'
            . 'var box=document.createElement("div");'
            . 'box.className="lt-flinks-entry";'
            . 'var label=document.createElement("span");'
            . 'label.textContent="EdgeOne CDN 缓存：保存「缓存设置」后可手动立即清除缓存（整站或仅动态页，取决于下方「清除范围」）。";'
            . 'box.appendChild(label);'
            . 'var btn=document.createElement("button");'
            . 'btn.type="button";'
            . 'btn.className="lt-nb-op";'
            . 'btn.style.marginLeft="6px";'
            . 'btn.textContent="立即刷新缓存";'
            . 'var status=document.createElement("span");'
            . 'status.style.marginLeft="8px";status.style.color="#8a7a5c";'
            . 'box.appendChild(btn);box.appendChild(status);'
            . 'btn.addEventListener("click",function(){'
            . 'var token=getToken();'
            . 'if(!token){status.textContent="未找到表单令牌，无法刷新";return;}'
            . 'status.textContent="正在清除…";'
            . 'btn.disabled=true;'
            . 'fetch(form.getAttribute("action"),{method:"POST",headers:{"Content-Type":"application/x-www-form-urlencoded"},'
            . 'body:"action=lt_edgeone_purge&_="+encodeURIComponent(token)})'
            . '.then(function(r){return r.json();})'
            . '.then(function(d){'
            . 'if(d&&d.success){status.textContent="清除成功（"+fmtRange(d.mode||"")+"，JobId: "+(d.jobId||"")+"）";status.style.color="#3a7d44";loadLog();}'
            . 'else{status.textContent="失败："+(d&&d.error?d.error:"未知错误");status.style.color="#a65b4a";}'
            . '})'
            . '.catch(function(){status.textContent="请求异常，请检查网络或 PHP curl 扩展";status.style.color="#a65b4a";})'
            . '.finally(function(){btn.disabled=false;});'
            . '});'
            . 'form.insertBefore(box, form.firstChild);'
            . 'form.insertBefore(box2, form.firstChild);'
            . 'loadLog();'
            . '})();'
            . '</script>';
    }
};

// ============================================================
// P2-2：友链管理入口（「设置外观」配置页）
// 背景：Typecho 1.3 后台通用页面不加载主题 functions.php（仅「设置外观」配置页经
// Widget_Themes_Config require），admin/menu.php navBar 钩子无法经主题在后台全局注册。
// 方案：入口随本页主题加载链生效——仅在「启用主题为 LanternPress 且主题目录存在
// （含 inc/flinks-admin.php）」时输出；主题禁用/切换/文件缺失时本页不再加载主题
// functions.php，入口自动移除（零插件、零内核改动）。
// 入口位置（批次35）：① 页面顶部「友链管理」菜单项——JS 追加至 ul.typecho-option-tabs
// 末尾（与「可以使用的外观 / 编辑当前外观 / 设置外观」同级，位于「设置外观」之后）；
// ② 表单顶部入口条（历史入口，与菜单项并存；如需移除可单独删去）。
// 局限：入口仅在「设置外观」配置页可见（Typecho 1.3 后台仅此页加载主题）。
// ============================================================
