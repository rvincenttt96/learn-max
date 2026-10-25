<?php
date_default_timezone_set('Asia/Tehran');
@set_time_limit(180);
ignore_user_abort(true);

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/jdate.php';
require_once __DIR__ . '/../../includes/rollcall.php';
require_once __DIR__ . '/../../includes/alocom.php';

if (php_sapi_name() !== 'cli') {
    $config = rollcall_config();
    $key = $config['cron_key'] ?? '';
    if ($key === '' || !isset($_GET['k']) || $_GET['k'] !== $key) {
        http_response_code(403);
        exit('Forbidden');
    }
}

try {
    $result = alocom_sync_due_classes($pdo);
    if (php_sapi_name() !== 'cli') {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }
} catch (Exception $e) {
    alocom_log('Cron fatal: ' . $e->getMessage());
    error_log(date('[Y-m-d H:i:s] ') . "AloCom Cron Error: " . $e->getMessage() . "\n", 3, __DIR__ . '/cron_error_log.txt');
    if (function_exists('ghazal_error_report')) {
        ghazal_error_report('ALO-CRASH', 'کرون حضورغیاب الوکام متوقف شد.', ghazal_error_trim($e->getMessage()));
    }
}
