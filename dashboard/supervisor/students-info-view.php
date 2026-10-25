<?php
$title = 'سوابق تحصیلی';
require_once 'layout.php';
require_once '../../includes/db.php';
require_once '../../includes/students-info.php';
require_role('supervisor');

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
?>

<style>
.si-hero { background:rgba(18,14,40,.9); border:1px solid rgba(211,47,47,.28); border-radius:16px; padding:18px 20px; color:#fff; margin-bottom:18px; }
.si-term { background:rgba(18,14,40,.88); border:1px solid rgba(255,255,255,.08); border-radius:16px; color:#fff; margin-bottom:16px; overflow:hidden; }
.si-term h5 { margin:0; }
.si-term .head { background:linear-gradient(90deg,#7b1a1a,#d32f2f); padding:12px 16px; display:flex; justify-content:space-between; gap:10px; flex-wrap:wrap; }
.si-term .body { padding:16px; }
.si-table { color:#fff; }
.si-table thead th { background:rgba(255,255,255,.06); white-space:nowrap; }
.si-pill { display:inline-block; background:rgba(255,255,255,.08); border-radius:999px; padding:4px 10px; margin:0 4px 4px 0; font-size:.85rem; }
.si-empty { text-align:center; padding:40px 12px; color:#cfc9d8; }
</style>

<div class="si-hero d-flex flex-wrap justify-content-between gap-3 align-items-center">
    <div>
        <div class="text-white-50 small mb-1">Students Information</div>
        <h4 class="mb-1"><?= htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) ?></h4>
        <div>شماره پرونده: <strong><?= htmlspecialchars($student['file_number']) ?></strong></div>
    </div>
    <a class="btn btn-outline-light" href="students-info.php">بازگشت به لیست</a>
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
<style>
#reportCardModal .modal-content { background:transparent; border:none; }
#reportCardModal .modal-body { display:flex; flex-direction:column; align-items:center; padding:15px; }
.download-btn { background:linear-gradient(45deg,#e94560,#ff2e63); color:#fff; border:none; padding:12px 25px; border-radius:12px; font-weight:700; margin-bottom:20px; }
.report-canvas { width:100%; max-width:600px; font-family:'YekanBakhFaNum', Tahoma, Arial, sans-serif; background:linear-gradient(145deg,#0c0a12 0%,#161022 100%); border-radius:24px; padding:30px; color:#fff; border:1px solid rgba(127,29,29,.35); }
.rc-header { text-align:center; margin-bottom:24px; }
.rc-student-info, .rc-grades-grid { display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:18px; }
.rc-grade-card, .rc-info-item { background:rgba(255,255,255,.04); border-radius:14px; padding:14px; }
.rc-highlight-card { grid-column:span 2; display:flex; justify-content:space-between; background:rgba(233,69,96,.18); border-radius:14px; padding:16px; }
.rc-final-score { font-size:2rem; font-weight:900; color:#e94560; }
</style>

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
</script>

<?php require_once 'layout-end.php'; ?>
