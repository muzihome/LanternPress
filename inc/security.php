<?php
declare(strict_types=1);

if (!defined('__TYPECHO_ROOT_DIR__')) exit;









define('LT_CSP_BASE', "default-src 'self'; style-src 'self' 'STYLE_NONCE_PLACEHOLDER' https:; style-src-attr 'unsafe-inline'; img-src 'self' data: blob: https:; font-src 'self' data: https:; connect-src 'self' https:; media-src 'self' https:; object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'self'");




if (!defined('LT_CSP_SCRIPT_SRC_EXTRA')) {
    define('LT_CSP_SCRIPT_SRC_EXTRA', '');
}




function lt_generate_csp_nonce(): string
{
    static $nonce = null;
    if ($nonce !== null) {
        return $nonce;
    }
    try {
        $nonce = bin2hex(random_bytes(16));
    } catch (\Throwable $e) {
        
        $bytes = @openssl_random_pseudo_bytes(16);
        $nonce = $bytes !== false ? bin2hex($bytes) : md5(uniqid((string) mt_rand(), true));
    }
    return $nonce;
}




function lt_add_nonce_to_scripts(string $html, string $nonce): string
{
    if ($html === '' || $nonce === '') {
        return $html;
    }
    
    
    
    return (string) preg_replace_callback(
        '/<\s*script\b([^>]*)>/is',
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
            return '<script' . $attrs . '>';
        },
        $html
    );
}



function lt_build_csp_policy(): string
{
    $nonce = lt_generate_csp_nonce();
    $base = str_replace("'STYLE_NONCE_PLACEHOLDER'", "'nonce-{$nonce}'", LT_CSP_BASE);
    
    $scriptExtra = defined('LT_CSP_SCRIPT_SRC_EXTRA') && is_string(LT_CSP_SCRIPT_SRC_EXTRA)
        ? ' ' . trim(LT_CSP_SCRIPT_SRC_EXTRA) : '';
    return "script-src 'self' 'nonce-{$nonce}'{$scriptExtra}; " . $base;
}


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
    
    
    if (strpos((string) ($_SERVER['REQUEST_URI'] ?? ''), '/admin/') !== false) {
        return;
    }
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    
    header('Permissions-Policy: geolocation=(), microphone=(), camera=()');

    header('Content-Security-Policy: ' . lt_build_csp_policy());
    
    header('X-Typecho-Theme: LanternPress/' . LT_THEME_VERSION);

    
    
    
    if (lt_is_https() && lt_hsts_enabled()) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}





function lt_hsts_enabled(): bool
{
    try {
        $ltOpts = \Typecho\Widget::widget('Widget_Options');
        $ltVal = isset($ltOpts->ltHstsEnable) ? (string) $ltOpts->ltHstsEnable : '';
    } catch (\Throwable $e) {
        return false;
    }
    return $ltVal === 'on';
}

function lt_is_https(): bool
{
    if ((!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')
        || (int) ($_SERVER['SERVER_PORT'] ?? 0) === 443) {
        return true;
    }
    
    
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







function lt_poster_ip_is_public(string $ip): bool
{
    if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
        return false;
    }
    return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
}




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
