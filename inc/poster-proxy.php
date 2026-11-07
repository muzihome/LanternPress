<?php

























declare(strict_types=1);






$extraHosts = [];
$ltPosterHostsFile = dirname(__DIR__) . '/cache/poster_hosts.php';
if (is_file($ltPosterHostsFile)) {
    $ltExtraHosts = @include $ltPosterHostsFile;
    if (is_array($ltExtraHosts)) {
        $extraHosts = $ltExtraHosts;
    }
}




$allowLoopback = false;

$verifyTls = true;


$rateLimit = 80;
$rateWindow = 60;




if (!defined('__TYPECHO_ROOT_DIR__')) {
    define('__TYPECHO_ROOT_DIR__', dirname(__DIR__, 4));
}
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/cache.php';



$siteHost = (string)($_SERVER['HTTP_HOST'] ?? '');
if ($siteHost === '' || preg_match('/[^a-zA-Z0-9.\-:\[\]]/', $siteHost) === 1) {
    $siteHost = '';
}
$siteHostClean = $siteHost !== ''
    ? strtolower(trim((string)parse_url('http://' . $siteHost, PHP_URL_HOST) ?: $siteHost, '[]'))
    : '';

$url = isset($_GET['url']) ? trim((string)$_GET['url']) : '';
$scheme = strtolower((string)parse_url($url, PHP_URL_SCHEME));


$host = trim(strtolower((string)parse_url($url, PHP_URL_HOST)), '[]');
$port = (int) parse_url($url, PHP_URL_PORT);




$hostIpLiteral = filter_var($host, FILTER_VALIDATE_IP);
if ($hostIpLiteral !== false && !lt_poster_ip_is_public($host)) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    exit('forbidden');
}



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
        
        http_response_code(403);
        header('Content-Type: text/plain; charset=utf-8');
        exit('forbidden');
    }
    foreach ($resolvedIps as $ip) {
        if (!lt_poster_ip_is_public($ip)) {
            http_response_code(403);
            header('Content-Type: text/plain; charset=utf-8');
            exit('forbidden');
        }
    }
}


if (!lt_poster_target_allowed($scheme, $host, $port, $extraHosts, $allowLoopback)) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    exit('forbidden');
}




if ($rateLimit > 0) {
    if (!lt_check_rate_limit('poster_' . lt_get_client_ip(), $rateLimit, $rateWindow)) {
        http_response_code(429);
        header('Content-Type: text/plain; charset=utf-8');
        exit('rate limited');
    }
}



$posterCacheDir = dirname(__DIR__) . '/cache/poster';
$posterCacheKey = md5($url);
$posterCacheBin = $posterCacheDir . '/' . $posterCacheKey . '.bin';
$posterCacheMeta = $posterCacheDir . '/' . $posterCacheKey . '.meta';
$posterCacheTtl = 7 * 24 * 3600;
$posterCacheHit = false;
$posterCachedMime = null;
if (is_file($posterCacheBin) && is_file($posterCacheMeta)
    && time() - (int) @filemtime($posterCacheBin) < $posterCacheTtl) {
    $cached = @file_get_contents($posterCacheBin);
    if ($cached !== false && $cached !== '') {
        $posterCacheHit = true;
        $data = $cached;
        $posterCachedMime = trim((string) @file_get_contents($posterCacheMeta));
    }
}



$isHttps = lt_is_https();
$referer = $siteHostClean !== ''
    ? (($isHttps ? 'https' : 'http') . '://' . $siteHostClean . '/')
    : '';





$resolvedIps = $resolvedIps ?? [];
$fetchPort = $port > 0 ? $port : ($scheme === 'https' ? 443 : 80);
$pinnedIp = null;
if (!empty($resolvedIps)) {
    $pinnedIp = $resolvedIps[0];
} elseif ($hostIpLiteral !== false) {
    $pinnedIp = $host;
}

if (!$posterCacheHit) {
    $data = false;
}
if (!$posterCacheHit && $pinnedIp !== null && function_exists('curl_init')) {
    $ch = curl_init($url);
    $reqHeaders = ['User-Agent: Mozilla/5.0 (compatible; LanternPress/2.5.0)'];
    if ($referer !== '') {
        $reqHeaders[] = 'Referer: ' . $referer;
    }
    
    $resolveIp = (str_contains((string)$pinnedIp, ':') && !str_starts_with((string)$pinnedIp, '['))
        ? '[' . $pinnedIp . ']' : (string)$pinnedIp;
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 8,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_RESOLVE => [$host . ':' . $fetchPort . ':' . $resolveIp],
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



if ($posterCacheHit && $posterCachedMime !== '' && str_starts_with($posterCachedMime, 'image/')) {
    $mime = $posterCachedMime;
} else {
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
}


if (!$posterCacheHit && $data !== false && $data !== '') {
    if (!is_dir($posterCacheDir)) {
        @mkdir($posterCacheDir, 0755, true);
    }
    $tmpBin = $posterCacheBin . '.tmp' . mt_rand();
    if (@file_put_contents($tmpBin, $data) !== false) {
        @rename($tmpBin, $posterCacheBin);
        $tmpMeta = $posterCacheMeta . '.tmp' . mt_rand();
        if (@file_put_contents($tmpMeta, $mime) !== false) {
            @rename($tmpMeta, $posterCacheMeta);
        }
    }
}

header('Content-Type: ' . $mime);

if ($siteHostClean !== '') {
    header('Access-Control-Allow-Origin: ' . (($isHttps ? 'https' : 'http') . '://' . $siteHostClean));
}
header('X-Content-Type-Options: nosniff');
header('Cache-Control: public, max-age=86400');
echo $data;
