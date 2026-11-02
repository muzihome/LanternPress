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

// 友链申请：独立数据模型（P2）——申请记录存于 options（ltFlinksData，flinks.php 管理），
// 前台渲染读 lt_flinks_visible()（status=1 通过项，sort+created 排序）；不再查询评论表。
// 兼容回退：flinks 数据为空时读评论表旧申请（迁移前过渡，保证旧友链不消失）；
// 后台「设置外观 → 友链管理」执行迁移 + 清理后（P2-2），评论表旧记录清空、flinks 成为唯一数据源。
// P0-1 缓存：申请列表结果经统一缓存层 lt_cache_remember 缓存（TTL 300s，
// 落盘 cache/flink_apply_{md5}.php），评论提交/审核/删除时由 hooks.php 失效钩子即时清空，
// 未命中钩子时 TTL 兜底（审核通过后至多 5 分钟进入列表）。
$ltApplyLinks = [];
try {
    $ltApplyLinks = lt_cache_remember('flink', 'apply-' . (int) $this->cid, 300, function () use ($ltFixFlinkUrl): array {
        $ltData = lt_flinks_visible(lt_flinks_data());
        if ($ltData !== []) {
            return $ltData;
        }
        // P2 兼容回退：flinks 为空（未迁移）时读评论表旧申请
        return lt_flinks_legacy((int) $this->cid, $ltFixFlinkUrl);
    });
} catch (\Throwable $e) {
    lt_log_error('flinks: 申请友链读取失败', $e);
}

// 友链卡片渲染：图标 + 名称；简介通过 title 属性悬停显示；三档图标优先级——①logo → ②邮箱Gravatar/代理 → ③首字圆形徽标
// P0-2：logo / 邮箱头像统一经 lt_maybe_proxy_image 判定——目标域在 poster-proxy 白名单内且非 SVG 时
// 走同源代理（获得 Cache-Control 缓存头 + 防盗链 Referer），否则直连原样；白名单未配置时全部直连（安全默认，避免 403 挂图）
$ltRenderFlink = static function (array $l): void {
    $ltInitial = '';
    if (preg_match('/./us', (string) ($l['name'] ?? ''), $ltM)) { $ltInitial = $ltM[0]; }
    $ltLogo = lt_maybe_proxy_image(lt_text($l['logo'] ?? ''));
    $ltMail = lt_text($l['mail'] ?? '');
    // 邮箱头像仅在 logo 为空时生成（Gravatar 或代理，无邮箱时返回空由首字徽标兜底）；
    // lt_parse_avatar(..., true) 内部已完成代理判定 + lt_safe_url + lt_esc_attr，此处直接输出
    $ltMailAvatar = '';
    if ($ltLogo === '' && $ltMail !== '') {
        $ltMailAvatar = lt_parse_avatar($ltMail, true);
    }
    $ltDesc = trim((string) preg_replace('#\[logo\](.*?)\[/logo\]#s', '', (string) ($l['desc'] ?? '')));
    ?>
    <a class="flink-card" href="<?php echo lt_esc_attr((string) $l['url']); ?>" target="_blank" rel="noopener noreferrer nofollow"<?php if ($ltDesc !== ''): ?> title="<?php echo lt_esc_attr($ltDesc); ?>"<?php endif; ?>>
<?php if ($ltLogo !== ''): ?>
        <img class="flink-icon flink-icon-img" src="<?php echo lt_esc_attr($ltLogo); ?>" alt="" width="38" height="38" loading="lazy" referrerpolicy="no-referrer" data-name="<?php echo lt_esc_attr($ltInitial); ?>">
<?php elseif ($ltMailAvatar !== ''): ?>
        <img class="flink-icon flink-icon-img" src="<?php echo $ltMailAvatar; ?>" alt="" width="38" height="38" loading="lazy" referrerpolicy="no-referrer" data-name="<?php echo lt_esc_attr($ltInitial); ?>">
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
            // A2（批次A）：最终列表 HTML 片段缓存（page-{cid}，TTL 300s）。
            // 失效面统一由 lt_flinks_forget_cid 覆盖：评论提交/审核/删除（hooks.php）、
            // 管理端写操作（lt_flinks_forget_all_pages）、正文编辑（finishArticle 钩子）。
            // 缓存命中时本段直接回显——0 DB 查询、0 JSON 解析、0 排序、0 HTML 拼接（性能提升核心）。
            // 申请表单不缓存（含每请求变化的 CSRF token）。
            $ltPageHtml = '';
            try {
                $ltPageHtml = lt_cache_remember('flink', 'page-' . (int) $this->cid, 300, function () use ($pageContent, $ltApplyLinks, $ltRenderFlink, $ltFixFlinkUrl): string {
                    ob_start();
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
                        // P0-1：正文自动解析结果缓存（非 HTML 模式），key 按友链页 cid；
                        // 正文变更时 hooks.php finishArticle 钩子即时失效，TTL 300s 兜底
                        $autoLinks = lt_cache_remember('flink', 'auto-' . (int) $this->cid, 300, function () use ($pageContent, $ltFixFlinkUrl): array {
                            $links = [];
                            // ① HTML 链接提取：覆盖 Markdown 已被 Typecho 渲染为 HTML（<a href> 列表）的场景
                            if (preg_match_all('/<a\s+[^>]*href=["\']([^"\']+)["\'][^>]*>(.*?)<\/a>/is', $pageContent, $matches, PREG_SET_ORDER)) {
                                foreach ($matches as $match) {
                                    $url = lt_safe_url($ltFixFlinkUrl($match[1]));
                                    $name = trim(strip_tags((string) $match[2]));
                                    if ($name !== '' && $url !== '') {
                                        $links[] = ['name' => $name, 'url' => $url, 'desc' => ''];
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
                                    $links[] = $link;
                                }
                            }
                            return $links;
                        });
                        // 申请友链与正文自动解析链接合并为统一列表（正文链接在前，申请记录按提交时间在后）
                        $mergedLinks = array_merge($autoLinks, $ltApplyLinks);
                        // B3（批次B）：分类分组渲染——任一条目含非空 category 时按分类分组
                        // （空 category 归「默认」组），全空则保持平铺（渲染现状不变）
                        $ltHasCategory = false;
                        foreach ($mergedLinks as $l) {
                            if (trim((string) ($l['category'] ?? '')) !== '') {
                                $ltHasCategory = true;
                                break;
                            }
                        }
                        if ($ltHasCategory) {
                            $ltGroups = [];
                            foreach ($mergedLinks as $l) {
                                $ltG = trim((string) ($l['category'] ?? ''));
                                $ltGroups[($ltG === '' ? '默认' : $ltG)][] = $l;
                            }
                            echo '<div class="flinks-container">';
                            foreach ($ltGroups as $ltGName => $ltGLinks) {
                                echo '<h3 class="flink-group-title">' . lt_esc_html($ltGName) . '</h3>' . "\n";
                                foreach ($ltGLinks as $l) {
                                    $ltRenderFlink($l);
                                }
                            }
                            echo '</div>';
                        } else {
                            echo '<div class="flinks-container">';
                            foreach ($mergedLinks as $l) {
                                $ltRenderFlink($l);
                            }
                            echo '</div>';
                        }
                        if (count($mergedLinks) === 0) {
                            echo '<p>' . _t('暂无友链') . '</p>';
                        }
                    }
                    return (string) ob_get_clean();
                });
            } catch (\Throwable $e) {
                lt_log_error('flinks: 页面渲染缓存失败', $e);
            }
            echo $ltPageHtml;
?>
<?php if (!$this->user->hasLogin()): ?>
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
<?php endif; ?>
        </div>
        </div>
        <aside class="sidebar post-sidebar" id="postSidebar">
<?php $this->need('sidebar.php'); ?>
        </aside>
    </div>
</div>
<?php $this->need('footer.php'); ?>
