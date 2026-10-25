(function () {
    const root = document.querySelector('[data-tp-root]');
    if (!root) {
        return;
    }

    const role = root.getAttribute('data-role') || 'teacher';
    const classId = parseInt(root.getAttribute('data-class-id') || '0', 10);
    const ajaxUrl = root.getAttribute('data-ajax') || 'ajax-term-plan.php';
    const remarkTimers = {};
    let methods = [];
    try {
        methods = JSON.parse(root.getAttribute('data-methods') || '[]');
    } catch (err) {
        methods = [];
    }

    function toast(msg) {
        let el = document.querySelector('.tp-toast');
        if (!el) {
            el = document.createElement('div');
            el.className = 'tp-toast';
            document.body.appendChild(el);
        }
        el.textContent = msg;
        el.classList.add('show');
        clearTimeout(el._t);
        el._t = setTimeout(function () {
            el.classList.remove('show');
        }, 2600);
    }

    function post(payload, keepalive) {
        const body = new FormData();
        Object.keys(payload).forEach(function (k) {
            if (payload[k] !== undefined && payload[k] !== null) {
                body.append(k, payload[k]);
            }
        });
        body.append('class_id', String(classId));
        return fetch(ajaxUrl, {
            method: 'POST',
            body: body,
            credentials: 'same-origin',
            keepalive: !!keepalive
        })
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

    function methodOptionsHtml(selected, custom) {
        let html = '<option value="">انتخاب متد…</option>';
        methods.forEach(function (m) {
            html += '<option value="' + m.replace(/"/g, '&quot;') + '"'
                + (m === selected ? ' selected' : '') + '>' + m + '</option>';
        });
        return html;
    }

    function isOther(val) {
        return String(val).toLowerCase() === 'other';
    }

    function applyTaskState(row, task) {
        row.dataset.status = task.status || 'pending';
        row.dataset.method = task.method || '';
        row.dataset.custom = task.method_custom || '';
        row.classList.toggle('is-done', task.status === 'done');
        row.classList.toggle('is-missed', task.status === 'missed');
        row.classList.toggle('needs-method', !!task.needs_method);
        row.classList.toggle('needs-remark', !!task.needs_remark);
        const btn = row.querySelector('.tp-check');
        if (btn) {
            btn.classList.toggle('is-done', task.status === 'done');
            btn.classList.toggle('is-missed', task.status === 'missed');
            btn.textContent = task.status === 'done' ? '✓' : (task.status === 'missed' ? '✕' : '');
            btn.setAttribute('aria-label', task.status === 'done' ? 'انجام شد' : (task.status === 'missed' ? 'انجام نشد' : 'بدون وضعیت'));
        }
        const methodWrap = row.querySelector('.tp-method-wrap');
        if (methodWrap) {
            methodWrap.classList.toggle('is-required', !!task.needs_method);
            const sel = methodWrap.querySelector('select');
            if (sel && task.method) {
                sel.value = task.method;
            }
            const custom = methodWrap.querySelector('.tp-custom');
            if (custom) {
                custom.classList.toggle('is-on', isOther(task.method));
                const inp = custom.querySelector('input');
                if (inp) {
                    inp.value = task.method_custom || '';
                }
            }
        }
        const remarkWrap = row.querySelector('.tp-remark-wrap');
        if (remarkWrap) {
            remarkWrap.classList.toggle('is-required', !!task.needs_remark);
            const ta = remarkWrap.querySelector('textarea');
            if (ta && task.teacher_remark != null) {
                ta.value = task.teacher_remark;
            }
        }
        const ro = row.querySelector('.tp-readonly-status');
        if (ro) {
            if (task.status === 'done') {
                ro.textContent = 'معلم: ✓ ' + (task.method === 'Other' ? (task.method_custom || 'Other') : (task.method || 'بدون متد'));
            } else if (task.status === 'missed') {
                ro.textContent = 'معلم: ✕ ' + (task.teacher_remark || 'بدون ریمارک');
            } else {
                ro.textContent = 'معلم هنوز وضعیتی نزده';
            }
        }
    }

    function nextStatus(cur) {
        if (cur === 'pending') return 'done';
        if (cur === 'done') return 'missed';
        return 'pending';
    }

    function saveTeacher(row) {
        const status = row.dataset.status || 'pending';
        const sel = row.querySelector('.tp-method-wrap select');
        const custom = row.querySelector('.tp-custom input');
        const remark = row.querySelector('.tp-remark-wrap textarea');
        return post({
            action: 'save_check',
            task_id: row.dataset.taskId,
            status: status,
            method: sel ? sel.value : '',
            method_custom: custom ? custom.value : '',
            teacher_remark: remark ? remark.value : ''
        }).then(function (data) {
            if (data.task) {
                applyTaskState(row, data.task);
            }
        }).catch(function (err) {
            toast(err.message);
        });
    }

    function taskRowHtml(task, editable) {
        const src = task.source === 'teacher' ? '<span class="tp-src">معلم</span>' : '';
        const titleBlock = editable
            ? '<input class="tp-task-title-input" value="">'
            : '<div class="tp-task-title"></div>';
        const del = (editable || task.source === 'teacher')
            ? '<button type="button" class="tp-del" title="حذف">×</button>'
            : '';
        const teacherBits = role === 'teacher'
            ? (
                '<div class="tp-method-wrap">' +
                    '<select class="tp-select" dir="ltr">' + methodOptionsHtml(task.method) + '</select>' +
                    '<div class="tp-custom' + (isOther(task.method) ? ' is-on' : '') + '">' +
                        '<input class="tp-input" dir="ltr" placeholder="متد خودتان را بنویسید">' +
                    '</div>' +
                    '<div class="tp-hint tp-hint-method">انتخاب متد ضروری است</div>' +
                '</div>' +
                '<div class="tp-remark-wrap">' +
                    '<textarea class="tp-textarea" rows="3" placeholder="چرا این تسک انجام نشد؟ چند جمله بنویسید"></textarea>' +
                    '<div class="tp-hint tp-hint-remark">نوشتن ریمارک برای ضربدر ضروری است</div>' +
                '</div>'
            )
            : '<div class="tp-readonly-status"></div>';
        const check = role === 'teacher'
            ? '<button type="button" class="tp-check"></button>'
            : '<span class="tp-check" style="cursor:default"></span>';
        const wrap = document.createElement('div');
        wrap.innerHTML =
            '<div class="tp-task" data-task-id="' + task.id + '" data-source="' + (task.source || '') + '">' +
                '<div class="tp-task-row">' +
                    check +
                    '<div class="tp-task-body">' +
                        (editable ? '<div class="tp-task-title">' + titleBlock + src + '</div>' : '<div class="tp-task-title">' + src + '<span class="tp-title-text"></span></div>') +
                        teacherBits +
                    '</div>' +
                    del +
                '</div>' +
            '</div>';
        const row = wrap.firstChild;
        const titleText = row.querySelector('.tp-title-text');
        if (titleText) {
            titleText.textContent = task.title;
        }
        const titleInput = row.querySelector('.tp-task-title-input');
        if (titleInput) {
            titleInput.value = task.title;
        }
        applyTaskState(row, task);
        return row;
    }

    window.tpBuildTaskRow = taskRowHtml;

    root.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' && e.target.classList.contains('tp-add-input')) {
            e.preventDefault();
            const btn = e.target.closest('.tp-add') && e.target.closest('.tp-add').querySelector('.tp-add-btn');
            if (btn) {
                btn.click();
            }
        }
    });

    root.addEventListener('click', function (e) {
        const check = e.target.closest('.tp-check');
        if (check && role === 'teacher' && check.tagName === 'BUTTON') {
            const row = check.closest('.tp-task');
            const next = nextStatus(row.dataset.status || 'pending');
            row.dataset.status = next;
            if (next !== 'done') {
                const sel = row.querySelector('select');
                if (sel) sel.value = '';
                const custom = row.querySelector('.tp-custom');
                if (custom) {
                    custom.classList.remove('is-on');
                    const inp = custom.querySelector('input');
                    if (inp) inp.value = '';
                }
            }
            if (next !== 'missed') {
                const ta = row.querySelector('.tp-remark-wrap textarea');
                if (ta) ta.value = '';
            }
            applyTaskState(row, {
                status: next,
                method: row.querySelector('select') ? row.querySelector('select').value : '',
                method_custom: '',
                teacher_remark: next === 'missed' ? '' : '',
                needs_method: next === 'done',
                needs_remark: next === 'missed'
            });
            saveTeacher(row);
            return;
        }

        const del = e.target.closest('.tp-del');
        if (del) {
            const row = del.closest('.tp-task');
            if (!confirm('این تسک حذف شود؟')) {
                return;
            }
            post({ action: 'delete_task', task_id: row.dataset.taskId })
                .then(function () {
                    row.remove();
                })
                .catch(function (err) { toast(err.message); });
            return;
        }

        const addBtn = e.target.closest('.tp-add-btn');
        if (addBtn) {
            const session = addBtn.closest('.tp-session');
            const input = session.querySelector('.tp-add-input');
            const title = (input.value || '').trim();
            if (!title) {
                toast('متن تسک را بنویسید.');
                input.focus();
                return;
            }
            post({
                action: 'add_task',
                session_no: session.getAttribute('data-session'),
                title: title
            }).then(function (data) {
                const list = session.querySelector('.tp-tasks');
                const empty = list.querySelector('.tp-no-tasks');
                if (empty) empty.remove();
                list.appendChild(taskRowHtml(data.task, role === 'supervisor'));
                input.value = '';
            }).catch(function (err) { toast(err.message); });
        }
    });

    root.addEventListener('change', function (e) {
        const sel = e.target.closest('.tp-method-wrap select');
        if (sel) {
            const row = sel.closest('.tp-task');
            const custom = row.querySelector('.tp-custom');
            custom.classList.toggle('is-on', isOther(sel.value));
            if (isOther(sel.value)) {
                const inp = custom.querySelector('input');
                if (inp) inp.focus();
                return;
            }
            saveTeacher(row);
            return;
        }
    });

    root.addEventListener('focusout', function (e) {
        if (role === 'teacher' && e.target.closest('.tp-custom input')) {
            const row = e.target.closest('.tp-task');
            saveTeacher(row);
            return;
        }
        if (role === 'teacher' && e.target.closest('.tp-remark-wrap textarea')) {
            const row = e.target.closest('.tp-task');
            saveTeacher(row);
            return;
        }
        if (role === 'supervisor' && e.target.closest('.tp-task-title-input')) {
            const row = e.target.closest('.tp-task');
            post({
                action: 'update_task',
                task_id: row.dataset.taskId,
                title: e.target.value
            }).catch(function (err) { toast(err.message); });
            return;
        }
        if (role === 'supervisor' && e.target.closest('.tp-sup-remark')) {
            const session = e.target.closest('.tp-session');
            const sno = session ? (session.getAttribute('data-session') || '0') : '0';
            clearTimeout(remarkTimers[sno]);
            saveSupervisorRemark(session, true);
        }
    });

    if (role !== 'supervisor') {
        return;
    }

    function remarkStatus(session, text, kind) {
        const el = session.querySelector('.tp-sup-save-status');
        if (!el) {
            return;
        }
        el.textContent = text;
        el.classList.remove('is-ok', 'is-err', 'is-busy');
        if (kind) {
            el.classList.add(kind);
        }
    }

    function saveSupervisorRemark(session, silent, keepalive) {
        if (!session) {
            return Promise.resolve();
        }
        const ta = session.querySelector('.tp-sup-remark');
        if (!ta) {
            return Promise.resolve();
        }
        const sno = session.getAttribute('data-session') || '';
        const val = ta.value;
        if ((session.dataset.savedRemark || '') === val.trim() && session.dataset.remarkDirty !== '1') {
            return Promise.resolve();
        }
        if (session.dataset.remarkSaving === '1') {
            return Promise.resolve();
        }
        clearTimeout(remarkTimers[sno]);
        session.dataset.remarkDirty = '1';
        session.dataset.remarkSaving = '1';
        remarkStatus(session, 'در حال ذخیره…', 'is-busy');
        return post({
            action: 'save_remark',
            session_no: sno,
            remark: val
        }, keepalive).then(function (data) {
            const saved = data.remark != null ? String(data.remark) : val.trim();
            session.dataset.savedRemark = saved;
            if (ta.value.trim() === saved) {
                session.dataset.remarkDirty = '0';
            }
            const box = session.querySelector('.tp-sup-box');
            if (box) {
                box.classList.toggle('has-remark', saved.trim() !== '');
            }
            remarkStatus(session, 'ذخیره شد', 'is-ok');
            if (!silent) {
                toast('ریمارک سوپروایزر ذخیره شد.');
            }
        }).catch(function (err) {
            remarkStatus(session, 'ذخیره نشد', 'is-err');
            toast(err.message);
        }).then(function () {
            session.dataset.remarkSaving = '0';
            if (session.dataset.remarkDirty === '1' && ta.value.trim() !== (session.dataset.savedRemark || '')) {
                saveSupervisorRemark(session, true, keepalive);
            }
        });
    }

    root.querySelectorAll('.tp-session').forEach(function (session) {
        const ta = session.querySelector('.tp-sup-remark');
        if (ta) {
            session.dataset.savedRemark = ta.value.trim();
            session.dataset.remarkDirty = '0';
        }
    });

    root.addEventListener('input', function (e) {
        const ta = e.target.closest('.tp-sup-remark');
        if (!ta) {
            return;
        }
        const session = ta.closest('.tp-session');
        session.dataset.remarkDirty = '1';
        remarkStatus(session, 'در حال نوشتن…', '');
        const sno = session.getAttribute('data-session') || '0';
        clearTimeout(remarkTimers[sno]);
        remarkTimers[sno] = setTimeout(function () {
            saveSupervisorRemark(session, true);
        }, 700);
    });

    root.addEventListener('click', function (e) {
        const btn = e.target.closest('.tp-sup-save');
        if (!btn) {
            return;
        }
        const session = btn.closest('.tp-session');
        const sno = session.getAttribute('data-session') || '0';
        clearTimeout(remarkTimers[sno]);
        session.dataset.remarkDirty = '1';
        saveSupervisorRemark(session, false);
    });

    function flushRemarks(keepalive) {
        root.querySelectorAll('.tp-session').forEach(function (session) {
            const sno = session.getAttribute('data-session') || '0';
            clearTimeout(remarkTimers[sno]);
            if (session.dataset.remarkDirty === '1') {
                saveSupervisorRemark(session, true, keepalive);
            }
        });
    }

    window.addEventListener('pagehide', function () { flushRemarks(true); });
    document.addEventListener('visibilitychange', function () {
        if (document.visibilityState === 'hidden') {
            flushRemarks(true);
        }
    });
})();
