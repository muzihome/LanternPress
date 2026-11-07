(function () {
'use strict';
var ENV = window.LT_NAV_BUILDER || {};
var SOURCES = ENV.sources || {};
var CATS = Array.isArray(SOURCES.cats) ? SOURCES.cats : [];
var PAGES = Array.isArray(SOURCES.pages) ? SOURCES.pages : [];
var MAX_DEPTH = (ENV.maxDepth | 0) || 3;
var dataInput = document.querySelector('textarea[name="navMenuData"]');
var legacyInput = document.querySelector('textarea[name="navMenu"]');
if (!dataInput || !legacyInput) {
return;
}
function optLi(input) {
return input.closest('li.typecho-option') || input.closest('li');
}
var dataLi = optLi(dataInput);
var legacyLi = optLi(legacyInput);
if (dataLi) {
dataLi.style.display = 'none';
}
if (legacyLi) {
legacyLi.style.display = 'none';
}
function el(tag, cls, text) {
var node = document.createElement(tag);
if (cls) {
node.className = cls;
}
if (text !== undefined && text !== null) {
node.textContent = text;
}
return node;
}
function op(label, title, onclick, disabled) {
var b = el('button', 'lt-nb-op', label);
b.type = 'button';
b.title = title;
if (disabled) {
b.disabled = true;
}
b.addEventListener('click', onclick);
return b;
}
function isSafeUrl(url) {
url = (url || '').trim();
if (!url) { return false; }
if (url.length > 2048) { return false; }
if (url.charAt(0) === '/' && url.charAt(1) === '/') { return false; }
var colon = url.indexOf(':');
if (colon > 0 && colon < 16) {
var scheme = url.slice(0, colon).toLowerCase();
if (/^[a-z][a-z0-9+.-]*$/.test(scheme)) {
if (scheme !== 'http' && scheme !== 'https' && scheme !== 'mailto') {
return false;
}
}
}
return true;
}
function assertSafeUrl(url, label) {
if (!isSafeUrl(url)) {
window.alert(label + ' 不合法：仅支持 http(s)://、mailto: 或站内相对路径（如 /about）；禁止 javascript:、// 协议相对等危险格式');
return false;
}
return true;
}
function parseLegacy() {
var out = [];
String(legacyInput.value || '').split(/\r?\n/).forEach(function (line) {
var idx = line.indexOf('|');
if (idx < 1) {
return;
}
var name = line.slice(0, idx).trim();
var url = line.slice(idx + 1).trim();
if (!name || !url) {
return;
}
out.push({ t: 'custom', n: name, u: url });
});
return out;
}
var tree = [];
var raw = String(dataInput.value || '').trim();
if (raw) {
try {
var parsed = JSON.parse(raw);
if (Array.isArray(parsed)) {
tree = parsed.filter(function (n) {
return n && typeof n === 'object' && (n.t === 'custom' || n.t === 'cat' || n.t === 'page');
});
}
} catch (e) {
}
}
if (!tree.length) {
tree = parseLegacy();
}
var catMap = {};
CATS.forEach(function (c) {
catMap[c.mid] = c;
});
var pageMap = {};
PAGES.forEach(function (p) {
pageMap[p.cid] = p;
});
function catLevel(cat) {
var level = 0;
var cur = cat;
var guard = 0;
while (cur && cur.parent && catMap[cur.parent] && guard++ < 10) {
cur = catMap[cur.parent];
level++;
}
return level;
}
function displayTitle(node) {
if (node.t === 'custom') {
return node.n || '(未命名)';
}
if (node.n) {
return node.n;
}
if (node.t === 'cat') {
return (catMap[node.id] && catMap[node.id].name) || '分类 #' + node.id;
}
return (pageMap[node.id] && pageMap[node.id].title) || '页面 #' + node.id;
}
function serialize(nodes) {
return nodes.map(function (node) {
var out = { t: node.t };
if (node.n) {
out.n = node.n;
}
if (node.t === 'custom') {
out.u = node.u || '';
} else {
out.id = node.id | 0;
}
if (node.c && node.c.length) {
out.c = serialize(node.c);
}
return out;
});
}
function sync() {
dataInput.value = JSON.stringify(serialize(tree));
}
function nodeAt(path) {
var nodes = tree;
var node = null;
for (var i = 0; i < path.length; i++) {
node = nodes[path[i]];
if (!node) {
return null;
}
if (i < path.length - 1) {
if (!Array.isArray(node.c)) {
node.c = [];
}
nodes = node.c;
}
}
return node;
}
function siblingsOf(path) {
if (path.length === 1) {
return tree;
}
var parent = nodeAt(path.slice(0, -1));
if (!parent) {
return tree;
}
if (!Array.isArray(parent.c)) {
parent.c = [];
}
return parent.c;
}
function assignPaths() {
(function walk(nodes, prefix) {
nodes.forEach(function (node, index) {
node.__path = prefix.concat(index);
if (node.c && node.c.length) {
walk(node.c, node.__path);
}
});
})(tree, []);
}
function move(path, delta) {
var sibs = siblingsOf(path);
var idx = path[path.length - 1];
var to = idx + delta;
if (to < 0 || to >= sibs.length) {
return;
}
var tmp = sibs[idx];
sibs[idx] = sibs[to];
sibs[to] = tmp;
renderAll();
}
function demote(path) {
var sibs = siblingsOf(path);
var idx = path[path.length - 1];
if (idx < 1) {
return;
}
var node = sibs.splice(idx, 1)[0];
var prev = sibs[idx - 1];
if (!Array.isArray(prev.c)) {
prev.c = [];
}
prev.c.push(node);
renderAll();
}
function promote(path) {
if (path.length < 2) {
return;
}
var sibs = siblingsOf(path);
var idx = path[path.length - 1];
var node = sibs.splice(idx, 1)[0];
var parentPath = path.slice(0, -1);
var parentSibs = siblingsOf(parentPath);
parentSibs.splice(parentPath[parentPath.length - 1] + 1, 0, node);
renderAll();
}
function renameNode(path) {
var node = nodeAt(path);
if (!node) {
return;
}
var input = window.prompt('显示名称（留空恢复系统名称）', node.n || '');
if (input === null) {
return;
}
node.n = input.trim();
renderAll();
}
function editCustom(path) {
var node = nodeAt(path);
if (!node) {
return;
}
var name = window.prompt('菜单名称', node.n || '');
if (name === null) {
return;
}
name = name.trim();
if (!name) {
window.alert('名称不能为空');
return;
}
var url = window.prompt('链接地址（支持相对路径或完整 URL）', node.u || '');
if (url === null) {
return;
}
url = url.trim();
if (!url) {
window.alert('链接不能为空');
return;
}
if (!assertSafeUrl(url, '链接')) {
return;
}
node.n = name;
node.u = url;
renderAll();
}
function removeNode(path) {
var node = nodeAt(path);
if (!node) {
return;
}
var kids = node.c && node.c.length ? '（连同 ' + node.c.length + ' 个子级）' : '';
if (!window.confirm('确定删除「' + displayTitle(node) + '」' + kids + '？')) {
return;
}
var sibs = siblingsOf(path);
sibs.splice(path[path.length - 1], 1);
renderAll();
}
function addNode(node) {
tree.push(node);
renderAll();
}
var treeBox = el('div', 'lt-nb-tree');
function renderNode(node) {
var path = node.__path;
var depth = path.length - 1;
var sibs = siblingsOf(path);
var idx = path[path.length - 1];
var row = el('div', 'lt-nb-node');
row.style.paddingLeft = depth * 18 + 6 + 'px';
row.appendChild(el('span', 'lt-nb-badge', node.t === 'custom' ? '链接' : (node.t === 'cat' ? '分类' : '页面')));
row.appendChild(el('span', 'lt-nb-name', displayTitle(node)));
if (node.t !== 'custom' && !node.n) {
row.appendChild(el('span', 'lt-nb-auto', '系统名称'));
}
row.appendChild(op('↑', '上移', function () {
move(path, -1);
}, idx === 0));
row.appendChild(op('↓', '下移', function () {
move(path, 1);
}, idx === sibs.length - 1));
row.appendChild(op('→', '降为上一项的子级', function () {
demote(path);
}, idx < 1 || depth + 1 >= MAX_DEPTH));
row.appendChild(op('←', '升一级', function () {
promote(path);
}, depth < 1 || idx !== sibs.length - 1));
if (node.t === 'custom') {
row.appendChild(op('编辑', '编辑名称与链接', function () {
editCustom(path);
}));
} else {
row.appendChild(op('改名', '覆盖显示名（留空恢复系统名称）', function () {
renameNode(path);
}));
}
row.appendChild(op('删除', '删除（连同子级）', function () {
removeNode(path);
}));
return row;
}
function renderAll() {
assignPaths();
treeBox.textContent = '';
if (!tree.length) {
treeBox.appendChild(el('p', 'lt-nb-empty', '菜单为空。可从上方添加菜单项；保持为空时前台将自动显示所有独立页面。'));
} else {
renderNodes(tree);
}
sync();
}
function renderNodes(nodes) {
nodes.forEach(function (node) {
treeBox.appendChild(renderNode(node));
if (node.c && node.c.length) {
renderNodes(node.c);
}
});
}
var tabsBar = el('div', 'lt-nb-tabs');
var srcWrap = el('div', 'lt-nb-src-wrap');
var panels = [];
function makePanel(key, label, build) {
var tab = el('button', 'lt-nb-tab' + (panels.length === 0 ? ' lt-on' : ''), label);
tab.type = 'button';
var panel = el('div', 'lt-nb-src-panel' + (panels.length === 0 ? ' lt-on' : ''));
panel.style.display = panels.length === 0 ? '' : 'none';
build(panel);
tab.addEventListener('click', function () {
panels.forEach(function (pair) {
pair.tab.classList.toggle('lt-on', pair.tab === tab);
pair.panel.style.display = pair.panel === panel ? '' : 'none';
});
});
tabsBar.appendChild(tab);
srcWrap.appendChild(panel);
panels.push({ tab: tab, panel: panel });
}
if (CATS.length) {
makePanel('cat', '分类目录', function (panel) {
var box = el('div', 'lt-nb-src');
CATS.forEach(function (cat) {
var row = el('div', 'lt-nb-src-item');
row.style.paddingLeft = catLevel(cat) * 16 + 6 + 'px';
row.appendChild(el('span', null, cat.name));
row.appendChild(op('添加', '添加到菜单', function () {
addNode({ t: 'cat', id: cat.mid | 0 });
}));
box.appendChild(row);
});
panel.appendChild(box);
});
}
if (PAGES.length) {
makePanel('page', '独立页面', function (panel) {
var box = el('div', 'lt-nb-src');
PAGES.forEach(function (page) {
var row = el('div', 'lt-nb-src-item');
row.appendChild(el('span', null, page.title));
row.appendChild(op('添加', '添加到菜单', function () {
addNode({ t: 'page', id: page.cid | 0 });
}));
box.appendChild(row);
});
panel.appendChild(box);
});
}
makePanel('custom', '自定义链接', function (panel) {
var box = el('div', 'lt-nb-src');
var nameInput = el('input');
nameInput.type = 'text';
nameInput.placeholder = '名称（如：首页）';
nameInput.maxLength = 60;
var urlInput = el('input');
urlInput.type = 'text';
urlInput.placeholder = '链接（如：/ 或 https://example.com）';
urlInput.maxLength = 500;
var addBtn = op('添加', '添加自定义链接', function () {
var name = nameInput.value.trim();
var url = urlInput.value.trim();
if (!name || !url) {
window.alert('请填写名称和链接');
return;
}
if (!assertSafeUrl(url, '链接')) {
return;
}
addNode({ t: 'custom', n: name, u: url });
nameInput.value = '';
urlInput.value = '';
});
var row = el('div', 'lt-nb-src-item');
row.appendChild(nameInput);
row.appendChild(urlInput);
row.appendChild(addBtn);
box.appendChild(row);
var quick = el('div', 'lt-nb-src-item');
quick.appendChild(el('span', 'lt-nb-auto', '快捷项：'));
quick.appendChild(op('首页', '添加指向站点首页的菜单项', function () {
addNode({ t: 'custom', n: '首页', u: '/' });
}));
box.appendChild(quick);
panel.appendChild(box);
});
var panelRoot = el('div', 'lt-nb');
panelRoot.appendChild(el('p', 'lt-nb-title', '导航菜单构建器'));
panelRoot.appendChild(el('p', 'lt-nb-desc', '从分类、独立页面中添加菜单项，或添加自定义链接（最多 ' + MAX_DEPTH + ' 级）。用 ↑↓ 调整顺序，→← 调整层级；改动实时保存，点击下方「保存设置」生效。'));
panelRoot.appendChild(tabsBar);
panelRoot.appendChild(srcWrap);
panelRoot.appendChild(el('p', 'lt-nb-subtitle', '当前菜单结构'));
panelRoot.appendChild(treeBox);
if (legacyLi) {
legacyLi.parentNode.insertBefore(panelRoot, legacyLi.nextSibling);
}
renderAll();
})();
