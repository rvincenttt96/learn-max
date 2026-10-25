<?php
/**
 * Lightweight Acc / WordPress hook → Error Logs channel.
 * No database. Fire-and-forget Mattermost only.
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/includes/error-log.php';

$given = $_SERVER['HTTP_X_GHAZAL_IDENTITY'] ?? ($_GET['k'] ?? '');
if (!ghazal_error_valid_secret($given)) {
    http_response_code(403);
    echo '{"ok":false}';
    exit;
}

$raw = file_get_contents('php://input');
$input = json_decode($raw, true);
if (!is_array($input)) {
    $input = $_POST;
}
$code = (string)($input['code'] ?? $_GET['code'] ?? '');
$reason = (string)($input['reason'] ?? $_GET['reason'] ?? '');
$extra = (string)($input['extra'] ?? $_GET['extra'] ?? '');
if ($code === '' || $reason === '') {
    http_response_code(400);
    echo '{"ok":false}';
    exit;
}

ghazal_error_report($code, $reason, $extra);
echo '{"ok":true}';
