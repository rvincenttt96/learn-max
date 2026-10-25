<?php
require_once '../../includes/auth.php';
require_once '../../includes/db.php';
require_once '../../includes/supervisor-ai-feedback.php';
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

    $action = strtolower(trim((string)($input['action'] ?? '')));
    if ($action === 'stats') {
        echo json_encode(['success' => true, 'stats' => supervisor_ai_feedback_stats($pdo)], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if ($action === 'like' || $action === 'dislike') {
        $out = supervisor_ai_feedback_vote(
            $pdo,
            $action,
            (string)($input['question'] ?? ''),
            (string)($input['answer'] ?? '')
        );
        echo json_encode(['success' => true] + $out, JSON_UNESCAPED_UNICODE);
        exit;
    }
    if ($action === 'report') {
        $out = supervisor_ai_feedback_report(
            $pdo,
            (string)($input['question'] ?? ''),
            (string)($input['answer'] ?? '')
        );
        echo json_encode(['success' => true] + $out, JSON_UNESCAPED_UNICODE);
        exit;
    }
    throw new Exception('درخواست نامعتبر است.');
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}
