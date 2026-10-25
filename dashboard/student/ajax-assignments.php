<?php
ini_set('display_errors', '0');
require_once '../../includes/auth.php';
require_once '../../includes/db.php';
require_once '../../includes/assignments.php';
require_role('student');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    assignments_json(['success' => false, 'message' => 'درخواست نامعتبر.'], 405);
}

try {
    assignments_ensure_schema($pdo);
} catch (Exception $e) {
    assignments_json(['success' => false, 'message' => 'جدول تکلیف آماده نیست.'], 500);
}

if (assignments_oversized_post()) {
    assignments_json([
        'success' => false,
        'message' => 'حجم فایل از سقف سرور بیشتر است (حداکثر ' . assignments_bytes_label(assignments_upload_limit()) . ').',
    ], 400);
}

$student_id = (int)($_SESSION['user_id'] ?? 0);
$action = trim((string)($_POST['action'] ?? ''));

try {
    if ($action !== 'upload') {
        assignments_json(['success' => false, 'message' => 'عملیات نامعتبر.'], 400);
    }
    $row = assignments_submit($pdo, $student_id, (int)($_POST['id'] ?? 0), $_FILES['file'] ?? []);
    assignments_json([
        'success' => true,
        'message' => 'فایل تحویل شد.',
        'assignment' => assignments_public_student($row),
    ]);
} catch (Exception $e) {
    assignments_json(['success' => false, 'message' => $e->getMessage()], 400);
}
