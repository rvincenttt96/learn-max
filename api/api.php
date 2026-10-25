<?php
/**
 * Ghazal Academy accounting API
 * Maps REST paths like /api/students/12 onto action + id.
 * Live rewrite sends /api/* to public_html/api/api.php (not public_html/api.php).
 */
require_once __DIR__ . '/config.php';
if (is_file(__DIR__ . '/identity-config.php')) {
    require_once __DIR__ . '/identity-config.php';
}
if (is_file(__DIR__ . '/identity-client.php')) {
    require_once __DIR__ . '/identity-client.php';
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    sendJson(['status' => 'ok']);
}

try {
    $pdo = getDbConnection();
} catch (Exception $e) {
    if (function_exists('ghazal_error_report')) {
        ghazal_error_report('DB-CONNECT', 'اتصال دیتابیس حسابداری برقرار نشد.', ghazal_error_trim($e->getMessage()));
    }
    throw $e;
}
if (function_exists('ghazal_ensure_student_code_column')) {
    ghazal_ensure_student_code_column($pdo);
}

function ghazal_acc_status_from_amounts($total, $paid) {
    $total = (int) $total;
    $paid = (int) $paid;
    if ($total <= 0 && $paid <= 0) {
        return 'pending';
    }
    return ($total - $paid) <= 0 ? 'paid' : 'unpaid';
}

function ghazal_acc_respond(array $payload, $status = 200) {
    ignore_user_abort(true);
    if (function_exists('session_write_close')) {
        @session_write_close();
    }
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        $json = '{"error":"encode"}';
        $status = 500;
    }
    if (!headers_sent()) {
        http_response_code((int) $status);
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Length: ' . strlen($json));
        header('Connection: close');
        header('X-Accel-Buffering: no');
    }
    echo $json;
    if (function_exists('apache_setenv')) {
        @apache_setenv('no-gzip', '1');
    }
    @ini_set('zlib.output_compression', '0');
    while (ob_get_level() > 0) {
        @ob_end_flush();
    }
    @flush();
    if (function_exists('fastcgi_finish_request')) {
        @fastcgi_finish_request();
    }
    @set_time_limit(120);
}

function ghazal_acc_identity_changed(array $student, array $input) {
    $oldFirst = trim((string) ($student['firstName'] ?? ''));
    $oldLast = trim((string) ($student['lastName'] ?? ''));
    $oldPhone = trim((string) ($student['phone'] ?? ''));
    $oldCode = function_exists('ghazal_identity_file_number')
        ? ghazal_identity_file_number($student['studentCode'] ?? '')
        : trim((string) ($student['studentCode'] ?? ''));
    $newFirst = array_key_exists('firstName', $input) ? trim((string) $input['firstName']) : $oldFirst;
    $newLast = array_key_exists('lastName', $input) ? trim((string) $input['lastName']) : $oldLast;
    $newPhone = array_key_exists('phone', $input) ? trim((string) $input['phone']) : $oldPhone;
    $newCode = $oldCode;
    if (array_key_exists('studentCode', $input) && function_exists('ghazal_identity_file_number')) {
        $incoming = ghazal_identity_file_number($input['studentCode']);
        if ($incoming !== '') {
            $newCode = $incoming;
        }
    }
    $panelClass = (int) ($input['panelClassId'] ?? $input['panel_class_id'] ?? 0);
    if ($panelClass > 0) {
        return true;
    }
    return $newFirst !== $oldFirst || $newLast !== $oldLast || $newPhone !== $oldPhone || $newCode !== $oldCode;
}

function ghazal_acc_sync_later(PDO $pdo, array $payload, $timeout = 70) {
    if (!function_exists('ghazal_identity_sync')) {
        return null;
    }
    try {
        $synced = ghazal_identity_sync($payload, $timeout);
        if (is_array($synced) && !empty($synced['code']) && !empty($payload['acc_student_id'])) {
            $pdo->prepare('UPDATE students SET studentCode = ? WHERE id = ?')
                ->execute([(string) $synced['code'], $payload['acc_student_id']]);
        }
        return $synced;
    } catch (Exception $e) {
        error_log('ghazal acc background identity: ' . $e->getMessage());
        if (function_exists('ghazal_error_report')) {
            ghazal_error_report('DB-SAVE', 'همگام‌سازی شماره پرونده بعد از ثبت حسابداری ناموفق بود.', ghazal_error_trim($e->getMessage()));
        }
        return null;
    }
}

function ghazal_ensure_pending_status(PDO $pdo) {
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    $col = $pdo->query("SHOW COLUMNS FROM students LIKE 'status'")->fetch();
    if ($col && stripos((string) $col['Type'], 'pending') === false) {
        $pdo->exec("ALTER TABLE students MODIFY COLUMN status ENUM('paid','unpaid','pending') NOT NULL DEFAULT 'unpaid'");
    }
}
ghazal_ensure_pending_status($pdo);
ghazal_ensure_salary_schema($pdo);
ghazal_ensure_extra_course_schema($pdo);

function ghazal_ensure_extra_course_schema(PDO $pdo) {
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    $col = $pdo->query("SHOW COLUMNS FROM students LIKE 'hasExtraCourse'")->fetch();
    if (!$col) {
        $pdo->exec("ALTER TABLE students ADD COLUMN hasExtraCourse TINYINT(1) NOT NULL DEFAULT 0");
        $pdo->exec("ALTER TABLE students ADD COLUMN extraCourseName VARCHAR(191) NULL DEFAULT NULL");
        $pdo->exec("ALTER TABLE students ADD COLUMN extraCoursePrice INT NOT NULL DEFAULT 0");
    }
}

function ghazal_ensure_salary_schema(PDO $pdo) {
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    $kind = $pdo->query("SHOW COLUMNS FROM salaries LIKE 'kind'")->fetch();
    if (!$kind) {
        try {
            $pdo->exec("ALTER TABLE salaries ADD COLUMN kind VARCHAR(16) NOT NULL DEFAULT 'staff'");
            try {
                $pdo->exec("UPDATE salaries SET kind = 'teacher' WHERE kind = 'staff' AND (role IN ('استاد','مدرس','teacher','Teacher') OR role LIKE '%استاد%' OR role LIKE '%مدرس%')");
            } catch (Exception $e) {
                // ignore
            }
        } catch (Exception $e) {
            if (function_exists('ghazal_error_report')) {
                ghazal_error_report('DB-SAVE', 'به‌روزرسانی جدول حقوق حسابداری ناموفق بود.', ghazal_error_trim($e->getMessage()));
            }
        }
    }
    $detail = $pdo->query("SHOW COLUMNS FROM salaries LIKE 'detail'")->fetch();
    if (!$detail) {
        $pdo->exec("ALTER TABLE salaries ADD COLUMN detail TEXT NULL");
    }
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS teacher_pay_rates (
            id INT AUTO_INCREMENT PRIMARY KEY,
            teacherName VARCHAR(191) NOT NULL,
            teacherId INT NULL DEFAULT NULL,
            offlineRate INT NOT NULL DEFAULT 0,
            onlineRate INT NOT NULL DEFAULT 0,
            privateRate INT NOT NULL DEFAULT 0,
            updatedAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_teacher_name (teacherName)
        )
    ");
}

function ghazal_teacher_amount($row) {
    $offline = (int)($row['offlineSessions'] ?? 0) * (int)($row['offlineRate'] ?? 0);
    $online = (int)($row['onlineSessions'] ?? 0) * (int)($row['onlineRate'] ?? 0);
    $private = (int)($row['privateSessions'] ?? 0) * (int)($row['privateRate'] ?? 0);
    return $offline + $online + $private;
}

$action = (string) ($_GET['action'] ?? '');
$id = $_GET['id'] ?? null;
$method = $_SERVER['REQUEST_METHOD'];

$path = trim($action, '/');
$parts = $path === '' ? [] : explode('/', $path);

if (count($parts) >= 2 && ($parts[1] ?? '') === 'batch') {
    $action = $parts[0] . '_batch';
    $id = null;
} elseif (count($parts) === 3 && ($parts[2] ?? '') === 'status') {
    $action = $parts[0] . '_status';
    $id = $parts[1];
} elseif (count($parts) === 3 && ($parts[2] ?? '') === 'receipt') {
    $action = $parts[0] . '_receipt';
    $id = $parts[1];
} elseif (count($parts) === 2) {
    $action = $parts[0];
    $id = $parts[1];
} elseif (count($parts) === 1) {
    $action = $parts[0];
}

$publicActions = ['health', 'login', 'me'];
if (!in_array($action, $publicActions, true)) {
    if (empty($_SESSION['user'])) {
        sendJson(['error' => 'برای این عملیات باید وارد شوید', 'code' => 'unauthorized'], 401);
    }
}
if ($method === 'GET' && function_exists('session_write_close')) {
    @session_write_close();
}

function mapId(array $rows): array
{
    return array_map(static function ($row) {
        if (isset($row['id'])) {
            $row['_id'] = (string) $row['id'];
            $row['id'] = (string) $row['id'];
        }
        foreach (['hasBook', 'hasInterview', 'hasDiscount', 'hasReceipt', 'hasExtraCourse'] as $flag) {
            if (array_key_exists($flag, $row)) {
                $row[$flag] = (bool) ((int) $row[$flag]);
            }
        }
        foreach (['totalPayable', 'amountPaid', 'debt', 'bookPrice', 'discountPercent', 'discountAmount', 'fee', 'amount', 'paidAmount', 'extraCoursePrice'] as $num) {
            if (array_key_exists($num, $row) && $row[$num] !== null) {
                $row[$num] = (int) $row[$num];
            }
        }
        return $row;
    }, $rows);
}

function ghazal_acc_latin_digits($raw)
{
    $map = [
        '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
        '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
        '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
        '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
    ];
    return strtr((string) $raw, $map);
}

function ghazal_acc_english_term_name($name)
{
    $original = trim((string) $name);
    if ($original === '') {
        return '';
    }
    $raw = $original;
    $year = '';
    if (preg_match('/\(([^)]+)\)\s*$/u', $raw, $m)) {
        $year = ghazal_acc_latin_digits(trim($m[1]));
        $raw = trim(preg_replace('/\s*\([^)]+\)\s*$/u', '', $raw));
    }
    $raw = preg_replace('/^ترم\s+/u', '', $raw);
    if ($year === '' && preg_match('/\s+([0-9۰-۹٠-٩]{4})$/u', $raw, $m)) {
        $year = ghazal_acc_latin_digits($m[1]);
        $raw = trim(preg_replace('/\s+[0-9۰-۹٠-٩]{4}$/u', '', $raw));
    }
    $t = str_replace(['ي', 'ك', '‌', 'ـ'], ['ی', 'ک', ' ', ''], $raw);
    $t = ghazal_acc_latin_digits($t);
    $t = preg_replace('/\s+/u', ' ', $t);
    $t = function_exists('mb_strtolower') ? mb_strtolower($t, 'UTF-8') : strtolower($t);
    $t = preg_replace('/\biii\b/', '3', $t);
    $t = preg_replace('/\bii\b/', '2', $t);
    $t = preg_replace('/\bi\b/', '1', $t);
    $t = str_replace(['spring', 'summer', 'autumn', 'fall', 'winter'], ['بهار', 'تابستان', 'پاییز', 'پاییز', 'زمستان'], $t);
    $t = trim(preg_replace('/\s+/u', ' ', $t));
    $map = [
        'بهار 1' => 'Spring 1',
        'بهار 2' => 'Spring 2',
        'بهار 3' => 'Spring 3',
        'تابستان 1' => 'Summer 1',
        'تابستان 2' => 'Summer 2',
        'پاییز 1' => 'Fall 1',
        'پاییز 2' => 'Fall 2',
        'پاییز 3' => 'Fall 3',
        'زمستان 1' => 'Winter 1',
        'زمستان 2' => 'Winter 2',
        'بهار' => 'Spring',
        'تابستان' => 'Summer',
        'پاییز' => 'Fall',
        'زمستان' => 'Winter',
    ];
    $english = $map[$t] ?? '';
    if ($english === '') {
        return $original;
    }
    if ($year !== '' && !preg_match('/\(' . preg_quote($year, '/') . '\)\s*$/u', $english)) {
        return $english . ' (' . $year . ')';
    }
    return $english;
}

function ghazal_acc_term_student_count(PDO $pdo, $id): int
{
    static $cache = [];
    $id = (string) $id;
    if ($id === '') {
        return 0;
    }
    if (!array_key_exists($id, $cache)) {
        $st = $pdo->prepare('SELECT COUNT(*) FROM students WHERE termId = ?');
        $st->execute([$id]);
        $cache[$id] = (int) $st->fetchColumn();
    }
    return $cache[$id];
}

function ghazal_acc_busiest_named(PDO $pdo, array $terms, $pattern): string
{
    $best = '';
    $bestN = -1;
    foreach ($terms as $t) {
        if (!preg_match($pattern, (string) ($t['name'] ?? ''))) {
            continue;
        }
        $id = (string) ($t['id'] ?? '');
        $n = ghazal_acc_term_student_count($pdo, $id);
        if ($n > $bestN) {
            $bestN = $n;
            $best = $id;
        }
    }
    return $best;
}

function ghazal_enrich_terms(array $terms): array
{
    $pdo = getDbConnection();
    $meta = null;
    if (function_exists('ghazal_identity_sync')) {
        $meta = ghazal_identity_sync(['action' => 'term_meta'], 8);
    }
    $teach = '';
    $reg = '';
    if (is_array($meta) && is_array($meta['teaching'] ?? null)) {
        $teach = (string) ($meta['teaching']['accountingTermId'] ?? '');
    }
    if (is_array($meta) && is_array($meta['registration'] ?? null)) {
        $reg = (string) ($meta['registration']['accountingTermId'] ?? '');
    }
    $renames = [];
    foreach ($terms as &$t) {
        $original = (string) ($t['name'] ?? '');
        $english = ghazal_acc_english_term_name($original);
        if ($english !== '' && $english !== $original) {
            $t['name'] = $english;
            $renames[(string) ($t['id'] ?? '')] = $english;
        } elseif ($english !== '') {
            $t['name'] = $english;
        }
    }
    unset($t);
    if ($renames) {
        $stmt = $pdo->prepare('UPDATE terms SET name = ? WHERE id = ?');
        foreach ($renames as $id => $english) {
            if ($id === '') {
                continue;
            }
            $stmt->execute([$english, $id]);
        }
    }
    foreach ($terms as &$t) {
        $id = (string) ($t['id'] ?? '');
        $t['isTeaching'] = ($teach !== '' && $id === $teach);
        $t['isRegistration'] = ($reg !== '' && $id === $reg && !$t['isTeaching']);
        $t['label'] = !empty($t['isTeaching'])
            ? ($t['name'] . ' — در حال برگزاری')
            : (!empty($t['isRegistration']) ? ($t['name'] . ' — در حال ثبت نام') : (string) ($t['name'] ?? ''));
    }
    unset($t);
    usort($terms, static function ($a, $b) {
        $rank = static function ($t) {
            if (!empty($t['isTeaching'])) {
                return 0;
            }
            if (!empty($t['isRegistration'])) {
                return 1;
            }
            return 2;
        };
        $d = $rank($a) <=> $rank($b);
        if ($d !== 0) {
            return $d;
        }
        return ((int) ($b['createdAt'] ?? 0)) <=> ((int) ($a['createdAt'] ?? 0));
    });
    return $terms;
}

function currentUser(): ?array
{
    return $_SESSION['user'] ?? null;
}

function requireRole(array $roles): void
{
    $user = currentUser();
    if (!$user || !in_array($user['role'] ?? '', $roles, true)) {
        sendJson(['error' => 'دسترسی کافی ندارید'], 403);
    }
}

function ghazal_bookstore_acc_config(): array
{
    static $config = null;
    if ($config !== null) {
        return $config;
    }
    $paths = [
        __DIR__ . '/bookstore-config.php',
        dirname(__DIR__) . '/bookstore-config.php',
    ];
    foreach ($paths as $path) {
        if (!is_file($path)) {
            continue;
        }
        $loaded = require $path;
        if (is_array($loaded)) {
            $config = $loaded;
            return $config;
        }
    }
    $config = [];
    return $config;
}

function ghazal_bookstore_shop_url($script)
{
    $script = (string) $script;
    $config = ghazal_bookstore_acc_config();
    if ($script === 'acc-orders.php') {
        $explicit = trim((string) ($config['url'] ?? $config['orders_url'] ?? ''));
        if ($explicit !== '') {
            if (strpos($explicit, 'panel-order.php') !== false) {
                return str_replace('panel-order.php', 'acc-orders.php', $explicit);
            }
            return $explicit;
        }
        return 'https://books.ghazalify.com/api/acc-orders.php';
    }
    $explicit = trim((string) ($config['receipt_url'] ?? ''));
    if ($explicit !== '') {
        return $explicit;
    }
    $orders = ghazal_bookstore_shop_url('acc-orders.php');
    if (strpos($orders, 'acc-orders.php') !== false) {
        return str_replace('acc-orders.php', 'acc-receipt.php', $orders);
    }
    return 'https://books.ghazalify.com/api/acc-receipt.php';
}

function ghazal_bookstore_shop_http($url, array $query = [], $timeout = 20, $body = null)
{
    $token = trim((string) (ghazal_bookstore_acc_config()['token'] ?? ''));
    if ($url === '' || $token === '') {
        return ['ok' => false, 'error' => 'اتصال فروشگاه کتاب هنوز تنظیم نشده است.', 'http' => 0, 'json' => null, 'raw' => '', 'content_type' => ''];
    }
    if ($query) {
        $url .= (strpos($url, '?') === false ? '?' : '&') . http_build_query($query);
    }
    $timeout = (int) $timeout;
    if ($timeout < 3) {
        $timeout = 3;
    }
    $headers = [
        'Accept: application/json, image/*, application/pdf, */*',
        'X-Ghazal-Books: ' . $token,
        'User-Agent: GhazalAccBookStore/1.0',
    ];
    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_CONNECTTIMEOUT => min(6, $timeout),
        CURLOPT_TIMEOUT => $timeout,
    ];
    if (is_array($body)) {
        $payload = json_encode($body, JSON_UNESCAPED_UNICODE);
        if ($payload === false) {
            return ['ok' => false, 'error' => 'ثبت تحویل کتاب انجام نشد.', 'http' => 0, 'json' => null, 'raw' => '', 'content_type' => ''];
        }
        $headers[] = 'Content-Type: application/json; charset=utf-8';
        $opts[CURLOPT_POST] = true;
        $opts[CURLOPT_POSTFIELDS] = $payload;
    }
    $opts[CURLOPT_HTTPHEADER] = $headers;
    $ch = curl_init($url);
    curl_setopt_array($ch, $opts);
    $raw = curl_exec($ch);
    $errno = curl_errno($ch);
    $err = curl_error($ch);
    $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $ctype = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    curl_close($ch);
    if ($raw === false || $errno) {
        return ['ok' => false, 'error' => 'ارتباط با فروشگاه کتاب برقرار نشد.', 'http' => $http, 'json' => null, 'raw' => '', 'content_type' => '', 'detail' => $err];
    }
    $json = null;
    if ($ctype === '' || strpos($ctype, 'json') !== false || (isset($raw[0]) && ($raw[0] === '{' || $raw[0] === '['))) {
        $json = json_decode((string) $raw, true);
    }
    $ok = $http > 0 && $http < 400;
    if (is_array($json) && array_key_exists('ok', $json)) {
        $ok = $ok && !empty($json['ok']);
    }
    return [
        'ok' => $ok,
        'error' => is_array($json) ? (string) ($json['error'] ?? '') : '',
        'http' => $http,
        'json' => is_array($json) ? $json : null,
        'raw' => (string) $raw,
        'content_type' => $ctype,
    ];
}

switch ($action) {
    case 'health':
        sendJson([
            'status' => 'ok',
            'database' => 'mysql',
            'php_version' => PHP_VERSION,
        ]);
        break;

    case 'login':
        if ($method !== 'POST') {
            sendJson(['error' => 'Method Not Allowed'], 405);
        }
        $input = getJsonInput();
        $username = trim((string) ($input['username'] ?? ''));
        $password = (string) ($input['password'] ?? '');
        if ($username === '' || $password === '') {
            sendJson(['success' => false, 'message' => 'نام کاربری و رمز عبور را وارد کنید'], 400);
        }

        $stmt = $pdo->prepare('SELECT id, username, password, role FROM users WHERE username = ? LIMIT 1');
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        $ok = false;
        if ($user) {
            $stored = (string) $user['password'];
            $ok = hash_equals($stored, $password) || password_verify($password, $stored);
        }
        if (!$ok) {
            sendJson(['success' => false, 'message' => 'نام کاربری یا رمز عبور اشتباه است'], 401);
        }
        $safe = [
            'id' => (string) $user['id'],
            'username' => $user['username'],
            'role' => $user['role'],
        ];
        $_SESSION['user'] = $safe;
        sendJson(['success' => true, 'user' => $safe]);
        break;

    case 'logout':
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'] ?? '', $p['secure'], $p['httponly']);
        }
        session_destroy();
        sendJson(['success' => true]);
        break;

    case 'me':
        sendJson(['success' => true, 'user' => currentUser()]);
        break;

    case 'terms':
        if ($method === 'GET') {
            $stmt = $pdo->query('SELECT * FROM terms ORDER BY createdAt DESC, id DESC');
            sendJson(mapId(ghazal_enrich_terms($stmt->fetchAll())));
        } elseif ($method === 'POST') {
            requireRole(['manager', 'reception']);
            $input = getJsonInput();
            $name = trim((string) ($input['name'] ?? ''));
            if ($name === '') {
                sendJson(['error' => 'نام ترم الزامی است'], 400);
            }
            $status = ($input['status'] ?? 'active') === 'completed' ? 'completed' : 'active';
            $createdAt = $input['createdAt'] ?? (int) round(microtime(true) * 1000);
            $stmt = $pdo->prepare('INSERT INTO terms (name, status, createdAt) VALUES (?, ?, ?)');
            $stmt->execute([$name, $status, $createdAt]);
            $insertId = (string) $pdo->lastInsertId();
            sendJson(['_id' => $insertId, 'id' => $insertId, 'name' => $name, 'status' => $status, 'createdAt' => $createdAt]);
        } elseif ($method === 'PATCH') {
            requireRole(['manager', 'reception']);
            if (!$id) {
                sendJson(['error' => 'ID required'], 400);
            }
            $input = getJsonInput();
            $stmt = $pdo->prepare('UPDATE terms SET name = COALESCE(?, name), status = COALESCE(?, status) WHERE id = ?');
            $stmt->execute([$input['name'] ?? null, $input['status'] ?? null, $id]);
            sendJson(['success' => true]);
        } elseif ($method === 'DELETE') {
            requireRole(['manager', 'reception']);
            if (!$id) {
                sendJson(['error' => 'ID required'], 400);
            }
            $sCheck = $pdo->prepare('SELECT COUNT(*) FROM students WHERE termId = ?');
            $sCheck->execute([$id]);
            if ((int) $sCheck->fetchColumn() > 0) {
                sendJson(['error' => 'امکان حذف ترم دارای زبان‌آموز وجود ندارد'], 400);
            }
            $salCheck = $pdo->prepare('SELECT COUNT(*) FROM salaries WHERE termId = ?');
            $salCheck->execute([$id]);
            if ((int) $salCheck->fetchColumn() > 0) {
                sendJson(['error' => 'امکان حذف ترم دارای سوابق حقوقی وجود ندارد'], 400);
            }
            $expCheck = $pdo->prepare('SELECT COUNT(*) FROM expenses WHERE termId = ?');
            $expCheck->execute([$id]);
            if ((int) $expCheck->fetchColumn() > 0) {
                sendJson(['error' => 'امکان حذف ترم دارای هزینه‌ها وجود ندارد'], 400);
            }
            $stmt = $pdo->prepare('DELETE FROM terms WHERE id = ?');
            $stmt->execute([$id]);
            sendJson(['success' => true]);
        } else {
            sendJson(['error' => 'Method Not Allowed'], 405);
        }
        break;

    case 'students':
        if ($method === 'GET') {
            if ($id) {
                $stmt = $pdo->prepare('SELECT * FROM students WHERE id = ?');
                $stmt->execute([$id]);
                $row = $stmt->fetch();
                if (!$row) {
                    sendJson(['error' => 'دانشجو پیدا نشد'], 404);
                }
                $row['hasReceipt'] = !empty($row['receiptUrl']);
                sendJson(mapId([$row])[0]);
            }
            $termId = $_GET['termId'] ?? null;
            $sql = 'SELECT id, termId, firstName, lastName, level, phone, classType, studentCode,
                           totalPayable, amountPaid, debt, status, createdAt,
                           hasBook, bookName, bookPrice, hasInterview, hasDiscount,
                           discountPercent, discountAmount,
                           hasExtraCourse, extraCourseName, extraCoursePrice,
                           CASE WHEN receiptUrl IS NULL OR receiptUrl = "" THEN 0 ELSE 1 END AS hasReceipt
                    FROM students';
            $params = [];
            if ($termId) {
                $sql .= ' WHERE termId = ?';
                $params[] = $termId;
            }
            $sql .= ' ORDER BY id DESC';
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            sendJson(mapId($stmt->fetchAll()));
        } elseif ($method === 'POST') {
            requireRole(['manager', 'reception']);
            $input = getJsonInput();
            $first = trim((string) ($input['firstName'] ?? ''));
            $last = trim((string) ($input['lastName'] ?? ''));
            if ($first === '' || $last === '') {
                sendJson(['error' => 'نام و نام خانوادگی الزامی است'], 400);
            }
            $total = (int) ($input['totalPayable'] ?? 0);
            $paid = (int) ($input['amountPaid'] ?? 0);
            $debt = $total - $paid;
            $status = ghazal_acc_status_from_amounts($total, $paid);
            $studentCode = ghazal_identity_file_number($input['studentCode'] ?? '');
            $stmt = $pdo->prepare('INSERT INTO students
                (firstName, lastName, level, phone, classType, studentCode, totalPayable, amountPaid, debt, status, termId, receiptUrl,
                 hasBook, bookName, bookPrice, hasInterview, hasDiscount, discountPercent, discountAmount,
                 hasExtraCourse, extraCourseName, extraCoursePrice)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([
                $first,
                $last,
                (string) ($input['level'] ?? ''),
                $input['phone'] ?? null,
                $input['classType'] ?? 'حضوری',
                $studentCode !== '' ? $studentCode : null,
                $total,
                $paid,
                $debt,
                $status,
                (string) ($input['termId'] ?? ''),
                $input['receiptUrl'] ?? null,
                !empty($input['hasBook']) ? 1 : 0,
                $input['bookName'] ?? null,
                (int) ($input['bookPrice'] ?? 0),
                !empty($input['hasInterview']) ? 1 : 0,
                !empty($input['hasDiscount']) ? 1 : 0,
                (int) ($input['discountPercent'] ?? 0),
                (int) ($input['discountAmount'] ?? 0),
                !empty($input['hasExtraCourse']) ? 1 : 0,
                $input['extraCourseName'] ?? null,
                (int) ($input['extraCoursePrice'] ?? 0),
            ]);
            $insertId = (string) $pdo->lastInsertId();
            $panelClassId = (int) ($input['panelClassId'] ?? $input['panel_class_id'] ?? 0);
            $panelCreated = false;
            if (function_exists('ghazal_identity_sync')) {
                $fast = ghazal_identity_sync([
                    'source' => 'accounting',
                    'code' => $studentCode,
                    'first_name' => $first,
                    'last_name' => $last,
                    'full_name' => trim($first . ' ' . $last),
                    'phone' => $input['phone'] ?? '',
                    'acc_student_id' => $insertId,
                    'create_if_missing' => 1,
                    'defer_remote' => 1,
                ], 8);
                if (is_array($fast) && !empty($fast['code'])) {
                    $studentCode = (string) $fast['code'];
                    $pdo->prepare('UPDATE students SET studentCode = ? WHERE id = ?')->execute([$studentCode, $insertId]);
                }
                $panelCreated = is_array($fast) && !empty($fast['panel']) && !empty($fast['panel']['created']);
            }
            ghazal_acc_respond([
                '_id' => $insertId,
                'id' => $insertId,
                'firstName' => $first,
                'lastName' => $last,
                'level' => $input['level'] ?? '',
                'phone' => $input['phone'] ?? null,
                'classType' => $input['classType'] ?? 'حضوری',
                'studentCode' => $studentCode,
                'totalPayable' => $total,
                'amountPaid' => $paid,
                'debt' => $debt,
                'status' => $status,
                'termId' => (string) ($input['termId'] ?? ''),
                'hasBook' => !empty($input['hasBook']),
                'bookName' => $input['bookName'] ?? null,
                'bookPrice' => (int) ($input['bookPrice'] ?? 0),
                'hasInterview' => !empty($input['hasInterview']),
                'hasDiscount' => !empty($input['hasDiscount']),
                'discountPercent' => (int) ($input['discountPercent'] ?? 0),
                'discountAmount' => (int) ($input['discountAmount'] ?? 0),
                'hasReceipt' => !empty($input['receiptUrl']),
            ]);
            ghazal_acc_sync_later($pdo, [
                'source' => 'accounting',
                'code' => $studentCode,
                'first_name' => $first,
                'last_name' => $last,
                'full_name' => trim($first . ' ' . $last),
                'phone' => $input['phone'] ?? '',
                'acc_student_id' => $insertId,
                'create_if_missing' => 1,
                'panel_class_id' => $panelClassId,
                'welcome_sms' => $panelCreated ? 1 : 0,
                'wp_reset_password' => $panelCreated ? 1 : 0,
            ]);
            break;
        } elseif ($method === 'PATCH') {
            requireRole(['manager', 'reception']);
            if (!$id) {
                sendJson(['error' => 'ID required'], 400);
            }
            $stmtSelect = $pdo->prepare('SELECT * FROM students WHERE id = ?');
            $stmtSelect->execute([$id]);
            $student = $stmtSelect->fetch();
            if (!$student) {
                sendJson(['error' => 'دانشجو پیدا نشد'], 404);
            }
            $input = getJsonInput();
            $totalPayable = isset($input['totalPayable']) ? (int) $input['totalPayable'] : (int) $student['totalPayable'];
            $amountPaid = isset($input['amountPaid']) ? (int) $input['amountPaid'] : (int) $student['amountPaid'];
            $debt = $totalPayable - $amountPaid;
            $status = ghazal_acc_status_from_amounts($totalPayable, $amountPaid);

            $stmt = $pdo->prepare('UPDATE students SET
                firstName = COALESCE(?, firstName),
                lastName = COALESCE(?, lastName),
                level = COALESCE(?, level),
                phone = COALESCE(?, phone),
                classType = COALESCE(?, classType),
                studentCode = COALESCE(?, studentCode),
                termId = COALESCE(?, termId),
                amountPaid = ?,
                debt = ?,
                status = ?,
                totalPayable = ?,
                hasBook = COALESCE(?, hasBook),
                bookName = COALESCE(?, bookName),
                bookPrice = COALESCE(?, bookPrice),
                hasInterview = COALESCE(?, hasInterview),
                hasDiscount = COALESCE(?, hasDiscount),
                discountPercent = COALESCE(?, discountPercent),
                discountAmount = COALESCE(?, discountAmount),
                hasExtraCourse = COALESCE(?, hasExtraCourse),
                extraCourseName = COALESCE(?, extraCourseName),
                extraCoursePrice = COALESCE(?, extraCoursePrice),
                receiptUrl = COALESCE(?, receiptUrl)
                WHERE id = ?');
            $stmt->execute([
                isset($input['firstName']) ? trim((string) $input['firstName']) : null,
                isset($input['lastName']) ? trim((string) $input['lastName']) : null,
                $input['level'] ?? null,
                $input['phone'] ?? null,
                $input['classType'] ?? null,
                isset($input['studentCode']) ? (ghazal_identity_file_number($input['studentCode']) ?: null) : null,
                isset($input['termId']) ? (string) $input['termId'] : null,
                $amountPaid,
                $debt,
                $status,
                $totalPayable,
                isset($input['hasBook']) ? ($input['hasBook'] ? 1 : 0) : null,
                $input['bookName'] ?? null,
                isset($input['bookPrice']) ? (int) $input['bookPrice'] : null,
                isset($input['hasInterview']) ? ($input['hasInterview'] ? 1 : 0) : null,
                isset($input['hasDiscount']) ? ($input['hasDiscount'] ? 1 : 0) : null,
                isset($input['discountPercent']) ? (int) $input['discountPercent'] : null,
                isset($input['discountAmount']) ? (int) $input['discountAmount'] : null,
                isset($input['hasExtraCourse']) ? ($input['hasExtraCourse'] ? 1 : 0) : null,
                $input['extraCourseName'] ?? null,
                isset($input['extraCoursePrice']) ? (int) $input['extraCoursePrice'] : null,
                $input['receiptUrl'] ?? null,
                $id,
            ]);
            $code = isset($input['studentCode'])
                ? ghazal_identity_file_number($input['studentCode'])
                : ghazal_identity_file_number($student['studentCode'] ?? '');
            if ($code === '') {
                $code = ghazal_identity_file_number($student['studentCode'] ?? '');
            }
            $needsIdentity = ghazal_acc_identity_changed($student, $input) || $code === '';
            $response = ['success' => true, 'debt' => $debt, 'status' => $status, 'totalPayable' => $totalPayable, 'amountPaid' => $amountPaid, 'studentCode' => $code];
            if (!$needsIdentity) {
                sendJson($response);
            }
            ghazal_acc_respond($response);
            ghazal_acc_sync_later($pdo, [
                'source' => 'accounting-edit',
                'code' => $code,
                'first_name' => isset($input['firstName']) ? trim((string) $input['firstName']) : $student['firstName'],
                'last_name' => isset($input['lastName']) ? trim((string) $input['lastName']) : $student['lastName'],
                'phone' => $input['phone'] ?? $student['phone'],
                'acc_student_id' => $id,
            ]);
            break;
        } elseif ($method === 'DELETE') {
            requireRole(['manager', 'reception']);
            if (!$id) {
                sendJson(['error' => 'ID required'], 400);
            }
            $stmtSelect = $pdo->prepare('SELECT id, studentCode, firstName, lastName, phone FROM students WHERE id = ?');
            $stmtSelect->execute([$id]);
            $student = $stmtSelect->fetch();
            if (!$student) {
                sendJson(['error' => 'دانشجو پیدا نشد'], 404);
            }
            $code = '';
            if (function_exists('ghazal_identity_file_number')) {
                $code = ghazal_identity_file_number($student['studentCode'] ?? '');
            } else {
                $code = trim((string) ($student['studentCode'] ?? ''));
            }
            $panelDeleted = false;
            if (function_exists('ghazal_identity_sync')) {
                $del = ghazal_identity_sync([
                    'action' => 'delete',
                    'source' => 'accounting-delete',
                    'code' => $code,
                    'first_name' => $student['firstName'] ?? '',
                    'last_name' => $student['lastName'] ?? '',
                    'full_name' => trim(($student['firstName'] ?? '') . ' ' . ($student['lastName'] ?? '')),
                    'phone' => $student['phone'] ?? '',
                    'acc_student_id' => $id,
                ], 20);
                if (!is_array($del) || empty($del['ok'])) {
                    sendJson(['error' => 'حذف از پنل انجام نشد. دانشجو در حسابداری باقی ماند.'], 502);
                }
                $panelDeleted = !empty($del['deleted']);
            }
            $pdo->prepare('DELETE FROM receipts WHERE studentId = ?')->execute([(string) $id]);
            $stmt = $pdo->prepare('DELETE FROM students WHERE id = ?');
            $stmt->execute([$id]);
            if ($stmt->rowCount() < 1) {
                sendJson(['error' => 'دانشجو پیدا نشد'], 404);
            }
            sendJson(['success' => true, 'panelDeleted' => $panelDeleted]);
        } else {
            sendJson(['error' => 'Method Not Allowed'], 405);
        }
        break;

    case 'panel_classes':
        if ($method !== 'GET') {
            sendJson(['error' => 'Method Not Allowed'], 405);
        }
        $classes = [];
        if (function_exists('ghazal_identity_sync')) {
            $data = ghazal_identity_sync(['action' => 'open_classes'], 4);
            if (is_array($data) && !empty($data['classes']) && is_array($data['classes'])) {
                $classes = $data['classes'];
            }
        }
        sendJson($classes);
        break;

    case 'students_receipt':
        if ($method !== 'GET' || !$id) {
            sendJson(['error' => 'Invalid Request'], 400);
        }
        $stmt = $pdo->prepare('SELECT receiptUrl FROM students WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) {
            sendJson(['error' => 'دانشجو پیدا نشد'], 404);
        }
        sendJson(['receiptUrl' => $row['receiptUrl'] ?: null]);
        break;

    case 'students_batch':
        requireRole(['manager', 'reception']);
        if ($method !== 'POST') {
            sendJson(['error' => 'Method Not Allowed'], 405);
        }
        $students = getJsonInput();
        if (!is_array($students)) {
            sendJson(['error' => 'Expected array'], 400);
        }
        $pdo->beginTransaction();
        try {
            $created = [];
            $stmt = $pdo->prepare('INSERT INTO students (firstName, lastName, level, phone, classType, studentCode, totalPayable, amountPaid, debt, status, termId, receiptUrl, hasBook, bookName, bookPrice, hasInterview, hasDiscount, discountPercent, discountAmount, hasExtraCourse, extraCourseName, extraCoursePrice) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            foreach ($students as $s) {
                $total = (int) ($s['totalPayable'] ?? 0);
                $paid = (int) ($s['amountPaid'] ?? 0);
                $debt = $total - $paid;
                $stmt->execute([
                    $s['firstName'] ?? '',
                    $s['lastName'] ?? '',
                    $s['level'] ?? '',
                    $s['phone'] ?? null,
                    $s['classType'] ?? 'حضوری',
                    trim((string) ($s['studentCode'] ?? '')) ?: null,
                    $total,
                    $paid,
                    $debt,
                    ghazal_acc_status_from_amounts($total, $paid),
                    $s['termId'] ?? '',
                    $s['receiptUrl'] ?? null,
                    !empty($s['hasBook']) ? 1 : 0,
                    $s['bookName'] ?? null,
                    (int) ($s['bookPrice'] ?? 0),
                    !empty($s['hasInterview']) ? 1 : 0,
                    !empty($s['hasDiscount']) ? 1 : 0,
                    (int) ($s['discountPercent'] ?? 0),
                    (int) ($s['discountAmount'] ?? 0),
                    !empty($s['hasExtraCourse']) ? 1 : 0,
                    $s['extraCourseName'] ?? null,
                    (int) ($s['extraCoursePrice'] ?? 0),
                ]);
                $created[] = [
                    'id' => $pdo->lastInsertId(),
                    'studentCode' => trim((string) ($s['studentCode'] ?? '')),
                    'firstName' => $s['firstName'] ?? '',
                    'lastName' => $s['lastName'] ?? '',
                    'phone' => $s['phone'] ?? '',
                ];
            }
            $pdo->commit();
            $createdFlags = [];
            if (function_exists('ghazal_identity_sync')) {
                foreach ($created as $i => $row) {
                    $fast = ghazal_identity_sync([
                        'source' => 'accounting-batch',
                        'code' => $row['studentCode'],
                        'first_name' => $row['firstName'],
                        'last_name' => $row['lastName'],
                        'phone' => $row['phone'],
                        'acc_student_id' => $row['id'],
                        'create_if_missing' => 1,
                        'defer_remote' => 1,
                    ], 8);
                    $createdFlags[$i] = is_array($fast) && !empty($fast['panel']) && !empty($fast['panel']['created']);
                    if (is_array($fast) && !empty($fast['code'])) {
                        $created[$i]['studentCode'] = (string) $fast['code'];
                        $pdo->prepare('UPDATE students SET studentCode = ? WHERE id = ?')->execute([$created[$i]['studentCode'], $row['id']]);
                    }
                }
            }
            ghazal_acc_respond(['success' => true, 'count' => count($students)]);
            foreach ($created as $i => $row) {
                $panelCreated = !empty($createdFlags[$i]);
                ghazal_acc_sync_later($pdo, [
                    'source' => 'accounting-batch',
                    'code' => $row['studentCode'],
                    'first_name' => $row['firstName'],
                    'last_name' => $row['lastName'],
                    'phone' => $row['phone'],
                    'acc_student_id' => $row['id'],
                    'create_if_missing' => 1,
                    'welcome_sms' => $panelCreated ? 1 : 0,
                    'wp_reset_password' => $panelCreated ? 1 : 0,
                ]);
            }
            break;
        } catch (Exception $e) {
            $pdo->rollBack();
            if (function_exists('ghazal_error_report')) {
                ghazal_error_report('DB-SAVE', 'ثبت گروهی دانش‌آموز در حسابداری ناموفق بود.', ghazal_error_trim($e->getMessage()));
            }
            sendJson(['error' => $e->getMessage()], 500);
        }
        break;

    case 'students_status':
        requireRole(['manager', 'reception']);
        if ($method !== 'PATCH' || !$id) {
            sendJson(['error' => 'Invalid Request'], 400);
        }
        $input = getJsonInput();
        $status = ($input['status'] ?? 'paid') === 'unpaid' ? 'unpaid' : 'paid';
        if ($status === 'paid') {
            $stmt = $pdo->prepare('UPDATE students SET status = ?, amountPaid = totalPayable, debt = 0 WHERE id = ?');
            $stmt->execute([$status, $id]);
        } else {
            $stmt = $pdo->prepare('UPDATE students SET status = ? WHERE id = ?');
            $stmt->execute([$status, $id]);
        }
        sendJson(['success' => true]);
        break;

    case 'salaries':
        if ($method === 'GET') {
            $stmt = $pdo->query('SELECT * FROM salaries ORDER BY id DESC');
            sendJson(mapId($stmt->fetchAll()));
        } elseif ($method === 'POST') {
            requireRole(['manager']);
            $input = getJsonInput();
            $kind = (($input['kind'] ?? '') === 'teacher') ? 'teacher' : 'staff';
            $stmt = $pdo->prepare('INSERT INTO salaries (teacherName, role, amount, month, status, termId, receiptUrl, kind, detail) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([
                $input['teacherName'] ?? '',
                $input['role'] ?? ($kind === 'teacher' ? 'استاد' : 'کادر'),
                (int) ($input['amount'] ?? 0),
                $input['month'] ?? '',
                $input['status'] ?? 'unpaid',
                $input['termId'] ?? '',
                $input['receiptUrl'] ?? null,
                $kind,
                isset($input['detail']) ? json_encode($input['detail'], JSON_UNESCAPED_UNICODE) : null,
            ]);
            $insertId = (string) $pdo->lastInsertId();
            $input['_id'] = $insertId;
            $input['id'] = $insertId;
            sendJson($input);
        } elseif ($method === 'PATCH') {
            requireRole(['manager']);
            if (!$id) {
                sendJson(['error' => 'ID required'], 400);
            }
            $input = getJsonInput();
            $kind = null;
            if (isset($input['kind'])) {
                $kind = ($input['kind'] === 'teacher') ? 'teacher' : 'staff';
            }
            $detail = array_key_exists('detail', $input)
                ? json_encode($input['detail'], JSON_UNESCAPED_UNICODE)
                : null;
            $stmt = $pdo->prepare('UPDATE salaries SET teacherName = COALESCE(?, teacherName), role = COALESCE(?, role), amount = COALESCE(?, amount), month = COALESCE(?, month), status = COALESCE(?, status), termId = COALESCE(?, termId), receiptUrl = COALESCE(?, receiptUrl), kind = COALESCE(?, kind), detail = COALESCE(?, detail) WHERE id = ?');
            $stmt->execute([
                $input['teacherName'] ?? null,
                $input['role'] ?? null,
                isset($input['amount']) ? (int) $input['amount'] : null,
                $input['month'] ?? null,
                $input['status'] ?? null,
                $input['termId'] ?? null,
                $input['receiptUrl'] ?? null,
                $kind,
                $detail,
                $id,
            ]);
            sendJson(['success' => true]);
        } elseif ($method === 'DELETE') {
            requireRole(['manager']);
            if (!$id) {
                sendJson(['error' => 'ID required'], 400);
            }
            $stmt = $pdo->prepare('DELETE FROM salaries WHERE id = ?');
            $stmt->execute([$id]);
            sendJson(['success' => true]);
        }
        break;

    case 'salaries_batch':
        requireRole(['manager']);
        if ($method !== 'POST') {
            sendJson(['error' => 'Method Not Allowed'], 405);
        }
        $salariesInput = getJsonInput();
        if (!is_array($salariesInput)) {
            sendJson(['error' => 'Expected array'], 400);
        }
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('INSERT INTO salaries (teacherName, role, amount, month, status, termId, receiptUrl, kind, detail) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
            foreach ($salariesInput as $sal) {
                $kind = (($sal['kind'] ?? '') === 'teacher') ? 'teacher' : 'staff';
                $stmt->execute([
                    $sal['teacherName'] ?? '',
                    $sal['role'] ?? ($kind === 'teacher' ? 'استاد' : 'کادر'),
                    (int) ($sal['amount'] ?? 0),
                    $sal['month'] ?? '',
                    $sal['status'] ?? 'unpaid',
                    $sal['termId'] ?? '',
                    $sal['receiptUrl'] ?? null,
                    $kind,
                    isset($sal['detail']) ? json_encode($sal['detail'], JSON_UNESCAPED_UNICODE) : null,
                ]);
            }
            $pdo->commit();
            sendJson(['success' => true, 'count' => count($salariesInput)]);
        } catch (Exception $e) {
            $pdo->rollBack();
            if (function_exists('ghazal_error_report')) {
                ghazal_error_report('DB-SAVE', 'ثبت گروهی فیش حقوق ناموفق بود.', ghazal_error_trim($e->getMessage()));
            }
            sendJson(['error' => $e->getMessage()], 500);
        }
        break;

    case 'expenses':
        if ($method === 'GET') {
            $stmt = $pdo->query('SELECT * FROM expenses ORDER BY id DESC');
            sendJson(mapId($stmt->fetchAll()));
        } elseif ($method === 'POST') {
            requireRole(['manager']);
            $input = getJsonInput();
            $stmt = $pdo->prepare('INSERT INTO expenses (title, amount, category, date, termId, receiptUrl) VALUES (?, ?, ?, ?, ?, ?)');
            $stmt->execute([
                $input['title'] ?? '',
                (int) ($input['amount'] ?? 0),
                $input['category'] ?? 'عمومی',
                $input['date'] ?? '',
                $input['termId'] ?? '',
                $input['receiptUrl'] ?? null,
            ]);
            $insertId = (string) $pdo->lastInsertId();
            $input['_id'] = $insertId;
            $input['id'] = $insertId;
            sendJson($input);
        } elseif ($method === 'PATCH') {
            requireRole(['manager']);
            if (!$id) {
                sendJson(['error' => 'ID required'], 400);
            }
            $input = getJsonInput();
            $stmt = $pdo->prepare('UPDATE expenses SET title = COALESCE(?, title), amount = COALESCE(?, amount), category = COALESCE(?, category), date = COALESCE(?, date), termId = COALESCE(?, termId), receiptUrl = COALESCE(?, receiptUrl) WHERE id = ?');
            $stmt->execute([
                $input['title'] ?? null,
                isset($input['amount']) ? (int) $input['amount'] : null,
                $input['category'] ?? null,
                $input['date'] ?? null,
                $input['termId'] ?? null,
                $input['receiptUrl'] ?? null,
                $id,
            ]);
            sendJson(['success' => true]);
        } elseif ($method === 'DELETE') {
            requireRole(['manager']);
            if (!$id) {
                sendJson(['error' => 'ID required'], 400);
            }
            $stmt = $pdo->prepare('DELETE FROM expenses WHERE id = ?');
            $stmt->execute([$id]);
            sendJson(['success' => true]);
        }
        break;

    case 'levels':
        if ($method === 'GET') {
            $stmt = $pdo->query('SELECT * FROM levels ORDER BY id ASC');
            sendJson(mapId($stmt->fetchAll()));
        } elseif ($method === 'POST') {
            requireRole(['manager', 'reception']);
            $input = getJsonInput();
            $stmt = $pdo->prepare('INSERT INTO levels (name, fee) VALUES (?, ?)');
            $stmt->execute([$input['name'] ?? '', (int) ($input['fee'] ?? 0)]);
            $insertId = (string) $pdo->lastInsertId();
            $input['_id'] = $insertId;
            $input['id'] = $insertId;
            sendJson($input);
        } elseif ($method === 'DELETE') {
            requireRole(['manager', 'reception']);
            if (!$id) {
                sendJson(['error' => 'ID required'], 400);
            }
            $stmt = $pdo->prepare('DELETE FROM levels WHERE id = ?');
            $stmt->execute([$id]);
            sendJson(['success' => true]);
        }
        break;

    case 'levels_batch':
        requireRole(['manager', 'reception']);
        if ($method !== 'POST') {
            sendJson(['error' => 'Method Not Allowed'], 405);
        }
        $levelsInput = getJsonInput();
        if (!is_array($levelsInput)) {
            sendJson(['error' => 'Expected array'], 400);
        }
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('INSERT INTO levels (name, fee) VALUES (?, ?)');
            foreach ($levelsInput as $lv) {
                $stmt->execute([$lv['name'] ?? '', (int) ($lv['fee'] ?? 0)]);
            }
            $pdo->commit();
            sendJson(['success' => true, 'count' => count($levelsInput)]);
        } catch (Exception $e) {
            $pdo->rollBack();
            if (function_exists('ghazal_error_report')) {
                ghazal_error_report('DB-SAVE', 'ثبت گروهی شهریه سطح ناموفق بود.', ghazal_error_trim($e->getMessage()));
            }
            sendJson(['error' => $e->getMessage()], 500);
        }
        break;

    case 'receipts':
        if ($method === 'GET') {
            $studentId = $_GET['studentId'] ?? $id;
            if ($studentId) {
                $stmt = $pdo->prepare('SELECT * FROM receipts WHERE studentId = ? ORDER BY id DESC');
                $stmt->execute([$studentId]);
            } else {
                $stmt = $pdo->query('SELECT * FROM receipts ORDER BY id DESC');
            }
            sendJson(mapId($stmt->fetchAll()));
        } elseif ($method === 'POST') {
            requireRole(['manager', 'reception']);
            $input = getJsonInput();
            $stmt = $pdo->prepare('INSERT INTO receipts (studentId, termId, paidAmount, date) VALUES (?, ?, ?, ?)');
            $stmt->execute([
                $input['studentId'] ?? '',
                $input['termId'] ?? '',
                (int) ($input['paidAmount'] ?? 0),
                $input['date'] ?? '',
            ]);
            sendJson(['success' => true, 'id' => (string) $pdo->lastInsertId()]);
        }
        break;

    case 'teacher_stats':
        requireRole(['manager']);
        if ($method !== 'GET') {
            sendJson(['error' => 'Method Not Allowed'], 405);
        }
        $termId = trim((string) ($_GET['termId'] ?? $_GET['term_id'] ?? $id ?? ''));
        if ($termId === '') {
            sendJson(['error' => 'termId required'], 400);
        }
        $termName = '';
        try {
            $nm = $pdo->prepare('SELECT name FROM terms WHERE id = ? LIMIT 1');
            $nm->execute([$termId]);
            $termName = trim((string)$nm->fetchColumn());
        } catch (Exception $e) {
            $termName = '';
        }
        $data = function_exists('ghazal_identity_sync')
            ? ghazal_identity_sync(['action' => 'teacher_stats', 'termId' => $termId, 'termName' => $termName], 8)
            : null;
        if (!is_array($data) || empty($data['ok'])) {
            sendJson([
                'ok' => false,
                'error' => is_array($data) ? ($data['error'] ?? 'آمار اساتید در دسترس نیست') : 'آمار اساتید در دسترس نیست',
                'teachers' => [],
            ], 502);
        }
        $rates = $pdo->query('SELECT teacherName, teacherId, offlineRate, onlineRate, privateRate FROM teacher_pay_rates')->fetchAll();
        $byName = [];
        foreach ($rates as $rate) {
            $byName[trim((string)$rate['teacherName'])] = $rate;
        }
        $slipStmt = $pdo->prepare("SELECT id, teacherName, amount, status FROM salaries WHERE kind = 'teacher' AND termId = ? ORDER BY id DESC");
        $slipStmt->execute([$termId]);
        $slips = [];
        foreach ($slipStmt->fetchAll() as $slip) {
            $n = trim((string)$slip['teacherName']);
            if ($n !== '' && !isset($slips[$n])) {
                $slips[$n] = $slip;
            }
        }
        foreach ($data['teachers'] as &$teacher) {
            $key = trim((string)$teacher['teacherName']);
            $hit = $byName[$key] ?? null;
            $teacher['offlineRate'] = $hit ? (int)$hit['offlineRate'] : 0;
            $teacher['onlineRate'] = $hit ? (int)$hit['onlineRate'] : 0;
            $teacher['privateRate'] = $hit ? (int)$hit['privateRate'] : 0;
            $teacher['amount'] = ghazal_teacher_amount($teacher);
            $slip = $slips[$key] ?? null;
            $teacher['salaryId'] = $slip ? (string)$slip['id'] : null;
            $teacher['salaryStatus'] = $slip['status'] ?? '';
            $teacher['salaryAmount'] = $slip ? (int)$slip['amount'] : 0;
        }
        unset($teacher);
        sendJson($data);
        break;

    case 'teacher_rates':
        requireRole(['manager']);
        if ($method === 'GET') {
            $stmt = $pdo->query('SELECT * FROM teacher_pay_rates ORDER BY teacherName');
            sendJson(mapId($stmt->fetchAll()));
        } elseif ($method === 'POST') {
            $input = getJsonInput();
            $rows = isset($input['rates']) && is_array($input['rates']) ? $input['rates'] : [$input];
            $stmt = $pdo->prepare("
                INSERT INTO teacher_pay_rates (teacherName, teacherId, offlineRate, onlineRate, privateRate)
                VALUES (?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    teacherId = VALUES(teacherId),
                    offlineRate = VALUES(offlineRate),
                    onlineRate = VALUES(onlineRate),
                    privateRate = VALUES(privateRate)
            ");
            foreach ($rows as $row) {
                $name = trim((string)($row['teacherName'] ?? ''));
                if ($name === '') {
                    continue;
                }
                $stmt->execute([
                    $name,
                    (int)($row['teacherId'] ?? 0) ?: null,
                    (int)($row['offlineRate'] ?? 0),
                    (int)($row['onlineRate'] ?? 0),
                    (int)($row['privateRate'] ?? 0),
                ]);
            }
            sendJson(['success' => true]);
        } else {
            sendJson(['error' => 'Method Not Allowed'], 405);
        }
        break;

    case 'teacher_payroll':
        requireRole(['manager']);
        if ($method !== 'POST') {
            sendJson(['error' => 'Method Not Allowed'], 405);
        }
        $input = getJsonInput();
        $termId = trim((string)($input['termId'] ?? ''));
        $month = trim((string)($input['month'] ?? ''));
        $rows = is_array($input['teachers'] ?? null) ? $input['teachers'] : [];
        if ($termId === '' || !$rows) {
            sendJson(['error' => 'ترم و پیمنت اساتید لازم است'], 400);
        }
        $termName = '';
        try {
            $nm = $pdo->prepare('SELECT name FROM terms WHERE id = ? LIMIT 1');
            $nm->execute([$termId]);
            $termName = trim((string)$nm->fetchColumn());
        } catch (Exception $e) {
            $termName = '';
        }
        $stats = function_exists('ghazal_identity_sync')
            ? ghazal_identity_sync(['action' => 'teacher_stats', 'termId' => $termId, 'termName' => $termName], 8)
            : null;
        if (!is_array($stats) || empty($stats['ok'])) {
            sendJson(['error' => is_array($stats) ? ($stats['error'] ?? 'آمار اساتید نیامد') : 'آمار اساتید نیامد'], 502);
        }
        $byName = [];
        foreach ($stats['teachers'] as $t) {
            $byName[trim((string)$t['teacherName'])] = $t;
        }
        if ($month === '') {
            $month = trim((string)(($stats['termTitle'] ?? '') . ' ' . ($stats['termKind'] ?? '')));
        }
        $rateStmt = $pdo->prepare("
            INSERT INTO teacher_pay_rates (teacherName, teacherId, offlineRate, onlineRate, privateRate)
            VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                teacherId = VALUES(teacherId),
                offlineRate = VALUES(offlineRate),
                onlineRate = VALUES(onlineRate),
                privateRate = VALUES(privateRate)
        ");
        $find = $pdo->prepare("SELECT id, status FROM salaries WHERE kind = 'teacher' AND termId = ? AND teacherName = ? ORDER BY id DESC LIMIT 1");
        $ins = $pdo->prepare('INSERT INTO salaries (teacherName, role, amount, month, status, termId, receiptUrl, kind, detail) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $upd = $pdo->prepare('UPDATE salaries SET amount = ?, month = ?, role = ?, detail = ?, status = ? WHERE id = ?');
        $created = 0;
        $updated = 0;
        $skipped = [];
        $saved = [];
        foreach ($rows as $row) {
            $name = trim((string)($row['teacherName'] ?? ''));
            if ($name === '' || !isset($byName[$name])) {
                continue;
            }
            $stat = $byName[$name];
            $stat['offlineRate'] = (int)($row['offlineRate'] ?? 0);
            $stat['onlineRate'] = (int)($row['onlineRate'] ?? 0);
            $stat['privateRate'] = (int)($row['privateRate'] ?? 0);
            $amount = ghazal_teacher_amount($stat);
            $rateStmt->execute([
                $name,
                (int)($stat['teacherId'] ?? 0) ?: null,
                $stat['offlineRate'],
                $stat['onlineRate'],
                $stat['privateRate'],
            ]);
            if ($amount <= 0) {
                continue;
            }
            $detail = json_encode([
                'offlineClasses' => $stat['offlineClasses'],
                'onlineClasses' => $stat['onlineClasses'],
                'privateClasses' => $stat['privateClasses'],
                'offlineSessions' => $stat['offlineSessions'],
                'onlineSessions' => $stat['onlineSessions'],
                'privateSessions' => $stat['privateSessions'],
                'offlineRate' => $stat['offlineRate'],
                'onlineRate' => $stat['onlineRate'],
                'privateRate' => $stat['privateRate'],
            ], JSON_UNESCAPED_UNICODE);
            $find->execute([$termId, $name]);
            $exist = $find->fetch(PDO::FETCH_ASSOC);
            if ($exist && ($exist['status'] ?? '') === 'paid') {
                $skipped[] = $name;
                continue;
            }
            if ($exist) {
                $upd->execute([$amount, $month, 'استاد', $detail, $exist['status'] ?: 'unpaid', $exist['id']]);
                $updated++;
                $saved[] = ['id' => (string)$exist['id'], 'teacherName' => $name, 'amount' => $amount, 'status' => $exist['status'] ?: 'unpaid'];
            } else {
                $ins->execute([$name, 'استاد', $amount, $month, 'unpaid', $termId, null, 'teacher', $detail]);
                $created++;
                $saved[] = ['id' => (string)$pdo->lastInsertId(), 'teacherName' => $name, 'amount' => $amount, 'status' => 'unpaid'];
            }
        }
        sendJson([
            'success' => true,
            'created' => $created,
            'updated' => $updated,
            'skippedPaid' => $skipped,
            'salaries' => $saved,
            'termTitle' => $stats['termTitle'] ?? '',
        ]);
        break;

    case 'bookstore_orders':
        requireRole(['manager', 'reception']);
        if ($method === 'POST') {
            $input = getJsonInput();
            $oid = (int) ($id ?? 0);
            if ($oid <= 0) {
                $oid = (int) ($input['id'] ?? ($_GET['id'] ?? 0));
            }
            if ($oid <= 0) {
                sendJson(['ok' => false, 'error' => 'شناسه سفارش خالی است.'], 400);
            }
            if (!array_key_exists('delivered', $input)) {
                sendJson(['ok' => false, 'error' => 'وضعیت تحویل کتاب مشخص نیست.'], 400);
            }
            $delivered = $input['delivered'];
            if (is_bool($delivered)) {
                $flag = $delivered;
            } elseif (is_numeric($delivered)) {
                $flag = (int) $delivered === 1;
            } else {
                $flag = in_array(strtolower(trim((string) $delivered)), ['1', 'true', 'yes', 'on'], true);
            }
            $hit = ghazal_bookstore_shop_http(ghazal_bookstore_shop_url('acc-orders.php'), [], 20, [
                'id' => $oid,
                'delivered' => $flag,
            ]);
            if (!$hit['ok'] || !is_array($hit['json'])) {
                sendJson([
                    'ok' => false,
                    'error' => $hit['error'] !== '' ? $hit['error'] : 'ثبت تحویل کتاب انجام نشد.',
                ], $hit['http'] >= 400 ? $hit['http'] : 502);
            }
            $json = $hit['json'];
            sendJson([
                'ok' => true,
                'order' => is_array($json['order'] ?? null) ? $json['order'] : null,
            ]);
        }
        if ($method !== 'GET') {
            sendJson(['error' => 'Method Not Allowed'], 405);
        }
        $status = trim((string) ($_GET['status'] ?? 'approved,ordered'));
        if ($status === '') {
            $status = 'approved,ordered';
        }
        $q = trim((string) ($_GET['q'] ?? ''));
        $hit = ghazal_bookstore_shop_http(ghazal_bookstore_shop_url('acc-orders.php'), [
            'status' => $status,
            'q' => $q,
        ], 20);
        if (!$hit['ok'] || !is_array($hit['json'])) {
            sendJson([
                'ok' => false,
                'error' => $hit['error'] !== '' ? $hit['error'] : 'خواندن سفارش‌های کتابفروشی انجام نشد.',
                'orders' => [],
                'counts' => ['approved' => 0, 'ordered' => 0],
            ], $hit['http'] >= 400 ? $hit['http'] : 502);
        }
        $json = $hit['json'];
        sendJson([
            'ok' => true,
            'orders' => is_array($json['orders'] ?? null) ? $json['orders'] : [],
            'counts' => is_array($json['counts'] ?? null) ? $json['counts'] : ['approved' => 0, 'ordered' => 0],
        ]);
        break;

    case 'bookstore_receipt':
        requireRole(['manager', 'reception']);
        if ($method !== 'GET') {
            sendJson(['error' => 'Method Not Allowed'], 405);
        }
        $rid = (int) $id;
        if ($rid <= 0) {
            $rid = (int) ($_GET['id'] ?? 0);
        }
        if ($rid <= 0) {
            sendJson(['error' => 'شناسه سفارش خالی است.'], 400);
        }
        $hit = ghazal_bookstore_shop_http(ghazal_bookstore_shop_url('acc-receipt.php'), ['id' => $rid], 20);
        if (!$hit['ok'] || $hit['raw'] === '') {
            $status = $hit['http'] >= 400 ? $hit['http'] : 502;
            sendJson(['ok' => false, 'error' => $hit['error'] !== '' ? $hit['error'] : 'رسید پیدا نشد.'], $status);
        }
        $ctype = trim((string) $hit['content_type']);
        if ($ctype === '' || strpos($ctype, 'application/json') !== false) {
            $ctype = 'application/octet-stream';
        }
        if (function_exists('session_write_close')) {
            @session_write_close();
        }
        if (!headers_sent()) {
            http_response_code(200);
            header('Content-Type: ' . $ctype);
            header('X-Content-Type-Options: nosniff');
            header('Content-Disposition: inline; filename="receipt-' . $rid . '"');
            header('Cache-Control: private, no-store');
            header('Content-Length: ' . strlen($hit['raw']));
        }
        echo $hit['raw'];
        exit;

    default:
        sendJson(['error' => 'Invalid action endpoint', 'action' => $action], 404);
}
