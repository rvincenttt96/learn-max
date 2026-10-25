<?php
$title = 'Observation Forms';
require_once 'layout.php';
require_once '../../includes/db.php';
require_once '../../includes/terms.php';
require_role('supervisor');

$stmt = $pdo->prepare("
    SELECT c.id, c.title, c.schedule, c.schedule_type, u.full_name as teacher_name
    FROM classes c
    JOIN users u ON c.teacher_id = u.id
    JOIN terms t ON c.term_id = t.id
    WHERE c.is_deleted = 0 AND " . term_sql_teaching('t') . "
    AND IFNULL(c.is_archived, 0) = 0
    ORDER BY c.title
");
$stmt->execute();
$classes = $stmt->fetchAll();
?>

<link href="../../assets/css/supervisor/observation-forms.css?v=<?= time() ?>" rel="stylesheet">
<?php if (!empty($_GET['success'])): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <?= htmlspecialchars($_GET['success']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if (!empty($_GET['error'])): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <?= htmlspecialchars($_GET['error']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>
<div class="dashboard-header">
    <h1 class="mb-0">Observation Forms</h1>
</div>

<div class="card">
    <div class="card-header bg-primary text-white">
        <h5 class="mb-0">Select Class for Observation</h5>
    </div>
    <div class="card-body">
        <?php if (empty($classes)): ?>
            <div class="alert alert-info text-center py-4">
                <i class="fas fa-search fa-2x mb-2"></i><br>
                No active classes available for observation.
            </div>
        <?php else: ?>
            <div class="row g-3">
                <?php foreach ($classes as $class): ?>
                <div class="col-12 col-md-6 col-lg-4">
                    <a href="observation-form-create.php?class_id=<?= $class['id'] ?>" 
                       class="d-block text-decoration-none">
                        <div class="card h-100 observation-class-card">
                            <div class="card-body text-center">
                                <h6 class="card-title mb-2"><?= htmlspecialchars($class['title']) ?><?= class_live_dot($class) ?></h6>
                                <small class="text-muted">Teacher: <?= htmlspecialchars($class['teacher_name']) ?></small>
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