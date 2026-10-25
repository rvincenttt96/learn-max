<?php
/**
 * Canonical student identity is the old file number (شماره پرونده).
 * Panel + site username and password = that number.
 * Accounting copies the file number from the panel; it does not invent new codes.
 */

require_once __DIR__ . '/rollcall.php';
require_once __DIR__ . '/accounting.php';
require_once __DIR__ . '/terms.php';
if (!function_exists('alocom_name_tokens')) {
    require_once __DIR__ . '/alocom.php';
}

function identity_log($message) {
    $line = date('[Y-m-d H:i:s] ') . $message . "\n";
    @error_log($line, 3, __DIR__ . '/../dashboard/supervisor/identity_log.txt');
}

function identity_valid_secret($given) {
    $config = rollcall_config();
    $secret = (string)($config['identity']['secret'] ?? '');
    if ($secret === '' || $given === '') {
        return false;
    }
    return hash_equals($secret, (string)$given);
}

function identity_default_password($code) {
    return identity_normalize_code($code);
}

function identity_generated_code_floor() {
    return 5000;
}

function identity_is_file_number($code) {
    $code = identity_normalize_code($code);
    if ($code === '' || !preg_match('/^[0-9]{3,4}$/', $code)) {
        return false;
    }
    return (int)$code > 0 && (int)$code < identity_generated_code_floor();
}

function identity_alocom_passwords($code) {
    $code = identity_normalize_code($code);
    return [$code, 'Ghazal' . $code];
}

function identity_normalize_code($code) {
    $code = trim((string)$code);
    $map = ['۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9',
            '٠'=>'0','١'=>'1','٢'=>'2','٣'=>'3','٤'=>'4','٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9'];
    $code = strtr($code, $map);
    $code = preg_replace('/\s+/', '', $code);
    return $code;
}

function identity_is_code($code) {
    $code = identity_normalize_code($code);
    return $code !== '' && preg_match('/^[0-9]{3,8}$/', $code);
}

function identity_split_name($full_name) {
    $full_name = trim(preg_replace('/\s+/u', ' ', (string)$full_name));
    if ($full_name === '') {
        return ['', ''];
    }
    $parts = preg_split('/\s+/u', $full_name);
    $first = array_shift($parts);
    $last = implode(' ', $parts);
    return [$first, $last];
}

function identity_ensure_acc_schema() {
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    $pdo = accounting_pdo();
    $col = $pdo->query("SHOW COLUMNS FROM students LIKE 'studentCode'")->fetch();
    if (!$col) {
        $pdo->exec("ALTER TABLE students ADD COLUMN studentCode VARCHAR(20) NULL DEFAULT NULL");
        try {
            $pdo->exec("ALTER TABLE students ADD INDEX idx_studentCode (studentCode)");
        } catch (Exception $e) {
            identity_log('index studentCode: ' . $e->getMessage());
        }
        identity_log('Added students.studentCode column');
    }
    accounting_ensure_pending_status($pdo);
}

function identity_used_codes(PDO $panel_pdo) {
    $used = [];
    $stmt = $panel_pdo->query("SELECT username FROM users WHERE username REGEXP '^[0-9]{3,8}$'");
    foreach ($stmt as $row) {
        $code = identity_normalize_code($row['username']);
        if ($code !== '') {
            $used[$code] = true;
        }
    }
    identity_ensure_acc_schema();
    $acc = accounting_pdo();
    $stmt = $acc->query("SELECT studentCode FROM students WHERE studentCode IS NOT NULL AND studentCode <> ''");
    foreach ($stmt as $row) {
        $code = identity_normalize_code($row['studentCode']);
        if ($code !== '') {
            $used[$code] = true;
        }
    }
    return $used;
}

function identity_next_code(PDO $panel_pdo) {
    $used = identity_used_codes($panel_pdo);
    $max = 1101;
    $stmt = $panel_pdo->query("SELECT username FROM users WHERE role = 'student' AND username REGEXP '^[0-9]{3,8}$'");
    foreach ($stmt as $row) {
        $code = identity_normalize_code($row['username']);
        if (identity_is_file_number($code)) {
            $max = max($max, (int)$code);
        }
    }
    identity_ensure_acc_schema();
    $acc = accounting_pdo();
    $stmt = $acc->query("SELECT studentCode FROM students WHERE studentCode IS NOT NULL AND studentCode <> ''");
    foreach ($stmt as $row) {
        $code = identity_normalize_code($row['studentCode']);
        if (identity_is_file_number($code)) {
            $max = max($max, (int)$code);
        }
    }
    $next = $max + 1;
    $guard = 0;
    while (isset($used[(string)$next]) && $guard < 10000) {
        $next++;
        $guard++;
    }
    return (string)$next;
}

function identity_is_placeholder_name($name, $code = '') {
    $name = trim((string)$name);
    if ($name === '') {
        return true;
    }
    $code = identity_normalize_code($code);
    if ($code !== '' && identity_normalize_code($name) === $code) {
        return true;
    }
    return (bool) preg_match('/^[0-9]{3,8}$/', identity_normalize_code($name));
}

function identity_code_from_person(PDO $panel_pdo, $full_name, $phone, $strict = false) {
    $full_name = trim((string)$full_name);
    $phone_key = preg_replace('/^0+/', '', rollcall_normalize_phone($phone));
    $name_hits = [];
    $phone_and_name = '';
    $stmt = $panel_pdo->query("SELECT username, full_name, phone FROM users WHERE role = 'student' AND is_deleted = 0");
    foreach ($stmt as $panel) {
        if (!identity_is_file_number($panel['username'])) {
            continue;
        }
        $code = identity_normalize_code($panel['username']);
        $row_key = preg_replace('/^0+/', '', rollcall_normalize_phone($panel['phone'] ?? ''));
        $names_match = $full_name !== '' && accounting_names_match($panel['full_name'] ?? '', $full_name);
        if ($phone_key !== '' && $row_key === $phone_key && $names_match) {
            $phone_and_name = $code;
            break;
        }
        if ($names_match) {
            $name_hits[$code] = true;
        }
        if (!$strict && $full_name !== '') {
            $pool = [[
                'id' => '0',
                'full' => $full_name,
                'phone_key' => $phone_key,
                'studentCode' => '',
            ]];
            $hit = accounting_match_student($panel, $pool);
            if ($hit) {
                $name_hits[$code] = true;
            }
        }
    }
    if ($phone_and_name !== '') {
        return $phone_and_name;
    }
    if (count($name_hits) === 1) {
        $keys = array_keys($name_hits);
        return (string) $keys[0];
    }
    return '';
}

function identity_code_from_phone($phone, PDO $panel_pdo) {
    $phone_key = preg_replace('/^0+/', '', rollcall_normalize_phone($phone));
    if ($phone_key === '') {
        return '';
    }
    $stmt = $panel_pdo->prepare("
        SELECT username FROM users
        WHERE role = 'student' AND is_deleted = 0 AND phone IS NOT NULL AND phone <> ''
    ");
    $stmt->execute();
    foreach ($stmt as $row) {
        $row_key = preg_replace('/^0+/', '', rollcall_normalize_phone($row['phone'] ?? ''));
        if ($row_key !== '' && $row_key === $phone_key && identity_is_file_number($row['username'])) {
            return identity_normalize_code($row['username']);
        }
    }
    identity_ensure_acc_schema();
    $acc = accounting_pdo();
    $stmt = $acc->query("SELECT phone, studentCode FROM students WHERE studentCode IS NOT NULL AND studentCode <> ''");
    foreach ($stmt as $row) {
        $row_key = preg_replace('/^0+/', '', rollcall_normalize_phone($row['phone'] ?? ''));
        if ($row_key !== '' && $row_key === $phone_key && identity_is_file_number($row['studentCode'])) {
            return identity_normalize_code($row['studentCode']);
        }
    }
    return '';
}

function identity_ensure_panel_student(PDO $panel_pdo, $code, $full_name, $phone, $plain_password, $reset_password = false) {
    $code = identity_normalize_code($code);
    $full_name = trim((string)$full_name);
    $phone = trim((string)$phone);
    if ($plain_password === '' || $plain_password === null) {
        $plain_password = identity_default_password($code);
    }
    $stmt = $panel_pdo->prepare("SELECT id, is_deleted, phone, full_name FROM users WHERE username = ? LIMIT 1");
    $stmt->execute([$code]);
    $existing = $stmt->fetch();
    if ($existing) {
        $sets = ['is_deleted = 0'];
        $params = [];
        if ($full_name !== '' && !identity_is_placeholder_name($full_name, $code)) {
            $sets[] = 'full_name = ?';
            $params[] = $full_name;
        }
        if ($phone !== '') {
            $sets[] = 'phone = ?';
            $params[] = $phone;
        }
        $sets[] = "role = 'student'";
        if ($reset_password) {
            $sets[] = 'password = ?';
            $params[] = password_hash($plain_password, PASSWORD_DEFAULT);
        }
        $params[] = $existing['id'];
        $panel_pdo->prepare('UPDATE users SET ' . implode(', ', $sets) . ' WHERE id = ?')->execute($params);
        return ['id' => (int)$existing['id'], 'created' => false];
    }
    $hash = password_hash($plain_password, PASSWORD_DEFAULT);
    $stmt = $panel_pdo->prepare("
        INSERT INTO users (username, password, full_name, phone, role, is_deleted, created_at)
        VALUES (?, ?, ?, ?, 'student', 0, NOW())
    ");
    $insert_name = ($full_name !== '' && !identity_is_placeholder_name($full_name, $code)) ? $full_name : $code;
    $stmt->execute([$code, $hash, $insert_name, $phone,]);
    return ['id' => (int)$panel_pdo->lastInsertId(), 'created' => true];
}

function identity_wp_request(array $payload) {
    $config = rollcall_config();
    $url = $config['wordpress']['ensure_url'] ?? 'https://ghazalify.com/wp-content/ghazal-identity-ensure.php';
    $secret = $config['identity']['secret'] ?? '';
    if ($url === '' || $secret === '') {
        return ['ok' => false, 'error' => 'wordpress not configured'];
    }
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Accept: application/json',
            'X-Ghazal-Identity: ' . $secret,
        ],
    ]);
    $raw = curl_exec($ch);
    $err = curl_error($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($raw === false) {
        return ['ok' => false, 'error' => $err, 'http' => $code];
    }
    $json = json_decode($raw, true);
    if (!is_array($json)) {
        return ['ok' => false, 'error' => 'invalid json', 'http' => $code, 'raw' => substr((string)$raw, 0, 300)];
    }
    $json['http'] = $code;
    return $json;
}

function identity_panel_pdo($explicit = null) {
    if ($explicit instanceof PDO) {
        return $explicit;
    }
    if (isset($GLOBALS['identity_panel_pdo']) && $GLOBALS['identity_panel_pdo'] instanceof PDO) {
        return $GLOBALS['identity_panel_pdo'];
    }
    if (isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO) {
        return $GLOBALS['pdo'];
    }
    return null;
}

function identity_term_pack(PDO $panel_pdo, $term) {
    if (!$term || !is_array($term)) {
        return null;
    }
    require_once __DIR__ . '/terms.php';
    require_once __DIR__ . '/year-program.php';
    $acc_id = term_find_accounting_id($term);
    if ($acc_id === '') {
        $acc_id = term_mirror_accounting($panel_pdo, $term, 'active');
    }
    return [
        'panelId' => (int)($term['id'] ?? 0),
        'title' => term_english_title($term['title'] ?? ''),
        'name' => term_acc_name($term['title'] ?? '', $term['year'] ?? ''),
        'accountingTermId' => (string)$acc_id,
        'startsOn' => $term['starts_on'] ?? null,
        'endsOn' => $term['ends_on'] ?? null,
    ];
}

function identity_term_meta(PDO $panel_pdo) {
    require_once __DIR__ . '/terms.php';
    term_ensure_schema($panel_pdo);
    try {
        term_promote_due($panel_pdo);
    } catch (Exception $e) {
        identity_log('term_promote_due: ' . $e->getMessage());
    }
    $open = term_open_rows($panel_pdo);
    $teaching = term_pick_teaching($open);
    $upcoming = term_pick_upcoming($open);
    return [
        'ok' => true,
        'today' => date('Y-m-d'),
        'teaching' => identity_term_pack($panel_pdo, $teaching),
        'registration' => identity_term_pack($panel_pdo, $upcoming),
    ];
}

function identity_active_term_id() {
    $panel = identity_panel_pdo();
    if ($panel instanceof PDO) {
        require_once __DIR__ . '/terms.php';
        term_ensure_schema($panel);
        $teaching = term_pick_teaching(term_open_rows($panel));
        if ($teaching) {
            $id = term_find_accounting_id($teaching);
            if ($id === '') {
                $id = term_mirror_accounting($panel, $teaching, 'active');
            }
            if ($id !== '') {
                return (string)$id;
            }
        }
    }
    $acc = accounting_pdo();
    $id = $acc->query("SELECT id FROM terms WHERE status = 'active' ORDER BY createdAt ASC, id ASC LIMIT 1")->fetchColumn();
    return $id !== false && $id !== null ? (string)$id : '';
}

function identity_find_acc_student_id($code, $phone) {
    identity_ensure_acc_schema();
    $acc = accounting_pdo();
    $code = identity_normalize_code($code);
    if ($code !== '') {
        $stmt = $acc->prepare('SELECT id FROM students WHERE studentCode = ? ORDER BY id DESC LIMIT 1');
        $stmt->execute([$code]);
        $id = $stmt->fetchColumn();
        if ($id) {
            return (int)$id;
        }
    }
    $phone_key = preg_replace('/^0+/', '', rollcall_normalize_phone($phone));
    if ($phone_key === '') {
        return 0;
    }
    $stmt = $acc->query('SELECT id, phone FROM students');
    foreach ($stmt as $row) {
        $row_key = preg_replace('/^0+/', '', rollcall_normalize_phone($row['phone'] ?? ''));
        if ($row_key !== '' && $row_key === $phone_key) {
            return (int)$row['id'];
        }
    }
    return 0;
}

function identity_ensure_acc_student($code, $full_name, $phone, $acc_student_id = null, $opts = []) {
    identity_ensure_acc_schema();
    $acc = accounting_pdo();
    $code = identity_normalize_code($code);
    if ($code === '' || !identity_is_file_number($code)) {
        return 0;
    }
    if (!is_array($opts)) {
        $opts = [];
    }
    $overwrite = !empty($opts['overwrite_name']);
    $previous = identity_normalize_code($opts['previous_code'] ?? '');
    list($first, $last) = identity_split_name($full_name);
    if ($first === '') {
        $first = $code;
    }
    if ($last === '') {
        $last = '-';
    }
    $id = (int)$acc_student_id;
    if ($id <= 0) {
        $id = identity_find_acc_student_id($code, $phone);
    }
    if ($id <= 0 && $previous !== '' && $previous !== $code) {
        $id = identity_find_acc_student_id($previous, '');
    }
    if ($id > 0) {
        if ($overwrite) {
            $sets = ['studentCode = ?', 'firstName = ?', 'lastName = ?'];
            $params = [$code, $first, $last];
            if ($phone !== '') {
                $sets[] = 'phone = ?';
                $params[] = $phone;
            }
            $params[] = $id;
            $acc->prepare('UPDATE students SET ' . implode(', ', $sets) . ' WHERE id = ?')->execute($params);
        } else {
            $acc->prepare("
                UPDATE students
                SET studentCode = ?,
                    firstName = IF(? = '' OR firstName IS NULL OR firstName = '', ?, firstName),
                    lastName = IF(? = '' OR lastName IS NULL OR lastName = '' OR lastName = '-', ?, lastName),
                    phone = IF(? = '' OR phone IS NULL OR phone = '', ?, phone)
                WHERE id = ?
            ")->execute([$code, $first, $first, $last, $last, $phone, $phone, $id]);
        }
        identity_set_acc_code($id, $code, $phone);
        return $id;
    }
    $termId = identity_active_term_id();
    $params = [$first, $last, $phone !== '' ? $phone : null, $code, $termId];
    try {
        $stmt = $acc->prepare("
            INSERT INTO students
                (firstName, lastName, level, phone, classType, studentCode,
                 totalPayable, amountPaid, debt, status, termId,
                 hasBook, bookPrice, hasInterview, hasDiscount, discountPercent, discountAmount, createdAt)
            VALUES (?, ?, '', ?, '', ?, 0, 0, 0, 'pending', ?, 0, 0, 0, 0, 0, 0, NOW())
        ");
        $stmt->execute($params);
    } catch (Exception $e) {
        identity_log('acc insert with createdAt failed: ' . $e->getMessage());
        $stmt = $acc->prepare("
            INSERT INTO students
                (firstName, lastName, level, phone, classType, studentCode,
                 totalPayable, amountPaid, debt, status, termId,
                 hasBook, bookPrice, hasInterview, hasDiscount, discountPercent, discountAmount)
            VALUES (?, ?, '', ?, '', ?, 0, 0, 0, 'pending', ?, 0, 0, 0, 0, 0, 0)
        ");
        $stmt->execute($params);
    }
    $id = (int)$acc->lastInsertId();
    identity_log('acc created id=' . $id . ' code=' . $code);
    return $id;
}

function identity_ensure_alocom_student(PDO $panel_pdo, $code, $full_name, $phone) {
    $code = identity_normalize_code($code);
    if ($code === '' || !identity_is_file_number($code)) {
        return ['ok' => false, 'error' => 'no file number'];
    }
    if (!function_exists('alocom_ensure_student_account')) {
        require_once __DIR__ . '/alocom-access.php';
    }
    $stmt = $panel_pdo->prepare("
        SELECT id, full_name, username, phone
        FROM users
        WHERE username = ? AND role = 'student' AND is_deleted = 0
        LIMIT 1
    ");
    $stmt->execute([$code]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$student) {
        return ['ok' => false, 'error' => 'panel user missing'];
    }
    if (trim((string)($student['full_name'] ?? '')) === '') {
        $student['full_name'] = $full_name;
    }
    if (trim((string)($student['phone'] ?? '')) === '') {
        $student['phone'] = $phone;
    }
    try {
        $account = alocom_ensure_student_account($panel_pdo, $student);
        identity_log('alocom ensure code=' . $code . ' user=' . ($account['username'] ?? '') . ' id=' . ($account['id'] ?? 0));
        $account['ok'] = true;
        return $account;
    } catch (Exception $e) {
        identity_log('alocom ensure ' . $code . ': ' . $e->getMessage());
        if (function_exists('ghazal_error_report') && stripos($e->getMessage(), 'AloCom') === false) {
            ghazal_error_report(
                'ALO-USER',
                'کاربر الوکام پرونده ' . $code . ' ساخته نشد.',
                ghazal_error_trim($e->getMessage())
            );
        }
        return ['ok' => false, 'error' => $e->getMessage()];
    }
}

function identity_set_acc_code($acc_student_id, $code, $phone = '') {
    identity_ensure_acc_schema();
    $acc = accounting_pdo();
    $code = identity_normalize_code($code);
    if ($acc_student_id) {
        $stmt = $acc->prepare('UPDATE students SET studentCode = ? WHERE id = ?');
        $stmt->execute([$code, $acc_student_id]);
    }
    $phone_key = preg_replace('/^0+/', '', rollcall_normalize_phone($phone));
    if ($phone_key !== '') {
        $stmt = $acc->query('SELECT id, phone, studentCode FROM students');
        foreach ($stmt as $row) {
            if ((string)$row['id'] === (string)$acc_student_id) {
                continue;
            }
            $existing = identity_normalize_code($row['studentCode'] ?? '');
            if (identity_is_file_number($existing) && $existing !== $code) {
                continue;
            }
            $row_key = preg_replace('/^0+/', '', rollcall_normalize_phone($row['phone'] ?? ''));
            if ($row_key !== '' && $row_key === $phone_key) {
                $acc->prepare('UPDATE students SET studentCode = ? WHERE id = ?')->execute([$code, $row['id']]);
            }
        }
    }
}

/**
 * @return array{ok:bool,code:string,panel:array,wordpress:array,accounting:bool}
 */
function identity_sync_student(PDO $panel_pdo, array $opts) {
    $GLOBALS['identity_panel_pdo'] = $panel_pdo;
    $defer_remote = !empty($opts['defer_remote']);
    if (!$defer_remote) {
        identity_ensure_acc_schema();
    }
    $full_name = trim((string)($opts['full_name'] ?? ''));
    $first = trim((string)($opts['first_name'] ?? ''));
    $last = trim((string)($opts['last_name'] ?? ''));
    if ($full_name === '') {
        $full_name = trim($first . ' ' . $last);
    }
    if ($first === '' && $full_name !== '') {
        list($first, $last) = identity_split_name($full_name);
    }
    $phone = trim((string)($opts['phone'] ?? ''));
    $code = identity_normalize_code($opts['code'] ?? '');
    $explicit_password = isset($opts['password']) && $opts['password'] !== '' && $opts['password'] !== null;
    $password = $explicit_password ? (string)$opts['password'] : '';
    $reset = !empty($opts['reset_password']);
    $acc_id = $opts['acc_student_id'] ?? null;
    $previous = identity_normalize_code($opts['previous_code'] ?? '');
    $source = (string)($opts['source'] ?? '');
    $allow_create = !empty($opts['create_if_missing']) || in_array($source, [
        'accounting',
        'accounting-batch',
        'panel-create',
        'panel-import',
        'panel-restore',
        'backfill-acc',
    ], true);

    if (!identity_is_file_number($code)) {
        $code = identity_code_from_person($panel_pdo, $full_name, $phone);
    }
    if (!identity_is_file_number($code)) {
        if (!$allow_create) {
            return [
                'ok' => false,
                'error' => 'no file number',
                'code' => '',
                'panel' => [],
                'wordpress' => [],
                'accounting' => false,
            ];
        }
        $code = identity_next_code($panel_pdo);
    }
    if ($password === '') {
        $password = identity_default_password($code);
    }

    $panel = identity_ensure_panel_student(
        $panel_pdo,
        $code,
        $full_name,
        $phone,
        $password,
        $reset
    );

    if ($defer_remote) {
        if (!empty($panel['created'])) {
            identity_queue_welcome_sms($panel_pdo, (int)($panel['id'] ?? 0), $code);
        }
        identity_log('sync defer_remote source=' . ($opts['source'] ?? '?') . ' code=' . $code . ' panel=' . (empty($panel['created']) ? 'existing' : 'created'));
        return [
            'ok' => true,
            'code' => $code,
            'panel' => $panel,
            'wordpress' => ['ok' => false, 'deferred' => true],
            'accounting' => false,
            'accounting_id' => 0,
            'alocom' => ['ok' => false, 'deferred' => true],
            'welcome_sms' => ['sent' => false, 'skipped' => 'deferred'],
            'deferred' => true,
        ];
    }

    $wp_reset = $reset || !empty($opts['wp_reset_password']) || !empty($panel['created']);
    $wp = identity_wp_request([
        'code' => $code,
        'previous_code' => $previous,
        'full_name' => $full_name !== '' ? $full_name : $code,
        'first_name' => $first,
        'last_name' => $last,
        'phone' => $phone,
        'password' => $password,
        'reset_password' => $wp_reset ? 1 : 0,
    ]);

    $acc_row_id = 0;
    $overwrite_name = !empty($opts['overwrite_name']) || in_array($source, [
        'students-info-edit',
        'students-info-import',
    ], true);
    try {
        $acc_row_id = identity_ensure_acc_student($code, $full_name, $phone, $acc_id, [
            'overwrite_name' => $overwrite_name,
            'previous_code' => $previous,
        ]);
    } catch (Exception $e) {
        identity_log('acc ensure ' . $code . ': ' . $e->getMessage());
        if (function_exists('ghazal_error_report')) {
            ghazal_error_report(
                'DB-SAVE',
                'ثبت حسابداری پرونده ' . $code . ' ناموفق بود.',
                ghazal_error_trim($e->getMessage())
            );
        }
    }

    $alocom = ['ok' => false];
    try {
        $alocom = identity_ensure_alocom_student($panel_pdo, $code, $full_name, $phone);
    } catch (Exception $e) {
        identity_log('alocom sync ' . $code . ': ' . $e->getMessage());
        $alocom = ['ok' => false, 'error' => $e->getMessage()];
        if (function_exists('ghazal_error_report') && stripos($e->getMessage(), 'AloCom') === false) {
            ghazal_error_report(
                'ALO-USER',
                'کاربر الوکام پرونده ' . $code . ' ساخته نشد.',
                ghazal_error_trim($e->getMessage())
            );
        }
    }

    $welcome = ['sent' => false];
    try {
        $welcome = identity_maybe_welcome_sms($panel_pdo, $opts, $panel, $code, $full_name, $first, $phone);
    } catch (Exception $e) {
        identity_log('welcome sms ' . $code . ': ' . $e->getMessage());
        $welcome = ['sent' => false, 'error' => $e->getMessage()];
        if (function_exists('ghazal_error_report')) {
            ghazal_error_report(
                'SMS-FAIL',
                'خوش‌آمدگویی پرونده ' . $code . ' به خطا خورد.',
                ghazal_error_trim($e->getMessage())
            );
        }
    }

    identity_log('sync source=' . ($opts['source'] ?? '?') . ' code=' . $code . ' panel=' . (empty($panel['created']) ? 'existing' : 'created') . ' acc=' . ($acc_row_id ?: 0) . ' alocom=' . (!empty($alocom['ok']) ? 'ok' : 'fail') . ' wp=' . (!empty($wp['ok']) ? 'ok' : 'fail') . ' welcome=' . (!empty($welcome['sent']) ? 'sent' : ($welcome['skipped'] ?? 'skip')));

    return [
        'ok' => true,
        'code' => $code,
        'panel' => $panel,
        'wordpress' => $wp,
        'accounting' => $acc_row_id > 0,
        'accounting_id' => $acc_row_id,
        'alocom' => $alocom,
        'welcome_sms' => $welcome,
    ];
}

function identity_welcome_pattern_code() {
    $config = rollcall_config();
    $sms = $config['sms'] ?? [];
    return (string)($sms['welcome_pattern_code'] ?? 'RMej2aBV8v');
}

/** Keep Latin names and file numbers from flipping inside RTL iPhone SMS. */
function identity_sms_ltr_isolate($value, $max_len) {
    $value = trim((string)$value);
    if ($value === '') {
        return '';
    }
    $wrapped = "\u{2066}" . $value . "\u{2069}";
    if (mb_strlen($wrapped, 'UTF-8') <= $max_len) {
        return $wrapped;
    }
    return mb_strlen($value, 'UTF-8') <= $max_len
        ? $value
        : mb_substr($value, 0, $max_len, 'UTF-8');
}

function identity_ensure_welcome_sms_log(PDO $pdo) {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS student_welcome_sms_log (
            user_id INT NOT NULL,
            file_number VARCHAR(20) NOT NULL,
            sent_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
}

function identity_ensure_welcome_sms_pending(PDO $pdo) {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS student_welcome_sms_pending (
            user_id INT NOT NULL,
            file_number VARCHAR(20) NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
}

function identity_queue_welcome_sms(PDO $pdo, $user_id, $code) {
    $user_id = (int) $user_id;
    if ($user_id <= 0) {
        return;
    }
    identity_ensure_welcome_sms_pending($pdo);
    $pdo->prepare('INSERT IGNORE INTO student_welcome_sms_pending (user_id, file_number) VALUES (?, ?)')
        ->execute([$user_id, identity_normalize_code($code)]);
}

function identity_welcome_is_pending(PDO $pdo, $user_id) {
    $user_id = (int) $user_id;
    if ($user_id <= 0) {
        return false;
    }
    identity_ensure_welcome_sms_pending($pdo);
    $st = $pdo->prepare('SELECT 1 FROM student_welcome_sms_pending WHERE user_id = ? LIMIT 1');
    $st->execute([$user_id]);
    return (bool) $st->fetch();
}

function identity_clear_welcome_pending(PDO $pdo, $user_id) {
    $user_id = (int) $user_id;
    if ($user_id <= 0) {
        return;
    }
    identity_ensure_welcome_sms_pending($pdo);
    $pdo->prepare('DELETE FROM student_welcome_sms_pending WHERE user_id = ?')->execute([$user_id]);
}

function identity_should_send_welcome_sms(array $opts, array $panel) {
    if (!empty($opts['welcome_sms'])) {
        return true;
    }
    $source = (string)($opts['source'] ?? '');
    // Educational panel often inserts the user first, then syncs (created=false).
    if (in_array($source, ['panel-create', 'panel-restore', 'panel-import'], true)) {
        return true;
    }
    // Accounting create (and batch) only when the panel account is actually new.
    if (!empty($panel['created']) && in_array($source, ['accounting', 'accounting-batch'], true)) {
        return true;
    }
    $pdo = $GLOBALS['identity_panel_pdo'] ?? null;
    $uid = (int)($panel['id'] ?? 0);
    if ($pdo instanceof PDO && $uid > 0 && identity_welcome_is_pending($pdo, $uid)) {
        return true;
    }
    return false;
}

function identity_maybe_welcome_sms(PDO $pdo, array $opts, array $panel, $code, $full_name, $first, $phone) {
    if (!identity_should_send_welcome_sms($opts, $panel)) {
        return ['sent' => false, 'skipped' => 'not-new'];
    }
    $user_id = (int)($panel['id'] ?? 0);
    if ($user_id <= 0) {
        return ['sent' => false, 'skipped' => 'no-user'];
    }

    identity_ensure_welcome_sms_log($pdo);
    $st = $pdo->prepare('SELECT 1 FROM student_welcome_sms_log WHERE user_id = ? LIMIT 1');
    $st->execute([$user_id]);
    if ($st->fetch()) {
        identity_clear_welcome_pending($pdo, $user_id);
        return ['sent' => false, 'skipped' => 'already'];
    }

    $phone = function_exists('rollcall_normalize_phone') ? rollcall_normalize_phone($phone) : trim((string)$phone);
    if ($phone === '' || $full_name === '') {
        $row = $pdo->prepare('SELECT phone, full_name FROM users WHERE id = ? LIMIT 1');
        $row->execute([$user_id]);
        $user = $row->fetch(PDO::FETCH_ASSOC) ?: [];
        if ($phone === '') {
            $phone = function_exists('rollcall_normalize_phone')
                ? rollcall_normalize_phone($user['phone'] ?? '')
                : trim((string)($user['phone'] ?? ''));
        }
        if ($full_name === '') {
            $full_name = trim((string)($user['full_name'] ?? ''));
        }
    }
    if ($phone === '') {
        identity_queue_welcome_sms($pdo, $user_id, $code);
        identity_log('welcome sms skip no phone code=' . $code);
        if (function_exists('ghazal_error_report')) {
            ghazal_error_report(
                'SMS-NO-PHONE',
                'خوش‌آمدگویی پرونده ' . $code . ' ارسال نشد؛ شماره در پرونده نیست.'
            );
        }
        return ['sent' => false, 'skipped' => 'no-phone'];
    }

    $name = trim((string)$first);
    if ($name === '') {
        list($name) = identity_split_name($full_name);
    }
    if ($name === '') {
        $name = (string)$code;
    }

    $pattern = identity_welcome_pattern_code();
    if ($pattern === '') {
        identity_queue_welcome_sms($pdo, $user_id, $code);
        if (function_exists('ghazal_error_report')) {
            ghazal_error_report('SMS-PATTERN', 'خوش‌آمدگویی پرونده ' . $code . ' ارسال نشد؛ پترن تنظیم نشده.');
        }
        return ['sent' => false, 'skipped' => 'no-pattern'];
    }
    $status = function_exists('rollcall_pattern_status') ? rollcall_pattern_status($pattern) : '';
    if ($status !== '' && $status !== 'active') {
        identity_queue_welcome_sms($pdo, $user_id, $code);
        identity_log('welcome sms pending pattern code=' . $code . ' status=' . $status);
        if (function_exists('ghazal_error_report')) {
            ghazal_error_report(
                'SMS-PATTERN',
                'خوش‌آمدگویی پرونده ' . $code . ' ارسال نشد؛ پترن تأیید نشده.',
                (string)$status
            );
        }
        return ['sent' => false, 'skipped' => 'pending', 'status' => $status];
    }

    if (function_exists('ghazal_error_sms_begin')) {
        ghazal_error_sms_begin('welcome', $full_name !== '' ? $full_name : (string)$code, (string)$code);
    }
    $result = sendFarazSMS($phone, $pattern, [
        'name' => identity_sms_ltr_isolate($name, 38) . ' ',
        'code' => identity_sms_ltr_isolate((string)$code, 12),
    ]);
    if (function_exists('ghazal_error_sms_end')) {
        ghazal_error_sms_end();
    }
    $decoded = $result['response'] ?? null;
    $http = (int)($result['http_code'] ?? 0);
    $api_status = is_array($decoded) ? (string)($decoded['status'] ?? '') : '';
    $ok = $http >= 200 && $http < 300 && ($api_status === '' || $api_status === 'success');
    if (!$ok) {
        $msg = is_array($decoded) ? (string)($decoded['message'] ?? '') : '';
        identity_queue_welcome_sms($pdo, $user_id, $code);
        identity_log('welcome sms fail code=' . $code . ' http=' . $http . ' msg=' . $msg);
        return ['sent' => false, 'skipped' => 'send-fail', 'message' => $msg];
    }

    $pdo->prepare('INSERT IGNORE INTO student_welcome_sms_log (user_id, file_number) VALUES (?, ?)')
        ->execute([$user_id, (string)$code]);
    identity_clear_welcome_pending($pdo, $user_id);
    identity_log('welcome sms sent code=' . $code);
    return ['sent' => true];
}

function identity_backfill(PDO $panel_pdo, $dry_run = false, $limit = 0, $offset = 0) {
    identity_ensure_acc_schema();
    $acc = accounting_pdo();
    $all_panel = $panel_pdo->query("
        SELECT id, username, full_name, phone
        FROM users
        WHERE role = 'student' AND is_deleted = 0
        ORDER BY id
    ")->fetchAll();
    $total_panel = count($all_panel);
    $limit = (int)$limit;
    $offset = (int)$offset;
    if ($limit > 0) {
        $panel_students = array_slice($all_panel, $offset, $limit);
    } else {
        $panel_students = $all_panel;
    }
    $chunk_end = $offset + count($panel_students);
    $last_chunk = ($limit <= 0) || ($chunk_end >= $total_panel);

    $acc_students = $acc->query("SELECT id, firstName, lastName, phone, studentCode, classType FROM students")->fetchAll();

    $results = [
        'panel_to_wp' => 0,
        'acc_linked' => 0,
        'acc_created' => 0,
        'codes' => [],
        'offset' => $offset,
        'limit' => $limit,
        'total_panel' => $total_panel,
        'next_offset' => $last_chunk ? null : $chunk_end,
        'done' => $last_chunk,
    ];
    $pool = [];
    foreach ($acc_students as $row) {
        $row['full'] = trim((string)$row['firstName'] . ' ' . (string)$row['lastName']);
        $row['phone_key'] = preg_replace('/^0+/', '', rollcall_normalize_phone($row['phone'] ?? ''));
        $pool[] = $row;
    }

    $acc_code_by_id = [];
    foreach ($panel_students as $student) {
        $code = identity_normalize_code($student['username']);
        if (!identity_is_file_number($code)) {
            continue;
        }
        $match = accounting_match_student($student, $pool);
        if ($match) {
            $acc_code_by_id[$match['id']] = $code;
            $phone_key = $match['phone_key'] ?? '';
            if ($phone_key !== '') {
                foreach ($acc_students as $row) {
                    $row_key = preg_replace('/^0+/', '', rollcall_normalize_phone($row['phone'] ?? ''));
                    if ($row_key !== '' && $row_key === $phone_key) {
                        $acc_code_by_id[$row['id']] = $code;
                    }
                }
            }
        }
        if (!$dry_run) {
            identity_sync_student($panel_pdo, [
                'source' => 'backfill-panel',
                'code' => $code,
                'full_name' => $student['full_name'],
                'phone' => $student['phone'],
                'password' => identity_default_password($code),
                'reset_password' => false,
                'wp_reset_password' => true,
            ]);
        }
        $results['panel_to_wp']++;
        $results['codes'][$code] = $student['full_name'];
    }

    if (!$last_chunk) {
        if (!$dry_run && $acc_code_by_id) {
            foreach ($acc_code_by_id as $id => $code) {
                $acc->prepare('UPDATE students SET studentCode = ? WHERE id = ?')->execute([$code, $id]);
                $results['acc_linked']++;
            }
        } else {
            $results['acc_linked'] = count($acc_code_by_id);
        }
        return $results;
    }

    foreach ($acc_students as $row) {
        if (!empty($acc_code_by_id[$row['id']])) {
            continue;
        }
        $existing = identity_normalize_code($row['studentCode'] ?? '');
        if (identity_is_file_number($existing)) {
            $acc_code_by_id[$row['id']] = $existing;
            continue;
        }
        $full = trim((string)$row['firstName'] . ' ' . (string)$row['lastName']);
        $from_person = identity_code_from_person($panel_pdo, $full, $row['phone'] ?? '', true);
        if ($from_person !== '') {
            $acc_code_by_id[$row['id']] = $from_person;
            continue;
        }
        if (!$dry_run) {
            $code = identity_next_code($panel_pdo);
            $full = trim((string)$row['firstName'] . ' ' . (string)$row['lastName']);
            identity_sync_student($panel_pdo, [
                'source' => 'backfill-acc',
                'code' => $code,
                'first_name' => $row['firstName'],
                'last_name' => $row['lastName'],
                'full_name' => $full,
                'phone' => $row['phone'],
                'acc_student_id' => $row['id'],
                'password' => identity_default_password($code),
                'reset_password' => true,
                'create_if_missing' => true,
            ]);
            $acc_code_by_id[$row['id']] = $code;
            $results['acc_created']++;
            $results['codes'][$code] = $full;
        } else {
            $results['acc_created']++;
        }
    }

    if (!$dry_run) {
        foreach ($acc_code_by_id as $id => $code) {
            $acc->prepare('UPDATE students SET studentCode = ? WHERE id = ?')->execute([$code, $id]);
            $results['acc_linked']++;
        }
    } else {
        $results['acc_linked'] = count($acc_code_by_id);
    }

    return $results;
}

function identity_repair_acc_codes(PDO $panel_pdo, $dry_run = false) {
    identity_ensure_acc_schema();
    $acc = accounting_pdo();
    $rows = $acc->query("SELECT id, firstName, lastName, phone, studentCode FROM students")->fetchAll();
    $repaired = [];
    $kept = [];
    $unmatched = [];
    foreach ($rows as $row) {
        $current = identity_normalize_code($row['studentCode'] ?? '');
        $full = trim((string)$row['firstName'] . ' ' . (string)$row['lastName']);
        if (identity_is_file_number($current)) {
            $kept[] = ['id' => $row['id'], 'code' => $current, 'name' => $full];
            continue;
        }
        $matched = identity_code_from_person($panel_pdo, $full, $row['phone'] ?? '', true);
        if ($matched !== '' && $matched !== $current) {
            if (!$dry_run) {
                $acc->prepare('UPDATE students SET studentCode = ? WHERE id = ?')->execute([$matched, $row['id']]);
            }
            $repaired[] = [
                'id' => $row['id'],
                'name' => $full,
                'from' => $current,
                'to' => $matched,
            ];
        } elseif (identity_is_file_number($matched ?: $current)) {
            $kept[] = ['id' => $row['id'], 'code' => $matched ?: $current, 'name' => $full];
        } else {
            $unmatched[] = ['id' => $row['id'], 'code' => $current, 'name' => $full];
        }
    }
    return ['repaired' => $repaired, 'kept' => count($kept), 'unmatched' => $unmatched];
}

function identity_lookup_student(PDO $panel_pdo, array $opts) {
    $code = identity_normalize_code($opts['code'] ?? '');
    $full_name = trim((string)($opts['full_name'] ?? ''));
    $first = trim((string)($opts['first_name'] ?? ''));
    $last = trim((string)($opts['last_name'] ?? ''));
    if ($full_name === '') {
        $full_name = trim($first . ' ' . $last);
    }
    $phone = trim((string)($opts['phone'] ?? ''));
    if (!identity_is_file_number($code)) {
        $code = identity_code_from_person($panel_pdo, $full_name, $phone, true);
    }
    if (!identity_is_file_number($code)) {
        return ['ok' => true, 'found' => false, 'code' => ''];
    }
    $stmt = $panel_pdo->prepare("
        SELECT id, username, full_name, phone, is_deleted
        FROM users
        WHERE username = ? AND role = 'student'
        LIMIT 1
    ");
    $stmt->execute([$code]);
    $user = $stmt->fetch();
    $acc = accounting_pdo();
    $acc_row = $acc->prepare('SELECT id, firstName, lastName, phone, studentCode FROM students WHERE studentCode = ?');
    $acc_row->execute([$code]);
    return [
        'ok' => true,
        'found' => (bool)$user,
        'code' => $code,
        'panel' => $user ?: null,
        'accounting' => $acc_row->fetchAll(),
    ];
}

function identity_reset_wp_file_passwords(PDO $panel_pdo) {
    $stmt = $panel_pdo->query("
        SELECT username, full_name, phone
        FROM users
        WHERE role = 'student' AND is_deleted = 0
        ORDER BY id
    ");
    $updated = 0;
    $failed = [];
    foreach ($stmt as $row) {
        $code = identity_normalize_code($row['username']);
        if (!identity_is_file_number($code)) {
            continue;
        }
        $wp = identity_wp_request([
            'code' => $code,
            'full_name' => $row['full_name'],
            'phone' => $row['phone'],
            'password' => identity_default_password($code),
            'reset_password' => 1,
        ]);
        if (!empty($wp['ok'])) {
            $updated++;
        } else {
            $failed[] = ['code' => $code, 'error' => $wp['error'] ?? 'wp fail'];
        }
    }
    return ['updated' => $updated, 'failed' => $failed];
}

function identity_retire_generated_users(PDO $panel_pdo) {
    $stmt = $panel_pdo->query("
        SELECT id, username, full_name
        FROM users
        WHERE role = 'student' AND username REGEXP '^[0-9]{3,8}$'
    ");
    $retired = [];
    foreach ($stmt as $row) {
        if (identity_is_file_number($row['username'])) {
            continue;
        }
        $old_username = 'old' . identity_normalize_code($row['username']);
        $clash = $panel_pdo->prepare('SELECT id FROM users WHERE username = ? AND id != ?');
        $clash->execute([$old_username, $row['id']]);
        if ($clash->fetch()) {
            $old_username .= 'x' . $row['id'];
        }
        $panel_pdo->prepare("UPDATE users SET is_deleted = 1, username = ? WHERE id = ?")->execute([$old_username, $row['id']]);
        $retired[] = [
            'id' => (int)$row['id'],
            'code' => identity_normalize_code($row['username']),
            'name' => $row['full_name'],
        ];
    }
    if ($retired) {
        identity_wp_request([
            'action' => 'retire',
            'codes' => array_column($retired, 'code'),
        ]);
    }
    return $retired;
}

function identity_align_file_numbers(PDO $panel_pdo, $dry_run = false) {
    $repair = identity_repair_acc_codes($panel_pdo, $dry_run);
    $allocated = [];
    if (!$dry_run) {
        $acc = accounting_pdo();
        foreach ($repair['unmatched'] as $row) {
            $full = $row['name'];
            $acc_row = $acc->prepare('SELECT id, firstName, lastName, phone FROM students WHERE id = ?');
            $acc_row->execute([$row['id']]);
            $detail = $acc_row->fetch();
            if (!$detail) {
                continue;
            }
            $sync = identity_sync_student($panel_pdo, [
                'source' => 'backfill-acc',
                'code' => '',
                'first_name' => $detail['firstName'],
                'last_name' => $detail['lastName'],
                'full_name' => $full,
                'phone' => $detail['phone'],
                'acc_student_id' => $detail['id'],
                'create_if_missing' => true,
                'reset_password' => true,
                'wp_reset_password' => true,
            ]);
            $allocated[] = [
                'id' => $detail['id'],
                'name' => $full,
                'code' => $sync['code'] ?? '',
            ];
        }
        $wp = identity_reset_wp_file_passwords($panel_pdo);
        $retired = identity_retire_generated_users($panel_pdo);
        $repair = identity_repair_acc_codes($panel_pdo, false);
    } else {
        $wp = ['updated' => 0, 'failed' => []];
        $retired = [];
    }
    return [
        'repair' => $repair,
        'allocated' => $allocated,
        'wordpress_passwords' => $wp,
        'retired' => $retired,
        'dry_run' => (bool)$dry_run,
        'next_code' => identity_next_code($panel_pdo),
    ];
}

function identity_list_open_classes(PDO $panel_pdo) {
    $queries = [
        "
            SELECT c.id, c.title, c.class_type, c.schedule, c.term_id, t.title AS term_name, t.starts_on
            FROM classes c
            JOIN terms t ON t.id = c.term_id
            WHERE c.is_deleted = 0
              AND IFNULL(c.is_archived, 0) = 0
              AND " . term_sql_open('t') . "
            ORDER BY t.starts_on IS NULL, t.starts_on, c.title
        ",
        "
            SELECT c.id, c.title, c.class_type, c.schedule, c.term_id, t.title AS term_name
            FROM classes c
            JOIN terms t ON t.id = c.term_id
            WHERE c.is_deleted = 0
              AND " . term_sql_open('t') . "
            ORDER BY t.id DESC, c.title
        ",
        "
            SELECT c.id, c.title, c.class_type, c.schedule, c.term_id, t.title AS term_name
            FROM classes c
            JOIN terms t ON t.id = c.term_id
            WHERE c.is_deleted = 0
            ORDER BY t.id DESC, c.title
        ",
    ];
    $stmt = null;
    $last = null;
    foreach ($queries as $sql) {
        try {
            $stmt = $panel_pdo->query($sql);
            break;
        } catch (Exception $e) {
            $last = $e;
        }
    }
    if (!$stmt) {
        identity_log('open classes: ' . ($last ? $last->getMessage() : 'no query'));
        return [];
    }
    $out = [];
    foreach ($stmt as $row) {
        $out[] = [
            'id' => (int)$row['id'],
            'title' => $row['title'],
            'class_type' => $row['class_type'],
            'classType' => accounting_class_type_label($row['class_type']),
            'schedule' => $row['schedule'] ?? '',
            'term_id' => (int)$row['term_id'],
            'term_name' => $row['term_name'],
            'starts_on' => $row['starts_on'] ?? null,
            'upcoming' => !empty($row['starts_on']) && $row['starts_on'] > date('Y-m-d'),
        ];
    }
    return $out;
}

function identity_class_level_key($title) {
    static $ready = false;
    if (!$ready) {
        $ready = true;
        $path = __DIR__ . '/term-plan-tracks.php';
        if (is_file($path)) {
            require_once $path;
        }
    }
    $title = trim((string)$title);
    if ($title === '') {
        return '';
    }
    $key = '';
    if (function_exists('term_plan_detect_level_key')) {
        $key = (string)term_plan_detect_level_key($title);
    }
    if ($key === '' && function_exists('term_plan_detect_legacy_level_key')) {
        $key = (string)term_plan_detect_legacy_level_key($title);
    }
    if ($key === '' && preg_match('/(?<![\p{L}\p{N}])([A-Za-z]{1,5})\s*(\d{1,2})(?![\p{L}\p{N}])/u', $title, $m)) {
        $key = strtoupper($m[1]) . (int)$m[2];
    }
    return $key;
}

function identity_level_rank($key) {
    static $order = [
        'T' => 10, 'WT' => 20, 'B' => 30, 'CB' => 40, 'E' => 50, 'CE' => 60,
        'PI' => 70, 'CPI' => 80, 'I' => 90, 'CI' => 100, 'UI' => 110, 'CUI' => 120,
        'ADV' => 130, 'CADV' => 140,
    ];
    if (!preg_match('/^([A-Z]+)(\d+)$/i', (string)$key, $m)) {
        return 9000;
    }
    $prefix = strtoupper($m[1]);
    $n = (int)$m[2];
    $base = $order[$prefix] ?? 800;
    return $base * 100 + $n;
}

function identity_level_sort($a, $b) {
    $ra = identity_level_rank($a);
    $rb = identity_level_rank($b);
    if ($ra !== $rb) {
        return $ra <=> $rb;
    }
    return strcasecmp((string)$a, (string)$b);
}

function identity_teacher_stats(PDO $panel_pdo, $acc_term_id, $acc_term_name = '') {
    $acc_term_id = trim((string)$acc_term_id);
    $acc_term_name = trim((string)$acc_term_name);
    if ($acc_term_id === '' && $acc_term_name === '') {
        return ['ok' => false, 'error' => 'term required', 'teachers' => []];
    }
    if (!function_exists('term_ensure_schema')) {
        require_once __DIR__ . '/terms.php';
    }
    term_ensure_schema($panel_pdo);
    if (!function_exists('term_english_title')) {
        require_once __DIR__ . '/year-program.php';
    }

    $term = null;
    if ($acc_term_id !== '') {
        try {
            $st = $panel_pdo->prepare("
                SELECT * FROM terms
                WHERE accounting_term_id = ?
                  AND IFNULL(is_deleted, 0) = 0
                ORDER BY id DESC
                LIMIT 1
            ");
            $st->execute([$acc_term_id]);
            $term = $st->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (Exception $e) {
            identity_log('teacher stats term: ' . $e->getMessage());
        }
    }
    if (!$term && $acc_term_name !== '') {
        try {
            $rows = $panel_pdo->query("
                SELECT * FROM terms
                WHERE IFNULL(is_deleted, 0) = 0
                ORDER BY id DESC
            ")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $row) {
                $name = term_acc_name($row['title'] ?? '', $row['year'] ?? '');
                $title = trim((string)($row['title'] ?? ''));
                $english = function_exists('term_english_title') ? term_english_title($title) : $title;
                if ($name === $acc_term_name || $title === $acc_term_name || $english === $acc_term_name) {
                    $term = $row;
                    break;
                }
            }
        } catch (Exception $e) {
            identity_log('teacher stats name: ' . $e->getMessage());
        }
    }
    if (!$term) {
        return ['ok' => false, 'error' => 'panel term not found', 'teachers' => []];
    }

    $stmt = $panel_pdo->prepare("
        SELECT u.id AS teacher_id, u.full_name AS teacher_name,
               c.class_type, c.title, c.schedule
        FROM classes c
        JOIN users u ON u.id = c.teacher_id
        WHERE COALESCE(c.origin_term_id, c.term_id) = ?
          AND IFNULL(c.is_deleted, 0) = 0
        ORDER BY c.title
    ");
    $stmt->execute([(int)$term['id']]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stats = [];
    foreach ($rows as $row) {
        $tid = (int)($row['teacher_id'] ?? 0);
        $name = trim((string)($row['teacher_name'] ?? ''));
        if ($name === '') {
            $name = 'نامشخص';
        }
        $key = $tid > 0 ? ('id:' . $tid) : ('n:' . $name);
        if (!isset($stats[$key])) {
            $stats[$key] = [
                'teacherId' => $tid,
                'teacherName' => $name,
                'online' => 0,
                'offline' => 0,
                'private' => 0,
                'classes' => [],
                'levelSet' => [],
            ];
        }
        $type = strtolower(trim((string)($row['class_type'] ?? '')));
        if ($type === 'online') {
            $stats[$key]['online']++;
        } elseif ($type === 'private') {
            $stats[$key]['private']++;
        } else {
            $stats[$key]['offline']++;
        }
        $title = trim((string)($row['title'] ?? ''));
        $level = identity_class_level_key($title);
        $type_label = function_exists('accounting_class_type_label')
            ? accounting_class_type_label($type)
            : ($type === 'online' ? 'آنلاین' : ($type === 'private' ? 'خصوصی' : 'حضوری'));
        $stats[$key]['classes'][] = [
            'title' => $title,
            'level' => $level,
            'type' => $type === 'online' || $type === 'private' ? $type : 'offline',
            'typeLabel' => $type_label,
            'schedule' => trim((string)($row['schedule'] ?? '')),
        ];
        if ($level !== '') {
            $stats[$key]['levelSet'][$level] = true;
        }
    }

    $teachers = [];
    foreach ($stats as $row) {
        $online_sessions = $row['online'] * 13;
        $offline_sessions = $row['offline'] * 13;
        $private_sessions = $row['private'] * 10;
        $levels = array_keys($row['levelSet'] ?? []);
        usort($levels, 'identity_level_sort');
        $teachers[] = [
            'teacherId' => $row['teacherId'],
            'teacherName' => $row['teacherName'],
            'offlineClasses' => $row['offline'],
            'onlineClasses' => $row['online'],
            'privateClasses' => $row['private'],
            'totalClasses' => $row['offline'] + $row['online'] + $row['private'],
            'offlineSessions' => $offline_sessions,
            'onlineSessions' => $online_sessions,
            'privateSessions' => $private_sessions,
            'totalSessions' => $offline_sessions + $online_sessions + $private_sessions,
            'levels' => $levels,
            'classes' => $row['classes'],
        ];
    }
    usort($teachers, static function ($a, $b) {
        return strcasecmp($a['teacherName'], $b['teacherName']);
    });

    $kind = term_row_is_upcoming($term) ? 'ثبت‌نام' : 'در حال تدریس';
    $range = trim(term_display_date($term['starts_on'] ?? '') . ' تا ' . term_display_date($term['ends_on'] ?? ''));
    $title = term_acc_name($term['title'] ?? '', $term['year'] ?? '');

    return [
        'ok' => true,
        'panelTermId' => (int)$term['id'],
        'termId' => $acc_term_id,
        'termTitle' => $title,
        'termKind' => $kind,
        'termRange' => $range,
        'teachers' => $teachers,
    ];
}

function identity_enroll_student_in_class(PDO $panel_pdo, $code, $class_id) {
    $code = identity_normalize_code($code);
    $class_id = (int)$class_id;
    if (!identity_is_file_number($code) || $class_id <= 0) {
        return ['ok' => false, 'error' => 'invalid'];
    }
    $st = $panel_pdo->prepare("
        SELECT id FROM users
        WHERE username = ? AND role = 'student' AND is_deleted = 0
        LIMIT 1
    ");
    $st->execute([$code]);
    $user_id = (int)$st->fetchColumn();
    if ($user_id <= 0) {
        return ['ok' => false, 'error' => 'panel user missing'];
    }
    $cls = $panel_pdo->prepare("
        SELECT c.id FROM classes c
        JOIN terms t ON t.id = c.term_id
        WHERE c.id = ? AND c.is_deleted = 0
          AND (c.is_archived = 0 OR c.is_archived IS NULL)
          AND " . term_sql_open('t') . "
        LIMIT 1
    ");
    $cls->execute([$class_id]);
    if (!$cls->fetchColumn()) {
        return ['ok' => false, 'error' => 'class missing'];
    }
    $ex = $panel_pdo->prepare("SELECT id, is_deleted FROM class_students WHERE class_id = ? AND user_id = ? LIMIT 1");
    $ex->execute([$class_id, $user_id]);
    $row = $ex->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        if (isset($row['is_deleted']) && (int)$row['is_deleted'] === 1) {
            $panel_pdo->prepare("UPDATE class_students SET is_deleted = 0 WHERE id = ?")->execute([$row['id']]);
        }
    } else {
        $panel_pdo->prepare("INSERT INTO class_students (class_id, user_id) VALUES (?, ?)")->execute([$class_id, $user_id]);
    }
    identity_sync_student_classes($panel_pdo, $user_id);
    $alocom = ['ok' => true, 'skipped' => 'offline'];
    try {
        $clsRow = $panel_pdo->prepare("
            SELECT id, title, class_type, online_link, schedule, term_id, teacher_id
            FROM classes WHERE id = ? LIMIT 1
        ");
        $clsRow->execute([$class_id]);
        $class = $clsRow->fetch(PDO::FETCH_ASSOC);
        $stRow = $panel_pdo->prepare("
            SELECT id, full_name, username, phone FROM users WHERE id = ? LIMIT 1
        ");
        $stRow->execute([$user_id]);
        $student = $stRow->fetch(PDO::FETCH_ASSOC);
        if ($class && $student && (strtolower((string)$class['class_type']) === 'online' || strtolower((string)$class['class_type']) === 'private' || trim((string)$class['online_link']) !== '')) {
            if (!function_exists('alocom_enroll_panel_student_in_event')) {
                require_once __DIR__ . '/alocom-access.php';
            }
            $alocom = alocom_enroll_panel_student_in_event($panel_pdo, $student, $class);
        }
    } catch (Exception $e) {
        identity_log('alocom class enroll code=' . $code . ': ' . $e->getMessage());
        $alocom = ['ok' => false, 'error' => $e->getMessage()];
        if (function_exists('ghazal_error_report') && stripos($e->getMessage(), 'AloCom') === false) {
            ghazal_error_report(
                'ALO-ENROLL',
                'ثبت الوکام پرونده ' . $code . ' در کلاس انجام نشد.',
                ghazal_error_trim($e->getMessage())
            );
        }
    }
    identity_log('enroll code=' . $code . ' class=' . $class_id . ' alocom=' . json_encode($alocom, JSON_UNESCAPED_UNICODE));
    if (empty($alocom['ok']) && empty($alocom['skipped']) && function_exists('ghazal_error_report')) {
        $err = (string)($alocom['error'] ?? 'ناموفق');
        if (stripos($err, 'AloCom') === false) {
            ghazal_error_report(
                'ALO-ENROLL',
                'ثبت الوکام پرونده ' . $code . ' در کلاس انجام نشد.',
                function_exists('ghazal_error_trim') ? ghazal_error_trim($err) : $err
            );
        }
    }
    return ['ok' => true, 'user_id' => $user_id, 'class_id' => $class_id, 'alocom' => $alocom];
}

function identity_delete_panel_student(PDO $panel_pdo, $code, $full_name = '') {
    $code = identity_normalize_code($code);
    $user = null;
    if (identity_is_file_number($code)) {
        $st = $panel_pdo->prepare("
            SELECT id, full_name, username, phone FROM users
            WHERE username = ? AND role = 'student'
            LIMIT 1
        ");
        $st->execute([$code]);
        $user = $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }
    if (!$user) {
        $full_name = trim((string)$full_name);
        if ($full_name !== '') {
            $st = $panel_pdo->prepare("
                SELECT id, full_name, username, phone FROM users
                WHERE role = 'student' AND is_deleted = 0 AND full_name = ?
                LIMIT 3
            ");
            $st->execute([$full_name]);
            $hits = $st->fetchAll(PDO::FETCH_ASSOC);
            if (count($hits) === 1) {
                $user = $hits[0];
            }
        }
    }
    if (!$user) {
        return ['ok' => true, 'deleted' => false, 'reason' => 'not found'];
    }
    $panel_pdo->prepare("UPDATE users SET is_deleted = 1 WHERE id = ?")->execute([(int)$user['id']]);
    try {
        $panel_pdo->prepare("DELETE FROM class_students WHERE user_id = ?")->execute([(int)$user['id']]);
    } catch (Exception $e) {
        $panel_pdo->prepare("UPDATE class_students SET is_deleted = 1 WHERE user_id = ?")->execute([(int)$user['id']]);
    }
    $out_code = identity_normalize_code($user['username'] ?? '') ?: $code;
    identity_log('panel delete code=' . $out_code . ' id=' . $user['id']);
    return ['ok' => true, 'deleted' => true, 'id' => (int)$user['id'], 'code' => $out_code];
}

function identity_find_acc_by_unique_name($full_name) {
    $full_name = trim((string)$full_name);
    if ($full_name === '') {
        return 0;
    }
    $acc = accounting_pdo();
    $rows = $acc->query('SELECT id, firstName, lastName FROM students')->fetchAll(PDO::FETCH_ASSOC);
    $hits = [];
    foreach ($rows as $row) {
        $acc_full = trim((string)$row['firstName'] . ' ' . (string)$row['lastName']);
        if ($acc_full !== '' && accounting_names_match($full_name, $acc_full)) {
            $hits[(int)$row['id']] = true;
        }
    }
    if (count($hits) === 1) {
        $keys = array_keys($hits);
        return (int) $keys[0];
    }
    return 0;
}

function identity_student_open_classes(PDO $panel_pdo, $user_id, $teaching_only = false) {
    require_once __DIR__ . '/terms.php';
    term_ensure_schema($panel_pdo);
    $sql = "
        SELECT c.id, c.title, c.class_type, c.online_link, c.term_id, t.title AS term_title, t.year,
               t.starts_on, t.is_archived, t.accounting_term_id
        FROM classes c
        JOIN class_students cs ON cs.class_id = c.id
        JOIN terms t ON t.id = c.term_id
        WHERE cs.user_id = ?
          AND (cs.is_deleted = 0 OR cs.is_deleted IS NULL)
          AND c.is_deleted = 0
          AND (c.is_archived = 0 OR c.is_archived IS NULL)
          AND " . ($teaching_only ? term_sql_teaching('t') : term_sql_open('t')) . "
        ORDER BY c.id DESC
    ";
    $stmt = $panel_pdo->prepare($sql);
    $stmt->execute([(int)$user_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function identity_student_active_classes(PDO $panel_pdo, $user_id) {
    return identity_student_open_classes($panel_pdo, $user_id, true);
}

function identity_find_acc_student_in_term($code, $acc_term_id) {
    identity_ensure_acc_schema();
    $code = identity_normalize_code($code);
    $acc_term_id = trim((string)$acc_term_id);
    if ($code === '' || $acc_term_id === '') {
        return 0;
    }
    $acc = accounting_pdo();
    $stmt = $acc->prepare('SELECT id FROM students WHERE studentCode = ? AND termId = ? ORDER BY id DESC LIMIT 1');
    $stmt->execute([$code, $acc_term_id]);
    return (int)$stmt->fetchColumn();
}

function identity_sync_student_for_class(PDO $panel_pdo, $user_id, array $class, array $user) {
    $GLOBALS['identity_panel_pdo'] = $panel_pdo;
    require_once __DIR__ . '/terms.php';
    $term = term_load($panel_pdo, class_billing_term_id($panel_pdo, $class));
    if (!$term) {
        return;
    }
    if (term_row_is_teaching($term) || term_row_is_upcoming($term)) {
        $acc_term_id = term_mirror_accounting($panel_pdo, $term, 'active');
    } else {
        $acc_term_id = term_find_accounting_id($term);
        if ($acc_term_id === '') {
            $acc_term_id = term_mirror_accounting($panel_pdo, $term, 'completed');
        }
    }
    if ($acc_term_id === '') {
        return;
    }
    $code = identity_normalize_code($user['username'] ?? '');
    if (!identity_is_file_number($code)) {
        return;
    }
    $acc = accounting_pdo();
    $acc_id = identity_find_acc_student_in_term($code, $acc_term_id);
    if ($acc_id <= 0) {
        $latest = identity_find_acc_student_id($code, $user['phone'] ?? '');
        if ($latest > 0) {
            $owned = $acc->prepare('SELECT id, termId, amountPaid, totalPayable FROM students WHERE id = ?');
            $owned->execute([$latest]);
            $owned_row = $owned->fetch(PDO::FETCH_ASSOC);
            $same_term = $owned_row && (string)$owned_row['termId'] === (string)$acc_term_id;
            $blank = $owned_row && (int)$owned_row['amountPaid'] <= 0 && (int)$owned_row['totalPayable'] <= 0
                && strtolower((string)($owned_row['termId'] ?? '')) === '';
            if ($same_term) {
                $acc_id = $latest;
            } elseif ($owned_row && (string)$owned_row['termId'] === '' && $blank) {
                $acc->prepare('UPDATE students SET termId = ? WHERE id = ?')->execute([$acc_term_id, $latest]);
                $acc_id = $latest;
            }
        }
        if ($acc_id <= 0) {
            list($first, $last) = identity_split_name($user['full_name'] ?? '');
            if ($first === '') {
                $first = $code;
            }
            if ($last === '') {
                $last = '-';
            }
            $phone = trim((string)($user['phone'] ?? ''));
            $acc->prepare("
                INSERT INTO students
                    (firstName, lastName, level, phone, classType, studentCode,
                     totalPayable, amountPaid, debt, status, termId,
                     hasBook, bookPrice, hasInterview, hasDiscount, discountPercent, discountAmount)
                VALUES (?, ?, '', ?, '', ?, 0, 0, 0, 'pending', ?, 0, 0, 0, 0, 0, 0)
            ")->execute([$first, $last, $phone !== '' ? $phone : null, $code, $acc_term_id]);
            $acc_id = (int)$acc->lastInsertId();
        }
    }
    if ($acc_id <= 0) {
        return;
    }
    $row = $acc->prepare('SELECT * FROM students WHERE id = ?');
    $row->execute([$acc_id]);
    $student = $row->fetch(PDO::FETCH_ASSOC);
    if (!$student) {
        return;
    }
    $classType = accounting_class_type_label($class['class_type'] ?? '');
    $level = accounting_match_level($class['title'] ?? '');
    if (!$level) {
        $acc->prepare("UPDATE students SET classType = ?, termId = ? WHERE id = ?")
            ->execute([$classType, $acc_term_id, $acc_id]);
        identity_log('acc class no level match code=' . $code . ' title=' . ($class['title'] ?? ''));
        return;
    }
    list($total, $discount) = identity_rebuild_tuition($student, (int)$level['fee']);
    $paid = (int)$student['amountPaid'];
    $current_level = trim((string)($student['level'] ?? ''));
    $current_total = (int)$student['totalPayable'];
    if ($current_total > 0 && strcasecmp($current_level, (string)$level['name']) === 0) {
        $acc->prepare("UPDATE students SET classType = ?, termId = ? WHERE id = ?")
            ->execute([$classType, $acc_term_id, $acc_id]);
        return;
    }
    $debt = $total - $paid;
    $status = accounting_status_from_amounts($total, $paid);
    $sql = "
        UPDATE students
        SET classType = ?,
            level = ?,
            totalPayable = ?,
            debt = ?,
            status = ?,
            termId = ?
    ";
    $params = [$classType, $level['name'], $total, $debt, $status, $acc_term_id];
    if (!empty($student['hasDiscount'])) {
        $sql .= ", discountAmount = ?";
        $params[] = $discount;
    }
    $sql .= " WHERE id = ?";
    $params[] = $acc_id;
    $acc->prepare($sql)->execute($params);
    identity_log('acc class sync code=' . $code . ' class=' . $class['title'] . ' term=' . $acc_term_id . ' fee=' . $total . ' status=' . $status);
}

function identity_student_has_settled_teaching_class(PDO $panel_pdo, array $student) {
    $classes = identity_student_active_classes($panel_pdo, (int)($student['id'] ?? 0));
    foreach ($classes as $class) {
        if (function_exists('alocom_event_skips_payment') && alocom_event_skips_payment($class)) {
            return true;
        }
        if (function_exists('alocom_is_private_class') && alocom_is_private_class($class)) {
            return true;
        }
        $type = strtolower(trim((string)($class['class_type'] ?? '')));
        $link = trim((string)($class['online_link'] ?? ''));
        if ($link !== '' && in_array($type, ['offline', 'inperson', 'in-person', 'حضوری'], true)) {
            return true;
        }
        $match = function_exists('accounting_match_student_for_access')
            ? 'accounting_match_student_for_access'
            : 'accounting_match_student_for_class';
        $acc = $match($panel_pdo, $student, $class);
        if ($acc && !empty($acc['settled'])) {
            return true;
        }
    }
    return false;
}

function identity_rebuild_tuition(array $student, $level_fee) {
    $base = (int)$level_fee;
    $hasDiscount = !empty($student['hasDiscount']);
    $pct = $hasDiscount ? (int)$student['discountPercent'] : 0;
    $discount = $hasDiscount ? (int)round($base * ($pct / 100)) : 0;
    $book = !empty($student['hasBook']) ? (int)$student['bookPrice'] : 0;
    $interview = !empty($student['hasInterview']) ? 250000 : 0;
    $total = max(0, $base - $discount + $book + $interview);
    return [$total, $discount];
}

/**
 * Keep Acc in sync with panel class assignment.
 * No class → ثبت اولیه (if no payments). Class assigned → type/level/term/fee, بدهکار.
 */
function identity_sync_student_classes(PDO $panel_pdo, $user_id) {
    $user_id = (int)$user_id;
    try {
        if ($user_id <= 0) {
            return;
        }
        accounting_ensure_pending_status();
        $st = $panel_pdo->prepare("
            SELECT id, username, full_name, phone, role, is_deleted
            FROM users WHERE id = ? LIMIT 1
        ");
        $st->execute([$user_id]);
        $user = $st->fetch(PDO::FETCH_ASSOC);
        if (!$user || ($user['role'] ?? '') !== 'student' || (int)$user['is_deleted'] === 1) {
            return;
        }
        $code = identity_normalize_code($user['username']);
        if (!identity_is_file_number($code)) {
            return;
        }
        $classes = identity_student_open_classes($panel_pdo, $user_id, false);
        $acc = accounting_pdo();
        $acc_id = identity_find_acc_student_id($code, $user['phone']);
        if ($acc_id <= 0) {
            $acc_id = identity_find_acc_by_unique_name($user['full_name']);
            if ($acc_id > 0) {
                identity_set_acc_code($acc_id, $code, $user['phone']);
            }
        }
        if ($acc_id <= 0 && !$classes) {
            $acc_id = identity_ensure_acc_student($code, $user['full_name'], $user['phone']);
        } elseif ($acc_id > 0) {
            identity_ensure_acc_student($code, $user['full_name'], $user['phone'], $acc_id);
        }
        if (!$classes) {
            if ($acc_id > 0) {
                $row = $acc->prepare('SELECT * FROM students WHERE id = ?');
                $row->execute([$acc_id]);
                $student = $row->fetch(PDO::FETCH_ASSOC);
                if ($student && (int)$student['amountPaid'] <= 0 && (int)$student['totalPayable'] <= 0) {
                    $acc->prepare("
                        UPDATE students
                        SET classType = '', level = '', totalPayable = 0, debt = 0, status = 'pending'
                        WHERE id = ?
                    ")->execute([$acc_id]);
                    identity_log('acc pending no-class code=' . $code);
                }
            }
            return;
        }

        foreach ($classes as $class) {
            identity_sync_student_for_class($panel_pdo, $user_id, $class, $user);
        }
    } catch (Exception $e) {
        identity_log('class sync user=' . $user_id . ': ' . $e->getMessage());
        if (function_exists('ghazal_error_report')) {
            ghazal_error_report(
                'DB-SAVE',
                'همگام‌سازی کلاس دانش‌آموز در حسابداری ناموفق بود.',
                ghazal_error_trim($e->getMessage())
            );
        }
    }
}
