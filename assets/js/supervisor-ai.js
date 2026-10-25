(function () {
    var root = document.getElementById('sai-root');
    if (!root) return;

    var isPage = root.classList.contains('is-page') || root.getAttribute('data-mode') === 'page';
    var toggle = root.querySelector('.sai-toggle');
    var panel = root.querySelector('.sai-panel');
    var log = root.querySelector('.sai-log');
    var form = root.querySelector('.sai-form');
    var input = root.querySelector('textarea');
    var sendBtn = root.querySelector('.sai-send');
    var csrf = root.getAttribute('data-csrf') || '';
    var messages = [];
    var lastPair = null;

    function setStats(stats) {
        if (!stats) return;
        root.querySelectorAll('.sai-stat b[data-k]').forEach(function (el) {
            var k = el.getAttribute('data-k');
            if (Object.prototype.hasOwnProperty.call(stats, k)) {
                el.textContent = String(stats[k]);
            }
        });
    }

    function toast(text, kind) {
        var el = document.createElement('div');
        el.className = 'sai-toast is-' + (kind || 'ok');
        el.textContent = text;
        panel.appendChild(el);
        setTimeout(function () {
            el.classList.add('is-out');
            setTimeout(function () { el.remove(); }, 280);
        }, 2400);
    }

    function postFeedback(action, pair) {
        return fetch('ajax-supervisor-ai-feedback.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                csrf: csrf,
                action: action,
                question: pair && pair.question ? pair.question : '',
                answer: pair && pair.answer ? pair.answer : ''
            })
        }).then(function (res) {
            return res.json().then(function (data) {
                return { ok: res.ok, data: data };
            });
        });
    }

    function markVote(bar, kind) {
        bar.querySelectorAll('.sai-act[data-act="like"], .sai-act[data-act="dislike"]').forEach(function (btn) {
            btn.classList.toggle('is-on', btn.getAttribute('data-act') === kind);
        });
    }

    function addActions(wrap, pair) {
        var bar = document.createElement('div');
        bar.className = 'sai-actions';
        bar._pair = pair;
        [
            { act: 'like', label: 'لایک' },
            { act: 'dislike', label: 'دیس‌لایک' },
            { act: 'report', label: 'گزارش' }
        ].forEach(function (item) {
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'sai-act sai-act-' + item.act;
            btn.setAttribute('data-act', item.act);
            btn.textContent = item.label;
            bar.appendChild(btn);
        });
        bar.addEventListener('click', function (e) {
            var btn = e.target.closest('.sai-act');
            if (!btn || btn.disabled) return;
            var act = btn.getAttribute('data-act');
            var current = bar._pair || lastPair;
            if (!current || !current.question || !current.answer) {
                toast('اول یک سؤال بپرسید.', 'err');
                return;
            }
            btn.disabled = true;
            postFeedback(act, current).then(function (out) {
                if (!out.data || !out.data.success) {
                    toast((out.data && out.data.message) ? out.data.message : 'انجام نشد.', 'err');
                    return;
                }
                if (out.data.stats) setStats(out.data.stats);
                if (act === 'like' || act === 'dislike') {
                    markVote(bar, act);
                    toast(act === 'like' ? 'لایک ثبت شد.' : 'دیس‌لایک ثبت شد.');
                } else {
                    btn.classList.add('is-on');
                    toast('گزارش به کانال AI Training ارسال شد.');
                }
            }).catch(function () {
                toast('ارتباط قطع شد. دوباره امتحان کنید.', 'err');
            }).finally(function () {
                btn.disabled = false;
            });
        });
        wrap.appendChild(bar);
        return bar;
    }

    function addBubble(text, kind, pair) {
        var wrap = document.createElement('div');
        wrap.className = 'sai-turn is-' + kind;
        var el = document.createElement('div');
        el.className = 'sai-bubble is-' + kind;
        el.textContent = text;
        wrap.appendChild(el);
        if (kind === 'bot' && pair && pair.question) {
            addActions(wrap, pair);
        }
        log.appendChild(wrap);
        log.scrollTop = log.scrollHeight;
        return wrap;
    }

    function setOpen(open) {
        root.classList.toggle('is-open', open);
        if (toggle) {
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        }
        if (open && input) {
            setTimeout(function () { input.focus(); }, 50);
        }
    }

    if (toggle) {
        toggle.addEventListener('click', function () {
            setOpen(!root.classList.contains('is-open'));
        });
    } else if (isPage) {
        setOpen(true);
    }

    root.querySelectorAll('.sai-chip').forEach(function (chip) {
        chip.addEventListener('click', function () {
            input.value = chip.getAttribute('data-q') || chip.textContent;
            setOpen(true);
            input.focus();
        });
    });

    input.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            form.dispatchEvent(new Event('submit', { cancelable: true }));
        }
    });

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        var text = (input.value || '').trim();
        if (!text || sendBtn.disabled) return;
        input.value = '';
        addBubble(text, 'user');
        messages.push({ role: 'user', content: text });
        sendBtn.disabled = true;
        var wait = addBubble('در حال پرس‌وجو از پنل…', 'bot');

        fetch('ajax-supervisor-ai.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ csrf: csrf, messages: messages })
        }).then(function (res) {
            return res.json().then(function (data) {
                return { ok: res.ok, data: data };
            });
        }).then(function (out) {
            wait.remove();
            if (!out.data || !out.data.success) {
                var err = (out.data && out.data.message) ? out.data.message : 'خطا در پاسخ دستیار.';
                addBubble(err, 'err');
                return;
            }
            var reply = out.data.reply || '';
            lastPair = { question: text, answer: reply };
            addBubble(reply, 'bot', lastPair);
            messages.push({ role: 'assistant', content: reply });
            if (messages.length > 12) {
                messages = messages.slice(-12);
            }
        }).catch(function () {
            wait.remove();
            addBubble('ارتباط با دستیار قطع شد. دوباره امتحان کنید.', 'err');
        }).finally(function () {
            sendBtn.disabled = false;
        });
    });
})();
