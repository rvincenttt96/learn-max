<?php
require_once '../../includes/auth.php';
require_once '../../includes/db.php';
require_once '../../includes/jdate.php';
require_once '../../includes/rollcall.php';
require_role('supervisor');

header('Content-Type: application/json; charset=utf-8');

try {
    $class_id = (int)($_GET['class_id'] ?? 0);
    $class_date = trim((string)($_GET['date'] ?? ''));

    if (!$class_id || $class_date === '' || $class_date === '0000-00-00') {
        throw new Exception('تاریخ نامعتبر است.');
    }

    $timestamp = strtotime($class_date);
    if ($timestamp === false || $timestamp <= 0) {
        throw new Exception('تاریخ نامعتبر است.');
    }

    $jalali_date = function_exists('jdate') ? jdate('Y/m/d', $timestamp) : date('Y/m/d', $timestamp);

    $stmt = $pdo->prepare("
        SELECT c.title, u.full_name AS teacher_name
        FROM classes c
        JOIN users u ON c.teacher_id = u.id
        WHERE c.id = ?
    ");
    $stmt->execute([$class_id]);
    $class_info = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$class_info) {
        throw new Exception('کلاس یافت نشد.');
    }

    rollcall_ensure_reason_column($pdo);

    $stmt = $pdo->prepare("
        SELECT
            u.id,
            u.full_name,
            u.username AS student_code,
            a.status AS raw_status,
            a.is_present,
            a.reason
        FROM users u
        JOIN class_students cs ON u.id = cs.user_id
        LEFT JOIN attendance a
            ON u.id = a.user_id
            AND a.class_id = ?
            AND a.class_date = ?
        WHERE
            cs.class_id = ?
            AND u.role = 'student'
            AND u.is_deleted = 0
        ORDER BY u.full_name
    ");
    $stmt->execute([$class_id, $class_date, $class_id]);

    $students = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $has_row = array_key_exists('raw_status', $row) && $row['raw_status'] !== null;
        $status = $has_row
            ? rollcall_normalize_status($row['raw_status'] ?? '', $row['is_present'] ?? null)
            : 'absent';
        $students[] = [
            'id' => (int)$row['id'],
            'full_name' => (string)($row['full_name'] ?? ''),
            'student_code' => (string)($row['student_code'] ?? ''),
            'status' => $status,
            'reason' => (string)($row['reason'] ?? ''),
            'recorded' => $has_row,
        ];
    }

    $stats = rollcall_session_stats($pdo, $class_id, $class_date);

    echo json_encode([
        'success' => true,
        'class_info' => $class_info,
        'jalali_date' => $jalali_date,
        'students' => $students,
        'present' => $stats['present'],
        'total' => $stats['total'],
        'status_class' => $stats['status_class'],
    ], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    error_log('Attendance details: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
