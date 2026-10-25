<?php
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
require_once '../../includes/terms.php';
require_once '../../includes/placement.php';
require_role('supervisor');

header('Content-Type: application/json; charset=utf-8');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('درخواست نامعتبر.');
    }
    $raw = file_get_contents('php://input');
    $input = json_decode($raw, true);
    if (!is_array($input) || !$input) {
        $input = $_POST;
    }
    $action = (string)($input['action'] ?? 'save');
    if (!in_array($action, ['save', 'create', 'delete'], true)) {
        throw new Exception('عملیات نامعتبر است.');
    }
    placement_ensure_schema($pdo);

    if ($action === 'delete') {
        $pdo->beginTransaction();
        $deleted = placement_delete_class($pdo, (int)($input['class_id'] ?? 0));
        $pdo->commit();
        $sync_ids = $deleted['student_ids'] ?? [];
        $alocom_deleted = false;
        $message = 'کلاس حذف شد.';
        $row = $deleted['class_row'] ?? [];
        $type = strtolower(trim((string)($row['class_type'] ?? '')));
        $link = trim((string)($row['online_link'] ?? ''));
        if ($type === 'online' || $type === 'private' || $link !== '') {
            try {
                require_once '../../includes/alocom-access.php';
                $gone = alocom_delete_class_event($pdo, $row);
                if (!empty($gone['deleted'])) {
                    $alocom_deleted = true;
                    $message = 'کلاس و رویداد الوکام حذف شدند.';
                } elseif (isset($gone['ok']) && $gone['ok'] === false) {
                    $message = 'کلاس حذف شد ولی رویداد الوکام پاک نشد.';
                }
            } catch (Throwable $e) {
                error_log('placement delete alocom: ' . $e->getMessage());
                if (function_exists('alocom_log')) {
                    alocom_log('placement delete alocom class=' . (int)($deleted['id'] ?? 0) . ' ' . $e->getMessage());
                }
                $message = 'کلاس حذف شد ولی رویداد الوکام پاک نشد.';
            }
        }
        placement_emit_json([
            'success' => true,
            'message' => $message,
            'class_id' => (int)$deleted['id'],
            'alocom_deleted' => $alocom_deleted,
        ]);
        if ($sync_ids) {
            $row['class_type'] = 'offline';
            $row['online_link'] = '';
            $term = term_load($pdo, (int)($deleted['term_id'] ?? 0));
            try {
                placement_sync_identities($pdo, $sync_ids, $row, $term ?: []);
            } catch (Throwable $e) {
                error_log('placement delete sync: ' . $e->getMessage());
            }
        }
        exit;
    }

    if ($action === 'create') {
        $input['class_id'] = 0;
    }
    $input['created_by'] = (int)($_SESSION['user_id'] ?? 0);
    $pdo->beginTransaction();
    $saved = placement_save_class($pdo, $input);
    $old_students = $saved['_old_students'] ?? [];
    $term = $saved['_term'] ?? [];
    unset($saved['_old_students'], $saved['_term']);
    $pdo->commit();

    $saved = placement_provision_online($pdo, $saved);
    $class_row = [
        'id' => (int)$saved['id'],
        'title' => (string)$saved['title'],
        'class_type' => (string)$saved['class_type'],
        'online_link' => (string)($saved['online_link'] ?? ''),
        'schedule' => (string)($saved['schedule'] ?? ''),
        'term_id' => (int)($saved['term_id'] ?? 0),
        'teacher_id' => (int)($saved['teacher_id'] ?? 0),
    ];
    $events = null;
    if (!empty($saved['alocom_event']) && is_array($saved['alocom_event'])) {
        $events = [$saved['alocom_event']];
    }
    $student_ids = $saved['student_ids'] ?? [];
    $sync_ids = array_values(array_unique(array_merge($old_students, $student_ids)));
    unset($saved['alocom_event']);

    $message = !empty($saved['created']) ? 'کلاس تازه ذخیره شد.' : 'کارت کلاس ذخیره شد.';
    $wants_alocom = (($saved['class_type'] ?? '') === 'online')
        || !empty($saved['create_alocom'])
        || trim((string)($saved['online_link'] ?? '')) !== '';
    if ($wants_alocom) {
        $roster = is_array($saved['alocom_roster'] ?? null) ? $saved['alocom_roster'] : [];
        $roster_n = (int)($roster['students'] ?? 0);
        $teacher_ok = !empty($roster['teacher']);
        $inperson = in_array((string)($saved['class_type'] ?? ''), ['offline', 'inperson', 'in-person'], true);
        if (!empty($saved['alocom_ok']) && !empty($saved['alocom_created'])) {
            if ($inperson) {
                $message = 'رویداد آنلاین جایگزین حضوری ساخته شد. ورود زبان‌آموز بدون تسویه باز است.';
            } elseif ($roster_n > 0 || $teacher_ok) {
                $message = 'کلاس ذخیره شد، اتاق الوکام ساخته شد و اعضا ثبت شدند.';
            } else {
                $message = 'کلاس ذخیره شد و اتاق الوکام ساخته شد.';
            }
        } elseif (!empty($saved['alocom_ok']) && trim((string)($saved['online_link'] ?? '')) !== '') {
            if ($inperson) {
                $message = 'کلاس ذخیره شد. رویداد جایگزین حضوری آماده است و ورود بدون تسویه باز است.';
            } elseif ($roster_n > 0 || $teacher_ok) {
                $message = 'کلاس ذخیره شد. اعضای اتاق الوکام ثبت شدند.';
            } else {
                $message = 'کلاس ذخیره شد. اتاق الوکام آماده است.';
            }
        } elseif (!empty($saved['alocom_warning'])) {
            $message = $saved['alocom_warning'];
        }
    }

    $need_sync = !empty($saved['created'])
        || !placement_ids_same($old_students, $student_ids)
        || $wants_alocom;

    placement_emit_json([
        'success' => true,
        'message' => $message,
        'class' => $saved,
    ]);

    if ($need_sync && $sync_ids) {
        try {
            if (!empty($saved['alocom_roster_ok'])) {
                $class_row['class_type'] = 'offline';
                $class_row['online_link'] = '';
            }
            placement_sync_identities($pdo, $sync_ids, $class_row, $term ?: [], $events);
        } catch (Throwable $e) {
            error_log('placement save sync: ' . $e->getMessage());
        }
    }
    exit;
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    if (function_exists('ghazal_error_from_throwable') && ($e instanceof PDOException || stripos($e->getMessage(), 'AloCom') !== false)) {
        ghazal_error_from_throwable($e);
    }
    echo json_encode(['success' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
