<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
require_once '../../includes/terms.php';
require_once '../../includes/identity-sync.php';
require_role('supervisor');

// اطمینان از وجود کاربر
if (!isset($_SESSION['user_id'])) {
    header("Location: terms.php?error=" . urlencode('کاربر معتبر نیست.'));
    exit;
}
$user_id = (int)$_SESSION['user_id'];

// Include SimpleXLSX
require_once '../../includes/simplexlsx.php';
use Shuchkin\SimpleXLSX;

/**
 * تبدیل فرمت‌های مختلف زمان به HH:MM ساده
 */
function normalizeScheduleTime($raw_value) {
    if (empty($raw_value)) {
        return '';
    }
    
    // حالت 1: اگر عدد باشه (فرمت Excel)
    if (is_numeric($raw_value)) {
        // تبدیل عدد Excel به ساعت
        $hours = floor(($raw_value - floor($raw_value)) * 24);
        $minutes = floor((($raw_value - floor($raw_value)) * 24 - $hours) * 60);
        return sprintf('%02d:%02d', $hours, $minutes);
    }
    
    // حالت 2: اگر رشته باشه
    $value = trim($raw_value);
    
    // اگر شامل تاریخ باشه (مثل 1970/01/10 16:00:00)
    if (preg_match('/(\d{1,2}:\d{1,2})(?::\d{1,2})?/', $value, $matches)) {
        return $matches[1];
    }
    
    // اگر فقط ساعت باشه (مثل 16:00 یا 16:00:00)
    if (preg_match('/^(\d{1,2}:\d{1,2})(?::\d{1,2})?$/', $value, $matches)) {
        return $matches[1];
    }
    
    // برگرداندن مقدار اصلی اگر تشخیص داده نشد
    return $value;
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('درخواست نامعتبر.');
    }
    
    if (!isset($_FILES['excel_file']) || $_FILES['excel_file']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('فایل Excel انتخاب نشده است.');
    }
    
    $file_tmp = $_FILES['excel_file']['tmp_name'];
    $file_ext = pathinfo($_FILES['excel_file']['name'], PATHINFO_EXTENSION);
    
    if ($file_ext !== 'xlsx') {
        throw new Exception('فقط فایل‌های با پسوند .xlsx مجاز هستند.');
    }
    
    // خواندن فایل Excel
    $xlsx = SimpleXLSX::parse($file_tmp);
    
    if (!$xlsx) {
        throw new Exception('خطا در خواندن فایل Excel: ' . SimpleXLSX::parseError());
    }
    
    $rows = $xlsx->rows();
    
    if (count($rows) < 2) {
        throw new Exception('فایل Excel حداقل باید یک سطر داده داشته باشد.');
    }
    
    // تبدیل تیترها
    $raw_headers = $rows[0];
    $header_map = [
        'term_title' => 'term_title',
        'year' => 'year',
        'class_title' => 'class_title',
        'teacher_name' => 'teacher_name',
        'student_code' => 'student_code',
        'schedule' => 'schedule',
        'schedule_type' => 'schedule_type',
        'class_type' => 'class_type',
        'online_link' => 'online_link'
    ];
    
    $headers = [];
    foreach ($raw_headers as $header) {
        $header_clean = trim($header);
        if (isset($header_map[$header_clean])) {
            $headers[] = $header_map[$header_clean];
        } else {
            $headers[] = strtolower($header_clean);
        }
    }
    
    // بررسی ستون‌های ضروری
    $required_headers = ['term_title', 'year', 'class_title', 'teacher_name', 'student_code'];
    $missing_headers = array_diff($required_headers, $headers);
    if (!empty($missing_headers)) {
        throw new Exception("ستون‌های ضروری یافت نشد: " . implode(', ', $missing_headers));
    }
    
    term_ensure_schema($pdo);
    term_promote_due($pdo);
    $starts_on = term_parse_starts_on($_POST['starts_on'] ?? '');
    $ends_on = term_parse_date($_POST['ends_on'] ?? '', 'پایان');
    term_assert_date_range($starts_on, $ends_on);
    $synced_students = [];
    $touched_classes = [];

    $pdo->beginTransaction();
    
    for ($i = 1; $i < count($rows); $i++) {
        $row = $rows[$i];
        while (count($row) < count($headers)) {
            $row[] = '';
        }
        
        $term_title = trim($row[array_search('term_title', $headers)] ?? '');
        $year = trim($row[array_search('year', $headers)] ?? '');
        $class_title = trim($row[array_search('class_title', $headers)] ?? '');
        $teacher_name = trim($row[array_search('teacher_name', $headers)] ?? '');
        $student_code = trim($row[array_search('student_code', $headers)] ?? '');
        $raw_schedule = $row[array_search('schedule', $headers)] ?? '';
        $schedule_type = trim($row[array_search('schedule_type', $headers)] ?? '');
        $class_type = trim($row[array_search('class_type', $headers)] ?? '');
        $online_link = trim($row[array_search('online_link', $headers)] ?? '');
        
        // پردازش هوشمند زمان
        $schedule = normalizeScheduleTime($raw_schedule);
        
        if (empty($term_title) || empty($year) || empty($class_title) || empty($teacher_name) || empty($student_code)) {
            continue;
        }
        
        if (empty($schedule_type)) $schedule_type = 'odd';
        if (empty($class_type)) $class_type = 'offline';
        
        // 1. پردازش ترم - با تمام ستون‌های الزامی
        $stmt = $pdo->prepare("SELECT id FROM terms WHERE title = ? AND year = ? AND is_deleted = 0");
        $stmt->execute([$term_title, $year]);
        $term = $stmt->fetch();
        
        if ($term) {
            $term_id = $term['id'];
            $loaded = term_load($pdo, $term_id);
            if ($loaded) {
                term_mirror_accounting($pdo, $loaded, 'active');
            }
        } else {
            term_require_future_start_if_teaching($pdo, $starts_on);
            term_close_current_before_new($pdo, $starts_on);
            $stmt = $pdo->prepare("
                INSERT INTO terms (title, year, starts_on, ends_on, created_by, is_deleted, is_archived)
                VALUES (?, ?, ?, ?, ?, 0, 0)
            ");
            $stmt->execute([$term_title, $year, $starts_on, $ends_on, $user_id]);
            $term_id = $pdo->lastInsertId();
            $loaded = term_load($pdo, $term_id);
            if ($loaded) {
                term_mirror_accounting($pdo, $loaded, 'active');
            }
        }
        
        // 2. پردازش معلم
        $stmt = $pdo->prepare("SELECT id FROM users WHERE full_name = ? AND role = 'teacher' AND is_deleted = 0");
        $stmt->execute([$teacher_name]);
        $teacher = $stmt->fetch();
        
        if (!$teacher) {
            throw new Exception("معلم '{$teacher_name}' یافت نشد.");
        }
        $teacher_id = $teacher['id'];
        
        // 3. پردازش کلاس
        $stmt = $pdo->prepare("
            SELECT id FROM classes 
            WHERE title = ? AND term_id = ? AND teacher_id = ? AND is_deleted = 0
        ");
        $stmt->execute([$class_title, $term_id, $teacher_id]);
        $class = $stmt->fetch();
        
        if ($class) {
            $class_id = $class['id'];
        } else {
			$stmt = $pdo->prepare("
				INSERT INTO classes (
					title, term_id, teacher_id, class_type, online_link, 
					schedule, schedule_type, created_by, is_deleted
				) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0)
			");
			$stmt->execute([
				$class_title, 
				$term_id, 
				$teacher_id, 
				$class_type, 
				$online_link, 
				$schedule, 
				$schedule_type,
				$user_id
			]);
            $class_id = $pdo->lastInsertId();
        }
        $touched_classes[(int)$class_id] = $class_title;
        
        // 4. پردازش دانش‌آموز
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? AND role = 'student' AND is_deleted = 0");
        $stmt->execute([$student_code]);
        $student = $stmt->fetch();
        
        if (!$student) {
            throw new Exception("دانش‌آموز با کد '{$student_code}' یافت نشد.");
        }
        $student_id = $student['id'];
        
        // 5. اضافه کردن دانش‌آموز به کلاس
        $stmt = $pdo->prepare("INSERT IGNORE INTO class_students (class_id, user_id) VALUES (?, ?)");
        $stmt->execute([$class_id, $student_id]);
        $synced_students[(int)$student_id] = true;
    }

    $pdo->commit();

    if ($touched_classes) {
        try {
            require_once '../../includes/term-plan.php';
            term_plan_ensure_schema($pdo);
            if (function_exists('term_plan_apply_level_template')) {
                foreach ($touched_classes as $cid => $ctitle) {
                    term_plan_apply_level_template($pdo, (int)$cid, (string)$ctitle, $user_id);
                }
            }
        } catch (Throwable $e) {
            error_log('term_plan apply on import-term: ' . $e->getMessage());
        }
    }

    foreach (array_keys($synced_students) as $sid) {
        try {
            identity_sync_student_classes($pdo, (int)$sid);
        } catch (Exception $e) {
            error_log('import term acc sync user=' . $sid . ': ' . $e->getMessage());
        }
    }
    header("Location: terms.php?success=" . urlencode('ترم و کلاس‌ها با موفقیت import شدند.'));
    exit;
    
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Import Error: " . $e->getMessage());
    header("Location: terms.php?error=" . urlencode($e->getMessage()));
    exit;
}
?>