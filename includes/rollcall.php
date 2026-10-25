<?php
/**
 * Shared roll-call save + Mattermost + absence SMS.
 */

if (!function_exists('jdate_format')) {
    require_once __DIR__ . '/jdate.php';
}

function rollcall_config() {
    static $config = null;
    if ($config === null) {
        $path = __DIR__ . '/rollcall-config.php';
        $config = is_file($path) ? require $path : [];
        if (!is_array($config)) {
            $config = [];
        }
    }
    return $config;
}

function rollcall_send_mattermost($message) {
    $config = rollcall_config();
    if ($message === '') {
        return false;
    }

    $bot_token = trim((string)($config['mattermost_bot_token'] ?? ''));
    $channel_id = trim((string)($config['mattermost_channel_id'] ?? ''));
    $base = rtrim((string)($config['mattermost_url'] ?? 'https://co.ghazalify.com'), '/');
    if ($bot_token !== '' && $channel_id !== '') {
        $payload = json_encode([
            'channel_id' => $channel_id,
            'message' => $message,
        ], JSON_UNESCAPED_UNICODE);
        $ch = curl_init($base . '/api/v4/posts');
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $bot_token,
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 12);
        $resp = curl_exec($ch);
        $errno = curl_errno($ch);
        $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $ok = $errno === 0 && $http >= 200 && $http < 300;
        if (!$ok) {
            error_log('mattermost bot fail errno=' . $errno . ' http=' . $http . ' resp=' . substr((string) $resp, 0, 180));
        }
        return $ok;
    }

    $webhook = $config['mattermost_webhook'] ?? '';
    if ($webhook === '') {
        return false;
    }
    $payload = json_encode(['text' => $message], JSON_UNESCAPED_UNICODE);
    $ch = curl_init($webhook);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 12);
    $resp = curl_exec($ch);
    $errno = curl_errno($ch);
    $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $ok = $errno === 0 && $http > 0 && $http < 400;
    if (!$ok) {
        error_log('mattermost fail errno=' . $errno . ' http=' . $http . ' resp=' . substr((string) $resp, 0, 180));
    }
    return $ok;
}

require_once __DIR__ . '/error-log.php';
require_once __DIR__ . '/sms.php';

function rollcall_normalize_status($status, $is_present = null) {
    $raw = trim((string)$status);
    $lower = function_exists('mb_strtolower') ? mb_strtolower($raw, 'UTF-8') : strtolower($raw);
    $present_values = ['present', 'حاضر', '1', 'true', 'yes', 'on'];
    $absent_values = ['absent', 'غایب', '0', 'false', 'no', 'off'];
    if (in_array($lower, $present_values, true)) {
        return 'present';
    }
    if (in_array($lower, $absent_values, true)) {
        return 'absent';
    }
    if ($is_present !== null && $is_present !== '') {
        return ((int)$is_present === 1) ? 'present' : 'absent';
    }
    return 'absent';
}

function rollcall_ensure_sms_log(PDO $pdo) {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS attendance_sms_log (
            class_id INT NOT NULL,
            user_id INT NOT NULL,
            class_date DATE NOT NULL,
            sent_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (class_id, user_id, class_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
}

function rollcall_sms_already_sent(PDO $pdo, $class_id, $user_id, $gregorian_date) {
    $stmt = $pdo->prepare("
        SELECT 1 FROM attendance_sms_log
        WHERE class_id = ? AND user_id = ? AND class_date = ?
        LIMIT 1
    ");
    $stmt->execute([(int)$class_id, (int)$user_id, $gregorian_date]);
    return (bool)$stmt->fetchColumn();
}

function rollcall_mark_sms_sent(PDO $pdo, $class_id, $user_id, $gregorian_date) {
    $stmt = $pdo->prepare("
        INSERT IGNORE INTO attendance_sms_log (class_id, user_id, class_date)
        VALUES (?, ?, ?)
    ");
    $stmt->execute([(int)$class_id, (int)$user_id, $gregorian_date]);
    return $stmt->rowCount() > 0;
}

function rollcall_ensure_missing_alert_log(PDO $pdo) {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS rollcall_missing_alerts (
            class_id INT NOT NULL,
            class_date DATE NOT NULL,
            sent_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (class_id, class_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
}

function rollcall_claim_missing_alert(PDO $pdo, $class_id, $class_date) {
    rollcall_ensure_missing_alert_log($pdo);
    $stmt = $pdo->prepare("
        INSERT IGNORE INTO rollcall_missing_alerts (class_id, class_date)
        VALUES (?, ?)
    ");
    $stmt->execute([(int)$class_id, $class_date]);
    return $stmt->rowCount() > 0;
}

function rollcall_release_missing_alert(PDO $pdo, $class_id, $class_date) {
    $stmt = $pdo->prepare("
        DELETE FROM rollcall_missing_alerts
        WHERE class_id = ? AND class_date = ?
    ");
    $stmt->execute([(int)$class_id, $class_date]);
}

function rollcall_ensure_alocom_attempt_log(PDO $pdo) {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS alocom_rollcall_attempts (
            class_id INT NOT NULL,
            class_date DATE NOT NULL,
            status VARCHAR(32) NOT NULL DEFAULT '',
            sent_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (class_id, class_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
}

function rollcall_claim_alocom_attempt(PDO $pdo, $class_id, $class_date) {
    rollcall_ensure_alocom_attempt_log($pdo);
    $stmt = $pdo->prepare("
        INSERT IGNORE INTO alocom_rollcall_attempts (class_id, class_date, status)
        VALUES (?, ?, 'pending')
    ");
    $stmt->execute([(int)$class_id, $class_date]);
    return $stmt->rowCount() > 0;
}

function rollcall_alocom_attempt_status(PDO $pdo, $class_id, $class_date) {
    rollcall_ensure_alocom_attempt_log($pdo);
    $stmt = $pdo->prepare("
        SELECT status FROM alocom_rollcall_attempts
        WHERE class_id = ? AND class_date = ?
        LIMIT 1
    ");
    $stmt->execute([(int)$class_id, $class_date]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ? (string)($row['status'] ?? '') : '';
}

function rollcall_alocom_attempt_complete(PDO $pdo, $class_id, $class_date) {
    $status = rollcall_alocom_attempt_status($pdo, $class_id, $class_date);
    return in_array($status, ['saved', 'already_saved'], true);
}

function rollcall_mark_alocom_attempt(PDO $pdo, $class_id, $class_date, $status) {
    rollcall_ensure_alocom_attempt_log($pdo);
    $stmt = $pdo->prepare("
        INSERT INTO alocom_rollcall_attempts (class_id, class_date, status)
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE status = VALUES(status)
    ");
    $stmt->execute([(int)$class_id, $class_date, (string)$status]);
}

function rollcall_release_alocom_attempt(PDO $pdo, $class_id, $class_date) {
    $stmt = $pdo->prepare("
        DELETE FROM alocom_rollcall_attempts
        WHERE class_id = ? AND class_date = ?
    ");
    $stmt->execute([(int)$class_id, $class_date]);
}

function rollcall_send_absence_sms($phone, $student_name, $jalali_date) {
    $config = rollcall_config();
    $sms = $config['sms'] ?? [];
    $phone = rollcall_normalize_phone($phone);
    if ($phone === '') {
        if (function_exists('ghazal_error_report')) {
            ghazal_error_report(
                'SMS-NO-PHONE',
                'غیبت — ' . trim((string)$student_name) . ' ارسال نشد؛ شماره در پرونده نیست.'
            );
        }
        return false;
    }
    $pattern = $sms['pattern_code'] ?? 'aX3d0ttYHc';
    $name = rollcall_sms_ltr_isolate($student_name, 38);
    if (function_exists('ghazal_error_sms_begin')) {
        ghazal_error_sms_begin('absence', trim((string)$student_name));
    }
    $result = sendFarazSMS($phone, $pattern, [
        'name' => $name !== '' ? ($name . ' ') : $name,
        'date' => rollcall_sms_ltr_isolate($jalali_date, 16),
    ]);
    if (function_exists('ghazal_error_sms_end')) {
        ghazal_error_sms_end();
    }
    $decoded = $result['response'] ?? null;
    $http = (int)($result['http_code'] ?? 0);
    $api_status = is_array($decoded) ? (string)($decoded['status'] ?? '') : '';
    return $http >= 200 && $http < 300 && ($api_status === '' || $api_status === 'success');
}

function rollcall_normalize_phone($phone) {
    $phone = trim((string)$phone);
    if ($phone === '') {
        return '';
    }
    if (function_exists('convertNumbers')) {
        $phone = convertNumbers($phone);
    } else {
        $phone = strtr($phone, [
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        ]);
    }
    $phone = preg_replace('/\D+/', '', $phone);
    if (strpos($phone, '0098') === 0) {
        $phone = substr($phone, 4);
    }
    if (strpos($phone, '98') === 0 && strlen($phone) >= 12) {
        $phone = substr($phone, 2);
    }
    if (strpos($phone, '9') === 0 && strlen($phone) === 10) {
        $phone = '0' . $phone;
    }
    if (strlen($phone) < 10) {
        return '';
    }
    return $phone;
}

function rollcall_class_info(PDO $pdo, $class_id) {
    $stmt = $pdo->prepare("
        SELECT c.id, c.title, c.schedule, c.schedule_type, c.class_type, c.online_link,
               u.full_name AS teacher_name
        FROM classes c
        LEFT JOIN users u ON c.teacher_id = u.id
        WHERE c.id = ?
    ");
    $stmt->execute([(int)$class_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return [
            'title' => 'Unknown Class',
            'schedule' => 'Not Set',
            'teacher_name' => 'Unknown Teacher',
            'class_type' => '',
            'online_link' => '',
        ];
    }
    if (empty($row['schedule'])) {
        $row['schedule'] = 'Not Set';
    }
    if (empty($row['teacher_name'])) {
        $row['teacher_name'] = 'Unknown Teacher';
    }
    return $row;
}

function rollcall_previous_status(PDO $pdo, $class_id, $gregorian_date) {
    $stmt = $pdo->prepare("
        SELECT user_id, status, is_present
        FROM attendance
        WHERE class_id = ? AND class_date = ?
    ");
    $stmt->execute([(int)$class_id, $gregorian_date]);
    $out = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $status = rollcall_normalize_status($row['status'] ?? '', $row['is_present'] ?? null);
        $out[(int)$row['user_id']] = $status;
    }
    return $out;
}

function rollcall_user_map(PDO $pdo, array $user_ids) {
    $user_ids = array_values(array_filter(array_map('intval', $user_ids)));
    if (!$user_ids) {
        return [];
    }
    $in = implode(',', array_fill(0, count($user_ids), '?'));
    $stmt = $pdo->prepare("SELECT id, full_name, phone FROM users WHERE id IN ($in)");
    $stmt->execute($user_ids);
    $out = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $out[(int)$row['id']] = $row;
    }
    return $out;
}

function rollcall_build_message(array $class_info, $jalali_date, array $absent_names, array $options = []) {
    $is_edit = !empty($options['is_edit']);
    $source = $options['source'] ?? 'teacher';
    $unmatched = $options['unmatched'] ?? [];

    if ($is_edit) {
        $title = "✏️ **Attendance Updated**";
    } elseif ($source === 'alocom') {
        $title = "🟢 **Online Roll Call (AloCom)**";
    } else {
        $title = empty($absent_names) ? "✅ **All Present**" : "🚨 **Absence Report**";
    }

    $msg = $title . "\n\n";
    $msg .= "👨‍🏫 **Instructor:** `" . ($class_info['teacher_name'] ?? '') . "`\n";
    $msg .= "🏫 **Class:** `" . ($class_info['title'] ?? '') . "`\n";
    $msg .= "🕒 **Time:** `" . ($class_info['schedule'] ?? '') . "`\n";
    $msg .= "📅 **Date:** " . $jalali_date . "\n\n";

    if (empty($absent_names)) {
        $msg .= "✅ **همه حاضرند**\n";
        $msg .= "All students are present.";
    } else {
        $msg .= "👥 **Absentee List:**\n";
        foreach ($absent_names as $i => $name) {
            $msg .= ($i + 1) . ". " . $name . "\n";
        }
        $msg .= "\n*Dear Supervisor, please follow up on these absences.*";
    }

    if (!empty($unmatched)) {
        $msg .= "\n\nℹ️ Unmatched AloCom joiners: " . implode(', ', $unmatched);
    }

    return $msg;
}

/**
 * Save attendance rows, notify Mattermost unless unchanged, SMS newly absent students.
 *
 * @param array $attendance [user_id => 'present'|'absent']
 * @param array $options is_edit, source, unmatched, send_sms, send_mattermost
 * @return array{saved:int,sms:int,absent:int,present:int}
 */
function rollcall_save_and_notify(PDO $pdo, $class_id, $gregorian_date, $jalali_date, array $attendance, array $options = []) {
    $class_id = (int)$class_id;
    $send_sms = array_key_exists('send_sms', $options) ? (bool)$options['send_sms'] : true;
    $send_mm = array_key_exists('send_mattermost', $options) ? (bool)$options['send_mattermost'] : true;
    rollcall_ensure_sms_log($pdo);

    $previous = rollcall_previous_status($pdo, $class_id, $gregorian_date);
    $is_edit = array_key_exists('is_edit', $options) ? (bool)$options['is_edit'] : (!empty($previous));
    $normalized_attendance = [];
    foreach ($attendance as $user_id => $status) {
        $user_id = (int)$user_id;
        if (!$user_id) {
            continue;
        }
        $normalized_attendance[$user_id] = rollcall_normalize_status($status);
    }
    $attendance_unchanged = false;
    if (!empty($previous) && $normalized_attendance) {
        $attendance_unchanged = true;
        $ids = array_unique(array_merge(array_keys($previous), array_keys($normalized_attendance)));
        foreach ($ids as $id) {
            if (($previous[$id] ?? null) !== ($normalized_attendance[$id] ?? null)) {
                $attendance_unchanged = false;
                break;
            }
        }
    }

    $own_txn = !$pdo->inTransaction();
    if ($own_txn) {
        $pdo->beginTransaction();
    }

    $stmt = $pdo->prepare("
        INSERT INTO attendance (class_id, user_id, class_date, is_present, status)
        VALUES (?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            is_present = VALUES(is_present),
            status = VALUES(status)
    ");

    $absent_ids = [];
    $present_ids = [];
    $sms_ids = [];
    $saved = 0;

    foreach ($attendance as $user_id => $status) {
        $user_id = (int)$user_id;
        if (!$user_id) {
            continue;
        }
        $status = $normalized_attendance[$user_id] ?? rollcall_normalize_status($status);
        $is_present = ($status === 'present') ? 1 : 0;
        $stmt->execute([$class_id, $user_id, $gregorian_date, $is_present, $status]);
        $saved++;

        if ($status === 'present') {
            $present_ids[] = $user_id;
            continue;
        }

        $absent_ids[] = $user_id;
        if (rollcall_sms_already_sent($pdo, $class_id, $user_id, $gregorian_date)) {
            continue;
        }
        $sms_ids[] = $user_id;
    }

    if ($own_txn && $pdo->inTransaction()) {
        $pdo->commit();
    }

    $class_info = rollcall_class_info($pdo, $class_id);
    $users = rollcall_user_map($pdo, array_merge($absent_ids, $sms_ids));
    $absent_names = [];
    foreach ($absent_ids as $id) {
        if (!empty($users[$id]['full_name'])) {
            $absent_names[] = $users[$id]['full_name'];
        }
    }

    $sms_sent = 0;
    if ($send_sms) {
        foreach ($sms_ids as $id) {
            $u = $users[$id] ?? null;
            if (!$u) {
                continue;
            }
            if (!rollcall_send_absence_sms($u['phone'] ?? '', $u['full_name'] ?? '', $jalali_date)) {
                continue;
            }
            if (rollcall_mark_sms_sent($pdo, $class_id, $id, $gregorian_date)) {
                $sms_sent++;
            }
        }
    }

    if ($send_mm && !$attendance_unchanged) {
        $options['is_edit'] = $is_edit;
        $msg = rollcall_build_message($class_info, $jalali_date, $absent_names, $options);
        rollcall_send_mattermost($msg);
    }

    return [
        'saved' => $saved,
        'sms' => $sms_sent,
        'absent' => count($absent_ids),
        'present' => count($present_ids),
        'is_edit' => $is_edit,
    ];
}

function rollcall_late_pattern_code() {
    $config = rollcall_config();
    $sms = $config['sms'] ?? [];
    return (string)($sms['late_pattern_code'] ?? 'BY86nXCqQY');
}

function rollcall_ensure_late_sms_log(PDO $pdo) {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS attendance_late_sms_log (
            class_id INT NOT NULL,
            user_id INT NOT NULL,
            class_date DATE NOT NULL,
            sent_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (class_id, user_id, class_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
}

function rollcall_pattern_status($code) {
    $code = trim((string)$code);
    if ($code === '') {
        return '';
    }
    $config = rollcall_config();
    $sms = $config['sms'] ?? [];
    $apiKey = $sms['api_key'] ?? 'PpgL4mm0OiqyOoDuh0GT6C9pTNI2elZzzrHxXwHrMxQMbNsrD1';
    $ch = curl_init('https://api.iranpayamak.com/ws/v1/patterns/' . rawurlencode($code));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Api-Key: ' . $apiKey,
        'Accept: application/json',
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 12);
    $raw = curl_exec($ch);
    curl_close($ch);
    $decoded = json_decode((string)$raw, true);
    $row = is_array($decoded) ? ($decoded['data'] ?? []) : [];
    return (string)($row['status'] ?? '');
}

function rollcall_sms_ltr_isolate($value, $max_len = 40) {
    if (function_exists('identity_sms_ltr_isolate')) {
        return identity_sms_ltr_isolate($value, $max_len);
    }
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

function rollcall_send_late_sms($phone, $student_name, $jalali_date) {
    $phone = rollcall_normalize_phone($phone);
    if ($phone === '') {
        if (function_exists('ghazal_error_report')) {
            ghazal_error_report(
                'SMS-NO-PHONE',
                'تأخیر — ' . trim((string)$student_name) . ' ارسال نشد؛ شماره در پرونده نیست.'
            );
        }
        return ['ok' => false, 'message' => 'شماره تماس این دانش‌آموز ثبت نشده است.'];
    }
    $pattern = rollcall_late_pattern_code();
    $status = strtolower(trim(rollcall_pattern_status($pattern)));
    if ($status !== '' && $status !== 'active') {
        if (function_exists('ghazal_error_report')) {
            ghazal_error_report(
                'SMS-PATTERN',
                'تأخیر — ' . trim((string)$student_name) . ' ارسال نشد؛ پترن تأیید نشده.',
                $status
            );
        }
        return ['ok' => false, 'message' => 'پترن پیامک تاخیر هنوز تأیید نشده است. کمی بعد دوباره تلاش کنید.'];
    }
    if (function_exists('ghazal_error_sms_begin')) {
        ghazal_error_sms_begin('late', trim((string)$student_name));
    }
    $result = sendFarazSMS($phone, $pattern, [
        'name' => rollcall_sms_ltr_isolate($student_name, 38) . ' ',
        'date' => rollcall_sms_ltr_isolate($jalali_date, 16),
    ]);
    if (function_exists('ghazal_error_sms_end')) {
        ghazal_error_sms_end();
    }
    $decoded = $result['response'] ?? null;
    $http = (int)($result['http_code'] ?? 0);
    $status = is_array($decoded) ? (string)($decoded['status'] ?? '') : '';
    $ok = $http >= 200 && $http < 300 && ($status === '' || $status === 'success');
    if ($ok) {
        return ['ok' => true, 'message' => 'پیامک تاخیر ارسال شد.'];
    }
    $msg = is_array($decoded) ? (string)($decoded['message'] ?? '') : '';
    if ($msg === '') {
        $msg = 'ارسال پیامک ناموفق بود.';
    }
    return ['ok' => false, 'message' => $msg];
}

function rollcall_notify_late(PDO $pdo, $class_id, $teacher_id, $user_id, $jalali_date) {
    $class_id = (int)$class_id;
    $teacher_id = (int)$teacher_id;
    $user_id = (int)$user_id;
    if (!$class_id || !$teacher_id || !$user_id) {
        return ['ok' => false, 'message' => 'داده نامعتبر است.'];
    }

    if ($teacher_id > 0) {
        $stmt = $pdo->prepare("
            SELECT u.id, u.full_name, u.phone
            FROM users u
            JOIN class_students cs ON cs.user_id = u.id
            JOIN classes c ON c.id = cs.class_id
            WHERE c.id = ? AND c.teacher_id = ? AND u.id = ?
              AND u.role = 'student' AND u.is_deleted = 0
            LIMIT 1
        ");
        $stmt->execute([$class_id, $teacher_id, $user_id]);
    } else {
        $stmt = $pdo->prepare("
            SELECT u.id, u.full_name, u.phone
            FROM users u
            JOIN class_students cs ON cs.user_id = u.id
            JOIN classes c ON c.id = cs.class_id
            WHERE c.id = ? AND u.id = ?
              AND u.role = 'student' AND u.is_deleted = 0
            LIMIT 1
        ");
        $stmt->execute([$class_id, $user_id]);
    }
    $student = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$student) {
        return ['ok' => false, 'message' => 'دانش‌آموز در این کلاس یافت نشد.'];
    }

    $digit_map = [
        '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
        '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
        '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
        '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
    ];
    $jalali_date = strtr(trim((string)$jalali_date), $digit_map);
    $gregorian_date = function_exists('jdate_to_gregorian') ? jdate_to_gregorian($jalali_date) : false;
    if (!$gregorian_date) {
        $gregorian_date = date('Y-m-d');
    }

    rollcall_ensure_late_sms_log($pdo);
    $claim = $pdo->prepare("
        INSERT IGNORE INTO attendance_late_sms_log (class_id, user_id, class_date)
        VALUES (?, ?, ?)
    ");
    $claim->execute([$class_id, $user_id, $gregorian_date]);
    if ($claim->rowCount() === 0) {
        return ['ok' => false, 'message' => 'برای این دانش‌آموز امروز پیامک تاخیر قبلاً ارسال شده است.'];
    }

    $sent = rollcall_send_late_sms($student['phone'] ?? '', $student['full_name'], $jalali_date);
    if (empty($sent['ok'])) {
        $pdo->prepare("DELETE FROM attendance_late_sms_log WHERE class_id = ? AND user_id = ? AND class_date = ?")
            ->execute([$class_id, $user_id, $gregorian_date]);
        return $sent;
    }

    $mark = $pdo->prepare("
        INSERT INTO attendance (class_id, user_id, class_date, is_present, status)
        VALUES (?, ?, ?, 1, 'present')
        ON DUPLICATE KEY UPDATE
            is_present = 1,
            status = 'present'
    ");
    $mark->execute([$class_id, $user_id, $gregorian_date]);

    return ['ok' => true, 'message' => 'پیامک تاخیر برای ' . $student['full_name'] . ' ارسال شد.'];
}

function rollcall_ensure_reason_column(PDO $pdo) {
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    try {
        $col = $pdo->query("SHOW COLUMNS FROM attendance LIKE 'reason'")->fetch();
        if (!$col) {
            $pdo->exec("ALTER TABLE attendance ADD COLUMN reason TEXT NULL");
        }
    } catch (Exception $e) {
        error_log('rollcall reason column: ' . $e->getMessage());
    }
}

function rollcall_session_stats(PDO $pdo, $class_id, $gregorian_date) {
    $stmt = $pdo->prepare("
        SELECT
            SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) AS present_count,
            COUNT(*) AS total_count
        FROM attendance
        WHERE class_id = ? AND class_date = ?
    ");
    $stmt->execute([(int)$class_id, $gregorian_date]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    $present = (int)($row['present_count'] ?? 0);
    $total = (int)($row['total_count'] ?? 0);
    $status_class = 'empty';
    if ($total > 0) {
        if ($present === $total) {
            $status_class = 'present';
        } elseif ($present === 0) {
            $status_class = 'absent';
        } else {
            $status_class = 'partial';
        }
    }
    return [
        'present' => $present,
        'total' => $total,
        'status_class' => $status_class,
    ];
}

function rollcall_student_in_class(PDO $pdo, $class_id, $user_id) {
    $stmt = $pdo->prepare("
        SELECT u.id, u.full_name, u.phone, u.username
        FROM users u
        JOIN class_students cs ON cs.user_id = u.id
        WHERE cs.class_id = ? AND u.id = ? AND u.role = 'student' AND u.is_deleted = 0
        LIMIT 1
    ");
    $stmt->execute([(int)$class_id, (int)$user_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function rollcall_family_name($full_name) {
    $full_name = trim(preg_replace('/\s+/u', ' ', (string)$full_name));
    if ($full_name === '') {
        return '';
    }
    if (function_exists('identity_split_name')) {
        list($first, $last) = identity_split_name($full_name);
    } elseif (function_exists('students_info_split_name')) {
        list($first, $last) = students_info_split_name($full_name);
    } else {
        $parts = preg_split('/\s+/u', $full_name);
        $first = array_shift($parts);
        $last = implode(' ', $parts);
    }
    $last = trim((string)$last);
    return $last !== '' ? $last : trim((string)$first);
}

function rollcall_clear_absence_sms_log(PDO $pdo, $class_id, $user_id, $gregorian_date) {
    try {
        $pdo->prepare("
            DELETE FROM attendance_sms_log
            WHERE class_id = ? AND user_id = ? AND class_date = ?
        ")->execute([(int)$class_id, (int)$user_id, $gregorian_date]);
    } catch (Exception $e) {
        error_log('clear absence sms log: ' . $e->getMessage());
    }
}

/**
 * Supervisor single-student status edit (present <-> absent).
 *
 * @return array{ok:bool,success:bool,message:string,status:string,reason:string,present:int,total:int,status_class:string,sms:bool}
 */
function rollcall_supervisor_set_status(PDO $pdo, $class_id, $user_id, $gregorian_date, $status, $reason = null, array $options = []) {
    $class_id = (int)$class_id;
    $user_id = (int)$user_id;
    $gregorian_date = trim((string)$gregorian_date);
    $send_sms = array_key_exists('send_sms', $options) ? (bool)$options['send_sms'] : true;
    $status = rollcall_normalize_status($status);

    if (!$class_id || !$user_id || $gregorian_date === '' || $gregorian_date === '0000-00-00') {
        return ['ok' => false, 'success' => false, 'message' => 'داده نامعتبر است.'];
    }

    $student = rollcall_student_in_class($pdo, $class_id, $user_id);
    if (!$student) {
        return ['ok' => false, 'success' => false, 'message' => 'دانش‌آموز در این کلاس یافت نشد.'];
    }

    rollcall_ensure_reason_column($pdo);
    rollcall_ensure_sms_log($pdo);

    $previous = rollcall_previous_status($pdo, $class_id, $gregorian_date);
    $was = $previous[$user_id] ?? null;
    $is_present = ($status === 'present') ? 1 : 0;
    $reason_value = null;
    if ($status === 'absent') {
        $reason_text = trim((string)($reason ?? ''));
        $reason_value = $reason_text !== '' ? $reason_text : null;
        if ($reason_value === null && $was === 'absent') {
            $keep = $pdo->prepare("
                SELECT reason FROM attendance
                WHERE class_id = ? AND user_id = ? AND class_date = ?
                LIMIT 1
            ");
            $keep->execute([$class_id, $user_id, $gregorian_date]);
            $existing_reason = trim((string)$keep->fetchColumn());
            $reason_value = $existing_reason !== '' ? $existing_reason : null;
        }
    }

    $stmt = $pdo->prepare("
        INSERT INTO attendance (class_id, user_id, class_date, is_present, status, reason)
        VALUES (?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            is_present = VALUES(is_present),
            status = VALUES(status),
            reason = VALUES(reason),
            updated_at = CURRENT_TIMESTAMP
    ");
    $stmt->execute([$class_id, $user_id, $gregorian_date, $is_present, $status, $reason_value]);

    $sms_sent = false;
    $sms_message = '';
    if ($status === 'present') {
        rollcall_clear_absence_sms_log($pdo, $class_id, $user_id, $gregorian_date);
        $message = 'وضعیت به حاضر تغییر کرد.';
    } else {
        $message = 'وضعیت به غایب تغییر کرد.';
        if ($send_sms && $was !== 'absent' && !rollcall_sms_already_sent($pdo, $class_id, $user_id, $gregorian_date)) {
            $timestamp = strtotime($gregorian_date);
            $jalali_date = ($timestamp && function_exists('jdate'))
                ? jdate('Y/m/d', $timestamp)
                : $gregorian_date;
            if (rollcall_send_absence_sms($student['phone'] ?? '', $student['full_name'] ?? '', $jalali_date)) {
                rollcall_mark_sms_sent($pdo, $class_id, $user_id, $gregorian_date);
                $sms_sent = true;
                $sms_message = ' پیامک غیبت ارسال شد.';
            } elseif (trim((string)($student['phone'] ?? '')) === '') {
                $sms_message = ' شماره تماس برای پیامک غیبت ثبت نشده است.';
            }
        }
        $message .= $sms_message;
    }

    $stats = rollcall_session_stats($pdo, $class_id, $gregorian_date);
    return [
        'ok' => true,
        'success' => true,
        'message' => $message,
        'status' => $status,
        'reason' => (string)($reason_value ?? ''),
        'present' => $stats['present'],
        'total' => $stats['total'],
        'status_class' => $stats['status_class'],
        'sms' => $sms_sent,
        'student_name' => (string)($student['full_name'] ?? ''),
    ];
}

function rollcall_supervisor_save_absence_reason(PDO $pdo, $class_id, $user_id, $gregorian_date, $reason, array $options = []) {
    $reason = trim((string)$reason);
    if ($reason === '') {
        return ['ok' => false, 'success' => false, 'message' => 'علت غیبت را وارد کنید.'];
    }
    $send_sms = array_key_exists('send_sms', $options) ? (bool)$options['send_sms'] : true;
    $result = rollcall_supervisor_set_status($pdo, $class_id, $user_id, $gregorian_date, 'absent', $reason, [
        'send_sms' => $send_sms,
    ]);
    if (!empty($result['ok'])) {
        $result['message'] = !empty($result['sms'])
            ? 'علت غیبت ثبت و پیامک ارسال شد.'
            : 'علت غیبت ثبت شد.';
    }
    return $result;
}

function rollcall_study_plan_pattern_code() {
    $config = rollcall_config();
    $sms = $config['sms'] ?? [];
    return (string)($sms['study_plan_pattern_code'] ?? 'zy6PWPVSVS');
}

function rollcall_ensure_study_plan_log(PDO $pdo) {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS study_plan_sms_log (
            id INT NOT NULL AUTO_INCREMENT,
            user_id INT NOT NULL,
            sent_by INT NOT NULL DEFAULT 0,
            family VARCHAR(80) NOT NULL DEFAULT '',
            phone VARCHAR(20) NOT NULL DEFAULT '',
            sent_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
}

function rollcall_study_plan_last_sent(PDO $pdo, array $user_ids) {
    $user_ids = array_values(array_filter(array_map('intval', $user_ids)));
    if (!$user_ids) {
        return [];
    }
    rollcall_ensure_study_plan_log($pdo);
    $in = implode(',', array_fill(0, count($user_ids), '?'));
    $stmt = $pdo->prepare("
        SELECT user_id, MAX(sent_at) AS sent_at
        FROM study_plan_sms_log
        WHERE user_id IN ($in)
        GROUP BY user_id
    ");
    $stmt->execute($user_ids);
    $out = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $out[(int)$row['user_id']] = (string)$row['sent_at'];
    }
    return $out;
}

function rollcall_send_study_plan_sms($phone, $family) {
    $phone = rollcall_normalize_phone($phone);
    $family = trim((string)$family);
    if ($phone === '') {
        if (function_exists('ghazal_error_report')) {
            ghazal_error_report(
                'SMS-NO-PHONE',
                'برنامه مطالعاتی — ' . $family . ' ارسال نشد؛ شماره در پرونده نیست.'
            );
        }
        return ['ok' => false, 'message' => 'شماره تماس این دانش‌آموز ثبت نشده است.'];
    }
    if ($family === '') {
        return ['ok' => false, 'message' => 'فامیل دانش‌آموز برای پیامک مشخص نیست.'];
    }
    $pattern = rollcall_study_plan_pattern_code();
    if ($pattern === '') {
        return ['ok' => false, 'message' => 'پترن پیامک برنامه مطالعاتی تنظیم نشده است.'];
    }
    $status = strtolower(trim(rollcall_pattern_status($pattern)));
    if ($status !== '' && $status !== 'active') {
        if (function_exists('ghazal_error_report')) {
            ghazal_error_report(
                'SMS-PATTERN',
                'برنامه مطالعاتی — ' . $family . ' ارسال نشد؛ پترن تأیید نشده.',
                $status
            );
        }
        return ['ok' => false, 'message' => 'پترن پیامک برنامه مطالعاتی هنوز تأیید نشده است. کمی بعد دوباره تلاش کنید.'];
    }
    if (function_exists('ghazal_error_sms_begin')) {
        ghazal_error_sms_begin('study-plan', $family);
    }
    $result = sendFarazSMS($phone, $pattern, [
        'family' => rollcall_sms_ltr_isolate($family, 38),
    ]);
    if (function_exists('ghazal_error_sms_end')) {
        ghazal_error_sms_end();
    }
    $decoded = $result['response'] ?? null;
    $http = (int)($result['http_code'] ?? 0);
    $api_status = is_array($decoded) ? (string)($decoded['status'] ?? '') : '';
    $ok = $http >= 200 && $http < 300 && ($api_status === '' || $api_status === 'success');
    if ($ok) {
        return ['ok' => true, 'message' => 'پیامک برنامه مطالعاتی ارسال شد.'];
    }
    $msg = is_array($decoded) ? (string)($decoded['message'] ?? '') : '';
    if ($msg === '') {
        $msg = 'ارسال پیامک ناموفق بود.';
    }
    return ['ok' => false, 'message' => $msg];
}

function rollcall_notify_study_plan(PDO $pdo, $user_id, $sent_by = 0) {
    $user_id = (int)$user_id;
    if (!$user_id) {
        return ['ok' => false, 'success' => false, 'message' => 'داده نامعتبر است.'];
    }

    if (is_file(__DIR__ . '/students-info.php')) {
        require_once __DIR__ . '/students-info.php';
    }
    if (function_exists('students_info_find_student')) {
        $student = students_info_find_student($pdo, $user_id);
    } else {
        $stmt = $pdo->prepare("
            SELECT id, username, full_name, phone
            FROM users
            WHERE id = ? AND role = 'student' AND is_deleted = 0
            LIMIT 1
        ");
        $stmt->execute([$user_id]);
        $student = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        if ($student) {
            $student['last_name'] = rollcall_family_name($student['full_name'] ?? '');
            $student['file_number'] = (string)($student['username'] ?? '');
        }
    }
    if (!$student) {
        return ['ok' => false, 'success' => false, 'message' => 'دانش‌آموز پیدا نشد.'];
    }

    $family = trim((string)($student['last_name'] ?? ''));
    if ($family === '') {
        $family = rollcall_family_name($student['full_name'] ?? '');
    }
    $sent = rollcall_send_study_plan_sms($student['phone'] ?? '', $family);
    if (empty($sent['ok'])) {
        $sent['success'] = false;
        $sent['family'] = $family;
        $sent['file_number'] = (string)($student['file_number'] ?? $student['username'] ?? '');
        return $sent;
    }

    rollcall_ensure_study_plan_log($pdo);
    $pdo->prepare("
        INSERT INTO study_plan_sms_log (user_id, sent_by, family, phone)
        VALUES (?, ?, ?, ?)
    ")->execute([
        $user_id,
        (int)$sent_by,
        $family,
        rollcall_normalize_phone($student['phone'] ?? ''),
    ]);

    return [
        'ok' => true,
        'success' => true,
        'message' => 'پیامک برنامه مطالعاتی برای اولیا گرامی «' . $family . '» ارسال شد.',
        'family' => $family,
        'file_number' => (string)($student['file_number'] ?? $student['username'] ?? ''),
    ];
}
