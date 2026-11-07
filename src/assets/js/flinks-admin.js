/* ============================================================
 * flinks-admin.js — 友链管理后台界面（P2-2 + 批次A/B）
 * 渲染安全：所有用户数据一律 textContent / createElement，杜绝 innerHTML 注入；
 * B1（批次B）：列表状态筛选 / 关键词搜索 / 分页 + 多选批量审核、批量删除；
 * B2（批次B）：审核/删除/批量操作本地增量更新（即时反馈）+ 静默 refresh 校正；
 * B3（批次B）：分类字段（表单编辑 + 列表展示；前台 flinks-template 按此分组渲染）。
 * 操作：list 渲染 + 审核/排序/编辑/删除 + 新增保存 + 迁移/清理（confirm）
 * 依赖：管理页 HTML 提供 meta[name=lt-csrf]
 * ============================================================ */
(function () {
  'use strict';

  var csrfMeta = document.querySelector('meta[name="lt-csrf"]');
  if (!csrfMeta) { return; }
  var csrf = csrfMeta.getAttribute('content');
  var api = (typeof LT_FLINKS_ADMIN_API !== 'undefined' && LT_FLINKS_ADMIN_API)
    ? LT_FLINKS_ADMIN_API
    : window.location.href;

  // ---- 最小工具 ----
  function el(tag, cls, text) {
    var n = document.createElement(tag);
    if (cls) { n.className = cls; }
    if (text !== undefined && text !== null) { n.textContent = text; }
    return n;
  }
  function toast(msg, isError) {
    var box = document.getElementById('lt-toast');
    if (!box) { return; }
    var item = el('div', 'lt-toast-item', msg);
    if (isError) { item.style.background = '#c0392b'; }
    box.appendChild(item);
    setTimeout(function () {
      if (item.parentNode) { item.parentNode.removeChild(item); }
    }, 3600);
  }
  function post(action, data, onOk, onErr) {
    var body = '_=' + encodeURIComponent(csrf) + '&action=' + encodeURIComponent(action);
    for (var k in data) {
      if (Object.prototype.hasOwnProperty.call(data, k)) {
        body += '&' + encodeURIComponent(k) + '=' + encodeURIComponent(String(data[k] == null ? '' : data[k]));
      }
    }
    var xhr = new XMLHttpRequest();
    xhr.open('POST', api, true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded; charset=UTF-8');
    xhr.onreadystatechange = function () {
      if (xhr.readyState !== 4) { return; }
      var res = null;
      try { res = JSON.parse(xhr.responseText); } catch (e) { /* ignore */ }
      if (!res || !res.ok) {
        var msg = (res && res.error) ? res.error : ('请求失败（HTTP ' + xhr.status + '）');
        if (onErr) { onErr(msg, res); } else { toast(msg, true); }
        return;
      }
      if (onOk) { onOk(res.data); }
    };
    xhr.onerror = function () { if (onErr) { onErr('网络错误', null); } else { toast('网络错误', true); } };
    xhr.send(body);
  }

  // ---- 状态/来源标识 ----
  function badge(status) {
    var map = { 1: ['lt-badge-1', '已通过'], 0: ['lt-badge-0', '待审核'], 2: ['lt-badge-2', '已拒绝'] };
    var m = map[status] || ['lt-badge-2', '未知'];
    return el('span', 'lt-badge ' + m[0], m[1]);
  }

  // ---- 全局状态（B1：筛选/分页参数；B2：本地选中集合）----
  var state = {
    items: [],
    stats: { total: 0, approved: 0, pending: 0, rejected: 0 },
    pages: [],
    filter: { status: '', q: '', page: 1, pageSize: 50 },
    total: 0,
    pageCount: 1,
    selected: {}
  };
  var editingId = 0;

  function renderStats() {
    var s = state.stats;
    var set = function (id, v) {
      var n = document.getElementById(id);
      if (n) { n.textContent = String(v); }
    };
    set('lt-n-total', s.total);
    set('lt-n-approved', s.approved);
    set('lt-n-pending', s.pending);
    set('lt-n-rejected', s.rejected);
  }

  function renderPages() {
    var sel = document.getElementById('lt-page');
    if (!sel) { return; }
    while (sel.firstChild) { sel.removeChild(sel.firstChild); }
    var detected = null;
    state.pages.forEach(function (p, i) {
      var opt = el('option', null, p.title + (p.template ? '（' + p.template + '）' : ''));
      opt.value = String(p.cid);
      sel.appendChild(opt);
      if (p.isFlinks && detected === null) { detected = i; }
    });
    if (state.pages.length === 0) {
      var opt = el('option', null, '未找到独立页面');
      opt.value = '0';
      sel.appendChild(opt);
      sel.disabled = true;
      return;
    }
    // 自动检测：默认选中 template 含 flinks-template 的页面
    if (detected !== null) { sel.selectedIndex = detected; }
    else if (state.pages[0]) { sel.selectedIndex = 0; }
  }

  function currentCid() {
    var sel = document.getElementById('lt-page');
    return sel ? (parseInt(sel.value, 10) || 0) : 0;
  }

  // ---- B1：列表工具栏（状态筛选 / 搜索 / 批量操作）----
  function renderToolbar() {
    var box = document.getElementById('lt-toolbar');
    if (!box) { return; }
    while (box.firstChild) { box.removeChild(box.firstChild); }
    // 状态筛选
    var stSel = el('select');
    stSel.title = '按状态筛选';
    [['', '全部状态'], ['0', '待审核'], ['1', '已通过'], ['2', '已拒绝']].forEach(function (pair) {
      var opt = el('option', null, pair[1]);
      opt.value = pair[0];
      if (state.filter.status === pair[0]) { opt.selected = true; }
      stSel.appendChild(opt);
    });
    stSel.addEventListener('change', function () {
      state.filter.status = stSel.value;
      state.filter.page = 1;
      refresh();
    });
    box.appendChild(stSel);
    // 关键词搜索
    var qInput = el('input');
    qInput.type = 'text';
    qInput.placeholder = '搜索名称 / 网址 / 邮箱…';
    qInput.maxLength = 100;
    qInput.value = state.filter.q;
    box.appendChild(qInput);
    var qBtn = el('button', null, '搜索');
    qBtn.type = 'button';
    qBtn.addEventListener('click', function () {
      state.filter.q = qInput.value.trim();
      state.filter.page = 1;
      refresh();
    });
    box.appendChild(qBtn);
    qInput.addEventListener('keydown', function (e) {
      if (e.key === 'Enter') {
        state.filter.q = qInput.value.trim();
        state.filter.page = 1;
        refresh();
      }
    });
    // 批量操作
    var batch = el('span', 'lt-batch');
    var selCount = Object.keys(state.selected).length;
    batch.appendChild(el('span', null, '已选 ' + selCount + ' 项'));
    var bApprove = el('button', null, '批量通过');
    bApprove.type = 'button';
    bApprove.className = 'lt-btn-primary';
    bApprove.disabled = selCount === 0;
    bApprove.addEventListener('click', function () { doBatch('batchStatus', 1); });
    batch.appendChild(bApprove);
    var bReject = el('button', null, '批量拒绝');
    bReject.type = 'button';
    bReject.disabled = selCount === 0;
    bReject.addEventListener('click', function () { doBatch('batchStatus', 2); });
    batch.appendChild(bReject);
    var bDel = el('button', null, '批量删除');
    bDel.type = 'button';
    bDel.className = 'lt-btn-danger';
    bDel.disabled = selCount === 0;
    bDel.addEventListener('click', function () { doBatch('batchDelete', 0); });
    batch.appendChild(bDel);
    box.appendChild(batch);
  }

  // ---- B1：分页条 ----
  function renderPager() {
    var box = document.getElementById('lt-pager');
    if (!box) { return; }
    while (box.firstChild) { box.removeChild(box.firstChild); }
    if (state.total === 0) { return; }
    var prev = el('button', null, '上一页');
    prev.type = 'button';
    prev.disabled = state.filter.page <= 1;
    prev.addEventListener('click', function () {
      if (state.filter.page > 1) { state.filter.page--; refresh(); }
    });
    box.appendChild(prev);
    box.appendChild(el('span', null, '第 ' + state.filter.page + ' / ' + state.pageCount + ' 页 · 共 ' + state.total + ' 条'));
    var next = el('button', null, '下一页');
    next.type = 'button';
    next.disabled = state.filter.page >= state.pageCount;
    next.addEventListener('click', function () {
      if (state.filter.page < state.pageCount) { state.filter.page++; refresh(); }
    });
    box.appendChild(next);
  }

  function renderList() {
    var box = document.getElementById('lt-list');
    if (!box) { return; }
    while (box.firstChild) { box.removeChild(box.firstChild); }
    if (state.items.length === 0) {
      box.appendChild(el('div', 'lt-empty', '暂无匹配友链。可在下方表单新增，或使用「迁移旧评论」导入评论表旧申请。'));
      renderToolbar();
      renderPager();
      return;
    }
    var table = el('table');
    var thead = el('thead');
    var hr = el('tr');
    // 全选列（当前页）
    var thSel = el('th');
    var checkAll = el('input');
    checkAll.type = 'checkbox';
    checkAll.title = '全选本页';
    checkAll.checked = state.items.length > 0 && state.items.every(function (it) { return !!state.selected[it.id]; });
    checkAll.addEventListener('change', function () {
      state.items.forEach(function (it) {
        if (checkAll.checked) { state.selected[it.id] = true; } else { delete state.selected[it.id]; }
      });
      box.querySelectorAll('input[data-row]').forEach(function (cb) {
        cb.checked = checkAll.checked;
      });
      renderToolbar();
    });
    thSel.appendChild(checkAll);
    hr.appendChild(thSel);
    ['排序', '名称', '分类', '网址', '状态', '操作'].forEach(function (h) {
      hr.appendChild(el('th', null, h));
    });
    thead.appendChild(hr);
    table.appendChild(thead);
    var tbody = el('tbody');
    state.items.forEach(function (it, i) {
      var tr = el('tr');
      // 选择列
      var tdSel = el('td');
      var cb = el('input');
      cb.type = 'checkbox';
      cb.setAttribute('data-row', String(it.id));
      cb.checked = !!state.selected[it.id];
      cb.addEventListener('change', function () {
        if (cb.checked) { state.selected[it.id] = true; } else { delete state.selected[it.id]; }
        renderToolbar();
      });
      tdSel.appendChild(cb);
      tr.appendChild(tdSel);
      // 排序
      var tdSort = el('td');
      var up = el('button', null, '↑');
      up.type = 'button';
      up.title = '上移';
      up.disabled = (i === 0);
      up.addEventListener('click', function () { doMove(it.id, 'up', up); });
      var down = el('button', null, '↓');
      down.type = 'button';
      down.title = '下移';
      down.disabled = (i === state.items.length - 1);
      down.addEventListener('click', function () { doMove(it.id, 'down', down); });
      tdSort.appendChild(up);
      tdSort.appendChild(down);
      tr.appendChild(tdSort);
      // 名称（LOGO 首字徽标预览）
      var tdName = el('td');
      var mark = el('span', null, (it.name && it.name.trim()) ? it.name.trim().charAt(0) : '?');
      mark.style.cssText = 'display:inline-block;width:22px;height:22px;line-height:22px;text-align:center;border-radius:50%;background:#a65b4a;color:#fff;font-size:12px;margin-right:8px;vertical-align:middle;';
      tdName.appendChild(mark);
      tdName.appendChild(document.createTextNode(it.name));
      tr.appendChild(tdName);
      // 分类（B3）
      var tdCat = el('td');
      tdCat.appendChild(el('span', 'lt-cat', (it.category && it.category.trim()) ? it.category : '默认'));
      tr.appendChild(tdCat);
      // 网址
      var tdUrl = el('td');
      var a = el('a', null, it.url);
      a.href = it.url;
      a.target = '_blank';
      a.rel = 'noopener noreferrer';
      a.style.color = '#a65b4a';
      tdUrl.appendChild(a);
      tr.appendChild(tdUrl);
      // 状态
      var tdStatus = el('td');
      tdStatus.appendChild(badge(it.status));
      tr.appendChild(tdStatus);
      // 操作
      var tdOps = el('td', 'lt-ops');
      var btnApprove = el('button', null, '通过');
      btnApprove.type = 'button';
      if (it.status !== 1) {
        btnApprove.addEventListener('click', function () { doStatus(it.id, 1, btnApprove); });
      } else { btnApprove.disabled = true; }
      var btnReject = el('button', null, '拒绝');
      btnReject.type = 'button';
      if (it.status !== 2) {
        btnReject.addEventListener('click', function () { doStatus(it.id, 2, btnReject); });
      } else { btnReject.disabled = true; }
      var btnEdit = el('button', null, '编辑');
      btnEdit.type = 'button';
      btnEdit.addEventListener('click', function () { startEdit(it); });
      var btnDel = el('button', null, '删除');
      btnDel.type = 'button';
      btnDel.className = 'lt-btn-danger';
      btnDel.addEventListener('click', function () { doDelete(it.id, btnDel); });
      tdOps.appendChild(btnApprove);
      tdOps.appendChild(btnReject);
      tdOps.appendChild(btnEdit);
      tdOps.appendChild(btnDel);
      tr.appendChild(tdOps);
      tbody.appendChild(tr);
    });
    table.appendChild(tbody);
    box.appendChild(table);
    renderToolbar();
    renderPager();
  }

  // ---- 拉取列表（B1：携带筛选/搜索/分页参数）----
  function refresh(onDone) {
    post('list', {
      status: state.filter.status,
      q: state.filter.q,
      page: state.filter.page,
      pageSize: state.filter.pageSize
    }, function (data) {
      state.items = data.items || [];
      state.stats = data.stats || state.stats;
      state.pages = data.pages || [];
      state.total = data.total || 0;
      state.pageCount = data.pageCount || 1;
      state.filter.page = data.page || 1;
      // 清理不在当前页的选中项（翻页/筛选后全选语义只对当前页有效）
      var curIds = {};
      state.items.forEach(function (it) { curIds[it.id] = true; });
      for (var k in state.selected) {
        if (!Object.prototype.hasOwnProperty.call(state.selected, k)) { continue; }
        if (!curIds[k]) { delete state.selected[k]; }
      }
      renderStats();
      renderPages();
      renderList();
      if (onDone) { onDone(); }
    });
  }

  // ---- B2：本地增量（审核/删除/批量即时更新 + 静默 refresh 校正边界）----
  function applyLocalStats(stats) {
    if (stats) { state.stats = stats; renderStats(); }
  }

  function doStatus(id, status, btn) {
    btn.disabled = true;
    post('setStatus', { id: id, status: status }, function (data) {
      state.items.forEach(function (it) {
        if (it.id === id) { it.status = status; }
      });
      applyLocalStats(data.stats);
      renderList();
      toast('状态已更新');
      refresh();
    }, function (msg) { btn.disabled = false; toast(msg, true); });
  }

  function doMove(id, dir, btn) {
    btn.disabled = true;
    post('move', { id: id, dir: dir }, function (data) {
      applyLocalStats(data.stats);
      toast('排序已更新');
      refresh(); // 排序影响全量顺序，直接静默刷新（本地增量不适用于跨页排序语义）
    }, function (msg) { btn.disabled = false; toast(msg, true); });
  }

  function doDelete(id, btn) {
    if (!window.confirm('确定删除这条友链吗？此操作不可恢复。')) { return; }
    btn.disabled = true;
    post('delete', { id: id }, function (data) {
      state.items = state.items.filter(function (it) { return it.id !== id; });
      delete state.selected[id];
      applyLocalStats(data.stats);
      renderList();
      toast('已删除');
      refresh();
    }, function (msg) { btn.disabled = false; toast(msg, true); });
  }

  function doBatch(action, statusOrZero) {
    var ids = Object.keys(state.selected).map(Number).filter(function (id) { return id > 0; });
    if (ids.length === 0) { return; }
    if (action === 'batchDelete' && !window.confirm('确定删除选中的 ' + ids.length + ' 条友链吗？此操作不可恢复。')) { return; }
    var idSet = {};
    ids.forEach(function (id) { idSet[id] = true; });
    var payload = action === 'batchDelete' ? { ids: ids } : { ids: ids, status: statusOrZero };
    post(action, payload, function (data) {
      if (action === 'batchDelete') {
        state.items = state.items.filter(function (it) { return !idSet[it.id]; });
        ids.forEach(function (id) { delete state.selected[id]; });
        toast('已批量删除 ' + (data.deleted || ids.length) + ' 条');
      } else {
        state.items.forEach(function (it) {
          if (idSet[it.id]) { it.status = statusOrZero; }
        });
        toast('已批量' + (statusOrZero === 1 ? '通过' : '拒绝') + ' ' + (data.changed || ids.length) + ' 条');
      }
      applyLocalStats(data.stats);
      renderList();
      refresh();
    }, function (msg) { toast(msg, true); });
  }

  // ---- 编辑 / 新增（B3：分类字段）----
  function startEdit(it) {
    editingId = it.id;
    var title = document.getElementById('lt-form-title');
    if (title) { title.textContent = '编辑友链（#' + it.id + '）'; }
    document.getElementById('f-id').value = String(it.id);
    document.getElementById('f-name').value = it.name;
    document.getElementById('f-url').value = it.url;
    document.getElementById('f-logo').value = it.logo || '';
    document.getElementById('f-mail').value = it.mail || '';
    document.getElementById('f-desc').value = it.desc || '';
    document.getElementById('f-category').value = it.category || '';
    document.getElementById('f-status').value = String(it.status);
    document.getElementById('lt-form').scrollIntoView({ behavior: 'smooth', block: 'start' });
  }

  function resetForm() {
    editingId = 0;
    var title = document.getElementById('lt-form-title');
    if (title) { title.textContent = '新增友链'; }
    ['f-id', 'f-name', 'f-url', 'f-logo', 'f-mail', 'f-desc', 'f-category'].forEach(function (id) {
      var n = document.getElementById(id);
      if (n) { n.value = ''; }
    });
    var st = document.getElementById('f-status');
    if (st) { st.value = '1'; }
  }

  function doSave(ev) {
    ev.preventDefault();
    var btn = document.getElementById('lt-save-btn');
    btn.disabled = true;
    var data = {
      id: editingId,
      name: document.getElementById('f-name').value,
      url: document.getElementById('f-url').value,
      logo: document.getElementById('f-logo').value,
      mail: document.getElementById('f-mail').value,
      desc: document.getElementById('f-desc').value,
      category: document.getElementById('f-category').value,
      status: document.getElementById('f-status').value
    };
    post('save', data, function () {
      // 保存后回到「全部」第 1 页，确保新条目立即可见
      state.filter.status = '';
      state.filter.q = '';
      state.filter.page = 1;
      refresh(function () {
        resetForm();
        btn.disabled = false;
        toast('已保存');
      });
    }, function (msg) { btn.disabled = false; toast(msg, true); });
  }

  function doMigrate(btn) {
    if (!window.confirm('将选中的友链页评论表旧申请（已通过）迁移到 flinks 数据源？已有数据时自动跳过。')) { return; }
    btn.disabled = true;
    post('migrate', { pageCid: currentCid() }, function (data) {
      btn.disabled = false;
      if (data.migrated > 0) {
        toast('迁移完成：' + data.migrated + ' 条');
      } else if (data.skipped > 0) {
        toast(data.reason || '已跳过迁移（数据已存在）');
      } else {
        toast(data.reason || '没有可迁移的旧申请');
      }
      refresh();
    }, function (msg) { btn.disabled = false; toast(msg, true); });
  }

  function doCleanup(btn) {
    btn.disabled = true;
    post('cleanup', { pageCid: currentCid() }, function (data) {
      btn.disabled = false;
      toast('清理完成：删除 ' + data.deleted + ' 条申请占位评论');
      refresh();
    }, function (msg) { btn.disabled = false; toast(msg, true); });
  }

  // ---- 绑定 ----
  function bind() {
    var form = document.getElementById('lt-form');
    if (form) { form.addEventListener('submit', doSave); }
    var resetBtn = document.getElementById('lt-reset-btn');
    if (resetBtn) { resetBtn.addEventListener('click', resetForm); }
    var migrateBtn = document.getElementById('lt-migrate-btn');
    if (migrateBtn) { migrateBtn.addEventListener('click', function () { doMigrate(migrateBtn); }); }
    var cleanupToggle = document.getElementById('lt-cleanup-toggle');
    var dangerZone = document.getElementById('lt-danger-zone');
    if (cleanupToggle && dangerZone) {
      cleanupToggle.addEventListener('click', function () {
        dangerZone.style.display = dangerZone.style.display === 'block' ? 'none' : 'block';
      });
    }
    var cleanupBtn = document.getElementById('lt-cleanup-btn');
    if (cleanupBtn) { cleanupBtn.addEventListener('click', function () { doCleanup(cleanupBtn); }); }
  }

  bind();
  refresh();
})();
