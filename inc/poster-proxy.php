<?php
/**
 * LanternPress 海报封面代理（兜底方案）
 *
 * 用途：当图床（如腾讯云 COS/CDN）未配置 CORS（Access-Control-Allow-Origin）
 *       或跨域直连被拦截时，将跨域图片经本代理同域转发，
 *       使海报画布可导出文章主图与站点 LOGO。
 * 说明：正常部署时前端优先跨域直连（依赖 COS/CDN 的 CORS 规则），
 *       仅当直连失败时才回退到本代理。
 *
 * 安全防护（v2.4.0 加固）：
 *   - 静态域名白名单：仅放行下方 $extraHosts 显式配置的图床域名（精确或 '.domain' 前缀通配），
 *     不再信任客户端可控的 HTTP_HOST 构建白名单（Host 头投毒 SSRF 根治）；
 *   - 仅允许 http/https 协议；端口仅 80/443；不跟随重定向（防 302 跳内网）；
 *   - IPv6 字面量归一化后统一做 IP 校验（私网/回环/链路本地/保留段拒绝）；
 *   - DNS 解析校验：域名型目标请求前解析全部 A/AAAA 记录，任一解析结果属私网/回环/
 *     链路本地/保留段则拒绝；解析失败同样拒绝（DNS 重绑定防护，安全默认）；
 *   - 轻量限流：同一客户端 IP 窗口内计数（独立实现，不依赖 Typecho 环境）；
 *   - 仅输出位图类型（webp/png/jpg/gif/avif/ico），禁止 svg（防内嵌脚本执行）；
 *     无扩展名时以内容嗅探（getimagesizefromstring）兜底；
 *   - 响应体大小上限 5MB；带站点 Referer 访问（兼容图床防盗链）；
 *   - 默认开启 TLS 校验（$verifyTls 可关，仅排查用）；
 *   - 回环地址默认拒绝（$allowLoopback 可开，仅本地调试）。
 *
 * 用法：inc/poster-proxy.php?url=https%3A%2F%2Fres.example.com%2Fimages%2Fcover.webp
 */
declare(strict_types=1);

// ---- 图床域名静态白名单（不含协议与端口）----
// 安全基线：仅放行此处显式配置的域名；精确匹配 'img.example.com'，前缀通配 '.example.com'
// （匹配 a.example.com / b.example.com 等子域）。站点自身域名的图片为同域直连，无需走代理。
$extraHosts = [
    // 'img.example.com',
    // '.oss.example.com',
];

// ---- 安全配置 ----
// 回环地址（127.x/localhost/::1）放行开关：本地开发调试时可置 true；
// 生产环境务必保持 false，防止文章内容被注入内网地址时经代理读取内网响应（SSRF 面）
$allowLoopback = false;
// TLS 校验开关：生产环境保持 true；若图床证书异常导致获取失败，可临时置 false 排查
$verifyTls = true;
// 轻量限流：同一客户端 IP 在 $rateWindow 秒窗口内的最大请求数（0 表示关闭限流）
$rateLimit = 30;
$rateWindow = 60;

// 客户端可控的 HTTP_HOST 仅用于拼接防盗链 Referer（图床按站点域名校验），
// 已做字符白名单校验；不参与任何安全放行判定（Host 头投毒 SSRF 根治）
$siteHost = (string)($_SERVER['HTTP_HOST'] ?? '');
if ($siteHost === '' || preg_match('/[^a-zA-Z0-9.\-:\[\]]/', $siteHost) === 1) {
    $siteHost = '';
}
$siteHostClean = $siteHost !== ''
    ? strtolower(trim((string)parse_url('http://' . $siteHost, PHP_URL_HOST) ?: $siteHost, '[]'))
    : '';

$url = isset($_GET['url']) ? trim((string)$_GET['url']) : '';
$scheme = strtolower((string)parse_url($url, PHP_URL_SCHEME));
// IPv6 字面量归一化：parse_url('http://[::1]/') 返回带方括号的 '[::1]'，
// 剥离方括号后统一做 IP 校验与白名单比较（IPv6 绕过修复）
$host = trim(strtolower((string)parse_url($url, PHP_URL_HOST)), '[]');
$port = (int) parse_url($url, PHP_URL_PORT);
// 端口限制：仅允许未显式指定端口（默认 80/443）或显式 80/443，
// 防止白名单域携带任意端口（如 :6379）经代理访问非 HTTP 服务（SSRF 加固）
$portOk = $port === 0 || $port === 80 || $port === 443;

// 回环判定（host 已归一化，方括号不影响比较）
$isLocalLoop = $host === 'localhost' || $host === '::1'
    || preg_match('/^127\.\d{1,3}\.\d{1,3}\.\d{1,3}$/', (string)$host) === 1;
// 私网/保留段 IP 字面量显式拒绝（IPv4/IPv6 私网、127.x、169.254、::1、fe80 等）
$hostIpLiteral = filter_var($host, FILTER_VALIDATE_IP);
if ($hostIpLiteral !== false
    && filter_var($hostIpLiteral, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    exit('forbidden');
}

// DNS 解析校验（DNS 重绑定防护）：域名型目标请求前解析全部 A/AAAA 记录，
// 任一解析结果属私网/回环/链路本地/保留段则拒绝；解析失败同样拒绝（安全默认）
if ($hostIpLiteral === false && $host !== '' && $host !== 'localhost') {
    $resolvedIps = [];
    $aRecords = gethostbynamel($host);
    if ($aRecords !== false) {
        $resolvedIps = array_merge($resolvedIps, $aRecords);
    }
    $aaaaRecords = @dns_get_record($host, DNS_AAAA);
    if (is_array($aaaaRecords)) {
        foreach ($aaaaRecords as $r) {
            if (!empty($r['ipv6'])) {
                $resolvedIps[] = (string)$r['ipv6'];
            }
        }
    }
    if ($resolvedIps === []) {
        // 解析失败：无法验证目标安全性，拒绝（安全默认）
        http_response_code(403);
        header('Content-Type: text/plain; charset=utf-8');
        exit('forbidden');
    }
    foreach ($resolvedIps as $ip) {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            http_response_code(403);
            header('Content-Type: text/plain; charset=utf-8');
            exit('forbidden');
        }
    }
}

// 静态白名单判定：仅放行 $extraHosts 显式配置的图床域名（精确或 '.domain' 前缀通配）
$isExtra = false;
foreach ($extraHosts as $eh) {
    $eh = strtolower(trim((string)$eh));
    if ($eh === '') {
        continue;
    }
    if ($eh === $host || (str_starts_with($eh, '.') && str_ends_with((string)$host, $eh))) {
        $isExtra = true;
        break;
    }
}
$isAllowed = in_array($scheme, ['http', 'https'], true)
    && $host !== '' && $host !== null
    && $portOk
    && ($isExtra || ($isLocalLoop && $allowLoopback));

if (!$isAllowed) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    exit('forbidden');
}

// 轻量限流：同一客户端 IP 窗口内计数（独立实现，不依赖 Typecho 环境）
if ($rateLimit > 0) {
    $ltClientIp = (string)($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
    // 限流文件路径：优先 sys_get_temp_dir()，失败时按优先级 fallback 到主题同级 cache 目录、最后回退到 '/tmp'
    // '/tmp' 在 Windows 上通常不存在（@is_dir 会返回 false），但第 144 行对其做了特殊放行直接进入 is_writable 检查；
    // Windows 环境下这个 fallback 自然跳过，不会报错或阻塞请求
    $ltRateBaseDirs = [
        rtrim(sys_get_temp_dir(), '/\\'),
        rtrim(__DIR__ . '/../cache', '/\\'),
        '/tmp',
    ];
    $ltRateDir = '';
    foreach ($ltRateBaseDirs as $ltCand) {
        if ($ltCand !== '' && ($ltCand === '/tmp' || @is_dir($ltCand) || @mkdir($ltCand, 0755, true))) {
            if (@is_writable($ltCand)) {
                $ltRateDir = $ltCand;
                break;
            }
        }
    }
    if ($ltRateDir !== '') {
        $ltRateFile = $ltRateDir . '/lt-poster-' . md5($ltClientIp) . '.tmp';
        $ltNow = time();
        $ltFp = @fopen($ltRateFile, 'c+');
        if ($ltFp !== false) {
            if (@flock($ltFp, LOCK_EX)) {
                $ltRaw = (string)@stream_get_contents($ltFp);
                $ltCount = 0;
                $ltWindowStart = $ltNow;
                // 固定窗口：窗口起点保留至窗口期届满才重置（此前误按秒级时间戳相等判定，窗口实为 1 秒）
                if (preg_match('/^(\d+):(\d+)$/', trim($ltRaw), $ltM) === 1 && ($ltNow - (int)$ltM[1]) < $rateWindow) {
                    $ltCount = (int)$ltM[2];
                    $ltWindowStart = (int)$ltM[1];
                }
                if ($ltCount >= $rateLimit) {
                    @flock($ltFp, LOCK_UN);
                    @fclose($ltFp);
                    http_response_code(429);
                    header('Content-Type: text/plain; charset=utf-8');
                    exit('rate limited');
                }
                rewind($ltFp);
                @ftruncate($ltFp, 0);
                @fwrite($ltFp, $ltWindowStart . ':' . ($ltCount + 1));
                @fflush($ltFp);
                @flock($ltFp, LOCK_UN);
            }
            @fclose($ltFp);
        }
    } else {
        // 所有 fallback 路径都不可写：限流完全失效，写入 PHP error_log 供运维排查
        error_log('[LanternPress poster-proxy] rate-limit file dir not writable, check open_basedir / SELinux / sys_get_temp_dir');
    }
}

// Referer 协议与站点实际协议一致（http 部署时防盗链图床按 http 校验）；
// 转发协议头仅在可信代理白名单内采信（与主题 lt_is_https 同源策略），防伪造
$ltRemoteAddr = !empty($_SERVER['REMOTE_ADDR']) ? trim((string) $_SERVER['REMOTE_ADDR']) : '';
$ltTrustedProxies = defined('LT_TRUSTED_PROXY_IPS') ? LT_TRUSTED_PROXY_IPS : null;
$ltProxyList = is_array($ltTrustedProxies)
    ? $ltTrustedProxies
    : (is_string($ltTrustedProxies) && $ltTrustedProxies !== ''
        ? array_map('trim', explode(',', $ltTrustedProxies))
        : []);
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (int) ($_SERVER['SERVER_PORT'] ?? 0) === 443
    || ((($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https') && in_array($ltRemoteAddr, $ltProxyList, true));
$referer = $siteHostClean !== ''
    ? (($isHttps ? 'https' : 'http') . '://' . $siteHostClean . '/')
    : '';

// DNS 重绑定 TOCTOU 加固：将请求固定到此前已校验的解析 IP，
// 使"DNS 校验通过"与"实际请求"指向同一 IP，消除校验后解析结果被篡改的窗口。
// cURL 不可用时回退 file_get_contents（保留 TOCTOU 风险，但静态域名白名单已大幅收敛攻击面）。
$resolvedIps = $resolvedIps ?? [];
$fetchPort = $port > 0 ? $port : ($scheme === 'https' ? 443 : 80);
$pinnedIp = null;
if (!empty($resolvedIps)) {
    $pinnedIp = $resolvedIps[0];
} elseif ($hostIpLiteral !== false) {
    $pinnedIp = $host;
}

$data = false;
if ($pinnedIp !== null && function_exists('curl_init')) {
    $ch = curl_init($url);
    $reqHeaders = ['User-Agent: Mozilla/5.0 (compatible; LanternPress/2.5.0)'];
    if ($referer !== '') {
        $reqHeaders[] = 'Referer: ' . $referer;
    }
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 8,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_RESOLVE => [$host . ':' . $fetchPort . ':' . $pinnedIp],
        CURLOPT_HTTPHEADER => $reqHeaders,
        CURLOPT_SSL_VERIFYPEER => $verifyTls,
        CURLOPT_SSL_VERIFYHOST => $verifyTls ? 2 : 0,
    ]);
    $data = curl_exec($ch);
    $curlErr = curl_error($ch);
    curl_close($ch);
    if ($data === false && $curlErr !== '') {
        error_log('[LanternPress poster-proxy] curl fetch failed: ' . $curlErr);
    }
}

if ($data === false) {
    // 回退：cURL 不可用或抓取失败时使用 file_get_contents
    $ctx = stream_context_create([
        'http' => [
            'timeout' => 8,
            'ignore_errors' => false,
            'follow_location' => 0,
            'max_redirects' => 0,
            'header' => ($referer !== '' ? "Referer: {$referer}\r\n" : '')
                . "User-Agent: Mozilla/5.0 (compatible; LanternPress/2.5.0)\r\n",
        ],
        'ssl' => ['verify_peer' => $verifyTls, 'verify_peer_name' => $verifyTls],
    ]);
    $data = @file_get_contents($url, false, $ctx);
}

if ($data === false || $data === '') {
    http_response_code(502);
    header('Content-Type: text/plain; charset=utf-8');
    exit('fetch failed');
}
if (strlen($data) > 5 * 1024 * 1024) {
    http_response_code(413);
    header('Content-Type: text/plain; charset=utf-8');
    exit('too large');
}

// MIME 判定：扩展名白名单优先；无扩展名时以内容嗅探兜底（防误标 octet-stream）
$ext = strtolower((string)pathinfo((string)parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));
$mime = match ($ext) {
    'webp' => 'image/webp',
    'png' => 'image/png',
    'jpg', 'jpeg' => 'image/jpeg',
    'gif' => 'image/gif',
    'avif' => 'image/avif',
    'ico' => 'image/x-icon',
    default => 'application/octet-stream',
};
if ($mime === 'application/octet-stream') {
    $imgInfo = @getimagesizefromstring($data);
    if ($imgInfo !== false && isset($imgInfo['mime'])) {
        $mime = match ($imgInfo['mime']) {
            'image/webp' => 'image/webp',
            'image/png' => 'image/png',
            'image/jpeg' => 'image/jpeg',
            'image/gif' => 'image/gif',
            'image/avif' => 'image/avif',
            'image/x-icon' => 'image/x-icon',
            default => 'application/octet-stream',
        };
    }
}
header('Content-Type: ' . $mime);
// CORS 收窄：仅允许站点自身 origin（同域图片代理无需 CORS；子域部署场景兼容），不再使用通配 *
if ($siteHostClean !== '') {
    header('Access-Control-Allow-Origin: ' . (($isHttps ? 'https' : 'http') . '://' . $siteHostClean));
}
header('X-Content-Type-Options: nosniff');
header('Cache-Control: public, max-age=86400');
echo $data;
