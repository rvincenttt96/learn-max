(function () {
  var pendingClass = 'gz-login-pending';
  var handoffClass = 'gz-auth-handoff';
  var bootClass = 'gz-booting';
  var readyClass = 'gz-app-ready';
  var clockTimer = 0;
  var TAB_TITLE = "حسابداری آموزشگاه غزال";
  var TAB_TITLE_MARKED = "\u2067" + TAB_TITLE + "\u2069";
  var origFetch = window.fetch;
  var handoffTimer = 0;
  var handoffPoll = 0;
  var handoffBound = false;
  var authSettled = false;
  var APP_CSS = '/assets/index-BzoE7TQo.css?v=acc-yekan2';
  var APP_JS = '/assets/index-DMmAISFC.js?v=clock-fix1';
  var APP_EXTRAS = ['/term-menu.js?v=term-tags-9', '/payroll.js?v=pay-boot4', '/acc-ui.js?v=mobi2', '/bookstore.js?v=books3'];

  function rtlTabTitle() {
    var el = document.querySelector("title");
    if (!el) {
      el = document.createElement("title");
      if (document.head) document.head.insertBefore(el, document.head.firstChild);
    }
    if (el) {
      el.setAttribute("dir", "rtl");
      el.setAttribute("lang", "fa");
      if (el.textContent !== TAB_TITLE_MARKED) el.textContent = TAB_TITLE_MARKED;
    }
    if (document.title !== TAB_TITLE_MARKED) document.title = TAB_TITLE_MARKED;
  }
  rtlTabTitle();
  setInterval(rtlTabTitle, 4000);

  function markMobileShell() {
    var mq = window.matchMedia ? window.matchMedia('(max-width: 900px)') : null;
    function apply() {
      document.documentElement.classList.toggle('gz-acc-mobile', !!(mq && mq.matches));
    }
    apply();
    if (mq && mq.addEventListener) mq.addEventListener('change', apply);
    else if (mq && mq.addListener) mq.addListener(apply);
  }
  markMobileShell();

  function hasUser() {
    try {
      return !!sessionStorage.getItem('ghazal-user');
    } catch (err) {
      return false;
    }
  }

  function bootUser() {
    try {
      var boot = window.__GHAZAL_ACC_BOOT;
      return !!(boot && boot.user && boot.user.username);
    } catch (err) {
      return false;
    }
  }

  function hasSessionHint() {
    return hasUser() || bootUser();
  }

  function rememberUser(user) {
    if (!user || !user.username) return;
    try {
      sessionStorage.setItem('ghazal-user', JSON.stringify(user));
    } catch (err) {}
    try {
      window.__GHAZAL_ACC_BOOT = { user: user };
    } catch (err) {}
  }

  function clearUser() {
    try {
      sessionStorage.removeItem('ghazal-user');
    } catch (err) {}
    try {
      window.__GHAZAL_ACC_BOOT = { user: null };
    } catch (err) {}
  }

  function ready(fn) {
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', fn);
    } else {
      fn();
    }
  }

  function loginEl() {
    return document.getElementById('gz-login');
  }

  function bootEl() {
    return document.getElementById('gz-boot');
  }

  var appStarted = false;

  function hasAsset(selector, url) {
    var nodes = document.querySelectorAll(selector);
    var base = String(url || '').split('?')[0];
    for (var i = 0; i < nodes.length; i++) {
      var val = nodes[i].getAttribute('href') || nodes[i].getAttribute('src') || nodes[i].href || nodes[i].src || '';
      if (val.indexOf(base) !== -1) return nodes[i];
    }
    return null;
  }

  function loadApp(cb) {
    if (appStarted) {
      if (cb) window.setTimeout(cb, 0);
      return;
    }
    appStarted = true;
    if (!hasAsset('link[rel="stylesheet"]', APP_CSS)) {
      var css = document.createElement('link');
      css.rel = 'stylesheet';
      css.setAttribute('crossorigin', 'anonymous');
      css.href = APP_CSS;
      document.head.appendChild(css);
    }

    APP_EXTRAS.forEach(function (src) {
      if (hasAsset('script', src)) return;
      var s = document.createElement('script');
      s.src = src;
      document.body.appendChild(s);
    });

    var done = false;
    function finish() {
      if (done) return;
      done = true;
      if (cb) cb();
    }
    var existing = hasAsset('script', APP_JS);
    if (existing) {
      existing.addEventListener('load', finish);
      existing.addEventListener('error', finish);
      window.setTimeout(finish, 400);
      return;
    }
    var mod = document.createElement('script');
    mod.type = 'module';
    mod.crossOrigin = 'anonymous';
    mod.src = APP_JS;
    mod.addEventListener('load', finish);
    mod.addEventListener('error', finish);
    document.body.appendChild(mod);
    window.setTimeout(finish, 1600);
  }

  function showLoginNow() {
    if (handoffTimer) {
      window.clearTimeout(handoffTimer);
      handoffTimer = 0;
    }
    var shell = loginEl();
    var boot = bootEl();
    if (shell) {
      shell.hidden = false;
      shell.removeAttribute('hidden');
      shell.style.setProperty('display', 'block', 'important');
    }
    if (boot) {
      boot.hidden = true;
      boot.style.removeProperty('display');
    }
    handoffBound = false;
    if (handoffPoll) {
      window.clearInterval(handoffPoll);
      handoffPoll = 0;
    }
    document.documentElement.classList.add(pendingClass);
    document.documentElement.classList.remove(handoffClass, bootClass, readyClass);
  }

  function showAppNow() {
    if (handoffTimer) {
      window.clearTimeout(handoffTimer);
      handoffTimer = 0;
    }
    if (handoffPoll) {
      window.clearInterval(handoffPoll);
      handoffPoll = 0;
    }
    var shell = loginEl();
    var boot = bootEl();
    if (shell) {
      shell.hidden = true;
      shell.style.setProperty('display', 'none', 'important');
    }
    if (boot) {
      boot.hidden = true;
      boot.style.removeProperty('display');
    }
    if (intendedSection() === 'payroll') {
      document.documentElement.classList.add('gz-pay-open');
      var pay = document.getElementById('gz-pay');
      var main = document.querySelector('#root main');
      if (pay && main && pay.parentNode !== main) main.appendChild(pay);
    }
    document.documentElement.classList.remove(pendingClass, handoffClass, bootClass);
    document.documentElement.classList.add(readyClass);
    try {
      window.dispatchEvent(new Event('gz-app-ready'));
    } catch (err) {}
    pinHeader();
  }

  function beginHandoff() {
    loadApp();
    var shell = loginEl();
    var boot = bootEl();
    if (shell) {
      shell.hidden = true;
      shell.style.setProperty('display', 'none', 'important');
    }
    if (boot) {
      boot.hidden = false;
      boot.removeAttribute('hidden');
      boot.style.removeProperty('display');
    }
    document.documentElement.classList.remove(pendingClass, readyClass);
    document.documentElement.classList.add(handoffClass, bootClass);
    watchHandoff();
  }

  function showShell(shell) {
    showLoginNow();
    if (shell) {
      shell.hidden = false;
      shell.removeAttribute('hidden');
    }
  }

  function hideShell(shell) {
    if (shell) shell.hidden = true;
    showAppNow();
  }

  function intendedSection() {
    try {
      var raw = String(location.hash || '').replace(/^#\/?/, '').split('?')[0].toLowerCase();
      if (raw) return raw;
      return sessionStorage.getItem('gz-acc-section') || localStorage.getItem('gz-acc-section') || '';
    } catch (err) {
      return '';
    }
  }

  function panelLooksReady() {
    var section = intendedSection();
    var root = document.getElementById('root');
    var text = (root && root.textContent) || '';
    if (/ورود به پنل غزال|ورود به حسابداری/.test(text) && !/دسترسی:|Sign out/.test(text)) return false;

    if (section === 'payroll') {
      var pay = document.getElementById('gz-pay');
      if (!pay || pay.hidden) return false;
      if (pay.getAttribute('data-ready') !== '1') return false;
      return /دسترسی:|Sign out/.test(text);
    }
    if (section === 'bookstore') {
      var books = document.getElementById('gz-books');
      if (!books || books.hidden) return false;
      return ((books.textContent || '').length > 40);
    }
    if (!/دسترسی:|Sign out/.test(text)) return false;
    if (section === 'registration') return /Registration|ثبت‌نام|ثبت نام/.test(text);
    if (section === 'expenses') return /Expenses|هزینه/.test(text);
    if (section === 'overview' || !section) return /داشبورد مدیریتی/.test(text);
    return true;
  }

  function clockParts(now) {
    var weekday = new Intl.DateTimeFormat('fa-IR-u-nu-latn', { timeZone: 'Asia/Tehran', weekday: 'long' }).format(now);
    var date = new Intl.DateTimeFormat('fa-IR-u-nu-latn', { timeZone: 'Asia/Tehran', year: 'numeric', month: 'long', day: 'numeric' }).format(now);
    var time = new Intl.DateTimeFormat('en-GB', { timeZone: 'Asia/Tehran', hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: false }).format(now);
    return { weekday: weekday, date: date, time: time };
  }

  function tickClock(el) {
    var parts = clockParts(new Date());
    el.innerHTML = '<div class="d">' + parts.weekday + '، ' + parts.date + '</div><div class="t">' + parts.time + '</div>';
  }

  function findHeader() {
    return document.querySelector('#root header');
  }

  function mountClock() {
    var leftover = document.getElementById('ghazal-live-clock');
    if (leftover) leftover.hidden = true;

    var header = findHeader();
    if (!header) return;
    var clocks = header.querySelectorAll('[data-gz-clock="1"]');
    if (clocks.length > 1) {
      var injected = document.getElementById('gz-tehran-clock');
      if (injected) injected.remove();
    }
    if (header.querySelector('[data-gz-clock="1"]')) return;
    if (document.getElementById('gz-tehran-clock')) return;

    var el = document.createElement('div');
    el.id = 'gz-tehran-clock';
    el.className = 'gz-tehran-clock';
    el.setAttribute('data-gz-clock', '1');
    var right = header.querySelector(':scope > div:last-child');
    if (right) {
      right.insertBefore(el, right.firstChild);
    } else {
      header.appendChild(el);
    }
    if (clockTimer) window.clearInterval(clockTimer);
    clockTimer = window.setInterval(function () {
      var node = document.getElementById('gz-tehran-clock');
      if (!node) {
        mountClock();
        return;
      }
      tickClock(node);
    }, 1000);
  }

  function pinHeader() {
    var header = findHeader();
    var scroller = document.querySelector('#root main') || document.querySelector('#root > div > div > main');
    var shell = document.querySelector('#root > div');
    if (shell) {
      shell.style.height = '100%';
      shell.style.maxHeight = '100%';
      shell.style.overflow = 'hidden';
    }
    if (scroller) {
      scroller.style.minHeight = '0';
      scroller.style.overflowY = 'auto';
    }
    if (header) {
      header.style.position = 'relative';
      header.style.top = 'auto';
      header.style.zIndex = '50';
      header.style.flexShrink = '0';
    }
    mountClock();
    var n = 0;
    (function retry() {
      n += 1;
      if (document.getElementById('gz-tehran-clock') || document.querySelector('#root [data-gz-clock="1"]')) return;
      mountClock();
      if (n < 40) window.setTimeout(retry, 250);
    })();
  }

  function watchHandoff() {
    if (!document.documentElement.classList.contains(handoffClass)
      && !document.documentElement.classList.contains(bootClass)) return;
    if (handoffBound) {
      if (panelLooksReady()) showAppNow();
      return;
    }
    handoffBound = true;
    var root = document.getElementById('root');
    var done = false;

    function finishApp() {
      if (done) return;
      done = true;
      if (handoffPoll) {
        window.clearInterval(handoffPoll);
        handoffPoll = 0;
      }
      showAppNow();
      if (obs) obs.disconnect();
    }

    function finishLogin() {
      if (done) return;
      done = true;
      if (handoffPoll) {
        window.clearInterval(handoffPoll);
        handoffPoll = 0;
      }
      clearUser();
      showLoginNow();
      if (obs) obs.disconnect();
    }

    var obs = new MutationObserver(function () {
      if (panelLooksReady()) finishApp();
    });
    var obsTarget = document.body || root;
    if (obsTarget) obs.observe(obsTarget, { childList: true, subtree: true, characterData: true });
    if (panelLooksReady()) finishApp();
    if (handoffPoll) window.clearInterval(handoffPoll);
    handoffPoll = window.setInterval(function () {
      if (done) {
        window.clearInterval(handoffPoll);
        handoffPoll = 0;
        return;
      }
      if (panelLooksReady()) finishApp();
    }, 80);

    if (handoffTimer) window.clearTimeout(handoffTimer);
    handoffTimer = window.setTimeout(function () {
      if (done) return;
      if (panelLooksReady() || hasSessionHint() || authSettled || window.__ghazalAccTookAuth) {
        finishApp();
        return;
      }
      finishLogin();
    }, 20000);
  }

  function probeSession() {
    origFetch('/api/me', { credentials: 'same-origin', headers: { Accept: 'application/json' } })
      .then(function (res) {
        if (!res || !res.ok) throw new Error('me');
        return res.json();
      })
      .then(function (json) {
        if (json && json.user) {
          authSettled = true;
          rememberUser(json.user);
          beginHandoff();
          return;
        }
        clearUser();
        showLoginNow();
      })
      .catch(function () {
        if (hasSessionHint() || authSettled) return;
        showLoginNow();
      });
  }

  window.fetch = function (input, init) {
    var url = typeof input === 'string' ? input : (input && input.url) || '';
    return origFetch.apply(this, arguments).then(function (res) {
      try {
        if (typeof url === 'string' && url.indexOf('/api/me') !== -1) {
          if (res && res.status === 401) {
            if (authSettled) return;
            clearUser();
            showLoginNow();
          } else if (res && res.ok) {
            res.clone().json().then(function (json) {
              if (json && json.user) {
                authSettled = true;
                rememberUser(json.user);
                return;
              }
              if (authSettled || hasSessionHint()) return;
              clearUser();
              showLoginNow();
            }).catch(function () {});
          }
        }
      } catch (err) {}
      return res;
    });
  };

  if (hasSessionHint()) loadApp();

  ready(function () {
    var shell = loginEl();
    var form = document.getElementById('gz-login-form');
    var leftover = document.getElementById('ghazal-live-clock');
    if (leftover) leftover.hidden = true;
    if (!shell || !form) return;

    if (hasSessionHint()) beginHandoff();
    else showLoginNow();
    probeSession();

    var user = document.getElementById('gz-user');
    var pass = document.getElementById('gz-pass');
    var err = document.getElementById('gz-login-error');
    var btn = document.getElementById('gz-login-btn');
    var toggle = document.getElementById('gz-pass-toggle');
    var btnLabel = btn ? btn.querySelector('.gz-submit__label') : null;

    shell.addEventListener('pointermove', function (e) {
      shell.style.setProperty('--mx', e.clientX + 'px');
      shell.style.setProperty('--my', e.clientY + 'px');
    });

    if (toggle && pass) {
      toggle.addEventListener('click', function () {
        var show = pass.type === 'password';
        pass.type = show ? 'text' : 'password';
        toggle.setAttribute('aria-pressed', show ? 'true' : 'false');
        toggle.textContent = show ? 'پنهان' : 'نمایش';
      });
    }

    if (user && document.documentElement.classList.contains(pendingClass)) {
      user.focus();
    }

    form.addEventListener('submit', function (e) {
      e.preventDefault();
      if (!user || !pass || !btn) return;

      var username = user.value.trim();
      var password = pass.value;
      if (!username || !password) {
        showError('نام کاربری و رمز عبور را وارد کنید');
        return;
      }

      setBusy(true);
      hideError();

      origFetch('/api/login', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          Accept: 'application/json'
        },
        credentials: 'same-origin',
        body: JSON.stringify({ username: username, password: password })
      })
        .then(function (res) {
          return res.json().then(function (json) {
            return { res: res, json: json || {} };
          });
        })
        .then(function (pack) {
          if (pack.json.success && pack.json.user) {
            rememberUser(pack.json.user);
            authSettled = true;
            window.dispatchEvent(new CustomEvent('ghazal-acc-login', { detail: pack.json.user }));
            beginHandoff();
            window.setTimeout(function () {
              if (panelLooksReady() || window.__ghazalAccTookAuth) return;
              window.location.replace(window.location.pathname || '/');
            }, 800);
            return;
          }
          throw new Error(pack.json.message || pack.json.error || 'نام کاربری یا رمز عبور اشتباه است');
        })
        .catch(function (ex) {
          showError(ex.message || 'خطا در برقراری ارتباط با سرور');
          setBusy(false);
          showLoginNow();
        });
    });

    function setBusy(on) {
      btn.disabled = on;
      btn.classList.toggle('is-busy', on);
      if (btnLabel) {
        btnLabel.textContent = on ? 'در حال بررسی...' : 'ورود به پنل';
      }
    }

    function showError(message) {
      if (!err) return;
      err.hidden = false;
      err.textContent = message;
    }

    function hideError() {
      if (!err) return;
      err.hidden = true;
      err.textContent = '';
    }
  });
})();
