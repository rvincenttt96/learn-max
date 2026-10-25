<?php
require_once '../../includes/auth.php';
require_once '../../includes/db.php';
require_once '../../includes/consultant.php';
if (!function_exists('ghazal_error_report') && is_file(__DIR__ . '/../../includes/error-log.php')) {
    require_once __DIR__ . '/../../includes/error-log.php';
}
require_role('consultant');
consultant_ensure_schema($pdo);

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
    $consultant_id = (int)($_SESSION['user_id'] ?? 0);
    if ($consultant_id <= 0) {
        throw new Exception('وارد نشده‌اید.');
    }

    if ($action === 'add') {
        $id = consultant_add_remark(
            $pdo,
            (int)($input['student_id'] ?? 0),
            $consultant_id,
            (string)($input['body'] ?? '')
        );
        echo json_encode([
            'success' => true,
            'message' => 'ریمارک ثبت شد.',
            'id' => $id,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($action === 'delete') {
        $ok = consultant_delete_remark($pdo, (int)($input['remark_id'] ?? 0), $consultant_id);
        if (!$ok) {
            throw new Exception('ریمارک پیدا نشد یا مال شما نیست.');
        }
        echo json_encode([
            'success' => true,
            'message' => 'ریمارک حذف شد.',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    throw new Exception('درخواست نامعتبر.');
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
