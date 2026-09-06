# 更新日志

本文件记录 LanternPress 主题的所有版本变更。

格式基于 [Keep a Changelog](https://keepachangelog.com/zh-CN/1.1.0/)，
版本号遵循 [语义化版本](https://semver.org/lang/zh-CN/) 规范。

> **LanternPress** 基于原始 **LanternTown** 主题（作者：TypeRenew/Yangsh888）二次开发。

---

## [2.1.6] - 2026-09-05

### 修复（上线前最终审查修复）

#### 轻微问题修复
- **L1：移除未使用的导入**
  - 移除 `libs/core.php` 中未使用的 `use Utils\Helper;` 导入，提升代码整洁度
- **L2：版本号统一**
  - 统一 `index.php`、`README.md`、`CHANGELOG.md` 版本号为 `v2.1.6`
  - 修复第四次审查修复补丁包命名为 v2.1.6 但代码中版本号仍为 v2.1.5 的不一致问题

### 上线前检查
- ✅ 全部 17 个 PHP 文件语法检查通过
- ✅ JS 文件语法检查通过
- ✅ 无 SQL 注入 / XSS / CSRF / 文件操作安全漏洞
- ✅ 安全响应头完善（CSP / HSTS / X-Frame-Options 等）
- ✅ 异常处理完善，关键路径全部 try/catch
- ✅ 性能优化完善（原子递增 / 内容缓存 / 懒加载等）
- ✅ PHP 7.4+ / Typecho 1.2.0+ 兼容性确认

---

## [2.1.5] - 2026-09-05

### 修复（第三次代码审查问题修复）

#### 中等问题修复
- **M1：PHP 7.4 兼容性**
  - 修复 8 处 PHP 8.0+ 非捕获异常语法（`catch (\Throwable)` 不带变量），在 PHP 7.4 中会导致致命解析错误
  - 全部改为 `catch (\Throwable $e)`，确保主题兼容 PHP 7.4+
  - 涉及文件：`libs/core.php`（3处）、`archive-template.php`（2处）、`tagcloud-template.php`（1处）、`component/index.recommend.php`（1处）、`post.php`（1处）

#### 轻微问题修复
- **L1：版本号同步**
  - `index.php` 主题版本号从 `2.1.1` 更新为 `2.1.5`，后台主题信息显示正确版本
- **L2：资源版本号递增**
  - `$ltAssetVersion` 从 `1.0.3` 递增为 `1.0.4`，确保 v2.1.2 新增的 `.no-thumb` CSS 样式能被浏览器正确加载
- **L3：懒加载 CSS 注入防护**
  - 懒加载背景图 URL 添加单引号转义（`src.replace(/'/g, "\\'")`），防止 `data-src` 读取时 HTML 实体解码导致的 CSS 注入
- **L4：点赞接口文章存在性校验**
  - 点赞前查询文章是否存在且状态为 `publish`，不存在时返回 `article not found` 错误
  - 防止对不存在的文章 ID 点赞产生无效的 fields 记录
  - 数据库查询异常时记录错误日志并放行，不影响正常点赞

---

## [2.1.4] - 2026-09-05

### 文档
- 新增独立 `CHANGELOG.md` 更新日志文件，遵循 Keep a Changelog 格式
- 重写 `README.md`：移除 TypeRenew 相关说明，更新为 Typecho 1.2.0+ 原版兼容
- README 中更新日志精简为近期版本摘要，完整日志链接到 CHANGELOG.md
- 补充常见问题：文章内容缓存、可信代理白名单、瀑布流超时等

---

## [2.1.3] - 2026-09-05

### 修复
- 移除 `lantern.config.js` 的 `preload` 声明，消除浏览器「preloaded but not used」警告
  - 该脚本在 footer 加载，不属于关键渲染路径，preload 收益有限
  - 保留 `jquery.min.js` 的 preload（在 header 内同步加载，preload 合理）

---

## [2.1.2] - 2026-09-05

### 修复
- 文章列表缩略图空值处理：无缩略图时不再输出空的 `data-src=""`，改用 `no-thumb` 类显示复古渐变背景
- 新增 `.no-thumb` CSS 样式（`#f5f0e6` → `#e8e0d0` 渐变），与主题复古风格一致

---

## [2.1.1] - 2026-09-05

### 修复（代码审查问题修复）

#### 严重问题修复
- **S1：阅读量/点赞递增竞态条件**
  - 新增 `lt_atomic_increment_field()` 函数，使用 `INSERT ... ON DUPLICATE KEY UPDATE int_value = int_value + 1` 实现 SQL 原子递增
  - `lt_increment_views()` 和 `lt_increment_likes()` 改用原子递增，彻底避免高并发下计数少计

#### 中等问题修复
- **M1：文章内容缓存配置变更失效**
  - 新增 `lt_get_config_hash()` 函数，计算影响文章内容显示的配置项哈希
  - 缓存格式改为 `{configHash}||{content}`，读取时校验哈希
  - 配置变更后哈希不匹配，缓存自动失效，重新生成内容

#### 轻微问题修复
- **L1：点赞 fetch 请求超时控制**
  - 点赞请求添加 `AbortController` 15 秒超时，避免网络异常时按钮一直 disabled
- **L2：速率限制文件缓存 flock 锁**
  - `lt_check_rate_limit()` 改用 `fopen` + `flock(LOCK_EX | LOCK_NB)` 排他锁
  - 锁获取失败时放行请求（非阻塞，避免影响用户体验）
  - 文件无法打开时降级为原无锁模式
- **L3：客户端 IP 可信代理白名单**
  - `lt_get_client_ip()` 增加 `LT_TRUSTED_PROXY_IPS` 常量支持
  - 用户可在 `config.inc.php` 中定义可信代理 IP 列表（数组或逗号分隔字符串）
  - 定义后仅信任来自这些代理 IP 的 `X-Forwarded-For` / `X-Real-IP` 头
  - 未定义时保持向后兼容（信任所有转发头）

---

## [2.1.0] - 2026-09-01

### 主题更名
- 主题名由 LanternTownPlus 更名为 **LanternPress**
- 作者更名为 **木子小鱼**
- 项目地址变更为 https://github.com/muzihome/LanternPress

### 新增
- 字符串字段操作函数：`lt_get_field_str()` / `lt_set_field_str()` / `lt_delete_field()`
- 文章内容处理结果数据库缓存（`ltProcessedContent` 字段），长文章性能提升明显
- `LT_CSP_POLICY` 常量，集中管理 CSP 安全策略
- `lt_field_cache()` 扩展为支持混合类型和显式删除（`$delete` 参数）

### 变更
- 评论 AJAX 提交重构为事件委托模式，DOM 替换后无需重新绑定，避免内存泄漏
- 图片属性插入健壮性修复，正确识别 `>` 和 `/>` 两种闭合符
- `lt_esc_attr()` 改为委托调用 `lt_esc_html()`，减少重复代码
- `finishArticle` 钩子同时清除文章内容处理缓存

### 修复
- 自闭合标签（`<img ... />`）的 `alt` 和 `loading` 属性插入位置错误
- AJAX 评论提交后评论表单事件监听器可能累积

---

## [2.0.1] - 2026-08-31

### 新增
- `lt_icon()` SVG 数组静态缓存，避免每次调用重复构建 14 个长 SVG 字符串
- `fetch` 请求 15 秒超时控制（AbortController），避免网络异常时按钮一直 loading

### 变更
- `initLazyLoad()` 使用单例 IntersectionObserver，避免瀑布流加载更多后重复创建 observer 导致内存泄漏
- `getIconByType()` 合并到 `lt_icon()`，别名映射（wechat→weixin、email→mail）内置
- `parseAvatar()` 统一为始终返回字符串，移除 `$return` 参数的双重行为设计

### 移除
- `getIconByType()` 函数（已合并到 `lt_icon()`）
- `lt_breadcrumb()` 空实现函数（面包屑导航已全面移除）

### 修复
- 移动端导航菜单「搜索」按钮样式异常（背景/宽度/高度/padding 与其他菜单项不一致）
- 面包屑导航已从所有页面全面移除

---

## [2.0.0] - 2026-08-31

**重大版本更新，基于原始 LanternTown v1.0.0 经过 7 轮深度优化。**

### 新增功能
- 公安备案号配置项（ICP 备案号下方显示，自动链接至 beian.mps.gov.cn）
- 自定义导航菜单（每行「名称|URL」格式，留空自动显示独立页面）
- 归档页模板（archive-template.php）——按年份/月份归档，带文章数量统计，文件缓存
- 友链页模板（flinks-template.php）——支持 Markdown 列表格式
- 标签云页模板（tagcloud-template.php）——标签大小按使用频次自动调整
- 文章点赞功能——AJAX 无刷新，Cookie 防重复（1 年），IP 速率限制
- 文章阅读量统计——Cookie 防刷（24 小时内同一文章只计一次）
- 文章目录树——可按文章单独开启/关闭，小屏幕自动隐藏，移动端浮动按钮
- 模态框搜索——点击「搜索」居中弹出，输入框与按钮主题色统一
- 「跟随系统」主题色——自动根据浏览器/系统深色偏好切换明暗主题
- 返回顶部按钮——平滑滚动
- 阅读进度条
- 代码一键复制（Prism + Clipboard.js）
- 打赏收款二维码、社交媒体链接
- 自定义页脚文案
- 头像镜像代理配置（解决国内 Gravatar 访问慢的问题）
- 统计代码注入（支持百度统计、Google Analytics 等）
- 自定义 CSS / JS（后台直接填写，无需修改主题文件）
- 阅读时长估算 + 字数统计

### 性能优化
- 文章列表图片懒加载（IntersectionObserver）
- 资源预加载（preload / preconnect）
- 归档页数据文件缓存（文章发布时自动清除）
- 数据库字段操作请求内静态缓存
- `INSERT ... ON DUPLICATE KEY UPDATE` 单条 SQL upsert
- 推荐文章批量查询（单次内容查询 + 单次字段查询）
- 上一篇/下一篇单次内容查询 + 单次字段查询
- 瀑布流加载更多 AJAX 片段请求（减少整页流量）
- 文章图片原生懒加载 `loading="lazy"`

### 安全加固
- 安全响应头：CSP / X-Frame-Options / X-Content-Type-Options / Referrer-Policy
- HTTPS 站点自动启用 HSTS
- 点赞接口 IP 速率限制（10 秒内最多 5 次）
- 全局 HTML 输出转义（XSS 防护）
- URL 安全校验（防止 javascript: / data: 协议注入）
- 缓存目录 `.htaccess` 保护

### 代码质量
- 全站国际化支持（`_t()` / `_e()`）
- 统一异常处理 + 错误日志（关键路径记录到 PHP error_log）
- 严格类型声明（`declare(strict_types=1)`）
- 函数文档注释完善
- 配置项描述统一 HTML 转义

### Bug 修复
- 后台配置面板缺少「保存设置」按钮
- 上一篇/下一篇时间戳相同时漏篇问题（复合条件排序）
- 文章图片嵌套锚点问题（已在 `<a>` 内的图片不重复包裹）
- 正则处理失败时内容丢失问题（失败回退原始内容）
- 点赞接口重复检查导致的冗余查询
- 评论提交后按钮状态未恢复问题
- 移动端菜单点击链接后不自动关闭问题
- matchMedia 监听器未清理导致的内存泄漏

---

## [1.0.0] - 原始版本（LanternTown）

### 新增
- 初始版本发布（作者：TypeRenew/Yangsh888）
- 经典报纸复古风格设计
- 基础文章列表、文章详情、评论功能
- Swiper 推荐文章轮播
- Fancybox 图片灯箱
- Prism 代码高亮

---

## 升级说明

### 从 v2.0.x 升级到 v2.1.x
1. 备份当前主题目录
2. 用新版本文件覆盖
3. 清除 OPcache
4. 文章内容缓存会在首次访问时自动重新生成（配置哈希校验）

### 从 v1.0.0（LanternTown）升级到 v2.x
1. 建议完整备份
2. 替换所有主题文件
3. 在后台重新配置主题设置（新增了多个配置项）
4. 清除 OPcache

---

## 相关链接

- **项目仓库**：https://github.com/muzihome/LanternPress
- **原始主题**：LanternTown（作者：TypeRenew/Yangsh888）
- **作者**：木子小鱼
