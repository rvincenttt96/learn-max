<?php
ini_set('display_errors', '0');
require_once '../../includes/auth.php';
require_once '../../includes/db.php';
require_once '../../includes/terms.php';
require_once '../../includes/term-plan.php';
require_role('supervisor');

term_plan_ensure_schema($pdo);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    term_plan_json(['success' => false, 'message' => 'درخواست نامعتبر.'], 405);
}

$supervisor_id = (int)($_SESSION['user_id'] ?? 0);
$class_id = (int)($_POST['class_id'] ?? 0);
$action = trim((string)($_POST['action'] ?? ''));
$class = $class_id ? term_plan_find_class($pdo, $class_id) : null;
if (!$class) {
    term_plan_json(['success' => false, 'message' => 'کلاس یافت نشد.'], 403);
}

try {
    if ($action === 'add_task') {
        $task = term_plan_add_task(
            $pdo,
            $class_id,
            (int)($_POST['session_no'] ?? 0),
            $_POST['title'] ?? '',
            'supervisor',
            $supervisor_id
        );
        term_plan_json(['success' => true, 'task' => $task]);
    }
    if ($action === 'update_task') {
        term_plan_update_task_title($pdo, (int)($_POST['task_id'] ?? 0), $class_id, $_POST['title'] ?? '');
        term_plan_json(['success' => true]);
    }
    if ($action === 'delete_task') {
        term_plan_delete_task($pdo, (int)($_POST['task_id'] ?? 0), $class_id);
        term_plan_json(['success' => true]);
    }
    if ($action === 'save_remark') {
        $session_no = (int)($_POST['session_no'] ?? 0);
        $remark = term_plan_save_supervisor_remark(
            $pdo,
            $class_id,
            $session_no,
            $_POST['remark'] ?? '',
            $supervisor_id
        );
        term_plan_json([
            'success' => true,
            'session_no' => $session_no,
            'remark' => $remark,
        ]);
    }
    term_plan_json(['success' => false, 'message' => 'عملیات نامعتبر.'], 400);
} catch (Exception $e) {
    term_plan_json(['success' => false, 'message' => $e->getMessage()], 400);
}
