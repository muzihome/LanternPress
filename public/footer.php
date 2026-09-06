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

?>
<footer>
    <div class="site-container footer">
        <div class="site-copyright">
            <?php if ($footerText !== ''): ?>
                <?php echo lt_esc_html($footerText); ?>
            <?php else: ?>
                <span> &copy; <?php echo lt_esc_html($currentYear); ?> <a href="<?php echo lt_esc_attr($siteUrl); ?>"><?php echo lt_esc_html($siteTitle); ?></a> All Rights Reserved. </span>
            <?php endif; ?>
        </div>
        <div class="site-recordation">
            <?php if ($recordNum !== ''): ?>
                <a href="https://beian.miit.gov.cn/" target="_blank" rel="noopener noreferrer"><?php echo lt_esc_html($recordNum); ?></a>
            <?php endif; ?>
            <?php if ($policeRecordNum !== ''): ?>  
                <span> &middot; </span> <a href="https://beian.mps.gov.cn/" target="_blank" rel="noopener noreferrer"><?php echo lt_esc_html($policeRecordNum); ?></a>  
            <?php endif; ?>        
        </div>        
    </div>
    <button type="button" id="back-to-top" aria-label="<?php echo lt_esc_attr(_t('返回顶部')); ?>"><?php echo lt_icon('backtop'); ?></button>
    <script src="<?php $this->options->themeUrl('assets/js/lantern.config.js');?>?v=<?php echo $ltAssetVersion; ?>"></script>
    <?php if ($customJs !== ''): ?>
        <script><?php echo $customJs; ?></script>
    <?php endif; ?>
    <?php if ($statisticsCode !== ''): ?>
        <?php echo $statisticsCode; ?>
    <?php endif; ?>
    <?php $this->footer(); ?>
</footer>
</body>
</html>
