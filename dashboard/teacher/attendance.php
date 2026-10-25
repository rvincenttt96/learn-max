<?php
$title = 'Roll Call';
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

$odd_classes = [];
$even_classes = [];

foreach ($classes as $class) {
    if ($class['schedule_type'] == 'odd') {
        $odd_classes[] = $class;
    } else {
        $even_classes[] = $class;
    }
}
?>
<link href="../../assets/css/teacher/attendance.css?v=<?= time() ?>" rel="stylesheet">
<div class="card attendance-main-card">
    <div class="card-header">
        <h5 class="mb-0 text-white">Roll Call</h5>
    </div>
    <div class="card-body">
        <h6 class="text-white mb-3">Sun/Tue/Thu</h6>
        <?php if (!empty($odd_classes)): ?>
            <div class="row g-3">
                <?php foreach ($odd_classes as $class): ?>
                <div class="col-12 col-md-6 col-lg-4">
                    <a href="attendance-students.php?class_id=<?= $class['id'] ?>" 
                       class="d-block text-decoration-none">
                        <div class="card h-100 attendance-card text-white">
                            <div class="card-body text-center">
                                <h6 class="card-title mb-0"><?= htmlspecialchars($class['title']) ?><?= class_live_dot($class) ?></h6>
                            </div>
                        </div>
                    </a>
                </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p class="text-muted">No classes Available</p>
        <?php endif; ?>

        <hr>
		<div class="attendance-divider"></div>

        <h6 class="text-white mb-3">Sat/Mon/Wed</h6>
        <?php if (!empty($even_classes)): ?>
            <div class="row g-3">
                <?php foreach ($even_classes as $class): ?>
                <div class="col-12 col-md-6 col-lg-4">
                    <a href="attendance-students.php?class_id=<?= $class['id'] ?>" 
                       class="d-block text-decoration-none">
                        <div class="card h-100 attendance-card text-white">
                            <div class="card-body text-center">
                                <h6 class="card-title mb-0"><?= htmlspecialchars($class['title']) ?><?= class_live_dot($class) ?></h6>
                            </div>
                        </div>
                    </a>
                </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p class="text-muted">No classes Available</p>
        <?php endif; ?>
    </div>
</div>

<?php require_once 'layout-end.php'; ?>