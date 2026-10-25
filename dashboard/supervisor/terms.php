<?php
$raw_input = file_get_contents('php://input');
$input_data = null;

$archive_mode = $_GET['archive_mode'] ?? 'years'; // years, terms, classes
$archive_year = $_GET['year'] ?? null;
$archive_term_id = $_GET['term_id'] ?? null;

if (!empty($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false) {
    $input_data = json_decode($raw_input, true);
} else {
    $input_data = $_POST;
}

// ================================
// AJAX: دریافت آمار اساتید (خروجی اکسل)
// ================================
if (!empty($_GET['action']) && $_GET['action'] == 'get_teacher_stats') {
    require_once '../../includes/db.php';
    require_once '../../includes/auth.php';
    require_once '../../includes/terms.php';
    require_role('supervisor');
    header('Content-Type: application/json; charset=utf-8');
    
    try {
        $term_id = (int)($_GET['term_id'] ?? 0);
        if (!$term_id) {
            throw new Exception('شناسه ترم نامعتبر است.');
        }
        classes_ensure_schema($pdo);
        
        $stmt = $pdo->prepare("
            SELECT u.full_name as teacher_name, c.class_type
            FROM classes c
            JOIN users u ON c.teacher_id = u.id
            WHERE COALESCE(c.origin_term_id, c.term_id) = ?
              AND IFNULL(c.is_deleted, 0) = 0
        ");
        $stmt->execute([$term_id]);
        $classes_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $stats = [];
        foreach ($classes_data as $c) {
            $teacher = $c['teacher_name'] ?: 'نامشخص';
            $type = $c['class_type'];
            
            if (!isset($stats[$teacher])) {
                $stats[$teacher] = [
                    'online' => 0, 'offline' => 0, 'private' => 0
                ];
            }
            
            if ($type === 'online') {
                $stats[$teacher]['online']++;
            } elseif ($type === 'private') {
                $stats[$teacher]['private']++;
            } else {
                $stats[$teacher]['offline']++;
            }
        }
        
        $exportData = [];
        foreach ($stats as $teacher => $data) {
            $online_sessions = $data['online'] * 13;
            $offline_sessions = $data['offline'] * 13;
            $private_sessions = $data['private'] * 10;
            
            $total_classes = $data['online'] + $data['offline'] + $data['private'];
            $total_sessions = $online_sessions + $offline_sessions + $private_sessions;
            
            $exportData[] = [
                'نام مدرس' => $teacher,
                'مجموع کل کلاس‌ها' => $total_classes,
                'تعداد کلاس حضوری' => $data['offline'],
                'تعداد کلاس آنلاین' => $data['online'],
                'تعداد کلاس خصوصی' => $data['private'],
                'مجموع جلسات ترم' => $total_sessions,
                'جلسات حضوری (×۱۳)' => $offline_sessions,
                'جلسات آنلاین (×۱۳)' => $online_sessions,
                'جلسات خصوصی (×۱۰)' => $private_sessions
            ];
        }
        
        echo json_encode(['success' => true, 'data' => $exportData]);
        exit;
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

// ================================
// AJAX: دریافت لیست دانش‌آموزان
// ================================
if (!empty($_GET['action']) && $_GET['action'] == 'get_students') {
    require_once '../../includes/db.php';
    require_once '../../includes/auth.php';
    require_role('supervisor');
    if (function_exists('session_write_close')) {
        @session_write_close();
    }
    ini_set('display_errors', '0');
    header('Content-Type: application/json; charset=utf-8');

    try {
        $class_id = (int)($_GET['class_id'] ?? 0);
        if (!$class_id) {
            throw new Exception('شناسه کلاس نامعتبر است.');
        }

        $stmt = $pdo->prepare("
            SELECT id, full_name, username AS student_code
            FROM users
            WHERE role = 'student' AND IFNULL(is_deleted, 0) = 0
            ORDER BY full_name
        ");
        $stmt->execute();
        $all_students = $stmt->fetchAll(PDO::FETCH_ASSOC);

        require_once '../../includes/class-roster.php';
        class_roster_restore_if_empty($pdo, $class_id);
        $selected_ids = array_map('strval', class_roster_ids($pdo, $class_id));

        echo json_encode([
            'success' => true,
            'all_students' => $all_students,
            'selected_students' => $selected_ids,
        ], JSON_UNESCAPED_UNICODE | (defined('JSON_INVALID_UTF8_SUBSTITUTE') ? JSON_INVALID_UTF8_SUBSTITUTE : 0));
        exit;
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

// ================================
// ذخیره ویرایش کلاس
// ================================
if (!empty($input_data['action']) && $input_data['action'] == 'save_edit') {
    require_once '../../includes/db.php';
    require_once '../../includes/auth.php';
    require_role('supervisor');
    if (function_exists('session_write_close')) {
        @session_write_close();
    }
    ini_set('display_errors', '0');
    header('Content-Type: application/json; charset=utf-8');
    
    try {
        $class_id = (int)($input_data['class_id'] ?? 0);
        if (!$class_id) {
            throw new Exception('شناسه کلاس نامعتبر است.');
        }
        
        $class_title = trim($input_data['class_title'] ?? '');
        $teacher_id = (int)($input_data['teacher_id'] ?? 0);
        $class_type = $input_data['class_type'] ?? '';
        $online_link = trim($input_data['online_link'] ?? '');
        $schedule = trim($input_data['schedule'] ?? '');
        $schedule_type = $input_data['schedule_type'] ?? '';
        require_once '../../includes/class-roster.php';
        $replace_roster = class_roster_should_replace($input_data);
        $student_ids = class_roster_int_ids($input_data['student_ids'] ?? []);
        
        if (empty($class_title) || empty($teacher_id) || !in_array($class_type, ['online', 'offline', 'private']) || empty($schedule_type)) {
            throw new Exception('لطفاً تمامی فیلدهای ستاره‌دار را پر کنید.');
        }
        
        // اگر خواستی اجبار بودن لینک آنلاین رو برداری، اینجا هم میتونی کامنتش کنی
        if ($class_type == 'online' && empty($online_link)) {
            throw new Exception('لینک کلاس آنلاین الزامی است.');
        }
        
        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare("
            UPDATE classes 
            SET title = ?, class_type = ?, online_link = ?, schedule = ?, schedule_type = ?, teacher_id = ?
            WHERE id = ?
        ");
        $stmt->execute([$class_title, $class_type, $online_link, $schedule, $schedule_type, $teacher_id, $class_id]);

        if ($replace_roster) {
            class_roster_replace($pdo, $class_id, $student_ids);
        }
        
        $pdo->commit();
        $kept = $replace_roster ? count($student_ids) : count(class_roster_ids($pdo, $class_id));
        $message = 'کلاس با موفقیت به‌روزرسانی شد.';
        if (!$replace_roster) {
            $message = 'مشخصات کلاس ذخیره شد. لیست شاگردها دست نخورد (' . $kept . ' نفر).';
        }
        echo json_encode([
            'success' => true,
            'message' => $message,
            'roster_replaced' => $replace_roster,
            'student_count' => $kept,
        ], JSON_UNESCAPED_UNICODE);
        exit;
        
    } catch (Exception $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        echo json_encode(['success' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

// ================================
// حذف ترم
// ================================
if (!empty($input_data['action']) && $input_data['action'] == 'delete_term') {
    require_once '../../includes/db.php';
    require_once '../../includes/auth.php';
    require_role('supervisor');
    header('Content-Type: application/json');
    
    try {
        $term_id = (int)($input_data['term_id'] ?? 0);
        if (!$term_id) {
            throw new Exception('شناسه ترم نامعتبر است.');
        }
        
        $stmt = $pdo->prepare("SELECT id FROM terms WHERE id = ?");
        $stmt->execute([$term_id]);
        if (!$stmt->fetch()) {
            throw new Exception('ترم یافت نشد.');
        }
        
        $stmt = $pdo->prepare("UPDATE terms SET is_deleted = 1 WHERE id = ?");
        $stmt->execute([$term_id]);
        
        $stmt = $pdo->prepare("UPDATE classes SET is_deleted = 1 WHERE term_id = ?");
        $stmt->execute([$term_id]);
        
        echo json_encode(['success' => true, 'message' => 'ترم با موفقیت حذف شد.']);
        exit;
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

// ================================
// حذف کلاس
// ================================
if (!empty($input_data['action']) && $input_data['action'] == 'delete_class') {
    require_once '../../includes/db.php';
    require_once '../../includes/auth.php';
    require_role('supervisor');
    header('Content-Type: application/json');
    
    try {
        $class_id = (int)($input_data['class_id'] ?? 0);
        if (!$class_id) {
            throw new Exception('شناسه کلاس نامعتبر است.');
        }
        
        $stmt = $pdo->prepare("SELECT c.id FROM classes c WHERE c.id = ?");
        $stmt->execute([$class_id]);
        if (!$stmt->fetch()) {
            throw new Exception('کلاس یافت نشد.');
        }
        
        $pdo->beginTransaction();
        
        $related_tables = [
            'student_quizzes',
            'supervisor_scores', 
            'homework_status',
            'attendance',
            'session_plans',
            'term_plan_task_checks',
            'term_plan_tasks',
            'term_plan_session_remarks',
            'class_students'
        ];
        
        foreach ($related_tables as $table) {
            try {
                $stmt = $pdo->prepare("DELETE FROM {$table} WHERE class_id = ?");
                $stmt->execute([$class_id]);
            } catch (PDOException $e) {
                if ($e->getCode() !== '42S02') { 
                    throw $e;
                }
            }
        }
        
        $stmt = $pdo->prepare("DELETE FROM classes WHERE id = ?");
        $stmt->execute([$class_id]);
        
        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'کلاس با موفقیت حذف شد.']);
        exit;
        
    } catch (Exception $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Delete Class Error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

// ================================
// آرشیو کردن یک کلاس (تک)
// ================================
if (!empty($input_data['action']) && $input_data['action'] == 'archive_class') {
    require_once '../../includes/db.php';
    require_once '../../includes/auth.php';
    require_role('supervisor');
    header('Content-Type: application/json');
    
    try {
        $class_id = (int)($input_data['class_id'] ?? 0);
        if (!$class_id) {
            throw new Exception('شناسه کلاس نامعتبر است.');
        }
        
        $stmt = $pdo->prepare("UPDATE classes SET is_archived = 1 WHERE id = ?");
        $stmt->execute([$class_id]);
        
        echo json_encode(['success' => true, 'message' => 'کلاس با موفقیت آرشیو شد.']);
        exit;
        
    } catch (Exception $e) {
        if (strpos($e->getMessage(), 'Unknown column') !== false) {
            echo json_encode(['success' => false, 'message' => 'خطا: ستون is_archived در جدول classes یافت نشد! لطفاً آن را در دیتابیس بسازید.']);
        } else {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }
}

// ================================
// خارج کردن کلاس از آرشیو (Restore)
// ================================
if (!empty($input_data['action']) && $input_data['action'] == 'unarchive_class') {
    require_once '../../includes/db.php';
    require_once '../../includes/auth.php';
    require_role('supervisor');
    header('Content-Type: application/json');
    
    try {
        $class_id = (int)($input_data['class_id'] ?? 0);
        if (!$class_id) {
            throw new Exception('شناسه کلاس نامعتبر است.');
        }
        
        $stmt = $pdo->prepare("UPDATE classes SET is_archived = 0 WHERE id = ?");
        $stmt->execute([$class_id]);
        
        echo json_encode(['success' => true, 'message' => 'کلاس با موفقیت به لیست فعال بازگشت.']);
        exit;
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

if (!empty($input_data['action']) && $input_data['action'] == 'set_forward') {
    require_once '../../includes/db.php';
    require_once '../../includes/auth.php';
    require_once '../../includes/terms.php';
    require_role('supervisor');
    header('Content-Type: application/json');
    classes_ensure_schema($pdo);

    try {
        $class_id = (int)($input_data['class_id'] ?? 0);
        $forward = !empty($input_data['forward']) ? 1 : 0;
        if (!$class_id) {
            throw new Exception('شناسه کلاس نامعتبر است.');
        }
        $stmt = $pdo->prepare("UPDATE classes SET forward_next = ? WHERE id = ? AND IFNULL(is_deleted, 0) = 0");
        $stmt->execute([$forward, $class_id]);
        echo json_encode([
            'success' => true,
            'message' => $forward
                ? 'این کلاس با آرشیو ترم، با همین وضعیت به ترم بعدی می‌رود. شهریه حسابداری همان ترم شروع می‌ماند.'
                : 'انتقال به ترم بعد برای این کلاس برداشته شد.',
            'forward' => $forward,
        ]);
        exit;
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

$title = 'ترم و کلاس‌ها';
require_once 'layout.php';
require_once '../../includes/db.php';
require_once '../../includes/terms.php';
require_once '../../includes/year-program.php';
require_once '../../includes/class-roster.php';
require_role('supervisor');
term_ensure_schema($pdo);
term_promote_due($pdo);
class_roster_restore_empty_teaching($pdo);

$active_tab = $_GET['tab'] ?? 'active';
$active_tab = in_array($active_tab, ['active', 'archived']) ? $active_tab : 'active';

$terms = [];
$terms_with_classes = [];
$years_data = [];
$terms_data = [];
$classes_data = [];
$teaching_term = null;
$upcoming_term = null;
$term_calendar = null;

if ($active_tab == 'archived') {
    if ($archive_mode == 'classes' && $archive_term_id) {
        $stmt = $pdo->prepare("
            SELECT c.id, c.title, c.schedule, c.schedule_type, c.class_type, 
                   c.online_link, c.teacher_id, u.full_name as teacher_name,
                   t.title as term_title, t.year, c.is_archived, t.is_archived as term_archived
            FROM classes c
            JOIN users u ON c.teacher_id = u.id
            JOIN terms t ON c.term_id = t.id
            WHERE c.term_id = ? AND c.is_deleted = 0 
              AND (t.is_archived = 1 OR c.is_archived = 1)
            ORDER BY 
                CASE WHEN c.schedule_type = 'odd' THEN 1 ELSE 2 END,
                c.schedule
        ");
        $stmt->execute([$archive_term_id]);
        $classes_data = $stmt->fetchAll();
        
        $stmt = $pdo->prepare("SELECT title, year, is_archived FROM terms WHERE id = ?");
        $stmt->execute([$archive_term_id]);
        $current_term = $stmt->fetch();
        
    } elseif ($archive_mode == 'terms' && $archive_year) {
        $stmt = $pdo->prepare("
            SELECT DISTINCT t.id, t.title, t.created_at 
            FROM terms t
            LEFT JOIN classes c ON t.id = c.term_id AND c.is_deleted = 0
            WHERE t.year = ? AND t.is_deleted = 0 
              AND (t.is_archived = 1 OR c.is_archived = 1)
            ORDER BY t.created_at DESC
        ");
        $stmt->execute([$archive_year]);
        $terms_data = $stmt->fetchAll();
        
    } else {
        $stmt = $pdo->prepare("
            SELECT DISTINCT t.year 
            FROM terms t
            LEFT JOIN classes c ON t.id = c.term_id AND c.is_deleted = 0
            WHERE t.is_deleted = 0 
              AND (t.is_archived = 1 OR c.is_archived = 1)
            ORDER BY t.year DESC
        ");
        $stmt->execute();
        $years_data = $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
} else {
    $stmt = $pdo->prepare("
        SELECT id, title, year, created_at, starts_on, ends_on, accounting_term_id
        FROM terms
        WHERE is_archived = 0 AND is_deleted = 0
        ORDER BY year DESC, created_at DESC
    ");
    $stmt->execute();
    $terms = $stmt->fetchAll();

    $teachers_stmt = $pdo->prepare("SELECT id, full_name FROM users WHERE role = 'teacher' AND is_deleted = 0");
    $teachers_stmt->execute();
    $teachers = $teachers_stmt->fetchAll();

    $terms_with_classes = [];
    foreach ($terms as $term) {
        // کلاس‌های فعال (آرشیو نشده)
        $stmt = $pdo->prepare("
            SELECT c.id, c.title, c.schedule, c.schedule_type, c.class_type, c.online_link, c.teacher_id,
                   c.forward_next, c.origin_term_id, u.full_name as teacher_name
            FROM classes c
            JOIN users u ON c.teacher_id = u.id
            WHERE c.term_id = ? AND c.is_deleted = 0 AND (c.is_archived = 0 OR c.is_archived IS NULL)
            ORDER BY 
                CASE WHEN c.schedule_type = 'odd' THEN 1 ELSE 2 END,
                c.schedule
        ");
        $stmt->execute([$term['id']]);
        $classes = $stmt->fetchAll();
        
        // کلاس‌های آرشیو شده همین ترم
        $stmt_archived = $pdo->prepare("
            SELECT c.id, c.title, c.schedule, c.schedule_type, c.class_type, c.online_link, c.teacher_id,
                   c.forward_next, c.origin_term_id, u.full_name as teacher_name
            FROM classes c
            JOIN users u ON c.teacher_id = u.id
            WHERE c.term_id = ? AND c.is_deleted = 0 AND c.is_archived = 1
            ORDER BY 
                CASE WHEN c.schedule_type = 'odd' THEN 1 ELSE 2 END,
                c.schedule
        ");
        $stmt_archived->execute([$term['id']]);
        $archived_classes = $stmt_archived->fetchAll();
        
        $terms_with_classes[] = [
            'term' => $term,
            'classes' => $classes,
            'archived_classes' => $archived_classes
        ];
    }
    $teaching_term = term_pick_teaching($terms);
    $upcoming_term = term_pick_upcoming($terms);
    $keep_ids = [];
    if ($teaching_term) {
        $keep_ids[(int)$teaching_term['id']] = true;
    }
    if ($upcoming_term) {
        $keep_ids[(int)$upcoming_term['id']] = true;
    }
    $terms_with_classes = array_values(array_filter($terms_with_classes, function ($item) use ($keep_ids) {
        return isset($keep_ids[(int)$item['term']['id']]);
    }));
    $term_calendar = term_calendar_payload(
        $terms,
        (int)($_GET['jy'] ?? 0)
    );
}
?>

<!-- نمایش پیام‌های موفقیت و خطا -->
<?php if (!empty($_GET['success'])): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <?= htmlspecialchars(urldecode($_GET['success'])) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if (!empty($_GET['error'])): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <?= htmlspecialchars(urldecode($_GET['error'])) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<link href="../../assets/css/supervisor/supervisor-terms.css?v=<?= time() ?>" rel="stylesheet">

<style>
/* استایل‌های سفارشی برای درست کردن رنگ منوهای کشویی و اینپوت‌ها در تم دارک */
.form-select, .form-control {
    background-color: #12121C !important;
    color: #ffffff !important;
    border: 1px solid rgba(127, 29, 29, 0.32) !important;
}
.form-select:focus, .form-control:focus {
    background-color: #12121C !important;
    color: #ffffff !important;
    border-color: #ef4444 !important;
    box-shadow: 0 0 0 0.25rem rgba(220, 38, 38, 0.22) !important;
}
.form-select option {
    background-color: #12121C;
    color: #ffffff;
    padding: 10px;
}
.class-forward {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    margin-inline-start: 6px;
    padding: 5px 11px;
    border-radius: 999px;
    border: 1px solid rgba(125, 211, 252, .42);
    color: #7dd3fc;
    font-size: .8rem;
    font-weight: 600;
    cursor: pointer;
    user-select: none;
    vertical-align: middle;
    background: rgba(14, 30, 48, .55);
    white-space: nowrap;
}
.class-forward input {
    accent-color: #38bdf8;
    width: 15px;
    height: 15px;
    margin: 0;
    cursor: pointer;
}
.class-forward.is-on {
    background: rgba(56, 189, 248, .2);
    border-color: #38bdf8;
    color: #e0f2fe;
}
.class-forward-badge {
    display: inline-block;
    margin-inline-start: 6px;
    padding: 2px 8px;
    border-radius: 999px;
    font-size: .7rem;
    background: rgba(56, 189, 248, .18);
    color: #7dd3fc;
    vertical-align: middle;
}
/* استایل مودال تیره */
.modal-content {
    background-color: rgba(15, 15, 24, 0.94);
    color: #ffffff;
    border: 1px solid rgba(239, 68, 68, 0.38);
}
.modal-header {
    border-bottom: 1px solid rgba(255,255,255,0.1);
}
.modal-footer {
    border-top: 1px solid rgba(255,255,255,0.1);
}
.text-muted {
    color: #a0a0a0 !important;
}
.term-dates-card .term-dates-pane {
    background: rgba(255,255,255,0.04);
    border: 1px solid rgba(255,255,255,0.08);
    border-radius: 14px;
    padding: 16px;
    height: 100%;
}
.term-dates-card .term-dates-pane h6 {
    margin-bottom: 4px;
    font-weight: 700;
}
.term-dates-card .term-dates-title {
    color: #f8fafc;
    font-size: 1.05rem;
}
.edu-cal-card {
    overflow: hidden;
}
.edu-cal-card .card-body {
    min-width: 0;
    max-width: 100%;
}
.edu-cal-card .edu-cal-toolbar {
    gap: 8px;
}
.edu-cal-months,
.edu-cal-track {
    direction: ltr;
}
.edu-cal-scroll-wrap {
    position: relative;
    max-width: 100%;
    min-width: 0;
}
.edu-cal-scroll {
    overflow-x: auto;
    overflow-y: hidden;
    scroll-behavior: smooth;
    border-radius: 12px;
    scrollbar-color: #e94560 rgba(255,255,255,0.08);
    max-width: 100%;
}
.edu-cal-track {
    min-width: 1680px;
}
.edu-cal-months {
    display: flex;
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 12px 12px 0 0;
    overflow: hidden;
    background: rgba(0,0,0,0.2);
}
.edu-cal-month {
    flex: 1 0 140px;
    text-align: center;
    padding: 10px 4px;
    border-inline-end: 1px solid rgba(255,255,255,0.08);
    min-width: 140px;
}
.edu-cal-month:last-child {
    border-inline-end: 0;
}
.edu-cal-month strong {
    display: block;
    font-size: 0.92rem;
}
.edu-cal-month small {
    color: #94a3b8;
}
.edu-cal-rows {
    position: relative;
    min-height: 88px;
    border: 1px solid rgba(255,255,255,0.1);
    border-top: 0;
    border-radius: 0 0 12px 12px;
    background:
        linear-gradient(90deg, rgba(255,255,255,0.04) 0, rgba(255,255,255,0.04) 1px, transparent 1px) 0 0 / calc(100% / 12) 100%,
        rgba(0,0,0,0.12);
}
.edu-cal-nav {
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    z-index: 4;
    width: 36px;
    height: 36px;
    border-radius: 50%;
    border: 1px solid rgba(255,255,255,0.2);
    background: #1a1a2e;
    color: #fff;
    font-size: 18px;
    line-height: 1;
}
.edu-cal-nav-prev { left: 6px; }
.edu-cal-nav-next { right: 6px; }
.edu-cal-today {
    position: absolute;
    top: 0;
    bottom: 0;
    width: 2px;
    background: #e94560;
    z-index: 3;
    pointer-events: none;
}
.edu-cal-today span {
    position: absolute;
    top: 4px;
    left: 6px;
    font-size: 11px;
    color: #e94560;
    white-space: nowrap;
}
.edu-cal-row {
    position: relative;
    height: 44px;
}
.edu-cal-bar {
    position: absolute;
    top: 8px;
    height: 28px;
    border: 0;
    border-radius: 999px;
    color: #0b1220;
    font-size: 12px;
    font-weight: 700;
    padding: 0 12px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    z-index: 2;
    min-width: 28px;
    cursor: pointer;
}
.edu-cal-bar.is-open-end {
    background-image: repeating-linear-gradient(-45deg, rgba(255,255,255,0.18) 0 8px, transparent 8px 16px);
}
.edu-cal-empty {
    padding: 22px 16px;
    text-align: center;
    color: #94a3b8;
}
@media (max-width: 767.98px) {
    .edu-cal-month strong { font-size: 0.78rem; }
    .edu-cal-bar { font-size: 11px; padding: 0 8px; }
    .edu-cal-track { min-width: 1440px; }
    .dashboard-header h1 {
        font-size: 1.35rem;
        line-height: 1.25;
        word-break: break-word;
    }
    .edu-cal-card,
    .edu-cal-scroll-wrap,
    .edu-cal-scroll,
    .term-management-card {
        max-width: 100%;
        min-width: 0;
    }
    .edu-cal-scroll {
        -webkit-overflow-scrolling: touch;
    }
    .edu-cal-card .card-header,
    .term-management-card .card-header {
        flex-direction: column !important;
        align-items: flex-start !important;
        gap: 10px;
    }
    .edu-cal-card .card-header small,
    .edu-cal-card .card-body p {
        white-space: normal;
        max-width: 100%;
    }
    .edu-cal-toolbar {
        flex-direction: column;
        align-items: stretch !important;
    }
    .edu-cal-toolbar .ms-auto {
        margin-inline-start: 0 !important;
    }
    .term-management-card .card-header > div {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        width: 100%;
    }
    .table-responsive-custom {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
        max-width: 100%;
    }
}
</style>

<div class="dashboard-header">
    <h1 class="mb-0">Terms & Class</h1>
</div>

<ul class="nav nav-tabs mb-4">
    <li class="nav-item">
        <a class="nav-link <?= $active_tab == 'active' ? 'active' : '' ?>" 
           href="?tab=active">Ongoing Terms</a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $active_tab == 'archived' ? 'active' : '' ?>" 
           href="?tab=archived">Archived terms</a>
    </li>
</ul>

<?php if ($active_tab == 'active' && $term_calendar): ?>
    <?php
        $cal = $term_calendar;
        $cal_span = $cal['span'];
    ?>
    <div class="card term-management-card edu-cal-card mb-4">
        <div class="card-header bg-primary text-white d-flex flex-wrap justify-content-between align-items-center gap-2">
            <h5 class="mb-0">تقویم آموزشی</h5>
            <small>تایم‌لاین سالانه — برای دیدن پاییز و زمستان به چپ و راست بکشید</small>
        </div>
        <div class="card-body">
            <p class="text-muted mb-3">اسم ترم‌ها انگلیسی است. روی نوار بزنید تا تاریخ شروع و پایان همان ترم عوض شود. برای چیدن چند ترم با هم، فایل اکسل بدهید.</p>
            <div class="d-flex flex-wrap align-items-center edu-cal-toolbar mb-3">
                <div class="btn-group" role="group">
                    <?php foreach ($cal['years'] as $y): ?>
                        <a class="btn btn-sm <?= (int)$cal['jy'] === (int)$y ? 'btn-light text-dark' : 'btn-outline-light' ?>"
                           href="?tab=active&amp;jy=<?= (int)$y ?>"><?= (int)$y ?></a>
                    <?php endforeach; ?>
                </div>
                <span class="text-muted ms-auto">امروز <?= htmlspecialchars($cal['today_label']) ?></span>
            </div>
            <div class="edu-cal-scroll-wrap mb-3">
                <button type="button" class="edu-cal-nav edu-cal-nav-prev" id="eduCalPrev" aria-label="قبلی">‹</button>
                <button type="button" class="edu-cal-nav edu-cal-nav-next" id="eduCalNext" aria-label="بعدی">›</button>
                <div class="edu-cal-scroll" id="eduCalScroll">
                    <div class="edu-cal-track">
                        <div class="edu-cal-months">
                            <?php foreach ($cal_span['months'] as $month): ?>
                                <div class="edu-cal-month">
                                    <strong><?= htmlspecialchars($month['name']) ?></strong>
                                    <small><?= (int)$month['days'] ?> روز</small>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="edu-cal-rows" id="eduCalRows" data-span-start="<?= htmlspecialchars($cal_span['start']) ?>" data-span-end="<?= htmlspecialchars($cal_span['end']) ?>" data-today="<?= htmlspecialchars($cal['today']) ?>">
                            <div class="edu-cal-today d-none" id="eduCalToday"><span>امروز</span></div>
                            <div class="edu-cal-empty d-none" id="eduCalEmpty">ترم تاریخ‌داری در این سال نیست. اکسل تقویم را وارد کنید یا روی نوار ترم جاری تاریخ بگذارید.</div>
                        </div>
                    </div>
                </div>
            </div>
            <form method="POST" action="import-calendar.php" enctype="multipart/form-data" class="row g-2 align-items-end">
                <div class="col-md-6">
                    <label class="form-label">وارد کردن تقویم آموزشی (Excel)</label>
                    <input type="file" name="excel_file" class="form-control" accept=".xlsx" required>
                    <small class="text-muted">ستون‌ها: year ، term ، start ، end — تاریخ شمسی مثل 1405-06-30 یا میلادی. راهنما:
                        <a href="excel/educational-calendar-1405.md" class="link-light">educational-calendar-1405.md</a>
                    </small>
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-outline-light w-100">وارد کردن تقویم</button>
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <a href="excel/educational-calendar-template.xlsx" class="btn btn-outline-secondary w-100">دانلود نمونه اکسل</a>
                </div>
            </form>
            <input type="hidden" id="current_term_id" value="<?= (int)($teaching_term['id'] ?? 0) ?>">
            <input type="hidden" id="upcoming_term_id" value="<?= (int)($upcoming_term['id'] ?? 0) ?>">
            <input type="hidden" id="current_starts_on" value="<?= htmlspecialchars(term_input_date($teaching_term['starts_on'] ?? '')) ?>">
            <input type="hidden" id="current_ends_on" value="<?= htmlspecialchars(term_input_date($teaching_term['ends_on'] ?? '')) ?>">
            <input type="hidden" id="new_starts_on" value="<?= htmlspecialchars(term_input_date($upcoming_term['starts_on'] ?? '')) ?>">
            <input type="hidden" id="new_ends_on" value="<?= htmlspecialchars(term_input_date($upcoming_term['ends_on'] ?? '')) ?>">
        </div>
    </div>
    <div class="modal fade" id="eduCalModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="eduCalModalTitle">ویرایش تاریخ ترم</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="cal_term_id" value="0">
                    <div class="mb-3">
                        <label class="form-label" for="cal_term_title">نام ترم</label>
                        <input type="text" id="cal_term_title" class="form-control" readonly>
                    </div>
                    <div class="row g-2">
                        <div class="col-sm-6">
                            <label class="form-label" for="cal_starts_on">شروع</label>
                            <input type="date" id="cal_starts_on" class="form-control">
                            <small class="text-muted" id="cal_starts_label"></small>
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label" for="cal_ends_on">پایان</label>
                            <input type="date" id="cal_ends_on" class="form-control">
                            <small class="text-muted" id="cal_ends_label"></small>
                        </div>
                    </div>
                    <p class="small text-muted mt-3 mb-0" id="cal_status_hint"></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">انصراف</button>
                    <button type="button" class="btn btn-primary" id="eduCalSaveBtn">ثبت تاریخ</button>
                </div>
            </div>
        </div>
    </div>
    <script>
    window.TERM_CALENDAR = <?= json_encode($cal, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    </script>
    <div class="card term-management-card mb-4">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0">New Term</h5>
        </div>
        <div class="card-body">
            <form method="POST" action="create-term.php" id="term-form">
                <input type="hidden" name="starts_on" id="create-starts-on">
                <input type="hidden" name="ends_on" id="create-ends-on">
                <p class="text-muted small">نام انگلیسی باشد؛ مثلاً Fall 1. تاریخ از تقویم بالا یا از اکسل تقویم می‌آید.</p>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">نام ترم *</label>
                        <input type="text" name="term_title" class="form-control" placeholder="Fall 1" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">سال تحصیلی *</label>
                        <select name="year" class="form-control" required>
                        <?php
                        $current_year = date('Y') - 621; 
                        for ($i = $current_year; $i >= $current_year - 3; $i--): ?>
                            <option value="<?= $i ?>"><?= $i ?></option>
                        <?php endfor; ?>
                        </select>
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100">Confirm</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
    <div class="card term-management-card mb-4">
        <div class="card-header bg-success text-white">
            <h5 class="mb-0">Import Term from Excel</h5>
        </div>
        <div class="card-body">
            <form method="POST" action="import-term.php" enctype="multipart/form-data" id="import-form">
                <input type="hidden" name="starts_on" id="import-starts-on">
                <input type="hidden" name="ends_on" id="import-ends-on">
                <div class="row g-3">
                    <div class="col-md-8">
                        <label class="form-label">Select Excel File (.xlsx) *</label>
                        <input type="file" name="excel_file" class="form-control" accept=".xlsx" required>
                        <small class="text-muted">ستون‌ها: term_title, year, class_title, teacher_name, student_code. تاریخ شروع و پایان همان بازهٔ ترم بعدی روی تقویم بالاست.</small>
                    </div>
                    <div class="col-md-4 d-flex align-items-end">
                        <button type="submit" class="btn btn-success w-100">Import</button>
                    </div>
                </div>
            </form>
            
            <div class="mt-3">
                <a href="excel/sample-term-template.xlsx" class="btn btn-outline-secondary btn-sm">
                    <i class="fas fa-download me-1"></i> Download Sample Template
                </a>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php if ($active_tab == 'archived'): ?>
    
    <?php if ($archive_mode == 'years'): ?>
        <div class="row g-4">
            <?php foreach ($years_data as $year): ?>
            <div class="col-md-4 col-lg-3">
                <div class="card archive-year-card">
                    <div class="card-body text-center">
                        <div class="year-icon mb-3">
                            <span class="display-4">📅</span>
                        </div>
                        <h5 class="card-title"><?= $year ?></h5>
                        <a href="?tab=archived&archive_mode=terms&year=<?= $year ?>" class="btn btn-primary w-100">
                            مشاهده ترم‌ها
                        </a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
            
            <?php if (empty($years_data)): ?>
            <div class="col-12">
                <div class="card">
                    <div class="card-body text-center py-5">
                        <div style="font-size: 3rem; margin-bottom: 20px;">📦</div>
                        <h4>سال تحصیلی بایگانی‌شده‌ای وجود ندارد</h4>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
        
    <?php elseif ($archive_mode == 'terms'): ?>
        <div class="mb-3">
            <a href="?tab=archived" class="btn btn-secondary">&larr; بازگشت به لیست سال‌ها</a>
            <h4 class="mt-3">ترم‌های سال <?= htmlspecialchars($archive_year) ?></h4>
        </div>
        
        <?php if (empty($terms_data)): ?>
            <div class="card">
                <div class="card-body text-center py-5">
                    <h4>ترمی در این سال یافت نشد</h4>
                </div>
            </div>
        <?php else: ?>
            <?php foreach ($terms_data as $term): ?>
            <div class="card term-management-card mb-4">
                <div class="card-header bg-secondary text-white">
                    <h5 class="mb-0">📋 <?= htmlspecialchars($term['title']) ?></h5>
                    <small class="text-white-50">
                        بایگانی شده در <?= date('Y/m/d', strtotime($term['created_at'])) ?>
                    </small>
                </div>
                <div class="card-body">
                    <a href="?tab=archived&archive_mode=classes&term_id=<?= $term['id'] ?>" 
                       class="btn btn-primary">مشاهده کلاس‌ها</a>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
        
    <?php elseif ($archive_mode == 'classes'): ?>
        <div class="mb-3">
            <a href="?tab=archived&archive_mode=terms&year=<?= htmlspecialchars($current_term['year']) ?>" class="btn btn-secondary">&larr; بازگشت به ترم‌ها</a>
            <h4 class="mt-3">کلاس‌های ترم: <?= htmlspecialchars($current_term['title']) ?> (سال <?= htmlspecialchars($current_term['year']) ?>)</h4>
            <?php if (empty($current_term['is_archived'])): ?>
                <span class="badge bg-warning text-dark mt-2">توجه: این ترم هنوز فعال است و فقط کلاس‌های آرشیو شده آن نمایش داده می‌شوند.</span>
            <?php endif; ?>
        </div>
        
        <?php if (empty($classes_data)): ?>
            <div class="card">
                <div class="card-body text-center py-5">
                    <h4>کلاسی موجود نیست</h4>
                </div>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-bordered">
                    <thead class="table-light">
                        <tr>
                            <th>کلاس</th>
                            <th>زمان</th>
                            <th>معلم</th>
                            <th>نوع</th>
                            <th>روز</th>
                            <th>جزئیات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($classes_data as $class): ?>
                        <tr>
                            <td><?= htmlspecialchars($class['title']) ?></td>
                            <td><?= htmlspecialchars($class['schedule'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($class['teacher_name']) ?></td>
                            <td>
                                <?php if ($class['class_type'] == 'online'): ?>
                                    <span class="badge bg-success">Online</span>
                                <?php elseif ($class['class_type'] == 'private'): ?>
                                    <span class="badge" style="background-color: #9b59b6; color: white;">Private</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Inperson</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($class['schedule_type'] == 'odd'): ?>
                                    <span class="badge bg-info">روز فرد</span>
                                <?php else: ?>
                                    <span class="badge bg-warning">روز زوج</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm" role="group">
                                    <a href="class-archive-details.php?class_id=<?= $class['id'] ?>&section=students" 
                                       class="btn btn-outline-primary">دانش‌آموزان</a>
                                    <a href="class-archive-details.php?class_id=<?= $class['id'] ?>&section=attendance" 
                                       class="btn btn-outline-info">حضور</a>
                                    <a href="class-archive-details.php?class_id=<?= $class['id'] ?>&section=report" 
                                       class="btn btn-outline-success">کارنامه</a>
                                    <a href="class-archive-details.php?class_id=<?= $class['id'] ?>&section=session" 
                                       class="btn btn-outline-warning">Session Plan</a>
                                    <a href="class-archive-details.php?class_id=<?= $class['id'] ?>&section=error" 
                                       class="btn btn-outline-danger">Error Gravity</a>
                                       
                                    <?php if (!empty($class['is_archived']) && empty($class['term_archived'])): ?>
                                        <button type="button" class="btn btn-outline-secondary" onclick="unarchiveClass(<?= $class['id'] ?>)">Restore</button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    <?php endif; ?>
    
<?php else: ?>
    <?php if (empty($terms_with_classes)): ?>
        <div class="card term-management-card">
            <div class="card-body text-center py-5">
                <div style="font-size: 3rem; margin-bottom: 20px;">📚</div>
                <h4>ترمی یافت نشد</h4>
                <p class="text-light">ابتدا یک ترم جدید تعریف کنید.</p>
            </div>
        </div>
    <?php else: ?>
        <?php foreach ($terms_with_classes as $item): ?>
        <div class="card term-management-card mb-4">
            <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                📋 <?= htmlspecialchars(term_english_title($item['term']['title'])) ?>
                <small class="text-white-50">(سال <?= htmlspecialchars($item['term']['year']) ?>)</small>
                <?php if (term_row_is_upcoming($item['term'])): ?>
                    <span class="badge bg-warning text-dark ms-2">ثبت‌نام — <?= htmlspecialchars(term_display_date($item['term']['starts_on'] ?? '')) ?> تا <?= htmlspecialchars(term_display_date($item['term']['ends_on'] ?? '')) ?></span>
                <?php else: ?>
                    <span class="badge bg-light text-dark ms-2">در حال تدریس — <?= htmlspecialchars(term_display_date($item['term']['starts_on'] ?? '')) ?> تا <?= htmlspecialchars(term_display_date($item['term']['ends_on'] ?? '')) ?></span>
                <?php endif; ?>
                </h5>
                <div>
                    <!-- دکمه خروجی اساتید -->
                    <button type="button" class="btn btn-sm btn-info me-2 text-dark font-weight-bold" 
                            onclick="exportTeacherStats(<?= $item['term']['id'] ?>, '<?= htmlspecialchars(addslashes($item['term']['title'])) ?>')">
                        <i class="fas fa-file-excel"></i> Teacher Stats
                    </button>
                    
                    <a href="placement.php?term_id=<?= $item['term']['id'] ?>"
                       class="btn btn-sm btn-warning me-2 text-dark">کلاس‌بندی</a>
                    <a href="create-class.php?term_id=<?= $item['term']['id'] ?>" 
                       class="btn btn-sm btn-light me-2">Add Class</a>
                    <button type="button" class="btn btn-sm btn-outline-warning" 
                            onclick="archiveTerm(<?= $item['term']['id'] ?>)">
                        Archived
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-danger" 
                        onclick="deleteTerm(<?= $item['term']['id'] ?>)">
                        Delete
                    </button>
                </div>
            </div>
            <div class="card-body">
                <?php if (empty($item['classes'])): ?>
                    <p class="text-white">No active classes Available</p>
                <?php else: ?>
                    <div class="table-responsive-custom">
                        <table class="table table-bordered">
                            <thead class="table-light">
                                <tr>
                                    <th>Class Name</th>
                                    <th>Time</th>
                                    <th>Instructor</th>
                                    <th>Class Type</th>
                                    <th>Days</th>
                                    <th>Edit</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($item['classes'] as $class):
                                    $is_forward = !empty($class['forward_next']);
                                    $origin_id = (int)($class['origin_term_id'] ?? 0);
                                    $carried = $origin_id > 0 && $origin_id !== (int)$item['term']['id'];
                                ?>
                                <tr>
                                    <td>
                                        <?= htmlspecialchars($class['title']) ?><?= class_live_dot($class + ['starts_on' => $item['term']['starts_on'] ?? '']) ?>
                                        <?php if ($carried): ?>
                                            <span class="class-forward-badge" title="از ترم شروع به این ترم Forward شده؛ شهریه حسابداری همان ترم شروع است">Forwarded</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($class['schedule'] ?? '-') ?></td>
                                    <td><?= htmlspecialchars($class['teacher_name']) ?></td>
                                    <td>
                                        <?php if ($class['class_type'] == 'online'): ?>
                                            <span class="badge bg-success">Online</span>
                                        <?php elseif ($class['class_type'] == 'private'): ?>
                                            <span class="badge" style="background-color: #9b59b6; color: white;">Private</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Inperson</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($class['schedule_type'] == 'odd'): ?>
                                            <span class="badge bg-info">Sun/Tue/Thu</span>
                                        <?php else: ?>
                                            <span class="badge bg-warning">Sat/Mon/Wed</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-danger"
                                                onclick="openEditModal(
                                                    <?= $class['id'] ?>,
                                                    '<?= addslashes($class['title']) ?>',
                                                    '<?= addslashes($class['schedule'] ?? '') ?>',
                                                    <?= $class['teacher_id'] ?>,
                                                    '<?= $class['class_type'] ?>',
                                                    '<?= addslashes($class['online_link'] ?? '') ?>',
                                                    '<?= $class['schedule_type'] ?>'
                                                )">
                                            Edit
                                        </button>
                                        <button type="button" class="btn btn-sm btn-warning text-dark"
                                                onclick="archiveClass(<?= $class['id'] ?>)">
                                            Archive
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-danger"
                                            onclick="deleteClass(<?= $class['id'] ?>)">
                                            Delete
                                        </button>
                                        <label class="class-forward<?= $is_forward ? ' is-on' : '' ?>" title="با آرشیو این ترم، کارت کلاس با همین وضعیت به ترم بعد می‌رود. شهریه حسابداری جابه‌جا نمی‌شود.">
                                            <input type="checkbox"
                                                   <?= $is_forward ? 'checked' : '' ?>
                                                   onchange="setClassForward(this, <?= (int)$class['id'] ?>)">
                                            Forward
                                        </label>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>

                <!-- نمایش کلاس‌های آرشیو شده این ترم -->
                <?php if (!empty($item['archived_classes'])): ?>
                    <div class="mt-4 pt-3 border-top border-secondary">
                        <button class="btn btn-sm btn-outline-light mb-3" type="button" data-bs-toggle="collapse" data-bs-target="#archivedClasses-<?= $item['term']['id'] ?>">
                            📦 Show Archived Classes (<?= count($item['archived_classes']) ?>)
                        </button>
                        
                        <div class="collapse" id="archivedClasses-<?= $item['term']['id'] ?>">
                            <div class="table-responsive-custom">
                                <table class="table table-bordered table-sm table-secondary">
                                    <thead class="table-dark text-white">
                                        <tr>
                                            <th>Class Name</th>
                                            <th>Time</th>
                                            <th>Instructor</th>
                                            <th>Class Type</th>
                                            <th>Days</th>
                                            <th>Restore</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($item['archived_classes'] as $a_class): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($a_class['title']) ?></td>
                                            <td><?= htmlspecialchars($a_class['schedule'] ?? '-') ?></td>
                                            <td><?= htmlspecialchars($a_class['teacher_name']) ?></td>
                                            <td>
                                                <?php if ($a_class['class_type'] == 'online'): ?>
                                                    <span class="badge bg-success">Online</span>
                                                <?php elseif ($a_class['class_type'] == 'private'): ?>
                                                    <span class="badge" style="background-color: #9b59b6; color: white;">Private</span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">Inperson</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($a_class['schedule_type'] == 'odd'): ?>
                                                    <span class="badge bg-info">Sun/Tue/Thu</span>
                                                <?php else: ?>
                                                    <span class="badge bg-warning">Sat/Mon/Wed</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-success" 
                                                        onclick="unarchiveClass(<?= $a_class['id'] ?>)">
                                                    Restore
                                                </button>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
<?php endif; ?>

<!-- Modal ویرایش کلاس -->
<div class="modal fade" id="editClassModal" tabindex="-1" aria-labelledby="editClassModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content lg-edit-class">
            <div class="modal-header">
                <h5 class="modal-title" id="editClassModalLabel">ویرایش کلاس</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="بستن"></button>
            </div>
            <div class="modal-body">
                <form id="edit-class-form">
                    <input type="hidden" id="edit-class-id" name="class_id">

                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label" for="edit-class-title">عنوان کلاس *</label>
                            <input type="text" id="edit-class-title" name="class_title" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="edit-teacher-id">معلم *</label>
                            <select id="edit-teacher-id" name="teacher_id" class="form-select" required>
                                <option value="">انتخاب کنید...</option>
                                <?php foreach (($teachers ?? []) as $teacher): ?>
                                <option value="<?= (int)$teacher['id'] ?>"><?= htmlspecialchars($teacher['full_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="edit-class-type">نوع کلاس *</label>
                            <select id="edit-class-type" name="class_type" class="form-select" required>
                                <option value="online">آنلاین</option>
                                <option value="offline">حضوری</option>
                                <option value="private">خصوصی</option>
                            </select>
                        </div>
                        <div class="col-md-6" id="edit-online-link-field">
                            <label class="form-label" for="edit-online-link">لینک کلاس آنلاین</label>
                            <input type="url" id="edit-online-link" name="online_link" class="form-control"
                                   placeholder="https://meet.google.com/...">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="edit-schedule">زمان کلاس</label>
                            <input type="text" id="edit-schedule" name="schedule" class="form-control"
                                   placeholder="شنبه 16:00">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="edit-schedule-type">روز کلاس *</label>
                            <select id="edit-schedule-type" name="schedule_type" class="form-select" required>
                                <option value="odd">روز فرد</option>
                                <option value="even">روز زوج</option>
                            </select>
                        </div>
                    </div>

                    <div class="lg-student-picker">
                        <div class="d-flex justify-content-between align-items-center gap-2 mb-2">
                            <h5 class="mb-0">انتخاب دانش‌آموزان</h5>
                            <span class="lg-student-meta" id="edit-student-meta">—</span>
                        </div>

                        <div class="selected-students mb-3" id="edit-selected-students">
                            <h6 class="mb-2">دانش‌آموزان انتخاب‌شده: <span id="edit-selected-count">0</span></h6>
                            <div class="d-flex flex-wrap gap-2" id="edit-selected-students-list"></div>
                        </div>

                        <div class="input-group mb-3">
                            <span class="input-group-text">جستجو</span>
                            <input type="text" id="edit-student-search" class="form-control" placeholder="نام یا کد دانش‌آموز..." autocomplete="off">
                        </div>

                        <div class="students-select-container">
                            <div class="students-list" id="edit-students-list">
                                <div class="lg-student-status">در حال بارگذاری...</div>
                            </div>
                        </div>

                        <div id="edit-selected-students-inputs"></div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">لغو</button>
                <button type="button" class="btn btn-danger" id="edit-class-save-btn" onclick="saveClassEdit()">ذخیره تغییرات</button>
            </div>
        </div>
    </div>
</div>

<!-- کتابخانه SheetJS برای خروجی گرفتن اکسل -->
<script src="https://cdn.jsdelivr.net/npm/xlsx/dist/xlsx.full.min.js"></script>

<script>
// === تابع دریافت خروجی آمار اساتید ===
function exportTeacherStats(termId, termTitle) {
    fetch(`?action=get_teacher_stats&term_id=${termId}`)
    .then(response => response.json())
    .then(res => {
        if (res.success) {
            if (res.data.length === 0) {
                alert('هیچ کلاسی برای این ترم ثبت نشده است.');
                return;
            }
            // تبدیل اطلاعات JSON به شیت اکسل
            const worksheet = XLSX.utils.json_to_sheet(res.data);
            const workbook = XLSX.utils.book_new();
            XLSX.utils.book_append_sheet(workbook, worksheet, "Teachers_Stats");
            
            // دانلود فایل
            const date = new Date().toLocaleDateString('fa-IR').replace(/\//g, '-');
            XLSX.writeFile(workbook, `Teachers_Stats_${termTitle}_${date}.xlsx`);
        } else {
            alert('خطا در دریافت اطلاعات: ' + res.message);
        }
    })
    .catch(err => {
        console.error(err);
        alert('خطا در ارتباط با سرور.');
    });
}

// === توابع عمومی ===
function deleteTerm(termId) {
    if (!confirm('آیا مطمئن هستید که می‌خواهید این ترم را حذف کنید؟\nتمام کلاس‌های این ترم نیز حذف خواهند شد.')) {
        return;
    }
    
    fetch('', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({action: 'delete_term', term_id: termId})
    })
    .then(response => response.json())
    .then(data => {
        alert(data.message);
        if (data.success) {
            location.reload();
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('خطا در حذف ترم');
    });
}

function deleteClass(classId) {
    if (!confirm('آیا مطمئن هستید که می‌خواهید این کلاس را حذف کنید؟')) {
        return;
    }
    
    fetch('', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({action: 'delete_class', class_id: classId})
    })
    .then(response => response.json())
    .then(data => {
        alert(data.message);
        if (data.success) {
            location.reload();
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('خطا در حذف کلاس');
    });
}

// === توابع آرشیو ترم ===
function restoreTerm(termId) {
    if (!confirm('آیا مطمئن هستید که می‌خواهید این ترم را از بایگانی بازگردانی کنید؟')) {
        return;
    }
    
    fetch('archive-term.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({term_id: termId, action: 'restore'})
    })
    .then(response => response.json())
    .then(data => {
        alert(data.message);
        if (data.success) {
            location.reload();
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('خطا در بازگردانی ترم');
    });
}

function archiveTerm(termId) {
    if (!confirm('این ترم بایگانی شود؟\nکلاس‌هایی که تیک Forward دارند با همین وضعیت به ترم بعدی می‌روند.\nشهریه حسابداری همان ترم شروع می‌ماند و به ترم بعد منتقل نمی‌شود.')) {
        return;
    }
    
    fetch('archive-term.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({term_id: termId})
    })
    .then(response => response.json())
    .then(data => {
        alert(data.message);
        if (data.success) {
            location.reload();
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('خطا در بایگانی ترم');
    });
}

// === توابع آرشیو تک‌کلاس ===
function setClassForward(input, classId) {
    const on = input.checked ? 1 : 0;
    const label = input.closest('.class-forward');
    fetch('', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({action: 'set_forward', class_id: classId, forward: on})
    })
    .then(response => response.json())
    .then(data => {
        if (!data.success) {
            input.checked = !on;
            alert(data.message || 'خطا در ذخیره Forward');
            return;
        }
        if (label) label.classList.toggle('is-on', !!on);
    })
    .catch(() => {
        input.checked = !on;
        alert('خطا در ذخیره Forward');
    });
}

function archiveClass(classId) {
    if (!confirm('آیا مطمئن هستید که می‌خواهید این کلاس را آرشیو کنید؟\nاین کلاس از لیست کلاس‌های فعال پنهان خواهد شد.')) {
        return;
    }
    
    fetch('', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({action: 'archive_class', class_id: classId})
    })
    .then(response => response.json())
    .then(data => {
        alert(data.message);
        if (data.success) {
            location.reload();
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('خطا در آرشیو کلاس');
    });
}

function unarchiveClass(classId) {
    if (!confirm('آیا مطمئن هستید که می‌خواهید این کلاس را از آرشیو خارج کرده و دوباره فعال کنید؟')) {
        return;
    }
    
    fetch('', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({action: 'unarchive_class', class_id: classId})
    })
    .then(response => response.json())
    .then(data => {
        alert(data.message);
        if (data.success) {
            location.reload();
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('خطا در بازگردانی کلاس');
    });
}

// === توابع مودال ویرایش ===
function openEditModal(classId, title, schedule, teacherId, classType, onlineLink, scheduleType) {
    document.getElementById('edit-class-id').value = classId;
    document.getElementById('edit-class-title').value = title;
    document.getElementById('edit-schedule').value = schedule;
    document.getElementById('edit-teacher-id').value = teacherId;
    document.getElementById('edit-class-type').value = classType;
    document.getElementById('edit-online-link').value = onlineLink;
    document.getElementById('edit-schedule-type').value = scheduleType;
    
    loadStudentsForClass(classId);
    
    const modal = new bootstrap.Modal(document.getElementById('editClassModal'));
    modal.show();
}

// === توابع دانش‌آموزان ===
function lgEsc(value) {
    return String(value == null ? '' : value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

function lgStudentById(studentId) {
    const sid = String(studentId);
    return (window.allStudentsForEdit || []).find(function (student) {
        return String(student.id) === sid;
    });
}

function loadStudentsForClass(classId) {
    window.editStudentsLoaded = false;
    window.editStudentsLoadError = false;
    window.allStudentsForEdit = [];
    window.selectedStudentsForEdit = new Set();
    const list = document.getElementById('edit-students-list');
    const meta = document.getElementById('edit-student-meta');
    list.innerHTML = '<div class="lg-student-status">در حال بارگذاری دانش‌آموزان...</div>';
    document.getElementById('edit-selected-count').textContent = '0';
    document.getElementById('edit-selected-students-list').innerHTML = '';
    document.getElementById('edit-selected-students-inputs').innerHTML = '';
    if (meta) meta.textContent = '—';

    fetch('terms.php?action=get_students&class_id=' + encodeURIComponent(classId), {
        headers: { 'Accept': 'application/json' },
        credentials: 'same-origin'
    })
    .then(function (response) {
        return response.text().then(function (text) {
            var data;
            try {
                data = JSON.parse(text);
            } catch (err) {
                throw new Error('پاسخ نامعتبر از سرور');
            }
            if (!response.ok || !data.success) {
                throw new Error((data && data.message) || 'خطا در بارگذاری دانش‌آموزان');
            }
            return data;
        });
    })
    .then(function (classData) {
        window.allStudentsForEdit = classData.all_students || [];
        window.selectedStudentsForEdit = new Set(
            (classData.selected_students || []).map(function (id) { return String(id); })
        );
        window.editStudentsLoaded = true;
        window.editStudentsLoadError = false;
        renderEditStudentsList();
        renderEditSelectedStudents();
    })
    .catch(function (error) {
        console.error('Error:', error);
        window.editStudentsLoaded = false;
        window.editStudentsLoadError = true;
        list.innerHTML = '<div class="lg-student-status is-error">خطا در بارگذاری دانش‌آموزان — ذخیره فقط مشخصات کلاس را عوض می‌کند، شاگردها پاک نمی‌شوند</div>';
    });
}

function renderEditStudentsList() {
    const searchInput = document.getElementById('edit-student-search');
    const searchTerm = (searchInput ? searchInput.value : '').trim().toLowerCase();
    const students = window.allStudentsForEdit || [];
    const filteredStudents = students.filter(function (student) {
        const name = String(student.full_name || '').toLowerCase();
        const code = String(student.student_code || '').toLowerCase();
        return !searchTerm || name.indexOf(searchTerm) !== -1 || code.indexOf(searchTerm) !== -1;
    });
    const meta = document.getElementById('edit-student-meta');
    if (meta) {
        meta.textContent = filteredStudents.length + ' از ' + students.length;
    }

    if (filteredStudents.length === 0) {
        document.getElementById('edit-students-list').innerHTML =
            '<div class="lg-student-status">' + (students.length ? 'دانش‌آموزی با این جستجو پیدا نشد' : 'دانش‌آموزی ثبت نشده است') + '</div>';
        return;
    }

    let html = '';
    filteredStudents.forEach(function (student, index) {
        const sid = String(student.id);
        const isSelected = window.selectedStudentsForEdit.has(sid);
        html +=
            '<button type="button" class="student-card' + (isSelected ? ' selected' : '') + '"' +
            ' style="animation-delay:' + Math.min(index * 18, 280) + 'ms"' +
            ' onclick="toggleEditStudentSelection(\'' + sid.replace(/'/g, '') + '\')">' +
            '<span class="select-check"></span>' +
            '<span class="student-name">' + lgEsc(student.full_name) + '</span>' +
            '<span class="student-code">' + lgEsc(student.student_code || '—') + '</span>' +
            '</button>';
    });

    document.getElementById('edit-students-list').innerHTML = html;
}

function renderEditSelectedStudents() {
    const selectedList = document.getElementById('edit-selected-students-list');
    const selectedCount = document.getElementById('edit-selected-count');
    const selectedInputs = document.getElementById('edit-selected-students-inputs');

    let tagsHtml = '';
    let inputsHtml = '';

    window.selectedStudentsForEdit.forEach(function (studentId) {
        const student = lgStudentById(studentId);
        if (student) {
            tagsHtml +=
                '<div class="selected-student-tag">' +
                lgEsc(student.full_name) +
                '<span class="remove-student" onclick="removeEditSelectedStudent(\'' + String(studentId).replace(/'/g, '') + '\', event)">×</span>' +
                '</div>';
            inputsHtml += '<input type="hidden" name="student_ids[]" value="' + lgEsc(studentId) + '">';
        }
    });

    selectedList.innerHTML = tagsHtml || '<span class="text-muted">هنوز کسی انتخاب نشده</span>';
    selectedCount.textContent = window.selectedStudentsForEdit.size;
    selectedInputs.innerHTML = inputsHtml;
}

function toggleEditStudentSelection(studentId) {
    if (window.selectedStudentsForEdit.has(studentId)) {
        window.selectedStudentsForEdit.delete(studentId);
    } else {
        window.selectedStudentsForEdit.add(studentId);
    }
    renderEditStudentsList();
    renderEditSelectedStudents();
}

function removeEditSelectedStudent(studentId, event) {
    event.stopPropagation();
    window.selectedStudentsForEdit.delete(studentId);
    renderEditStudentsList();
    renderEditSelectedStudents();
}

function saveClassEdit() {
    const formData = new FormData(document.getElementById('edit-class-form'));
    const rosterReady = !!window.editStudentsLoaded && !window.editStudentsLoadError;
    const studentIds = [];
    if (rosterReady) {
        window.selectedStudentsForEdit.forEach(studentId => {
            studentIds.push(studentId);
        });
    }
    if (rosterReady && studentIds.length === 0) {
        if (!confirm('هیچ شاگردی انتخاب نشده. اگر ذخیره کنید لیست شاگردهای این کلاس خالی می‌ماند. ادامه می‌دهید؟')) {
            return;
        }
    }
    
    const data = {
        action: 'save_edit',
        class_id: formData.get('class_id'),
        class_title: formData.get('class_title'),
        teacher_id: formData.get('teacher_id'),
        class_type: formData.get('class_type'),
        online_link: formData.get('online_link'),
        schedule: formData.get('schedule'),
        schedule_type: formData.get('schedule_type'),
        roster_ready: rosterReady,
        student_ids: studentIds
    };
    
    fetch('', {
        method: 'POST',
        headers: {'Content-Type': 'application/json', 'Accept': 'application/json'},
        credentials: 'same-origin',
        body: JSON.stringify(data)
    })
    .then(function (response) {
        return response.text().then(function (text) {
            var result;
            try {
                result = JSON.parse(text);
            } catch (err) {
                throw new Error('پاسخ نامعتبر از سرور');
            }
            result._httpOk = response.ok;
            return result;
        });
    })
    .then(result => {
        alert(result.message || (result.success ? 'ذخیره شد' : 'خطا در ذخیره تغییرات'));
        if (result.success) {
            bootstrap.Modal.getInstance(document.getElementById('editClassModal')).hide();
            location.reload();
        }
    })
    .catch(error => {
        console.error('Save error:', error);
        alert('خطا در ذخیره تغییرات. اگر روز و ساعت عوض شد، لیست شاگردها را دوباره باز کنید تا خالی نماند.');
    });
}

// === Event Listeners ===
window.allStudentsForEdit = [];
window.selectedStudentsForEdit = new Set();
window.editStudentsLoaded = false;
window.editStudentsLoadError = false;

document.getElementById('edit-student-search').addEventListener('input', function() {
    renderEditStudentsList();
});

// این قسمت برای مخفی شدن لینک در فایل قبلی حذف شد!

document.getElementById('term-form')?.addEventListener('submit', function(e) {
    e.preventDefault();
    syncNewTermDatesIntoForms();
    const formData = new FormData(this);
    fetch('create-term.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        alert(data.message);
        if (data.success) location.reload();
    })
    .catch(error => {
        console.error('Error:', error);
        alert('خطا در ارتباط با سرور.');
    });
});

function syncNewTermDatesIntoForms() {
    var start = (document.getElementById('new_starts_on') || {}).value || '';
    var end = (document.getElementById('new_ends_on') || {}).value || '';
    ['create-starts-on', 'import-starts-on'].forEach(function (id) {
        var el = document.getElementById(id);
        if (el) el.value = start;
    });
    ['create-ends-on', 'import-ends-on'].forEach(function (id) {
        var el = document.getElementById(id);
        if (el) el.value = end;
    });
}

document.getElementById('import-form')?.addEventListener('submit', function () {
    syncNewTermDatesIntoForms();
});

document.getElementById('saveTermDatesBtn')?.addEventListener('click', function () {
    var body = new FormData();
    body.append('current_term_id', (document.getElementById('current_term_id') || {}).value || '0');
    body.append('upcoming_term_id', (document.getElementById('upcoming_term_id') || {}).value || '0');
    body.append('current_starts_on', (document.getElementById('current_starts_on') || {}).value || '');
    body.append('current_ends_on', (document.getElementById('current_ends_on') || {}).value || '');
    body.append('new_starts_on', (document.getElementById('new_starts_on') || {}).value || '');
    body.append('new_ends_on', (document.getElementById('new_ends_on') || {}).value || '');
    fetch('save-term-dates.php', { method: 'POST', body: body })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            alert(data.message || 'ذخیره شد.');
            if (data.success) location.reload();
        })
        .catch(function () {
            alert('خطا در ذخیره تاریخ ترم‌ها.');
        });
});

(function () {
    var data = window.TERM_CALENDAR;
    if (!data || !data.span) return;
    var MONTHS = ['فروردین','اردیبهشت','خرداد','تیر','مرداد','شهریور','مهر','آبان','آذر','دی','بهمن','اسفند'];
    var COLORS = ['#7dd3fc','#86efac','#fcd34d','#f9a8d4','#c4b5fd','#fda4af'];
    var rows = document.getElementById('eduCalRows');
    var emptyEl = document.getElementById('eduCalEmpty');
    var todayEl = document.getElementById('eduCalToday');
    var modalEl = document.getElementById('eduCalModal');
    var scroller = document.getElementById('eduCalScroll');
    if (!rows || !modalEl) return;
    var modal = bootstrap.Modal.getOrCreateInstance(modalEl);

    function gDays() { return [31,28,31,30,31,30,31,31,30,31,30,31]; }
    function jDays() { return [31,31,31,31,31,31,30,30,30,30,30,29]; }
    function div(a, b) { return Math.trunc(a / b); }
    function gregorianToJalali(gy, gm, gd) {
        gy -= 1600; gm -= 1; gd -= 1;
        var g_days = gDays();
        var j_days = jDays();
        var g_day_no = 365 * gy + div(gy + 3, 4) - div(gy + 99, 100) + div(gy + 399, 400);
        for (var i = 0; i < gm; i++) g_day_no += g_days[i];
        if (gm > 1 && ((gy % 4 === 0 && gy % 100 !== 0) || (gy % 400 === 0))) g_day_no++;
        g_day_no += gd;
        var j_day_no = g_day_no - 79;
        var j_np = div(j_day_no, 12053);
        j_day_no %= 12053;
        var jy = 979 + 33 * j_np + 4 * div(j_day_no, 1461);
        j_day_no %= 1461;
        if (j_day_no >= 366) {
            jy += div(j_day_no - 1, 365);
            j_day_no = (j_day_no - 1) % 365;
        }
        i = 0;
        while (i < 11 && j_day_no >= j_days[i]) {
            j_day_no -= j_days[i];
            i++;
        }
        return [jy, i + 1, j_day_no + 1];
    }
    function pretty(ymd) {
        if (!ymd) return '—';
        var p = ymd.split('-');
        if (p.length !== 3) return ymd;
        var j = gregorianToJalali(+p[0], +p[1], +p[2]);
        return j[2] + ' ' + (MONTHS[j[1] - 1] || '') + ' ' + j[0];
    }
    function dayMs(ymd) {
        return Date.parse(ymd + 'T00:00:00');
    }
    function addDays(ymd, n) {
        var d = new Date(ymd + 'T00:00:00');
        d.setDate(d.getDate() + n);
        var m = String(d.getMonth() + 1).padStart(2, '0');
        var day = String(d.getDate()).padStart(2, '0');
        return d.getFullYear() + '-' + m + '-' + day;
    }
    var spanStart = dayMs(data.span.start);
    var spanEnd = dayMs(data.span.end) + 86400000;
    var spanLen = spanEnd - spanStart;

    function pct(ms) {
        return ((ms - spanStart) / spanLen) * 100;
    }

    function paintToday() {
        var t = dayMs(data.today);
        if (t < spanStart || t >= spanEnd) return;
        todayEl.classList.remove('d-none');
        todayEl.style.left = pct(t) + '%';
    }

    function jsonFetch(url, body) {
        return fetch(url, { method: 'POST', body: body, credentials: 'same-origin' })
            .then(function (r) {
                return r.text().then(function (t) {
                    try {
                        return JSON.parse(t);
                    } catch (e) {
                        var plain = t.replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim();
                        throw new Error(plain.slice(0, 240) || ('خطای سرور ' + r.status));
                    }
                });
            });
    }

    function paint() {
        rows.querySelectorAll('.edu-cal-row').forEach(function (n) { n.remove(); });
        var datedInView = 0;
        (data.terms || []).forEach(function (term, idx) {
            if (!term.id || !term.starts_on) return;
            var start = term.starts_on;
            var end = term.ends_on || addDays(start, 45);
            var openEnd = !term.ends_on;
            var a = dayMs(start);
            var b = dayMs(end) + 86400000;
            if (b <= spanStart || a >= spanEnd) return;
            datedInView++;
            var left = Math.max(0, pct(a));
            var right = Math.min(100, pct(b));
            var row = document.createElement('div');
            row.className = 'edu-cal-row';
            var bar = document.createElement('button');
            bar.type = 'button';
            bar.className = 'edu-cal-bar' + (openEnd ? ' is-open-end' : '');
            bar.style.left = left + '%';
            bar.style.width = Math.max(2.2, right - left) + '%';
            bar.style.background = COLORS[idx % COLORS.length];
            var rangeLabel = pretty(start) + (term.ends_on ? ' تا ' + pretty(term.ends_on) : ' تا …');
            bar.textContent = term.title + ' · ' + rangeLabel;
            bar.title = rangeLabel + (term.teaching ? ' — current term' : ' — next term');
            bar.addEventListener('click', function () { openModal(term, term.starts_on, term.ends_on); });
            row.appendChild(bar);
            rows.appendChild(row);
        });
        emptyEl.classList.toggle('d-none', datedInView > 0);
        paintToday();
        rows.style.minHeight = (Math.max(1, datedInView) * 44 + 16) + 'px';
    }

    function setJalaliHints() {
        document.getElementById('cal_starts_label').textContent = pretty(document.getElementById('cal_starts_on').value);
        document.getElementById('cal_ends_label').textContent = pretty(document.getElementById('cal_ends_on').value);
        var start = document.getElementById('cal_starts_on').value;
        var hint = document.getElementById('cal_status_hint');
        if (!start) {
            hint.textContent = '';
            return;
        }
        hint.textContent = start > data.today
            ? 'اگر شروع بعد از امروز باشد این ترم فقط ثبت‌نام است تا آن روز.'
            : 'با این تاریخ، ترم در حال تدریس حساب می‌شود.';
    }

    function openModal(term, start, end) {
        if (!term || !term.id) return;
        document.getElementById('cal_term_id').value = String(term.id);
        document.getElementById('cal_term_title').value = term.title || '';
        document.getElementById('cal_starts_on').value = start || '';
        document.getElementById('cal_ends_on').value = end || '';
        document.getElementById('eduCalModalTitle').textContent = 'ویرایش تاریخ «' + term.title + '»';
        setJalaliHints();
        modal.show();
    }

    document.getElementById('cal_starts_on').addEventListener('change', setJalaliHints);
    document.getElementById('cal_ends_on').addEventListener('change', setJalaliHints);

    document.getElementById('eduCalPrev')?.addEventListener('click', function () {
        if (scroller) scroller.scrollBy({ left: -420, behavior: 'smooth' });
    });
    document.getElementById('eduCalNext')?.addEventListener('click', function () {
        if (scroller) scroller.scrollBy({ left: 420, behavior: 'smooth' });
    });

    document.getElementById('eduCalSaveBtn')?.addEventListener('click', function () {
        var termId = (document.getElementById('cal_term_id').value || '0');
        var start = document.getElementById('cal_starts_on').value || '';
        var end = document.getElementById('cal_ends_on').value || '';
        if (termId === '0') {
            alert('ترم انتخاب نشده است.');
            return;
        }
        if (!start) {
            alert('تاریخ شروع را مشخص کنید.');
            return;
        }
        var body = new FormData();
        body.append('mode', 'one');
        body.append('term_id', termId);
        body.append('starts_on', start);
        body.append('ends_on', end);
        jsonFetch('save-term-dates.php', body)
            .then(function (res) {
                alert(res.message || 'ذخیره شد.');
                if (res.success) location.reload();
            })
            .catch(function (err) {
                alert(err.message || 'خطا در ذخیره تاریخ.');
            });
    });

    paint();
    function scrollCalendarToToday() {
        if (!scroller || !todayEl || todayEl.classList.contains('d-none')) {
            return;
        }
        var width = scroller.scrollWidth;
        var leftPct = parseFloat(todayEl.style.left) || 0;
        var target = (width * leftPct / 100) - (scroller.clientWidth / 2);
        if (target < 0) {
            target = 0;
        }
        scroller.scrollLeft = target;
        if (Math.abs(scroller.scrollLeft) < 20 && target > 40) {
            var max = Math.max(0, width - scroller.clientWidth);
            scroller.scrollLeft = -(Math.max(0, max - target));
        }
        var wrap = document.querySelector('.main-content-wrapper');
        if (wrap) {
            wrap.scrollTop = 0;
        }
        window.scrollTo(0, 0);
    }
    scrollCalendarToToday();
    window.setTimeout(scrollCalendarToToday, 50);
    window.setTimeout(scrollCalendarToToday, 300);
})();
</script>

<?php require_once 'layout-end.php'; ?>
