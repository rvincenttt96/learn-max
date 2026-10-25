<?php
require_once '../../includes/auth.php';
require_once '../../includes/db.php';
require_once '../../includes/terms.php';
require_once '../../includes/term-plan.php';
require_role('teacher');

$teacher_id = (int)($_SESSION['user_id'] ?? 0);
$class_id = (int)($_GET['class_id'] ?? 0);
$class = $class_id ? term_plan_find_class($pdo, $class_id, $teacher_id) : null;
if (!$class) {
    header('Location: term-plan.php?error=' . urlencode('کلاس مورد نظر یافت نشد.'));
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
     data-role="teacher"
     data-class-id="<?= (int)$class['id'] ?>"
     data-ajax="ajax-term-plan.php"
     data-methods="<?= term_plan_h(json_encode(term_plan_methods(), JSON_UNESCAPED_UNICODE)) ?>">

    <div class="tp-head">
        <div class="tp-crumb">
            <a class="tp-btn tp-btn-ghost" href="term-plan.php">بازگشت</a>
            <div>
                <h1><span>Term</span> Plan</h1>
                <p><?= term_plan_h($class['title']) ?> · جلسه <?= (int)$progress['elapsed'] ?> از <?= (int)$progress['total'] ?></p>
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

            <?php $sup_remark = trim((string)$session['supervisor_remark']); ?>
            <div class="tp-sup-box<?= $sup_remark !== '' ? ' has-remark' : '' ?>">
                <label>Supervisor Remark</label>
                <?php if ($sup_remark !== ''): ?>
                    <div class="tp-sup-text"><?= nl2br(term_plan_h($sup_remark), false) ?></div>
                <?php else: ?>
                    <p class="tp-sup-empty">سوپروایزر هنوز ریمارکی برای این جلسه ننوشته.</p>
                <?php endif; ?>
            </div>

            <div class="tp-tasks">
                <?php if (!$session['tasks']): ?>
                    <p class="tp-no-tasks tp-sup-empty">سوپروایزر تسکی تعیین نکرده. از Add Task استفاده کنید.</p>
                <?php else: ?>
                    <?php foreach ($session['tasks'] as $task): ?>
                        <?= term_plan_render_task_row($task, 'teacher') ?>
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
