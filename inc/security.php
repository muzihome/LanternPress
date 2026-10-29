<?php
declare(strict_types=1);

if (!defined('__TYPECHO_ROOT_DIR__')) exit;

// ============================================================
// security.php — CSP nonce 体系 / 安全响应头 / 客户端 IP / HTTPS 判定 / 错误日志
// 依赖：core.php 需先定义 LT_THEME_VERSION（lt_send_security_headers 使用）
// ============================================================

// script-src 由 lt_build_csp_policy() 动态注入 nonce，此处仅保留除 script-src 外的其余指令
// （script-src 每次请求变化，不能用 define 硬编码）
// style-src 同样用 nonce（批次27），style-attr 'unsafe-inline' 单独放行 style="..." 属性（CSP Level 3）
define('LT_CSP_BASE', "default-src 'self'; style-src 'self' 'STYLE_NONCE_PLACEHOLDER' https:; style-attr 'unsafe-inline'; img-src 'self' data: blob: https:; font-src 'self' data: https:; connect-src 'self' https:; media-src 'self' https:; object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'self'");

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
    return "script-src 'self' 'nonce-{$nonce}'; " . $base;
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
