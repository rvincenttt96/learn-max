<?php
$title = 'سوابق تحصیلی';
require_once 'layout.php';
require_once '../../includes/db.php';
require_once '../../includes/students-info.php';
require_once '../../includes/psych-tests.php';

$id = (int)($_GET['id'] ?? 0);
$student = $id ? students_info_find_student($pdo, $id) : null;
if (!$student) {
    echo '<div class="alert alert-danger">دانش‌آموز پیدا نشد.</div>';
    echo '<a class="btn btn-light" href="students-info.php">بازگشت</a>';
    require_once 'layout-end.php';
    return;
}
$history = students_info_history($pdo, (int)$student['id']);
$labels = students_info_quiz_labels();
$remarks = consultant_remarks_for_student($pdo, (int)$student['id']);
$me = (int)($_SESSION['user_id'] ?? 0);
psych_tests_ensure_schema($pdo);
$psych_rows = psych_tests_for_student($pdo, (int)$student['id']);
?>

<div class="si-hero d-flex flex-wrap justify-content-between gap-3 align-items-center">
    <div>
        <div class="text-white-50 small mb-1">Students Information</div>
        <h4 class="mb-1"><?= htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) ?></h4>
        <div>شماره پرونده: <strong><?= htmlspecialchars($student['file_number']) ?></strong></div>
    </div>
    <a class="btn btn-outline-light" href="students-info.php">بازگشت به لیست</a>
</div>

<link href="../../assets/css/psych-tests.css?v=pt1" rel="stylesheet">
<div class="pt-page">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
        <h5 class="mb-0">آزمون روانشناسی</h5>
        <a class="btn btn-sm btn-outline-light" href="psych-view.php?id=<?= (int)$student['id'] ?>">نمایش کامل</a>
    </div>
    <?= psych_tests_render_result($psych_rows['neo60'] ?? null, false, 'neo60') ?>
    <?= psych_tests_render_result($psych_rows['bigfive'] ?? null, false, 'bigfive') ?>
</div>

<div class="cr-box">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <h5 class="mb-0">ریمارک مشاور</h5>
        <span class="cr-lock">فقط نقش Consultant این بخش را می‌بیند</span>
    </div>
    <form id="crForm" class="cr-form mb-3">
        <input type="hidden" name="student_id" value="<?= (int)$student['id'] ?>">
        <label class="form-label" for="crBody">ریمارک جدید</label>
        <textarea id="crBody" name="body" class="form-control mb-2" maxlength="4000" required placeholder="یادداشت مشاور درباره این دانش‌آموز…"></textarea>
        <button type="submit" class="btn btn-warning" id="crSubmit">ثبت ریمارک</button>
        <div class="text-danger small mt-2" id="crError" style="display:none"></div>
    </form>
    <div id="crList">
        <?php if (!$remarks): ?>
            <p class="text-white-50 mb-0" id="crEmpty">هنوز ریمارکی ثبت نشده است.</p>
        <?php else: ?>
            <?php foreach ($remarks as $r): ?>
            <div class="cr-item" data-id="<?= (int)$r['id'] ?>">
                <div class="cr-meta">
                    <span><?= htmlspecialchars($r['consultant_name'] ?: 'مشاور') ?> · <?= htmlspecialchars(consultant_remark_when($r['created_at'])) ?></span>
                    <?php if ((int)$r['consultant_id'] === $me): ?>
                        <button type="button" class="btn btn-sm btn-outline-danger cr-del" data-id="<?= (int)$r['id'] ?>">حذف</button>
                    <?php endif; ?>
                </div>
                <div class="cr-body"><?= nl2br(htmlspecialchars($r['body'])) ?></div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php if (!$history): ?>
    <div class="si-term">
        <div class="si-empty">
            <h5>سابقه کلاسی ثبت نشده</h5>
            <p class="mb-0">این دانش‌آموز هنوز در هیچ کلاسی نیست.</p>
        </div>
    </div>
<?php else: ?>
    <?php foreach ($history as $h):
        $t = $h['totals'];
        $report = [
            'student_name' => $student['full_name'],
            'student_id' => $student['file_number'],
            'class_name' => $h['class_title'],
            'teacher_name' => $h['teacher_name'] ?? 'N/A',
            'quiz_total' => $t['quiz_total'],
            'class_activity' => $t['activity'],
            'presence' => $t['presence'],
            'sup_score' => (int)$t['supervisor_score'],
            'final_score' => $t['final_score'],
            'ml_score' => $t['ml_score'],
        ];
    ?>
    <div class="si-term">
        <div class="head">
            <h5><?= htmlspecialchars(($h['term_title'] ?: 'بدون ترم') . ' — ' . ($h['level'] ?: $h['class_title'])) ?></h5>
            <small><?= htmlspecialchars($h['teacher_name'] ?: 'مدرس ثبت نشده') ?></small>
        </div>
        <div class="body">
            <div class="mb-3">
                <span class="si-pill">حضور: <?= htmlspecialchars($h['attendance_label']) ?></span>
                <?php if ($t['has_quiz_scores']): ?>
                    <span class="si-pill">نمره نهایی: <?= (int)$t['final_score'] ?></span>
                <?php endif; ?>
            </div>

            <h6 class="mb-2">گزارش حضور</h6>
            <?php if (empty($h['sessions'])): ?>
                <p class="text-white-50">جلسه‌ای ثبت نشده است.</p>
            <?php else: ?>
                <div class="table-responsive mb-3">
                    <table class="table table-sm table-bordered si-table">
                        <thead>
                            <tr>
                                <th>تاریخ جلسه</th>
                                <th>وضعیت</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($h['sessions'] as $sess): ?>
                            <tr>
                                <td><?= htmlspecialchars($sess['class_date']) ?></td>
                                <td>
                                    <?php if (!empty($sess['present'])): ?>
                                        <span class="badge bg-success">حاضر</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">غایب</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <h6 class="mb-2">نمرات کوییز</h6>
            <div class="table-responsive mb-3">
                <table class="table table-sm table-bordered si-table">
                    <thead>
                        <tr>
                            <?php foreach ($labels as $label): ?>
                                <th><?= htmlspecialchars($label) ?></th>
                            <?php endforeach; ?>
                            <th>جمع کلاس</th>
                            <th>نمره سوپروایزر</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <?php if ($t['has_quiz_scores']): ?>
                                <?php foreach ($t['quizzes'] as $quiz): ?>
                                    <td><?= ($quiz === null || $quiz === '') ? '—' : (int)$quiz ?></td>
                                <?php endforeach; ?>
                                <td><?= (int)$t['class_total'] ?></td>
                                <td><?= $t['supervisor_score'] === null ? '—' : (int)$t['supervisor_score'] ?></td>
                            <?php else: ?>
                                <td colspan="<?= count($labels) + 2 ?>" class="text-center text-white-50">نمره‌ای ثبت نشده است</td>
                            <?php endif; ?>
                        </tr>
                    </tbody>
                </table>
            </div>

            <button type="button" class="btn btn-primary" <?= $t['has_quiz_scores'] ? '' : 'disabled' ?>
                onclick='openReportModal(<?= htmlspecialchars(json_encode($report, JSON_UNESCAPED_UNICODE), ENT_QUOTES, "UTF-8") ?>)'>
                نمایش ریپورت کارت
            </button>
        </div>
    </div>
    <?php endforeach; ?>
<?php endif; ?>

<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>

<div class="modal fade" id="reportCardModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-body">
                <button class="download-btn" type="button" onclick="downloadReport()">Save as Image</button>
                <div class="report-canvas" id="export-target">
                    <div class="rc-header">
                        <h1>GHAZAL ACADEMY</h1>
                        <p>Term Performance Report</p>
                    </div>
                    <div class="rc-student-info">
                        <div class="rc-info-item"><p>Learner</p><h4 id="rc-student-name"></h4></div>
                        <div class="rc-info-item"><p>Class / Level</p><h4 id="rc-class-name"></h4></div>
                        <div class="rc-info-item"><p>Student ID</p><h4 id="rc-student-id"></h4></div>
                        <div class="rc-info-item"><p>Instructor</p><h4 id="rc-instructor"></h4></div>
                    </div>
                    <div class="rc-grades-grid">
                        <div class="rc-grade-card"><p>Quiz Total</p><h2 id="rc-quiz">0</h2></div>
                        <div class="rc-grade-card"><p>Class Activity</p><h2 id="rc-activity">0</h2></div>
                        <div class="rc-grade-card"><p>Presence</p><h2 id="rc-presence">0</h2></div>
                        <div class="rc-highlight-card">
                            <div><p>SUPERVISOR MARK</p><h2 id="rc-sup">0</h2></div>
                            <div><p>FINAL SCORE</p><div class="rc-final-score" id="rc-final">0</div></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
function openReportModal(data) {
    document.getElementById('rc-student-name').innerText = data.student_name || '';
    document.getElementById('rc-student-id').innerText = data.student_id || '';
    document.getElementById('rc-class-name').innerText = data.class_name || '';
    document.getElementById('rc-instructor').innerText = data.teacher_name || '';
    document.getElementById('rc-quiz').innerText = data.quiz_total;
    document.getElementById('rc-activity').innerText = data.class_activity;
    document.getElementById('rc-presence').innerText = data.presence;
    document.getElementById('rc-sup').innerText = data.sup_score;
    document.getElementById('rc-final').innerText = data.final_score;
    new bootstrap.Modal(document.getElementById('reportCardModal')).show();
}
function downloadReport() {
    const btn = document.querySelector('.download-btn');
    btn.disabled = true;
    html2canvas(document.getElementById('export-target'), { scale: 2, backgroundColor: '#0f0c29' }).then(canvas => {
        const link = document.createElement('a');
        link.download = 'Ghazal_Academy_Report.jpg';
        link.href = canvas.toDataURL('image/jpeg', 0.95);
        link.click();
        btn.disabled = false;
    }).catch(() => { btn.disabled = false; });
}

(function () {
    const form = document.getElementById('crForm');
    const err = document.getElementById('crError');
    const submitBtn = document.getElementById('crSubmit');
    function showErr(msg) {
        if (!err) return;
        err.textContent = msg || '';
        err.style.display = msg ? '' : 'none';
    }
    if (form) {
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            showErr('');
            const body = (document.getElementById('crBody').value || '').trim();
            if (!body) {
                showErr('متن ریمارک را بنویسید.');
                return;
            }
            submitBtn.disabled = true;
            fetch('ajax-remark.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'add',
                    student_id: form.querySelector('[name="student_id"]').value,
                    body: body
                })
            })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.success) {
                    location.reload();
                    return;
                }
                showErr(data.message || 'خطا در ثبت ریمارک.');
                submitBtn.disabled = false;
            })
            .catch(function () {
                showErr('خطا در ارتباط با سرور.');
                submitBtn.disabled = false;
            });
        });
    }
    document.querySelectorAll('.cr-del').forEach(function (btn) {
        btn.addEventListener('click', function () {
            if (!confirm('این ریمارک حذف شود؟')) return;
            fetch('ajax-remark.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'delete', remark_id: btn.getAttribute('data-id') })
            })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.success) {
                    location.reload();
                    return;
                }
                alert(data.message || 'حذف نشد.');
            })
            .catch(function () { alert('خطا در حذف ریمارک.'); });
        });
    });
})();
</script>

<?php require_once 'layout-end.php'; ?>
