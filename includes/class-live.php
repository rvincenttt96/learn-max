<?php
/**
 * Green live-session marker next to class titles.
 * A class is live on its odd/even day, between schedule start and end (Asia/Tehran).
 * Default session length is 90 minutes when only a start time is stored.
 */

if (function_exists('class_live_dot')) {
    return;
}

function class_live_now($now = null) {
    date_default_timezone_set('Asia/Tehran');
    return $now === null ? time() : (int)$now;
}

function class_live_today_type($now = null) {
    $now = class_live_now($now);
    $weekday = (int)date('w', $now);
    if ($weekday === 5) {
        return null;
    }
    return in_array($weekday, [6, 1, 3], true) ? 'even' : 'odd';
}

function class_live_parse_schedule($schedule, $now = null) {
    $now = class_live_now($now);
    $schedule = trim((string)$schedule);
    if ($schedule === '' || $schedule === 'تنظیم نشده' || strcasecmp($schedule, 'Not Set') === 0) {
        return null;
    }
    if (!function_exists('ghazal_normalize_digits')) {
        require_once __DIR__ . '/digits.php';
    }
    $schedule = ghazal_normalize_digits($schedule);
    $schedule = str_replace(['.', '–', '—'], [':', '-', '-'], $schedule);
    $parts = preg_split('/\s*-\s*/', $schedule);
    $start = trim((string)($parts[0] ?? ''));
    $end = trim((string)($parts[1] ?? ''));
    if (preg_match('/^\d{1,2}$/', $start)) {
        $start .= ':00';
    }
    if ($end !== '' && preg_match('/^\d{1,2}$/', $end)) {
        $end .= ':00';
    }
    if (!preg_match('/^\d{1,2}:\d{2}/', $start)) {
        return null;
    }
    $day = date('Y-m-d', $now);
    $start_ts = strtotime($day . ' ' . $start);
    if (!$start_ts) {
        return null;
    }
    $end_ts = $end !== '' ? strtotime($day . ' ' . $end) : ($start_ts + 90 * 60);
    if (!$end_ts || $end_ts <= $start_ts) {
        $end_ts = $start_ts + 90 * 60;
    }
    return [$start_ts, $end_ts];
}

function class_live_window($class, $now = null) {
    $now = class_live_now($now);
    if (!empty($class['is_archived']) || !empty($class['term_archived'])) {
        return null;
    }
    $starts = trim((string)($class['starts_on'] ?? $class['term_starts_on'] ?? ''));
    if ($starts !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $starts) && $starts > date('Y-m-d', $now)) {
        return null;
    }
    $want = strtolower(trim((string)($class['schedule_type'] ?? '')));
    $today = class_live_today_type($now);
    if ($today === null || ($want !== 'odd' && $want !== 'even') || $want !== $today) {
        return null;
    }
    return class_live_parse_schedule($class['schedule'] ?? '', $now);
}

function class_is_in_session($class, $now = null) {
    $now = class_live_now($now);
    $window = class_live_window($class, $now);
    if (!$window) {
        return false;
    }
    return $now >= $window[0] && $now < $window[1];
}

function class_live_dot($class, $now = null) {
    $now = class_live_now($now);
    $window = class_live_window($class, $now);
    $start = $window ? (int)$window[0] : 0;
    $end = $window ? (int)$window[1] : 0;
    $on = $start > 0 && $now >= $start && $now < $end;
    $label = $on ? 'در حال برگزاری' : '';
    return '<span class="class-live-dot' . ($on ? ' is-on' : '') . '"'
        . ' data-session-start="' . $start . '"'
        . ' data-session-end="' . $end . '"'
        . ($label !== '' ? ' title="' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '" aria-label="' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '"' : ' aria-hidden="true"')
        . '></span>';
}
