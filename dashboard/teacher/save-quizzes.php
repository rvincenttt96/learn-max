<?php
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
require_once '../../includes/quizzes.php';
require_role('teacher');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: quiz-scores.php");
    exit;
}

quizzes_ensure_schema($pdo);

$class_id = (int)($_POST['class_id'] ?? 0);
$quizzes = $_POST['quizzes'] ?? [];

if (!$class_id) {
    $_SESSION['error_message'] = 'کلاس انتخاب نشده است.';
    header("Location: quiz-scores.php");
    exit;
}

$stmt = $pdo->prepare("SELECT id FROM classes WHERE id = ? AND teacher_id = ?");
$stmt->execute([$class_id, $_SESSION['user_id']]);
if (!$stmt->fetch()) {
    $_SESSION['error_message'] = 'شما مجاز به ویرایش این کلاس نیستید.';
    header("Location: quiz-scores.php");
    exit;
}

$config = quizzes_config();
$cols = [];
$placeholders = [];
$updates = [];
for ($i = 1; $i <= quizzes_count(); $i++) {
    $col = 'quiz_' . $i;
    $cols[] = $col;
    $placeholders[] = '?';
    $updates[] = $col . ' = VALUES(' . $col . ')';
}

try {
    $pdo->beginTransaction();

    foreach ($quizzes as $student_id => $student_quizzes) {
        $student_id = (int)$student_id;
        if (!$student_id) {
            continue;
        }

        $quiz_values = [];
        foreach ($config as $i => $q) {
            $quiz_values[] = quizzes_parse_input($student_quizzes[$i] ?? '', $q['max']);
        }

        $sql = 'INSERT INTO student_quizzes (user_id, class_id, ' . implode(', ', $cols) . ')
            VALUES (?, ?, ' . implode(', ', $placeholders) . ')
            ON DUPLICATE KEY UPDATE ' . implode(', ', $updates);
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array_merge([$student_id, $class_id], $quiz_values));

        $stmt = $pdo->prepare("
            INSERT IGNORE INTO supervisor_scores (user_id, class_id, supervisor_score, is_submitted)
            VALUES (?, ?, NULL, 0)
        ");
        $stmt->execute([$student_id, $class_id]);
    }

    $pdo->commit();
    $_SESSION['success_message'] = 'نمرات با موفقیت ثبت شدند.';
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $_SESSION['error_message'] = 'خطا در ثبت نمرات: ' . $e->getMessage();
}

header("Location: quiz-scores.php?class_id=" . $class_id);
exit;
