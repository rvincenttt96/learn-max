<?php
$title = 'Term Plan';
$current_page = 'term-plan.php';
require_once 'layout.php';
require_once '../../includes/db.php';
require_once '../../includes/terms.php';
require_once '../../includes/term-plan.php';

term_plan_ensure_schema($pdo);
$classes = term_plan_supervisor_classes($pdo);
$ok = trim((string)($_GET['success'] ?? ''));
$err = trim((string)($_GET['error'] ?? ''));
$tracks = function_exists('term_plan_tracks_status') ? term_plan_tracks_status($pdo) : null;
?>
<link href="../../assets/css/term-plan.css?v=tp3" rel="stylesheet">
<div class="tp-page">
    <div class="tp-head">
        <div>
            <h1><span>Term</span> Plan</h1>
            <p>با اکسل تسک جلسات را وارد کنید، بعد روی کارت کلاس ادیت و ریمارک بنویسید</p>
        </div>
        <div class="tp-head-actions">
            <a class="tp-btn tp-btn-ghost" href="term-plan-template.php">نمونه اکسل</a>
            <a class="tp-btn tp-btn-green" href="term-plan-export.php">خروجی واردشده‌ها</a>
            <form id="tpImportForm" action="term-plan-import.php" method="post" enctype="multipart/form-data" style="margin:0">
                <input type="file" name="excel_file" id="tpExcel" class="tp-file" accept=".xlsx">
                <button type="button" class="tp-btn tp-btn-red" onclick="document.getElementById('tpExcel').click()">ورود اکسل</button>
            </form>
        </div>
    </div>

    <?php if ($tracks && (int)$tracks['level_count'] > 0): ?>
        <div class="tp-alert tp-alert-ok tp-track-note">
            <?= (int)$tracks['level_count'] ?> لول جدید در حسابداری ثبت شد:
            WT5 تا WT16 (شهریه همان T)، و Connectivity با نام‌های CB / CE / CPI / CI / CUI / CADV
            (شهریه همان B تا ADV). ترم‌پلن فایل‌ها فقط برای همین لول‌های جدید است؛ کلاس‌های T / B / E / PI / I / UI / ADV فعلی این پلن را نمی‌گیرند. با ساخت کلاس WT یا CB و بقیه، تسک جلسات همان سری جدید می‌آید.
            <?php if (!empty($tracks['missing_fee'])): ?>
                <br>شهریه این لول‌ها پیدا نشد و ساخته نشد: <?= term_plan_h($tracks['missing_fee']) ?>
            <?php endif; ?>
            <?php if (!empty($tracks['levels_error'])): ?>
                <br>حسابداری: <?= term_plan_h($tracks['levels_error']) ?>
            <?php endif; ?>
        </div>
    <?php endif; ?>
    <?php if ($ok !== ''): ?>
        <div class="tp-alert tp-alert-ok"><?= term_plan_h($ok) ?></div>
    <?php endif; ?>
    <?php if ($err !== ''): ?>
        <div class="tp-alert tp-alert-err"><?= term_plan_h($err) ?></div>
    <?php endif; ?>

    <?php if (!$classes): ?>
        <div class="tp-empty">
            <div style="font-size:2.4rem">🗓️</div>
            <h5>کلاسی برای این ترم نیست</h5>
            <p class="mb-0">بعد از کلاس‌بندی ترم، کارت‌ها اینجا می‌آید.</p>
        </div>
    <?php else: ?>
        <div class="tp-cards">
            <?php foreach ($classes as $class):
                $progress = term_plan_progress($class);
                $badge = term_plan_day_badge($class);
                $time = term_plan_display_time($class);
            ?>
            <a class="tp-card" href="term-plan-details.php?class_id=<?= (int)$class['id'] ?>">
                <div class="tp-card-header">
                    <div>
                        <h2><?= term_plan_h($class['title']) ?><?= class_live_dot($class) ?></h2>
                        <div class="tp-teacher">👨‍🏫 <?= term_plan_h($class['teacher_name'] ?? '') ?></div>
                    </div>
                    <div class="tp-meta">
                        <div class="tp-badges">
                            <?= term_plan_type_badge_html($class) ?>
                            <span class="day-badge <?= $badge[0] ?>"><?= term_plan_h($badge[1]) ?></span>
                        </div>
                        <span class="time-badge"><?= term_plan_h($time) ?></span>
                    </div>
                </div>
                <div class="tp-progress">
                    <?= term_plan_progress_ring_html($progress) ?>
                </div>
                <div class="tp-stat"><?= (int)$class['task_count'] ?> تسک واردشده</div>
            </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
<script>
document.getElementById('tpExcel') && document.getElementById('tpExcel').addEventListener('change', function () {
    if (this.files && this.files.length) {
        document.getElementById('tpImportForm').submit();
    }
});
</script>
<?php require_once 'layout-end.php'; ?>
