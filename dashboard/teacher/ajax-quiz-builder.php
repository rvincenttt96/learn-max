<?php
ini_set('display_errors', '0');
require_once '../../includes/auth.php';
require_once '../../includes/db.php';
require_once '../../includes/term-plan.php';
require_once '../../includes/quiz-builder.php';
require_once '../../includes/quiz-student.php';
require_once '../../includes/quiz-ai.php';
require_role('teacher');

quiz_builder_ensure_schema($pdo);

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    quiz_builder_json(['success' => false, 'message' => 'درخواست نامعتبر.'], 405);
}

$teacher_id = (int)($_SESSION['user_id'] ?? 0);
$action = trim((string)($_POST['action'] ?? ''));
$class_id = (int)($_POST['class_id'] ?? 0);
$class = $class_id ? term_plan_find_class($pdo, $class_id, $teacher_id) : null;

try {
    if ($action === 'list_exams') {
        if (!$class) {
            throw new Exception('کلاس یافت نشد.');
        }
        $rows = quiz_builder_class_exams($pdo, $class_id, $teacher_id);
        $exams = [];
        foreach ($rows as $row) {
            $exams[] = quiz_builder_public_exam($row);
        }
        quiz_builder_json(['success' => true, 'exams' => $exams]);
    }

    if ($action === 'get_exam') {
        $exam = quiz_builder_find_exam($pdo, (int)($_POST['exam_id'] ?? 0), $teacher_id);
        if (!$exam) {
            throw new Exception('آزمون پیدا نشد.');
        }
        $book = quiz_builder_find_book($pdo, (int)$exam['book_id']);
        $exam['book_name'] = $book ? $book['original_name'] : '';
        quiz_builder_json(['success' => true, 'exam' => quiz_builder_public_exam($exam)]);
    }

    if ($action === 'generate') {
        if (!$class) {
            throw new Exception('کلاس یافت نشد.');
        }
        if (!quiz_ai_rate_ok('quiz_builder_ai_rate', 20, 900)) {
            throw new Exception('تعداد ساخت در این چند دقیقه زیاد شد. کمی بعد دوباره امتحان کنید.');
        }
        $level_key = quiz_builder_detect_level_key($class['title'] ?? '');
        if ($level_key === '') {
            throw new Exception('سطح کلاس از عنوان تشخیص داده نشد.');
        }
        $book = quiz_builder_find_book($pdo, (int)($_POST['book_id'] ?? 0));
        if (!$book || !quiz_builder_book_covers_level($book, $level_key)) {
            throw new Exception('این کتاب برای سطح این کلاس نیست.');
        }
        if (($book['extract_status'] ?? '') !== 'ready') {
            throw new Exception('متن این کتاب هنوز آماده نیست.');
        }
        $tpl = quiz_builder_template_for_level((int)($_POST['template_no'] ?? 0), $level_key);
        if (!$tpl) {
            throw new Exception('قالب کوییز نامعتبر است.');
        }
        $from = max(1, (int)($_POST['page_from'] ?? 1));
        $to = max($from, (int)($_POST['page_to'] ?? $from));
        $pages = max(1, (int)$book['page_count']);
        if ($from > $pages) {
            throw new Exception('شماره صفحه شروع از تعداد صفحات کتاب بیشتر است.');
        }
        $to = min($to, $pages);
        if (($to - $from) > 24) {
            throw new Exception('حداکثر ۲۵ صفحه در هر ساخت.');
        }
        $diff_key = trim((string)($_POST['difficulty'] ?? 'medium'));
        $diffs = quiz_builder_difficulties();
        if (!isset($diffs[$diff_key])) {
            $diff_key = 'medium';
        }
        $excerpt = quiz_builder_excerpt($pdo, (int)$book['id'], $from, $to);
        $payload = quiz_ai_generate($excerpt, $tpl, $level_key, $diff_key);
        $exam_id = quiz_builder_save_exam($pdo, [
            'class_id' => $class_id,
            'teacher_id' => $teacher_id,
            'book_id' => (int)$book['id'],
            'template_no' => (int)$tpl['no'],
            'session_no' => (int)$tpl['session'],
            'difficulty' => $diff_key,
            'page_from' => $from,
            'page_to' => $to,
            'questions_json' => quiz_builder_encode_payload($payload),
            'status' => 'draft',
        ]);
        $exam = quiz_builder_find_exam($pdo, $exam_id, $teacher_id);
        $exam['book_name'] = $book['original_name'];
        quiz_builder_json([
            'success' => true,
            'message' => ((int)$tpl['no'] === 7)
                ? 'تاپیک رایتینگ ساخته شد. اگر دوست ندارید، تاپیک دیگر بگیرید یا متن را عوض کنید.'
                : 'آزمون ساخته شد. سؤال‌های نامطلوب را جایگزین کنید.',
            'exam' => quiz_builder_public_exam($exam),
        ]);
    }

    if ($action === 'replace') {
        $exam = quiz_builder_find_exam($pdo, (int)($_POST['exam_id'] ?? 0), $teacher_id);
        if (!$exam) {
            throw new Exception('آزمون پیدا نشد.');
        }
        if (!quiz_ai_rate_ok('quiz_builder_ai_rate', 20, 900)) {
            throw new Exception('تعداد ساخت در این چند دقیقه زیاد شد. کمی بعد دوباره امتحان کنید.');
        }
        $class = term_plan_find_class($pdo, (int)$exam['class_id'], $teacher_id);
        if (!$class) {
            throw new Exception('کلاس یافت نشد.');
        }
        $level_key = quiz_builder_detect_level_key($class['title'] ?? '');
        $book = quiz_builder_find_book($pdo, (int)$exam['book_id']);
        if (!$book) {
            throw new Exception('کتاب این آزمون حذف شده است.');
        }
        $tpl = quiz_builder_template_for_level((int)$exam['template_no'], $level_key);
        if (!$tpl) {
            throw new Exception('قالب نامعتبر است.');
        }
        $payload = quiz_builder_decode_payload($exam['questions_json']);
        $qid = trim((string)($_POST['question_id'] ?? ''));
        $idx = -1;
        $old = null;
        foreach ($payload['questions'] as $i => $q) {
            if ((string)($q['id'] ?? '') === $qid) {
                $idx = $i;
                $old = $q;
                break;
            }
        }
        if ($idx < 0 && ($tpl['kinds'] ?? '') === 'writing' && !empty($payload['questions'][0])) {
            $idx = 0;
            $old = $payload['questions'][0];
            $qid = (string)($old['id'] ?? 'q-1');
        }
        if ($idx < 0) {
            throw new Exception((($tpl['kinds'] ?? '') === 'writing') ? 'تاپیک پیدا نشد.' : 'سؤال پیدا نشد.');
        }
        $excerpt = quiz_builder_excerpt($pdo, (int)$book['id'], (int)$exam['page_from'], (int)$exam['page_to']);
        $new = quiz_ai_replace_one(
            $excerpt,
            $tpl,
            $level_key,
            (string)$exam['difficulty'],
            $payload['questions'],
            $old
        );
        $new['id'] = $qid;
        $payload['questions'][$idx] = $new;
        if (($tpl['kinds'] ?? '') === 'writing') {
            $payload = quiz_builder_force_writing_topic($payload, $tpl);
        }
        quiz_builder_update_exam($pdo, (int)$exam['id'], [
            'questions_json' => quiz_builder_encode_payload($payload),
            'status' => 'draft',
        ]);
        $exam = quiz_builder_find_exam($pdo, (int)$exam['id'], $teacher_id);
        $exam['book_name'] = $book['original_name'];
        quiz_builder_json([
            'success' => true,
            'message' => (($tpl['kinds'] ?? '') === 'writing') ? 'تاپیک جدید ساخته شد.' : 'سؤال جایگزین شد.',
            'exam' => quiz_builder_public_exam($exam),
        ]);
    }

    if ($action === 'save_questions') {
        $exam = quiz_builder_find_exam($pdo, (int)($_POST['exam_id'] ?? 0), $teacher_id);
        if (!$exam) {
            throw new Exception('آزمون پیدا نشد.');
        }
        $incoming = json_decode((string)($_POST['questions_json'] ?? ''), true);
        if (!is_array($incoming)) {
            throw new Exception('سؤال‌ها نامعتبر است.');
        }
        $payload = quiz_builder_decode_payload($exam['questions_json']);
        if (isset($incoming['questions'])) {
            $payload['questions'] = $incoming['questions'];
        } else {
            $payload['questions'] = $incoming;
        }
        foreach (['passage', 'listening_script', 'listening_repetition'] as $k) {
            if (array_key_exists($k, $incoming)) {
                $payload[$k] = $incoming[$k];
            }
        }
        $tpl = quiz_builder_template((int)$exam['template_no']);
        if ($tpl && ($tpl['kinds'] ?? '') === 'writing') {
            $payload = quiz_builder_force_writing_topic($payload, $tpl);
            if (empty($payload['questions']) || trim((string)($payload['questions'][0]['prompt'] ?? '')) === '') {
                throw new Exception('تاپیک رایتینگ خالی است.');
            }
        }
        quiz_builder_update_exam($pdo, (int)$exam['id'], [
            'questions_json' => quiz_builder_encode_payload($payload),
        ]);
        $book = quiz_builder_find_book($pdo, (int)$exam['book_id']);
        $exam = quiz_builder_find_exam($pdo, (int)$exam['id'], $teacher_id);
        $exam['book_name'] = $book ? $book['original_name'] : '';
        quiz_builder_json([
            'success' => true,
            'message' => 'اصلاح‌ها ذخیره شد.',
            'exam' => quiz_builder_public_exam($exam),
        ]);
    }

    if ($action === 'set_status') {
        $exam = quiz_builder_find_exam($pdo, (int)($_POST['exam_id'] ?? 0), $teacher_id);
        if (!$exam) {
            throw new Exception('آزمون پیدا نشد.');
        }
        $status = trim((string)($_POST['status'] ?? ''));
        if (!in_array($status, ['draft', 'ready'], true)) {
            throw new Exception('وضعیت نامعتبر است.');
        }
        $payload = quiz_builder_decode_payload($exam['questions_json']);
        $tpl = quiz_builder_template((int)$exam['template_no']);
        if ($tpl && ($tpl['kinds'] ?? '') === 'writing') {
            $payload = quiz_builder_force_writing_topic($payload, $tpl);
        }
        if ($status === 'ready' && empty($payload['questions'])) {
            throw new Exception($tpl && ($tpl['kinds'] ?? '') === 'writing'
                ? 'تاپیک خالی را نمی‌شود به کلاس فرستاد.'
                : 'آزمون بدون سؤال را نمی‌شود آماده کرد.');
        }
        $update = ['status' => $status];
        if ($tpl && ($tpl['kinds'] ?? '') === 'writing') {
            $update['questions_json'] = quiz_builder_encode_payload($payload);
        }
        quiz_builder_update_exam($pdo, (int)$exam['id'], $update);
        $book = quiz_builder_find_book($pdo, (int)$exam['book_id']);
        $exam = quiz_builder_find_exam($pdo, (int)$exam['id'], $teacher_id);
        $exam['book_name'] = $book ? $book['original_name'] : '';
        $writing = $tpl && (($tpl['kinds'] ?? '') === 'writing');
        quiz_builder_json([
            'success' => true,
            'message' => $status === 'ready'
                ? ($writing ? 'تاپیک برای دانش‌آموزان کلاس باز شد.' : 'آزمون برای دانش‌آموزان کلاس باز شد.')
                : 'برگشت به پیش‌نویس؛ دانش‌آموزان دیگر نمی‌بینند.',
            'exam' => quiz_builder_public_exam($exam),
        ]);
    }

    if ($action === 'list_attempts') {
        $exam = quiz_builder_find_exam($pdo, (int)($_POST['exam_id'] ?? 0), $teacher_id);
        if (!$exam) {
            throw new Exception('آزمون پیدا نشد.');
        }
        quiz_builder_json([
            'success' => true,
            'results' => quiz_student_exam_results($pdo, $exam),
        ]);
    }

    if ($action === 'grade_writing') {
        $row = quiz_student_find_attempt_id($pdo, (int)($_POST['attempt_id'] ?? 0), $teacher_id);
        if (!$row) {
            throw new Exception('پاسخ دانش‌آموز پیدا نشد.');
        }
        if (!in_array((string)$row['status'], ['submitted', 'graded'], true)) {
            throw new Exception('این آزمون هنوز تحویل نشده.');
        }
        quiz_student_grade_writing($pdo, $row, $_POST['score'] ?? 0);
        $exam = quiz_builder_find_exam($pdo, (int)$row['exam_id'], $teacher_id);
        quiz_builder_json([
            'success' => true,
            'message' => 'نمره رایتینگ ثبت شد.',
            'results' => quiz_student_exam_results($pdo, $exam),
        ]);
    }

    if ($action === 'delete') {
        quiz_builder_delete_exam($pdo, (int)($_POST['exam_id'] ?? 0), $teacher_id);
        quiz_builder_json(['success' => true, 'message' => 'آزمون حذف شد.']);
    }

    quiz_builder_json(['success' => false, 'message' => 'عملیات نامعتبر.'], 400);
} catch (Exception $e) {
    quiz_builder_json(['success' => false, 'message' => $e->getMessage()], 400);
}
