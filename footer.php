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
    <!-- AVIF 降级：浏览器不支持 avif 时依次回退 webp → jpg/jpeg/png/gif/bmp/tif/tiff（合并自线上手工修改） -->
    <script>(function(){var d=document,q='img[src*=".avif"]',s='data:image/avif;base64,AAAAIGZ0eXBhdmlmAAAAAGF2aWZtaXNwMmNjb2xpc29tYw==';function t(c){var i=new Image;i.onload=function(){c(i.width>0)};i.onerror=function(){c(false)};i.src=s}function r(){var imgs=d.querySelectorAll(q);for(var j=0;j<imgs.length;j++){(function(img){var exts=['.jpg','.jpeg','.png','.gif','.bmp','.tif','.tiff'],idx=0,oldErr=img.onerror;img.onerror=function(){var base=img.src.replace(/\.(webp|avif)(\?|$)/,'$2');function n(){if(idx>=exts.length){if(oldErr)oldErr.call(img);return}var ts=base.replace(/(\?|$)/,exts[idx]+'$1');idx++;var ti=new Image();ti.onload=function(){img.src=ts};ti.onerror=n;ti.src=ts}n()};img.src=img.src.replace(/\.avif(\?|$)/,'.webp$1')})(imgs[j])}}t(function(u){if(!u){if(d.readyState==='loading')d.addEventListener('DOMContentLoaded',r);else r()}})})();</script>
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
