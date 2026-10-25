(function () {
  var SECTION_KEY = 'gz-acc-section';
  var TERM_KEY = 'gz-acc-term';
  var SECTION_LABELS = {
    overview: 'Overview',
    registration: 'Registration',
    bookstore: 'Bookstore',
    payroll: 'Payroll',
    expenses: 'Expenses'
  };
  var LABEL_SECTIONS = {
    Overview: 'overview',
    Registration: 'registration',
    Bookstore: 'bookstore',
    Payroll: 'payroll',
    Expenses: 'expenses'
  };

  var origFetch = window.fetch;
  var terms = [];
  var termsById = {};
  var students = [];
  var studentsByCode = {};
  var studentsByName = {};
  var lastSection = '';
  var restoringTerm = false;
  var restoringSection = false;
  var lastSectionRestore = 0;
  var lastTermRestore = 0;
  var termAttempts = 0;
  var lastPrintStudent = null;
  var writingHash = false;

  function digits(raw) {
    var map = { '۰': '0', '۱': '1', '۲': '2', '۳': '3', '۴': '4', '۵': '5', '۶': '6', '۷': '7', '۸': '8', '۹': '9', '٠': '0', '١': '1', '٢': '2', '٣': '3', '٤': '4', '٥': '5', '٦': '6', '٧': '7', '٨': '8', '٩': '9' };
    var s = String(raw == null ? '' : raw).replace(/[۰-۹٠-٩]/g, function (ch) { return map[ch] || ch; }).replace(/[^\d-]/g, '');
    var n = parseInt(s, 10);
    return isNaN(n) ? 0 : n;
  }

  function money(n) {
    return (Number(n) || 0).toLocaleString('en-US');
  }

  function esc(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function storeGet(key) {
    try {
      return sessionStorage.getItem(key) || localStorage.getItem(key) || '';
    } catch (e) {
      return '';
    }
  }

  function storeSet(key, value) {
    if (!value) return;
    try {
      sessionStorage.setItem(key, value);
      localStorage.setItem(key, value);
    } catch (e) {}
  }

  function signedIn() {
    return !!document.querySelector('#root aside, #root header');
  }

  function parseHash() {
    var raw = String(location.hash || '').replace(/^#\/?/, '');
    if (!raw) return { section: '', term: '' };
    var parts = raw.split('?');
    var section = String(parts[0] || '').toLowerCase();
    if (!SECTION_LABELS[section]) section = '';
    var term = '';
    if (parts[1]) {
      try {
        term = new URLSearchParams(parts[1]).get('term') || '';
      } catch (e) {}
    }
    return { section: section, term: term };
  }

  function writeHash(section, term) {
    var next = '#/' + (section || 'overview');
    if (term) next += '?term=' + encodeURIComponent(term);
    if (location.hash === next) return;
    writingHash = true;
    try {
      history.replaceState(null, '', next);
    } catch (e) {
      location.hash = next;
    }
    window.setTimeout(function () { writingHash = false; }, 0);
  }

  function desiredSection() {
    var hash = parseHash();
    return hash.section || storeGet(SECTION_KEY) || '';
  }

  function desiredTerm() {
    var hash = parseHash();
    return hash.term || storeGet(TERM_KEY) || '';
  }

  function saveSection(id) {
    if (!id || !SECTION_LABELS[id]) return;
    storeSet(SECTION_KEY, id);
    writeHash(id, desiredTerm() || storeGet(TERM_KEY));
  }

  function saveTerm(id) {
    if (!id) return;
    storeSet(TERM_KEY, String(id));
    writeHash(desiredSection() || storeGet(SECTION_KEY) || 'overview', String(id));
  }

  function headingText() {
    var h = document.querySelector('#root main h2');
    return h ? (h.textContent || '').replace(/\s+/g, ' ').trim() : '';
  }

  function currentSection() {
    var buttons = document.querySelectorAll('#root aside button');
    for (var i = 0; i < buttons.length; i++) {
      var label = (buttons[i].textContent || '').replace(/\s+/g, ' ').trim();
      var sec = LABEL_SECTIONS[label];
      if (!sec) continue;
      var cls = buttons[i].className || '';
      if (cls.indexOf('from-red-600') !== -1 || cls.indexOf('to-rose-700') !== -1) return sec;
    }
    if (document.documentElement.classList.contains('gz-books-open')) return 'bookstore';
    if (document.documentElement.classList.contains('gz-pay-open')) return 'payroll';
    var h = headingText();
    if (h.indexOf('مدیریت ثبت‌نام') !== -1) return 'registration';
    if (h.indexOf('داشبورد') !== -1) return 'overview';
    if (h.indexOf('مدیریت مخارج') !== -1) return 'expenses';
    if (h.indexOf('حقوق') !== -1) return 'payroll';
    if (h.indexOf('Bookstore') !== -1) return 'bookstore';
    var books = document.getElementById('gz-books');
    if (books && !books.hidden) return 'bookstore';
    var pay = document.getElementById('gz-pay');
    if (pay && !pay.hidden) return 'payroll';
    return lastSection || '';
  }

  function rememberTerms(list) {
    if (!Array.isArray(list)) return;
    terms = list.map(function (t) {
      var copy = {};
      Object.keys(t || {}).forEach(function (k) { copy[k] = t[k]; });
      copy.id = String((t && (t._id || t.id)) || '');
      return copy;
    });
    termsById = {};
    terms.forEach(function (t) { termsById[t.id] = t; });
  }

  function normCode(c) {
    return String(c == null ? '' : c).replace(/[^\d]/g, '');
  }

  function rememberStudents(list) {
    if (!Array.isArray(list)) return;
    students = list;
    studentsByCode = {};
    studentsByName = {};
    list.forEach(function (s) {
      var raw = String(s.studentCode || '').trim();
      var code = normCode(raw);
      if (raw && raw !== '—') {
        var prevRaw = studentsByCode[raw];
        if (!prevRaw || (!prevRaw.hasExtraCourse && s.hasExtraCourse)) studentsByCode[raw] = s;
      }
      if (code) {
        var prev = studentsByCode[code];
        if (!prev || (!prev.hasExtraCourse && s.hasExtraCourse)) studentsByCode[code] = s;
      }
      var name = ((s.firstName || '') + ' ' + (s.lastName || '')).replace(/\s+/g, ' ').trim();
      if (name) studentsByName[name] = s;
    });
  }

  function findNavButton(section) {
    var want = SECTION_LABELS[section];
    if (!want) return null;
    var buttons = document.querySelectorAll('#root aside button');
    for (var i = 0; i < buttons.length; i++) {
      var label = (buttons[i].textContent || '').replace(/\s+/g, ' ').trim();
      if (label === want) return buttons[i];
    }
    return null;
  }

  function restoreSection() {
    if (!document.querySelector('#root aside button')) return;
    var saved = desiredSection();
    if (!saved) return;
    var now = currentSection();
    if (now === saved) return;
    if (!now && lastSection === saved) return;
    var btn = findNavButton(saved);
    if (!btn) return;
    var t = Date.now();
    if (t - lastSectionRestore < 450) return;
    lastSectionRestore = t;
    restoringSection = true;
    btn.click();
    window.setTimeout(function () { restoringSection = false; }, 120);
  }

  function termName(id) {
    return (termsById[String(id)] || {}).name || '';
  }

  function termMenuOpen() {
    if (document.querySelector('.gz-term-wrap.open')) return true;
    var menus = document.querySelectorAll('#root main [class*="absolute"][class*="z-50"], #root main [class*="absolute"][class*="shadow"]');
    for (var i = 0; i < menus.length; i++) {
      var txt = (menus[i].textContent || '');
      if (txt.indexOf('Add new term') === -1) continue;
      if (menus[i].querySelectorAll('button').length <= 3) continue;
      var style = window.getComputedStyle(menus[i]);
      if (style && style.position === 'absolute' && style.display !== 'none') return true;
    }
    return false;
  }

  function visibleTermLabel() {
    var buttons = document.querySelectorAll('#root main button');
    var found = '';
    for (var i = 0; i < buttons.length; i++) {
      var btn = buttons[i];
      if (btn.closest('aside')) continue;
      if (btn.closest('#gz-pay, #gz-books')) continue;
      if (btn.closest('[data-gz-extra]')) continue;
      var txt = (btn.textContent || '').replace(/\s+/g, ' ').trim();
      if (!txt || txt.length > 90) continue;
      if (!/Select term|همه ترم‌ها|Fall|Summer|Winter|Spring|140[0-9]/.test(txt)) continue;
      if (/Add new term|Add student|Template|Upload Excel|Excel export/.test(txt)) continue;
      found = txt;
      if (btn.querySelector && btn.querySelector('svg')) return txt;
    }
    return found;
  }

  function termAlreadyShown(id) {
    var name = termName(id);
    var paySel = document.getElementById('gz-pay-term');
    if (paySel && document.documentElement.classList.contains('gz-pay-open')) {
      return String(paySel.value) === String(id);
    }
    if (!name) return false;
    var label = visibleTermLabel();
    if (!label) return false;
    if (id === 'all') return /همه ترم‌ها/.test(label);
    return label.indexOf(name) !== -1;
  }

  function restoreNativeTermSelects(id) {
    document.querySelectorAll('select').forEach(function (sel) {
      if (sel.id === 'gz-pay-term') return;
      if (!sel.options || sel.options.length < 2) return;
      var has = false;
      for (var i = 0; i < sel.options.length; i++) {
        if (String(sel.options[i].value) === String(id)) { has = true; break; }
      }
      if (!has) return;
      if (String(sel.value) === String(id)) return;
      sel.value = String(id);
      sel.dispatchEvent(new Event('input', { bubbles: true }));
      sel.dispatchEvent(new Event('change', { bubbles: true }));
    });
  }

  function clickMatchingTerm(name) {
    var items = document.querySelectorAll('.gz-term-item, #root button');
    var exact = null;
    var fuzzy = null;
    for (var i = 0; i < items.length; i++) {
      var el = items[i];
      if (el.closest('aside')) continue;
      var txt = (el.textContent || '').replace(/\s+/g, ' ').trim();
      if (!txt || /Add new term/.test(txt)) continue;
      if (txt === name || txt.indexOf(name + ' ') === 0 || txt.indexOf(name + '—') === 0 || txt.indexOf(name + ' —') === 0) {
        exact = el;
        break;
      }
      if (!fuzzy && txt.indexOf(name) !== -1) fuzzy = el;
    }
    var hit = exact || fuzzy;
    if (hit) hit.click();
    return !!hit;
  }

  function restoreCustomTermPicker(id) {
    var name = termName(id);
    if (!name) return;
    if (termAlreadyShown(id)) return;
    if (termMenuOpen()) {
      clickMatchingTerm(name);
      return;
    }
    var triggers = document.querySelectorAll('#root button');
    var trigger = null;
    for (var i = 0; i < triggers.length; i++) {
      var btn = triggers[i];
      if (btn.closest('aside')) continue;
      if (btn.closest('#gz-pay, #gz-books')) continue;
      var txt = (btn.textContent || '').replace(/\s+/g, ' ').trim();
      if (!txt || txt.length > 90) continue;
      if (!/Select term|همه ترم‌ها|Fall|Summer|Winter|Spring|140[0-9]/.test(txt)) continue;
      if (/Add new term|Add student|Template|Upload Excel|Excel export/.test(txt)) continue;
      trigger = btn;
      if (txt.indexOf(name) !== -1) return;
      break;
    }
    if (!trigger) return;
    restoringTerm = true;
    trigger.click();
    window.setTimeout(function () {
      clickMatchingTerm(name);
      restoringTerm = false;
    }, 140);
  }

  function restoreTerm() {
    var id = desiredTerm();
    if (!id) return;
    if (termAlreadyShown(id)) {
      restoreNativeTermSelects(id);
      return;
    }
    var t = Date.now();
    if (t - lastTermRestore < 650) return;
    lastTermRestore = t;
    restoreNativeTermSelects(id);
    restoreCustomTermPicker(id);
    var paySel = document.getElementById('gz-pay-term');
    if (paySel && String(paySel.value) !== String(id)) {
      var has = false;
      for (var i = 0; i < paySel.options.length; i++) {
        if (String(paySel.options[i].value) === String(id)) has = true;
      }
      if (has) {
        paySel.value = String(id);
        paySel.dispatchEvent(new Event('input', { bubbles: true }));
        paySel.dispatchEvent(new Event('change', { bubbles: true }));
      }
    }
  }

  function extraStateFromDom(box) {
    var on = box.querySelector('#gz-extra-on');
    var name = box.querySelector('#gz-extra-name');
    var price = box.querySelector('#gz-extra-price');
    return {
      present: true,
      on: !!(on && on.checked),
      name: name ? name.value.trim() : '',
      price: price ? digits(price.value) : 0
    };
  }

  function extraState() {
    var box = document.querySelector('[data-gz-extra="1"]');
    if (!box) return { present: false, on: false, name: '', price: 0 };
    return extraStateFromDom(box);
  }

  function applyExtraToTotal(form) {
    var extra = extraState();
    if (!extra.present) return;
    var input = form && form.querySelector('input[name="t"]');
    if (!input) return;
    var add = extra.on ? extra.price : 0;
    var prev = Number(input.dataset.gzExtra || 0);
    var current = digits(input.value);
    input.value = String(Math.max(0, current - prev + add));
    input.dataset.gzExtra = String(add);
  }

  function studentForEditModal() {
    var texts = [];
    document.querySelectorAll('#root h3, #root h2').forEach(function (n) { texts.push((n.textContent || '').trim()); });
    for (var i = 0; i < texts.length; i++) {
      var m = texts[i].match(/Edit payment:\s*(.+)$/i) || texts[i].match(/ویرایش[:\s]+(.+)$/);
      if (m && studentsByName[m[1].trim()]) return studentsByName[m[1].trim()];
    }
    return null;
  }

  function extraBlockHtml(prefill) {
    prefill = prefill || {};
    var on = !!prefill.hasExtraCourse;
    var priceVal = prefill.extraCoursePrice ? String(prefill.extraCoursePrice) : '';
    return (
      '<div class="gz-extra space-y-2 pt-2 border-t border-red-900/30" data-gz-extra="1">' +
        '<label class="flex items-center gap-2.5 text-sm text-slate-200 font-medium cursor-pointer select-none">' +
          '<input id="gz-extra-on" type="checkbox"' + (on ? ' checked' : '') + ' class="w-4 h-4 accent-red-600 rounded cursor-pointer">' +
          '<span>Extra Course</span>' +
        '</label>' +
        '<div id="gz-extra-fields" class="gz-extra-fields grid grid-cols-1 sm:grid-cols-2 gap-3 pt-1 p-3 rounded-lg"' + (on ? '' : ' hidden') + '>' +
          '<div class="space-y-1">' +
            '<label class="text-[10px] text-slate-400 font-medium">اسم دوره</label>' +
            '<input id="gz-extra-name" type="text" value="' + esc(prefill.extraCourseName || '') + '" placeholder="مثال: Conversation" class="w-full bg-[#12121C] border border-red-900/40 text-white rounded-lg py-2 px-3 text-xs focus:border-red-500 outline-none">' +
          '</div>' +
          '<div class="space-y-1">' +
            '<label class="text-[10px] text-slate-400 font-medium">هزینه دوره (تومان)</label>' +
            '<input id="gz-extra-price" inputmode="numeric" value="' + esc(priceVal) + '" placeholder="تومان" class="w-full bg-[#12121C] border border-red-900/40 text-white rounded-lg py-2 px-3 text-xs focus:border-red-500 outline-none font-mono">' +
          '</div>' +
        '</div>' +
      '</div>'
    );
  }

  function injectExtraCourse() {
    var form = document.querySelector('#root form');
    if (!form) return;
    var headers = form.querySelectorAll('div');
    for (var i = 0; i < headers.length; i++) {
      var txt = (headers[i].textContent || '').trim();
      if (txt !== 'خدمات، تخفیف و افزوده مالی') continue;
      var box = headers[i].parentElement;
      if (!box) continue;
      if (box.querySelector('[data-gz-extra="1"]')) continue;
      if (/Extra Course/.test(box.textContent || '') && box.querySelector('input[type=checkbox]')) continue;
      var prefill = studentForEditModal() || {};
      var wrap = document.createElement('div');
      wrap.innerHTML = extraBlockHtml(prefill);
      var interviewRow = null;
      var rows = box.children;
      for (var r = 0; r < rows.length; r++) {
        var rowTxt = (rows[r].textContent || '').trim();
        if (/^interview/i.test(rowTxt) || (rowTxt.indexOf('interview') !== -1 && rows[r].querySelector('input[type=checkbox]'))) {
          interviewRow = rows[r];
          break;
        }
      }
      var node = wrap.firstChild;
      if (interviewRow) box.insertBefore(node, interviewRow);
      else box.appendChild(node);
      var form = box.closest('form') || document.querySelector('#root form');
      bindExtra(box, form);
    }
  }

  function bindExtra(box, form) {
    var on = box.querySelector('#gz-extra-on');
    var fields = box.querySelector('#gz-extra-fields');
    var name = box.querySelector('#gz-extra-name');
    var price = box.querySelector('#gz-extra-price');
    function sync() {
      if (fields) fields.hidden = !(on && on.checked);
      if (form) applyExtraToTotal(form);
    }
    if (on && !on._gzBound) {
      on._gzBound = true;
      on.addEventListener('change', sync);
    }
    if (price && !price._gzBound) {
      price._gzBound = true;
      price.addEventListener('input', sync);
    }
    if (name && !name._gzBound) {
      name._gzBound = true;
      name.addEventListener('input', function () {});
    }
    sync();
  }

  function mergeStudentPayload(url, method, body) {
    var extra = extraState();
    var data;
    try { data = JSON.parse(body); } catch (e) { return body; }
    if (!data || typeof data !== 'object' || Array.isArray(data)) return body;
    if (!extra.present) return body;
    if (Object.prototype.hasOwnProperty.call(data, 'hasExtraCourse') && data.hasExtraCourse === extra.on && String(data.extraCourseName || '') === extra.name) {
      return body;
    }
    var idMatch = String(url).match(/\/api\/students\/(\d+)/);
    var orig = null;
    if (idMatch) {
      students.forEach(function (s) {
        if (String(s.id) === String(idMatch[1])) orig = s;
      });
    }
    if (!orig) orig = studentForEditModal();
    var origExtra = orig && orig.hasExtraCourse ? (Number(orig.extraCoursePrice) || 0) : 0;
    var newExtra = extra.on ? extra.price : 0;
    data.hasExtraCourse = extra.on;
    data.extraCourseName = extra.on ? extra.name : '';
    data.extraCoursePrice = newExtra;
    var sent = Number(data.totalPayable) || 0;
    var form = document.querySelector('#root form');
    var t = form ? form.querySelector('input[name="t"]') : null;
    var marked = t ? Number(t.dataset.gzExtra || 0) : 0;
    var isPatch = String(method || '').toUpperCase() === 'PATCH' || !!idMatch;
    if (isPatch) {
      data.totalPayable = Math.max(0, sent - origExtra + newExtra);
    } else if (marked === newExtra && newExtra > 0 && sent >= newExtra) {
      data.totalPayable = sent;
    } else {
      data.totalPayable = Math.max(0, sent + newExtra);
    }
    return JSON.stringify(data);
  }

  function findStudentForRow(tr) {
    var tds = tr.querySelectorAll('td');
    if (!tds.length) return null;
    var code = normCode(tds[0].textContent || '');
    if (!code) {
      for (i = 0; i < tds.length; i++) {
        var digits = normCode(tds[i].textContent || '');
        if (digits && studentsByCode[digits]) { code = digits; break; }
      }
    }
    var matches = [];
    var i;
    if (code) {
      for (i = 0; i < students.length; i++) {
        if (normCode(students[i].studentCode) === code) matches.push(students[i]);
      }
    }
    var termId = desiredTerm();
    if (termId && matches.length > 1) {
      var forTerm = [];
      for (i = 0; i < matches.length; i++) {
        if (String(matches[i].termId) === String(termId)) forTerm.push(matches[i]);
      }
      if (forTerm.length) matches = forTerm;
    }
    for (i = 0; i < matches.length; i++) {
      if (matches[i].hasExtraCourse === true || matches[i].hasExtraCourse === 1 || matches[i].hasExtraCourse === '1') {
        return matches[i];
      }
    }
    if (matches[0]) return matches[0];
    var nameCell = tds[1] || tds[0];
    var name = ((nameCell && nameCell.querySelector('span')) || nameCell).textContent || '';
    name = name.replace(/\s+/g, ' ').trim()
      .split('کتاب')[0]
      .split('Interview')[0]
      .split('تخفیف')[0]
      .split('Course')[0]
      .trim();
    return studentsByName[name] || null;
  }

  var paintingTags = false;
  function paintCourseTags() {
    if (paintingTags) return;
    var sec = lastSection || desiredSection();
    if (sec && sec !== 'registration') return;
    paintingTags = true;
    try {
    document.querySelectorAll('#root main table tr').forEach(function (tr) {
      if (tr.closest('#gz-pay, #gz-books')) return;
      if (tr.querySelector('th')) return;
      var tds = tr.querySelectorAll('td');
      var nameCell = tds[1] || tds[0];
      if (!nameCell) return;
      var stu = findStudentForRow(tr);
      var existing = nameCell.querySelector('[data-gz-course-tag]');
      if (!stu) return;
      if (!(stu.hasExtraCourse === true || stu.hasExtraCourse === 1 || stu.hasExtraCourse === '1')) {
        if (existing) existing.remove();
        return;
      }
      var label = stu.extraCourseName ? ('Course: ' + stu.extraCourseName) : 'Course';
      var title = stu.extraCourseName
        ? ('Extra Course: ' + stu.extraCourseName + ' (' + money(stu.extraCoursePrice) + ' تومان)')
        : 'Extra Course';
      if (existing) {
        if (existing.textContent !== label) existing.textContent = label;
        if (existing.title !== title) existing.title = title;
        return;
      }
      var wrap = nameCell.querySelector('.flex') || nameCell;
      var tag = document.createElement('span');
      tag.setAttribute('data-gz-course-tag', '1');
      tag.className = 'gz-course-tag bg-fuchsia-950/70 text-fuchsia-300 border border-fuchsia-500/40 text-[10px] px-2 py-0.5 rounded-full font-medium';
      tag.title = title;
      tag.textContent = label;
      wrap.appendChild(tag);
    });
    } finally {
      paintingTags = false;
    }
    hideShopBookTags();
  }

  var shopBookCodes = {};
  var shopBookFetched = 0;
  function pullShopBookCodes() {
    if (!signedIn()) return;
    if (Date.now() - shopBookFetched < 20000) return;
    shopBookFetched = Date.now();
    origFetch('/api/bookstore_orders?status=approved,ordered', {
      credentials: 'same-origin',
      headers: { Accept: 'application/json' }
    }).then(function (r) {
      return r.ok ? r.json() : { orders: [] };
    }).then(function (data) {
      var next = {};
      var list = (data && Array.isArray(data.orders)) ? data.orders : [];
      for (var i = 0; i < list.length; i++) {
        var code = normCode(list[i].student_code);
        if (code) next[code] = true;
      }
      shopBookCodes = next;
      hideShopBookTags();
    }).catch(function () {});
  }

  function hideShopBookTags() {
    var sec = lastSection || desiredSection();
    if (sec && sec !== 'registration') return;
    document.querySelectorAll('#root main table tr').forEach(function (tr) {
      if (tr.closest('#gz-pay, #gz-books')) return;
      if (tr.querySelector('th')) return;
      var stu = findStudentForRow(tr);
      if (!stu) return;
      var code = normCode(stu.studentCode);
      if (!code || !shopBookCodes[code]) return;
      var tds = tr.querySelectorAll('td');
      var nameCell = tds[1] || tds[0];
      if (!nameCell) return;
      nameCell.querySelectorAll('span').forEach(function (span) {
        if (span.getAttribute('data-gz-course-tag')) return;
        var t = (span.textContent || '').replace(/\s+/g, ' ').trim();
        var title = String(span.title || '');
        if (/^کتاب/.test(t) || title.indexOf('کتاب') === 0) span.remove();
      });
    });
  }

  function injectInvoiceRow(html, student) {
    if (!student || !student.hasExtraCourse) return html;
    if (html.indexOf('Extra Course:') !== -1) return html;
    var price = money(student.extraCoursePrice);
    var name = esc(student.extraCourseName || 'Extra Course');
    var row = '<tr class="">\n        <td class="n">•</td>\n        <td class="d">Extra Course: ' + name + '</td>\n        <td class="a">' + price + ' <span class="u">تومان</span></td>\n      </tr>';
    var needle = 'مصاحبه / Interview';
    var idx = html.lastIndexOf(needle);
    if (idx === -1) {
      return html.replace('</tbody>', row + '</tbody>');
    }
    var close = html.indexOf('</tr>', idx);
    if (close === -1) return html;
    return html.slice(0, close + 5) + row + html.slice(close + 5);
  }

  window.fetch = function (input, init) {
    var url = typeof input === 'string' ? input : (input && input.url) || '';
    init = init || {};
    var method = (init.method || (typeof input !== 'string' && input.method) || 'GET').toUpperCase();
    if (/\/api\/students(\/|$|\?)/.test(url) && (method === 'POST' || method === 'PATCH') && init.body && typeof init.body === 'string') {
      init = Object.assign({}, init, { body: mergeStudentPayload(url, method, init.body) });
    }
    return origFetch.apply(this, arguments).then(function (res) {
      if (!res || !res.ok) return res;
      if (typeof url === 'string' && url.indexOf('/api/terms') !== -1 && url.indexOf('/api/terms/') === -1) {
        return res.clone().json().then(function (data) {
          rememberTerms(data);
          return res;
        }).catch(function () { return res; });
      }
      if (typeof url === 'string' && /\/api\/students\/?(\?|$)/.test(url) && method === 'GET' && url.indexOf('/api/students/') === -1) {
        return res.clone().json().then(function (data) {
          rememberStudents(Array.isArray(data) ? data : []);
          window.setTimeout(paintCourseTags, 50);
          return res;
        }).catch(function () { return res; });
      }
      if (typeof url === 'string' && url.indexOf('/api/me') !== -1) {
        window.setTimeout(restoreSection, 200);
        window.setTimeout(restoreSection, 700);
        window.setTimeout(restoreTerm, 900);
      }
      return res;
    });
  };

  var origOpen = window.open;
  window.open = function () {
    var w = origOpen.apply(this, arguments);
    try {
      if (w && lastPrintStudent && lastPrintStudent.hasExtraCourse) {
        var doc = w.document;
        var write = doc.write.bind(doc);
        doc.write = function (html) {
          if (typeof html === 'string') html = injectInvoiceRow(html, lastPrintStudent);
          return write(html);
        };
      }
    } catch (e) {}
    return w;
  };

  document.addEventListener('click', function (e) {
    var btn = e.target.closest('aside button');
    if (btn && !restoringSection) {
      var label = (btn.textContent || '').replace(/\s+/g, ' ').trim();
      if (LABEL_SECTIONS[label]) saveSection(LABEL_SECTIONS[label]);
    }
    var termBtn = e.target.closest('button, .gz-term-item');
    if (termBtn && !restoringTerm) {
      var text = (termBtn.textContent || '').replace(/\s+/g, ' ').trim();
      for (var i = 0; i < terms.length; i++) {
        if (terms[i].name && text.indexOf(terms[i].name) !== -1 && !/Add new term/.test(text)) {
          if (termBtn.closest('.gz-term-wrap, .gz-pay-term, #gz-pay, #gz-books') || /Fall|Summer|Winter|Spring|140/.test(text) || termBtn.classList.contains('gz-term-item')) {
            saveTerm(terms[i].id);
            break;
          }
        }
      }
    }
    var printBtn = e.target.closest('button');
    if ((printBtn && printBtn.getAttribute('title') === 'Receipts') || (printBtn && printBtn.closest('td') && printBtn.querySelector('svg'))) {
      var tr = printBtn && printBtn.closest('tr');
      if (tr && tr.children[0]) {
        lastPrintStudent = studentsByCode[(tr.children[0].textContent || '').trim()] || lastPrintStudent;
      }
    }
  }, true);

  document.addEventListener('change', function (e) {
    if (e.target && e.target.tagName === 'SELECT') {
      var val = e.target.value;
      if (termsById[String(val)]) saveTerm(val);
    }
  }, true);

  window.addEventListener('hashchange', function () {
    if (writingHash) return;
    var hash = parseHash();
    if (hash.section) storeSet(SECTION_KEY, hash.section);
    if (hash.term) storeSet(TERM_KEY, hash.term);
    restoreSection();
    window.setTimeout(restoreTerm, 280);
  });

  function markRegClass() {
    var sec = currentSection() || desiredSection();
    document.documentElement.classList.toggle('gz-reg-open', sec === 'registration');
  }

  function isMobileShell() {
    return document.documentElement.classList.contains('gz-acc-mobile')
      || (window.matchMedia && window.matchMedia('(max-width: 900px)').matches);
  }

  function stampTableLabels() {
    var tables = document.querySelectorAll('#root main table');
    for (var t = 0; t < tables.length; t++) {
      var table = tables[t];
      if (table.classList.contains('gz-pay-table')) continue;
      if (table.closest('#gz-pay, #gz-books')) continue;
      var ths = table.querySelectorAll('thead th');
      if (!ths.length) continue;
      var labels = [];
      for (var i = 0; i < ths.length; i++) {
        labels.push((ths[i].textContent || '').replace(/\s+/g, ' ').trim());
      }
      var isStudent = labels.indexOf('شماره پرونده') !== -1 || labels.indexOf('نام دانشجو') !== -1;
      table.classList.toggle('gz-acc-student-table', isStudent);
      var rows = table.querySelectorAll('tbody tr');
      for (var r = 0; r < rows.length; r++) {
        var tds = rows[r].children;
        for (var c = 0; c < tds.length && c < labels.length; c++) {
          if (tds[c].getAttribute('data-gz-th') !== labels[c]) {
            tds[c].setAttribute('data-gz-th', labels[c]);
          }
        }
      }
    }
  }

  function markSearchOpen() {
    var header = document.querySelector('#root header');
    var open = false;
    if (header) {
      var nodes = header.querySelectorAll('div');
      for (var i = 0; i < nodes.length; i++) {
        var cls = nodes[i].className || '';
        if (cls.indexOf('inset-x-4') !== -1 && cls.indexOf('absolute') !== -1) {
          open = true;
          break;
        }
      }
    }
    document.documentElement.classList.toggle('gz-acc-search-open', open);
  }

  function tuneSearchInputs() {
    var inputs = document.querySelectorAll('#root header input[type="text"], #root header input:not([type])');
    for (var i = 0; i < inputs.length; i++) {
      var el = inputs[i];
      el.style.fontSize = '16px';
      if (!el.getAttribute('enterkeyhint')) el.setAttribute('enterkeyhint', 'search');
      el.setAttribute('autocapitalize', 'off');
      el.setAttribute('autocorrect', 'off');
      el.setAttribute('spellcheck', 'false');
    }
  }

  function markRegChrome() {
    var h2 = document.querySelector('#root main h2');
    if (h2) {
      var titleBlock = h2.parentElement;
      var toolbar = titleBlock && titleBlock.nextElementSibling;
      if (toolbar && toolbar.tagName === 'DIV') {
        toolbar.classList.add('gz-acc-toolbar');
        var secondaries = [];
        for (var i = 0; i < toolbar.children.length; i++) {
          var child = toolbar.children[i];
          if (child.tagName !== 'BUTTON') continue;
          if ((child.className || '').indexOf('from-red-600') !== -1) continue;
          secondaries.push(child);
        }
        for (var s = 0; s < secondaries.length; s++) {
          secondaries[s].classList.toggle('gz-acc-span', secondaries.length % 2 === 1 && s === secondaries.length - 1);
        }
      }
    }
    var h4 = document.querySelector('#root main h4');
    if (h4) {
      var row = h4.parentElement;
      if (row) row.classList.add('gz-acc-listhead');
      var filters = h4.nextElementSibling;
      if (filters && filters.tagName === 'DIV') {
        filters.classList.add('gz-acc-filters');
      }
    }
  }

  function keepFocusedInView(e) {
    var el = e.target;
    if (!el || !el.tagName) return;
    if (!/^(INPUT|TEXTAREA|SELECT)$/.test(el.tagName)) return;
    window.setTimeout(function () {
      try {
        el.scrollIntoView({ block: 'center', inline: 'nearest' });
      } catch (err) {}
    }, 280);
  }

  function ensureRegFx() {
    if (isMobileShell()) {
      var leftover = document.getElementById('gz-reg-fx');
      if (leftover) leftover.remove();
      return;
    }
    if (document.getElementById('gz-reg-fx')) return;
    var main = document.querySelector('#root main');
    if (!main) return;
    var fx = document.createElement('div');
    fx.id = 'gz-reg-fx';
    fx.className = 'gz-reg-fx';
    fx.setAttribute('aria-hidden', 'true');
    fx.innerHTML =
      '<div class="gz-reg-fx__orb gz-reg-fx__orb--a"></div>' +
      '<div class="gz-reg-fx__orb gz-reg-fx__orb--b"></div>' +
      '<div class="gz-reg-fx__orb gz-reg-fx__orb--c"></div>' +
      '<div class="gz-reg-fx__grid"></div>';
    main.insertBefore(fx, main.firstChild);
  }

  function tick() {
    if (!signedIn()) return;
    var hash = parseHash();
    if (!hash.section && storeGet(SECTION_KEY)) {
      writeHash(storeGet(SECTION_KEY), storeGet(TERM_KEY));
    }
    restoreSection();
    var sec = currentSection();
    if (sec && sec !== lastSection) {
      lastSection = sec;
      termAttempts = 0;
      if (!restoringSection) saveSection(sec);
      lastTermRestore = 0;
      window.setTimeout(restoreTerm, 220);
      window.setTimeout(restoreTerm, 700);
      window.setTimeout(restoreTerm, 1400);
    } else if (termAttempts < 10) {
      var before = termAlreadyShown(desiredTerm());
      restoreTerm();
      if (!before) termAttempts += 1;
    }
    markRegClass();
    ensureRegFx();
    injectExtraCourse();
    paintCourseTags();
    pullShopBookCodes();
    stampTableLabels();
    markSearchOpen();
    tuneSearchInputs();
    markRegChrome();
    var form = document.querySelector('#root form');
    if (form && extraState().present) applyExtraToTotal(form);
    if (signedIn() && students.length === 0) pullStudents();
  }

  var lastPull = 0;
  function pullStudents() {
    if (Date.now() - lastPull < 2500) return;
    lastPull = Date.now();
    origFetch('/api/students', { credentials: 'same-origin' }).then(function (r) { return r.ok ? r.json() : []; }).then(function (data) {
      rememberStudents(Array.isArray(data) ? data : []);
      paintCourseTags();
    }).catch(function () {});
  }

  function ready(fn) {
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', fn);
    else fn();
  }

  ready(function () {
    origFetch('/api/terms', { credentials: 'same-origin' }).then(function (r) { return r.ok ? r.json() : []; }).then(rememberTerms).catch(function () {});
    pullStudents();
    var startedAt = Date.now();
    function loop() {
      tick();
      window.setTimeout(loop, Date.now() - startedAt < 8000 ? 450 : 1600);
    }
    loop();
    var obsTimer = 0;
    var root = document.getElementById('root') || document.documentElement;
    new MutationObserver(function () {
      if (paintingTags) return;
      if (obsTimer) return;
      obsTimer = window.setTimeout(function () {
        obsTimer = 0;
        if (!signedIn()) return;
        injectExtraCourse();
        paintCourseTags();
        markRegClass();
        ensureRegFx();
        stampTableLabels();
        markSearchOpen();
        tuneSearchInputs();
        markRegChrome();
      }, 180);
    }).observe(root, { childList: true, subtree: true });
    document.addEventListener('focusin', keepFocusedInView, true);
  });
})();
