<?php
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
require_once '../../includes/students-info.php';
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
    $result = students_info_import($pdo, $_FILES['excel_file']['tmp_name']);
    $msg = 'ورود گروهی انجام شد. '
        . (int)$result['created'] . ' دانش‌آموز جدید، '
        . (int)$result['updated'] . ' به‌روزرسانی'
        . ((int)$result['enrolled'] > 0 ? '، ' . (int)$result['enrolled'] . ' ثبت کلاس' : '')
        . '.';
    if (!empty($result['errors'])) {
        $msg .= "\n\n" . implode("\n", $result['errors']);
    }
    header('Location: students-info.php?success=' . urlencode($msg));
    exit;
} catch (Exception $e) {
    header('Location: students-info.php?error=' . urlencode($e->getMessage()));
    exit;
}
