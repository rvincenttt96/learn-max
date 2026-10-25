<?php
$title = 'Term Plan';
$current_page = 'term-plan.php';
require_once 'layout.php';
require_once '../../includes/db.php';
require_once '../../includes/terms.php';
require_once '../../includes/term-plan.php';

$teacher_id = (int)$_SESSION['user_id'];
term_plan_ensure_schema($pdo);
$classes = term_plan_teacher_classes($pdo, $teacher_id);
?>
<link href="../../assets/css/term-plan.css?v=tp2" rel="stylesheet">
<div class="tp-page">
    <div class="tp-head">
        <div>
            <h1><span>Term</span> Plan</h1>
            <p>جلسات ترم هر کلاس و درصد سپری‌شده، بر اساس جلسه‌هایی که برگزار شده</p>
        </div>
    </div>

    <?php if (!$classes): ?>
        <div class="tp-empty">
            <div style="font-size:2.4rem">🗓️</div>
            <h5>کلاسی برای این ترم نیست</h5>
            <p class="mb-0">وقتی کلاسی به شما وصل باشد، کارت‌های ترم اینجا می‌آید.</p>
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
            </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
<?php require_once 'layout-end.php'; ?>
