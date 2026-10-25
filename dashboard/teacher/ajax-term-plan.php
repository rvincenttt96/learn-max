<?php
ini_set('display_errors', '0');
require_once '../../includes/auth.php';
require_once '../../includes/db.php';
require_once '../../includes/terms.php';
require_once '../../includes/term-plan.php';
require_role('teacher');

term_plan_ensure_schema($pdo);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    term_plan_json(['success' => false, 'message' => 'درخواست نامعتبر.'], 405);
}

$teacher_id = (int)($_SESSION['user_id'] ?? 0);
$class_id = (int)($_POST['class_id'] ?? 0);
$action = trim((string)($_POST['action'] ?? ''));
$class = $class_id ? term_plan_find_class($pdo, $class_id, $teacher_id) : null;
if (!$class) {
    term_plan_json(['success' => false, 'message' => 'دسترسی غیرمجاز.'], 403);
}

try {
    if ($action === 'save_check') {
        $task = term_plan_save_check(
            $pdo,
            (int)($_POST['task_id'] ?? 0),
            $class_id,
            $teacher_id,
            $_POST['status'] ?? 'pending',
            $_POST['method'] ?? '',
            $_POST['method_custom'] ?? '',
            $_POST['teacher_remark'] ?? ''
        );
        term_plan_json(['success' => true, 'task' => $task]);
    }
    if ($action === 'add_task') {
        $task = term_plan_add_task(
            $pdo,
            $class_id,
            (int)($_POST['session_no'] ?? 0),
            $_POST['title'] ?? '',
            'teacher',
            $teacher_id
        );
        term_plan_json(['success' => true, 'task' => $task]);
    }
    if ($action === 'delete_task') {
        term_plan_delete_task($pdo, (int)($_POST['task_id'] ?? 0), $class_id, 'teacher', $teacher_id);
        term_plan_json(['success' => true]);
    }
    term_plan_json(['success' => false, 'message' => 'عملیات نامعتبر.'], 400);
} catch (Exception $e) {
    term_plan_json(['success' => false, 'message' => $e->getMessage()], 400);
}
