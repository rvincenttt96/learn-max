<?php
require_once '../../includes/db.php';
require_once '../../includes/jdate.php';
require_once '../../includes/auth.php';
require_once '../../includes/rollcall.php';

require_role('teacher');

try {
    $class_id = (int)($_POST['class_id'] ?? 0);
    $jalali_date = $_POST['class_date'] ?? '';
    $attendance = $_POST['attendance'] ?? [];

    if (!$class_id || !$jalali_date || empty($attendance)) {
        throw new Exception('اطلاعات ناقص است.');
    }

    $gregorian_date = jdate_to_gregorian($jalali_date);
    if (!$gregorian_date) {
        throw new Exception('تاریخ شمسی نامعتبر است.');
    }

    rollcall_save_and_notify($pdo, $class_id, $gregorian_date, $jalali_date, $attendance);

    header("Location: attendance-students.php?class_id=$class_id&saved=1");
    exit;

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    if ($e instanceof PDOException && function_exists('ghazal_error_report')) {
        ghazal_error_report('DB-SAVE', 'ثبت حضورغیاب در دیتابیس ناموفق بود.', ghazal_error_trim($e->getMessage()));
    }

    header("Location: attendance-students.php?class_id=$class_id&error=" . urlencode($e->getMessage()));
    exit;
}
