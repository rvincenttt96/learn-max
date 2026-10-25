<?php
require_once '../../includes/auth.php';
require_once '../../includes/db.php';
require_once '../../includes/terms.php';
require_once '../../includes/term-plan.php';
require_role('supervisor');

$class_id = (int)($_GET['class_id'] ?? 0);
$class = $class_id ? term_plan_find_class($pdo, $class_id) : null;
if (!$class) {
    header('Location: term-plan.php?error=' . urlencode('کلاس یافت نشد.'));
    exit;
}

$title = 'Term Plan';
$current_page = 'term-plan-details.php';
require_once 'layout.php';

list($progress, $sessions) = term_plan_sessions_payload($pdo, $class);
$badge = term_plan_day_badge($class);
$time = term_plan_display_time($class);
?>
<link href="../../assets/css/term-plan.css?v=tp2" rel="stylesheet">
<div class="tp-page"
     data-tp-root
     data-role="supervisor"
     data-class-id="<?= (int)$class['id'] ?>"
     data-ajax="ajax-term-plan.php"
     data-methods="<?= term_plan_h(json_encode(term_plan_methods(), JSON_UNESCAPED_UNICODE)) ?>">

    <div class="tp-head">
        <div class="tp-crumb">
            <a class="tp-btn tp-btn-ghost" href="term-plan.php">بازگشت</a>
            <div>
                <h1><span>Term</span> Plan</h1>
                <p><?= term_plan_h($class['title']) ?> · <?= term_plan_h($class['teacher_name'] ?? '') ?></p>
            </div>
        </div>
        <div class="tp-meta">
            <div class="tp-badges">
                <?= term_plan_type_badge_html($class) ?>
                <span class="day-badge <?= $badge[0] ?>"><?= term_plan_h($badge[1]) ?></span>
                <span class="time-badge"><?= term_plan_h($time) ?></span>
            </div>
        </div>
    </div>

    <div class="tp-sessions">
        <?php foreach ($sessions as $session):
            $n = (int)$session['session_no'];
            $elapsed_cls = !empty($session['elapsed']) ? ' is-elapsed' : '';
        ?>
        <article class="tp-session<?= $elapsed_cls ?>" data-session="<?= $n ?>">
            <div class="tp-session-top">
                <h3 class="tp-session-no">Session <em><?= sprintf('%02d', $n) ?></em></h3>
                <?php if ($session['date_label'] !== ''): ?>
                    <div class="tp-session-date"><?= term_plan_h($session['date_label']) ?></div>
                <?php endif; ?>
            </div>

            <div class="tp-sup-box<?= trim((string)$session['supervisor_remark']) !== '' ? ' has-remark' : '' ?>">
                <div class="tp-sup-head">
                    <label>Supervisor Remark</label>
                    <span class="tp-sup-save-status" aria-live="polite"></span>
                </div>
                <textarea class="tp-sup-remark" rows="3" placeholder="ریمارک این جلسه برای معلم"><?= term_plan_h($session['supervisor_remark']) ?></textarea>
                <button type="button" class="tp-btn tp-btn-red tp-sup-save">ذخیره ریمارک</button>
            </div>

            <div class="tp-tasks">
                <?php if (!$session['tasks']): ?>
                    <p class="tp-no-tasks tp-sup-empty">هنوز تسکی برای این جلسه نیست. از اکسل یا Add Task اضافه کنید.</p>
                <?php else: ?>
                    <?php foreach ($session['tasks'] as $task): ?>
                        <?= term_plan_render_task_row($task, 'supervisor') ?>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <div class="tp-add">
                <input class="tp-input tp-add-input" placeholder="تسک جدید برای این جلسه">
                <button type="button" class="tp-btn tp-btn-red tp-add-btn">Add Task</button>
            </div>
        </article>
        <?php endforeach; ?>
    </div>
</div>
<script src="../../assets/js/term-plan.js?v=tp2"></script>
<?php require_once 'layout-end.php'; ?>
