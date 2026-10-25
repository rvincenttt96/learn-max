<?php
/**
 * Scenario matrix for the supervisor assistant.
 *
 * Every panel question is: intent × scope × date × follow-up.
 * PHP answers the matrix; the LLM is only a last resort.
 *
 * Intents: absences, missing_rollcall, missing_session_plan, session_plan,
 *          missing_homework, homework_status, class_session, error_gravity,
 *          observation_form, eas_*, mind_lingo, report_card, study_plan,
 *          live_now, teachers_list, users_counts, terms, explain_panel,
 *          placement, student_info, person, classes.
 * Scopes:  school | class | teacher | student
 * Dates:   امروز / دیروز / پریروز / inherited
 * Follow:  date-only, «اونایی که…», topic inherit, class inherit, person inherit
 */

function supervisor_ai_is_greeting($text) {
    $t = supervisor_ai_norm($text);
    return (bool)preg_match('/^(سلام|درود|هی|hello|hi|hey)([\s!.،,؟?]*| علیکم| علیكم)?$/iu', $t);
}

function supervisor_ai_followup_menu($when = '') {
    $when = supervisor_ai_norm($when);
    $label = $when !== '' ? $when : 'این تاریخ';
    return 'برای «' . $label . '» بگو کدام را می‌خواهی: غایبین، رول‌کال نزده، سشن‌پلن ننوشته، یا تکلیف ثبت‌نشده.';
}

function supervisor_ai_those_menu() {
    return 'بگو منظورت کدام است: غایبین، رول‌کال نزده، سشن‌پلن ننوشته، یا تکلیف ثبت‌نشده.';
}

function supervisor_ai_is_school_list_ask($text) {
    return (bool)preg_match('/کدوم\s*کلاس|کدام\s*کلاس|چه\s*کلاس|کلاس‌هایی\s+که|کلاسهایی\s+که|کلاس ها\s+که/u', $text);
}

function supervisor_ai_is_school_wide_topic($topic) {
    return in_array($topic, [
        'absences',
        'missing_rollcall',
        'missing_session_plan',
        'missing_homework',
        'missing_error_gravity',
        'missing_observation_form',
        'missing_mind_lingo',
        'live_now',
        'teachers_list',
        'users_counts',
        'terms',
        'explain_panel',
        'study_plan',
        'eas_overview',
        'eas_interview',
        'eas_observation',
        'eas_poll',
        'placement',
        'missing_phone',
    ], true);
}

function supervisor_ai_inherit_person_for_topic($topic) {
    return !in_array($topic, [
        'live_now',
        'teachers_list',
        'users_counts',
        'terms',
        'explain_panel',
        'placement',
        'study_plan',
        'missing_phone',
    ], true);
}

function supervisor_ai_person_bundle_from_people(array $people) {
    $teachers = [];
    $students = [];
    foreach ($people as $p) {
        if (($p['role'] ?? '') === 'teacher') {
            $teachers[] = $p;
        } else {
            $students[] = $p;
        }
    }
    return [
        'teachers' => $teachers,
        'students' => $students,
        'raw' => ['ok' => true, 'people' => $people],
    ];
}

function supervisor_ai_person_bundle(PDO $pdo, $person) {
    $empty = ['teachers' => [], 'students' => [], 'raw' => ['ok' => true, 'people' => []]];
    if ($person === '') {
        return $empty;
    }
    $found = supervisor_ai_lookup_person($pdo, $person);
    $teachers = [];
    $students = [];
    foreach (($found['people'] ?? []) as $p) {
        if (($p['role'] ?? '') === 'teacher') {
            $teachers[] = $p;
        } else {
            $students[] = $p;
        }
    }
    return ['teachers' => $teachers, 'students' => $students, 'raw' => $found];
}

function supervisor_ai_single_teacher_name(array $bundle) {
    if (count($bundle['teachers'] ?? []) === 1) {
        return (string)($bundle['teachers'][0]['full_name'] ?? '');
    }
    return '';
}

function supervisor_ai_single_student(array $bundle) {
    if (count($bundle['teachers'] ?? []) === 0 && count($bundle['students'] ?? []) === 1) {
        return $bundle['students'][0];
    }
    return null;
}

function supervisor_ai_format_class_session(array $data) {
    if (!empty($data['error'])) {
        return (string)$data['error'];
    }
    if (!empty($data['ambiguous'])) {
        return supervisor_ai_format_class_overview($data);
    }
    $when = $data['date'] ?? [];
    $head = supervisor_ai_norm(trim(
        (string)($data['class_title'] ?? '') . ' — معلم: ' . (string)($data['teacher'] ?? '')
        . ' — ' . (string)($when['relative'] ?? '') . ' ' . (string)($when['jalali'] ?? '')
        . ' ' . (string)($when['weekday'] ?? '')
    ));
    if (empty($data['rollcall_saved'])) {
        return $head . "\nبرای این تاریخ رول‌کال ذخیره نشده است. این با «غایبی نیست» فرق دارد.";
    }
    $lines = [
        $head,
        'حاضر ' . (int)($data['present_count'] ?? 0)
            . ' از ' . (int)($data['enrolled_count'] ?? 0)
            . ' — غایب ' . (int)($data['absent_count'] ?? 0),
    ];
    $abs = $data['absentees'] ?? [];
    if ($abs) {
        $lines[] = 'غایبین:';
        foreach ($abs as $p) {
            $line = '• ' . trim((string)($p['full_name'] ?? '') . ' ' . (string)($p['file_number'] ?? ''));
            if (!empty($p['reason'])) {
                $line .= ' (' . $p['reason'] . ')';
            }
            $lines[] = $line;
        }
        if (!empty($data['absentees_truncated'])) {
            $lines[] = '… فهرست کوتاه شده است.';
        }
    } else {
        $lines[] = 'غایبی ثبت نشده است.';
    }
    return implode("\n", $lines);
}

function supervisor_ai_format_student_overview(array $data) {
    if (!empty($data['error'])) {
        return (string)$data['error'];
    }
    if (!empty($data['ambiguous'])) {
        $lines = ['چند دانش‌آموز هم‌نام است. شماره پرونده را بگو:'];
        foreach (($data['matches'] ?? []) as $m) {
            $lines[] = '• ' . (string)($m['full_name'] ?? '') . ' — پرونده ' . (string)($m['file_number'] ?? '');
        }
        return implode("\n", $lines);
    }
    $lines = [
        (string)($data['full_name'] ?? '') . ' دانش‌آموز است (پرونده ' . (string)($data['file_number'] ?? '') . ').',
    ];
    $classes = $data['classes'] ?? [];
    if (!$classes) {
        $lines[] = 'در کلاس فعالی ثبت نشده است.';
        return implode("\n", $lines);
    }
    $lines[] = 'کلاس‌ها:';
    $i = 1;
    foreach ($classes as $c) {
        $line = $i . '. ' . (string)($c['title'] ?? '')
            . ' — معلم: ' . (string)($c['teacher'] ?? '')
            . (trim((string)($c['days'] ?? '')) !== '' ? ' — ' . $c['days'] : '');
        if (!empty($c['current_term'])) {
            $line .= ' — ترم جاری';
        }
        $line .= ' — حاضر ' . (int)($c['present_count'] ?? 0) . ' / غایب ' . (int)($c['absent_count'] ?? 0);
        $dates = $c['recent_absent_dates'] ?? [];
        if ($dates) {
            $line .= ' (آخرین غیبت: ' . implode('، ', array_slice($dates, 0, 3)) . ')';
        }
        $lines[] = $line;
        $i++;
    }
    return implode("\n", $lines);
}

function supervisor_ai_session_plan_class(PDO $pdo, $class_query, $date = '', $user_text = '') {
    $src = supervisor_ai_norm(trim($user_text . ' ' . $class_query));
    $day = supervisor_ai_parse_day_filter($src);
    $clock = supervisor_ai_parse_clock($src);
    $session_n = supervisor_ai_asks_session_n($src);
    $matches = supervisor_ai_match_classes($pdo, $class_query, $day, $clock);
    if (!$matches) {
        $all = supervisor_ai_match_classes($pdo, $class_query);
        if ($all) {
            $list = [];
            foreach ($all as $row) {
                $list[] = supervisor_ai_class_match_row($row);
            }
            $note = 'با این روز/ساعت کلاسی پیدا نشد. کلاس‌های هم‌عنوان:';
            return ['ok' => true, 'ambiguous' => true, 'matches' => $list, 'note' => $note];
        }
        return ['ok' => false, 'error' => 'کلاسی با این عنوان در ترم جاری پیدا نشد.'];
    }
    if (count($matches) > 1) {
        $list = [];
        foreach ($matches as $row) {
            $list[] = supervisor_ai_class_match_row($row);
        }
        return [
            'ok' => true,
            'ambiguous' => true,
            'matches' => $list,
            'note' => 'چند کلاس با این عنوان است. روز یا ساعت را دقیق‌تر بگو.',
        ];
    }
    $picked = $matches[0];
    $id = (int)$picked['id'];
    $title = (string)$picked['title'];
    $teacher = (string)($picked['teacher_name'] ?? '');
    $params = [$id];
    $sql = "
        SELECT session_date, content
        FROM session_plans
        WHERE class_id = ?
    ";
    $when = null;
    if ($date !== '') {
        $when = supervisor_ai_resolve_date($date);
        if (!empty($when['error'])) {
            return ['ok' => false, 'error' => $when['error']];
        }
        $sql .= " AND DATE(session_date) = ?";
        $params[] = $when['gregorian'];
    }
    $sql .= " ORDER BY DATE(session_date) ASC, session_date ASC LIMIT 40";
    try {
        $st = $pdo->prepare($sql);
        $st->execute($params);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return ['ok' => false, 'error' => 'جدول سشن‌پلن در دسترس نیست.'];
    }
    $label = '';
    $fa_n = [1 => 'اول', 2 => 'دوم', 3 => 'سوم', 4 => 'چهارم', 5 => 'پنجم', 6 => 'ششم'];
    if ($session_n === -1) {
        $label = 'آخرین جلسه';
        $rows = $rows ? [end($rows)] : [];
    } elseif ($session_n > 0) {
        $label = 'جلسه ' . ($fa_n[$session_n] ?? (string)$session_n);
        if (count($rows) < $session_n) {
            return [
                'ok' => true,
                'title' => $title,
                'teacher' => $teacher,
                'days' => supervisor_ai_schedule_label($picked['schedule_type'] ?? ''),
                'schedule' => (string)($picked['schedule'] ?? ''),
                'session_n' => $session_n,
                'session_label' => $label,
                'date' => $when,
                'rows' => [],
                'written_count' => count($rows),
            ];
        }
        $rows = [$rows[$session_n - 1]];
    } elseif ($date === '') {
        $rows = array_slice($rows, -4);
        $rows = array_reverse($rows);
    }
    return [
        'ok' => true,
        'title' => $title,
        'teacher' => $teacher,
        'days' => supervisor_ai_schedule_label($picked['schedule_type'] ?? ''),
        'schedule' => (string)($picked['schedule'] ?? ''),
        'session_n' => $session_n,
        'session_label' => $label,
        'date' => $when,
        'rows' => $rows,
        'written_count' => null,
    ];
}

function supervisor_ai_format_session_plan_class(array $data) {
    if (!empty($data['error'])) {
        return (string)$data['error'];
    }
    if (!empty($data['ambiguous'])) {
        return supervisor_ai_format_class_overview($data);
    }
    $head = (string)($data['title'] ?? '') . ' — معلم: ' . (string)($data['teacher'] ?? '');
    if (trim((string)($data['days'] ?? '')) !== '') {
        $head .= ' — ' . $data['days'];
    }
    if (trim((string)($data['schedule'] ?? '')) !== '') {
        $head .= ' (ساعت ' . $data['schedule'] . ')';
    }
    $rows = $data['rows'] ?? [];
    $label = trim((string)($data['session_label'] ?? ''));
    if (!$rows) {
        $written = $data['written_count'];
        if ($label !== '' && $written !== null) {
            return $head . "\n" . $label . ' نوشته نشده است. تا الان ' . (int)$written . ' سشن‌پلن برای این کلاس ثبت شده.';
        }
        $when = $data['date'] ?? null;
        if (is_array($when) && !empty($when['jalali'])) {
            return $head . "\nبرای " . $when['jalali'] . ' سشن‌پلنی نوشته نشده است.';
        }
        return $head . "\nهنوز سشن‌پلنی برای این کلاس ثبت نشده است.";
    }
    $lines = [$head];
    $lines[] = $label !== '' ? ($label . ':') : 'سشن‌پلن:';
    foreach ($rows as $r) {
        $content = trim((string)($r['content'] ?? ''));
        if (function_exists('mb_substr') && mb_strlen($content, 'UTF-8') > 420) {
            $content = mb_substr($content, 0, 420, 'UTF-8') . '…';
        }
        $lines[] = '• ' . supervisor_ai_pretty_date((string)$r['session_date']) . ($content !== '' ? "\n" . $content : ' — خالی');
    }
    return implode("\n", $lines);
}

function supervisor_ai_student_homework(PDO $pdo, $file_or_name, $date = '') {
    $found = supervisor_ai_find_student($pdo, $file_or_name);
    if (!$found) {
        return ['ok' => false, 'error' => 'دانش‌آموز پیدا نشد.'];
    }
    if (isset($found[0]) && is_array($found[0])) {
        $matches = [];
        foreach ($found as $row) {
            $matches[] = [
                'file_number' => (string)$row['file_number'],
                'full_name' => (string)$row['full_name'],
            ];
        }
        return ['ok' => true, 'ambiguous' => true, 'matches' => $matches];
    }
    $when = supervisor_ai_resolve_date($date !== '' ? $date : 'امروز');
    if (!empty($when['error'])) {
        return ['ok' => false, 'error' => $when['error']];
    }
    $uid = (int)$found['id'];
    try {
        $st = $pdo->prepare("
            SELECT h.homework_date, h.status, c.title
            FROM homework_status h
            JOIN classes c ON c.id = h.class_id
            WHERE h.user_id = ?
            ORDER BY h.homework_date DESC
            LIMIT 12
        ");
        $st->execute([$uid]);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        $day = $pdo->prepare("
            SELECT h.status, c.title
            FROM homework_status h
            JOIN classes c ON c.id = h.class_id
            WHERE h.user_id = ? AND h.homework_date = ?
        ");
        $day->execute([$uid, $when['gregorian']]);
        $today_rows = $day->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return ['ok' => false, 'error' => 'جدول تکلیف در دسترس نیست.'];
    }
    return [
        'ok' => true,
        'full_name' => (string)$found['full_name'],
        'file_number' => (string)$found['file_number'],
        'date' => $when,
        'day_rows' => $today_rows,
        'recent' => $rows,
    ];
}

function supervisor_ai_format_student_homework(array $data) {
    if (!empty($data['error'])) {
        return (string)$data['error'];
    }
    if (!empty($data['ambiguous'])) {
        return supervisor_ai_format_student_overview($data);
    }
    $head = (string)($data['full_name'] ?? '') . ' — پرونده ' . (string)($data['file_number'] ?? '');
    $when = $data['date'] ?? [];
    $label = supervisor_ai_norm(trim((string)($when['relative'] ?? '') . ' ' . (string)($when['jalali'] ?? '')));
    $map = [
        'complete' => 'انجام‌شده',
        'incomplete' => 'ناقص',
        'not_submitted' => 'نسپرده',
    ];
    $day = $data['day_rows'] ?? [];
    if ($day) {
        $bits = [];
        foreach ($day as $r) {
            $st = (string)($r['status'] ?? '');
            $bits[] = (string)$r['title'] . ': ' . ($map[$st] ?? $st);
        }
        return $head . "\nتکلیف " . $label . ":\n• " . implode("\n• ", $bits);
    }
    $recent = $data['recent'] ?? [];
    if (!$recent) {
        return $head . "\nبرای " . $label . ' تکلیفی ثبت نشده است.';
    }
    $lines = [$head, 'برای ' . $label . ' ردیفی نیست. آخرین تکلیف‌های ثبت‌شده:'];
    foreach (array_slice($recent, 0, 8) as $r) {
        $st = (string)($r['status'] ?? '');
        $lines[] = '• ' . supervisor_ai_pretty_date((string)$r['homework_date'])
            . ' — ' . (string)$r['title'] . ': ' . ($map[$st] ?? $st);
    }
    return implode("\n", $lines);
}

function supervisor_ai_student_info(PDO $pdo, $person) {
    $found = supervisor_ai_find_student($pdo, $person);
    if (!$found) {
        $bundle = supervisor_ai_person_bundle($pdo, $person);
        if ($bundle['teachers']) {
            return $bundle['raw'];
        }
        return ['ok' => false, 'error' => 'دانش‌آموزی با این مشخصات پیدا نشد.'];
    }
    if (isset($found[0]) && is_array($found[0])) {
        return supervisor_ai_student_overview($pdo, $person);
    }
    $overview = supervisor_ai_student_overview($pdo, (string)$found['file_number']);
    $st = $pdo->prepare("SELECT phone FROM users WHERE id = ? LIMIT 1");
    $st->execute([(int)$found['id']]);
    $overview['phone'] = (string)($st->fetchColumn() ?: '');
    $overview['mode'] = 'info';
    return $overview;
}

function supervisor_ai_format_student_info(array $data) {
    if (!empty($data['people'])) {
        return supervisor_ai_format_person($data);
    }
    $base = supervisor_ai_format_student_overview($data);
    $phone = trim((string)($data['phone'] ?? ''));
    if ($phone !== '') {
        return $base . "\nتلفن: " . $phone;
    }
    if (($data['mode'] ?? '') === 'info') {
        return $base . "\nتلفن در پرونده خالی است.";
    }
    return $base;
}

function supervisor_ai_placement_summary(PDO $pdo) {
    if (!function_exists('term_open_rows')) {
        require_once __DIR__ . '/terms.php';
    }
    if (!function_exists('placement_load_term_classes')) {
        require_once __DIR__ . '/placement.php';
    }
    $open = term_open_rows($pdo);
    $teaching = term_pick_teaching($open);
    $upcoming = term_pick_upcoming($open);
    $term = $upcoming ?: $teaching;
    if (!$term) {
        return ['ok' => true, 'empty' => true];
    }
    $classes = placement_load_term_classes($pdo, (int)$term['id']);
    $dated = 0;
    $ticked = 0;
    $no_teacher = 0;
    $no_students = 0;
    foreach ($classes as $c) {
        if (trim((string)($c['interview_date'] ?? '')) !== '') {
            $dated++;
        }
        if ((int)($c['interview_status'] ?? 0) === 1) {
            $ticked++;
        }
        if ((int)($c['teacher_id'] ?? 0) < 1) {
            $no_teacher++;
        }
        if (empty($c['students'])) {
            $no_students++;
        }
    }
    return [
        'ok' => true,
        'term' => (string)($term['title'] ?? ''),
        'upcoming' => (bool)$upcoming,
        'teaching' => (string)($teaching['title'] ?? ''),
        'count' => count($classes),
        'interview_dated' => $dated,
        'interview_ticked' => $ticked,
        'no_teacher' => $no_teacher,
        'no_students' => $no_students,
    ];
}

function supervisor_ai_format_placement(array $data) {
    if (!empty($data['empty'])) {
        return 'ترم بازی برای کلاس‌بندی پیدا نشد.';
    }
    $lines = [
        'کلاس‌بندی — ترم «' . (string)($data['term'] ?? '') . '»'
            . (!empty($data['upcoming']) ? ' (آینده)' : ' (در حال تدریس)'),
        (int)($data['count'] ?? 0) . ' کلاس در این ترم.',
        'مصاحبه تاریخ‌دار: ' . (int)($data['interview_dated'] ?? 0)
            . ' — تیک‌خورده: ' . (int)($data['interview_ticked'] ?? 0),
        'بدون معلم: ' . (int)($data['no_teacher'] ?? 0)
            . ' — بدون دانش‌آموز: ' . (int)($data['no_students'] ?? 0),
    ];
    $teach = trim((string)($data['teaching'] ?? ''));
    if ($teach !== '' && $teach !== (string)($data['term'] ?? '')) {
        $lines[] = 'ترم در حال تدریس جداگانه: ' . $teach;
    }
    $lines[] = 'صفحه: Terms & Classes / کلاس‌بندی. جزئیات یک کلاس را با عنوانش بپرس.';
    return implode("\n", $lines);
}

function supervisor_ai_teacher_feature(PDO $pdo, $teacher_name, $feature, $date = '') {
    $bundle = supervisor_ai_person_bundle($pdo, $teacher_name);
    if (count($bundle['teachers']) > 1) {
        return ['ok' => true, 'disambiguate' => true, 'raw' => $bundle['raw']];
    }
    if (!$bundle['teachers']) {
        return ['ok' => false, 'error' => 'معلمی با این نام پیدا نشد.'];
    }
    $teacher = $bundle['teachers'][0];
    $current = [];
    foreach (($teacher['classes'] ?? []) as $c) {
        if (!empty($c['current_term'])) {
            $current[] = $c;
        }
    }
    $gap = null;
    $gap_missing = 'نزده';
    $gap_wait = 'هنوز شروع نشده';
    $gap_done = 'زده';
    if ($feature === 'missing_rollcall' || $feature === 'class_session') {
        $gap = supervisor_ai_missing_rollcall($pdo, $date !== '' ? $date : 'امروز');
        $gap_missing = 'رول‌کال نزده';
        $gap_wait = 'هنوز شروع نشده';
        $gap_done = 'زده';
    } elseif ($feature === 'missing_session_plan' || $feature === 'session_plan') {
        $gap = supervisor_ai_missing_session_plans($pdo, $date !== '' ? $date : 'امروز');
        $gap_missing = 'ننوشته';
        $gap_wait = 'هنوز شروع نشده';
        $gap_done = 'نوشته';
    } elseif ($feature === 'missing_homework' || $feature === 'homework_status') {
        $gap = supervisor_ai_missing_homework($pdo, $date !== '' ? $date : 'امروز');
        $gap_missing = 'ثبت نشده';
        $gap_wait = 'هنوز شروع نشده';
        $gap_done = 'ثبت شده';
    }
    $items = [];
    foreach ($current as $c) {
        $title = (string)($c['title'] ?? '');
        $status = '';
        if ($feature === 'error_gravity') {
            $eg = supervisor_ai_error_gravity($pdo, $title, false);
            $content = trim((string)(($eg['row']['content'] ?? '')));
            $status = $content === '' ? 'ندارد' : 'دارد';
        } elseif ($feature === 'observation_form') {
            $of = supervisor_ai_observation_forms($pdo, $title, false);
            $any = false;
            foreach (($of['rows'] ?? []) as $r) {
                if (!empty($r['date']) || !empty($r['observer_name']) || !empty($r['strengths'])) {
                    $any = true;
                    break;
                }
            }
            $status = $any ? 'دارد' : 'ندارد';
        } elseif ($feature === 'mind_lingo') {
            $ml = supervisor_ai_mind_lingo($pdo, $title, false);
            $enrolled = count($ml['students'] ?? []);
            $filled = 0;
            foreach (($ml['students'] ?? []) as $s) {
                if ($s['communication_dev'] !== null && $s['communication_dev'] !== '') {
                    $filled++;
                }
            }
            $status = $filled . '/' . $enrolled;
        } elseif ($feature === 'eas' || $feature === 'eas_interview' || $feature === 'eas_observation' || $feature === 'eas_poll') {
            $eas = supervisor_ai_eas_class($pdo, $title);
            $r = $eas['row'] ?? [];
            $obs = (((int)($r['observation_status'] ?? 0) === 1) ? 'شده' : 'نشده');
            $intv = (((int)($r['interview_status'] ?? 0) === 1) ? 'شده' : 'نشده');
            $poll = (((int)($r['poll_status'] ?? 0) === 1) ? 'شده' : 'نشده');
            if ($feature === 'eas_observation') {
                $status = 'ارزیابی (آبزرو) ' . $obs;
            } elseif ($feature === 'eas_interview') {
                $status = 'مصاحبه ' . $intv;
                $plan = function_exists('supervisor_ai_eas_interview_plan') ? supervisor_ai_eas_interview_plan($r) : '';
                if ($plan !== '') {
                    $status .= ' — ' . $plan;
                }
            } elseif ($feature === 'eas_poll') {
                $status = 'نظرسنجی ' . $poll;
            } else {
                $status = 'مصاحبه ' . $intv . '، ارزیابی (آبزرو) ' . $obs . '، نظرسنجی ' . $poll;
            }
        } elseif (is_array($gap)) {
            $norm = supervisor_ai_norm($title);
            $bucket = '';
            foreach (['missing', 'not_started', 'done', 'never_planned'] as $key) {
                foreach (($gap[$key] ?? []) as $row) {
                    if (supervisor_ai_norm($row['title'] ?? '') === $norm) {
                        $bucket = $key;
                        break 2;
                    }
                }
            }
            if ($bucket === 'missing') {
                $status = $gap_missing;
            } elseif ($bucket === 'not_started') {
                $status = $gap_wait;
            } elseif ($bucket === 'done') {
                $status = $gap_done;
            } elseif ($bucket === 'never_planned') {
                $status = 'سابقهٔ سشن‌پلن ندارد';
            } else {
                $status = 'در برنامهٔ این تاریخ نیست';
            }
        } else {
            $status = '—';
        }
        $items[] = [
            'title' => $title,
            'days' => (string)($c['days'] ?? ''),
            'status' => $status,
        ];
    }
    return [
        'ok' => true,
        'teacher' => (string)($teacher['full_name'] ?? ''),
        'feature' => $feature,
        'count' => count($current),
        'items' => $items,
    ];
}

function supervisor_ai_format_teacher_feature(array $data) {
    if (!empty($data['error'])) {
        return (string)$data['error'];
    }
    if (!empty($data['disambiguate'])) {
        return supervisor_ai_format_person($data['raw'] ?? []);
    }
    $labels = [
        'error_gravity' => 'Error Gravity',
        'observation_form' => 'فرم مشاهده',
        'mind_lingo' => 'Mind Lingo',
        'eas' => 'EAS',
        'eas_interview' => 'مصاحبه EAS',
        'eas_observation' => 'ارزیابی (آبزرو)',
        'eas_poll' => 'نظرسنجی EAS',
        'missing_rollcall' => 'رول‌کال',
        'class_session' => 'رول‌کال',
        'missing_session_plan' => 'سشن‌پلن',
        'session_plan' => 'سشن‌پلن',
        'missing_homework' => 'تکلیف',
        'homework_status' => 'تکلیف',
    ];
    $feat = (string)($data['feature'] ?? '');
    $label = $labels[$feat] ?? $feat;
    $n = (int)($data['count'] ?? 0);
    if ($n === 0) {
        return (string)($data['teacher'] ?? '') . ' در ترم جاری کلاسی ندارد.';
    }
    $lines = [(string)($data['teacher'] ?? '') . ' — ' . $label . ' (' . $n . ' کلاس ترم جاری):'];
    $i = 1;
    foreach (($data['items'] ?? []) as $it) {
        $lines[] = $i . '. ' . (string)($it['title'] ?? '')
            . (trim((string)($it['days'] ?? '')) !== '' ? ' — ' . $it['days'] : '')
            . ' — ' . (string)($it['status'] ?? '');
        $i++;
    }
    return implode("\n", $lines);
}

function supervisor_ai_format_gap_with_teacher(array $data, $formatter, $teacher_name) {
    if ($teacher_name === '' || !function_exists('supervisor_ai_filter_teacher')) {
        return $formatter($data);
    }
    $missing = supervisor_ai_filter_teacher($data['missing'] ?? [], $teacher_name);
    $not_started = supervisor_ai_filter_teacher($data['not_started'] ?? [], $teacher_name);
    $done = supervisor_ai_filter_teacher($data['done'] ?? [], $teacher_name);
    $never = supervisor_ai_filter_teacher($data['never_planned'] ?? [], $teacher_name);
    $copy = $data;
    $copy['missing'] = $missing;
    $copy['not_started'] = $not_started;
    $copy['done'] = $done;
    $copy['never_planned'] = $never;
    $copy['missing_count'] = count($missing);
    $copy['not_started_count'] = count($not_started);
    $copy['done_count'] = count($done);
    $copy['never_planned_count'] = count($never);
    $copy['scheduled_count'] = count($missing) + count($not_started) + count($done) + count($never);
    $text = $formatter($copy);
    if ((int)$copy['scheduled_count'] === 0 && empty($data['error'])) {
        $when = $data['date'] ?? [];
        $head = supervisor_ai_norm(trim(
            (string)($when['relative'] ?? '') . ' — ' . (string)($when['jalali'] ?? '') . ' — ' . (string)($when['weekday'] ?? '')
        ));
        return $head . "\n" . $teacher_name . ' در این تاریخ کلاسی در برنامه ندارد.';
    }
    return $text;
}

function supervisor_ai_history_asks_previous_term(array $history, $skip_text = '') {
    $skip = supervisor_ai_norm($skip_text);
    for ($i = count($history) - 1; $i >= 0; $i--) {
        if (($history[$i]['role'] ?? '') !== 'user') {
            continue;
        }
        $text = supervisor_ai_norm($history[$i]['content'] ?? '');
        if ($text === '' || ($skip !== '' && $text === $skip)) {
            continue;
        }
        if (supervisor_ai_asks_previous_term($text)) {
            return true;
        }
        if (supervisor_ai_is_date_followup($text) || supervisor_ai_asks_those_followup($text)) {
            continue;
        }
        break;
    }
    return false;
}

function supervisor_ai_try_scenarios(PDO $pdo, $user_text, array $history = []) {
    $user_text = supervisor_ai_unfold_finglish(supervisor_ai_join_name_tokens($user_text));
    if ($user_text === '') {
        return null;
    }
    if (supervisor_ai_is_greeting($user_text)) {
        return 'سلام. از پنل زنده بپرس: کلاس امروز، غایبین، رول‌کال نزده، سشن‌پلن، تکلیف، EAS، Error Gravity، کارنامه، کلاس‌بندی، یا اسم معلم.';
    }

    list($topic, $topic_date) = supervisor_ai_resolve_topic_date($user_text, $history);
    $date = $topic_date !== '' ? $topic_date : 'امروز';

    if ($topic === '' && supervisor_ai_is_date_followup($user_text)) {
        $when = $topic_date !== '' ? $topic_date : supervisor_ai_date_from_text($user_text);
        return supervisor_ai_followup_menu($when !== '' ? $when : 'این تاریخ');
    }
    if ($topic === '' && supervisor_ai_asks_those_followup($user_text)) {
        return supervisor_ai_those_menu();
    }

    $class = supervisor_ai_guess_class_query($user_text);
    $person_now = supervisor_ai_guess_person_query($user_text);
    $school_list = supervisor_ai_is_school_list_ask($user_text);
    if ($class === '' && !$school_list && $topic !== '' && !supervisor_ai_is_school_wide_topic($topic)
        && function_exists('supervisor_ai_class_from_history')) {
        $class = supervisor_ai_class_from_history($history, $user_text);
    }

    $want_prev = supervisor_ai_asks_previous_term($user_text)
        || supervisor_ai_history_asks_previous_term($history, $user_text);
    if ($want_prev && $class !== '' && in_array($topic, ['', 'terms'], true)) {
        $topic = 'report_card';
    }

    $person = $person_now;
    if ($person === '' && supervisor_ai_inherit_person_for_topic($topic)) {
        $follow = supervisor_ai_is_date_followup($user_text) || supervisor_ai_asks_those_followup($user_text);
        if ($follow || ($topic !== '' && !$school_list && !supervisor_ai_is_school_wide_topic($topic))) {
            $person = supervisor_ai_person_from_history($history);
        }
    }

    $gender = supervisor_ai_honorific_gender($user_text);
    $bundle = $person !== '' ? supervisor_ai_person_bundle($pdo, $person) : ['teachers' => [], 'students' => [], 'raw' => ['people' => []]];
    if ($gender !== '' && count($bundle['teachers']) > 1) {
        $filtered = supervisor_ai_filter_people_by_gender($bundle['raw']['people'] ?? [], $gender);
        $bundle = supervisor_ai_person_bundle_from_people($filtered);
    }
    if ($person !== '' && count($bundle['teachers']) > 1 && $topic !== 'explain_panel') {
        $needs_one = in_array($topic, [
            'error_gravity', 'observation_form', 'mind_lingo', 'eas_interview',
            'eas_observation', 'eas_poll', 'eas_overview', 'missing_homework',
            'homework_status', 'missing_rollcall', 'missing_session_plan',
            'session_plan', 'class_session', 'report_card',
        ], true);
        if ($needs_one && $class === '') {
            return supervisor_ai_format_person($bundle['raw']);
        }
    }
    $teacher = supervisor_ai_single_teacher_name($bundle);
    $student = supervisor_ai_single_student($bundle);

    if ($topic === 'class_link') {
        if ($class === '') {
            return 'برای لینک کلاس عنوان را بگو؛ مثلاً لینک P5 آنلاین.';
        }
        return supervisor_ai_format_class_link(supervisor_ai_class_link($pdo, $class, $user_text));
    }
    if ($topic === 'study_plan') {
        $range = supervisor_ai_date_range_from_text($user_text);
        $when = $range ? '' : supervisor_ai_date_from_text($user_text);
        if ($when === '' && !$range && (supervisor_ai_asks_exact_when($user_text) || supervisor_ai_is_date_followup($user_text))) {
            $when = supervisor_ai_last_explicit_date($history, $user_text);
        }
        return supervisor_ai_format_study_plan(supervisor_ai_study_plan($pdo, $user_text, $when));
    }
    if ($topic === 'report_card' && $class !== '') {
        return supervisor_ai_format_report_card(supervisor_ai_report_card($pdo, $class, $want_prev));
    }

    if ($topic === 'missing_rollcall') {
        $data = supervisor_ai_missing_rollcall($pdo, $date);
        if ($teacher !== '') {
            return supervisor_ai_format_gap_with_teacher($data, 'supervisor_ai_format_missing_rollcall', $teacher);
        }
        return supervisor_ai_format_missing_rollcall($data);
    }
    if ($topic === 'missing_session_plan' || $topic === 'session_plan') {
        if ($topic === 'session_plan' && $class !== '') {
            return supervisor_ai_format_session_plan_class(
                supervisor_ai_session_plan_class($pdo, $class, supervisor_ai_date_from_text($user_text), $user_text)
            );
        }
        if ($teacher !== '' && $topic === 'session_plan' && $class === '') {
            return supervisor_ai_format_teacher_feature(supervisor_ai_teacher_feature($pdo, $teacher, 'session_plan', $date));
        }
        $data = supervisor_ai_missing_session_plans($pdo, $date);
        if ($teacher !== '') {
            $formatted = supervisor_ai_format_gap_with_teacher($data, 'supervisor_ai_format_missing_session_plans', $teacher);
            return $formatted;
        }
        $mode = supervisor_ai_gap_answer_mode($user_text, $topic);
        $by_teacher = supervisor_ai_asks_teacher_list($user_text);
        return supervisor_ai_format_session_plan_day($data, $mode, $by_teacher);
    }
    if ($topic === 'class_session') {
        if ($class !== '') {
            return supervisor_ai_format_class_session(supervisor_ai_class_session($pdo, $class, $date));
        }
        if ($teacher !== '') {
            return supervisor_ai_format_teacher_feature(supervisor_ai_teacher_feature($pdo, $teacher, 'class_session', $date));
        }
        return supervisor_ai_format_absences(supervisor_ai_today_absences($pdo, $date));
    }
    if ($topic === 'absences' || ($topic === '' && supervisor_ai_asks_absences($user_text))) {
        if ($class !== '') {
            return supervisor_ai_format_class_session(supervisor_ai_class_session($pdo, $class, $date));
        }
        if ($student) {
            return supervisor_ai_format_student_overview(supervisor_ai_student_overview($pdo, (string)($student['file_number'] ?? $person)));
        }
        if ($teacher !== '') {
            return supervisor_ai_format_teacher_feature(supervisor_ai_teacher_feature($pdo, $teacher, 'class_session', $date));
        }
        return supervisor_ai_format_absences(supervisor_ai_today_absences($pdo, $date));
    }
    if ($topic === 'missing_phone') {
        return supervisor_ai_format_students_without_phone(supervisor_ai_students_without_phone($pdo));
    }
    if ($topic === 'student_info') {
        $who = $person !== '' ? $person : $class;
        if ($who === '') {
            return 'برای پرونده، اسم یا شماره پروندهٔ دانش‌آموز را بگو.';
        }
        return supervisor_ai_format_student_info(supervisor_ai_student_info($pdo, $who));
    }
    if ($topic === 'placement') {
        return supervisor_ai_format_placement(supervisor_ai_placement_summary($pdo));
    }

    $teacher_features = [
        'error_gravity', 'missing_error_gravity',
        'observation_form', 'missing_observation_form',
        'mind_lingo', 'missing_mind_lingo',
        'eas_interview', 'eas_observation', 'eas_poll', 'eas_overview',
        'missing_homework', 'homework_status', 'report_card',
    ];
    if ($class === '' && $teacher !== '' && in_array($topic, $teacher_features, true)) {
        $feat = $topic;
        if ($feat === 'missing_error_gravity') {
            $feat = 'error_gravity';
        }
        if ($feat === 'missing_observation_form') {
            $feat = 'observation_form';
        }
        if ($feat === 'missing_mind_lingo') {
            $feat = 'mind_lingo';
        }
        if ($feat === 'eas_overview') {
            $feat = 'eas';
        }
        if ($feat === 'report_card' && $student) {
            return supervisor_ai_format_report_card(supervisor_ai_report_card($pdo, (string)($student['file_number'] ?? ''), $want_prev));
        }
        if ($feat === 'homework_status' && $student && !$teacher) {
            return supervisor_ai_format_student_homework(supervisor_ai_student_homework($pdo, (string)$student['file_number'], $date));
        }
        if ($feat === 'report_card') {
            $titles = [];
            foreach (($bundle['teachers'][0]['classes'] ?? []) as $c) {
                if (!empty($c['current_term'])) {
                    $titles[] = (string)$c['title'];
                }
            }
            if (count($titles) === 1) {
                return supervisor_ai_format_report_card(supervisor_ai_report_card($pdo, $titles[0], $want_prev));
            }
            $sample = (string)($titles[0] ?? 'P1');
            return (string)$teacher . ' چند کلاس دارد. برای کارنامه عنوان کلاس را بگو؛ مثلاً کارنامهٔ ' . $sample . '.';
        }
        return supervisor_ai_format_teacher_feature(supervisor_ai_teacher_feature($pdo, $teacher, $feat, $date));
    }
    if (($topic === 'homework_status' || $topic === 'missing_homework') && $student && $class === '') {
        return supervisor_ai_format_student_homework(supervisor_ai_student_homework($pdo, (string)$student['file_number'], $date));
    }
    if ($topic === 'report_card' && $student && $class === '') {
        $ov = supervisor_ai_student_overview($pdo, (string)$student['file_number']);
        $cur = [];
        foreach (($ov['classes'] ?? []) as $c) {
            if (!empty($c['current_term'])) {
                $cur[] = $c;
            }
        }
        if (count($cur) === 1) {
            return supervisor_ai_format_report_card(supervisor_ai_report_card($pdo, (string)$cur[0]['title'], $want_prev));
        }
        return supervisor_ai_format_student_overview($ov) . "\nبرای نمره، عنوان کلاس را هم بگو.";
    }

    if (function_exists('supervisor_ai_try_coverage') && $topic !== '' && $topic !== 'absences') {
        $person_for_coverage = $teacher !== '' ? $teacher : ($person !== '' ? $person : '');
        $covered = supervisor_ai_try_coverage($pdo, $topic, $date, $class, $person_for_coverage, $user_text);
        if (is_string($covered) && $covered !== '') {
            return $covered;
        }
    }

    $asks_classes = supervisor_ai_asks_classes($user_text);
    $day = supervisor_ai_effective_day($user_text);
    $person_ctx = $person !== '' ? $person : (($asks_classes || $day !== '') ? supervisor_ai_person_from_history($history) : '');

    if ($class !== '' && $person_now === '') {
        return supervisor_ai_format_class_overview(supervisor_ai_class_overview($pdo, $class));
    }
    if ($person_ctx !== '' && ($day === 'even' || $day === 'odd') && $class === '') {
        $found = supervisor_ai_lookup_person($pdo, $person_ctx);
        if ($gender !== '' && !empty($found['people'])) {
            $found['people'] = supervisor_ai_filter_people_by_gender($found['people'], $gender);
        }
        if (!empty($found['people'])) {
            return supervisor_ai_format_person($found, $day);
        }
    }
    if ($asks_classes && $person_now === '' && $class === '' && $person_ctx === '') {
        if (supervisor_ai_asks_today($user_text) || $day === 'friday') {
            return supervisor_ai_format_today_classes($pdo);
        }
        if ($day !== '') {
            return supervisor_ai_format_classes(supervisor_ai_list_classes($pdo, '', $day));
        }
        if (preg_match('/ترم|جاری|همه|لیست|چند/u', $user_text)) {
            return supervisor_ai_format_classes(supervisor_ai_list_classes($pdo, '', ''));
        }
    }
    if ($person_ctx !== '') {
        $found = supervisor_ai_lookup_person($pdo, $person_ctx);
        if ($gender !== '' && !empty($found['people'])) {
            $found['people'] = supervisor_ai_filter_people_by_gender($found['people'], $gender);
            $tn = 0;
            foreach ($found['people'] as $p) {
                if (($p['role'] ?? '') === 'teacher') {
                    $tn++;
                }
            }
            $found['teacher_count'] = $tn;
            $found['count'] = count($found['people']);
            if ($tn <= 1) {
                $found['note'] = null;
            }
        }
        if (!empty($found['people'])) {
            $only_students = true;
            foreach (($found['people'] ?? []) as $p) {
                if (($p['role'] ?? '') === 'teacher') {
                    $only_students = false;
                    break;
                }
            }
            if ($only_students && count($found['people']) === 1) {
                return supervisor_ai_format_student_overview(
                    supervisor_ai_student_overview($pdo, (string)($found['people'][0]['file_number'] ?? $person_ctx))
                );
            }
            return supervisor_ai_format_person($found, '');
        }
    }
    return null;
}
