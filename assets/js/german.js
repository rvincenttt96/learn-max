(function () {
    const G = window.GERMAN || null;
    const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    function $(sel, root) {
        return (root || document).querySelector(sel);
    }
    function $all(sel, root) {
        return Array.from((root || document).querySelectorAll(sel));
    }
    function toast(msg, err) {
        const el = document.getElementById('gToast');
        if (!el) return;
        el.textContent = msg;
        el.hidden = false;
        el.classList.toggle('is-err', !!err);
        requestAnimationFrame(function () { el.classList.add('is-on'); });
        clearTimeout(toast._t);
        toast._t = setTimeout(function () {
            el.classList.remove('is-on');
            setTimeout(function () { el.hidden = true; }, reduced ? 0 : 180);
        }, 2400);
    }
    function digits(s) {
        return String(s || '')
            .replace(/[۰-۹]/g, function (d) { return '۰۱۲۳۴۵۶۷۸۹'.indexOf(d); })
            .replace(/[٠-٩]/g, function (d) { return '٠١٢٣٤٥٦٧٨٩'.indexOf(d); })
            .replace(/[^\d]/g, '');
    }
    function money(n) {
        n = parseInt(n, 10) || 0;
        return n.toLocaleString('en-US');
    }
    let credsReload = false;

    function copyText(text) {
        const value = String(text || '');
        if (!value) return Promise.reject(new Error('خالی است'));
        if (navigator.clipboard && navigator.clipboard.writeText) {
            return navigator.clipboard.writeText(value);
        }
        return new Promise(function (resolve, reject) {
            const ta = document.createElement('textarea');
            ta.value = value;
            ta.setAttribute('readonly', '');
            ta.style.position = 'fixed';
            ta.style.opacity = '0';
            document.body.appendChild(ta);
            ta.select();
            try {
                document.execCommand('copy');
                resolve();
            } catch (err) {
                reject(err);
            }
            document.body.removeChild(ta);
        });
    }

    function showCreds(person, reloadAfter) {
        if (!person) return;
        credsReload = !!reloadAfter;
        const nameEl = document.getElementById('credName');
        const userEl = document.getElementById('credUser');
        const passEl = document.getElementById('credPass');
        if (nameEl) nameEl.textContent = person.full_name || '';
        if (userEl) userEl.textContent = person.username || '';
        if (passEl) passEl.textContent = person.password || person.username || '';
        openSheet('credsSheet');
    }

    function bindCreds() {
        $all('[data-close-creds]').forEach(function (el) {
            el.addEventListener('click', function () {
                closeSheet('credsSheet');
                if (credsReload) {
                    credsReload = false;
                    setTimeout(function () { location.reload(); }, reduced ? 0 : 180);
                }
            });
        });
        $all('[data-copy]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                const which = btn.getAttribute('data-copy');
                let text = '';
                if (which === 'both') {
                    const u = (document.getElementById('credUser') || {}).textContent || '';
                    const p = (document.getElementById('credPass') || {}).textContent || '';
                    text = u + ' / ' + p;
                } else {
                    const el = document.getElementById(which);
                    text = el ? el.textContent : '';
                }
                copyText(text).then(function () { toast('کپی شد'); })
                    .catch(function () { toast('کپی نشد', true); });
            });
        });
    }

    function openSheet(id) {
        const root = document.getElementById(id);
        if (!root) return;
        root.hidden = false;
        const sheet = root.querySelector('.g-sheet');
        const body = root.querySelector('.g-sheet-body');
        if (sheet) sheet.scrollTop = 0;
        if (body) body.scrollTop = 0;
        requestAnimationFrame(function () {
            root.classList.add('is-open');
            const first = root.querySelector('.g-input');
            if (first && typeof first.focus === 'function') {
                first.focus({ preventScroll: true });
            }
        });
        document.body.style.overflow = 'hidden';
    }
    function closeSheet(id) {
        const root = document.getElementById(id);
        if (!root) return;
        root.classList.remove('is-open');
        document.body.style.overflow = '';
        setTimeout(function () { root.hidden = true; }, reduced ? 0 : 200);
    }
    function ajaxUrl() {
        return (G && G.ajax) || 'ajax.php';
    }
    function post(data) {
        const body = data instanceof FormData ? data : new URLSearchParams(data);
        return fetch(ajaxUrl(), { method: 'POST', body: body, credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (j) {
                if (!j || !j.success) throw new Error((j && j.message) || 'خطا');
                return j;
            });
    }

    function clock() {
        const t = document.getElementById('gClockTime');
        const d = document.getElementById('gClockDate');
        if (!t) return;
        function tick() {
            const now = new Date();
            const hh = String(now.getHours()).padStart(2, '0');
            const mm = String(now.getMinutes()).padStart(2, '0');
            t.innerHTML = hh + '<span>:</span>' + mm;
            if (d) d.textContent = now.toLocaleDateString('fa-IR', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
        }
        tick();
        setInterval(tick, 1000);
    }

    function feeFor(level, kind, manual) {
        if (kind === 'semi_private') return parseInt(digits(manual), 10) || 0;
        const row = (G.fees || {})[level] || {};
        if (kind === 'private') return parseInt(row.private_fee, 10) || 0;
        return parseInt(row.group_fee, 10) || 0;
    }

    function updateFeePreview() {
        const kind = $('#fKind') && $('#fKind').value;
        const level = $('#fLevel') && $('#fLevel').value;
        const sessions = parseInt(($('#fSessions') || {}).value, 10) || 0;
        const wrap = document.getElementById('manualFeeWrap');
        const feeInput = document.getElementById('fFee');
        const label = document.getElementById('feeLabel');
        const preview = document.getElementById('feePreview');
        if (!kind || !feeInput) return;
        const isSemi = kind === 'semi_private';
        feeInput.readOnly = !isSemi;
        if (label) label.textContent = isSemi ? 'شهریه هر جلسه (دستی)' : 'شهریه هر جلسه (از جدول)';
        if (!isSemi) feeInput.value = money(feeFor(level, kind, 0));
        const per = feeFor(level, kind, feeInput.value);
        if (preview) {
            preview.textContent = sessions + ' جلسه × ' + money(per) + ' = شهریه کل هر زبان‌آموز ' + money(sessions * per) + ' تومان';
        }
        if (wrap) wrap.style.opacity = '1';
    }

    function fillClassForm(data) {
        $('#fClassId').value = data && data.id ? data.id : 0;
        $('#fTitle').value = (data && data.title) || '';
        $('#fLevel').value = (data && data.level) || 'A1';
        $('#fKind').value = (data && data.kind) || 'group';
        $('#fSessions').value = (data && data.session_count) || 10;
        $('#fDay').value = (data && data.schedule_type) || '';
        $('#fSchedule').value = (data && data.schedule) || '';
        $('#fTeacher').value = (data && data.teacher_id) || 0;
        const ids = (data && data.student_ids) || [];
        $all('#studentPicker input[type="checkbox"]').forEach(function (cb) {
            cb.checked = ids.indexOf(parseInt(cb.value, 10)) !== -1;
        });
        const title = document.getElementById('classSheetTitle');
        if (title) title.textContent = data && data.id ? 'ویرایش کلاس' : 'کلاس تازه';
        if (data && data.kind === 'semi_private') {
            $('#fFee').value = money(data.fee_per_session || 0);
        }
        updateFeePreview();
        if (data && data.kind !== 'semi_private') updateFeePreview();
    }

    function collectSessions(boxRoot) {
        const status = {};
        const dates = {};
        $all('.session-item', boxRoot).forEach(function (item) {
            const box = $('.session-box', item);
            if (!box) return;
            const n = box.getAttribute('data-num');
            if (box.classList.contains('status-tick')) status[n] = 1;
            else if (box.classList.contains('status-cross')) status[n] = 2;
            const date = $('.date-input', item);
            if (date && date.value) dates[n] = date.value;
        });
        return { status: status, dates: dates };
    }

    function saveSessions(container) {
        const classId = container.getAttribute('data-class-id');
        const pack = collectSessions(container);
        const fd = new FormData();
        fd.append('action', 'save_sessions');
        fd.append('class_id', classId);
        fd.append('session_status', JSON.stringify(pack.status));
        fd.append('session_dates', JSON.stringify(pack.dates));
        post(fd).catch(function (e) { toast(e.message, true); });
    }

    function toggleSession(box) {
        if (box.closest('.g-readonly-card')) return;
        if (box.classList.contains('status-tick')) {
            box.classList.remove('status-tick');
            box.classList.add('status-cross');
            box.textContent = '✖';
        } else if (box.classList.contains('status-cross')) {
            box.classList.remove('status-cross');
            box.textContent = box.getAttribute('data-num');
        } else {
            box.classList.add('status-tick');
            box.textContent = '✔';
        }
        const wrap = box.closest('.sessions-container');
        if (wrap) {
            clearTimeout(wrap._t);
            wrap._t = setTimeout(function () { saveSessions(wrap); }, 280);
        }
    }

    function filterCards() {
        const q = digits(($('#gSearch') || {}).value || '').toLowerCase();
        const qText = (($('#gSearch') || {}).value || '').toLowerCase();
        const kind = ($('#gKindFilter') || {}).value || 'all';
        const level = ($('#gLevelFilter') || {}).value || 'all';
        let n = 0;
        $all('.g-card').forEach(function (card) {
            const hay = (card.getAttribute('data-search') || '').toLowerCase();
            const okQ = !qText || hay.indexOf(qText) !== -1 || hay.indexOf(q) !== -1;
            const okK = kind === 'all' || card.getAttribute('data-kind') === kind;
            const okL = level === 'all' || card.getAttribute('data-level') === level;
            const show = okQ && okK && okL;
            card.style.display = show ? '' : 'none';
            if (show) n += 1;
        });
        const empty = document.getElementById('gEmpty');
        if (empty && $all('.g-card').length) empty.style.display = n ? 'none' : '';
    }

    function addPersonOption(person) {
        if (!person || !person.id) return;
        if (person.role === 'german_teacher') {
            const sel = document.getElementById('fTeacher');
            if (!sel) return;
            const opt = document.createElement('option');
            opt.value = person.id;
            opt.textContent = (person.full_name || '') + ' — ' + (person.username || '');
            sel.appendChild(opt);
            sel.value = person.id;
            if (G.teachers) G.teachers.push(person);
        } else if (person.role === 'german_student') {
            const picker = document.getElementById('studentPicker');
            if (!picker) return;
            const empty = picker.querySelector('[data-empty-students]');
            if (empty) empty.remove();
            const label = document.createElement('label');
            label.className = 'g-pick';
            label.setAttribute('data-name', (person.full_name || '') + ' ' + (person.username || ''));
            const cb = document.createElement('input');
            cb.type = 'checkbox';
            cb.name = 'student_ids[]';
            cb.value = person.id;
            cb.checked = true;
            const name = document.createElement('span');
            name.textContent = person.full_name || '';
            const file = document.createElement('span');
            file.className = 'student-file';
            file.textContent = person.username || '';
            label.appendChild(cb);
            label.appendChild(name);
            label.appendChild(file);
            picker.appendChild(label);
            if (G.students) G.students.push(person);
        }
    }

    function bindClasses() {
        clock();
        const addBtn = document.getElementById('gAddClassBtn');
        if (addBtn) addBtn.addEventListener('click', function () {
            fillClassForm(null);
            openSheet('classSheet');
        });
        $all('[data-open-class]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                fillClassForm(null);
                openSheet('classSheet');
            });
        });
        $all('[data-close-sheet]').forEach(function (el) {
            el.addEventListener('click', function () { closeSheet('classSheet'); });
        });
        $all('[data-close-person]').forEach(function (el) {
            el.addEventListener('click', function () { closeSheet('personSheet'); });
        });
        ['fKind', 'fLevel', 'fSessions', 'fFee'].forEach(function (id) {
            const el = document.getElementById(id);
            if (el) el.addEventListener('input', updateFeePreview);
            if (el) el.addEventListener('change', updateFeePreview);
        });
        $all('[data-quick]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                const kind = btn.getAttribute('data-quick');
                $('#pRole').value = kind === 'teacher' ? 'german_teacher' : 'german_student';
                document.getElementById('personSheetTitle').textContent = kind === 'teacher' ? 'افزودن استاد' : 'افزودن زبان‌آموز';
                $('#personForm').reset();
                $('#pRole').value = kind === 'teacher' ? 'german_teacher' : 'german_student';
                openSheet('personSheet');
            });
        });
        const personForm = document.getElementById('personForm');
        if (personForm) {
            personForm.addEventListener('submit', function (e) {
                e.preventDefault();
                const fd = new FormData(personForm);
                fd.append('action', 'add_person');
                post(fd).then(function (j) {
                    addPersonOption(j.person);
                    closeSheet('personSheet');
                    showCreds(j.person, false);
                }).catch(function (err) { toast(err.message, true); });
            });
        }
        const classForm = document.getElementById('classForm');
        if (classForm) {
            classForm.addEventListener('submit', function (e) {
                e.preventDefault();
                const btn = document.getElementById('classSaveBtn');
                btn.disabled = true;
                const fd = new FormData(classForm);
                fd.append('action', 'save_class');
                post(fd).then(function (j) {
                    toast(j.message || 'ذخیره شد');
                    location.reload();
                }).catch(function (err) {
                    toast(err.message, true);
                    btn.disabled = false;
                });
            });
        }
        $all('[data-edit-class]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                let data = {};
                try { data = JSON.parse(btn.getAttribute('data-edit-class') || '{}'); } catch (e) { data = {}; }
                fillClassForm(data);
                openSheet('classSheet');
            });
        });
        $all('[data-del-class]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                if (!confirm('این کلاس آلمانی حذف شود؟')) return;
                post({ action: 'delete_class', class_id: btn.getAttribute('data-del-class') })
                    .then(function (j) { toast(j.message); location.reload(); })
                    .catch(function (err) { toast(err.message, true); });
            });
        });
        $all('.session-box').forEach(function (box) {
            box.addEventListener('click', function () { toggleSession(box); });
        });
        document.addEventListener('jdp:change', function (e) {
            const wrap = e.target.closest && e.target.closest('.sessions-container');
            if (wrap && !wrap.closest('.g-readonly-card')) saveSessions(wrap);
        });
        $all('.g-pay-form').forEach(function (form) {
            form.addEventListener('submit', function (e) {
                e.preventDefault();
                const fd = new FormData(form);
                fd.append('action', 'add_payment');
                fd.append('class_id', form.getAttribute('data-class-id'));
                fd.append('student_id', form.getAttribute('data-student-id'));
                const btn = form.querySelector('button[type="submit"]');
                if (btn) btn.disabled = true;
                post(fd).then(function (j) {
                    toast(j.message || 'پرداخت ثبت شد');
                    location.reload();
                }).catch(function (err) {
                    toast(err.message, true);
                    if (btn) btn.disabled = false;
                });
            });
        });
        $all('[data-del-pay]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                if (!confirm('این پرداخت حذف شود؟')) return;
                post({ action: 'delete_payment', payment_id: btn.getAttribute('data-del-pay') })
                    .then(function (j) { toast(j.message); location.reload(); })
                    .catch(function (err) { toast(err.message, true); });
            });
        });
        const search = document.getElementById('gSearch');
        if (search) search.addEventListener('input', filterCards);
        ['gKindFilter', 'gLevelFilter'].forEach(function (id) {
            const el = document.getElementById(id);
            if (el) el.addEventListener('change', filterCards);
        });
        const pickerSearch = document.getElementById('studentPickerSearch');
        if (pickerSearch) {
            pickerSearch.addEventListener('input', function () {
                const q = pickerSearch.value.toLowerCase();
                $all('#studentPicker .g-pick').forEach(function (row) {
                    const hay = (row.getAttribute('data-name') || '').toLowerCase();
                    row.style.display = !q || hay.indexOf(q) !== -1 ? '' : 'none';
                });
            });
        }
        if (window.jalaliDatepicker) {
            try { jalaliDatepicker.startWatch({ minDate: 'attr', maxDate: 'attr', time: false }); } catch (e) {}
        }
        updateFeePreview();
    }

    function bindPeople() {
        $all('[data-person-form]').forEach(function (form) {
            form.addEventListener('submit', function (e) {
                e.preventDefault();
                const fd = new FormData(form);
                fd.append('action', 'add_person');
                fd.append('role', form.getAttribute('data-person-form'));
                const btn = form.querySelector('button[type="submit"]');
                if (btn) btn.disabled = true;
                post(fd).then(function (j) {
                    form.reset();
                    if (btn) btn.disabled = false;
                    showCreds(j.person, true);
                }).catch(function (err) {
                    toast(err.message, true);
                    if (btn) btn.disabled = false;
                });
            });
        });
        $all('[data-reset-pass]').forEach(function (form) {
            form.addEventListener('submit', function (e) {
                e.preventDefault();
                const fd = new FormData(form);
                fd.append('action', 'reset_password');
                fd.append('user_id', form.getAttribute('data-user-id'));
                fd.append('role', form.getAttribute('data-role'));
                post(fd).then(function (j) { toast(j.message || 'رمز به‌روز شد'); form.reset(); })
                    .catch(function (err) { toast(err.message, true); });
            });
        });
        $all('[data-del-person]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                if (!confirm('این فرد از برنامه آلمانی حذف شود؟')) return;
                post({
                    action: 'delete_person',
                    user_id: btn.getAttribute('data-del-person'),
                    role: btn.getAttribute('data-role')
                }).then(function (j) { toast(j.message); location.reload(); })
                    .catch(function (err) { toast(err.message, true); });
            });
        });
        $all('[data-filter-list]').forEach(function (input) {
            input.addEventListener('input', function () {
                const q = input.value.toLowerCase();
                const list = document.getElementById(input.getAttribute('data-filter-list'));
                if (!list) return;
                $all('li', list).forEach(function (li) {
                    const hay = (li.getAttribute('data-search') || '').toLowerCase();
                    li.style.display = !q || hay.indexOf(q) !== -1 ? '' : 'none';
                });
            });
        });
    }

    function bindFees() {
        const form = document.getElementById('feesForm');
        if (!form) return;
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            const fd = new FormData(form);
            fd.append('action', 'save_fees');
            post(fd).then(function (j) { toast(j.message || 'شهریه‌ها ذخیره شد'); })
                .catch(function (err) { toast(err.message, true); });
        });
    }

    $all('.g-sheet-root').forEach(function (el) {
        document.body.appendChild(el);
    });
    const toastEl = document.getElementById('gToast');
    if (toastEl) document.body.appendChild(toastEl);
    bindCreds();

    document.addEventListener('DOMContentLoaded', function () {
        if (!G) return;
        if (G.page === 'classes') bindClasses();
        if (G.page === 'people') bindPeople();
        if (G.page === 'fees') bindFees();
    });
})();
