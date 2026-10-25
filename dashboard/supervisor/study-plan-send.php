<?php
require_once '../../includes/auth.php';
require_once '../../includes/db.php';
require_once '../../includes/rollcall.php';
require_role('supervisor');

header('Content-Type: application/json; charset=utf-8');

$input = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($input)) {
    $input = $_POST;
}

$user_id = (int)($input['student_id'] ?? $input['user_id'] ?? 0);
$sent_by = (int)($_SESSION['user_id'] ?? 0);
$result = rollcall_notify_study_plan($pdo, $user_id, $sent_by);
echo json_encode($result, JSON_UNESCAPED_UNICODE);
