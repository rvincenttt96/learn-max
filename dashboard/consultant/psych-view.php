<?php
$title = 'Psych Tests';
$current_page = 'psych-view.php';
require_once 'layout.php';
require_once '../../includes/db.php';
require_once '../../includes/psych-tests.php';
require_role('consultant');
psych_tests_ensure_schema($pdo);

$id = (int)($_GET['id'] ?? 0);
$st = $pdo->prepare("
    SELECT id, username, full_name, avatar
    FROM users
    WHERE id = ? AND role = 'student' AND IFNULL(is_deleted, 0) = 0
    LIMIT 1
");
$st->execute([$id]);
$student = $st->fetch(PDO::FETCH_ASSOC);
if (!$student) {
    echo '<link href="../../assets/css/psych-tests.css?v=pt1" rel="stylesheet">';
    echo '<div class="pt-empty"><h5>دانش‌آموز پیدا نشد</h5><p><a class="pt-btn pt-btn-ghost" href="psych-tests.php">بازگشت</a></p></div>';
    require_once 'layout-end.php';
    return;
}
$rows = psych_tests_for_student($pdo, (int)$student['id']);
?>
<link href="../../assets/css/psych-tests.css?v=pt1" rel="stylesheet">
<div class="pt-page">
    <div class="si-hero d-flex flex-wrap justify-content-between gap-3 align-items-center">
        <div>
            <div class="text-white-50 small mb-1">Psych Tests</div>
            <h4 class="mb-1"><?= psych_tests_h($student['full_name']) ?></h4>
            <div>کد: <strong class="ltr-num"><?= psych_tests_h($student['username']) ?></strong></div>
            <div class="pt-lock mt-2">فقط نقش Consultant این نمره‌ها را می‌بیند</div>
        </div>
        <a class="btn btn-outline-light" href="psych-tests.php">بازگشت به لیست</a>
    </div>

    <?= psych_tests_render_result($rows['neo60'] ?? null, true, 'neo60') ?>
    <?= psych_tests_render_result($rows['bigfive'] ?? null, true, 'bigfive') ?>
</div>
<?php require_once 'layout-end.php'; ?>
