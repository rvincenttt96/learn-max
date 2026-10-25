(function () {
  var terms = [];
  var byId = {};
  var lastUser = null;
  var fetching = false;

  function escapeHtml(s) {
    return String(s || '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function decorateTerms(list) {
    if (!Array.isArray(list)) return [];
    var out = list.map(function (t) {
      var copy = {};
      Object.keys(t || {}).forEach(function (k) { copy[k] = t[k]; });
      copy.id = String((t && (t._id || t.id)) || '');
      return copy;
    });
    var teach = '';
    var reg = '';
    out.forEach(function (t) {
      if (t.isTeaching && !teach) teach = String(t.id);
      if (t.isRegistration && !reg) reg = String(t.id);
    });
    out.forEach(function (t) {
      var id = String(t.id);
      t.isTeaching = teach !== '' && id === teach;
      t.isRegistration = reg !== '' && id === reg && !t.isTeaching;
      t.label = t.isTeaching
        ? (t.name + ' — در حال برگزاری')
        : (t.isRegistration ? (t.name + ' — در حال ثبت نام') : (t.name || ''));
    });
    out.sort(function (a, b) {
      var ra = a.isTeaching ? 0 : a.isRegistration ? 1 : 2;
      var rb = b.isTeaching ? 0 : b.isRegistration ? 1 : 2;
      if (ra !== rb) return ra - rb;
      return Number(b.createdAt || 0) - Number(a.createdAt || 0);
    });
    return out;
  }

  function remember(list) {
    terms = decorateTerms(list);
    byId = {};
    terms.forEach(function (t) { byId[String(t.id)] = t; });
    return terms;
  }

  function isTermsUrl(url) {
    return typeof url === 'string' && url.indexOf('/api/terms') !== -1 && url.indexOf('/api/terms/') === -1;
  }

  var origFetch = window.fetch;
  window.fetch = function (input, init) {
    var url = typeof input === 'string' ? input : (input && input.url) || '';
    return origFetch.apply(this, arguments).then(function (res) {
      if (!isTermsUrl(url) || !res || !res.ok) return res;
      return res.json().then(function (data) {
        var decorated = Array.isArray(data) ? remember(data) : data;
        return new Response(JSON.stringify(decorated), {
          status: res.status,
          statusText: res.statusText,
          headers: { 'Content-Type': 'application/json' }
        });
      });
    });
  };

  function badgeHtml(t) {
    if (!t) return '';
    if (t.isTeaching) {
      return '<span class="gz-term-chip gz-term-chip-teach">در حال برگزاری</span>';
    }
    if (t.isRegistration) {
      return '<span class="gz-term-chip gz-term-chip-reg">در حال ثبت نام</span>';
    }
    return '';
  }

  function loadTerms() {
    if (fetching) return Promise.resolve();
    fetching = true;
    return origFetch('/api/terms', { credentials: 'same-origin' })
      .then(function (r) { return r.ok ? r.json() : []; })
      .then(function (data) {
        remember(Array.isArray(data) ? data : []);
      })
      .catch(function () {})
      .then(function () { fetching = false; });
  }

  function enhanceSelect(sel) {
    if (!sel || sel.dataset.gzTerm === '1') return;
    var opts = Array.prototype.slice.call(sel.options || []);
    var matched = opts.filter(function (o) { return o.value === 'all' || byId[String(o.value)]; });
    if (matched.length < 2 && !opts.some(function (o) { return byId[String(o.value)]; })) return;

    sel.dataset.gzTerm = '1';
    sel.classList.add('gz-term-native');

    var wrap = document.createElement('div');
    wrap.className = 'gz-term-wrap';
    sel.parentNode.insertBefore(wrap, sel);
    wrap.appendChild(sel);

    var btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'gz-term-btn';
    wrap.appendChild(btn);

    var menu = document.createElement('div');
    menu.className = 'gz-term-menu';
    wrap.appendChild(menu);

    function render() {
      opts = Array.prototype.slice.call(sel.options || []);
      var cur = byId[String(sel.value)];
      var fallback = sel.selectedOptions && sel.selectedOptions[0] ? sel.selectedOptions[0].text : '';
      var label = (sel.value === 'all' || sel.value === '')
        ? 'همه ترم‌ها'
        : ((cur && cur.name) || fallback.replace(/\s+—\s+در حال .+$/, ''));
      btn.innerHTML = '<span class="gz-term-btn-label">' + escapeHtml(label) + '</span>' + badgeHtml(cur);
      menu.innerHTML = '';
      if (opts.some(function (o) { return o.value === 'all'; })) {
        var allBtn = document.createElement('button');
        allBtn.type = 'button';
        allBtn.className = 'gz-term-item' + (!sel.value || sel.value === 'all' ? ' is-active' : '');
        allBtn.innerHTML = '<span class="gz-term-item-name">همه ترم‌ها</span>';
        allBtn.addEventListener('click', function () {
          sel.value = 'all';
          sel.dispatchEvent(new Event('input', { bubbles: true }));
          sel.dispatchEvent(new Event('change', { bubbles: true }));
          wrap.classList.remove('open');
          render();
        });
        menu.appendChild(allBtn);
      }
      var ordered = terms.length ? terms : opts.map(function (o) { return byId[String(o.value)]; }).filter(Boolean);
      ordered.forEach(function (t) {
        if (!t) return;
        var item = document.createElement('button');
        item.type = 'button';
        item.className = 'gz-term-item' + (String(sel.value) === String(t.id) ? ' is-active' : '');
        item.innerHTML = '<span class="gz-term-item-name">' + escapeHtml(t.name) + '</span>' + badgeHtml(t);
        item.addEventListener('click', function () {
          try { sessionStorage.setItem('gz-acc-term-pinned', '1'); } catch (e) {}
          sel.value = String(t.id);
          sel.dispatchEvent(new Event('input', { bubbles: true }));
          sel.dispatchEvent(new Event('change', { bubbles: true }));
          wrap.classList.remove('open');
          render();
        });
        menu.appendChild(item);
      });
    }

    btn.addEventListener('click', function (e) {
      e.preventDefault();
      e.stopPropagation();
      var willOpen = !wrap.classList.contains('open');
      document.querySelectorAll('.gz-term-wrap.open').forEach(function (other) {
        if (other !== wrap) other.classList.remove('open');
      });
      wrap.classList.toggle('open', willOpen);
      if (willOpen) render();
    });

    sel.addEventListener('change', render);
    render();
  }

  function scan() {
    if (!terms.length) return;
    document.querySelectorAll('select').forEach(enhanceSelect);
  }

  function closeAll(e) {
    var target = e.target;
    document.querySelectorAll('.gz-term-wrap.open').forEach(function (wrap) {
      if (!wrap.contains(target)) wrap.classList.remove('open');
    });
    document.querySelectorAll('button').forEach(function (addBtn) {
      if ((addBtn.textContent || '').indexOf('Add new term') === -1) return;
      var dropdown = addBtn.parentElement;
      if (!dropdown) return;
      var rect = dropdown.getBoundingClientRect();
      if (rect.height < 80 || rect.width < 140) return;
      var wrap = dropdown.parentElement;
      if (!wrap || wrap.contains(target)) return;
      var toggle = wrap.querySelector('button');
      if (toggle && toggle !== addBtn) toggle.click();
    });
  }

  document.addEventListener('pointerdown', closeAll, true);
  document.addEventListener('keydown', function (e) {
    if (e.key !== 'Escape') return;
    document.querySelectorAll('.gz-term-wrap.open').forEach(function (wrap) {
      wrap.classList.remove('open');
    });
  });

  function loggedIn() {
    try { return !!sessionStorage.getItem('ghazal-user'); } catch (err) { return false; }
  }

  function tick() {
    var user = loggedIn();
    if (user && !lastUser) {
      lastUser = true;
      loadTerms().then(scan);
      return;
    }
    if (!user) {
      lastUser = false;
      return;
    }
    scan();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', tick);
  } else {
    tick();
  }
  setInterval(tick, 1800);
  var scanTimer = 0;
  new MutationObserver(function () {
    if (!lastUser) return;
    if (scanTimer) return;
    scanTimer = window.setTimeout(function () {
      scanTimer = 0;
      scan();
    }, 200);
  }).observe(document.getElementById('root') || document.documentElement, { childList: true, subtree: true });
})();
