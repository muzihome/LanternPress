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
class Lantern {
constructor() {
this.initLazyLoad();
this.initCodeLang();
this.initBackToTop();
this.initReadingProgress();
this.initSearchGuard();
this.initMobileMenuClose();
this.initLikeButton();
this.initSearchModal();
this.initArticleLinks();
this.initNavbar();
this.initRecommendSlider();
this.initLightbox();
this.initCodeCopy();
this.initCatalog();
this.initSocialPopup();
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
slider.addEventListener('pointerdown', (e) => {
pointerActive = true;
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
}
}, true);
}
initLightbox() {
const links = Array.prototype.slice.call(document.querySelectorAll('#post-content a.fancybox'));
if (!links.length) {
return;
}
const groups = {};
links.forEach((link) => {
const key = link.getAttribute('data-fancybox') || 'default';
if (!groups[key]) {
groups[key] = [];
}
groups[key].push(link);
});
const box = document.createElement('div');
box.className = 'lt-lightbox';
box.setAttribute('role', 'dialog');
box.setAttribute('aria-modal', 'true');
box.setAttribute('aria-hidden', 'true');
box.innerHTML =
'<div class="lt-lightbox-overlay"></div>' +
'<button type="button" class="lt-lightbox-nav lt-lightbox-close" aria-label="关闭">&times;</button>' +
'<button type="button" class="lt-lightbox-nav lt-lightbox-prev" aria-label="上一张">&#8249;</button>' +
'<div class="lt-lightbox-stage"><img class="lt-lightbox-img" alt=""></div>' +
'<button type="button" class="lt-lightbox-nav lt-lightbox-next" aria-label="下一张">&#8250;</button>' +
'<div class="lt-lightbox-caption"></div>';
document.body.appendChild(box);
const img = box.querySelector('.lt-lightbox-img');
const caption = box.querySelector('.lt-lightbox-caption');
const overlay = box.querySelector('.lt-lightbox-overlay');
const closeBtn = box.querySelector('.lt-lightbox-close');
const prevBtn = box.querySelector('.lt-lightbox-prev');
const nextBtn = box.querySelector('.lt-lightbox-next');
let currentGroup = null;
let currentIndex = 0;
let lastTrigger = null;
const render = () => {
const trigger = currentGroup[currentIndex];
const srcImg = trigger.querySelector('img');
img.classList.remove('lt-zoomed');
img.style.transform = '';
img.src = trigger.getAttribute('href');
img.alt = srcImg ? (srcImg.alt || '') : '';
caption.textContent = img.alt;
};
const open = (key, index, trigger) => {
currentGroup = groups[key];
currentIndex = index;
lastTrigger = trigger;
render();
box.classList.add('show');
box.setAttribute('aria-hidden', 'false');
document.body.style.overflow = 'hidden';
closeBtn.focus();
};
const close = () => {
box.classList.remove('show');
box.setAttribute('aria-hidden', 'true');
document.body.style.overflow = '';
if (lastTrigger && typeof lastTrigger.focus === 'function') {
lastTrigger.focus();
}
lastTrigger = null;
};
const nav = (dir) => {
const n = currentGroup.length;
currentIndex = (currentIndex + dir + n) % n;
render();
};
document.addEventListener('click', (e) => {
const link = e.target.closest && e.target.closest('a.fancybox');
if (!link || !document.body.contains(link)) {
return;
}
const key = link.getAttribute('data-fancybox') || 'default';
if (!groups[key]) {
return;
}
e.preventDefault();
open(key, groups[key].indexOf(link), link);
});
overlay.addEventListener('click', close);
closeBtn.addEventListener('click', close);
prevBtn.addEventListener('click', () => nav(-1));
nextBtn.addEventListener('click', () => nav(1));
img.addEventListener('click', (e) => {
e.stopPropagation();
img.classList.toggle('lt-zoomed');
});
document.addEventListener('keydown', (e) => {
if (!box.classList.contains('show')) {
return;
}
if (e.key === 'Escape') {
close();
} else if (e.key === 'ArrowLeft') {
nav(-1);
} else if (e.key === 'ArrowRight') {
nav(1);
} else if (e.key === 'Tab') {
const focusables = [closeBtn, prevBtn, nextBtn].filter((b) => b.offsetParent !== null);
if (!focusables.length) {
return;
}
const first = focusables[0];
const last = focusables[focusables.length - 1];
const active = document.activeElement;
if (e.shiftKey && (active === first || !box.contains(active))) {
e.preventDefault();
last.focus();
} else if (!e.shiftKey && active === last) {
e.preventDefault();
first.focus();
}
}
});
}
initCodeCopy() {
const doInit = () => {
const pres = document.querySelectorAll('#post-content pre');
if (!pres.length) {
return;
}
pres.forEach((pre) => {
if (pre.querySelector('.copy-btn')) {
return;
}
const btn = document.createElement('button');
btn.type = 'button';
btn.className = 'copy-btn';
btn.textContent = '复制';
btn.addEventListener('click', async () => {
const code = pre.querySelector('code');
const ok = await copyText(code ? code.textContent : pre.textContent);
btn.textContent = ok ? '已复制' : '复制失败';
setTimeout(() => { btn.textContent = '复制'; }, 1500);
});
pre.appendChild(btn);
});
};
if (document.readyState === 'loading') {
document.addEventListener('DOMContentLoaded', doInit);
} else {
doInit();
}
}
initCatalog() {
const postContent = document.getElementById('post-content');
const mount = document.getElementById('catalog-directory');
const container = document.querySelector('.catalog-container');
if (!postContent || !mount || !container) {
return;
}
if (window.matchMedia('(max-width: 767px)').matches) {
['catalog-float-btn', 'catalog-mask'].forEach((id) => {
const el = document.getElementById(id);
if (el && el.parentNode) {
el.parentNode.removeChild(el);
}
});
if (container.parentNode) {
container.parentNode.removeChild(container);
}
return;
}
const titles = Array.prototype.slice.call(postContent.querySelectorAll('h1,h2,h3,h4,h5,h6'));
if (!titles.length) {
return;
}
const root = document.createElement('ul');
const stack = [root];
const levels = [0];
titles.forEach((node, index) => {
if (!node.id) {
node.id = 'menu-index-' + (index + 1);
}
const level = parseInt(node.tagName.charAt(1), 10) || 1;
const li = document.createElement('li');
const a = document.createElement('a');
a.href = '#' + node.id;
a.textContent = node.textContent || '';
li.appendChild(a);
while (levels.length > 1 && levels[levels.length - 1] >= level) {
levels.pop();
stack.pop();
}
stack[stack.length - 1].appendChild(li);
const childUl = document.createElement('ul');
li.appendChild(childUl);
stack.push(childUl);
levels.push(level);
});
mount.appendChild(root);
mount.querySelectorAll('ul').forEach((ul) => {
if (ul.children.length === 0 && ul.parentNode) {
ul.parentNode.removeChild(ul);
}
});
const catalogCid = container.getAttribute('data-cid') || '0';
mount.querySelectorAll('li').forEach((li, idx) => {
let subUl = null;
for (let i = 0; i < li.children.length; i++) {
if (li.children[i].tagName === 'UL') {
subUl = li.children[i];
break;
}
}
if (!subUl || !subUl.children.length) {
return;
}
const storageKey = 'lt_catalog_' + catalogCid + '_' + idx;
let expanded = true;
try {
if (localStorage.getItem(storageKey) === '0') {
expanded = false;
}
} catch (e) { }
if (!expanded) {
subUl.style.display = 'none';
}
const toggle = document.createElement('span');
toggle.className = 'catalog-toggle';
toggle.textContent = expanded ? '\u25BE' : '\u25B8';
toggle.setAttribute('role', 'button');
toggle.setAttribute('tabindex', '0');
toggle.setAttribute('aria-label', '展开/收起');
toggle.setAttribute('aria-expanded', expanded ? 'true' : 'false');
toggle.style.cssText = 'cursor:pointer;display:inline-block;width:12px;margin-right:2px;font-size:10px;color:inherit;user-select:none;';
li.insertBefore(toggle, li.firstChild);
const togglePanelNode = (ev) => {
ev.stopPropagation();
expanded = !expanded;
subUl.style.display = expanded ? '' : 'none';
toggle.textContent = expanded ? '\u25BE' : '\u25B8';
toggle.setAttribute('aria-expanded', expanded ? 'true' : 'false');
try {
localStorage.setItem(storageKey, expanded ? '1' : '0');
} catch (err) { }
};
toggle.addEventListener('click', togglePanelNode);
toggle.addEventListener('keydown', (e) => {
if (e.key === 'Enter' || e.key === ' ') {
e.preventDefault();
togglePanelNode();
}
});
});
const mobileMql = window.matchMedia('(max-width: 767px)');
let isMobile = mobileMql.matches;
const floatBtn = document.getElementById('catalog-float-btn');
const mask = document.getElementById('catalog-mask');
const panelCloseBtn = container.querySelector('.catalog-panel-close');
const anchors = Array.prototype.slice.call(mount.querySelectorAll('a'));
const pageY = () => window.pageYOffset || document.documentElement.scrollTop || 0;
const closePanel = () => {
container.classList.remove('open');
if (mask) {
mask.classList.remove('show');
}
};
const applyMode = (mobile) => {
isMobile = mobile;
if (mobile) {
if (floatBtn) {
floatBtn.style.display = 'block';
}
container.classList.add('mobile-panel');
} else {
closePanel();
if (floatBtn) {
floatBtn.style.display = '';
}
container.classList.remove('mobile-panel');
updateActive();
}
};
if (floatBtn) {
floatBtn.addEventListener('click', () => {
container.classList.toggle('open');
if (mask) {
mask.classList.toggle('show');
}
});
}
if (panelCloseBtn) {
panelCloseBtn.addEventListener('click', closePanel);
}
if (mask) {
mask.addEventListener('click', closePanel);
}
mount.addEventListener('click', (e) => {
const a = e.target.closest('a');
if (!a) {
return;
}
const href = a.getAttribute('href');
if (!href || href.charAt(0) !== '#') {
return;
}
const target = document.querySelector(href);
if (!target) {
return;
}
e.preventDefault();
target.scrollIntoView({ behavior: 'smooth', block: 'start' });
if (window.history && window.history.replaceState) {
window.history.replaceState(null, '', href);
}
if (isMobile) {
setTimeout(closePanel, 300);
}
});
let ticking = false;
const updateActive = () => {
ticking = false;
if (isMobile) {
return;
}
const top = pageY();
let active = 0;
titles.forEach((node, idx) => {
const nodeTop = node.getBoundingClientRect().top + top;
if (top + 10 > nodeTop) {
active = idx;
}
});
anchors.forEach((a, i) => {
a.classList.toggle('current', i === active);
});
const contentTop = postContent.getBoundingClientRect().top + top;
const inRange = top > 70 && top < contentTop + postContent.offsetHeight;
mount.style.opacity = inRange ? '1' : '0';
};
window.addEventListener('scroll', () => {
if (!ticking) {
window.requestAnimationFrame(updateActive);
ticking = true;
}
}, { passive: true });
const onMqChange = (e) => applyMode(e.matches);
if (typeof mobileMql.addEventListener === 'function') {
mobileMql.addEventListener('change', onMqChange);
} else if (typeof mobileMql.addListener === 'function') {
mobileMql.addListener(onMqChange);
}
applyMode(isMobile);
updateActive();
}
initSocialPopup() {
const bg = document.getElementById('background-layer');
if (!bg) {
return;
}
const popupBody = document.getElementById('popup-body');
const closeBtn = document.getElementById('social-pop-close');
let lastFocused = null;
let rewardUrls = [];
try {
rewardUrls = JSON.parse(bg.getAttribute('data-reward') || '[]');
} catch (e) {
rewardUrls = [];
}
const isVisible = () => bg.style.display === 'flex';
const open = (images) => {
popupBody.textContent = '';
images.forEach((src) => {
const img = document.createElement('img');
img.src = src;
img.alt = '';
popupBody.appendChild(img);
});
lastFocused = document.activeElement;
bg.style.display = 'flex';
bg.style.opacity = '1';
if (closeBtn) {
closeBtn.focus();
}
};
const close = () => {
bg.style.display = 'none';
bg.style.opacity = '0';
popupBody.textContent = '';
if (lastFocused && typeof lastFocused.focus === 'function') {
lastFocused.focus();
}
lastFocused = null;
};
bg.addEventListener('click', (e) => {
if (e.target === bg) {
close();
}
});
document.addEventListener('keydown', (e) => {
if (e.key === 'Escape' && isVisible()) {
close();
}
});
if (closeBtn) {
closeBtn.addEventListener('click', close);
closeBtn.addEventListener('keydown', (e) => {
if (e.key === 'Enter' || e.key === ' ') {
e.preventDefault();
close();
}
});
}
document.querySelectorAll('[data-reward="1"]').forEach((el) => {
el.addEventListener('click', (e) => {
e.preventDefault();
if (rewardUrls.length) {
open(rewardUrls);
}
});
});
document.querySelectorAll('[data-qr]').forEach((el) => {
el.addEventListener('click', (e) => {
e.preventDefault();
const src = el.getAttribute('data-qr');
if (src) {
open([src]);
}
});
});
document.querySelectorAll('.author-email').forEach((el) => {
el.addEventListener('click', (e) => {
e.preventDefault();
const encoded = el.getAttribute('data-email') || '';
let mail = '';
try {
mail = atob(encoded);
} catch (err) {
return;
}
if (mail && /^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(mail)) {
window.location.href = 'mailto:' + mail;
}
});
});
document.querySelectorAll('.share-copy').forEach((btn) => {
btn.addEventListener('click', async () => {
const url = btn.getAttribute('data-url') || window.location.href;
const label = btn.querySelector('.share-btn-text');
const oldText = label ? label.textContent : '';
const done = (ok) => {
if (label) {
label.textContent = ok ? '已复制' : '复制失败';
setTimeout(() => { label.textContent = oldText; }, 1500);
}
};
done(await copyText(url));
});
});
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
const _cancelLink = this.dom('cancel-comment-reply-link');
if (_cancelLink) { _cancelLink.style.display = ''; }
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
const _cancelLinkHide = this.dom('cancel-comment-reply-link');
if (_cancelLinkHide) { _cancelLinkHide.style.display = 'none'; }
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
this.initCommentAjax();
}
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
handleCommentSubmit(form) {
const submitBtn = document.getElementById('misubmit');
const showBox = (type, msg) => {
form.parentNode.querySelectorAll('.comment-form-status').forEach((b) => b.remove());
const box = document.createElement('div');
box.className = 'comment-form-status ' + type;
box.setAttribute('role', 'status');
box.textContent = msg;
form.parentNode.insertBefore(box, form);
};
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
currentComments.outerHTML = newComments.outerHTML;
const freshForm = document.getElementById('comment-form');
if (freshForm) {
freshForm.parentNode.querySelectorAll('.comment-form-status').forEach((b) => b.remove());
const box = document.createElement('div');
box.className = 'comment-form-status success';
box.setAttribute('role', 'status');
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
el.style.backgroundImage = `url('${src.replace(/'/g, "\\'")}')`;
el.classList.add('loaded');
el.removeAttribute('data-src');
el.classList.remove('lazy-bg');
}
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
initCodeLang() {
const pres = document.querySelectorAll('#post-content pre');
if (!pres.length) {
return;
}
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
const token = btn.getAttribute('data-token') || '';
if (token) {
formData.append('_', token);
}
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
iconEl.textContent = '\u2665';
}
btn.classList.add('liked');
btn.disabled = true;
} else {
throw new Error(data && data.error ? data.error : 'like failed');
}
} catch (err) {
const originalHTML = btn.innerHTML;
btn.innerHTML = '<span class="lt-like-icon">\u2715</span><span class="lt-like-count">失败</span>';
setTimeout(function () {
btn.innerHTML = originalHTML;
btn.disabled = false;
}, 1500);
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
new Lantern();
})();
