<?php
$title = 'Teachers Information';
require_once 'layout.php';
require_once '../../includes/db.php';
require_once '../../includes/teachers-info.php';
require_role('supervisor');

$teachers = teachers_info_list($pdo);
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
        <input type="search" id="tiLiveSearch" class="form-control si-search" placeholder="جستجو: نام کاربری، اسم، فامیل" autocomplete="off">
    </div>
    <div class="si-actions">
        <button type="button" class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#tiAddModal">Add Teacher</button>
    </div>
</div>

<div class="card si-card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Teachers Information</h5>
        <span class="badge bg-light text-dark" id="tiCount" data-suffix=" استاد"><?= count($teachers) ?> استاد</span>
    </div>
    <div class="card-body p-0">
        <?php if (!$teachers): ?>
            <div class="si-empty">
                <div style="font-size:2.4rem;margin-bottom:8px;">👩‍🏫</div>
                <h5>استادی پیدا نشد</h5>
                <p class="mb-0">با Add Teacher استاد اضافه کنید، یا جستجو را عوض کنید.</p>
            </div>
        <?php else: ?>
            <div class="si-empty" id="tiNoMatch" style="display:none">
                <h5>موردی پیدا نشد</h5>
                <p class="mb-0">عبارت جستجو را عوض کنید.</p>
            </div>
            <div class="table-responsive" id="tiTableWrap">
                <table class="table table-bordered si-table align-middle">
                    <thead>
                        <tr>
                            <th>نام کاربری</th>
                            <th>اسم</th>
                            <th>فامیل</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($teachers as $t):
                            $search_blob = trim($t['username'] . ' ' . $t['first_name'] . ' ' . $t['last_name'] . ' ' . ($t['full_name'] ?? '') . ' ' . ($t['phone'] ?? ''));
                        ?>
                        <tr data-search="<?= htmlspecialchars($search_blob, ENT_QUOTES, 'UTF-8') ?>">
                            <td class="si-file"><?= htmlspecialchars($t['username']) ?></td>
                            <td><?= htmlspecialchars($t['first_name'] !== '' ? $t['first_name'] : '—') ?></td>
                            <td><?= htmlspecialchars($t['last_name'] !== '' ? $t['last_name'] : '—') ?></td>
                            <td>
                                <div class="si-row-actions">
                                    <a class="btn btn-sm btn-outline-light" href="teachers-info-view.php?id=<?= (int)$t['id'] ?>">Teacher Profile</a>
                                    <button type="button" class="btn btn-sm btn-info ti-edit"
                                        data-id="<?= (int)$t['id'] ?>"
                                        data-user="<?= htmlspecialchars($t['username'], ENT_QUOTES, 'UTF-8') ?>"
                                        data-first="<?= htmlspecialchars($t['first_name'], ENT_QUOTES, 'UTF-8') ?>"
                                        data-last="<?= htmlspecialchars($t['last_name'], ENT_QUOTES, 'UTF-8') ?>"
                                        data-phone="<?= htmlspecialchars((string)($t['phone'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">Edit</button>
                                    <button type="button" class="btn btn-sm btn-danger ti-delete"
                                        data-id="<?= (int)$t['id'] ?>"
                                        data-name="<?= htmlspecialchars(trim($t['first_name'] . ' ' . $t['last_name']), ENT_QUOTES, 'UTF-8') ?>">Delete</button>
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

<div class="modal fade" id="tiAddModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="background:rgba(15,15,24,.96);color:#fff;border:1px solid rgba(239,68,68,.3);border-radius:24px;">
            <form id="tiAddForm">
                <div class="modal-header border-secondary">
                    <h5 class="modal-title">Add Teacher</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">نام کاربری</label>
                        <input type="text" name="username" id="tiAddUser" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">اسم</label>
                        <input type="text" name="first_name" id="tiAddFirst" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">فامیل</label>
                        <input type="text" name="last_name" id="tiAddLast" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">شماره تماس</label>
                        <input type="text" name="phone" id="tiAddPhone" class="form-control">
                    </div>
                    <p class="text-white-50 small mb-0">اگر رمز خالی بماند همان نام کاربری است.</p>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">انصراف</button>
                    <button type="submit" class="btn btn-warning">ثبت</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="tiEditModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="background:rgba(15,15,24,.96);color:#fff;border:1px solid rgba(239,68,68,.3);border-radius:24px;">
            <form id="tiEditForm">
                <div class="modal-header border-secondary">
                    <h5 class="modal-title">Edit</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="user_id" id="tiEditId">
                    <div class="mb-3">
                        <label class="form-label">نام کاربری</label>
                        <input type="text" name="username" id="tiEditUser" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">اسم</label>
                        <input type="text" name="first_name" id="tiEditFirst" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">فامیل</label>
                        <input type="text" name="last_name" id="tiEditLast" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">شماره تماس</label>
                        <input type="text" name="phone" id="tiEditPhone" class="form-control">
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
    const searchInput = document.getElementById('tiLiveSearch');
    const countEl = document.getElementById('tiCount');
    const emptyEl = document.getElementById('tiNoMatch');
    const tableWrap = document.getElementById('tiTableWrap');
    const rows = document.querySelectorAll('#tiTableWrap tbody tr');
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

    const editModalEl = document.getElementById('tiEditModal');
    const editModal = editModalEl ? new bootstrap.Modal(editModalEl) : null;

    document.querySelectorAll('.ti-edit').forEach(function (btn) {
        btn.addEventListener('click', function () {
            document.getElementById('tiEditId').value = btn.getAttribute('data-id') || '';
            document.getElementById('tiEditUser').value = btn.getAttribute('data-user') || '';
            document.getElementById('tiEditFirst').value = btn.getAttribute('data-first') || '';
            document.getElementById('tiEditLast').value = btn.getAttribute('data-last') || '';
            document.getElementById('tiEditPhone').value = btn.getAttribute('data-phone') || '';
            if (editModal) editModal.show();
        });
    });

    const form = document.getElementById('tiEditForm');
    if (form) {
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            fetch('teachers-info-save.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'edit',
                    user_id: document.getElementById('tiEditId').value,
                    username: document.getElementById('tiEditUser').value,
                    first_name: document.getElementById('tiEditFirst').value,
                    last_name: document.getElementById('tiEditLast').value,
                    phone: document.getElementById('tiEditPhone').value
                })
            })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                alert(data.message || (data.success ? 'ذخیره شد.' : 'خطا'));
                if (data.success) location.reload();
            })
            .catch(function () { alert('خطا در ذخیره اطلاعات.'); });
        });
    }

    const addForm = document.getElementById('tiAddForm');
    if (addForm) {
        addForm.addEventListener('submit', function (e) {
            e.preventDefault();
            fetch('teachers-info-save.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'add',
                    username: document.getElementById('tiAddUser').value,
                    first_name: document.getElementById('tiAddFirst').value,
                    last_name: document.getElementById('tiAddLast').value,
                    phone: document.getElementById('tiAddPhone').value
                })
            })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                alert(data.message || (data.success ? 'ثبت شد.' : 'خطا'));
                if (data.success) location.reload();
            })
            .catch(function () { alert('خطا در ثبت استاد.'); });
        });
    }

    document.querySelectorAll('.ti-delete').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const name = btn.getAttribute('data-name') || '';
            if (!confirm('استاد «' + name + '» حذف شود؟')) return;
            fetch('teachers-info-save.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'delete', user_id: btn.getAttribute('data-id') })
            })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                alert(data.message || (data.success ? 'حذف شد.' : 'خطا'));
                if (data.success) location.reload();
            })
            .catch(function () { alert('خطا در حذف استاد.'); });
        });
    });
})();
</script>

<?php require_once 'layout-end.php'; ?>
