<?php
declare(strict_types=1);

if (!defined('__TYPECHO_ROOT_DIR__')) exit;






























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

    
    $canonicalHeaders = "content-type:application/json; charset=utf-8\nhost:{$host}\n";
    $signedHeaders = 'content-type;host';
    $canonicalRequest = "POST\n/\n\n{$canonicalHeaders}\n{$signedHeaders}\n"
        . hash('sha256', $body);

    
    $credentialScope = "{$date}/{$service}/tc3_request";
    $stringToSign = "TC3-HMAC-SHA256\n{$timestamp}\n{$credentialScope}\n"
        . hash('sha256', $canonicalRequest);

    
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




function lt_edgeone_purge_all(array $cfg): array
{
    return lt_edgeone_request('CreatePurgeTask', '2022-09-01', [
        'ZoneId' => $cfg['zoneId'],
        'Type' => 'purge_all',
    ], $cfg);
}




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




function lt_edgeone_state_file(): string
{
    return dirname(__DIR__) . '/cache/edgeone-state.php';
}




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




function lt_edgeone_write_state(int $ts): void
{
    $f = lt_edgeone_state_file();
    $dir = dirname($f);
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    @file_put_contents($f, (string) $ts, LOCK_EX);
}




function lt_edgeone_clear_log_file(): string
{
    return dirname(__DIR__) . '/cache/edgeone-clearlog.json';
}




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







function lt_edgeone_dirty_file(): string
{
    return dirname(__DIR__) . '/cache/edgeone.dirty';
}




function lt_edgeone_mark_dirty(): void
{
    $f = lt_edgeone_dirty_file();
    $dir = dirname($f);
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    @file_put_contents($f, '0', LOCK_EX);
}




function lt_edgeone_clear_dirty(): void
{
    $f = lt_edgeone_dirty_file();
    if (is_file($f)) {
        @unlink($f);
    }
}




register_shutdown_function(static function (): void {
    try {
        $flag = lt_edgeone_dirty_file();
        if (!is_file($flag)) {
            return; 
        }
        $attempts = (int) @file_get_contents($flag);
        if ($attempts >= 5) {
            return; 
        }
        $cfg = lt_edgeone_config();
        if (!$cfg['enable'] || $cfg['zoneId'] === '' || $cfg['secretId'] === '' || $cfg['secretKey'] === '') {
            return;
        }
        $result = $cfg['mode'] === 'dynamic'
            ? lt_edgeone_purge_dynamic($cfg)
            : lt_edgeone_purge_all($cfg);
        if ($result[0]) {
            lt_edgeone_clear_dirty();
            lt_edgeone_write_state(time()); 
            lt_edgeone_log_append(time(), $cfg['mode'], true, 'JobId ' . $result[2]);
        } else {
            @file_put_contents($flag, (string) ($attempts + 1), LOCK_EX);
            lt_log_error('edgeone purge failed: ' . $result[1]);
            lt_edgeone_log_append(time(), $cfg['mode'], false, $result[1]);
        }
    } catch (\Throwable $e) {
        lt_log_error('edgeone purge error', $e);
    }
});








function lt_edgeone_json_begin(): void
{
    while (ob_get_level() > 0) {
        @ob_end_clean();
    }
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
    }
}





if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'
    && (($_POST['action'] ?? '') === 'lt_edgeone_purge')) {
    lt_edgeone_json_begin();

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
        
        lt_edgeone_write_state(time());
        lt_edgeone_clear_dirty();
        lt_edgeone_log_append(time(), $cfg['mode'], true, 'JobId ' . $result[2]);
        echo json_encode(['success' => true, 'jobId' => $result[2], 'mode' => $cfg['mode']]);
        exit;
    }
    lt_edgeone_log_append(time(), $cfg['mode'], false, $result[1]);
    echo json_encode(['success' => false, 'error' => $result[1]]);
    exit;
}





if (($_SERVER['REQUEST_METHOD'] ?? '') === 'GET'
    && (($_GET['action'] ?? '') === 'lt_edgeone_log')) {
    lt_edgeone_json_begin();
    
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
