<?php
declare(strict_types=1);

if (!defined('__TYPECHO_ROOT_DIR__')) exit;
?>
<?php $this->need('public/header.php'); ?>
<div class="site-container">
    <div class="not-find-container">
        <div class="not-find">404</div>
        <div class="not-find-text"><?php _e('抱歉，页面未找到'); ?></div>
    </div>
</div>

<?php $this->need('public/footer.php'); ?>
