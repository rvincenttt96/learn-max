(function () {
  var TAB_APPROVED = 'approved';
  var TAB_ORDERED = 'ordered';
  var tab = TAB_APPROVED;
  var orders = [];
  var counts = { approved: 0, ordered: 0 };
  var loading = false;
  var notice = { type: '', text: '' };
  var searchTimer = 0;
  var lastBooks = false;
  var misses = 0;
  var delivering = {};
  var origFetch = window.fetch;
  var root = null;

  var NAV_BASE = 'w-full flex items-center gap-4 px-5 py-3.5 rounded-2xl transition-all duration-200 cursor-pointer';
  var NAV_ON = NAV_BASE + ' bg-gradient-to-r from-red-600 to-rose-700 text-white font-medium shadow-[0_0_20px_rgba(220,38,38,0.4)] border border-red-400/30';
  var NAV_OFF = NAV_BASE + ' text-slate-400 hover:text-red-300 hover:bg-red-950/20 border border-transparent';
  var BOOK_ICON = '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 7v14"/><path d="M3 18a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1h5a4 4 0 0 1 4 4 4 4 0 0 1 4-4h5a1 1 0 0 1 1 1v13a1 1 0 0 1-1 1h-6a3 3 0 0 0-3 3 3 3 0 0 0-3-3z"/></svg>';

  function esc(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
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

  function allowedUser() {
    try {
      var raw = sessionStorage.getItem('ghazal-user');
      if (!raw) return false;
      var user = JSON.parse(raw);
      return !!(user && (user.role === 'manager' || user.role === 'reception'));
    } catch (err) {
      return false;
    }
  }

  function roleFromAside() {
    var aside = document.querySelector('#root aside');
    var text = (aside && aside.textContent) || '';
    return /دسترسی:\s*(مدیر|پذیرش)/.test(text);
  }

  function canOpen() {
    return allowedUser() || roleFromAside();
  }

  function booksHashActive() {
    try {
      var raw = String(location.hash || '').replace(/^#\/?/, '').split('?')[0].toLowerCase();
      return raw === 'bookstore';
    } catch (e) {
      return false;
    }
  }

  function persistBooks() {
    try {
      return (sessionStorage.getItem('gz-acc-section') || localStorage.getItem('gz-acc-section')) === 'bookstore';
    } catch (e) {
      return false;
    }
  }

  function saveSection() {
    try {
      sessionStorage.setItem('gz-acc-section', 'bookstore');
      localStorage.setItem('gz-acc-section', 'bookstore');
    } catch (e) {}
    var term = '';
    try {
      term = sessionStorage.getItem('gz-acc-term') || localStorage.getItem('gz-acc-term') || '';
    } catch (e) {}
    var next = '#/bookstore';
    if (term) next += '?term=' + encodeURIComponent(term);
    if (location.hash === next) return;
    try {
      history.replaceState(null, '', next);
    } catch (e) {
      location.hash = next;
    }
  }

  function headerSearch() {
    var input = document.querySelector('#root header input[type="text"]');
    return input ? String(input.value || '').trim() : '';
  }

  function navReady() {
    return document.querySelectorAll('#root aside button').length > 0;
  }

  function buttonLabel(btn) {
    return (btn.textContent || '').replace(/\s+/g, ' ').trim();
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
    root = document.getElementById('gz-books');
    if (!root) {
      root = document.createElement('div');
      root.id = 'gz-books';
      root.hidden = true;
    }
    parkRoot();
    if (!root._bound) {
      root._bound = true;
      root.addEventListener('click', onClick);
    }
    return root;
  }

  function hidePayroll() {
    document.documentElement.classList.remove('gz-pay-open');
    var pay = document.getElementById('gz-pay');
    if (!pay) return;
    pay.hidden = true;
    pay.style.setProperty('display', 'none', 'important');
  }

  function show(on) {
    var el = ensureRoot();
    var wasHidden = el.hidden;
    el.hidden = !on;
    if (on) {
      el.removeAttribute('hidden');
      el.style.setProperty('display', 'flex', 'important');
      hidePayroll();
      saveSection();
    } else {
      el.style.setProperty('display', 'none', 'important');
    }
    parkRoot();
    document.documentElement.classList.toggle('gz-books-open', !!on);
    paintNav(!!on);
    if (on && wasHidden) {
      render();
      loadOrders();
    }
  }

  function closeMobileSidebar() {
    var nodes = document.querySelectorAll('#root .fixed');
    for (var i = 0; i < nodes.length; i++) {
      var cls = nodes[i].className || '';
      if (cls.indexOf('bg-black') !== -1 || cls.indexOf('z-40') !== -1) {
        if (nodes[i].closest('aside')) continue;
        nodes[i].click();
        return;
      }
    }
  }

  function injectNav() {
    var nav = document.querySelector('#root aside nav');
    if (!nav) return;
    var existing = document.getElementById('gz-books-nav');
    if (existing && nav.contains(existing)) {
      paintNav(lastBooks);
      return;
    }
    var buttons = nav.querySelectorAll('button');
    var reg = null;
    for (var i = 0; i < buttons.length; i++) {
      if (buttonLabel(buttons[i]) === 'Registration') {
        reg = buttons[i];
        break;
      }
    }
    var btn = existing || document.createElement('button');
    btn.id = 'gz-books-nav';
    btn.type = 'button';
    btn.className = lastBooks ? NAV_ON : NAV_OFF;
    btn.innerHTML = BOOK_ICON + '<span class="text-sm text-right flex-1 font-medium">Bookstore</span>';
    if (!btn._bound) {
      btn._bound = true;
      btn.addEventListener('click', function (e) {
        e.preventDefault();
        e.stopPropagation();
        lastBooks = true;
        misses = 0;
        show(true);
        closeMobileSidebar();
      });
    }
    if (reg && reg.parentNode === nav) {
      if (reg.nextSibling) nav.insertBefore(btn, reg.nextSibling);
      else nav.appendChild(btn);
    } else {
      nav.insertBefore(btn, nav.firstChild);
    }
    paintNav(lastBooks);
  }

  function paintNav(on) {
    var btn = document.getElementById('gz-books-nav');
    if (btn) {
      btn.className = on ? NAV_ON : NAV_OFF;
      var svg = btn.querySelector('svg');
      if (svg) svg.setAttribute('class', on ? 'text-white' : 'text-slate-400');
    }
    if (!on) return;
    var buttons = document.querySelectorAll('#root aside nav button');
    for (var i = 0; i < buttons.length; i++) {
      if (buttons[i].id === 'gz-books-nav') continue;
      var cls = buttons[i].className || '';
      if (cls.indexOf('from-red-600') === -1) continue;
      buttons[i].className = NAV_OFF;
    }
  }

  function setNotice(type, text) {
    notice = { type: type || '', text: text || '' };
    render();
  }

  function onClick(e) {
    var t = e.target.closest('[data-books]');
    if (!t) return;
    var act = t.getAttribute('data-books');
    if (act === 'tab') {
      tab = t.getAttribute('data-tab') === TAB_ORDERED ? TAB_ORDERED : TAB_APPROVED;
      render();
      return;
    }
    if (act === 'reload') {
      loadOrders(true);
      return;
    }
    if (act === 'deliver') {
      e.preventDefault();
      var id = parseInt(t.getAttribute('data-id'), 10);
      var row = findOrder(id);
      if (!row) return;
      setDelivered(id, !orderDelivered(row));
    }
  }

  function filteredOrders() {
    var rows = orders.filter(function (o) {
      return (o.status || '') === tab;
    });
    var q = headerSearch().toLowerCase();
    if (!q) return rows;
    return rows.filter(function (o) {
      return [
        o.full_name, o.first_name, o.last_name, o.student_code,
        o.tracking_code, o.title, o.mobile, o.status_label
      ].join(' ').toLowerCase().indexOf(q) !== -1;
    });
  }

  function findOrder(id) {
    id = Number(id);
    for (var i = 0; i < orders.length; i++) {
      if (Number(orders[i].id) === id) return orders[i];
    }
    return null;
  }

  function orderDelivered(row) {
    return !!(row && (row.delivered === true || row.delivered === 1 || row.delivered === '1'));
  }

  function setDelivered(id, delivered) {
    id = Number(id);
    if (!id || delivering[id]) return;
    var row = findOrder(id);
    if (!row) return;
    var prev = orderDelivered(row);
    delivering[id] = true;
    row.delivered = !!delivered;
    row.delivered_at = delivered ? (row.delivered_at || '') : '';
    notice = { type: '', text: '' };
    render();
    api('/api/bookstore_orders/' + encodeURIComponent(id), {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ delivered: !!delivered })
    }).then(function (data) {
      delivering[id] = false;
      if (data && data.order) {
        for (var i = 0; i < orders.length; i++) {
          if (Number(orders[i].id) === Number(data.order.id)) {
            orders[i] = data.order;
            break;
          }
        }
      } else if (row) {
        row.delivered = !!delivered;
      }
      render();
    }).catch(function (err) {
      delivering[id] = false;
      if (row) {
        row.delivered = prev;
      }
      setNotice('err', err.message || 'ثبت تحویل کتاب انجام نشد');
    });
  }

  function loadOrders(force) {
    if (loading && !force) return;
    loading = true;
    notice = { type: '', text: '' };
    render();
    api('/api/bookstore_orders?status=approved,ordered').then(function (data) {
      orders = (data && Array.isArray(data.orders)) ? data.orders : [];
      counts = (data && data.counts) ? data.counts : { approved: 0, ordered: 0 };
      loading = false;
      if (data && data.ok === false && data.error) {
        setNotice('err', data.error);
      } else {
        render();
      }
    }).catch(function (err) {
      loading = false;
      orders = [];
      setNotice('err', err.message || 'سفارش‌های کتابفروشی نیامد');
    });
  }

  function itemTags(row) {
    var items = Array.isArray(row.items) ? row.items : [];
    if (!items.length) {
      return '<span class="gz-books-tag">' + esc(row.title || '—') + '</span>';
    }
    return items.map(function (it) {
      var qty = Number(it.quantity) || 1;
      return '<span class="gz-books-tag">' + esc(it.title || '') + (qty > 1 ? ' ×' + qty : '') + '</span>';
    }).join('');
  }

  function receiptHtml(row) {
    if (!row.has_receipt) {
      return '<span class="gz-books-noreceipt">بدون رسید</span>';
    }
    var href = '/api/bookstore_receipt/' + encodeURIComponent(row.id);
    return (
      '<a class="gz-books-receipt" href="' + href + '" target="_blank" rel="noopener">' +
        '<img src="' + href + '" alt="">' +
        '<span>رسید</span>' +
      '</a>'
    );
  }

  function statusChip(row) {
    var kind = row.status === 'ordered' ? 'done' : 'ready';
    return '<span class="gz-books-st gz-books-st--' + kind + '">' + esc(row.status_label || row.status) + '</span>';
  }

  function deliverHtml(row) {
    var on = orderDelivered(row);
    var busy = !!delivering[Number(row.id)];
    return (
      '<label class="gz-books-deliver' + (on ? ' is-on' : '') + (busy ? ' is-busy' : '') + '" data-books="deliver" data-id="' + esc(row.id) + '">' +
        '<input type="checkbox"' + (on ? ' checked' : '') + (busy ? ' disabled' : '') + '>' +
        '<span>تحویل کتاب</span>' +
      '</label>'
    );
  }

  function cardHtml(row) {
    var postage = Number(row.postage_fee) || 0;
    var delivery = esc(row.delivery_label || '—');
    if (postage > 0) delivery += ' · پست ' + money(postage) + ' تومان';
    return (
      '<article class="gz-books-card' + (orderDelivered(row) ? ' is-delivered' : '') + '">' +
        '<div class="gz-books-who">' +
          '<span class="gz-books-code">' + esc(row.student_code || '—') + '</span>' +
          '<div>' +
            '<strong>' + esc(row.full_name || ((row.first_name || '') + ' ' + (row.last_name || ''))) + '</strong>' +
            '<p>' + esc(row.tracking_code || '') + (row.source_label ? ' · ' + esc(row.source_label) : '') + '</p>' +
          '</div>' +
        '</div>' +
        '<div class="gz-books-tags">' + itemTags(row) + '</div>' +
        '<div class="gz-books-ship">' + delivery + '</div>' +
        '<div class="gz-books-hand">' + deliverHtml(row) + '</div>' +
        '<div class="gz-books-pay">' +
          '<b>' + money(row.grand_total || row.sell_total) + ' <small>تومان</small></b>' +
          statusChip(row) +
        '</div>' +
        '<div class="gz-books-file">' + receiptHtml(row) + '</div>' +
      '</article>'
    );
  }

  function pane() {
    var rows = filteredOrders();
    var total = rows.reduce(function (sum, row) { return sum + (Number(row.grand_total || row.sell_total) || 0); }, 0);
    var deliveredN = rows.filter(orderDelivered).length;
    var empty = tab === TAB_ORDERED
      ? 'هنوز سفارش خریدشده‌ای در این فهرست نیست.'
      : 'هنوز سفارش تأییدشده‌ای از کتابفروشی نیامده.';
    var label = tab === TAB_ORDERED ? 'خریدشده' : 'تأییدشده';
    return (
      '<div class="gz-books-toolbar">' +
        '<div class="gz-books-stats">' +
          '<span>' + rows.length + ' سفارش ' + label + '</span>' +
          '<span>' + deliveredN + ' تحویل کتاب</span>' +
          '<span>جمع ' + money(total) + ' تومان</span>' +
        '</div>' +
        '<button type="button" class="gz-books-refresh" data-books="reload">نوسازی</button>' +
      '</div>' +
      (loading ? '<div class="gz-books-note">در حال خواندن سفارش‌ها...</div>' : '') +
      (rows.length
        ? '<div class="gz-books-list">' + rows.map(cardHtml).join('') + '</div>'
        : (loading ? '' : '<div class="gz-books-empty">' + empty + '</div>'))
    );
  }

  function render() {
    var el = ensureRoot();
    if (el.hidden) return;
    el.innerHTML =
      '<div class="gz-books-page">' +
        '<div class="gz-books-head">' +
          '<div>' +
            '<h2>Bookstore</h2>' +
            '<p>سفارش‌های تأییدشده فروشگاه کتاب؛ جدا از ثبت‌نام دستی. تیک تحویل کتاب را اینجا بزنید.</p>' +
          '</div>' +
          '<div class="gz-books-tabs" role="tablist">' +
            '<button type="button" class="' + (tab === TAB_APPROVED ? 'is-on' : '') + '" data-books="tab" data-tab="approved">تأیید شده <b>' + (counts.approved || 0) + '</b></button>' +
            '<button type="button" class="' + (tab === TAB_ORDERED ? 'is-on' : '') + '" data-books="tab" data-tab="ordered">خرید شد <b>' + (counts.ordered || 0) + '</b></button>' +
          '</div>' +
        '</div>' +
        (notice.text ? '<div class="gz-books-note gz-books-note--' + esc(notice.type) + '">' + esc(notice.text) + '</div>' : '') +
        pane() +
      '</div>';
  }

  function otherSectionActive() {
    var buttons = document.querySelectorAll('#root aside nav button, #root aside button');
    for (var i = 0; i < buttons.length; i++) {
      if (buttons[i].id === 'gz-books-nav') continue;
      var cls = buttons[i].className || '';
      if (cls.indexOf('from-red-600') !== -1 || cls.indexOf('to-rose-700') !== -1) return true;
    }
    return false;
  }

  function sync() {
    injectNav();
    if (!canOpen()) {
      if (lastBooks) {
        lastBooks = false;
        show(false);
      }
      return;
    }
    var want = booksHashActive() || persistBooks();
    if (want && otherSectionActive() && !booksHashActive()) {
      want = false;
    }
    if (want) {
      misses = 0;
      if (!lastBooks) {
        lastBooks = true;
        show(true);
      } else {
        parkRoot();
        paintNav(true);
      }
      return;
    }
    if (lastBooks) {
      if (navReady() && !booksHashActive() && !persistBooks()) {
        lastBooks = false;
        misses = 0;
        show(false);
        return;
      }
      misses += 1;
      if (misses < 2) return;
      lastBooks = false;
      show(false);
    }
  }

  function ready(fn) {
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', fn);
    else fn();
  }

  ready(function () {
    ensureRoot();
    sync();
    setInterval(sync, 1200);
    window.addEventListener('hashchange', sync);
    document.addEventListener('input', function (e) {
      if (!e.target || e.target.closest('#root header') == null) return;
      window.clearTimeout(searchTimer);
      searchTimer = window.setTimeout(function () {
        if (lastBooks) render();
      }, 120);
    });
    var obsTimer = 0;
    var host = document.getElementById('root') || document.documentElement;
    new MutationObserver(function () {
      if (obsTimer) return;
      obsTimer = window.setTimeout(function () {
        obsTimer = 0;
        injectNav();
        if (lastBooks) paintNav(true);
      }, 80);
    }).observe(host, { childList: true, subtree: true });
  });
})();
