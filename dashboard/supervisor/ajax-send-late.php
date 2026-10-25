<?php
require_once '../../includes/auth.php';
require_once '../../includes/db.php';
require_once '../../includes/jdate.php';
require_once '../../includes/rollcall.php';
require_role('supervisor');

header('Content-Type: application/json; charset=utf-8');

$input = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($input)) {
    $input = $_POST;
}

$class_id = (int)($input['class_id'] ?? 0);
$user_id = (int)($input['student_id'] ?? $input['user_id'] ?? 0);
$class_date = trim((string)($input['date'] ?? ''));

if (!$class_id || !$user_id || $class_date === '' || $class_date === '0000-00-00') {
    echo json_encode(['ok' => false, 'success' => false, 'message' => 'داده نامعتبر است.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$timestamp = strtotime($class_date);
if ($timestamp === false || $timestamp <= 0) {
    echo json_encode(['ok' => false, 'success' => false, 'message' => 'تاریخ نامعتبر است.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$jalali_date = function_exists('jdate') ? jdate('Y/m/d', $timestamp) : date('Y/m/d', $timestamp);
$result = rollcall_notify_late($pdo, $class_id, 0, $user_id, $jalali_date);
$result['success'] = !empty($result['ok']);
echo json_encode($result, JSON_UNESCAPED_UNICODE);
