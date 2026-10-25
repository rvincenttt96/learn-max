<?php
$title = 'کلاس‌های آلمانی';
$current_page = 'index.php';
require_once 'layout.php';

$classes = german_classes_for($pdo, 'german_teacher', (int)$_SESSION['user_id']);
?>

<div class="g-header">
    <div class="g-header-copy">
        <h1><span>EAS</span> کلاس‌های من</h1>
        <p>کلاس‌های آلمانی که به شما به‌عنوان استاد وصل شده‌اند.</p>
    </div>
</div>

<?php if (!$classes): ?>
    <div class="g-empty">
        <h3>کلاسی برای شما ثبت نشده</h3>
        <p>وقتی سوپروایزر آلمانی شما را به کلاسی وصل کند، اینجا دیده می‌شود.</p>
    </div>
<?php else: ?>
<div class="g-cards">
<?php foreach ($classes as $class):
    $kind = $class['kind'];
    $day = german_day_badge($class['schedule_type'] ?? '');
    $session_count = max(1, (int)$class['session_count']);
?>
    <article class="eas-card g-card g-readonly-card">
        <div class="card-header">
            <div class="class-info">
                <h2><?= german_h($class['title']) ?></h2>
            </div>
            <div class="meta-info">
                <div class="badges-wrapper">
                    <span class="type-badge <?= german_kind_badge($kind) ?>"><?= german_h(german_kind_label($kind)) ?></span>
                    <span class="type-badge type-level"><?= german_h($class['level']) ?></span>
                    <?php if ($day[1] !== ''): ?>
                        <span class="day-badge <?= $day[0] ?>"><?= german_h($day[1]) ?></span>
                    <?php endif; ?>
                </div>
                <?php if (trim((string)$class['schedule']) !== ''): ?>
                    <span class="time-badge"><?= german_h($class['schedule']) ?></span>
                <?php endif; ?>
            </div>
        </div>
        <div class="sessions-container">
            <div class="section-title">📅 جلسات (<?= $session_count ?>)</div>
            <div class="sessions-grid">
                <?php for ($i = 1; $i <= $session_count; $i++):
                    $status = (int)($class['session_status'][(string)$i] ?? $class['session_status'][$i] ?? 0);
                    $status_class = $status === 1 ? 'status-tick' : ($status === 2 ? 'status-cross' : '');
                    $content = $status === 1 ? '✔' : ($status === 2 ? '✖' : (string)$i);
                    $saved_date = $class['session_dates'][(string)$i] ?? $class['session_dates'][$i] ?? '';
                ?>
                <div class="session-item">
                    <div class="session-box <?= $status_class ?>"><?= $content ?></div>
                    <div class="date-input"><?= german_h($saved_date) ?></div>
                </div>
                <?php endfor; ?>
            </div>
        </div>
        <div>
            <div class="section-title">👥 زبان‌آموزان</div>
            <div class="student-badges" style="display:flex;flex-wrap:wrap;gap:8px;">
                <?php if (!$class['students']): ?>
                    <span class="student-badge g-muted">بدون زبان‌آموز</span>
                <?php else: ?>
                    <?php foreach ($class['students'] as $st): ?>
                        <span class="student-badge"><?= german_h($st['full_name']) ?> <span class="student-file"><?= german_h($st['username']) ?></span></span>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </article>
<?php endforeach; ?>
</div>
<?php endif; ?>

<?php require_once 'layout-end.php'; ?>
