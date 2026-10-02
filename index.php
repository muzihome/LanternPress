<?php
declare(strict_types=1);

/**
 * 一款经典报纸复古风 Typecho 主题，适配 PHP 8 与 MySQL 8
 *
 * @package LanternPress
 * @author 木子小鱼
 * @version 2.4.0
 * @link https://github.com/muzihome/LanternPress
 */

if (!defined('__TYPECHO_ROOT_DIR__')) exit;
?>
<?php if ($this->request->get('from') === 'ajax'): ?>
<?php // 瀑布流「加载更多」片段请求：只输出文章列表 + 分页，避免整页流量与解析开销（E6） ?>
<?php $this->need('inc/index.list.php'); ?>
<?php $this->need('inc/pagination.php'); ?>
<?php return; ?>
<?php endif; ?>
<?php $this->need('header.php'); ?>
<?php $this->need('inc/index.recommend.php'); ?>
<?php $this->need('inc/index.list.php'); ?>
<?php $this->need('inc/pagination.php'); ?>
<?php $this->need('footer.php'); ?>
