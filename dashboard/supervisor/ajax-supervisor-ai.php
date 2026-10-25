<?php
require_once '../../includes/auth.php';
require_once '../../includes/db.php';
require_once '../../includes/supervisor-ai.php';
require_role('supervisor');

header('Content-Type: application/json; charset=utf-8');

try {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        throw new Exception('روش نامعتبر است.');
    }
    $input = json_decode((string)file_get_contents('php://input'), true);
    if (!is_array($input)) {
        $input = $_POST;
    }
    $csrf = (string)($input['csrf'] ?? '');
    $ok = isset($_SESSION['supervisor_ai_csrf'])
        && is_string($_SESSION['supervisor_ai_csrf'])
        && $csrf !== ''
        && hash_equals($_SESSION['supervisor_ai_csrf'], $csrf);
    if (!$ok) {
        throw new Exception('نشست نامعتبر است. صفحه را رفرش کنید.');
    }

    $now = time();
    $bucket = $_SESSION['supervisor_ai_rate'] ?? ['start' => $now, 'n' => 0];
    if ($now - (int)$bucket['start'] > 900) {
        $bucket = ['start' => $now, 'n' => 0];
    }
    $bucket['n'] = (int)$bucket['n'] + 1;
    $_SESSION['supervisor_ai_rate'] = $bucket;
    if ($bucket['n'] > 80) {
        throw new Exception('تعداد پرسش در این چند دقیقه زیاد شد. کمی بعد دوباره امتحان کنید.');
    }

    $messages = $input['messages'] ?? [];
    if (!is_array($messages)) {
        throw new Exception('پیام‌ها نامعتبر است.');
    }
    $out = supervisor_ai_chat($pdo, $messages);
    echo json_encode(['success' => true] + $out, JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}
