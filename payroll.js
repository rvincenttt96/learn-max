(function () {
  var TAB_TEACHERS = 'teachers';
  var TAB_STAFF = 'staff';
  var tab = TAB_TEACHERS;
  var terms = [];
  var termId = '';
  var stats = null;
  var staff = [];
  var teachers = [];
  var loading = false;
  var submitting = false;
  var submittingRow = null;
  var staffFormOpen = false;
  var editingStaff = null;
  var notice = { type: '', text: '' };
  var searchTimer = 0;
  var lastPayroll = false;
  var misses = 0;
  var origFetch = window.fetch;
  var root = null;
  var infoIdx = null;
  var infoClosing = false;
  var infoTimer = 0;
  var rateTimer = 0;
  var rateDirty = {};
  var teacherReq = 0;
  var teacherTerm = '';
  var payFetchDone = false;

  var DIGIT_MAP = {
    '۰': '0', '۱': '1', '۲': '2', '۳': '3', '۴': '4',
    '۵': '5', '۶': '6', '۷': '7', '۸': '8', '۹': '9',
    '٠': '0', '١': '1', '٢': '2', '٣': '3', '٤': '4',
    '٥': '5', '٦': '6', '٧': '7', '٨': '8', '٩': '9'
  };

  function esc(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function digits(raw) {
    var s = String(raw == null ? '' : raw).replace(/[۰-۹٠-٩]/g, function (ch) {
      return DIGIT_MAP[ch] || ch;
    }).replace(/[^\d-]/g, '');
    var n = parseInt(s, 10);
    return isNaN(n) ? 0 : n;
  }

  function money(n) {
    return (Number(n) || 0).toLocaleString('en-US');
  }

  function api(url, opt) {
    opt = opt || {};
    opt.credentials = 'same-origin';
    opt.headers = Object.assign({ Accept: 'application/json' }, opt.headers || {});
    return origFetch(url, opt).then(function (res) {
      return res.json().then(function (json) {
        if (!res.ok) {
          throw new Error((json && (json.error || json.message)) || ('HTTP ' + res.status));
        }
        return json;
      });
    });
  }

  function loggedIn() {
    try {
      var raw = sessionStorage.getItem('ghazal-user');
      if (!raw) return null;
      var user = JSON.parse(raw);
      return user && user.role === 'manager' ? user : null;
    } catch (err) {
      return null;
    }
  }

  function payrollNavActive() {
    var buttons = document.querySelectorAll('#root aside button, #root nav button');
    for (var i = 0; i < buttons.length; i++) {
      var label = (buttons[i].textContent || '').replace(/\s+/g, ' ').trim();
      if (!/(^|\s)Payroll(\s|$)/.test(label)) continue;
      var cls = buttons[i].className || '';
      if (cls.indexOf('from-red-600') !== -1 || cls.indexOf('to-rose-700') !== -1) {
        return true;
      }
    }
    return false;
  }

  function headerSearch() {
    var input = document.querySelector('#root header input[type="text"]');
    return input ? String(input.value || '').trim() : '';
  }

  function savedTermId() {
    try {
      return sessionStorage.getItem('gz-acc-term') || localStorage.getItem('gz-acc-term') || '';
    } catch (e) {
      return '';
    }
  }

  function persistTermId(id) {
    if (!id) return;
    try {
      sessionStorage.setItem('gz-acc-term', String(id));
      localStorage.setItem('gz-acc-term', String(id));
    } catch (e) {}
  }

  function pinTermChoice() {
    try { sessionStorage.setItem('gz-acc-term-pinned', '1'); } catch (e) {}
  }

  function termPinned() {
    try { return sessionStorage.getItem('gz-acc-term-pinned') === '1'; } catch (e) { return false; }
  }

  function pickTermId(list) {
    if (!list || !list.length) return '';
    var saved = savedTermId();
    var teach = list.find(function (t) { return t.isTeaching; });
    var savedRow = list.find(function (t) { return String(t.id) === String(saved); });
    if (!termPinned() && teach && savedRow && String(savedRow.status || '') === 'completed') {
      persistTermId(String(teach.id));
      return String(teach.id);
    }
    if (saved && list.some(function (t) { return String(t.id) === String(saved); })) {
      return String(saved);
    }
    if (teach) return String(teach.id);
    var reg = list.find(function (t) { return t.isRegistration; });
    if (reg) return String(reg.id);
    return String(list[0].id);
  }

  function currentTerm() {
    return terms.find(function (t) { return String(t.id) === String(termId); }) || null;
  }

  function amountOf(row) {
    return (Number(row.offlineSessions) || 0) * digits(row.offlineRate)
      + (Number(row.onlineSessions) || 0) * digits(row.onlineRate)
      + (Number(row.privateSessions) || 0) * digits(row.privateRate);
  }

  function filteredTeachers() {
    var q = headerSearch().toLowerCase();
    if (!q) return teachers;
    return teachers.filter(function (t) {
      return String(t.teacherName || '').toLowerCase().indexOf(q) !== -1;
    });
  }

  function filteredStaff() {
    var q = headerSearch().toLowerCase();
    var rows = staff.filter(function (s) {
      if (termId && String(s.termId) !== String(termId)) return false;
      return (s.kind || 'staff') !== 'teacher';
    });
    if (!q) return rows;
    return rows.filter(function (s) {
      return [s.teacherName, s.role, s.month, s.status].join(' ').toLowerCase().indexOf(q) !== -1;
    });
  }

  function setNotice(type, text) {
    notice = { type: type || '', text: text || '' };
    render();
  }

  function payHost() {
    return document.querySelector('#root main')
      || (document.querySelector('#root header') && document.querySelector('#root header').parentNode)
      || document.getElementById('root');
  }

  function parkRoot() {
    if (!root) return;
    var host = payHost();
    var target = host || document.body;
    if (root.parentNode !== target) {
      target.appendChild(root);
    }
    root.classList.add('gz-pay--page');
    root.style.position = 'relative';
    root.style.minHeight = '70vh';
    root.style.background = '#08080c';
    root.style.color = '#f8fafc';
  }

  function ensureRoot() {
    if (root && document.documentElement.contains(root)) {
      parkRoot();
      return root;
    }
    root = document.getElementById('gz-pay');
    if (!root) {
      root = document.createElement('div');
      root.id = 'gz-pay';
      root.hidden = true;
    }
    parkRoot();
    if (!root._bound) {
      root._bound = true;
      root.addEventListener('click', onClick);
      root.addEventListener('input', onInput);
      root.addEventListener('change', onChange);
      root.addEventListener('blur', onRateBlur, true);
      root.addEventListener('submit', onSubmit);
    }
    return root;
  }

  function show(on) {
    var el = ensureRoot();
    var wasHidden = el.hidden;
    el.hidden = !on;
    if (on) {
      el.removeAttribute('hidden');
      el.style.setProperty('display', 'flex', 'important');
    } else {
      el.style.setProperty('display', 'none', 'important');
    }
    parkRoot();
    document.documentElement.classList.toggle('gz-pay-open', !!on);
    if (!on) {
      infoIdx = null;
      infoClosing = false;
      window.clearTimeout(infoTimer);
      dropInfoNode();
      document.documentElement.classList.remove('gz-pay-info-open');
    }
    if (on && wasHidden) {
      if (!teachers.length) loading = true;
      render();
    }
  }

  function onClick(e) {
    var t = e.target.closest('[data-pay]');
    if (!t) return;
    var act = t.getAttribute('data-pay');
    if (act === 'tab') {
      tab = t.getAttribute('data-tab') === TAB_STAFF ? TAB_STAFF : TAB_TEACHERS;
      staffFormOpen = false;
      editingStaff = null;
      render();
      if (tab === TAB_TEACHERS) loadTeachers();
      else loadStaff();
      return;
    }
    if (act === 'calc') {
      submitTeachers();
      return;
    }
    if (act === 'calc-one') {
      submitTeachers(Number(t.getAttribute('data-i')));
      return;
    }
    if (act === 'reload') {
      if (tab === TAB_TEACHERS) {
        flushRateSaves().then(function () { loadTeachers(true); });
      } else loadStaff();
      return;
    }
    if (act === 'staff-new') {
      editingStaff = null;
      staffFormOpen = true;
      render();
      return;
    }
    if (act === 'staff-cancel') {
      editingStaff = null;
      staffFormOpen = false;
      render();
      return;
    }
    if (act === 'staff-edit') {
      var id = t.getAttribute('data-id');
      editingStaff = staff.find(function (s) { return String(s.id) === String(id); }) || null;
      staffFormOpen = true;
      render();
      return;
    }
    if (act === 'staff-del') {
      removeStaff(t.getAttribute('data-id'));
      return;
    }
    if (act === 'mark') {
      markSalary(t.getAttribute('data-id'), t.getAttribute('data-status'));
      return;
    }
    if (act === 'del-teacher') {
      removeStaff(t.getAttribute('data-id'), true);
      return;
    }
    if (act === 'info-keep') {
      e.stopPropagation();
      return;
    }
    if (act === 'info') {
      e.preventDefault();
      openInfo(Number(t.getAttribute('data-i')));
      return;
    }
    if (act === 'info-close') {
      e.preventDefault();
      closeInfo();
    }
  }

  function ratePayload(row) {
    return {
      teacherName: row.teacherName,
      teacherId: row.teacherId || 0,
      offlineRate: digits(row.offlineRate),
      onlineRate: digits(row.onlineRate),
      privateRate: digits(row.privateRate)
    };
  }

  function flushRateSaves() {
    window.clearTimeout(rateTimer);
    var idxs = Object.keys(rateDirty);
    rateDirty = {};
    if (!idxs.length) return Promise.resolve();
    var rows = [];
    idxs.forEach(function (idx) {
      var row = teachers[Number(idx)];
      if (row && String(row.teacherName || '').trim()) rows.push(ratePayload(row));
    });
    if (!rows.length) return Promise.resolve();
    return api('/api/teacher_rates', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      keepalive: true,
      body: JSON.stringify({ rates: rows })
    }).catch(function (err) {
      setNotice('err', err.message || 'نرخ ذخیره نشد');
    });
  }

  function queueRateSave(idx) {
    if (idx == null || idx < 0 || !teachers[idx]) return;
    rateDirty[idx] = true;
    window.clearTimeout(rateTimer);
    rateTimer = window.setTimeout(function () { flushRateSaves(); }, 250);
  }

  function onInput(e) {
    var input = e.target;
    if (!input.classList || !input.classList.contains('gz-pay-rate')) return;
    var idx = Number(input.getAttribute('data-i'));
    var field = input.getAttribute('data-f');
    if (!teachers[idx] || !field) return;
    teachers[idx][field] = digits(input.value);
    var amount = money(amountOf(teachers[idx]));
    var cells = root.querySelectorAll('[data-amt="' + idx + '"]');
    for (var c = 0; c < cells.length; c++) cells[c].textContent = amount;
    var total = root.querySelector('[data-pay-total]');
    if (total) total.textContent = money(teachers.reduce(function (sum, row) { return sum + amountOf(row); }, 0));
    queueRateSave(idx);
  }

  function onRateBlur(e) {
    var input = e.target;
    if (!input.classList || !input.classList.contains('gz-pay-rate')) return;
    var idx = Number(input.getAttribute('data-i'));
    if (!teachers[idx]) return;
    teachers[idx][input.getAttribute('data-f')] = digits(input.value);
    rateDirty[idx] = true;
    flushRateSaves();
  }

  function onChange(e) {
    if (e.target && e.target.classList && e.target.classList.contains('gz-pay-rate')) {
      onRateBlur(e);
      return;
    }
    if (e.target && e.target.id === 'gz-pay-term') {
      termId = e.target.value;
      pinTermChoice();
      persistTermId(termId);
      staffFormOpen = false;
      editingStaff = null;
      if (tab === TAB_TEACHERS) loadTeachers(true);
      else loadStaff();
    }
  }

  function onSubmit(e) {
    var form = e.target;
    if (!form || form.id !== 'gz-pay-staff-form') return;
    e.preventDefault();
    saveStaff(form);
  }

  function loadTermsThen(cb) {
    if (terms.length) {
      if (!termId) termId = pickTermId(terms);
      cb && cb();
      return;
    }
    api('/api/terms').then(function (data) {
      terms = Array.isArray(data) ? data : [];
      if (!termId) termId = pickTermId(terms);
      if (!termId) {
        payFetchDone = true;
        loading = false;
      }
      cb && cb();
      if (!termId) render();
    }).catch(function (err) {
      payFetchDone = true;
      loading = false;
      setNotice('err', err.message || 'ترم‌ها نیامد');
    });
  }

  function loadTeachers(force) {
    if (!termId) {
      stats = null;
      teachers = [];
      loading = false;
      payFetchDone = true;
      render();
      return;
    }
    if (!force && stats && String(stats.termId) === String(termId) && teachers.length) {
      loading = false;
      payFetchDone = true;
      render();
      return;
    }
    if (!force && loading && teacherTerm === String(termId)) return;
    var req = ++teacherReq;
    teacherTerm = String(termId);
    loading = true;
    render();
    api('/api/teacher_stats?termId=' + encodeURIComponent(termId)).then(function (data) {
      if (req !== teacherReq) return;
      stats = data || {};
      teachers = (stats.teachers || []).map(function (t) {
        return Object.assign({}, t, {
          offlineRate: Number(t.offlineRate) || 0,
          onlineRate: Number(t.onlineRate) || 0,
          privateRate: Number(t.privateRate) || 0
        });
      });
      loading = false;
      payFetchDone = true;
      notice = { type: '', text: '' };
      render();
    }).catch(function (err) {
      if (req !== teacherReq) return;
      stats = { ok: false, error: err.message };
      teachers = [];
      loading = false;
      payFetchDone = true;
      setNotice('err', err.message || 'آمار اساتید نیامد');
    });
  }

  function loadStaff() {
    api('/api/salaries').then(function (data) {
      staff = Array.isArray(data) ? data : [];
      render();
    }).catch(function (err) {
      setNotice('err', err.message || 'فیش‌های کادر نیامد');
    });
  }

  function submitTeachers(onlyIdx) {
    if (submitting || !termId) return;
    var source = teachers;
    if (typeof onlyIdx === 'number') {
      if (!teachers[onlyIdx]) return;
      source = [teachers[onlyIdx]];
    }
    source.forEach(function (t, i) {
      var idx = typeof onlyIdx === 'number' ? onlyIdx : teachers.indexOf(t);
      if (idx < 0) idx = i;
      rateDirty[idx] = true;
    });
    var allRows = source.map(ratePayload);
    var rows = allRows.filter(function (t) {
      return t.offlineRate > 0 || t.onlineRate > 0 || t.privateRate > 0;
    });
    if (!rows.length) {
      flushRateSaves().then(function () {
        setNotice('ok', 'نرخ صفر ذخیره شد.');
        loadTeachers(true);
      });
      return;
    }
    submitting = true;
    submittingRow = typeof onlyIdx === 'number' ? onlyIdx : -1;
    render();
    var term = currentTerm();
    var month = ((stats && stats.termTitle) || (term && term.name) || '') + ' ' + ((stats && stats.termKind) || '');
    flushRateSaves().then(function () {
    return api('/api/teacher_payroll', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ termId: termId, month: month.trim(), teachers: allRows })
    });
    }).then(function (data) {
      submitting = false;
      submittingRow = null;
      var parts = [];
      if (data.created) parts.push(data.created + ' فیش جدید');
      if (data.updated) parts.push(data.updated + ' فیش به‌روز');
      if (data.skippedPaid && data.skippedPaid.length) {
        parts.push(data.skippedPaid.length + ' فیش پرداخت‌شده دست نخورد');
      }
      var prefix = typeof onlyIdx === 'number'
        ? ('فیش «' + (source[0] && source[0].teacherName ? source[0].teacherName : '') + '» ثبت شد')
        : 'حقوق اساتید محاسبه شد';
      setNotice('ok', parts.length ? (prefix + ': ' + parts.join('، ') + '.') : (prefix + '.'));
      loadTeachers(true);
      loadStaff();
    }).catch(function (err) {
      submitting = false;
      submittingRow = null;
      setNotice('err', err.message || 'محاسبه انجام نشد');
    });
  }

  function saveStaff(form) {
    if (submitting) return;
    var payload = {
      teacherName: form.n.value.trim(),
      role: form.r.value.trim() || 'کادر',
      amount: digits(form.a.value),
      month: form.m.value.trim(),
      status: form.s.value || 'unpaid',
      termId: termId,
      kind: 'staff'
    };
    if (!payload.teacherName || !payload.amount) {
      setNotice('err', 'نام و مبلغ فیش کادر لازم است.');
      return;
    }
    submitting = true;
    render();
    var req = editingStaff
      ? api('/api/salaries/' + encodeURIComponent(editingStaff.id), {
          method: 'PATCH',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(payload)
        })
      : api('/api/salaries', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(payload)
        });
    req.then(function () {
      submitting = false;
      staffFormOpen = false;
      editingStaff = null;
      setNotice('ok', 'فیش کادر ذخیره شد.');
      loadStaff();
    }).catch(function (err) {
      submitting = false;
      setNotice('err', err.message || 'ذخیره فیش کادر انجام نشد');
    });
  }

  function removeStaff(id, isTeacher) {
    if (!id) return;
    if (!window.confirm(isTeacher ? 'این فیش استاد حذف شود؟' : 'این فیش کادر حذف شود؟')) return;
    api('/api/salaries/' + encodeURIComponent(id), { method: 'DELETE' }).then(function () {
      setNotice('ok', 'فیش حذف شد.');
      if (isTeacher) loadTeachers(true);
      loadStaff();
    }).catch(function (err) {
      setNotice('err', err.message || 'حذف انجام نشد');
    });
  }

  function markSalary(id, status) {
    if (!id) return;
    api('/api/salaries/' + encodeURIComponent(id), {
      method: 'PATCH',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ status: status })
    }).then(function () {
      if (tab === TAB_TEACHERS) loadTeachers(true);
      loadStaff();
    }).catch(function (err) {
      setNotice('err', err.message || 'وضعیت عوض نشد');
    });
  }

  function termOptionsHtml() {
    return terms.map(function (t) {
      var selected = String(t.id) === String(termId) ? ' selected' : '';
      return '<option value="' + esc(t.id) + '"' + selected + '>' + esc(t.name || '') + '</option>';
    }).join('');
  }

  function bannerHtml() {
    var term = currentTerm();
    var title = (stats && stats.termTitle) || (term && term.name) || (loading ? 'در حال بارگذاری...' : 'ترم');
    var kind = (stats && stats.termKind) || (term && term.isRegistration ? 'ثبت‌نام' : (term && term.isTeaching ? 'در حال تدریس' : ''));
    var range = (stats && stats.termRange) || '';
    return (
      '<div class="gz-pay-banner">' +
        '<span class="gz-pay-chip">TeacherStats</span>' +
        '<div class="gz-pay-banner__meta">' +
          '<span class="gz-pay-title">' + esc(title) + '</span>' +
          (kind || range
            ? '<span class="gz-pay-range">' + esc((kind ? kind + (range ? ' — ' : '') : '') + range) + '</span>'
            : '') +
        '</div>' +
      '</div>'
    );
  }

  function statusChip(status) {
    if (status === 'paid') return '<span class="gz-pay-st gz-pay-st--paid">پرداخت شده</span>';
    if (status === 'unpaid') return '<span class="gz-pay-st gz-pay-st--due">در انتظار پرداخت</span>';
    return '<span class="gz-pay-st">محاسبه نشده</span>';
  }

  function rateInputHtml(t, idx, field, paid) {
    var n = digits(t[field]);
    return '<input class="gz-pay-rate" data-i="' + idx + '" data-f="' + field + '" inputmode="numeric" value="' +
      esc(n ? String(n) : '') + '"' + (paid ? ' disabled' : '') + '>';
  }

  function teacherLevels(t) {
    var levels = Array.isArray(t && t.levels) ? t.levels.slice() : [];
    if (levels.length) return levels;
    var seen = {};
    (Array.isArray(t && t.classes) ? t.classes : []).forEach(function (c) {
      var k = String((c && (c.level || c.title)) || '').trim();
      if (k && !seen[k]) {
        seen[k] = true;
        levels.push(k);
      }
    });
    return levels;
  }

  function teacherClasses(t) {
    return Array.isArray(t && t.classes) ? t.classes : [];
  }

  function infoNode() {
    return (root && root.querySelector('.gz-pay-info')) || document.querySelector('body > .gz-pay-info');
  }

  function dropInfoNode() {
    var leftover = document.querySelector('body > .gz-pay-info');
    if (leftover && leftover.parentNode) leftover.parentNode.removeChild(leftover);
  }

  function parkInfo() {
    var ov = root && root.querySelector('.gz-pay-info');
    if (!ov) ov = document.querySelector('body > .gz-pay-info');
    if (!ov) return;
    if (ov.parentNode !== document.body) {
      document.body.appendChild(ov);
    }
    if (!ov._bound) {
      ov._bound = true;
      ov.addEventListener('click', onClick);
    }
  }

  function revealInfo() {
    parkInfo();
    var ov = infoNode();
    if (!ov) return;
    ov.setAttribute('data-open', '1');
    document.documentElement.classList.add('gz-pay-info-open');
    var closeBtn = ov.querySelector('.gz-pay-info__x');
    if (closeBtn && closeBtn.focus) closeBtn.focus();
  }

  function openInfo(idx) {
    if (!teachers[idx]) return;
    var already = infoIdx != null && !infoClosing;
    window.clearTimeout(infoTimer);
    infoClosing = false;
    infoIdx = idx;
    render();
    if (already) {
      revealInfo();
      return;
    }
    window.requestAnimationFrame(function () {
      window.requestAnimationFrame(revealInfo);
    });
  }

  function closeInfo() {
    if (infoIdx == null || infoClosing) return;
    infoClosing = true;
    var ov = infoNode();
    if (ov) ov.removeAttribute('data-open');
    document.documentElement.classList.remove('gz-pay-info-open');
    window.clearTimeout(infoTimer);
    infoTimer = window.setTimeout(function () {
      infoIdx = null;
      infoClosing = false;
      render();
    }, 250);
  }

  function infoOverlayHtml() {
    if (infoIdx == null || !teachers[infoIdx]) return '';
    var t = teachers[infoIdx];
    var levels = teacherLevels(t);
    var classes = teacherClasses(t);
    var chips = levels.length
      ? levels.map(function (lv) {
          return '<span class="gz-pay-info__chip">' + esc(lv) + '</span>';
        }).join('')
      : '<p class="gz-pay-info__empty">برای این استاد در این ترم لولی ثبت نشده.</p>';
    var rows = classes.length
      ? '<ul class="gz-pay-info__classes">' + classes.map(function (c) {
          var title = String((c && c.title) || '').trim() || 'بدون عنوان';
          var bits = [];
          if (c && c.level) bits.push(c.level);
          if (c && c.typeLabel) bits.push(c.typeLabel);
          if (c && c.schedule) bits.push(c.schedule);
          return '<li><strong>' + esc(title) + '</strong>' +
            (bits.length ? '<span>' + esc(bits.join(' · ')) + '</span>' : '') +
            '</li>';
        }).join('') + '</ul>'
      : '';
    return (
      '<div class="gz-pay-info" data-pay="info-close">' +
        '<div class="gz-pay-info__dim"></div>' +
        '<div class="gz-pay-info__panel" data-pay="info-keep" role="dialog" aria-modal="true" aria-labelledby="gz-pay-info-title">' +
          '<button type="button" class="gz-pay-info__x" data-pay="info-close" aria-label="بستن">بستن</button>' +
          '<p class="gz-pay-info__kicker">لول‌های تدریس‌شده</p>' +
          '<h3 id="gz-pay-info-title">' + esc(t.teacherName || '') + '</h3>' +
          '<div class="gz-pay-info__chips">' + chips + '</div>' +
          (rows ? '<p class="gz-pay-info__sub">کلاس‌های همین ترم</p>' + rows : '') +
        '</div>' +
      '</div>'
    );
  }

  function infoBtnHtml(idx) {
    return '<button type="button" class="gz-pay-info-btn" data-pay="info" data-i="' + idx + '">Info</button>';
  }

  function submitOneHtml(idx, paid, rowBusy) {
    if (paid) return '';
    return '<button type="button" class="gz-pay-btn gz-pay-btn--sm" data-pay="calc-one" data-i="' + idx + '"' +
      (rowBusy || loading ? ' disabled' : '') + '>' +
      (rowBusy && submittingRow === idx ? 'در حال ثبت...' : 'ثبت فیش') +
    '</button>';
  }

  function salaryMiniHtml(t, paid) {
    if (!t.salaryId) return '';
    return '<div class="gz-pay-mini">' +
      (paid
        ? '<button type="button" data-pay="mark" data-id="' + esc(t.salaryId) + '" data-status="unpaid">برگشت به انتظار</button>'
        : '<button type="button" data-pay="mark" data-id="' + esc(t.salaryId) + '" data-status="paid">پرداخت شد</button>') +
      '<button type="button" data-pay="del-teacher" data-id="' + esc(t.salaryId) + '">حذف فیش</button>' +
    '</div>';
  }

  function teacherRowsHtml() {
    var rows = filteredTeachers();
    if (!rows.length) {
      return '<div class="gz-pay-empty">برای این ترم کلاسی در پنل ثبت نشده، یا جستجو نتیجه‌ای نداشت.</div>';
    }
    var table = (
      '<div class="gz-pay-table-wrap"><table class="gz-pay-table">' +
        '<thead><tr>' +
          '<th>نام مدرس</th>' +
          '<th>کل کلاس</th>' +
          '<th>حضوری</th>' +
          '<th>آنلاین</th>' +
          '<th>خصوصی</th>' +
          '<th>جلسات حضوری ×۱۳</th>' +
          '<th>جلسات آنلاین ×۱۳</th>' +
          '<th>جلسات خصوصی ×۱۰</th>' +
          '<th>پیمنت حضوری</th>' +
          '<th>پیمنت آنلاین</th>' +
          '<th>پیمنت خصوصی</th>' +
          '<th>حقوق</th>' +
          '<th>وضعیت</th>' +
        '</tr></thead><tbody>' +
        rows.map(function (t, i) {
          var idx = teachers.indexOf(t);
          if (idx < 0) idx = i;
          var paid = t.salaryStatus === 'paid';
          var rowBusy = submitting && (submittingRow === idx || submittingRow === -1);
          return (
            '<tr class="' + (paid ? 'is-paid' : '') + '">' +
              '<td class="gz-pay-name"><div class="gz-pay-name__row">' +
                '<span>' + esc(t.teacherName) + '</span>' +
                '<div class="gz-pay-name__acts">' +
                  infoBtnHtml(idx) +
                  submitOneHtml(idx, paid, rowBusy) +
                '</div>' +
              '</div></td>' +
              '<td>' + esc(t.totalClasses) + '</td>' +
              '<td>' + esc(t.offlineClasses) + '</td>' +
              '<td>' + esc(t.onlineClasses) + '</td>' +
              '<td>' + esc(t.privateClasses) + '</td>' +
              '<td>' + esc(t.offlineSessions) + '</td>' +
              '<td>' + esc(t.onlineSessions) + '</td>' +
              '<td>' + esc(t.privateSessions) + '</td>' +
              '<td>' + rateInputHtml(t, idx, 'offlineRate', paid) + '</td>' +
              '<td>' + rateInputHtml(t, idx, 'onlineRate', paid) + '</td>' +
              '<td>' + rateInputHtml(t, idx, 'privateRate', paid) + '</td>' +
              '<td class="gz-pay-amt" data-amt="' + idx + '">' + money(amountOf(t)) + '</td>' +
              '<td>' + statusChip(t.salaryStatus) + salaryMiniHtml(t, paid) + '</td>' +
            '</tr>'
          );
        }).join('') +
      '</tbody></table></div>'
    );
    var cards = '<div class="gz-pay-teacher-cards">' + rows.map(function (t, i) {
      var idx = teachers.indexOf(t);
      if (idx < 0) idx = i;
      var paid = t.salaryStatus === 'paid';
      var rowBusy = submitting && (submittingRow === idx || submittingRow === -1);
      return (
        '<article class="gz-pay-tcard' + (paid ? ' is-paid' : '') + '">' +
          '<div class="gz-pay-tcard__top">' +
            '<strong>' + esc(t.teacherName) + '</strong>' +
            '<div class="gz-pay-tcard__top-end">' +
              statusChip(t.salaryStatus) +
              infoBtnHtml(idx) +
            '</div>' +
          '</div>' +
          '<div class="gz-pay-tcard__stats">' +
            '<span>کل کلاس <b>' + esc(t.totalClasses) + '</b></span>' +
            '<span>حضوری <b>' + esc(t.offlineClasses) + '</b></span>' +
            '<span>آنلاین <b>' + esc(t.onlineClasses) + '</b></span>' +
            '<span>خصوصی <b>' + esc(t.privateClasses) + '</b></span>' +
            '<span>جلسه حضوری <b>' + esc(t.offlineSessions) + '</b></span>' +
            '<span>جلسه آنلاین <b>' + esc(t.onlineSessions) + '</b></span>' +
            '<span>جلسه خصوصی <b>' + esc(t.privateSessions) + '</b></span>' +
          '</div>' +
          '<div class="gz-pay-tcard__rates">' +
            '<label>پیمنت حضوری' + rateInputHtml(t, idx, 'offlineRate', paid) + '</label>' +
            '<label>پیمنت آنلاین' + rateInputHtml(t, idx, 'onlineRate', paid) + '</label>' +
            '<label>پیمنت خصوصی' + rateInputHtml(t, idx, 'privateRate', paid) + '</label>' +
          '</div>' +
          '<div class="gz-pay-tcard__pay">' +
            '<span class="gz-pay-amt" data-amt="' + idx + '">' + money(amountOf(t)) + ' <small>تومان</small></span>' +
            submitOneHtml(idx, paid, rowBusy) +
          '</div>' +
          salaryMiniHtml(t, paid) +
        '</article>'
      );
    }).join('') + '</div>';
    return table + cards;
  }

  function teacherPane() {
    var total = teachers.reduce(function (sum, row) { return sum + amountOf(row); }, 0);
    return (
      '<div class="gz-pay-pane">' +
        bannerHtml() +
        '<p class="gz-pay-help">آمار از کلاس‌های پنل آموزشی می‌آید: حذف کلاس (تکی، از کلاس‌بندی، یا با حذف ترم) جلسات را کم می‌کند؛ آرشیو تکی کلاس یا بایگانی کل ترم از حقوق کم نمی‌شود. نرخ هر استاد را وارد کنید و همان سطر را ثبت کنید.</p>' +
        '<div class="gz-pay-scroll">' +
          (loading ? '<div class="gz-pay-empty">در حال خواندن آمار اساتید از پنل آموزشی...</div>' : teacherRowsHtml()) +
        '</div>' +
        '<div class="gz-pay-foot">' +
          '<div>جمع حقوق محاسبه‌شده: <strong data-pay-total>' + money(total) + '</strong> تومان</div>' +
          '<div class="gz-pay-actions">' +
            '<button type="button" class="gz-pay-btn gz-pay-btn--ghost" data-pay="reload">تازه‌سازی آمار</button>' +
            '<button type="button" class="gz-pay-btn gz-pay-btn--ghost" data-pay="calc"' + (submitting || loading ? ' disabled' : '') + '>' +
              (submitting && submittingRow === -1 ? 'در حال ثبت همه...' : 'ثبت همه فیش‌ها') +
            '</button>' +
          '</div>' +
        '</div>' +
      '</div>'
    );
  }

  function staffFormHtml() {
    if (!staffFormOpen) return '';
    var s = editingStaff || {};
    return (
      '<form id="gz-pay-staff-form" class="gz-pay-form">' +
        '<h3>' + (editingStaff ? 'ویرایش فیش کادر' : 'فیش جدید کادر') + '</h3>' +
        '<div class="gz-pay-grid">' +
          '<label>نام و نام خانوادگی<input name="n" required value="' + esc(s.teacherName || '') + '"></label>' +
          '<label>سمت<input name="r" value="' + esc(s.role || 'کادر') + '"></label>' +
          '<label>مبلغ (تومان)<input name="a" inputmode="numeric" required value="' + esc(s.amount || '') + '"></label>' +
          '<label>توضیح / ماه<input name="m" value="' + esc(s.month || '') + '"></label>' +
          '<label>وضعیت<select name="s">' +
            '<option value="unpaid"' + (s.status !== 'paid' ? ' selected' : '') + '>در انتظار پرداخت</option>' +
            '<option value="paid"' + (s.status === 'paid' ? ' selected' : '') + '>پرداخت شده</option>' +
          '</select></label>' +
        '</div>' +
        '<div class="gz-pay-actions">' +
          '<button type="button" class="gz-pay-btn gz-pay-btn--ghost" data-pay="staff-cancel">انصراف</button>' +
          '<button type="submit" class="gz-pay-btn"' + (submitting ? ' disabled' : '') + '>' + (submitting ? 'در حال ذخیره...' : 'ذخیره فیش کادر') + '</button>' +
        '</div>' +
      '</form>'
    );
  }

  function staffPane() {
    var rows = filteredStaff();
    var total = rows.reduce(function (sum, s) { return sum + (Number(s.amount) || 0); }, 0);
    return (
      '<div class="gz-pay-pane">' +
        '<div class="gz-pay-staff-head">' +
          '<div>' +
            '<h3>حقوق کادر</h3>' +
            '<p>فیش دستی پذیرش، حسابداری و بقیهٔ پرسنل غیر از اساتید.</p>' +
          '</div>' +
          '<button type="button" class="gz-pay-btn" data-pay="staff-new">فیش جدید کادر</button>' +
        '</div>' +
        staffFormHtml() +
        '<div class="gz-pay-scroll">' +
        (rows.length
          ? '<div class="gz-pay-cards">' + rows.map(function (s) {
              return (
                '<article class="gz-pay-card">' +
                  '<div>' +
                    '<strong>' + esc(s.teacherName) + '</strong>' +
                    '<p>' + esc(s.role || 'کادر') + (s.month ? ' • ' + esc(s.month) : '') + '</p>' +
                  '</div>' +
                  '<div class="gz-pay-card__end">' +
                    '<b>' + money(s.amount) + ' <small>تومان</small></b>' +
                    statusChip(s.status) +
                    '<div class="gz-pay-mini">' +
                      '<button type="button" data-pay="staff-edit" data-id="' + esc(s.id) + '">ویرایش</button>' +
                      (s.status === 'paid'
                        ? '<button type="button" data-pay="mark" data-id="' + esc(s.id) + '" data-status="unpaid">برگشت به انتظار</button>'
                        : '<button type="button" data-pay="mark" data-id="' + esc(s.id) + '" data-status="paid">پرداخت شد</button>') +
                      '<button type="button" data-pay="staff-del" data-id="' + esc(s.id) + '">حذف</button>' +
                    '</div>' +
                  '</div>' +
                '</article>'
              );
            }).join('') + '</div>'
          : '<div class="gz-pay-empty">برای این ترم هنوز فیش کادری صادر نشده.</div>') +
        '</div>' +
        '<div class="gz-pay-foot"><div>جمع کادر این ترم: <strong>' + money(total) + '</strong> تومان</div></div>' +
      '</div>'
    );
  }

  function render() {
    var el = ensureRoot();
    if (el.hidden) return;
    dropInfoNode();
    el.innerHTML =
      '<div class="gz-pay__inner">' +
        '<div class="gz-pay__head">' +
          '<div>' +
            '<h2>حقوق و دستمزد</h2>' +
            '<p>اساتید از آمار کلاس‌های پنل؛ کادر با فیش دستی.</p>' +
          '</div>' +
          '<div class="gz-pay__tools">' +
            '<label class="gz-pay-term">' +
              '<span>ترم</span>' +
              '<select id="gz-pay-term">' + termOptionsHtml() + '</select>' +
            '</label>' +
            '<div class="gz-pay-tabs" role="tablist">' +
              '<button type="button" class="' + (tab === TAB_TEACHERS ? 'is-on' : '') + '" data-pay="tab" data-tab="teachers">حقوق اساتید</button>' +
              '<button type="button" class="' + (tab === TAB_STAFF ? 'is-on' : '') + '" data-pay="tab" data-tab="staff">حقوق کادر</button>' +
            '</div>' +
          '</div>' +
        '</div>' +
        (notice.text ? '<div class="gz-pay-msg gz-pay-msg--' + esc(notice.type) + '">' + esc(notice.text) + '</div>' : '') +
        (tab === TAB_TEACHERS ? teacherPane() : staffPane()) +
        infoOverlayHtml() +
      '</div>';
    parkInfo();
    el.setAttribute('data-ready', (payFetchDone && !loading) ? '1' : '0');
    if (infoIdx != null && !infoClosing) {
      window.requestAnimationFrame(function () {
        window.requestAnimationFrame(revealInfo);
      });
    }
  }

  function payrollHashActive() {
    try {
      var raw = String(location.hash || '').replace(/^#\/?/, '').split('?')[0].toLowerCase();
      return raw === 'payroll';
    } catch (e) {
      return false;
    }
  }

  function persistPayroll() {
    try {
      return (sessionStorage.getItem('gz-acc-section') || localStorage.getItem('gz-acc-section')) === 'payroll';
    } catch (e) {
      return false;
    }
  }

  function wantPayroll() {
    return payrollHashActive() || persistPayroll();
  }

  function prefetchPayroll() {
    if (!isManager() || !wantPayroll()) return;
    if (!teachers.length) loading = true;
    loadTermsThen(function () {
      var saved = savedTermId();
      if (saved && terms.some(function (t) { return String(t.id) === String(saved); })) {
        termId = String(saved);
      } else if (!termId) {
        termId = pickTermId(terms);
      }
      loadTeachers(false);
      if (!staff.length) loadStaff();
    });
  }

  function reactPayrollVisible() {
    var main = document.querySelector('#root main');
    var text = (main && main.textContent) || '';
    return /حقوق و دستمزد/.test(text) && /New payslip|فیش‌های قانونی/.test(text);
  }

  function isManager() {
    if (loggedIn()) return true;
    var aside = document.querySelector('#root aside');
    var text = (aside && aside.textContent) || '';
    return /دسترسی:\s*مدیر/.test(text);
  }

  function navReady() {
    return document.querySelectorAll('#root aside button').length > 0;
  }

  function sync() {
    if (document.documentElement.classList.contains('gz-books-open')) {
      if (lastPayroll) {
        lastPayroll = false;
        misses = 0;
        show(false);
      }
      return;
    }
    var on = reactPayrollVisible()
      || ((payrollHashActive() || payrollNavActive() || persistPayroll() || (!navReady() && persistPayroll())) && isManager());
    if (document.documentElement.classList.contains('gz-booting') && isManager() && wantPayroll()) {
      on = true;
    }
    if (on) {
      misses = 0;
      if (!lastPayroll) {
        lastPayroll = true;
        if (!teachers.length) loading = true;
        show(true);
        loadTermsThen(function () {
          var saved = savedTermId();
          if (saved && terms.some(function (t) { return String(t.id) === String(saved); })) {
            termId = String(saved);
          } else if (!termId) {
            termId = pickTermId(terms);
          }
          loadTeachers(false);
          if (!staff.length) loadStaff();
        });
      } else {
        parkRoot();
      }
      return;
    }
    if (lastPayroll) {
      if (navReady() && !payrollNavActive() && !payrollHashActive() && !reactPayrollVisible()) {
        lastPayroll = false;
        misses = 0;
        show(false);
        return;
      }
      misses += 1;
      if (misses < 2) return;
      lastPayroll = false;
      show(false);
    }
  }

  function ready(fn) {
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', fn);
    else fn();
  }

  ready(function () {
    ensureRoot();
    prefetchPayroll();
    sync();
    setInterval(sync, 1200);
    window.addEventListener('hashchange', sync);
    window.addEventListener('gz-app-ready', function () {
      if (!lastPayroll) return;
      parkRoot();
      render();
    });
    window.addEventListener('pagehide', function () { flushRateSaves(); });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && infoIdx != null) {
        e.preventDefault();
        closeInfo();
      }
    });
    document.addEventListener('input', function (e) {
      if (!e.target || e.target.closest('#root header') == null) return;
      window.clearTimeout(searchTimer);
      searchTimer = window.setTimeout(function () {
        if (lastPayroll) render();
      }, 120);
    });
  });
})();
