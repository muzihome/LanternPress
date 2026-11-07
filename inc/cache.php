<?php
declare(strict_types=1);

if (!defined('__TYPECHO_ROOT_DIR__')) exit;












if (!defined('LT_CONTENT_CACHE_VERSION')) {
    $ltContentCacheFiles = [__DIR__ . '/core.php', __DIR__ . '/format.php', __DIR__ . '/content.php', dirname(__DIR__) . '/functions.php', dirname(__DIR__) . '/post.php', dirname(__DIR__) . '/sidebar.php'];
    $ltContentCacheSig = '';
    foreach ($ltContentCacheFiles as $ltContentCacheFile) {
        
        $ltContentCacheSig .= (string) @filemtime($ltContentCacheFile);
        $ltContentCacheSig .= (string) @filesize($ltContentCacheFile);
        $ltContentCacheHead = @file_get_contents($ltContentCacheFile, false, null, 0, 64);
        $ltContentCacheTail = @file_get_contents($ltContentCacheFile, false, null, max(0, (int) @filesize($ltContentCacheFile) - 64));
        $ltContentCacheSig .= (string) $ltContentCacheHead . (string) $ltContentCacheTail;
    }
    define('LT_CONTENT_CACHE_VERSION', '4-' . substr(md5($ltContentCacheSig), 0, 8));
}





function lt_cache_dir(string $sub = ''): string
{
    static $dirs = [];
    if (isset($dirs[$sub])) {
        return $dirs[$sub];
    }
    $dir = dirname(__DIR__) . '/cache' . ($sub !== '' ? '/' . ltrim($sub, '/') : '');
    if (!is_dir($dir)) {
        if (!@mkdir($dir, 0755, true) || !is_dir($dir)) {
            lt_log_error('lt_cache_dir: cannot create cache directory: ' . $dir);
            $dirs[$sub] = '';
            return '';
        }
    }

    $htaccess = $dir . '/.htaccess';
    if (!is_file($htaccess)) {
        @file_put_contents(
            $htaccess,
            "<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nOrder allow,deny\nDeny from all\n</IfModule>\n"
        );
    }
    $dirs[$sub] = $dir;
    return $dir;
}


function lt_cache_read(string $path): ?string
{
    if (!is_file($path)) {
        return null;
    }
    $data = @file_get_contents($path);
    if ($data === false || strpos($data, '<?php exit; ?>') !== 0) {
        return null;
    }
    return substr($data, strlen('<?php exit; ?>'));
}


function lt_cache_write(string $path, string $payload, int $maxLen = 60000): bool
{
    if ($payload === '') {
        return false;
    }
    if (strlen($payload) > $maxLen) {
        lt_log_error('lt_cache_write: payload too large path=' . $path . ' len=' . strlen($payload));
        return false;
    }
    $tmp = dirname($path) . '/.' . basename($path) . '.tmp.' . bin2hex(random_bytes(4));
    if (@file_put_contents($tmp, '<?php exit; ?>' . $payload, LOCK_EX) === false) {
        return false;
    }
    if (!@rename($tmp, $path)) {
        @unlink($tmp);
        return false;
    }
    return true;
}

function lt_cache_delete(string $path): void
{
    if (is_file($path)) {
        @unlink($path);
    }
}






function lt_cache_remember(string $group, string $key, int $ttl, callable $load): mixed
{
    $dir = lt_cache_dir();
    if ($dir === '') {
        return $load();
    }
    $path = $dir . '/' . $group . '_' . md5($key) . '.php';
    if ($ttl > 0 && is_file($path) && time() - (int) @filemtime($path) < $ttl) {
        $raw = lt_cache_read($path);
        if ($raw !== null) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded) || is_string($decoded)) {
                return $decoded;
            }
        }
    }
    
    
    
    $lockDir = lt_cache_dir('locks');
    $lockFile = $lockDir !== '' ? $lockDir . '/' . (string) preg_replace('/[^a-z0-9_-]/i', '', $group) . '.lock' : '';
    $fp = $lockFile !== '' ? @fopen($lockFile, 'c') : false;
    $locked = false;
    if ($fp !== false) {
        if (@flock($fp, LOCK_EX | LOCK_NB)) {
            $locked = true;
            
            if (is_file($path) && time() - (int) @filemtime($path) < $ttl) {
                $raw2 = lt_cache_read($path);
                if ($raw2 !== null) {
                    $decoded2 = json_decode($raw2, true);
                    if (is_array($decoded2) || is_string($decoded2)) {
                        @flock($fp, LOCK_UN);
                        @fclose($fp);
                        return $decoded2;
                    }
                }
            }
        }
    }
    
    
    if (!$locked) {
        if ($fp !== false) {
            @fclose($fp); 
        }
        if (is_file($path)) {
            $rawStale = lt_cache_read($path);
            if ($rawStale !== null) {
                $decodedStale = json_decode($rawStale, true);
                if (is_array($decodedStale) || is_string($decodedStale)) {
                    return $decodedStale;
                }
            }
        }
    }
    $data = $load();
    
    
    if (is_array($data) || is_string($data)) {
        lt_cache_write($path, (string) json_encode($data, JSON_UNESCAPED_UNICODE), 512 * 1024);
    }
    if ($locked && $fp !== false) {
        @flock($fp, LOCK_UN);
        @fclose($fp);
    }
    return $data;
}

function lt_cache_forget(string $group, string $key): void
{
    $dir = lt_cache_dir();
    if ($dir === '') {
        return;
    }
    lt_cache_delete($dir . '/' . $group . '_' . md5($key) . '.php');
}







function lt_comment_list_ver(int $cid): int
{
    $dir = lt_cache_dir();
    if ($dir === '') {
        return 0;
    }
    $path = $dir . '/comment_list_ver_' . $cid . '.php';
    $raw = lt_cache_read($path);
    return $raw !== null ? (int) trim($raw) : 0;
}

function lt_comment_list_bump(int $cid): void
{
    $dir = lt_cache_dir();
    if ($dir === '' || $cid <= 0) {
        return;
    }
    $path = $dir . '/comment_list_ver_' . $cid . '.php';
    
    
    
    $fp = @fopen($path, 'c+');
    if ($fp === false || !@flock($fp, LOCK_EX)) {
        if (is_resource($fp)) {
            @fclose($fp);
        }
        lt_cache_write($path, (string) (lt_comment_list_ver($cid) + 1), 32);
        return;
    }
    rewind($fp);
    $raw = (string) stream_get_contents($fp);
    $ver = 0;
    if (strpos($raw, '<?php exit; ?>') === 0) {
        $ver = (int) trim(substr($raw, strlen('<?php exit; ?>')));
    }
    ftruncate($fp, 0);
    rewind($fp);
    @fwrite($fp, '<?php exit; ?>' . ($ver + 1));
    @fflush($fp);
    @flock($fp, LOCK_UN);
    @fclose($fp);
}








function lt_sync_poster_hosts(string $raw): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    $hosts = [];
    foreach (preg_split('/[\r\n,]+/', $raw) ?: [] as $ltLine) {
        $ltHost = strtolower(trim($ltLine));
        if ($ltHost === '' || str_starts_with($ltHost, '#')) {
            continue;
        }
        
        if (preg_match('/^(?:\.)?(?:[a-z0-9](?:[a-z0-9-]*[a-z0-9])?\.)*[a-z0-9](?:[a-z0-9-]*[a-z0-9])?$/', $ltHost) !== 1) {
            continue;
        }
        $hosts[] = $ltHost;
    }
    $hosts = array_values(array_unique($hosts));
    $payload = "<?php\nreturn " . var_export($hosts, true) . ";\n";
    $dir = lt_cache_dir();
    if ($dir === '') {
        return;
    }
    $path = $dir . '/poster_hosts.php';
    if (@file_get_contents($path) === $payload) {
        return;
    }
    $tmp = $path . '.' . bin2hex(random_bytes(4)) . '.tmp';
    if (@file_put_contents($tmp, $payload, LOCK_EX) !== false && @rename($tmp, $path)) {
        return;
    }
    @unlink($tmp);
}

function lt_get_config_hash(): string
{
    return md5('lt-content-v' . LT_CONTENT_CACHE_VERSION);
}



function lt_content_cache_dir(): string
{
    return lt_cache_dir('content');
}

function lt_content_cache_get(int $cid): ?string
{
    if ($cid <= 0 || ($dir = lt_content_cache_dir()) === '') {
        return null;
    }
    return lt_cache_read($dir . '/' . $cid . '.php');
}

function lt_content_cache_set(int $cid, string $payload): void
{
    if ($cid <= 0 || $payload === '' || ($dir = lt_content_cache_dir()) === '') {
        return;
    }
    
    lt_cache_write($dir . '/' . $cid . '.php', $payload, 512 * 1024);
}

function lt_content_cache_delete(int $cid): void
{
    if ($cid <= 0 || ($dir = lt_content_cache_dir()) === '') {
        return;
    }
    lt_cache_delete($dir . '/' . $cid . '.php');
}



function lt_rate_limit_gc(string $cacheDir): void
{
    try {
        foreach ((array) glob($cacheDir . '/rate_limit_*.php') as $file) {
            if (!is_file($file)) {
                continue;
            }
            
            
            $window = 0;
            $data = @file_get_contents($file);
            if ($data !== false && strpos($data, '<?php exit; ?>') === 0) {
                $decoded = json_decode(substr($data, strlen('<?php exit; ?>')), true);
                if (is_array($decoded) && isset($decoded['w'])) {
                    $window = (int) $decoded['w'];
                }
            }
            if (time() - (int) @filemtime($file) > max(86400 * 2, $window)) {
                @unlink($file);
            }
        }
    } catch (\Throwable $e) {

    }
}

function lt_check_rate_limit(string $key, int $maxRequests, int $windowSeconds): bool
{
    if ($maxRequests <= 0 || $windowSeconds <= 0) {
        return true;
    }
    
    
    if (str_contains($key, 'unknown')) {
        $key .= '|ua:' . md5((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''));
    }
    
    $cacheDir = lt_cache_dir();
    if ($cacheDir === '') {
        
        return true;
    }
    $cacheFile = $cacheDir . '/rate_limit_' . md5($key) . '.php';
    $now = time();
    $records = [];
    $allowed = true;


    
    
    $gcStamp = $cacheDir . '/.rate_limit_gc';
    if (!is_file($gcStamp) || $now - (int) @filemtime($gcStamp) > 3600) {
        @touch($gcStamp);
        lt_rate_limit_gc($cacheDir);
    }

    try {
        $fp = @fopen($cacheFile, 'c+');
        if ($fp === false) {

            if (is_file($cacheFile)) {
                $data = @file_get_contents($cacheFile);
                if ($data !== false && strpos($data, '<?php exit; ?>') === 0) {
                    $encoded = substr($data, strlen('<?php exit; ?>'));
                    
                    $decoded = json_decode($encoded, true);
                    if (is_array($decoded)) {
                        
                        $records = (isset($decoded['r']) && is_array($decoded['r'])) ? $decoded['r'] : $decoded;
                    }
                }
            }
            $records = array_values(array_filter($records, static fn($ts) => ($now - $ts) < $windowSeconds));
            if (count($records) >= $maxRequests) {
                return false;
            }
            $records[] = $now;
            if (is_dir($cacheDir) && is_writable($cacheDir)) {
                
                
                $ltTmp = $cacheFile . '.tmp.' . bin2hex(random_bytes(4));
                if (@file_put_contents($ltTmp, '<?php exit; ?>' . json_encode(['w' => $windowSeconds, 'r' => $records]), LOCK_EX) !== false) {
                    @rename($ltTmp, $cacheFile);
                }
            }
            return true;
        }


        
        
        $lockRetries = 0;
        $maxLockRetries = 3;
        while ($lockRetries < $maxLockRetries && !@flock($fp, LOCK_EX | LOCK_NB)) {
            $lockRetries++;
            usleep(50000); 
        }
        if ($lockRetries >= $maxLockRetries && !@flock($fp, LOCK_EX | LOCK_NB)) {
            @fclose($fp);
            return true; 
        }


        rewind($fp);
        $data = stream_get_contents($fp);
        if ($data !== false && strpos($data, '<?php exit; ?>') === 0) {
            $encoded = substr($data, strlen('<?php exit; ?>'));
            
            $decoded = json_decode($encoded, true);
            if (is_array($decoded)) {
                
                $records = (isset($decoded['r']) && is_array($decoded['r'])) ? $decoded['r'] : $decoded;
            }
        }


        $records = array_values(array_filter($records, static fn($ts) => ($now - $ts) < $windowSeconds));


        if (count($records) >= $maxRequests) {
            $allowed = false;
        } else {
            $records[] = $now;

            ftruncate($fp, 0);
            rewind($fp);
            @fwrite($fp, '<?php exit; ?>' . json_encode(['w' => $windowSeconds, 'r' => $records]));
        }

        @flock($fp, LOCK_UN);
        @fclose($fp);
    } catch (\Throwable $e) {

        return true;
    }

    return $allowed;
}

















function lt_check_rate_limit_scoped(string $scope, string $identity, string $item, int $windowSeconds): bool
{
    if ($windowSeconds <= 0 || $item === '') {
        return true;
    }
    $cacheDir = lt_cache_dir();
    if ($cacheDir === '') {
        return true;
    }
    $path = $cacheDir . '/rlsc_' . (string) preg_replace('/[^a-z0-9_-]/i', '', $scope)
        . '_' . md5($identity) . '.php';
    $now = time();
    $fp = @fopen($path, 'c+');
    if ($fp === false) {
        return true; 
    }
    $lockRetries = 0;
    while ($lockRetries < 3 && !@flock($fp, LOCK_EX | LOCK_NB)) {
        $lockRetries++;
        usleep(50000);
    }
    if (!@flock($fp, LOCK_EX | LOCK_NB)) {
        @fclose($fp);
        return true;
    }
    try {
        rewind($fp);
        $raw = (string) stream_get_contents($fp);
        $map = [];
        if (strpos($raw, '<?php exit; ?>') === 0) {
            $decoded = json_decode(substr($raw, strlen('<?php exit; ?>')), true);
            if (is_array($decoded)) {
                $map = $decoded;
            }
        }
        
        $map = array_filter($map, static fn($ts) => is_int($ts) && ($now - $ts) < $windowSeconds);
        if (isset($map[$item])) {
            return false;
        }
        if (count($map) >= 2000) {
            asort($map);
            $map = array_slice($map, -1999, null, true);
        }
        $map[$item] = $now;
        ftruncate($fp, 0);
        rewind($fp);
        @fwrite($fp, '<?php exit; ?>' . json_encode($map));
        @fflush($fp);
        return true;
    } finally {
        @flock($fp, LOCK_UN);
        @fclose($fp);
    }
}
