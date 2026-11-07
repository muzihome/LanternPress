<?php
declare(strict_types=1);

if (!defined('__TYPECHO_ROOT_DIR__')) exit;











$ltInvalidateContentCaches = static function ($contents = null): void {
    
    lt_cache_forget('archive', 'archive');
    
    lt_cache_forget('tagcloud', 'page');
    
    lt_cache_forget('sidebar', 'recent5');
    lt_cache_forget('sidebar', 'tag24');
    lt_cache_forget('sidebar', 'category12');
    
    lt_prevnext_flush();
    
    if (function_exists('lt_edgeone_mark_dirty')) {
        lt_edgeone_mark_dirty();
    }
    $ltLegacyArchiveCache = dirname(__DIR__) . '/cache/archive-cache.php';
    if (is_file($ltLegacyArchiveCache)) {
        @unlink($ltLegacyArchiveCache);
    }

    if (is_array($contents) && isset($contents['cid'])) {

        lt_content_cache_delete((int) $contents['cid']);
        lt_delete_field((int) $contents['cid'], 'ltProcessedContent');
        
        lt_flinks_forget_cid((int) $contents['cid']);
    }
};

\Typecho\Plugin::factory('\Widget\Contents\Post\Edit')->finishPublish = $ltInvalidateContentCaches;
\Typecho\Plugin::factory('\Widget\Contents\Post\Edit')->finishSave = $ltInvalidateContentCaches;
\Typecho\Plugin::factory('\Widget\Contents\Post\Edit')->finishDelete = $ltInvalidateContentCaches;
\Typecho\Plugin::factory('\Widget\Contents\Page\Edit')->finishPublish = $ltInvalidateContentCaches;
\Typecho\Plugin::factory('\Widget\Contents\Page\Edit')->finishSave = $ltInvalidateContentCaches;
\Typecho\Plugin::factory('\Widget\Contents\Page\Edit')->finishDelete = $ltInvalidateContentCaches;




$ltInvalidateMarkCaches = static function ($status = null, $post = null): void {
    lt_cache_forget('archive', 'archive');
    lt_cache_forget('tagcloud', 'page');
    lt_cache_forget('sidebar', 'recent5');
    lt_cache_forget('sidebar', 'tag24');
    lt_cache_forget('sidebar', 'category12');
    lt_prevnext_flush();
    if (function_exists('lt_edgeone_mark_dirty')) {
        lt_edgeone_mark_dirty();
    }
    $ltCid = is_array($post) ? (int) ($post['cid'] ?? 0) : (int) $post;
    if ($ltCid > 0) {
        lt_content_cache_delete($ltCid);
        lt_flinks_forget_cid($ltCid);
    }
};
\Typecho\Plugin::factory('\Widget\Contents\Post\Edit')->finishMark = $ltInvalidateMarkCaches;
\Typecho\Plugin::factory('\Widget\Contents\Page\Edit')->finishMark = $ltInvalidateMarkCaches;










\Typecho\Plugin::factory('Widget_Feedback')->finishComment = static function ($feedback = null): void {
    
    
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
        
        lt_cache_forget('sidebar', 'comments3');
        if ($ltCid > 0) {
            lt_flinks_forget_cid($ltCid);
            lt_comment_list_bump($ltCid);
        }
    } catch (\Throwable $e) {
        lt_log_error('cache forget finishComment failed', $e);
    }
};

\Typecho\Plugin::factory('Widget_Comments_Edit')->mark = static function ($comment = null, $edit = null, string $status = ''): void {
    lt_cache_forget('sidebar', 'comments3');
    if (is_array($comment)) {
        $ltCid = (int) ($comment['cid'] ?? 0);
        if ($ltCid > 0) {
            lt_flinks_forget_cid($ltCid);
            lt_comment_list_bump($ltCid);
        }
    }
};

\Typecho\Plugin::factory('Widget_Comments_Edit')->delete = static function ($comment = null): void {
    lt_cache_forget('sidebar', 'comments3');
    if (is_array($comment)) {
        $ltCid = (int) ($comment['cid'] ?? 0);
        if ($ltCid > 0) {
            lt_flinks_forget_cid($ltCid);
            lt_comment_list_bump($ltCid);
        }
    }
};














\Typecho\Plugin::factory('Widget_Feedback')->comment = static function (array $comment, $content): array {
    if (!is_object($content) || !str_contains((string) ($content->template ?? ''), 'flink')) {
        return $comment;
    }
    
    $ltFlinkFixUrl = static function (string $url): string {
        $url = trim($url);
        if ($url !== '' && strpos($url, '://') === false && !str_starts_with($url, 'mailto:')) {
            return 'https://' . $url;
        }
        return $url;
    };
    
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
    
    if (lt_flinks_dup_exists(lt_flinks_data(), $ltFlinkUrl, $ltFlinkMail)) {
        throw new \Typecho\Exception(_t('您已提交过友链申请，请勿重复提交'));
    }
    $ltRequireModeration = (bool) (\Typecho\Widget::widget('\Widget\Options')->commentsRequireModeration ?? false);
    $ltNewId = lt_flinks_apply($ltEntry, $ltRequireModeration);
    if ($ltNewId === null) {
        throw new \Typecho\Exception(_t('友链申请提交失败，请稍后再试'));
    }
    
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
            
            header('HTTP/1.1 500 Internal Server Error');
            echo json_encode(['success' => false, 'error' => 'server error']);
            exit;
        }

        $ltLikes = lt_increment_likes($ltCid);
        
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
