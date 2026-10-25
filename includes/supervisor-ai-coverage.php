<?php
/**
 * Extra supervisor-panel intents: homework, EAS, observation, error gravity,
 * Mind Lingo, report card, study plan, live classes, staff/users/terms.
 * Read-only. Loaded from supervisor-ai.php.
 */

function supervisor_ai_has_negation($text) {
    return (bool)preg_match('/نزد|نزده|نزن|نکرد|نشده|ننوشت|ننشوت|بدون|نداده|ندادن|ندارن|ندارد|نداره|نیست|نیستن|missing|didn|without|who (?:has )?not/iu', $text);
}

function supervisor_ai_feature_topic($text) {
    $text = supervisor_ai_unfold_finglish($text);
    if (supervisor_ai_asks_missing_rollcall($text) || supervisor_ai_asks_missing_session_plan($text) || supervisor_ai_asks_absences($text)) {
        return '';
    }
    if (function_exists('supervisor_ai_asks_missing_phone') && supervisor_ai_asks_missing_phone($text)) {
        return 'missing_phone';
    }
    if (preg_match('/کجا(?:ست|یه)?|کدوم صفحه|کدام صفحه|صفحهٔ?\s+|کجای پنل|پنل چی داره|نقشهٔ?\s*پنل|explain.?panel|چیکار میتونی|چه کارهایی|چه می‌توانی|help\b/iu', $text)) {
        return 'explain_panel';
    }
    if (preg_match('/کلاس‌بندی|کلاس بندى|placement|ترم\s*جدید/iu', $text)) {
        return 'placement';
    }
    if (preg_match('/پرونده|students?\s*info|شماره\s*پرونده/iu', $text)) {
        return 'student_info';
    }
    if (function_exists('supervisor_ai_asks_class_link') && supervisor_ai_asks_class_link($text)) {
        return 'class_link';
    }
    if (preg_match('/تکلیف|homework|assignment/iu', $text)) {
        if (supervisor_ai_has_negation($text) && preg_match('/معلم|ثبت|رول|کلاس‌هایی که|چه معلم/u', $text)) {
            return 'missing_homework';
        }
        if (supervisor_ai_has_negation($text) || preg_match('/نداده|ناقص|incomplete|not[_\s-]*submitted/iu', $text)) {
            if (preg_match('/معلم|ثبت نشده/u', $text)) {
                return 'missing_homework';
            }
            return 'homework_status';
        }
        return preg_match('/ثبت نشده|نزده‌اند|نزدن/u', $text) ? 'missing_homework' : 'homework_status';
    }
    if (preg_match('/ارور\s*گراویتی|error\s*gravity|نقاط\s*ضعف|نقاط\s*قوت/iu', $text)) {
        return supervisor_ai_has_negation($text) ? 'missing_error_gravity' : 'error_gravity';
    }
    if (preg_match('/فرم\s*(?:آبزرو|مشاهده)|observation\s*form|آبزرو\s*form/iu', $text)) {
        return supervisor_ai_has_negation($text) ? 'missing_observation_form' : 'observation_form';
    }
    $is_mind_lingo = (bool)preg_match('/مایند\s*لینگو|mind\s*lingo/iu', $text);
    if (!$is_mind_lingo && preg_match('/آبزرور?|آبزرو|observe|observation|ارزیابی/iu', $text)) {
        return 'eas_observation';
    }
    if (preg_match('/مصاحبه|interview/iu', $text)) {
        return 'eas_interview';
    }
    if (preg_match('/نظرسنجی|پُل\b|poll/iu', $text)) {
        return 'eas_poll';
    }
    if (preg_match('/\bEAS\b|ای‌ای‌اس|ای ای اس/iu', $text)) {
        return 'eas_overview';
    }
    if (preg_match('/مایند\s*لینگو|mind\s*lingo/iu', $text)) {
        return supervisor_ai_has_negation($text) ? 'missing_mind_lingo' : 'mind_lingo';
    }
    if (function_exists('supervisor_ai_asks_previous_term') && supervisor_ai_asks_previous_term($text)
        && (preg_match('/نمره|nomre|کارنامه|quiz|کوییز|نمرات/iu', $text)
            || supervisor_ai_asks_classes($text)
            || supervisor_ai_guess_class_query($text) !== '')) {
        return 'report_card';
    }
    if (preg_match('/کارنامه|نمره|کوییز|quiz|report\s*card|nomre/iu', $text)) {
        return 'report_card';
    }
    if (preg_match('/سشن|session\s*plan|طرح[\s‌]*جلسه|session[\s_\-]*plan/iu', $text)) {
        return supervisor_ai_has_negation($text) ? 'missing_session_plan' : 'session_plan';
    }
    if (preg_match('/رول[\s‌\-]*کال|رولکال|roll[\s\-]*call|حضور\s*غیاب|حضورغیاب|attendance/iu', $text)) {
        return supervisor_ai_has_negation($text) ? 'missing_rollcall' : 'class_session';
    }
    if (preg_match('/استادی[\s‌]*پلن|study\s*plan/iu', $text)) {
        return 'study_plan';
    }
    if (preg_match('/لایو|در حال برگزاری|class_live|live\s*now|دایرهٔ?\s*سبز/iu', $text)) {
        return 'live_now';
    }
    if (function_exists('supervisor_ai_asks_previous_term') && supervisor_ai_asks_previous_term($text)) {
        return '';
    }
    if (preg_match('/ترم\s*جاری|چه ترم|لیست\s*ترم|\blist\s*terms?\b/iu', $text) && !supervisor_ai_asks_classes($text)) {
        return 'terms';
    }
    if (preg_match('/چند\s*(دانش|معلم|کاربر|استاد)|تعداد\s*(دانش|معلم|کاربر)|لیست\s*کاربر/u', $text)) {
        return 'users_counts';
    }
    if (preg_match('/لیست\s*(معلم|معلم‌ها|معلما|اساتید|استاد)/u', $text)
        || preg_match('/چه\s*(معلم|استاد).*(داریم|هستن|هستند)/u', $text)) {
        if (!supervisor_ai_has_negation($text)) {
            return 'teachers_list';
        }
    }
    return '';
}

function supervisor_ai_class_from_history(array $history, $skip_text = '') {
    $skip = supervisor_ai_norm($skip_text);
    for ($i = count($history) - 1; $i >= 0; $i--) {
        if (($history[$i]['role'] ?? '') !== 'user') {
            continue;
        }
        $text = supervisor_ai_norm($history[$i]['content'] ?? '');
        if ($text === '' || ($skip !== '' && $text === $skip)) {
            continue;
        }
        $c = supervisor_ai_guess_class_query($text);
        if ($c !== '') {
            return $c;
        }
    }
    return '';
}

function supervisor_ai_teacher_name_from_person(PDO $pdo, $person) {
    if ($person === '') {
        return '';
    }
    $found = supervisor_ai_lookup_person($pdo, $person);
    $teachers = [];
    foreach (($found['people'] ?? []) as $p) {
        if (($p['role'] ?? '') === 'teacher') {
            $teachers[] = (string)($p['full_name'] ?? '');
        }
    }
    if (count($teachers) === 1) {
        return $teachers[0];
    }
    return '';
}

function supervisor_ai_all_teaching_classes(PDO $pdo, $day_code = '') {
    if (!function_exists('term_sql_teaching')) {
        require_once __DIR__ . '/terms.php';
    }
    $sql = "
        SELECT c.id, c.title, c.schedule, c.schedule_type, c.class_type,
               u.full_name AS teacher_name, t.title AS term_title, t.starts_on
        FROM classes c
        JOIN terms t ON c.term_id = t.id
        LEFT JOIN users u ON c.teacher_id = u.id
        WHERE c.is_deleted = 0
          AND IFNULL(c.is_archived, 0) = 0
          AND " . term_sql_teaching('t') . "
    ";
    $params = [];
    if ($day_code === 'even' || $day_code === 'odd') {
        $sql .= " AND c.schedule_type = ?";
        $params[] = $day_code;
    }
    $sql .= " ORDER BY c.schedule, c.title";
    $st = $pdo->prepare($sql);
    $st->execute($params);
    return $st ? $st->fetchAll(PDO::FETCH_ASSOC) : [];
}

function supervisor_ai_filter_teacher(array $rows, $teacher_name) {
    if ($teacher_name === '') {
        return $rows;
    }
    $want = supervisor_ai_fold_latin($teacher_name);
    $out = [];
    foreach ($rows as $row) {
        $have = supervisor_ai_fold_latin($row['teacher'] ?? $row['teacher_name'] ?? '');
        if ($have !== '' && ($have === $want || supervisor_ai_names_close($have, $want) || strpos($have, $want) !== false || strpos($want, $have) !== false)) {
            $out[] = $row;
        }
    }
    return $out;
}

function supervisor_ai_limit_lines(array $lines, $max = 24) {
    if (count($lines) <= $max) {
        return $lines;
    }
    $head = array_slice($lines, 0, $max);
    $head[] = '… و ' . (count($lines) - $max) . ' مورد دیگر.';
    return $head;
}

function supervisor_ai_missing_homework(PDO $pdo, $date = '') {
    $when = supervisor_ai_resolve_date($date);
    if (!empty($when['error'])) {
        return ['ok' => false, 'error' => $when['error']];
    }
    $gregorian = $when['gregorian'];
    $code = supervisor_ai_day_code_of_date($gregorian);
    if ($code === 'friday') {
        return [
            'ok' => true,
            'date' => $when,
            'day_kind' => 'جمعه',
            'scheduled_count' => 0,
            'done_count' => 0,
            'missing_count' => 0,
            'not_started_count' => 0,
            'missing' => [],
            'not_started' => [],
            'summary_fa' => 'جمعه کلاسی نیست.',
        ];
    }
    $classes = supervisor_ai_teaching_classes_for_day($pdo, $code);
    $check = $pdo->prepare("SELECT COUNT(*) FROM homework_status WHERE class_id = ? AND homework_date = ?");
    $done = [];
    $missing = [];
    $not_started = [];
    foreach ($classes as $row) {
        try {
            $check->execute([(int)$row['id'], $gregorian]);
            $n = (int)$check->fetchColumn();
        } catch (Exception $e) {
            $n = 0;
        }
        $started = supervisor_ai_class_started_by($row['schedule'] ?? '', $gregorian);
        $item = supervisor_ai_gap_item($row, $started);
        if ($n > 0) {
            $done[] = $item;
        } elseif (!$started) {
            $not_started[] = $item;
        } else {
            $missing[] = $item;
        }
    }
    return [
        'ok' => true,
        'date' => $when,
        'day_kind' => $code === 'even' ? 'زوج' : 'فرد',
        'scheduled_count' => count($classes),
        'done_count' => count($done),
        'missing_count' => count($missing),
        'not_started_count' => count($not_started),
        'missing' => $missing,
        'not_started' => $not_started,
        'done' => $done,
    ];
}

function supervisor_ai_format_missing_homework(array $data, $teacher_name = '') {
    if (!empty($data['error'])) {
        return (string)$data['error'];
    }
    $when = $data['date'] ?? [];
    $head = supervisor_ai_norm(trim(
        (string)($when['relative'] ?? '') . ' — ' . (string)($when['jalali'] ?? '') . ' — ' . (string)($when['weekday'] ?? '')
    ));
    $missing = supervisor_ai_filter_teacher($data['missing'] ?? [], $teacher_name);
    $not_started = supervisor_ai_filter_teacher($data['not_started'] ?? [], $teacher_name);
    $done_n = (int)($data['done_count'] ?? 0);
    $sched = (int)($data['scheduled_count'] ?? 0);
    if ($sched === 0) {
        return $head . "\nبرای این تاریخ کلاسی برنامه‌ریزی نشده است.";
    }
    $lines = [
        $head . ' — روز ' . (string)($data['day_kind'] ?? ''),
        'از ' . $sched . ' کلاس آن روز، ' . $done_n . ' تکلیف ثبت کرده‌اند.',
    ];
    if ($missing) {
        $lines[] = 'تکلیف ثبت‌نشده (' . count($missing) . ' کلاس):';
        $lines = array_merge($lines, supervisor_ai_format_gap_lines($missing));
    } else {
        $lines[] = 'همهٔ کلاس‌هایی که تا الان باید تکلیف ثبت می‌کردند، ثبت کرده‌اند.';
    }
    if ($not_started) {
        $lines[] = count($not_started) . ' کلاس هنوز شروع نشده و تکلیف‌شان بعداً سنجیده می‌شود.';
    }
    return implode("\n", $lines);
}

function supervisor_ai_homework_status(PDO $pdo, $date = '', $class_query = '') {
    $when = supervisor_ai_resolve_date($date);
    if (!empty($when['error'])) {
        return ['ok' => false, 'error' => $when['error']];
    }
    $gregorian = $when['gregorian'];
    if (!function_exists('term_sql_teaching')) {
        require_once __DIR__ . '/terms.php';
    }
    $sql = "
        SELECT c.title, u.full_name, u.username AS file_number, h.status
        FROM homework_status h
        JOIN classes c ON c.id = h.class_id
        JOIN terms t ON c.term_id = t.id
        JOIN users u ON u.id = h.user_id
        WHERE h.homework_date = ?
          AND c.is_deleted = 0
          AND IFNULL(c.is_archived, 0) = 0
          AND " . term_sql_teaching('t') . "
          AND u.role = 'student'
          AND u.is_deleted = 0
          AND h.status IN ('not_submitted', 'incomplete')
    ";
    $params = [$gregorian];
    if ($class_query !== '') {
        $sql .= " AND c.title LIKE ?";
        $params[] = '%' . $class_query . '%';
    }
    $sql .= " ORDER BY c.title, u.full_name LIMIT 80";
    try {
        $st = $pdo->prepare($sql);
        $st->execute($params);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return ['ok' => false, 'error' => 'جدول تکلیف در دسترس نیست.'];
    }
    $saved = $pdo->prepare("
        SELECT COUNT(DISTINCT class_id) FROM homework_status h
        JOIN classes c ON c.id = h.class_id
        JOIN terms t ON c.term_id = t.id
        WHERE h.homework_date = ? AND c.is_deleted = 0 AND " . term_sql_teaching('t') . "
    ");
    $saved->execute([$gregorian]);
    return [
        'ok' => true,
        'date' => $when,
        'saved_class_count' => (int)$saved->fetchColumn(),
        'gap_count' => count($rows),
        'rows' => $rows,
    ];
}

function supervisor_ai_format_homework_status(array $data) {
    if (!empty($data['error'])) {
        return (string)$data['error'];
    }
    $when = $data['date'] ?? [];
    $head = supervisor_ai_norm(trim(
        (string)($when['relative'] ?? '') . ' — ' . (string)($when['jalali'] ?? '') . ' — ' . (string)($when['weekday'] ?? '')
    ));
    $n_saved = (int)($data['saved_class_count'] ?? 0);
    if ($n_saved === 0) {
        return $head . "\nبرای این تاریخ هیچ تکلیفی ثبت نشده است. این با «همه انجام داده‌اند» فرق دارد.";
    }
    $rows = $data['rows'] ?? [];
    if (!$rows) {
        return $head . "\nتکلیف ثبت شده و ناقص/نسپرده‌ای در این تاریخ نیست.";
    }
    $by = [];
    foreach ($rows as $row) {
        $title = (string)$row['title'];
        if (!isset($by[$title])) {
            $by[$title] = [];
        }
        $label = ((string)$row['status'] === 'incomplete') ? 'ناقص' : 'نسپرده';
        $by[$title][] = trim((string)$row['full_name'] . ' ' . (string)$row['file_number']) . ' (' . $label . ')';
    }
    $lines = [$head, 'تکلیف ناقص یا نسپرده در ' . count($by) . ' کلاس:'];
    foreach ($by as $title => $names) {
        $lines[] = '• ' . $title . ': ' . implode('، ', array_slice($names, 0, 8));
    }
    return implode("\n", supervisor_ai_limit_lines($lines, 28));
}

function supervisor_ai_eas_normalize_flag($flag) {
    $raw = trim((string)$flag);
    $f = strtolower(str_replace([' ', '-', '_'], '', $raw));
    if ($f === '') {
        return '';
    }
    if (in_array($f, ['observation', 'observe', 'obs', 'abzro', 'arzyabi', 'eval', 'evaluation'], true)
        || preg_match('/آبزرو|ارزیابی/u', $raw)) {
        return 'observation';
    }
    if (in_array($f, ['poll', 'survey'], true) || preg_match('/نظرسنجی/u', $raw)) {
        return 'poll';
    }
    if (in_array($f, ['interview', 'int'], true) || preg_match('/مصاحبه/u', $raw)) {
        return 'interview';
    }
    return '';
}

function supervisor_ai_eas_interview_plan(array $c) {
    $who = trim((string)($c['interviewer_name'] ?? ''));
    $when = trim((string)($c['interview_date'] ?? ''));
    $time = trim((string)($c['interview_time'] ?? ''));
    return trim(preg_replace('/\s+/u', ' ', $who . ' ' . $time . ' ' . $when));
}

function supervisor_ai_eas_gaps(PDO $pdo, $flag) {
    if (!function_exists('term_sql_teaching')) {
        require_once __DIR__ . '/terms.php';
    }
    $flag = supervisor_ai_eas_normalize_flag($flag);
    if ($flag === '') {
        return ['ok' => false, 'error' => 'flag باید interview یا observation یا poll باشد.'];
    }
    $col = [
        'interview' => 'interview_status',
        'observation' => 'observation_status',
        'poll' => 'poll_status',
    ][$flag];
    $select_base = 'c.title, u.full_name AS teacher_name,
               e.interview_status, e.observation_status, e.poll_status';
    $selects = [$select_base];
    if ($flag === 'interview') {
        $selects = [
            $select_base . ', e.interview_date, e.interviewer_name, e.interview_time',
            $select_base . ', e.interview_date, e.interviewer_name',
        ];
    }
    $rows = null;
    foreach ($selects as $select) {
        $st = $pdo->prepare("
            SELECT $select
            FROM classes c
            JOIN terms t ON c.term_id = t.id
            LEFT JOIN users u ON c.teacher_id = u.id
            LEFT JOIN eas_management e ON e.class_id = c.id
            WHERE c.is_deleted = 0
              AND IFNULL(c.is_archived, 0) = 0
              AND " . term_sql_teaching('t') . "
              AND (e.class_id IS NULL OR IFNULL(e.`$col`, 0) = 0)
            ORDER BY c.title
        ");
        try {
            $st->execute();
            $rows = $st->fetchAll(PDO::FETCH_ASSOC);
            break;
        } catch (Exception $e) {
            $rows = null;
        }
    }
    if ($rows === null) {
        return ['ok' => false, 'error' => 'جدول EAS در دسترس نیست.'];
    }
    return ['ok' => true, 'flag' => $flag, 'count' => count($rows), 'classes' => $rows];
}

function supervisor_ai_format_eas_gaps(array $data, $label) {
    if (!empty($data['error'])) {
        return (string)$data['error'];
    }
    $flag = supervisor_ai_eas_normalize_flag($data['flag'] ?? '');
    if ($flag === '' && $label === 'مصاحبه') {
        $flag = 'interview';
    }
    $n = (int)($data['count'] ?? 0);
    if ($n === 0) {
        return 'در ترم جاری همهٔ کلاس‌ها ' . $label . ' دارند.';
    }
    $lines = ['در ترم جاری ' . $n . ' کلاس ' . $label . ' تیک‌نخورده دارند.'];
    if ($flag === 'observation') {
        $lines[] = 'این تیک ارزیابی (آبزرو) صفحهٔ EAS است، نه برنامهٔ مصاحبه.';
    }
    $scheduled = 0;
    if ($flag === 'interview') {
        foreach (($data['classes'] ?? []) as $c) {
            if (supervisor_ai_eas_interview_plan($c) !== '') {
                $scheduled++;
            }
        }
        if ($scheduled > 0) {
            $lines[] = 'از این تعداد ' . $scheduled . ' تا تاریخ/مسئول مصاحبه دارند ولی هنوز تیک نخورده‌اند — با «بی‌برنامه» یکی نیست.';
        }
    }
    $i = 1;
    foreach (($data['classes'] ?? []) as $c) {
        $line = $i . '. ' . (string)$c['title'] . ' — معلم: ' . (string)($c['teacher_name'] ?? '');
        if ($flag === 'interview') {
            $plan = supervisor_ai_eas_interview_plan($c);
            if ($plan !== '') {
                $line .= ' — برنامه مصاحبه: ' . $plan . ' (تیک نخورده)';
            }
        }
        $lines[] = $line;
        $i++;
        if ($i > 30) {
            $lines[] = '… و موارد دیگر.';
            break;
        }
    }
    return implode("\n", $lines);
}

function supervisor_ai_eas_class(PDO $pdo, $class_query) {
    $matches = supervisor_ai_match_classes($pdo, $class_query);
    if (!$matches) {
        return ['ok' => false, 'error' => 'کلاسی با این عنوان در ترم جاری پیدا نشد.'];
    }
    if (count($matches) > 1 && supervisor_ai_norm($class_query) !== supervisor_ai_norm($matches[0]['title'])) {
        return ['ok' => true, 'ambiguous' => true, 'matches' => $matches];
    }
    $id = (int)$matches[0]['id'];
    $st = $pdo->prepare("
        SELECT c.title, u.full_name AS teacher_name,
               e.session_1, e.session_2, e.session_3, e.session_4, e.session_5,
               e.session_6, e.session_7, e.session_8, e.session_9, e.session_10,
               e.session_11, e.session_12, e.session_13,
               e.poll_status, e.observation_status, e.interview_status,
               e.interview_date, e.interviewer_name, e.admin_note
        FROM classes c
        LEFT JOIN users u ON c.teacher_id = u.id
        LEFT JOIN eas_management e ON e.class_id = c.id
        WHERE c.id = ?
        LIMIT 1
    ");
    $st->execute([$id]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return ['ok' => false, 'error' => 'کلاس پیدا نشد.'];
    }
    $done = 0;
    for ($i = 1; $i <= 13; $i++) {
        if ((int)($row['session_' . $i] ?? 0) > 0) {
            $done++;
        }
    }
    return ['ok' => true, 'row' => $row, 'sessions_done' => $done];
}

function supervisor_ai_format_eas_class(array $data) {
    if (!empty($data['error'])) {
        return (string)$data['error'];
    }
    if (!empty($data['ambiguous'])) {
        return supervisor_ai_format_class_overview($data);
    }
    $r = $data['row'] ?? [];
    $yn = function ($v) {
        return ((int)$v === 1) ? 'شده' : 'نشده';
    };
    $lines = [
        (string)($r['title'] ?? '') . ' — معلم: ' . (string)($r['teacher_name'] ?? ''),
        'جلسات تیک‌خورده: ' . (int)($data['sessions_done'] ?? 0) . ' از ۱۳',
        'مصاحبه: ' . $yn($r['interview_status'] ?? 0)
            . (supervisor_ai_eas_interview_plan($r) !== '' ? ' — ' . supervisor_ai_eas_interview_plan($r) : ''),
        'ارزیابی (آبزرو): ' . $yn($r['observation_status'] ?? 0),
        'نظرسنجی: ' . $yn($r['poll_status'] ?? 0),
    ];
    $note = trim((string)($r['admin_note'] ?? ''));
    if ($note !== '') {
        if (function_exists('mb_substr')) {
            $note = mb_substr($note, 0, 280, 'UTF-8');
        }
        $lines[] = 'یادداشت: ' . $note;
    }
    return implode("\n", $lines);
}

function supervisor_ai_error_gravity(PDO $pdo, $class_query = '', $missing_only = false) {
    if (!function_exists('term_sql_teaching')) {
        require_once __DIR__ . '/terms.php';
    }
    if ($class_query !== '' && !$missing_only) {
        $matches = supervisor_ai_match_classes($pdo, $class_query);
        if (!$matches) {
            return ['ok' => false, 'error' => 'کلاسی با این عنوان در ترم جاری پیدا نشد.'];
        }
        $id = (int)$matches[0]['id'];
        $st = $pdo->prepare("
            SELECT c.title, u.full_name AS teacher_name, eg.content
            FROM classes c
            LEFT JOIN users u ON c.teacher_id = u.id
            LEFT JOIN error_gravity eg ON eg.class_id = c.id AND IFNULL(eg.is_deleted, 0) = 0
            WHERE c.id = ?
            LIMIT 1
        ");
        $st->execute([$id]);
        return ['ok' => true, 'mode' => 'one', 'row' => $st->fetch(PDO::FETCH_ASSOC) ?: []];
    }
    $st = $pdo->query("
        SELECT c.title, u.full_name AS teacher_name
        FROM classes c
        JOIN terms t ON c.term_id = t.id
        LEFT JOIN users u ON c.teacher_id = u.id
        LEFT JOIN error_gravity eg ON eg.class_id = c.id AND IFNULL(eg.is_deleted, 0) = 0
        WHERE c.is_deleted = 0 AND IFNULL(c.is_archived, 0) = 0 AND " . term_sql_teaching('t') . "
          AND (eg.id IS NULL OR TRIM(IFNULL(eg.content, '')) = '')
        ORDER BY c.title
    ");
    $rows = $st ? $st->fetchAll(PDO::FETCH_ASSOC) : [];
    return ['ok' => true, 'mode' => 'missing', 'count' => count($rows), 'classes' => $rows];
}

function supervisor_ai_format_error_gravity(array $data) {
    if (!empty($data['error'])) {
        return (string)$data['error'];
    }
    if (($data['mode'] ?? '') === 'one') {
        $r = $data['row'] ?? [];
        $content = trim((string)($r['content'] ?? ''));
        $head = (string)($r['title'] ?? '') . ' — معلم: ' . (string)($r['teacher_name'] ?? '');
        if ($content === '') {
            return $head . "\nبرای این کلاس Error Gravity نوشته نشده است.";
        }
        if (function_exists('mb_substr') && mb_strlen($content, 'UTF-8') > 900) {
            $content = mb_substr($content, 0, 900, 'UTF-8') . '…';
        }
        return $head . "\n" . $content;
    }
    $n = (int)($data['count'] ?? 0);
    if ($n === 0) {
        return 'همهٔ کلاس‌های ترم جاری Error Gravity دارند.';
    }
    $lines = ['در ترم جاری ' . $n . ' کلاس Error Gravity ندارند:'];
    $i = 1;
    foreach (($data['classes'] ?? []) as $c) {
        $lines[] = $i . '. ' . (string)$c['title'] . ' — معلم: ' . (string)($c['teacher_name'] ?? '');
        $i++;
        if ($i > 30) {
            $lines[] = '… و موارد دیگر.';
            break;
        }
    }
    return implode("\n", $lines);
}

function supervisor_ai_observation_forms(PDO $pdo, $class_query = '', $missing_only = false) {
    if (!function_exists('term_sql_teaching')) {
        require_once __DIR__ . '/terms.php';
    }
    if ($class_query !== '' && !$missing_only) {
        $matches = supervisor_ai_match_classes($pdo, $class_query);
        if (!$matches) {
            return ['ok' => false, 'error' => 'کلاسی با این عنوان در ترم جاری پیدا نشد.'];
        }
        $id = (int)$matches[0]['id'];
        $st = $pdo->prepare("
            SELECT c.title, u.full_name AS teacher_name,
                   of.date, of.observer_name, of.strengths, of.areas_for_improvement, of.suggestions
            FROM classes c
            LEFT JOIN users u ON c.teacher_id = u.id
            LEFT JOIN observation_forms of ON of.class_id = c.id
            WHERE c.id = ?
            ORDER BY of.id DESC
            LIMIT 3
        ");
        $st->execute([$id]);
        return ['ok' => true, 'mode' => 'one', 'rows' => $st->fetchAll(PDO::FETCH_ASSOC)];
    }
    $st = $pdo->query("
        SELECT c.title, u.full_name AS teacher_name
        FROM classes c
        JOIN terms t ON c.term_id = t.id
        LEFT JOIN users u ON c.teacher_id = u.id
        LEFT JOIN observation_forms of ON of.class_id = c.id
        WHERE c.is_deleted = 0 AND IFNULL(c.is_archived, 0) = 0 AND " . term_sql_teaching('t') . "
          AND of.id IS NULL
        ORDER BY c.title
    ");
    $rows = $st ? $st->fetchAll(PDO::FETCH_ASSOC) : [];
    return ['ok' => true, 'mode' => 'missing', 'count' => count($rows), 'classes' => $rows];
}

function supervisor_ai_format_observation_forms(array $data) {
    if (!empty($data['error'])) {
        return (string)$data['error'];
    }
    if (($data['mode'] ?? '') === 'one') {
        $rows = $data['rows'] ?? [];
        if (!$rows) {
            return 'فرم مشاهده‌ای برای این کلاس پیدا نشد.';
        }
        $title = (string)($rows[0]['title'] ?? '');
        $lines = [$title . ' — معلم: ' . (string)($rows[0]['teacher_name'] ?? '')];
        $any = false;
        foreach ($rows as $r) {
            if (empty($r['date']) && empty($r['observer_name']) && empty($r['strengths'])) {
                continue;
            }
            $any = true;
            $lines[] = 'مشاهده ' . supervisor_ai_pretty_date((string)$r['date']) . ' — ناظر: ' . (string)($r['observer_name'] ?? '');
            if (trim((string)($r['strengths'] ?? '')) !== '') {
                $lines[] = 'نقاط قوت: ' . $r['strengths'];
            }
            if (trim((string)($r['areas_for_improvement'] ?? '')) !== '') {
                $lines[] = 'برای بهبود: ' . $r['areas_for_improvement'];
            }
        }
        if (!$any) {
            return $title . "\nفرم مشاهده‌ای برای این کلاس ثبت نشده است.";
        }
        return implode("\n", $lines);
    }
    $n = (int)($data['count'] ?? 0);
    if ($n === 0) {
        return 'همهٔ کلاس‌های ترم جاری حداقل یک فرم مشاهده دارند.';
    }
    $lines = ['در ترم جاری ' . $n . ' کلاس فرم مشاهده ندارند:'];
    $i = 1;
    foreach (($data['classes'] ?? []) as $c) {
        $lines[] = $i . '. ' . (string)$c['title'] . ' — معلم: ' . (string)($c['teacher_name'] ?? '');
        $i++;
        if ($i > 30) {
            $lines[] = '… و موارد دیگر.';
            break;
        }
    }
    return implode("\n", $lines);
}

function supervisor_ai_mind_lingo(PDO $pdo, $class_query = '', $missing_only = false) {
    if (!function_exists('term_sql_teaching')) {
        require_once __DIR__ . '/terms.php';
    }
    if ($class_query !== '' && !$missing_only) {
        $matches = supervisor_ai_match_classes($pdo, $class_query);
        if (!$matches) {
            return ['ok' => false, 'error' => 'کلاسی با این عنوان در ترم جاری پیدا نشد.'];
        }
        $id = (int)$matches[0]['id'];
        $st = $pdo->prepare("
            SELECT u.full_name, u.username AS file_number,
                   m.communication_dev, m.learning_eng, m.learning_cons, m.personal_growth
            FROM class_students cs
            JOIN users u ON u.id = cs.user_id
            LEFT JOIN mind_lingo m ON m.student_id = u.id AND m.class_id = ?
            WHERE cs.class_id = ? AND u.role = 'student' AND u.is_deleted = 0
            ORDER BY u.full_name
            LIMIT 40
        ");
        $st->execute([$id, $id]);
        return [
            'ok' => true,
            'mode' => 'one',
            'title' => (string)$matches[0]['title'],
            'teacher' => (string)($matches[0]['teacher_name'] ?? ''),
            'students' => $st->fetchAll(PDO::FETCH_ASSOC),
        ];
    }
    $st = $pdo->query("
        SELECT c.title, u.full_name AS teacher_name,
               COUNT(DISTINCT us.id) AS enrolled,
               COUNT(DISTINCT m.id) AS filled
        FROM classes c
        JOIN terms t ON c.term_id = t.id
        LEFT JOIN users u ON c.teacher_id = u.id
        LEFT JOIN class_students cs ON cs.class_id = c.id
        LEFT JOIN users us ON us.id = cs.user_id AND us.role = 'student' AND us.is_deleted = 0
        LEFT JOIN mind_lingo m ON m.class_id = c.id AND m.student_id = us.id
        WHERE c.is_deleted = 0 AND IFNULL(c.is_archived, 0) = 0 AND " . term_sql_teaching('t') . "
        GROUP BY c.id, c.title, u.full_name
        HAVING filled < enrolled
        ORDER BY c.title
    ");
    $rows = $st ? $st->fetchAll(PDO::FETCH_ASSOC) : [];
    return ['ok' => true, 'mode' => 'missing', 'count' => count($rows), 'classes' => $rows];
}

function supervisor_ai_format_mind_lingo(array $data) {
    if (!empty($data['error'])) {
        return (string)$data['error'];
    }
    if (($data['mode'] ?? '') === 'one') {
        $lines = [(string)($data['title'] ?? '') . ' — معلم: ' . (string)($data['teacher'] ?? ''), 'Mind Lingo:'];
        $missing = 0;
        foreach (($data['students'] ?? []) as $s) {
            $has = $s['communication_dev'] !== null && $s['communication_dev'] !== '';
            if (!$has) {
                $missing++;
                $lines[] = '• ' . $s['full_name'] . ' ' . $s['file_number'] . ' — ثبت نشده';
                continue;
            }
            $sum = (int)$s['communication_dev'] + (int)$s['learning_eng'] + (int)$s['learning_cons'] + (int)$s['personal_growth'];
            $lines[] = '• ' . $s['full_name'] . ' ' . $s['file_number'] . ' — مجموع ' . $sum
                . ' (C' . (int)$s['communication_dev']
                . ' E' . (int)$s['learning_eng']
                . ' L' . (int)$s['learning_cons']
                . ' P' . (int)$s['personal_growth'] . ')';
        }
        if ($missing) {
            $lines[] = $missing . ' نفر هنوز Mind Lingo ندارند.';
        }
        return implode("\n", supervisor_ai_limit_lines($lines, 28));
    }
    $n = (int)($data['count'] ?? 0);
    if ($n === 0) {
        return 'همهٔ کلاس‌های ترم جاری Mind Lingo کامل دارند.';
    }
    $lines = ['کلاس‌هایی که Mind Lingo ناقص است (' . $n . '):'];
    $i = 1;
    foreach (($data['classes'] ?? []) as $c) {
        $lines[] = $i . '. ' . (string)$c['title'] . ' — معلم: ' . (string)($c['teacher_name'] ?? '')
            . ' — ' . (int)$c['filled'] . '/' . (int)$c['enrolled'];
        $i++;
        if ($i > 30) {
            $lines[] = '… و موارد دیگر.';
            break;
        }
    }
    return implode("\n", $lines);
}

function supervisor_ai_current_teaching_term(PDO $pdo) {
    if (!function_exists('term_sql_teaching')) {
        require_once __DIR__ . '/terms.php';
    }
    $st = $pdo->query("
        SELECT *
        FROM terms t
        WHERE " . term_sql_teaching('t') . "
        ORDER BY t.starts_on DESC, t.id DESC
        LIMIT 1
    ");
    $row = $st ? $st->fetch(PDO::FETCH_ASSOC) : false;
    return $row ?: null;
}

function supervisor_ai_previous_term_row(PDO $pdo) {
    if (!function_exists('placement_previous_term')) {
        require_once __DIR__ . '/placement.php';
    }
    $current = supervisor_ai_current_teaching_term($pdo);
    if (!$current) {
        return null;
    }
    return placement_previous_term($pdo, $current);
}

function supervisor_ai_report_card(PDO $pdo, $class_query, $previous = false) {
    if ($previous) {
        return supervisor_ai_previous_term_report_card($pdo, $class_query);
    }
    if ($class_query === '') {
        return ['ok' => false, 'error' => 'برای کارنامه عنوان کلاس را بگو؛ مثلاً کارنامهٔ P1.'];
    }
    $matches = supervisor_ai_match_classes($pdo, $class_query);
    if (!$matches) {
        return ['ok' => false, 'error' => 'کلاسی با این عنوان در ترم جاری پیدا نشد.'];
    }
    if (count($matches) > 1 && supervisor_ai_norm($class_query) !== supervisor_ai_norm($matches[0]['title'])) {
        return ['ok' => true, 'ambiguous' => true, 'matches' => $matches];
    }
    $id = (int)$matches[0]['id'];
    if (is_file(__DIR__ . '/quizzes.php')) {
        require_once __DIR__ . '/quizzes.php';
        quizzes_ensure_schema($pdo);
    }
    $st = $pdo->prepare("
        SELECT u.full_name, u.username AS file_number,
               sq.quiz_1, sq.quiz_2, sq.quiz_3, sq.quiz_4, sq.quiz_5,
               sq.quiz_6, sq.quiz_7, sq.quiz_8, sq.quiz_9
        FROM class_students cs
        JOIN users u ON u.id = cs.user_id
        LEFT JOIN student_quizzes sq ON sq.user_id = u.id AND sq.class_id = ?
        WHERE cs.class_id = ? AND u.role = 'student' AND u.is_deleted = 0
        ORDER BY u.full_name
        LIMIT 40
    ");
    $st->execute([$id, $id]);
    return [
        'ok' => true,
        'title' => (string)$matches[0]['title'],
        'teacher' => (string)($matches[0]['teacher_name'] ?? ''),
        'students' => $st->fetchAll(PDO::FETCH_ASSOC),
    ];
}

function supervisor_ai_previous_term_report_card(PDO $pdo, $class_query) {
    if ($class_query === '') {
        return ['ok' => false, 'error' => 'برای نمرهٔ ترم قبل عنوان کلاس را بگو؛ مثلاً نمرات ترم قبل PI2.'];
    }
    $matches = supervisor_ai_match_classes($pdo, $class_query);
    if (!$matches) {
        return ['ok' => false, 'error' => 'کلاسی با این عنوان در ترم جاری پیدا نشد.'];
    }
    if (count($matches) > 1 && supervisor_ai_norm($class_query) !== supervisor_ai_norm($matches[0]['title'])) {
        return ['ok' => true, 'ambiguous' => true, 'matches' => $matches];
    }
    $prev = supervisor_ai_previous_term_row($pdo);
    if (!$prev) {
        return ['ok' => false, 'error' => 'ترم قبلی در پنل پیدا نشد.'];
    }
    $id = (int)$matches[0]['id'];
    $prev_id = (int)($prev['id'] ?? 0);
    if (is_file(__DIR__ . '/quizzes.php')) {
        require_once __DIR__ . '/quizzes.php';
        quizzes_ensure_schema($pdo);
    }
    $st = $pdo->prepare("
        SELECT u.id, u.full_name, u.username AS file_number
        FROM class_students cs
        JOIN users u ON u.id = cs.user_id
        WHERE cs.class_id = ? AND u.role = 'student' AND u.is_deleted = 0
        ORDER BY u.full_name
        LIMIT 40
    ");
    $st->execute([$id]);
    $roster = $st->fetchAll(PDO::FETCH_ASSOC);
    $by_user = [];
    if ($roster && $prev_id > 0) {
        $ids = [];
        foreach ($roster as $row) {
            $ids[] = (int)$row['id'];
        }
        $ids = array_values(array_filter($ids));
        if ($ids) {
            $in = implode(',', $ids);
            $hist = $pdo->query("
                SELECT cs.user_id, c.title AS class_title, u_teacher.full_name AS teacher_name,
                       t.title AS term_title,
                       sq.quiz_1, sq.quiz_2, sq.quiz_3, sq.quiz_4, sq.quiz_5,
                       sq.quiz_6, sq.quiz_7, sq.quiz_8, sq.quiz_9, sq.quiz_10,
                       ss.supervisor_score
                FROM class_students cs
                JOIN classes c ON c.id = cs.class_id AND c.is_deleted = 0
                LEFT JOIN terms t ON c.term_id = t.id
                LEFT JOIN users u_teacher ON c.teacher_id = u_teacher.id
                LEFT JOIN student_quizzes sq ON sq.user_id = cs.user_id AND sq.class_id = c.id
                LEFT JOIN supervisor_scores ss ON ss.user_id = cs.user_id AND ss.class_id = c.id
                WHERE cs.user_id IN ($in)
                  AND t.id = " . $prev_id . "
                ORDER BY c.title
            ");
            $rows = $hist ? $hist->fetchAll(PDO::FETCH_ASSOC) : [];
            foreach ($rows as $row) {
                $uid = (int)$row['user_id'];
                if (!isset($by_user[$uid])) {
                    $by_user[$uid] = [];
                }
                $totals = function_exists('quizzes_grade_totals') ? quizzes_grade_totals($row) : [];
                $by_user[$uid][] = [
                    'class_title' => (string)($row['class_title'] ?? ''),
                    'teacher_name' => (string)($row['teacher_name'] ?? ''),
                    'has_quiz_scores' => !empty($totals['has_quiz_scores']),
                    'class_total' => (int)($totals['class_total'] ?? 0),
                    'final_score' => (int)($totals['final_score'] ?? 0),
                    'supervisor_score' => $totals['supervisor_score'] ?? null,
                ];
            }
        }
    }
    $students = [];
    foreach ($roster as $row) {
        $students[] = [
            'full_name' => (string)$row['full_name'],
            'file_number' => (string)$row['file_number'],
            'previous_classes' => $by_user[(int)$row['id']] ?? [],
        ];
    }
    $prev_title = (string)($prev['title'] ?? '');
    if (function_exists('term_english_title') && $prev_title !== '') {
        $prev_title = term_english_title($prev_title);
    }
    return [
        'ok' => true,
        'previous' => true,
        'title' => (string)$matches[0]['title'],
        'teacher' => (string)($matches[0]['teacher_name'] ?? ''),
        'term_title' => $prev_title,
        'students' => $students,
    ];
}

function supervisor_ai_format_report_card(array $data) {
    if (!empty($data['error'])) {
        return (string)$data['error'];
    }
    if (!empty($data['ambiguous'])) {
        return supervisor_ai_format_class_overview($data);
    }
    if (!empty($data['previous'])) {
        $lines = [
            'نمرات ترم قبل دانش‌آموزان ' . (string)($data['title'] ?? '')
                . ' — معلم جاری: ' . (string)($data['teacher'] ?? ''),
            'ترم قبل: ' . (string)($data['term_title'] ?? ''),
        ];
        foreach (($data['students'] ?? []) as $s) {
            $prev_cls = $s['previous_classes'] ?? [];
            if (!$prev_cls) {
                $lines[] = '• ' . $s['full_name'] . ' ' . $s['file_number'] . ' — در ترم قبل کلاسی نداشته';
                continue;
            }
            foreach ($prev_cls as $pc) {
                $bit = '• ' . $s['full_name'] . ' ' . $s['file_number']
                    . ' — ' . (string)($pc['class_title'] ?? '');
                if (trim((string)($pc['teacher_name'] ?? '')) !== '') {
                    $bit .= ' — معلم: ' . $pc['teacher_name'];
                }
                if (!empty($pc['has_quiz_scores'])) {
                    $bit .= ' — نمره کلاس ' . (int)$pc['class_total'];
                    if ($pc['supervisor_score'] !== null && $pc['supervisor_score'] !== '') {
                        $bit .= ' — نهایی ' . (int)$pc['final_score'];
                    }
                } else {
                    $bit .= ' — نمره‌ای ثبت نشده';
                }
                $lines[] = $bit;
            }
        }
        return implode("\n", supervisor_ai_limit_lines($lines, 36));
    }
    $lines = [(string)($data['title'] ?? '') . ' — معلم: ' . (string)($data['teacher'] ?? ''), 'کارنامه (مجموع کوییزهای واردشده):'];
    foreach (($data['students'] ?? []) as $s) {
        $sum = 0;
        $filled = 0;
        for ($i = 1; $i <= 9; $i++) {
            $v = $s['quiz_' . $i] ?? null;
            if ($v !== null && $v !== '') {
                $sum += (float)$v;
                $filled++;
            }
        }
        $lines[] = '• ' . $s['full_name'] . ' ' . $s['file_number']
            . ($filled ? (' — ' . rtrim(rtrim(number_format($sum, 1), '0'), '.') . ' از ' . $filled . ' نمره') : ' — نمره‌ای ثبت نشده');
    }
    return implode("\n", supervisor_ai_limit_lines($lines, 28));
}

function supervisor_ai_study_plan(PDO $pdo, $text, $date = '') {
    if (function_exists('rollcall_ensure_study_plan_log')) {
        require_once __DIR__ . '/rollcall.php';
        rollcall_ensure_study_plan_log($pdo);
    } elseif (function_exists('rollcall_study_plan_last_sent')) {
        require_once __DIR__ . '/rollcall.php';
    }
    $want_nophone = (bool)preg_match('/شماره|تلفن|فون|phone/iu', $text);
    if ($want_nophone) {
        $st = $pdo->query("
            SELECT full_name, username AS file_number
            FROM users
            WHERE role = 'student' AND is_deleted = 0
              AND (phone IS NULL OR TRIM(phone) = '')
            ORDER BY full_name
            LIMIT 25
        ");
        $rows = $st ? $st->fetchAll(PDO::FETCH_ASSOC) : [];
        $cnt = $pdo->query("
            SELECT COUNT(*) FROM users
            WHERE role = 'student' AND is_deleted = 0
              AND (phone IS NULL OR TRIM(phone) = '')
        ");
        return ['ok' => true, 'mode' => 'nophone', 'count' => (int)$cnt->fetchColumn(), 'rows' => $rows];
    }
    $range = function_exists('supervisor_ai_date_range_from_text') ? supervisor_ai_date_range_from_text($text) : null;
    $when = null;
    $params = [];
    $sql = "
        SELECT u.full_name, u.username AS file_number, s.sent_at, s.family
        FROM study_plan_sms_log s
        JOIN users u ON u.id = s.user_id
        WHERE 1=1
    ";
    if ($range) {
        $sql .= " AND DATE(s.sent_at) >= ? AND DATE(s.sent_at) <= ?";
        $params[] = $range['from'];
        $params[] = $range['to'];
        $date = '';
    } elseif ($date !== '') {
        $when = supervisor_ai_resolve_date($date);
        if (!empty($when['error'])) {
            return ['ok' => false, 'error' => (string)$when['error']];
        }
        $sql .= " AND DATE(s.sent_at) = ?";
        $params[] = $when['gregorian'];
    }
    $sql .= " ORDER BY s.sent_at DESC LIMIT 200";
    try {
        $st = $pdo->prepare($sql);
        $st->execute($params);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return ['ok' => false, 'error' => 'لاگ Study Plan در دسترس نیست.'];
    }
    return [
        'ok' => true,
        'mode' => 'sent',
        'rows' => $rows,
        'filter_date' => $when,
        'relative' => $date,
        'range' => $range,
    ];
}

function supervisor_ai_format_study_plan(array $data) {
    if (!empty($data['error'])) {
        return (string)$data['error'];
    }
    if (($data['mode'] ?? '') === 'nophone') {
        $n = (int)($data['count'] ?? 0);
        if ($n === 0) {
            return 'همهٔ دانش‌آموزان شماره تلفن دارند.';
        }
        $lines = [$n . ' دانش‌آموز شماره تلفن ندارند (نمونه):'];
        foreach (($data['rows'] ?? []) as $r) {
            $lines[] = '• ' . $r['full_name'] . ' — پرونده ' . $r['file_number'];
        }
        return implode("\n", $lines);
    }
    $rows = $data['rows'] ?? [];
    $when = $data['filter_date'] ?? null;
    $rel = (string)($data['relative'] ?? '');
    $range = $data['range'] ?? null;
    if (is_array($range) && !empty($range['from'])) {
        $label = (string)($range['label'] ?? ('از ' . supervisor_ai_pretty_date($range['from']) . ' تا ' . supervisor_ai_pretty_date($range['to'] ?? '')));
        if (!$rows) {
            return 'هیچ Study Plan در بازهٔ ' . $label . ' ارسال نشده است.';
        }
        $lines = ['Study Planهای ارسال‌شده ' . $label . ' (' . count($rows) . ' ارسال):'];
    } elseif ($when) {
        $label = trim(($rel !== '' ? $rel . ' — ' : '') . (string)($when['jalali'] ?? '') . ' — ' . (string)($when['weekday'] ?? ''));
        if (!$rows) {
            return 'هیچ Study Plan در ' . $label . ' ارسال نشده است.';
        }
        $lines = ['Study Planهای ارسال‌شده ' . $label . ' (' . count($rows) . ' نفر):'];
    } else {
        if (!$rows) {
            return 'هنوز هیچ Study Plan ارسال نشده است.';
        }
        $lines = ['آخرین ارسال‌های Study Plan:'];
    }
    foreach ($rows as $r) {
        $when_txt = supervisor_ai_pretty_datetime($r['sent_at'] ?? '');
        $lines[] = '• ' . $r['full_name'] . ' ' . $r['file_number']
            . ($when_txt !== '' ? (' — ' . $when_txt) : '');
    }
    return implode("\n", $lines);
}

function supervisor_ai_phone_blank($phone) {
    $p = trim((string)$phone);
    return $p === '' || $p === '0' || $p === '-' || $p === '—';
}

function supervisor_ai_students_without_phone(PDO $pdo) {
    $st = $pdo->query("
        SELECT full_name, username AS file_number, phone
        FROM users
        WHERE role = 'student' AND is_deleted = 0
        ORDER BY full_name
    ");
    $all = $st ? $st->fetchAll(PDO::FETCH_ASSOC) : [];
    $rows = [];
    foreach ($all as $row) {
        if (supervisor_ai_phone_blank($row['phone'] ?? '')) {
            $rows[] = [
                'full_name' => (string)$row['full_name'],
                'file_number' => (string)$row['file_number'],
            ];
        }
    }
    return ['ok' => true, 'count' => count($rows), 'rows' => $rows];
}

function supervisor_ai_format_students_without_phone(array $data) {
    if (!empty($data['error'])) {
        return (string)$data['error'];
    }
    $rows = $data['rows'] ?? [];
    $n = (int)($data['count'] ?? count($rows));
    if ($n === 0) {
        return 'در Students Information همهٔ دانش‌آموزان شماره تماس دارند.';
    }
    $lines = [$n . ' دانش‌آموز در Students Information شماره تماس ثبت‌شده ندارند:'];
    $shown = array_slice($rows, 0, 80);
    foreach ($shown as $r) {
        $lines[] = '• ' . (string)($r['full_name'] ?? '') . ' — پرونده ' . (string)($r['file_number'] ?? '');
    }
    if ($n > count($shown)) {
        $lines[] = '… و ' . ($n - count($shown)) . ' نفر دیگر.';
    }
    return implode("\n", $lines);
}

function supervisor_ai_live_now(PDO $pdo) {
    if (!function_exists('class_is_in_session')) {
        require_once __DIR__ . '/class-live.php';
    }
    $classes = supervisor_ai_all_teaching_classes($pdo, supervisor_ai_today_day_code() === 'friday' ? '' : supervisor_ai_today_day_code());
    if (supervisor_ai_today_day_code() === 'friday') {
        return ['ok' => true, 'friday' => true, 'classes' => []];
    }
    $live = [];
    foreach ($classes as $c) {
        if (class_is_in_session($c)) {
            $live[] = $c;
        }
    }
    return ['ok' => true, 'friday' => false, 'classes' => $live, 'today_count' => count($classes)];
}

function supervisor_ai_format_live_now(array $data) {
    if (!empty($data['friday'])) {
        return 'امروز جمعه است و کلاسی برگزار نمی‌شود.';
    }
    $live = $data['classes'] ?? [];
    $pack = supervisor_ai_date_pack(supervisor_ai_today(), 'امروز');
    $head = 'کلاس‌های در حال برگزاری — ' . (string)$pack['jalali'] . ' — ' . (string)$pack['weekday'];
    if (!$live) {
        return $head . "\nالان هیچ کلاسی داخل بازهٔ ساعتش نیست. کلاس‌های امروز " . (int)($data['today_count'] ?? 0) . ' تا است.';
    }
    $lines = [$head . ' — ' . count($live) . ' کلاس:'];
    $i = 1;
    foreach ($live as $c) {
        $lines[] = $i . '. ' . (string)$c['title'] . ' — معلم: ' . (string)($c['teacher_name'] ?? '')
            . (trim((string)($c['schedule'] ?? '')) !== '' ? ' (ساعت ' . $c['schedule'] . ')' : '');
        $i++;
    }
    return implode("\n", $lines);
}

function supervisor_ai_teachers_list(PDO $pdo) {
    if (!function_exists('term_sql_teaching')) {
        require_once __DIR__ . '/terms.php';
    }
    $st = $pdo->query("
        SELECT u.full_name,
               SUM(CASE WHEN " . term_sql_teaching('t') . " THEN 1 ELSE 0 END) AS current_n,
               COUNT(c.id) AS total_n
        FROM users u
        LEFT JOIN classes c ON c.teacher_id = u.id AND c.is_deleted = 0 AND IFNULL(c.is_archived, 0) = 0
        LEFT JOIN terms t ON c.term_id = t.id
        WHERE u.is_deleted = 0 AND (u.role IN ('teacher', 'supervisor'))
        GROUP BY u.id, u.full_name
        HAVING total_n > 0
        ORDER BY current_n DESC, u.full_name
    ");
    return ['ok' => true, 'teachers' => $st ? $st->fetchAll(PDO::FETCH_ASSOC) : []];
}

function supervisor_ai_format_teachers_list(array $data) {
    $rows = $data['teachers'] ?? [];
    $current = 0;
    foreach ($rows as $r) {
        if ((int)$r['current_n'] > 0) {
            $current++;
        }
    }
    $lines = ['اساتیدی که کلاس دارند: ' . count($rows) . ' نفر (ترم جاری: ' . $current . ').'];
    foreach ($rows as $r) {
        if ((int)$r['current_n'] < 1) {
            continue;
        }
        $lines[] = '• ' . $r['full_name'] . ' — ترم جاری ' . (int)$r['current_n'] . ' کلاس';
    }
    return implode("\n", supervisor_ai_limit_lines($lines, 28));
}

function supervisor_ai_users_counts(PDO $pdo) {
    $st = $pdo->query("
        SELECT role, COUNT(*) AS n
        FROM users
        WHERE is_deleted = 0
        GROUP BY role
    ");
    $by = [];
    foreach (($st ? $st->fetchAll(PDO::FETCH_ASSOC) : []) as $row) {
        $by[(string)$row['role']] = (int)$row['n'];
    }
    return ['ok' => true, 'counts' => $by];
}

function supervisor_ai_format_users_counts(array $data) {
    $c = $data['counts'] ?? [];
    return "تعداد کاربران فعال:\n"
        . '• دانش‌آموز: ' . (int)($c['student'] ?? 0) . "\n"
        . '• معلم: ' . (int)($c['teacher'] ?? 0) . "\n"
        . '• سوپروایزر: ' . (int)($c['supervisor'] ?? 0);
}

function supervisor_ai_format_terms(array $data) {
    $lines = ['ترم‌ها:'];
    foreach (($data['terms'] ?? []) as $t) {
        $tag = !empty($t['teaching']) ? 'در حال تدریس' : (!empty($t['archived']) ? 'بایگانی' : 'دیگر');
        $lines[] = '• ' . (string)($t['title'] ?? '') . ' — ' . $tag
            . (trim((string)($t['starts_on'] ?? '')) !== '' ? ' — شروع ' . $t['starts_on'] : '');
    }
    return implode("\n", $lines);
}

function supervisor_ai_format_explain_panel($text) {
    $map = supervisor_ai_panel_map();
    $t = supervisor_ai_norm($text);
    $hints = [
        'ai assistance' => 'ai-assistance',
        'ai-assistance' => 'ai-assistance',
        'assistance' => 'ai-assistance',
        'دستیار' => 'ai-assistance',
        'رول' => 'attendance',
        'غایب' => 'attendance',
        'سشن' => 'session-plan',
        'تکلیف' => 'homework',
        'کارنامه' => 'report-card',
        'استادی' => 'study-plan',
        'study' => 'study-plan',
        'ارور' => 'error-gravity',
        'گراویتی' => 'error-gravity',
        'فرم مشاهده' => 'observation-forms',
        'observation form' => 'observation-forms',
        'ارزیابی' => 'eas',
        'آبزرو' => 'eas',
        'observe' => 'eas',
        'مصاحبه' => 'eas',
        'مایند' => 'mind-lingo',
        'mind' => 'mind-lingo',
        'دانش' => 'students-info',
        'شماره تماس' => 'students-info',
        'تلفن' => 'students-info',
        'معلم' => 'teachers-info',
        'استاد' => 'teachers-info',
        'ترم' => 'terms',
        'کلاس‌بندی' => 'placement',
        'eas' => 'eas',
        'داشبورد' => 'dashboard',
        'کاربر' => 'users',
    ];
    foreach ($hints as $needle => $id) {
        if (function_exists('mb_stripos') ? mb_stripos($t, $needle) !== false : stripos($t, $needle) !== false) {
            foreach ($map['pages'] as $p) {
                if ($p['id'] === $id) {
                    return 'صفحهٔ «' . $p['title'] . '»: ' . $p['about'];
                }
            }
        }
    }
    $lines = ['صفحات پنل سوپروایزر:'];
    foreach ($map['pages'] as $p) {
        $lines[] = '• ' . $p['title'] . ' — ' . $p['about'];
    }
    return implode("\n", $lines);
}

function supervisor_ai_pick_class_link_match(array $matches, $want_type = '') {
    if (count($matches) <= 1) {
        return $matches;
    }
    $typed = [];
    if ($want_type !== '') {
        foreach ($matches as $c) {
            if (strtolower(trim((string)($c['class_type'] ?? ''))) === $want_type) {
                $typed[] = $c;
            }
        }
        if (count($typed) === 1) {
            return $typed;
        }
        if ($typed) {
            $matches = $typed;
        }
    }
    $with_link = [];
    foreach ($matches as $c) {
        if (trim((string)($c['online_link'] ?? '')) !== '') {
            $with_link[] = $c;
        }
    }
    if (count($with_link) === 1) {
        return $with_link;
    }
    $online = [];
    foreach ($matches as $c) {
        if (strtolower(trim((string)($c['class_type'] ?? ''))) === 'online') {
            $online[] = $c;
        }
    }
    if (count($online) === 1) {
        return $online;
    }
    return $matches;
}

function supervisor_ai_class_link(PDO $pdo, $class_query, $user_text = '') {
    if ($class_query === '') {
        return ['ok' => false, 'error' => 'برای لینک کلاس عنوان را بگو؛ مثلاً لینک P5 آنلاین.'];
    }
    $type = supervisor_ai_parse_class_type($user_text);
    $day = supervisor_ai_parse_day_filter($user_text);
    $clock = function_exists('supervisor_ai_parse_clock') ? supervisor_ai_parse_clock($user_text) : '';
    $matches = supervisor_ai_match_classes($pdo, $class_query, $day, $clock, $type);
    if (!$matches) {
        return ['ok' => false, 'error' => 'کلاسی با این عنوان در ترم جاری پیدا نشد.'];
    }
    $matches = supervisor_ai_pick_class_link_match($matches, $type);
    $exact = supervisor_ai_norm($class_query) === supervisor_ai_norm($matches[0]['title'] ?? '');
    if (count($matches) > 1 && !$exact) {
        return ['ok' => true, 'ambiguous' => true, 'matches' => $matches, 'note' => 'چند کلاس مشابه است. عنوان دقیق یا آنلاین/آفلاین را بگو.'];
    }
    if (count($matches) > 1 && $exact) {
        $matches = supervisor_ai_pick_class_link_match($matches, $type !== '' ? $type : 'online');
    }
    if (count($matches) > 1) {
        $list = [];
        foreach ($matches as $row) {
            $list[] = [
                'title' => (string)($row['title'] ?? ''),
                'teacher' => (string)($row['teacher_name'] ?? ''),
                'type' => (string)($row['class_type'] ?? ''),
                'days' => supervisor_ai_schedule_label($row['schedule_type'] ?? ''),
                'schedule' => (string)($row['schedule'] ?? ''),
            ];
        }
        return ['ok' => true, 'ambiguous' => true, 'matches' => $list, 'note' => 'چند کلاس با این عنوان است. آنلاین یا ساعت را بگو.'];
    }
    $row = $matches[0];
    return [
        'ok' => true,
        'title' => (string)($row['title'] ?? ''),
        'teacher' => (string)($row['teacher_name'] ?? ''),
        'type' => (string)($row['class_type'] ?? ''),
        'schedule' => (string)($row['schedule'] ?? ''),
        'days' => supervisor_ai_schedule_label($row['schedule_type'] ?? ''),
        'online_link' => trim((string)($row['online_link'] ?? '')),
        'wanted_type' => $type,
    ];
}

function supervisor_ai_format_class_link(array $data) {
    if (!empty($data['error'])) {
        return (string)$data['error'];
    }
    if (!empty($data['ambiguous'])) {
        $note = trim((string)($data['note'] ?? ''));
        $lines = [$note !== '' ? $note : 'چند کلاس مشابه است:'];
        foreach (($data['matches'] ?? []) as $i => $c) {
            $line = ($i + 1) . '. ' . (string)($c['title'] ?? '') . ' — ' . (string)($c['teacher'] ?? '');
            if (trim((string)($c['type'] ?? '')) !== '') {
                $line .= ' — ' . supervisor_ai_class_type_label($c['type']);
            }
            if (trim((string)($c['days'] ?? '')) !== '') {
                $line .= ' — ' . $c['days'];
            }
            if (trim((string)($c['schedule'] ?? '')) !== '') {
                $line .= ' (ساعت ' . $c['schedule'] . ')';
            }
            $lines[] = $line;
        }
        return implode("\n", $lines);
    }
    $type = (string)($data['type'] ?? '');
    $link = trim((string)($data['online_link'] ?? ''));
    $wanted = (string)($data['wanted_type'] ?? '');
    $head = (string)($data['title'] ?? '')
        . ' — ' . supervisor_ai_class_type_label($type)
        . ' — معلم: ' . (string)($data['teacher'] ?? '')
        . ' — ' . (string)($data['days'] ?? '')
        . (trim((string)($data['schedule'] ?? '')) !== '' ? ' (ساعت ' . $data['schedule'] . ')' : '');
    if ($link !== '') {
        return $head . "\nلینک الوکام:\n" . $link;
    }
    if ($wanted === 'online' && $type !== 'online' && $type !== 'private') {
        return $head . "\nاین کلاس آنلاین نیست و لینک الوکام ندارد.";
    }
    return $head . "\nبرای این کلاس لینک الوکام در پنل ثبت نشده است.";
}

function supervisor_ai_try_coverage(PDO $pdo, $topic, $date, $class_query, $person, $user_text) {
    $teacher = ($person !== '') ? supervisor_ai_teacher_name_from_person($pdo, $person) : '';
    switch ($topic) {
        case 'missing_homework':
            return supervisor_ai_format_missing_homework(supervisor_ai_missing_homework($pdo, $date !== '' ? $date : 'امروز'), $teacher);
        case 'homework_status':
            return supervisor_ai_format_homework_status(supervisor_ai_homework_status($pdo, $date !== '' ? $date : 'امروز', $class_query));
        case 'eas_interview':
            if ($class_query !== '') {
                return supervisor_ai_format_eas_class(supervisor_ai_eas_class($pdo, $class_query));
            }
            return supervisor_ai_format_eas_gaps(supervisor_ai_eas_gaps($pdo, 'interview'), 'مصاحبه');
        case 'eas_observation':
            if ($class_query !== '') {
                return supervisor_ai_format_eas_class(supervisor_ai_eas_class($pdo, $class_query));
            }
            return supervisor_ai_format_eas_gaps(supervisor_ai_eas_gaps($pdo, 'observation'), 'ارزیابی (آبزرو)');
        case 'eas_poll':
            if ($class_query !== '') {
                return supervisor_ai_format_eas_class(supervisor_ai_eas_class($pdo, $class_query));
            }
            return supervisor_ai_format_eas_gaps(supervisor_ai_eas_gaps($pdo, 'poll'), 'نظرسنجی');
        case 'eas_overview':
            if ($class_query !== '') {
                return supervisor_ai_format_eas_class(supervisor_ai_eas_class($pdo, $class_query));
            }
            $a = supervisor_ai_eas_gaps($pdo, 'interview');
            $b = supervisor_ai_eas_gaps($pdo, 'observation');
            $c = supervisor_ai_eas_gaps($pdo, 'poll');
            return 'EAS ترم جاری:' . "\n"
                . 'مصاحبه نشده: ' . (int)($a['count'] ?? 0) . "\n"
                . 'ارزیابی (آبزرو) نشده: ' . (int)($b['count'] ?? 0) . "\n"
                . 'نظرسنجی نشده: ' . (int)($c['count'] ?? 0) . "\n"
                . 'برای جزئیات یک کلاس، عنوانش را بگو.';
        case 'missing_error_gravity':
            return supervisor_ai_format_error_gravity(supervisor_ai_error_gravity($pdo, '', true));
        case 'error_gravity':
            return supervisor_ai_format_error_gravity(supervisor_ai_error_gravity($pdo, $class_query, $class_query === ''));
        case 'missing_observation_form':
            return supervisor_ai_format_observation_forms(supervisor_ai_observation_forms($pdo, '', true));
        case 'observation_form':
            return supervisor_ai_format_observation_forms(supervisor_ai_observation_forms($pdo, $class_query, $class_query === ''));
        case 'missing_mind_lingo':
            return supervisor_ai_format_mind_lingo(supervisor_ai_mind_lingo($pdo, '', true));
        case 'mind_lingo':
            return supervisor_ai_format_mind_lingo(supervisor_ai_mind_lingo($pdo, $class_query, $class_query === ''));
        case 'report_card':
            $prev = function_exists('supervisor_ai_asks_previous_term') && supervisor_ai_asks_previous_term($user_text);
            return supervisor_ai_format_report_card(supervisor_ai_report_card($pdo, $class_query, $prev));
        case 'class_link':
            return supervisor_ai_format_class_link(supervisor_ai_class_link($pdo, $class_query, $user_text));
        case 'study_plan':
            $when = supervisor_ai_date_from_text($user_text);
            return supervisor_ai_format_study_plan(supervisor_ai_study_plan($pdo, $user_text, $when));
        case 'live_now':
            return supervisor_ai_format_live_now(supervisor_ai_live_now($pdo));
        case 'teachers_list':
            return supervisor_ai_format_teachers_list(supervisor_ai_teachers_list($pdo));
        case 'users_counts':
            return supervisor_ai_format_users_counts(supervisor_ai_users_counts($pdo));
        case 'terms':
            return supervisor_ai_format_terms(supervisor_ai_list_terms($pdo));
        case 'explain_panel':
            return supervisor_ai_format_explain_panel($user_text);
        case 'missing_phone':
            return supervisor_ai_format_students_without_phone(supervisor_ai_students_without_phone($pdo));
        case 'session_plan':
        case 'class_session':
        case 'student_info':
        case 'placement':
            return null;
        default:
            return null;
    }
}
