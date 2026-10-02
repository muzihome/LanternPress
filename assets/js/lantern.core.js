(() => {
'use strict';
function copyText(text) {
return new Promise((resolve) => {
const fallback = () => {
const ta = document.createElement('textarea');
ta.value = text;
ta.setAttribute('readonly', '');
ta.style.position = 'fixed';
ta.style.top = '-9999px';
ta.style.left = '-9999px';
ta.style.opacity = '0';
document.body.appendChild(ta);
ta.select();
ta.setSelectionRange(0, ta.value.length);
let ok = false;
try {
ok = document.execCommand('copy');
} catch (e) {
ok = false;
}
document.body.removeChild(ta);
resolve(ok);
};
if (navigator.clipboard && navigator.clipboard.writeText) {
navigator.clipboard.writeText(text).then(() => resolve(true), fallback);
} else {
fallback();
}
});
}
function showToast(msg, type) {
type = type || 'success';
document.querySelectorAll('.lt-toast').forEach(function (t) { t.remove(); });
const toast = document.createElement('div');
toast.className = 'lt-toast lt-toast-' + type;
toast.textContent = msg;
document.body.appendChild(toast);
requestAnimationFrame(() => toast.classList.add('lt-toast-show'));
setTimeout(() => {
toast.classList.remove('lt-toast-show');
setTimeout(() => toast.remove(), 300);
}, 1800);
}
class Lantern {
constructor() {
    this.initLazyLoad();
    this.initBackToTop();
    this.initSearchGuard();
    this.initMobileMenuClose();
    this.initSearchModal();
    this.initArticleLinks();
    this.initNavbar();
    this.initRecommendSlider();
    if (!window.LANTERTOWN_CONFIG) {
        return;
    }
    this.initThemeMode();
    this.initLoadMore();
}
initThemeMode() {
const blog = document.getElementById('blog_container');
if (!blog) {
return;
}
blog.classList.remove('bg0', 'bg1', 'bg2', 'bg3', 'bg-auto');
const mode = parseInt(window.LANTERTOWN_CONFIG.THEME_MODE, 10) || 0;
if (mode === 4) {
const mq = window.matchMedia ? window.matchMedia('(prefers-color-scheme: dark)') : null;
const applyAuto = () => {
blog.classList.remove('bg0', 'bg3');
blog.classList.add(mq && mq.matches ? 'bg3' : 'bg0');
};
if (mq && typeof mq.addEventListener === 'function') {
mq.addEventListener('change', applyAuto);
window.addEventListener('pageshow', (e) => {
if (e.persisted) {
applyAuto();
}
});
}
applyAuto();
} else {
blog.classList.add('bg' + mode);
}
}
initNavbar() {
const nav = document.getElementById('navbar');
if (!nav) {
return;
}
const OFFSET = 70;
nav.classList.add('animated');
let lastY = window.pageYOffset || document.documentElement.scrollTop || 0;
let ticking = false;
const update = () => {
ticking = false;
const y = window.pageYOffset || document.documentElement.scrollTop || 0;
if (y > OFFSET && y > lastY) {
nav.classList.remove('slideDown');
nav.classList.add('slideUp');
} else if (y < lastY || y <= OFFSET) {
nav.classList.remove('slideUp');
nav.classList.add('slideDown');
}
lastY = y;
};
window.addEventListener('scroll', () => {
if (!ticking) {
window.requestAnimationFrame(update);
ticking = true;
}
}, { passive: true });
update();
const icon = document.getElementById('navbar-mobile-menu-icon');
const list = document.getElementById('mobile-menu-list');
if (icon && list) {
const toggleMenu = () => {
const open = icon.classList.toggle('open');
list.style.display = open ? 'block' : 'none';
icon.setAttribute('aria-expanded', open ? 'true' : 'false');
};
icon.addEventListener('click', toggleMenu);
icon.addEventListener('keydown', (e) => {
if (e.key === 'Enter' || e.key === ' ') {
e.preventDefault();
toggleMenu();
}
});
}
}
initRecommendSlider() {
const root = document.querySelector('.recommend-slider');
const slider = root && root.querySelector('.lt-slider');
const track = slider && slider.querySelector('.lt-slider-track');
if (!root || !slider || !track) {
return;
}
let realSlides = Array.prototype.slice.call(track.children);
if (realSlides.length < 2) {
return;
}
const loop = realSlides.length >= 3;
const total = realSlides.length;
const prevBtn = root.querySelector('.slider-btn-prev');
const nextBtn = root.querySelector('.slider-btn-next');
if (loop) {
const markClone = (node) => {
node.classList.add('lt-clone');
node.setAttribute('aria-hidden', 'true');
const focusable = node.querySelectorAll('[tabindex], a, button');
focusable.forEach((el) => el.setAttribute('tabindex', '-1'));
const lazyBgs = node.querySelectorAll('.lazy-bg[data-src]');
if (lazyBgs.length) {
if (this._lazyObserver) {
lazyBgs.forEach((el) => this._lazyObserver.observe(el));
} else {
lazyBgs.forEach((el) => {
const src = el.getAttribute('data-src');
if (src) {
el.style.backgroundImage = `url('${src.replace(/'/g, "\\'")}')`;
el.classList.add('loaded');
el.removeAttribute('data-src');
el.classList.remove('lazy-bg');
}
});
}
}
};
const lastClone = realSlides[total - 1].cloneNode(true);
markClone(lastClone);
const firstClone = realSlides[0].cloneNode(true);
markClone(firstClone);
track.insertBefore(lastClone, track.firstChild);
track.appendChild(firstClone);
}
let index = loop ? 1 : 0;
let slideWidth = 0;
let offset = 0;
let animating = false;
let animTimer = null;
let autoTimer = null;
let suppressClick = false;
const maxIndex = () => (loop ? total + 1 : total - 1);
const layout = (animate) => {
const rect = slider.getBoundingClientRect();
const firstSlide = track.children[0];
slideWidth = slider.clientWidth
|| slider.offsetWidth
|| (rect && rect.width ? rect.width : 0)
|| (firstSlide ? firstSlide.getBoundingClientRect().width : 0)
|| slideWidth;
offset = -index * slideWidth;
track.classList.toggle('lt-no-anim', !animate);
track.style.transform = 'translate3d(' + offset + 'px,0,0)';
};
const releaseAnim = () => {
animating = false;
if (animTimer !== null) {
clearTimeout(animTimer);
animTimer = null;
}
};
const wrapJump = () => {
if (!loop) {
return;
}
if (index === 0) {
index = total;
layout(false);
} else if (index === total + 1) {
index = 1;
layout(false);
}
};
const go = (dir) => {
if (animating) {
return;
}
let target = index + dir;
if (!loop) {
target = (target + total) % total;
} else if (target < 0 || target > total + 1) {
return;
}
index = target;
animating = true;
layout(true);
if (animTimer !== null) {
clearTimeout(animTimer);
}
animTimer = setTimeout(() => {
animTimer = null;
animating = false;
}, 600);
};
track.addEventListener('transitionend', (e) => {
if (e.target !== track || e.propertyName !== 'transform') {
return;
}
releaseAnim();
wrapJump();
});
window.addEventListener('resize', () => {
releaseAnim();
layout(false);
});
layout(false);
if (prevBtn) {
prevBtn.addEventListener('mousedown', (e) => e.preventDefault());
prevBtn.addEventListener('click', () => { go(-1); restartAuto(); });
}
if (nextBtn) {
nextBtn.addEventListener('mousedown', (e) => e.preventDefault());
nextBtn.addEventListener('click', () => { go(1); restartAuto(); });
}
const stopAuto = () => {
if (autoTimer !== null) {
clearInterval(autoTimer);
autoTimer = null;
}
};
const startAuto = () => {
stopAuto();
autoTimer = setInterval(() => go(1), 4000);
};
function restartAuto() {
startAuto();
}
root.addEventListener('focusin', stopAuto);
root.addEventListener('focusout', (e) => {
if (e.relatedTarget && root.contains(e.relatedTarget)) {
return;
}
startAuto();
});
root.addEventListener('touchstart', stopAuto, { passive: true });
root.addEventListener('touchend', startAuto, { passive: true });
startAuto();
window.addEventListener('load', () => {
releaseAnim();
layout(false);
});
let startX = 0;
let deltaX = 0;
let pointerActive = false;
let downTarget = null;
slider.addEventListener('pointerdown', (e) => {
pointerActive = true;
downTarget = e.target;
startX = e.clientX;
deltaX = 0;
releaseAnim();
stopAuto();
try {
slider.setPointerCapture(e.pointerId);
} catch (err) { }
});
slider.addEventListener('pointermove', (e) => {
if (!pointerActive) {
return;
}
deltaX = e.clientX - startX;
track.classList.add('lt-no-anim');
track.style.transform = 'translate3d(' + (offset + deltaX) + 'px,0,0)';
});
const endDrag = (e) => {
if (!pointerActive) {
return;
}
pointerActive = false;
if (Math.abs(deltaX) > slideWidth * 0.2) {
suppressClick = true;
go(deltaX < 0 ? 1 : -1);
} else {
layout(true);
}
startAuto();
};
slider.addEventListener('pointerup', endDrag);
slider.addEventListener('pointercancel', endDrag);
slider.addEventListener('click', (e) => {
if (suppressClick) {
suppressClick = false;
e.preventDefault();
e.stopPropagation();
return;
}
// 指针捕获后 click target 被浏览器重定向为 slider，须用 pointerdown 记录的原始目标定位文章
// （捕获阶段处理并阻止冒泡，避免 document 委托重复跳转；列表区无 capture 不受影响）
const clickTarget = downTarget && document.contains(downTarget) ? downTarget : e.target;
const item = clickTarget.closest('.article-item');
if (item) {
const href = item.getAttribute('data-href');
if (href) {
e.preventDefault();
e.stopPropagation();
window.location.href = href;
}
}
}, true);
}
initLoadMore() {
if (window.LANTERTOWN_CONFIG.TURN_PAGE_TYPE !== 'waterfall') {
return;
}
const loadMoreLink = document.querySelector('.loadmore a');
if (!loadMoreLink) {
return;
}
const self = this;
loadMoreLink.addEventListener('click', async function (e) {
e.preventDefault();
if (this.hasAttribute('disabled')) {
return;
}
const url = this.getAttribute('href');
if (!url) {
return;
}
const originalText = this.textContent;
this.textContent = 'loading...';
this.setAttribute('disabled', 'disabled');
this.setAttribute('aria-disabled', 'true');
try {
const sep = url.indexOf('?') >= 0 ? '&' : '?';
const controller = new AbortController();
const timeoutId = setTimeout(function () { controller.abort(); }, 15000);
const response = await fetch(url + sep + 'from=ajax', {
credentials: 'same-origin',
signal: controller.signal
});
clearTimeout(timeoutId);
if (!response.ok) {
throw new Error('HTTP ' + response.status);
}
const data = await response.text();
this.removeAttribute('disabled');
this.removeAttribute('aria-disabled');
this.textContent = originalText;
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
this.setAttribute('href', newURL);
} else {
this.closest('.loadmore')?.remove();
}
self.initLazyLoad();
} catch (err) {
this.removeAttribute('disabled');
this.removeAttribute('aria-disabled');
if (err && err.name === 'AbortError') {
this.textContent = '请求超时，请重试';
} else {
this.textContent = originalText;
}
}
});
}
initLazyLoad() {
const lazyBgElements = document.querySelectorAll('.lazy-bg[data-src]');
if (!lazyBgElements.length) {
return;
}
const loadImage = (el) => {
const src = el.getAttribute('data-src');
if (src) {
el.style.backgroundImage = `url('${src.replace(/'/g, "\\'")}')`;
el.classList.add('loaded');
el.removeAttribute('data-src');
el.classList.remove('lazy-bg');
}
fitSidebarToContent();
};
if ('IntersectionObserver' in window) {
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
initSearchModal() {
const modal = document.getElementById('search-modal');
const input = document.getElementById('search-modal-input');
const overlay = document.getElementById('search-modal-overlay');
const closeBtn = document.getElementById('search-modal-close');
const openBtns = document.querySelectorAll('#nav-search-btn, #nav-search-btn-mobile');
if (!modal || !openBtns.length) {
return;
}
let lastOpener = null;
const open = (opener) => {
modal.classList.add('show');
document.body.style.overflow = 'hidden';
lastOpener = opener || null;
setTimeout(() => { if (input) input.focus(); }, 50);
};
const close = () => {
modal.classList.remove('show');
document.body.style.overflow = '';
if (lastOpener && typeof lastOpener.focus === 'function') {
lastOpener.focus();
}
lastOpener = null;
};
openBtns.forEach(function (btn) {
btn.addEventListener('click', function () {
const mobileIcon = document.getElementById('navbar-mobile-menu-icon');
const mobileList = document.getElementById('mobile-menu-list');
if (mobileIcon && mobileIcon.classList.contains('open')) {
mobileIcon.classList.remove('open');
mobileIcon.setAttribute('aria-expanded', 'false');
if (mobileList) mobileList.style.display = 'none';
}
open(btn);
});
});
if (overlay) overlay.addEventListener('click', close);
if (closeBtn) closeBtn.addEventListener('click', close);
document.addEventListener('keydown', function (e) {
if (e.key === 'Escape' && modal.classList.contains('show')) {
close();
}
});
}
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
if (e.target.closest('a, button')) return;
e.preventDefault();
const href = item.getAttribute('data-href');
if (href) window.location.href = href;
});
}
}

window.Lantern = Lantern;
window.LT_UTIL = { copyText: copyText, showToast: showToast };
window.fitSidebarToContent = fitSidebarToContent;

function fitSidebarToContent() {
    var c = document.getElementById('post-content');
    var s = document.getElementById('postSidebar');
    if (!c || !s) return;
    if (window.matchMedia('(min-width: 1151px)').matches) {
        // min-height 语义：正文更高则侧边栏跟随正文高度；侧边栏内容更高时自然撑开，不被裁剪
        s.style.minHeight = c.offsetHeight + 'px';
    } else {
        s.style.minHeight = '';
    }
}
// AVIF 降级：浏览器不支持 avif 时依次回退 webp → jpg/jpeg/png/gif/bmp/tif/tiff（自 footer 内联迁入统一管理）
function initAvifFallback() {
    var d = document, q = 'img[src*=".avif"]',
        s = 'data:image/avif;base64,AAAAIGZ0eXBhdmlmAAAAAGF2aWZtaXNwMmNjb2xpc29tYw==';
    function t(c) {
        var i = new Image();
        i.onload = function () { c(i.width > 0); };
        i.onerror = function () { c(false); };
        i.src = s;
    }
    function r() {
        var imgs = d.querySelectorAll(q);
        for (var j = 0; j < imgs.length; j++) {
            (function (img) {
                var exts = ['.jpg', '.jpeg', '.png', '.gif', '.bmp', '.tif', '.tiff'], idx = 0, oldErr = img.onerror;
                img.onerror = function () {
                    var base = img.src.replace(/\.(webp|avif)(\?|$)/, '$2');
                    function n() {
                        if (idx >= exts.length) { if (oldErr) oldErr.call(img); return; }
                        var ts = base.replace(/(\?|$)/, exts[idx] + '$1'); idx++;
                        var ti = new Image();
                        ti.onload = function () { img.src = ts; };
                        ti.onerror = n;
                        ti.src = ts;
                    }
                    n();
                };
                img.src = img.src.replace(/\.avif(\?|$)/, '.webp$1');
            })(imgs[j]);
        }
    }
    t(function (u) {
        if (!u) {
            if (d.readyState === 'loading') d.addEventListener('DOMContentLoaded', r);
            else r();
        }
    });
}
initAvifFallback();
window.addEventListener('load', () => {
    fitSidebarToContent();
    // 图片懒加载/字体加载后正文高度可能变化，延迟再对齐一次
    setTimeout(fitSidebarToContent, 200);
});
// 懒加载图片在 load 之后才完成加载时，加载完成后再对齐一次（慢网兜底）
document.querySelectorAll('#post-content img').forEach((img) => {
    if (img && !img.complete) {
        img.addEventListener('load', () => setTimeout(fitSidebarToContent, 60), { once: true });
    }
});
let _ltSidebarResizeTimer = null;
window.addEventListener('resize', () => {
    if (_ltSidebarResizeTimer) clearTimeout(_ltSidebarResizeTimer);
    _ltSidebarResizeTimer = setTimeout(fitSidebarToContent, 100);
});

window.LANTERN_INSTANCE = new Lantern();
})();
