<?php
// ======================================================================
// 1. بخش درخواست‌های AJAX (باید دقیقاً در ابتدای فایل و قبل از HTML باشد)
// ======================================================================
if (!empty($_GET['action']) && $_GET['action'] == 'get_students_list') {
    require_once '../../includes/db.php';
    require_once '../../includes/terms.php';
    require_once '../../includes/auth.php';
    // اگر تابع require_role در auth.php هست اینجا در دسترسه
    
    // خاموش کردن ارورهای PHP برای جلوگیری از خراب شدن ساختار JSON
    ini_set('display_errors', 0);
    header('Content-Type: application/json');
    
    try {
        $class_id = (int)($_GET['class_id'] ?? 0);
        
        if (!$class_id) {
            throw new Exception('شناسه کلاس نامعتبر است.');
        }
        
        $stmt = $pdo->prepare("
            SELECT u.id, u.full_name, u.username as student_code, u.phone
            FROM users u
            JOIN class_students cs ON u.id = cs.user_id
            WHERE cs.class_id = ? AND u.role = 'student' AND u.is_deleted = 0
            ORDER BY u.full_name
        ");
        $stmt->execute([$class_id]);
        $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['success' => true, 'students' => $students]);
        exit;
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

// ======================================================================
// 2. بخش لود صفحه اصلی داشبورد (UI)
// ======================================================================
error_reporting(E_ALL);
ini_set('display_errors', 1);

$title = 'داشبورد سوپروایزر';
require_once 'layout.php'; // تولید کدهای هدر HTML از اینجا شروع می‌شود
require_once '../../includes/db.php';
// require_once '../../includes/jdate-enhanced.php';

require_role('supervisor');

$stmt = $pdo->prepare("
    SELECT c.id, c.title, c.schedule, c.schedule_type, c.class_type, c.online_link, u.full_name as teacher_name
    FROM classes c
    JOIN users u ON c.teacher_id = u.id
    JOIN terms t ON c.term_id = t.id
    WHERE c.is_deleted = 0 
    AND " . term_sql_teaching('t') . "
    AND IFNULL(c.is_archived, 0) = 0
    ORDER BY c.title
");
$stmt->execute();
$classes = $stmt->fetchAll();

$odd_classes = [];
$even_classes = [];

foreach ($classes as $class) {
    if ($class['schedule_type'] == 'odd') {
        $even_classes[] = $class;
    } else {
        $odd_classes[] = $class;
    }
}
?>

<link href="../../assets/css/supervisor/supervisor-dashboard.css?v=<?= time() ?>" rel="stylesheet">


<?php
date_default_timezone_set('Asia/Tehran');

$today_eng_weekday = date('w'); 

$mapping = [
    6 => 1, // شنبه
    0 => 2, // یکشنبه
    1 => 3, // دوشنبه
    2 => 4, // سه‌شنبه
    3 => 5, // چهارشنبه
    4 => 6, // پنج‌شنبه
    5 => 7  // جمعه
];

$today_weekday = $mapping[$today_eng_weekday];

if ($today_weekday == 7) {
    $active_classes = [];
    $section_title = 'It is Friday you know...';
} 
elseif (in_array($today_weekday, [1, 3, 5])) {
    $active_classes = $odd_classes; 
    $section_title = 'Classes for today (Sat/Mon/Wed)'; 
} 
else {
    $active_classes = $even_classes; 
    $section_title = 'Classes for today (Sun/Tue/Thu)'; 
}
?>

<?php if (!empty($active_classes)): ?>
<div class="card mb-4 ">
    <div class="card-header bg-danger text-white">
        <h5 class="mb-0">📅 <?= htmlspecialchars($section_title) ?></h5>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <?php foreach ($active_classes as $class): ?>
            <div class="col-12 col-md-6 col-lg-2 ">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-2 ">
                            <h6 class="card-title mb-0"><?= htmlspecialchars($class['title']) ?><?= class_live_dot($class) ?></h6>
                            <?php if ($class['class_type'] == 'online'): ?>
                                <span class="badge bg-success">Online</span>
                            <?php else: ?>
                                <span class="badge bg-secondary">Inperson</span>
                            <?php endif; ?>
                        </div>
                        <p Style="font-size:12px; text-align:center;color:#fff;">Instructor: <?= htmlspecialchars($class['teacher_name']) ?></p>
                        
                        <div class="mt-2">
                            <button type="button " 
                                    class="btn w-100 btn-danger"
                                    onclick="showStudentsMain(<?= $class['id'] ?>, '<?= htmlspecialchars($class['title']) ?>')">
                                اسامی دانش‌آموزان
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php else: ?>
<div class="card mb-4">
    <div class="card-header bg-warning text-white">
        <h5 class="mb-0">📅 <?= htmlspecialchars($section_title) ?></h5>
    </div>
    <div class="card-body text-center">
        <div class="alert alert-info mb-0">
            <strong>We're off the clock!</strong><br>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="modal fade" id="studentsListModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Participants</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="students-list-content-main">
                    <div class="lg-student-status">I'm processing... wait...</div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    
    <div class="col-6 col-sm-6 col-md-4 col-lg-3">
        <a href="placement.php" class="text-decoration-none">
            <div class="dashboard-card bg-placement h-100">
                <div class="card-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 576 512"><path fill="currentColor" d="M64 32C28.7 32 0 60.7 0 96L0 416c0 35.3 28.7 64 64 64l448 0c35.3 0 64-28.7 64-64l0-320c0-35.3-28.7-64-64-64L64 32zm80 256a64 64 0 1 1 0 128 64 64 0 1 1 0-128zm-32-80c0-26.5 21.5-48 48-48l224 0c26.5 0 48 21.5 48 48s-21.5 48-48 48l-224 0c-26.5 0-48-21.5-48-48zm288 80a64 64 0 1 1 0 128 64 64 0 1 1 0-128z"/></svg>
                </div>
                <h5 class="mb-1">کلاس‌بندی</h5>
            </div>
        </a>
    </div>

    <div class="col-6 col-sm-6 col-md-4 col-lg-3">
        <a href="eas.php" class="text-decoration-none">
            <div class="dashboard-card bg-eas h-100">
                <div class="card-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><path fill="currentColor" d="M0 416c0 17.7 14.3 32 32 32l54.7 0c12.3 28.3 40.5 48 73.3 48s61-19.7 73.3-48L480 448c17.7 0 32-14.3 32-32s-14.3-32-32-32l-246.7 0c-12.3-28.3-40.5-48-73.3-48s-61 19.7-73.3 48L32 384c-17.7 0-32 14.3-32 32zm128 0a32 32 0 1 0 -64 0 32 32 0 1 0 64 0zM320 256a32 32 0 1 0 -64 0 32 32 0 1 0 64 0zm32-80c-12.3-28.3-40.5-48-73.3-48s-61 19.7-73.3 48L32 176c-17.7 0-32 14.3-32 32s14.3 32 32 32l246.7 0c12.3 28.3 40.5 48 73.3 48s61-19.7 73.3-48l54.7 0c17.7 0 32-14.3 32-32s-14.3-32-32-32l-54.7 0zM192 64a32 32 0 1 0 -64 0 32 32 0 1 0 64 0zm73.3 0L480 64c17.7 0 32-14.3 32-32s-14.3-32-32-32l-214.7 0c-12.3-28.3-40.5-48-73.3-48s-61 19.7-73.3 48L32 0C14.3 0 0 14.3 0 32s14.3 32 32 32l86.7 0c12.3 28.3 40.5 48 73.3 48s61-19.7 73.3-48z"/></svg>
                </div>
                <h5 class="mb-1">EAS Panel</h5>
            </div>
        </a>
    </div>

    <div class="col-6 col-sm-6 col-md-4 col-lg-3">
        <a href="classes.php" class="text-decoration-none">
            <div class="dashboard-card bg-class h-100">
                <div class="card-icon">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><path opacity=".4" fill="currentColor" d="M32 96l0 320c0 17.7 14.3 32 32 32l320 0c17.7 0 32-14.3 32-32l0-320c0-17.7-14.3-32-32-32L64 64C46.3 64 32 78.3 32 96zm120 64a24 24 0 1 1 -48 0 24 24 0 1 1 48 0zm0 96a24 24 0 1 1 -48 0 24 24 0 1 1 48 0zm0 96a24 24 0 1 1 -48 0 24 24 0 1 1 48 0zm40-192c0-8.8 7.2-16 16-16l128 0c8.8 0 16 7.2 16 16s-7.2 16-16 16l-128 0c-8.8 0-16-7.2-16-16zm0 96c0-8.8 7.2-16 16-16l128 0c8.8 0 16 7.2 16 16s-7.2 16-16 16l-128 0c-8.8 0-16-7.2-16-16zm0 96c0-8.8 7.2-16 16-16l128 0c8.8 0 16 7.2 16 16s-7.2 16-16 16l-128 0c-8.8 0-16-7.2-16-16z"/><path fill="currentColor" d="M64 64C46.3 64 32 78.3 32 96l0 320c0 17.7 14.3 32 32 32l320 0c35.3 0 64 28.7 64 64l0 320c0 35.3-28.7 64-64 64L64 480c-35.3 0-64-28.7-64-64L0 96zm104 64a24 24 0 1 1 48 0 24 24 0 1 1 -48 0zm88 0c0-8.8 7.2-16 16-16l128 0c8.8 0 16 7.2 16 16s-7.2 16-16 16l-128 0c-8.8 0-16-7.2-16-16zm0 96c0-8.8 7.2-16 16-16l128 0c8.8 0 16 7.2 16 16s-7.2 16-16 16l-128 0c-8.8 0-16-7.2-16-16zm0 96c0-8.8 7.2-16 16-16l128 0c8.8 0 16 7.2 16 16s-7.2 16-16 16l-128 0c-8.8 0-16-7.2-16-16zm-64-72a24 24 0 1 1 0-48 24 24 0 1 1 0 48zm-24 72a24 24 0 1 1 48 0 24 24 0 1 1 -48 0z"/></svg>
                </div>
                <h5 class="mb-1">Class Lists</h5>
            </div>
        </a>
    </div>

    <div class="col-6 col-sm-6 col-md-4 col-lg-3">
        <a href="attendance.php" class="text-decoration-none">
            <div class="dashboard-card bg-attendance h-100">
                <div class="card-icon">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640"><path opacity=".4" fill="currentColor" d="M48.5 576C57.3 576 64.5 568.8 64.5 560L64.5 544C64.5 464.5 129 400 208.5 400L304.5 400C384 400 448.5 464.5 448.5 544L448.5 560C448.5 568.8 455.7 576 464.5 576L48.5 576zM352.5 192C352.5 245 309.5 288 256.5 288C203.5 288 160.5 245 160.5 192C160.5 139 203.5 96 256.5 96C309.5 96 352.5 139 352.5 192z"/><path fill="currentColor" d="M304.5 368C401.7 368 480.5 446.8 480.5 544L480.5 560C480.5 568.8 473.3 576 464.5 576C455.7 576 448.5 568.8 448.5 560L448.5 544C448.5 464.5 384 400 304.5 400L208.5 400C129 400 64.5 464.5 64.5 544L64.5 560C64.5 568.8 57.3 576 48.5 576C39.7 576 32.5 568.8 32.5 560L32.5 544C32.5 446.8 111.3 368 208.5 368L304.5 368zM595.6 170.2C600.8 163.1 610.8 161.5 617.9 166.7C625 171.9 626.6 181.9 621.4 189L515.8 334.2C513 338 508.7 340.4 504 340.7C499.3 341 494.7 339.3 491.4 335.9L437 279.6C430.9 273.2 431 263.1 437.4 257C443.8 250.9 453.9 251.1 460 257.4L501.2 300L595.6 170.2zM256.5 320C185.8 320 128.5 262.7 128.5 192C128.5 121.3 185.8 64 256.5 64C327.2 64 384.5 121.3 384.5 192C384.5 262.7 327.2 320 256.5 320zM256.5 96C203.5 96 160.5 139 160.5 192C160.5 245 203.5 288 256.5 288C309.5 288 352.5 245 352.5 192C352.5 139 309.5 96 256.5 96z"/></svg>
                </div>
                <h5 class="mb-1">Roll Call</h5>
            </div>
        </a>
    </div>

    <div class="col-6 col-sm-6 col-md-4 col-lg-3">
        <a href="report-card.php" class="text-decoration-none">
            <div class="dashboard-card bg-quiz h-100">
                <div class="card-icon">
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><path opacity=".4" fill="currentColor" d="M32 256l0 144c0 26.5 21.5 48 48 48l368 0 0-80c0 8.8-7.2 16-16 16s-16-7.2-16-16l0-265.6-54.4 76.2c-6 8.4-15.7 13.4-26 13.4l-6.7 0c-5.8 0-11.5-1.6-16.5-4.6L256 153.6 256 368c0 8.8-7.2 16-16 16s-16-7.2-16-16l0-201.6-54.4 76.2c-6 8.4-15.7 13.4-26 13.4L32 256zm96 16c0-8.8 7.2-16 16-16s16 7.2 16 16l0 96c0 8.8-7.2 16-16 16s-16-7.2-16-16l0-96zm192-64c0-8.8 7.2-16 16-16s16 7.2 16 16l0 160c0 8.8-7.2 16-16 16s-16-7.2-16-16l0-160z"/><path fill="currentColor" d="M16 32c8.8 0 16 7.2 16 16l0 352c0 26.5 21.5 48 48 48l416 0c8.8 0 16 7.2 16 16s-7.2 16-16 16L80 480c-44.2 0-80-35.8-80-80L0 48c0-8.8 7.2-16 16-16zM144 256c8.8 0 16 7.2 16 16l0 96c0 8.8-7.2 16-16 16s-16-7.2-16-16l0-96c0-8.8 7.2-16 16-16zM256 144l0 224c0 8.8-7.2 16-16 16s-16-7.2-16-16l0-224c0-8.8 7.2-16 16-16s16 7.2 16 16zm80 48c8.8 0 16 7.2 16 16l0 160c0 8.8-7.2 16-16 16s-16-7.2-16-16l0-160c0-8.8 7.2-16 16-16zM448 80l0 288c0 8.8-7.2 16-16 16s-16-7.2-16-16l0-288c0-8.8 7.2-16 16-16s16 7.2 16 16z"/></svg>
                </div>
                <h5 class="mb-1">report card</h5>
            </div>
        </a>
    </div>

    <div class="col-6 col-sm-6 col-md-4 col-lg-3">
        <a href="homework.php" class="text-decoration-none">
            <div class="dashboard-card bg-homework h-100">
                <div class="card-icon">
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><path opacity=".4" fill="currentColor" d="M32 80l0 352c0 8.8 7.2 16 16 16l35.2 0c53.8 0 107 10.4 156.8 30.7l0-378.7-21.4-8.9C175.7 73.2 129.7 64 83.2 64L48 64c-8.8 0-16 7.2-16 16zm48 64c0-8.8 7.2-16 16-16l12.2 0c26 0 51.7 6.1 74.9 17.7 7.9 4 11.1 13.6 7.2 21.5s-13.6 11.1-21.5 7.2C150 164.9 129.3 160 108.2 160L96 160c-8.8 0-16-7.2-16-16zm0 96c0-8.8 7.2-16 16-16l12.2 0c26 0 51.7 6.1 74.9 17.7 7.9 4 11.1 13.6 7.2 21.5s-13.6 11.1-21.5 7.2C150 260.9 129.3 256 108.2 256L96 256c-8.8 0-16-7.2-16-16zm0 96c0-8.8 7.2-16 16-16l12.2 0c26 0 51.7 6.1 74.9 17.7 7.9 4 11.1 13.6 7.2 21.5s-13.6 11.1-21.5 7.2C150 356.9 129.3 352 108.2 352L96 352c-8.8 0-16-7.2-16-16zM272 100l0 378.7C321.8 458.4 375 448 428.8 448l35.2 0c8.8 0 16-7.2 16-16l0-352c0-8.8-7.2-16-16-16l-35.2 0c-46.5 0-92.5 9.2-135.4 27.1L272 100zm49.7 67.2c-4-7.9-.7-17.5 7.2-21.5 23.3-11.6 48.9-17.7 74.9-17.7l12.2 0c8.8 0 16 7.2 16 16s-7.2 16-16 16l-12.2 0c-21 0-41.8 4.9-60.6 14.3-7.9 4-17.5 .7-21.5-7.2zm0 96c-4-7.9-.7-17.5 7.2-21.5 23.3-11.6 48.9-17.7 74.9-17.7l12.2 0c8.8 0 16 7.2 16 16s-7.2 16-16 16l-12.2 0c-21 0-41.8 4.9-60.6 14.3-7.9 4-17.5 .7-21.5-7.2zm0 96c-4-7.9-.7-17.5 7.2-21.5 23.3-11.6 48.9-17.7 74.9-17.7l12.2 0c8.8 0 16 7.2 16 16s-7.2 16-16 16l-12.2 0c-21 0-41.8 4.9-60.6 14.3-7.9 4-17.5 .7-21.5-7.2z"/><path fill="currentColor" d="M218.6 91.1l21.4 8.9 0 378.7C190.2 458.4 137 448 83.2 448L48 448c-8.8 0-16-7.2-16-16L32 80c0-8.8 7.2-16 16-16l35.2 0c46.5 0 92.5 9.2 135.4 27.1zM428.8 448c-53.8 0-107 10.4-156.8 30.7l0-378.7 21.4-8.9C336.3 73.2 382.3 64 428.8 64L464 64c8.8 0 16 7.2 16 16l0 352c0 8.8-7.2 16-16 16l-35.2 0zM256 72L230.9 61.5C184.1 42 133.9 32 83.2 32L48 32C21.5 32 0 53.5 0 80L0 432c0 26.5 21.5 48 48 48l35.2 0c50.7 0 100.9 10 147.7 29.5l12.8 5.3c7.9 3.3 16.7 3.3 24.6 0l12.8-5.3c46.8-19.5 97-29.5 147.7-29.5l35.2 0c26.5 0 48-21.5 48-48l0-352c0-26.5-21.5-48-48-48l-35.2 0c-50.7 0-100.9 10-147.7 29.5L256 72zM96 128c-8.8 0-16 7.2-16 16s7.2 16 16 16l12.2 0c21 0 41.8 4.9 60.6 14.3 7.9 4 17.5 .7 21.5-7.2s.7-17.5-7.2-21.5c-23.3-11.6-48.9-17.7-74.9-17.7L96 128zm307.8 0c-26 0-51.7 6.1-74.9 17.7-7.9 4-11.1 13.6-7.2 21.5s13.6 11.1 21.5 7.2c18.8-9.4 39.6-14.3 60.6-14.3l12.2 0c8.8 0 16-7.2 16-16s-7.2-16-16-16l-12.2 0zM96 224c-8.8 0-16 7.2-16 16s7.2 16 16 16l12.2 0c21 0 41.8 4.9 60.6 14.3 7.9 4 17.5 .7 21.5-7.2s.7-17.5-7.2-21.5c-23.3-11.6-48.9-17.7-74.9-17.7L96 224zm307.8 0c-26 0-51.7 6.1-74.9 17.7-7.9 4-11.1 13.6-7.2 21.5s13.6 11.1 21.5 7.2c18.8-9.4 39.6-14.3 60.6-14.3l12.2 0c8.8 0 16-7.2 16-16s-7.2-16-16-16l-12.2 0zM96 320c-8.8 0-16 7.2-16 16s7.2 16 16 16l12.2 0c21 0 41.8 4.9 60.6 14.3 7.9 4 17.5 .7 21.5-7.2s.7-17.5-7.2-21.5c-23.3-11.6-48.9-17.7-74.9-17.7L96 320zm307.8 0c-26 0-51.7 6.1-74.9 17.7-7.9 4-11.1 13.6-7.2 21.5s13.6 11.1 21.5 7.2c18.8-9.4 39.6-14.3 60.6-14.3l12.2 0c8.8 0 16-7.2 16-16s-7.2-16-16-16l-12.2 0z"/></svg>
                </div>
                <h5 class="mb-1">Assignment status</h5>
            </div>
        </a>
    </div>

    <div class="col-6 col-sm-6 col-md-4 col-lg-3">
        <a href="session-plan.php" class="text-decoration-none">
            <div class="dashboard-card bg-session h-100">
                <div class="card-icon">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 560 560"><path opacity=".4" fill="currentColor" d=""/><path fill="currentColor" d="M448.4 96l-384 0c-17.7 0-32 14.3-32 32l0 256c0 17.7 14.3 32 32 32l197.8 0c-4.1 9-7.1 18.5-8.7 28.3l-.6 3.7-188.5 0c-35.3 0-64-28.7-64-64l0-256c0-35.3 28.7-64 64-64l384 0c35.3 0 64 28.7 64 64l0 76.1c-10.8 0-21.6 1.5-32 4.4l0-80.6c0-17.7-14.3-32-32-32zM96.4 304c0-8.8 7.2-16 16-16l160 0c8.8 0 16 7.2 16 16s-7.2 16-16 16l-160 0c-8.8 0-16-7.2-16-16zm16-112l288 0c8.8 0 16 7.2 16 16s-7.2 16-16 16l-288 0c-8.8 0-16-7.2-16-16s7.2-16 16-16zM341.5 440.1c-4.7 4.7-7.8 10.8-8.9 17.4l-8.5 50.8 50.8-8.5c6.6-1.1 12.6-4.2 17.4-8.9l102.2-102.2-50.7-50.7-102.2 102.2zM466.3 315.3l50.7 50.7 20.7-20.7c14-14 14-36.7 0-50.7s-36.7-14-50.7 0l-20.7 20.7zM301 452.2c2.2-13.2 8.4-25.3 17.9-34.7L464.4 272c26.5-26.5 69.5-26.5 96 0s26.5 69.5 0 96L414.9 513.5c-9.4 9.4-21.6 15.7-34.7 17.9l-74.6 12.4c-.8 .1-1.6 .2-2.4 .2-8.2 0-14.8-6.6-14.8-14.8 0-.8 .1-1.6 .2-2.4L301 452.2z"/></svg>
                </div>
                <h5 class="mb-1">Session plan</h5>
            </div>
        </a>
    </div>

    <div class="col-6 col-sm-6 col-md-4 col-lg-3">
        <a href="term-plan.php" class="text-decoration-none">
            <div class="dashboard-card bg-termplan h-100">
                <div class="card-icon">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><path opacity=".4" fill="currentColor" d="M32 96c0-35.3 28.7-64 64-64l96 0c35.3 0 64 28.7 64 64l0 96c0 35.3-28.7 64-64 64l-96 0c-35.3 0-64-28.7-64-64L32 96z"/><path fill="currentColor" d="M256 96c0-35.3 28.7-64 64-64l96 0c35.3 0 64 28.7 64 64l0 96c0 35.3-28.7 64-64 64l-96 0c-35.3 0-64-28.7-64-64l0-96zM32 320c0-35.3 28.7-64 64-64l96 0c35.3 0 64 28.7 64 64l0 96c0 35.3-28.7 64-64 64l-96 0c-35.3 0-64-28.7-64-64l0-96zm224 0c0-35.3 28.7-64 64-64l96 0c35.3 0 64 28.7 64 64l0 96c0 35.3-28.7 64-64 64l-96 0c-35.3 0-64-28.7-64-64l0-96z"/></svg>
                </div>
                <h5 class="mb-1">Term Plan</h5>
            </div>
        </a>
    </div>

    <div class="col-6 col-sm-6 col-md-4 col-lg-3">
        <a href="quiz-books.php" class="text-decoration-none">
            <div class="dashboard-card bg-quizbooks h-100">
                <div class="card-icon">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 448 512"><path opacity=".4" fill="currentColor" d="M96 80l288 0 0 352-288 0c-17.7 0-32-14.3-32-32s14.3-32 32-32l256 0 0-32-256 0c-53 0-96 43-96 96L0 96C0 43 43 0 96 0l288 0 0 80-288 0c-17.7 0-32-14.3-32-32s14.3-32 32-32z"/><path fill="currentColor" d="M96 0C43 0 0 43 0 96L0 416c0 53 43 96 96 96l320 0 0-32-320 0c-35.3 0-64-28.7-64-64 0-17.7 14.3-32 32-32l288 0 0-384L96 0z"/></svg>
                </div>
                <h5 class="mb-1">Coursebooks</h5>
            </div>
        </a>
    </div>

    <div class="col-6 col-sm-6 col-md-4 col-lg-3">
        <a href="error-gravity.php" class="text-decoration-none">
            <div class="dashboard-card bg-error h-100">
                <div class="card-icon">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><path opacity=".4" fill="currentColor" d="M32 256a224 224 0 1 0 448 0 224 224 0 1 0 -448 0zM280 360a24 24 0 1 1 -48 0 24 24 0 1 1 48 0zM232.5 152.9c-.8-13.5 10-24.9 23.5-24.9s24.3 11.4 23.5 24.9l-7.6 136c-.5 8.5-7.5 15.1-16 15.1s-15.5-6.6-16-15.1l-7.6-136z"/><path fill="currentColor" d="M256 512a256 256 0 1 1 0-512 256 256 0 1 1 0 512zm0-480a224 224 0 1 0 0 448 224 224 0 1 0 0-448zm0 352a24 24 0 1 1 0-48 24 24 0 1 1 0 48zm0-256c13.5 0 24.3 11.4 23.5 24.9l-7.6 136c-.5 8.5-7.5 15.1-16 15.1s-15.5-6.6-16-15.1l-7.6-136c-.8-13.5 10-24.9 23.5-24.9z"/></svg>                              
                </div>
                <h5 class="mb-1">Error Gravity</h5>
            </div>
        </a>
    </div>

    <div class="col-6 col-sm-6 col-md-4 col-lg-3">
        <a href="observation-forms.php" class="text-decoration-none">
            <div class="dashboard-card bg-observation h-100">
                <div class="card-icon">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><path opacity=".4" fill="currentColor" d="M32 64l0 384c0 17.7 14.3 32 32 32l256 0c17.7 0 32-14.3 32-32l0-256-96 0c-35.3 0-64-28.7-64-64l0-96-128 0C46.3 32 32 46.3 32 64zM96 272c0-8.8 7.2-16 16-16l160 0c8.8 0 16 7.2 16 16s-7.2 16-16 16l-160 0c-8.8 0-16-7.2-16-16zm0 96c0-8.8 7.2-16 16-16l160 0c8.8 0 16 7.2 16 16s-7.2 16-16 16l-160 0c-8.8 0-16-7.2-16-16z"/><path fill="currentColor" d="M64 32l128 0 0 96c0 35.3 28.7 64 64 64l96 0 0 256c0 17.7-14.3 32-32 32L64 480c-17.7 0-32-14.3-32-32L32 64c0-17.7 14.3-32 32-32zM224 45.3L338.7 160 256 160c-17.7 0-32-14.3-32-32l0-82.7zM64 0C28.7 0 0 28.7 0 64L0 448c0 35.3 28.7 64 64 64l256 0c35.3 0 64-28.7 64-64l0-261.5c0-17-6.7-33.3-18.7-45.3L242.7 18.7C230.7 6.7 214.5 0 197.5 0L64 0zm48 256c-8.8 0-16 7.2-16 16s7.2 16 16 16l160 0c8.8 0 16-7.2 16-16s-7.2-16-16-16l-160 0zm0 96c-8.8 0-16 7.2-16 16s7.2 16 16 16l160 0c8.8 0 16-7.2 16-16s-7.2-16-16-16l-160 0z"/></svg>
                </div>
                <h5 class="mb-1">observation forms</h5>
            </div>
        </a>
    </div>

    <div class="col-6 col-sm-6 col-md-4 col-lg-3">
        <a href="users.php" class="text-decoration-none">
            <div class="dashboard-card bg-users h-100">
                <div class="card-icon">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 576 512"><path opacity=".4" fill="currentColor" d="M16 512c8.8 0 16-7.2 16-16l0-16c0-79.5 64.5-144 144-144l74.9 0c-7.1 20-10.9 41.6-10.9 64 0 41.8 13.4 80.5 36 112L16 512zM320 128a96 96 0 1 1 -192 0 96 96 0 1 1 192 0z"/><path fill="currentColor" d="M320 128a96 96 0 1 0 -192 0 96 96 0 1 0 192 0zM96 128a128 128 0 1 1 256 0 128 128 0 1 1 -256 0zM32 480l0 16c0 8.8-7.2 16-16 16S0 504.8 0 496l0-16c0-97.2 78.8-176 176-176l89.7 0c-5.9 10.1-10.8 20.8-14.8 32L176 336C96.5 336 32 400.5 32 480zm512-80a112 112 0 1 0 -224 0 112 112 0 1 0 224 0zm-256 0a144 144 0 1 1 288 0 144 144 0 1 1 -288 0zm160-64l0 48 48 0c8.8 0 16 7.2 16 16s-7.2 16-16 16l-48 0 0 48c0 8.8-7.2 16-16 16s-16-7.2-16-16l0-48-48 0c-8.8 0-16-7.2-16-16s7.2-16 16-16l48 0 0-48c0-8.8 7.2-16 16-16s16 7.2 16 16z"/></svg>
                </div>
                <h5 class="mb-1">Users' Classification</h5>
            </div>
        </a>
    </div>

    <div class="col-6 col-sm-6 col-md-4 col-lg-3">
        <a href="students-info.php" class="text-decoration-none">
            <div class="dashboard-card bg-message h-100">
                <div class="card-icon">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 576 512"><path opacity=".4" fill="currentColor" d="M64 80c-8.8 0-16 7.2-16 16l0 320c0 8.8 7.2 16 16 16l272 0 0-80c0-26.5 21.5-48 48-48l80 0 0-208c0-8.8-7.2-16-16-16L64 80z"/><path fill="currentColor" d="M48 96c0-26.5 21.5-48 48-48l384 0c26.5 0 48 21.5 48 48l0 237.5c0 17-6.7 33.3-18.7 45.3L413.3 474.7c-12 12-28.3 18.7-45.3 18.7L96 493.4C69.5 493.4 48 471.9 48 445.4L48 96z"/></svg>
                </div>
                <h5 class="mb-1">Students Information</h5>
            </div>
        </a>
    </div>

    <div class="col-6 col-sm-6 col-md-4 col-lg-3">
        <a href="teachers-info.php" class="text-decoration-none">
            <div class="dashboard-card bg-held h-100">
                <div class="card-icon">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640"><path fill="currentColor" d="M224 352c-70.7 0-128 57.3-128 128l0 32c0 17.7 14.3 32 32 32l384 0c17.7 0 32-14.3 32-32l0-32c0-70.7-57.3-128-128-128l-192 0zM320 320a112 112 0 1 0 0-224 112 112 0 1 0 0 224z"/></svg>
                </div>
                <h5 class="mb-1">Teachers Information</h5>
            </div>
        </a>
    </div>

    <div class="col-6 col-sm-6 col-md-4 col-lg-3">
        <a href="terms.php" class="text-decoration-none">
            <div class="dashboard-card bg-terms h-100">
                <div class="card-icon">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><path opacity=".4" fill="currentColor" d="M32 80l0 96c0 8.8 7.2 16 16 16l96 0c8.8 0 16-7.2 16-16l0-96c0-8.8-7.2-16-16-16L48 64c-8.8 0-16 7.2-16 16zm0 256l0 96c0 8.8 7.2 16 16 16l96 0c8.8 0 16-7.2 16-16l0-96c0-8.8-7.2-16-16-16l-96 0c-8.8 0-16 7.2-16 16zM288 80l0 96c0 8.8 7.2 16 16 16l96 0c8.8 0 16-7.2 16-16l0-96c0-8.8-7.2-16-16-16l-96 0c-8.8 0-16 7.2-16 16z"/><path fill="currentColor" d="M48 64c-8.8 0-16 7.2-16 16l0 96c0 8.8 7.2 16 16 16l96 0c8.8 0 16-7.2 16-16l0-96c0-8.8-7.2-16-16-16L48 64zM0 80C0 53.5 21.5 32 48 32l96 0c26.5 0 48 21.5 48 48l0 96c0 26.5-21.5 48-48 48l-96 0c-26.5 0-48-21.5-48-48L0 80zM48 320c-8.8 0-16 7.2-16 16l0 96c0 8.8 7.2 16 16 16l96 0c8.8 0 16-7.2 16-16l0-96c0-8.8-7.2-16-16-16l-96 0zM0 336c0-26.5 21.5-48 48-48l96 0c26.5 0 48 21.5 48 48l0 96c0 26.5-21.5 48-48 48l-96 0c-26.5 0-48-21.5-48-48l0-96zM400 64l-96 0c-8.8 0-16 7.2-16 16l0 96c0 8.8 7.2 16 16 16l96 0c8.8 0 16-7.2 16-16l0-96c0-8.8-7.2-16-16-16zM304 32l96 0c26.5 0 48 21.5 48 48l0 96c0 26.5-21.5 48-48 48l-96 0c-26.5 0-48-21.5-48-48l0-96c0-26.5 21.5-48 48-48zm64 272l0 64 64 0c8.8 0 16 7.2 16 16s-7.2 16-16 16l-64 0 0 64c0 8.8-7.2 16-16 16s-16-7.2-16-16l0-64-64 0c-8.8 0-16-7.2-16-16s7.2-16 16-16l64 0 0-64c0-8.8 7.2-16 16-16s16 7.2 16 16z"/></svg>
                </div>
                <h5 class="mb-1">Terms & Class</h5>
            </div>
        </a>
    </div>

    <div class="col-6 col-sm-6 col-md-4 col-lg-3">
        <a href="mind-lingo.php" class="text-decoration-none">
            <div class="dashboard-card bg-remark h-100">
                <div class="card-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><path fill="currentColor" d="M184 0c30.9 0 58.1 17.3 72 43.5C270 17.3 297.1 0 328 0c48.6 0 88 39.4 88 88c0 10.9-2 21.3-5.7 31c34.9 14.8 59.7 49 59.7 89c0 36.4-20.4 68.3-50.6 84.1c3.1 10.1 4.6 20.8 4.6 31.9c0 57.4-46.6 104-104 104c-11.8 0-23.1-2-33.6-5.6c-17 19.8-41.9 33.6-69.8 33.6c-28.5 0-54-14.3-71-34.6c-10.4 3.3-21.6 5.2-33.3 5.2c-57.4 0-104-46.6-104-104c0-11.6 1.9-22.8 5.4-33.4C18 317 0 286.7 0 252c0-43.1 27.9-79 66.5-92.4C63 148.9 61 137.6 61 126c0-50.8 41.2-92 92-92c10.7 0 21 1.8 30.6 5.1C194.2 19 216 0 241 0z"/></svg>
                </div>
                <h5 class="mb-1">Mind Lingo</h5>
            </div>
        </a>
    </div>

    <div class="col-6 col-sm-6 col-md-4 col-lg-3">
        <a href="profile.php" class="text-decoration-none">
            <div class="dashboard-card bg-profile h-100">
                <div class="card-icon">
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 576 512"><path opacity=".4" fill="currentColor" d="M32 96l0 320c0 17.7 14.3 32 32 32l448 0c17.7 0 32-14.3 32-32l0-320c0-17.7-14.3-32-32-32L64 64C46.3 64 32 78.3 32 96zM80 368c0-44.2 35.8-80 80-80l64 0c44.2 0 80 35.8 80 80 0 8.8-7.2 16-16 16-5.9 0-11.1-3.2-13.9-8l-164.3 0c-2.8 4.8-7.9 8-13.9 8-8.8 0-16-7.2-16-16zM256 192a64 64 0 1 1 -128 0 64 64 0 1 1 128 0zm80-16c0-8.8 7.2-16 16-16l128 0c8.8 0 16 7.2 16 16s-7.2 16-16 16l-128 0c-8.8 0-16-7.2-16-16zm0 96c0-8.8 7.2-16 16-16l128 0c8.8 0 16 7.2 16 16s-7.2 16-16 16l-128 0c-8.8 0-16-7.2-16-16z"/><path fill="currentColor" d="M512 64c17.7 0 32 14.3 32 32l0 320c0 17.7-14.3 32-32 32L64 448c-17.7 0-32-14.3-32-32L32 96c0-17.7 14.3-32 32-32l448 0zM64 32C28.7 32 0 60.7 0 96L0 416c0 35.3 28.7 64 64 64l448 0c35.3 0 64-28.7 64-64l0-320c0-35.3-28.7-64-64-64L64 32zm96 160a32 32 0 1 1 64 0 32 32 0 1 1 -64 0zm96 0a64 64 0 1 0 -128 0 64 64 0 1 0 128 0zM112 368c0-26.5 21.5-48 48-48l64 0c26.5 0 48 21.5 48 48 0 8.8 7.2 16 16 16s16-7.2 16-16c0-44.2-35.8-80-80-80l-64 0c-44.2 0-80 35.8-80 80 0 8.8 7.2 16 16 16s16-7.2 16-16zM352 160c-8.8 0-16 7.2-16 16s7.2 16 16 16l128 0c8.8 0 16-7.2 16-16s-7.2-16-16-16l-128 0zm0 96c-8.8 0-16 7.2-16 16s7.2 16 16 16l128 0c8.8 0 16-7.2 16-16s-7.2-16-16-16l-128 0z"/></svg>
                </div>
                <h5 class="mb-1">profile</h5>
            </div>
        </a>
    </div>
</div>
<div class="dashboard-card messages-widget">

    <div class="card-header">
        <h3>Last Conversations</h3>
        <a href="../messages/index.php">See All</a>
    </div>

    <div class="card-body">

        <?php if(empty($recentConversations)): ?>
            <p> !No Conversation Exists</p>
        <?php else: ?>

            <?php foreach($recentConversations as $conv): ?>

                <div class="message-row">

                    <div class="message-info">
                        <strong>
                            <?php echo htmlspecialchars($conv['participant_name']); ?>
                        </strong>

                        <p class="last-message">
                            <?php echo htmlspecialchars(substr($conv['last_message'],0,40)); ?>
                        </p>
                    </div>

                    <div class="message-meta">
                        <span class="time">
                            <?php echo date("H:i", strtotime($conv['last_message_time'])); ?>
                        </span>

                        <a href="../messages/chat.php?conversation_id=<?php echo $conv['id']; ?>">
                            باز کردن
                        </a>
                    </div>

                </div>

            <?php endforeach; ?>

        <?php endif; ?>

    </div>

</div>

<?php require_once 'layout-end.php'; ?>

<script>
function showStudentsMain(classId, classTitle) {
    document.querySelector('#studentsListModal .modal-title').textContent = 'Participants';
    var box = document.getElementById('students-list-content-main');
    box.innerHTML = '<div class="lg-student-status">I\'m processing... wait...</div>';

    fetch('?action=get_students_list&class_id=' + encodeURIComponent(classId), {
        headers: { 'Accept': 'application/json' },
        credentials: 'same-origin'
    })
    .then(function (response) { return response.json(); })
    .then(function (data) {
        if (window.lgRenderParticipants) {
            window.lgRenderParticipants('students-list-content-main', data, {
                label: 'Class',
                title: classTitle,
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

    bootstrap.Modal.getOrCreateInstance(document.getElementById('studentsListModal')).show();
}
</script>