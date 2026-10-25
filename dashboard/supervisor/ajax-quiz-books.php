<?php
ini_set('display_errors', '0');
@ini_set('max_execution_time', '60');
@ini_set('max_input_time', '180');
@set_time_limit(60);
require_once '../../includes/auth.php';
require_once '../../includes/db.php';
require_once '../../includes/quiz-builder.php';
require_once '../../includes/quiz-pdf.php';
require_role('supervisor');

quiz_builder_ensure_schema($pdo);
quiz_builder_storage_dir();

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    quiz_builder_json(['success' => false, 'message' => 'درخواست نامعتبر.'], 405);
}

$action = trim((string)($_POST['action'] ?? ''));
$supervisor_id = (int)($_SESSION['user_id'] ?? 0);
$length = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
if ($action === '' && empty($_POST) && empty($_FILES) && $length > 0) {
    $lim = quiz_builder_upload_limit();
    quiz_builder_json([
        'success' => false,
        'message' => 'حجم فایل از حد سرور بیشتر است (حد ' . round($lim / 1048576) . ' مگابایت). فایل را فشرده کنید یا SB و WB را جدا بگذارید.',
    ], 400);
}

try {
    if ($action === 'upload') {
        $course_key = quiz_builder_normalize_course_key($_POST['course_key'] ?? '');
        $level = quiz_builder_normalize_level_key($_POST['level_key'] ?? '');
        if ($course_key === '' && $level === '') {
            throw new Exception('کتاب دوره را انتخاب کنید؛ مثلاً Wider World 2 یا Connectivity Foundation.');
        }
        if (empty($_FILES['book']['name']) || (int)($_FILES['book']['error'] ?? 4) !== UPLOAD_ERR_OK) {
            $err = (int)($_FILES['book']['error'] ?? 4);
            if ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE) {
                throw new Exception('حجم فایل از حد سرور بیشتر است.');
            }
            throw new Exception('فایل PDF را انتخاب کنید.');
        }
        $file = $_FILES['book'];
        $size = (int)$file['size'];
        if ($size < 64) {
            throw new Exception('فایل خالی است.');
        }
        $limit = quiz_builder_upload_limit();
        if ($size > $limit) {
            throw new Exception('حداکثر حجم مجاز ' . round($limit / 1048576) . ' مگابایت است.');
        }
        $orig = (string)$file['name'];
        if ($course_key === '') {
            $course_key = quiz_builder_guess_course_from_name($orig);
        }
        $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
        if ($ext !== 'pdf') {
            throw new Exception('فقط PDF پذیرفته می‌شود.');
        }
        $fh = fopen($file['tmp_name'], 'rb');
        $head = $fh ? fread($fh, 5) : '';
        if ($fh) {
            fclose($fh);
        }
        if ($head !== '%PDF-') {
            throw new Exception('این فایل PDF معتبر نیست.');
        }
        $tag = $course_key !== '' ? strtolower($course_key) : strtolower($level);
        $stored = 'qb_' . $tag . '_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.pdf';
        $dest = quiz_builder_storage_dir() . '/' . $stored;
        if (!move_uploaded_file($file['tmp_name'], $dest)) {
            throw new Exception('ذخیره فایل روی سرور ناموفق بود. پوشه آپلود ساخته نشد.');
        }
        $book = quiz_builder_insert_book($pdo, $course_key, $level, $orig, $stored, $size, $supervisor_id);
        quiz_builder_json([
            'success' => true,
            'message' => 'فایل ذخیره شد. استخراج متن شروع می‌شود.',
            'book' => quiz_builder_public_book($book),
        ]);
    }

    if ($action === 'register') {
        @set_time_limit(20);
        @ignore_user_abort(true);
        $course_key = quiz_builder_normalize_course_key($_POST['course_key'] ?? '');
        $book = quiz_builder_register_local_pdf($pdo, (string)($_POST['filename'] ?? ''), $course_key, $supervisor_id);
        quiz_builder_json([
            'success' => true,
            'message' => 'فایل در کتابخانه ثبت شد. استخراج متن بعد از رفرش شروع می‌شود.',
            'book' => quiz_builder_public_book($book),
        ]);
    }

    if ($action === 'extract_init') {
        $book = quiz_builder_find_book($pdo, (int)($_POST['book_id'] ?? 0));
        if (!$book) {
            throw new Exception('کتاب پیدا نشد.');
        }
        $restart = !empty($_POST['restart']);
        $book = quiz_pdf_extract_init($pdo, $book, $restart);
        quiz_builder_json([
            'success' => true,
            'done' => false,
            'book' => quiz_builder_public_book($book),
        ]);
    }

    if ($action === 'extract_chunk' || $action === 'extract') {
        $book = quiz_builder_find_book($pdo, (int)($_POST['book_id'] ?? 0));
        if (!$book) {
            throw new Exception('کتاب پیدا نشد.');
        }
        $status = (string)($book['extract_status'] ?? '');
        $need_init = $action === 'extract'
            || in_array($status, ['pending', 'failed', 'empty'], true)
            || (int)($book['page_count'] ?? 0) < 1;
        if ($need_init) {
            $restart = $action === 'extract' || in_array($status, ['failed', 'empty'], true);
            $book = quiz_pdf_extract_init($pdo, $book, $restart);
        }
        $size = (int)($book['file_size'] ?? 0);
        $batch = $size > 40 * 1048576 ? 2 : 4;
        $book = quiz_pdf_extract_chunk($pdo, $book, $batch);
        $done = in_array((string)$book['extract_status'], ['ready', 'empty', 'failed'], true);
        quiz_builder_json([
            'success' => true,
            'done' => $done,
            'message' => $done
                ? ((string)$book['extract_status'] === 'ready' ? 'استخراج تمام شد.' : (string)$book['extract_error'])
                : '',
            'book' => quiz_builder_public_book($book),
        ]);
    }

    if ($action === 'delete') {
        quiz_builder_delete_book($pdo, (int)($_POST['book_id'] ?? 0));
        quiz_builder_json(['success' => true, 'message' => 'کتاب حذف شد.']);
    }

    if ($action === 'preview') {
        $book = quiz_builder_find_book($pdo, (int)($_POST['book_id'] ?? 0));
        if (!$book) {
            throw new Exception('کتاب پیدا نشد.');
        }
        quiz_builder_json([
            'success' => true,
            'book' => quiz_builder_public_book($book),
            'pages' => quiz_builder_page_preview($pdo, (int)$book['id'], 4),
        ]);
    }

    quiz_builder_json(['success' => false, 'message' => 'عملیات نامعتبر.'], 400);
} catch (Exception $e) {
    quiz_builder_json(['success' => false, 'message' => $e->getMessage()], 400);
}
