<?php
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
require_once '../../includes/simplexlsx.php';
if (!function_exists('ghazal_error_report') && is_file(__DIR__ . '/../../includes/error-log.php')) {
    require_once __DIR__ . '/../../includes/error-log.php';
}
use Shuchkin\SimpleXLSX;
require_role('supervisor');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method.');
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
        'نام کامل' => 'full_name',
        'کد کاربری' => 'username',
        'شماره پرونده' => 'username',
        'نقش' => 'role',
        'شماره تماس' => 'phone',
        'رمز عبور' => 'password',
        
        // انگلیسی
        'full_name' => 'full_name',
        'username' => 'username',
        'role' => 'role',
        'phone' => 'phone',
        'password' => 'password'
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
    $required_headers = ['full_name', 'role'];
    $missing_headers = array_diff($required_headers, $headers);
    if (!empty($missing_headers)) {
        throw new Exception("ستون‌های ضروری یافت نشد: " . implode(', ', $missing_headers));
    }
    
    $pdo->beginTransaction();
    $imported_count = 0;
    $errors = [];
    $imported_students = [];
    
    for ($i = 1; $i < count($rows); $i++) {
        $row = $rows[$i];
        while (count($row) < count($headers)) {
            $row[] = '';
        }
        
        $full_name = trim($row[array_search('full_name', $headers)] ?? '');
        $username = trim($row[array_search('username', $headers)] ?? '');
        $role = trim($row[array_search('role', $headers)] ?? '');
        $phone = trim($row[array_search('phone', $headers)] ?? '');
        $password = trim($row[array_search('password', $headers)] ?? '');
        
        // اعتبارسنجی داده‌ها
        if (empty($full_name) || empty($role)) {
            $errors[] = "سطر {$i}: فیلدهای ضروری خالی هستند.";
            continue;
        }

        if (!in_array($role, ['teacher', 'student'])) {
            $errors[] = "سطر {$i}: نقش باید 'teacher' یا 'student' باشد.";
            continue;
        }

        if ($role === 'student') {
            require_once __DIR__ . '/../../includes/identity-sync.php';
            if ($username === '') {
                $username = identity_next_code($pdo);
            }
            if ($password === '') {
                $password = $username;
            }
        }

        if ($username === '' || $password === '') {
            $errors[] = "سطر {$i}: نام کاربری و رمز برای معلم الزامی است.";
            continue;
        }
        
        if (strlen($password) < 4) {
            $errors[] = "سطر {$i}: رمز عبور باید حداقل 4 کاراکتر باشد.";
            continue;
        }
        
        // بررسی تکراری بودن username
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$username]);
        if ($stmt->fetch()) {
            $errors[] = "سطر {$i}: نام کاربری '{$username}' قبلاً ثبت شده است.";
            continue;
        }
        
        // هش کردن رمز عبور
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        
        // INSERT بدون ستون‌های created_at و updated_at
        $stmt = $pdo->prepare("
            INSERT INTO users (
                full_name, username, password, role, phone, is_deleted
            ) VALUES (?, ?, ?, ?, ?, 0)
        ");
        $stmt->execute([$full_name, $username, $password_hash, $role, $phone]);
        $imported_count++;
        if ($role === 'student') {
            $imported_students[] = [
                'code' => $username,
                'full_name' => $full_name,
                'phone' => $phone,
                'password' => $password,
            ];
        }
    }
    
    $pdo->commit();

    if (!empty($imported_students)) {
        try {
            require_once __DIR__ . '/../../includes/identity-sync.php';
            foreach ($imported_students as $student) {
                identity_sync_student($pdo, [
                    'source' => 'panel-import',
                    'code' => $student['code'],
                    'full_name' => $student['full_name'],
                    'phone' => $student['phone'],
                    'password' => $student['password'],
                    'wp_reset_password' => true,
                    'welcome_sms' => true,
                ]);
            }
        } catch (Exception $e) {
            error_log('identity import sync: ' . $e->getMessage());
        }
    }
    
    $message = "امپورت با موفقیت انجام شد. {$imported_count} کاربر اضافه شد.";
    if (!empty($errors)) {
        $message .= "\n\nخطاهای رخ داده:\n" . implode("\n", $errors);
    }
    
    // ریدایرکت به صفحه لیست کاربران با پیام موفقیت
    header("Location: users.php?success=" . urlencode($message));
    exit;
    
} catch (Exception $e) {
    $pdo->rollBack();
    error_log("Import Users Error: " . $e->getMessage());
    if ($e instanceof PDOException && function_exists('ghazal_error_report')) {
        ghazal_error_report('DB-SAVE', 'ایمپورت کاربران در دیتابیس ناموفق بود.', ghazal_error_trim($e->getMessage()));
    }
    
    // ریدایرکت با پیام خطا
    header("Location: import-users.php?error=" . urlencode($e->getMessage()));
    exit;
}
?>