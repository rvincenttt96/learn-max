<?php
/**
 * Internal identity hook. Called by accounting / WordPress with a shared secret.
 */
date_default_timezone_set('Asia/Tehran');
ignore_user_abort(true);
@set_time_limit(90);
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/identity-sync.php';
$GLOBALS['identity_panel_pdo'] = $pdo;

header('Content-Type: application/json; charset=utf-8');

$given = $_SERVER['HTTP_X_GHAZAL_IDENTITY'] ?? ($_GET['k'] ?? '');
if (!identity_valid_secret($given)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'forbidden'], JSON_UNESCAPED_UNICODE);
    exit;
}

$raw = file_get_contents('php://input');
$input = json_decode($raw, true);
if (!is_array($input)) {
    $input = $_POST;
}

$action = (string)($input['action'] ?? $_GET['action'] ?? 'sync');

try {
    if ($action === 'repair_acc') {
        $dry = !empty($input['dry_run']) || !empty($_GET['dry_run']);
        $result = identity_repair_acc_codes($pdo, $dry);
        echo json_encode(['ok' => true, 'result' => $result], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($action === 'open_classes') {
        echo json_encode(['ok' => true, 'classes' => identity_list_open_classes($pdo)], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($action === 'teacher_stats') {
        $termId = (string)($input['termId'] ?? $input['term_id'] ?? $_GET['termId'] ?? '');
        $termName = (string)($input['termName'] ?? $input['term_name'] ?? $_GET['termName'] ?? '');
        echo json_encode(identity_teacher_stats($pdo, $termId, $termName), JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($action === 'term_meta') {
        echo json_encode(identity_term_meta($pdo), JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($action === 'enroll') {
        $result = identity_enroll_student_in_class($pdo, $input['code'] ?? '', (int)($input['panel_class_id'] ?? $input['class_id'] ?? 0));
        echo json_encode($result, JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($action === 'delete') {
        $result = identity_delete_panel_student(
            $pdo,
            $input['code'] ?? '',
            $input['full_name'] ?? trim(($input['first_name'] ?? '') . ' ' . ($input['last_name'] ?? ''))
        );
        echo json_encode($result, JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($action === 'lookup') {
        $result = identity_lookup_student($pdo, $input);
        echo json_encode($result, JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($action === 'align') {
        $dry = !empty($input['dry_run']) || !empty($_GET['dry_run']);
        $result = identity_align_file_numbers($pdo, $dry);
        echo json_encode(['ok' => true, 'result' => $result], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($action === 'reset_wp_passwords') {
        $result = identity_reset_wp_file_passwords($pdo);
        echo json_encode(['ok' => true, 'result' => $result], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($action === 'retire_generated') {
        $result = identity_retire_generated_users($pdo);
        echo json_encode(['ok' => true, 'result' => $result], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($action === 'backfill') {
        $dry = !empty($input['dry_run']) || !empty($_GET['dry_run']);
        $limit = (int)($input['limit'] ?? $_GET['limit'] ?? 0);
        $offset = (int)($input['offset'] ?? $_GET['offset'] ?? 0);
        $result = identity_backfill($pdo, $dry, $limit, $offset);
        echo json_encode(['ok' => true, 'result' => $result], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $defer_remote = !empty($input['defer_remote']);
    $result = identity_sync_student($pdo, [
        'source' => $input['source'] ?? 'hook',
        'code' => $input['code'] ?? '',
        'previous_code' => $input['previous_code'] ?? '',
        'first_name' => $input['first_name'] ?? '',
        'last_name' => $input['last_name'] ?? '',
        'full_name' => $input['full_name'] ?? '',
        'phone' => $input['phone'] ?? '',
        'password' => $input['password'] ?? '',
        'acc_student_id' => $input['acc_student_id'] ?? null,
        'reset_password' => !empty($input['reset_password']),
        'wp_reset_password' => !empty($input['wp_reset_password']),
        'welcome_sms' => !empty($input['welcome_sms']),
        'defer_remote' => $defer_remote,
        'create_if_missing' => array_key_exists('create_if_missing', $input)
            ? !empty($input['create_if_missing'])
            : in_array((string)($input['source'] ?? ''), ['accounting', 'accounting-batch', 'panel-create', 'panel-import', 'panel-restore'], true),
    ]);
    $class_id = (int)($input['panel_class_id'] ?? $input['class_id'] ?? 0);
    if (!$defer_remote && $class_id > 0 && !empty($result['ok']) && !empty($result['code'])) {
        $enroll = identity_enroll_student_in_class($pdo, $result['code'], $class_id);
        $result['enroll'] = $enroll;
    }
    echo json_encode($result, JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    identity_log('ensure error: ' . $e->getMessage());
    if (function_exists('ghazal_error_from_throwable')) {
        ghazal_error_from_throwable($e);
    }
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
