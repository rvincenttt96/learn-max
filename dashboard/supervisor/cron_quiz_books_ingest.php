<?php
date_default_timezone_set('Asia/Tehran');
@set_time_limit(300);
@ini_set('memory_limit', '512M');
ignore_user_abort(true);

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/quiz-builder.php';
require_once __DIR__ . '/../../includes/rollcall.php';

if (php_sapi_name() !== 'cli') {
    $config = rollcall_config();
    $key = (string)($config['cron_key'] ?? '');
    if ($key === '') {
        $key = (string)($config['identity']['secret'] ?? '');
    }
    $given = (string)($_GET['k'] ?? '');
    if ($key === '' || $given === '' || !hash_equals($key, $given)) {
        http_response_code(403);
        exit('Forbidden');
    }
}

quiz_builder_ensure_schema($pdo);
header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'ok' => true,
    'ingested' => quiz_builder_ingest_sidecars($pdo, 0),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
