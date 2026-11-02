<?php
declare(strict_types=1);

if (!defined('__TYPECHO_ROOT_DIR__')) exit;

// ============================================================
// flinks.php — 友链独立数据模型（P2）
// 友链申请从评论表解耦，独立存储于 Typecho options 表 ltFlinksData（JSON）：
//   每条记录：id / name / url / logo / mail / desc / status / sort / created
//   status：1=通过（前台显示） 0=待审 2=拒绝；sort：后台排序权重（P2-2 管理 TAB）
// 提交通道复用评论接口（hooks.php comment 过滤器写数据 + finishComment 删影子评论），
// 审核由后台「设置外观 → 友链管理」TAB 操作（P2-2）。
// 设计原则：DB 读写为薄封装（lt_flinks_data / lt_flinks_save），解析/去重/排序为纯函数可单测。
// 依赖：core.php（lt_text / lt_safe_url / lt_log_error）
// ============================================================

// ---- options 键名 ----
const LT_FLINKS_OPTION = 'ltFlinksData';

// ---- 纯函数：URL 归一化（无协议补 https:// + 白名单协议校验；管理后台保存与前台同规则）----
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

// ---- 纯函数：旧评论行 → flinks 条目（[logo] 前缀 / Markdown 剥离 / 60 字截断） ----
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

// ---- 纯函数：去重判定（同 URL 域名归一化或同邮箱的已通过/待审记录） ----
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

// ---- 纯函数：可见列表排序（status=1，sort 升序 → created 升序） ----
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

// ---- DB 薄封装：读全部 flinks 数据（DB 直读，不经 Options widget 缓存） ----
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

// ---- DB 薄封装：写回 flinks 数据（存在则 update，否则 insert；返回是否成功） ----
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
            $db->query($db->insert('table.options')->rows(['name' => LT_FLINKS_OPTION, 'value' => $payload, 'user' => 0]));
        }
        return true;
    } catch (\Throwable $e) {
        lt_log_error('lt_flinks_save: write failed', $e);
        return false;
    }
}

// ---- 事务化读写：文件锁串行化「读→改→写」窗口（批次A-A1）----
// 背景：lt_flinks_data() 读后经业务修改再 lt_flinks_save() 整表写回，期间并发申请/管理
// 操作会互相覆盖（最后写赢，先写丢失）。本函数以 cache/flink_rw.lock 文件锁把整段
// RMW 串行化；锁目录不可用/竞争极端时宽松降级直通（与限流降级策略一致，不阻断前台）。
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
        return $fn();
    }
    try {
        return $fn();
    } finally {
        @flock($fp, LOCK_UN);
        @fclose($fp);
    }
}

// ---- 统一缓存失效（批次A-A2）：友链页 apply / auto / page 三段缓存一次清空 ----
function lt_flinks_forget_cid(int $cid): void
{
    if ($cid <= 0) {
        return;
    }
    lt_cache_forget('flink', 'apply-' . $cid);
    lt_cache_forget('flink', 'auto-' . $cid);
    lt_cache_forget('flink', 'page-' . $cid);
}

// 全站友链页缓存失效（管理端写操作后调用：页面列表遍历，友链页数量极少）
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

// ---- 申请写入（提交通道调用）：去重 + 分配 id + status 按审核开关（事务化） ----
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

// ---- 兼容回退：flinks 为空时读评论表旧申请（迁移前过渡；迁移后评论被清理自然为空） ----
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

// ---- 迁移：旧 approved 顶层申请评论 → flinks（幂等：flinks 已非空时跳过；事务化） ----
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

// ---- 清理影子评论：迁移后删除旧申请评论（按 cid + 占位/已迁标记，防误删真实评论） ----
function lt_flinks_cleanup_shadow(int $pageCid): int
{
    try {
        $db = \Typecho\Db::get();
        // 仅删除友链页顶层 approved 申请评论（[logo] 开头或含 [flink-apply] 占位标记），parent=0；
        // 普通文章评论 cid 不同，天然不受影响；友链页真实读者评论（无 [logo]/[flink-apply]）保留
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
