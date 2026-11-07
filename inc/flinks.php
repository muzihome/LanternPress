<?php
declare(strict_types=1);

if (!defined('__TYPECHO_ROOT_DIR__')) exit;













const LT_FLINKS_OPTION = 'ltFlinksData';


function lt_flinks_normalize_url(string $url): string
{
    $url = trim($url);
    if ($url === '') {
        return '';
    }
    if (preg_match('#^[a-z][a-z0-9+.-]*://#i', $url) !== 1) {
        $url = 'https://' . $url;
    }
    return lt_safe_url($url);
}


function lt_flinks_parse_entry(array $row, callable $fixUrl): array
{
    $name = trim(lt_text($row['author'] ?? ''));
    if ($name === '') {
        return [];
    }
    $url = lt_safe_url($fixUrl(lt_text($row['url'] ?? '')));
    $rawText = lt_text($row['text'] ?? '');
    $logo = '';
    if (preg_match('/\[logo\](.+?)\[\/logo\]/s', $rawText, $m) === 1) {
        $logo = lt_safe_url(trim($m[1]));
    }
    $rawText = (string) preg_replace('/\[logo\].*?\[\/logo\]/s', '', $rawText);
    $rawText = (string) preg_replace('/[*_~`]{1,3}([^*_~`\n]+)[*_~`]{1,3}/u', '$1', $rawText);
    $desc = trim(strip_tags($rawText));
    return [
        'id' => 0,
        'name' => $name,
        'url' => $url !== '' ? $url : '#',
        'logo' => $logo,
        'mail' => lt_text($row['mail'] ?? ''),
        'desc' => $desc !== '' ? \Typecho\Common::subStr($desc, 0, 60, '...') : '',
        'status' => 1,
        'sort' => 0,
        'created' => (int) ($row['created'] ?? time()),
    ];
}


function lt_flinks_dup_exists(array $data, string $url, string $mail): bool
{
    $urlNorm = strtolower(preg_replace('#^[a-z][a-z0-9+.-]*://#i', '', trim($url)) ?? '');
    $urlNorm = rtrim(preg_replace('#[/?].*$#', '', $urlNorm) ?? '', '/');
    $mailNorm = strtolower(trim($mail));
    foreach ($data as $entry) {
        if (is_array($entry) && (int) ($entry['status'] ?? 1) !== 2) {
            if ($urlNorm !== '') {
                $eUrl = strtolower(preg_replace('#^[a-z][a-z0-9+.-]*://#i', '', (string) ($entry['url'] ?? '')) ?? '');
                $eUrl = rtrim(preg_replace('#[/?].*$#', '', $eUrl) ?? '', '/');
                if ($eUrl !== '' && $eUrl === $urlNorm) {
                    return true;
                }
            }
            if ($mailNorm !== '' && $mailNorm === strtolower(trim((string) ($entry['mail'] ?? '')))) {
                return true;
            }
        }
    }
    return false;
}


function lt_flinks_visible(array $data): array
{
    $visible = array_values(array_filter($data, static fn($e) => is_array($e) && (int) ($e['status'] ?? 0) === 1));
    usort($visible, static function (array $a, array $b): int {
        $sa = (int) ($a['sort'] ?? 0);
        $sb = (int) ($b['sort'] ?? 0);
        if ($sa !== $sb) {
            return $sa <=> $sb;
        }
        return ((int) ($a['created'] ?? 0)) <=> ((int) ($b['created'] ?? 0));
    });
    return $visible;
}


function lt_flinks_data(): array
{
    try {
        $db = \Typecho\Db::get();
        $row = $db->fetchRow(
            $db->select('value')->from('table.options')->where('name = ?', LT_FLINKS_OPTION)->limit(1)
        );
        if (!is_array($row) || empty($row['value'])) {
            return [];
        }
        $decoded = json_decode((string) $row['value'], true);
        return is_array($decoded) ? $decoded : [];
    } catch (\Throwable $e) {
        lt_log_error('lt_flinks_data: read failed', $e);
        return [];
    }
}


function lt_flinks_save(array $data): bool
{
    try {
        $db = \Typecho\Db::get();
        $payload = (string) json_encode(array_values($data), JSON_UNESCAPED_UNICODE);
        $exists = $db->fetchRow(
            $db->select('name')->from('table.options')->where('name = ?', LT_FLINKS_OPTION)->limit(1)
        );
        if ($exists) {
            $db->query($db->update('table.options')->rows(['value' => $payload])->where('name = ?', LT_FLINKS_OPTION));
        } else {
            try {
                $db->query($db->insert('table.options')->rows(['name' => LT_FLINKS_OPTION, 'value' => $payload, 'user' => 0]));
            } catch (\Throwable $eInsert) {
                
                
                try {
                    $db->query($db->update('table.options')->rows(['value' => $payload])->where('name = ?', LT_FLINKS_OPTION));
                } catch (\Throwable $eUpdate) {
                    lt_log_error('lt_flinks_save: insert-conflict then update failed', $eUpdate);
                    return false;
                }
            }
        }
        return true;
    } catch (\Throwable $e) {
        lt_log_error('lt_flinks_save: write failed', $e);
        return false;
    }
}





function lt_flinks_transaction(callable $fn): mixed
{
    $dir = lt_cache_dir();
    if ($dir === '') {
        return $fn();
    }
    $fp = @fopen($dir . '/flink_rw.lock', 'c+');
    if ($fp === false) {
        return $fn();
    }
    $lockRetries = 0;
    while ($lockRetries < 3 && !@flock($fp, LOCK_EX | LOCK_NB)) {
        $lockRetries++;
        usleep(50000);
    }
    if ($lockRetries >= 3 && !@flock($fp, LOCK_EX | LOCK_NB)) {
        @fclose($fp);
        
        
        lt_log_error('lt_flinks_transaction: lock unavailable after retries, running degraded', null);
        return $fn();
    }
    try {
        return $fn();
    } finally {
        @flock($fp, LOCK_UN);
        @fclose($fp);
    }
}


function lt_flinks_forget_cid(int $cid): void
{
    if ($cid <= 0) {
        return;
    }
    lt_cache_forget('flink', 'apply-' . $cid);
    lt_cache_forget('flink', 'auto-' . $cid);
    lt_cache_forget('flink', 'page-' . $cid);
}


function lt_flinks_forget_all_pages(): void
{
    try {
        $widget = \Typecho\Widget::widget('Widget_Contents_Page_List');
        if ($widget instanceof \Widget\Contents\Page\Rows) {
            while ($widget->next()) {
                lt_flinks_forget_cid((int) $widget->cid);
            }
        }
    } catch (\Throwable $e) {
        lt_log_error('lt_flinks_forget_all_pages: pages query failed', $e);
    }
}


function lt_flinks_apply(array $entry, bool $requireModeration): ?int
{
    return lt_flinks_transaction(static function () use ($entry, $requireModeration): ?int {
        $data = lt_flinks_data();
        if (lt_flinks_dup_exists($data, (string) ($entry['url'] ?? ''), (string) ($entry['mail'] ?? ''))) {
            return null;
        }
        $nextId = 1;
        foreach ($data as $e) {
            $nextId = max($nextId, (int) ($e['id'] ?? 0) + 1);
        }
        $entry['id'] = $nextId;
        $entry['status'] = $requireModeration ? 0 : 1;
        $entry['created'] = time();
        $data[] = $entry;
        return lt_flinks_save($data) ? $nextId : null;
    });
}


function lt_flinks_legacy(int $pageCid, callable $fixUrl): array
{
    try {
        $db = \Typecho\Db::get();
        $rows = $db->fetchAll(
            $db->select('author', 'mail', 'url', 'text', 'created')
                ->from('table.comments')
                ->where('cid = ?', $pageCid)
                ->where('type = ?', 'comment')
                ->where('status = ?', 'approved')
                ->where('parent = ?', 0)
                ->order('created', \Typecho\Db::SORT_ASC)
        );
    } catch (\Throwable $e) {
        lt_log_error('lt_flinks_legacy: query failed', $e);
        return [];
    }
    $links = [];
    foreach ((array) $rows as $row) {
        $entry = lt_flinks_parse_entry($row, $fixUrl);
        if ($entry !== []) {
            $links[] = $entry;
        }
    }
    return $links;
}


function lt_flinks_migrate(int $pageCid, callable $fixUrl): array
{
    return lt_flinks_transaction(static function () use ($pageCid, $fixUrl): array {
        if (lt_flinks_data() !== []) {
            return ['migrated' => 0, 'skipped' => 1, 'reason' => 'flinks 数据已存在，跳过迁移（防重复）'];
        }
        try {
            $db = \Typecho\Db::get();
            $rows = $db->fetchAll(
                $db->select('author', 'mail', 'url', 'text', 'created')
                    ->from('table.comments')
                    ->where('cid = ?', $pageCid)
                    ->where('type = ?', 'comment')
                    ->where('status = ?', 'approved')
                    ->where('parent = ?', 0)
                    ->order('created', \Typecho\Db::SORT_ASC)
            );
        } catch (\Throwable $e) {
            lt_log_error('lt_flinks_migrate: query failed', $e);
            return ['migrated' => 0, 'skipped' => 0, 'reason' => 'query failed'];
        }
        $data = [];
        foreach ((array) $rows as $row) {
            $entry = lt_flinks_parse_entry($row, $fixUrl);
            if ($entry === []) {
                continue;
            }
            $entry['id'] = count($data) + 1;
            $entry['status'] = 1;
            $data[] = $entry;
        }
        if ($data === []) {
            return ['migrated' => 0, 'skipped' => 0, 'reason' => '无已通过申请'];
        }
        return lt_flinks_save($data)
            ? ['migrated' => count($data), 'skipped' => 0, 'reason' => '']
            : ['migrated' => 0, 'skipped' => 0, 'reason' => 'write failed'];
    });
}


function lt_flinks_cleanup_shadow(int $pageCid): int
{
    try {
        $db = \Typecho\Db::get();
        
        
        $rows = $db->fetchAll(
            $db->select('coid', 'text')
                ->from('table.comments')
                ->where('cid = ?', $pageCid)
                ->where('type = ?', 'comment')
                ->where('status = ?', 'approved')
                ->where('parent = ?', 0)
        );
        $deleted = 0;
        foreach ((array) $rows as $row) {
            $text = (string) ($row['text'] ?? '');
            if (str_starts_with($text, '[logo]') || str_starts_with($text, '[flink-apply]')) {
                $db->query($db->delete('table.comments')->where('coid = ?', (int) $row['coid']));
                $deleted++;
            }
        }
        return $deleted;
    } catch (\Throwable $e) {
        lt_log_error('lt_flinks_cleanup_shadow: failed', $e);
        return 0;
    }
}
