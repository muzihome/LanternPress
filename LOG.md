# LanternPress 更新日志

## v2.5.0 - 2026-10-28

- **友链系统**：申请功能（复用评论模块，author/mail/url/text 映射 + [logo] 图标三档优先级 + 后台审核流）；解析多格式兼容（Markdown / HTML / ||，无协议自动补 https）；卡片式显示样式；双栏布局接入侧边栏模块（最新文章 / 热门文章 / 侧边栏广告 / 标签云）
- **后台配置**：「设置外观」30 项归纳为 6 组 TAB 选项卡（含无障碍方向键）；侧边栏模块按页面类型独立配置（文章页 / 独立页面，首页 / 分类页预留）；配置项 30 → 33
- **文章页**：多分类面包屑层级化（首页 → 一级 → 二级 → 三级 → 正文）
- **安全加固**：poster-proxy SSRF 全链路（Host 投毒 / IPv6 / DNS 重绑定 / 限流 / TOCTOU 钉 IP）；评论 XSS 协议白名单 + 属性白名单；配置下发去内联脚本（lt-config JSON）；JSON-LD 密码页排除；SameSite；字段缓存 null 哨兵
- **修复与优化**：归档缓存路径、字段缓存哨兵、缓存目录统一、限频原子写与锁重试、quote 拼写、死代码清理（CSS / JS / PHP）
- **验证**：全量 php -l / node --check / PoC 回归通过

## v2.4.0 - 2026-10-02

- **功能新增**：生成海报（canvas 报纸风竖版：16:9 封面/标题/作者+日期/摘要/LOGO/二维码，封面优先级 主图→正文首图→随机图，poster-proxy 兜底，LOGO 可后台配置）；长文折叠（「阅读剩余 X%」胶囊展开，4 项后台配置，移动端按视口折算）；相关推荐模块（复用 cIdRecommend）；面包屑导航；文章目录默认折叠二级标题（localStorage 记忆）+ sticky 跟随正文；收藏改浏览器书签引导
- **性能优化**：JS 按需加载拆分（lantern.core.js + lantern.post.js，列表页 JS 传输 -80%）；qrcode 懒加载（首次微信 hover/生成海报按需注入，再省 19.9KB）；内容缓存增强（hash/chars/summary/content 四段格式，旧格式自动补算升级）；热门 300s / 站点信息 600s 文件缓存
- **文章页**：三种布局切换（右侧/左侧/无侧边栏）；后台「文章页侧边栏模块」多选（10 模块可勾选隐藏）；底部重排（标签→分割线→分享/数据工具栏→广告位→上/下一篇→相关文章→相关推荐→评论区）；上/下一篇简化为轻量行式；分享工具栏多轮重构（iconfont 迁移→SVG 修复→精简 5 项→恢复 7 项→浅灰 hover 品牌色→移动端微信二维码切换）
- **模板规范**：20 个模板静态规范化（缩进/行尾空格/幽灵空格/echo 换行）；目录结构 partials → inc
- **安全加固**：评论内容白名单 HTML 渲染（lt_safe_comment_html）；poster-proxy SSRF 防御；CSP script-src 收紧；密码文章 meta 摘要拦截
- **修复**：阅读时长/全文字数（四轮根治：HTML 实体/非法 UTF-8/缓存补算/执行顺序）、首页轮播点击、评论 HTML 源码、全站 500（strict_types）、远程上线问题、工具栏图标裁切、点赞/阅读量限频、海报 B1-B10 + C1-C9 系列
- **资产版本**：1.4.0 → 1.4.16（静态资源缓存键，与主题版本解耦）

## v2.3.0 - 2026-09-21

- **文章页重构**：正文区布局重构（来源框 / 赞打赏 / 分享工具栏 / 底部广告位 / 上一篇下一篇卡片导航）；新增 10 模块侧边栏（目录/搜索/微言/广告/分类/最新/热门/最新评论/标签云/站点信息）；三种布局后台切换（右侧/左侧/无侧边栏）；微信复制、收藏、微博/QQ 外链分享
- **安全加固**：点赞 CSRF、评论令牌规范化、缓存序列化格式加固；密码文章 meta description 截断（og/twitter/description 不泄漏正文摘要）；CSP script-src 收紧（移除 https: 通配）；head meta 清理（移除 generator/template/twitter/RSD/pingback/wlwmanifest/冗余聚合与 keywords，canonical 去重、description 独占、RSS feed URL 修复）
- **性能优化**：内容文件缓存、列表查询优化、Prism 按需加载、首屏 LCP 优化、限流文件回收；内容缓存版本自动派生（正文处理文件变更即全站失效）；CSS 死代码清理约 334 行（10 组旧布局选择器，微信 hover 品牌色同步修复）；侧边栏断点优化（平板 768-1150px 保留、移动端 ≤767px 隐藏）
- **功能新增**：首页推荐轮播（4 秒自动 + 控制按钮悬停显示/离开隐藏）；PWA Web App Manifest 与主题图标；移动端文章页/独立页不再加载目录树
- **无障碍**：跳转链接、键盘焦点、轮播箭头语义化、对比度、目录折叠键盘可达、减弱动态效果
- **SEO**：全站唯一 h1、结构化数据、归档描述、feed/搜索路由化
- **兼容与修复**：适配 Typecho 1.3.0 与 PHP 8.2+；修复密码保护、阅读量统计、搜索关键词等缺陷；侧边栏 widget 查询 try/catch（失败隐藏模块）；备份导入无评论段 500 防御；热门文章 JOIN_LEFT 常量与真实链接、标签云页同修；README 环境要求 PHP 7.4 → 8.0
- 验证：WSL 沙箱 42 项断言回归 0 失败、meta_check 逐页核验（canonical/description/指纹头全对）、上线前最终审查（配置面板 24 项/密码三态/瀑布流/归档友链模板/真实数据热门）全部通过

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
