<?php

if (!empty($_GET['action']) && $_GET['action'] == 'get_students') {
    require_once '../../includes/db.php';
require_once '../../includes/terms.php';
    require_once '../../includes/auth.php';
    require_role('teacher');
    header('Content-Type: application/json; charset=utf-8');
    
    try {
        $class_id = (int)($_GET['class_id'] ?? 0);
        if (!$class_id) {
            throw new Exception('شناسه کلاس نامعتبر است.');
        }
        
        $stmt = $pdo->prepare("SELECT id FROM classes WHERE id = ? AND teacher_id = ?");
        $stmt->execute([$class_id, $_SESSION['user_id']]);
        if (!$stmt->fetch()) {
            throw new Exception('دسترسی غیرمجاز.');
        }
        
        $stmt = $pdo->prepare("
            SELECT c.title, u.full_name as teacher_name, u.phone as teacher_phone
            FROM classes c
            JOIN users u ON c.teacher_id = u.id
            WHERE c.id = ?
        ");
        $stmt->execute([$class_id]);
        $class_info = $stmt->fetch();
        
        if (!$class_info) {
            throw new Exception('کلاس یافت نشد.');
        }

        require_once '../../includes/class-roster.php';
        class_roster_restore_if_empty($pdo, $class_id);
        
        $stmt = $pdo->prepare("
            SELECT u.id, u.full_name, u.username as student_code, u.phone
            FROM users u
            JOIN class_students cs ON u.id = cs.user_id
            WHERE cs.class_id = ? AND u.role = 'student' AND u.is_deleted = 0
            ORDER BY u.full_name
        ");
        $stmt->execute([$class_id]);
        $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'class_info' => $class_info,
            'students' => $students
        ], JSON_UNESCAPED_UNICODE);
        exit;
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false, 
            'message' => $e->getMessage()
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}


$title = 'My Class List';
require_once 'layout.php';
require_once '../../includes/db.php';
require_role('teacher');
require_once '../../includes/alocom-access.php';
$alocom_login = alocom_panel_login_for_teacher($pdo, (int)$_SESSION['user_id'], $panel_username ?? '');

$stmt = $pdo->prepare("
    SELECT c.id, c.title, c.class_type, c.online_link, c.schedule, c.schedule_type
    FROM classes c
    LEFT JOIN terms t ON c.term_id = t.id
    WHERE c.teacher_id = ? 
    AND c.is_deleted = 0 
    AND (" . term_sql_teaching('t') . " OR c.term_id IS NULL)
    AND IFNULL(c.is_archived, 0) = 0
    ORDER BY c.title
");
$stmt->execute([$_SESSION['user_id']]);
$classes = $stmt->fetchAll();
?>
<link href="../../assets/css/teacher/classes.css?v=alocom-cred1" rel="stylesheet">

<div class="card classes-card">
    <div class="card-header text-center">
        <h5 class="mb-0">
            my class lists
            <svg style="white:25px;height:25px;" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 448 512">
                <path opacity=".4" fill="currentColor" d="M32 128l0 288c0 17.7 14.3 32 32 32l320 0c17.7 0 32-14.3 32-32l0-288c0-17.7-14.3-32-32-32L64 96c-17.7 0-32 14.3-32 32zM96 240c0-8.8 7.2-16 16-16l224 0c8.8 0 16 7.2 16 16s-7.2 16-16 16l-224 0c-8.8 0-16-7.2-16-16zm0 96c0-8.8 7.2-16 16-16l128 0c8.8 0 16 7.2 16 16s-7.2 16-16 16l-128 0c-8.8 0-16-7.2-16-16z"/>
                <path fill="currentColor" d="M128 16c0-8.8-7.2-16-16-16S96 7.2 96 16l0 48-32 0C28.7 64 0 92.7 0 128L0 416c0 35.3 28.7 64 64 64l320 0c35.3 0 64-28.7 64-64l0-288c0-35.3-28.7-64-64-64l-32 0 0-48c0-8.8-7.2-16-16-16s-16 7.2-16 16l0 48-192 0 0-48zM64 96l320 0c17.7 0 32 14.3 32 32l0 288c0 17.7-14.3 32-32 32L64 448c-17.7 0-32-14.3-32-32l0-288c0-17.7 14.3-32 32-32zM96 240c0 8.8 7.2 16 16 16l224 0c8.8 0 16-7.2 16-16s-7.2-16-16-16l-224 0c-8.8 0-16 7.2-16 16zm16 80c-8.8 0-16 7.2-16 16s7.2 16 16 16l128 0c8.8 0 16-7.2 16-16s-7.2-16-16-16l-128 0z"/>
            </svg>
        </h5>
    </div>
    <div class="card-body">
        <?php if (!empty($alocom_login['username'])): ?>
            <div class="alocom-login-box">
                <span class="alocom-login-kicker">نام کاربری و رمز الوکام</span>
                <div class="alocom-login-grid">
                    <div class="alocom-login-item">
                        <span class="alocom-login-label">نام کاربری</span>
                        <span class="alocom-login-value" dir="ltr"><?= htmlspecialchars($alocom_login['username'], ENT_QUOTES, 'UTF-8') ?></span>
                        <button type="button" class="alocom-login-copy" data-copy="<?= htmlspecialchars($alocom_login['username'], ENT_QUOTES, 'UTF-8') ?>">کپی نام کاربری</button>
                    </div>
                    <div class="alocom-login-item">
                        <span class="alocom-login-label">رمز عبور</span>
                        <span class="alocom-login-value" dir="ltr"><?= htmlspecialchars($alocom_login['password'], ENT_QUOTES, 'UTF-8') ?></span>
                        <button type="button" class="alocom-login-copy" data-copy="<?= htmlspecialchars($alocom_login['password'], ENT_QUOTES, 'UTF-8') ?>">کپی رمز</button>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        <?php if (empty($classes)): ?>
            <div class="alert alert-info">No classes Available</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-bordered classes-table">
                    <thead class="table-light">
                        <tr>
                            <th>Class Title</th>
                            <th>Class Type</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($classes as $class): ?>
                        <tr>
                            <td><?= htmlspecialchars($class['title']) ?><?= class_live_dot($class) ?></td>
                            <td>
                                <?php if ($class['class_type'] == 'online'): ?>
                                    <span class="badge bg-success">Online</span>
                                <?php elseif ($class['class_type'] == 'private'): ?>
                                    <span class="badge" style="background-color:#9b59b6;">Private</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Inperson</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <button type="button" class="btn btn-sm btn-outline-danger me-1" 
                                        onclick="showStudents(<?= $class['id'] ?>)" title="مشاهده دانش‌آموزان">
                                    Learners list
                                </button>
                                <a href="remarks.php?class_id=<?= $class['id'] ?>" class="btn btn-sm btn-outline-danger">remarks</a>
                                <?php if (!empty($class['online_link'])): ?>
                                    <a href="<?= htmlspecialchars($class['online_link']) ?>" 
                                       target="_blank"
                                       class="btn btn-sm btn-success" title="ورود به اتاق الوکام">
                                        <?= $class['class_type'] === 'private' ? 'Join room' : ($class['class_type'] === 'online' ? 'Join the class' : 'Join backup room') ?>
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
        <a href="index.php" class="btn back-to-dashboard">Back to Dashboard</a>
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
    fetch('classes.php?action=get_students&class_id=' + encodeURIComponent(classId), {
        headers: { 'Accept': 'application/json' },
        credentials: 'same-origin'
    })
    .then(function (response) { return response.json(); })
    .then(function (data) {
        if (window.lgRenderParticipants) {
            window.lgRenderParticipants('students-list-content', data, { label: 'Class' });
            return;
        }
        box.innerHTML = data && data.success
            ? '<div class="lg-student-status">' + (data.students || []).length + ' learners</div>'
            : '<div class="lg-student-status is-error">خطا در بارگذاری اطلاعات کلاس</div>';
    })
    .catch(function (error) {
        console.error('Error:', error);
        box.innerHTML = '<div class="lg-student-status is-error">خطا در ارتباط با سرور</div>';
    });

    bootstrap.Modal.getOrCreateInstance(document.getElementById('studentsModal')).show();
}

(function () {
    document.querySelectorAll('.alocom-login-copy').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var value = btn.getAttribute('data-copy') || '';
            var done = function () {
                var prev = btn.textContent;
                btn.classList.add('is-copied');
                btn.textContent = 'کپی شد';
                setTimeout(function () {
                    btn.classList.remove('is-copied');
                    btn.textContent = prev;
                }, 1400);
            };
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(value).then(done).catch(function () {
                    window.prompt('کپی کنید', value);
                });
                return;
            }
            window.prompt('کپی کنید', value);
        });
    });
})();
</script>

<?php require_once 'layout-end.php'; ?>