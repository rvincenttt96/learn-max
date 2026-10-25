<?php
$title = 'My Classes';
require_once 'layout.php';
require_once '../../includes/db.php';
require_once '../../includes/terms.php';
require_role('student');

$user_id = $_SESSION['user_id'];
require_once '../../includes/alocom-access.php';
$alocom_login = alocom_panel_login_for_student($pdo, $user_id, $username ?? '');

$stmt = $pdo->prepare("
    SELECT 
        c.id, c.title, c.schedule, c.schedule_type, c.class_type, c.online_link,
        u.full_name as teacher_name,
        t.title as term_title
    FROM classes c
    JOIN class_students cs ON c.id = cs.class_id
    JOIN users u ON c.teacher_id = u.id
    JOIN terms t ON c.term_id = t.id
    WHERE cs.user_id = ? AND c.is_deleted = 0 AND IFNULL(c.is_archived, 0) = 0 AND " . term_sql_teaching('t') . "
    ORDER BY c.title
");
$stmt->execute([$user_id]);
$classes = $stmt->fetchAll();

$schedule_types = [
    'odd' => '(یکشنبه/سه‌شنبه/پنجشنبه) روز فرد ',
    'even' => 'روز زوج (شنبه/دوشنبه/چهارشنبه) '
];
?>

<link href="../../assets/css/student/student-classes.css?v=alocom-cred1" rel="stylesheet">


<?php if (empty($classes)): ?>
    <div class="card">
        <div class="card-body text-center py-5">
            <div style="font-size: 3rem; margin-bottom: 20px;">📚</div>
            <h4>کلاسی برای شما ثبت نشده است</h4>
        </div>
    </div>
<?php else: ?>
    <div class="row g-4">
        <?php foreach ($classes as $class): ?>
        <div class="col-12 col-md-12 col-lg-12">
            <div class="card class-card h-100">
                <div class="card-header bg-danger text-white">
                    <h5 class="mb-0"><?= htmlspecialchars($class['title']) ?><?= class_live_dot($class) ?></h5>
                </div>
                <div class="card-body">
                    <div class="class-info mb-3">
                        <p><strong>ترم:</strong> <?= htmlspecialchars($class['term_title']) ?></p>
                        <p><strong>معلم:</strong> <?= htmlspecialchars($class['teacher_name']) ?></p>
                        <p><strong>زمان:</strong> <?= htmlspecialchars($class['schedule'] ?? 'تعیین نشده') ?></p>
                        <p><strong>روز:</strong> <?= $schedule_types[$class['schedule_type']] ?? 'نا مشخص' ?></p>
                    </div>
                    
                    <div class="class-type mb-3">
                        <?php if ($class['class_type'] == 'online'): ?>
                            <span class="badge bg-success">کلاس آنلاین</span>
                        <?php elseif ($class['class_type'] == 'private'): ?>
                            <span class="badge" style="background-color:#9b59b6;">کلاس خصوصی</span>
                        <?php else: ?>
                            <span class="badge bg-secondary">کلاس حضوری</span>
                        <?php endif; ?>
                    </div>
                    
                    <?php
                    $class_type = strtolower((string)($class['class_type'] ?? ''));
                    $has_online_link = trim((string)($class['online_link'] ?? '')) !== '';
                    $show_online = $has_online_link;
                    $join_label = $class_type === 'private'
                        ? 'ورود به اتاق الوکام'
                        : ($class_type === 'online' ? 'ورود به کلاس آنلاین' : 'ورود به رویداد آنلاین');
                    ?>
                    <?php if ($show_online): ?>
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
                            <a href="join-online.php?class_id=<?= (int)$class['id'] ?>"
                               class="btn btn-success w-100 alocom-join-btn">
                                <i class="fas fa-video me-2"></i><?= htmlspecialchars($join_label) ?>
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<script>
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