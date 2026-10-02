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
 * 安全防护：
 *   - 仅允许 http/https 协议；
 *   - host 白名单：站点主机名 / 站点顶级域后缀 / extraHosts（精确或 '.domain' 前缀通配）；
 *     回环地址默认拒绝（$allowLoopback 可开，仅本地调试）；
 *   - 不跟随重定向（防止白名单域 URL 302 到内网地址后被拉取，SSRF 加固）；
 *   - 仅输出位图类型（webp/png/jpg/gif/avif/ico），禁止 svg（防内嵌脚本执行）；
 *   - 响应体大小上限 5MB；带站点 Referer 访问（兼容图床防盗链）；
 *   - 默认开启 TLS 校验（$verifyTls 可关，仅排查用）。
 *
 * 用法：assets/poster-proxy.php?url=https%3A%2F%2Fres.example.com%2Fimages%2Fcover.webp
 */
declare(strict_types=1);

// ---- 按需增补图床域名（不含协议与端口）----
// 精确匹配：'img.example.com'；前缀通配子域：'.example.com'（匹配 a.example.com / b.example.com 等）
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

$siteHost = (string)($_SERVER['HTTP_HOST'] ?? '');
$siteHostClean = parse_url('http://' . $siteHost, PHP_URL_HOST) ?: $siteHost;
$hostParts = explode('.', (string)$siteHostClean);
$topDomain = count($hostParts) >= 2 ? implode('.', array_slice($hostParts, -2)) : $siteHostClean;

$url = isset($_GET['url']) ? trim((string)$_GET['url']) : '';
$scheme = strtolower((string)parse_url($url, PHP_URL_SCHEME));
$host = parse_url($url, PHP_URL_HOST);

$isLocalLoop = $host === 'localhost' || $host === '::1' || preg_match('/^127\.\d{1,3}\.\d{1,3}\.\d{1,3}$/', (string)$host) === 1;
// 私网/保留段 IP 字面量显式拒绝（IPv4/IPv6 私网、127.x、169.254、::1、fe80 等），
// 防止 Host 头伪造场景下绕过域名白名单直达内网（SSRF 防御加深）
$hostIpLiteral = filter_var($host, FILTER_VALIDATE_IP);
if ($hostIpLiteral !== false
    && filter_var($hostIpLiteral, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    exit('forbidden');
}
// extraHosts 支持精确匹配与 '.domain' 前缀通配
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
    && $host !== null && $host !== ''
    && (
        $host === $siteHostClean
        || $host === $topDomain
        || str_ends_with((string)$host, '.' . $topDomain)
        || $isExtra
        || ($isLocalLoop && $allowLoopback)
    );

if (!$isAllowed) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    exit('forbidden');
}

// Referer 协议与站点实际协议一致（http 部署时防盗链图床按 http 校验）
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
$referer = ($isHttps ? 'https' : 'http') . '://' . $siteHost . '/';
$ctx = stream_context_create([
    'http' => [
        'timeout' => 8,
        'ignore_errors' => false,
        'follow_location' => 0,
        'max_redirects' => 0,
        'header' => "Referer: {$referer}\r\nUser-Agent: Mozilla/5.0 (compatible; LanternPress/2.4.0)\r\n",
    ],
    'ssl' => ['verify_peer' => $verifyTls, 'verify_peer_name' => $verifyTls],
]);

$data = @file_get_contents($url, false, $ctx);
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
header('Content-Type: ' . $mime);
header('Access-Control-Allow-Origin: *');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: public, max-age=86400');
echo $data;
