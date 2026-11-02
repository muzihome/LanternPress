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
        // 友链页正文变更 → 友链三段缓存统一失效（apply/auto/page，flinks-template.php）
        lt_flinks_forget_cid((int) $contents['cid']);
    }
};

// ============================================================
// 友链列表缓存失效（P0-1）
// flinks-template.php 的申请列表缓存（apply-{cid}）与正文自动解析缓存（auto-{cid}）
// 在以下三个时点即时清空（TTL 300s 兜底）：
//   1) 评论提交完成（Widget_Feedback->finishComment，评论 insert 之后）；
//   2) 后台审核状态变更（Widget_Comments_Edit->mark，approved/rejected/spam）；
//   3) 评论删除（Widget_Comments_Edit->delete）。
// 审核通过后友链即时进入列表，无需等待 TTL。
// ============================================================
\Typecho\Plugin::factory('Widget_Feedback')->finishComment = static function ($feedback = null): void {
    // P2 影子评论清理：本申请产生的占位评论（text 以 [flink-apply] 开头）在插入后立即删除，
    // 实现评论表零残留（友链申请完全从评论流/统计解耦）；真实读者评论不受影响
    try {
        $coid = (int) ($feedback->coid ?? 0);
        $text = (string) ($feedback->text ?? '');
        if ($coid > 0 && str_starts_with($text, '[flink-apply]')) {
            $db = \Typecho\Db::get();
            $db->query($db->delete('table.comments')->where('coid = ?', $coid));
        }
    } catch (\Throwable $e) {
        lt_log_error('flink shadow comment cleanup failed', $e);
    }
    try {
        $ltCid = (int) \Typecho\Request::getInstance()->get('cid');
        if ($ltCid > 0) {
            lt_flinks_forget_cid($ltCid);
            lt_comment_list_bump($ltCid);
        }
    } catch (\Throwable $e) {
        lt_log_error('cache forget finishComment failed', $e);
    }
};

\Typecho\Plugin::factory('Widget_Comments_Edit')->mark = static function ($comment = null, $edit = null, string $status = ''): void {
    if (is_array($comment)) {
        $ltCid = (int) ($comment['cid'] ?? 0);
        if ($ltCid > 0) {
            lt_flinks_forget_cid($ltCid);
            lt_comment_list_bump($ltCid);
        }
    }
};

\Typecho\Plugin::factory('Widget_Comments_Edit')->delete = static function ($comment = null): void {
    if (is_array($comment)) {
        $ltCid = (int) ($comment['cid'] ?? 0);
        if ($ltCid > 0) {
            lt_flinks_forget_cid($ltCid);
            lt_comment_list_bump($ltCid);
        }
    }
};

// ============================================================
// 友链申请提交通道（P2：独立数据模型，评论表零残留）
// 申请表单（flinks-template.php）仍 POST 到 Typecho 评论接口（复用 CSRF token / 校验/错误流），
// 本过滤器（Widget_Feedback->comment，validator 之后、insert 之前）在友链页模板下：
//   1) 限频：单 IP 每小时 3 次（lt_check_rate_limit，复用统一缓存层）；
//   2) 解析申请字段（名称/URL/LOGO/邮箱/简介）→ flinks 条目；
//   3) 去重：同 URL（域名归一化）或同邮箱已存在通过/待审记录 → 抛异常友好提示（表单错误流展示）；
//   4) 写入独立数据（options JSON，status 按后台审核开关 0=待审/1=通过）；
//   5) 评论 text 置占位标记 '[flink-apply] 友链申请已受理'（保证评论流程完整），
//      finishComment 钩子随即删除该影子评论 → 评论表零残留，不再污染最新评论/统计。
// 注意：评论提交请求会经 Archive 初始化加载主题 functions.php，本钩子注册时机可靠；
// 仅 flink 模板页生效，普通文章评论不受任何影响。
// ============================================================
\Typecho\Plugin::factory('Widget_Feedback')->comment = static function (array $comment, $content): array {
    if (!is_object($content) || !str_contains((string) ($content->template ?? ''), 'flink')) {
        return $comment;
    }
    // 无协议 URL 自动补全（与 flinks-template.php 解析一致）
    $ltFlinkFixUrl = static function (string $url): string {
        $url = trim($url);
        if ($url !== '' && strpos($url, '://') === false && !str_starts_with($url, 'mailto:')) {
            return 'https://' . $url;
        }
        return $url;
    };
    // 限频：IP + 邮箱双维度，各每小时 3 次（B4：共享 IP / IPv6 / 换 IP 复用邮箱均可被拦截）
    if (!lt_check_rate_limit('flink_apply_' . lt_get_client_ip(), 3, 3600)) {
        throw new \Typecho\Exception(_t('友链申请过于频繁，请稍后再试'));
    }
    $ltFlinkName = trim((string) ($comment['author'] ?? ''));
    $ltFlinkUrl = lt_safe_url($ltFlinkFixUrl(trim((string) ($comment['url'] ?? ''))));
    $ltFlinkMail = trim((string) ($comment['mail'] ?? ''));
    if ($ltFlinkMail !== '' && !lt_check_rate_limit('flink_apply_mail_' . strtolower($ltFlinkMail), 3, 3600)) {
        throw new \Typecho\Exception(_t('友链申请过于频繁，请稍后再试'));
    }
    $ltFlinkLogo = isset($_POST['logo']) ? trim((string) $_POST['logo']) : '';
    $ltFlinkLogo = lt_safe_url($ltFlinkFixUrl($ltFlinkLogo));
    $ltFlinkDesc = trim((string) ($comment['text'] ?? ''));
    // 解析申请条目（[logo] 独立字段并入 entry；desc 剥离 Markdown）
    $ltEntry = [
        'name' => $ltFlinkName,
        'url' => $ltFlinkUrl !== '' ? $ltFlinkUrl : '#',
        'logo' => $ltFlinkLogo !== '' && $ltFlinkLogo !== 'http://' && $ltFlinkLogo !== 'https://' ? $ltFlinkLogo : '',
        'mail' => $ltFlinkMail,
        'desc' => trim(strip_tags((string) preg_replace(['#\[logo\](.*?)\[/logo\]#s', '/[*_~`]{1,3}([^*_~`\n]+)[*_~`]{1,3}/u'], ['', '$1'], $ltFlinkDesc))),
        'sort' => 0,
        'created' => time(),
    ];
    if ($ltEntry['name'] === '') {
        throw new \Typecho\Exception(_t('请填写网站名称'));
    }
    // 去重：同 URL / 同邮箱已存在通过或待审记录 → 友好提示
    if (lt_flinks_dup_exists(lt_flinks_data(), $ltFlinkUrl, $ltFlinkMail)) {
        throw new \Typecho\Exception(_t('您已提交过友链申请，请勿重复提交'));
    }
    $ltRequireModeration = (bool) (\Typecho\Widget::widget('\Widget\Options')->commentsRequireModeration ?? false);
    $ltNewId = lt_flinks_apply($ltEntry, $ltRequireModeration);
    if ($ltNewId === null) {
        throw new \Typecho\Exception(_t('友链申请提交失败，请稍后再试'));
    }
    // 影子评论占位：保证提交成功跳转与 Typecho 流程完整；finishComment 钩子随即删除
    $comment['text'] = '[flink-apply] 友链申请已受理';
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
            $ltTokenValid = lt_csrf_token_matches($ltToken, (string) $ltExpected, $ltExpectedReferer);
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
