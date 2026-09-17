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


$customJsEscaped = $customJs !== '' ? preg_replace('/<\/(script|style)>/i', '<\\/$1>', $customJs) : '';
$statisticsCodeEscaped = $statisticsCode !== '' ? preg_replace('/<\/(script|style)>/i', '<\\/$1>', $statisticsCode) : '';

?>
</main>
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
    <script defer src="<?php $this->options->themeUrl('assets/js/lantern.js');?>?v=<?php echo lt_esc_attr(LT_ASSET_VERSION); ?>"></script>
    <?php if ($customJsEscaped !== ''): ?>
        <script><?php echo $customJsEscaped; ?></script>
    <?php endif; ?>
    <?php if ($statisticsCodeEscaped !== ''): ?>
        <?php echo $statisticsCodeEscaped; ?>
    <?php endif; ?>
    <?php $this->footer(); ?>
</footer>
</body>
</html>
