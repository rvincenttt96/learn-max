<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
$title = 'نقاط قوت و ضعف (Error Gravity)';
require_once 'layout.php';
require_once '../../includes/db.php';
require_once '../../includes/terms.php';
require_role('supervisor'); // اضافه شد

$selected_class_id = $_GET['class_id'] ?? null;
$content = ''; 

// اصلاح شده: اضافه کردن فیلترهای کامل
$stmt = $pdo->prepare("
    SELECT c.id, c.title, c.schedule, c.schedule_type
    FROM classes c
    LEFT JOIN terms t ON c.term_id = t.id
    WHERE c.is_deleted = 0
    AND (" . term_sql_teaching('t') . " OR c.term_id IS NULL)
    AND IFNULL(c.is_archived, 0) = 0
    ORDER BY c.title
");
$stmt->execute();
$classes = $stmt->fetchAll();

if ($selected_class_id) {
    $contentStmt = $pdo->prepare("
        SELECT content 
        FROM error_gravity 
        WHERE class_id = ?
    ");
    $contentStmt->execute([$selected_class_id]);
    $result = $contentStmt->fetch();
    
    if ($result) {
        $content = $result['content']; 
    } else {
        $content = ''; 
    }
}
?>
<link href="../../assets/css/teacher/error-gravity.css?v=<?= time() ?>" rel="stylesheet">
<div class="card error-card">
    <div class="card-header text-white">
        <h5 class="mb-0">📝 نقاط قوت و ضعف (Error Gravity)</h5>
    </div>
    <div class="card-body">
        <?php if (empty($classes)): ?>
            <div class="alert alert-info text-white">شما هنوز کلاسی ندارید.</div>

        <?php elseif (!$selected_class_id): ?>
           
            <p class="text-white"><strong>لطفاً کلاس مورد نظر را انتخاب کنید:</strong></p>
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
                    <label class="form-label text-white">نقاط قوت و ضعف این کلاس را توضیح دهید:</label>
                    <textarea name="content" class="form-control" rows="6" placeholder="مثال: دانش‌آموزان فعال بودند ولی نیاز به تمرین بیشتر دارند..." readonly><?= htmlspecialchars($content) ?></textarea>
                </div>
                <div class="d-flex gap-2 flex-wrap">
                    <a href="error-gravity.php" class="btn error-action-btn">تغییر کلاس</a>
                </div>
            </form>

        <?php endif; ?>

    </div>
</div>

<?php require_once 'layout-end.php'; ?>