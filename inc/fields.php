<?php
declare(strict_types=1);

if (!defined('__TYPECHO_ROOT_DIR__')) exit;










define('LT_FIELD_MISSING', new \stdClass());

function lt_field_cache(int $cid, string $name, mixed $value = null, bool $delete = false): mixed
{
    static $cache = [];
    $key = $cid . '|' . $name;
    if ($delete) {
        unset($cache[$key]);
        return null;
    }
    
    
    if (func_num_args() >= 3) {
        $cache[$key] = $value;
        return $value;
    }
    
    return array_key_exists($key, $cache) ? $cache[$key] : LT_FIELD_MISSING;
}

function lt_field_upsert(
    \Typecho\Db $db,
    int $cid,
    string $name,
    string $type,
    string $intValueRaw,
    string $strValue,
    float $floatValue,
    mixed $updateInt = null,
    ?int $incrementBy = null,
    ?string $updateStr = null
): void {
    $prefix = $db->getPrefix();
    $table = $prefix . 'fields';
    $adapter = $db->getAdapter();
    $adapterClass = get_class($adapter);
    
    
    
    
    if (!method_exists($adapter, 'quoteValue')) {
        throw new \RuntimeException('Typecho Db Adapter does not implement quoteValue(); refusing unsafe upsert for fields table');
    }
    $nameQuoted = $adapter->quoteValue($name);
    $strQuoted = $adapter->quoteValue($strValue);
    $intValueQuoted = (string) (int) $intValueRaw;


    $isPgOrSQLite = stripos($adapterClass, 'Pgsql') !== false
        || stripos($adapterClass, 'SQLite') !== false;

    if ($isPgOrSQLite) {
        $sql = "INSERT INTO \"{$table}\" (\"cid\", \"name\", \"type\", \"int_value\", \"str_value\", \"float_value\") "
             . "VALUES ({$cid}, {$nameQuoted}, '{$type}', {$intValueQuoted}, {$strQuoted}, {$floatValue}) "
             . "ON CONFLICT (\"cid\", \"name\") DO UPDATE SET ";

        $sets = [];
        if ($incrementBy !== null) {
            $sets[] = "\"int_value\" = \"int_value\" + " . (int) $incrementBy;
        } elseif ($updateInt !== null) {
            $sets[] = "\"int_value\" = " . (int) $updateInt;
        }
        if ($updateStr !== null) {
            $updateStrQuoted = $adapter->quoteValue($updateStr);
            $sets[] = "\"str_value\" = {$updateStrQuoted}";
        }
        if (empty($sets)) {

            $sql = "INSERT INTO \"{$table}\" (\"cid\", \"name\", \"type\", \"int_value\", \"str_value\", \"float_value\") "
                 . "VALUES ({$cid}, {$nameQuoted}, '{$type}', {$intValueQuoted}, {$strQuoted}, {$floatValue}) "
                 . "ON CONFLICT (\"cid\", \"name\") DO NOTHING";
        } else {
            $sql .= implode(', ', $sets);
        }
    } else {

        $sql = "INSERT INTO `{$table}` (`cid`, `name`, `type`, `int_value`, `str_value`, `float_value`) "
             . "VALUES ({$cid}, {$nameQuoted}, '{$type}', {$intValueQuoted}, {$strQuoted}, {$floatValue}) "
             . "ON DUPLICATE KEY UPDATE ";

        $sets = [];
        if ($incrementBy !== null) {
            $sets[] = "`int_value` = `int_value` + " . (int) $incrementBy;
        } elseif ($updateInt !== null) {
            $sets[] = "`int_value` = " . (int) $updateInt;
        }
        if ($updateStr !== null) {
            $updateStrQuoted = $adapter->quoteValue($updateStr);
            $sets[] = "`str_value` = {$updateStrQuoted}";
        }
        if (empty($sets)) {
            $sets[] = "`cid` = `cid`";
        }
        $sql .= implode(', ', $sets);
    }

    $db->query($sql);
}

function lt_get_field_int(int $cid, string $name): int
{
    if ($cid <= 0 || $name === '') {
        return 0;
    }

    $cached = lt_field_cache($cid, $name);
    if ($cached !== LT_FIELD_MISSING) {
        return $cached;
    }
    try {
        $db = \Typecho\Db::get();
        $row = $db->fetchRow(
            $db->select('int_value')
                ->from('table.fields')
                ->where('cid = ? AND name = ?', $cid, $name)
                ->limit(1)
        );
        $value = $row ? (int) ($row['int_value'] ?? 0) : 0;
        lt_field_cache($cid, $name, $value);
        return $value;
    } catch (\Throwable $e) {
        lt_log_error('lt_get_field_int query failed cid=' . $cid . ' name=' . $name, $e);
        return 0;
    }
}

function lt_set_field_int(int $cid, string $name, int $value): void
{
    if ($cid <= 0 || $name === '') {
        return;
    }
    try {
        $db = \Typecho\Db::get();

        lt_field_upsert($db, $cid, $name, 'int', (string) $value, '0', 0, $value);

        lt_field_cache($cid, $name, $value);
    } catch (\Throwable $e) {
        lt_log_error('lt_set_field_int upsert failed cid=' . $cid . ' name=' . $name . ' value=' . $value, $e);
    }
}



function lt_atomic_increment_field(int $cid, string $name, int $step = 1): int
{
    if ($cid <= 0 || $name === '' || $step === 0) {
        return 0;
    }
    try {
        $db = \Typecho\Db::get();

        lt_field_upsert($db, $cid, $name, 'int', (string) $step, '', 0, null, $step, null);

        $row = $db->fetchRow(
            $db->select('int_value')
                ->from('table.fields')
                ->where('cid = ? AND name = ?', $cid, $name)
                ->limit(1)
        );
        $value = $row ? (int) ($row['int_value'] ?? 0) : $step;
        lt_field_cache($cid, $name, $value);
        return $value;
    } catch (\Throwable $e) {
        lt_log_error('lt_atomic_increment_field failed cid=' . $cid . ' name=' . $name . ' step=' . $step, $e);
        return 0;
    }
}



function lt_get_field_str(int $cid, string $name): ?string
{
    if ($cid <= 0 || $name === '') {
        return null;
    }

    
    $cached = lt_field_cache($cid, $name);
    if ($cached !== LT_FIELD_MISSING) {
        return is_string($cached) ? $cached : null;
    }
    try {
        $db = \Typecho\Db::get();
        $row = $db->fetchRow(
            $db->select('str_value')
                ->from('table.fields')
                ->where('cid = ? AND name = ?', $cid, $name)
                ->limit(1)
        );
        $value = $row ? ($row['str_value'] ?? null) : null;
        lt_field_cache($cid, $name, $value);
        return $value;
    } catch (\Throwable $e) {
        lt_log_error('lt_get_field_str query failed cid=' . $cid . ' name=' . $name, $e);
        return null;
    }
}

function lt_set_field_str(int $cid, string $name, string $value): void
{
    if ($cid <= 0 || $name === '') {
        return;
    }


    if (strlen($value) > 60000) {
        lt_log_error('lt_set_field_str value too large cid=' . $cid . ' name=' . $name . ' length=' . strlen($value), null);
        return;
    }
    try {
        $db = \Typecho\Db::get();

        lt_field_upsert($db, $cid, $name, 'str', '0', $value, 0.0, null, null, $value);
        lt_field_cache($cid, $name, $value);
    } catch (\Throwable $e) {
        lt_log_error('lt_set_field_str upsert failed cid=' . $cid . ' name=' . $name, $e);
    }
}

function lt_delete_field(int $cid, string $name): void
{
    if ($cid <= 0 || $name === '') {
        return;
    }
    try {
        $db = \Typecho\Db::get();
        $db->query(
            $db->delete('table.fields')
                ->where('cid = ? AND name = ?', $cid, $name)
        );
        lt_field_cache($cid, $name, null, true);
    } catch (\Throwable $e) {
        lt_log_error('lt_delete_field failed cid=' . $cid . ' name=' . $name, $e);
    }
}

function lt_get_views(int $cid): int
{
    return lt_get_field_int($cid, 'ltViews');
}

function lt_increment_views(int $cid): void
{
    if ($cid <= 0) {
        return;
    }
    $cookieName = 'lt_views_' . $cid;
    if (!empty($_COOKIE[$cookieName])) {
        return;
    }
    
    
    
    if (!lt_check_rate_limit_scoped('views', lt_get_client_ip(), (string) $cid, 60)) {
        return;
    }

    
    
    try {
        $db = \Typecho\Db::get();
        lt_field_upsert($db, $cid, 'ltViews', 'int', '1', '', 0, null, 1, null);
        
        
        lt_field_cache($cid, 'ltViews', null, true);
    } catch (\Throwable $e) {
        lt_log_error('lt_increment_views upsert failed cid=' . $cid, $e);
    }
    if (!headers_sent()) {
        
        setcookie($cookieName, '1', [
            'expires' => time() + 86400,
            'path' => '/',
            'secure' => lt_is_https(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
}

function lt_get_likes(int $cid): int
{
    return lt_get_field_int($cid, 'ltLikes');
}

function lt_has_liked(int $cid): bool
{
    return $cid > 0 && !empty($_COOKIE['lt_liked_' . $cid]);
}

function lt_increment_likes(int $cid): int
{
    if ($cid <= 0) {
        return 0;
    }
    $cookieName = 'lt_liked_' . $cid;
    if (!empty($_COOKIE[$cookieName])) {
        return lt_get_likes($cid);
    }
    
    
    $ltLikeIp = lt_get_client_ip();
    if ($ltLikeIp !== '' && !lt_check_rate_limit_scoped('like', $ltLikeIp, (string) $cid, 31536000)) {
        return lt_get_likes($cid);
    }

    $newValue = lt_atomic_increment_field($cid, 'ltLikes');
    if (!headers_sent()) {
        setcookie($cookieName, '1', [
            'expires' => time() + 31536000,
            'path' => '/',
            'secure' => lt_is_https(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
    return $newValue;
}
