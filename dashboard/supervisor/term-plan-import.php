<?php
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
require_once '../../includes/terms.php';
require_once '../../includes/term-plan.php';
require_role('supervisor');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('درخواست نامعتبر است.');
    }
    if (!isset($_FILES['excel_file']) || $_FILES['excel_file']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('فایل اکسل انتخاب نشده است.');
    }
    $ext = strtolower(pathinfo($_FILES['excel_file']['name'], PATHINFO_EXTENSION));
    if ($ext !== 'xlsx') {
        throw new Exception('فقط فایل .xlsx پذیرفته می‌شود.');
    }
    $result = term_plan_import_xlsx($pdo, $_FILES['excel_file']['tmp_name'], (int)$_SESSION['user_id']);
    $msg = 'ورود Term Plan انجام شد. '
        . (int)$result['tasks'] . ' تسک در '
        . (int)$result['classes'] . ' کلاس'
        . ((int)$result['remarks'] > 0 ? '، ' . (int)$result['remarks'] . ' ریمارک' : '')
        . '.';
    if (!empty($result['errors'])) {
        $msg .= "\n\n" . implode("\n", array_slice($result['errors'], 0, 20));
        if (count($result['errors']) > 20) {
            $msg .= "\n… و " . (count($result['errors']) - 20) . ' خطای دیگر';
        }
    }
    header('Location: term-plan.php?success=' . urlencode($msg));
    exit;
} catch (Exception $e) {
    header('Location: term-plan.php?error=' . urlencode($e->getMessage()));
    exit;
}
