<?php
/**
 * Supervisor Students Information: list, academic history, Excel import/export.
 */

require_once __DIR__ . '/quizzes.php';

function students_info_split_name($full_name) {
    if (function_exists('identity_split_name')) {
        return identity_split_name($full_name);
    }
    $full_name = trim(preg_replace('/\s+/u', ' ', (string)$full_name));
    if ($full_name === '') {
        return ['', ''];
    }
    $parts = preg_split('/\s+/u', $full_name);
    $first = array_shift($parts);
    $last = implode(' ', $parts);
    return [$first, $last];
}

function students_info_join_name($first, $last) {
    return trim(preg_replace('/\s+/u', ' ', trim((string)$first) . ' ' . trim((string)$last)));
}

function students_info_quiz_labels() {
    require_once __DIR__ . '/quizzes.php';
    return quizzes_labels();
}

function students_info_list(PDO $pdo, $q = '') {
    $q = trim((string)$q);
    $sql = "
        SELECT u.id, u.username, u.full_name, u.phone, u.avatar
        FROM users u
        WHERE u.role = 'student' AND u.is_deleted = 0
    ";
    $params = [];
    if ($q !== '') {
        $sql .= " AND (u.username LIKE ? OR u.full_name LIKE ? OR u.phone LIKE ?)";
        $like = '%' . $q . '%';
        $params = [$like, $like, $like];
    }
    $sql .= " ORDER BY CAST(u.username AS UNSIGNED), u.username, u.full_name";
    $st = $pdo->prepare($sql);
    $st->execute($params);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as &$row) {
        list($first, $last) = students_info_split_name($row['full_name'] ?? '');
        $row['first_name'] = $first;
        $row['last_name'] = $last;
        $row['file_number'] = (string)($row['username'] ?? '');
    }
    unset($row);
    return $rows;
}

function students_info_find_student(PDO $pdo, $user_id) {
    $st = $pdo->prepare("
        SELECT id, username, full_name, phone, avatar
        FROM users
        WHERE id = ? AND role = 'student' AND is_deleted = 0
        LIMIT 1
    ");
    $st->execute([(int)$user_id]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return null;
    }
    list($first, $last) = students_info_split_name($row['full_name'] ?? '');
    $row['first_name'] = $first;
    $row['last_name'] = $last;
    $row['file_number'] = (string)($row['username'] ?? '');
    return $row;
}

function students_info_update(PDO $pdo, $user_id, $file_number, $first_name, $last_name, $phone) {
    $student = students_info_find_student($pdo, $user_id);
    if (!$student) {
        throw new Exception('دانش‌آموز پیدا نشد.');
    }
    $file_number = trim((string)$file_number);
    $first_name = trim((string)$first_name);
    $last_name = trim((string)$last_name);
    $phone = trim((string)$phone);
    $full_name = students_info_join_name($first_name, $last_name);
    if ($full_name === '') {
        throw new Exception('اسم و فامیل را وارد کنید.');
    }
    if ($file_number === '') {
        $file_number = $student['file_number'];
    }
    $st = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id != ? LIMIT 1");
    $st->execute([$file_number, (int)$student['id']]);
    if ($st->fetch()) {
        throw new Exception('این شماره پرونده قبلاً استفاده شده است.');
    }
    $pdo->prepare("
        UPDATE users
        SET username = ?, full_name = ?, phone = ?
        WHERE id = ? AND role = 'student' AND is_deleted = 0
    ")->execute([$file_number, $full_name, $phone, (int)$student['id']]);

    try {
        require_once __DIR__ . '/identity-sync.php';
        identity_sync_student($pdo, [
            'source' => 'students-info-edit',
            'code' => $file_number,
            'previous_code' => $student['file_number'],
            'full_name' => $full_name,
            'phone' => $phone,
            'overwrite_name' => true,
        ]);
    } catch (Exception $e) {
        error_log('students-info edit identity: ' . $e->getMessage());
    }

    return students_info_find_student($pdo, (int)$student['id']);
}

function students_info_create(PDO $pdo, $file_number, $first_name, $last_name, $phone) {
    require_once __DIR__ . '/identity-sync.php';
    $file_number = trim((string)$file_number);
    $first_name = trim((string)$first_name);
    $last_name = trim((string)$last_name);
    $phone = trim((string)$phone);
    $full_name = students_info_join_name($first_name, $last_name);
    if ($full_name === '') {
        throw new Exception('اسم و فامیل را وارد کنید.');
    }
    if ($file_number === '') {
        $file_number = identity_next_code($pdo);
    }
    $st = $pdo->prepare("SELECT id, is_deleted FROM users WHERE username = ? LIMIT 1");
    $st->execute([$file_number]);
    $existing = $st->fetch(PDO::FETCH_ASSOC);
    if ($existing && (int)$existing['is_deleted'] === 0) {
        throw new Exception('این شماره پرونده قبلاً استفاده شده است.');
    }
    $password = $file_number;
    $hash = password_hash($password, PASSWORD_DEFAULT);
    if ($existing) {
        $pdo->prepare("
            UPDATE users
            SET full_name = ?, password = ?, role = 'student', phone = ?, is_deleted = 0
            WHERE id = ?
        ")->execute([$full_name, $hash, $phone, (int)$existing['id']]);
        $user_id = (int)$existing['id'];
    } else {
        $pdo->prepare("
            INSERT INTO users (full_name, username, password, role, phone, is_deleted)
            VALUES (?, ?, ?, 'student', ?, 0)
        ")->execute([$full_name, $file_number, $hash, $phone]);
        $user_id = (int)$pdo->lastInsertId();
    }
    try {
        identity_sync_student($pdo, [
            'source' => 'panel-create',
            'code' => $file_number,
            'full_name' => $full_name,
            'phone' => $phone,
            'password' => $password,
            'wp_reset_password' => true,
            'welcome_sms' => true,
        ]);
    } catch (Exception $e) {
        error_log('students-info add identity: ' . $e->getMessage());
    }
    return students_info_find_student($pdo, $user_id);
}

function students_info_delete(PDO $pdo, $user_id) {
    $student = students_info_find_student($pdo, $user_id);
    if (!$student) {
        throw new Exception('دانش‌آموز پیدا نشد.');
    }
    require_once __DIR__ . '/identity-sync.php';
    $result = identity_delete_panel_student($pdo, $student['file_number'], $student['full_name']);
    if (empty($result['deleted'])) {
        $pdo->prepare("UPDATE users SET is_deleted = 1 WHERE id = ? AND role = 'student'")
            ->execute([(int)$student['id']]);
    }
    return true;
}

function students_info_attendance_by_class(PDO $pdo, $user_id) {
    $st = $pdo->prepare("
        SELECT class_id, class_date, is_present, status
        FROM attendance
        WHERE user_id = ?
          AND class_date IS NOT NULL
          AND class_date != '0000-00-00'
          AND class_date >= '1900-01-01'
        ORDER BY class_date ASC
    ");
    $st->execute([(int)$user_id]);
    $by_class = [];
    while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
        $cid = (int)$row['class_id'];
        if (!isset($by_class[$cid])) {
            $by_class[$cid] = [];
        }
        $status = strtolower(trim((string)($row['status'] ?? '')));
        $present = ((int)$row['is_present'] === 1) || $status === 'present';
        $by_class[$cid][] = [
            'class_date' => $row['class_date'],
            'present' => $present,
            'status' => $present ? 'present' : ($status !== '' ? $status : 'absent'),
        ];
    }
    return $by_class;
}

function students_info_grade_totals(array $grade) {
    require_once __DIR__ . '/quizzes.php';
    return quizzes_grade_totals($grade);
}

function students_info_history(PDO $pdo, $user_id) {
    require_once __DIR__ . '/quizzes.php';
    quizzes_ensure_schema($pdo);
    $user_id = (int)$user_id;
    $st = $pdo->prepare("
        SELECT
            c.id AS class_id,
            c.title AS class_title,
            c.teacher_id,
            u_teacher.full_name AS teacher_name,
            t.id AS term_id,
            t.title AS term_title,
            t.is_archived,
            t.starts_on,
            t.created_at,
            " . quizzes_sql_columns('sq') . ",
            ss.supervisor_score,
            ss.is_submitted,
            ss.submitted_at,
            ml.communication_dev, ml.learning_eng, ml.learning_cons, ml.personal_growth
        FROM class_students cs
        JOIN classes c ON c.id = cs.class_id
        LEFT JOIN terms t ON c.term_id = t.id
        LEFT JOIN users u_teacher ON c.teacher_id = u_teacher.id
        LEFT JOIN student_quizzes sq ON cs.user_id = sq.user_id AND c.id = sq.class_id
        LEFT JOIN supervisor_scores ss ON cs.user_id = ss.user_id AND c.id = ss.class_id
        LEFT JOIN mind_lingo ml ON cs.user_id = ml.student_id AND c.id = ml.class_id
        WHERE cs.user_id = ?
          AND c.is_deleted = 0
          AND (t.id IS NULL OR t.is_deleted = 0 OR t.is_deleted IS NULL)
        ORDER BY COALESCE(t.starts_on, t.created_at) DESC, c.title
    ");
    $st->execute([$user_id]);
    $grades = $st->fetchAll(PDO::FETCH_ASSOC);
    $attendance = students_info_attendance_by_class($pdo, $user_id);
    foreach ($grades as &$grade) {
        $cid = (int)$grade['class_id'];
        $sessions = $attendance[$cid] ?? [];
        $present = 0;
        $absent = 0;
        foreach ($sessions as $s) {
            if (!empty($s['present'])) {
                $present++;
            } else {
                $absent++;
            }
        }
        $grade['sessions'] = $sessions;
        $grade['present_count'] = $present;
        $grade['absent_count'] = $absent;
        $grade['session_count'] = count($sessions);
        $grade['attendance_label'] = $grade['session_count']
            ? ($present . ' حاضر / ' . $absent . ' غایب')
            : 'ثبت نشده';
        $grade['totals'] = students_info_grade_totals($grade);
        $grade['level'] = (string)($grade['class_title'] ?? '');
    }
    unset($grade);
    return $grades;
}

function students_info_xml_escape($s) {
    return htmlspecialchars((string)$s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
}

function students_info_xlsx_col($index) {
    $n = (int)$index + 1;
    $s = '';
    while ($n > 0) {
        $n--;
        $s = chr(65 + ($n % 26)) . $s;
        $n = intdiv($n, 26);
    }
    return $s;
}

function students_info_sheet_xml(array $rows) {
    $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
    $xml .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';
    foreach ($rows as $r => $cols) {
        $row_num = $r + 1;
        $xml .= '<row r="' . $row_num . '">';
        foreach ($cols as $c => $val) {
            $ref = students_info_xlsx_col($c) . $row_num;
            $xml .= '<c r="' . $ref . '" t="inlineStr"><is><t xml:space="preserve">'
                . students_info_xml_escape($val) . '</t></is></c>';
        }
        $xml .= '</row>';
    }
    $xml .= '</sheetData></worksheet>';
    return $xml;
}

function students_info_write_xlsx($path, array $sheets) {
    $zip = new ZipArchive();
    if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        throw new Exception('نتوانست فایل اکسل ساخته شود.');
    }
    $names = array_keys($sheets);
    $types = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
        . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
        . '<Default Extension="xml" ContentType="application/xml"/>'
        . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>';
    $workbook = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
        . 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets>';
    $rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">';
    $i = 1;
    foreach ($names as $name) {
        $types .= '<Override PartName="/xl/worksheets/sheet' . $i . '.xml" '
            . 'ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        $workbook .= '<sheet name="' . students_info_xml_escape($name) . '" sheetId="' . $i . '" r:id="rId' . $i . '"/>';
        $rels .= '<Relationship Id="rId' . $i . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . $i . '.xml"/>';
        $zip->addFromString('xl/worksheets/sheet' . $i . '.xml', students_info_sheet_xml($sheets[$name]));
        $i++;
    }
    $types .= '</Types>';
    $workbook .= '</sheets></workbook>';
    $rels .= '</Relationships>';
    $zip->addFromString('[Content_Types].xml', $types);
    $zip->addFromString('_rels/.rels',
        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
        . '</Relationships>'
    );
    $zip->addFromString('xl/workbook.xml', $workbook);
    $zip->addFromString('xl/_rels/workbook.xml.rels', $rels);
    $zip->close();
}

function students_info_cell_value($c, array $shared) {
    $t = (string)($c['t'] ?? '');
    if ($t === 's') {
        $idx = (int)(string)$c->v;
        return (string)($shared[$idx] ?? '');
    }
    if ($t === 'inlineStr') {
        if (isset($c->is->t)) {
            return (string)$c->is->t;
        }
        $text = '';
        if (isset($c->is->r)) {
            foreach ($c->is->r as $r) {
                $text .= (string)$r->t;
            }
        }
        return $text;
    }
    if (isset($c->v)) {
        return (string)$c->v;
    }
    return '';
}

function students_info_parse_sheet_xml($xml, array $shared) {
    if ($xml === false || $xml === null || $xml === '') {
        return [];
    }
    $sx = @simplexml_load_string($xml);
    if (!$sx) {
        return [];
    }
    $sx->registerXPathNamespace('m', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
    $rows = [];
    $max_col = 0;
    foreach ($sx->sheetData->row as $row) {
        $ridx = ((int)$row['r']) - 1;
        if ($ridx < 0) {
            $ridx = count($rows);
        }
        if (!isset($rows[$ridx])) {
            $rows[$ridx] = [];
        }
        foreach ($row->c as $c) {
            $ref = (string)$c['r'];
            $col = 0;
            if (preg_match('/^([A-Z]+)/', $ref, $m)) {
                $letters = $m[1];
                $n = 0;
                $len = strlen($letters);
                for ($i = 0; $i < $len; $i++) {
                    $n = $n * 26 + (ord($letters[$i]) - 64);
                }
                $col = $n - 1;
            }
            $rows[$ridx][$col] = students_info_cell_value($c, $shared);
            if ($col > $max_col) {
                $max_col = $col;
            }
        }
    }
    if (!$rows) {
        return [];
    }
    ksort($rows);
    $out = [];
    foreach ($rows as $row) {
        $line = [];
        for ($i = 0; $i <= $max_col; $i++) {
            $line[] = isset($row[$i]) ? trim((string)$row[$i]) : '';
        }
        $out[] = $line;
    }
    return $out;
}

function students_info_read_xlsx($tmp) {
    $zip = new ZipArchive();
    if ($zip->open($tmp) !== true) {
        throw new Exception('فایل اکسل قابل خواندن نیست.');
    }
    $shared = [];
    $ss = $zip->getFromName('xl/sharedStrings.xml');
    if ($ss) {
        $sx = @simplexml_load_string($ss);
        if ($sx) {
            foreach ($sx->si as $si) {
                $text = '';
                if (isset($si->t)) {
                    $text = (string)$si->t;
                } elseif (isset($si->r)) {
                    foreach ($si->r as $r) {
                        $text .= (string)$r->t;
                    }
                }
                $shared[] = $text;
            }
        }
    }
    $wb_xml = $zip->getFromName('xl/workbook.xml');
    $rels_xml = $zip->getFromName('xl/_rels/workbook.xml.rels');
    $rel_map = [];
    if ($rels_xml) {
        $rx = @simplexml_load_string($rels_xml);
        if ($rx) {
            foreach ($rx->Relationship as $rel) {
                $rel_map[(string)$rel['Id']] = (string)$rel['Target'];
            }
        }
    }
    $sheets = [];
    $wb = $wb_xml ? @simplexml_load_string($wb_xml) : false;
    if ($wb && isset($wb->sheets->sheet)) {
        foreach ($wb->sheets->sheet as $sheet) {
            $name = (string)$sheet['name'];
            $rid = '';
            $attrs = $sheet->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships');
            if ($attrs && isset($attrs['id'])) {
                $rid = (string)$attrs['id'];
            }
            $target = $rel_map[$rid] ?? '';
            if ($target === '') {
                continue;
            }
            $target = ltrim(str_replace('\\', '/', $target), '/');
            if (strpos($target, 'xl/') !== 0) {
                $target = 'xl/' . $target;
            }
            $xml = $zip->getFromName($target);
            $sheets[$name] = students_info_parse_sheet_xml($xml, $shared);
        }
    }
    if (!$sheets) {
        $xml = $zip->getFromName('xl/worksheets/sheet1.xml');
        if ($xml) {
            $sheets['Students'] = students_info_parse_sheet_xml($xml, $shared);
        }
    }
    $zip->close();
    if (!$sheets) {
        throw new Exception('برگه‌ای در فایل اکسل پیدا نشد.');
    }
    return $sheets;
}

function students_info_header_key($raw) {
    $raw = trim((string)$raw);
    $norm = mb_strtolower($raw, 'UTF-8');
    $norm = str_replace(['‌', 'ـ'], '', $norm);
    $map = [
        'file_number' => 'file_number',
        'filenumber' => 'file_number',
        'username' => 'file_number',
        'شماره پرونده' => 'file_number',
        'کد' => 'file_number',
        'کد کاربری' => 'file_number',
        'first_name' => 'first_name',
        'firstname' => 'first_name',
        'اسم' => 'first_name',
        'نام' => 'first_name',
        'last_name' => 'last_name',
        'lastname' => 'last_name',
        'فامیل' => 'last_name',
        'نام خانوادگی' => 'last_name',
        'full_name' => 'full_name',
        'fullname' => 'full_name',
        'نام کامل' => 'full_name',
        'phone' => 'phone',
        'شماره تماس' => 'phone',
        'موبایل' => 'phone',
        'term' => 'term',
        'ترم' => 'term',
        'level' => 'level',
        'لول' => 'level',
        'سطح' => 'level',
        'class' => 'level',
        'کلاس' => 'level',
    ];
    if (isset($map[$norm])) {
        return $map[$norm];
    }
    $ascii = preg_replace('/[^a-z0-9_]+/', '', strtolower($raw));
    return $map[$ascii] ?? $ascii;
}

function students_info_rows_as_assoc(array $rows) {
    if (count($rows) < 2) {
        return [];
    }
    $headers = [];
    foreach ($rows[0] as $h) {
        $headers[] = students_info_header_key($h);
    }
    $out = [];
    for ($i = 1; $i < count($rows); $i++) {
        $row = $rows[$i];
        $item = [];
        $empty = true;
        foreach ($headers as $idx => $key) {
            if ($key === '') {
                continue;
            }
            $val = trim((string)($row[$idx] ?? ''));
            if ($val !== '') {
                $empty = false;
            }
            $item[$key] = $val;
        }
        if (!$empty) {
            $out[] = $item;
        }
    }
    return $out;
}

function students_info_student_headers() {
    return ['file_number', 'first_name', 'last_name', 'phone'];
}

function students_info_history_headers() {
    $headers = [
        'file_number', 'first_name', 'last_name', 'term', 'level', 'teacher',
        'present_count', 'absent_count', 'attendance',
    ];
    foreach (students_info_quiz_labels() as $i => $label) {
        $headers[] = 'quiz_' . $i;
    }
    $headers[] = 'supervisor_score';
    $headers[] = 'final_score';
    return $headers;
}

function students_info_export_sheets(PDO $pdo) {
    $students = students_info_list($pdo);
    $student_rows = [students_info_student_headers()];
    $history_rows = [students_info_history_headers()];
    foreach ($students as $s) {
        $student_rows[] = [
            $s['file_number'],
            $s['first_name'],
            $s['last_name'],
            $s['phone'] ?? '',
        ];
        $history = students_info_history($pdo, (int)$s['id']);
        if (!$history) {
            $empty = array_fill(0, count(students_info_history_headers()), '');
            $empty[0] = $s['file_number'];
            $empty[1] = $s['first_name'];
            $empty[2] = $s['last_name'];
            $history_rows[] = $empty;
            continue;
        }
        foreach ($history as $h) {
            $t = $h['totals'];
            $line = [
                $s['file_number'],
                $s['first_name'],
                $s['last_name'],
                $h['term_title'] ?? '',
                $h['level'] ?? '',
                $h['teacher_name'] ?? '',
                (string)$h['present_count'],
                (string)$h['absent_count'],
                $h['attendance_label'],
            ];
            for ($i = 1; $i <= quizzes_count(); $i++) {
                $v = $t['quizzes'][$i] ?? null;
                $line[] = ($v === null || $v === '') ? '' : (string)$v;
            }
            $line[] = $t['supervisor_score'] === null ? '' : (string)$t['supervisor_score'];
            $line[] = $t['has_quiz_scores'] ? (string)$t['final_score'] : '';
            $history_rows[] = $line;
        }
    }
    return [
        'Students' => $student_rows,
        'History' => $history_rows,
    ];
}

function students_info_template_sheets() {
    return [
        'Students' => [
            students_info_student_headers(),
            ['1110', 'Sara', 'Ahmadi', '09120000000'],
        ],
        'History' => [
            students_info_history_headers(),
        ],
    ];
}

function students_info_find_class(PDO $pdo, $term_title, $level_title) {
    $term_title = trim((string)$term_title);
    $level_title = trim((string)$level_title);
    if ($term_title === '' || $level_title === '') {
        return 0;
    }
    $st = $pdo->prepare("
        SELECT c.id
        FROM classes c
        JOIN terms t ON t.id = c.term_id
        WHERE c.is_deleted = 0
          AND (t.is_deleted = 0 OR t.is_deleted IS NULL)
          AND t.title = ?
          AND c.title = ?
        ORDER BY c.id DESC
        LIMIT 1
    ");
    $st->execute([$term_title, $level_title]);
    return (int)$st->fetchColumn();
}

function students_info_enroll(PDO $pdo, $user_id, $class_id) {
    $user_id = (int)$user_id;
    $class_id = (int)$class_id;
    if ($user_id <= 0 || $class_id <= 0) {
        return false;
    }
    $st = $pdo->prepare('SELECT 1 FROM class_students WHERE class_id = ? AND user_id = ? LIMIT 1');
    $st->execute([$class_id, $user_id]);
    if ($st->fetchColumn()) {
        return false;
    }
    $pdo->prepare('INSERT INTO class_students (class_id, user_id) VALUES (?, ?)')->execute([$class_id, $user_id]);
    return true;
}

function students_info_import(PDO $pdo, $tmp_path) {
    $sheets = students_info_read_xlsx($tmp_path);
    $student_sheet = null;
    foreach ($sheets as $name => $rows) {
        $key = mb_strtolower(trim((string)$name), 'UTF-8');
        if (in_array($key, ['students', 'دانش‌آموزان', 'دانش آموزان', 'students information'], true)) {
            $student_sheet = $rows;
            break;
        }
    }
    if ($student_sheet === null) {
        $student_sheet = reset($sheets);
    }
    $items = students_info_rows_as_assoc($student_sheet ?: []);
    if (!$items) {
        throw new Exception('در فایل اکسل سطری برای وارد کردن پیدا نشد.');
    }

    require_once __DIR__ . '/identity-sync.php';

    $created = 0;
    $updated = 0;
    $enrolled = 0;
    $errors = [];
    $line_no = 1;
    foreach ($items as $item) {
        $line_no++;
        $file = trim((string)($item['file_number'] ?? ''));
        $first = trim((string)($item['first_name'] ?? ''));
        $last = trim((string)($item['last_name'] ?? ''));
        $full = trim((string)($item['full_name'] ?? ''));
        $phone = trim((string)($item['phone'] ?? ''));
        if ($full === '') {
            $full = students_info_join_name($first, $last);
        }
        if ($full === '') {
            $errors[] = 'سطر ' . $line_no . ': اسم یا فامیل خالی است.';
            continue;
        }
        if ($file === '') {
            $file = identity_next_code($pdo);
        }
        $st = $pdo->prepare("SELECT id, full_name, phone FROM users WHERE username = ? AND role = 'student' LIMIT 1");
        $st->execute([$file]);
        $existing = $st->fetch(PDO::FETCH_ASSOC);
        $password = $file;
        try {
            if ($existing) {
                $pdo->prepare('UPDATE users SET full_name = ?, phone = ?, is_deleted = 0 WHERE id = ?')
                    ->execute([$full, $phone !== '' ? $phone : ($existing['phone'] ?? ''), (int)$existing['id']]);
                $user_id = (int)$existing['id'];
                $updated++;
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $pdo->prepare("
                    INSERT INTO users (full_name, username, password, role, phone, is_deleted)
                    VALUES (?, ?, ?, 'student', ?, 0)
                ")->execute([$full, $file, $hash, $phone]);
                $user_id = (int)$pdo->lastInsertId();
                $created++;
            }
            try {
                identity_sync_student($pdo, [
                    'source' => 'students-info-import',
                    'code' => $file,
                    'full_name' => $full,
                    'phone' => $phone,
                    'password' => $password,
                    'wp_reset_password' => empty($existing),
                    'welcome_sms' => empty($existing),
                ]);
            } catch (Exception $e) {
                error_log('students-info identity: ' . $e->getMessage());
            }
            $term = trim((string)($item['term'] ?? ''));
            $level = trim((string)($item['level'] ?? ''));
            if ($term !== '' && $level !== '') {
                $class_id = students_info_find_class($pdo, $term, $level);
                if ($class_id > 0) {
                    if (students_info_enroll($pdo, $user_id, $class_id)) {
                        $enrolled++;
                    }
                } else {
                    $errors[] = 'سطر ' . $line_no . ': کلاس «' . $level . '» در ترم «' . $term . '» پیدا نشد.';
                }
            }
        } catch (Exception $e) {
            $errors[] = 'سطر ' . $line_no . ': ' . $e->getMessage();
        }
    }

    return [
        'created' => $created,
        'updated' => $updated,
        'enrolled' => $enrolled,
        'errors' => $errors,
    ];
}

function students_info_send_xlsx($filename, array $sheets) {
    $tmp = tempnam(sys_get_temp_dir(), 'stinfo');
    if ($tmp === false) {
        throw new Exception('نتوانست فایل موقت ساخته شود.');
    }
    $xlsx = $tmp . '.xlsx';
    @unlink($tmp);
    students_info_write_xlsx($xlsx, $sheets);
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . filesize($xlsx));
    header('Cache-Control: no-store');
    readfile($xlsx);
    @unlink($xlsx);
    exit;
}
