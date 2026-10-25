(function () {
    const root = document.querySelector('[data-asg-student]');
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
    const id = parseInt(boot.id || '0', 10);
    const form = document.getElementById('asgUploadForm');
    const fileEl = document.getElementById('asgFile');
    const btn = document.getElementById('asgUploadBtn');
    const alertBox = document.getElementById('asgAlert');
    const progress = document.getElementById('asgProgress');
    const progressText = document.getElementById('asgProgressText');
    const current = document.getElementById('asgCurrent');

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
        if (progressText) {
            progressText.textContent = on ? 'در حال آپلود…' : '';
        }
        if (btn) {
            btn.disabled = !!on;
        }
    }

    if (!form || !id) {
        return;
    }

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        const file = fileEl && fileEl.files && fileEl.files[0] ? fileEl.files[0] : null;
        if (!file) {
            showAlert('یک فایل انتخاب کنید.', false);
            return;
        }
        if (boot.max_bytes && file.size > boot.max_bytes) {
            showAlert('حجم فایل حداکثر ' + boot.max_label + ' است.', false);
            return;
        }
        const body = new FormData();
        body.append('action', 'upload');
        body.append('id', String(id));
        body.append('file', file);
        busy(true);
        fetch(ajax, { method: 'POST', body: body, credentials: 'same-origin' })
            .then(function (r) {
                const ct = r.headers.get('content-type') || '';
                if (ct.indexOf('json') === -1) {
                    throw new Error('نشست منقضی شده. صفحه را رفرش کنید.');
                }
                return r.json();
            })
            .then(function (data) {
                if (!data.success) {
                    throw new Error(data.message || 'آپلود انجام نشد.');
                }
                const asg = data.assignment || {};
                if (current) {
                    current.hidden = false;
                    current.innerHTML = '<p>فایل فعلی: <a href="assignment-file.php?kind=submission&id=' + id + '">'
                        + esc(asg.file_name) + '</a> <span>' + esc(asg.file_size_label) + '</span> <span>'
                        + esc(asg.submitted_label) + '</span></p>';
                }
                if (btn) {
                    btn.textContent = 'جایگزینی فایل';
                }
                if (fileEl) {
                    fileEl.value = '';
                }
                showAlert(data.message || 'فایل تحویل شد.', true);
            })
            .catch(function (err) {
                showAlert(err.message, false);
            })
            .then(function () {
                busy(false);
            });
    });
})();
