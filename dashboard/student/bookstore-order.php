<?php
require_once '../../includes/auth.php';
require_once '../../includes/db.php';
require_once '../../includes/bookstore.php';
require_role('student');

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$stmt = $pdo->prepare('SELECT id, full_name, username, phone FROM users WHERE id = ? LIMIT 1');
$stmt->execute([(int)$_SESSION['user_id']]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$student) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'حساب کاربری پیدا نشد.'], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    echo json_encode([
        'ok' => true,
        'orders' => bookstore_student_orders((string)$student['username']),
        'postage_fee' => bookstore_postage_fee(),
        'payment' => bookstore_payment_info(),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'method_not_allowed'], JSON_UNESCAPED_UNICODE);
    exit;
}

$action = trim((string)($_POST['action'] ?? ''));
if ($action === '' && isset($_FILES['receipt'])) {
    $action = 'receipt';
}

if ($action === 'receipt') {
    $tracking = trim((string)($_POST['tracking_code'] ?? ''));
    $file = $_FILES['receipt'] ?? null;
    if (!is_array($file)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'فایل رسید را انتخاب کنید.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $result = bookstore_upload_receipt($student, $tracking, $file);
    if (empty($result['ok'])) {
        http_response_code(400);
    }
    echo json_encode($result, JSON_UNESCAPED_UNICODE);
    exit;
}

$raw = file_get_contents('php://input');
$input = json_decode((string)$raw, true);
if (!is_array($input)) {
    $input = $_POST;
}

$package = trim((string)($input['package'] ?? ''));
if ($package === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'لطفاً یک پکیج انتخاب کنید.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$books = $input['books'] ?? [];
if (!is_array($books)) {
    $books = [];
}

$result = bookstore_submit_package($student, $package, $books, [
    'delivery_type' => $input['delivery_type'] ?? $input['delivery'] ?? 'pickup',
    'address' => (string)($input['address'] ?? ''),
    'postal_code' => (string)($input['postal_code'] ?? $input['postal'] ?? ''),
    'pdo' => $pdo,
]);
if (empty($result['ok'])) {
    http_response_code(400);
}
echo json_encode($result, JSON_UNESCAPED_UNICODE);
