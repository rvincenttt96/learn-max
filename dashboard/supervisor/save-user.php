<?php
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
if (!function_exists('ghazal_error_report') && is_file(__DIR__ . '/../../includes/error-log.php')) {
    require_once __DIR__ . '/../../includes/error-log.php';
}
require_role('supervisor');

header('Content-Type: application/json; charset=utf-8');

$ghazal_user_roles = ['teacher', 'student', 'supervisor', 'consultant'];

function ghazal_ensure_consultant_role(PDO $pdo, $role) {
    if ($role !== 'consultant') {
        return;
    }
    require_once __DIR__ . '/../../includes/consultant.php';
    consultant_ensure_schema($pdo);
}

function ghazal_sync_student_identity(PDO $pdo, array $opts) {
    try {
        require_once __DIR__ . '/../../includes/identity-sync.php';
        return identity_sync_student($pdo, $opts);
    } catch (Exception $e) {
        error_log('identity sync: ' . $e->getMessage());
        return null;
    }
}

try {
    // تعریف متغیر input_data
    $raw_input = file_get_contents('php://input');
    $input_data = null;
    
    if (!empty($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false) {
        $input_data = json_decode($raw_input, true);
    } else {
        $input_data = $_POST;
    }
    
    // ۱. حذف کاربر
    if (!empty($input_data['action']) && $input_data['action'] === 'delete_user') {
        $user_id = (int)($input_data['user_id'] ?? 0);
        $user_type = $input_data['user_type'] ?? '';
        
        // سوپروایزر به لیست مجاز اضافه شد
        if (!$user_id || !in_array($user_type, $ghazal_user_roles, true)) {
            throw new Exception('اطلاعات نامعتبر است.');
        }
        
        // بررسی وجود کاربر
        $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ? AND role = ? AND is_deleted = 0");
        $stmt->execute([$user_id, $user_type]);
        if (!$stmt->fetch()) {
            throw new Exception('کاربر یافت نشد.');
        }
        
        // حذف نرم (توصیه شده)
        $stmt = $pdo->prepare("UPDATE users SET is_deleted = 1 WHERE id = ?");
        $stmt->execute([$user_id]);
        
        echo json_encode(['success' => true, 'message' => 'کاربر با موفقیت حذف شد.']);
        exit;
    }
    
    // ۲. تغییر رمز عبور
    if (isset($_POST['user_id']) && isset($_POST['new_password'])) {
        $user_id = (int)$_POST['user_id'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        if (empty($new_password) || $new_password !== $confirm_password) {
            throw new Exception('رمز عبور معتبر نیست.');
        }
        
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        
        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->execute([$hashed_password, $user_id]);

        $u = $pdo->prepare("SELECT username, full_name, phone, role FROM users WHERE id = ?");
        $u->execute([$user_id]);
        $row = $u->fetch();
        if ($row && ($row['role'] ?? '') === 'student') {
            ghazal_sync_student_identity($pdo, [
                'source' => 'panel-password',
                'code' => $row['username'],
                'full_name' => $row['full_name'],
                'phone' => $row['phone'],
                'password' => $new_password,
                'wp_reset_password' => true,
            ]);
        }
        
        echo json_encode(['success' => true, 'message' => 'رمز عبور با موفقیت تغییر کرد.']);
        exit;
    }
    
    // ۳. ویرایش کاربر
    if (isset($_POST['user_id']) && !isset($_POST['new_password'])) {
        $user_id = (int)$_POST['user_id'];
        $username = trim($_POST['username'] ?? '');
        $role = $_POST['role'] ?? '';
        $full_name = trim($_POST['full_name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        
        // سوپروایزر به شرط اضافه شد
        if (empty($username) || empty($role) || empty($full_name) || !in_array($role, $ghazal_user_roles, true)) {
            throw new Exception('لطفاً تمامی فیلدهای ستاره‌دار را پر کنید یا نقش نامعتبر است.');
        }
        ghazal_ensure_consultant_role($pdo, $role);
        
        // بررسی تکراری بودن نام کاربری (به جز خود کاربر فعلی)
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
        $stmt->execute([$username, $user_id]);
        if ($stmt->fetch()) {
            throw new Exception('این نام کاربری قبلاً استفاده شده است.');
        }

        $old = $pdo->prepare("SELECT username, role FROM users WHERE id = ?");
        $old->execute([$user_id]);
        $old_row = $old->fetch();
        $previous_code = $old_row['username'] ?? '';
        
        $avatar_url = null;
        if (!empty($_FILES['avatar']['name'])) {
            $upload_dir = '../../uploads/avatars/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            $file = $_FILES['avatar'];
            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $new_filename = 'user_' . time() . '.' . $ext;
            $target_path = $upload_dir . $new_filename;
            if (move_uploaded_file($file['tmp_name'], $target_path)) {
                $avatar_url = '/uploads/avatars/' . $new_filename;
            }
        }
        
        // به‌روزرسانی کاربر همراه با نقش
        if ($avatar_url !== null) {
            $stmt = $pdo->prepare("
                UPDATE users 
                SET username = ?, full_name = ?, phone = ?, role = ?, avatar = ?
                WHERE id = ?
            ");
            $stmt->execute([$username, $full_name, $phone, $role, $avatar_url, $user_id]);
        } else {
            $stmt = $pdo->prepare("
                UPDATE users 
                SET username = ?, full_name = ?, phone = ?, role = ?
                WHERE id = ?
            ");
            $stmt->execute([$username, $full_name, $phone, $role, $user_id]);
        }

        if ($role === 'student') {
            ghazal_sync_student_identity($pdo, [
                'source' => 'panel-edit',
                'code' => $username,
                'previous_code' => $previous_code,
                'full_name' => $full_name,
                'phone' => $phone,
            ]);
        }
        
        echo json_encode(['success' => true, 'message' => 'اطلاعات کاربر با موفقیت به‌روزرسانی شد.']);
        exit;
    }
    
    // ۴. ثبت کاربر جدید
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $username = trim($_POST['username'] ?? '');
        $role = $_POST['role'] ?? '';
        $full_name = trim($_POST['full_name'] ?? '');
        $password = $_POST['password'] ?? '';
        $phone = trim($_POST['phone'] ?? '');

        if (empty($role) || empty($full_name) || !in_array($role, $ghazal_user_roles, true)) {
            throw new Exception('لطفاً تمامی فیلدهای ستاره‌دار را پر کنید یا نقش نامعتبر است.');
        }
        ghazal_ensure_consultant_role($pdo, $role);

        if ($role === 'student') {
            require_once __DIR__ . '/../../includes/identity-sync.php';
            if ($username === '') {
                $username = identity_next_code($pdo);
            }
        }
        if ($password === '' && $username !== '') {
            $password = $username;
        }

        if (empty($username) || empty($password)) {
            throw new Exception('لطفاً تمامی فیلدهای ستاره‌دار را پر کنید.');
        }
        
        // سوپروایزر به شرط اضافه شد
        if (!in_array($role, $ghazal_user_roles, true)) {
            throw new Exception('نقش نامعتبر است.');
        }
        
        // بررسی تکراری بودن نام کاربری و وضعیت حذف
        $stmt = $pdo->prepare("SELECT id, is_deleted FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $existing_user = $stmt->fetch();
        
        if ($existing_user && $existing_user['is_deleted'] == 0) {
            throw new Exception('این نام کاربری قبلاً استفاده شده است.');
        }
        
        $avatar_url = null;
        if (!empty($_FILES['avatar']['name'])) {
            $upload_dir = '../../uploads/avatars/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            $file = $_FILES['avatar'];
            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $new_filename = 'user_' . time() . '.' . $ext;
            $target_path = $upload_dir . $new_filename;
            if (move_uploaded_file($file['tmp_name'], $target_path)) {
                $avatar_url = '/uploads/avatars/' . $new_filename;
            }
        }
        
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        if ($existing_user && $existing_user['is_deleted'] == 1) {
            // کاربر قبلاً حذف شده بوده -> بازیابی و آپدیت اطلاعاتش
            if ($avatar_url !== null) {
                $stmt = $pdo->prepare("
                    UPDATE users 
                    SET password = ?, full_name = ?, phone = ?, avatar = ?, role = ?, is_deleted = 0, created_at = NOW() 
                    WHERE id = ?
                ");
                $stmt->execute([$hashed_password, $full_name, $phone, $avatar_url, $role, $existing_user['id']]);
            } else {
                $stmt = $pdo->prepare("
                    UPDATE users 
                    SET password = ?, full_name = ?, phone = ?, role = ?, is_deleted = 0, created_at = NOW() 
                    WHERE id = ?
                ");
                $stmt->execute([$hashed_password, $full_name, $phone, $role, $existing_user['id']]);
            }
            if ($role === 'student') {
                ghazal_sync_student_identity($pdo, [
                    'source' => 'panel-restore',
                    'code' => $username,
                    'full_name' => $full_name,
                    'phone' => $phone,
                    'password' => $password,
                    'wp_reset_password' => true,
                    'welcome_sms' => true,
                ]);
            }
            echo json_encode(['success' => true, 'message' => 'این کاربر قبلاً حذف شده بود؛ حساب او بازیابی و اطلاعاتش با موفقیت به‌روزرسانی شد.']);
            exit;
        } else {
            // کاربر کاملاً جدید است
            $stmt = $pdo->prepare("
                INSERT INTO users (username, password, full_name, phone, avatar, role, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$username, $hashed_password, $full_name, $phone, $avatar_url, $role]);

            if ($role === 'student') {
                ghazal_sync_student_identity($pdo, [
                    'source' => 'panel-create',
                    'code' => $username,
                    'full_name' => $full_name,
                    'phone' => $phone,
                    'password' => $password,
                    'wp_reset_password' => true,
                    'welcome_sms' => true,
                ]);
            }
            
            echo json_encode(['success' => true, 'message' => 'کاربر با موفقیت ثبت شد.']);
            exit;
        }
    }
    
    throw new Exception('درخواست نامعتبر.');
    
} catch (PDOException $e) {
    if (function_exists('ghazal_error_report')) {
        ghazal_error_report('DB-SAVE', 'ثبت کاربر در دیتابیس پنل ناموفق بود.', ghazal_error_trim($e->getMessage()));
    }
    echo json_encode(['success' => false, 'message' => 'خطای دیتابیس: ' . $e->getMessage()]);
    exit;
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit;
}
?>