# LanternPress 更新日志

## 开发版（未发布） - 2026-10-02

- 第四轮全面深度审查（回归批次6-8 + CSS 结构审计 + JS 配置契约核验）与批次9修复（主题版本号不变，资产版本保持 1.4.16）：
  - 批次9（低风险/冗余）：lantern.post.js 海报导出像素比死配置点清理——注释宣称可通过 LANTERTOWN_CONFIG.POSTER_PX 覆盖导出像素比，但 header.php themeConfig 未注入该字段、functions.php themeFields 无 posterPx 配置项，覆盖能力实际不生效（条件守卫恒跳过、静默回退 2x）；删除死分支与注释，固定 2x 高清导出（运行行为不变）
- 验证：node --check 通过；源码/部署均无 POSTER_PX 与 pxVal 残留；deploy-sync 重建部署（去注释 20 文件、原样复制 18 文件）；部署与源码去注释后语义一致
- 同步：源码 → 部署（deploy-sync 重建）→ 公库（robocopy /MIR，GitHub 推送待用户手动执行）；测试站 demo.muzihome.com 需服务器端部署后生效
- 批次9 远程验证（测试站已部署）：页面基线全部 PASS（首页 200 / 文章 285、271、288、252 全部 200 / 分类页 wlog 200 / 404 页 404）；lantern.post.js 远程无 POSTER_PX 与 pxVal 残留、含 PX = 2，与本地部署字节级一致（48044B）；core.js 基线正常；资产版本 1.4.16；站点证书有效（Let's Encrypt，至 2026-12-19，openssl Verification OK；本机 python urllib 报证书过期为其本地 CA 库问题，非站点问题）
- 第四轮审查结论：基线全绿（20 PHP / 2 JS / git 干净 / 部署一致）；批次6-8 回归正确、模板迁移引用零残留；CSS 650 规则结构健康（重复选择器均为合法级联、!important 15 处用途明确）；JS 配置契约 header 注入 12 字段全部命中（仅 POSTER_PX 例外，即批次9 修复）；新发现仅 1 项低风险 + 3 观察（CSS 注释同行 / 10 个未使用变量 / LOG 批次7 表述）；详细见 D:\worksyn\dev\docx\LanternPress-深度审查报告-第四轮-2026-10-02.md

- 批次7（功能修复）：独立页面「自定义模板」无法选择归档页/友链页/标签云页——根因：Typecho 自定义模板下拉框只扫描主题根目录下带 `@package custom` 注释的 PHP，而三个模板位于 inc/ 子目录且无该注释；修复：主题根目录新增三个薄入口文件（page-archive.php「归档页」/ page-flinks.php「友链页」/ page-tagcloud.php「标签云页」，文件头标准 @package custom 注释 + need 引入 inc 内完整模板）；deploy-sync.php 增加 page-*.php 去注释豁免（保留 Typecho 识别注释，与 index.php 同策略）；README 独立页面模板章节文件名同步更新（archive-template.php → page-archive.php 等）
- 验证：3 新文件 + deploy-sync.php php -l 全过；Typecho 内核同款扫描断言通过（3 模板名正确识别）；deploy-sync 重建后部署目录 3 文件存在且 @package custom 注释完整保留
- 同步：源码 → 部署（deploy-sync 重建）→ 公库（robocopy /MIR，GitHub 推送待用户手动执行）；测试站 demo.muzihome.com 需服务器端部署后生效（后台「自定义模板」下拉即可选择）

- 测试站 demo.muzihome.com 服务器端部署后远程验证（第三轮/批次5/批次6 全部通过，主题目录 /usr/themes/LanternPress，资产 1.4.16，HTTPS 可用 http 自动跳转）：
  - 页面基线：首页 / 文章285（折叠+目录+海报+评论）/ 文章271（点赞+评论）/ 404 页 全部 PASS；分类页 wlog 仅 8 篇（< 单页 10 篇）无分页组件属单页数据正常行为
  - poster-proxy（inc/poster-proxy.php）：白名单外域 403、无参数 403、正常代理取图 200；批次6 O2 端口限制远程生效（:6379 / :8443 → 403，:443 / 无端口放行）
  - 资产内容特征：lantern.core.js（initAvifFallback / fitSidebarToContent minHeight）/ lantern.post.js（文章目录锚点判重 menu-index- / sharePosterBtn）全部命中

- 第三轮全面审查（回归批次5修复 + 未覆盖模板全量精读 + inc 核心/CI/CSS 变量与断点核验）与批次6修复（主题版本号不变，资产版本保持 1.4.16）：
  - 批次6A（中风险）：Gitee 镜像 workflow 标签推送改逐标签 merge-base 判断（与分支防护一致：Gitee 同名标签领先/分叉时跳过强制推送，不再无条件覆盖私库标签；fetch 补拉 tags refspec）；poster-proxy 目标端口限制（仅允许未指定或 80/443，防白名单域携带任意端口如 :6379 经代理访问非 HTTP 服务，SSRF 加固）
  - 批次6B（低风险/文档）：README「自定义 JS」注明在主题脚本加载前执行、请勿依赖主题脚本对象（如 window.Lantern）
- 验证：20 个 PHP php -l 全过；批次6 逻辑断言脚本通过（O1 逐 tag 防护片段 + O2 端口 7 例 + O3 README 说明）；deploy-sync.php 重建部署 + 自校验通过（去注释 23 文件、原样复制 15 文件、注释消除 30,186 字节）；部署目录代码级核验（O2 端口限制已同步）
- 同步：源码 → 部署（deploy-sync 重建）→ 公库（robocopy /MIR 3 文件，GitHub 推送待用户手动执行）；测试站 demo.muzihome.com（HTTP 基线 200 / 资产 1.4.16）需服务器端部署后远程验证
- 第三轮审查结论：基线全绿（20 PHP / 2 JS / git 干净 / 部署一致）；N1-N7 修复全部回归正确；新增 3 文件精读无高危（O1 中 / O2 低 / O3 低 / 2 观察）；详细见 D:\worksyn\dev\docx\LanternPress-深度审查报告-第三轮-2026-10-02.md

## 开发版（未发布） - 2026-10-02（批次5）

- 第二轮全面深度审查（回归四批次修复 + 深层边界/安全/一致性）与批次5修复（主题版本号不变，资产版本保持 1.4.16）：
  - 批次5A（中风险）：post.php 图片属性正则 \bsrc=/\balt= 改负向后瞻 (?<![-\w])（防误匹配 data-src/data-alt，属性乱序时不再替换错误属性）；相关文章 fallback 与相关推荐两处查询补 password 过滤（密码保护文章标题不再出现在相关区块，与 lt_prev_next 行为一致）；分享图协议相对地址（//cdn.com/x）补 https: 前缀（不再拼出 siteUrl//cdn.com/x 错误 URL）
  - 批次5B（低风险）：文章目录自动锚点 id 判重（正文已有同名 id 时追加后缀，防锚点跳转歧义）；inc/cache 运行时 .htaccess 统一为 Apache 2.2/2.4 双 IfModule 版本（与内容缓存目录一致，2.4 下不再产生无效指令）
  - 批次5C（低风险/冗余）：内容缓存版本指纹由「mtime」升级为「mtime + 文件大小 + 首尾 64 字节内容摘要」（git checkout/rsync 保留时间戳时内容变更同样触发全站缓存失效），缓存结构版本前缀 3- → 4-；移除 post-content 容器冗余 line-numbers 类（Prism 行号类仅作用于 pre，无功能影响）
- 验证：20 个 PHP php -l 全过；2 个 JS node --check 全过；N1-N5 逻辑断言脚本通过；deploy-sync.php 重建部署 + 自校验通过（去注释 23 文件、原样复制 15 文件）；部署目录代码级核验（N1/N2/N3/N4/N5 全部同步）
- 同步：源码 → 部署（deploy-sync 重建）→ 公库（robocopy /MIR，GitHub 推送待用户手动执行）；测试站 demo.muzihome.com（HTTP 基线 200 / 资产 1.4.16）需服务器端部署后远程验证
- 全量代码审查与四批次修复（主题版本号不变，资产版本保持 1.4.16）：
  - 批次1（高风险）：归档缓存路径统一至根 cache（发布/修改文章后缓存立即清除，不再等 3600s TTL 过期）；inc/cache 运行时目录创建时补 .htaccess 防护；manifest.php 根目录探测改为逐级上溯（修复独立访问时 PWA manifest 输出默认 name/start_url）；点赞限流 GC 跳过 like_once_ 前缀（修复 1 年 IP 去重窗口被 2 天 GC 清理破坏）
  - 批次2（中低风险）：Gitee 镜像 workflow 分支推送前对比 Gitee 领先/分叉状态，领先则跳过强制推送（防覆盖私库未同步提交）；fitSidebarToContent 改 min-height 语义（短文+全模块时侧边栏内容不再被裁剪）；lt_is_https 转发协议头仅可信代理白名单内采信（防伪造 X-Forwarded-Proto 导致 Secure Cookie/HSTS 异常）；poster-proxy host 比较大小写归一、Referer 协议头同样走可信代理判断
  - 批次3（低风险与文档）：README 配置项 20→30 全面刷新、方法二补充「Git 克隆后需先构建」说明；移除限流/归档缓存 unserialize 回退（仅 json 解析，防对象注入面）；AVIF 降级脚本自 footer 内联迁入 lantern.core.js（initAvifFallback 统一管理）；点赞 token 双候选校验（当前请求 URL + Referer，隐私模式/内嵌浏览器不再 403）
  - 批次4（重构）：loadThumb 增加 banner 参数，统一缩略图选择链（index.recommend 复用，消除重复逻辑）；header.php 页面标题 <title>/meta 两组拼接合并；index.list.php 游标回绕契约加注释说明（已核对 Typecho 1.3 源码）
- 验证：20 个 PHP php -l 全过；2 个 JS node --check 全过；deploy-sync.php 重建部署 + 自校验通过；部署目录代码级核验（H1/H2/M1/M3/L2/L6/L7/R2 全部同步）；H1/H2/M1/R2/R3 逻辑断言脚本通过
- 同步：源码 → 部署（deploy-sync 重建）→ 公库（robocopy /MIR，GitHub 推送待用户手动执行）；测试站 demo.muzihome.com（HTTP 基线 200 / 资产 1.4.16）需服务器端部署后远程验证
- 修复：deploy-sync.php 增加 .gitattributes 排除（版本控制配置文件不随部署包分发，部署目录已清理）

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
