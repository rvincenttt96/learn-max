<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/digits.php';
require_once __DIR__ . '/../../includes/german.php';
require_role('german_supervisor');
german_ensure_schema($pdo);

header('Content-Type: application/json; charset=utf-8');

function german_json_ok($extra = []) {
    echo json_encode(array_merge(['success' => true], $extra), JSON_UNESCAPED_UNICODE);
    exit;
}

function german_json_fail($message) {
    echo json_encode(['success' => false, 'message' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$me = (int)($_SESSION['user_id'] ?? 0);

try {
    if ($action === 'save_class') {
        $class_id = (int)($_POST['class_id'] ?? 0);
        $title = trim((string)($_POST['title'] ?? ''));
        $level = strtoupper(trim((string)($_POST['level'] ?? '')));
        $kind = trim((string)($_POST['kind'] ?? ''));
        $session_count = (int)($_POST['session_count'] ?? 0);
        $manual_fee = (int)preg_replace('/[^\d]/', '', ghazal_normalize_digits($_POST['fee_per_session'] ?? '0'));
        $teacher_id = (int)($_POST['teacher_id'] ?? 0);
        $schedule = trim((string)($_POST['schedule'] ?? ''));
        $schedule_type = trim((string)($_POST['schedule_type'] ?? ''));
        $student_ids = $_POST['student_ids'] ?? [];
        if (!is_array($student_ids)) {
            $student_ids = $student_ids === '' ? [] : explode(',', (string)$student_ids);
        }
        $student_ids = array_values(array_unique(array_filter(array_map('intval', $student_ids))));

        if (!in_array($level, german_levels(), true)) {
            throw new Exception('لول نامعتبر است.');
        }
        if (!isset(german_kinds()[$kind])) {
            throw new Exception('نوع کلاس نامعتبر است.');
        }
        if ($session_count < 1 || $session_count > 40) {
            throw new Exception('تعداد جلسات باید بین ۱ و ۴۰ باشد.');
        }
        if ($teacher_id) {
            german_assert_person($pdo, $teacher_id, 'german_teacher');
        }
        if ($title === '') {
            $title = $level . ' — ' . german_kind_label($kind);
        }
        if ($schedule_type !== '' && !in_array($schedule_type, ['odd', 'even'], true)) {
            $schedule_type = '';
        }
        $fees = german_fees_map($pdo);
        if ($kind === 'semi_private' && $manual_fee <= 0) {
            throw new Exception('برای نیمه خصوصی شهریه هر جلسه را وارد کنید.');
        }
        $fee = german_fee_per_session($fees, $level, $kind, $manual_fee);

        if ($class_id) {
            german_class_row($pdo, $class_id);
            $pdo->prepare("
                UPDATE german_classes
                SET title = ?, level = ?, kind = ?, session_count = ?, fee_per_session = ?,
                    teacher_id = ?, schedule = ?, schedule_type = ?
                WHERE id = ? AND is_deleted = 0
            ")->execute([$title, $level, $kind, $session_count, $fee, $teacher_id, $schedule, $schedule_type, $class_id]);
        } else {
            $pdo->prepare("
                INSERT INTO german_classes
                    (title, level, kind, session_count, fee_per_session, teacher_id, schedule, schedule_type, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ")->execute([$title, $level, $kind, $session_count, $fee, $teacher_id, $schedule, $schedule_type, $me]);
            $class_id = (int)$pdo->lastInsertId();
        }

        $pdo->prepare("DELETE FROM german_class_students WHERE class_id = ?")->execute([$class_id]);
        foreach ($student_ids as $sid) {
            german_enroll($pdo, $class_id, $sid);
        }
        german_refresh_enrollment_fees($pdo, $class_id);
        german_json_ok(['class_id' => $class_id, 'message' => 'کلاس ذخیره شد.']);
    }

    if ($action === 'delete_class') {
        $class_id = (int)($_POST['class_id'] ?? 0);
        german_class_row($pdo, $class_id);
        $pdo->prepare("UPDATE german_classes SET is_deleted = 1 WHERE id = ?")->execute([$class_id]);
        german_json_ok(['message' => 'کلاس حذف شد.']);
    }

    if ($action === 'add_person') {
        $role = $_POST['role'] ?? '';
        $full_name = $_POST['full_name'] ?? '';
        $person = german_create_person(
            $pdo,
            $role,
            $full_name,
            '',
            '',
            ghazal_normalize_digits($_POST['phone'] ?? '')
        );
        german_json_ok([
            'id' => (int)$person['id'],
            'person' => $person,
            'message' => 'ثبت شد. یوزر ' . $person['username'] . ' — رمز ' . $person['password'],
        ]);
    }

    if ($action === 'reset_password') {
        $user_id = (int)($_POST['user_id'] ?? 0);
        $role = $_POST['role'] ?? '';
        $password = ghazal_normalize_digits($_POST['password'] ?? '');
        if (!in_array($role, ['german_teacher', 'german_student'], true)) {
            throw new Exception('نقش نامعتبر است.');
        }
        german_assert_person($pdo, $user_id, $role);
        if ($password === '') {
            throw new Exception('رمز جدید را وارد کنید.');
        }
        $pdo->prepare("UPDATE users SET password = ? WHERE id = ? AND role = ?")
            ->execute([password_hash($password, PASSWORD_DEFAULT), $user_id, $role]);
        german_json_ok(['message' => 'رمز به‌روز شد.']);
    }

    if ($action === 'save_sessions') {
        $class_id = (int)($_POST['class_id'] ?? 0);
        german_class_row($pdo, $class_id);
        $status = json_decode($_POST['session_status'] ?? '{}', true);
        $dates = json_decode($_POST['session_dates'] ?? '{}', true);
        if (!is_array($status)) {
            $status = [];
        }
        if (!is_array($dates)) {
            $dates = [];
        }
        $pdo->prepare("UPDATE german_classes SET session_status_json = ?, session_dates_json = ? WHERE id = ?")
            ->execute([json_encode($status, JSON_UNESCAPED_UNICODE), json_encode($dates, JSON_UNESCAPED_UNICODE), $class_id]);
        german_json_ok();
    }

    if ($action === 'add_payment') {
        $class_id = (int)($_POST['class_id'] ?? 0);
        $student_id = (int)($_POST['student_id'] ?? 0);
        $amount = (int)preg_replace('/[^\d]/', '', ghazal_normalize_digits($_POST['amount'] ?? '0'));
        $note = trim((string)($_POST['note'] ?? ''));
        german_class_row($pdo, $class_id);
        german_assert_person($pdo, $student_id, 'german_student');
        if ($amount <= 0) {
            throw new Exception('مبلغ پرداخت را وارد کنید.');
        }
        $en = $pdo->prepare("SELECT id FROM german_class_students WHERE class_id = ? AND student_id = ?");
        $en->execute([$class_id, $student_id]);
        if (!$en->fetch()) {
            throw new Exception('این زبان‌آموز در این کلاس نیست.');
        }
        $receipt = ['', ''];
        if (!empty($_FILES['receipt']['tmp_name'])) {
            $receipt = german_save_receipt($_FILES['receipt']);
        }
        $pdo->prepare("
            INSERT INTO german_payments (class_id, student_id, amount, receipt_path, receipt_name, note, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ")->execute([$class_id, $student_id, $amount, $receipt[0], $receipt[1], $note, $me]);
        german_json_ok(['message' => 'پرداخت ثبت شد.']);
    }

    if ($action === 'delete_payment') {
        $pay_id = (int)($_POST['payment_id'] ?? 0);
        $stmt = $pdo->prepare("
            SELECT p.id FROM german_payments p
            JOIN german_classes c ON c.id = p.class_id AND c.is_deleted = 0
            WHERE p.id = ?
        ");
        $stmt->execute([$pay_id]);
        if (!$stmt->fetch()) {
            throw new Exception('پرداخت یافت نشد.');
        }
        $pdo->prepare("DELETE FROM german_payments WHERE id = ?")->execute([$pay_id]);
        german_json_ok(['message' => 'پرداخت حذف شد.']);
    }

    if ($action === 'save_fees') {
        $levels = german_levels();
        $upd = $pdo->prepare("UPDATE german_fees SET group_fee = ?, private_fee = ? WHERE level = ?");
        foreach ($levels as $level) {
            $g = (int)preg_replace('/[^\d]/', '', ghazal_normalize_digits($_POST['group_' . $level] ?? '0'));
            $p = (int)preg_replace('/[^\d]/', '', ghazal_normalize_digits($_POST['private_' . $level] ?? '0'));
            $upd->execute([$g, $p, $level]);
        }
        german_json_ok(['message' => 'شهریه‌ها ذخیره شد.']);
    }

    if ($action === 'delete_person') {
        $user_id = (int)($_POST['user_id'] ?? 0);
        $role = $_POST['role'] ?? '';
        if (!in_array($role, ['german_teacher', 'german_student'], true)) {
            throw new Exception('نقش نامعتبر است.');
        }
        german_assert_person($pdo, $user_id, $role);
        $pdo->prepare("UPDATE users SET is_deleted = 1 WHERE id = ? AND role = ?")->execute([$user_id, $role]);
        german_json_ok(['message' => 'حذف شد.']);
    }

    throw new Exception('درخواست نامعتبر.');
} catch (Exception $e) {
    german_json_fail($e->getMessage());
}
