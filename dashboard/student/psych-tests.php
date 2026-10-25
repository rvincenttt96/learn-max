<?php
$title = 'Psych Tests';
$current_page = 'psych-tests.php';
require_once 'layout.php';
require_once '../../includes/db.php';
require_once '../../includes/psych-tests.php';
require_role('student');

$student_id = (int)$_SESSION['user_id'];
$list_error = '';
$cards = [];
try {
    psych_tests_ensure_schema($pdo);
    $cards = psych_tests_student_cards($pdo, $student_id);
} catch (Exception $e) {
    error_log('student psych-tests.php: ' . $e->getMessage());
    $list_error = 'الان لیست آزمون در دسترس نیست. یک بار دیگر صفحه را تازه کنید.';
}
?>
<link href="../../assets/css/psych-tests.css?v=pt1" rel="stylesheet">
<div class="pt-page">
    <div class="pt-head">
        <div>
            <h1>آزمون روانشناسی</h1>
            <p>دو آزمون شخصیت برای دانش‌آموزان آموزشگاه. نتیجه برای مشاور ثبت می‌شود و در پنل شما نمره عوامل دیده نمی‌شود.</p>
        </div>
    </div>

    <?php if ($list_error): ?>
        <div class="pt-empty">
            <h5>لیست آزمون لود نشد</h5>
            <p><?= psych_tests_h($list_error) ?></p>
        </div>
    <?php else: ?>
        <div class="pt-cards">
            <?php foreach ($cards as $card):
                $pill = $card['status'] === 'submitted' ? 'done' : ($card['status'] === 'draft' ? 'draft' : '');
                $cta = 'شروع آزمون';
                if ($card['status'] === 'draft') {
                    $cta = 'ادامه آزمون';
                } elseif ($card['status'] === 'submitted') {
                    $cta = 'تکرار آزمون';
                }
            ?>
            <a class="pt-card" href="psych-take.php?key=<?= psych_tests_h($card['key']) ?>">
                <h2><?= psych_tests_h($card['title']) ?></h2>
                <p><?= psych_tests_h($card['subtitle']) ?></p>
                <div class="pt-meta">
                    <span class="pt-pill <?= $pill ?>"><?= psych_tests_h(psych_tests_status_fa($card['status'], $card['has_scores'])) ?></span>
                    <span class="pt-pill"><?= (int)$card['answered'] ?> / <?= (int)$card['item_count'] ?></span>
                    <span class="pt-pill"><?= psych_tests_h($cta) ?></span>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
<?php require_once 'layout-end.php'; ?>
