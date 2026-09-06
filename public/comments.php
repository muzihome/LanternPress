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
                    <img class="comment-avatar" src="<?php echo parseAvatar($comments->mail); ?>" alt=""/>
                </div>
                <div style="display: flex; flex-wrap: wrap;">
                    <div style="width: 100%; margin-top: 10px">
                        <span class="comment-author <?php echo lt_esc_attr($commentClass); ?>">
                            <?php if ($commentUrl): ?>
                                <?php // Bug1：评论者URL经过lt_safe_url协议白名单过滤，防止javascript:等恶意URL（XSS） ?>
                                <a target="_blank" rel="nofollow noopener noreferrer" href="<?php echo lt_esc_attr(lt_safe_url($commentUrl)); ?>">
                                    <?php echo lt_esc_html($authorName); ?>
                                </a>
                            <?php else: ?>
                                <?php echo lt_esc_html($authorName); ?>
                            <?php endif;?>
                        </span>
                        <?php if ($isWaiting): ?>
                            <em><?php _e('（审核后可见）'); ?></em>
                        <?php endif; ?>
                        <time class="comment-time"><?php $comments->date('M j, Y'); ?></time>
                        <span class="comment-reply"><?php $comments->reply(_t('回复')); ?></span>
                    </div>
                    <div class="comment-content">
                        <?php echo getReply((int) $comments->parent, lt_text($comments->content)); ?>
                    </div>
                </div>
            </div>
        </div>
        <?php if (!empty($comments->children)): ?>
            <div class="comment-children">
                <?php $comments->threadedComments($options); ?>
            </div>
        <?php endif; ?>
    </li>
<?php } ?>

<div class="comment-container">
    <div id="comments" class="clearfix">
        <?php $this->comments()->to($comments); ?>
        <?php if ($this->allow('comment')): ?>
            <div id="<?php $this->respondId(); ?>" class="respond" data-respondId="<?php $this->respondId() ?>">
                <h2 class="response">
                    <?php _e('添加评论'); ?>
                    <span style="font-size: 16px">
                        <?php if ($this->user->hasLogin()): ?>
                            <?php printf(_t('已登录为 %s，'), '<a href="' . $this->options->profileUrl() . '" data-no-instant>' . $this->user->screenName() . '</a>'); ?>
                            <a href="<?php $this->options->logoutUrl(); ?>" title="<?php echo lt_esc_attr(_t('退出登录')); ?>" data-no-instant><?php _e('退出登录'); ?></a>
                        <?php endif; ?>
                        <?php $comments->cancelReply(_t(' 取消回复')); ?>
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
                    <input type="hidden" name="_" value="<?php echo lt_esc_attr($security->getToken($this->request->getReferer())); ?>">
                </form>
            </div>
        <?php else: ?>
            <span class="response"><?php _e('评论已关闭'); ?></span>
        <?php endif; ?>

        <?php if ($comments->have()): ?>
            <?php $comments->listComments(); ?>
            <div style="height: 40px"></div>
            <?php $comments->pageNav(
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
                    'prevClass' => 'iconfont prev',
                    'nextClass' => 'iconfont next'
                ]
            ); ?>
        <?php endif; ?>
    </div>
</div>
