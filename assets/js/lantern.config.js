(() => {
    'use strict';

    class Lantern {
        constructor() {
            // 不依赖配置的能力优先执行，避免配置异常时这些功能失效
            this.initLazyLoad();
            this.initCodeLang();
            this.initBackToTop();
            this.initReadingProgress();
            this.initSearchGuard();
            this.initMobileMenuClose();
            this.initLikeButton();
            this.initSearchModal();
            this.initArticleLinks();
            this.initFancybox();
            this.initCodeCopy();
            if (!window.LANTERTOWN_CONFIG) {
                return;
            }
            this.initThemeMode();
            this.initLoadMore();
            this.initComment();
        }

        initThemeMode() {
            const blog = document.getElementById('blog_container');
            if (!blog) {
                return;
            }
            // 幂等设置：先清除可能存在的主题类，再按配置添加，避免多类共存样式错乱
            blog.classList.remove('bg0', 'bg1', 'bg2', 'bg3', 'bg-auto');
            const mode = parseInt(window.LANTERTOWN_CONFIG.THEME_MODE, 10) || 0;
            if (mode === 4) {
                // E9：跟随系统深色模式，动态在明/暗两套主题间切换
                const mq = window.matchMedia ? window.matchMedia('(prefers-color-scheme: dark)') : null;
                const applyAuto = () => {
                    blog.classList.remove('bg0', 'bg3');
                    blog.classList.add(mq && mq.matches ? 'bg3' : 'bg0');
                };
                if (mq && typeof mq.addEventListener === 'function') {
                    mq.addEventListener('change', applyAuto);
                    // J2：页面卸载时移除 matchMedia 监听器，避免 bfcache 恢复时重复注册导致内存泄漏
                    window.addEventListener('pagehide', function cleanup() {
                        mq.removeEventListener('change', applyAuto);
                        window.removeEventListener('pagehide', cleanup);
                    });
                }
                applyAuto();
            } else {
                blog.classList.add('bg' + mode);
            }
        }

        initLoadMore() {
            if (window.LANTERTOWN_CONFIG.TURN_PAGE_TYPE !== 'waterfall') {
                return;
            }
            const loadMoreLink = document.querySelector('.loadmore a');
            if (!loadMoreLink) {
                return;
            }
            loadMoreLink.setAttribute('data-href', loadMoreLink.getAttribute('href') || '');
            loadMoreLink.removeAttribute('href');
            const self = this;
            loadMoreLink.addEventListener('click', async function () {
                if (this.hasAttribute('disabled')) {
                    return;
                }
                this.textContent = 'loading...';
                this.setAttribute('disabled', 'disabled');
                const url = this.getAttribute('data-href');
                if (!url) {
                    this.removeAttribute('disabled');
                    this.textContent = '加载更多';
                    return;
                }
                try {
                    // E6：请求服务端片段（仅文章列表），减少整页流量与解析开销
                    const sep = url.indexOf('?') >= 0 ? '&' : '?';
                    // J1：使用 AbortController 设置 15 秒超时，避免网络异常时按钮一直 loading
                    const controller = new AbortController();
                    const timeoutId = setTimeout(function () { controller.abort(); }, 15000);
                    const response = await fetch(url + sep + 'from=ajax', {
                        credentials: 'same-origin',
                        signal: controller.signal
                    });
                    clearTimeout(timeoutId);
                    const data = await response.text();
                    this.removeAttribute('disabled');
                    this.textContent = '加载更多';
                    const parser = new DOMParser();
                    const parsed = parser.parseFromString(data, 'text/html');
                    const list = parsed.querySelectorAll('.recent');
                    const articleList = document.getElementById('articleList');
                    let firstAdded = null;
                    if (articleList && list.length) {
                        list.forEach((node) => {
                            const imported = document.importNode(node, true);
                            if (!firstAdded) {
                                firstAdded = imported;
                            }
                            articleList.appendChild(imported);
                        });
                    }
                    const navbar = document.querySelector('.navbar');
                    if (firstAdded) {
                        window.scroll({
                            top: firstAdded.getBoundingClientRect().top + window.pageYOffset - ((navbar ? navbar.offsetHeight : 0) + 20),
                            behavior: 'smooth'
                        });
                    }
                    const newURL = parsed.querySelector('.loadmore a')?.getAttribute('href') || '';
                    if (newURL) {
                        this.setAttribute('data-href', newURL);
                    } else {
                        this.closest('.loadmore')?.remove();
                    }
                    self.initLazyLoad();
                } catch (err) {
                    this.removeAttribute('disabled');
                    // J1：区分超时与其他错误
                    this.textContent = (err && err.name === 'AbortError') ? '请求超时，请重试' : '加载更多';
                }
            });
        }

        initComment() {
            window.TypechoComment = {
                dom: function (id) {
                    return document.getElementById(id);
                },
                create: function (tag, attr) {
                    const el = document.createElement(tag);
                    for (const key in attr) {
                        if (Object.prototype.hasOwnProperty.call(attr, key)) {
                            el.setAttribute(key, attr[key]);
                        }
                    }
                    return el;
                },
                reply: function (cid, coid) {
                    const comment = this.dom(cid);
                    const respond = document.querySelector('.respond');
                    const response = this.dom(respond ? respond.getAttribute('data-respondId') : '');
                    let input = this.dom('comment-parent');
                    const form = response && response.tagName === 'FORM' ? response : (response ? response.getElementsByTagName('form')[0] : null);
                    if (!comment || !response || !form) {
                        return false;
                    }
                    const textarea = response.getElementsByTagName('textarea')[0];
                    if (input === null) {
                        input = this.create('input', {
                            type: 'hidden',
                            name: 'parent',
                            id: 'comment-parent'
                        });
                        form.appendChild(input);
                    }
                    input.setAttribute('value', coid);
                    if (this.dom('comment-form-place-holder') === null) {
                        const holder = this.create('div', {
                            id: 'comment-form-place-holder'
                        });
                        response.parentNode.insertBefore(holder, response);
                    }
                    if (!comment.contains(response)) {
                        comment.appendChild(response);
                    }
                    this.dom('cancel-comment-reply-link').style.display = '';
                    if (textarea !== null && textarea.name === 'text') {
                        const nav = document.querySelector('.navbar');
                        const anchor = this.dom(cid);
                        const comments = this.dom('comments');
                        window.scroll({
                            top: ((anchor ? anchor.getBoundingClientRect().top + window.pageYOffset : (comments ? comments.getBoundingClientRect().top + window.pageYOffset : 0))) - ((nav ? nav.offsetHeight : 0) + 20),
                            behavior: 'smooth'
                        });
                    }
                    return false;
                },
                cancelReply: function () {
                    const respond = document.querySelector('.respond');
                    const response = this.dom(respond ? respond.getAttribute('data-respondId') : '');
                    const holder = this.dom('comment-form-place-holder');
                    const input = this.dom('comment-parent');
                    if (!response) {
                        return true;
                    }
                    if (input !== null) {
                        input.parentNode.removeChild(input);
                    }
                    if (holder === null) {
                        return true;
                    }
                    this.dom('cancel-comment-reply-link').style.display = 'none';
                    holder.parentNode.insertBefore(response, holder);
                    const comments = this.dom('comments');
                    const nav = document.querySelector('.navbar');
                    window.scroll({
                        top: (comments ? comments.getBoundingClientRect().top + window.pageYOffset : 0) - ((nav ? nav.offsetHeight : 0) + 20),
                        behavior: 'smooth'
                    });
                    return false;
                }
            };

            // E8：评论 AJAX 提交（仅当存在评论表单且支持 fetch 时启用）
            this.initCommentAjax();
        }

        // B2：评论 AJAX 提交使用事件委托，DOM 替换后无需重新绑定，避免监听器残留
        initCommentAjax() {
            if (typeof window.fetch !== 'function') {
                return;
            }
            const self = this;
            document.addEventListener('submit', (e) => {
                const form = e.target;
                if (form && form.id === 'comment-form') {
                    e.preventDefault();
                    self.handleCommentSubmit(form);
                }
            });
        }

        // 评论表单提交处理逻辑（事件委托调用，无需绑定到具体 DOM）
        handleCommentSubmit(form) {
            const submitBtn = document.getElementById('misubmit');
            const showBox = (type, msg) => {
                const box = document.createElement('div');
                box.className = 'comment-form-status ' + type;
                box.textContent = msg;
                form.parentNode.insertBefore(box, form);
            };
            // R-a：从 Typecho 返回页面中尽量提取具体错误消息
            const extractError = (html) => {
                const patterns = [
                    /(评论内容不能为空|请输入评论内容|评论内容过长|请输入正确的邮箱地址|邮箱格式不正确|评论提交过于频繁|请稍后再试|验证码错误|请输入验证码|请输入昵称|昵称不能为空)/,
                    /(对不起[^<。；;]{0,40})/,
                    /(页面已过期|请刷新页面后重试|系统错误)/
                ];
                for (const p of patterns) {
                    const m = html.match(p);
                    if (m) {
                        return m[1];
                    }
                }
                return null;
            };
            (async () => {
                if (submitBtn) {
                    submitBtn.disabled = true;
                }
                const formData = new FormData(form);
                try {
                    const resp = await fetch(form.action, {
                        method: 'POST',
                        body: formData,
                        credentials: 'same-origin',
                        headers: { 'X-Requested-With': 'XMLHttpRequest' }
                    });
                    const html = await resp.text();
                    if (!resp.ok) {
                        const msg = resp.status === 403 ? '请求已过期，请刷新页面后重试' : (extractError(html) || '提交失败，请检查填写内容后重试');
                        showBox('error', msg);
                        return;
                    }
                    const doc = new DOMParser().parseFromString(html, 'text/html');
                    const newComments = doc.getElementById('comments');
                    const currentComments = document.getElementById('comments');
                    if (newComments && currentComments) {
                        // 用服务端返回的最新评论区替换当前 DOM（含新评论与分页）
                        currentComments.outerHTML = newComments.outerHTML;
                        const freshForm = document.getElementById('comment-form');
                        if (freshForm) {
                            // B2：事件委托已全局监听 submit，无需重新绑定
                            const box = document.createElement('div');
                            box.className = 'comment-form-status success';
                            box.textContent = '评论提交成功，感谢你的参与';
                            freshForm.parentNode.insertBefore(box, freshForm);
                        }
                        const freshComments = document.getElementById('comments');
                        if (freshComments) {
                            const navH = document.querySelector('.navbar')?.offsetHeight || 70;
                            window.scrollTo({
                                top: freshComments.getBoundingClientRect().top + window.pageYOffset - (navH + 20),
                                behavior: 'smooth'
                            });
                        }
                    } else {
                        showBox('error', extractError(html) || '提交失败，请检查填写内容后重试');
                    }
                } catch (err) {
                    showBox('error', '网络错误，请稍后重试');
                } finally {
                    if (submitBtn) {
                        submitBtn.disabled = false;
                    }
                }
            })();
        }

        initLazyLoad() {
            const lazyBgElements = document.querySelectorAll('.lazy-bg[data-src]');
            if (!lazyBgElements.length) {
                return;
            }

            const loadImage = (el) => {
                const src = el.getAttribute('data-src');
                if (src) {
                    // L3：转义单引号，防止 CSS 注入（data-src 读取时浏览器会自动解码 HTML 实体）
                    el.style.backgroundImage = `url('${src.replace(/'/g, "\\'")}')`;
                    el.classList.add('loaded');
                    el.removeAttribute('data-src');
                    el.classList.remove('lazy-bg');
                }
            };

            if ('IntersectionObserver' in window) {
                // P2：使用单例 observer，避免瀑布流加载更多后重复创建 observer 导致内存泄漏
                if (!this._lazyObserver) {
                    this._lazyObserver = new IntersectionObserver((entries) => {
                        entries.forEach(entry => {
                            if (entry.isIntersecting) {
                                loadImage(entry.target);
                                this._lazyObserver.unobserve(entry.target);
                            }
                        });
                    }, {
                        rootMargin: '50px 0px',
                        threshold: 0.01
                    });
                }
                lazyBgElements.forEach(el => this._lazyObserver.observe(el));
            } else {
                lazyBgElements.forEach(el => loadImage(el));
            }
        }

        // E7：为代码块显示语言标签
        initCodeLang() {
            const pres = document.querySelectorAll('#post-content pre');
            if (!pres.length) {
                return;
            }
            // O3：常见语言规范化映射
            const langMap = {
                'javascript': 'JavaScript', 'js': 'JavaScript', 'jsx': 'JSX',
                'typescript': 'TypeScript', 'ts': 'TypeScript', 'tsx': 'TSX',
                'python': 'Python', 'py': 'Python',
                'java': 'Java', 'kotlin': 'Kotlin', 'swift': 'Swift',
                'go': 'Go', 'golang': 'Go', 'rust': 'Rust',
                'c': 'C', 'cpp': 'C++', 'c++': 'C++', 'csharp': 'C#', 'cs': 'C#',
                'php': 'PHP', 'ruby': 'Ruby', 'perl': 'Perl',
                'html': 'HTML', 'xml': 'XML', 'svg': 'SVG',
                'css': 'CSS', 'scss': 'SCSS', 'sass': 'Sass', 'less': 'Less',
                'json': 'JSON', 'yaml': 'YAML', 'yml': 'YAML', 'toml': 'TOML',
                'sql': 'SQL', 'mysql': 'MySQL', 'bash': 'Bash', 'sh': 'Shell', 'shell': 'Shell',
                'powershell': 'PowerShell', 'ps': 'PowerShell',
                'markdown': 'Markdown', 'md': 'Markdown',
                'docker': 'Docker', 'dockerfile': 'Dockerfile',
                'nginx': 'Nginx', 'apache': 'Apache',
                'vue': 'Vue', 'react': 'React',
                'diff': 'Diff', 'git': 'Git',
                'plaintext': 'Plain Text', 'text': 'Plain Text', 'txt': 'Plain Text'
            };
            pres.forEach(function (pre) {
                const code = pre.querySelector('code[class*="language-"]');
                if (!code || pre.querySelector('.code-lang')) {
                    return;
                }
                const m = (code.className || '').match(/language-([\w-]+)/);
                if (!m) {
                    return;
                }
                const raw = m[1].replace(/^-/, '').toLowerCase();
                const span = document.createElement('span');
                span.className = 'code-lang';
                span.textContent = langMap[raw] || raw.charAt(0).toUpperCase() + raw.slice(1);
                pre.appendChild(span);
            });
        }

        // E4：返回顶部按钮
        initBackToTop() {
            const btn = document.getElementById('back-to-top');
            if (!btn) {
                return;
            }
            const toggle = () => {
                const top = window.pageYOffset || document.documentElement.scrollTop || 0;
                btn.classList.toggle('show', top > 300);
            };
            window.addEventListener('scroll', toggle, { passive: true });
            toggle();
            btn.addEventListener('click', () => {
                window.scrollTo({ top: 0, behavior: 'smooth' });
            });
        }

        // E-a：文章页阅读进度条
        initReadingProgress() {
            const bar = document.getElementById('reading-progress');
            if (!bar) {
                return;
            }
            const update = () => {
                const doc = document.documentElement;
                const total = doc.scrollHeight - window.innerHeight;
                const top = window.pageYOffset || doc.scrollTop || 0;
                const ratio = total > 0 ? Math.min(1, top / total) : 0;
                bar.style.width = (ratio * 100) + '%';
            };
            window.addEventListener('scroll', update, { passive: true });
            update();
        }

        // R-b：搜索空关键词拦截
        initSearchGuard() {
            document.querySelectorAll('form[role="search"]').forEach((form) => {
                if (form.getAttribute('data-guard-ready')) {
                    return;
                }
                form.setAttribute('data-guard-ready', '1');
                form.addEventListener('submit', function (e) {
                    const input = this.querySelector('input[name="s"]');
                    if (input && !(input.value || '').trim()) {
                        e.preventDefault();
                        input.focus();
                    }
                });
            });
        }

        // R-c：移动菜单点选链接后自动收起
        initMobileMenuClose() {
            const ul = document.getElementById('mobile-menu-list');
            if (!ul) {
                return;
            }
            ul.addEventListener('click', function (e) {
                if (!e.target.closest('a')) {
                    return;
                }
                const obj = document.getElementById('navbar-mobile-menu-icon');
                if (obj) {
                    obj.classList.remove('open');
                    obj.setAttribute('aria-expanded', 'false');
                    ul.style.display = 'none';
                }
            });
        }

        // B8：文章点赞按钮（AJAX 提交，cookie 防重复）
        initLikeButton() {
            const btn = document.querySelector('.lt-like-btn');
            if (!btn) {
                return;
            }
            btn.addEventListener('click', async function () {
                if (btn.disabled || btn.classList.contains('liked')) {
                    return;
                }
                const cid = btn.getAttribute('data-cid');
                if (!cid) {
                    return;
                }
                const countEl = btn.querySelector('.lt-like-count');
                const iconEl = btn.querySelector('.lt-like-icon');
                btn.disabled = true;
                try {
                    const formData = new FormData();
                    formData.append('action', 'lt_like');
                    formData.append('cid', cid);
                    // L1：添加 AbortController 15 秒超时，避免网络异常时按钮一直 disabled
                    const controller = new AbortController();
                    const timeoutId = setTimeout(function () { controller.abort(); }, 15000);
                    const resp = await fetch(window.location.href, {
                        method: 'POST',
                        body: formData,
                        credentials: 'same-origin',
                        headers: { 'X-Requested-With': 'XMLHttpRequest' },
                        signal: controller.signal
                    });
                    clearTimeout(timeoutId);
                    const contentType = resp.headers.get('content-type') || '';
                    if (!resp.ok || !contentType.includes('application/json')) {
                        throw new Error('server returned non-JSON response');
                    }
                    const data = await resp.json();
                    if (data && data.success) {
                        if (countEl) {
                            countEl.textContent = data.likes;
                        }
                        if (iconEl) {
                            iconEl.textContent = '♥';
                        }
                        btn.classList.add('liked');
                        btn.disabled = true;
                    } else {
                        throw new Error(data && data.error ? data.error : 'like failed');
                    }
                } catch (err) {
                    // A4：失败时按钮整体临时显示"失败"，1.5 秒后恢复原状
                    const originalHTML = btn.innerHTML;
                    btn.innerHTML = '<span class="lt-like-icon">✕</span><span class="lt-like-count">失败</span>';
                    setTimeout(function () {
                        btn.innerHTML = originalHTML;
                        btn.disabled = false;
                    }, 1500);
                }
            });
        }

        // 搜索模态框：点击导航"搜索"按钮弹出，居中显示搜索框
        initSearchModal() {
            const modal = document.getElementById('search-modal');
            const input = document.getElementById('search-modal-input');
            const overlay = document.getElementById('search-modal-overlay');
            const closeBtn = document.getElementById('search-modal-close');
            const openBtns = document.querySelectorAll('#nav-search-btn, #nav-search-btn-mobile');
            if (!modal || !openBtns.length) {
                return;
            }
            const open = () => {
                modal.classList.add('show');
                document.body.style.overflow = 'hidden';
                setTimeout(() => { if (input) input.focus(); }, 50);
            };
            const close = () => {
                modal.classList.remove('show');
                document.body.style.overflow = '';
            };
            openBtns.forEach(function (btn) {
                btn.addEventListener('click', function () {
                    // 移动端点击后先关闭移动菜单
                    const mobileIcon = document.getElementById('navbar-mobile-menu-icon');
                    const mobileList = document.getElementById('mobile-menu-list');
                    if (mobileIcon && mobileIcon.classList.contains('open')) {
                        mobileIcon.classList.remove('open');
                        mobileIcon.setAttribute('aria-expanded', 'false');
                        if (mobileList) mobileList.style.display = 'none';
                    }
                    open();
                });
            });
            if (overlay) overlay.addEventListener('click', close);
            if (closeBtn) closeBtn.addEventListener('click', close);
            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape' && modal.classList.contains('show')) {
                    close();
                }
            });
            // Bug4：空关键词拦截由 initSearchGuard 统一处理，此处不再重复绑定
        }

        // Bug3：文章卡片跳转事件委托，替代内联 onclick 和全局 toPost 函数
        initArticleLinks() {
            document.addEventListener('click', function (e) {
                const item = e.target.closest('.article-item');
                if (!item || e.target.closest('a, button')) return;
                const href = item.getAttribute('data-href');
                if (href) window.location.href = href;
            });
            document.addEventListener('keydown', function (e) {
                if (e.key !== 'Enter' && e.key !== ' ') return;
                const item = e.target.closest('.article-item');
                if (!item) return;
                e.preventDefault();
                const href = item.getAttribute('data-href');
                if (href) window.location.href = href;
            });
        }

        // O1：fancybox 图片灯箱初始化（原 footer.php 内联脚本整合至此）
        initFancybox() {
            const doInit = () => {
                if (typeof jQuery !== 'function' || typeof jQuery.fn.fancybox !== 'function') return;
                jQuery('.fancybox').fancybox({
                    loop: true,
                    buttons: ['zoom', 'slideShow', 'thumbs', 'close'],
                    animationEffect: 'zoom-in-out',
                    transitionEffect: 'slide'
                });
            };
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', doInit);
            } else {
                doInit();
            }
        }

        // O1：代码块复制按钮（原 footer.php 内联脚本整合至此）
        initCodeCopy() {
            const doInit = () => {
                const pres = document.querySelectorAll('#post-content pre');
                if (!pres.length || typeof window.ClipboardJS !== 'function') return;
                pres.forEach(function (pre) {
                    if (pre.querySelector('.copy-btn')) return;
                    const btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'copy-btn';
                    btn.textContent = '复制';
                    pre.appendChild(btn);
                });
                new ClipboardJS('.copy-btn', {
                    target: function (trigger) {
                        const code = trigger.parentElement.querySelector('code');
                        return code || trigger.parentElement;
                    }
                }).on('success', function (e) {
                    const btn = e.trigger;
                    btn.textContent = '已复制';
                    setTimeout(function () { btn.textContent = '复制'; }, 1500);
                    e.clearSelection();
                }).on('error', function (e) {
                    e.trigger.textContent = '复制失败';
                    setTimeout(function () { e.trigger.textContent = '复制'; }, 1500);
                });
            };
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', doInit);
            } else {
                doInit();
            }
        }
    }

    window.Lantern = Lantern;
    new Lantern();
})();
