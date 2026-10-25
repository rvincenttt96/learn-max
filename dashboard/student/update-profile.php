<?php
require_once '../../includes/auth.php';
require_once '../../includes/db.php';
require_once '../../includes/parent-phones.php';
require_role('student');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: profile.php');
    exit;
}

$user_id = (int)($_SESSION['user_id'] ?? 0);
$full_name = trim((string)($_POST['full_name'] ?? ''));
$phone = trim((string)($_POST['phone'] ?? ''));

if ($full_name === '') {
    header('Location: profile.php?error=' . urlencode('لطفاً نام را وارد کنید.'));
    exit;
}

try {
    users_ensure_parent_phone_columns($pdo);
    $father_phone = users_sanitize_parent_phone($_POST['father_phone'] ?? '', 'شماره پدر');
    $mother_phone = users_sanitize_parent_phone($_POST['mother_phone'] ?? '', 'شماره مادر');
    if ($phone !== '') {
        $phone = users_sanitize_parent_phone($phone, 'شماره تماس');
    }
} catch (Exception $e) {
    header('Location: profile.php?error=' . urlencode($e->getMessage()));
    exit;
}

$stmt = $pdo->prepare("
    UPDATE users
    SET full_name = ?, phone = ?, father_phone = ?, mother_phone = ?
    WHERE id = ? AND role = 'student' AND is_deleted = 0
");
$stmt->execute([
    $full_name,
    $phone !== '' ? $phone : null,
    $father_phone !== '' ? $father_phone : null,
    $mother_phone !== '' ? $mother_phone : null,
    $user_id,
]);

$_SESSION['full_name'] = $full_name;
header('Location: profile.php?success=' . urlencode('اطلاعات با موفقیت به‌روزرسانی شد.'));
exit;
