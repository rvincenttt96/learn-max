(function () {
    const root = document.querySelector('[data-qb-teacher]');
    if (!root) {
        return;
    }
    let boot = {};
    try {
        boot = JSON.parse(root.getAttribute('data-boot') || '{}');
    } catch (err) {
        boot = {};
    }
    const ajax = boot.ajax || 'ajax-quiz-builder.php';
    const classId = parseInt(boot.class_id || '0', 10);
    const diffs = boot.difficulties || {};
    let exams = boot.exams || [];
    let current = null;

    const form = document.getElementById('qbBuildForm');
    const alertBox = document.getElementById('qbAlert');
    const progress = document.getElementById('qbProgress');
    const progressText = document.getElementById('qbProgressText');
    const genBtn = document.getElementById('qbGenBtn');
    const listEl = document.getElementById('qbExamList');
    const viewEl = document.getElementById('qbExamView');
    const bookSel = document.getElementById('qbBook');
    const fromEl = document.getElementById('qbFrom');
    const toEl = document.getElementById('qbTo');

    function toast(msg) {
        let el = document.querySelector('.qb-toast');
        if (!el) {
            el = document.createElement('div');
            el.className = 'qb-toast';
            document.body.appendChild(el);
        }
        el.textContent = msg;
        el.classList.add('show');
        clearTimeout(el._t);
        el._t = setTimeout(function () {
            el.classList.remove('show');
        }, 2800);
    }

    function showAlert(msg, ok) {
        if (!alertBox) {
            return;
        }
        alertBox.hidden = !msg;
        alertBox.className = 'qb-alert ' + (ok ? 'ok' : 'err');
        alertBox.textContent = msg || '';
    }

    function busy(on, text) {
        if (progress) {
            progress.hidden = !on;
        }
        if (progressText && text) {
            progressText.textContent = text;
        }
        if (genBtn) {
            genBtn.disabled = !!on;
        }
    }

    function post(payload) {
        const body = new FormData();
        Object.keys(payload).forEach(function (k) {
            if (payload[k] !== undefined && payload[k] !== null) {
                body.append(k, payload[k]);
            }
        });
        if (classId) {
            body.append('class_id', String(classId));
        }
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
                    throw new Error((data && data.message) || 'خطا در ذخیره.');
                }
                return data;
            });
    }

    function esc(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function pagesMax() {
        if (!bookSel) {
            return 1;
        }
        const opt = bookSel.options[bookSel.selectedIndex];
        return Math.max(1, parseInt(opt && opt.getAttribute('data-pages') || '1', 10));
    }

    function syncPages() {
        const max = pagesMax();
        if (fromEl) {
            fromEl.max = String(max);
            if (parseInt(fromEl.value, 10) > max) {
                fromEl.value = '1';
            }
        }
        if (toEl) {
            toEl.max = String(max);
            if (!toEl.value || parseInt(toEl.value, 10) > max) {
                toEl.value = String(Math.min(8, max));
            }
        }
    }

    function upsertExam(exam) {
        let found = false;
        exams = exams.map(function (e) {
            if (e.id === exam.id) {
                found = true;
                return exam;
            }
            return e;
        });
        if (!found) {
            exams.unshift(exam);
        }
        current = exam;
        renderList();
        renderView();
    }

    function renderList() {
        if (!listEl) {
            return;
        }
        if (!exams.length) {
            listEl.innerHTML = '<div class="qb-empty" style="padding:18px"><p class="mb-0">هنوز آزمونی برای این کلاس ساخته نشده.</p></div>';
            return;
        }
        listEl.innerHTML = '<div class="qb-exam-list">' + exams.map(function (e) {
            const active = current && current.id === e.id ? ' active' : '';
            return '<div class="qb-exam-item' + active + '" data-exam="' + e.id + '">'
                + '<div><strong>' + esc(e.template_label) + '</strong>'
                + '<div class="qb-book-meta"><span class="qb-pill qb-pill-' + esc(e.status) + '">' + esc(e.status_label) + '</span>'
                + '<span>صفحه ' + e.page_from + '–' + e.page_to + '</span>'
                + '<span>' + esc(diffs[e.difficulty] || e.difficulty) + '</span>'
                + '<span>' + (isWritingExam(e) ? 'تاپیک رایتینگ' : ((e.questions ? e.questions.length : 0) + ' سؤال')) + '</span></div></div>'
                + '<span class="qb-card-go">باز کردن</span></div>';
        }).join('') + '</div>';
    }

    function isWritingExam(exam) {
        return !!(exam && (exam.is_writing || exam.kinds === 'writing' || parseInt(exam.template_no, 10) === 7));
    }

    function isWritingTpl() {
        const tpl = (form && form.querySelector('input[name="template_no"]:checked') || {}).value;
        return String(tpl || '') === '7';
    }

    function syncGenCopy() {
        if (genBtn) {
            genBtn.textContent = isWritingTpl() ? 'ساخت تاپیک رایتینگ' : 'ساخت با هوش مصنوعی';
        }
    }

    function extraBlock(exam) {
        if (isWritingExam(exam)) {
            return '';
        }
        let html = '';
        if (exam.passage) {
            html += '<label class="qb-field"><span>Reading passage</span><textarea class="qb-passage" data-k="passage">' + esc(exam.passage) + '</textarea></label>';
        }
        if (exam.listening_script) {
            html += '<p class="qb-hint">فایل صوتی Quiz 5 در مرحله بعد اضافه می‌شود؛ اسکریپت را می‌توانید اصلاح کنید.</p>';
            html += '<label class="qb-field"><span>Listening script</span><textarea class="qb-passage" data-k="listening_script">' + esc(exam.listening_script) + '</textarea></label>';
        }
        if (exam.listening_repetition) {
            html += '<label class="qb-field"><span>Listening repetition</span><textarea class="qb-passage" data-k="listening_repetition">' + esc(exam.listening_repetition) + '</textarea></label>';
        }
        if (exam.warning) {
            html += '<div class="qb-alert err">' + esc(exam.warning) + '</div>';
        }
        return html;
    }

    function questionHtml(q, i, exam) {
        if (isWritingExam(exam) || q.type === 'writing' || q.skill === 'writing') {
            return '<div class="qb-topic" data-qid="' + esc(q.id) + '">'
                + '<div class="qb-q-head"><div><strong>تاپیک رایتینگ</strong> '
                + '<span class="qb-pill">چند خط دانش‌آموز</span></div>'
                + '<button type="button" class="qb-btn qb-btn-ghost" data-replace="' + esc(q.id) + '">تاپیک دیگر</button></div>'
                + '<label class="qb-field"><span>تاپیک (به انگلیسی)</span>'
                + '<textarea class="qb-topic-editor" data-k="prompt">' + esc(q.prompt) + '</textarea></label>'
                + '<p class="qb-hint">دانش‌آموز همین متن را می‌بیند و چند خط می‌نویسد. گزینه و پاسخ صحیح ندارد؛ نمره را بعد از تحویل شما می‌دهید.</p>'
                + '</div>';
        }
        const opts = (q.options || []).map(function (opt, n) {
            return '<input data-k="options.' + n + '" value="' + esc(opt) + '">';
        }).join('');
        return '<div class="qb-q" data-qid="' + esc(q.id) + '">'
            + '<div class="qb-q-head"><div><strong>Q' + (i + 1) + '</strong> '
            + '<span class="qb-pill">' + esc(q.skill) + '</span> '
            + '<span class="qb-pill">' + esc(q.type) + '</span></div>'
            + '<button type="button" class="qb-btn qb-btn-ghost" data-replace="' + esc(q.id) + '">جایگزینی با AI</button></div>'
            + '<label class="qb-field"><span>صورت سؤال</span><textarea data-k="prompt">' + esc(q.prompt) + '</textarea></label>'
            + (opts ? '<div class="qb-q-opts">' + opts + '</div>' : '')
            + '<label class="qb-field"><span>پاسخ صحیح (فقط برای معلم)</span><input data-k="answer" value="' + esc(q.answer) + '"></label>'
            + '</div>';
    }

    function collectExam() {
        if (!viewEl || !current) {
            return current;
        }
        const next = JSON.parse(JSON.stringify(current));
        const pass = viewEl.querySelector('[data-k="passage"]');
        const script = viewEl.querySelector('[data-k="listening_script"]');
        const rep = viewEl.querySelector('[data-k="listening_repetition"]');
        if (pass) {
            next.passage = pass.value;
        }
        if (script) {
            next.listening_script = script.value;
        }
        if (rep) {
            next.listening_repetition = rep.value;
        }
        next.questions = (next.questions || []).map(function (q) {
            const box = viewEl.querySelector('[data-qid="' + q.id + '"]');
            if (!box) {
                return q;
            }
            q.prompt = (box.querySelector('[data-k="prompt"]') || {}).value || q.prompt;
            q.answer = (box.querySelector('[data-k="answer"]') || {}).value || q.answer;
            const optInputs = box.querySelectorAll('[data-k^="options."]');
            if (optInputs.length) {
                q.options = Array.prototype.map.call(optInputs, function (el) {
                    return el.value;
                });
            }
            return q;
        });
        return next;
    }

    function renderView() {
        if (!viewEl) {
            return;
        }
        if (!current) {
            viewEl.hidden = true;
            viewEl.innerHTML = '';
            return;
        }
        viewEl.hidden = false;
        const e = current;
        viewEl.innerHTML = '<div class="qb-q-head"><div><h3>' + esc(e.template_label) + '</h3>'
            + '<div class="qb-book-meta"><span class="qb-pill qb-pill-' + esc(e.status) + '">' + esc(e.status_label) + '</span>'
            + '<span>' + esc(e.book_name) + '</span>'
            + '<span>S.' + e.session_no + ' · نمره ' + e.mark + ' · ' + e.minutes + ' دقیقه</span>'
            + (isWritingExam(e) ? '<span class="qb-pill">تاپیک رایتینگ</span>' : '')
            + '</div></div>'
            + '<div class="qb-book-actions">'
            + '<button type="button" class="qb-btn qb-btn-ghost" data-save="1">ذخیره اصلاح</button>'
            + (e.status === 'ready'
                ? '<button type="button" class="qb-btn qb-btn-ghost" data-status="draft">برگشت به پیش‌نویس</button>'
                : '<button type="button" class="qb-btn qb-btn-green" data-status="ready">ارسال به کلاس</button>')
            + '<button type="button" class="qb-btn qb-btn-danger" data-del="' + e.id + '">حذف</button>'
            + '</div></div>'
            + extraBlock(e)
            + (e.questions || []).map(function (q, i) { return questionHtml(q, i, e); }).join('')
            + '<div class="qb-results" id="qbResults"></div>';
        loadResults(e.id);
    }

    function loadResults(examId) {
        const box = viewEl ? viewEl.querySelector('#qbResults') : null;
        if (!box) {
            return;
        }
        box.innerHTML = '<p class="qb-hint">در حال بارگذاری نتایج کلاس…</p>';
        post({ action: 'list_attempts', exam_id: String(examId) })
            .then(function (data) {
                renderResults(data.results || {});
            })
            .catch(function () {
                box.innerHTML = '';
            });
    }

    function renderResults(res) {
        const box = viewEl ? viewEl.querySelector('#qbResults') : null;
        if (!box) {
            return;
        }
        const rows = res.students || [];
        const avg = res.average == null ? '—' : res.average;
        let html = '<h3>نتایج دانش‌آموزان</h3>'
            + '<div class="qb-book-meta"><span>' + (res.submitted || 0) + ' از ' + (res.total || 0) + ' تحویل</span>'
            + '<span>میانگین ' + esc(avg) + (res.mark ? ' از ' + res.mark : '') + '</span></div>';
        if (!rows.length) {
            html += '<p class="qb-hint">دانش‌آموزی در این کلاس نیست.</p>';
            box.innerHTML = html;
            return;
        }
        html += '<table><thead><tr><th>دانش‌آموز</th><th>وضعیت</th><th>نمره</th>'
            + (res.has_writing ? '<th>متن شاگرد</th><th>نمره معلم</th>' : '')
            + '</tr></thead><tbody>';
        rows.forEach(function (s) {
            const score = s.score_total == null ? (s.pending_count ? 'منتظر رایتینگ' : '—') : (s.score_total + ' / ' + s.score_max);
            html += '<tr><td>' + esc(s.full_name) + '</td><td>' + esc(s.status_label) + '</td><td>' + esc(score) + '</td>';
            if (res.has_writing) {
                html += '<td class="qb-write-preview">' + esc(s.writing || '') + '</td><td>';
                if (s.attempt_id && (s.status === 'submitted' || s.status === 'graded')) {
                    html += '<form class="qb-grade-row" data-grade="' + s.attempt_id + '">'
                        + '<input type="number" min="0" max="' + esc(res.mark) + '" value="' + (s.score_total != null ? esc(s.score_total) : '') + '">'
                        + '<button type="submit" class="qb-btn qb-btn-green">ثبت</button></form>';
                } else {
                    html += '—';
                }
                html += '</td>';
            }
            html += '</tr>';
        });
        html += '</tbody></table>';
        box.innerHTML = html;
    }

    if (bookSel) {
        bookSel.addEventListener('change', syncPages);
        syncPages();
    }

    const tplBox = document.getElementById('qbTemplates');
    if (tplBox) {
        tplBox.addEventListener('change', syncGenCopy);
    }
    syncGenCopy();

    if (form) {
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            const tpl = (form.querySelector('input[name="template_no"]:checked') || {}).value;
            busy(true, String(tpl || '') === '7'
                ? 'در حال ساخت تاپیک رایتینگ از صفحه‌های کتاب… ممکن است تا یک دقیقه طول بکشد.'
                : 'در حال ساخت سؤال‌ها از صفحه‌های کتاب… ممکن است تا یک دقیقه طول بکشد.');
            showAlert('', true);
            post({
                action: 'generate',
                book_id: bookSel ? bookSel.value : '',
                page_from: fromEl ? fromEl.value : '1',
                page_to: toEl ? toEl.value : '1',
                difficulty: document.getElementById('qbDiff') ? document.getElementById('qbDiff').value : 'medium',
                template_no: tpl || '1'
            }).then(function (data) {
                upsertExam(data.exam);
                showAlert(data.message, true);
                toast(data.message);
                if (viewEl) {
                    viewEl.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            }).catch(function (err) {
                showAlert(err.message, false);
            }).then(function () {
                busy(false);
            });
        });
    }

    if (listEl) {
        listEl.addEventListener('click', function (e) {
            const item = e.target.closest('[data-exam]');
            if (!item) {
                return;
            }
            const id = parseInt(item.getAttribute('data-exam'), 10);
            current = exams.filter(function (x) { return x.id === id; })[0] || null;
            renderList();
            renderView();
        });
    }

    if (viewEl) {
        viewEl.addEventListener('submit', function (e) {
            const form = e.target.closest('[data-grade]');
            if (!form || !current) {
                return;
            }
            e.preventDefault();
            const id = form.getAttribute('data-grade');
            const input = form.querySelector('input');
            const btn = form.querySelector('button');
            if (btn) {
                btn.disabled = true;
            }
            post({
                action: 'grade_writing',
                exam_id: String(current.id),
                attempt_id: id,
                score: input ? input.value : '0'
            }).then(function (data) {
                toast(data.message);
                renderResults(data.results || {});
            }).catch(function (err) {
                showAlert(err.message, false);
            }).then(function () {
                if (btn) {
                    btn.disabled = false;
                }
            });
        });
        viewEl.addEventListener('click', function (e) {
            const replaceBtn = e.target.closest('[data-replace]');
            const saveBtn = e.target.closest('[data-save]');
            const statusBtn = e.target.closest('[data-status]');
            const delBtn = e.target.closest('[data-del]');
            if (replaceBtn && current) {
                const qid = replaceBtn.getAttribute('data-replace');
                replaceBtn.disabled = true;
                busy(true, isWritingExam(current) ? 'در حال ساخت تاپیک دیگر…' : 'در حال ساخت سؤال جایگزین…');
                post({ action: 'replace', exam_id: String(current.id), question_id: qid })
                    .then(function (data) {
                        upsertExam(data.exam);
                        toast(data.message);
                    })
                    .catch(function (err) {
                        showAlert(err.message, false);
                    })
                    .then(function () {
                        busy(false);
                        replaceBtn.disabled = false;
                    });
                return;
            }
            if (saveBtn && current) {
                const payload = collectExam();
                saveBtn.disabled = true;
                post({
                    action: 'save_questions',
                    exam_id: String(current.id),
                    questions_json: JSON.stringify({
                        passage: payload.passage || '',
                        listening_script: payload.listening_script || '',
                        listening_repetition: payload.listening_repetition || '',
                        questions: payload.questions || []
                    })
                }).then(function (data) {
                    upsertExam(data.exam);
                    toast(data.message);
                }).catch(function (err) {
                    showAlert(err.message, false);
                }).then(function () {
                    saveBtn.disabled = false;
                });
                return;
            }
            if (statusBtn && current) {
                statusBtn.disabled = true;
                post({ action: 'set_status', exam_id: String(current.id), status: statusBtn.getAttribute('data-status') })
                    .then(function (data) {
                        upsertExam(data.exam);
                        toast(data.message);
                    })
                    .catch(function (err) {
                        showAlert(err.message, false);
                    })
                    .then(function () {
                        statusBtn.disabled = false;
                    });
                return;
            }
            if (delBtn && current) {
                if (!window.confirm('این آزمون حذف شود؟')) {
                    return;
                }
                post({ action: 'delete', exam_id: String(current.id) })
                    .then(function (data) {
                        exams = exams.filter(function (x) { return x.id !== current.id; });
                        current = exams[0] || null;
                        renderList();
                        renderView();
                        toast(data.message);
                    })
                    .catch(function (err) {
                        showAlert(err.message, false);
                    });
            }
        });
    }

    renderList();
    if (exams.length) {
        current = exams[0];
        renderView();
    }
})();
