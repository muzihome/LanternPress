<?php
declare(strict_types=1);










if (!defined('__TYPECHO_ADMIN__')) {
    define('__TYPECHO_ADMIN__', true);
}




ob_start();
$ltRoot = dirname(__DIR__, 4); 
if (!is_file($ltRoot . '/config.inc.php')) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    http_response_code(500);
    exit('LanternPress: config.inc.php not found');
}
require_once $ltRoot . '/config.inc.php';
\Widget\Init::alloc();








try {
    $ltSiteRoot = rtrim((string) \Widget\Options::alloc()->siteUrl, '/');
    \Typecho\Cookie::setPrefix($ltSiteRoot);
    
    \Typecho\Widget::destroy('Widget_User');
} catch (\Throwable $e) {
    
}


require_once __DIR__ . '/core.php';


while (ob_get_level() > 0) {
    ob_end_clean();
}


function lt_admin_json(array $payload, int $status = 200): void
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    
    $ltMap = [200 => 'OK', 400 => 'Bad Request', 403 => 'Forbidden', 404 => 'Not Found', 429 => 'Too Many Requests', 500 => 'Internal Server Error'];
    header('HTTP/1.1 ' . $status . ' ' . ($ltMap[$status] ?? 'OK'), true, $status);
    header('Content-Type: application/json; charset=utf-8');
    echo (string) json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}


function lt_admin_fix_url(string $url): string
{
    return lt_flinks_normalize_url($url);
}


function lt_admin_stats(array $data): array
{
    $stats = ['total' => 0, 'approved' => 0, 'pending' => 0, 'rejected' => 0];
    foreach ($data as $e) {
        if (!is_array($e)) {
            continue;
        }
        $stats['total']++;
        switch ((int) ($e['status'] ?? 0)) {
            case 1:
                $stats['approved']++;
                break;
            case 2:
                $stats['rejected']++;
                break;
            default:
                $stats['pending']++;
                break;
        }
    }
    return $stats;
}


function lt_admin_pages(): array
{
    $pages = [];
    try {
        $widget = \Typecho\Widget::widget('Widget_Contents_Page_List');
        if ($widget instanceof \Widget\Contents\Page\Rows) {
            while ($widget->next()) {
                $tpl = (string) $widget->template;
                $pages[] = [
                    'cid' => (int) $widget->cid,
                    'title' => (string) $widget->title,
                    'slug' => (string) $widget->slug,
                    'template' => $tpl,
                    'isFlinks' => str_contains($tpl, 'flinks-template'),
                ];
            }
        }
    } catch (\Throwable $e) {
        lt_log_error('flinks-admin: pages query failed', $e);
    }
    return $pages;
}




$ltUser = \Typecho\Widget::widget('Widget_User');
if (!$ltUser->hasLogin() || !$ltUser->pass('administrator')) {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        lt_admin_json(['ok' => false, 'error' => '无权限：请以管理员登录'], 403);
    }
    
    
    try {
        $ltLoginUrl = rtrim((string) \Widget\Options::alloc()->siteUrl, '/') . '/admin/login.php';
    } catch (\Throwable $e) {
        $ltLoginUrl = 'login.php';
    }
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    header('HTTP/1.1 302 Found', true, 302);
    header('Location: ' . $ltLoginUrl);
    exit;
}

$ltIsPost = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';


if ($ltIsPost && !lt_check_rate_limit('flink_admin_' . lt_get_client_ip(), 60, 3600)) {
    lt_admin_json(['ok' => false, 'error' => '请求过于频繁，请稍后再试'], 429);
}

$ltSecurity = \Widget\Security::alloc();


$ltSelfPath = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
$ltCsrf = $ltSecurity->getToken($ltSelfPath);

if ($ltIsPost) {
    $ltRefPath = (string) parse_url((string) \Typecho\Request::getInstance()->getReferer(), PHP_URL_PATH);
    $ltGot = (string) ($_POST['_'] ?? '');
    if (!hash_equals($ltSecurity->getToken($ltRefPath), $ltGot)) {
        lt_admin_json(['ok' => false, 'error' => 'CSRF 校验失败，请刷新页面重试'], 403);
    }
}




if (!$ltIsPost) {
    $ltOptions = \Widget\Options::alloc();
    $ltThemeUrl = rtrim((string) $ltOptions->themeUrl, '/');
    $ltAdminUrl = rtrim((string) $ltOptions->siteUrl, '/') . '/admin/';
    $ltJsUrl = $ltThemeUrl . '/assets/js/flinks-admin.js?v=' . urlencode(LT_ASSET_VERSION);
    $ltTitle = '友链管理 - LanternPress';
    $ltStyle = 'body{margin:0;background:#f1f0ee;font:14px/1.6 "Segoe UI","Microsoft YaHei",sans-serif;color:#333;}#lt-wrap{max-width:980px;margin:0 auto;padding:24px 16px 48px;}#lt-head{display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;}#lt-head h1{font-size:22px;margin:0;color:#a65b4a;}#lt-head .lt-back{color:#777;text-decoration:none;font-size:13px;}#lt-head .lt-back:hover{color:#a65b4a;}.lt-card{background:#fff;border:1px solid #e0dbd1;border-radius:6px;padding:16px;margin-bottom:16px;}.lt-card h2{margin:0 0 10px;font-size:16px;color:#555;}#lt-stats{display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin-bottom:16px;}#lt-stats .lt-stat{background:#fff;border:1px solid #e0dbd1;border-radius:6px;padding:14px;text-align:center;}#lt-stats .lt-stat b{display:block;font-size:26px;color:#a65b4a;}#lt-stats .lt-stat span{font-size:13px;color:#777;}table{width:100%;border-collapse:collapse;}th,td{padding:8px 10px;border-bottom:1px solid #eee;text-align:left;font-size:13px;}th{color:#888;font-weight:600;}td .lt-ops{white-space:nowrap;}button{appearance:none;cursor:pointer;border:1px solid #d9d2c7;background:#fff;border-radius:4px;font-size:13px;line-height:1.6;padding:3px 10px;color:#555;}.lt-btn-primary{background:#a65b4a;border-color:#a65b4a;color:#fff;}.lt-btn-danger{background:#fff;border-color:#c0392b;color:#c0392b;}button:hover{background:#f3ede1;color:#a65b4a;border-color:#a65b4a;}.lt-btn-primary:hover{background:#93493a;color:#fff;}button:disabled{opacity:.4;cursor:default;}.lt-badge{display:inline-block;padding:1px 8px;border-radius:10px;font-size:12px;}.lt-badge-1{background:#e8f5e9;color:#2e7d32;}.lt-badge-0{background:#fff3e0;color:#ef6c00;}.lt-badge-2{background:#f5f5f5;color:#757575;}.lt-form{display:grid;grid-template-columns:1fr 1fr;gap:10px 14px;}.lt-form label{font-size:13px;color:#666;display:block;margin-bottom:3px;}.lt-form .lt-full{grid-column:1/-1;}.lt-form input,.lt-form select,.lt-form textarea{width:100%;box-sizing:border-box;border:1px solid #d5d0c5;border-radius:4px;padding:6px 8px;font-size:13px;background:#fff;}.lt-form-actions{grid-column:1/-1;display:flex;gap:8px;margin-top:4px;}#lt-toast{position:fixed;top:16px;right:16px;z-index:999;display:flex;flex-direction:column;gap:8px;}#lt-toast .lt-toast-item{background:#333;color:#fff;padding:10px 16px;border-radius:6px;font-size:13px;box-shadow:0 2px 8px rgba(0,0,0,.2);}.lt-migrate-row{display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-bottom:8px;}.lt-hint{font-size:12px;color:#999;margin:4px 0 0;}select#lt-page{min-width:220px;}.lt-empty{color:#aaa;padding:20px;text-align:center;}.lt-danger-zone{display:none;margin-top:8px;border-top:1px dashed #e0dbd1;padding-top:10px;}/* B1（批次B）：列表工具栏 + 分页 + 选择列 */#lt-toolbar{display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin-bottom:10px;}#lt-toolbar select,#lt-toolbar input[type=text]{border:1px solid #d5d0c5;border-radius:4px;padding:5px 8px;font-size:13px;background:#fff;max-width:180px;}#lt-toolbar .lt-batch{display:inline-flex;gap:6px;align-items:center;margin-left:auto;}#lt-toolbar .lt-batch span{font-size:12px;color:#888;}#lt-pager{display:flex;align-items:center;justify-content:flex-end;gap:8px;margin-top:10px;font-size:13px;color:#777;}#lt-pager button{min-width:60px;}th input[type=checkbox],td input[type=checkbox]{vertical-align:middle;cursor:pointer;}.lt-cat{color:#888;font-size:12px;}';
    $ltHtml = '<!DOCTYPE html><html lang="zh-CN"><head><meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<title>' . $ltTitle . '</title>'
        . '<meta name="lt-csrf" content="' . lt_esc_attr($ltCsrf) . '">'
        . '<style>' . $ltStyle . '</style>'
        . '</head><body>'
        . '<div id="lt-wrap">'
        . '<header id="lt-head"><h1>友链管理</h1><a class="lt-back" href="' . lt_esc_attr($ltAdminUrl . 'index.php') . '">← 返回控制台</a></header>'
        . '<div id="lt-stats">'
        . '<div class="lt-stat"><b id="lt-n-total">-</b><span>总数</span></div>'
        . '<div class="lt-stat"><b id="lt-n-approved">-</b><span>已通过</span></div>'
        . '<div class="lt-stat"><b id="lt-n-pending">-</b><span>待审核</span></div>'
        . '<div class="lt-stat"><b id="lt-n-rejected">-</b><span>已拒绝</span></div>'
        . '</div>'
        . '<div class="lt-card"><h2>友链页识别</h2>'
        . '<label for="lt-page" style="font-size:13px;color:#666;">友链页（迁移/清理目标）</label> '
        . '<select id="lt-page"></select>'
        . '<p class="lt-hint">自动检测使用 flinks-template 模板的独立页面；若检测不到可手动选择。</p>'
        . '</div>'
        . '<div class="lt-card"><h2>友链列表</h2>'
        . '<div id="lt-toolbar"></div>'
        . '<div id="lt-list"><div class="lt-empty">加载中…</div></div>'
        . '<div id="lt-pager"></div>'
        . '</div>'
        . '<div class="lt-card"><h2 id="lt-form-title">新增友链</h2>'
        . '<form id="lt-form" class="lt-form" autocomplete="off">'
        . '<input type="hidden" name="id" id="f-id" value="">'
        . '<div><label>名称 *</label><input type="text" name="name" id="f-name" maxlength="60" required></div>'
        . '<div><label>网址 *</label><input type="text" name="url" id="f-url" maxlength="200" required placeholder="https://…"></div>'
        . '<div><label>LOGO</label><input type="text" name="logo" id="f-logo" maxlength="200" placeholder="https://…（留空自动头像/首字）"></div>'
        . '<div><label>邮箱</label><input type="email" name="mail" id="f-mail" maxlength="120"></div>'
        . '<div><label>分类</label><input type="text" name="category" id="f-category" maxlength="20" placeholder="如：友情 / 工具（留空=默认）"></div>'
        . '<div class="lt-full"><label>简介</label><textarea name="desc" id="f-desc" rows="2" maxlength="300"></textarea></div>'
        . '<div><label>状态</label><select name="status" id="f-status">'
        . '<option value="1">已通过（前台显示）</option>'
        . '<option value="0">待审核</option>'
        . '<option value="2">已拒绝</option>'
        . '</select></div>'
        . '<div class="lt-form-actions">'
        . '<button type="submit" class="lt-btn-primary" id="lt-save-btn">保存</button>'
        . '<button type="button" id="lt-reset-btn">清空</button>'
        . '</div></form></div>'
        . '<div class="lt-card"><h2>数据迁移与清理</h2>'
        . '<div class="lt-migrate-row">'
        . '<button type="button" id="lt-migrate-btn">迁移旧评论</button>'
        . '<span class="lt-hint">将友链页评论表中的旧申请（已通过）幂等迁移到 flinks 数据源；已有数据时自动跳过。</span>'
        . '</div>'
        . '<div class="lt-migrate-row">'
        . '<button type="button" id="lt-cleanup-toggle" class="lt-btn-danger">清理旧申请评论…</button>'
        . '<span class="lt-hint">危险操作：仅删除友链页评论表中以 [logo] / [flink-apply] 开头的申请占位评论，普通评论不受影响。</span>'
        . '</div>'
        . '<div class="lt-danger-zone" id="lt-danger-zone">'
        . '<p class="lt-hint" style="color:#c0392b;">确认删除后不可恢复，评论表将零残留。请再次确认：</p>'
        . '<button type="button" id="lt-cleanup-btn" class="lt-btn-danger">我确认，执行清理</button>'
        . '</div>'
        . '</div>'
        . '</div>'
        . '<div id="lt-toast"></div>'
        . '<script src="' . lt_esc_attr($ltJsUrl) . '" defer></script>'
        . '</body></html>';
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    header('HTTP/1.1 200 OK', true, 200);
    header('Content-Type: text/html; charset=utf-8');
    
    header('X-Content-Type-Options: nosniff');
    header("Content-Security-Policy: default-src 'self'; style-src 'self' 'unsafe-inline'; script-src 'self'; img-src 'self' data: https:; connect-src 'self'; frame-ancestors 'none'");
    echo $ltHtml;
    exit;
}




$ltAction = (string) ($_POST['action'] ?? '');

switch ($ltAction) {
    case 'list':
    {
        $ltData = lt_flinks_data();
        
        $ltStatus = (string) ($_POST['status'] ?? '');
        $ltQ = trim((string) ($_POST['q'] ?? ''));
        $ltPage = max(1, (int) ($_POST['page'] ?? 1));
        $ltPageSize = max(1, min(200, (int) ($_POST['pageSize'] ?? 50)));
        $ltItems = [];
        foreach (array_values($ltData) as $ltE) {
            if (!is_array($ltE)) {
                continue;
            }
            if ($ltStatus !== '' && (int) ($ltE['status'] ?? 0) !== (int) $ltStatus) {
                continue;
            }
            if ($ltQ !== '') {
                $ltHay = mb_strtolower(implode(' ', [
                    (string) ($ltE['name'] ?? ''),
                    (string) ($ltE['url'] ?? ''),
                    (string) ($ltE['desc'] ?? ''),
                    (string) ($ltE['mail'] ?? ''),
                ]));
                if (mb_strpos($ltHay, mb_strtolower($ltQ)) === false) {
                    continue;
                }
            }
            
            $ltItems[] = [
                'id' => (int) ($ltE['id'] ?? 0),
                'name' => (string) ($ltE['name'] ?? ''),
                'url' => (string) ($ltE['url'] ?? ''),
                'logo' => (string) ($ltE['logo'] ?? ''),
                'mail' => (string) ($ltE['mail'] ?? ''),
                'desc' => (string) ($ltE['desc'] ?? ''),
                'status' => (int) ($ltE['status'] ?? 0),
                'sort' => (int) ($ltE['sort'] ?? 0),
                'created' => (int) ($ltE['created'] ?? 0),
                'category' => (string) ($ltE['category'] ?? ''),
                'src' => 'flinks',
            ];
        }
        
        usort($ltItems, static function (array $a, array $b): int {
            if ($a['sort'] !== $b['sort']) {
                return $a['sort'] <=> $b['sort'];
            }
            return $a['created'] <=> $b['created'];
        });
        $ltTotal = count($ltItems);
        $ltPageCount = max(1, (int) ceil($ltTotal / $ltPageSize));
        if ($ltPage > $ltPageCount) {
            $ltPage = $ltPageCount;
        }
        $ltPaged = array_slice($ltItems, ($ltPage - 1) * $ltPageSize, $ltPageSize);
        lt_admin_json([
            'ok' => true,
            'data' => [
                'items' => $ltPaged,
                'stats' => lt_admin_stats($ltData),
                'pages' => lt_admin_pages(),
                'page' => $ltPage,
                'pageSize' => $ltPageSize,
                'total' => $ltTotal,
                'pageCount' => $ltPageCount,
            ],
        ]);
    }

    case 'save':
    {
        $ltId = (int) ($_POST['id'] ?? 0);
        $ltName = trim((string) ($_POST['name'] ?? ''));
        $ltUrl = (string) ($_POST['url'] ?? '');
        $ltLogo = trim((string) ($_POST['logo'] ?? ''));
        $ltMail = trim((string) ($_POST['mail'] ?? ''));
        $ltDesc = trim((string) ($_POST['desc'] ?? ''));
        $ltCategory = trim((string) ($_POST['category'] ?? ''));

        if ($ltName === '' || mb_strlen($ltName) > 60) {
            lt_admin_json(['ok' => false, 'error' => '名称必填且不超过 60 字'], 400);
        }
        if ($ltUrl === '') {
            lt_admin_json(['ok' => false, 'error' => '网址必填'], 400);
        }
        $ltUrl = lt_admin_fix_url($ltUrl);
        if ($ltUrl === '') {
            lt_admin_json(['ok' => false, 'error' => '网址格式不正确'], 400);
        }
        if ($ltLogo !== '') {
            $ltLogo = lt_admin_fix_url($ltLogo);
            if ($ltLogo === '') {
                lt_admin_json(['ok' => false, 'error' => 'LOGO 格式不正确'], 400);
            }
        }
        if ($ltMail !== '' && filter_var($ltMail, FILTER_VALIDATE_EMAIL) === false) {
            lt_admin_json(['ok' => false, 'error' => '邮箱格式不正确'], 400);
        }
        if (mb_strlen($ltDesc) > 300) {
            lt_admin_json(['ok' => false, 'error' => '简介不超过 300 字'], 400);
        }
        
        if (mb_strlen($ltCategory) > 20) {
            lt_admin_json(['ok' => false, 'error' => '分类不超过 20 字'], 400);
        }
        
        $ltStatusRaw = (string) ($_POST['status'] ?? '');
        $ltStatus = $ltStatusRaw === ''
            ? ((bool) \Widget\Options::alloc()->commentsRequireModeration ? 0 : 1)
            : (int) $ltStatusRaw;
        if (!in_array($ltStatus, [0, 1, 2], true)) {
            lt_admin_json(['ok' => false, 'error' => '状态值不合法'], 400);
        }
        
        $ltRes = lt_flinks_transaction(static function () use ($ltId, $ltName, $ltUrl, $ltLogo, $ltMail, $ltDesc, $ltCategory, $ltStatus): array {
            $ltData = lt_flinks_data();
            
            $ltOthers = $ltData;
            if ($ltId > 0) {
                $ltOthers = array_values(array_filter($ltData, static fn(array $e): bool => (int) ($e['id'] ?? 0) !== $ltId));
            }
            if (lt_flinks_dup_exists($ltOthers, $ltUrl, $ltMail)) {
                return ['ok' => false, 'error' => '已存在相同网址或邮箱的友链', 'http' => 400];
            }
            if ($ltId > 0) {
                $ltFound = false;
                foreach ($ltData as $ltIdx => $ltE) {
                    if (is_array($ltE) && (int) ($ltE['id'] ?? 0) === $ltId) {
                        $ltData[$ltIdx]['name'] = $ltName;
                        $ltData[$ltIdx]['url'] = $ltUrl;
                        $ltData[$ltIdx]['logo'] = $ltLogo;
                        $ltData[$ltIdx]['mail'] = $ltMail;
                        $ltData[$ltIdx]['desc'] = $ltDesc;
                        $ltData[$ltIdx]['category'] = $ltCategory;
                        $ltData[$ltIdx]['status'] = $ltStatus;
                        $ltFound = true;
                        break;
                    }
                }
                if (!$ltFound) {
                    return ['ok' => false, 'error' => '记录不存在', 'http' => 404];
                }
                $ltSaved = $ltData;
            } else {
                $ltNextId = 1;
                foreach ($ltData as $ltE) {
                    $ltNextId = max($ltNextId, (int) ($ltE['id'] ?? 0) + 1);
                }
                $ltMaxSort = 0;
                foreach ($ltData as $ltE) {
                    $ltMaxSort = max($ltMaxSort, (int) ($ltE['sort'] ?? 0));
                }
                $ltData[] = [
                    'id' => $ltNextId,
                    'name' => $ltName,
                    'url' => $ltUrl,
                    'logo' => $ltLogo,
                    'mail' => $ltMail,
                    'desc' => $ltDesc,
                    'category' => $ltCategory,
                    'status' => $ltStatus,
                    'sort' => $ltMaxSort + 1,
                    'created' => time(),
                ];
                $ltSaved = $ltData;
            }
            if (!lt_flinks_save($ltSaved)) {
                return ['ok' => false, 'error' => '保存失败，请重试', 'http' => 500];
            }
            return ['ok' => true, 'data' => ['stats' => lt_admin_stats($ltSaved)]];
        });
        
        if (($ltRes['ok'] ?? false) === true) {
            lt_flinks_forget_all_pages();
        }
        lt_admin_json(['ok' => $ltRes['ok'] ?? false, 'error' => $ltRes['error'] ?? '', 'data' => $ltRes['data'] ?? []], (int) ($ltRes['http'] ?? ($ltRes['ok'] ? 200 : 500)));
    }

    case 'delete':
    {
        $ltId = (int) ($_POST['id'] ?? 0);
        if ($ltId <= 0) {
            lt_admin_json(['ok' => false, 'error' => '缺少记录 id'], 400);
        }
        
        $ltRes = lt_flinks_transaction(static function () use ($ltId): array {
            $ltData = array_values(array_filter(lt_flinks_data(), static fn(array $e): bool => (int) ($e['id'] ?? 0) !== $ltId));
            if (!lt_flinks_save($ltData)) {
                return ['ok' => false, 'error' => '删除失败，请重试', 'http' => 500];
            }
            return ['ok' => true, 'data' => ['stats' => lt_admin_stats($ltData)]];
        });
        
        if (($ltRes['ok'] ?? false) === true) {
            lt_flinks_forget_all_pages();
        }
        lt_admin_json(['ok' => $ltRes['ok'] ?? false, 'error' => $ltRes['error'] ?? '', 'data' => $ltRes['data'] ?? []], (int) ($ltRes['http'] ?? ($ltRes['ok'] ? 200 : 500)));
    }

    case 'setStatus':
    {
        $ltId = (int) ($_POST['id'] ?? 0);
        $ltStatus = (int) ($_POST['status'] ?? -1);
        if ($ltId <= 0 || !in_array($ltStatus, [0, 1, 2], true)) {
            lt_admin_json(['ok' => false, 'error' => '参数不合法'], 400);
        }
        
        $ltRes = lt_flinks_transaction(static function () use ($ltId, $ltStatus): array {
            $ltData = lt_flinks_data();
            $ltFound = false;
            foreach ($ltData as $ltIdx => $ltE) {
                if (is_array($ltE) && (int) ($ltE['id'] ?? 0) === $ltId) {
                    $ltData[$ltIdx]['status'] = $ltStatus;
                    $ltFound = true;
                    break;
                }
            }
            if (!$ltFound) {
                return ['ok' => false, 'error' => '记录不存在', 'http' => 404];
            }
            if (!lt_flinks_save($ltData)) {
                return ['ok' => false, 'error' => '保存失败，请重试', 'http' => 500];
            }
            return ['ok' => true, 'data' => ['stats' => lt_admin_stats($ltData)]];
        });
        
        if (($ltRes['ok'] ?? false) === true) {
            lt_flinks_forget_all_pages();
        }
        lt_admin_json(['ok' => $ltRes['ok'] ?? false, 'error' => $ltRes['error'] ?? '', 'data' => $ltRes['data'] ?? []], (int) ($ltRes['http'] ?? ($ltRes['ok'] ? 200 : 500)));
    }

    case 'move':
    {
        $ltId = (int) ($_POST['id'] ?? 0);
        $ltDir = (string) ($_POST['dir'] ?? '');
        if ($ltId <= 0 || !in_array($ltDir, ['up', 'down'], true)) {
            lt_admin_json(['ok' => false, 'error' => '参数不合法'], 400);
        }
        
        $ltRes = lt_flinks_transaction(static function () use ($ltId, $ltDir): array {
            $ltData = array_values(lt_flinks_data());
            $ltIdx = null;
            foreach ($ltData as $ltI => $ltE) {
                if (is_array($ltE) && (int) ($ltE['id'] ?? 0) === $ltId) {
                    $ltIdx = $ltI;
                    break;
                }
            }
            if ($ltIdx === null) {
                return ['ok' => false, 'error' => '记录不存在', 'http' => 404];
            }
            $ltTarget = $ltDir === 'up' ? $ltIdx - 1 : $ltIdx + 1;
            if ($ltTarget < 0 || $ltTarget >= count($ltData)) {
                return ['ok' => false, 'error' => $ltDir === 'up' ? '已在最顶部' : '已在最底部', 'http' => 400];
            }
            [$ltData[$ltIdx], $ltData[$ltTarget]] = [$ltData[$ltTarget], $ltData[$ltIdx]];
            foreach ($ltData as $ltI => &$ltE) {
                $ltE['sort'] = $ltI + 1;
            }
            unset($ltE);
            if (!lt_flinks_save($ltData)) {
                return ['ok' => false, 'error' => '保存失败，请重试', 'http' => 500];
            }
            return ['ok' => true, 'data' => ['stats' => lt_admin_stats($ltData)]];
        });
        
        if (($ltRes['ok'] ?? false) === true) {
            lt_flinks_forget_all_pages();
        }
        lt_admin_json(['ok' => $ltRes['ok'] ?? false, 'error' => $ltRes['error'] ?? '', 'data' => $ltRes['data'] ?? []], (int) ($ltRes['http'] ?? ($ltRes['ok'] ? 200 : 500)));
    }

    
    case 'batchStatus':
    {
        $ltIds = array_values(array_unique(array_filter(array_map('intval', (array) ($_POST['ids'] ?? [])), static fn(int $i): bool => $i > 0)));
        $ltStatus = (int) ($_POST['status'] ?? -1);
        if ($ltIds === [] || !in_array($ltStatus, [0, 1, 2], true)) {
            lt_admin_json(['ok' => false, 'error' => '参数不合法：请选择记录并指定状态'], 400);
        }
        
        $ltRes = lt_flinks_transaction(static function () use ($ltIds, $ltStatus): array {
            $ltData = lt_flinks_data();
            $ltChanged = 0;
            foreach ($ltData as &$ltE) {
                if (is_array($ltE) && in_array((int) ($ltE['id'] ?? 0), $ltIds, true)) {
                    $ltE['status'] = $ltStatus;
                    $ltChanged++;
                }
            }
            unset($ltE);
            if ($ltChanged === 0) {
                return ['ok' => false, 'error' => '未找到可更新的记录', 'http' => 404];
            }
            if (!lt_flinks_save($ltData)) {
                return ['ok' => false, 'error' => '保存失败，请重试', 'http' => 500];
            }
            return ['ok' => true, 'data' => ['changed' => $ltChanged, 'stats' => lt_admin_stats($ltData)]];
        });
        
        if (($ltRes['ok'] ?? false) === true) {
            lt_flinks_forget_all_pages();
        }
        lt_admin_json(['ok' => $ltRes['ok'] ?? false, 'error' => $ltRes['error'] ?? '', 'data' => $ltRes['data'] ?? []], (int) ($ltRes['http'] ?? ($ltRes['ok'] ? 200 : 500)));
    }

    case 'batchDelete':
    {
        $ltIds = array_values(array_unique(array_filter(array_map('intval', (array) ($_POST['ids'] ?? [])), static fn(int $i): bool => $i > 0)));
        if ($ltIds === []) {
            lt_admin_json(['ok' => false, 'error' => '请选择要删除的记录'], 400);
        }
        
        $ltRes = lt_flinks_transaction(static function () use ($ltIds): array {
            $ltBefore = lt_flinks_data();
            $ltData = array_values(array_filter($ltBefore, static fn(array $e): bool => !in_array((int) ($e['id'] ?? 0), $ltIds, true)));
            $ltDeleted = count($ltBefore) - count($ltData);
            if ($ltDeleted === 0) {
                return ['ok' => false, 'error' => '未找到可删除的记录', 'http' => 404];
            }
            if (!lt_flinks_save($ltData)) {
                return ['ok' => false, 'error' => '删除失败，请重试', 'http' => 500];
            }
            return ['ok' => true, 'data' => ['deleted' => $ltDeleted, 'stats' => lt_admin_stats($ltData)]];
        });
        
        if (($ltRes['ok'] ?? false) === true) {
            lt_flinks_forget_all_pages();
        }
        lt_admin_json(['ok' => $ltRes['ok'] ?? false, 'error' => $ltRes['error'] ?? '', 'data' => $ltRes['data'] ?? []], (int) ($ltRes['http'] ?? ($ltRes['ok'] ? 200 : 500)));
    }

    case 'migrate':
    {
        $ltCid = (int) ($_POST['pageCid'] ?? 0);
        if ($ltCid <= 0) {
            lt_admin_json(['ok' => false, 'error' => '请先选择友链页'], 400);
        }
        $ltFix = static function (string $u): string {
            $u = trim($u);
            if ($u !== '' && preg_match('#^[a-z][a-z0-9+.-]*://#i', $u) !== 1) {
                $u = 'https://' . $u;
            }
            return $u;
        };
        $ltResult = lt_flinks_migrate($ltCid, $ltFix);
        
        if (($ltResult['migrated'] ?? 0) > 0) {
            lt_flinks_forget_all_pages();
        }
        
        lt_admin_json(['ok' => true, 'data' => $ltResult]);
    }

    case 'cleanup':
    {
        $ltCid = (int) ($_POST['pageCid'] ?? 0);
        if ($ltCid <= 0) {
            lt_admin_json(['ok' => false, 'error' => '请先选择友链页'], 400);
        }
        $ltDeleted = lt_flinks_cleanup_shadow($ltCid);
        lt_admin_json(['ok' => true, 'data' => ['deleted' => $ltDeleted]]);
    }

    case 'stats':
    {
        lt_admin_json(['ok' => true, 'data' => ['stats' => lt_admin_stats(lt_flinks_data())]]);
    }

    default:
        lt_admin_json(['ok' => false, 'error' => '未知操作'], 404);
}
