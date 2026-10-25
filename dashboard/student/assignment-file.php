<?php
require_once '../../includes/auth.php';
require_once '../../includes/db.php';
require_once '../../includes/assignments.php';
require_role('student');

$student_id = (int)($_SESSION['user_id'] ?? 0);
$kind = trim((string)($_GET['kind'] ?? 'teacher'));
$id = (int)($_GET['id'] ?? 0);

try {
    assignments_ensure_schema($pdo);
} catch (Exception $e) {
    http_response_code(500);
    echo 'فایل در دسترس نیست.';
    exit;
}

$asg = assignments_find_student($pdo, $id, $student_id);
if (!$asg) {
    http_response_code(404);
    echo 'فایل پیدا نشد.';
    exit;
}

if ($kind === 'submission') {
    if (trim((string)($asg['sub_stored_name'] ?? '')) === '') {
        http_response_code(404);
        echo 'فایل پیدا نشد.';
        exit;
    }
    $mime = '';
    if (!empty($asg['submission_id'])) {
        $sub = assignments_find_submission($pdo, (int)$asg['submission_id']);
        $mime = $sub['mime'] ?? '';
    }
    assignments_send_file($asg['sub_stored_name'], $asg['sub_original_name'] ?? 'file', $mime);
}

if (trim((string)($asg['teacher_stored_name'] ?? '')) === '') {
    http_response_code(404);
    echo 'فایل پیدا نشد.';
    exit;
}
assignments_send_file(
    $asg['teacher_stored_name'],
    $asg['teacher_original_name'] ?? 'file',
    $asg['teacher_mime'] ?? ''
);
