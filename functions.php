<?php
declare(strict_types=1);

if (!defined('__TYPECHO_ROOT_DIR__')) exit;

require_once __DIR__ . '/libs/core.php';

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

    // B1：自定义导航菜单（每行「名称|URL」，留空则自动显示所有独立页面）
    $navMenu = new \Typecho\Widget\Helper\Form\Element\Textarea(
        'navMenu',
        null,
        null,
        _t('自定义导航菜单'),
        _t('每行一个，格式：名称|URL（如：首页|/  归档|/archives.html  关于|/about.html  谷歌|https://google.com）。留空则自动显示所有独立页面。URL 支持相对路径或完整地址。')
    );
    $form->addInput($navMenu);

    $cIdRecommend = new \Typecho\Widget\Helper\Form\Element\Text(
        'cIdRecommend',
        null,
        null,
        _t('首页推荐阅读'),
        _t('填写推荐阅读的文章id，用||分隔开，如：3||4')
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

    $socialLink = new \Typecho\Widget\Helper\Form\Element\Textarea(
        'socialLink',
        null,
        null,
        _t('社交媒体链接'),
        _t('一行一个，填写格式：名称:url:链接 或 名称:qr:二维码图片地址。展示在文章页作者介绍处')
    );
    $form->addInput($socialLink);

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
        _t('将原样输出到页脚（通常为统计/分析脚本），支持直接粘贴统计站点提供的代码')
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

    // 异常兜底：面板不白屏，把错误信息展示出来便于排查
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
        _t('开启后，文章页面和自定义页面将显示目录树（小屏幕上不会显示）')
    );
    $layout->addItem($directoryStatus);
}

// A2：文章发布/修改/删除时主动清除归档缓存和文章内容处理缓存，保证即时更新
\Typecho\Plugin::factory('Widget_Contents_Post_Edit')->finishArticle = function ($contents = null): void {
    $cacheFile = __DIR__ . '/cache/archive-cache.php';
    if (is_file($cacheFile)) {
        @unlink($cacheFile);
    }
    // P1：清除文章内容处理缓存，文章修改后重新生成
    if (is_array($contents) && isset($contents['cid'])) {
        lt_delete_field((int) $contents['cid'], 'ltProcessedContent');
    }
};

// B8：文章点赞 AJAX 处理（直接检查 POST，不依赖钩子，兼容 Typecho 1.2.0+）
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $ltAction = isset($_POST['action']) ? trim((string) $_POST['action']) : '';
    if ($ltAction === 'lt_like') {
        header('Content-Type: application/json; charset=utf-8');
        // S1：点赞接口速率限制（同一 IP 10 秒内最多 5 次请求，防止刷量）
        $ltClientIp = lt_get_client_ip();
        if (!lt_check_rate_limit('like_' . $ltClientIp, 5, 10)) {
            header('HTTP/1.1 429 Too Many Requests');
            echo json_encode(['success' => false, 'error' => '请求过于频繁，请稍后再试']);
            exit;
        }
        $ltCid = isset($_POST['cid']) ? (int) $_POST['cid'] : 0;
        if ($ltCid <= 0) {
            echo json_encode(['success' => false, 'error' => 'invalid cid']);
            exit;
        }
        // L4：校验文章是否存在，防止对不存在的文章 ID 点赞产生无效数据
        try {
            $ltDb = \Typecho\Db::get();
            $ltPostRow = $ltDb->fetchRow(
                $ltDb->select('cid')
                    ->from('table.contents')
                    ->where('cid = ? AND status = ?', $ltCid, 'publish')
                    ->limit(1)
            );
            if (!$ltPostRow) {
                echo json_encode(['success' => false, 'error' => 'article not found']);
                exit;
            }
        } catch (\Throwable $e) {
            lt_log_error('like article existence check failed cid=' . $ltCid, $e);
        }
        // O2：移除 lt_has_liked 冗余前置检查，lt_increment_likes 内部已处理重复点赞（cookie 存在时返回当前点赞数，不增加）
        $ltLikes = lt_increment_likes($ltCid);
        echo json_encode([
            'success' => true,
            'likes' => $ltLikes,
            'liked' => true
        ]);
        exit;
    }
}
