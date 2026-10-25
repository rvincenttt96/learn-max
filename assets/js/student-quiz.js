(function () {
    const root = document.querySelector('[data-qb-student]');
    if (!root) {
        return;
    }
    let boot = {};
    try {
        boot = JSON.parse(root.getAttribute('data-boot') || '{}');
    } catch (err) {
        boot = {};
    }
    const ajax = boot.ajax || 'ajax-quiz.php';
    const examId = parseInt(boot.exam_id || '0', 10);
    const takeEl = document.getElementById('qbTake');
    const alertBox = document.getElementById('qbAlert');
    const progress = document.getElementById('qbProgress');
    const timerEl = document.getElementById('qbTimer');
    const timerText = document.getElementById('qbTimerText');
    const timerHint = document.getElementById('qbTimerHint');

    let exam = null;
    let attempt = null;
    let remaining = 0;
    let tick = null;
    let saving = false;
    let dirty = false;
    let utterance = null;

    function esc(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function showAlert(msg, ok) {
        if (!alertBox) {
            return;
        }
        alertBox.hidden = !msg;
        alertBox.className = 'qb-alert ' + (ok ? 'ok' : 'err');
        alertBox.textContent = msg || '';
    }

    function busy(on) {
        if (progress) {
            progress.hidden = !on;
        }
    }

    function post(payload) {
        const body = new FormData();
        Object.keys(payload).forEach(function (k) {
            if (payload[k] !== undefined && payload[k] !== null) {
                body.append(k, payload[k]);
            }
        });
        body.append('exam_id', String(examId));
        return fetch(ajax, { method: 'POST', body: body, credentials: 'same-origin' })
            .then(function (r) {
                const ct = r.headers.get('content-type') || '';
                if (ct.indexOf('json') === -1) {
                    throw new Error('نشست منقضی شده. صفحه را رفرش کنید.');
                }
                return r.json();
            })
            .then(function (data) {
                if (!data || !data.success) {
                    throw new Error((data && data.message) || 'خطا.');
                }
                return data;
            });
    }

    function apply(data) {
        exam = data.exam;
        attempt = data.attempt;
        remaining = attempt ? parseInt(attempt.remaining_sec || '0', 10) : 0;
        render();
        startTimer();
    }

    function done() {
        return attempt && (attempt.status === 'submitted' || attempt.status === 'graded');
    }

    function isWriting() {
        return !!(exam && exam.is_writing);
    }

    function collectAnswers() {
        const answers = {};
        if (!takeEl) {
            return answers;
        }
        takeEl.querySelectorAll('[data-qid]').forEach(function (box) {
            const id = box.getAttribute('data-qid');
            const checked = box.querySelector('input[type="radio"]:checked');
            const text = box.querySelector('textarea, input[type="text"]');
            if (checked) {
                answers[id] = checked.value;
            } else if (text) {
                answers[id] = text.value;
            }
        });
        return answers;
    }

    function fmt(sec) {
        sec = Math.max(0, parseInt(sec, 10) || 0);
        const m = Math.floor(sec / 60);
        const s = sec % 60;
        return (m < 10 ? '0' : '') + m + ':' + (s < 10 ? '0' : '') + s;
    }

    function startTimer() {
        if (tick) {
            clearInterval(tick);
            tick = null;
        }
        if (!timerEl) {
            return;
        }
        if (!attempt || done()) {
            timerEl.hidden = true;
            return;
        }
        timerEl.hidden = false;
        const paint = function () {
            if (timerText) {
                timerText.textContent = fmt(remaining);
            }
            if (timerHint && !done()) {
                timerHint.textContent = isWriting() ? 'زمان نوشتن' : 'زمان باقی‌مانده';
            }
            if (remaining <= 60) {
                timerEl.classList.add('qb-timer-low');
            } else {
                timerEl.classList.remove('qb-timer-low');
            }
        };
        paint();
        tick = setInterval(function () {
            remaining -= 1;
            paint();
            if (remaining <= 0) {
                clearInterval(tick);
                tick = null;
                submitQuiz(true);
            }
        }, 1000);
    }

    function playScript(text, btn) {
        if (!text) {
            return;
        }
        if (!window.speechSynthesis) {
            showAlert('پخش صوت روی این مرورگر نیست. متن لیسنینگ را معلم در کلاس پخش می‌کند.', false);
            return;
        }
        window.speechSynthesis.cancel();
        if (utterance && btn && btn.getAttribute('data-playing') === '1') {
            btn.setAttribute('data-playing', '0');
            btn.textContent = 'پخش لیسنینگ';
            utterance = null;
            return;
        }
        utterance = new SpeechSynthesisUtterance(text);
        utterance.lang = 'en-US';
        utterance.rate = 0.92;
        utterance.onend = function () {
            if (btn) {
                btn.setAttribute('data-playing', '0');
                btn.textContent = 'پخش لیسنینگ';
            }
            utterance = null;
        };
        if (btn) {
            btn.setAttribute('data-playing', '1');
            btn.textContent = 'توقف';
        }
        window.speechSynthesis.speak(utterance);
    }

    function optionHtml(q, given, review) {
        const opts = (q.options || []).slice();
        if (q.type === 'tf' && opts.length < 2) {
            opts.push('True', 'False');
        }
        if (!opts.length) {
            return '';
        }
        return '<div class="qb-take-opts">' + opts.map(function (opt) {
            const sel = String(given || '') === String(opt) ? ' checked' : '';
            const dis = review ? ' disabled' : '';
            let extra = '';
            if (review && q.answer) {
                if (String(opt) === String(q.answer) || (q.ok && String(opt) === String(given))) {
                    extra = String(opt) === String(q.answer) ? ' qb-opt-ok' : '';
                }
                if (given && String(opt) === String(given) && !q.ok) {
                    extra = ' qb-opt-bad';
                }
            }
            return '<label class="qb-opt' + extra + '"><input type="radio" name="q-' + esc(q.id) + '" value="' + esc(opt) + '"' + sel + dis + '> <span>' + esc(opt) + '</span></label>';
        }).join('') + '</div>';
    }

    function questionHtml(q, i, given, review) {
        if (isWriting() || q.type === 'writing') {
            const mark = review
                ? (q.pending ? '<span class="qb-pill qb-pill-extracting">منتظر معلم</span>'
                    : (attempt && attempt.score_total != null
                        ? '<span class="qb-pill qb-pill-ready">نمره داده شد</span>'
                        : '<span class="qb-pill qb-pill-draft">ارسال شده</span>'))
                : '';
            return '<div class="qb-topic" data-qid="' + esc(q.id) + '">'
                + '<div class="qb-q-head"><div class="qb-topic-kicker">Writing topic</div>' + mark + '</div>'
                + '<div class="qb-topic-prompt">' + esc(q.prompt).replace(/\n/g, '<br>') + '</div>'
                + '<textarea ' + (review ? 'readonly ' : '') + 'placeholder="Write a few lines about this topic.">' + esc(given || '') + '</textarea>'
                + '</div>';
        }
        const mark = review
            ? (q.pending ? '<span class="qb-pill qb-pill-extracting">منتظر معلم</span>'
                : (q.ok === true ? '<span class="qb-pill qb-pill-ready">درست</span>'
                    : (q.ok === false ? '<span class="qb-pill qb-pill-failed">نادرست</span>'
                        : (q.writing ? '<span class="qb-pill qb-pill-draft">رایتینگ</span>' : ''))))
            : '';
        let body = '';
        if (q.type === 'mc' || q.type === 'tf') {
            body = optionHtml(q, given, review);
        } else {
            body = '<input type="text" ' + (review ? 'readonly ' : '') + 'value="' + esc(given || '') + '" placeholder="Your answer">';
        }
        let key = '';
        if (review && q.answer) {
            key = '<div class="qb-key">Correct: ' + esc(q.answer) + '</div>';
        }
        const n = i + 1;
        return '<div class="qb-q" data-qid="' + esc(q.id) + '">'
            + '<div class="qb-q-head"><div><span class="qb-q-num">' + n + '</span> '
            + '<span class="qb-pill">' + esc(q.skill) + '</span></div>' + mark + '</div>'
            + '<div class="qb-q-prompt"><span class="qb-q-n">' + n + '.</span> '
            + esc(q.prompt).replace(/\n/g, '<br>') + '</div>'
            + body + key + '</div>';
    }

    function render() {
        if (!takeEl || !exam) {
            return;
        }
        takeEl.hidden = false;
        if (!attempt) {
            const writing = isWriting();
            takeEl.innerHTML = '<div class="qb-empty"><h5>' + (writing ? 'تاپیک رایتینگ' : 'آماده‌اید؟') + '</h5>'
                + '<p>' + (writing
                    ? ('یک تاپیک از درس می‌بینید و چند خط درباره‌اش می‌نویسید. تایمر ' + esc(exam.minutes) + ' دقیقه‌ای می‌افتد و دیگر متوقف نمی‌شود. نمره را معلم می‌دهد.')
                    : ('با شروع آزمون، تایمر ' + esc(exam.minutes) + ' دقیقه‌ای می‌افتد و دیگر متوقف نمی‌شود. یک‌بار فرصت دارید.'))
                + '</p>'
                + '<button type="button" class="qb-btn qb-btn-red" id="qbBegin">' + (writing ? 'شروع رایتینگ' : 'شروع آزمون') + '</button></div>';
            const begin = document.getElementById('qbBegin');
            if (begin) {
                begin.addEventListener('click', function () {
                    begin.disabled = true;
                    busy(true);
                    post({ action: 'start' }).then(function (data) {
                        apply(data.data);
                    }).catch(function (err) {
                        showAlert(err.message, false);
                        begin.disabled = false;
                    }).then(function () {
                        busy(false);
                    });
                });
            }
            if (timerEl) {
                timerEl.hidden = true;
            }
            return;
        }
        const review = done();
        const writing = isWriting();
        const answers = (attempt && attempt.answers) || {};
        let extra = '';
        if (!writing && exam.passage) {
            extra += '<div class="qb-passage">' + esc(exam.passage) + '</div>';
        }
        if (!writing && exam.listening_script) {
            extra += '<div class="qb-listen"><button type="button" class="qb-btn qb-btn-red" id="qbPlay">پخش لیسنینگ</button>'
                + '<p class="qb-hint">متن اسکریپت نشان داده نمی‌شود. یک یا دو بار گوش کنید و بعد به سؤال‌ها جواب بدهید. صوت از مرورگر پخش می‌شود.</p></div>';
            if (review) {
                extra += '<div class="qb-passage"><strong>Script</strong><br>' + esc(exam.listening_script) + '</div>';
            }
        }
        if (exam.listening_repetition) {
            extra += '<div class="qb-passage"><strong>Listen and repeat</strong><br>' + esc(exam.listening_repetition) + '</div>';
        }
        let score = '';
        if (review) {
            if (attempt.score_total !== null && attempt.score_total !== undefined) {
                score = '<div class="qb-alert ok">نمره شما: ' + esc(attempt.score_total) + ' از ' + esc(attempt.score_max) + '</div>';
            } else if (attempt.pending_count > 0) {
                score = '<div class="qb-alert ok">' + (writing ? 'ارسال شد. نمره را معلم وارد می‌کند.' : 'ارسال شد. نمره رایتینگ را معلم وارد می‌کند.') + '</div>';
            }
        }
        const actions = review
            ? '<a class="qb-btn qb-btn-ghost" href="quizzes.php">بازگشت به لیست</a>'
            : '<button type="button" class="qb-btn qb-btn-ghost" id="qbSave">ذخیره موقت</button>'
                + '<button type="button" class="qb-btn qb-btn-red" id="qbSubmit">' + (writing ? 'تحویل رایتینگ' : 'تحویل آزمون') + '</button>';
        takeEl.innerHTML = score + extra
            + (exam.questions || []).map(function (q, i) {
                return questionHtml(q, i, q.given != null ? q.given : answers[q.id], review);
            }).join('')
            + '<div class="qb-take-actions">' + actions + '</div>';
        const play = document.getElementById('qbPlay');
        if (play) {
            play.addEventListener('click', function () {
                playScript(exam.listening_script, play);
            });
        }
        const saveBtn = document.getElementById('qbSave');
        const subBtn = document.getElementById('qbSubmit');
        if (saveBtn) {
            saveBtn.addEventListener('click', function () {
                saveQuiz(true);
            });
        }
        if (subBtn) {
            subBtn.addEventListener('click', function () {
                if (!window.confirm(isWriting()
                    ? 'رایتینگ تحویل شود؟ بعد از تحویل دیگر نمی‌توانید عوض کنید.'
                    : 'آزمون تحویل شود؟ بعد از تحویل دیگر نمی‌توانید عوض کنید.')) {
                    return;
                }
                submitQuiz(false);
            });
        }
        if (!review) {
            takeEl.querySelectorAll('input, textarea').forEach(function (el) {
                el.addEventListener('input', function () {
                    dirty = true;
                });
                el.addEventListener('change', function () {
                    dirty = true;
                });
            });
        }
    }

    function saveQuiz(toast) {
        if (done() || saving) {
            return Promise.resolve();
        }
        saving = true;
        return post({
            action: 'save',
            answers_json: JSON.stringify(collectAnswers())
        }).then(function (data) {
            dirty = false;
            attempt = data.data.attempt;
            exam = data.data.exam;
            if (done()) {
                apply(data.data);
            }
            if (toast) {
                showAlert(data.message, true);
            }
        }).catch(function (err) {
            showAlert(err.message, false);
        }).then(function () {
            saving = false;
        });
    }

    function submitQuiz(auto) {
        if (done() || saving) {
            return;
        }
        saving = true;
        if (timerHint && auto) {
            timerHint.textContent = 'زمان تمام شد؛ در حال ثبت…';
        }
        post({
            action: 'submit',
            answers_json: JSON.stringify(collectAnswers())
        }).then(function (data) {
            dirty = false;
            apply(data.data);
            showAlert(data.message, true);
            if (takeEl) {
                takeEl.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        }).catch(function (err) {
            showAlert(err.message, false);
        }).then(function () {
            saving = false;
        });
    }

    setInterval(function () {
        if (dirty && !done()) {
            saveQuiz(false);
        }
    }, 15000);

    window.addEventListener('beforeunload', function (e) {
        if (dirty && !done()) {
            e.preventDefault();
            e.returnValue = '';
        }
    });

    busy(true);
    post({ action: 'open' }).then(function (data) {
        apply(data.data);
        showAlert('', true);
    }).catch(function (err) {
        showAlert(err.message, false);
    }).then(function () {
        busy(false);
    });
})();
