<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
$title = 'تکالیف';
require_once 'layout.php';
require_once '../../includes/db.php';
require_once '../../includes/terms.php';
require_once '../../includes/jdate.php';
require_role('supervisor');

$tab = $_GET['tab'] ?? 'today';
$tab = in_array($tab, ['today', 'odd', 'even']) ? $tab : 'today';

$today_weekday = jdate('w');
$is_today_odd = in_array($today_weekday, [1, 3, 5]);

if ($tab === 'today') {
    if ($today_weekday == 6) {
        $schedule_condition = '1=0';
    } else {
        $schedule_condition = $is_today_odd ? "c.schedule_type = 'odd'" : "c.schedule_type = 'even'";
    }
} elseif ($tab === 'odd') {
    $schedule_condition = "c.schedule_type = 'odd'";
} else {
    $schedule_condition = "c.schedule_type = 'even'";
}


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

<link href="../../assets/css/supervisor/supervisor-homework.css?v=<?= time() ?>" rel="stylesheet">


<ul class="nav nav-tabs mb-4">
    <li class="nav-item">
        <a class="nav-link <?= $tab == 'odd' ? 'active' : '' ?>" href="?tab=odd">روزهای فرد</a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $tab == 'even' ? 'active' : '' ?>" href="?tab=even">روزهای زوج</a>
    </li>
</ul>

<?php if (empty($classes)): ?>
    <div class="card">
        <div class="card-body text-center py-5">
            <div style="font-size: 3rem; margin-bottom: 20px;">
                <?= $tab == 'today' ? '📚' : ($tab == 'odd' ? '🔢' : '🔢') ?>
            </div>
            <h4>
                <?php
                if ($tab == 'today') {
                    echo $today_weekday == 6 ? 'امروز جمعه است' : 'کلاسی برای امروز یافت نشد';
                } else {
                    echo 'کلاسی یافت نشد';
                }
                ?>
            </h4>
            <p class="text-light">
                <?php
                if ($tab == 'today') {
                    if ($today_weekday == 6) {
                        echo 'جمعه روز تعطیل است.';
                    } else {
                        echo 'کلاسی برای روز ' . ($is_today_odd ? 'فرد' : 'زوج') . ' تعریف نشده است.';
                    }
                } else {
                    echo 'برای مشاهده تکالیف، کلاسی تعریف کنید.';
                }
                ?>
            </p>
        </div>
    </div>
<?php else: ?>
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered homework-session-table">
                    <thead class="table-light">
                        <tr>
                            <th>کلاس</th>
                            <th>معلم</th>
                            <?php for ($i = 1; $i <= 14; $i++): ?>
                                <th>جلسه <?= $i ?></th>
                            <?php endfor; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($classes as $class): ?>
                        <tr>
                            <td><?= htmlspecialchars($class['title']) ?><?= class_live_dot($class) ?></td>
                            <td><?= htmlspecialchars($class['teacher_name']) ?></td>
							<?php for ($i = 0; $i < 14; $i++): ?>
								<?php 
								$date = $class_sessions[$class['id']][$i];
								if ($date): 
									$stmt = $pdo->prepare("
										SELECT 
											SUM(CASE WHEN status = 'complete' THEN 1 ELSE 0 END) as complete_count,
											SUM(CASE WHEN status = 'incomplete' THEN 1 ELSE 0 END) as incomplete_count,
											COUNT(*) as total_count
										FROM homework_status  
										WHERE class_id = ? AND homework_date = ?
									");
									$stmt->execute([$class['id'], $date]);
									$stats = $stmt->fetch();
									
									if ($stats && $stats['total_count'] > 0) {
										$complete = (int)$stats['complete_count'];
										$incomplete = (int)$stats['incomplete_count'];
										$not_submitted = (int)$stats['total_count'] - $complete - $incomplete;
										$total = (int)$stats['total_count'];
										
										if ($complete == $total) {
											$status_class = 'text-success';
										} elseif ($not_submitted == $total) {
											$status_class = 'text-danger';
										} else {
											$status_class = 'text-warning';
										}
									} else {
										$status_class = 'text-muted';
										$complete = 0;
										$total = 0;
									}
								?>
								<td>
									<div class="session-cell <?= $status_class ?>" 
										onclick="showHomeworkDetails(<?= $class['id'] ?>, '<?= $date ?>')">
										<div class="session-date"><?= jdate('m/d', strtotime($date)) ?></div>
										<?php if ($total > 0): ?>
											<div class="session-stats">
												<small><?= $complete ?>/<?= $total ?></small>
											</div>
										<?php endif; ?>
									</div>
								</td>
								<?php else: ?>
								<td class="text-center text-muted">-</td>
								<?php endif; ?>
							<?php endfor; ?>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php endif; ?>

<div class="modal fade" id="homeworkModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header text-white">
                <h5 class="modal-title">جزئیات تکالیف</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="homework-details-content">
                    <div class="text-center py-4">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">در حال بارگذاری...</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function showHomeworkDetails(classId, date) {
    fetch(`get-homework-details.php?class_id=${classId}&date=${date}`)
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            let html = `
            <div class="homework-header mb-4 p-3 bg-primary bg-opacity-10 rounded">
                <h6 class="mb-1">کلاس: ${data.class_info.title}</h6>
                <p class="mb-0">تاریخ: ${data.jalali_date}</p>
            </div>
            <div class="table-responsive">
                <table class="table table-bordered">
                    <thead class="table-light">
                        <tr class="text-dark">
                            <th>نام دانش‌آموز</th>
                            <th>کد دانش‌آموز</th>
                            <th>وضعیت</th>
                        </tr>
                    </thead>
                    <tbody>`;
            
            data.students.forEach(student => {
                let statusClass = '';
                let statusText = '';
                
                switch(student.status) {
                    case 'complete':
                        statusClass = 'text-success';
                        statusText = 'انجام شده';
                        break;
                    case 'incomplete':
                        statusClass = 'text-warning';
                        statusText = 'ناتمام';
                        break;
                    case 'not_submitted':
                        statusClass = 'text-danger';
                        statusText = 'ارسال نشده';
                        break;
                    default:
                        statusClass = 'text-muted';
                        statusText = 'نامشخص';
                }
                
                html += `
                <tr>
                    <td>${student.full_name}</td>
                    <td>${student.student_code}</td>
                    <td><span class="${statusClass}">${statusText}</span></td>
                </tr>`;
            });
            
            html += `
                    </tbody>
                </table>
            </div>`;
            
            document.getElementById('homework-details-content').innerHTML = html;
        } else {
            document.getElementById('homework-details-content').innerHTML = 
                '<p class="text-center text-danger py-3">خطا در بارگذاری اطلاعات</p>';
        }
    })
    .catch(error => {
        console.error('Error:', error);
        document.getElementById('homework-details-content').innerHTML = 
            '<p class="text-center text-danger py-3">خطا در ارتباط با سرور</p>';
    });
    
    const modal = new bootstrap.Modal(document.getElementById('homeworkModal'));
    modal.show();
}

// حل مشکل لایه سیاه
document.addEventListener('hidden.bs.modal', function (event) {
    document.querySelectorAll('.modal-backdrop').forEach(el => el.remove());
});
</script>

<?php require_once 'layout-end.php'; ?>