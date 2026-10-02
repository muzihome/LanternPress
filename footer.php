<?php
declare(strict_types=1);

if (!defined('__TYPECHO_ROOT_DIR__')) exit;

$recordNum = trim(lt_text($this->options->recordNum ?? ''));
$policeRecordNum = trim(lt_text($this->options->policeRecordNum ?? ''));
$footerText = trim(lt_text($this->options->footerText ?? ''));
$customJs = trim(lt_text($this->options->customJs ?? ''));
$statisticsCode = trim(lt_text($this->options->statisticsCode ?? ''));
$currentYear = date('Y');
$siteUrl = rtrim(lt_text($this->options->siteUrl), '/');
$siteTitle = lt_text($this->options->title ?? '');
// 文章页/独立页才加载文章页专属脚本（lantern.post.js；微信二维码 qrcode.min.js 已改为首次使用时懒加载）
$ltIsSingleFooter = $this->is('post') || $this->is('page');

$customJsEscaped = $customJs !== '' ? preg_replace('/<\/(script|style)>/i', '<\\/$1>', $customJs) : '';
$statisticsCodeEscaped = $statisticsCode !== '' ? preg_replace('/<\/(script|style)>/i', '<\\/$1>', $statisticsCode) : '';

?>
</main>
<footer>
    <div class="site-container footer">
        <div class="site-copyright">
<?php if ($footerText !== ''): ?>
<?php echo lt_esc_html($footerText) . "\n"; ?>
<?php else: ?>
                <span>&copy; <?php echo lt_esc_html($currentYear); ?> <a href="<?php echo lt_esc_attr($siteUrl); ?>"><?php echo lt_esc_html($siteTitle); ?></a> All Rights Reserved.</span>
<?php endif; ?>
        </div>
        <div class="site-recordation">
<?php if ($recordNum !== ''): ?>
                <a href="https://beian.miit.gov.cn/" target="_blank" rel="noopener noreferrer"><?php echo lt_esc_html($recordNum); ?></a>
<?php endif; ?>
<?php if ($policeRecordNum !== ''): ?>
                <span>&middot;</span> <a href="https://beian.mps.gov.cn/" target="_blank" rel="noopener noreferrer"><?php echo lt_esc_html($policeRecordNum); ?></a>
<?php endif; ?>
        </div>
    </div>
    <button type="button" id="back-to-top" aria-label="<?php echo lt_esc_attr(_t('返回顶部')); ?>"><?php echo lt_icon('backtop'); ?></button>
    <script defer src="<?php $this->options->themeUrl('assets/js/lantern.core.js'); ?>?v=<?php echo lt_esc_attr(LT_ASSET_VERSION); ?>"></script>
    <!-- AVIF 降级已随 lantern.core.js 统一管理（initAvifFallback） -->
<?php if ($ltIsSingleFooter): ?>
        <script defer src="<?php $this->options->themeUrl('assets/js/lantern.post.js'); ?>?v=<?php echo lt_esc_attr(LT_ASSET_VERSION); ?>"></script>
<?php endif; ?>
<?php if ($customJsEscaped !== ''): ?>
        <script><?php echo $customJsEscaped; ?></script>
<?php endif; ?>
<?php if ($statisticsCodeEscaped !== ''): ?>
<?php echo $statisticsCodeEscaped . "\n"; ?>
<?php endif; ?>
<?php $this->footer(); ?>
</footer>
</body>
</html>
