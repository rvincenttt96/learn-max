<?php
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
require_once '../../includes/students-info.php';
if (!function_exists('ghazal_error_report') && is_file(__DIR__ . '/../../includes/error-log.php')) {
    require_once __DIR__ . '/../../includes/error-log.php';
}
require_role('supervisor');

header('Content-Type: application/json; charset=utf-8');

try {
    $raw = file_get_contents('php://input');
    $input = [];
    if (!empty($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false) {
        $input = json_decode($raw, true) ?: [];
    } else {
        $input = $_POST;
    }
    $action = (string)($input['action'] ?? '');

    if ($action === 'add') {
        $created = students_info_create(
            $pdo,
            $input['file_number'] ?? '',
            $input['first_name'] ?? '',
            $input['last_name'] ?? '',
            $input['phone'] ?? ''
        );
        $code = $created['file_number'] ?? '';
        echo json_encode([
            'success' => true,
            'message' => 'دانش‌آموز اضافه شد. شماره پرونده: ' . $code,
            'id' => (int)($created['id'] ?? 0),
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $user_id = (int)($input['user_id'] ?? 0);
    if ($user_id <= 0) {
        throw new Exception('شناسه دانش‌آموز نامعتبر است.');
    }

    if ($action === 'delete') {
        students_info_delete($pdo, $user_id);
        echo json_encode(['success' => true, 'message' => 'دانش‌آموز حذف شد.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($action === 'edit') {
        students_info_update(
            $pdo,
            $user_id,
            $input['file_number'] ?? '',
            $input['first_name'] ?? '',
            $input['last_name'] ?? '',
            $input['phone'] ?? ''
        );
        echo json_encode(['success' => true, 'message' => 'اطلاعات دانش‌آموز ذخیره شد.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    throw new Exception('درخواست نامعتبر است.');
} catch (Exception $e) {
    http_response_code($e instanceof PDOException ? 500 : 400);
    if ($e instanceof PDOException && function_exists('ghazal_error_report')) {
        ghazal_error_report('DB-SAVE', 'ثبت اطلاعات دانش‌آموز در دیتابیس ناموفق بود.', ghazal_error_trim($e->getMessage()));
    }
    echo json_encode(['success' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
