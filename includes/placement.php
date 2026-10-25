<?php
/**
 * Class placement (کلاس‌بندی): students, instructor, interview date/time, interviewer.
 * Interview fields live on eas_management so EAS stays in sync.
 */

function placement_quote_ident_like(PDO $pdo, $name) {
    return $pdo->quote((string)$name);
}

function placement_table_exists(PDO $pdo, $table) {
    $q = $pdo->quote($table);
    try {
        $row = $pdo->query("SHOW TABLES LIKE $q")->fetch();
        return (bool)$row;
    } catch (Exception $e) {
        return false;
    }
}

function placement_column_exists(PDO $pdo, $table, $column) {
    try {
        $q = placement_quote_ident_like($pdo, $column);
        $tbl = preg_replace('/[^a-zA-Z0-9_]/', '', (string)$table);
        $row = $pdo->query("SHOW COLUMNS FROM `{$tbl}` LIKE $q")->fetch();
        return (bool)$row;
    } catch (Exception $e) {
        return false;
    }
}

function placement_ensure_schema(PDO $pdo) {
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    if (!placement_table_exists($pdo, 'eas_management')) {
        try {
            $pdo->exec("
                CREATE TABLE eas_management (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    class_id INT NOT NULL,
                    interview_date VARCHAR(32) NULL DEFAULT NULL,
                    interview_time VARCHAR(16) NULL DEFAULT NULL,
                    interviewer_name VARCHAR(191) NULL DEFAULT NULL,
                    interviewer_id INT NULL DEFAULT NULL,
                    interview_status TINYINT NOT NULL DEFAULT 0,
                    UNIQUE KEY uq_eas_class (class_id)
                )
            ");
        } catch (Exception $e) {
            error_log('placement eas_management create: ' . $e->getMessage());
        }
        return;
    }
    $alters = [
        'interview_time' => "ALTER TABLE eas_management ADD COLUMN interview_time VARCHAR(16) NULL DEFAULT NULL",
        'interviewer_id' => "ALTER TABLE eas_management ADD COLUMN interviewer_id INT NULL DEFAULT NULL",
    ];
    foreach ($alters as $col => $sql) {
        if (!placement_column_exists($pdo, 'eas_management', $col)) {
            try {
                $pdo->exec($sql);
            } catch (Exception $e) {
                error_log('placement alter ' . $col . ': ' . $e->getMessage());
            }
        }
    }
    if (is_file(__DIR__ . '/term-plan.php')) {
        require_once __DIR__ . '/term-plan.php';
        term_plan_ensure_schema($pdo);
    }
}

function placement_staff_rows(PDO $pdo) {
    return $pdo->query("
        SELECT id, full_name, role
        FROM users
        WHERE is_deleted = 0
          AND role IN ('teacher', 'supervisor')
        ORDER BY role ASC, full_name ASC
    ")->fetchAll(PDO::FETCH_ASSOC);
}

function placement_level_names() {
    try {
        if (!function_exists('accounting_pdo')) {
            require_once __DIR__ . '/accounting.php';
        }
        $rows = accounting_pdo()->query('SELECT name FROM levels ORDER BY name')->fetchAll(PDO::FETCH_COLUMN);
        $out = [];
        foreach ((array)$rows as $name) {
            $name = trim((string)$name);
            if ($name !== '') {
                $out[] = $name;
            }
        }
        return $out;
    } catch (Throwable $e) {
        return [];
    }
}

function placement_teacher_rows(PDO $pdo) {
    return $pdo->query("
        SELECT id, full_name
        FROM users
        WHERE role = 'teacher' AND is_deleted = 0
        ORDER BY full_name ASC
    ")->fetchAll(PDO::FETCH_ASSOC);
}

function placement_student_catalog(PDO $pdo) {
    return $pdo->query("
        SELECT id, full_name, username AS student_code
        FROM users
        WHERE role = 'student' AND is_deleted = 0
        ORDER BY full_name ASC
    ")->fetchAll(PDO::FETCH_ASSOC);
}

function placement_normalize_time($raw) {
    $raw = trim((string)$raw);
    if ($raw === '') {
        return '';
    }
    $map = ['۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9',
            '٠'=>'0','١'=>'1','٢'=>'2','٣'=>'3','٤'=>'4','٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9'];
    $raw = strtr($raw, $map);
    if (preg_match('/^(\d{1,2}):(\d{2})(?::\d{2})?$/', $raw, $m)) {
        $h = (int)$m[1];
        $i = (int)$m[2];
        if ($h > 23 || $i > 59) {
            return '';
        }
        return sprintf('%02d:%02d', $h, $i);
    }
    return '';
}

function placement_schedule_for_time_input($raw) {
    $direct = placement_normalize_time($raw);
    if ($direct !== '') {
        return $direct;
    }
    $raw = trim((string)$raw);
    if ($raw === '') {
        return '';
    }
    $map = ['۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9',
            '٠'=>'0','١'=>'1','٢'=>'2','٣'=>'3','٤'=>'4','٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9'];
    $raw = strtr($raw, $map);
    $raw = str_replace(['.', '–', '—'], [':', '-', '-'], $raw);
    if (preg_match('/(\d{1,2}):(\d{2})/', $raw, $m)) {
        return placement_normalize_time($m[1] . ':' . $m[2]);
    }
    if (preg_match('/^(\d{1,2})$/', $raw, $m)) {
        return placement_normalize_time($m[1] . ':00');
    }
    return '';
}

function placement_ids_same($a, $b) {
    $a = array_values(array_unique(array_map('intval', (array)$a)));
    $b = array_values(array_unique(array_map('intval', (array)$b)));
    sort($a);
    sort($b);
    return $a === $b;
}

function placement_emit_json(array $payload) {
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        $json = json_encode(['success' => false, 'message' => 'پاسخ ساخته نشد.'], JSON_UNESCAPED_UNICODE);
    }
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Length: ' . strlen($json));
        header('Connection: close');
    }
    echo $json;
    while (ob_get_level() > 0) {
        @ob_end_flush();
    }
    @flush();
    if (function_exists('fastcgi_finish_request')) {
        @fastcgi_finish_request();
    }
}

function placement_int_ids($raw) {
    if (!is_array($raw)) {
        return [];
    }
    $out = [];
    foreach ($raw as $id) {
        $id = (int)$id;
        if ($id > 0) {
            $out[$id] = $id;
        }
    }
    return array_values($out);
}

function placement_class_student_ids(PDO $pdo, $class_id) {
    $stmt = $pdo->prepare("SELECT user_id FROM class_students WHERE class_id = ?");
    $stmt->execute([(int)$class_id]);
    return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}

function placement_set_students(PDO $pdo, $class_id, array $student_ids) {
    $class_id = (int)$class_id;
    $pdo->prepare("DELETE FROM class_students WHERE class_id = ?")->execute([$class_id]);
    $ins = $pdo->prepare("INSERT INTO class_students (class_id, user_id) VALUES (?, ?)");
    foreach ($student_ids as $uid) {
        $uid = (int)$uid;
        if ($uid > 0) {
            $ins->execute([$class_id, $uid]);
        }
    }
}

function placement_eas_row(PDO $pdo, $class_id) {
    if (!placement_table_exists($pdo, 'eas_management')) {
        return [];
    }
    $stmt = $pdo->prepare("SELECT * FROM eas_management WHERE class_id = ? LIMIT 1");
    $stmt->execute([(int)$class_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: [];
}

function placement_upsert_interview(PDO $pdo, $class_id, $interview_date, $interview_time, $interviewer_id, $interviewer_name) {
    placement_ensure_schema($pdo);
    $class_id = (int)$class_id;
    $interview_date = trim((string)$interview_date);
    $interview_time = placement_normalize_time($interview_time);
    $interviewer_id = (int)$interviewer_id;
    if ($interviewer_id <= 0) {
        $interviewer_id = null;
    }
    $interviewer_name = trim((string)$interviewer_name);

    $existing = placement_eas_row($pdo, $class_id);
    $has_time = placement_column_exists($pdo, 'eas_management', 'interview_time');
    $has_iid = placement_column_exists($pdo, 'eas_management', 'interviewer_id');

    if ($existing) {
        $sets = ['interview_date = ?', 'interviewer_name = ?'];
        $params = [$interview_date !== '' ? $interview_date : null, $interviewer_name !== '' ? $interviewer_name : null];
        if ($has_time) {
            $sets[] = 'interview_time = ?';
            $params[] = $interview_time !== '' ? $interview_time : null;
        }
        if ($has_iid) {
            $sets[] = 'interviewer_id = ?';
            $params[] = $interviewer_id;
        }
        $params[] = $class_id;
        $pdo->prepare('UPDATE eas_management SET ' . implode(', ', $sets) . ' WHERE class_id = ?')->execute($params);
        return;
    }

    $cols = ['class_id', 'interview_date', 'interviewer_name'];
    $vals = [$class_id, $interview_date !== '' ? $interview_date : null, $interviewer_name !== '' ? $interviewer_name : null];
    if ($has_time) {
        $cols[] = 'interview_time';
        $vals[] = $interview_time !== '' ? $interview_time : null;
    }
    if ($has_iid) {
        $cols[] = 'interviewer_id';
        $vals[] = $interviewer_id;
    }
    $ph = implode(', ', array_fill(0, count($cols), '?'));
    $pdo->prepare('INSERT INTO eas_management (' . implode(', ', $cols) . ') VALUES (' . $ph . ')')->execute($vals);
}

function placement_interviewer_name(PDO $pdo, $interviewer_id) {
    $interviewer_id = (int)$interviewer_id;
    if ($interviewer_id <= 0) {
        return '';
    }
    $stmt = $pdo->prepare("SELECT full_name FROM users WHERE id = ? AND is_deleted = 0 LIMIT 1");
    $stmt->execute([$interviewer_id]);
    return trim((string)$stmt->fetchColumn());
}

function placement_load_term_classes(PDO $pdo, $term_id) {
    placement_ensure_schema($pdo);
    $term_id = (int)$term_id;
    $eas_bits = '';
    if (placement_table_exists($pdo, 'eas_management')) {
        $eas_bits = ', e.interview_date, e.interviewer_name';
        if (placement_column_exists($pdo, 'eas_management', 'interview_status')) {
            $eas_bits .= ', e.interview_status';
        }
        if (placement_column_exists($pdo, 'eas_management', 'interview_time')) {
            $eas_bits .= ', e.interview_time';
        }
        if (placement_column_exists($pdo, 'eas_management', 'interviewer_id')) {
            $eas_bits .= ', e.interviewer_id';
        }
    }
    $join_eas = placement_table_exists($pdo, 'eas_management')
        ? 'LEFT JOIN eas_management e ON e.class_id = c.id'
        : '';
    $stmt = $pdo->prepare("
        SELECT
            c.id,
            c.title,
            c.schedule,
            c.schedule_type,
            c.class_type,
            c.online_link,
            c.teacher_id,
            u.full_name AS teacher_name
            $eas_bits
        FROM classes c
        LEFT JOIN users u ON c.teacher_id = u.id
        $join_eas
        WHERE c.term_id = ?
          AND c.is_deleted = 0
          AND (c.is_archived = 0 OR c.is_archived IS NULL)
        ORDER BY
            CASE WHEN c.schedule_type = 'odd' THEN 1 ELSE 2 END,
            c.schedule,
            c.id
    ");
    $stmt->execute([$term_id]);
    $classes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (!$classes) {
        return [];
    }

    $ids = array_map(static function ($row) {
        return (int)$row['id'];
    }, $classes);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $st = $pdo->prepare("
        SELECT cs.class_id, u.id, u.full_name, u.username AS student_code
        FROM class_students cs
        JOIN users u ON u.id = cs.user_id
        WHERE cs.class_id IN ($placeholders)
          AND u.role = 'student'
          AND u.is_deleted = 0
        ORDER BY u.full_name
    ");
    $st->execute($ids);
    $by_class = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $cid = (int)$row['class_id'];
        if (!isset($by_class[$cid])) {
            $by_class[$cid] = [];
        }
        $by_class[$cid][] = [
            'id' => (int)$row['id'],
            'full_name' => (string)$row['full_name'],
            'student_code' => (string)$row['student_code'],
        ];
    }

    foreach ($classes as &$class) {
        $cid = (int)$class['id'];
        $class['students'] = $by_class[$cid] ?? [];
        $class['interview_time'] = placement_normalize_time($class['interview_time'] ?? '');
        $class['teacher_id'] = (int)($class['teacher_id'] ?? 0);
        $class['interviewer_id'] = (int)($class['interviewer_id'] ?? 0);
    }
    unset($class);
    return $classes;
}

function placement_term_sort_key(array $term) {
    $on = trim((string)($term['starts_on'] ?? ''));
    if ($on === '' || $on === '0000-00-00') {
        $on = '0000-01-01';
    }
    return $on . ':' . str_pad((string)((int)($term['id'] ?? 0)), 8, '0', STR_PAD_LEFT);
}

function placement_previous_term(PDO $pdo, array $active) {
    $active_id = (int)($active['id'] ?? 0);
    if ($active_id <= 0) {
        return null;
    }
    if (function_exists('term_row_is_upcoming') && term_row_is_upcoming($active) && function_exists('term_open_rows')) {
        $teaching = term_pick_teaching(term_open_rows($pdo));
        if ($teaching && (int)($teaching['id'] ?? 0) !== $active_id) {
            return $teaching;
        }
    }
    $rows = $pdo->query("
        SELECT *
        FROM terms
        WHERE (is_deleted = 0 OR is_deleted IS NULL)
          AND id <> " . $active_id . "
    ")->fetchAll(PDO::FETCH_ASSOC);
    $active_key = placement_term_sort_key($active);
    $best = null;
    $best_key = '';
    foreach ($rows as $row) {
        $key = placement_term_sort_key($row);
        if ($key >= $active_key) {
            continue;
        }
        if ($best === null || $key > $best_key) {
            $best = $row;
            $best_key = $key;
        }
    }
    return $best;
}

function placement_class_copy_payload(array $class) {
    $students = [];
    foreach ($class['students'] ?? [] as $st) {
        $students[] = [
            'id' => (int)($st['id'] ?? 0),
            'full_name' => (string)($st['full_name'] ?? ''),
            'student_code' => (string)($st['student_code'] ?? ''),
        ];
    }
    return [
        'source_id' => (int)($class['id'] ?? 0),
        'title' => (string)($class['title'] ?? ''),
        'teacher_id' => (int)($class['teacher_id'] ?? 0),
        'teacher_name' => (string)($class['teacher_name'] ?? ''),
        'class_type' => (string)($class['class_type'] ?? 'offline'),
        'schedule_type' => (string)($class['schedule_type'] ?? 'odd'),
        'schedule' => (string)($class['schedule'] ?? ''),
        'students' => $students,
        'interview_date' => (string)($class['interview_date'] ?? ''),
        'interview_time' => (string)($class['interview_time'] ?? ''),
        'interviewer_id' => (int)($class['interviewer_id'] ?? 0),
        'interviewer_name' => (string)($class['interviewer_name'] ?? ''),
    ];
}

function placement_previous_term_payload(PDO $pdo, array $active) {
    $prev = placement_previous_term($pdo, $active);
    if (!$prev) {
        return [
            'term_id' => 0,
            'title' => '',
            'classes' => [],
        ];
    }
    $title = (string)($prev['title'] ?? '');
    if (function_exists('term_english_title')) {
        $title = term_english_title($title);
    }
    $classes = placement_load_term_classes($pdo, (int)$prev['id']);
    $copies = [];
    foreach ($classes as $class) {
        $copies[] = placement_class_copy_payload($class);
    }
    return [
        'term_id' => (int)$prev['id'],
        'title' => $title,
        'count' => count($copies),
        'classes' => $copies,
    ];
}

function placement_sync_identities(PDO $pdo, array $user_ids, array $class_row, $term, $events = null) {
    $user_ids = placement_int_ids($user_ids);
    if (!$user_ids) {
        return;
    }
    try {
        require_once __DIR__ . '/identity-sync.php';
        foreach ($user_ids as $uid) {
            identity_sync_student_classes($pdo, $uid);
        }
    } catch (Throwable $e) {
        error_log('placement identity: ' . $e->getMessage());
    }

    $class_type = (string)($class_row['class_type'] ?? '');
    $online_link = trim((string)($class_row['online_link'] ?? ''));
    if ($class_type !== 'online' && $online_link === '') {
        return;
    }
    try {
        require_once __DIR__ . '/alocom-access.php';
        foreach ($user_ids as $uid) {
            $st = $pdo->prepare("SELECT id, full_name, username, phone FROM users WHERE id = ? LIMIT 1");
            $st->execute([$uid]);
            $student = $st->fetch(PDO::FETCH_ASSOC);
            if (!$student) {
                continue;
            }
            alocom_enroll_panel_student_in_event($pdo, $student, $class_row, $events);
        }
    } catch (Throwable $e) {
        error_log('placement alocom: ' . $e->getMessage());
    }
}

function placement_provision_online(PDO $pdo, array $saved) {
    $saved['alocom_ok'] = true;
    $saved['alocom_created'] = false;
    $saved['alocom_warning'] = '';
    $type = strtolower(trim((string)($saved['class_type'] ?? '')));
    if ($type === 'online') {
        $saved['create_alocom'] = true;
    }
    try {
        require_once __DIR__ . '/alocom-access.php';
        if (!alocom_class_needs_event($saved)) {
            return $saved;
        }
        $event = alocom_ensure_class_event($pdo, [
            'id' => (int)($saved['id'] ?? 0),
            'title' => (string)($saved['title'] ?? ''),
            'class_type' => $type !== '' ? $type : 'online',
            'create_alocom' => true,
            'online_link' => (string)($saved['online_link'] ?? ''),
            'teacher_id' => (int)($saved['teacher_id'] ?? 0),
            'term_id' => (int)($saved['term_id'] ?? 0),
            'schedule' => (string)($saved['schedule'] ?? ''),
            'schedule_type' => (string)($saved['schedule_type'] ?? ''),
        ]);
        if (is_array($event)) {
            $link = trim((string)($event['online_link'] ?? $event['alocom_link'] ?? ''));
            if ($link !== '') {
                $saved['online_link'] = $link;
            }
            $saved['alocom_created'] = !empty($event['created']);
            $saved['alocom_event'] = $event;
        }
        if (trim((string)($saved['online_link'] ?? '')) === '') {
            $saved['alocom_ok'] = false;
            $saved['alocom_warning'] = 'کلاس ذخیره شد ولی لینک اتاق الوکام هنوز نیامد.';
        } else {
            $class_for_roster = [
                'id' => (int)($saved['id'] ?? 0),
                'title' => (string)($saved['title'] ?? ''),
                'class_type' => $type !== '' ? $type : 'online',
                'create_alocom' => true,
                'online_link' => (string)($saved['online_link'] ?? ''),
                'teacher_id' => (int)($saved['teacher_id'] ?? 0),
                'term_id' => (int)($saved['term_id'] ?? 0),
            ];
            $roster = alocom_sync_class_event_roster($pdo, $class_for_roster, is_array($event) ? $event : null);
            $saved['alocom_roster'] = [
                'added' => (int)($roster['added'] ?? 0),
                'students' => (int)($roster['students'] ?? 0),
                'teacher' => !empty($roster['teacher']),
            ];
            $saved['alocom_roster_ok'] = !empty($roster['ok']);
            if (empty($roster['ok']) && (int)($roster['students'] ?? 0) === 0 && empty($roster['teacher'])) {
                $saved['alocom_warning'] = 'اتاق الوکام آماده است ولی اعضا ثبت نشدند. دوباره ذخیره کنید.';
            }
        }
    } catch (Throwable $e) {
        error_log('placement alocom ensure: ' . $e->getMessage());
        if (function_exists('alocom_log')) {
            alocom_log('placement ensure class=' . (int)($saved['id'] ?? 0) . ' ' . $e->getMessage());
        }
        $saved['alocom_ok'] = false;
        $saved['alocom_warning'] = 'کلاس ذخیره شد ولی اتاق الوکام ساخته نشد. دوباره ذخیره کنید.';
    }
    return $saved;
}

function placement_delete_class(PDO $pdo, $class_id) {
    $class_id = (int)$class_id;
    if ($class_id <= 0) {
        throw new Exception('شناسه کلاس نامعتبر است.');
    }
    $stmt = $pdo->prepare("SELECT * FROM classes WHERE id = ? AND is_deleted = 0 LIMIT 1");
    $stmt->execute([$class_id]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$existing) {
        throw new Exception('کلاس یافت نشد.');
    }
    $student_ids = placement_class_student_ids($pdo, $class_id);

    $pdo->prepare("DELETE FROM class_students WHERE class_id = ?")->execute([$class_id]);
    if (placement_table_exists($pdo, 'eas_management')) {
        try {
            $pdo->prepare("DELETE FROM eas_management WHERE class_id = ?")->execute([$class_id]);
        } catch (PDOException $e) {
            if ($e->getCode() !== '42S02') {
                throw $e;
            }
        }
    }
    $pdo->prepare("UPDATE classes SET is_deleted = 1 WHERE id = ?")->execute([$class_id]);

    return [
        'id' => $class_id,
        'student_ids' => $student_ids,
        'term_id' => (int)($existing['term_id'] ?? 0),
        'class_row' => $existing,
    ];
}

function placement_save_class(PDO $pdo, array $input) {
    if (!function_exists('term_load')) {
        require_once __DIR__ . '/terms.php';
    }
    $class_id = (int)($input['class_id'] ?? 0);
    $term_id = (int)($input['term_id'] ?? 0);
    $title = trim((string)($input['title'] ?? $input['class_title'] ?? ''));
    $teacher_id = (int)($input['teacher_id'] ?? 0);
    $class_type = (string)($input['class_type'] ?? '');
    $schedule_type = (string)($input['schedule_type'] ?? '');
    $schedule_raw = trim((string)($input['schedule'] ?? ''));
    $schedule = placement_schedule_for_time_input($schedule_raw);
    if ($schedule === '') {
        $schedule = $schedule_raw;
    }
    $online_link = trim((string)($input['online_link'] ?? ''));
    $create_alocom = !empty($input['create_alocom']);
    if ($class_type === 'online') {
        $create_alocom = true;
    }
    $student_ids = placement_int_ids($input['student_ids'] ?? []);
    $interview_date = trim((string)($input['interview_date'] ?? ''));
    $interview_time = placement_normalize_time($input['interview_time'] ?? '');
    $interviewer_id = (int)($input['interviewer_id'] ?? 0);
    $interviewer_name = trim((string)($input['interviewer_name'] ?? ''));

    if ($title === '' || $teacher_id <= 0 || !in_array($class_type, ['online', 'offline', 'private'], true) || !in_array($schedule_type, ['odd', 'even'], true)) {
        throw new Exception('لول، مدرس، نوع و روز برگزاری لازم است.');
    }

    if ($interviewer_id > 0) {
        $from_id = placement_interviewer_name($pdo, $interviewer_id);
        if ($from_id !== '') {
            $interviewer_name = $from_id;
        }
    }

    $term = null;
    if ($class_id > 0) {
        $stmt = $pdo->prepare("SELECT * FROM classes WHERE id = ? AND is_deleted = 0 LIMIT 1");
        $stmt->execute([$class_id]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$existing) {
            throw new Exception('کلاس یافت نشد.');
        }
        $term_id = (int)$existing['term_id'];
        if ($online_link === '') {
            $online_link = trim((string)($existing['online_link'] ?? ''));
        }
        $old_students = placement_class_student_ids($pdo, $class_id);
        $pdo->prepare("
            UPDATE classes
            SET title = ?, class_type = ?, online_link = ?, schedule = ?, schedule_type = ?, teacher_id = ?
            WHERE id = ?
        ")->execute([$title, $class_type, $online_link, $schedule, $schedule_type, $teacher_id, $class_id]);
        if (!$student_ids && $old_students && empty($input['roster_ready']) && empty($input['clear_roster'])) {
            $student_ids = $old_students;
        }
        placement_set_students($pdo, $class_id, $student_ids);
        placement_upsert_interview($pdo, $class_id, $interview_date, $interview_time, $interviewer_id, $interviewer_name);
        $created = false;
        if (function_exists('term_plan_apply_level_template')) {
            try {
                term_plan_apply_level_template($pdo, $class_id, $title, (int)($input['created_by'] ?? 0), [
                    'id' => $class_id,
                    'title' => $title,
                    'class_type' => $class_type,
                ]);
            } catch (Throwable $e) {
                error_log('term_plan apply on placement update: ' . $e->getMessage());
            }
        }
    } else {
        if ($term_id <= 0) {
            throw new Exception('ترم نامعتبر است.');
        }
        $term = term_load($pdo, $term_id);
        if (!$term || (int)($term['is_deleted'] ?? 0) === 1) {
            throw new Exception('ترم یافت نشد.');
        }
        $created_by = (int)($input['created_by'] ?? 0);
        $pdo->prepare("
            INSERT INTO classes (title, term_id, class_type, online_link, schedule, schedule_type, teacher_id, created_by, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ")->execute([$title, $term_id, $class_type, $online_link, $schedule, $schedule_type, $teacher_id, $created_by]);
        $class_id = (int)$pdo->lastInsertId();
        placement_set_students($pdo, $class_id, $student_ids);
        placement_upsert_interview($pdo, $class_id, $interview_date, $interview_time, $interviewer_id, $interviewer_name);
        $old_students = [];
        $created = true;
        if (function_exists('term_plan_apply_level_template')) {
            try {
                term_plan_apply_level_template($pdo, $class_id, $title, $created_by, [
                    'id' => $class_id,
                    'title' => $title,
                    'class_type' => $class_type,
                ]);
            } catch (Throwable $e) {
                error_log('term_plan apply on placement create: ' . $e->getMessage());
            }
        }
    }

    if (!$term) {
        $term = term_load($pdo, $term_id);
    }

    return [
        'id' => $class_id,
        'title' => $title,
        'teacher_id' => $teacher_id,
        'class_type' => $class_type,
        'schedule_type' => $schedule_type,
        'schedule' => $schedule,
        'online_link' => $online_link,
        'create_alocom' => $create_alocom,
        'interview_date' => $interview_date,
        'interview_time' => $interview_time,
        'interviewer_id' => $interviewer_id,
        'interviewer_name' => $interviewer_name,
        'student_ids' => $student_ids,
        'term_id' => $term_id,
        'created' => $created,
        '_old_students' => $old_students,
        '_term' => $term ?: [],
    ];
}
