<?php
$title = 'Quizzes';
$current_page = 'quizzes.php';
require_once 'layout.php';
require_once '../../includes/db.php';
require_once '../../includes/quiz-builder.php';
require_once '../../includes/quiz-student.php';
require_role('student');

$student_id = (int)$_SESSION['user_id'];
$list_error = '';
$groups = [];
try {
    quiz_student_fail_fast($pdo);
    quiz_student_ensure_schema($pdo);
    $rows = quiz_student_list($pdo, $student_id);
    foreach ($rows as $row) {
        $key = (string)($row['class_title'] ?? 'کلاس');
        try {
            $groups[$key][] = quiz_student_public_card($row);
        } catch (Exception $e) {
            error_log('quiz_student card: ' . $e->getMessage());
        }
    }
} catch (Exception $e) {
    error_log('student quizzes.php: ' . $e->getMessage());
    $list_error = 'الان لیست آزمون در دسترس نیست. یک بار دیگر صفحه را تازه کنید.';
}
?>
<link href="../../assets/css/quiz-builder.css?v=qb9" rel="stylesheet">
<div class="qb-page">
    <div class="qb-head">
        <div>
            <h1><span>Quizzes</span></h1>
            <p>آزمون‌هایی که معلم برای کلاس شما ارسال کرده اینجاست. زمان هر کوییز روی کارت نوشته شده؛ وقتی شروع کنید تایمر می‌افتد. Quiz 7 تاپیک رایتینگ است و چند خط می‌نویسید.</p>
        </div>
    </div>

    <?php if ($list_error): ?>
        <div class="qb-empty">
            <div class="qb-empty-icon">⏳</div>
            <h5>لیست آزمون لود نشد</h5>
            <p><?= quiz_builder_h($list_error) ?></p>
        </div>
    <?php elseif (!$groups): ?>
        <div class="qb-empty">
            <div class="qb-empty-icon">📝</div>
            <h5>هنوز آزمونی باز نشده</h5>
            <p>وقتی معلم از Quiz Builder آزمون را «ارسال به کلاس» کند، اینجا می‌آید.</p>
        </div>
    <?php else: ?>
        <?php foreach ($groups as $class_title => $exams): ?>
            <section class="qb-exams">
                <h2><?= quiz_builder_h($class_title) ?></h2>
                <div class="qb-cards">
                    <?php foreach ($exams as $exam):
                        $st = (string)$exam['attempt_status'];
                        $pill = $st === '' ? 'pending' : ($st === 'in_progress' ? 'extracting' : ($st === 'graded' ? 'ready' : 'draft'));
                    ?>
                        <a class="qb-card" href="quiz.php?id=<?= (int)$exam['id'] ?>">
                            <div>
                                <h2><?= quiz_builder_h($exam['template_label']) ?></h2>
                                <div class="qb-card-meta">
                                    <span class="qb-pill qb-pill-<?= quiz_builder_h($pill) ?>"><?= quiz_builder_h($exam['attempt_status_label']) ?></span>
                                    <span>S.<?= (int)$exam['session_no'] ?></span>
                                    <span><?= (int)$exam['minutes'] ?> دقیقه</span>
                                    <span>نمره <?= (int)$exam['mark'] ?></span>
                                    <span><?= !empty($exam['is_writing']) ? 'تاپیک رایتینگ' : ((int)$exam['question_count'] . ' سؤال') ?></span>
                                    <?php if ($exam['score_total'] !== null): ?>
                                        <span><?= (int)$exam['score_total'] ?> / <?= (int)$exam['score_max'] ?></span>
                                    <?php elseif ((int)$exam['pending_count'] > 0): ?>
                                        <span>منتظر نمره معلم</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <span class="qb-card-go"><?= quiz_builder_h($exam['cta']) ?> ←</span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
<?php require_once 'layout-end.php'; ?>
