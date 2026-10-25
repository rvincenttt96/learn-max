<?php
/**
 * Accounting DB (acccounting.ghazalify.com).
 * settled = status paid only (ثبت اولیه / بدهکار do not get AloCom class rooms).
 */

if (!function_exists('ghazal_error_report')) {
    require_once __DIR__ . '/error-log.php';
}

function accounting_config() {
    if (function_exists('rollcall_config')) {
        $config = rollcall_config();
        if (is_array($config)) {
            return $config;
        }
    }
    $path = __DIR__ . '/rollcall-config.php';
    if (is_file($path)) {
        $config = require $path;
        if (is_array($config)) {
            return $config;
        }
    }
    return [];
}

function accounting_pdo() {
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    $config = accounting_config();
    $acc = $config['accounting'] ?? [];
    $host = $acc['host'] ?? 'localhost';
    $db = $acc['database'] ?? 'ghazali1_Acc';
    $user = $acc['user'] ?? 'ghazali1_Acc';
    $pass = $acc['password'] ?? '';
    try {
        $pdo = new PDO(
            "mysql:host={$host};dbname={$db};charset=utf8mb4",
            $user,
            $pass,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]
        );
    } catch (Exception $e) {
        if (function_exists('ghazal_error_report')) {
            ghazal_error_report('DB-CONNECT', 'اتصال به دیتابیس حسابداری برقرار نشد.', ghazal_error_trim($e->getMessage()));
        }
        throw $e;
    }
    $col = $pdo->query("SHOW COLUMNS FROM students LIKE 'studentCode'")->fetch();
    if (!$col) {
        $pdo->exec("ALTER TABLE students ADD COLUMN studentCode VARCHAR(20) NULL DEFAULT NULL");
        try {
            $pdo->exec("ALTER TABLE students ADD INDEX idx_studentCode (studentCode)");
        } catch (Exception $e) {
            // index may already exist
        }
    }
    accounting_ensure_pending_status($pdo);
    return $pdo;
}

function accounting_ensure_pending_status(PDO $pdo = null) {
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    if (!$pdo) {
        $pdo = accounting_pdo();
    }
    $col = $pdo->query("SHOW COLUMNS FROM students LIKE 'status'")->fetch();
    if ($col && stripos((string)$col['Type'], 'pending') === false) {
        $pdo->exec("ALTER TABLE students MODIFY COLUMN status ENUM('paid','unpaid','pending') NOT NULL DEFAULT 'unpaid'");
    }
}

/**
 * No class / no tuition → ثبت اولیه. Payments stay paid/unpaid as Acc already does.
 */
function accounting_status_from_amounts($totalPayable, $amountPaid) {
    $total = (int)$totalPayable;
    $paid = (int)$amountPaid;
    if ($total <= 0 && $paid <= 0) {
        return 'pending';
    }
    return ($total - $paid) <= 0 ? 'paid' : 'unpaid';
}

function accounting_class_type_label($panel_type) {
    $t = strtolower(trim((string)$panel_type));
    if ($t === 'online' || $t === 'آنلاین') {
        return 'آنلاین';
    }
    if ($t === 'private' || $t === 'خصوصی' || $t === 'pv') {
        return 'خصوصی';
    }
    return 'حضوری';
}

function accounting_level_compact($raw) {
    $t = trim((string)$raw);
    $t = str_replace(['ي', 'ك', '‌', 'ـ'], ['ی', 'ک', '', ''], $t);
    $t = preg_replace('/[\s\-_.–—]+/u', '', $t);
    if (function_exists('mb_strtolower')) {
        return mb_strtolower($t, 'UTF-8');
    }
    return strtolower($t);
}

function accounting_match_level($title) {
    $title = trim(preg_replace('/\s+/u', ' ', (string)$title));
    if ($title === '') {
        return null;
    }
    $pdo = accounting_pdo();
    $levels = $pdo->query('SELECT id, name, fee FROM levels')->fetchAll(PDO::FETCH_ASSOC);
    if (!$levels) {
        return null;
    }
    $len = function_exists('mb_strlen')
        ? static function ($s) { return mb_strlen((string)$s, 'UTF-8'); }
        : static function ($s) { return strlen((string)$s); };
    usort($levels, static function ($a, $b) use ($len) {
        return $len($b['name']) <=> $len($a['name']);
    });
    foreach ($levels as $level) {
        $name = trim((string)$level['name']);
        if ($name === '') {
            continue;
        }
        if (strcasecmp($title, $name) === 0) {
            return $level;
        }
    }
    $want = accounting_level_compact($title);
    if ($want !== '') {
        foreach ($levels as $level) {
            $name = trim((string)$level['name']);
            if ($name === '') {
                continue;
            }
            if (accounting_level_compact($name) === $want) {
                return $level;
            }
        }
    }
    foreach ($levels as $level) {
        $name = trim((string)$level['name']);
        if ($name === '') {
            continue;
        }
        $pattern = '/(?<![\p{L}\p{N}])' . preg_quote($name, '/') . '(?![\p{L}\p{N}])/u';
        if (preg_match($pattern, $title)) {
            return $level;
        }
    }
    return null;
}

function accounting_hydrate_student_row(array $row) {
    $row['full'] = trim((string)$row['firstName'] . ' ' . (string)$row['lastName']);
    $row['phone_key'] = preg_replace('/^0+/', '', rollcall_normalize_phone($row['phone'] ?? ''));
    $status = strtolower(trim((string)$row['status']));
    $row['settled'] = ($status === 'paid');
    return $row;
}

function accounting_students_for_term($acc_term_id) {
    $acc_term_id = trim((string)$acc_term_id);
    if ($acc_term_id === '') {
        return [];
    }
    $pdo = accounting_pdo();
    $stmt = $pdo->prepare("
        SELECT s.id, s.termId, s.firstName, s.lastName, s.phone, s.status, s.debt, s.classType, s.level,
               s.studentCode,
               t.name AS term_name, t.status AS term_status
        FROM students s
        LEFT JOIN terms t ON t.id = s.termId
        WHERE s.termId = ?
    ");
    $stmt->execute([$acc_term_id]);
    $rows = [];
    foreach ($stmt->fetchAll() as $row) {
        $rows[] = accounting_hydrate_student_row($row);
    }
    return $rows;
}

function accounting_match_student_for_term(array $panel_student, $acc_term_id) {
    $pool = accounting_students_for_term($acc_term_id);
    return accounting_match_student($panel_student, $pool);
}

function accounting_match_student_for_class(PDO $panel_pdo, array $panel_student, array $class) {
    if (!function_exists('term_load')) {
        require_once __DIR__ . '/terms.php';
    }
    $term = null;
    $term_id = 0;
    if (!empty($class['id']) && function_exists('class_billing_term_id')) {
        $term_id = class_billing_term_id($panel_pdo, $class);
    }
    if ($term_id <= 0) {
        $term_id = (int)($class['term_id'] ?? 0);
    }
    if ($term_id > 0) {
        $term = term_load($panel_pdo, $term_id);
    }
    $linked = $term ? term_find_accounting_id($term) : '';
    if ($linked === '' && !empty($class['id'])) {
        $linked = term_class_acc_term_id($panel_pdo, (int)$class['id'], false);
    }
    if ($linked !== '') {
        $hit = accounting_match_student_for_term($panel_student, $linked);
        if ($hit) {
            return $hit;
        }
        if ($term && term_row_is_upcoming($term)) {
            return null;
        }
    }
    $pool = accounting_active_students();
    return accounting_match_student($panel_student, $pool);
}

/**
 * AloCom room access: current class term only (Fall 1 while it is teaching).
 * Does not fall back to other active Acc terms (Summer leftover / Fall 2 registration).
 */
function accounting_match_student_for_access(PDO $panel_pdo, array $panel_student, array $class) {
    if (!function_exists('term_load')) {
        require_once __DIR__ . '/terms.php';
    }
    $term_id = 0;
    if (!empty($class['id']) && function_exists('class_access_term_id')) {
        $term_id = class_access_term_id($panel_pdo, $class);
    }
    if ($term_id <= 0) {
        $term_id = (int)($class['term_id'] ?? 0);
    }
    if ($term_id <= 0) {
        return null;
    }
    $term = term_load($panel_pdo, $term_id);
    $linked = $term ? term_find_accounting_id($term) : '';
    if ($linked === '' && !empty($class['id']) && function_exists('term_class_access_acc_term_id')) {
        $linked = term_class_access_acc_term_id($panel_pdo, (int)$class['id'], false);
    }
    if ($linked === '') {
        return null;
    }
    return accounting_match_student_for_term($panel_student, $linked);
}

function accounting_active_students() {
    $pdo = accounting_pdo();
    $stmt = $pdo->query("
        SELECT s.id, s.termId, s.firstName, s.lastName, s.phone, s.status, s.debt, s.classType, s.level,
               s.studentCode,
               t.name AS term_name, t.status AS term_status
        FROM students s
        LEFT JOIN terms t ON t.id = s.termId
        WHERE t.status = 'active' OR t.id IS NULL
    ");
    $rows = [];
    foreach ($stmt->fetchAll() as $row) {
        $rows[] = accounting_hydrate_student_row($row);
    }
    return $rows;
}

function accounting_match_student(array $panel_student, array &$pool) {
    $code = trim((string)($panel_student['username'] ?? ''));
    if ($code !== '') {
        foreach ($pool as $i => $acc) {
            if (trim((string)($acc['studentCode'] ?? '')) === $code) {
                $hit = $acc;
                unset($pool[$i]);
                return $hit;
            }
        }
    }
    $phone_key = preg_replace('/^0+/', '', rollcall_normalize_phone($panel_student['phone'] ?? ''));
    if ($phone_key !== '') {
        foreach ($pool as $i => $acc) {
            if ($acc['phone_key'] !== '' && $acc['phone_key'] === $phone_key) {
                $hit = $acc;
                unset($pool[$i]);
                return $hit;
            }
        }
    }
    $panel_full = (string)($panel_student['full_name'] ?? '');
    foreach ($pool as $i => $acc) {
        if (accounting_names_match($panel_full, $acc['full'])) {
            $hit = $acc;
            unset($pool[$i]);
            return $hit;
        }
    }
    $p_tokens = alocom_name_tokens($panel_full);
    $pfirst = $p_tokens[0] ?? '';
    $plast = $p_tokens ? end($p_tokens) : '';
    if ($pfirst !== '') {
        $hits = [];
        foreach ($pool as $i => $acc) {
            $afirst = alocom_name_tokens($acc['full'])[0] ?? '';
            if ($afirst !== '' && accounting_first_names_close($pfirst, $afirst)) {
                $hits[] = $i;
            }
        }
        if (count($hits) === 1) {
            $hit = $pool[$hits[0]];
            unset($pool[$hits[0]]);
            return $hit;
        }
    }
    if ($plast !== '') {
        $hits = [];
        foreach ($pool as $i => $acc) {
            $atok = alocom_name_tokens($acc['full']);
            $alast = $atok ? end($atok) : '';
            if ($alast !== '' && accounting_last_names_close($plast, $alast)) {
                $hits[] = $i;
            }
        }
        if (count($hits) === 1) {
            $hit = $pool[$hits[0]];
            unset($pool[$hits[0]]);
            return $hit;
        }
    }
    return null;
}

function accounting_first_names_close($a, $b) {
    if (alocom_names_similar($a, $b)) {
        return true;
    }
    $ca = preg_replace('/[aeiou]+/i', '', alocom_compact_name($a));
    $cb = preg_replace('/[aeiou]+/i', '', alocom_compact_name($b));
    if ($ca === '' || $cb === '') {
        return false;
    }
    return $ca === $cb || levenshtein($ca, $cb) <= 1;
}

function accounting_last_names_close($a, $b) {
    if (alocom_names_similar($a, $b)) {
        return true;
    }
    $ca = preg_replace('/[aeiou]+/i', '', alocom_compact_name($a));
    $cb = preg_replace('/[aeiou]+/i', '', alocom_compact_name($b));
    if ($ca === '' || $cb === '' || (strlen($ca) < 4 && strlen($cb) < 4)) {
        return false;
    }
    return $ca === $cb || levenshtein($ca, $cb) <= 2;
}

function accounting_names_match($panel_full, $acc_full) {
    if (alocom_names_similar($panel_full, $acc_full)) {
        return true;
    }
    $p = alocom_name_tokens($panel_full);
    $a = alocom_name_tokens($acc_full);
    if (!$p || !$a) {
        return false;
    }
    $plast = end($p);
    $alast = end($a);
    $pfirst = $p[0];
    $afirst = $a[0];
    $plc = preg_replace('/[aeiou]+/i', '', alocom_compact_name($plast));
    $alc = preg_replace('/[aeiou]+/i', '', alocom_compact_name($alast));
    $last_ok = alocom_names_similar($plast, $alast)
        || ($plc !== '' && $alc !== '' && (strlen($plc) >= 4 || strlen($alc) >= 4) && levenshtein($plc, $alc) <= 1);
    $pfc = preg_replace('/[aeiou]+/i', '', alocom_compact_name($pfirst));
    $afc = preg_replace('/[aeiou]+/i', '', alocom_compact_name($afirst));
    $first_ok = alocom_names_similar($pfirst, $afirst)
        || levenshtein(alocom_compact_name($pfirst), alocom_compact_name($afirst)) <= 2
        || ($pfc !== '' && $afc !== '' && levenshtein($pfc, $afc) <= 1);
    return $last_ok && $first_ok;
}
