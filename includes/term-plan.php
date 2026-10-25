<?php
/**
 * Term Plan: supervisor session tasks + teacher 3-state checks.
 */

if (!function_exists('jalali_parse_any_date')) {
    require_once __DIR__ . '/jalali.php';
}
if (!function_exists('class_live_today_type')) {
    require_once __DIR__ . '/class-live.php';
}

function term_plan_methods() {
    return [
        'Pair Work',
        'group Work',
        'Elicitation',
        'TBL',
        'PBL',
        'PPP',
        'Sen by Sen Repetition',
        'Simultaneous Repetition',
        'Other',
    ];
}

function term_plan_methods_fixed() {
    $out = [];
    foreach (term_plan_methods() as $m) {
        if ($m !== 'Other') {
            $out[] = $m;
        }
    }
    return $out;
}

function term_plan_table_exists(PDO $pdo, $table) {
    static $cache = [];
    $table = (string)$table;
    if (isset($cache[$table])) {
        return $cache[$table];
    }
    try {
        $q = $pdo->quote($table);
        $cache[$table] = (bool)$pdo->query("SHOW TABLES LIKE $q")->fetch();
    } catch (Exception $e) {
        $cache[$table] = false;
    }
    return $cache[$table];
}

function term_plan_ensure_schema(PDO $pdo) {
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS term_plan_tasks (
                id INT AUTO_INCREMENT PRIMARY KEY,
                class_id INT NOT NULL,
                session_no TINYINT UNSIGNED NOT NULL,
                title VARCHAR(500) NOT NULL,
                sort_order INT NOT NULL DEFAULT 0,
                source VARCHAR(16) NOT NULL DEFAULT 'supervisor',
                created_by INT NULL DEFAULT NULL,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                KEY idx_tp_class_session (class_id, session_no)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS term_plan_task_checks (
                id INT AUTO_INCREMENT PRIMARY KEY,
                task_id INT NOT NULL,
                class_id INT NOT NULL,
                teacher_id INT NOT NULL,
                status VARCHAR(16) NOT NULL DEFAULT 'pending',
                method VARCHAR(64) NULL DEFAULT NULL,
                method_custom VARCHAR(191) NULL DEFAULT NULL,
                teacher_remark TEXT NULL,
                updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uq_tp_task (task_id),
                KEY idx_tp_check_class (class_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS term_plan_session_remarks (
                id INT AUTO_INCREMENT PRIMARY KEY,
                class_id INT NOT NULL,
                session_no TINYINT UNSIGNED NOT NULL,
                remark TEXT NULL,
                supervisor_id INT NULL DEFAULT NULL,
                updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uq_tp_remark (class_id, session_no)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        term_plan_ensure_remark_index($pdo);
    } catch (Exception $e) {
        error_log('term_plan schema: ' . $e->getMessage());
    }
    if (is_file(__DIR__ . '/term-plan-tracks.php')) {
        require_once __DIR__ . '/term-plan-tracks.php';
        if (function_exists('term_plan_bootstrap_new_tracks')) {
            term_plan_bootstrap_new_tracks($pdo);
        }
    }
}

function term_plan_ensure_remark_index(PDO $pdo) {
    try {
        $has = false;
        foreach ($pdo->query("SHOW INDEX FROM term_plan_session_remarks")->fetchAll(PDO::FETCH_ASSOC) as $idx) {
            if (($idx['Key_name'] ?? '') === 'uq_tp_remark') {
                $has = true;
                break;
            }
        }
        if ($has) {
            return;
        }
        $pdo->exec("
            DELETE r1 FROM term_plan_session_remarks r1
            INNER JOIN term_plan_session_remarks r2
                ON r1.class_id = r2.class_id
               AND r1.session_no = r2.session_no
               AND r1.id < r2.id
        ");
        $pdo->exec("ALTER TABLE term_plan_session_remarks ADD UNIQUE KEY uq_tp_remark (class_id, session_no)");
    } catch (Exception $e) {
        error_log('term_plan remark index: ' . $e->getMessage());
    }
}

function term_plan_class_type_key($class) {
    $key = strtolower(trim((string)($class['class_type'] ?? '')));
    if ($key === '' || in_array($key, ['inperson', 'in-person', 'حضوری', 'offline'], true)) {
        return 'offline';
    }
    if (in_array($key, ['private', 'خصوصی'], true)) {
        return 'private';
    }
    if (in_array($key, ['online', 'آنلاین'], true)) {
        return 'online';
    }
    return $key;
}

function term_plan_session_total($class) {
    return term_plan_class_type_key($class) === 'private' ? 10 : 13;
}

function term_plan_day_matches($class, $ts) {
    $w = (int)date('w', $ts);
    if ($w === 5) {
        return false;
    }
    $slot = in_array($w, [6, 1, 3], true) ? 'even' : 'odd';
    $want = strtolower(trim((string)($class['schedule_type'] ?? '')));
    if ($want === 'even' || strpos($want, 'زوج') !== false) {
        return $slot === 'even';
    }
    if ($want === 'odd' || strpos($want, 'فرد') !== false) {
        return $slot === 'odd';
    }
    if ($want === 'private' || strpos($want, 'خصوصی') !== false || term_plan_class_type_key($class) === 'private') {
        return $slot === 'even';
    }
    return false;
}

function term_plan_parse_date_value($raw) {
    $raw = trim((string)$raw);
    if ($raw === '') {
        return null;
    }
    try {
        $ymd = jalali_parse_any_date($raw);
        return $ymd ?: null;
    } catch (Exception $e) {
        return null;
    }
}

function term_plan_eas_dates($class) {
    $json = $class['session_dates_json'] ?? '';
    if (is_array($json)) {
        $obj = $json;
    } else {
        $obj = json_decode((string)$json, true);
    }
    if (!is_array($obj)) {
        return [];
    }
    $out = [];
    foreach ($obj as $key => $val) {
        $n = 0;
        if (preg_match('/(\d+)/', (string)$key, $m)) {
            $n = (int)$m[1];
        }
        if ($n < 1) {
            continue;
        }
        $ymd = term_plan_parse_date_value($val);
        if ($ymd) {
            $out[$n] = $ymd;
        }
    }
    ksort($out);
    return $out;
}

function term_plan_calendar_dates($class, $limit) {
    $limit = max(1, (int)$limit);
    $start = trim((string)($class['starts_on'] ?? $class['term_starts_on'] ?? ''));
    if ($start === '' || $start === '0000-00-00' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $start)) {
        return [];
    }
    $end = trim((string)($class['ends_on'] ?? $class['term_ends_on'] ?? ''));
    if ($end === '' || $end === '0000-00-00' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $end)) {
        $end = date('Y-m-d', strtotime($start . ' +120 days'));
    }
    $out = [];
    $ts = strtotime($start . ' 12:00:00');
    $end_ts = strtotime($end . ' 12:00:00');
    $guard = 0;
    $n = 1;
    while ($ts && $ts <= $end_ts && $n <= $limit && $guard < 400) {
        $guard++;
        if (term_plan_day_matches($class, $ts)) {
            $out[$n] = date('Y-m-d', $ts);
            $n++;
        }
        $ts = strtotime('+1 day', $ts);
    }
    return $out;
}

function term_plan_session_dates($class) {
    $total = term_plan_session_total($class);
    $eas = term_plan_eas_dates($class);
    $cal = term_plan_calendar_dates($class, $total);
    $out = [];
    for ($i = 1; $i <= $total; $i++) {
        $out[$i] = $eas[$i] ?? $cal[$i] ?? null;
    }
    return $out;
}

function term_plan_progress($class, $today = null) {
    date_default_timezone_set('Asia/Tehran');
    $today = $today ?: date('Y-m-d');
    $total = term_plan_session_total($class);
    $dates = term_plan_session_dates($class);
    $elapsed = 0;
    foreach ($dates as $ymd) {
        if ($ymd && $ymd <= $today) {
            $elapsed++;
        }
    }
    if ($elapsed > $total) {
        $elapsed = $total;
    }
    $percent = $total > 0 ? (int)round(($elapsed / $total) * 100) : 0;
    if ($percent > 100) {
        $percent = 100;
    }
    return [
        'total' => $total,
        'elapsed' => $elapsed,
        'percent' => $percent,
        'dates' => $dates,
        'today' => $today,
    ];
}

function term_plan_h($s) {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function term_plan_day_badge($class) {
    $type = strtolower((string)($class['schedule_type'] ?? ''));
    if ($type === 'even' || strpos($type, 'زوج') !== false) {
        return ['day-even', 'روزهای زوج', 'even'];
    }
    if ($type === 'odd' || strpos($type, 'فرد') !== false) {
        return ['day-odd', 'روزهای فرد', 'odd'];
    }
    if ($type === 'private' || strpos($type, 'خصوصی') !== false || term_plan_class_type_key($class) === 'private') {
        return ['day-private', 'خصوصی', 'private'];
    }
    return ['day-even', 'نامشخص', 'all'];
}

function term_plan_type_badge_html($class) {
    $key = term_plan_class_type_key($class);
    if ($key === 'online') {
        return "<span class='type-badge type-online'>آنلاین</span>";
    }
    if ($key === 'private') {
        return "<span class='type-badge type-private'>خصوصی</span>";
    }
    return "<span class='type-badge type-inperson'>حضوری</span>";
}

function term_plan_display_time($class) {
    $t = trim((string)($class['schedule'] ?? ''));
    if ($t === '') {
        $t = trim((string)($class['class_time'] ?? ''));
    }
    return $t !== '' ? $t : 'تنظیم نشده';
}

function term_plan_progress_ring_html(array $progress) {
    $pct = (int)$progress['percent'];
    $elapsed = (int)$progress['elapsed'];
    $total = (int)$progress['total'];
    $r = 52;
    $c = 2 * M_PI * $r;
    $offset = $c * (1 - ($pct / 100));
    $tone = $pct >= 80 ? 'is-high' : ($pct >= 40 ? 'is-mid' : 'is-low');
    $html = '<div class="tp-ring-wrap ' . $tone . '">';
    $html .= '<svg class="tp-ring" viewBox="0 0 120 120" aria-hidden="true">';
    $html .= '<circle class="tp-ring-track" cx="60" cy="60" r="' . $r . '"></circle>';
    $html .= '<circle class="tp-ring-fill" cx="60" cy="60" r="' . $r . '"';
    $html .= ' stroke-dasharray="' . round($c, 2) . '" stroke-dashoffset="' . round($offset, 2) . '"></circle>';
    $html .= '</svg>';
    $html .= '<div class="tp-ring-label">';
    $html .= '<strong>' . $pct . '<span>%</span></strong>';
    $html .= '<em>سپری‌شده</em>';
    $html .= '</div></div>';
    $html .= '<div class="tp-progress-copy">';
    $html .= '<div class="tp-elapsed-meta">جلسه <b>' . $elapsed . '</b> از <b>' . $total . '</b></div>';
    $html .= '<div class="tp-pips" aria-hidden="true">';
    for ($i = 1; $i <= $total; $i++) {
        $cls = 'tp-pip';
        if ($i <= $elapsed) {
            $cls .= ' is-done';
        } elseif ($i === $elapsed + 1) {
            $cls .= ' is-now';
        }
        $html .= '<span class="' . $cls . '" title="Session ' . $i . '">' . $i . '</span>';
    }
    $html .= '</div></div>';
    return $html;
}

function term_plan_class_sql(PDO $pdo) {
    $eas = term_plan_table_exists($pdo, 'eas_management')
        ? 'LEFT JOIN eas_management e ON e.class_id = c.id'
        : '';
    $eas_col = term_plan_table_exists($pdo, 'eas_management')
        ? 'e.session_dates_json'
        : 'NULL AS session_dates_json';
    return [$eas, $eas_col];
}

function term_plan_teacher_classes(PDO $pdo, $teacher_id) {
    term_plan_ensure_schema($pdo);
    list($eas, $eas_col) = term_plan_class_sql($pdo);
    if (!function_exists('term_sql_teaching')) {
        require_once __DIR__ . '/terms.php';
    }
    $sql = "
        SELECT c.id, c.title, c.schedule, c.schedule_type, c.class_type, c.class_time,
               t.starts_on, t.ends_on, $eas_col
        FROM classes c
        LEFT JOIN terms t ON c.term_id = t.id
        $eas
        WHERE c.teacher_id = ?
          AND c.is_deleted = 0
          AND (" . term_sql_teaching('t') . " OR c.term_id IS NULL)
          AND IFNULL(c.is_archived, 0) = 0
        ORDER BY c.title
    ";
    $st = $pdo->prepare($sql);
    $st->execute([(int)$teacher_id]);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

function term_plan_supervisor_classes(PDO $pdo) {
    term_plan_ensure_schema($pdo);
    list($eas, $eas_col) = term_plan_class_sql($pdo);
    if (!function_exists('term_sql_teaching')) {
        require_once __DIR__ . '/terms.php';
    }
    $sql = "
        SELECT c.id, c.title, c.schedule, c.schedule_type, c.class_type, c.class_time,
               c.teacher_id, u.full_name AS teacher_name,
               t.starts_on, t.ends_on, $eas_col,
               COALESCE(tc.task_count, 0) AS task_count
        FROM classes c
        JOIN users u ON c.teacher_id = u.id
        LEFT JOIN terms t ON c.term_id = t.id
        $eas
        LEFT JOIN (
            SELECT class_id, COUNT(*) AS task_count
            FROM term_plan_tasks
            GROUP BY class_id
        ) tc ON tc.class_id = c.id
        WHERE c.is_deleted = 0
          AND (" . term_sql_teaching('t') . " OR c.term_id IS NULL)
          AND IFNULL(c.is_archived, 0) = 0
        ORDER BY c.title
    ";
    return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
}

function term_plan_find_class(PDO $pdo, $class_id, $teacher_id = 0) {
    term_plan_ensure_schema($pdo);
    list($eas, $eas_col) = term_plan_class_sql($pdo);
    if (!function_exists('term_sql_teaching')) {
        require_once __DIR__ . '/terms.php';
    }
    $sql = "
        SELECT c.id, c.title, c.schedule, c.schedule_type, c.class_type, c.class_time,
               c.teacher_id, u.full_name AS teacher_name,
               t.starts_on, t.ends_on, $eas_col
        FROM classes c
        LEFT JOIN users u ON c.teacher_id = u.id
        LEFT JOIN terms t ON c.term_id = t.id
        $eas
        WHERE c.id = ?
          AND c.is_deleted = 0
          AND (" . term_sql_teaching('t') . " OR c.term_id IS NULL)
          AND IFNULL(c.is_archived, 0) = 0
    ";
    $params = [(int)$class_id];
    if ((int)$teacher_id > 0) {
        $sql .= ' AND c.teacher_id = ?';
        $params[] = (int)$teacher_id;
    }
    $sql .= ' LIMIT 1';
    $st = $pdo->prepare($sql);
    $st->execute($params);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function term_plan_load_tasks(PDO $pdo, $class_id) {
    term_plan_ensure_schema($pdo);
    $st = $pdo->prepare("
        SELECT t.*,
               ck.status, ck.method, ck.method_custom, ck.teacher_remark, ck.teacher_id AS check_teacher_id
        FROM term_plan_tasks t
        LEFT JOIN term_plan_task_checks ck ON ck.task_id = t.id
        WHERE t.class_id = ?
        ORDER BY t.session_no ASC, t.sort_order ASC, t.id ASC
    ");
    $st->execute([(int)$class_id]);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

function term_plan_load_remarks(PDO $pdo, $class_id) {
    term_plan_ensure_schema($pdo);
    $st = $pdo->prepare("SELECT session_no, remark FROM term_plan_session_remarks WHERE class_id = ? ORDER BY id ASC");
    $st->execute([(int)$class_id]);
    $out = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $out[(int)$row['session_no']] = trim((string)($row['remark'] ?? ''));
    }
    return $out;
}

function term_plan_sessions_payload(PDO $pdo, array $class) {
    $progress = term_plan_progress($class);
    $tasks = term_plan_load_tasks($pdo, (int)$class['id']);
    $remarks = term_plan_load_remarks($pdo, (int)$class['id']);
    $by_session = [];
    for ($i = 1; $i <= $progress['total']; $i++) {
        $ymd = $progress['dates'][$i] ?? null;
        $by_session[$i] = [
            'session_no' => $i,
            'date' => $ymd,
            'date_label' => $ymd ? jalali_pretty($ymd) : '',
            'elapsed' => ($ymd && $ymd <= $progress['today']),
            'supervisor_remark' => $remarks[$i] ?? '',
            'tasks' => [],
        ];
    }
    foreach ($tasks as $task) {
        $n = (int)$task['session_no'];
        if (!isset($by_session[$n])) {
            continue;
        }
        $by_session[$n]['tasks'][] = term_plan_public_task($task);
    }
    return [$progress, $by_session];
}

function term_plan_public_task(array $task) {
    $status = (string)($task['status'] ?? 'pending');
    if (!in_array($status, ['pending', 'done', 'missed'], true)) {
        $status = 'pending';
    }
    $method = (string)($task['method'] ?? '');
    $custom = (string)($task['method_custom'] ?? '');
    $needs_method = ($status === 'done' && ($method === '' || ($method === 'Other' && trim($custom) === '')));
    $needs_remark = ($status === 'missed' && trim((string)($task['teacher_remark'] ?? '')) === '');
    return [
        'id' => (int)$task['id'],
        'session_no' => (int)$task['session_no'],
        'title' => (string)$task['title'],
        'sort_order' => (int)$task['sort_order'],
        'source' => (string)($task['source'] ?? 'supervisor'),
        'status' => $status,
        'method' => $method,
        'method_custom' => $custom,
        'teacher_remark' => (string)($task['teacher_remark'] ?? ''),
        'needs_method' => $needs_method,
        'needs_remark' => $needs_remark,
    ];
}

function term_plan_next_sort(PDO $pdo, $class_id, $session_no) {
    $st = $pdo->prepare("SELECT COALESCE(MAX(sort_order), 0) FROM term_plan_tasks WHERE class_id = ? AND session_no = ?");
    $st->execute([(int)$class_id, (int)$session_no]);
    return ((int)$st->fetchColumn()) + 1;
}

function term_plan_add_task(PDO $pdo, $class_id, $session_no, $title, $source, $user_id) {
    $title = trim((string)$title);
    if ($title === '') {
        throw new Exception('متن تسک را بنویسید.');
    }
    if (mb_strlen($title) > 500) {
        throw new Exception('تسک خیلی طولانی است.');
    }
    $session_no = (int)$session_no;
    if ($session_no < 1 || $session_no > 13) {
        throw new Exception('شماره جلسه نامعتبر است.');
    }
    $sort = term_plan_next_sort($pdo, $class_id, $session_no);
    $st = $pdo->prepare("
        INSERT INTO term_plan_tasks (class_id, session_no, title, sort_order, source, created_by)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $st->execute([(int)$class_id, $session_no, $title, $sort, $source, (int)$user_id]);
    $id = (int)$pdo->lastInsertId();
    $row = [
        'id' => $id,
        'session_no' => $session_no,
        'title' => $title,
        'sort_order' => $sort,
        'source' => $source,
        'status' => 'pending',
        'method' => '',
        'method_custom' => '',
        'teacher_remark' => '',
    ];
    return term_plan_public_task($row);
}

function term_plan_update_task_title(PDO $pdo, $task_id, $class_id, $title) {
    $title = trim((string)$title);
    if ($title === '') {
        throw new Exception('متن تسک را بنویسید.');
    }
    $st = $pdo->prepare("UPDATE term_plan_tasks SET title = ? WHERE id = ? AND class_id = ?");
    $st->execute([$title, (int)$task_id, (int)$class_id]);
    if ($st->rowCount() < 1) {
        $chk = $pdo->prepare("SELECT id FROM term_plan_tasks WHERE id = ? AND class_id = ?");
        $chk->execute([(int)$task_id, (int)$class_id]);
        if (!$chk->fetch()) {
            throw new Exception('تسک پیدا نشد.');
        }
    }
}

function term_plan_delete_task(PDO $pdo, $task_id, $class_id, $only_source = '', $only_user = 0) {
    $sql = "SELECT id, source, created_by FROM term_plan_tasks WHERE id = ? AND class_id = ? LIMIT 1";
    $st = $pdo->prepare($sql);
    $st->execute([(int)$task_id, (int)$class_id]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        throw new Exception('تسک پیدا نشد.');
    }
    if ($only_source !== '' && (string)$row['source'] !== $only_source) {
        throw new Exception('فقط تسک‌هایی که خودتان اضافه کرده‌اید قابل حذف است.');
    }
    if ($only_user > 0 && (int)$row['created_by'] !== (int)$only_user) {
        throw new Exception('فقط تسک‌هایی که خودتان اضافه کرده‌اید قابل حذف است.');
    }
    $pdo->prepare("DELETE FROM term_plan_task_checks WHERE task_id = ?")->execute([(int)$task_id]);
    $pdo->prepare("DELETE FROM term_plan_tasks WHERE id = ? AND class_id = ?")->execute([(int)$task_id, (int)$class_id]);
}

function term_plan_save_supervisor_remark(PDO $pdo, $class_id, $session_no, $remark, $supervisor_id) {
    term_plan_ensure_schema($pdo);
    $session_no = (int)$session_no;
    if ($session_no < 1 || $session_no > 13) {
        throw new Exception('شماره جلسه نامعتبر است.');
    }
    $remark = trim((string)$remark);
    $class_id = (int)$class_id;
    $supervisor_id = (int)$supervisor_id;

    $st = $pdo->prepare("
        SELECT id FROM term_plan_session_remarks
        WHERE class_id = ? AND session_no = ?
        ORDER BY id DESC
    ");
    $st->execute([$class_id, $session_no]);
    $ids = $st->fetchAll(PDO::FETCH_COLUMN);
    if ($ids) {
        $keep = (int)$ids[0];
        $up = $pdo->prepare("UPDATE term_plan_session_remarks SET remark = ?, supervisor_id = ? WHERE id = ?");
        $up->execute([$remark, $supervisor_id, $keep]);
        if (count($ids) > 1) {
            $del = $pdo->prepare("DELETE FROM term_plan_session_remarks WHERE class_id = ? AND session_no = ? AND id <> ?");
            $del->execute([$class_id, $session_no, $keep]);
        }
    } else {
        try {
            $ins = $pdo->prepare("
                INSERT INTO term_plan_session_remarks (class_id, session_no, remark, supervisor_id)
                VALUES (?, ?, ?, ?)
            ");
            $ins->execute([$class_id, $session_no, $remark, $supervisor_id]);
        } catch (Exception $e) {
            $up = $pdo->prepare("
                UPDATE term_plan_session_remarks
                SET remark = ?, supervisor_id = ?
                WHERE class_id = ? AND session_no = ?
            ");
            $up->execute([$remark, $supervisor_id, $class_id, $session_no]);
            if ($up->rowCount() < 1) {
                throw $e;
            }
        }
    }
    return $remark;
}

function term_plan_normalize_status($status) {
    $status = strtolower(trim((string)$status));
    if (!in_array($status, ['pending', 'done', 'missed'], true)) {
        throw new Exception('وضعیت نامعتبر است.');
    }
    return $status;
}

function term_plan_normalize_method($method, $custom) {
    $method = trim((string)$method);
    $custom = trim((string)$custom);
    if ($method === '') {
        return ['', ''];
    }
    $fixed = term_plan_methods_fixed();
    if (strcasecmp($method, 'Other') === 0) {
        if ($custom === '') {
            throw new Exception('برای Other باید خودتان متد را بنویسید.');
        }
        if (mb_strlen($custom) > 191) {
            throw new Exception('نام متد خیلی طولانی است.');
        }
        return ['Other', $custom];
    }
    foreach ($fixed as $name) {
        if (strcasecmp($name, $method) === 0) {
            return [$name, ''];
        }
    }
    throw new Exception('متد انتخاب‌شده در لیست نیست.');
}

function term_plan_save_check(PDO $pdo, $task_id, $class_id, $teacher_id, $status, $method, $custom, $remark) {
    $own = $pdo->prepare("SELECT id FROM term_plan_tasks WHERE id = ? AND class_id = ?");
    $own->execute([(int)$task_id, (int)$class_id]);
    if (!$own->fetch()) {
        throw new Exception('تسک پیدا نشد.');
    }
    $status = term_plan_normalize_status($status);
    $method_name = '';
    $method_custom = '';
    $remark = trim((string)$remark);
    if ($status === 'done') {
        if (trim((string)$method) !== '') {
            list($method_name, $method_custom) = term_plan_normalize_method($method, $custom);
        }
        $remark = '';
    } elseif ($status === 'missed') {
        $method_name = '';
        $method_custom = '';
    } else {
        $method_name = '';
        $method_custom = '';
        $remark = '';
    }
    $st = $pdo->prepare("
        INSERT INTO term_plan_task_checks
            (task_id, class_id, teacher_id, status, method, method_custom, teacher_remark)
        VALUES (?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            teacher_id = VALUES(teacher_id),
            status = VALUES(status),
            method = VALUES(method),
            method_custom = VALUES(method_custom),
            teacher_remark = VALUES(teacher_remark)
    ");
    $st->execute([
        (int)$task_id,
        (int)$class_id,
        (int)$teacher_id,
        $status,
        $method_name !== '' ? $method_name : null,
        $method_custom !== '' ? $method_custom : null,
        $remark !== '' ? $remark : null,
    ]);
    $row = [
        'id' => (int)$task_id,
        'session_no' => 0,
        'title' => '',
        'sort_order' => 0,
        'source' => '',
        'status' => $status,
        'method' => $method_name,
        'method_custom' => $method_custom,
        'teacher_remark' => $remark,
    ];
    return term_plan_public_task($row);
}

function term_plan_norm_title($s) {
    if (!function_exists('ghazal_normalize_digits')) {
        require_once __DIR__ . '/digits.php';
    }
    $s = ghazal_normalize_digits((string)$s);
    $s = mb_strtolower(trim($s), 'UTF-8');
    $s = preg_replace('/\s+/u', ' ', $s);
    return $s;
}

function term_plan_header_key($raw) {
    $raw = trim(mb_strtolower((string)$raw, 'UTF-8'));
    $raw = str_replace(['‌', 'ـ'], '', $raw);
    $map = [
        'class' => 'class',
        'class_title' => 'class',
        'classtitle' => 'class',
        'کلاس' => 'class',
        'عنوان کلاس' => 'class',
        'level' => 'class',
        'سطح' => 'class',
        'session' => 'session',
        'session_no' => 'session',
        'sessionno' => 'session',
        'جلسه' => 'session',
        'شماره جلسه' => 'session',
        'task' => 'task',
        'activity' => 'task',
        'تسک' => 'task',
        'فعالیت' => 'task',
        'عنوان' => 'task',
        'supervisor remark' => 'remark',
        'supervisor_remark' => 'remark',
        'remark' => 'remark',
        'ریمارک' => 'remark',
        'ریمارک سوپروایزر' => 'remark',
        'توضیح سوپروایزر' => 'remark',
    ];
    if (isset($map[$raw])) {
        return $map[$raw];
    }
    $ascii = preg_replace('/[^a-z0-9_]+/', '', $raw);
    return $map[$ascii] ?? $ascii;
}

function term_plan_parse_session_no($raw) {
    if (!function_exists('ghazal_normalize_digits')) {
        require_once __DIR__ . '/digits.php';
    }
    $raw = ghazal_normalize_digits(trim((string)$raw));
    if ($raw === '') {
        return 0;
    }
    if (preg_match('/(\d{1,2})/', $raw, $m)) {
        $n = (int)$m[1];
        if ($n >= 1 && $n <= 13) {
            return $n;
        }
    }
    return 0;
}

function term_plan_match_classes(array $index, $raw) {
    $raw = trim((string)$raw);
    if ($raw === '') {
        return [];
    }
    $norm = term_plan_norm_title($raw);
    if (isset($index['norm'][$norm])) {
        return [$index['norm'][$norm]];
    }
    $hits = [];
    foreach ($index['all'] as $c) {
        $t = term_plan_norm_title($c['title'] ?? '');
        if ($t === $norm) {
            $hits[] = $c;
            continue;
        }
        if ($norm !== '' && preg_match('/(?:^|\s)' . preg_quote($norm, '/') . '(?:\s|$)/u', $t)) {
            $hits[] = $c;
        }
    }
    if ($hits) {
        return $hits;
    }
    foreach ($index['all'] as $c) {
        $t = term_plan_norm_title($c['title'] ?? '');
        if ($norm !== '' && (mb_strpos($t, $norm) === 0 || mb_strpos($norm, $t) === 0)) {
            $hits[] = $c;
        }
    }
    return $hits;
}

function term_plan_class_index(array $classes) {
    $index = ['norm' => [], 'all' => $classes];
    foreach ($classes as $c) {
        $index['norm'][term_plan_norm_title($c['title'] ?? '')] = $c;
    }
    return $index;
}

function term_plan_read_xlsx_rows($tmp) {
    if (!function_exists('students_info_read_xlsx')) {
        require_once __DIR__ . '/students-info.php';
    }
    $sheets = students_info_read_xlsx($tmp);
    $rows = [];
    foreach ($sheets as $name => $sheet_rows) {
        if (!$sheet_rows) {
            continue;
        }
        $nlow = mb_strtolower(trim((string)$name), 'UTF-8');
        if (in_array($nlow, ['guide', 'راهنما', 'readme'], true)) {
            continue;
        }
        $headers = [];
        $header_row = $sheet_rows[0] ?? [];
        foreach ($header_row as $i => $h) {
            $headers[$i] = term_plan_header_key($h);
        }
        if (!in_array('session', $headers, true) || !in_array('task', $headers, true)) {
            continue;
        }
        for ($r = 1; $r < count($sheet_rows); $r++) {
            $line = $sheet_rows[$r];
            $assoc = ['class' => '', 'session' => '', 'task' => '', 'remark' => ''];
            foreach ($headers as $i => $key) {
                if ($key === '' || !array_key_exists($key, $assoc)) {
                    continue;
                }
                $assoc[$key] = isset($line[$i]) ? trim((string)$line[$i]) : '';
            }
            $rows[] = $assoc;
        }
    }
    if (!$rows) {
        throw new Exception('ستون‌های Class / Session / Task در فایل اکسل پیدا نشد.');
    }
    return $rows;
}

function term_plan_import_xlsx(PDO $pdo, $tmp, $supervisor_id) {
    term_plan_ensure_schema($pdo);
    $rows = term_plan_read_xlsx_rows($tmp);
    $classes = term_plan_supervisor_classes($pdo);
    $index = term_plan_class_index($classes);
    $grouped = [];
    $errors = [];
    $unmatched = [];
    foreach ($rows as $i => $row) {
        $line = $i + 2;
        $title = trim((string)$row['task']);
        $session_no = term_plan_parse_session_no($row['session']);
        $class_raw = trim((string)$row['class']);
        $remark = trim((string)$row['remark']);
        if ($title === '' && $session_no < 1 && $class_raw === '' && $remark === '') {
            continue;
        }
        if ($session_no < 1) {
            $errors[] = 'ردیف ' . $line . ': شماره جلسه نامعتبر است.';
            continue;
        }
        if ($title === '' && $remark === '') {
            $errors[] = 'ردیف ' . $line . ': تسک یا ریمارک خالی است.';
            continue;
        }
        $matched = term_plan_match_classes($index, $class_raw);
        if (!$matched) {
            $unmatched[$class_raw] = true;
            $errors[] = 'ردیف ' . $line . ': کلاس «' . $class_raw . '» پیدا نشد.';
            continue;
        }
        foreach ($matched as $class) {
            $cid = (int)$class['id'];
            $key = $cid . ':' . $session_no;
            if (!isset($grouped[$key])) {
                $grouped[$key] = [
                    'class_id' => $cid,
                    'session_no' => $session_no,
                    'tasks' => [],
                    'remark' => '',
                ];
            }
            if ($title !== '') {
                $grouped[$key]['tasks'][] = $title;
            }
            if ($remark !== '') {
                $grouped[$key]['remark'] = $remark;
            }
        }
    }
    $tasks_written = 0;
    $remarks_written = 0;
    $classes_touched = [];
    $pdo->beginTransaction();
    try {
        foreach ($grouped as $g) {
            $cid = (int)$g['class_id'];
            $sno = (int)$g['session_no'];
            $classes_touched[$cid] = true;
            if ($g['tasks']) {
                term_plan_replace_supervisor_tasks($pdo, $cid, $sno, $g['tasks'], $supervisor_id);
                $tasks_written += count($g['tasks']);
            }
            if ($g['remark'] !== '') {
                term_plan_save_supervisor_remark($pdo, $cid, $sno, $g['remark'], $supervisor_id);
                $remarks_written++;
            }
        }
        $pdo->commit();
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
    return [
        'tasks' => $tasks_written,
        'remarks' => $remarks_written,
        'classes' => count($classes_touched),
        'errors' => $errors,
        'unmatched' => array_keys($unmatched),
    ];
}

function term_plan_replace_supervisor_tasks(PDO $pdo, $class_id, $session_no, array $titles, $supervisor_id) {
    $st = $pdo->prepare("
        SELECT id, title FROM term_plan_tasks
        WHERE class_id = ? AND session_no = ? AND source = 'supervisor'
        ORDER BY sort_order ASC, id ASC
    ");
    $st->execute([(int)$class_id, (int)$session_no]);
    $existing = $st->fetchAll(PDO::FETCH_ASSOC);
    $used = [];
    $order = 1;
    foreach ($titles as $title) {
        $title = trim((string)$title);
        if ($title === '') {
            continue;
        }
        $found_id = 0;
        foreach ($existing as $ex) {
            if (isset($used[(int)$ex['id']])) {
                continue;
            }
            if (term_plan_norm_title($ex['title']) === term_plan_norm_title($title)) {
                $found_id = (int)$ex['id'];
                break;
            }
        }
        if ($found_id) {
            $pdo->prepare("UPDATE term_plan_tasks SET title = ?, sort_order = ? WHERE id = ?")
                ->execute([$title, $order, $found_id]);
            $used[$found_id] = true;
        } else {
            $ins = $pdo->prepare("
                INSERT INTO term_plan_tasks (class_id, session_no, title, sort_order, source, created_by)
                VALUES (?, ?, ?, ?, 'supervisor', ?)
            ");
            $ins->execute([(int)$class_id, (int)$session_no, $title, $order, (int)$supervisor_id]);
        }
        $order++;
    }
    foreach ($existing as $ex) {
        $id = (int)$ex['id'];
        if (!isset($used[$id])) {
            $pdo->prepare("DELETE FROM term_plan_task_checks WHERE task_id = ?")->execute([$id]);
            $pdo->prepare("DELETE FROM term_plan_tasks WHERE id = ?")->execute([$id]);
        }
    }
}

function term_plan_export_rows(PDO $pdo) {
    $classes = term_plan_supervisor_classes($pdo);
    $by_id = [];
    foreach ($classes as $c) {
        $by_id[(int)$c['id']] = $c;
    }
    $st = $pdo->query("
        SELECT t.class_id, t.session_no, t.title, t.sort_order, r.remark
        FROM term_plan_tasks t
        LEFT JOIN term_plan_session_remarks r
            ON r.class_id = t.class_id AND r.session_no = t.session_no
        WHERE t.source = 'supervisor'
        ORDER BY t.class_id, t.session_no, t.sort_order, t.id
    ");
    $rows = [['Class', 'Session', 'Task', 'Supervisor Remark']];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $cid = (int)$row['class_id'];
        $title = $by_id[$cid]['title'] ?? ('#' . $cid);
        $rows[] = [
            $title,
            (string)(int)$row['session_no'],
            (string)$row['title'],
            (string)($row['remark'] ?? ''),
        ];
    }
    $extra = $pdo->query("
        SELECT r.class_id, r.session_no, r.remark
        FROM term_plan_session_remarks r
        LEFT JOIN term_plan_tasks t
            ON t.class_id = r.class_id AND t.session_no = r.session_no AND t.source = 'supervisor'
        WHERE t.id IS NULL AND r.remark IS NOT NULL AND TRIM(r.remark) <> ''
        ORDER BY r.class_id, r.session_no
    ")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($extra as $row) {
        $cid = (int)$row['class_id'];
        $title = $by_id[$cid]['title'] ?? ('#' . $cid);
        $rows[] = [$title, (string)(int)$row['session_no'], '', (string)$row['remark']];
    }
    return $rows;
}

function term_plan_template_rows(PDO $pdo) {
    $classes = term_plan_supervisor_classes($pdo);
    $rows = [['Class', 'Session', 'Task', 'Supervisor Remark']];
    $n = 0;
    foreach ($classes as $c) {
        $total = term_plan_session_total($c);
        for ($s = 1; $s <= min(2, $total); $s++) {
            $rows[] = [$c['title'], (string)$s, $s === 1 ? 'Warm-up' : '', ''];
            $n++;
            if ($n >= 12) {
                break 2;
            }
        }
    }
    if ($n === 0) {
        $rows[] = ['B1 Even 16:00', '1', 'Warm-up', ''];
        $rows[] = ['B1 Even 16:00', '1', 'Presentation', ''];
        $rows[] = ['B1 Even 16:00', '2', 'Controlled practice', ''];
    }
    $guide = [
        ['Term Plan Excel'],
        ['هر ردیف یک تسک برای یک جلسه از یک کلاس است.'],
        ['Class = عنوان کامل کلاس در پنل، یا سطح مثل B1 برای همه کلاس‌های آن سطح.'],
        ['Session = شماره جلسه از 1 تا 13 (خصوصی تا 10).'],
        ['Task = متن تسکی که معلم باید تیک بزند.'],
        ['Supervisor Remark = ریمارک همان جلسه؛ روی چند ردیف تکرار شود، آخرین مقدار می‌ماند.'],
        ['تسک‌هایی که معلم خودش Add Task کرده با ایمپورت پاک نمی‌شوند.'],
    ];
    return [
        'Term Plan' => $rows,
        'Guide' => $guide,
    ];
}

function term_plan_send_xlsx(array $sheets, $filename) {
    if (!function_exists('students_info_write_xlsx')) {
        require_once __DIR__ . '/students-info.php';
    }
    $tmp = tempnam(sys_get_temp_dir(), 'tpxlsx');
    students_info_write_xlsx($tmp, $sheets);
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-store');
    readfile($tmp);
    @unlink($tmp);
    exit;
}

function term_plan_json($payload, $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function term_plan_method_options_html($selected) {
    $html = '<option value="">انتخاب متد…</option>';
    foreach (term_plan_methods() as $m) {
        $sel = ($selected === $m) ? ' selected' : '';
        $html .= '<option value="' . term_plan_h($m) . '"' . $sel . '>' . term_plan_h($m) . '</option>';
    }
    return $html;
}

function term_plan_render_task_row(array $task, $role) {
    $task = term_plan_public_task($task);
    $is_teacher = $role === 'teacher';
    $editable = $role === 'supervisor';
    $cls = 'tp-task';
    if ($task['status'] === 'done') {
        $cls .= ' is-done';
    }
    if ($task['status'] === 'missed') {
        $cls .= ' is-missed';
    }
    if ($task['needs_method']) {
        $cls .= ' needs-method';
    }
    if ($task['needs_remark']) {
        $cls .= ' needs-remark';
    }
    $check_cls = 'tp-check';
    $check_txt = '';
    if ($task['status'] === 'done') {
        $check_cls .= ' is-done';
        $check_txt = '✓';
    } elseif ($task['status'] === 'missed') {
        $check_cls .= ' is-missed';
        $check_txt = '✕';
    }
    $src = $task['source'] === 'teacher' ? '<span class="tp-src">معلم</span>' : '';
    $del = '';
    if ($editable || ($is_teacher && $task['source'] === 'teacher')) {
        $del = '<button type="button" class="tp-del" title="حذف">×</button>';
    }
    $check = $is_teacher
        ? '<button type="button" class="' . $check_cls . '">' . $check_txt . '</button>'
        : '<span class="' . $check_cls . '" style="cursor:default">' . $check_txt . '</span>';
    if ($editable) {
        $title = '<div class="tp-task-title">' . $src
            . '<input class="tp-task-title-input" value="' . term_plan_h($task['title']) . '"></div>';
    } else {
        $title = '<div class="tp-task-title">' . $src
            . '<span class="tp-title-text">' . term_plan_h($task['title']) . '</span></div>';
    }
    $extra = '';
    if ($is_teacher) {
        $other_on = ($task['method'] === 'Other') ? ' is-on' : '';
        $mw_req = $task['needs_method'] ? ' is-required' : '';
        $rw_req = $task['needs_remark'] ? ' is-required' : '';
        $extra .= '<div class="tp-method-wrap' . $mw_req . '">';
        $extra .= '<select class="tp-select" dir="ltr">' . term_plan_method_options_html($task['method']) . '</select>';
        $extra .= '<div class="tp-custom' . $other_on . '">';
        $extra .= '<input class="tp-input" dir="ltr" placeholder="متد خودتان را بنویسید" value="' . term_plan_h($task['method_custom']) . '">';
        $extra .= '</div><div class="tp-hint tp-hint-method">انتخاب متد ضروری است</div></div>';
        $extra .= '<div class="tp-remark-wrap' . $rw_req . '">';
        $extra .= '<textarea class="tp-textarea" rows="3" placeholder="چرا این تسک انجام نشد؟ چند جمله بنویسید">'
            . term_plan_h($task['teacher_remark']) . '</textarea>';
        $extra .= '<div class="tp-hint tp-hint-remark">نوشتن ریمارک برای ضربدر ضروری است</div></div>';
    } else {
        if ($task['status'] === 'done') {
            $m = $task['method'] === 'Other' ? ($task['method_custom'] ?: 'Other') : ($task['method'] ?: 'بدون متد');
            $label = 'معلم: ✓ ' . $m;
        } elseif ($task['status'] === 'missed') {
            $label = 'معلم: ✕ ' . ($task['teacher_remark'] !== '' ? $task['teacher_remark'] : 'بدون ریمارک');
        } else {
            $label = 'معلم هنوز وضعیتی نزده';
        }
        $extra .= '<div class="tp-readonly-status">' . term_plan_h($label) . '</div>';
    }
    return '<div class="' . $cls . '" data-task-id="' . (int)$task['id'] . '" data-source="'
        . term_plan_h($task['source']) . '" data-status="' . term_plan_h($task['status']) . '">'
        . '<div class="tp-task-row">' . $check
        . '<div class="tp-task-body">' . $title . $extra . '</div>'
        . $del . '</div></div>';
}
