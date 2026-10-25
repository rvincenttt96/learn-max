<?php
$title = 'Study Plan';
require_once 'layout.php';
require_once '../../includes/db.php';
require_once '../../includes/students-info.php';
require_once '../../includes/rollcall.php';
require_role('supervisor');

$students = students_info_list($pdo);
$last_sent = [];
if ($students) {
    $ids = array_map(static function ($row) {
        return (int)$row['id'];
    }, $students);
    $last_sent = rollcall_study_plan_last_sent($pdo, $ids);
}

$sms_preview = "اولیا گرامی (فامیلی) برنامه مطالعاتی فرزند شما برای ایشان ارسال گردید .\nپیشاپیش ممنون از همکاری شما\nآموزشگاه زبان های خارجه غزال";
?>

<style>
.sp-toolbar { display:flex; flex-wrap:wrap; gap:10px; align-items:center; justify-content:space-between; margin-bottom:16px; }
.sp-card { background:rgba(14,14,22,.72); border:1px solid rgba(127,29,29,.32); border-radius:20px; color:#fff; backdrop-filter:blur(24px); }
.sp-card .card-header { background:linear-gradient(90deg,rgba(127,29,29,.55),rgba(190,18,60,.32)); border:0; border-radius:20px 20px 0 0; }
.sp-table { color:#fff; margin:0; }
.sp-table thead th { white-space:nowrap; background:rgba(255,255,255,.06); }
.sp-table tbody tr:hover { background:rgba(211,47,47,.12); }
.sp-empty { text-align:center; padding:48px 16px; color:#cfc9d8; }
.sp-search { max-width:360px; }
.sp-file { font-variant-numeric: tabular-nums; font-weight:700; }
.sp-row-actions { display:flex; flex-wrap:wrap; gap:6px; align-items:center; }
.sp-preview { white-space:pre-line; background:rgba(0,0,0,.28); border:1px solid rgba(239,68,68,.22); border-radius:14px; padding:14px 16px; color:#f4e8ee; font-size:.95rem; line-height:1.8; }
.sp-sent { font-size:.78rem; color:#a5d6a7; }
.sp-nophone { font-size:.78rem; color:#ffcccb; }
@media (max-width: 767.98px) {
  .sp-toolbar { align-items:stretch; }
  .sp-search { max-width:none; }
  .sp-row-actions .btn { flex:1 1 auto; }
}
</style>

<div class="sp-toolbar">
    <div class="d-flex gap-2 flex-grow-1">
        <input type="search" id="spLiveSearch" class="form-control sp-search" placeholder="جستجو: شماره پرونده، اسم، فامیل" autocomplete="off">
    </div>
    <span class="badge bg-light text-dark" id="spCount" data-suffix=" دانش‌آموز"><?= count($students) ?> دانش‌آموز</span>
</div>

<div class="card sp-card mb-3">
    <div class="card-header">
        <h5 class="mb-0">متن پیامک Study Plan</h5>
    </div>
    <div class="card-body">
        <p class="mb-2 text-white-50">با دکمه Study Plan همین متن برای اولیا ارسال می‌شود. به‌جای (فامیلی) فامیل دانش‌آموز می‌نشیند.</p>
        <div class="sp-preview"><?= htmlspecialchars($sms_preview) ?></div>
    </div>
</div>

<div class="card sp-card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Study Plan</h5>
    </div>
    <div class="card-body p-0">
        <?php if (!$students): ?>
            <div class="sp-empty">
                <div style="font-size:2.4rem;margin-bottom:8px;">📚</div>
                <h5>دانش‌آموزی پیدا نشد</h5>
                <p class="mb-0">اول از Students Information دانش‌آموز اضافه کنید.</p>
            </div>
        <?php else: ?>
            <div class="sp-empty" id="spNoMatch" style="display:none">
                <h5>موردی پیدا نشد</h5>
                <p class="mb-0">عبارت جستجو را عوض کنید.</p>
            </div>
            <div class="table-responsive" id="spTableWrap">
                <table class="table table-bordered sp-table align-middle">
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
                            $family = trim((string)($s['last_name'] !== '' ? $s['last_name'] : ($s['full_name'] ?? '')));
                            $search_blob = trim($s['file_number'] . ' ' . $s['first_name'] . ' ' . $s['last_name'] . ' ' . ($s['full_name'] ?? '') . ' ' . ($s['phone'] ?? ''));
                            $has_phone = rollcall_normalize_phone($s['phone'] ?? '') !== '';
                            $sent_at = $last_sent[(int)$s['id']] ?? '';
                        ?>
                        <tr data-search="<?= htmlspecialchars($search_blob, ENT_QUOTES, 'UTF-8') ?>">
                            <td class="sp-file"><?= htmlspecialchars($s['file_number']) ?></td>
                            <td><?= htmlspecialchars($s['first_name'] !== '' ? $s['first_name'] : '—') ?></td>
                            <td><?= htmlspecialchars($s['last_name'] !== '' ? $s['last_name'] : '—') ?></td>
                            <td>
                                <div class="sp-row-actions">
                                    <button type="button"
                                        class="btn btn-sm btn-warning sp-send"
                                        data-id="<?= (int)$s['id'] ?>"
                                        data-family="<?= htmlspecialchars($family, ENT_QUOTES, 'UTF-8') ?>"
                                        data-name="<?= htmlspecialchars(trim($s['first_name'] . ' ' . $s['last_name']), ENT_QUOTES, 'UTF-8') ?>"
                                        <?= $has_phone ? '' : 'disabled' ?>>
                                        Study Plan
                                    </button>
                                    <?php if (!$has_phone): ?>
                                        <span class="sp-nophone">شماره تماس ندارد</span>
                                    <?php elseif ($sent_at !== ''): ?>
                                        <span class="sp-sent">ارسال شد</span>
                                    <?php endif; ?>
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

<script>
(function () {
    function spNorm(s) {
        return String(s || '').toLowerCase()
            .replace(/[۰-۹]/g, function (d) { return '0123456789'['۰۱۲۳۴۵۶۷۸۹'.indexOf(d)]; })
            .replace(/[٠-٩]/g, function (d) { return '0123456789'['٠١٢٣٤٥٦٧٨٩'.indexOf(d)]; });
    }
    const searchInput = document.getElementById('spLiveSearch');
    const countEl = document.getElementById('spCount');
    const emptyEl = document.getElementById('spNoMatch');
    const tableWrap = document.getElementById('spTableWrap');
    const rows = document.querySelectorAll('#spTableWrap tbody tr');
    function filterRows() {
        if (!searchInput || !rows.length) return;
        const q = spNorm(searchInput.value).trim();
        let n = 0;
        rows.forEach(function (row) {
            const hay = spNorm(row.getAttribute('data-search') || row.textContent);
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

    document.querySelectorAll('.sp-send').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const family = btn.getAttribute('data-family') || '';
            const name = btn.getAttribute('data-name') || '';
            const label = family || name || 'این دانش‌آموز';
            if (!confirm('پیامک برنامه مطالعاتی برای اولیا گرامی «' + label + '» ارسال شود؟')) {
                return;
            }
            const prev = btn.textContent;
            btn.disabled = true;
            btn.textContent = 'در حال ارسال...';
            fetch('study-plan-send.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ student_id: btn.getAttribute('data-id') })
            })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                alert(data.message || (data.ok || data.success ? 'پیامک ارسال شد.' : 'ارسال ناموفق بود.'));
                if (data.ok || data.success) {
                    let badge = btn.parentNode.querySelector('.sp-sent');
                    if (!badge) {
                        badge = document.createElement('span');
                        badge.className = 'sp-sent';
                        btn.parentNode.appendChild(badge);
                    }
                    badge.textContent = 'ارسال شد';
                }
            })
            .catch(function () {
                alert('خطا در ارتباط با سرور.');
            })
            .finally(function () {
                btn.disabled = false;
                btn.textContent = prev;
            });
        });
    });
})();
</script>

<?php require_once 'layout-end.php'; ?>
