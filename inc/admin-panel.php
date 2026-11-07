<?php
declare(strict_types=1);

if (!defined('__TYPECHO_ROOT_DIR__')) exit;









\Typecho\Plugin::factory('admin/footer.php')->end = static function (): void {
    $ltUri = (string) ($_SERVER['REQUEST_URI'] ?? '');
    if (strpos($ltUri, 'options-theme.php') === false) {
        return;
    }

    
    $ltGroups = [
        '站点基础信息' => ['logoUrl', 'posterLogo', 'shortcutIcon', 'recordNum', 'policeRecordNum', 'avatarProxy', 'ltHstsEnable'],
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

        
        
        echo '<script id="lt-edgeone-test-js">'
            . '(function(){'
            . 'var form=document.querySelector("form[action*=\"themes-edit\"]");'
            . 'if(!form){return;}'
            . 'function fmtRange(m){return m==="dynamic"?"仅动态页":"整站";}'
            . 'function fmtTs(t){var d=new Date(t*1000);return d.getFullYear()+"-"+("0"+(d.getMonth()+1)).slice(-2)+"-"+("0"+d.getDate()).slice(-2)+" "+("0"+d.getHours()).slice(-2)+":"+("0"+d.getMinutes()).slice(-2)+":"+("0"+d.getSeconds()).slice(-2);}'
            
            . 'function parseJson(t){var i=t.indexOf("{\\"success\\"");var s=i>=0?t.slice(i):t;try{return JSON.parse(s);}catch(e){return null;}}'
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
            . 'fetch(base,{method:"GET"}).then(function(r){return r.text();}).then(function(t){var d=parseJson(t);'
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
            . '.then(function(r){return r.text();})'
            . '.then(function(t){var d=parseJson(t)||{};'
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

        
        
        
        
        
        
        echo '<script id="lt-hsts-probe-js">'
            . '(function(){'
            . 'var form=document.querySelector("form[action*=\"themes-edit\"]");'
            . 'if(!form){return;}'
            . 'var radio=form.querySelector("input[name=\"ltHstsEnable\"]");'
            . 'if(!radio){return;}'
            . 'var ul=(radio.closest?radio.closest("ul.typecho-option"):null);'
            . 'if(!ul){return;}'
            . 'function parseJson(t){var i=t.indexOf("{\\"success\\"");var s=i>=0?t.slice(i):t;try{return JSON.parse(s);}catch(e){return null;}}'
            . 'function fmt(r){'
            . 'if(!r){return "未探测";}'
            . 'if(!r.ok){return "探测失败"+(r.error?"（"+r.error+"）":"");}'
            . 'return r.hsts?("已下发 "+r.hsts):"未下发";'
            . '}'
            . 'var box=document.createElement("div");'
            . 'box.style.cssText="margin:6px 0 2px 8px;font-size:12px;line-height:1.9;color:#8a7a5c;";'
            . 'var btn=document.createElement("button");'
            . 'btn.type="button";btn.className="lt-nb-op";btn.textContent="检测 HSTS 来源";'
            . 'var out=document.createElement("div");out.style.cssText="margin-top:6px;";'
            . 'box.appendChild(btn);box.appendChild(out);'
            . 'ul.parentNode.insertBefore(box,ul.nextSibling);'
            . 'btn.addEventListener("click",function(){'
            . 'var base=location.pathname+location.search;'
            . 'base+=(base.indexOf("?")>=0?"&":"?")+"action=lt_hsts_probe";'
            . 'out.textContent="正在探测（约需数秒）…";btn.disabled=true;'
            . 'fetch(base,{method:"GET",cache:"no-store"})'
            . '.then(function(r){return r.text();})'
            . '.then(function(t){'
            . 'var d=parseJson(t);'
            . 'if(!d||!d.success){out.textContent="探测接口不可用"+((d&&d.error)?"（"+d.error+"）":"");return;}'
            . 'out.textContent="";'
            . 'var e=document.createElement("div");e.textContent="边缘层（CDN / 网关）："+fmt(d.edge);out.appendChild(e);'
            . 'var o=document.createElement("div");o.textContent="源站（直连本机 127.0.0.1）："+fmt(d.origin);out.appendChild(o);'
            . 'var a=document.createElement("div");a.style.color="#a65b4a";a.textContent="建议："+(d.advice||"");out.appendChild(a);'
            . '})'
            . '.catch(function(){out.textContent="请求异常，请检查网络或 PHP curl 扩展";})'
            . '.finally(function(){btn.disabled=false;});'
            . '});'
            . '})();'
            . '</script>';
    }
};

















function lt_hsts_probe_one(string $url, bool $viaOrigin): array
{
    if (!function_exists('curl_init')) {
        return ['ok' => false, 'hsts' => null, 'status' => 0, 'error' => 'PHP curl 扩展未启用'];
    }
    $ltParts = parse_url($url);
    if (!is_array($ltParts) || empty($ltParts['host'])) {
        return ['ok' => false, 'hsts' => null, 'status' => 0, 'error' => '站点地址无法解析'];
    }
    $ltPort = (int) ($ltParts['port'] ?? ((($ltParts['scheme'] ?? 'http') === 'https') ? 443 : 80));

    $ltCh = curl_init($url);
    $ltOpts = [
        CURLOPT_NOBODY => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_TIMEOUT => 6,
        CURLOPT_USERAGENT => 'LanternPress-HSTS-Probe/' . LT_THEME_VERSION,
        CURLOPT_HTTPHEADER => ['Cache-Control: no-cache', 'Pragma: no-cache'],
    ];
    if ($viaOrigin) {
        $ltOpts[CURLOPT_RESOLVE] = [$ltParts['host'] . ':' . $ltPort . ':127.0.0.1'];
        $ltOpts[CURLOPT_SSL_VERIFYPEER] = false;
        $ltOpts[CURLOPT_SSL_VERIFYHOST] = 0;
    }
    curl_setopt_array($ltCh, $ltOpts);
    $ltResp = curl_exec($ltCh);
    $ltStatus = (int) curl_getinfo($ltCh, CURLINFO_RESPONSE_CODE);
    $ltError = (string) curl_error($ltCh);
    curl_close($ltCh);

    if ($ltResp === false || $ltResp === '') {
        return [
            'ok' => false,
            'hsts' => null,
            'status' => $ltStatus,
            'error' => $ltError !== '' ? $ltError : '无响应（可能被防火墙/证书校验拦截）',
        ];
    }
    if ($ltStatus >= 400) {
        return [
            'ok' => false,
            'hsts' => null,
            'status' => $ltStatus,
            'error' => 'HTTP ' . $ltStatus . '（探测资源不可访问）',
        ];
    }

    $ltHsts = null;
    foreach (preg_split('/\r\n|\n|\r/', (string) $ltResp) ?: [] as $ltLine) {
        if (preg_match('/^Strict-Transport-Security:\s*(.+)$/i', trim($ltLine), $ltMatch)) {
            $ltHsts = trim($ltMatch[1]);
        }
    }
    return ['ok' => true, 'hsts' => $ltHsts, 'status' => $ltStatus, 'error' => ''];
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'GET'
    && (($_GET['action'] ?? '') === 'lt_hsts_probe')) {
    lt_edgeone_json_begin();

    try {
        $ltUser = \Typecho\Widget::widget('Widget_User');
        if (!$ltUser->hasLogin() || ((string) $ltUser->group) !== 'administrator') {
            header('HTTP/1.1 403 Forbidden');
            echo json_encode(['success' => false, 'error' => 'unauthorized']);
            exit;
        }
    } catch (\Throwable $e) {
        lt_log_error('hsts probe auth failed', $e);
        header('HTTP/1.1 403 Forbidden');
        echo json_encode(['success' => false, 'error' => 'unauthorized']);
        exit;
    }

    try {
        $ltOpts = \Typecho\Widget::widget('Widget_Options');
        $ltSite = rtrim((string) $ltOpts->siteUrl, '/');
        $ltTheme = preg_replace('/[^A-Za-z0-9_-]/', '', (string) ($ltOpts->theme ?? '')) ?: 'LanternPress';
    } catch (\Throwable $e) {
        lt_log_error('hsts probe options failed', $e);
        echo json_encode(['success' => false, 'error' => '读取站点配置失败']);
        exit;
    }
    if ($ltSite === '') {
        echo json_encode(['success' => false, 'error' => '站点地址（siteUrl）为空，无法探测']);
        exit;
    }

    
    $ltProbeUrl = $ltSite . '/usr/themes/' . $ltTheme . '/assets/img/default-cover.avif?_ltprobe=' . time();

    $ltEdge = lt_hsts_probe_one($ltProbeUrl, false);
    $ltOrigin = lt_hsts_probe_one($ltProbeUrl, true);

    $ltEdgeHsts = $ltEdge['ok'] ? $ltEdge['hsts'] : null;
    $ltOriginHsts = $ltOrigin['ok'] ? $ltOrigin['hsts'] : null;
    if ($ltEdgeHsts !== null && $ltOriginHsts !== null) {
        $ltAdvice = '上游已下发 HSTS（边缘与源站都带），本开关保持「停用」即可，不要再由主题重复下发。';
    } elseif ($ltEdgeHsts === null && $ltOriginHsts !== null) {
        $ltAdvice = '源站已下发 HSTS，但边缘响应里没有（CDN 可能剥离或覆盖）；先向 CDN 确认，本开关保持「停用」。';
    } elseif ($ltEdgeHsts !== null && $ltOriginHsts === null) {
        $ltAdvice = '仅边缘（CDN / 网关）下发 HSTS，源站直连没有；若站点只经 CDN 对外访问，本开关保持「停用」即可。';
    } elseif (!$ltEdge['ok'] && !$ltOrigin['ok']) {
        $ltAdvice = '两层都无法探测，请手动执行 curl -sI ' . $ltSite . '/ | grep -i strict 判断。';
    } else {
        $ltAdvice = '当前没有任何上游下发 HSTS，整站目前没有 HSTS 保护——请把本开关改选「启用」，由主题下发。';
    }

    echo json_encode(
        ['success' => true, 'edge' => $ltEdge, 'origin' => $ltOrigin, 'advice' => $ltAdvice],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    exit;
}













