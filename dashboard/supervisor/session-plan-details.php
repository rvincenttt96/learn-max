<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
$title = 'جزئیات برنامه جلسه';
require_once 'layout.php';
require_once '../../includes/db.php';
require_once '../../includes/terms.php';
require_once '../../includes/jdate.php'; 
require_role('supervisor'); 

$class_id = $_GET['class_id'] ?? null;

if (!$class_id) {
    header("Location: session-plan.php?error=" . urlencode('کلاس مورد نظر مشخص نشده است.'));
    exit;
}

$stmt = $pdo->prepare("
    SELECT c.id, c.title, u.full_name as teacher_name
    FROM classes c
    JOIN users u ON c.teacher_id = u.id
    JOIN terms t ON c.term_id = t.id
    WHERE c.id = ? AND " . term_sql_teaching('t') . "
");
$stmt->execute([$class_id]);
$class_info = $stmt->fetch();

if (!$class_info) {
    header("Location: session-plan.php?error=" . urlencode('کلاس یافت نشد یا دسترسی غیرمجاز است.'));
    exit;
}

$stmt = $pdo->prepare("
    SELECT 
        sp.id, 
        sp.session_date, 
        sp.content, 
        u.full_name AS teacher_name
    FROM session_plans sp
    LEFT JOIN users u ON sp.teacher_id = u.id
    WHERE sp.class_id = ?
    ORDER BY sp.session_date DESC, sp.created_at DESC
");
$stmt->execute([$class_id]);
$plans = $stmt->fetchAll();
?>

<link href="../../assets/css/supervisor/supervisor-session-plan.css?v=<?= time() ?>" rel="stylesheet">

<div class="card session-details-card">
    <div class="card-header text-white d-flex justify-content-between align-items-center">
        <h5>📋 Session Plan: <?= htmlspecialchars($class_info['title']) ?></h5>
        <a href="session-plan.php" class="btn btn-sm btn-light">Back to List</a>
    </div>
    <div class="card-body">
        <?php if (empty($plans)): ?>
            <div class="alert alert-info text-center py-4">
                <i class="fas fa-file-alt fa-2x mb-2"></i><br>
                هنوز برنامه‌ای برای این کلاس ثبت نشده است.
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-bordered session-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>تاریخ</th>
                            <th>برنامه</th>
                            <th>معلم</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($plans as $index => $plan): ?>
                        <tr id="plan-<?= $plan['id'] ?>">
                            <td><?= $index + 1 ?></td>
                            <td><?= htmlspecialchars(jdate_format($plan['session_date'], 'Y/m/d'), ENT_QUOTES, 'UTF-8') ?></td>
                            <td style="white-space: pre-line;"><?= htmlspecialchars($plan['content'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($plan['teacher_name'] ?? '—', ENT_QUOTES, 'UTF-8') ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once 'layout-end.php'; ?>