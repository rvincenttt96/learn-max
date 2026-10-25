<?php
$title = 'Session plan';
require_once 'layout.php';
require_once '../../includes/db.php';
require_once '../../includes/terms.php';

$teacher_id = $_SESSION['user_id'];

$stmt = $pdo->prepare("
    SELECT c.id, c.title, c.schedule, c.schedule_type
    FROM classes c
    LEFT JOIN terms t ON c.term_id = t.id
    WHERE c.teacher_id = ? 
    AND c.is_deleted = 0 
    AND (" . term_sql_teaching('t') . " OR c.term_id IS NULL)
    AND IFNULL(c.is_archived, 0) = 0
    ORDER BY c.title
");
$stmt->execute([$teacher_id]);
$classes = $stmt->fetchAll();
?>
<link href="../../assets/css/teacher/session-plan.css?v=lg13" rel="stylesheet">
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
                                <h6 class="card-title mb-0"><?= htmlspecialchars($class['title']) ?><?= class_live_dot($class) ?></h6>
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