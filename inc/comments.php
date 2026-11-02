<?php
declare(strict_types=1);

if (!defined('__TYPECHO_ROOT_DIR__')) exit;

function threadedComments(object $comments, object $options): void
{
    $commentClass = '';
    if ($comments->authorId) {
        if ($comments->authorId == $comments->ownerId) {
            $commentClass .= ' comment-by-author';
        } else {
            $commentClass .= ' comment-by-user';
        }
    }

    $authorName = lt_text($comments->author);
    $commentUrl = lt_text($comments->url);
    $commentStatus = lt_text($comments->status);
    $isWaiting = $commentStatus === 'waiting';
    ?>
    <li id="li-<?php $comments->theId(); ?>" class="comment-body">
        <div id="<?php $comments->theId(); ?>">
            <div class="comment-view">
                <div>
                    <img class="comment-avatar" src="<?php echo lt_parse_avatar($comments->mail); ?>" alt=""/>
                </div>
                <div class="comment-meta-row">
                    <div class="comment-meta-inner">
                        <span class="comment-author <?php echo lt_esc_attr($commentClass); ?>">
<?php if ($commentUrl): ?>
                                <a target="_blank" rel="nofollow noopener noreferrer" href="<?php echo lt_esc_attr(lt_safe_url($commentUrl)); ?>">
<?php echo lt_esc_html($authorName) . "\n"; ?>
                                </a>
<?php else: ?>
<?php echo lt_esc_html($authorName) . "\n"; ?>
<?php endif; ?>
                        </span>
<?php if ($isWaiting): ?>
                            <em><?php _e('（审核后可见）'); ?></em>
<?php endif; ?>
                        <time class="comment-time"><?php $comments->date('M j, Y'); ?></time>
                        <span class="comment-reply">
                            <a href="javascript:void(0);" class="comment-reply-link" data-cid="<?php $comments->theId(); ?>" data-coid="<?php echo (int) $comments->coid; ?>" data-no-instant><?php _e('回复'); ?></a>
                        </span>
                    </div>
                    <div class="comment-content">
<?php echo getReply((int) $comments->parent, lt_text($comments->content)) . "\n"; ?>
                    </div>
                </div>
            </div>
        </div>
<?php if (!empty($comments->children)): ?>
            <div class="comment-children">
<?php $comments->threadedComments($options); echo "\n"; ?>
            </div>
<?php endif; ?>
    </li>
<?php } ?>

<div class="comment-container">
    <div id="comments" class="clearfix">
<?php $this->comments()->to($comments); ?>
<?php // 方案1·N+1 消除：构建父作者映射供 getReply 复用（一次性查询），置于全局供渲染消费 ?>
<?php $GLOBALS['lt_comment_parent_map'] = lt_comment_parent_map((int) $this->cid); ?>
<?php if ($this->allow('comment')): ?>
            <div id="<?php $this->respondId(); ?>" class="respond" data-respondId="<?php $this->respondId() ?>">
                <h2 class="response">
<?php _e('添加评论'); echo "\n"; ?>
                    <span class="comment-login-hint">
<?php if ($this->user->hasLogin()): ?>
<?php printf(
                                _t('已登录为 %s，'),
                                '<a href="' . lt_esc_attr($this->options->profileUrl) . '" data-no-instant>' . lt_esc_html($this->user->screenName) . '</a>'
                            ); echo "\n"; ?>
                            <a href="<?php $this->options->logoutUrl(); ?>" title="<?php echo lt_esc_attr(_t('退出登录')); ?>" data-no-instant><?php _e('退出登录'); ?></a>
<?php endif; ?>
                        <a id="cancel-comment-reply-link" class="cancel-comment-reply-link" href="javascript:void(0);" data-no-instant style="display:none;"><?php _e(' 取消回复'); ?></a><?php echo "\n"; ?>
                    </span>
                </h2>
                <form method="post" action="<?php $this->commentUrl() ?>" id="comment-form" class="comment-form" role="form">
                    <textarea name="text" id="textarea" class="form-control" placeholder="<?php echo lt_esc_attr(_t('请输入评论... ')); ?>" required><?php $this->remember('text', false); ?></textarea>
<?php if (!$this->user->hasLogin()): ?>
                        <div class="comment-user-info-container">
                            <input type="text" name="author" maxlength="12" id="author" class="form-control input-control clearfix" placeholder="<?php echo lt_esc_attr(_t('Name (*)')); ?>" value="<?php $this->remember('author'); ?>" autocomplete="name" required>
                            <input type="email" name="mail" id="mail" class="form-control input-control clearfix" placeholder="<?php echo lt_esc_attr(_t('Email (*)')); ?>" value="<?php $this->remember('mail'); ?>" autocomplete="email" <?php if (lt_comment_require_mail($this->options)): ?> required<?php endif; ?>>
                            <input type="url" name="url" id="url" class="form-control input-control clearfix" placeholder="<?php echo lt_esc_attr(_t('Site (http://)')); ?>" value="<?php $this->remember('url'); ?>" autocomplete="url" <?php if (lt_comment_require_url($this->options)): ?> required<?php endif; ?>>
                        </div>
<?php endif; ?>
                    <button type="submit" class="submit" id="misubmit"><?php _e('提交'); ?></button>
<?php $security = $this->widget('\Widget\Security'); ?>
                    <input type="hidden" name="_" value="<?php echo lt_esc_attr($security->getToken($this->request->getRequestUrl())); ?>">
                </form>
            </div>
<?php else: ?>
            <span class="response"><?php _e('评论已关闭'); ?></span>
<?php endif; ?>

<?php // 方案1·评论区缓存化：按 cid + 评论版本号 + 分页缓存渲染后的列表（含分页），消除重复清洗与 N+1；
            // 版本号由提交/审核/删除钩子 bump（hooks.php），旧 ver 缓存自然 miss；表单含每请求 CSRF token 不入缓存
            $ltCommentPage = (int) \Typecho\Request::getInstance()->get('commentPage', 0);
            $ltCommentKey = 'list-' . (int) $this->cid . '-' . lt_comment_list_ver((int) $this->cid) . '-p' . $ltCommentPage;
            $ltCommentList = lt_cache_remember('comment', $ltCommentKey, 300, static function () use ($comments): string {
                if (!$comments->have()) {
                    return '';
                }
                ob_start();
                $comments->listComments();
                echo "\n";
                echo '<div class="comment-list-tail-spacer"></div>';
                $comments->pageNav(
                    lt_icon('left'),
                    lt_icon('right'),
                    1,
                    '...',
                    [
                        'wrapTag' => 'ul',
                        'wrapClass' => 'pagination-container',
                        'itemTag' => 'li',
                        'textTag' => 'a',
                        'currentClass' => 'active',
                        'prevClass' => 'prev',
                        'nextClass' => 'next'
                    ]
                );
                echo "\n";
                return (string) ob_get_clean();
            });
            if ($ltCommentList !== ''): ?>
<?php echo $ltCommentList; ?>
<?php endif; ?>
    </div>
</div>
