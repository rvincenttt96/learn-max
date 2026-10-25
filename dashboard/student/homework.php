<?php
$title = 'وضعیت تکالیف';
require_once 'layout.php';
require_once '../../includes/db.php';
require_once '../../includes/terms.php';
require_once '../../includes/jdate.php';
require_role('student');

$user_id = $_SESSION['user_id'];

$stmt = $pdo->prepare("
    SELECT c.id, c.title, c.schedule, c.schedule_type
    FROM classes c
    JOIN class_students cs ON c.id = cs.class_id
    JOIN terms t ON c.term_id = t.id
    WHERE cs.user_id = ? AND c.is_deleted = 0 AND IFNULL(c.is_archived, 0) = 0 AND " . term_sql_teaching('t') . "
    ORDER BY c.title
");
$stmt->execute([$user_id]);
$classes = $stmt->fetchAll();

$class_sessions = [];
foreach ($classes as $class) {
    $stmt = $pdo->prepare("
        SELECT DISTINCT class_date 
        FROM attendance 
        WHERE class_date != '0000-00-00' 
        AND class_date IS NOT NULL
        AND class_date >= '1900-01-01'
        AND class_id = ?
        ORDER BY class_date ASC
        LIMIT 14
    ");
    $stmt->execute([$class['id']]);
    $sessions = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    sort($sessions);
    while (count($sessions) < 14) {
        $sessions[] = null;
    }
    $class_sessions[$class['id']] = $sessions;
}
?>

<link href="../../assets/css/student/student-homework.css?v=<?= time() ?>" rel="stylesheet">


<?php if (empty($classes)): ?>
    <div class="card">
        <div class="card-body text-center py-5">
            <div style="font-size: 3rem; margin-bottom: 20px;">📝</div>
            <h4>کلاسی برای شما ثبت نشده است</h4>
        </div>
    </div>
<?php else: ?>
    <?php foreach ($classes as $class): ?>
    <div class="card mb-4">
        <div class="card-header text-white">
            <h5 class="mb-0"><?= htmlspecialchars($class['title']) ?><?= class_live_dot($class) ?></h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered">
                    <thead class="table-light">
                        <tr>
                            <?php for ($i = 1; $i <= 14; $i++): ?>
                                <th>تکلیف <?= $i ?></th>
                            <?php endfor; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
						<?php for ($i = 0; $i < 14; $i++): ?>
							<?php 
							$date = $class_sessions[$class['id']][$i];
							if ($date): 
								$stmt = $pdo->prepare("
									SELECT status FROM homework_status 
									WHERE class_id = ? AND user_id = ? AND homework_date = ?
								");
								$stmt->execute([$class['id'], $user_id, $date]);
								$status = $stmt->fetchColumn();
								
								$status_class = '';
								$status_text = '';
								switch($status) {
									case 'complete':
										$status_class = 'bg-success';
										$status_text = 'انجام شده';
										break;
									case 'incomplete':
										$status_class = 'bg-warning';
										$status_text = 'ناتمام';
										break;
									case 'not_submitted':
										$status_class = 'bg-danger';
										$status_text = 'ارسال نشده';
										break;
									default:
										$status_class = 'bg-secondary';
										$status_text = 'بدون تکلیف';
								}
							?>
							<td class="text-center <?= $status_class ?> text-white">
								<div><?= jdate('m/d', strtotime($date)) ?></div>
								<small><?= $status_text ?></small>
							</td>
							<?php else: ?>
							<td class="text-center text-muted">-</td>
							<?php endif; ?>
						<?php endfor; ?>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
<?php endif; ?>

<?php require_once 'layout-end.php'; ?>