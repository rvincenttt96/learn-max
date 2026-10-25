<?php
$title = 'Students Information';
require_once 'layout.php';
require_once '../../includes/db.php';
require_once '../../includes/students-info.php';
require_role('supervisor');

$students = students_info_list($pdo);
$ok = trim((string)($_GET['success'] ?? ''));
$err = trim((string)($_GET['error'] ?? ''));
?>

<style>
.si-toolbar { display:flex; flex-wrap:wrap; gap:10px; align-items:center; justify-content:space-between; margin-bottom:16px; }
.si-actions { display:flex; flex-wrap:wrap; gap:8px; }
.si-card { background:rgba(14,14,22,.72); border:1px solid rgba(127,29,29,.32); border-radius:20px; color:#fff; backdrop-filter:blur(24px); }
.si-card .card-header { background:linear-gradient(90deg,rgba(127,29,29,.55),rgba(190,18,60,.32)); border:0; border-radius:20px 20px 0 0; }
.si-table { color:#fff; margin:0; }
.si-table thead th { white-space:nowrap; background:rgba(255,255,255,.06); }
.si-table tbody tr:hover { background:rgba(211,47,47,.12); }
.si-empty { text-align:center; padding:48px 16px; color:#cfc9d8; }
.si-search { max-width:360px; }
.si-file { font-variant-numeric: tabular-nums; font-weight:700; }
.si-row-actions { display:flex; flex-wrap:wrap; gap:6px; }
@media (max-width: 767.98px) {
  .si-toolbar { align-items:stretch; }
  .si-search { max-width:none; }
  .si-actions .btn { flex:1 1 46%; }
}
</style>

<?php if ($ok !== ''): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <?= nl2br(htmlspecialchars($ok)) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>
<?php if ($err !== ''): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <?= nl2br(htmlspecialchars($err)) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="si-toolbar">
    <div class="d-flex gap-2 flex-grow-1 si-search-wrap">
        <input type="search" id="siLiveSearch" class="form-control si-search" placeholder="جستجو: شماره پرونده، اسم، فامیل" autocomplete="off">
    </div>
    <div class="si-actions">
        <a class="btn btn-success" href="students-info-export.php">خروجی اکسل</a>
        <a class="btn btn-outline-light" href="students-info-export.php?template=1">نمونه اکسل</a>
        <button type="button" class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#siAddModal">Add Student</button>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#siImportModal">ورود گروهی اکسل</button>
    </div>
</div>

<div class="card si-card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Students Information</h5>
        <span class="badge bg-light text-dark" id="siCount" data-suffix=" دانش‌آموز"><?= count($students) ?> دانش‌آموز</span>
    </div>
    <div class="card-body p-0">
        <?php if (!$students): ?>
            <div class="si-empty">
                <div style="font-size:2.4rem;margin-bottom:8px;">👩‍🎓</div>
                <h5>دانش‌آموزی پیدا نشد</h5>
                <p class="mb-0">با Add Student یا ورود گروهی اکسل دانش‌آموز اضافه کنید، یا جستجو را عوض کنید.</p>
            </div>
        <?php else: ?>
            <div class="si-empty" id="siNoMatch" style="display:none">
                <h5>موردی پیدا نشد</h5>
                <p class="mb-0">عبارت جستجو را عوض کنید.</p>
            </div>
            <div class="table-responsive" id="siTableWrap">
                <table class="table table-bordered si-table align-middle">
                    <thead>
                        <tr>
                            <th>شماره پرونده</th>
                            <th>اسم</th>
                            <th>فامیل</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($students as $s):
                            $search_blob = trim($s['file_number'] . ' ' . $s['first_name'] . ' ' . $s['last_name'] . ' ' . ($s['full_name'] ?? '') . ' ' . ($s['phone'] ?? ''));
                        ?>
                        <tr data-search="<?= htmlspecialchars($search_blob, ENT_QUOTES, 'UTF-8') ?>">
                            <td class="si-file"><?= htmlspecialchars($s['file_number']) ?></td>
                            <td><?= htmlspecialchars($s['first_name'] !== '' ? $s['first_name'] : '—') ?></td>
                            <td><?= htmlspecialchars($s['last_name'] !== '' ? $s['last_name'] : '—') ?></td>
                            <td>
                                <div class="si-row-actions">
                                    <a class="btn btn-sm btn-outline-light" href="students-info-view.php?id=<?= (int)$s['id'] ?>">Student Profile</a>
                                    <button type="button" class="btn btn-sm btn-info si-edit"
                                        data-id="<?= (int)$s['id'] ?>"
                                        data-file="<?= htmlspecialchars($s['file_number'], ENT_QUOTES, 'UTF-8') ?>"
                                        data-first="<?= htmlspecialchars($s['first_name'], ENT_QUOTES, 'UTF-8') ?>"
                                        data-last="<?= htmlspecialchars($s['last_name'], ENT_QUOTES, 'UTF-8') ?>"
                                        data-phone="<?= htmlspecialchars((string)($s['phone'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">Edit</button>
                                    <button type="button" class="btn btn-sm btn-danger si-delete"
                                        data-id="<?= (int)$s['id'] ?>"
                                        data-name="<?= htmlspecialchars(trim($s['first_name'] . ' ' . $s['last_name']), ENT_QUOTES, 'UTF-8') ?>">Delete</button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="modal fade" id="siImportModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="background:rgba(15,15,24,.96);color:#fff;border:1px solid rgba(239,68,68,.3);border-radius:24px;">
            <form method="post" action="students-info-import.php" enctype="multipart/form-data">
                <div class="modal-header border-secondary">
                    <h5 class="modal-title">ورود گروهی اکسل</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="text-white-50">ستون‌های برگه Students: شماره پرونده، اسم، فامیل، شماره تماس. اگر شماره پرونده خالی باشد ساخته می‌شود. دانش‌آموز تکراری به‌روز می‌شود.</p>
                    <input type="file" name="excel_file" class="form-control" accept=".xlsx" required>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">انصراف</button>
                    <button type="submit" class="btn btn-primary">ورود فایل</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="siAddModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="background:rgba(15,15,24,.96);color:#fff;border:1px solid rgba(239,68,68,.3);border-radius:24px;">
            <form id="siAddForm">
                <div class="modal-header border-secondary">
                    <h5 class="modal-title">Add Student</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">شماره پرونده</label>
                        <input type="text" name="file_number" id="siAddFile" class="form-control" placeholder="خالی بماند تا خودکار ساخته شود">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">اسم</label>
                        <input type="text" name="first_name" id="siAddFirst" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">فامیل</label>
                        <input type="text" name="last_name" id="siAddLast" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">شماره تماس</label>
                        <input type="text" name="phone" id="siAddPhone" class="form-control">
                    </div>
                    <p class="text-white-50 small mb-0">رمز ورود همان شماره پرونده است.</p>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">انصراف</button>
                    <button type="submit" class="btn btn-warning">ثبت</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="siEditModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="background:rgba(15,15,24,.96);color:#fff;border:1px solid rgba(239,68,68,.3);border-radius:24px;">
            <form id="siEditForm">
                <div class="modal-header border-secondary">
                    <h5 class="modal-title">Edit</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="user_id" id="siEditId">
                    <div class="mb-3">
                        <label class="form-label">شماره پرونده</label>
                        <input type="text" name="file_number" id="siEditFile" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">اسم</label>
                        <input type="text" name="first_name" id="siEditFirst" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">فامیل</label>
                        <input type="text" name="last_name" id="siEditLast" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">شماره تماس</label>
                        <input type="text" name="phone" id="siEditPhone" class="form-control">
                    </div>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">انصراف</button>
                    <button type="submit" class="btn btn-info">ذخیره</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
(function () {
    function siNorm(s) {
        return String(s || '').toLowerCase()
            .replace(/[۰-۹]/g, function (d) { return '0123456789'['۰۱۲۳۴۵۶۷۸۹'.indexOf(d)]; })
            .replace(/[٠-٩]/g, function (d) { return '0123456789'['٠١٢٣٤٥٦٧٨٩'.indexOf(d)]; });
    }
    const searchInput = document.getElementById('siLiveSearch');
    const countEl = document.getElementById('siCount');
    const emptyEl = document.getElementById('siNoMatch');
    const tableWrap = document.getElementById('siTableWrap');
    const rows = document.querySelectorAll('#siTableWrap tbody tr');
    function filterRows() {
        if (!searchInput || !rows.length) return;
        const q = siNorm(searchInput.value).trim();
        let n = 0;
        rows.forEach(function (row) {
            const hay = siNorm(row.getAttribute('data-search') || row.textContent);
            const show = !q || hay.indexOf(q) !== -1;
            row.style.display = show ? '' : 'none';
            if (show) n++;
        });
        if (countEl) countEl.textContent = n + (countEl.getAttribute('data-suffix') || '');
        if (emptyEl) emptyEl.style.display = n === 0 ? '' : 'none';
        if (tableWrap) tableWrap.style.display = n === 0 ? 'none' : '';
    }
    if (searchInput) {
        searchInput.addEventListener('input', filterRows);
        searchInput.addEventListener('search', filterRows);
    }

    const editModalEl = document.getElementById('siEditModal');
    const editModal = editModalEl ? new bootstrap.Modal(editModalEl) : null;

    document.querySelectorAll('.si-edit').forEach(function (btn) {
        btn.addEventListener('click', function () {
            document.getElementById('siEditId').value = btn.getAttribute('data-id') || '';
            document.getElementById('siEditFile').value = btn.getAttribute('data-file') || '';
            document.getElementById('siEditFirst').value = btn.getAttribute('data-first') || '';
            document.getElementById('siEditLast').value = btn.getAttribute('data-last') || '';
            document.getElementById('siEditPhone').value = btn.getAttribute('data-phone') || '';
            if (editModal) editModal.show();
        });
    });

    const form = document.getElementById('siEditForm');
    if (form) {
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            const payload = {
                action: 'edit',
                user_id: document.getElementById('siEditId').value,
                file_number: document.getElementById('siEditFile').value,
                first_name: document.getElementById('siEditFirst').value,
                last_name: document.getElementById('siEditLast').value,
                phone: document.getElementById('siEditPhone').value
            };
            fetch('students-info-save.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                alert(data.message || (data.success ? 'ذخیره شد.' : 'خطا'));
                if (data.success) location.reload();
            })
            .catch(function () { alert('خطا در ذخیره اطلاعات.'); });
        });
    }

    const addForm = document.getElementById('siAddForm');
    if (addForm) {
        addForm.addEventListener('submit', function (e) {
            e.preventDefault();
            fetch('students-info-save.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'add',
                    file_number: document.getElementById('siAddFile').value,
                    first_name: document.getElementById('siAddFirst').value,
                    last_name: document.getElementById('siAddLast').value,
                    phone: document.getElementById('siAddPhone').value
                })
            })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                alert(data.message || (data.success ? 'ثبت شد.' : 'خطا'));
                if (data.success) location.reload();
            })
            .catch(function () { alert('خطا در ثبت دانش‌آموز.'); });
        });
    }

    document.querySelectorAll('.si-delete').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const name = btn.getAttribute('data-name') || '';
            if (!confirm('دانش‌آموز «' + name + '» حذف شود؟')) return;
            fetch('students-info-save.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'delete', user_id: btn.getAttribute('data-id') })
            })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                alert(data.message || (data.success ? 'حذف شد.' : 'خطا'));
                if (data.success) location.reload();
            })
            .catch(function () { alert('خطا در حذف دانش‌آموز.'); });
        });
    });
})();
</script>

<?php require_once 'layout-end.php'; ?>
