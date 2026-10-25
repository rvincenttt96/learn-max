<?php
$title = 'Psych Tests';
$current_page = 'psych-tests.php';
require_once 'layout.php';
require_once '../../includes/db.php';
require_once '../../includes/psych-tests.php';
require_role('consultant');
psych_tests_ensure_schema($pdo);

$students = psych_tests_consultant_students($pdo);
?>
<link href="../../assets/css/psych-tests.css?v=pt1" rel="stylesheet">
<div class="pt-page">
    <div class="pt-head">
        <div>
            <h1>آزمون روانشناسی</h1>
            <p>نتیجه NEO-60 و Big Five فقط برای مشاور است. دانش‌آموز آزمون را در پنل خودش می‌دهد؛ نمره عوامل اینجا دیده می‌شود.</p>
        </div>
        <span class="pt-lock">فقط نقش Consultant</span>
    </div>

    <div class="c-search">
        <input type="search" id="ptSearch" class="form-control" placeholder="جستجو با اسم یا کد دانش‌آموز" autocomplete="off" autofocus>
    </div>

    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <h5 class="mb-0">دانش‌آموزان</h5>
        <span class="c-count" id="ptCount" data-suffix=" دانش‌آموز"><?= count($students) ?> دانش‌آموز</span>
    </div>

    <?php if (!$students): ?>
        <div class="pt-empty">
            <h5>دانش‌آموزی پیدا نشد</h5>
            <p class="mb-0">وقتی دانش‌آموز در پنل باشد، اینجا دیده می‌شود.</p>
        </div>
    <?php else: ?>
        <div class="pt-empty" id="ptNoMatch" style="display:none">
            <h5>موردی پیدا نشد</h5>
            <p class="mb-0">اسم یا کد را عوض کنید.</p>
        </div>
        <div class="cml-list" id="ptList">
            <?php foreach ($students as $s):
                $blob = trim(($s['full_name'] ?? '') . ' ' . ($s['username'] ?? ''));
                $initial = mb_substr((string)$s['full_name'], 0, 1);
                $neo_done = !empty($s['neo_scores']);
                $bf_done = !empty($s['bf_scores']);
            ?>
            <a class="cml-card pt-card" href="psych-view.php?id=<?= (int)$s['id'] ?>" data-search="<?= psych_tests_h($blob) ?>">
                <div class="cml-head">
                    <?php if (!empty($s['avatar'])): ?>
                        <img src="<?= psych_tests_h($s['avatar']) ?>" class="cml-avatar" alt="">
                    <?php else: ?>
                        <div class="cml-avatar"><?= psych_tests_h($initial) ?></div>
                    <?php endif; ?>
                    <div>
                        <p class="cml-name"><?= psych_tests_h($s['full_name']) ?></p>
                        <div class="cml-meta">کد: <span class="ltr-num"><?= psych_tests_h($s['username']) ?></span></div>
                    </div>
                </div>
                <div class="pt-meta">
                    <span class="pt-pill <?= $neo_done ? 'done' : '' ?>">NEO-60: <?= $neo_done ? 'ثبت شده' : 'ندارد' ?></span>
                    <span class="pt-pill <?= $bf_done ? 'done' : '' ?>">Big Five: <?= $bf_done ? 'ثبت شده' : 'ندارد' ?></span>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
<script>
(function () {
    const input = document.getElementById('ptSearch');
    const list = document.getElementById('ptList');
    const empty = document.getElementById('ptNoMatch');
    const count = document.getElementById('ptCount');
    if (!input || !list) return;
    const cards = Array.prototype.slice.call(list.querySelectorAll('[data-search]'));
    const suffix = (count && count.getAttribute('data-suffix')) || '';
    input.addEventListener('input', function () {
        const q = (input.value || '').trim().toLowerCase();
        let n = 0;
        cards.forEach(function (card) {
            const blob = (card.getAttribute('data-search') || '').toLowerCase();
            const show = !q || blob.indexOf(q) !== -1;
            card.style.display = show ? '' : 'none';
            if (show) n += 1;
        });
        if (empty) empty.style.display = n ? 'none' : '';
        if (count) count.textContent = n + suffix;
    });
})();
</script>
<?php require_once 'layout-end.php'; ?>
