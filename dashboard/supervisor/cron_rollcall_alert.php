<?php
// تنظیم منطقه زمانی روی تهران
date_default_timezone_set('Asia/Tehran');

// حل مشکل اصلی کرون‌جاب: استفاده از __DIR__ برای آدرس‌دهی مطلق
@set_time_limit(180);
ignore_user_abort(true);

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/terms.php';
require_once __DIR__ . '/../../includes/jdate.php';
require_once __DIR__ . '/../../includes/rollcall.php';
require_once __DIR__ . '/../../includes/alocom.php';
require_once __DIR__ . '/../../includes/alocom-access.php';

try {
    $due = alocom_sync_due_classes($pdo);
    if (is_array($due)) {
        $n = isset($due['synced']) && is_array($due['synced']) ? count($due['synced']) : 0;
        alocom_log('due rollcall done n=' . $n . (isset($due['skipped']) ? ' skipped=' . $due['skipped'] : ''));
    }
} catch (Exception $e) {
    error_log(date('[Y-m-d H:i:s] ') . "AloCom due rollcall: " . $e->getMessage() . "\n", 3, __DIR__ . '/cron_error_log.txt');
    if (function_exists('ghazal_error_from_throwable')) {
        ghazal_error_from_throwable($e);
    }
}

// 1. تشخیص اینکه امروز روز زوج است یا فرد
$today_eng_weekday = date('w'); // 0 (یکشنبه) تا 6 (شنبه)

// در ایران: شنبه، دوشنبه، چهارشنبه -> زوج (even) | یکشنبه، سه‌شنبه، پنجشنبه -> فرد (odd)
if ($today_eng_weekday == 5) {
    try {
        alocom_sync_class_access($pdo, false, false);
    } catch (Exception $e) {
        error_log(date('[Y-m-d H:i:s] ') . "AloCom access sync: " . $e->getMessage() . "\n", 3, __DIR__ . '/cron_error_log.txt');
        if (function_exists('ghazal_error_from_throwable')) {
            ghazal_error_from_throwable($e);
        }
    }
    exit("Today is Friday. No classes.");
}

$today_type = in_array($today_eng_weekday, [6, 1, 3]) ? 'even' : 'odd';
$today_date = date('Y-m-d');
$now = time();

try {
    // 2. پیدا کردن تمام کلاس‌های فعال امروز که حذف یا آرشیو نشده‌اند
    $stmt = $pdo->prepare("
        SELECT c.id, c.title, c.schedule, u.full_name as teacher_name
        FROM classes c
        JOIN terms t ON c.term_id = t.id
        JOIN users u ON c.teacher_id = u.id
        WHERE c.schedule_type = ? AND c.is_deleted = 0 AND " . term_sql_teaching('t') . "
          AND IFNULL(c.is_archived, 0) = 0
    ");
    $stmt->execute([$today_type]);
    $classes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($classes as $class) {
        if (empty($class['schedule']) || $class['schedule'] == 'تنظیم نشده') {
            continue;
        }

        $time_parts = explode('-', $class['schedule']);
        $start_time_str = trim($time_parts[0]);
        $start_timestamp = strtotime($today_date . ' ' . $start_time_str);
        if (!$start_timestamp) {
            continue;
        }

        // One Mattermost ping at T+30. Cron is every 5 minutes, so allow until T+40
        // in case the 18:30 tick is late — the claim row still blocks repeats.
        $alert_at = $start_timestamp + (30 * 60);
        $alert_until = $start_timestamp + (40 * 60);
        if ($now < $alert_at || $now >= $alert_until) {
            continue;
        }

        $check_stmt = $pdo->prepare("
            SELECT COUNT(*) FROM attendance
            WHERE class_id = ? AND class_date = ?
        ");
        $check_stmt->execute([$class['id'], $today_date]);
        $has_attendance = $check_stmt->fetchColumn();
        if ((int)$has_attendance > 0) {
            continue;
        }
        if (!rollcall_claim_missing_alert($pdo, $class['id'], $today_date)) {
            continue;
        }

        $msg = "⚠️ **Roll Call Alert!**\n\n";
        $msg .= "👨‍🏫 **Instructor:** `" . $class['teacher_name'] . "`\n";
        $msg .= "🏫 **Class:** `" . $class['title'] . "`\n";
        $msg .= "🕒 **Time:** `" . $class['schedule'] . "`\n\n";
        $msg .= "*More than 30 minutes have passed since the class started, but the attendance has not been submitted yet.*";
        if (!rollcall_send_mattermost($msg)) {
            rollcall_release_missing_alert($pdo, $class['id'], $today_date);
        }
    }
} catch (Exception $e) {
    error_log(date('[Y-m-d H:i:s] ') . "Cron Error: " . $e->getMessage() . "\n", 3, __DIR__ . '/cron_error_log.txt');
}

try {
    alocom_sync_class_access($pdo, false, false);
} catch (Exception $e) {
    error_log(date('[Y-m-d H:i:s] ') . "AloCom access sync: " . $e->getMessage() . "\n", 3, __DIR__ . '/cron_error_log.txt');
}
