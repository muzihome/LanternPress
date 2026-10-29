<?php
declare(strict_types=1);

if (!defined('__TYPECHO_ROOT_DIR__')) exit;

// ============================================================
// fields.php — 自定义字段存取（ltViews / ltLikes / ltProcessedContent 等）
// 请求内静态缓存 + 跨库 upsert（MySQL / PgSQL / SQLite）；浏览量/点赞含 Cookie + IP 限频去重
// 依赖：security.php（lt_log_error / lt_get_client_ip / lt_is_https）、cache.php（lt_check_rate_limit）
// ============================================================

// 字段缓存哨兵值：区分"键不存在（未查库）"与"键存在但值为 null"。
// lt_field_cache 读操作在键缺失时返回此哨兵，调用方以 === 判等即可区分，
// 避免 lt_get_field_str 等场景用额外的 $queried 数组记录查库状态。
define('LT_FIELD_MISSING', new \stdClass());

function lt_field_cache(int $cid, string $name, mixed $value = null, bool $delete = false): mixed
{
    static $cache = [];
    $key = $cid . '|' . $name;
    if ($delete) {
        unset($cache[$key]);
        return null;
    }
    // 用 func_num_args 区分"读"（2 参）与"写"（3+ 参），允许将 null 作为合法值写入缓存；
    // 此前以 $value !== null 判定写入，导致 null 值无法被缓存（每次都会重新查库）。
    if (func_num_args() >= 3) {
        $cache[$key] = $value;
        return $value;
    }
    // 键不存在时返回哨兵 LT_FIELD_MISSING，与"缓存值为 null"区分开
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
    // Typecho 1.3.0+ Db Adapter 接口定义 quoteValue()（非 quote()），
    // 1.2 起全部适配器均已实现 quoteValue——不存在不支持的适配器。
    // 若 method_exists 返回 false 说明运行时环境异常（自定义适配器/版本兼容问题），
    // 此时拒绝写入比 addslashes 静默产生坏数据更安全。
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

    // 哨兵 LT_FIELD_MISSING 表示尚未查库；非哨兵（含 null）表示已查过，直接返回缓存值
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
    // IP 限频：同一 IP 60 秒内仅计一次，防清除 cookie 后反复刷量
    if (!lt_check_rate_limit('views_' . $cid . '_' . lt_get_client_ip(), 1, 60)) {
        return;
    }

    lt_atomic_increment_field($cid, 'ltViews');
    if (!headers_sent()) {
        // PHP 7.3+ 支持 options 数组形式；显式声明 SameSite=Lax 与 CSP form-action 'self' 保持一致
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
    // IP 一次去重（1 年窗口）：清 cookie 后同 IP 不可重复刷赞
    $ltLikeIp = lt_get_client_ip();
    if ($ltLikeIp !== '' && !lt_check_rate_limit('like_once_' . $cid . '_' . $ltLikeIp, 1, 31536000)) {
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
