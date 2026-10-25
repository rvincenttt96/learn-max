<?php
/**
 * Panel terms drive accounting terms.
 * starts_on in the future = registration only (not on teaching screens / AloCom / cron).
 * starts_on null or today-or-past + not archived = teaching.
 * When a dated term starts, older teaching terms are archived.
 */

function term_sql_teaching($alias = 't') {
    $a = preg_replace('/[^a-zA-Z0-9_]/', '', (string)$alias);
    if ($a === '') {
        $a = 't';
    }
    return "({$a}.is_deleted = 0 OR {$a}.is_deleted IS NULL)
      AND ({$a}.is_archived = 0 OR {$a}.is_archived IS NULL)
      AND ({$a}.starts_on IS NULL OR {$a}.starts_on <= CURDATE())";
}

function term_sql_open($alias = 't') {
    $a = preg_replace('/[^a-zA-Z0-9_]/', '', (string)$alias);
    if ($a === '') {
        $a = 't';
    }
    return "({$a}.is_deleted = 0 OR {$a}.is_deleted IS NULL)
      AND ({$a}.is_archived = 0 OR {$a}.is_archived IS NULL)";
}

function term_sql_teaching_or_unterm($alias = 't') {
    return '(' . term_sql_teaching($alias) . ' OR c.term_id IS NULL)';
}

function term_parse_starts_on($raw) {
    return term_parse_date($raw, 'شروع');
}

function term_parse_date($raw, $label = '') {
    $raw = trim((string)$raw);
    if ($raw === '') {
        return null;
    }
    $prefix = $label !== '' ? ('تاریخ ' . $label) : 'تاریخ';
    if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $raw, $m)) {
        throw new Exception($prefix . ' نامعتبر است. قالب درست: سال-ماه-روز میلادی.');
    }
    if (!checkdate((int)$m[2], (int)$m[3], (int)$m[1])) {
        throw new Exception($prefix . ' نامعتبر است.');
    }
    return $raw;
}

function term_assert_date_range($starts_on, $ends_on) {
    if ($starts_on && $ends_on && (string)$ends_on < (string)$starts_on) {
        throw new Exception('تاریخ پایان نمی‌تواند قبل از تاریخ شروع باشد.');
    }
}

function term_display_date($raw) {
    $raw = trim((string)$raw);
    if ($raw === '' || $raw === '0000-00-00') {
        return '—';
    }
    if (!function_exists('jalali_pretty')) {
        require_once __DIR__ . '/jalali.php';
    }
    $pretty = jalali_pretty($raw);
    return $pretty !== '' ? $pretty : $raw;
}

function term_input_date($raw) {
    $raw = trim((string)$raw);
    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw) ? $raw : '';
}

function term_has_teaching(PDO $pdo) {
    term_ensure_schema($pdo);
    $n = $pdo->query('SELECT COUNT(*) FROM terms t WHERE ' . term_sql_teaching('t'))->fetchColumn();
    return (int)$n > 0;
}

function term_require_future_start_if_teaching(PDO $pdo, $starts_on, $except_id = 0) {
    $teaching = term_pick_teaching(term_open_rows($pdo));
    if (!$teaching) {
        return;
    }
    if ((int)$except_id > 0 && (int)$teaching['id'] === (int)$except_id) {
        return;
    }
    if ($starts_on === null || $starts_on === '' || (string)$starts_on <= date('Y-m-d')) {
        throw new Exception('ترم در حال تدریس وجود دارد. تاریخ شروع ترم جدید باید بعد از امروز باشد تا ترم فعلی از داشبورد معلم و دانش‌آموز حذف نشود.');
    }
}

function term_row_is_teaching(array $term) {
    if ((int)($term['is_deleted'] ?? 0) === 1 || (int)($term['is_archived'] ?? 0) === 1) {
        return false;
    }
    $start = $term['starts_on'] ?? null;
    if ($start === null || $start === '' || $start === '0000-00-00') {
        return true;
    }
    return (string)$start <= date('Y-m-d');
}

function term_row_is_upcoming(array $term) {
    if ((int)($term['is_deleted'] ?? 0) === 1 || (int)($term['is_archived'] ?? 0) === 1) {
        return false;
    }
    $start = $term['starts_on'] ?? null;
    if ($start === null || $start === '' || $start === '0000-00-00') {
        return false;
    }
    return (string)$start > date('Y-m-d');
}

function term_acc_name($title, $year = '') {
    if (!function_exists('term_english_title')) {
        require_once __DIR__ . '/year-program.php';
    }
    $title = term_english_title(trim((string)$title));
    $year = trim((string)$year);
    if ($year !== '') {
        $year = function_exists('ghazal_program_latin_digits') ? ghazal_program_latin_digits($year) : $year;
    }
    if ($title === '') {
        return '';
    }
    if ($year !== '' && !preg_match('/\(' . preg_quote($year, '/') . '\)\s*$/u', $title)) {
        return $title . ' (' . $year . ')';
    }
    return $title;
}

function term_ensure_schema(PDO $pdo) {
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    try {
        $col = $pdo->query("SHOW COLUMNS FROM terms LIKE 'starts_on'")->fetch();
        if (!$col) {
            $pdo->exec("ALTER TABLE terms ADD COLUMN starts_on DATE NULL DEFAULT NULL");
        }
    } catch (Exception $e) {
        error_log('terms starts_on: ' . $e->getMessage());
    }
    try {
        $col = $pdo->query("SHOW COLUMNS FROM terms LIKE 'accounting_term_id'")->fetch();
        if (!$col) {
            $pdo->exec("ALTER TABLE terms ADD COLUMN accounting_term_id VARCHAR(32) NULL DEFAULT NULL");
        }
    } catch (Exception $e) {
        error_log('terms accounting_term_id: ' . $e->getMessage());
    }
    try {
        $col = $pdo->query("SHOW COLUMNS FROM terms LIKE 'ends_on'")->fetch();
        if (!$col) {
            $pdo->exec("ALTER TABLE terms ADD COLUMN ends_on DATE NULL DEFAULT NULL");
        }
    } catch (Exception $e) {
        error_log('terms ends_on: ' . $e->getMessage());
    }
    classes_ensure_schema($pdo);
}

function classes_ensure_schema(PDO $pdo) {
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    try {
        $col = $pdo->query("SHOW COLUMNS FROM classes LIKE 'forward_next'")->fetch();
        if (!$col) {
            $pdo->exec("ALTER TABLE classes ADD COLUMN forward_next TINYINT(1) NOT NULL DEFAULT 0");
        }
    } catch (Exception $e) {
        error_log('classes forward_next: ' . $e->getMessage());
    }
    try {
        $col = $pdo->query("SHOW COLUMNS FROM classes LIKE 'origin_term_id'")->fetch();
        if (!$col) {
            $pdo->exec("ALTER TABLE classes ADD COLUMN origin_term_id INT NULL DEFAULT NULL");
        }
        $pdo->exec("UPDATE classes SET origin_term_id = term_id WHERE origin_term_id IS NULL AND term_id IS NOT NULL");
    } catch (Exception $e) {
        error_log('classes origin_term_id: ' . $e->getMessage());
    }
}

function class_billing_term_id(PDO $pdo, array $class) {
    classes_ensure_schema($pdo);
    $origin = (int)($class['origin_term_id'] ?? 0);
    if ($origin > 0) {
        return $origin;
    }
    $id = (int)($class['id'] ?? 0);
    if ($id > 0) {
        try {
            $st = $pdo->prepare('SELECT origin_term_id, term_id FROM classes WHERE id = ? LIMIT 1');
            $st->execute([$id]);
            $row = $st->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                $from_row = (int)($row['origin_term_id'] ?? 0);
                return $from_row > 0 ? $from_row : (int)($row['term_id'] ?? 0);
            }
        } catch (Exception $e) {
            error_log('class billing term: ' . $e->getMessage());
        }
    }
    return (int)($class['term_id'] ?? 0);
}

/**
 * AloCom join/account status uses the class's current teaching term (Fall 1 now),
 * not origin_term_id. Tuition still bills the origin via class_billing_term_id.
 */
function class_access_term_id(PDO $pdo, array $class) {
    $id = (int)($class['term_id'] ?? 0);
    if ($id > 0) {
        return $id;
    }
    $class_id = (int)($class['id'] ?? 0);
    if ($class_id > 0) {
        try {
            $st = $pdo->prepare('SELECT term_id FROM classes WHERE id = ? LIMIT 1');
            $st->execute([$class_id]);
            $row = $st->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                return (int)($row['term_id'] ?? 0);
            }
        } catch (Exception $e) {
            error_log('class access term: ' . $e->getMessage());
        }
    }
    return 0;
}

function term_pick_forward_destination(PDO $pdo, $from_term_id) {
    $from_term_id = (int)$from_term_id;
    $candidates = [];
    foreach (term_open_rows($pdo) as $row) {
        if ((int)($row['id'] ?? 0) === $from_term_id) {
            continue;
        }
        $candidates[] = $row;
    }
    $upcoming = term_pick_upcoming($candidates);
    if ($upcoming) {
        return $upcoming;
    }
    return term_pick_teaching($candidates);
}

function term_forward_marked_classes(PDO $pdo, $from_term_id, $to_term_id) {
    classes_ensure_schema($pdo);
    $from_term_id = (int)$from_term_id;
    $to_term_id = (int)$to_term_id;
    if ($from_term_id <= 0 || $to_term_id <= 0 || $from_term_id === $to_term_id) {
        return 0;
    }
    $st = $pdo->prepare("
        UPDATE classes
        SET origin_term_id = COALESCE(origin_term_id, term_id),
            term_id = ?
        WHERE term_id = ?
          AND IFNULL(is_deleted, 0) = 0
          AND IFNULL(is_archived, 0) = 0
          AND IFNULL(forward_next, 0) = 1
    ");
    $st->execute([$to_term_id, $from_term_id]);
    return $st->rowCount();
}

function term_set_dates(PDO $pdo, $term_id, $starts_on, $ends_on) {
    term_ensure_schema($pdo);
    term_assert_date_range($starts_on, $ends_on);
    $stmt = $pdo->prepare('UPDATE terms SET starts_on = ?, ends_on = ? WHERE id = ? AND (is_deleted = 0 OR is_deleted IS NULL)');
    $stmt->execute([$starts_on, $ends_on, (int)$term_id]);
}

function term_open_rows(PDO $pdo) {
    term_ensure_schema($pdo);
    return $pdo->query("
        SELECT *
        FROM terms
        WHERE (is_deleted = 0 OR is_deleted IS NULL)
          AND (is_archived = 0 OR is_archived IS NULL)
        ORDER BY year DESC, id DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
}

function term_pick_teaching(array $terms) {
    $best = null;
    $best_on = null;
    foreach ($terms as $term) {
        if (!term_row_is_teaching($term)) {
            continue;
        }
        $on = trim((string)($term['starts_on'] ?? ''));
        if ($on === '' || $on === '0000-00-00') {
            $on = '0000-01-01';
        }
        if ($best === null || $on >= $best_on) {
            $best = $term;
            $best_on = $on;
        }
    }
    return $best;
}

function term_pick_upcoming(array $terms) {
    $best = null;
    $best_on = null;
    foreach ($terms as $term) {
        if (!term_row_is_upcoming($term)) {
            continue;
        }
        $on = (string)($term['starts_on'] ?? '');
        if ($best === null || $on < $best_on) {
            $best = $term;
            $best_on = $on;
        }
    }
    return $best;
}

function term_close_current_before_new(PDO $pdo, $new_starts_on) {
    if (!$new_starts_on) {
        return;
    }
    $current = term_pick_teaching(term_open_rows($pdo));
    if (!$current || (int)($current['id'] ?? 0) <= 0) {
        return;
    }
    $existing_end = trim((string)($current['ends_on'] ?? ''));
    if ($existing_end !== '' && $existing_end !== '0000-00-00') {
        return;
    }
    $day_before = date('Y-m-d', strtotime($new_starts_on . ' -1 day'));
    if ($day_before && $day_before < $new_starts_on) {
        $pdo->prepare('UPDATE terms SET ends_on = ? WHERE id = ?')->execute([$day_before, (int)$current['id']]);
    }
}

function term_load(PDO $pdo, $term_id) {
    term_ensure_schema($pdo);
    $stmt = $pdo->prepare("SELECT * FROM terms WHERE id = ? LIMIT 1");
    $stmt->execute([(int)$term_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function term_mirror_accounting(PDO $panel_pdo, array $term, $status = 'active') {
    $name = term_acc_name($term['title'] ?? '', $term['year'] ?? '');
    if ($name === '') {
        return '';
    }
    if (!function_exists('accounting_pdo')) {
        require_once __DIR__ . '/accounting.php';
    }
    $acc = accounting_pdo();
    $existingId = trim((string)($term['accounting_term_id'] ?? ''));
    if ($existingId !== '') {
        $chk = $acc->prepare('SELECT id FROM terms WHERE id = ? LIMIT 1');
        $chk->execute([$existingId]);
        if ($chk->fetchColumn()) {
            $acc->prepare('UPDATE terms SET name = COALESCE(?, name), status = COALESCE(?, status) WHERE id = ?')
                ->execute([$name, $status, $existingId]);
            return $existingId;
        }
    }
    $found = $acc->prepare('SELECT id FROM terms WHERE name = ? ORDER BY id DESC LIMIT 1');
    $found->execute([$name]);
    $id = $found->fetchColumn();
    if ($id) {
        $acc->prepare('UPDATE terms SET status = COALESCE(?, status) WHERE id = ?')->execute([$status, $id]);
    } else {
        $createdAt = (int) round(microtime(true) * 1000);
        $acc->prepare('INSERT INTO terms (name, status, createdAt) VALUES (?, ?, ?)')
            ->execute([$name, $status, $createdAt]);
        $id = $acc->lastInsertId();
    }
    $id = (string)$id;
    if ($id !== '' && (int)($term['id'] ?? 0) > 0) {
        $panel_pdo->prepare('UPDATE terms SET accounting_term_id = ? WHERE id = ?')
            ->execute([$id, (int)$term['id']]);
    }
    return $id;
}

function term_complete_accounting(array $term) {
    $id = trim((string)($term['accounting_term_id'] ?? ''));
    if ($id === '') {
        $name = term_acc_name($term['title'] ?? '', $term['year'] ?? '');
        if ($name === '') {
            return;
        }
        if (!function_exists('accounting_pdo')) {
            require_once __DIR__ . '/accounting.php';
        }
        $acc = accounting_pdo();
        $found = $acc->prepare('SELECT id FROM terms WHERE name = ? ORDER BY id DESC LIMIT 1');
        $found->execute([$name]);
        $id = (string)$found->fetchColumn();
    }
    if ($id === '') {
        return;
    }
    if (!function_exists('accounting_pdo')) {
        require_once __DIR__ . '/accounting.php';
    }
    accounting_pdo()->prepare("UPDATE terms SET status = 'completed' WHERE id = ?")->execute([$id]);
}

function term_promote_due(PDO $pdo) {
    static $ran = false;
    if ($ran) {
        return;
    }
    $ran = true;
    term_ensure_schema($pdo);
    $today = date('Y-m-d');
    $open = $pdo->query("
        SELECT id, title, year, starts_on, accounting_term_id, is_archived, is_deleted
        FROM terms
        WHERE (is_deleted = 0 OR is_deleted IS NULL)
          AND (is_archived = 0 OR is_archived IS NULL)
    ")->fetchAll(PDO::FETCH_ASSOC);
    $latestStart = null;
    $latestId = 0;
    foreach ($open as $row) {
        $on = (string)($row['starts_on'] ?? '');
        if ($on === '' || $on === '0000-00-00') {
            continue;
        }
        if ($on <= $today && ($latestStart === null || $on > $latestStart)) {
            $latestStart = $on;
            $latestId = (int)$row['id'];
        }
    }
    if ($latestStart === null) {
        return;
    }
    foreach ($open as $row) {
        $on = (string)($row['starts_on'] ?? '');
        if ($on !== '' && $on !== '0000-00-00' && $on > $today) {
            continue;
        }
        if ($on === $latestStart) {
            term_mirror_accounting($pdo, $row, 'active');
            continue;
        }
        if ($latestId > 0) {
            term_forward_marked_classes($pdo, (int)$row['id'], $latestId);
        }
        $pdo->prepare('UPDATE terms SET is_archived = 1 WHERE id = ?')->execute([(int)$row['id']]);
        term_complete_accounting($row);
    }
}

function term_find_accounting_id(array $term) {
    $id = trim((string)($term['accounting_term_id'] ?? ''));
    if ($id !== '') {
        return $id;
    }
    if (!function_exists('accounting_pdo')) {
        require_once __DIR__ . '/accounting.php';
    }
    $acc = accounting_pdo();
    $names = [];
    $with_year = term_acc_name($term['title'] ?? '', $term['year'] ?? '');
    $title = trim((string)($term['title'] ?? ''));
    if ($with_year !== '') {
        $names[] = $with_year;
    }
    if ($title !== '' && $title !== $with_year) {
        $names[] = $title;
    }
    foreach ($names as $name) {
        $found = $acc->prepare('SELECT id FROM terms WHERE name = ? ORDER BY id DESC LIMIT 1');
        $found->execute([$name]);
        $hit = $found->fetchColumn();
        if ($hit) {
            return (string)$hit;
        }
    }
    return '';
}

function term_calendar_item(array $term) {
    if (!function_exists('term_english_title')) {
        require_once __DIR__ . '/year-program.php';
    }
    $start = term_input_date($term['starts_on'] ?? '');
    $end = term_input_date($term['ends_on'] ?? '');
    return [
        'id' => (int)($term['id'] ?? 0),
        'title' => term_english_title($term['title'] ?? ''),
        'original_title' => (string)($term['title'] ?? ''),
        'year' => (string)($term['year'] ?? ''),
        'starts_on' => $start,
        'ends_on' => $end,
        'starts_label' => $start !== '' ? term_display_date($start) : '',
        'ends_label' => $end !== '' ? term_display_date($end) : '',
        'teaching' => term_row_is_teaching($term),
        'upcoming' => term_row_is_upcoming($term),
        'dated' => $start !== '',
    ];
}

function term_calendar_payload(array $terms, $jy = 0, $half = 0) {
    if (!function_exists('jalali_today_parts')) {
        require_once __DIR__ . '/jalali.php';
    }
    $today = jalali_today_parts();
    $jy = (int)$jy;
    if ($jy < 1390 || $jy > 1500) {
        $jy = (int)$today[0];
    }
    $span = jalali_year_span($jy);
    $items = [];
    foreach ($terms as $term) {
        $items[] = term_calendar_item($term);
    }
    return [
        'today' => date('Y-m-d'),
        'today_label' => jalali_pretty(date('Y-m-d')),
        'jy' => $jy,
        'span' => $span,
        'years' => range((int)$today[0] - 1, (int)$today[0] + 1),
        'terms' => $items,
    ];
}

function term_class_acc_term_id(PDO $panel_pdo, $class_id, $create = false) {
    term_ensure_schema($panel_pdo);
    $stmt = $panel_pdo->prepare("
        SELECT t.*
        FROM classes c
        JOIN terms t ON t.id = COALESCE(c.origin_term_id, c.term_id)
        WHERE c.id = ?
        LIMIT 1
    ");
    $stmt->execute([(int)$class_id]);
    $term = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$term) {
        return '';
    }
    $id = term_find_accounting_id($term);
    if ($id !== '') {
        return $id;
    }
    if (!$create) {
        return '';
    }
    return term_mirror_accounting($panel_pdo, $term, term_row_is_teaching($term) || term_row_is_upcoming($term) ? 'active' : 'completed');
}

function term_class_access_acc_term_id(PDO $panel_pdo, $class_id, $create = false) {
    term_ensure_schema($panel_pdo);
    $stmt = $panel_pdo->prepare("
        SELECT t.*
        FROM classes c
        JOIN terms t ON t.id = c.term_id
        WHERE c.id = ?
        LIMIT 1
    ");
    $stmt->execute([(int)$class_id]);
    $term = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$term) {
        return '';
    }
    $id = term_find_accounting_id($term);
    if ($id !== '') {
        return $id;
    }
    if (!$create) {
        return '';
    }
    return term_mirror_accounting($panel_pdo, $term, term_row_is_teaching($term) || term_row_is_upcoming($term) ? 'active' : 'completed');
}
