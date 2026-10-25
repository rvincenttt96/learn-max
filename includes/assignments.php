<?php
/**
 * Per-session class assignments with student file upload.
 * Separate from homework_status (attendance ticks) and Quiz Marks.
 */

function assignments_h($s) {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function assignments_json($payload, $code = 200) {
    http_response_code((int)$code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function assignments_session_max() {
    return 14;
}

function assignments_ini_bytes($val) {
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

function assignments_upload_limit() {
    $hard = 8 * 1024 * 1024;
    $candidates = [
        assignments_ini_bytes(ini_get('upload_max_filesize')),
        assignments_ini_bytes(ini_get('post_max_size')),
        $hard,
    ];
    $candidates = array_filter($candidates);
    return $candidates ? min($candidates) : $hard;
}

function assignments_bytes_label($n) {
    $n = (int)$n;
    if ($n < 1024) {
        return $n . ' B';
    }
    if ($n < 1024 * 1024) {
        return round($n / 1024, 1) . ' KB';
    }
    return round($n / (1024 * 1024), 1) . ' MB';
}

function assignments_htaccess_body() {
    return "<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n"
        . "<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n";
}

function assignments_storage_dir() {
    $dir = dirname(__DIR__) . '/uploads/assignments';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    $ht = $dir . '/.htaccess';
    if (!is_file($ht)) {
        @file_put_contents($ht, assignments_htaccess_body());
    }
    $deny = $dir . '/index.html';
    if (!is_file($deny)) {
        @file_put_contents($deny, '');
    }
    return $dir;
}

function assignments_fail_fast(PDO $pdo) {
    try {
        $pdo->exec('SET SESSION lock_wait_timeout = 3');
    } catch (Exception $e) {
        // ignore
    }
    try {
        $pdo->exec('SET SESSION innodb_lock_wait_timeout = 3');
    } catch (Exception $e) {
        // ignore
    }
}

function assignments_ensure_schema(PDO $pdo) {
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    assignments_storage_dir();
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS assignments (
                id INT AUTO_INCREMENT PRIMARY KEY,
                class_id INT NOT NULL,
                teacher_id INT NOT NULL,
                session_no TINYINT UNSIGNED NOT NULL,
                title VARCHAR(180) NOT NULL,
                body MEDIUMTEXT,
                teacher_stored_name VARCHAR(255) NULL,
                teacher_original_name VARCHAR(255) NULL,
                teacher_mime VARCHAR(80) NULL,
                teacher_size INT NOT NULL DEFAULT 0,
                due_at DATE NULL,
                status VARCHAR(16) NOT NULL DEFAULT 'published',
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                KEY idx_asg_class (class_id),
                KEY idx_asg_teacher (teacher_id),
                KEY idx_asg_session (class_id, session_no)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS assignment_submissions (
                id INT AUTO_INCREMENT PRIMARY KEY,
                assignment_id INT NOT NULL,
                class_id INT NOT NULL,
                student_id INT NOT NULL,
                stored_name VARCHAR(255) NOT NULL,
                original_name VARCHAR(255) NOT NULL,
                mime VARCHAR(80) NOT NULL DEFAULT '',
                file_size INT NOT NULL DEFAULT 0,
                submitted_at DATETIME NULL,
                UNIQUE KEY uq_asg_student (assignment_id, student_id),
                KEY idx_asub_class (class_id),
                KEY idx_asub_student (student_id),
                KEY idx_asub_asg (assignment_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    } catch (Exception $e) {
        error_log('assignments schema: ' . $e->getMessage());
        throw $e;
    }
}

function assignments_today() {
    $tz = date_default_timezone_get();
    if ($tz !== 'Asia/Tehran') {
        date_default_timezone_set('Asia/Tehran');
    }
    return date('Y-m-d');
}

function assignments_now() {
    $tz = date_default_timezone_get();
    if ($tz !== 'Asia/Tehran') {
        date_default_timezone_set('Asia/Tehran');
    }
    return date('Y-m-d H:i:s');
}

function assignments_human_date($ymd) {
    $ymd = trim((string)$ymd);
    if ($ymd === '' || strpos($ymd, '0000-00-00') === 0) {
        return '';
    }
    $ts = strtotime(substr($ymd, 0, 10) . ' 12:00:00');
    if (!$ts) {
        return $ymd;
    }
    if (function_exists('jdate')) {
        return jdate('Y/m/d', $ts);
    }
    $j = __DIR__ . '/jdate.php';
    if (is_file($j)) {
        require_once $j;
        if (function_exists('jdate')) {
            return jdate('Y/m/d', $ts);
        }
    }
    return date('Y/m/d', $ts);
}

function assignments_human_datetime($dt) {
    $dt = trim((string)$dt);
    if ($dt === '') {
        return '';
    }
    $ts = strtotime($dt);
    if (!$ts) {
        return $dt;
    }
    if (function_exists('jdate')) {
        return jdate('Y/m/d H:i', $ts);
    }
    $j = __DIR__ . '/jdate.php';
    if (is_file($j)) {
        require_once $j;
        if (function_exists('jdate')) {
            return jdate('Y/m/d H:i', $ts);
        }
    }
    return date('Y/m/d H:i', $ts);
}

function assignments_allowed_ext() {
    return ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'gif', 'doc', 'docx', 'zip'];
}

function assignments_mime_ok($mime, $ext) {
    $mime = strtolower(trim((string)$mime));
    $ext = strtolower(trim((string)$ext));
    $map = [
        'pdf' => ['application/pdf'],
        'jpg' => ['image/jpeg', 'image/pjpeg'],
        'jpeg' => ['image/jpeg', 'image/pjpeg'],
        'png' => ['image/png'],
        'webp' => ['image/webp'],
        'gif' => ['image/gif'],
        'doc' => ['application/msword', 'application/octet-stream'],
        'docx' => [
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/zip',
            'application/octet-stream',
        ],
        'zip' => ['application/zip', 'application/x-zip-compressed', 'application/octet-stream'],
    ];
    if (!isset($map[$ext])) {
        return false;
    }
    if ($mime === '' || $mime === 'application/octet-stream') {
        return true;
    }
    return in_array($mime, $map[$ext], true);
}

function assignments_ext($name) {
    $name = strtolower((string)$name);
    $pos = strrpos($name, '.');
    if ($pos === false) {
        return '';
    }
    return preg_replace('/[^a-z0-9]/', '', substr($name, $pos + 1));
}

function assignments_safe_orig($name) {
    $name = basename(str_replace('\\', '/', (string)$name));
    $name = preg_replace('/[\x00-\x1F\x7F]/', '', $name);
    if (function_exists('mb_substr')) {
        $name = mb_substr($name, 0, 180, 'UTF-8');
    } else {
        $name = substr($name, 0, 180);
    }
    $name = trim($name);
    return $name !== '' ? $name : 'file';
}

function assignments_file_path($stored) {
    $name = basename((string)$stored);
    if ($name === '' || $name !== (string)$stored || strpos($name, '..') !== false) {
        return '';
    }
    return assignments_storage_dir() . '/' . $name;
}

function assignments_delete_stored($stored) {
    $path = assignments_file_path($stored);
    if ($path !== '' && is_file($path)) {
        @unlink($path);
    }
}

function assignments_store_upload(array $file, $prefix) {
    $limit = assignments_upload_limit();
    $err = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($err === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE) {
        throw new Exception('حجم فایل از سقف سرور بیشتر است (حداکثر ' . assignments_bytes_label($limit) . ').');
    }
    if ($err !== UPLOAD_ERR_OK) {
        throw new Exception('آپلود فایل انجام نشد.');
    }
    $tmp = (string)($file['tmp_name'] ?? '');
    if ($tmp === '' || !is_uploaded_file($tmp)) {
        throw new Exception('فایل آپلود معتبر نیست.');
    }
    $size = (int)($file['size'] ?? 0);
    if ($size <= 0) {
        throw new Exception('فایل خالی است.');
    }
    if ($size > $limit) {
        throw new Exception('حجم فایل حداکثر ' . assignments_bytes_label($limit) . ' است.');
    }
    $orig = assignments_safe_orig($file['name'] ?? 'file');
    $ext = assignments_ext($orig);
    if (!in_array($ext, assignments_allowed_ext(), true)) {
        throw new Exception('فقط PDF، تصویر، Word یا ZIP پذیرفته می‌شود.');
    }
    $mime = '';
    if (function_exists('finfo_open')) {
        $fi = finfo_open(FILEINFO_MIME_TYPE);
        if ($fi) {
            $mime = (string)finfo_file($fi, $tmp);
            finfo_close($fi);
        }
    }
    if ($mime === '') {
        $mime = (string)($file['type'] ?? '');
    }
    if (!assignments_mime_ok($mime, $ext)) {
        throw new Exception('نوع فایل مجاز نیست.');
    }
    $stored = preg_replace('/[^a-z0-9_-]/i', '', (string)$prefix) . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
    $dest = assignments_storage_dir() . '/' . $stored;
    if (!move_uploaded_file($tmp, $dest)) {
        throw new Exception('ذخیره فایل روی سرور انجام نشد.');
    }
    @chmod($dest, 0644);
    return [
        'stored_name' => $stored,
        'original_name' => $orig,
        'mime' => $mime !== '' ? $mime : 'application/octet-stream',
        'file_size' => $size,
    ];
}

function assignments_disposition($orig) {
    $orig = assignments_safe_orig($orig);
    $ascii = preg_replace('/[^\x20-\x7E]/', '_', $orig);
    $ascii = str_replace('"', '', $ascii);
    if ($ascii === '' || $ascii === '_') {
        $ascii = 'file';
    }
    return 'attachment; filename="' . $ascii . '"; filename*=UTF-8\'\'' . rawurlencode($orig);
}

function assignments_send_file($stored, $orig, $mime) {
    $path = assignments_file_path($stored);
    if ($path === '' || !is_file($path)) {
        http_response_code(404);
        echo 'فایل پیدا نشد.';
        exit;
    }
    $mime = trim((string)$mime);
    if ($mime === '') {
        $mime = 'application/octet-stream';
    }
    header('X-Content-Type-Options: nosniff');
    header('Content-Type: ' . $mime);
    header('Content-Disposition: ' . assignments_disposition($orig));
    header('Content-Length: ' . filesize($path));
    header('Cache-Control: private, no-store');
    readfile($path);
    exit;
}

function assignments_teacher_classes(PDO $pdo, $teacher_id) {
    if (!function_exists('term_plan_teacher_classes')) {
        require_once __DIR__ . '/term-plan.php';
    }
    return term_plan_teacher_classes($pdo, $teacher_id);
}

function assignments_find_class(PDO $pdo, $class_id, $teacher_id = 0) {
    if (!function_exists('term_plan_find_class')) {
        require_once __DIR__ . '/term-plan.php';
    }
    return term_plan_find_class($pdo, $class_id, $teacher_id);
}

function assignments_class_sql() {
    if (!function_exists('term_sql_teaching')) {
        require_once __DIR__ . '/terms.php';
    }
    return "c.is_deleted = 0 AND IFNULL(c.is_archived, 0) = 0 AND " . term_sql_teaching('t');
}

function assignments_student_in_class(PDO $pdo, $student_id, $class_id) {
    $st = $pdo->prepare("
        SELECT c.id
        FROM classes c
        JOIN class_students cs ON cs.class_id = c.id
        JOIN terms t ON t.id = c.term_id
        WHERE c.id = ? AND cs.user_id = ? AND " . assignments_class_sql() . "
        LIMIT 1
    ");
    $st->execute([(int)$class_id, (int)$student_id]);
    return (bool)$st->fetchColumn();
}

function assignments_class_roster(PDO $pdo, $class_id) {
    $st = $pdo->prepare("
        SELECT u.id, u.full_name, u.username
        FROM users u
        JOIN class_students cs ON cs.user_id = u.id
        WHERE cs.class_id = ?
        ORDER BY u.full_name ASC
    ");
    $st->execute([(int)$class_id]);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

function assignments_class_student_count(PDO $pdo, $class_id) {
    $st = $pdo->prepare("SELECT COUNT(*) FROM class_students WHERE class_id = ?");
    $st->execute([(int)$class_id]);
    return (int)$st->fetchColumn();
}

function assignments_due_state(array $row) {
    $due = trim((string)($row['due_at'] ?? ''));
    if ($due === '' || strpos($due, '0000-00-00') === 0) {
        return ['overdue' => false, 'late_submit' => false];
    }
    $due = substr($due, 0, 10);
    $submitted = trim((string)($row['submitted_at'] ?? ''));
    $has = $submitted !== '' || !empty($row['submission_id']) || trim((string)($row['sub_stored_name'] ?? '')) !== '';
    $late = false;
    if ($has && $submitted !== '') {
        $late = substr($submitted, 0, 10) > $due;
    }
    return [
        'overdue' => !$has && assignments_today() > $due,
        'late_submit' => $late,
    ];
}

function assignments_status_label($key) {
    $map = [
        'pending' => 'تحویل نشده',
        'submitted' => 'تحویل شد',
        'late' => 'دیر تحویل شد',
        'overdue' => 'مهلت گذشته',
    ];
    return $map[$key] ?? 'تحویل نشده';
}

function assignments_public_teacher(array $row) {
    return [
        'id' => (int)$row['id'],
        'class_id' => (int)$row['class_id'],
        'session_no' => (int)$row['session_no'],
        'title' => (string)$row['title'],
        'body' => (string)($row['body'] ?? ''),
        'due_at' => (!empty($row['due_at']) && strpos((string)$row['due_at'], '0000-00-00') !== 0)
            ? substr((string)$row['due_at'], 0, 10)
            : null,
        'due_label' => assignments_human_date($row['due_at'] ?? ''),
        'has_teacher_file' => trim((string)($row['teacher_stored_name'] ?? '')) !== '',
        'teacher_file_name' => (string)($row['teacher_original_name'] ?? ''),
        'teacher_file_size_label' => assignments_bytes_label((int)($row['teacher_size'] ?? 0)),
        'submission_count' => (int)($row['submission_count'] ?? 0),
        'student_count' => (int)($row['student_count'] ?? 0),
        'created_at' => (string)($row['created_at'] ?? ''),
        'created_label' => assignments_human_datetime($row['created_at'] ?? ''),
    ];
}

function assignments_public_student(array $row) {
    $due = assignments_due_state($row);
    $has = !empty($row['submission_id']) || trim((string)($row['sub_stored_name'] ?? '')) !== '';
    $key = !$has ? ($due['overdue'] ? 'overdue' : 'pending') : ($due['late_submit'] ? 'late' : 'submitted');
    return [
        'id' => (int)$row['id'],
        'class_id' => (int)$row['class_id'],
        'class_title' => (string)($row['class_title'] ?? ''),
        'session_no' => (int)$row['session_no'],
        'title' => (string)$row['title'],
        'body' => (string)($row['body'] ?? ''),
        'due_at' => (!empty($row['due_at']) && strpos((string)$row['due_at'], '0000-00-00') !== 0)
            ? substr((string)$row['due_at'], 0, 10)
            : null,
        'due_label' => assignments_human_date($row['due_at'] ?? ''),
        'has_teacher_file' => trim((string)($row['teacher_stored_name'] ?? '')) !== '',
        'teacher_file_name' => (string)($row['teacher_original_name'] ?? ''),
        'teacher_file_size_label' => assignments_bytes_label((int)($row['teacher_size'] ?? 0)),
        'submitted' => $has,
        'submitted_at' => $has ? (string)($row['submitted_at'] ?? '') : null,
        'submitted_label' => $has ? assignments_human_datetime($row['submitted_at'] ?? '') : '',
        'file_name' => (string)($row['sub_original_name'] ?? ''),
        'file_size_label' => assignments_bytes_label((int)($row['sub_file_size'] ?? 0)),
        'status_key' => $key,
        'status_label' => assignments_status_label($key),
        'cta' => $has ? 'مشاهده و جایگزینی فایل' : 'تحویل تکلیف',
    ];
}

function assignments_class_rows(PDO $pdo, $class_id, $teacher_id) {
    $st = $pdo->prepare("
        SELECT a.*,
               (SELECT COUNT(*) FROM assignment_submissions s WHERE s.assignment_id = a.id) AS submission_count
        FROM assignments a
        WHERE a.class_id = ? AND a.teacher_id = ?
        ORDER BY a.session_no ASC, a.id DESC
    ");
    $st->execute([(int)$class_id, (int)$teacher_id]);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);
    $n = assignments_class_student_count($pdo, $class_id);
    foreach ($rows as &$row) {
        $row['student_count'] = $n;
    }
    unset($row);
    return $rows;
}

function assignments_find_teacher(PDO $pdo, $id, $teacher_id) {
    $st = $pdo->prepare("
        SELECT a.*,
               (SELECT COUNT(*) FROM assignment_submissions s WHERE s.assignment_id = a.id) AS submission_count
        FROM assignments a
        WHERE a.id = ? AND a.teacher_id = ?
        LIMIT 1
    ");
    $st->execute([(int)$id, (int)$teacher_id]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return null;
    }
    $row['student_count'] = assignments_class_student_count($pdo, (int)$row['class_id']);
    return $row;
}

function assignments_parse_due($raw) {
    $raw = trim((string)$raw);
    if ($raw === '') {
        return null;
    }
    if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $raw, $m)) {
        throw new Exception('تاریخ مهلت نامعتبر است.');
    }
    if (!checkdate((int)$m[2], (int)$m[3], (int)$m[1])) {
        throw new Exception('تاریخ مهلت نامعتبر است.');
    }
    return $raw;
}

function assignments_clip($s, $max) {
    $s = trim((string)$s);
    if (function_exists('mb_substr')) {
        return mb_substr($s, 0, $max, 'UTF-8');
    }
    return substr($s, 0, $max);
}

function assignments_save(PDO $pdo, $teacher_id, $class_id, array $input, $file = null) {
    $id = (int)($input['id'] ?? 0);
    $session = (int)($input['session_no'] ?? 0);
    if ($session < 1 || $session > assignments_session_max()) {
        throw new Exception('شماره جلسه باید بین ۱ و ۱۴ باشد.');
    }
    $title = assignments_clip($input['title'] ?? '', 180);
    if ($title === '' || (function_exists('mb_strlen') ? mb_strlen($title, 'UTF-8') : strlen($title)) < 2) {
        throw new Exception('عنوان تکلیف را بنویسید.');
    }
    $body = assignments_clip($input['body'] ?? '', 8000);
    $due = assignments_parse_due($input['due_at'] ?? '');
    $class = assignments_find_class($pdo, $class_id, $teacher_id);
    if (!$class) {
        throw new Exception('کلاس یافت نشد.');
    }

    $existing = null;
    if ($id > 0) {
        $existing = assignments_find_teacher($pdo, $id, $teacher_id);
        if (!$existing || (int)$existing['class_id'] !== (int)$class_id) {
            throw new Exception('این تکلیف پیدا نشد.');
        }
    }

    $upload = null;
    if (is_array($file) && (int)($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        $prefix = $id > 0 ? ('t' . $id) : ('tnew' . $teacher_id);
        $upload = assignments_store_upload($file, $prefix);
    }

    if ($id > 0) {
        $st = $pdo->prepare("
            UPDATE assignments
            SET session_no = ?, title = ?, body = ?, due_at = ?, status = 'published'
            WHERE id = ? AND teacher_id = ?
        ");
        $st->execute([$session, $title, $body, $due, $id, (int)$teacher_id]);
        if ($upload) {
            assignments_delete_stored($existing['teacher_stored_name'] ?? '');
            $pdo->prepare("
                UPDATE assignments
                SET teacher_stored_name = ?, teacher_original_name = ?, teacher_mime = ?, teacher_size = ?
                WHERE id = ? AND teacher_id = ?
            ")->execute([
                $upload['stored_name'],
                $upload['original_name'],
                $upload['mime'],
                $upload['file_size'],
                $id,
                (int)$teacher_id,
            ]);
        }
        return assignments_find_teacher($pdo, $id, $teacher_id);
    }

    $st = $pdo->prepare("
        INSERT INTO assignments
            (class_id, teacher_id, session_no, title, body, due_at, status)
        VALUES (?, ?, ?, ?, ?, ?, 'published')
    ");
    $st->execute([(int)$class_id, (int)$teacher_id, $session, $title, $body, $due]);
    $new_id = (int)$pdo->lastInsertId();
    if ($upload) {
        $final = 't' . $new_id . '_' . bin2hex(random_bytes(4)) . '.' . assignments_ext($upload['stored_name']);
        $from = assignments_file_path($upload['stored_name']);
        $to = assignments_storage_dir() . '/' . $final;
        if ($from && is_file($from) && @rename($from, $to)) {
            $upload['stored_name'] = $final;
        }
        $pdo->prepare("
            UPDATE assignments
            SET teacher_stored_name = ?, teacher_original_name = ?, teacher_mime = ?, teacher_size = ?
            WHERE id = ?
        ")->execute([
            $upload['stored_name'],
            $upload['original_name'],
            $upload['mime'],
            $upload['file_size'],
            $new_id,
        ]);
    }
    return assignments_find_teacher($pdo, $new_id, $teacher_id);
}

function assignments_clear_teacher_file(PDO $pdo, $id, $teacher_id) {
    $row = assignments_find_teacher($pdo, $id, $teacher_id);
    if (!$row) {
        throw new Exception('این تکلیف پیدا نشد.');
    }
    assignments_delete_stored($row['teacher_stored_name'] ?? '');
    $pdo->prepare("
        UPDATE assignments
        SET teacher_stored_name = NULL, teacher_original_name = NULL, teacher_mime = NULL, teacher_size = 0
        WHERE id = ? AND teacher_id = ?
    ")->execute([(int)$id, (int)$teacher_id]);
    return assignments_find_teacher($pdo, $id, $teacher_id);
}

function assignments_delete(PDO $pdo, $id, $teacher_id) {
    $row = assignments_find_teacher($pdo, $id, $teacher_id);
    if (!$row) {
        throw new Exception('این تکلیف پیدا نشد.');
    }
    $st = $pdo->prepare("SELECT stored_name FROM assignment_submissions WHERE assignment_id = ?");
    $st->execute([(int)$id]);
    foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $stored) {
        assignments_delete_stored($stored);
    }
    $pdo->prepare("DELETE FROM assignment_submissions WHERE assignment_id = ?")->execute([(int)$id]);
    assignments_delete_stored($row['teacher_stored_name'] ?? '');
    $pdo->prepare("DELETE FROM assignments WHERE id = ? AND teacher_id = ?")->execute([(int)$id, (int)$teacher_id]);
}

function assignments_submissions(PDO $pdo, array $asg) {
    $roster = assignments_class_roster($pdo, (int)$asg['class_id']);
    $st = $pdo->prepare("SELECT * FROM assignment_submissions WHERE assignment_id = ?");
    $st->execute([(int)$asg['id']]);
    $by = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $by[(int)$row['student_id']] = $row;
    }
    $out = [];
    $done = 0;
    foreach ($roster as $stu) {
        $sub = $by[(int)$stu['id']] ?? null;
        $has = (bool)$sub;
        if ($has) {
            $done++;
        }
        $state = assignments_due_state([
            'due_at' => $asg['due_at'] ?? '',
            'submitted_at' => $sub['submitted_at'] ?? '',
            'submission_id' => $sub['id'] ?? 0,
            'sub_stored_name' => $sub['stored_name'] ?? '',
        ]);
        $key = !$has ? ($state['overdue'] ? 'overdue' : 'pending') : ($state['late_submit'] ? 'late' : 'submitted');
        $out[] = [
            'student_id' => (int)$stu['id'],
            'full_name' => (string)($stu['full_name'] ?? ''),
            'username' => (string)($stu['username'] ?? ''),
            'submitted' => $has,
            'submission_id' => $has ? (int)$sub['id'] : 0,
            'file_name' => $has ? (string)$sub['original_name'] : '',
            'file_size_label' => $has ? assignments_bytes_label((int)$sub['file_size']) : '',
            'submitted_label' => $has ? assignments_human_datetime($sub['submitted_at'] ?? '') : '',
            'status_key' => $key,
            'status_label' => assignments_status_label($key),
        ];
    }
    return [
        'rows' => $out,
        'submitted' => $done,
        'total' => count($roster),
    ];
}

function assignments_find_submission(PDO $pdo, $submission_id) {
    $st = $pdo->prepare("SELECT * FROM assignment_submissions WHERE id = ? LIMIT 1");
    $st->execute([(int)$submission_id]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function assignments_student_list_sql($with_sub) {
    $select = "
        SELECT a.id, a.class_id, a.teacher_id, a.session_no, a.title, a.body,
               a.teacher_stored_name, a.teacher_original_name, a.teacher_mime, a.teacher_size,
               a.due_at, a.status, a.created_at, c.title AS class_title
    ";
    if ($with_sub) {
        $select .= ",
               s.id AS submission_id, s.stored_name AS sub_stored_name,
               s.original_name AS sub_original_name, s.file_size AS sub_file_size,
               s.submitted_at";
    } else {
        $select .= ",
               NULL AS submission_id, '' AS sub_stored_name, '' AS sub_original_name,
               0 AS sub_file_size, NULL AS submitted_at";
    }
    $from = "
        FROM assignments a
        JOIN classes c ON c.id = a.class_id
        JOIN class_students cs ON cs.class_id = c.id AND cs.user_id = ?
        JOIN terms t ON t.id = c.term_id
    ";
    if ($with_sub) {
        $from .= " LEFT JOIN assignment_submissions s ON s.assignment_id = a.id AND s.student_id = ?";
    }
    return $select . $from . "
        WHERE a.status = 'published' AND " . assignments_class_sql() . "
        ORDER BY c.title ASC, a.session_no ASC, a.id DESC
    ";
}

function assignments_student_list(PDO $pdo, $student_id) {
    assignments_fail_fast($pdo);
    $sid = (int)$student_id;
    try {
        $st = $pdo->prepare(assignments_student_list_sql(true));
        $st->execute([$sid, $sid]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log('assignments_student_list: ' . $e->getMessage());
        try {
            $st = $pdo->prepare(assignments_student_list_sql(false));
            $st->execute([$sid]);
            return $st->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e2) {
            error_log('assignments_student_list fallback: ' . $e2->getMessage());
            return [];
        }
    }
}

function assignments_find_student(PDO $pdo, $id, $student_id) {
    assignments_fail_fast($pdo);
    $st = $pdo->prepare("
        SELECT a.*, c.title AS class_title,
               s.id AS submission_id, s.stored_name AS sub_stored_name,
               s.original_name AS sub_original_name, s.file_size AS sub_file_size,
               s.submitted_at
        FROM assignments a
        JOIN classes c ON c.id = a.class_id
        JOIN class_students cs ON cs.class_id = c.id AND cs.user_id = ?
        JOIN terms t ON t.id = c.term_id
        LEFT JOIN assignment_submissions s ON s.assignment_id = a.id AND s.student_id = ?
        WHERE a.id = ? AND a.status = 'published' AND " . assignments_class_sql() . "
        LIMIT 1
    ");
    $st->execute([(int)$student_id, (int)$student_id, (int)$id]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function assignments_submit(PDO $pdo, $student_id, $assignment_id, array $file) {
    $asg = assignments_find_student($pdo, $assignment_id, $student_id);
    if (!$asg) {
        throw new Exception('این تکلیف برای شما باز نیست.');
    }
    $upload = assignments_store_upload($file, 's' . (int)$student_id . 'a' . (int)$assignment_id);
    if (!$upload) {
        throw new Exception('یک فایل انتخاب کنید.');
    }
    $old = trim((string)($asg['sub_stored_name'] ?? ''));
    $now = assignments_now();
    $pdo->prepare("
        INSERT INTO assignment_submissions
            (assignment_id, class_id, student_id, stored_name, original_name, mime, file_size, submitted_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            stored_name = VALUES(stored_name),
            original_name = VALUES(original_name),
            mime = VALUES(mime),
            file_size = VALUES(file_size),
            submitted_at = VALUES(submitted_at)
    ")->execute([
        (int)$asg['id'],
        (int)$asg['class_id'],
        (int)$student_id,
        $upload['stored_name'],
        $upload['original_name'],
        $upload['mime'],
        $upload['file_size'],
        $now,
    ]);
    if ($old !== '' && $old !== $upload['stored_name']) {
        assignments_delete_stored($old);
    }
    return assignments_find_student($pdo, $assignment_id, $student_id);
}

function assignments_oversized_post() {
    $len = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
    return $len > 0 && empty($_POST) && empty($_FILES);
}
