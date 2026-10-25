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

$result = rollcall_supervisor_set_status($pdo, $class_id, $user_id, $class_date, 'present', null, [
    'send_sms' => false,
]);
if (!empty($result['ok'])) {
    $result['message'] = 'وضعیت با موفقیت به «حاضر» تغییر یافت.';
}
echo json_encode($result, JSON_UNESCAPED_UNICODE);
