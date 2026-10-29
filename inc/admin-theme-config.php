<?php
declare(strict_types=1);

if (!defined('__TYPECHO_ROOT_DIR__')) exit;

// ============================================================
// admin-theme-config.php — 后台「设置外观」表单（themeConfig）与文章/页面编辑字段（themeFields）
// 由 functions.php 加载；TAB 分组与导航菜单构建器注入见 admin-panel.php
// ============================================================

function themeConfig(\Typecho\Widget\Helper\Form $form): void
{
    $buildError = null;

    try {
    $logoUrl = new \Typecho\Widget\Helper\Form\Element\Text(
        'logoUrl',
        null,
        null,
        _t('站点 LOGO 地址'),
        _t('在这里填入一个图片 URL 地址, 以在网站标题处加上一个 LOGO')
    );
    $form->addInput($logoUrl);

    $posterLogo = new \Typecho\Widget\Helper\Form\Element\Text(
        'posterLogo',
        null,
        null,
        _t('海报 LOGO 地址'),
        _t('生成文章海报时使用的站点 LOGO 地址；留空则自动选用导航栏 LOGO / 站点 LOGO / JSON-LD LOGO')
    );
    $form->addInput($posterLogo);


    $navMenu = new \Typecho\Widget\Helper\Form\Element\Textarea(
        'navMenu',
        null,
        null,
        _t('自定义导航菜单'),
        _t('每行一个，格式：名称|URL（如：首页|/  归档|/archives.html  关于|/about.html  谷歌|https://google.com）。留空则自动显示所有独立页面。URL 支持相对路径或完整地址。')
    );


    $form->addInput($navMenu);

    $navMenuData = new \Typecho\Widget\Helper\Form\Element\Textarea(
        'navMenuData',
        null,
        null,
        _t('导航菜单构建器数据'),
        _t('导航菜单构建器自动维护的结构化数据（JSON），由后台可视化界面读写，请勿手工修改。清空本项后前台回退使用上方「自定义导航菜单」文本配置。')
    );
    $form->addInput($navMenuData);

    $cIdRecommend = new \Typecho\Widget\Helper\Form\Element\Text(
        'cIdRecommend',
        null,
        null,
        _t('首页推荐阅读'),
        _t('填写推荐阅读的文章id，用||分隔开，如：3||4。也兼容逗号、中文逗号、空格、分号等写法，前台自动归一')
    );


    $form->addInput($cIdRecommend);

    $recordNum = new \Typecho\Widget\Helper\Form\Element\Text(
        'recordNum',
        null,
        null,
        _t('备案号'),
        _t('如有备案号，请填写在这里')
    );
    $form->addInput($recordNum);

    $policeRecordNum = new \Typecho\Widget\Helper\Form\Element\Text(
        'policeRecordNum',
        null,
        null,
        _t('公安备案号'),
        _t('如有公安备案号，请填写在这里（将展示在备案号下方，并链接至 beian.mps.gov.cn）')
    );
    $form->addInput($policeRecordNum);

    $shortcutIcon = new \Typecho\Widget\Helper\Form\Element\Text(
        'shortcutIcon',
        null,
        null,
        _t('favicon地址'),
        _t('')
    );
    $form->addInput($shortcutIcon);

    $indexThumbs = new \Typecho\Widget\Helper\Form\Element\Textarea(
        'indexThumbs',
        null,
        null,
        _t('首页文章图片'),
        _t('每行填写一张图片，如果设置了多张图片，主题会按文章 ID 稳定分配一张（同一文章始终同一张，保证缓存一致）')
    );
    $form->addInput($indexThumbs);

    $greyImg = new \Typecho\Widget\Helper\Form\Element\Radio(
        'greyImg',
        ['1' => _t('灰白'), '0' => _t('彩色')],
        '1',
        _t('首页图片是否默认灰白显示'),
        _t('')
    );
    $form->addInput($greyImg);

    $turnPageType = new \Typecho\Widget\Helper\Form\Element\Radio(
        'turnPageType',
        ['page' => _t('页码翻页模式'), 'waterfall' => _t('加载更多')],
        'page',
        _t('翻页模式'),
        _t('')
    );
    $form->addInput($turnPageType);

    $themeMode = new \Typecho\Widget\Helper\Form\Element\Radio(
        'themeMode',
        ['0' => _t('复古黄'), '1' => _t('纯白色'), '2' => _t('灰白色'), '3' => _t('暗夜黑'), '4' => _t('跟随系统')],
        '0',
        _t('主题色'),
        _t('「跟随系统」将根据浏览器/系统的深色偏好自动切换明暗')
    );
    $form->addInput($themeMode);

    $postLayout = new \Typecho\Widget\Helper\Form\Element\Select(
        'postLayout',
        ['right' => _t('右侧边栏（默认）'), 'left' => _t('左侧边栏'), 'none' => _t('无侧边栏')],
        'right',
        _t('文章页布局'),
        _t('文章页/独立页面正文与侧边栏的排列方式；「无侧边栏」时正文居中显示。移动端（≤1150px）自动转单列，侧边栏移至正文下方')
    );
    $form->addInput($postLayout);

    $postFoldEnable = new \Typecho\Widget\Helper\Form\Element\Radio(
        'postFoldEnable',
        ['1' => _t('开启'), '0' => _t('关闭')],
        '1',
        _t('长文折叠'),
        _t('正文高度超过「折叠阈值」时折叠显示，底部出现「阅读剩余 X%」按钮，点击展开全文；剩余比例低于「最小剩余比例」的短文自动不折叠。折叠仅影响前端显示，正文完整输出，不影响 SEO 与字数统计')
    );
    $form->addInput($postFoldEnable);

    $postFoldThreshold = new \Typecho\Widget\Helper\Form\Element\Text(
        'postFoldThreshold',
        null,
        '2000',
        _t('折叠阈值(px)'),
        _t('正文实际高度超过此值才触发折叠，默认 2000（约 2.5 屏）')
    );
    $form->addInput($postFoldThreshold);

    $postFoldHeight = new \Typecho\Widget\Helper\Form\Element\Text(
        'postFoldHeight',
        null,
        '1500',
        _t('折叠显示高度(px)'),
        _t('折叠后保留的正文高度，默认 1500（约 1.8 屏）；移动端自动按视口高度 ×1.8 折算，此值仅桌面端生效')
    );
    $form->addInput($postFoldHeight);

    $postFoldMinRatio = new \Typecho\Widget\Helper\Form\Element\Text(
        'postFoldMinRatio',
        null,
        '20',
        _t('最小剩余比例(%)'),
        _t('折叠后剩余内容占比低于此值时不折叠（避免出现「阅读剩余 10%」的尴尬按钮），默认 20')
    );
    $form->addInput($postFoldMinRatio);

    $sidebarPostModules = new \Typecho\Widget\Helper\Form\Element\Checkbox(
        'sidebarPostModules',
        [
            'catalog' => _t('文章目录'),
            'search' => _t('搜索框'),
            'memos' => _t('微言'),
            'ad' => _t('广告位'),
            'category' => _t('分类目录'),
            'recent' => _t('最新文章'),
            'hot' => _t('热门文章'),
            'comments' => _t('最新评论'),
            'tag' => _t('标签云'),
            'siteinfo' => _t('站点信息'),
        ],
        ['catalog', 'search', 'memos', 'ad', 'category', 'recent', 'hot', 'comments', 'tag', 'siteinfo'],
        _t('文章页侧边栏模块'),
        _t('勾选需要在文章页侧边栏显示的模块，取消勾选则隐藏对应模块；「文章目录」模块同时受文章编辑页「是否开启文章目录树」字段控制。未配置时默认全部显示')
    );
    $form->addInput($sidebarPostModules);

    $sidebarPageModules = new \Typecho\Widget\Helper\Form\Element\Checkbox(
        'sidebarPageModules',
        [
            'catalog' => _t('文章目录'),
            'search' => _t('搜索框'),
            'memos' => _t('微言'),
            'ad' => _t('广告位'),
            'category' => _t('分类目录'),
            'recent' => _t('最新文章'),
            'hot' => _t('热门文章'),
            'comments' => _t('最新评论'),
            'tag' => _t('标签云'),
            'siteinfo' => _t('站点信息'),
        ],
        ['catalog', 'search', 'memos', 'ad', 'category', 'recent', 'hot', 'comments', 'tag', 'siteinfo'],
        _t('独立页面侧边栏模块'),
        _t('勾选需要在独立页面侧边栏显示的模块，与文章页相互独立；「文章目录」模块同时受页面编辑页「是否开启文章目录树」字段控制。从未保存过本配置时跟随「文章页侧边栏模块」设置，保存后按此处勾选独立生效')
    );
    $form->addInput($sidebarPageModules);

    $sidebarIndexModules = new \Typecho\Widget\Helper\Form\Element\Checkbox(
        'sidebarIndexModules',
        [
            'catalog' => _t('文章目录'),
            'search' => _t('搜索框'),
            'memos' => _t('微言'),
            'ad' => _t('广告位'),
            'category' => _t('分类目录'),
            'recent' => _t('最新文章'),
            'hot' => _t('热门文章'),
            'comments' => _t('最新评论'),
            'tag' => _t('标签云'),
            'siteinfo' => _t('站点信息'),
        ],
        ['catalog', 'search', 'memos', 'ad', 'category', 'recent', 'hot', 'comments', 'tag', 'siteinfo'],
        _t('首页侧边栏模块（预留）'),
        _t('预留配置：首页暂未启用侧边栏，此处勾选暂不生效，将在后续版本接入首页双栏布局后生效')
    );
    $form->addInput($sidebarIndexModules);

    $sidebarCategoryModules = new \Typecho\Widget\Helper\Form\Element\Checkbox(
        'sidebarCategoryModules',
        [
            'catalog' => _t('文章目录'),
            'search' => _t('搜索框'),
            'memos' => _t('微言'),
            'ad' => _t('广告位'),
            'category' => _t('分类目录'),
            'recent' => _t('最新文章'),
            'hot' => _t('热门文章'),
            'comments' => _t('最新评论'),
            'tag' => _t('标签云'),
            'siteinfo' => _t('站点信息'),
        ],
        ['catalog', 'search', 'memos', 'ad', 'category', 'recent', 'hot', 'comments', 'tag', 'siteinfo'],
        _t('分类页侧边栏模块（预留）'),
        _t('预留配置：分类页暂未启用侧边栏，此处勾选暂不生效，将在后续版本接入分类页双栏布局后生效')
    );
    $form->addInput($sidebarCategoryModules);

    $sidebarMemos = new \Typecho\Widget\Helper\Form\Element\Textarea(
        'sidebarMemos',
        null,
        null,
        _t('侧边栏微言'),
        _t('一行一条，展示在侧边栏「微言」模块；格式：日期|内容（如：09-05|把复杂的事情做简单），也可只填内容。留空则隐藏该模块')
    );
    $form->addInput($sidebarMemos);

    $sidebarAdCode = new \Typecho\Widget\Helper\Form\Element\Textarea(
        'sidebarAdCode',
        null,
        null,
        _t('侧边栏广告位'),
        _t('自定义 HTML 或文本，展示在侧边栏「推荐」广告位（建议尺寸 360×250）。留空则隐藏该模块。注意：站点 CSP 仅放行同源与内联资源，外部域脚本/iframe 将被浏览器拦截')
    );
    $form->addInput($sidebarAdCode);

    $showCopyright = new \Typecho\Widget\Helper\Form\Element\Radio(
        'showCopyright',
        ['1' => _t('是'), '0' => _t('否')],
        '1',
        _t('版权声明'),
        _t('在文章结尾处显示版权声明')
    );
    $form->addInput($showCopyright);

    $writerIntro = new \Typecho\Widget\Helper\Form\Element\Radio(
        'writerIntro',
        ['1' => _t('是'), '0' => _t('否')],
        '1',
        _t('作者简介'),
        _t('在文章结尾处显示作者简介')
    );
    $form->addInput($writerIntro);

    $selfIntro = new \Typecho\Widget\Helper\Form\Element\Text(
        'selfIntro',
        null,
        null,
        _t('一句话自我介绍'),
        _t('展示在文章页作者介绍处')
    );
    $form->addInput($selfIntro);

    $rewardUrl = new \Typecho\Widget\Helper\Form\Element\Textarea(
        'rewardUrl',
        null,
        null,
        _t('打赏收款二维码'),
        _t('请填写二维码图片地址，一行一个，最多填写两个。展示在文章页作者介绍处')
    );
    $form->addInput($rewardUrl);

    $postAdSlot = new \Typecho\Widget\Helper\Form\Element\Textarea(
        'postAdSlot',
        null,
        null,
        _t('文章底部广告位'),
        _t('自定义 HTML 或文本，展示在文章正文底部（工具栏与上一篇/下一篇导航之间）。留空则隐藏该模块。注意：站点 CSP 仅放行同源与内联资源，外部域脚本/iframe 将被浏览器拦截')
    );
    $form->addInput($postAdSlot);

    $socialLink = new \Typecho\Widget\Helper\Form\Element\Textarea(
        'socialLink',
        null,
        null,
        _t('社交媒体链接'),
        _t('一行一个，填写格式：名称:url:链接 或 名称:qr:二维码图片地址。展示在文章页作者介绍处')
    );
    $form->addInput($socialLink);

    $posterProxyHosts = new \Typecho\Widget\Helper\Form\Element\Textarea(
        'posterProxyHosts',
        null,
        null,
        _t('海报图床域名白名单'),
        _t('海报封面代理（inc/poster-proxy.php）仅放行此处配置的图床域名，一行一个，如 img.example.com；前缀通配 .example.com 匹配其全部子域。站点自身域名图片为同域直连，无需配置')
    );
    $form->addInput($posterProxyHosts);

    $footerText = new \Typecho\Widget\Helper\Form\Element\Text(
        'footerText',
        null,
        null,
        _t('自定义页脚文案'),
        _t('留空则显示默认版权信息；填写后替换页脚第一行内容')
    );
    $form->addInput($footerText);

    $customCss = new \Typecho\Widget\Helper\Form\Element\Textarea(
        'customCss',
        null,
        null,
        _t('自定义 CSS'),
        _t('将原样输出到页面 &lt;style&gt; 中，可用于覆盖主题样式，无需修改主题文件')
    );
    $form->addInput($customCss);

    $customJs = new \Typecho\Widget\Helper\Form\Element\Textarea(
        'customJs',
        null,
        null,
        _t('自定义 JS'),
        _t('将原样输出到页脚 &lt;script&gt; 中，支持放置互动脚本')
    );
    $form->addInput($customJs);

    $statisticsCode = new \Typecho\Widget\Helper\Form\Element\Textarea(
        'statisticsCode',
        null,
        null,
        _t('统计代码'),
        _t('将原样输出到页脚（通常为统计/分析脚本），支持直接粘贴统计站点提供的代码。注意：站点 CSP 仅放行同源与内联资源，外部域脚本（如 hm.baidu.com）将被浏览器拦截')
    );
    $form->addInput($statisticsCode);

    $avatarProxy = new \Typecho\Widget\Helper\Form\Element\Text(
        'avatarProxy',
        null,
        null,
        _t('头像镜像地址（Gravatar 代理）'),
        _t('国内访问 Gravatar 慢时可填镜像，如 https://cravatar.cn/avatar/；留空则使用官方 Gravatar')
    );
    $form->addInput($avatarProxy);
    } catch (\Throwable $e) {
        $buildError = $e;
    }


    if ($buildError !== null) {
        $errorItem = new \Typecho\Widget\Helper\Layout('p', ['class' => 'message error']);
        $errorItem->html('主题配置项构建异常：' . htmlspecialchars($buildError->getMessage(), ENT_QUOTES, 'UTF-8'));
        $form->addItem($errorItem);
    }
}

function themeFields(\Typecho\Widget\Helper\Layout $layout): void
{
    $articleDesc = new \Typecho\Widget\Helper\Form\Element\Textarea(
        'articleDesc',
        null,
        null,
        _t('文章摘要'),
        _t('此段文字将展示到首页，如果不填写，则默认取文章前130字')
    );
    $layout->addItem($articleDesc);

    $bannerUrl = new \Typecho\Widget\Helper\Form\Element\Text(
        'bannerUrl',
        null,
        null,
        _t('文章主图'),
        _t('在这里填入一个图片URL地址')
    );
    $layout->addItem($bannerUrl);

    $directoryStatus = new \Typecho\Widget\Helper\Form\Element\Select(
        'directoryStatus',
        ['off' => _t('关闭（默认）'), 'on' => _t('开启')],
        'off',
        _t('是否开启文章目录树'),
        _t('开启后，文章页侧边栏将显示「文章目录」模块（目录随正文滚动高亮；移动端 ≤767px 不显示，避免小屏布局错乱）')
    );
    $layout->addItem($directoryStatus);
}
