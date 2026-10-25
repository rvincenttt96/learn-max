<?php
/**
 * Ghazal quiz builder: supervisor coursebook library + teacher exam drafts.
 * Graded take-quiz scores are copied into Quiz Marks (quiz_1..quiz_7).
 */

function quiz_builder_h($s) {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function quiz_builder_json($payload, $code = 200) {
    http_response_code((int)$code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function quiz_builder_storage_dir() {
    $dir = dirname(__DIR__) . '/uploads/quiz-books';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    $ht = $dir . '/.htaccess';
    if (!is_file($ht)) {
        @file_put_contents($ht, quiz_builder_htaccess_body());
    }
    $deny = $dir . '/index.html';
    if (!is_file($deny)) {
        @file_put_contents($deny, '');
    }
    return $dir;
}

function quiz_builder_htaccess_body() {
    return "<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n"
        . "<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n";
}

function quiz_builder_column_exists(PDO $pdo, $table, $column) {
    try {
        $st = $pdo->prepare("SHOW COLUMNS FROM `$table` LIKE ?");
        $st->execute([(string)$column]);
        return (bool)$st->fetch();
    } catch (Exception $e) {
        return false;
    }
}

function quiz_builder_ensure_schema(PDO $pdo) {
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS quiz_books (
                id INT AUTO_INCREMENT PRIMARY KEY,
                course_key VARCHAR(24) NULL DEFAULT NULL,
                level_key VARCHAR(24) NULL DEFAULT NULL,
                original_name VARCHAR(255) NOT NULL,
                stored_name VARCHAR(255) NOT NULL,
                mime VARCHAR(80) NOT NULL DEFAULT 'application/pdf',
                file_size INT NOT NULL DEFAULT 0,
                page_count INT NOT NULL DEFAULT 0,
                extract_cursor INT NOT NULL DEFAULT 0,
                extract_status VARCHAR(24) NOT NULL DEFAULT 'pending',
                extract_error VARCHAR(500) NULL,
                uploaded_by INT NULL DEFAULT NULL,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                KEY idx_qb_level (level_key),
                KEY idx_qb_course (course_key),
                KEY idx_qb_status (extract_status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        if (!quiz_builder_column_exists($pdo, 'quiz_books', 'course_key')) {
            $pdo->exec("ALTER TABLE quiz_books ADD COLUMN course_key VARCHAR(24) NULL DEFAULT NULL AFTER id");
        }
        if (!quiz_builder_column_exists($pdo, 'quiz_books', 'extract_cursor')) {
            $pdo->exec("ALTER TABLE quiz_books ADD COLUMN extract_cursor INT NOT NULL DEFAULT 0 AFTER page_count");
        }
        try {
            $pdo->exec("ALTER TABLE quiz_books MODIFY level_key VARCHAR(24) NULL DEFAULT NULL");
        } catch (Exception $e) {
            // already nullable
        }
        try {
            $pdo->exec("ALTER TABLE quiz_books ADD KEY idx_qb_course (course_key)");
        } catch (Exception $e) {
            // index exists
        }
        quiz_builder_backfill_course_keys($pdo);
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS quiz_book_pages (
                id INT AUTO_INCREMENT PRIMARY KEY,
                book_id INT NOT NULL,
                page_no INT NOT NULL,
                body MEDIUMTEXT,
                UNIQUE KEY uq_qbp (book_id, page_no),
                KEY idx_qbp_book (book_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS quiz_exams (
                id INT AUTO_INCREMENT PRIMARY KEY,
                class_id INT NOT NULL,
                teacher_id INT NOT NULL,
                book_id INT NOT NULL,
                template_no TINYINT UNSIGNED NOT NULL,
                session_no TINYINT UNSIGNED NULL,
                difficulty VARCHAR(16) NOT NULL DEFAULT 'medium',
                page_from INT NOT NULL DEFAULT 1,
                page_to INT NOT NULL DEFAULT 1,
                questions_json MEDIUMTEXT,
                status VARCHAR(16) NOT NULL DEFAULT 'draft',
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                KEY idx_qe_class (class_id),
                KEY idx_qe_teacher (teacher_id),
                KEY idx_qe_book (book_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    } catch (Exception $e) {
        error_log('quiz_builder schema: ' . $e->getMessage());
    }
    quiz_builder_ensure_attempts_table($pdo);
}

function quiz_builder_ensure_attempts_table(PDO $pdo) {
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS quiz_attempts (
                id INT AUTO_INCREMENT PRIMARY KEY,
                exam_id INT NOT NULL,
                class_id INT NOT NULL,
                student_id INT NOT NULL,
                status VARCHAR(16) NOT NULL DEFAULT 'in_progress',
                started_at DATETIME NULL,
                submitted_at DATETIME NULL,
                answers_json MEDIUMTEXT,
                result_json MEDIUMTEXT,
                score_raw DECIMAL(8,2) NULL,
                score_max_raw DECIMAL(8,2) NULL,
                score_total DECIMAL(8,2) NULL,
                score_max DECIMAL(8,2) NULL,
                pending_count INT NOT NULL DEFAULT 0,
                UNIQUE KEY uq_qa_exam_student (exam_id, student_id),
                KEY idx_qa_class (class_id),
                KEY idx_qa_student (student_id),
                KEY idx_qa_exam (exam_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    } catch (Exception $e) {
        error_log('quiz_attempts schema: ' . $e->getMessage());
    }
}

function quiz_builder_level_groups() {
    $teen = [];
    for ($i = 1; $i <= 16; $i++) {
        $teen[] = 'T' . $i;
    }
    $wt = [];
    for ($i = 5; $i <= 16; $i++) {
        $wt[] = 'WT' . $i;
    }
    $adults = [];
    foreach (['B', 'E', 'PI', 'I', 'UI', 'ADV'] as $p) {
        for ($i = 1; $i <= 5; $i++) {
            $adults[] = $p . $i;
        }
    }
    $conn = [];
    foreach (['CB', 'CE', 'CPI', 'CI', 'CUI', 'CADV'] as $p) {
        for ($i = 1; $i <= 5; $i++) {
            $conn[] = $p . $i;
        }
    }
    return [
        'Teen' => $teen,
        'Wider World' => $wt,
        'Adults' => $adults,
        'Connectivity' => $conn,
    ];
}

function quiz_builder_known_levels() {
    $out = [];
    foreach (quiz_builder_level_groups() as $keys) {
        foreach ($keys as $k) {
            $out[$k] = $k;
        }
    }
    return $out;
}

function quiz_builder_course_level_block($prefix, $start, $count) {
    $levels = [];
    for ($i = 0; $i < $count; $i++) {
        $from = ($i * 2) + 1;
        $to = $from + 1;
        $key = $prefix . ($start + $i);
        $levels[$key] = [
            'units' => $from . '–' . $to,
            'unit_from' => $from,
            'unit_to' => $to,
        ];
    }
    return $levels;
}

function quiz_builder_courses() {
    return [
        'WW2' => [
            'key' => 'WW2',
            'group' => 'Wider World',
            'label' => 'Wider World 2',
            'note' => 'یک PDF برای چهار ترم. هر دو یونیت یک سطح: WT5 تا WT8.',
            'levels' => quiz_builder_course_level_block('WT', 5, 4),
        ],
        'WW3' => [
            'key' => 'WW3',
            'group' => 'Wider World',
            'label' => 'Wider World 3',
            'note' => 'یک PDF برای WT9 تا WT12. یونیت ۱–۲ = WT9، ۳–۴ = WT10، …',
            'levels' => quiz_builder_course_level_block('WT', 9, 4),
        ],
        'WW4' => [
            'key' => 'WW4',
            'group' => 'Wider World',
            'label' => 'Wider World 4',
            'note' => 'یک PDF برای WT13 تا WT16.',
            'levels' => quiz_builder_course_level_block('WT', 13, 4),
        ],
        'CNF' => [
            'key' => 'CNF',
            'group' => 'Connectivity',
            'label' => 'Connectivity Foundation',
            'note' => 'هر دو یونیت یک سطح: CB1 تا CB5 (یونیت ۱–۲ = CB1).',
            'levels' => quiz_builder_course_level_block('CB', 1, 5),
        ],
        'CN1' => [
            'key' => 'CN1',
            'group' => 'Connectivity',
            'label' => 'Connectivity 1',
            'note' => 'CE1 تا CE5. هر دو یونیت یک ترم.',
            'levels' => quiz_builder_course_level_block('CE', 1, 5),
        ],
        'CN2' => [
            'key' => 'CN2',
            'group' => 'Connectivity',
            'label' => 'Connectivity 2',
            'note' => 'CPI1 تا CPI5.',
            'levels' => quiz_builder_course_level_block('CPI', 1, 5),
        ],
        'CN3' => [
            'key' => 'CN3',
            'group' => 'Connectivity',
            'label' => 'Connectivity 3',
            'note' => 'CI1 تا CI5.',
            'levels' => quiz_builder_course_level_block('CI', 1, 5),
        ],
        'CN4' => [
            'key' => 'CN4',
            'group' => 'Connectivity',
            'label' => 'Connectivity 4',
            'note' => 'CUI1 تا CUI5.',
            'levels' => quiz_builder_course_level_block('CUI', 1, 5),
        ],
        'CN5' => [
            'key' => 'CN5',
            'group' => 'Connectivity',
            'label' => 'Connectivity 5',
            'note' => 'CADV1 تا CADV5.',
            'levels' => quiz_builder_course_level_block('CADV', 1, 5),
        ],
    ];
}

function quiz_builder_course($key) {
    $all = quiz_builder_courses();
    $key = strtoupper(trim((string)$key));
    return isset($all[$key]) ? $all[$key] : null;
}

function quiz_builder_normalize_course_key($raw) {
    $raw = strtoupper(trim((string)$raw));
    return quiz_builder_course($raw) ? $raw : '';
}

function quiz_builder_course_for_level($level_key) {
    $level_key = strtoupper(trim((string)$level_key));
    if ($level_key === '') {
        return null;
    }
    foreach (quiz_builder_courses() as $course) {
        if (isset($course['levels'][$level_key])) {
            return $course;
        }
    }
    return null;
}

function quiz_builder_guess_course_from_name($name) {
    $n = function_exists('mb_strtolower') ? mb_strtolower((string)$name, 'UTF-8') : strtolower((string)$name);
    $n = str_replace(['_', '-', '.', '–', '—'], ' ', $n);
    $n = preg_replace('/\s+/u', ' ', $n);
    $is_conn = (bool)preg_match('/connect|کانکت|کنکت/u', $n);
    $is_ww = (bool)preg_match('/wider|وایدر/u', $n);
    if ($is_conn) {
        if (preg_match('/foundat|فاندیشن|بنیاد/u', $n)) {
            return 'CNF';
        }
        foreach ([5 => 'CN5', 4 => 'CN4', 3 => 'CN3', 2 => 'CN2', 1 => 'CN1'] as $num => $key) {
            if (preg_match('/\b(?:level|lvl|book|جلد)?\s*' . $num . '\b/u', $n)) {
                return $key;
            }
        }
        return '';
    }
    if ($is_ww) {
        foreach ([4 => 'WW4', 3 => 'WW3', 2 => 'WW2'] as $num => $key) {
            if (preg_match('/\b(?:level|lvl|book|جلد)?\s*' . $num . '\b/u', $n)) {
                return $key;
            }
        }
    }
    return '';
}

function quiz_builder_backfill_course_keys(PDO $pdo) {
    try {
        if (!quiz_builder_column_exists($pdo, 'quiz_books', 'course_key')) {
            return;
        }
        $rows = $pdo->query("
            SELECT id, course_key, level_key, original_name
            FROM quiz_books
            WHERE course_key IS NULL OR course_key = ''
        ")->fetchAll(PDO::FETCH_ASSOC);
        if (!$rows) {
            return;
        }
        $upd = $pdo->prepare("UPDATE quiz_books SET course_key = ? WHERE id = ?");
        foreach ($rows as $row) {
            $course = quiz_builder_course_for_level($row['level_key'] ?? '');
            if (!$course) {
                $guess = quiz_builder_guess_course_from_name($row['original_name'] ?? '');
                $course = $guess !== '' ? quiz_builder_course($guess) : null;
            }
            if ($course) {
                $upd->execute([$course['key'], (int)$row['id']]);
            }
        }
    } catch (Exception $e) {
        error_log('quiz_builder backfill: ' . $e->getMessage());
    }
}

function quiz_builder_unit_hint($course_key, $level_key) {
    $course = quiz_builder_course($course_key);
    $level_key = strtoupper(trim((string)$level_key));
    if (!$course || $level_key === '' || empty($course['levels'][$level_key]['units'])) {
        return '';
    }
    return 'Units ' . $course['levels'][$level_key]['units'];
}

function quiz_builder_course_levels_label(array $course) {
    $keys = array_keys($course['levels']);
    if (!$keys) {
        return '';
    }
    if (count($keys) === 1) {
        return $keys[0];
    }
    return $keys[0] . ' تا ' . $keys[count($keys) - 1];
}

function quiz_builder_book_covers_level(array $book, $level_key) {
    $level_key = strtoupper(trim((string)$level_key));
    if ($level_key === '') {
        return false;
    }
    if (strtoupper((string)($book['level_key'] ?? '')) === $level_key) {
        return true;
    }
    $course = quiz_builder_course($book['course_key'] ?? '');
    return $course && isset($course['levels'][$level_key]);
}

function quiz_builder_ini_bytes($val) {
    $val = trim((string)$val);
    if ($val === '') {
        return 0;
    }
    $n = (float)$val;
    $u = strtolower(substr($val, -1));
    if ($u === 'g') {
        $n *= 1024 * 1024 * 1024;
    } elseif ($u === 'm') {
        $n *= 1024 * 1024;
    } elseif ($u === 'k') {
        $n *= 1024;
    }
    return (int)$n;
}

function quiz_builder_upload_limit() {
    $candidates = [
        quiz_builder_ini_bytes(ini_get('upload_max_filesize')),
        quiz_builder_ini_bytes(ini_get('post_max_size')),
        150 * 1024 * 1024,
    ];
    $candidates = array_filter($candidates);
    return $candidates ? min($candidates) : (150 * 1024 * 1024);
}

function quiz_builder_insert_book(PDO $pdo, $course_key, $level, $orig, $stored, $size, $supervisor_id) {
    $st = $pdo->prepare("
        INSERT INTO quiz_books
            (course_key, level_key, original_name, stored_name, mime, file_size, page_count, extract_cursor, extract_status, uploaded_by)
        VALUES (?, ?, ?, ?, 'application/pdf', ?, 0, 0, 'pending', ?)
    ");
    $st->execute([
        $course_key !== '' ? $course_key : null,
        $level !== '' ? $level : null,
        $orig,
        $stored,
        (int)$size,
        (int)$supervisor_id,
    ]);
    return quiz_builder_find_book($pdo, (int)$pdo->lastInsertId());
}

function quiz_builder_unregistered_pdfs(PDO $pdo) {
    $dir = quiz_builder_storage_dir();
    $used = [];
    try {
        foreach ($pdo->query("SELECT stored_name, original_name FROM quiz_books")->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $used[basename((string)$row['stored_name'])] = true;
            $orig = basename((string)($row['original_name'] ?? ''));
            if ($orig !== '') {
                $used[$orig] = true;
            }
        }
    } catch (Exception $e) {
        $used = [];
    }
    $names = [];
    foreach (glob($dir . '/*.pdf') ?: [] as $path) {
        $names[basename($path)] = $path;
    }
    $out = [];
    foreach ($names as $base => $path) {
        if (isset($used[$base])) {
            continue;
        }
        $orig = quiz_builder_da_temp_original($base);
        if ($orig !== '' && isset($names[$orig])) {
            continue;
        }
        $size = (int)@filesize($path);
        if ($size < 64 || !quiz_builder_pdf_head_ok($path)) {
            continue;
        }
        $out[] = [
            'filename' => $base,
            'size' => $size,
            'guess' => quiz_builder_guess_course_from_name($orig !== '' ? $orig : $base),
        ];
    }
    usort($out, function ($a, $b) {
        return strcasecmp($a['filename'], $b['filename']);
    });
    return $out;
}

function quiz_builder_pdf_head_ok($path) {
    $fh = @fopen($path, 'rb');
    if (!$fh) {
        return false;
    }
    $head = fread($fh, 5);
    fclose($fh);
    return $head === '%PDF-';
}

function quiz_builder_da_temp_original($filename) {
    $base = basename((string)$filename);
    if (preg_match('/^[A-Za-z0-9]{5,10}_(.+\.pdf)$/i', $base, $m)) {
        return $m[1];
    }
    return '';
}

function quiz_builder_register_local_pdf(PDO $pdo, $filename, $course_key, $supervisor_id) {
    $base = basename((string)$filename);
    if ($base === '' || strpos($base, '..') !== false || substr($base, 0, 1) === '.') {
        throw new Exception('نام فایل نامعتبر است.');
    }
    if (strtolower(pathinfo($base, PATHINFO_EXTENSION)) !== 'pdf') {
        throw new Exception('فقط PDF پذیرفته می‌شود.');
    }
    $dir = quiz_builder_storage_dir();
    $src = $dir . '/' . $base;
    if (!is_file($src)) {
        throw new Exception('این PDF در پوشه کتاب‌ها روی سرور نیست.');
    }
    $size = (int)filesize($src);
    if ($size < 64) {
        throw new Exception('فایل خالی است.');
    }
    if (!quiz_builder_pdf_head_ok($src)) {
        throw new Exception('این فایل PDF معتبر نیست. اگر هنوز در فایل‌منیجر در حال آپلود است، صبر کنید.');
    }
    $course_key = quiz_builder_normalize_course_key($course_key);
    if ($course_key === '') {
        $orig = quiz_builder_da_temp_original($base);
        $course_key = quiz_builder_guess_course_from_name($orig !== '' ? $orig : $base);
    }
    if ($course_key === '') {
        throw new Exception('کتاب دوره را انتخاب کنید.');
    }
    $dup = $pdo->prepare("SELECT id FROM quiz_books WHERE stored_name = ? OR original_name = ? ORDER BY id DESC LIMIT 1");
    $dup->execute([$base, $base]);
    $existing_id = (int)($dup->fetchColumn() ?: 0);
    if ($existing_id > 0) {
        return quiz_builder_find_book($pdo, $existing_id);
    }
    return quiz_builder_insert_book($pdo, $course_key, '', $base, $base, $size, $supervisor_id);
}

function quiz_builder_find_book_by_name(PDO $pdo, $stored, $original = '') {
    $stored = basename((string)$stored);
    $original = basename((string)$original);
    if ($stored === '' && $original === '') {
        return null;
    }
    $st = $pdo->prepare("
        SELECT * FROM quiz_books
        WHERE stored_name = ? OR original_name = ? OR stored_name = ? OR original_name = ?
        ORDER BY id DESC
        LIMIT 1
    ");
    $st->execute([$stored, $stored, $original, $original]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function quiz_builder_ingest_sidecar_file(PDO $pdo, $path, $supervisor_id = 0) {
    $path = (string)$path;
    if (!is_file($path)) {
        throw new Exception('فایل استخراج پیدا نشد.');
    }
    $raw = file_get_contents($path);
    $payload = json_decode((string)$raw, true);
    unset($raw);
    if (!is_array($payload)) {
        throw new Exception('JSON استخراج نامعتبر است: ' . basename($path));
    }
    $stored = basename((string)($payload['stored_name'] ?? ''));
    $orig = basename((string)($payload['original_name'] ?? $stored));
    $pages_in = $payload['pages'] ?? null;
    if ($stored === '' || strpos($stored, '..') !== false) {
        throw new Exception('نام فایل PDF در JSON نامعتبر است.');
    }
    if (!is_array($pages_in) || !$pages_in) {
        throw new Exception('متن صفحات در JSON نیست.');
    }
    $pdf = quiz_builder_storage_dir() . '/' . $stored;
    if (!is_file($pdf)) {
        throw new Exception('PDF متناظر روی سرور نیست: ' . $stored);
    }
    $pages = [];
    foreach ($pages_in as $no => $body) {
        $n = (int)$no;
        if ($n < 1) {
            continue;
        }
        $pages[$n] = trim((string)$body);
    }
    if (!$pages) {
        throw new Exception('هیچ صفحه‌ای در JSON نبود.');
    }
    ksort($pages, SORT_NUMERIC);
    $course_key = quiz_builder_normalize_course_key($payload['course_key'] ?? '');
    if ($course_key === '') {
        $course_key = quiz_builder_guess_course_from_name($orig !== '' ? $orig : $stored);
    }
    $size = (int)filesize($pdf);
    $book = quiz_builder_find_book_by_name($pdo, $stored, $orig);
    if (!$book) {
        $book = quiz_builder_insert_book($pdo, $course_key, '', $orig !== '' ? $orig : $stored, $stored, $size, (int)$supervisor_id);
    } elseif ($course_key !== '' && (string)($book['course_key'] ?? '') === '') {
        $pdo->prepare("UPDATE quiz_books SET course_key = ? WHERE id = ?")->execute([$course_key, (int)$book['id']]);
        $book['course_key'] = $course_key;
    }
    $id = (int)$book['id'];
    $stats = quiz_builder_replace_pages($pdo, $id, $pages);
    $count = max((int)$stats['pages'], (int)max(array_keys($pages)));
    $status = ((int)$stats['with_text'] > 0) ? 'ready' : 'empty';
    $err = $status === 'empty'
        ? 'صفحه شمرده شد ولی متن خوانا نبود. احتمالاً اسکن تصویری است.'
        : null;
    $pdo->prepare("
        UPDATE quiz_books
        SET page_count = ?, extract_cursor = ?, extract_status = ?, extract_error = ?, file_size = ?
        WHERE id = ?
    ")->execute([$count, $count, $status, $err, $size, $id]);
    $book = quiz_builder_find_book($pdo, $id);
    @unlink($path);
    return [
        'ok' => true,
        'stored_name' => $stored,
        'course_key' => (string)($book['course_key'] ?? $course_key),
        'pages' => $count,
        'with_text' => (int)$stats['with_text'],
        'status' => $status,
        'book_id' => $id,
    ];
}

function quiz_builder_ingest_sidecars(PDO $pdo, $supervisor_id = 0) {
    $dir = quiz_builder_storage_dir();
    $out = [];
    foreach (glob($dir . '/*.pages.json') ?: [] as $path) {
        try {
            $out[] = quiz_builder_ingest_sidecar_file($pdo, $path, $supervisor_id);
        } catch (Exception $e) {
            $out[] = [
                'ok' => false,
                'file' => basename($path),
                'error' => $e->getMessage(),
            ];
        }
    }
    return $out;
}

function quiz_builder_normalize_level_key($raw) {
    $raw = trim((string)$raw);
    if ($raw === '') {
        return '';
    }
    $raw = str_replace(['ي', 'ك', '‌', 'ـ'], ['ی', 'ک', '', ''], $raw);
    $raw = preg_replace('/[\s\-_.–—]+/u', '', $raw);
    $raw = function_exists('mb_strtoupper') ? mb_strtoupper($raw, 'UTF-8') : strtoupper($raw);
    $known = quiz_builder_known_levels();
    return isset($known[$raw]) ? $raw : '';
}

function quiz_builder_detect_level_key($title) {
    $title = trim((string)$title);
    if ($title === '') {
        return '';
    }
    if (!function_exists('term_plan_detect_level_key')) {
        $tracks = __DIR__ . '/term-plan-tracks.php';
        if (is_file($tracks)) {
            require_once $tracks;
        }
    }
    if (function_exists('term_plan_detect_level_key')) {
        $key = term_plan_detect_level_key($title);
        if ($key !== '') {
            return $key;
        }
    }
    if (function_exists('term_plan_detect_legacy_level_key')) {
        $key = term_plan_detect_legacy_level_key($title);
        if ($key !== '') {
            return $key;
        }
    }
    $direct = quiz_builder_normalize_level_key($title);
    return $direct;
}

function quiz_builder_level_prefix($level_key) {
    $key = strtoupper(trim((string)$level_key));
    return (string)preg_replace('/\d+$/', '', $key);
}

function quiz_builder_has_phonetics($level_key) {
    return in_array(quiz_builder_level_prefix($level_key), [
        'PI', 'I', 'UI', 'ADV', 'CPI', 'CI', 'CUI', 'CADV',
    ], true);
}

function quiz_builder_cefr_band($level_key) {
    $p = quiz_builder_level_prefix($level_key);
    if (in_array($p, ['T', 'WT', 'B', 'CB'], true)) {
        return 'A1–A2';
    }
    if (in_array($p, ['E', 'CE'], true)) {
        return 'A2';
    }
    if (in_array($p, ['PI', 'CPI'], true)) {
        return 'A2–B1';
    }
    if (in_array($p, ['I', 'CI'], true)) {
        return 'B1';
    }
    if (in_array($p, ['UI', 'CUI'], true)) {
        return 'B1–B2';
    }
    if (in_array($p, ['ADV', 'CADV'], true)) {
        return 'B2';
    }
    return 'A2–B1';
}

function quiz_builder_templates() {
    return [
        1 => [
            'no' => 1,
            'label' => 'Quiz 1',
            'session' => 4,
            'mark' => 10,
            'minutes' => 10,
            'title' => 'Quiz 1 · Session 4',
            'kinds' => 'mc',
            'mix' => ['grammar' => 5, 'vocab' => 5, 'phonetics' => 2],
            'question_count' => 12,
            'notes' => 'پنج گرامر، پنج واژگان، دو فونتیک (فقط PI به بعد). Listening Repetition جدا از نمره.',
            'listening_repetition' => true,
        ],
        2 => [
            'no' => 2,
            'label' => 'Quiz 2',
            'session' => 6,
            'mark' => 10,
            'minutes' => 10,
            'title' => 'Quiz 2 · Session 6',
            'kinds' => 'mc_fill',
            'mix' => ['grammar' => 5, 'vocab' => 5, 'phonetics' => 2],
            'question_count' => 12,
            'notes' => 'ترکیب چندگزینه‌ای و جای‌خالی، همان ترکیب Quiz 1.',
            'listening_repetition' => false,
        ],
        3 => [
            'no' => 3,
            'label' => 'Quiz 3',
            'session' => 7,
            'mark' => 8,
            'minutes' => 15,
            'title' => 'Quiz 3 · Reading/Writing · Session 7',
            'kinds' => 'reading',
            'mix' => ['tf' => 2, 'short' => 2, 'mc' => 2],
            'question_count' => 6,
            'notes' => 'متن خواندن ساخته‌شده از کتاب + دو درست/نادرست، دو کوتاه، دو چندگزینه‌ای. نمره ۸.',
            'listening_repetition' => false,
        ],
        4 => [
            'no' => 4,
            'label' => 'Quiz 4',
            'session' => 8,
            'mark' => 10,
            'minutes' => 10,
            'title' => 'Quiz 4 · Session 8',
            'kinds' => 'mc_fill',
            'mix' => ['grammar' => 5, 'vocab' => 5, 'phonetics' => 2],
            'question_count' => 12,
            'notes' => 'مثل Quiz 2 روی مطالب Session 8.',
            'listening_repetition' => false,
        ],
        5 => [
            'no' => 5,
            'label' => 'Quiz 5',
            'session' => 11,
            'mark' => 10,
            'minutes' => 15,
            'title' => 'Quiz 5 · Listening · Session 11',
            'kinds' => 'listening',
            'mix' => ['mc' => 6, 'short' => 4],
            'question_count' => 10,
            'notes' => 'اسکریپت لیسنینگ + سؤال‌های متنی. فایل صوتی TTS مرحله بعد است.',
            'listening_repetition' => false,
        ],
        6 => [
            'no' => 6,
            'label' => 'Quiz 6',
            'session' => 13,
            'mark' => 10,
            'minutes' => 10,
            'title' => 'Quiz 6 · Session 13',
            'kinds' => 'mc_fill',
            'mix' => ['grammar' => 8, 'vocab' => 7, 'phonetics' => 2],
            'question_count' => 17,
            'notes' => 'هشت گرامر، هفت واژگان، دو فونتیک در صورت وجود.',
            'listening_repetition' => false,
        ],
        7 => [
            'no' => 7,
            'label' => 'Quiz 7',
            'session' => 13,
            'mark' => 8,
            'minutes' => 15,
            'title' => 'Quiz 7 · Writing topic · Session 13',
            'kinds' => 'writing',
            'mix' => ['writing' => 1],
            'question_count' => 1,
            'notes' => 'از صفحات کتاب یک تاپیک می‌سازد. دانش‌آموز چند خط درباره‌اش می‌نویسد؛ نمره ۸ را شما می‌دهید. سؤال چندگزینه‌ای نیست.',
            'listening_repetition' => false,
        ],
    ];
}

function quiz_builder_template($no) {
    $all = quiz_builder_templates();
    $no = (int)$no;
    return isset($all[$no]) ? $all[$no] : null;
}

function quiz_builder_is_writing($template_no) {
    $tpl = quiz_builder_template($template_no);
    return $tpl && (($tpl['kinds'] ?? '') === 'writing');
}

function quiz_builder_force_writing_topic(array $payload, array $tpl) {
    $mark = max(1, (int)($tpl['mark'] ?? 8));
    $picked = null;
    foreach ($payload['questions'] ?? [] as $item) {
        if (!is_array($item)) {
            continue;
        }
        $item = quiz_builder_normalize_question($item, 0);
        if ($item['prompt'] === '') {
            continue;
        }
        if ($item['type'] === 'writing' || $item['skill'] === 'writing') {
            $picked = $item;
            break;
        }
        if ($picked === null) {
            $picked = $item;
        }
    }
    if ($picked) {
        $picked['id'] = 'q-1';
        $picked['type'] = 'writing';
        $picked['skill'] = 'writing';
        $picked['options'] = [];
        $picked['points'] = $mark;
        $picked['answer'] = '';
        $payload['questions'] = [$picked];
    } else {
        $payload['questions'] = [];
    }
    $payload['passage'] = '';
    $payload['listening_script'] = '';
    $payload['listening_repetition'] = '';
    $payload['warning'] = '';
    return $payload;
}

function quiz_builder_template_for_level($no, $level_key) {
    $tpl = quiz_builder_template($no);
    if (!$tpl) {
        return null;
    }
    $has_ph = quiz_builder_has_phonetics($level_key);
    if (isset($tpl['mix']['phonetics']) && !$has_ph) {
        $tpl['mix']['phonetics'] = 0;
        $tpl['question_count'] = (int)$tpl['question_count'] - 2;
        if ($tpl['question_count'] < 1) {
            $tpl['question_count'] = 1;
        }
    }
    $tpl['has_phonetics'] = $has_ph;
    $tpl['cefr'] = quiz_builder_cefr_band($level_key);
    return $tpl;
}

function quiz_builder_difficulties() {
    return [
        'easy' => 'آسان',
        'medium' => 'متوسط',
        'hard' => 'دشوار',
    ];
}

function quiz_builder_status_label($status) {
    $map = [
        'pending' => 'در انتظار استخراج',
        'extracting' => 'در حال استخراج',
        'ready' => 'آماده',
        'empty' => 'بدون متن',
        'failed' => 'ناموفق',
        'draft' => 'پیش‌نویس',
        'in_progress' => 'در حال پاسخ',
        'submitted' => 'تحویل‌شده',
        'graded' => 'نمره‌داده‌شده',
        'published' => 'ارسال‌شده به کلاس',
    ];
    $status = (string)$status;
    return $map[$status] ?? $status;
}

function quiz_builder_book_path(array $book) {
    $name = basename((string)($book['stored_name'] ?? ''));
    if ($name === '' || strpos($name, '..') !== false) {
        return '';
    }
    return quiz_builder_storage_dir() . '/' . $name;
}

function quiz_builder_find_book(PDO $pdo, $book_id) {
    $st = $pdo->prepare("SELECT * FROM quiz_books WHERE id = ? LIMIT 1");
    $st->execute([(int)$book_id]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function quiz_builder_books_for_level(PDO $pdo, $level_key) {
    $level_key = (string)$level_key;
    $rows = $pdo->query("
        SELECT b.*,
               (SELECT COUNT(*) FROM quiz_exams e WHERE e.book_id = b.id) AS exam_count
        FROM quiz_books b
        ORDER BY b.id DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
    $out = [];
    foreach ($rows as $row) {
        if (quiz_builder_book_covers_level($row, $level_key)) {
            $out[] = $row;
        }
    }
    return $out;
}

function quiz_builder_all_books(PDO $pdo) {
    return $pdo->query("
        SELECT b.*,
               u.full_name AS uploader_name,
               (SELECT COUNT(*) FROM quiz_exams e WHERE e.book_id = b.id) AS exam_count
        FROM quiz_books b
        LEFT JOIN users u ON u.id = b.uploaded_by
        ORDER BY IFNULL(b.course_key, b.level_key) ASC, b.id DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
}

function quiz_builder_public_book(array $book, $for_level = '') {
    $course = quiz_builder_course($book['course_key'] ?? '');
    $level_keys = $course ? array_keys($course['levels']) : [];
    if (!$level_keys && !empty($book['level_key'])) {
        $level_keys = [(string)$book['level_key']];
    }
    return [
        'id' => (int)$book['id'],
        'course_key' => (string)($book['course_key'] ?? ''),
        'course_label' => $course ? (string)$course['label'] : '',
        'levels_label' => $course ? quiz_builder_course_levels_label($course) : (string)($book['level_key'] ?? ''),
        'level_key' => (string)($book['level_key'] ?? ''),
        'unit_hint' => quiz_builder_unit_hint($book['course_key'] ?? '', $for_level),
        'original_name' => (string)$book['original_name'],
        'page_count' => (int)$book['page_count'],
        'extract_cursor' => (int)($book['extract_cursor'] ?? 0),
        'file_size' => (int)($book['file_size'] ?? 0),
        'extract_status' => (string)$book['extract_status'],
        'extract_error' => (string)($book['extract_error'] ?? ''),
        'exam_count' => (int)($book['exam_count'] ?? 0),
        'uploader_name' => (string)($book['uploader_name'] ?? ''),
        'created_at' => (string)($book['created_at'] ?? ''),
        'status_label' => quiz_builder_status_label($book['extract_status'] ?? ''),
    ];
}

function quiz_builder_excerpt(PDO $pdo, $book_id, $from, $to, $max_chars = 14000) {
    $from = max(1, (int)$from);
    $to = max($from, (int)$to);
    $st = $pdo->prepare("
        SELECT page_no, body
        FROM quiz_book_pages
        WHERE book_id = ? AND page_no BETWEEN ? AND ?
        ORDER BY page_no ASC
    ");
    $st->execute([(int)$book_id, $from, $to]);
    $chunks = [];
    $used = 0;
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $body = trim((string)($row['body'] ?? ''));
        if ($body === '') {
            continue;
        }
        $piece = "--- Page " . (int)$row['page_no'] . " ---\n" . $body;
        $len = function_exists('mb_strlen') ? mb_strlen($piece, 'UTF-8') : strlen($piece);
        if ($used + $len > $max_chars) {
            $remain = $max_chars - $used;
            if ($remain > 80) {
                $piece = function_exists('mb_substr')
                    ? mb_substr($piece, 0, $remain, 'UTF-8')
                    : substr($piece, 0, $remain);
                $chunks[] = $piece . "\n[truncated]";
            }
            break;
        }
        $chunks[] = $piece;
        $used += $len;
    }
    return trim(implode("\n\n", $chunks));
}

function quiz_builder_page_preview(PDO $pdo, $book_id, $limit = 3) {
    $st = $pdo->prepare("
        SELECT page_no, body FROM quiz_book_pages
        WHERE book_id = ? AND CHAR_LENGTH(IFNULL(body,'')) > 0
        ORDER BY page_no ASC
        LIMIT " . (int)$limit . "
    ");
    $st->execute([(int)$book_id]);
    $out = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $body = trim((string)$row['body']);
        $snip = function_exists('mb_substr') ? mb_substr($body, 0, 220, 'UTF-8') : substr($body, 0, 220);
        $out[] = [
            'page_no' => (int)$row['page_no'],
            'snippet' => $snip,
        ];
    }
    return $out;
}

function quiz_builder_replace_pages(PDO $pdo, $book_id, array $pages) {
    $pdo->prepare("DELETE FROM quiz_book_pages WHERE book_id = ?")->execute([(int)$book_id]);
    return quiz_builder_upsert_pages($pdo, $book_id, $pages);
}

function quiz_builder_upsert_pages(PDO $pdo, $book_id, array $pages) {
    $ins = $pdo->prepare("
        INSERT INTO quiz_book_pages (book_id, page_no, body)
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE body = VALUES(body)
    ");
    $n = 0;
    $with_text = 0;
    foreach ($pages as $no => $body) {
        $no = (int)$no;
        if ($no < 1) {
            continue;
        }
        $text = trim((string)$body);
        $ins->execute([(int)$book_id, $no, $text]);
        $n++;
        if ($text !== '') {
            $with_text++;
        }
    }
    return ['pages' => $n, 'with_text' => $with_text];
}

function quiz_builder_pages_with_text(PDO $pdo, $book_id) {
    $st = $pdo->prepare("
        SELECT COUNT(*) FROM quiz_book_pages
        WHERE book_id = ? AND CHAR_LENGTH(IFNULL(body,'')) > 0
    ");
    $st->execute([(int)$book_id]);
    return (int)$st->fetchColumn();
}

function quiz_builder_find_exam(PDO $pdo, $exam_id, $teacher_id = 0) {
    $sql = "SELECT * FROM quiz_exams WHERE id = ?";
    $params = [(int)$exam_id];
    if ((int)$teacher_id > 0) {
        $sql .= " AND teacher_id = ?";
        $params[] = (int)$teacher_id;
    }
    $sql .= " LIMIT 1";
    $st = $pdo->prepare($sql);
    $st->execute($params);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function quiz_builder_class_exams(PDO $pdo, $class_id, $teacher_id) {
    $st = $pdo->prepare("
        SELECT e.*, b.original_name AS book_name, b.level_key
        FROM quiz_exams e
        LEFT JOIN quiz_books b ON b.id = e.book_id
        WHERE e.class_id = ? AND e.teacher_id = ?
        ORDER BY e.template_no ASC, e.id DESC
    ");
    $st->execute([(int)$class_id, (int)$teacher_id]);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

function quiz_builder_decode_payload($raw) {
    if (is_array($raw)) {
        return $raw;
    }
    $raw = trim((string)$raw);
    if ($raw === '') {
        return ['questions' => []];
    }
    $data = json_decode($raw, true);
    return is_array($data) ? $data : ['questions' => []];
}

function quiz_builder_public_exam(array $exam) {
    $payload = quiz_builder_decode_payload($exam['questions_json'] ?? '');
    $tpl = quiz_builder_template((int)($exam['template_no'] ?? 0));
    if ($tpl && ($tpl['kinds'] ?? '') === 'writing') {
        $payload = quiz_builder_force_writing_topic($payload, $tpl);
    }
    return [
        'id' => (int)$exam['id'],
        'class_id' => (int)$exam['class_id'],
        'book_id' => (int)$exam['book_id'],
        'book_name' => (string)($exam['book_name'] ?? ''),
        'template_no' => (int)$exam['template_no'],
        'template_label' => $tpl ? $tpl['label'] : ('Quiz ' . (int)$exam['template_no']),
        'kinds' => $tpl ? (string)($tpl['kinds'] ?? '') : '',
        'is_writing' => $tpl && (($tpl['kinds'] ?? '') === 'writing'),
        'session_no' => (int)($exam['session_no'] ?? ($tpl['session'] ?? 0)),
        'mark' => (int)($tpl['mark'] ?? 0),
        'minutes' => (int)($tpl['minutes'] ?? 0),
        'difficulty' => (string)$exam['difficulty'],
        'page_from' => (int)$exam['page_from'],
        'page_to' => (int)$exam['page_to'],
        'status' => (string)$exam['status'],
        'status_label' => ((string)($exam['status'] ?? '') === 'ready')
            ? 'ارسال‌شده به کلاس'
            : quiz_builder_status_label($exam['status'] ?? 'draft'),
        'updated_at' => (string)($exam['updated_at'] ?? ''),
        'passage' => (string)($payload['passage'] ?? ''),
        'listening_script' => (string)($payload['listening_script'] ?? ''),
        'listening_repetition' => (string)($payload['listening_repetition'] ?? ''),
        'warning' => (string)($payload['warning'] ?? ''),
        'questions' => isset($payload['questions']) && is_array($payload['questions'])
            ? array_values($payload['questions'])
            : [],
    ];
}

function quiz_builder_normalize_question(array $q, $index) {
    $type = strtolower(trim((string)($q['type'] ?? 'mc')));
    if (!in_array($type, ['mc', 'fill', 'tf', 'short', 'writing'], true)) {
        $type = 'mc';
    }
    $skill = strtolower(trim((string)($q['skill'] ?? '')));
    if (!in_array($skill, ['grammar', 'vocab', 'phonetics', 'reading', 'listening', 'writing'], true)) {
        $skill = 'grammar';
    }
    $options = [];
    if (!empty($q['options']) && is_array($q['options'])) {
        foreach ($q['options'] as $opt) {
            $opt = trim((string)$opt);
            if ($opt !== '') {
                $options[] = $opt;
            }
        }
    }
    if ($type === 'tf' && count($options) < 2) {
        $options = ['True', 'False'];
    }
    $id = trim((string)($q['id'] ?? ''));
    if ($id === '') {
        $id = 'q-' . ($index + 1);
    }
    return [
        'id' => $id,
        'skill' => $skill,
        'type' => $type,
        'prompt' => trim((string)($q['prompt'] ?? '')),
        'options' => $options,
        'answer' => trim((string)($q['answer'] ?? '')),
        'points' => max(1, (int)($q['points'] ?? 1)),
    ];
}

function quiz_builder_encode_payload(array $payload) {
    if (isset($payload['questions']) && is_array($payload['questions'])) {
        $norm = [];
        foreach (array_values($payload['questions']) as $i => $q) {
            if (!is_array($q)) {
                continue;
            }
            $item = quiz_builder_normalize_question($q, $i);
            if ($item['prompt'] === '') {
                continue;
            }
            $norm[] = $item;
        }
        $payload['questions'] = $norm;
    }
    foreach (['passage', 'listening_script', 'listening_repetition', 'warning'] as $k) {
        if (isset($payload[$k])) {
            $payload[$k] = trim((string)$payload[$k]);
        }
    }
    return json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function quiz_builder_save_exam(PDO $pdo, array $row) {
    $st = $pdo->prepare("
        INSERT INTO quiz_exams (
            class_id, teacher_id, book_id, template_no, session_no,
            difficulty, page_from, page_to, questions_json, status
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $st->execute([
        (int)$row['class_id'],
        (int)$row['teacher_id'],
        (int)$row['book_id'],
        (int)$row['template_no'],
        (int)$row['session_no'],
        (string)$row['difficulty'],
        (int)$row['page_from'],
        (int)$row['page_to'],
        (string)$row['questions_json'],
        (string)($row['status'] ?? 'draft'),
    ]);
    return (int)$pdo->lastInsertId();
}

function quiz_builder_update_exam(PDO $pdo, $exam_id, array $fields) {
    $allowed = [
        'book_id', 'template_no', 'session_no', 'difficulty',
        'page_from', 'page_to', 'questions_json', 'status',
    ];
    $sets = [];
    $params = [];
    foreach ($allowed as $col) {
        if (array_key_exists($col, $fields)) {
            $sets[] = $col . ' = ?';
            $params[] = $fields[$col];
        }
    }
    if (!$sets) {
        return;
    }
    $params[] = (int)$exam_id;
    $pdo->prepare("UPDATE quiz_exams SET " . implode(', ', $sets) . " WHERE id = ?")->execute($params);
}

function quiz_builder_delete_exam(PDO $pdo, $exam_id, $teacher_id) {
    $exam = quiz_builder_find_exam($pdo, $exam_id, $teacher_id);
    if (!$exam) {
        return;
    }
    $pdo->prepare("DELETE FROM quiz_attempts WHERE exam_id = ?")->execute([(int)$exam_id]);
    $pdo->prepare("DELETE FROM quiz_exams WHERE id = ? AND teacher_id = ?")
        ->execute([(int)$exam_id, (int)$teacher_id]);
}

function quiz_builder_delete_book(PDO $pdo, $book_id) {
    $book = quiz_builder_find_book($pdo, $book_id);
    if (!$book) {
        throw new Exception('کتاب پیدا نشد.');
    }
    $used = (int)$pdo->query("SELECT COUNT(*) FROM quiz_exams WHERE book_id = " . (int)$book_id)->fetchColumn();
    if ($used > 0) {
        throw new Exception('این کتاب در آزمون معلم‌ها استفاده شده و حذف نمی‌شود. اول آزمون‌ها را پاک کنید.');
    }
    $path = quiz_builder_book_path($book);
    $pdo->prepare("DELETE FROM quiz_book_pages WHERE book_id = ?")->execute([(int)$book_id]);
    $pdo->prepare("DELETE FROM quiz_books WHERE id = ?")->execute([(int)$book_id]);
    if ($path !== '' && is_file($path)) {
        @unlink($path);
    }
}

function quiz_builder_teacher_classes(PDO $pdo, $teacher_id) {
    if (!function_exists('term_plan_teacher_classes')) {
        require_once __DIR__ . '/term-plan.php';
    }
    $classes = term_plan_teacher_classes($pdo, $teacher_id);
    foreach ($classes as &$class) {
        $class['level_key'] = quiz_builder_detect_level_key($class['title'] ?? '');
    }
    unset($class);
    return $classes;
}
