<?php
declare(strict_types=1);

if (!defined('__TYPECHO_ROOT_DIR__')) exit;

require_once __DIR__ . '/inc/core.php';


lt_send_security_headers();

// 后台「设置外观」表单与编辑字段
require_once __DIR__ . '/inc/admin-theme-config.php';
// 发布钩子（缓存/字段清理）与点赞 Ajax 接口
require_once __DIR__ . '/inc/hooks.php';
// 后台「设置外观」TAB 分组 + 导航菜单构建器注入
require_once __DIR__ . '/inc/admin-panel.php';
