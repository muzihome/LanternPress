<?php
declare(strict_types=1);

if (!defined('__TYPECHO_ROOT_DIR__')) exit;

// ============================================================
// edgeone-cache.php — 腾讯云 EdgeOne CDN 缓存自动刷新（前台兜底）
// 由 functions.php 加载（core.php 模块链已就绪，security.php 的 lt_log_error 可用）
//
// 背景：生产站 EdgeOne 全站加速下，动态 HTML 若被边缘缓存，登录用户会拿到
// 他人渲染的页面（CSRF token 不匹配）→ 评论提交失败。根治后若要「匿名长缓存
// 提速 + 内容更新即时可见」，需要内容变化时自动清除 EdgeOne 缓存。
//
// 机制（零插件、零内核）：Typecho 后台保存文章/页面时不加载主题 functions.php，
// 主题钩子无法在发布瞬间可靠触发。因此采用「前台访问兜底」：
//   1) 每次前台请求在脚本结束（register_shutdown_function，响应已发出）时，
//      查询 contents 表 MAX(modified)，与上次已清除的内容版本比对；
//   2) 若内容有新更新且 EdgeOne 刷新已启用 → 调用 EdgeOne「清除缓存」API
//      （CreatePurgeTask，TC3-HMAC-SHA256 签名）；
//   3) 成功后记录本次内容版本，避免同一内容反复刷新。
// 效果：博主在后台发布/更新文章或页面后，下一次前台访问即自动清除 CDN 缓存，
// 长 TTL 的匿名页面缓存被刷新为新内容；CSRF 相关的「带 Cookie 回源」规则不受影响。
//
// 配置项（后台「设置外观」→「缓存设置」Tab）：
//   ltEdgeoneEnable     是否启用 CDN 自动刷新（Radio 1/0）
//   ltEdgeoneZoneId     EdgeOne 站点 ID（形如 zone-xxxxxxxxxxxx，控制台站点概览可复制）
//   ltEdgeoneSecretId   腾讯云 API 密钥 SecretId（仅需「清除缓存」权限）
//   ltEdgeoneSecretKey  腾讯云 API 密钥 SecretKey（Password 输入）
//   ltEdgeoneMode       清除范围：all=整站 / dynamic=仅动态页（首页+分类+标签目录）
// ============================================================

/**
 * 读取 EdgeOne 自动刷新配置（Typecho 主题配置已并入 Widget_Options）
 */
function lt_edgeone_config(): array
{
    $default = [
        'enable' => false,
        'zoneId' => '',
        'secretId' => '',
        'secretKey' => '',
        'mode' => 'all',
    ];
    try {
        $ltOpt = \Typecho\Widget::widget('Widget_Options');
        $ltSecretKey = trim((string) ($ltOpt->ltEdgeoneSecretKeyVal ?? ''));
        if ($ltSecretKey === '' || $ltSecretKey === '********') {
            // 兼容旧版：真实密钥曾直接存于占位键
            $ltSecretKey = trim((string) ($ltOpt->ltEdgeoneSecretKey ?? ''));
        }
        if ($ltSecretKey === '********') {
            $ltSecretKey = '';
        }
        return [
            'enable' => ((string) ($ltOpt->ltEdgeoneEnable ?? '0')) === '1',
            'zoneId' => trim((string) ($ltOpt->ltEdgeoneZoneId ?? '')),
            'secretId' => trim((string) ($ltOpt->ltEdgeoneSecretId ?? '')),
            'secretKey' => $ltSecretKey,
            'mode' => ((string) ($ltOpt->ltEdgeoneMode ?? 'all')) === 'dynamic' ? 'dynamic' : 'all',
        ];
    } catch (\Throwable $e) {
        return $default;
    }
}

/**
 * 腾讯云 TC3-HMAC-SHA256 签名并调用 EdgeOne 云 API（纯 PHP，零外部依赖）
 *
 * @return array{0:bool,1:string,2:string} [成功, 错误信息, JobId]
 */
function lt_edgeone_request(string $action, string $version, array $payload, array $cfg): array
{
    $host = 'teo.tencentcloudapi.com';
    $service = 'teo';
    $timestamp = time();
    $date = gmdate('Y-m-d', $timestamp);

    $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($body === false) {
        return [false, '请求参数序列化失败', ''];
    }

    // step 1: canonical request
    $canonicalHeaders = "content-type:application/json; charset=utf-8\nhost:{$host}\n";
    $signedHeaders = 'content-type;host';
    $canonicalRequest = "POST\n/\n\n{$canonicalHeaders}\n{$signedHeaders}\n"
        . hash('sha256', $body);

    // step 2: string to sign
    $credentialScope = "{$date}/{$service}/tc3_request";
    $stringToSign = "TC3-HMAC-SHA256\n{$timestamp}\n{$credentialScope}\n"
        . hash('sha256', $canonicalRequest);

    // step 3: signature
    $secretDate = hash_hmac('sha256', $date, 'TC3' . $cfg['secretKey'], true);
    $secretService = hash_hmac('sha256', $service, $secretDate, true);
    $secretSigning = hash_hmac('sha256', 'tc3_request', $secretService, true);
    $signature = hash_hmac('sha256', $stringToSign, $secretSigning);
    $authorization = "TC3-HMAC-SHA256 Credential={$cfg['secretId']}/{$credentialScope}, "
        . "SignedHeaders={$signedHeaders}, Signature={$signature}";

    if (!function_exists('curl_init')) {
        return [false, 'PHP curl 扩展不可用', ''];
    }

    $headers = [
        'Content-Type: application/json; charset=utf-8',
        'Host: ' . $host,
        'Authorization: ' . $authorization,
        'X-TC-Action: ' . $action,
        'X-TC-Version: ' . $version,
        'X-TC-Timestamp: ' . $timestamp,
    ];

    $ch = curl_init('https://' . $host);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 4,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);
    $resp = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);

    if ($resp === false) {
        return [false, '请求 EdgeOne 失败：' . (string) $err, ''];
    }

    $data = json_decode((string) $resp, true);
    $response = is_array($data) ? ($data['Response'] ?? null) : null;
    if (is_array($response) && isset($response['JobId'])) {
        return [true, '', (string) $response['JobId']];
    }
    $errorMsg = is_array($response) && isset($response['Error']['Message'])
        ? (string) $response['Error']['Message']
        : (string) $resp;
    return [false, $errorMsg, ''];
}

/**
 * 清除整站缓存（purge_all）
 */
function lt_edgeone_purge_all(array $cfg): array
{
    return lt_edgeone_request('CreatePurgeTask', '2022-09-01', [
        'ZoneId' => $cfg['zoneId'],
        'Type' => 'purge_all',
    ], $cfg);
}

/**
 * 清除动态页缓存（purge_url 首页 + purge_prefix 分类/标签目录）
 */
function lt_edgeone_purge_dynamic(array $cfg): array
{
    $site = '';
    try {
        $site = rtrim((string) \Typecho\Widget::widget('Widget_Options')->siteUrl, '/');
    } catch (\Throwable $e) {
    }
    if ($site === '') {
        return [false, '无法获取站点根地址', ''];
    }
    $result = lt_edgeone_request('CreatePurgeTask', '2022-09-01', [
        'ZoneId' => $cfg['zoneId'],
        'Type' => 'purge_url',
        'Targets' => [$site . '/'],
    ], $cfg);
    if ($result[0]) {
        $result = lt_edgeone_request('CreatePurgeTask', '2022-09-01', [
            'ZoneId' => $cfg['zoneId'],
            'Type' => 'purge_prefix',
            'Targets' => [$site . '/category/', $site . '/tag/'],
        ], $cfg);
    }
    return $result;
}

/**
 * 兜底刷新状态文件（cache/ 目录，运行期产物不随部署）
 */
function lt_edgeone_state_file(): string
{
    return dirname(__DIR__) . '/cache/edgeone-state.php';
}

/**
 * 读取上次已清除的内容版本（MAX(modified)），文件不存在视为 0
 */
function lt_edgeone_read_state(): int
{
    $f = lt_edgeone_state_file();
    if (!is_file($f)) {
        return 0;
    }
    $s = @file_get_contents($f);
    if ($s === false) {
        return 0;
    }
    $n = (int) trim($s);
    return $n > 0 ? $n : 0;
}

/**
 * 记录本次已清除的内容版本
 */
function lt_edgeone_write_state(int $ts): void
{
    $f = lt_edgeone_state_file();
    $dir = dirname($f);
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    @file_put_contents($f, (string) $ts, LOCK_EX);
}

/**
 * 清除记录文件（cache/ 目录，运行期产物不随部署）
 */
function lt_edgeone_clear_log_file(): string
{
    return dirname(__DIR__) . '/cache/edgeone-clearlog.json';
}

/**
 * 读取最近清除记录（旧→新，最近 20 条）
 */
function lt_edgeone_log_read(): array
{
    $f = lt_edgeone_clear_log_file();
    if (!is_file($f)) {
        return [];
    }
    $s = @file_get_contents($f);
    if ($s === false || $s === '') {
        return [];
    }
    $a = json_decode((string) $s, true);
    return is_array($a) ? array_values($a) : [];
}

/**
 * 追加一条清除记录（时间戳 / 范围 / 成败 / 说明），保留最近 20 条，原子写
 */
function lt_edgeone_log_append(int $ts, string $mode, bool $ok, string $detail): void
{
    $f = lt_edgeone_clear_log_file();
    $dir = dirname($f);
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    $list = lt_edgeone_log_read();
    $list[] = ['t' => $ts, 'mode' => $mode, 'ok' => $ok, 'detail' => $detail];
    $list = array_slice($list, -20);
    @file_put_contents($f, json_encode($list, JSON_UNESCAPED_UNICODE), LOCK_EX);
}

/**
 * 前台兜底刷新：脚本结束（响应已发出）时检查内容是否有更新，是则清除 EdgeOne 缓存
 */
register_shutdown_function(static function (): void {
    try {
        $cfg = lt_edgeone_config();
        if (!$cfg['enable'] || $cfg['zoneId'] === '' || $cfg['secretId'] === '' || $cfg['secretKey'] === '') {
            return;
        }
        $db = \Typecho\Db::get();
        $row = $db->fetchRow(
            $db->select('MAX(modified) AS m')
                ->from('table.contents')
                ->where('(type = ? OR type = ?) AND status = ?', 'post', 'page', 'publish')
        );
        $cur = (int) ($row['m'] ?? 0);
        if ($cur <= 0) {
            return;
        }
        if ($cur <= lt_edgeone_read_state()) {
            return; // 内容自上次清除以来无新更新
        }
        $result = $cfg['mode'] === 'dynamic'
            ? lt_edgeone_purge_dynamic($cfg)
            : lt_edgeone_purge_all($cfg);
        if ($result[0]) {
            lt_edgeone_write_state($cur);
            lt_edgeone_log_append(time(), $cfg['mode'], true, 'JobId ' . $result[2]);
        } else {
            lt_log_error('edgeone purge failed: ' . $result[1]);
            lt_edgeone_log_append(time(), $cfg['mode'], false, $result[1]);
        }
    } catch (\Throwable $e) {
        lt_log_error('edgeone purge error', $e);
    }
});

// ============================================================
// 手动测试清除（后台「设置外观」页测试按钮 → fetch POST）
// 仅在带 action=lt_edgeone_purge 的 POST 请求时处理，其余请求放行 Typecho 正常流程
// ============================================================
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'
    && (($_POST['action'] ?? '') === 'lt_edgeone_purge')) {
    header('Content-Type: application/json; charset=utf-8');

    $ltToken = isset($_POST['_']) ? trim((string) $_POST['_']) : '';
    if ($ltToken === '') {
        header('HTTP/1.1 403 Forbidden');
        echo json_encode(['success' => false, 'error' => 'invalid token']);
        exit;
    }
    try {
        $ltSecurity = \Typecho\Widget::widget('\Widget\Security');
        $ltRequest = \Typecho\Request::getInstance();
        $ltExpected = $ltSecurity->getToken($ltRequest->getRequestUrl());
        $ltReferer = (string) $ltRequest->getReferer();
        $ltExpectedReferer = $ltReferer !== '' ? $ltSecurity->getToken($ltReferer) : '';
        $ltTokenValid = hash_equals((string) $ltExpected, $ltToken)
            || ($ltExpectedReferer !== '' && hash_equals($ltExpectedReferer, $ltToken));
        if (!$ltTokenValid) {
            header('HTTP/1.1 403 Forbidden');
            echo json_encode(['success' => false, 'error' => 'invalid token']);
            exit;
        }
    } catch (\Throwable $e) {
        lt_log_error('edgeone test csrf check failed', $e);
        header('HTTP/1.1 403 Forbidden');
        echo json_encode(['success' => false, 'error' => 'invalid token']);
        exit;
    }

    // 管理员身份校验（纵深防御，与 GET 日志读取一致）：仅已登录管理员可触发清除
    try {
        $ltUser = \Typecho\Widget::widget('Widget_User');
        if (!$ltUser->hasLogin() || ((string) $ltUser->group) !== 'administrator') {
            header('HTTP/1.1 403 Forbidden');
            echo json_encode(['success' => false, 'error' => 'unauthorized']);
            exit;
        }
    } catch (\Throwable $e) {
        lt_log_error('edgeone purge auth failed', $e);
        header('HTTP/1.1 403 Forbidden');
        echo json_encode(['success' => false, 'error' => 'unauthorized']);
        exit;
    }

    $cfg = lt_edgeone_config();
    if (!$cfg['enable'] || $cfg['zoneId'] === '' || $cfg['secretId'] === '' || $cfg['secretKey'] === '') {
        echo json_encode(['success' => false, 'error' => '请先在「缓存设置」中启用并填写 ZoneId / SecretId / SecretKey']);
        exit;
    }
    $result = $cfg['mode'] === 'dynamic'
        ? lt_edgeone_purge_dynamic($cfg)
        : lt_edgeone_purge_all($cfg);
    if ($result[0]) {
        // 手动清除后同步内容版本，避免下一次访问重复刷新
        try {
            $ltDb = \Typecho\Db::get();
            $ltRow = $ltDb->fetchRow(
                $ltDb->select('MAX(modified) AS m')->from('table.contents')
                    ->where('(type = ? OR type = ?) AND status = ?', 'post', 'page', 'publish')
            );
            lt_edgeone_write_state((int) ($ltRow['m'] ?? 0));
        } catch (\Throwable $e) {
        }
        lt_edgeone_log_append(time(), $cfg['mode'], true, 'JobId ' . $result[2]);
        echo json_encode(['success' => true, 'jobId' => $result[2], 'mode' => $cfg['mode']]);
        exit;
    }
    lt_edgeone_log_append(time(), $cfg['mode'], false, $result[1]);
    echo json_encode(['success' => false, 'error' => $result[1]]);
    exit;
}

// ============================================================
// 最近清除记录读取（后台「缓存设置」页 JS → GET action=lt_edgeone_log）
// 只读运行期缓存文件（时间/范围/成败/说明，不含密钥），无副作用，无需 CSRF
// ============================================================
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'GET'
    && (($_GET['action'] ?? '') === 'lt_edgeone_log')) {
    header('Content-Type: application/json; charset=utf-8');
    // 仅限已登录管理员读取（运行日志，避免前台匿名可读）
    try {
        $ltUser = \Typecho\Widget::widget('Widget_User');
        if (!$ltUser->hasLogin() || ((string) $ltUser->group) !== 'administrator') {
            header('HTTP/1.1 403 Forbidden');
            echo json_encode(['success' => false, 'error' => 'unauthorized']);
            exit;
        }
    } catch (\Throwable $e) {
        lt_log_error('edgeone log auth failed', $e);
        header('HTTP/1.1 403 Forbidden');
        echo json_encode(['success' => false, 'error' => 'unauthorized']);
        exit;
    }
    $ltLog = lt_edgeone_log_read();
    echo json_encode(['success' => true, 'items' => array_reverse($ltLog)], JSON_UNESCAPED_UNICODE);
    exit;
}
