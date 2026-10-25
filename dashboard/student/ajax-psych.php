<?php
require_once '../../includes/auth.php';
require_once '../../includes/db.php';
require_once '../../includes/psych-tests.php';
if (!function_exists('ghazal_error_report') && is_file(__DIR__ . '/../../includes/error-log.php')) {
    require_once __DIR__ . '/../../includes/error-log.php';
}
require_role('student');

header('Content-Type: application/json; charset=utf-8');

try {
    $raw = file_get_contents('php://input');
    $input = [];
    if (!empty($_SERVER['CONTENT_TYPE']) && strpos((string)$_SERVER['CONTENT_TYPE'], 'application/json') !== false) {
        $input = json_decode($raw, true) ?: [];
    } else {
        $input = $_POST;
    }
    $student_id = (int)($_SESSION['user_id'] ?? 0);
    if ($student_id <= 0) {
        throw new Exception('وارد نشده‌اید.');
    }
    $action = (string)($input['action'] ?? '');
    $test_key = (string)($input['test_key'] ?? '');
    $answers = $input['answers'] ?? [];
    if ($action === 'draft') {
        $id = psych_tests_save_draft($pdo, $student_id, $test_key, is_array($answers) ? $answers : []);
        echo json_encode([
            'success' => true,
            'message' => 'پیش‌نویس ذخیره شد.',
            'id' => $id,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if ($action === 'submit') {
        psych_tests_submit($pdo, $student_id, $test_key, is_array($answers) ? $answers : []);
        echo json_encode([
            'success' => true,
            'message' => 'آزمون ثبت شد.',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    throw new Exception('درخواست نامعتبر.');
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
