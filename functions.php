<?php
declare(strict_types=1);

if (!defined('__TYPECHO_ROOT_DIR__')) exit;

require_once __DIR__ . '/inc/core.php';


lt_send_security_headers();


require_once __DIR__ . '/inc/admin-theme-config.php';

require_once __DIR__ . '/inc/hooks.php';

require_once __DIR__ . '/inc/edgeone-cache.php';

require_once __DIR__ . '/inc/admin-panel.php';
