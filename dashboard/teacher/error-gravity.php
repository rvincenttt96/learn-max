<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once '../../includes/db.php';
require_once '../../includes/terms.php';
    
    $class_id = (int)($_POST['class_id'] ?? 0);
    $user_id = (int)($_POST['user_id'] ?? 0);
    $teacher_id = $user_id;
    
    if (!$class_id || !$teacher_id) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => 'دسترسی غیرمجاز.']);
        exit;
    }
    
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
    $content = trim($_POST['content'] ?? '');

    if (empty($content)) {
        
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => 'متن نمی‌تواند خالی باشد.']);
        exit;
    } else {
        try {
            
            $stmt = $pdo->prepare("
                INSERT INTO error_gravity (class_id, teacher_id, content, is_deleted)
                VALUES (?, ?, ?, 0)
                ON DUPLICATE KEY UPDATE 
                    content = VALUES(content),
                    is_deleted = 0,
                    deleted_by = NULL,
                    deleted_at = NULL,
                    updated_at = NOW()
            ");
            $stmt->execute([$class_id, $teacher_id, $content]);
            
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => true, 'message' => 'نقاط قوت و ضعف با موفقیت ذخیره شد.']);
            exit;
        } catch (Exception $e) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'message' => 'خطا در ذخیره‌سازی.']);
            exit;
        }
    }
}
  
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => 'عملیات نامعتبر.']);
    exit;
}

$title = 'Error Gravity';
require_once 'layout.php';
require_once '../../includes/db.php';

$teacher_id = $_SESSION['user_id'] ?? null;
if (!$teacher_id) {
    header("Location: ../login.php?error=" . urlencode('ابتدا وارد شوید.'));
    exit;
}


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


$selected_class_id = (int)($_GET['class_id'] ?? 0);
$class_title = '';
$content = '';

if ($selected_class_id) {

    $stmt = $pdo->prepare("SELECT title FROM classes WHERE id = ? AND teacher_id = ?");
    $stmt->execute([$selected_class_id, $teacher_id]);
    $class_title = $stmt->fetchColumn();

    if (!$class_title) {
        header("Location: error-gravity.php?error=" . urlencode('دسترسی غیرمجاز.'));
        exit;
    }


    $stmt = $pdo->prepare("SELECT content FROM error_gravity WHERE class_id = ? AND teacher_id = ? AND is_deleted = 0");
    $stmt->execute([$selected_class_id, $teacher_id]);
    $row = $stmt->fetch();
    $content = $row ? $row['content'] : '';
}
?>
<link href="../../assets/css/teacher/error-gravity.css?v=<?= time() ?>" rel="stylesheet">
<div class="card error-card">
    <div class="card-header text-white">
        <h5 class="mb-0">Error Gravity</h5>
    </div>
    <div class="card-body">

        <?php if (empty($classes)): ?>
            <div class="alert alert-info text-white">No Classes Available</div>

        <?php elseif (!$selected_class_id): ?>
           
            <p class="text-white"><strong>Select The Class </strong></p>
            <div class="list-group error-class-list">
                <?php foreach ($classes as $class): ?>
                    <a href="error-gravity.php?class_id=<?= $class['id'] ?>" class="list-group-item list-group-item-action error-class-item">
                        <?= htmlspecialchars($class['title']) ?><?= class_live_dot($class) ?>
                    </a>
                <?php endforeach; ?>
            </div>

        <?php else: ?>
           
            <form id="save-form" class="error-form">
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="class_id" value="<?= $selected_class_id ?>">
    <input type="hidden" name="user_id" value="<?= $_SESSION['user_id'] ?>">
    <div class="mb-3">
        <label class="form-label text-white">Give Us The Details:</label>
        <textarea name="content" class="form-control" rows="6" placeholder="مثال: دانش‌آموزان فعال بودند ولی در گرامر present perfect مشکل دارند..."><?= htmlspecialchars($content) ?></textarea>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <button type="button" id="submit-btn" class="btn error-save-btn">Save</button>
        <a href="error-gravity.php" class="btn error-action-btn">Change the Class</a>
    </div>
</form>

        <?php endif; ?>

    </div>
</div>

<?php require_once 'layout-end.php'; ?>

<?php if ($selected_class_id): ?>
<script>
document.getElementById('submit-btn').addEventListener('click', function() {
    const form = document.getElementById('save-form');
    const formData = new FormData(form);
    
    fetch('', { method: 'POST', body: formData })
    .then(response => response.json())
    .then(data => {
        alert(data.message);
        if (data.success) {
            window.location.href = 'error-gravity.php?class_id=<?= $selected_class_id ?>';
        }
    })
    .catch(error => {
        console.error('خطا:', error);
        alert('خطا در ارتباط با سرور.');
    });
});
</script>
<?php endif; ?>