<?php
declare(strict_types=1);

if (!defined('__TYPECHO_ROOT_DIR__')) exit;

// ============================================================
// hooks.php — 内容发布钩子（缓存/字段清理）与点赞 Ajax POST 接口
// 由 functions.php 加载（core.php 模块链已就绪）
// ============================================================

\Typecho\Plugin::factory('\Widget\Contents\Post\Edit')->finishArticle = function ($contents = null): void {
    // 归档缓存失效（统一缓存层命名）+ 一次性遗留清理旧固定命名文件
    lt_cache_forget('archive', 'archive');
    $ltLegacyArchiveCache = dirname(__DIR__) . '/cache/archive-cache.php';
    if (is_file($ltLegacyArchiveCache)) {
        @unlink($ltLegacyArchiveCache);
    }

    if (is_array($contents) && isset($contents['cid'])) {

        lt_content_cache_delete((int) $contents['cid']);
        lt_delete_field((int) $contents['cid'], 'ltProcessedContent');
    }
};

// ============================================================
// 友链申请「网站LOGO」持久化（H1）
// 友链申请表单（flinks-template.php）含 <input name="logo">，Typecho 评论处理不消费该字段，
// 提交后静默丢弃。这里在评论入库前（Widget_Feedback->comment 过滤器，validator 之后、insert 之前）
// 把 logo 经 lt_safe_url 白名单校验后拼为 [logo]URL[/logo] 前缀写入评论 text，
// 由 flinks-template.php 三档图标解析（①[logo]标记 → ②邮箱头像 → ③首字徽标）。
// 普通文章评论表单无 logo 字段，不受影响；text 已以 [logo] 开头时不再重复拼接。
// 注意：评论提交请求会经 Archive 初始化加载主题 functions.php，本钩子注册时机可靠。
// 仅当评论所属页面为友链页（自定义模板 flinks-template）且 text 未以 [logo] 开头时才拼接，
// 避免普通文章评论区被注入同名 POST 字段时把 [logo] 标记混入可见评论内容。
// ============================================================
\Typecho\Plugin::factory('Widget_Feedback')->comment = static function (array $comment, $content): array {
    if (!is_object($content) || !str_contains((string) ($content->template ?? ''), 'flink')) {
        return $comment;
    }
    $ltFlinkLogo = isset($_POST['logo']) ? trim((string) $_POST['logo']) : '';
    if ($ltFlinkLogo === '') {
        return $comment;
    }
    $ltSafeLogo = lt_safe_url($ltFlinkLogo);
    if ($ltSafeLogo === '' || $ltSafeLogo === 'http://' || $ltSafeLogo === 'https://') {
        return $comment;
    }
    $ltText = (string) ($comment['text'] ?? '');
    if (str_starts_with($ltText, '[logo]')) {
        return $comment;
    }
    $comment['text'] = '[logo]' . $ltSafeLogo . '[/logo] ' . $ltText;
    return $comment;
};


if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $ltAction = isset($_POST['action']) ? trim((string) $_POST['action']) : '';
    if ($ltAction === 'lt_like') {
        header('Content-Type: application/json; charset=utf-8');

        $ltClientIp = lt_get_client_ip();
        if (!lt_check_rate_limit('like_' . $ltClientIp, 5, 10)) {
            header('HTTP/1.1 429 Too Many Requests');
            echo json_encode(['success' => false, 'error' => '请求过于频繁，请稍后再试']);
            exit;
        }


        $ltToken = isset($_POST['_']) ? trim((string) $_POST['_']) : '';
        if ($ltToken === '') {
            header('HTTP/1.1 403 Forbidden');
            echo json_encode(['success' => false, 'error' => 'invalid token']);
            exit;
        }
        try {
            $ltSecurity = \Typecho\Widget::widget('\Widget\Security');
            // 双候选校验：页面 token 按当前请求 URL 生成（post.php），与 Referer 任一匹配即通过；
            // 隐私模式/内嵌浏览器无 Referer 时不再误伤（CSRF 防护仍由会话绑定 token 保证）
            $ltRequest = \Typecho\Request::getInstance();
            $ltExpected = $ltSecurity->getToken($ltRequest->getRequestUrl());
            $ltReferer = (string) $ltRequest->getReferer();
            $ltExpectedReferer = $ltReferer !== '' ? $ltSecurity->getToken($ltReferer) : '';
            $ltTokenValid = hash_equals((string) $ltExpected, $ltToken)
                || ($ltExpectedReferer !== '' && hash_equals($ltExpectedReferer, $ltToken));
            if (!$ltTokenValid) {
                header('HTTP/1.1 403 Forbidden');
                echo json_encode(['success' => false, 'error' => 'invalid token']);
                exit;
            }
        } catch (\Throwable $e) {
            lt_log_error('like csrf check failed', $e);
            header('HTTP/1.1 403 Forbidden');
            echo json_encode(['success' => false, 'error' => 'invalid token']);
            exit;
        }
        $ltCid = isset($_POST['cid']) ? (int) $_POST['cid'] : 0;
        if ($ltCid <= 0) {
            echo json_encode(['success' => false, 'error' => 'invalid cid']);
            exit;
        }

        try {
            $ltDb = \Typecho\Db::get();
            $ltPostRow = $ltDb->fetchRow(
                $ltDb->select('cid')
                    ->from('table.contents')
                    ->where('cid = ? AND status = ?', $ltCid, 'publish')
                    ->limit(1)
            );
            if (!$ltPostRow) {
                echo json_encode(['success' => false, 'error' => 'article not found']);
                exit;
            }
        } catch (\Throwable $e) {
            lt_log_error('like article existence check failed cid=' . $ltCid, $e);
            // 存在性校验自身出错时不应继续写入字段，明确返回 500 而非静默放行
            header('HTTP/1.1 500 Internal Server Error');
            echo json_encode(['success' => false, 'error' => 'server error']);
            exit;
        }

        $ltLikes = lt_increment_likes($ltCid);
        // 字段写入失败时 lt_increment_likes 返回 0（正常首赞从 1 起），不得谎报成功
        if ($ltLikes <= 0) {
            header('HTTP/1.1 500 Internal Server Error');
            echo json_encode(['success' => false, 'error' => 'server error']);
            exit;
        }
        echo json_encode([
            'success' => true,
            'likes' => $ltLikes,
            'liked' => true
        ]);
        exit;
    }
}
