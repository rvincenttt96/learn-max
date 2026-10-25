<?php
require_once '../../includes/auth.php';
require_once '../../includes/db.php';
require_once '../../includes/terms.php';
require_role('student');
date_default_timezone_set('Asia/Tehran');

$class_id = (int)($_GET['class_id'] ?? 0);
$user_id = (int)$_SESSION['user_id'];

function ghazal_join_fail($message) {
    http_response_code(403);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><title>ورود به کلاس</title>';
    echo '<style>@font-face{font-family:"YekanBakhFaNum";src:url("/fonts/YekanBakhFaNum-Regular.ttf") format("truetype");font-display:swap}body{font-family:YekanBakhFaNum,Tahoma,sans-serif;background:#08080C;color:#eee;display:flex;align-items:center;justify-content:center;height:100vh;margin:0}';
    echo '.box{max-width:28rem;background:rgba(14,14,22,.92);border:1px solid rgba(127,29,29,.4);border-radius:24px;padding:28px;text-align:center;box-shadow:0 12px 40px rgba(0,0,0,.55)}</style></head><body>';
    echo '<div class="box"><p>' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</p>';
    echo '<p><a href="classes.php" style="color:#f87171">بازگشت به کلاس‌های من</a></p></div></body></html>';
    exit;
}

if ($class_id <= 0) {
    ghazal_join_fail('کلاس نامعتبر است.');
}

$stmt = $pdo->prepare("
    SELECT c.id, c.title, c.class_type, c.online_link, c.schedule, c.term_id,
           t.starts_on, t.is_archived, t.is_deleted, t.accounting_term_id,
           u.id AS student_id, u.full_name, u.username, u.phone
    FROM classes c
    JOIN class_students cs ON cs.class_id = c.id
    JOIN users u ON u.id = cs.user_id
    JOIN terms t ON t.id = c.term_id
    WHERE c.id = ?
      AND cs.user_id = ?
      AND u.is_deleted = 0
      AND (cs.is_deleted = 0 OR cs.is_deleted IS NULL)
      AND c.is_deleted = 0
      AND IFNULL(c.is_archived, 0) = 0
    LIMIT 1
");
$stmt->execute([$class_id, $user_id]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$row) {
    ghazal_join_fail('این کلاس برای شما ثبت نشده است.');
}

$term = [
    'id' => (int)$row['term_id'],
    'starts_on' => $row['starts_on'],
    'is_archived' => $row['is_archived'],
    'is_deleted' => $row['is_deleted'],
    'accounting_term_id' => $row['accounting_term_id'],
];
if (!term_row_is_teaching($term)) {
    if (term_row_is_upcoming($term)) {
        ghazal_join_fail('ترم هنوز شروع نشده است. تا تاریخ شروع تدریس نمی‌توانید وارد کلاس آنلاین شوید.');
    }
    ghazal_join_fail('این کلاس مربوط به ترم بایگانی‌شده است.');
}

$class = [
    'id' => (int)$row['id'],
    'title' => $row['title'],
    'class_type' => $row['class_type'],
    'online_link' => $row['online_link'],
    'schedule' => $row['schedule'],
    'term_id' => (int)$row['term_id'],
];
$student = [
    'id' => (int)$row['student_id'],
    'full_name' => $row['full_name'],
    'username' => $row['username'],
    'phone' => $row['phone'],
];

$type = strtolower((string)$class['class_type']);
if ($type !== 'online' && $type !== 'private' && trim((string)$class['online_link']) === '') {
    ghazal_join_fail('این کلاس لینک آنلاین ندارد.');
}

try {
    require_once __DIR__ . '/../../includes/alocom-access.php';
    require_once __DIR__ . '/../../includes/accounting.php';
    $acc = accounting_match_student_for_access($pdo, $student, $class);
    if (!alocom_event_skips_payment($class) && !($acc && !empty($acc['settled']))) {
        ghazal_join_fail('تا تسویه شهریه این ترم، ورود به کلاس آنلاین باز نمی‌شود.');
    }
    $enroll = alocom_enroll_panel_student_in_event($pdo, $student, $class);
    $url = is_array($enroll) ? (string)($enroll['sign_in'] ?? '') : '';
    if ($url === '' || !alocom_url_is_direct_entry($url)) {
        ghazal_join_fail('الان نتوانستیم لینک مستقیم ورود الوکام را بسازیم. لینک عمومی کلاس را در مرورگر باز نکنید؛ چند لحظه دیگر از همین دکمه در پنل دوباره بزنید.');
    }
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
    header('Location: ' . $url, true, 303);
    exit;
} catch (Exception $e) {
    error_log('join-online: ' . $e->getMessage());
    ghazal_join_fail('ورود به کلاس آنلاین الان انجام نشد. لطفاً دوباره تلاش کنید.');
}
