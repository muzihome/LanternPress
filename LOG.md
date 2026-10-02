# LanternPress 更新日志

## v2.4.0 - 2026-10-02

- 生成海报功能（canvas 报纸风竖版海报：16:9 封面/标题/作者+日期/摘要/LOGO或站点名/二维码，下载 PNG；封面优先级 主图→正文首图→随机图；poster-proxy 代理兜底；海报 LOGO 可后台配置）
- 长文折叠（正文超阈值折叠显示「阅读剩余 X%」胶囊按钮点击展开；后台 4 项配置，移动端按视口折算）
- JS 按需加载拆分（lantern.core.js + lantern.post.js，列表页 JS 传输 -80%）
- qrcode 懒加载（首次微信 hover/生成海报时按需注入，初始传输再省 19.9KB）
- 内容缓存增强（hash/chars/summary/content 四段格式，旧格式自动补算升级）
- 相关推荐模块（复用首页 cIdRecommend 配置，无内容不显示）
- 后台「文章页侧边栏模块」多选（10 模块可勾选隐藏）
- 文章页三种布局切换（右侧/左侧/无侧边栏）
- 面包屑导航；文章目录默认折叠二级标题（localStorage 记忆）、仅目录 sticky 跟随正文底部
- 收藏改为浏览器书签引导（单用户站点站内收藏无意义）
- 分享工具栏多轮重构（iconfont 迁移→微博/QQ/QQ空间 SVG 修复→精简 5 项→恢复 7 项→默认浅灰 hover 品牌色→移动端微信二维码点击切换；flex 防裁切）
- 上/下一篇简化为轻量行式（圆点装饰左右对称）
- 文章页底部重排（标签→分割线→分享/数据工具栏→广告位→上/下一篇→相关文章→相关推荐→评论区）
- 模板规范化（方案 A：20 模板缩进/行尾空格/幽灵空格/echo 换行）；目录结构 partials→inc
- 评论内容白名单 HTML 渲染（lt_safe_comment_html）
- 修复：阅读时长/全文字数（四轮根治：HTML 实体/非法 UTF-8/缓存补算/执行顺序）、首页轮播点击、评论 HTML 源码、全站 500（strict_types）、远程上线问题、工具栏图标裁切、点赞/阅读量限频、海报 B1-B10+C1-C9 系列
- 性能：列表页 JS -80%、qrcode 按需注入、热门文章 300s/站点信息 600s 文件缓存
- 安全：poster-proxy SSRF 防御、CSP script-src 收紧、密码文章 meta 摘要拦截
- 资产版本 1.4.0 → 1.4.16（静态资源缓存键，与主题版本解耦）
- 验证：远程全功能实测通过（demo.muzihome.com：目录联动/微信二维码/复制链接/点赞/生成海报/轮播搜索/评论提交）

## v2.3.0 - 2026-09-21

- 安全加固：点赞接口 CSRF 防护、评论表单令牌规范化、缓存序列化格式加固
- 首页推荐轮播：自动轮播 4 秒、控制按钮悬停显示 / 离开自动隐藏
- 移动端优化：文章页与独立页面在移动端不再加载目录树
- PWA：新增 Web App Manifest 与主题图标
- 无障碍：跳转链接、键盘焦点、轮播箭头语义化、对比度、目录折叠键盘可达、减弱动态效果
- 性能：内容文件缓存、列表查询优化、Prism 按需加载、首屏 LCP 优化、限流文件回收
- SEO：全站唯一 h1、结构化数据、归档描述、feed / 搜索路由化
- 兼容：适配 Typecho 1.3.0 与 PHP 8.2+；修复密码保护、阅读量统计、搜索关键词等缺陷
- 文章页正文区重构：来源框、赞/打赏按钮、分享工具栏、底部广告位、上一篇/下一篇卡片导航（对齐新设计稿）
- 新增文章页侧边栏（10 模块：目录/搜索/微言/广告/分类/最新/热门/最新评论/标签云/站点信息）
- 文章页三种布局（右侧边栏/左侧边栏/无侧边栏）可在后台外观设置切换
- 上一篇/下一篇改为卡片式导航；微信复制、收藏、微博/QQ 外链分享接入
- 安全加固（复审修复）：① 密码保护文章 meta description 截断——header.php 对 `$isHiddenSingle` 置空 `$pageDesc`（og/twitter/description 不再输出正文摘要，Typecho 内核 `archiveDescription=plainExcerpt` 泄漏路径同步以空 description 拦截，密码页元信息仅剩「请输入密码访问」）；② CSP `script-src` 收紧——移除 `https:` 通配（`inc/core.php` LT_CSP_POLICY），仅保留 `'self' 'unsafe-inline'`，style/img/font/connect 的 `https:` 保留（海报跨域取图 COS/CDN + poster-proxy 兜底）；经 WSL 沙箱验证 42 项断言回归 0 失败、密码页无正文摘要、CSP 头生效
- 内容缓存版本自动派生（替代手动递增）：`LT_CONTENT_CACHE_VERSION` 由正文处理相关文件（`inc/core.php` / `functions.php` / `post.php` / `sidebar.php`）的 filemtime 派生 `3-<hash8>`，任一文件变更即全站正文缓存自动失效重建，杜绝发布时漏递增版本导致旧缓存串新逻辑；README 缓存问答同步更新
- 侧边栏 widget 查询加固：最新评论（Widget_Comments_Recent）与标签云（Widget_Metas_Tag_Cloud）两处查询补 try/catch（失败记 lt_log_error 并隐藏模块），与热门/站点信息模块一致
- 侧边栏断点优化（平板保留侧边栏）：原 `@media(max-width:1150px)` 整体单列 + 隐藏侧边栏拆分为两块——平板 `768-1150px` 保留三态布局与侧边栏（容器收窄 1000px、padding 16px、目录 sticky 继续跟随）；移动端 `≤767px` 单列 + `display:none !important` 隐藏侧边栏（与 JS isMobile 767px 断点一致）
- 死代码清理（CSS 瘦身）：删除 10 组旧布局残留死类选择器（`#image-list` 图库 21 条、`.archive-list .archives/.archive-post` 归档 18 条、`.article-writer` 作者卡片 9 条、`.post-recommend` 推荐 9 条、`.tags-container/.terms-*` 标签 7 条、`.category-container/.category-list` 分类 5 条、`.flink-img/.flink-name` 友链 3 条、`.footer-police` 1 条、`.social-link` 1 条、`.share-weixin` 1 条），共删除约 334 行；同时修复微信分享 hover 品牌色（`.share-weixin` 旧类名改 `.share-wechat`，与 post.php 实际按钮类一致，微信图标 hover 恢复 #07c160）；保留 `.archive-list`/`.archive-page`/`.flinks-container` 等活类；经 WSL 沙箱 42 项断言回归 0 失败
- head meta 元信息清理（性能/SEO/安全统筹）：移除 `X-UA-Compatible(IE=edge)`（IE 已停止支持）；移除主题 twitter:card/title/description/image 全套（X 在国内不可用，og 已覆盖分享卡片功能）；关闭 Typecho 内核 header() 冗余输出——`generator`（Typecho 1.3.0 版本指纹→安全隐患）、`template`（主题名指纹）、`pingback`/`EditURI(RSD)`/`wlwmanifest`（XML-RPC 时代遗留，Windows Live Writer 已死）、`rss1(RDF)`/`rss2`/`atom` 三条聚合链接（保留主题单条 RSS 2.0）、`keywords`（meta keywords 已无 SEO 价值）、`social` 全套（内核 og:type/url/twitter 系列/twitter:domain 与主题 og 重复）；canonical 去重——单页 canonical 由内核输出（Archive::header 无条件输出）、主题仅非单页输出（首页/分类/标签/搜索等），消除单页重复 canonical；description 由主题独占输出（内核恒传空覆盖 plainExcerpt 路径）；修复 RSS feed URL（内核路由 `/feed[feed:string:0]` 无参生成 `{feed}` 字面量坏链接，改为传 `['feed'=>'']` 生成 `/feed`，坏值回退 `siteUrl.'/feed/'`）；保留 charset/renderer(webkit 国产浏览器)/viewport/preconnect/favicon/manifest(PWA)/robots(搜索页 noindex)/canonical/description/og 系列/JSON-LD(Article+WebSite+BreadcrumbList)/commentReply 脚本；经 WSL 沙箱 meta_check 逐页核验（canonical 各页 1 条、description 非密码页 1 条、generator/template/wlwmanifest/RSD/pingback/twitter/keywords 均 0 条、feed 无 `{feed}`）+ 42 项断言回归 0 失败
- 备份导入 server error 修复：Typecho 备份无评论段时旧评论残留与内容错配，最新评论模块 permalink 生成 TypeError 导致 500；主题侧 try/catch 跳过不可用评论，并修正 Widget_Comments_Recent 参数 size→pageSize
- 全面深度审查修复：热门文章模块引用未定义常量 Typecho\Db::JOIN_LEFT 导致条目全空，改为 LEFT_JOIN；LEFT JOIN 条件移入 ON（带其它字段无 ltViews 的文章不再被排除）；热门链接改为 Router::url 生成真实 URL（原为 #）
- 标签云页模板同步修复 2 处 JOIN_LEFT 常量；WSL 动态复验：热门 5 条、标签云页 5 标签、三态布局、导航卡、点赞/评论/搜索链路全部通过
- 上线前最终审查：后台配置面板 24 项渲染与保存、密码文章三态（未解锁/正确/错误）、瀑布流加载、归档/友链页模板、manifest/feed、真实数据热门回归、MySQL 方言复核全部通过
- 文档修正：README 环境要求 PHP 最低版本 7.4 → 8.0（主题使用 str_contains，PHP 7 无法运行，与 v2.2.1 声明对齐）

## v2.2.2 - 2026-09-16

- 热修：首页轮播 resize 冻结兜底、卡片键盘委托守卫、克隆幻灯片懒加载、搜索页 noindex、部署脚本失败不写盘与缓存保护

## v2.2.1 - 2026-09-16

- Typecho 1.3.0 规范与 PHP 8.2+ 全面修复：密码保护链路、多数据库 upsert、安全响应头、搜索关键词取值等

## v2.2.0 - 2026-09-16

- 第三方库原生 JS 化（移除 jQuery / Swiper / Fancybox 等，保留 Prism）
- 目录结构整合（partials /、assets/vendor/）

## v2.1.6 - 2026-09-05

- 上线前最终审查修复：移除未使用导入、版本号统一

## v2.1.5 - 2026-09-05

- 第三次代码审查修复：PHP 7.4 兼容、版本号同步、CSS 注入防护、文章存在性校验

## v2.1.4 - 2026-09-05

- 新增 CHANGELOG 更新日志
- README 重写与 FAQ 补充

## v2.1.3 - 2026-09-05

- 移除脚本 preload 声明，消除浏览器警告

## v2.1.2 - 2026-09-05

- 文章列表缩略图空值处理与复古占位背景

## v2.1.1 - 2026-09-05

- 阅读量 / 点赞原子递增
- 内容缓存配置变更自动失效
- 点赞请求超时控制、速率限制文件锁、可信代理白名单

## v2.1.0 - 2026-09-01

- 主题更名为 LanternPress
- 文章内容数据库缓存
- CSP 安全策略常量

## v2.0.x - 2026-08-31

- 重大更新：公安备案、自定义导航、归档 / 友链 / 标签云、点赞、阅读量、目录树、模态框搜索、跟随系统主题、性能优化、安全加固

## v1.0.0 - 原始版本（LanternTown）

- 初始版本发布
- 文章底部分享工具栏迁移到 iconfont 字体图标：左侧 海报+微信好友+朋友圈+QQ+QQ空间+微博+头条+复制链接，右侧 阅读量+评论数+收藏；移除推特分享项；新增 assets/css/iconfont.css + assets/fonts/{woff2,woff,ttf}；header.php 引入 iconfont.min.css；工具栏 SVG 图标路径问题彻底消除
- 全量图标迁移 iconfont：core.php lt_icon() 改为输出 <i class="lt-icon icon-xxx">（grid/search/left/right/mail/close/weixin/weibo/bilibili/github/zhihu/link/backtop 13 个 key）；post.php 面包屑 home 内联 SVG 替换为 icon-home；lantern.css 适配 i.lt-icon 尺寸规则（back-to-top/breadcrumb/category/slider-btn）；点赞/打赏按钮 iconfont 暂未收录对应字形，保留原内联 SVG
- 点赞/打赏图标迁移 iconfont：post.php 两处内联 SVG 替换为 icon-upvote / icon-reward；core.php reward 加入字体映射并删除 rawSvg 兜底；iconfont.css 追加 .icon-upvote(.e608)/.icon-reward(.e609)；lantern.css .action-icon 改为 font-size:20px；字体文件更新（woff2 6852B）
- 源码审查清理：core.php 删除未使用的 $rawSvg 兜底块；post.php 关闭按钮去冗余 iconfont 外层；index.list/recommend、comments/pagination 模板去除冗余 iconfont 类；header.php 移除 font.min.css 引入；删除死文件 assets/css/font.css（.svg-icon 已无人使用）和 svg.html 测试页；lantern.css 删除重复的 .post-content pre 规则
- 首页分类图标 .category-icon .lt-icon font-size 调为18px（与标题文字视觉对齐，修复基类16px覆盖问题）；文章页分享工具栏 hover/focus 时各社交平台呈现品牌原色（微信/朋友圈#07c160、QQ#12b7f5、QQ空间#ff9900、微博#e6162d、头条#f04142）
