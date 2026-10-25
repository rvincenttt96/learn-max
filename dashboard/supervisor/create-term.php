<?php
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
require_once '../../includes/terms.php';
require_role('supervisor');

header('Content-Type: application/json; charset=utf-8');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('درخواست نامعتبر.');
    }

    $term_title = trim($_POST['term_title'] ?? '');
    $year = trim($_POST['year'] ?? '');
    $starts_on = term_parse_starts_on($_POST['starts_on'] ?? '');
    $ends_on = term_parse_date($_POST['ends_on'] ?? '', 'پایان');
    term_assert_date_range($starts_on, $ends_on);

    if ($term_title === '' || $year === '') {
        throw new Exception('لطفاً نام ترم و سال تحصیلی را وارد کنید.');
    }

    term_ensure_schema($pdo);
    term_promote_due($pdo);
    term_require_future_start_if_teaching($pdo, $starts_on);

    $stmt = $pdo->prepare("SELECT id FROM terms WHERE title = ? AND year = ? AND is_deleted = 0");
    $stmt->execute([$term_title, $year]);
    if ($stmt->fetch()) {
        throw new Exception('ترمی با این نام و سال قبلاً ثبت شده است.');
    }

    term_close_current_before_new($pdo, $starts_on);
    $stmt = $pdo->prepare("INSERT INTO terms (title, year, starts_on, ends_on, created_by, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
    $stmt->execute([$term_title, $year, $starts_on, $ends_on, $_SESSION['user_id']]);
    $term = term_load($pdo, (int)$pdo->lastInsertId());
    $acc_note = '';
    if ($term) {
        try {
            term_mirror_accounting($pdo, $term, 'active');
        } catch (Throwable $e) {
            error_log('term acc mirror: ' . $e->getMessage());
            $acc_note = ' ترم در پنل ذخیره شد؛ حسابداری بعداً هم‌نام می‌شود.';
        }
    }

    echo json_encode(['success' => true, 'message' => 'ترم جدید با موفقیت ایجاد شد.' . ($acc_note !== '' ? $acc_note : ' در حسابداری هم ترم هم‌نام ساخته شد.')], JSON_UNESCAPED_UNICODE);
    exit;

} catch (Throwable $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
    exit;
}
