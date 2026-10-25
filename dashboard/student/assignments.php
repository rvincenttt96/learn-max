<?php
$title = 'Assignments';
$current_page = 'assignments.php';
require_once 'layout.php';
require_once '../../includes/db.php';
require_once '../../includes/assignments.php';
require_role('student');

$student_id = (int)$_SESSION['user_id'];
$list_error = '';
$groups = [];
try {
    assignments_ensure_schema($pdo);
    $rows = assignments_student_list($pdo, $student_id);
    foreach ($rows as $row) {
        $key = (string)($row['class_title'] ?? 'کلاس');
        $groups[$key][] = assignments_public_student($row);
    }
} catch (Exception $e) {
    error_log('student assignments.php: ' . $e->getMessage());
    $list_error = 'الان لیست تکلیف در دسترس نیست. یک بار دیگر صفحه را تازه کنید.';
}
?>
<link href="../../assets/css/quiz-builder.css?v=qb7" rel="stylesheet">
<link href="../../assets/css/assignments.css?v=asg1" rel="stylesheet">
<div class="qb-page asg-page">
    <div class="qb-head">
        <div>
            <h1><span>Assignments</span></h1>
            <p>تکلیفی که معلم برای جلسه کلاس گذاشته اینجا می‌آید. فایل را آپلود کنید؛ می‌توانید همان فایل را بعداً جایگزین کنید. وضعیت تیک حضورغیاب در Homework Status جداست.</p>
        </div>
    </div>

    <?php if ($list_error): ?>
        <div class="qb-empty">
            <div class="qb-empty-icon">⏳</div>
            <h5>لیست تکلیف لود نشد</h5>
            <p><?= assignments_h($list_error) ?></p>
        </div>
    <?php elseif (!$groups): ?>
        <div class="qb-empty">
            <div class="qb-empty-icon">📎</div>
            <h5>هنوز تکلیفی گذاشته نشده</h5>
            <p>وقتی معلم برای جلسه‌ای Assignment تعریف کند، اینجا دیده می‌شود.</p>
        </div>
    <?php else: ?>
        <?php foreach ($groups as $class_title => $items): ?>
            <section class="qb-exams">
                <h2><?= assignments_h($class_title) ?></h2>
                <div class="qb-cards">
                    <?php foreach ($items as $item):
                        $pill = $item['status_key'] === 'submitted' ? 'ready' : ($item['status_key'] === 'late' ? 'draft' : ($item['status_key'] === 'overdue' ? 'failed' : 'pending'));
                    ?>
                        <a class="qb-card" href="assignment.php?id=<?= (int)$item['id'] ?>">
                            <div>
                                <h2><?= assignments_h($item['title']) ?></h2>
                                <div class="qb-card-meta">
                                    <span class="qb-pill qb-pill-<?= assignments_h($pill) ?>"><?= assignments_h($item['status_label']) ?></span>
                                    <span>S.<?= (int)$item['session_no'] ?></span>
                                    <?php if ($item['due_label'] !== ''): ?>
                                        <span>مهلت <?= assignments_h($item['due_label']) ?></span>
                                    <?php endif; ?>
                                    <?php if ($item['has_teacher_file']): ?>
                                        <span>پیوست معلم</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <span class="qb-card-go"><?= assignments_h($item['cta']) ?> ←</span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
<?php require_once 'layout-end.php'; ?>
