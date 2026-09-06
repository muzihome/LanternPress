<?php
declare(strict_types=1);

if (!defined('__TYPECHO_ROOT_DIR__')) exit;

$searchKeyword = mb_substr(lt_text($this->request->get('s')), 0, 100, 'UTF-8');
$total = (int) $this->getTotal();
?>
<span><?php _e('搜索到'); ?></span>
<span style="color: #cc493d"><?php echo $total; ?></span>
<span><?php _e('篇与'); ?></span>
<span style="color: #cc493d"><?php echo lt_esc_html($searchKeyword); ?></span>
<span><?php _e('相关的结果'); ?></span>
