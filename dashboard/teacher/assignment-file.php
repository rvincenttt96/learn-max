<?php
require_once '../../includes/auth.php';
require_once '../../includes/db.php';
require_once '../../includes/term-plan.php';
require_once '../../includes/assignments.php';
require_role('teacher');

$teacher_id = (int)($_SESSION['user_id'] ?? 0);
$kind = trim((string)($_GET['kind'] ?? 'teacher'));
$id = (int)($_GET['id'] ?? 0);

try {
    assignments_ensure_schema($pdo);
} catch (Exception $e) {
    http_response_code(500);
    echo 'فایل در دسترس نیست.';
    exit;
}

if ($kind === 'submission') {
    $sub = assignments_find_submission($pdo, $id);
    if (!$sub) {
        http_response_code(404);
        echo 'فایل پیدا نشد.';
        exit;
    }
    $asg = assignments_find_teacher($pdo, (int)$sub['assignment_id'], $teacher_id);
    if (!$asg) {
        http_response_code(403);
        echo 'اجازه دانلود این فایل را ندارید.';
        exit;
    }
    assignments_send_file($sub['stored_name'], $sub['original_name'], $sub['mime'] ?? '');
}

$asg = assignments_find_teacher($pdo, $id, $teacher_id);
if (!$asg || trim((string)($asg['teacher_stored_name'] ?? '')) === '') {
    http_response_code(404);
    echo 'فایل پیدا نشد.';
    exit;
}
assignments_send_file(
    $asg['teacher_stored_name'],
    $asg['teacher_original_name'] ?? 'file',
    $asg['teacher_mime'] ?? ''
);
