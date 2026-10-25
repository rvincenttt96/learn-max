(function () {
    const root = document.querySelector('[data-asg-teacher]');
    if (!root) {
        return;
    }
    let boot = {};
    try {
        boot = JSON.parse(root.getAttribute('data-boot') || '{}');
    } catch (err) {
        boot = {};
    }
    const ajax = boot.ajax || 'ajax-assignments.php';
    const fileBase = boot.file || 'assignment-file.php';
    const classId = parseInt(boot.class_id || '0', 10);
    const sessionMax = parseInt(boot.session_max || '14', 10);
    let items = boot.assignments || [];
    let currentId = 0;
    let filterSession = 0;

    const form = document.getElementById('asgForm');
    const alertBox = document.getElementById('asgAlert');
    const progress = document.getElementById('asgProgress');
    const progressText = document.getElementById('asgProgressText');
    const saveBtn = document.getElementById('asgSaveBtn');
    const resetBtn = document.getElementById('asgResetBtn');
    const removeFileBtn = document.getElementById('asgRemoveFileBtn');
    const listEl = document.getElementById('asgList');
    const sessionsEl = document.getElementById('asgSessions');
    const detailEl = document.getElementById('asgDetail');
    const idEl = document.getElementById('asgId');
    const sessionEl = document.getElementById('asgSession');
    const titleEl = document.getElementById('asgTitle');
    const dueEl = document.getElementById('asgDue');
    const bodyEl = document.getElementById('asgBody');
    const fileEl = document.getElementById('asgFile');
    const fileHint = document.getElementById('asgFileHint');

    function esc(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

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
        if (saveBtn) {
            saveBtn.disabled = !!on;
        }
    }

    function post(payload, extraFile) {
        const body = new FormData();
        Object.keys(payload).forEach(function (k) {
            if (payload[k] !== undefined && payload[k] !== null) {
                body.append(k, payload[k]);
            }
        });
        if (classId) {
            body.append('class_id', String(classId));
        }
        if (extraFile) {
            body.append('file', extraFile);
        }
        return fetch(ajax, { method: 'POST', body: body, credentials: 'same-origin' })
            .then(function (r) {
                const ct = r.headers.get('content-type') || '';
                if (ct.indexOf('json') === -1) {
                    throw new Error('نشست منقضی شده. صفحه را رفرش کنید.');
                }
                return r.json().then(function (data) {
                    data._http = r.status;
                    return data;
                });
            })
            .then(function (data) {
                if (!data.success) {
                    throw new Error(data.message || 'خطا');
                }
                return data;
            });
    }

    function counts() {
        const map = {};
        for (let i = 1; i <= sessionMax; i++) {
            map[i] = 0;
        }
        items.forEach(function (it) {
            const n = parseInt(it.session_no, 10);
            if (map[n] !== undefined) {
                map[n] += 1;
            }
        });
        return map;
    }

    function renderSessions() {
        if (!sessionsEl) {
            return;
        }
        const map = counts();
        let html = '<button type="button" class="asg-chip' + (filterSession === 0 ? ' is-on' : '') + '" data-s="0">همه جلسات</button>';
        for (let i = 1; i <= sessionMax; i++) {
            const n = map[i] || 0;
            html += '<button type="button" class="asg-chip' + (filterSession === i ? ' is-on' : '') + (n ? ' has-item' : '') + '" data-s="' + i + '">S.' + i + (n ? ' · ' + n : '') + '</button>';
        }
        sessionsEl.innerHTML = html;
        sessionsEl.querySelectorAll('.asg-chip').forEach(function (btn) {
            btn.addEventListener('click', function () {
                filterSession = parseInt(btn.getAttribute('data-s') || '0', 10);
                if (filterSession > 0 && sessionEl) {
                    sessionEl.value = String(filterSession);
                }
                renderSessions();
                renderList();
            });
        });
    }

    function upsert(row) {
        const id = parseInt(row.id, 10);
        let found = false;
        items = items.map(function (it) {
            if (parseInt(it.id, 10) === id) {
                found = true;
                return row;
            }
            return it;
        });
        if (!found) {
            items.unshift(row);
        }
        items.sort(function (a, b) {
            const ds = parseInt(a.session_no, 10) - parseInt(b.session_no, 10);
            return ds !== 0 ? ds : parseInt(b.id, 10) - parseInt(a.id, 10);
        });
    }

    function fillForm(row) {
        currentId = row ? parseInt(row.id, 10) : 0;
        if (idEl) {
            idEl.value = String(currentId);
        }
        if (sessionEl) {
            sessionEl.value = String(row ? row.session_no : (filterSession || 1));
        }
        if (titleEl) {
            titleEl.value = row ? (row.title || '') : '';
        }
        if (dueEl) {
            dueEl.value = row && row.due_at ? row.due_at : '';
        }
        if (bodyEl) {
            bodyEl.value = row ? (row.body || '') : '';
        }
        if (fileEl) {
            fileEl.value = '';
        }
        if (resetBtn) {
            resetBtn.hidden = !row;
        }
        if (removeFileBtn) {
            removeFileBtn.hidden = !(row && row.has_teacher_file);
        }
        if (saveBtn) {
            saveBtn.textContent = row ? 'ذخیره تغییرات' : 'انتشار برای کلاس';
        }
        if (fileHint) {
            if (row && row.has_teacher_file) {
                fileHint.textContent = 'پیوست فعلی: ' + row.teacher_file_name + ' (' + row.teacher_file_size_label + '). فایل جدید جایگزین می‌شود. سقف ' + (boot.max_label || '') + '.';
            } else {
                fileHint.textContent = 'پیوست اختیاری است. PDF، تصویر، Word یا ZIP — حداکثر ' + (boot.max_label || '۸ مگابایت') + '.';
            }
        }
    }

    function renderList() {
        if (!listEl) {
            return;
        }
        const shown = items.filter(function (it) {
            return !filterSession || parseInt(it.session_no, 10) === filterSession;
        });
        if (!shown.length) {
            const s = filterSession ? ('جلسه ' + filterSession) : 'این کلاس';
            listEl.innerHTML = '<div class="qb-empty"><h5>برای ' + esc(s) + ' تکلیفی نیست</h5><p>عنوان را بنویسید و انتشار را بزنید.</p></div>';
            return;
        }
        listEl.innerHTML = shown.map(function (it) {
            const due = it.due_label ? ('مهلت ' + it.due_label) : 'بدون مهلت';
            const file = it.has_teacher_file ? ('پیوست: ' + it.teacher_file_name) : 'بدون پیوست';
            const sub = (it.submission_count || 0) + ' / ' + (it.student_count || 0) + ' تحویل';
            return '<article class="qb-exam-item asg-item" data-id="' + it.id + '">'
                + '<div><h3>' + esc(it.title) + '</h3>'
                + '<div class="qb-card-meta"><span class="qb-pill qb-pill-ready">S.' + esc(it.session_no) + '</span>'
                + '<span>' + esc(due) + '</span><span>' + esc(file) + '</span><span>' + esc(sub) + '</span></div>'
                + (it.body ? '<p class="qb-hint">' + esc(it.body).slice(0, 180) + '</p>' : '')
                + '</div><div class="asg-item-actions">'
                + '<button type="button" class="qb-btn" data-act="subs">تحویل‌ها</button>'
                + '<button type="button" class="qb-btn" data-act="edit">ویرایش</button>'
                + (it.has_teacher_file ? '<a class="qb-btn" href="' + fileBase + '?kind=teacher&id=' + it.id + '">دانلود پیوست</a>' : '')
                + '<button type="button" class="qb-btn qb-btn-danger" data-act="del">حذف</button>'
                + '</div></article>';
        }).join('');
    }

    function loadSubs(id) {
        busy(true, 'در حال خواندن تحویل‌ها…');
        post({ action: 'submissions', id: String(id) })
            .then(function (data) {
                const asg = data.assignment || {};
                const rows = data.submissions || [];
                let html = '<h2>' + esc(asg.title || 'تحویل‌ها') + '</h2>';
                html += '<p class="qb-hint">S.' + esc(asg.session_no) + ' · ' + (data.submitted || 0) + ' از ' + (data.total || 0) + ' نفر فایل فرستاده‌اند.</p>';
                if (!rows.length) {
                    html += '<p>هنوز دانش‌آموزی در این کلاس نیست.</p>';
                } else {
                    html += '<table><thead><tr><th>دانش‌آموز</th><th>وضعیت</th><th>فایل</th><th>زمان</th></tr></thead><tbody>';
                    rows.forEach(function (r) {
                        const pill = r.status_key === 'submitted' ? 'ready' : (r.status_key === 'late' ? 'draft' : (r.status_key === 'overdue' ? 'failed' : 'pending'));
                        const file = r.submitted
                            ? '<a href="' + fileBase + '?kind=submission&id=' + r.submission_id + '">' + esc(r.file_name) + '</a> <span>' + esc(r.file_size_label) + '</span>'
                            : '—';
                        html += '<tr><td>' + esc(r.full_name) + '<div class="qb-hint">' + esc(r.username) + '</div></td>'
                            + '<td><span class="qb-pill qb-pill-' + pill + '">' + esc(r.status_label) + '</span></td>'
                            + '<td>' + file + '</td><td>' + esc(r.submitted_label || '—') + '</td></tr>';
                    });
                    html += '</tbody></table>';
                }
                if (detailEl) {
                    detailEl.hidden = false;
                    detailEl.innerHTML = html;
                    detailEl.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            })
            .catch(function (err) {
                showAlert(err.message, false);
                toast(err.message);
            })
            .then(function () {
                busy(false);
            });
    }

    if (fileHint) {
        fileHint.textContent = 'پیوست اختیاری است. PDF، تصویر، Word یا ZIP — حداکثر ' + (boot.max_label || '۸ مگابایت') + '.';
    }

    renderSessions();
    renderList();

    if (listEl) {
        listEl.addEventListener('click', function (e) {
            const btn = e.target.closest('[data-act]');
            if (!btn) {
                return;
            }
            const card = btn.closest('[data-id]');
            const id = card ? parseInt(card.getAttribute('data-id'), 10) : 0;
            const row = items.filter(function (it) { return parseInt(it.id, 10) === id; })[0];
            const act = btn.getAttribute('data-act');
            if (act === 'edit' && row) {
                fillForm(row);
                if (form) {
                    form.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            }
            if (act === 'subs') {
                loadSubs(id);
            }
            if (act === 'del') {
                if (!window.confirm('این تکلیف و فایل‌های تحویل‌شده حذف شود؟')) {
                    return;
                }
                busy(true, 'در حال حذف…');
                post({ action: 'delete', id: String(id) })
                    .then(function (data) {
                        items = items.filter(function (it) { return parseInt(it.id, 10) !== id; });
                        if (currentId === id) {
                            fillForm(null);
                        }
                        if (detailEl) {
                            detailEl.hidden = true;
                        }
                        renderSessions();
                        renderList();
                        toast(data.message || 'حذف شد.');
                        showAlert(data.message, true);
                    })
                    .catch(function (err) {
                        showAlert(err.message, false);
                        toast(err.message);
                    })
                    .then(function () {
                        busy(false);
                    });
            }
        });
    }

    if (resetBtn) {
        resetBtn.addEventListener('click', function () {
            fillForm(null);
            showAlert('', true);
        });
    }

    if (removeFileBtn) {
        removeFileBtn.addEventListener('click', function () {
            if (!currentId) {
                return;
            }
            busy(true, 'حذف پیوست…');
            post({ action: 'remove_file', id: String(currentId) })
                .then(function (data) {
                    upsert(data.assignment);
                    fillForm(data.assignment);
                    renderSessions();
                    renderList();
                    toast(data.message);
                    showAlert(data.message, true);
                })
                .catch(function (err) {
                    showAlert(err.message, false);
                    toast(err.message);
                })
                .then(function () {
                    busy(false);
                });
        });
    }

    if (form) {
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            const title = (titleEl && titleEl.value || '').trim();
            if (title.length < 2) {
                showAlert('عنوان تکلیف را بنویسید.', false);
                return;
            }
            const file = fileEl && fileEl.files && fileEl.files[0] ? fileEl.files[0] : null;
            if (file && boot.max_bytes && file.size > boot.max_bytes) {
                showAlert('حجم فایل حداکثر ' + boot.max_label + ' است.', false);
                return;
            }
            busy(true, 'در حال انتشار…');
            post({
                action: 'save',
                id: idEl ? idEl.value : '0',
                session_no: sessionEl ? sessionEl.value : '1',
                title: title,
                body: bodyEl ? bodyEl.value : '',
                due_at: dueEl ? dueEl.value : ''
            }, file)
                .then(function (data) {
                    upsert(data.assignment);
                    filterSession = parseInt(data.assignment.session_no, 10) || filterSession;
                    fillForm(null);
                    if (sessionEl) {
                        sessionEl.value = String(filterSession || 1);
                    }
                    renderSessions();
                    renderList();
                    toast(data.message);
                    showAlert(data.message, true);
                })
                .catch(function (err) {
                    showAlert(err.message, false);
                    toast(err.message);
                })
                .then(function () {
                    busy(false);
                });
        });
    }
})();
