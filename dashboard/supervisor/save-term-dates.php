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

    $mode = trim((string)($_POST['mode'] ?? ''));
    if ($mode === 'one') {
        $term_id = (int)($_POST['term_id'] ?? 0);
        if ($term_id <= 0) {
            throw new Exception('ترم انتخاب نشده است.');
        }
        $starts = term_parse_date($_POST['starts_on'] ?? '', 'شروع');
        $ends = term_parse_date($_POST['ends_on'] ?? '', 'پایان');
        if (!$starts) {
            throw new Exception('تاریخ شروع را روی تقویم مشخص کنید.');
        }
        term_assert_date_range($starts, $ends);
        term_ensure_schema($pdo);
        $row = term_load($pdo, $term_id);
        if (!$row || (int)($row['is_deleted'] ?? 0) === 1 || (int)($row['is_archived'] ?? 0) === 1) {
            throw new Exception('ترم پیدا نشد.');
        }
        if ($starts) {
            term_require_future_start_if_teaching($pdo, $starts, $term_id);
        }
        term_set_dates($pdo, $term_id, $starts, $ends);
        echo json_encode([
            'success' => true,
            'message' => 'تاریخ «' . ($row['title'] ?? 'ترم') . '» روی تقویم ذخیره شد.',
        ]);
        exit;
    }

    $current_id = (int)($_POST['current_term_id'] ?? 0);
    $upcoming_id = (int)($_POST['upcoming_term_id'] ?? 0);
    $current_starts = term_parse_date($_POST['current_starts_on'] ?? '', 'شروع ترم جاری');
    $current_ends = term_parse_date($_POST['current_ends_on'] ?? '', 'پایان ترم جاری');
    $new_starts = term_parse_date($_POST['new_starts_on'] ?? '', 'شروع ترم جدید');
    $new_ends = term_parse_date($_POST['new_ends_on'] ?? '', 'پایان ترم جدید');

    term_assert_date_range($current_starts, $current_ends);
    term_assert_date_range($new_starts, $new_ends);
    if ($current_ends && $new_starts && (string)$new_starts <= (string)$current_ends) {
        throw new Exception('شروع ترم جدید باید بعد از پایان ترم جاری باشد.');
    }

    term_ensure_schema($pdo);
    $updated = 0;
    if ($current_id > 0) {
        $row = term_load($pdo, $current_id);
        if (!$row || (int)($row['is_archived'] ?? 0) === 1) {
            throw new Exception('ترم جاری پیدا نشد.');
        }
        term_set_dates($pdo, $current_id, $current_starts, $current_ends);
        $updated++;
    }
    if ($upcoming_id > 0) {
        $row = term_load($pdo, $upcoming_id);
        if (!$row || (int)($row['is_archived'] ?? 0) === 1) {
            throw new Exception('ترم جدید پیدا نشد.');
        }
        if ($new_starts) {
            term_require_future_start_if_teaching($pdo, $new_starts, $upcoming_id);
        }
        term_set_dates($pdo, $upcoming_id, $new_starts, $new_ends);
        $updated++;
    }

    $message = 'تاریخ ترم‌ها ذخیره شد.';
    if ($upcoming_id <= 0 && ($new_starts || $new_ends)) {
        $message = $updated
            ? 'تاریخ ترم جاری ذخیره شد. تاریخ ترم جدید با Confirm یا Import روی ترم تازه‌ساخته می‌نشیند.'
            : 'تاریخ ترم جدید با Confirm یا Import روی ترم تازه‌ساخته می‌نشیند.';
    } elseif ($updated === 0) {
        $message = 'ترم جاری یا ترم جدیدی برای ذخیره تاریخ وجود ندارد.';
    }

    echo json_encode([
        'success' => true,
        'message' => $message,
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
