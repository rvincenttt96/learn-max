<?php
$title = 'Class Lists';
require_once 'layout.php';
require_once '../../includes/db.php';
require_once '../../includes/terms.php';
require_role('supervisor');
date_default_timezone_set('Asia/Tehran');

$stmt = $pdo->prepare("
    SELECT 
        c.id, c.title, c.schedule, c.schedule_type, c.class_type, c.online_link, 
        COALESCE(u.full_name, 'معلم حذف شده') as teacher_name,
        COALESCE(t.title, 'ترم حذف شده') as term_title
    FROM classes c
    LEFT JOIN users u ON c.teacher_id = u.id
    LEFT JOIN terms t ON c.term_id = t.id
    WHERE c.is_deleted = 0 
    AND " . term_sql_teaching('t') . "
    AND IFNULL(c.is_archived, 0) = 0
    ORDER BY 
        CASE WHEN c.schedule_type = 'odd' THEN 1 ELSE 2 END,
        c.schedule
");
$stmt->execute();
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

<link href="/assets/css/supervisor/supervisor-classes.css?v=<?= time() ?>" rel="stylesheet">


<div class="card class-list-card mb-4">
    <div class="card-header bg-danger text-white">
        <h5 class="mb-0">
		<svg style="white:25px;height:25px;" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 448 512"><!--! Font Awesome Pro 7.1.0 by @fontawesome - https://fontawesome.com License - https://fontawesome.com/license (Commercial License) Copyright 2025 Fonticons, Inc. --><path opacity=".4" fill="currentColor" d="M32 128l0 288c0 17.7 14.3 32 32 32l320 0c17.7 0 32-14.3 32-32l0-288c0-17.7-14.3-32-32-32L64 96c-17.7 0-32 14.3-32 32zM96 240c0-8.8 7.2-16 16-16l224 0c8.8 0 16 7.2 16 16s-7.2 16-16 16l-224 0c-8.8 0-16-7.2-16-16zm0 96c0-8.8 7.2-16 16-16l128 0c8.8 0 16 7.2 16 16s-7.2 16-16 16l-128 0c-8.8 0-16-7.2-16-16z"/><path fill="currentColor" d="M128 16c0-8.8-7.2-16-16-16S96 7.2 96 16l0 48-32 0C28.7 64 0 92.7 0 128L0 416c0 35.3 28.7 64 64 64l320 0c35.3 0 64-28.7 64-64l0-288c0-35.3-28.7-64-64-64l-32 0 0-48c0-8.8-7.2-16-16-16s-16 7.2-16 16l0 48-192 0 0-48zM64 96l320 0c17.7 0 32 14.3 32 32l0 288c0 17.7-14.3 32-32 32L64 448c-17.7 0-32-14.3-32-32l0-288c0-17.7 14.3-32 32-32zM96 240c0 8.8 7.2 16 16 16l224 0c8.8 0 16-7.2 16-16s-7.2-16-16-16l-224 0c-8.8 0-16 7.2-16 16zm16 80c-8.8 0-16 7.2-16 16s7.2 16 16 16l128 0c8.8 0 16-7.2 16-16s-7.2-16-16-16l-128 0z"/></svg>
		 Classes for today(Sun/Tue/Thu)</h5>
    </div>
    <div class="card-body">
        <?php if (empty($odd_classes)): ?>
            <p class="text-muted text-center py-3">No classes available</p>
        <?php else: ?>
            <div class="row g-3">
                <?php foreach ($odd_classes as $class): ?>
                <div class="col-12">
                    <div class="class-item">
                        <div class="class-header">
                            <h6 class="mb-0">
                                <?= htmlspecialchars($class['title']) ?><?= class_live_dot($class) ?>
                            </h6>
                            <small class="text-white"><?= htmlspecialchars($class['term_title']) ?></small>
                        </div>
                        
                        <div class="class-details">
                            <div class="detail-item">
                                <i class="fas fa-clock"></i>
                                <span><?= htmlspecialchars($class['schedule']) ?></span>
                            </div>
                            <div class="detail-item">
                                <i class="fas fa-chalkboard-teacher"></i>
                                <span><?= htmlspecialchars($class['teacher_name']) ?></span>
                            </div>
                            <div class="detail-item">
                                <?php if ($class['class_type'] == 'online'): ?>
                                    <i class="fas fa-laptop text-success"></i>
                                    <span class="text-success">online</span>
                                <?php elseif ($class['class_type'] == 'private'): ?>
                                    <i class="fas fa-user text-info"></i>
                                    <span style="color:#c084fc;">private</span>
                                <?php else: ?>
                                    <i class="fas fa-building text-secondary"></i>
                                    <span class="text-secondary">Inperson</span>
                                <?php endif; ?>
                            </div>
							
                        </div>
                        <div class="class-actions justify-content-md-end">
                            <button type="button" class="btn btn-sm btn-danger" 
                                    onclick="showStudents(<?= $class['id'] ?>)">
                                Learners list
                            </button>
                            <a href="remarks-view.php?class_id=<?= $class['id'] ?>" class="btn btn-sm btn-info">مشاهده توضیحات</a>
                            <?php if (!empty($class['online_link'])): ?>
                                <a href="<?= htmlspecialchars($class['online_link']) ?>" 
                                   target="_blank" class="btn btn-sm btn-success">
                                    <i class="fas fa-video"></i> <?= $class['class_type'] === 'private' ? 'Join room' : ($class['class_type'] === 'online' ? 'Join the class' : 'Join backup room') ?>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>


<div class="card class-list-card">
    <div class="card-header bg-danger text-white">
        <h5 class="mb-0">
		<svg style="white:25px;height:25px;" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 448 512"><!--! Font Awesome Pro 7.1.0 by @fontawesome - https://fontawesome.com License - https://fontawesome.com/license (Commercial License) Copyright 2025 Fonticons, Inc. --><path opacity=".4" fill="currentColor" d="M32 128l0 288c0 17.7 14.3 32 32 32l320 0c17.7 0 32-14.3 32-32l0-288c0-17.7-14.3-32-32-32L64 96c-17.7 0-32 14.3-32 32zM96 240c0-8.8 7.2-16 16-16l224 0c8.8 0 16 7.2 16 16s-7.2 16-16 16l-224 0c-8.8 0-16-7.2-16-16zm0 96c0-8.8 7.2-16 16-16l128 0c8.8 0 16 7.2 16 16s-7.2 16-16 16l-128 0c-8.8 0-16-7.2-16-16z"/><path fill="currentColor" d="M128 16c0-8.8-7.2-16-16-16S96 7.2 96 16l0 48-32 0C28.7 64 0 92.7 0 128L0 416c0 35.3 28.7 64 64 64l320 0c35.3 0 64-28.7 64-64l0-288c0-35.3-28.7-64-64-64l-32 0 0-48c0-8.8-7.2-16-16-16s-16 7.2-16 16l0 48-192 0 0-48zM64 96l320 0c17.7 0 32 14.3 32 32l0 288c0 17.7-14.3 32-32 32L64 448c-17.7 0-32-14.3-32-32l0-288c0-17.7 14.3-32 32-32zM96 240c0 8.8 7.2 16 16 16l224 0c8.8 0 16-7.2 16-16s-7.2-16-16-16l-224 0c-8.8 0-16 7.2-16 16zm16 80c-8.8 0-16 7.2-16 16s7.2 16 16 16l128 0c8.8 0 16-7.2 16-16s-7.2-16-16-16l-128 0z"/></svg>
		 Classes for today(Sat/Mon/Wed)</h5>
    </div>
    <div class="card-body">
        <?php if (empty($even_classes)): ?>
            <p class="text-muted text-center py-3">No classes available</p>
        <?php else: ?>
            <div class="row g-3">
                <?php foreach ($even_classes as $class): ?>
                <div class="col-12">
                    <div class="class-item">
                        <div class="class-header">
                            <h6 class="mb-0">
                                <?= htmlspecialchars($class['title']) ?><?= class_live_dot($class) ?>
                            </h6>
                            <small class="text-white"><?= htmlspecialchars($class['term_title']) ?></small>
                        </div>
                        
                        <div class="class-details">
                            <div class="detail-item">
                                <i class="fas fa-clock"></i>
                                <span><?= htmlspecialchars($class['schedule']) ?></span>
                            </div>
                            <div class="detail-item">
                                <i class="fas fa-chalkboard-teacher"></i>
                                <span><?= htmlspecialchars($class['teacher_name']) ?></span>
                            </div>
                            <div class="detail-item">
                                <?php if ($class['class_type'] == 'online'): ?>
                                    <i class="fas fa-laptop text-success"></i>
                                    <span class="text-success">Online</span>
                                <?php elseif ($class['class_type'] == 'private'): ?>
                                    <i class="fas fa-user text-info"></i>
                                    <span style="color:#c084fc;">private</span>
                                <?php else: ?>
                                    <i class="fas fa-building text-secondary"></i>
                                    <span class="text-secondary">Inperson</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="class-actions justify-content-md-end">
                            <button type="button" class="btn btn-sm btn-danger" 
                                    onclick="showStudents(<?= $class['id'] ?>)">
                                Learners list
                            </button>
                            <a href="remarks-view.php?class_id=<?= $class['id'] ?>" class="btn btn-sm btn-info">مشاهده توضیحات</a>
                            <?php if (!empty($class['online_link'])): ?>
                                <a href="<?= htmlspecialchars($class['online_link']) ?>" 
                                   target="_blank" class="btn btn-sm btn-success">
                                    <i class="fas fa-video"></i> <?= $class['class_type'] === 'private' ? 'Join room' : ($class['class_type'] === 'online' ? 'Join the class' : 'Join backup room') ?>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="modal fade" id="studentsModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Participants</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="students-list-content">
                    <div class="lg-student-status">I'm processing... wait...</div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function showStudents(classId) {
    var box = document.getElementById('students-list-content');
    box.innerHTML = '<div class="lg-student-status">I\'m processing... wait...</div>';
    fetch('get-class-students-simple.php?class_id=' + encodeURIComponent(classId), {
        headers: { 'Accept': 'application/json' },
        credentials: 'same-origin'
    })
    .then(function (response) { return response.json(); })
    .then(function (data) {
        if (window.lgRenderParticipants) {
            window.lgRenderParticipants('students-list-content', data, {
                label: 'معلم کلاس',
                showTeacherPhone: true,
                showPhone: true
            });
            return;
        }
        box.innerHTML = '<div class="lg-student-status is-error">خطا در بارگذاری اطلاعات کلاس</div>';
    })
    .catch(function (error) {
        console.error('Error:', error);
        box.innerHTML = '<div class="lg-student-status is-error">خطا در ارتباط با سرور</div>';
    });

    bootstrap.Modal.getOrCreateInstance(document.getElementById('studentsModal')).show();
}
</script>

<?php require_once 'layout-end.php'; ?>