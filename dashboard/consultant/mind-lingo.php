<?php
$title = 'Mind Lingo';
$current_page = 'mind-lingo.php';
require_once 'layout.php';
require_once '../../includes/db.php';

$students = consultant_mind_lingo_students($pdo);
?>

<div class="mind-lingo-page">
    <div class="c-search">
        <input type="search" id="cmlSearch" class="form-control" placeholder="جستجو با اسم دانش‌آموز" autocomplete="off" autofocus>
    </div>

    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <h5 class="mb-0">Mind Lingo — ترم جاری</h5>
        <span class="c-count" id="cmlCount" data-suffix=" دانش‌آموز"><?= count($students) ?> دانش‌آموز</span>
    </div>

    <?php if (!$students): ?>
        <div class="c-empty cml-card">
            <div style="font-size:2.2rem;margin-bottom:8px;">🧠</div>
            <h5>دانش‌آموزی در ترم جاری نیست</h5>
            <p class="mb-0">وقتی کلاسی در ترم تدریس باشد، اینجا دیده می‌شود.</p>
        </div>
    <?php else: ?>
        <div class="c-empty" id="cmlNoMatch" style="display:none">
            <h5>موردی پیدا نشد</h5>
            <p class="mb-0">اسم را عوض کنید یا کامل‌تر بنویسید.</p>
        </div>
        <div class="cml-list" id="cmlList">
            <?php foreach ($students as $student):
                $blob = trim(
                    ($student['full_name'] ?? '') . ' ' .
                    ($student['username'] ?? '') . ' ' .
                    implode(' ', array_column($student['classes'], 'class_title')) . ' ' .
                    implode(' ', array_column($student['classes'], 'teacher_name'))
                );
                $initial = mb_substr((string)$student['full_name'], 0, 1);
            ?>
            <article class="cml-card" data-search="<?= consultant_h($blob) ?>">
                <div class="cml-head">
                    <?php if (!empty($student['avatar'])): ?>
                        <img src="<?= consultant_h($student['avatar']) ?>" class="cml-avatar" alt="">
                    <?php else: ?>
                        <div class="cml-avatar"><?= consultant_h($initial) ?></div>
                    <?php endif; ?>
                    <div>
                        <p class="cml-name"><?= consultant_h($student['full_name']) ?></p>
                        <div class="cml-meta">کد: <span class="ltr-num"><?= consultant_h($student['username']) ?></span></div>
                    </div>
                </div>
                <?php foreach ($student['classes'] as $cls):
                    $payload = [
                        'full_name' => $student['full_name'],
                        'class_title' => $cls['class_title'],
                        'teacher_name' => $cls['teacher_name'],
                        'communication_dev' => $cls['communication_dev'],
                        'learning_eng' => $cls['learning_eng'],
                        'learning_cons' => $cls['learning_cons'],
                        'personal_growth' => $cls['personal_growth'],
                        'teacher_note' => $cls['teacher_note'],
                    ];
                ?>
                <div class="cml-class">
                    <div class="cml-class-title">
                        <?= consultant_h($cls['class_title']) ?>
                        <?php if ($cls['schedule_label'] !== ''): ?>
                            <div class="cml-class-sub"><?= consultant_h($cls['schedule_label']) ?></div>
                        <?php endif; ?>
                        <?php if (!empty($cls['teacher_name'])): ?>
                            <div class="cml-class-sub"><?= consultant_h($cls['teacher_name']) ?></div>
                        <?php endif; ?>
                    </div>
                    <div>
                        <?php if (!empty($cls['evaluated'])): ?>
                            <span class="badge bg-success score-badge ltr-num"><?= (int)$cls['total'] ?> / 40</span>
                        <?php else: ?>
                            <span class="badge bg-warning text-dark score-badge">در انتظار</span>
                        <?php endif; ?>
                    </div>
                    <div>
                        <?php if (!empty($cls['evaluated'])): ?>
                            <button type="button" class="btn btn-sm btn-danger px-3 fw-bold"
                                onclick='viewDetails(<?= consultant_h(json_encode($payload, JSON_UNESCAPED_UNICODE)) ?>)'>
                                مشاهده
                            </button>
                        <?php else: ?>
                            <button type="button" class="btn btn-sm btn-secondary px-3" disabled>بدون داده</button>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div class="modal fade" id="detailsModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">جزئیات ارزیابی — <span id="det-student-name"></span></h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="بستن"></button>
                </div>
                <div class="modal-body p-4">
                    <p class="text-white-50 mb-4" id="det-class-line"></p>
                    <div class="row g-4">
                        <div class="col-12 col-md-6">
                            <label class="fw-bold mb-1">رشد ارتباطی</label>
                            <div class="progress">
                                <div id="bar-comm" class="progress-bar bg-danger" role="progressbar" style="width: 0%;">0/10</div>
                            </div>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="fw-bold mb-1">مشارکت در یادگیری</label>
                            <div class="progress">
                                <div id="bar-eng" class="progress-bar bg-success" role="progressbar" style="width: 0%;">0/10</div>
                            </div>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="fw-bold mb-1">پیوستگی یادگیری</label>
                            <div class="progress">
                                <div id="bar-cons" class="progress-bar bg-warning text-dark" role="progressbar" style="width: 0%;">0/10</div>
                            </div>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="fw-bold mb-1">رشد فردی</label>
                            <div class="progress">
                                <div id="bar-growth" class="progress-bar bg-info" role="progressbar" style="width: 0%;">0/10</div>
                            </div>
                        </div>
                        <div class="col-12 mt-4">
                            <label class="fw-bold mb-2">یادداشت معلم</label>
                            <div id="det-note" class="note-box p-3"></div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">بستن</button>
                </div>
            </div>
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
    const searchInput = document.getElementById('cmlSearch');
    const countEl = document.getElementById('cmlCount');
    const emptyEl = document.getElementById('cmlNoMatch');
    const listEl = document.getElementById('cmlList');
    const cards = document.querySelectorAll('#cmlList .cml-card');
    function filterCards() {
        if (!searchInput || !cards.length) return;
        const q = siNorm(searchInput.value).trim();
        let n = 0;
        cards.forEach(function (card) {
            const hay = siNorm(card.getAttribute('data-search') || card.textContent);
            const show = !q || hay.indexOf(q) !== -1;
            card.style.display = show ? '' : 'none';
            if (show) n++;
        });
        if (countEl) countEl.textContent = n + (countEl.getAttribute('data-suffix') || '');
        if (emptyEl) emptyEl.style.display = n === 0 ? '' : 'none';
        if (listEl) listEl.style.display = n === 0 ? 'none' : '';
    }
    if (searchInput) {
        searchInput.addEventListener('input', filterCards);
        searchInput.addEventListener('search', filterCards);
    }
})();

function viewDetails(student) {
    document.getElementById('det-student-name').innerText = student.full_name || '';
    const classBits = [student.class_title, student.teacher_name].filter(Boolean);
    document.getElementById('det-class-line').innerText = classBits.join(' — ');

    document.getElementById('bar-comm').style.width = (student.communication_dev * 10) + '%';
    document.getElementById('bar-comm').innerText = student.communication_dev + ' / 10';

    document.getElementById('bar-eng').style.width = (student.learning_eng * 10) + '%';
    document.getElementById('bar-eng').innerText = student.learning_eng + ' / 10';

    document.getElementById('bar-cons').style.width = (student.learning_cons * 10) + '%';
    document.getElementById('bar-cons').innerText = student.learning_cons + ' / 10';

    document.getElementById('bar-growth').style.width = (student.personal_growth * 10) + '%';
    document.getElementById('bar-growth').innerText = student.personal_growth + ' / 10';

    const noteBox = document.getElementById('det-note');
    if (student.teacher_note && String(student.teacher_note).trim() !== '') {
        noteBox.innerText = student.teacher_note;
        noteBox.classList.remove('text-muted', 'fst-italic');
    } else {
        noteBox.innerText = 'یادداشتی توسط معلم ثبت نشده است.';
        noteBox.classList.add('text-muted', 'fst-italic');
    }

    new bootstrap.Modal(document.getElementById('detailsModal')).show();
}
</script>

<?php require_once 'layout-end.php'; ?>
