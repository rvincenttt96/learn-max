<?php
$title = 'session-plan';
require_once 'layout.php'; 
require_once '../../includes/db.php';
require_once '../../includes/terms.php'; 
require_role('supervisor');

$stmt = $pdo->prepare("
    SELECT
        c.id,
        c.title,
        c.schedule,
        c.schedule_type,
        u.full_name as teacher_name,
        COALESCE(sp_count.total_plans, 0) as total_session_plans
    FROM classes c
    JOIN users u ON c.teacher_id = u.id
    LEFT JOIN terms t ON c.term_id = t.id
    LEFT JOIN (
        SELECT class_id, COUNT(*) as total_plans
        FROM session_plans
        GROUP BY class_id
    ) sp_count ON c.id = sp_count.class_id
    WHERE c.is_deleted = 0
    AND (" . term_sql_teaching('t') . " OR c.term_id IS NULL)
    AND IFNULL(c.is_archived, 0) = 0
    ORDER BY c.title
");
$stmt->execute();
$classes = $stmt->fetchAll();
?>
<link href="../../assets/css/supervisor/supervisor-session-plan.css?v=<?= time() ?>" rel="stylesheet">
<div class="card session-main-card">
    <div class="card-header text-white">
        <h5 class="mb-0">Session Plan</h5>
    </div>
    <div class="card-body">
        <?php if (empty($classes)): ?>
            <div class="alert alert-info">No Classes Available</div>
        <?php else: ?>
            <div class="row g-3">
                <?php foreach ($classes as $class): ?>
                <div class="col-12 col-md-6 col-lg-4">
                    <a href="session-plan-details.php?class_id=<?= $class['id'] ?>" 
                       class="d-block text-decoration-none">
                        <div class="card h-100 session-class-card text-white">
                            <div class="card-body text-center">
                                <h6 class="card-title mb-2"><?= htmlspecialchars($class['title']) ?><?= class_live_dot($class) ?></h6>
                                <p class="mb-1"><small>Teacher: <?= htmlspecialchars($class['teacher_name']) ?></small></p>
                                <p class="mb-0">
                                    <small>
                                        Session Plans: <strong><?= (int)$class['total_session_plans'] ?></strong>
                                    </small>
                                </p>
                            </div>
                        </div>
                    </a>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once 'layout-end.php'; ?>