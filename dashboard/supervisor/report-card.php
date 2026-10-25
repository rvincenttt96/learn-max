<?php
// error_reporting(E_ALL);
// ini_set('display_errors', 1);
$title = 'کارنامه';
require_once 'layout.php';
require_once '../../includes/db.php';
require_once '../../includes/terms.php';
require_once '../../includes/jdate.php';
require_role('supervisor');

$tab = $_GET['tab'] ?? 'odd';
$tab = in_array($tab, ['odd', 'even']) ? $tab : 'odd';

$schedule_condition = $tab === 'odd' ? "c.schedule_type = 'odd'" : "c.schedule_type = 'even'";

$stmt = $pdo->prepare("
    SELECT c.id, c.title, c.schedule, c.schedule_type, u.full_name as teacher_name, t.title as term_title
    FROM classes c
    JOIN users u ON c.teacher_id = u.id
    LEFT JOIN terms t ON c.term_id = t.id
    WHERE (" . term_sql_teaching('t') . " OR c.term_id IS NULL)
    AND IFNULL(c.is_archived, 0) = 0
    AND c.is_deleted = 0
    AND ($schedule_condition)
    ORDER BY c.title
");
$stmt->execute(); 
$classes = $stmt->fetchAll();
?>

<link href="../../assets/css/supervisor/supervisor-report-card.css?v=<?= time() ?>" rel="stylesheet">


<ul class="nav nav-tabs mb-4">
    <li class="nav-item" >
        <a class="nav-link text-white <?= $tab == 'odd' ? 'active' : '' ?>" href="?tab=odd">Sun/Tue/Thu</a>
    </li>
    <li class="nav-item">
        <a class="nav-link text-white <?= $tab == 'even' ? 'active' : '' ?>" href="?tab=even">Sat/Mon/Wed</a>
    </li>
</ul>



<?php if (empty($classes)): ?>
    <div class="card">
	  <div class="card-header">
    Featured
 	 </div>
        <div class="card-body text-center py-5">
            <div style="font-size: 3rem; margin-bottom: 20px;">📊</div>
            <h4>I see no classes...</h4>
        </div>
    </div>
<?php else: ?>
    <div class="row">
        <?php foreach ($classes as $class): ?>
        <div class="col-12 col-md-6 col-lg-4 mb-4">
            <div class="report-card-class">
                <div class="class-header">
                    <h5 class="mb-1"><?= htmlspecialchars($class['title']) ?><?= class_live_dot($class) ?></h5>
                    <small class="text-muted">Instructor: <?= htmlspecialchars($class['teacher_name']) ?></small>
                </div>
                <div class="class-body">
                    <a href="view-class-report.php?class_id=<?= $class['id'] ?>" class="btn btn-primary w-100">
                        Check the Report card 
                    </a>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php require_once 'layout-end.php'; ?>