(() => {
'use strict';
const Lantern = window.Lantern;
if (!Lantern) { return; }
const LT = window.LT_UTIL || {};
const copyText = LT.copyText || function () { return Promise.resolve(false); };
const showToast = LT.showToast || function () { };
const proto = Lantern.prototype;

// qrcode 库懒加载：首次需要（微信扫码 / 海报二维码）时动态注入 qrcode.min.js，
// 避免文章页初始加载 19.9KB 第三方库；加载失败可重试（_ltQrPromise 置空）
let _ltQrPromise = null;
const _ltLoadQrcode = () => {
	if (typeof QRCode !== 'undefined') return Promise.resolve();
	if (_ltQrPromise) return _ltQrPromise;
	const cfg = window.LANTERTOWN_CONFIG || {};
	const base = String(cfg.THEME_URL || '').replace(/\/+$/, '') || '/usr/themes/LanternPress';
	const ver = cfg.ASSET_VERSION ? '?v=' + encodeURIComponent(String(cfg.ASSET_VERSION)) : '';
	_ltQrPromise = new Promise((resolve, reject) => {
		const s = document.createElement('script');
		s.src = base + '/assets/vendor/qrcode/qrcode.min.js' + ver;
		s.async = true;
		s.onload = () => { resolve(); };
		s.onerror = () => { _ltQrPromise = null; reject(new Error('qrcode 库加载失败')); };
		document.head.appendChild(s);
	});
	return _ltQrPromise;
};

proto.initLightbox = function () {
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
};

proto.initCodeCopy = function () {
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
};

proto.initCatalog = function () {
const postContent = document.getElementById('post-content');
const mount = document.getElementById('catalog-directory');
const container = document.querySelector('.catalog-container');
if (!postContent || !mount || !container) {
return;
}
if (window.matchMedia('(max-width: 767px)').matches) {
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
let autoId = 'menu-index-' + (index + 1);
let suffix = 2;
// 自动 id 判重：正文已存在同名 id 时追加后缀，避免锚点跳转歧义
while (document.getElementById(autoId)) {
autoId = 'menu-index-' + (index + 1) + '-' + suffix++;
}
node.id = autoId;
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
let expanded = false;
try {
if (localStorage.getItem(storageKey) === '1') {
expanded = true;
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
const panelCloseBtn = container.querySelector('.catalog-panel-close');
const anchors = Array.prototype.slice.call(mount.querySelectorAll('a'));
const pageY = () => window.pageYOffset || document.documentElement.scrollTop || 0;
const closePanel = () => {
container.classList.remove('open');
};
const applyMode = (mobile) => {
isMobile = mobile;
if (mobile) {
container.classList.add('mobile-panel');
} else {
closePanel();
container.classList.remove('mobile-panel');
updateActive();
}
};
if (panelCloseBtn) {
panelCloseBtn.addEventListener('click', closePanel);
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
};

proto.initSocialPopup = function () {
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
this.openPosterLayer(bg, closeBtn);
};
const close = () => {
this.closePosterLayer(bg, lastFocused);
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
const ok = await copyText(url);
showToast(ok ? '链接已复制' : '复制失败，请手动复制', ok ? 'success' : 'error');
});
});
};

proto.initComment = function () {
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
};

proto.initCommentAjax = function () {
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
};

proto.handleCommentSubmit = function (form) {
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
};

proto.initCodeLang = function () {
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
};

proto.initReadingProgress = function () {
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
};

proto.initLikeButton = function () {
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
const originCount = countEl ? countEl.textContent : '';
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
btn.classList.add('liked');
btn.disabled = true;
} else {
throw new Error(data && data.error ? data.error : 'like failed');
}
} catch (err) {
if (countEl) {
countEl.textContent = '失败';
countEl.classList.add('lt-like-fail');
setTimeout(function () {
countEl.textContent = originCount;
countEl.classList.remove('lt-like-fail');
btn.disabled = false;
}, 1500);
} else {
btn.disabled = false;
}
}
});
};

proto.initPostToolbar = function () {
	// 微信：桌面 hover/focus 弹出扫码；触屏点击切换、点外部关闭（qrcode 库懒加载，首次需要时注入）
	document.querySelectorAll('.share-wechat-wrap').forEach((wrap) => {
		const btn = wrap.querySelector('.share-wechat');
		const box = wrap.querySelector('.j-share-qrcode');
		if (!box) return;
		let rendered = false;
		const render = () => {
			if (rendered) return;
			_ltLoadQrcode().then(() => {
				try {
					new QRCode(box, {
						text: (btn && btn.getAttribute('data-share-url')) || window.location.href,
						width: 128,
						height: 128,
						correctLevel: QRCode.CorrectLevel.M
					});
					rendered = true;
				} catch (err) {
					console.warn('[LanternPress] 微信二维码生成失败', err);
				}
			}).catch((err) => {
				console.warn('[LanternPress] qrcode 库加载失败', err);
			});
		};
		wrap.addEventListener('mouseenter', render);
		wrap.addEventListener('focusin', render);
		// 触屏（hover:none，不误伤触屏笔记本）：点击切换面板；点击外部关闭（统一委托，避免多 wrap 累积监听）
		if (window.matchMedia && window.matchMedia('(hover: none)').matches) {
			btn.addEventListener('click', () => {
				render();
				wrap.classList.toggle('active');
			});
		}
	});
	// 触屏：点击面板外部关闭所有微信面板（单次委托监听）
	if (window.matchMedia && window.matchMedia('(hover: none)').matches) {
		document.addEventListener('click', (e) => {
			const activeWrap = e.target.closest('.share-wechat-wrap');
			document.querySelectorAll('.share-wechat-wrap.active').forEach((w) => {
				if (!activeWrap || w !== activeWrap) w.classList.remove('active');
			});
		});
	}
		// 原生分享：调用系统分享面板（navigator.share），不支持则隐藏按钮
		document.querySelectorAll('.share-native').forEach((el) => {
			if (typeof navigator.share !== 'function') {
				el.style.display = 'none';
				return;
			}
			el.addEventListener('click', async (e) => {
				e.preventDefault();
				const url = el.getAttribute('data-share-url') || window.location.href;
				const title = el.getAttribute('data-share-title') || document.title;
				try {
					await navigator.share({ title: title, url: url });
				} catch (err) {
					if (err && err.name !== 'AbortError') {
						showToast('分享失败，请使用复制链接', 'error');
					}
				}
			});
		});
	// 书签：浏览器安全限制无法自动添加收藏，点击引导用户使用快捷键（1.5s 内重复点击不重复弹）
	let _ltFavToastAt = 0;
	document.querySelectorAll('.share-fav').forEach((btn) => {
		btn.addEventListener('click', () => {
			const now = Date.now();
			if (now - _ltFavToastAt < 1500) return;
			_ltFavToastAt = now;
			const isTouch = window.matchMedia && window.matchMedia('(hover: none)').matches;
			const isMac = /Mac|iPhone|iPad/i.test(navigator.platform || '');
			showToast(
				isTouch
					? '请在浏览器菜单中选择「添加到书签/收藏」'
					: (isMac ? '请按 ⌘ + D 将本页添加到浏览器书签' : '请按 Ctrl + D 将本页添加到浏览器书签'),
				'success'
			);
		});
	});
};

proto.initPostFold = function () {
	const content = document.getElementById('post-content');
	if (!content || content.getAttribute('data-fold-bound')) return;
	const cfg = window.LANTERTOWN_CONFIG || {};
	if (String(cfg.POST_FOLD_ENABLE) === '0') return;
	const threshold = Math.max(400, parseInt(cfg.POST_FOLD_THRESHOLD, 10) || 2000);
	const heightCfg = Math.max(300, parseInt(cfg.POST_FOLD_HEIGHT, 10) || 1500);
	const minRatio = Math.min(80, Math.max(5, parseInt(cfg.POST_FOLD_MIN_RATIO, 10) || 20));
	// 移动端（≤767px，与主题断点一致）按视口高度折算约 1.8 屏；桌面用后台配置值
	const getFoldHeight = () => (window.matchMedia && window.matchMedia('(max-width: 767px)').matches)
		? Math.max(300, Math.round((window.innerHeight || 800) * 1.8))
		: heightCfg;
	let foldHeight = getFoldHeight();
	let wrap = null;
	let expanded = false;
	const maybeFold = () => {
		// 已折叠 / 已展开 / 已绑定：幂等跳过（含 load 二次检测）
		if (expanded || wrap) return;
		const fullHeight = content.scrollHeight;
		if (fullHeight <= threshold) return;
		const remainRatio = Math.round((fullHeight - foldHeight) / fullHeight * 100);
		if (remainRatio < minRatio) return;
		wrap = document.createElement('div');
		wrap.id = 'postFoldWrap';
		wrap.className = 'post-fold-wrap post-fold';
		wrap.style.setProperty('--lt-fold-height', foldHeight + 'px');
		content.parentNode.insertBefore(wrap, content);
		wrap.appendChild(content);
		const btn = document.createElement('button');
		btn.type = 'button';
		btn.className = 'post-fold-btn';
		btn.setAttribute('aria-label', '展开全文继续阅读');
		btn.textContent = '\u9605\u8bfb\u5269\u4f59 ' + remainRatio + '%';
		wrap.appendChild(btn);
		btn.addEventListener('click', expand);
		// 目录点击折叠区内锚点：先展开再走默认锚点跳转
		const catalog = document.querySelector('.catalog-directory');
		if (catalog) {
			catalog.addEventListener('click', (e) => {
				if (expanded || !wrap.classList.contains('post-fold')) return;
				const a = e.target.closest('a[href^="#"]');
				if (!a) return;
				const id = decodeURIComponent(a.getAttribute('href').slice(1));
				const target = id ? document.getElementById(id) : null;
				if (target && target.offsetTop > foldHeight) {
					e.preventDefault();
					expand();
					// 展开后滚动到锚点（延时等布局重排）
					setTimeout(() => { try { document.getElementById(id).scrollIntoView({ behavior: 'smooth', block: 'start' }); } catch (err) { } }, 60);
				}
			});
		}
	};
	const expand = () => {
		if (expanded) return;
		expanded = true;
		if (wrap) wrap.classList.remove('post-fold');
		if (window.fitSidebarToContent) setTimeout(window.fitSidebarToContent, 60);
	};
	// 移动端旋转 / 跨断点（767px）时重算折叠高度；展开后不再干预
	const recalcFoldHeight = () => {
		const next = getFoldHeight();
		if (next === foldHeight) return;
		foldHeight = next;
		if (expanded || !wrap || !wrap.classList.contains('post-fold')) return;
		const fullHeight = content.scrollHeight;
		if (Math.round((fullHeight - foldHeight) / fullHeight * 100) < minRatio) {
			// 旋转后剩余比例跌破下限 → 自动展开，避免「阅读剩余 X%」比例过低
			expand();
			return;
		}
		wrap.style.setProperty('--lt-fold-height', foldHeight + 'px');
	};
	if (window.matchMedia) {
		const mq = window.matchMedia('(max-width: 767px)');
		const handler = () => recalcFoldHeight();
		if (mq.addEventListener) mq.addEventListener('change', handler);
		else if (mq.addListener) mq.addListener(handler);
	}
	content.setAttribute('data-fold-bound', '1');
	maybeFold();
	// 图片/字体加载后 scrollHeight 可能变大：未展开且未折叠时二次检测
	window.addEventListener('load', maybeFold);
};

proto.initPoster = function () {
		const btn = document.getElementById('sharePosterBtn');
		const bg = document.getElementById('background-layer');
		const popupBody = document.getElementById('popup-body');
		if (!btn || !bg || !popupBody) return;
		// Esc 关闭弹窗
		document.addEventListener('keydown', (e) => {
			if (e.key === 'Escape' && bg.style.display === 'flex') this.closePosterLayer(bg);
		});
		btn.addEventListener('click', async (e) => {
			e.preventDefault();
			if (btn.classList.contains('poster-loading')) return;
			const token = (this._posterSession = (this._posterSession || 0) + 1);
			const labelEl = btn.querySelector('span');
			const labelTxt = labelEl ? String(labelEl.textContent || '') : '';
			btn.classList.add('poster-loading');
			btn.disabled = true;
			if (labelEl) labelEl.textContent = '\u751f\u6210\u4e2d\u2026';
			try {
				const og = document.querySelector('meta[property="og:image"]');
				const titleEl = document.querySelector('.post-title');
				const title = titleEl ? String(titleEl.textContent || '').replace(/\s+/g, ' ').trim() : String(document.title || '');
				let coverImg = null;
				// 封面优先级：服务端标识（theme:poster_cover=0 表示无封面）→ 路径兜底（blog_bg.jpg 兼容旧缓存）
				// 无封面时跳过 og:image，继续尝试正文首图 → 再无则随机封面
				const coverFlagEl = document.querySelector('meta[name="theme:poster_cover"]');
				const coverIsDefault = (coverFlagEl && coverFlagEl.content === '0')
					|| (!coverFlagEl && og && og.content && /blog_bg\.jpg/i.test(String(og.content).trim()));
				if (og && og.content && !coverIsDefault) {
					coverImg = await this.loadPosterCover(og.content);
				}
			if (!coverImg) {
				const imgs = document.querySelectorAll('#post-content img');
				for (const im of imgs) {
					const src = im.getAttribute('data-src') || im.currentSrc || im.src || '';
					if (!src) continue;
					coverImg = await this.loadPosterCover(new URL(src, window.location.href).href);
					if (coverImg) break;
				}
			}
			if (!coverImg) coverImg = await this.loadPosterCover(this.fallbackCover(title));
			const header = document.querySelector('#background-layer .popup-header');
			if (header) header.style.display = 'none';
			const logoImg = await this.loadLogoCover(this.getLogoUrl());
			// 海报二维码依赖 QRCode 库：懒加载（失败不阻断海报生成，仅无二维码）
			try { await _ltLoadQrcode(); } catch (err) { /* 忽略：renderPoster 内部有 typeof QRCode 防御 */ }
			let dataUrl = this.renderPoster(coverImg, logoImg);
			if (!dataUrl) {
				// 跨域封面导致画布污染：改用内嵌随机封面重绘
				const fbImg = await this.loadPosterCover(this.fallbackCover(title));
				dataUrl = this.renderPoster(fbImg, logoImg);
			}
			if (!dataUrl) { if (token === this._posterSession) this.renderPosterError(bg, popupBody); return; }
			// 生成期间被关闭（Esc/遮罩/×）则不再弹开
			if (token !== this._posterSession) return;
			popupBody.textContent = '';
			const wrap = document.createElement('div');
			wrap.className = 'poster-popup';
			const img = document.createElement('img');
			img.src = dataUrl;
			img.alt = '文章海报';
			const actions = document.createElement('div');
			actions.className = 'poster-actions';
			const dl = document.createElement('a');
			dl.className = 'poster-download';
			dl.href = dataUrl;
			dl.download = 'poster-' + Date.now() + '.png';
			dl.textContent = '\u2193\u4e0b\u8f7d';
			// iOS Safari 不支持 a[download]：改为新标签打开海报（可长按保存）
			if (/iPad|iPhone|iPod/i.test(navigator.userAgent || '')) {
				dl.removeAttribute('download');
				dl.textContent = '\u2193\u4fdd\u5b58';
				dl.addEventListener('click', (ev) => {
					ev.preventDefault();
					window.open(dataUrl, '_blank');
				});
			}
			const close = document.createElement('button');
			close.type = 'button';
			close.className = 'poster-close';
			close.textContent = '\u00d7\u5173\u95ed';
			close.addEventListener('click', () => {
				this.closePosterLayer(bg);
			});
			actions.appendChild(dl);
			actions.appendChild(close);
			wrap.appendChild(img);
			wrap.appendChild(actions);
			popupBody.appendChild(wrap);
			this.openPosterLayer(bg, dl);
			} catch (err) {
				if (token === this._posterSession) this.renderPosterError(bg, popupBody);
			} finally {
				btn.classList.remove('poster-loading');
				btn.disabled = false;
				if (labelEl) labelEl.textContent = labelTxt;
			}
		});
};

proto.renderPosterError = function (bg, popupBody) {
		if (!bg || !popupBody) return;
		popupBody.textContent = '';
		const wrap = document.createElement('div');
		wrap.className = 'poster-popup poster-error';
		const msg = document.createElement('p');
		msg.className = 'poster-error-msg';
		msg.textContent = '\u6d77\u62a5\u751f\u6210\u5931\u8d25\uff0c\u8bf7\u7a0d\u540e\u91cd\u8bd5';
		const close = document.createElement('button');
		close.type = 'button';
		close.className = 'poster-close';
		close.textContent = '\u00d7\u5173\u95ed';
		close.addEventListener('click', () => {
			this.closePosterLayer(bg);
		});
		wrap.appendChild(msg);
		wrap.appendChild(close);
		popupBody.appendChild(wrap);
		this.openPosterLayer(bg, close);
};

proto.openPosterLayer = function (bg, focusEl) {
		if (!bg) return;
		document.body.classList.add('poster-lock-scroll');
		bg.style.display = 'flex';
		bg.style.opacity = '0';
		cancelAnimationFrame(this._posterRAF);
		this._posterRAF = requestAnimationFrame(() => {
			this._posterRAF = requestAnimationFrame(() => { bg.style.opacity = '1'; });
		});
		if (focusEl && focusEl.focus) {
			try { focusEl.focus({ preventScroll: true }); } catch (err) { }
		}
};

proto.closePosterLayer = function (bg, focusEl) {
		if (!bg) return;
		document.body.classList.remove('poster-lock-scroll');
		if (bg.style.display !== 'flex') return;
		// 会话令牌失效：生成中的异步完成后不再弹开弹窗（Esc/遮罩/× 关闭均触发）
		this._posterSession = (this._posterSession || 0) + 1;
		bg.style.opacity = '0';
		clearTimeout(this._posterCloseTimer);
		this._posterCloseTimer = setTimeout(() => { bg.style.display = 'none'; }, 200);
		// 清空弹层内容（:has(.poster-popup) 依赖，保证 popup-header 恢复显示）
		const popupBody = document.getElementById('popup-body');
		if (popupBody) popupBody.textContent = '';
		// 恢复 popup-header 内联样式（JS 兜底隐藏的残留，避免打赏弹窗 × 消失）
		const hd = document.querySelector('#background-layer .popup-header');
		if (hd) hd.style.display = '';
		// 焦点还原（优先调用方指定元素，默认回生成海报按钮）
		const btn = focusEl || document.getElementById('sharePosterBtn');
		if (btn && btn.focus) {
			try { btn.focus({ preventScroll: true }); } catch (err) { }
		}
};

proto.proxyUrl = function (url) {
		try {
			const u = new URL(url, window.location.href);
			if (u.origin !== window.location.origin) {
				const themeUrl = (window.LANTERTOWN_CONFIG && window.LANTERTOWN_CONFIG.THEME_URL)
					? String(window.LANTERTOWN_CONFIG.THEME_URL)
					: '/usr/themes/LanternPress/';
				return window.location.origin + themeUrl + 'inc/poster-proxy.php?url=' + encodeURIComponent(u.href);
			}
		} catch (err) { }
		return url;
};

proto.loadCover = function (url, minSize) {
		return new Promise((resolve) => {
			if (!url) { resolve(null); return; }
			const img = new Image();
			let settled = false;
			const fin = (v) => { if (!settled) { settled = true; clearTimeout(timer); resolve(v); } };
			const ok = () => fin((img.naturalWidth >= minSize && img.naturalHeight >= minSize) ? img : null);
			const timer = setTimeout(() => {
				if (window.console && console.warn) console.warn('[poster] cover load timeout:', url);
				fin(null);
			}, 10000);
			// 图床已配置 CORS（ACAO）时 crossOrigin 直连可导出；
			// 直连失败（图床未配 ACAO 被 CORS 拦截）→ 主题代理兜底（同域加载可绘制导出）
			img.crossOrigin = 'anonymous';
			img.onload = ok;
			img.onerror = () => {
				img.onload = null;
				img.onerror = null;
				const p = this.proxyUrl(url);
				if (p !== url) {
					img.removeAttribute('crossorigin');
					img.src = p;
					img.onload = ok;
					img.onerror = () => {
						if (window.console && console.warn) console.warn('[poster] cover load failed (proxy):', p);
						fin(null);
					};
				} else {
					if (window.console && console.warn) console.warn('[poster] cover load failed:', url);
					fin(null);
				}
			};
			img.src = url;
		});
};

proto.loadPosterCover = function (url) {
		return this.loadCover(url, 100);
};

proto.getLogoUrl = function () {
		let url = '';
		// ① 后台「海报 LOGO 地址」配置（最高优先，海报专用）
		if (window.LANTERTOWN_CONFIG && window.LANTERTOWN_CONFIG.POSTER_LOGO) {
			url = String(window.LANTERTOWN_CONFIG.POSTER_LOGO).trim();
		}
		// ② 页面实际渲染的 LOGO（导航栏 logo 图）
		if (!url) {
			const logoEl = document.querySelector('.navbar-logo img.logo');
			if (logoEl) url = logoEl.getAttribute('data-src') || logoEl.currentSrc || logoEl.src || '';
		}
		// ③ 主题配置 THEME_LOGO
		if (!url && window.LANTERTOWN_CONFIG && window.LANTERTOWN_CONFIG.THEME_LOGO) {
			url = String(window.LANTERTOWN_CONFIG.THEME_LOGO).trim();
		}
		// ④ JSON-LD publisher.logo / logo
		if (!url) {
			try {
				const nodes = document.querySelectorAll('script[type="application/ld+json"]');
				for (const s of nodes) {
					const data = JSON.parse(s.textContent || '{}');
					const lu = (data && data.publisher && data.publisher.logo && data.publisher.logo.url) || (data && data.logo && data.logo.url);
					if (lu) { url = String(lu); break; }
				}
			} catch (err) { }
		}
		return url ? new URL(url, window.location.href).href : '';
};

proto.loadLogoCover = function (url) {
		return this.loadCover(url, 16);
};

proto.fallbackCover = function (title) {
		const covers = [
			'data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%22750%22%20height%3D%22450%22%20viewBox%3D%220%200%20750%20450%22%3E%3Cdefs%3E%3ClinearGradient%20id%3D%22a%22%20x1%3D%220%22%20y1%3D%220%22%20x2%3D%220%22%20y2%3D%221%22%3E%3Cstop%20offset%3D%220%22%20stop-color%3D%22%234a90d9%22%2F%3E%3Cstop%20offset%3D%221%22%20stop-color%3D%22%23cfe8f7%22%2F%3E%3C%2FlinearGradient%3E%3C%2Fdefs%3E%3Crect%20width%3D%22750%22%20height%3D%22450%22%20fill%3D%22url%28%23a%29%22%2F%3E%3Cpath%20d%3D%22M0%2C450%20L120%2C190%20L260%2C340%20L380%2C150%20L520%2C330%20L640%2C210%20L750%2C320%20L750%2C450%20Z%22%20fill%3D%22%233a5f8a%22%2F%3E%3Cpath%20d%3D%22M380%2C150%20L470%2C280%20L290%2C280%20Z%22%20fill%3D%22%23ffffff%22%2F%3E%3Cpath%20d%3D%22M240%2C450%20L420%2C120%20L600%2C450%20Z%22%20fill%3D%22%232c4a6e%22%2F%3E%3Cpath%20d%3D%22M420%2C120%20L500%2C260%20L340%2C260%20Z%22%20fill%3D%22%23f0f6ff%22%2F%3E%3Cpolygon%20points%3D%22640%2C330%20628%2C358%20652%2C358%22%20fill%3D%22%232d6a4f%22%2F%3E%3Cpolygon%20points%3D%22640%2C338%20630%2C362%20650%2C362%22%20fill%3D%22%231b4332%22%2F%3E%3Ccircle%20cx%3D%22640%22%20cy%3D%22330%22%20r%3D%227%22%20fill%3D%22%232d6a4f%22%2F%3E%3Ccircle%20cx%3D%22560%22%20cy%3D%22282%22%20r%3D%225%22%20fill%3D%22%23cc493d%22%2F%3E%3Cline%20x1%3D%22560%22%20y1%3D%22287%22%20x2%3D%22560%22%20y2%3D%22304%22%20stroke%3D%22%23333%22%20stroke-width%3D%223%22%2F%3E%3C%2Fsvg%3E',
			'data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%22750%22%20height%3D%22450%22%20viewBox%3D%220%200%20750%20450%22%3E%3Cdefs%3E%3ClinearGradient%20id%3D%22b%22%20x1%3D%220%22%20y1%3D%220%22%20x2%3D%220%22%20y2%3D%221%22%3E%3Cstop%20offset%3D%220%22%20stop-color%3D%22%230f766e%22%2F%3E%3Cstop%20offset%3D%221%22%20stop-color%3D%22%2399f6e4%22%2F%3E%3C%2FlinearGradient%3E%3C%2Fdefs%3E%3Crect%20width%3D%22750%22%20height%3D%22450%22%20fill%3D%22url%28%23b%29%22%2F%3E%3Ccircle%20cx%3D%22120%22%20cy%3D%22120%22%20r%3D%2270%22%20fill%3D%22rgba%28255%2C255%2C255%2C0.18%29%22%2F%3E%3Ccircle%20cx%3D%22650%22%20cy%3D%2290%22%20r%3D%2246%22%20fill%3D%22rgba%28255%2C255%2C255%2C0.14%29%22%2F%3E%3Cpath%20d%3D%22M0%2C340%20Q120%2C270%20240%2C340%20T480%2C340%20T720%2C340%20L720%2C450%20L0%2C450%20Z%22%20fill%3D%22%23115e59%22%2F%3E%3Cpath%20d%3D%22M0%2C400%20Q150%2C330%20320%2C400%20T640%2C400%20L640%2C450%20L0%2C450%20Z%22%20fill%3D%22%23134e4a%22%2F%3E%3Cpolygon%20points%3D%22380%2C250%20420%2C180%20460%2C250%22%20fill%3D%22%23d1fae5%22%2F%3E%3C%2Fsvg%3E',
			'data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%22750%22%20height%3D%22450%22%20viewBox%3D%220%200%20750%20450%22%3E%3Cdefs%3E%3ClinearGradient%20id%3D%22c%22%20x1%3D%220%22%20y1%3D%220%22%20x2%3D%220%22%20y2%3D%221%22%3E%3Cstop%20offset%3D%220%22%20stop-color%3D%22%235b21b6%22%2F%3E%3Cstop%20offset%3D%221%22%20stop-color%3D%22%23c4b5fd%22%2F%3E%3C%2FlinearGradient%3E%3C%2Fdefs%3E%3Crect%20width%3D%22750%22%20height%3D%22450%22%20fill%3D%22url%28%23c%29%22%2F%3E%3Cpolygon%20points%3D%220%2C450%20200%2C120%20400%2C450%22%20fill%3D%22rgba%28255%2C255%2C255%2C0.16%29%22%2F%3E%3Cpolygon%20points%3D%22280%2C450%20470%2C90%20660%2C450%22%20fill%3D%22rgba%28255%2C255%2C255%2C0.22%29%22%2F%3E%3Cpolygon%20points%3D%22520%2C450%20660%2C220%20750%2C340%20750%2C450%22%20fill%3D%22rgba%2830%2C10%2C80%2C0.35%29%22%2F%3E%3Ccircle%20cx%3D%22560%22%20cy%3D%22150%22%20r%3D%2226%22%20fill%3D%22%23fde68a%22%2F%3E%3C%2Fsvg%3E',
			'data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%22750%22%20height%3D%22450%22%20viewBox%3D%220%200%20750%20450%22%3E%3Cdefs%3E%3ClinearGradient%20id%3D%22d%22%20x1%3D%220%22%20y1%3D%220%22%20x2%3D%220%22%20y2%3D%221%22%3E%3Cstop%20offset%3D%220%22%20stop-color%3D%22%23ea580c%22%2F%3E%3Cstop%20offset%3D%221%22%20stop-color%3D%22%23fed7aa%22%2F%3E%3C%2FlinearGradient%3E%3C%2Fdefs%3E%3Crect%20width%3D%22750%22%20height%3D%22450%22%20fill%3D%22url%28%23d%29%22%2F%3E%3Ccircle%20cx%3D%22560%22%20cy%3D%22130%22%20r%3D%2255%22%20fill%3D%22rgba%28255%2C255%2C255%2C0.35%29%22%2F%3E%3Cpath%20d%3D%22M0%2C380%20Q140%2C300%20300%2C380%20T620%2C380%20L620%2C450%20L0%2C450%20Z%22%20fill%3D%22%239a3412%22%2F%3E%3Cpath%20d%3D%22M0%2C420%20Q180%2C360%20380%2C420%20T760%2C420%20L760%2C450%20L0%2C450%20Z%22%20fill%3D%22%237c2d12%22%2F%3E%3Cpolygon%20points%3D%22180%2C250%20240%2C160%20300%2C250%22%20fill%3D%22%23fff7ed%22%2F%3E%3C%2Fsvg%3E'
		];
		let h = 0;
		const t = String(title || '');
		for (let i = 0; i < t.length; i++) h = (h * 31 + t.charCodeAt(i)) % 1000003;
		return covers[h % covers.length];
};

proto.renderPoster = function (coverImg, logoImg) {
		const ogSite = document.querySelector('meta[property="og:site_name"]');
		let siteTitle = (ogSite && ogSite.content) ? String(ogSite.content).trim() : '';
		if (!siteTitle && window.LANTERTOWN_CONFIG && window.LANTERTOWN_CONFIG.BLOG_TITLE) {
			siteTitle = String(window.LANTERTOWN_CONFIG.BLOG_TITLE).trim();
		}
		if (!siteTitle) {
			const siteEl = document.querySelector('.logo-text, .site-brand, .site-name, .navbar-brand, .site-title');
			if (siteEl) siteTitle = String(siteEl.textContent || '').trim();
		}
		if (!siteTitle) {
			const dt = String(document.title || '');
			const seps = [' - ', ' -', '-', '_', '|'].filter((s) => dt.indexOf(s) > 0);
			let sepIdx = Infinity;
			for (const s of seps) sepIdx = Math.min(sepIdx, dt.indexOf(s));
			siteTitle = sepIdx !== Infinity ? dt.slice(0, sepIdx).trim() : dt;
		}
		if (!siteTitle) siteTitle = 'LanternPress';
		const titleEl = document.querySelector('.post-title');
		const title = titleEl ? String(titleEl.textContent || '').replace(/\s+/g, ' ').trim() : (document.title || '');
		const url = window.location.href;
		let host = '';
		try { host = new URL(url).hostname; } catch (err) { host = ''; }
		const hostParts = String(host).split('.').filter(Boolean);
		let topDomain = '';
		// IP 地址 / 本机域名整体作为顶级域名（避免 127.0.0.1 → "0.1" 之类错误切片）
		if (host && (host === 'localhost' || /^\d{1,3}(?:\.\d{1,3}){3}$/.test(host))) {
			topDomain = host;
		} else {
			topDomain = hostParts.length >= 2 ? hostParts.slice(-2).join('.') : host;
		}
		let author = '';
		let dateText = '';
		const metaEl = document.querySelector('.post-meta');
		if (metaEl) {
			const a = metaEl.querySelector('a');
			if (a) author = String(a.textContent || '').trim();
			// 日期仅在作者链接之后匹配（作者名含"XXXX年X月X日"时避免误取）
			let metaText = String(metaEl.textContent || '');
			if (a && author) metaText = metaText.slice(String(a.textContent || '').length);
			const dm = metaText.match(/\d{4}年\d{1,2}月\d{1,2}日(?:\s*\d{1,2}:\d{2}(?::\d{2})?)?/);
			if (dm) dateText = dm[0];
		}
		const contentEl = document.getElementById('post-content');
		let summary = '';
		if (contentEl) {
			const clone = contentEl.cloneNode(true);
			// 剔除脚本/样式/代码块/引用/表单/导航/内嵌帧/广告/隐藏元素，以及标题（避免序号与标题词混入摘要）
			clone.querySelectorAll('script, style, pre, blockquote, form, .post-password-form, nav, iframe, ins.adsbygoogle, .adsbygoogle, [hidden], [style*="display:none"]').forEach((n) => n.remove());
			clone.querySelectorAll('h1, h2, h3, h4').forEach((n) => n.remove());
			summary = String(clone.textContent || '').replace(/\s+/g, ' ').trim();
			// 按 Unicode 码点截断（避免 emoji/代理对被 slice 截半出现乱码）
			summary = Array.from(summary).slice(0, 150).join('');
		}
		const canvas = document.createElement('canvas');
		const PX = 2; // 导出像素比固定 2x（高清）
		canvas.width = 750;
		let ctx = canvas.getContext('2d');
		// 预分行：标题（bold 32px）与摘要（24px）必须先设对应字体再量宽（量度与绘制字体必须一致），
		// 行数决定作者/摘要/底部区块/画布高度；超出 maxLines 时末行补省略号
		const titleX = 30;
		const titleW = 690;
		const wrapLines = (text, maxWidth, maxLines) => {
			const lines = [];
			let cur = '';
			let overflow = false;
			for (const ch of String(text).split('')) {
				const test = cur + ch;
				if (ctx.measureText(test).width > maxWidth && cur) {
					// 超宽时优先回退到最后一个空格处断行（保留英文单词完整）
					const sp = cur.lastIndexOf(' ');
					const cut = sp > 0 ? sp : cur.length;
					lines.push(cur.slice(0, cut));
					if (lines.length === maxLines) {
						overflow = true;
						break;
					}
					cur = sp > 0 ? cur.slice(cut + 1) + ch : ch;
				} else {
					cur = test;
				}
			}
			if (cur && lines.length < maxLines) lines.push(cur);
			// 溢出时末行补省略号（截短到可容纳 '…' 的宽度）
			if (overflow && lines.length) {
				const ell = '\u2026';
				let last = lines[lines.length - 1];
				while (last.length > 0 && ctx.measureText(last + ell).width > maxWidth) {
					last = last.slice(0, -1);
				}
				lines[lines.length - 1] = last + ell;
			}
			return lines;
		};
		ctx.font = 'bold 32px "PingFang SC","Microsoft YaHei",sans-serif';
		const titleLines = title ? wrapLines(title, titleW, 2) : [];
		ctx.font = '24px "PingFang SC","Microsoft YaHei",sans-serif';
		const summaryLines = summary ? wrapLines(summary, titleW, 6) : [];
		// 布局坐标：标题/摘要按实际行数自适应；摘要满 6 行时与原版 750×948 画布完全一致
		const sLH = 36;
		const infoY = titleLines.length >= 2 ? 562 : 520;
		const sumY = infoY + 60;
		const sumBottom = summaryLines.length > 0 ? sumY + (summaryLines.length - 1) * sLH + 4 : sumY - 16;
		const bottomTop = Math.max(600, sumBottom + 36);
		const tipY = bottomTop + 132;
		const qrY = bottomTop - 2;
		const logH = Math.max(750, qrY + 184 + 10);
		// 切换到 2x 渲染（重设尺寸会重置上下文，重新获取并整体缩放）
		canvas.width = 750 * PX;
		canvas.height = logH * PX;
		ctx = canvas.getContext('2d');
		ctx.scale(PX, PX);
		// 纯白背景
		ctx.fillStyle = '#ffffff';
		ctx.fillRect(0, 0, 750, logH);
		// 顶部封面图（cover 裁剪保持比例；无图时 initPoster 已注入随机封面，此处恒有图，仍兜底）
		const cover = (coverImg && coverImg.naturalWidth > 0) ? coverImg : null;
		if (cover) {
			const iw = cover.naturalWidth, ih = cover.naturalHeight;
			const tw = 750, th = 422;
			const sc = Math.max(tw / iw, th / ih);
			const sw = tw / sc, sh = th / sc;
			ctx.drawImage(cover, (iw - sw) / 2, (ih - sh) / 2, sw, sh, 0, 0, tw, th);
		} else {
			const g = ctx.createLinearGradient(0, 0, 0, 450);
			g.addColorStop(0, '#9bb8d3');
			g.addColorStop(1, '#e8f0f7');
			ctx.fillStyle = g;
			ctx.fillRect(0, 0, 750, 450);
		}
		// 文章标题位于封面图下方白底区（图片与作者+日期栏之间）：深色加粗、最多 2 行
		if (titleLines.length) {
			ctx.fillStyle = '#222222';
			ctx.font = 'bold 32px "PingFang SC","Microsoft YaHei",sans-serif';
			let ty = 468;
			for (const ln of titleLines) {
				ctx.fillText(ln, titleX, ty);
				ty += 42;
			}
		}
		// 作者（左）+ 日期（右）同行，位于标题下方（白底区深灰）
		ctx.font = '20px "PingFang SC","Microsoft YaHei",sans-serif';
		if (author) {
			// 作者名超宽截断（保留右侧日期空间）
			const maxAW = 200;
			let authorText = author;
			if (ctx.measureText(authorText).width > maxAW) {
				while (authorText.length > 0 && ctx.measureText(authorText + '\u2026').width > maxAW) {
					authorText = authorText.slice(0, -1);
				}
				authorText += '\u2026';
			}
			const avX = titleX + 15, avY = infoY - 12;
			ctx.fillStyle = '#e0e0e0';
			ctx.beginPath();
			ctx.arc(avX, avY, 12, 0, Math.PI * 2);
			ctx.fill();
			ctx.fillStyle = '#ffffff';
			ctx.beginPath();
			ctx.arc(avX, avY - 4.5, 4, 0, Math.PI * 2);
			ctx.fill();
			ctx.beginPath();
			ctx.arc(avX, avY + 3, 7, 0, Math.PI);
			ctx.fill();
			ctx.fillStyle = '#888888';
			ctx.fillText(authorText, titleX + 40, infoY);
		}
		if (dateText) ctx.fillText(dateText, titleX + titleW - ctx.measureText(dateText).width, infoY);
		// 摘要正文（白底区，最多 6 行，行数决定底部区块位置）
		if (summaryLines.length) {
			ctx.fillStyle = '#555555';
			ctx.font = '24px "PingFang SC","Microsoft YaHei",sans-serif';
			let sy = sumY;
			for (const ln of summaryLines) {
				ctx.fillText(ln, titleX, sy);
				sy += sLH;
			}
		}
		// 底部：左站点 LOGO（优先）/ 站点名 + 提示、右二维码（摘要行数自适应，紧凑排布）
		if (logoImg && logoImg.naturalWidth > 0) {
			// LOGO 等比缩放，区域高 48px（较原 60px 缩小），与提示行整体相对二维码垂直居中
			const lw = logoImg.naturalWidth, lh = logoImg.naturalHeight;
			const maxH = 48, maxW = 320;
			let dlh = maxH, dlw = lw * maxH / lh;
			if (dlw > maxW) { dlw = maxW; dlh = lh * maxW / lw; }
			const ly = bottomTop + 44 + (48 - dlh) / 2;
			ctx.drawImage(logoImg, 30, ly, dlw, dlh);
		} else {
			ctx.fillStyle = '#222222';
			ctx.font = 'bold 36px "PingFang SC","Microsoft YaHei",sans-serif';
			ctx.fillText(String(siteTitle).slice(0, 12), 30, bottomTop + 76);
		}
		const tipPrefix = '\u8bc6\u522b\u53f3\u4fa7\u4e8c\u7ef4\u7801\uff0c\u8fdb\u5165';
		const tipDomain = String(topDomain).toUpperCase();
		const tipSuffix = '\u9605\u8bfb\u5168\u6587';
		ctx.fillStyle = '#444444';
		ctx.font = '20px "PingFang SC","Microsoft YaHei",sans-serif';
		ctx.fillText(tipPrefix, 30, tipY);
		const tipPx = 30 + ctx.measureText(tipPrefix).width;
		// 域名天蓝色加粗
		ctx.fillStyle = '#38bdf8';
		ctx.font = 'bold 20px "PingFang SC","Microsoft YaHei",sans-serif';
		ctx.fillText(tipDomain, tipPx, tipY);
		const tipDx = tipPx + ctx.measureText(tipDomain).width;
		ctx.fillStyle = '#444444';
		ctx.font = '20px "PingFang SC","Microsoft YaHei",sans-serif';
		ctx.fillText(tipSuffix, tipDx, tipY);
		try {
			if (typeof QRCode !== 'undefined') {
				// 二维码内容优先取 canonical（og:url=文章永久链接，不受当前地址临时参数影响）
				const qrUrl = (document.querySelector('meta[property="og:url"]') || {}).content || url;
				const qrBox = document.createElement('div');
				// 内容 144px，白盒 184px 内四周留 20px（约 4 模块，符合 QR 静区规范，提高扫码成功率）
				new QRCode(qrBox, { text: qrUrl, width: 144, height: 144, correctLevel: QRCode.CorrectLevel.M });
				const qrCanvas = qrBox.querySelector('canvas');
				if (qrCanvas) {
					// 圆角白盒 + 浅灰描边（美化外边框，位置随底部区块自适应）
					ctx.fillStyle = '#ffffff';
					ctx.strokeStyle = '#e5e5e5';
					ctx.lineWidth = 1.5;
					if (typeof ctx.roundRect === 'function') {
						ctx.beginPath();
						ctx.roundRect(536, qrY, 184, 184, 10);
						ctx.fill();
						ctx.stroke();
					} else {
						ctx.fillRect(536, qrY, 184, 184);
						ctx.strokeRect(536, qrY, 184, 184);
					}
					ctx.drawImage(qrCanvas, 556, qrY + 20, 144, 144);
				}
			}
		} catch (err) { }
		try {
			return canvas.toDataURL('image/png');
		} catch (err) {
			// canvas 被跨域图污染，无法导出
			return null;
		}
};

// 手动初始化文章页功能（core 构造已执行全站功能；此处保持与原构造顺序一致）
const inst = window.LANTERN_INSTANCE;
if (!inst) { return; }
inst.initCodeLang && inst.initCodeLang();
inst.initReadingProgress && inst.initReadingProgress();
inst.initLikeButton && inst.initLikeButton();
inst.initLightbox && inst.initLightbox();
inst.initCodeCopy && inst.initCodeCopy();
inst.initCatalog && inst.initCatalog();
inst.initSocialPopup && inst.initSocialPopup();
inst.initPostToolbar && inst.initPostToolbar();
inst.initPostFold && inst.initPostFold();
inst.initPoster && inst.initPoster();
inst.initComment && inst.initComment();
})();
