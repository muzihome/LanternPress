<?php
declare(strict_types=1);

if (!defined('__TYPECHO_ROOT_DIR__')) exit;

// ============================================================
// security.php — CSP nonce 体系 / 安全响应头 / 客户端 IP / HTTPS 判定 / 错误日志
// 依赖：core.php 需先定义 LT_THEME_VERSION（lt_send_security_headers 使用）
// ============================================================

// script-src 由 lt_build_csp_policy() 动态注入 nonce，此处仅保留除 script-src 外的其余指令
// （script-src 每次请求变化，不能用 define 硬编码）
// style-src 同样用 nonce（批次27），style-src-attr 'unsafe-inline' 单独放行 style="..." 属性（CSP Level 3 正确指令名）
define('LT_CSP_BASE', "default-src 'self'; style-src 'self' 'STYLE_NONCE_PLACEHOLDER' https:; style-src-attr 'unsafe-inline'; img-src 'self' data: blob: https:; font-src 'self' data: https:; connect-src 'self' https:; media-src 'self' https:; object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'self'");

// 广告/统计第三方脚本域白名单：默认空=仅 self+nonce。启用广告位脚本时在此追加域名
// （如 'https://pagead2.googlesyndication.com https://*.googlesyndication.com'），
// 勿用 'https:' 全放行（会绕过 nonce 保护）。可在 config.inc.php 或主题配置中覆盖。
if (!defined('LT_CSP_SCRIPT_SRC_EXTRA')) {
    define('LT_CSP_SCRIPT_SRC_EXTRA', '');
}

// CSP nonce（批次26）：per-request 随机值，所有 <script nonce="xxx"> 需匹配才放行。
// 去掉 script-src 'unsafe-inline' 的前提——主题硬编码的 inline JS 已移至 lantern.core.js，
// 管理员可控的 $customJs / $statisticsCodeSafe 由 lt_add_nonce_to_scripts() 自动改写加 nonce。
function lt_generate_csp_nonce(): string
{
    static $nonce = null;
    if ($nonce !== null) {
        return $nonce;
    }
    try {
        $nonce = bin2hex(random_bytes(16));
    } catch (\Throwable $e) {
        // random_bytes 在极旧环境可能不可用，fallback 到 openssl / uniqid（低熵但能用）
        $bytes = @openssl_random_pseudo_bytes(16);
        $nonce = $bytes !== false ? bin2hex($bytes) : md5(uniqid((string) mt_rand(), true));
    }
    return $nonce;
}

// 给 HTML 片段内所有 <script 标签（含 <script> 无属性形式）加 nonce="xxx"，
// 使管理员自定义 JS / 统计代码内联脚本在 nonce 模式下能正常执行。
// 仅改写 <script> 标签本身，不触碰标签内容。
function lt_add_nonce_to_scripts(string $html, string $nonce): string
{
    if ($html === '' || $nonce === '') {
        return $html;
    }
    // <script>  → <script nonce="xxx">
    // <script ... > → <script nonce="xxx" ... >
    // 大小写不敏感，兼容各种写法
    return (string) preg_replace_callback(
        '/<\s*script\b([^>]*)>/is',
        static function (array $m) use ($nonce): string {
            $attrs = $m[1] ?? '';
            // 如果已有 nonce 属性，替换为当前值（避免重复或旧 nonce）
            if (preg_match('/\snonce\s*=\s*(["\']?)([^"\'>\s]*)\1/i', $attrs)) {
                $attrs = preg_replace(
                    '/\snonce\s*=\s*(["\']?)([^"\'>\s]*)\1/i',
                    ' nonce="' . $nonce . '"',
                    $attrs
                );
            } else {
                $attrs = ' nonce="' . $nonce . '"' . $attrs;
            }
            return '<script' . $attrs . '>';
        },
        $html
    );
}

// 构建完整 CSP：script-src + style-src 均带 per-request nonce，其余指令来自 LT_CSP_BASE
// LT_CSP_BASE 里 style-src 有占位符 'nonce-StyleNoncePlaceholder'，此处统一替换
function lt_build_csp_policy(): string
{
    $nonce = lt_generate_csp_nonce();
    $base = str_replace("'STYLE_NONCE_PLACEHOLDER'", "'nonce-{$nonce}'", LT_CSP_BASE);
    // 追加广告/统计第三方脚本域白名单（默认空；LT_CSP_SCRIPT_SRC_EXTRA 可配置）
    $scriptExtra = defined('LT_CSP_SCRIPT_SRC_EXTRA') && is_string(LT_CSP_SCRIPT_SRC_EXTRA)
        ? ' ' . trim(LT_CSP_SCRIPT_SRC_EXTRA) : '';
    return "script-src 'self' 'nonce-{$nonce}'{$scriptExtra}; " . $base;
}

// 给 HTML 片段内所有 <style> 标签加 nonce="xxx"（批次27，与 lt_add_nonce_to_scripts 对称）
function lt_add_nonce_to_styles(string $html, string $nonce): string
{
    if ($html === '' || $nonce === '') {
        return $html;
    }
    return (string) preg_replace_callback(
        '/<\s*style\b([^>]*)>/is',
        static function (array $m) use ($nonce): string {
            $attrs = $m[1] ?? '';
            if (preg_match('/\snonce\s*=\s*(["\']?)([^"\'>\s]*)\1/i', $attrs)) {
                $attrs = preg_replace(
                    '/\snonce\s*=\s*(["\']?)([^"\'>\s]*)\1/i',
                    ' nonce="' . $nonce . '"',
                    $attrs
                );
            } else {
                $attrs = ' nonce="' . $nonce . '"' . $attrs;
            }
            return '<style' . $attrs . '>';
        },
        $html
    );
}

function lt_send_security_headers(): void
{
    if (headers_sent()) {
        return;
    }
    // 后台请求不发送主题安全头：Typecho 后台依赖内联脚本，nonce-only CSP 会将其全部拦截。
    // 此前依赖「后台先输出 HTML 再加载 functions.php」的 headers_sent 时序隐式规避，此处显式化防回归
    if (strpos((string) ($_SERVER['REQUEST_URI'] ?? ''), '/admin/') !== false) {
        return;
    }
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    // 站点无任何需要摄像头/麦克风/定位的功能，显式禁用收紧浏览器能力面（#4）
    header('Permissions-Policy: geolocation=(), microphone=(), camera=()');

    header('Content-Security-Policy: ' . lt_build_csp_policy());
    // 主题身份标记：方便运维排查 header 冲突（其他插件/内核可能覆盖 CSP）
    header('X-Typecho-Theme: LanternPress/' . LT_THEME_VERSION);

    if (lt_is_https()) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}

function lt_is_https(): bool
{
    if ((!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')
        || (int) ($_SERVER['SERVER_PORT'] ?? 0) === 443) {
        return true;
    }
    // 转发协议头仅在可信代理白名单内采信（与 lt_get_client_ip 同源策略），
    // 防止无代理的 HTTP 站点被伪造 X-Forwarded-Proto 导致 Secure Cookie / HSTS 行为异常
    if (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower((string) $_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https') {
        $ltRemoteAddr = !empty($_SERVER['REMOTE_ADDR']) ? trim((string) $_SERVER['REMOTE_ADDR']) : '';
        $ltTrustedProxies = defined('LT_TRUSTED_PROXY_IPS') ? LT_TRUSTED_PROXY_IPS : null;
        $ltProxyList = is_array($ltTrustedProxies)
            ? $ltTrustedProxies
            : (is_string($ltTrustedProxies) && $ltTrustedProxies !== ''
                ? array_map('trim', explode(',', $ltTrustedProxies))
                : []);
        if (in_array($ltRemoteAddr, $ltProxyList, true)) {
            return true;
        }
    }
    return false;
}

function lt_log_error(string $message, ?\Throwable $e = null): void
{
    try {
        $logMsg = '[LanternPress] ' . $message;
        if ($e !== null) {
            $logMsg .= ' | ' . get_class($e) . ': ' . $e->getMessage() . ' | File: ' . $e->getFile() . ':' . $e->getLine();
        }
        error_log($logMsg);
    } catch (\Throwable $e) {

    }
}

function lt_get_client_ip(): string
{
    $remoteAddr = !empty($_SERVER['REMOTE_ADDR']) ? trim((string) $_SERVER['REMOTE_ADDR']) : '';



    $trustedProxies = defined('LT_TRUSTED_PROXY_IPS') ? LT_TRUSTED_PROXY_IPS : null;
    $isTrustedProxy = false;

    if ($trustedProxies !== null) {
        if (is_array($trustedProxies)) {
            $isTrustedProxy = in_array($remoteAddr, $trustedProxies, true);
        } elseif (is_string($trustedProxies) && $trustedProxies !== '') {
            $list = array_map('trim', explode(',', $trustedProxies));
            $isTrustedProxy = in_array($remoteAddr, $list, true);
        }
    } else {


        $isTrustedProxy = false;
    }

    $ip = '';
    if ($isTrustedProxy) {
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = trim(explode(',', (string) $_SERVER['HTTP_X_FORWARDED_FOR'])[0]);
        } elseif (!empty($_SERVER['HTTP_X_REAL_IP'])) {
            $ip = trim((string) $_SERVER['HTTP_X_REAL_IP']);
        }
    }


    if ($ip === '') {
        $ip = $remoteAddr;
    }

    return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : 'unknown';
}

// ============================================================
// poster-proxy SSRF 防护纯函数（与端点同源规则，可离线单测）
// ============================================================

// 公网 IP 判定：私网/回环/链路本地/保留段一律视为非公网（false）。
// IPv4/IPv6 统一走 filter_var 内建 flags，非法字符串同样 false（安全默认）。
function lt_poster_ip_is_public(string $ip): bool
{
    if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
        return false;
    }
    return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
}

// 代理目标静态判定（不触网）：http/https 协议白名单 + 端口仅 80/443/默认 +
// 静态域名白名单（精确或 '.domain' 前缀通配）+ 回环地址需显式开关。
// DNS 解析校验含网络副作用，保留在端点内联执行；本函数覆盖全部离线可测规则。
function lt_poster_target_allowed(string $scheme, string $host, int $port, array $extraHosts, bool $allowLoopback = false): bool
{
    $host = trim(strtolower($host), '[]');
    $scheme = strtolower($scheme);
    $portOk = $port === 0 || $port === 80 || $port === 443;

    $isLocalLoop = $host === 'localhost' || $host === '::1'
        || preg_match('/^127\.\d{1,3}\.\d{1,3}\.\d{1,3}$/', $host) === 1;

    $isExtra = false;
    foreach ($extraHosts as $eh) {
        $eh = strtolower(trim((string) $eh));
        if ($eh === '') {
            continue;
        }
        if ($eh === $host || (str_starts_with($eh, '.') && str_ends_with($host, $eh))) {
            $isExtra = true;
            break;
        }
    }

    return in_array($scheme, ['http', 'https'], true)
        && $host !== ''
        && $portOk
        && ($isExtra || ($isLocalLoop && $allowLoopback));
}

// 点赞接口 CSRF 双候选校验（纯函数）：当前请求 URL token 或 Referer token 任一匹配即通过。
// 常数时间比较（hash_equals）；token 为空或候选均为空时恒 false（安全默认）。
function lt_csrf_token_matches(string $token, string $expected, string $expectedReferer = ''): bool
{
    if ($token === '') {
        return false;
    }
    if ($expected !== '' && hash_equals($expected, $token)) {
        return true;
    }
    return $expectedReferer !== '' && hash_equals($expectedReferer, $token);
}
