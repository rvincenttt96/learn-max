<?php
ini_set('display_errors', '0');
require_once '../../includes/auth.php';
require_once '../../includes/db.php';
require_once '../../includes/quiz-builder.php';
require_once '../../includes/quiz-student.php';
require_role('student');

try {
    quiz_student_fail_fast($pdo);
    quiz_student_ensure_schema($pdo);
} catch (Exception $e) {
    error_log('ajax-quiz schema: ' . $e->getMessage());
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    quiz_builder_json(['success' => false, 'message' => 'درخواست نامعتبر.'], 405);
}

$student_id = (int)($_SESSION['user_id'] ?? 0);
$action = trim((string)($_POST['action'] ?? ''));

try {
    if (!in_array($action, ['open', 'start', 'save', 'submit'], true)) {
        quiz_builder_json(['success' => false, 'message' => 'عملیات نامعتبر.'], 400);
    }

    $exam = quiz_student_open_exam($pdo, (int)($_POST['exam_id'] ?? 0), $student_id);
    if (!$exam) {
        throw new Exception('این آزمون برای شما باز نیست.');
    }

    $attempt = quiz_student_find_attempt($pdo, (int)$exam['id'], $student_id);

    if ($action === 'open') {
        if ($attempt) {
            $attempt = quiz_student_maybe_expire($pdo, $attempt, $exam);
            quiz_builder_json([
                'success' => true,
                'data' => quiz_student_take_payload($exam, $attempt),
            ]);
        }
        $full = quiz_builder_public_exam($exam);
        quiz_builder_json([
            'success' => true,
            'data' => [
                'exam' => [
                    'id' => $full['id'],
                    'class_id' => $full['class_id'],
                    'class_title' => (string)($exam['class_title'] ?? ''),
                    'template_no' => $full['template_no'],
                    'template_label' => $full['template_label'],
                    'session_no' => $full['session_no'],
                    'is_writing' => !empty($full['is_writing']),
                    'mark' => $full['mark'],
                    'minutes' => $full['minutes'],
                    'passage' => '',
                    'listening_script' => '',
                    'listening_repetition' => '',
                    'questions' => [],
                ],
                'attempt' => null,
            ],
        ]);
    }

    if ($action === 'start') {
        $attempt = quiz_student_upsert_attempt($pdo, $exam, $student_id);
        $attempt = quiz_student_maybe_expire($pdo, $attempt, $exam);
        quiz_builder_json([
            'success' => true,
            'message' => quiz_builder_is_writing((int)$exam['template_no']) ? 'رایتینگ شروع شد.' : 'آزمون شروع شد.',
            'data' => quiz_student_take_payload($exam, $attempt),
        ]);
    }

    if (!$attempt) {
        throw new Exception('اول آزمون را شروع کنید.');
    }
    $attempt = quiz_student_maybe_expire($pdo, $attempt, $exam);

    if ($action === 'save' || $action === 'submit') {
        if (!in_array((string)$attempt['status'], ['submitted', 'graded'], true)) {
            $incoming = json_decode((string)($_POST['answers_json'] ?? ''), true);
            $answers = quiz_student_sanitize_answers($exam, is_array($incoming) ? $incoming : []);
            if ($action === 'submit') {
                $attempt = quiz_student_submit($pdo, $attempt, $exam, $answers);
            } else {
                quiz_student_store_answers($pdo, $attempt, $answers);
                $attempt = quiz_student_find_attempt($pdo, (int)$exam['id'], $student_id);
                $attempt = quiz_student_maybe_expire($pdo, $attempt, $exam);
            }
        }
    }

    $msg = 'ok';
    $writing = quiz_builder_is_writing((int)$exam['template_no']);
    if ($action === 'submit') {
        $msg = ((int)$attempt['pending_count'] > 0)
            ? ($writing ? 'رایتینگ ارسال شد. نمره را معلم می‌دهد.' : 'آزمون ارسال شد. نمره رایتینگ را معلم می‌دهد.')
            : ($writing ? 'رایتینگ ثبت شد.' : 'آزمون ثبت شد.');
    } elseif ($action === 'save') {
        $msg = $writing ? 'متن ذخیره شد.' : 'پاسخ‌ها ذخیره شد.';
    }
    quiz_builder_json([
        'success' => true,
        'message' => $msg,
        'data' => quiz_student_take_payload($exam, $attempt),
    ]);
} catch (Exception $e) {
    quiz_builder_json(['success' => false, 'message' => $e->getMessage()], 400);
}
