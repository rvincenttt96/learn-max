<?php
$title = 'کلاس و شهریه';
$current_page = 'index.php';
require_once 'layout.php';

$classes = german_classes_for($pdo, 'german_student', (int)$_SESSION['user_id']);
?>

<div class="g-header">
    <div class="g-header-copy">
        <h1><span>Deutsch</span> کلاس و شهریه</h1>
        <p>کلاس آلمانی، تعداد جلسات، و وضعیت پرداخت شهریه.</p>
    </div>
</div>

<?php if (!$classes): ?>
    <div class="g-empty">
        <h3>کلاسی برای شما ثبت نشده</h3>
        <p>وقتی در یک کلاس آلمانی ثبت شوید، شهریه و رسیدها اینجا می‌آید.</p>
    </div>
<?php else: ?>
<div class="g-cards">
<?php foreach ($classes as $class):
    $kind = $class['kind'];
    $day = german_day_badge($class['schedule_type'] ?? '');
    $session_count = max(1, (int)$class['session_count']);
    $me = $class['me'] ?? null;
?>
    <article class="eas-card g-card g-readonly-card">
        <div class="card-header">
            <div class="class-info">
                <h2><?= german_h($class['title']) ?></h2>
                <div class="teacher">👨‍🏫 <?= german_h($class['teacher_name'] ?: 'بدون مدرس') ?></div>
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
        <?php if ($me): ?>
        <div class="g-pay-row">
            <div class="g-pay-head">
                <strong>شهریه این کلاس</strong>
                <span class="g-status g-status-<?= german_h($me['status_key']) ?>"><?= german_h($me['status_label']) ?></span>
            </div>
            <div class="g-pay-meta">
                <span><?= $session_count ?> جلسه × <?= german_money($class['fee_per_session']) ?></span>
                <span>کل <?= german_money($me['fee_total']) ?></span>
                <span>پرداخت <?= german_money($me['paid']) ?></span>
                <span>مانده <?= german_money($me['debt']) ?></span>
            </div>
            <?php if (!empty($me['payments'])): ?>
            <ul class="g-receipts">
                <?php foreach ($me['payments'] as $pay): ?>
                <li>
                    <span><?= german_money($pay['amount']) ?> تومان</span>
                    <span class="g-muted"><?= german_h($pay['created_at']) ?></span>
                    <?php if (!empty($pay['receipt_path'])): ?>
                        <a href="receipt.php?id=<?= (int)$pay['id'] ?>" target="_blank" rel="noopener">رسید</a>
                    <?php endif; ?>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
        </div>
        <?php endif; ?>
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
    </article>
<?php endforeach; ?>
</div>
<?php endif; ?>

<?php require_once 'layout-end.php'; ?>
