<?php
/**
 * 友链页
 *
 * @package custom
 */
declare(strict_types=1);

if (!defined('__TYPECHO_ROOT_DIR__')) exit;

// F-3：无协议 URL 自动补全（仅友链解析使用；不改动 lt_safe_url 的全局行为）
$ltFixFlinkUrl = static function (string $url): string {
    $url = trim($url);
    if ($url !== '' && strpos($url, '://') === false && !str_starts_with($url, 'mailto:')) {
        return 'https://' . $url;
    }
    return $url;
};

$this->need('header.php');
// 友链页侧边栏：复用侧边栏模块（最新文章 / 热门文章 / 侧边栏广告 / 标签云），
// sidebar.php 通过 $sidebarForceModules 裁剪模块；广告模块在后台未配置广告代码时不显示
$sidebarForceModules = ['recent', 'hot', 'ad', 'tag'];

// 友链申请：复用评论模块——本页已审核通过（approved）的顶层评论即申请记录，
// 映射规则：author=网站名称，mail 的 Gravatar=网站图标，url=网站地址，text（评论正文）=网站简介。
// 后台开启评论审核时 status='waiting' 不在此显示，审核通过（approved）后才进入友链列表；未开启则提交即显示。
$ltApplyLinks = [];
try {
    $ltDb = \Typecho\Db::get();
    $ltApplyRows = $ltDb->fetchAll(
        $ltDb->select('author', 'mail', 'url', 'text')
            ->from('table.comments')
            ->where('cid = ?', (int) $this->cid)
            ->where('type = ?', 'comment')
            ->where('status = ?', 'approved')
            ->where('parent = ?', 0)
            ->order('created', \Typecho\Db::SORT_ASC)
    );
    foreach ((array) $ltApplyRows as $ltApplyRow) {
        $ltApplyName = trim(lt_text($ltApplyRow['author'] ?? ''));
        if ($ltApplyName === '') {
            continue;
        }
        $ltApplyUrl = lt_safe_url($ltFixFlinkUrl(lt_text($ltApplyRow['url'] ?? '')));
        $ltRawText = lt_text($ltApplyRow['text'] ?? '');
        // 三档图标优先级：①[logo]标记 → ②邮箱Gravatar/代理 → ③首字徽标（渲染闭包处理）
        $ltApplyLogo = '';
        if (preg_match('/^\[logo\](.+?)\[\/logo\]\s*/s', $ltRawText, $ltLm) === 1) {
            $ltApplyLogo = lt_safe_url(trim($ltLm[1]));
            $ltRawText = (string) substr($ltRawText, strlen($ltLm[0]));
        }
        // 剥离 Markdown 强调标记（**加粗** / _斜体_ / `代码` / ~~删除线~~），避免残留星号
        $ltRawText = (string) preg_replace('/[*_~`]{1,3}([^*_~`\n]+)[*_~`]{1,3}/u', '$1', $ltRawText);
        $ltApplyDesc = trim(strip_tags($ltRawText));
        $ltApplyLinks[] = [
            'name' => $ltApplyName,
            'url' => $ltApplyUrl !== '' ? $ltApplyUrl : '#',
            'desc' => $ltApplyDesc !== '' ? \Typecho\Common::subStr($ltApplyDesc, 0, 60, '...') : '',
            'logo' => $ltApplyLogo,
            'mail' => lt_text($ltApplyRow['mail'] ?? '')
        ];
    }
} catch (\Throwable $e) {
    lt_log_error('flinks: 申请友链读取失败', $e);
}

// 友链卡片渲染：图标 + 名称；简介通过 title 属性悬停显示；三档图标优先级——①logo → ②邮箱Gravatar/代理 → ③首字圆形徽标
$ltRenderFlink = static function (array $l): void {
    $ltInitial = '';
    if (preg_match('/./us', (string) ($l['name'] ?? ''), $ltM)) { $ltInitial = $ltM[0]; }
    $ltLogo = lt_text($l['logo'] ?? '');
    $ltMail = lt_text($l['mail'] ?? '');
    // 邮箱头像仅在 logo 为空时生成（Gravatar 或代理，无邮箱时返回空由首字徽标兜底）
    // lt_parse_avatar() 内部已完成 lt_safe_url + lt_esc_attr，此处不再重复处理
    $ltMailAvatar = '';
    if ($ltLogo === '' && $ltMail !== '') {
        $ltMailAvatar = lt_parse_avatar($ltMail);
    }
    $ltDesc = trim((string) ($l['desc'] ?? ''));
    ?>
    <a class="flink-card" href="<?php echo lt_esc_attr((string) $l['url']); ?>" target="_blank" rel="noopener noreferrer nofollow"<?php if ($ltDesc !== ''): ?> title="<?php echo lt_esc_attr($ltDesc); ?>"<?php endif; ?>>
<?php if ($ltLogo !== ''): ?>
        <img class="flink-icon flink-icon-img" src="<?php echo lt_esc_attr($ltLogo); ?>" alt="" loading="lazy">
<?php elseif ($ltMailAvatar !== ''): ?>
        <img class="flink-icon flink-icon-img" src="<?php echo $ltMailAvatar; ?>" alt="" loading="lazy">
<?php elseif ($ltInitial !== ''): ?>
        <span class="flink-icon"><?php echo lt_esc_html($ltInitial); ?></span>
<?php endif; ?>
        <span class="flink-name"><?php echo lt_esc_html((string) $l['name']); ?></span>
    </a>
    <?php
};
?>
<div class="post">
    <div class="post-layout-wrapper sidebar-right" id="postLayoutWrapper">
        <div class="post-container flinks-page">
            <div class="post-title"><?php $this->title(); ?></div>
            <div id="post-content" class="post-content">
<?php
            ob_start();
            $this->content();
            $pageContent = (string) ob_get_clean();
            // F-4：仅当正文以 flinks-container 容器开头（自定义 HTML 模式）才原样输出，
            // 避免正文中零散出现该类名时误触发原样分支
            if (preg_match('/^\s*<div[^>]*class=["\'][^"\']*flinks-container/i', $pageContent) === 1) {
                echo $pageContent;
                // 申请审核通过的友链追加为独立标准列表（不改动作者手写内容）
                if ($ltApplyLinks !== []) {
                    echo '<div class="flinks-container flinks-apply-list">';
                    foreach ($ltApplyLinks as $l) {
                        $ltRenderFlink($l);
                    }
                    echo '</div>';
                }
            } else {
                $autoLinks = [];
                // ① HTML 链接提取：覆盖 Markdown 已被 Typecho 渲染为 HTML（<a href> 列表）的场景
                if (preg_match_all('/<a\s+[^>]*href=["\']([^"\']+)["\'][^>]*>(.*?)<\/a>/is', $pageContent, $matches, PREG_SET_ORDER)) {
                    foreach ($matches as $match) {
                        $url = lt_safe_url($ltFixFlinkUrl($match[1]));
                        $name = trim(strip_tags((string) $match[2]));
                        if ($name !== '' && $url !== '') {
                            $autoLinks[] = ['name' => $name, 'url' => $url, 'desc' => ''];
                        }
                    }
                } else {
                    // ② 纯文本行解析：每行支持 Markdown 列表（- [名称](URL) - 描述）或 || 格式（名称||URL||描述）
                    foreach (lt_lines(trim(strip_tags($pageContent))) as $line) {
                        $link = null;
                        if (preg_match('/^[-*]\s*\[([^\]]+)\]\(([^)]+)\)(?:\s*[-—]\s*(.*))?$/', $line, $m) === 1) {
                            $name = trim($m[1]);
                            $url = lt_safe_url($ltFixFlinkUrl($m[2]));
                            $desc = isset($m[3]) ? trim($m[3]) : '';
                            if ($name !== '' && $url !== '') {
                                $link = ['name' => $name, 'url' => $url, 'desc' => $desc];
                            }
                        } else {
                            $parts = array_map('trim', explode('||', $line, 3));
                            if (count($parts) >= 2 && $parts[0] !== '') {
                                $url = lt_safe_url($ltFixFlinkUrl($parts[1]));
                                if ($url !== '') {
                                    $link = ['name' => $parts[0], 'url' => $url, 'desc' => $parts[2] ?? ''];
                                }
                            }
                        }
                        if ($link === null) {
                            // F-2：无效行记录日志（不静默丢弃），便于排查格式问题
                            lt_log_error('flinks: 跳过无法解析的行: ' . $line);
                            continue;
                        }
                        $autoLinks[] = $link;
                    }
                }
                // 申请友链与正文自动解析链接合并为统一列表（正文链接在前，申请记录按提交时间在后）
                $autoLinks = array_merge($autoLinks, $ltApplyLinks);
                ?>
                <div class="flinks-container">
<?php foreach ($autoLinks as $l): ?>
<?php $ltRenderFlink($l); ?>
<?php endforeach; ?>
                </div>
<?php if (count($autoLinks) === 0): ?>
                    <p><?php _e('暂无友链'); ?></p>
<?php endif; ?>
<?php } ?>
                <div class="flinks-apply respond">
                    <h2 class="response"><?php _e('申请链接'); ?></h2>
<?php if ($this->allow('comment')): ?>
                    <p class="flinks-apply-hint">
<?php if (lt_bool($this->options->commentsRequireModeration ?? null)): ?>
<?php _e('提交后需经站长审核，审核通过后将显示在上方友链列表。'); ?>
<?php else: ?>
<?php _e('提交后将直接显示在上方友链列表。'); ?>
<?php endif; ?>
                    </p>
                    <form method="post" action="<?php $this->commentUrl() ?>" id="flinks-apply-form" class="comment-form" role="form">
<?php if (!$this->user->hasLogin()): ?>
                        <div class="flinks-apply-row">
                            <input type="text" name="author" maxlength="12" id="author" class="form-control input-control clearfix" placeholder="<?php echo lt_esc_attr(_t('网站名称 (*)')); ?>" value="<?php $this->remember('author'); ?>" autocomplete="organization" required>
                            <input type="url" name="url" id="url" class="form-control input-control clearfix" placeholder="<?php echo lt_esc_attr(_t('网站地址 (https://) (*)')); ?>" value="<?php $this->remember('url'); ?>" autocomplete="url" required>
                        </div>
<?php endif; ?>
                        <div class="flinks-apply-row">
                            <input type="url" name="logo" id="logo" class="form-control input-control clearfix" placeholder="<?php echo lt_esc_attr(_t('网站LOGO（选填）')); ?>" autocomplete="off">
<?php if (!$this->user->hasLogin()): ?>
                            <input type="email" name="mail" id="mail" class="form-control input-control clearfix" placeholder="<?php echo lt_esc_attr(_t('联系邮箱 (*)')); ?>" value="<?php $this->remember('mail'); ?>" autocomplete="email" required>
<?php endif; ?>
                        </div>
                        <textarea name="text" id="textarea" class="form-control" placeholder="<?php echo lt_esc_attr(_t('网站简介...')); ?>" required><?php $this->remember('text', false); ?></textarea>
                        <button type="submit" class="submit"><?php _e('提交申请'); ?></button>
<?php $security = $this->widget('\Widget\Security'); ?>
                        <input type="hidden" name="_" value="<?php echo lt_esc_attr($security->getToken($this->request->getRequestUrl())); ?>">
                    </form>
<?php else: ?>
                    <p class="flinks-apply-hint"><?php _e('友链申请已关闭。'); ?></p>
<?php endif; ?>
                </div>
        </div>
        </div>
        <aside class="sidebar post-sidebar" id="postSidebar">
<?php $this->need('sidebar.php'); ?>
        </aside>
    </div>
</div>
<?php $this->need('footer.php'); ?>
