<?php
$title = 'Students Information';
require_once 'layout.php';
require_once '../../includes/db.php';
require_once '../../includes/students-info.php';

$students = students_info_list($pdo);
?>

<div class="si-toolbar">
    <div class="d-flex gap-2 flex-grow-1 si-search-wrap c-search mb-0">
        <input type="search" id="siLiveSearch" class="form-control si-search" placeholder="جستجو: شماره پرونده، اسم، فامیل" autocomplete="off">
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
                <p class="mb-0">هنوز دانش‌آموزی در پنل ثبت نشده است.</p>
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
                                <a class="btn btn-sm btn-outline-light" href="students-info-view.php?id=<?= (int)$s['id'] ?>">Student Profile</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
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
})();
</script>

<?php require_once 'layout-end.php'; ?>
