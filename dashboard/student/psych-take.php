<?php
$title = 'Psych Tests';
$current_page = 'psych-take.php';
require_once 'layout.php';
require_once '../../includes/db.php';
require_once '../../includes/psych-tests.php';
require_role('student');

$student_id = (int)$_SESSION['user_id'];
$key = (string)($_GET['key'] ?? '');
$def = null;
$row = null;
$boot = null;
try {
    psych_tests_ensure_schema($pdo);
    $def = psych_tests_def($key);
    $row = psych_tests_get($pdo, $student_id, $key);
    $boot = psych_tests_public_student_boot($def, $row);
} catch (Exception $e) {
    $def = null;
}
$labels = psych_tests_likert_labels();
?>
<link href="../../assets/css/psych-tests.css?v=pt1" rel="stylesheet">
<div class="pt-page">
<?php if (!$def || !is_array($boot)): ?>
    <div class="pt-empty">
        <h5>آزمون پیدا نشد</h5>
        <p>این آزمون برای پنل دانش‌آموز تعریف نشده است.</p>
        <p><a class="pt-btn pt-btn-ghost" href="psych-tests.php">بازگشت</a></p>
    </div>
<?php else: ?>
    <div class="pt-head">
        <div>
            <h1><?= psych_tests_h($def['title']) ?></h1>
            <p>برای هر جمله عددی از ۱ تا ۵ انتخاب کنید. ۱ یعنی <?= psych_tests_h($labels[1]) ?> و ۵ یعنی <?= psych_tests_h($labels[5]) ?>. نمره عوامل بعد از ثبت فقط به مشاور نشان داده می‌شود.</p>
        </div>
        <a class="pt-btn pt-btn-ghost" href="psych-tests.php">همه آزمون‌ها</a>
    </div>

    <div class="pt-progress">
        <div class="pt-progress-label">
            <span>پیشرفت</span>
            <span id="ptCount">0 از <?= (int)$def['item_count'] ?></span>
        </div>
        <div class="pt-progress-track"><div class="pt-progress-bar" id="ptBar"></div></div>
        <div class="pt-lock mt-2">۱ <?= psych_tests_h($labels[1]) ?> · ۲ <?= psych_tests_h($labels[2]) ?> · ۳ <?= psych_tests_h($labels[3]) ?> · ۴ <?= psych_tests_h($labels[4]) ?> · ۵ <?= psych_tests_h($labels[5]) ?></div>
    </div>
    <noscript><div class="pt-alert">برای دادن آزمون باید جاوااسکریپت روشن باشد.</div></noscript>

    <div class="pt-alert" id="ptAlert" hidden></div>

    <div class="pt-ok" id="ptOk" hidden>
        <div style="font-size:2rem">✓</div>
        <h2>آزمون ثبت شد</h2>
        <p>جواب شما ذخیره شد و نتیجه در پنل مشاور نمایش داده می‌شود.</p>
        <p><a class="pt-btn" href="psych-tests.php">بازگشت به آزمون‌ها</a></p>
    </div>

    <form class="pt-form" id="ptForm" data-pt-take data-boot="<?= psych_tests_h(json_encode($boot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>">
        <?php foreach ($def['items'] as $n => $text): ?>
        <div class="pt-q" data-n="<?= (int)$n ?>">
            <p class="pt-stem"><span class="pt-num"><?= (int)$n ?></span><?= psych_tests_h($text) ?></p>
            <div class="pt-likert" role="radiogroup" aria-label="سوال <?= (int)$n ?>">
                <?php foreach ($labels as $v => $lab): ?>
                <label data-v="<?= (int)$v ?>">
                    <input type="radio" name="q<?= (int)$n ?>" value="<?= (int)$v ?>">
                    <span><?= (int)$v ?></span>
                    <small><?= psych_tests_h($lab) ?></small>
                </label>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>
        <div class="pt-actions">
            <button type="button" class="pt-btn pt-btn-ghost" id="ptSave">ذخیره پیش‌نویس</button>
            <button type="submit" class="pt-btn" id="ptSubmit" disabled>ثبت آزمون</button>
        </div>
    </form>
    <script src="../../assets/js/psych-tests.js?v=pt1"></script>
<?php endif; ?>
</div>
<?php require_once 'layout-end.php'; ?>
