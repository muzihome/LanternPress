<?php
/**
 * 友链
 *
 * @package custom
 */
declare(strict_types=1);

if (!defined('__TYPECHO_ROOT_DIR__')) exit;

$this->need('public/header.php');
?>
<div class="post">
    <div class="post-container flinks-page">
        <div class="post-title"><?php $this->title(); ?></div>
        <div id="post-content" class="post-content">
            <?php
            // 支持两种写法：
            // 1) 直接在页面正文写入 .flinks-container 结构（内置样式已支持）
            // 2) 或者使用每行「名称 || 地址 || 简介」格式，由本模板自动渲染
            ob_start();
            $this->content();
            $pageContent = (string) ob_get_clean();
            if (strpos($pageContent, 'flinks-container') !== false) {
                // 正文已包含 flinks-container 结构时，直接原样输出
                echo $pageContent;
            } else {
                // A6：仅在需要自动渲染时才解析纯文本行，避免 strip_tags 破坏非友链内容
                $flinkLines = lt_lines(trim(strip_tags($pageContent)));
                $autoLinks = [];
                foreach ($flinkLines as $line) {
                    $parts = array_map('trim', explode('||', $line, 3));
                    if (count($parts) < 2 || $parts[0] === '' || !lt_safe_url($parts[1])) {
                        continue;
                    }
                    $autoLinks[] = [
                        'name' => $parts[0],
                        'url' => lt_safe_url($parts[1]),
                        'desc' => $parts[2] ?? ''
                    ];
                }
                ?>
                <div class="flinks-container">
                    <?php foreach ($autoLinks as $l): ?>
                        <a href="<?php echo lt_esc_attr($l['url']); ?>" target="_blank" rel="noopener noreferrer nofollow">
                            <span><?php echo lt_esc_html($l['name']); ?></span>
                            <?php if ($l['desc'] !== ''): ?>
                                <small><?php echo lt_esc_html($l['desc']); ?></small>
                            <?php endif; ?>
                        </a>
                    <?php endforeach; ?>
                </div>
                <?php if (count($autoLinks) === 0): ?>
                    <p><?php _e('暂无友链'); ?></p>
                <?php endif; ?>
            <?php } ?>
        </div>
    </div>
</div>
<?php $this->need('public/footer.php'); ?>
