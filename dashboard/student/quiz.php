<?php
$title = 'Quiz';
$current_page = 'quiz.php';
require_once 'layout.php';
require_once '../../includes/db.php';
require_once '../../includes/quiz-builder.php';
require_once '../../includes/quiz-student.php';
require_role('student');

$student_id = (int)$_SESSION['user_id'];
$exam = null;
$pub = null;
try {
    quiz_student_fail_fast($pdo);
    quiz_student_ensure_schema($pdo);
    $exam_id = (int)($_GET['id'] ?? 0);
    $exam = $exam_id ? quiz_student_open_exam($pdo, $exam_id, $student_id) : null;
    if ($exam) {
        $pub = quiz_builder_public_exam($exam);
    }
} catch (Exception $e) {
    error_log('student quiz.php: ' . $e->getMessage());
    $exam = null;
}
if (!$exam || !is_array($pub)) {
    echo '<link href="../../assets/css/quiz-builder.css?v=qb9" rel="stylesheet">';
    echo '<div class="qb-page"><div class="qb-empty"><h5>آزمون پیدا نشد</h5><p>یا هنوز ارسال نشده یا برای کلاس شما نیست.</p><p><a class="qb-btn qb-btn-ghost" href="quizzes.php">بازگشت به Quizzes</a></p></div></div>';
    require_once 'layout-end.php';
    exit;
}
$boot = [
    'ajax' => 'ajax-quiz.php',
    'exam_id' => (int)$exam['id'],
];
?>
<link href="../../assets/css/quiz-builder.css?v=qb9" rel="stylesheet">
<div class="qb-page qb-take-page" data-qb-student data-boot="<?= quiz_builder_h(json_encode($boot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>">
    <div class="qb-head">
        <div>
            <h1><span><?= quiz_builder_h($pub['template_label']) ?></span></h1>
            <p><?= quiz_builder_h($exam['class_title'] ?? '') ?> · Session <?= (int)$pub['session_no'] ?> · <?= (int)$pub['minutes'] ?> دقیقه · نمره <?= (int)$pub['mark'] ?><?= !empty($pub['is_writing']) ? ' · چند خط درباره تاپیک بنویسید' : '' ?></p>
        </div>
        <a class="qb-btn qb-btn-ghost" href="quizzes.php">همه آزمون‌ها</a>
    </div>
    <div class="qb-timer" id="qbTimer" hidden>
        <strong id="qbTimerText">--:--</strong>
        <span id="qbTimerHint">زمان باقی‌مانده</span>
    </div>
    <div class="qb-alert" id="qbAlert" hidden></div>
    <noscript><div class="qb-alert err">برای شرکت در آزمون باید جاوااسکریپت روشن باشد.</div></noscript>
    <div class="qb-progress" id="qbProgress">
        <div class="qb-progress-bar"></div>
        <span>در حال باز کردن آزمون…</span>
    </div>
    <div id="qbTake" hidden></div>
</div>
<script src="../../assets/js/student-quiz.js?v=sq4"></script>
<?php require_once 'layout-end.php'; ?>
