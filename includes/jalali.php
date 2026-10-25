<?php
/**
 * Gregorian ↔ Jalali for the educational calendar.
 * Classic Kazim / calendar.ir day-number conversion.
 */

function jalali_div($a, $b) {
    return intdiv((int)$a, (int)$b);
}

function jalali_month_names() {
    return [
        1 => 'فروردین',
        2 => 'اردیبهشت',
        3 => 'خرداد',
        4 => 'تیر',
        5 => 'مرداد',
        6 => 'شهریور',
        7 => 'مهر',
        8 => 'آبان',
        9 => 'آذر',
        10 => 'دی',
        11 => 'بهمن',
        12 => 'اسفند',
    ];
}

function jalali_month_name($month) {
    $names = jalali_month_names();
    return $names[(int)$month] ?? '';
}

function jalali_g_days() {
    return [31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
}

function jalali_j_days() {
    return [31, 31, 31, 31, 31, 31, 30, 30, 30, 30, 30, 29];
}

function jalali_from_gregorian($gy, $gm, $gd) {
    $gy = (int)$gy - 1600;
    $gm = (int)$gm - 1;
    $gd = (int)$gd - 1;
    $g_days = jalali_g_days();
    $j_days = jalali_j_days();
    $g_day_no = 365 * $gy + jalali_div($gy + 3, 4) - jalali_div($gy + 99, 100) + jalali_div($gy + 399, 400);
    for ($i = 0; $i < $gm; $i++) {
        $g_day_no += $g_days[$i];
    }
    if ($gm > 1 && (($gy % 4 === 0 && $gy % 100 !== 0) || ($gy % 400 === 0))) {
        $g_day_no++;
    }
    $g_day_no += $gd;
    $j_day_no = $g_day_no - 79;
    $j_np = jalali_div($j_day_no, 12053);
    $j_day_no %= 12053;
    $jy = 979 + 33 * $j_np + 4 * jalali_div($j_day_no, 1461);
    $j_day_no %= 1461;
    if ($j_day_no >= 366) {
        $jy += jalali_div($j_day_no - 1, 365);
        $j_day_no = ($j_day_no - 1) % 365;
    }
    $i = 0;
    while ($i < 11 && $j_day_no >= $j_days[$i]) {
        $j_day_no -= $j_days[$i];
        $i++;
    }
    return [$jy, $i + 1, $j_day_no + 1];
}

function jalali_to_gregorian($jy, $jm, $jd) {
    $jy = (int)$jy - 979;
    $jm = (int)$jm - 1;
    $jd = (int)$jd - 1;
    $g_days = jalali_g_days();
    $j_days = jalali_j_days();
    $j_day_no = 365 * $jy + jalali_div($jy, 33) * 8 + jalali_div(($jy % 33) + 3, 4);
    for ($i = 0; $i < $jm; $i++) {
        $j_day_no += $j_days[$i];
    }
    $j_day_no += $jd;
    $g_day_no = $j_day_no + 79;
    $gy = 1600 + 400 * jalali_div($g_day_no, 146097);
    $g_day_no %= 146097;
    $leap = 1;
    if ($g_day_no >= 36525) {
        $g_day_no--;
        $gy += 100 * jalali_div($g_day_no, 36524);
        $g_day_no %= 36524;
        if ($g_day_no >= 365) {
            $g_day_no++;
        } else {
            $leap = 0;
        }
    }
    $gy += 4 * jalali_div($g_day_no, 1461);
    $g_day_no %= 1461;
    if ($g_day_no >= 366) {
        $leap = 0;
        $g_day_no--;
        $gy += jalali_div($g_day_no, 365);
        $g_day_no %= 365;
    }
    $i = 0;
    while (true) {
        $v = $g_days[$i] + ($i === 1 && $leap ? 1 : 0);
        if ($g_day_no < $v) {
            break;
        }
        $g_day_no -= $v;
        $i++;
    }
    return [$gy, $i + 1, $g_day_no + 1];
}

function jalali_is_leap($jy) {
    $jy = (int)$jy;
    $a = jalali_to_gregorian($jy, 12, 30);
    $b = jalali_to_gregorian($jy + 1, 1, 1);
    return !($a[0] === $b[0] && $a[1] === $b[1] && $a[2] === $b[2]);
}

function jalali_month_days($jy, $jm) {
    $jm = (int)$jm;
    if ($jm < 1 || $jm > 12) {
        return 0;
    }
    if ($jm <= 6) {
        return 31;
    }
    if ($jm <= 11) {
        return 30;
    }
    return jalali_is_leap($jy) ? 30 : 29;
}

function jalali_pad($n) {
    return str_pad((string)(int)$n, 2, '0', STR_PAD_LEFT);
}

function jalali_parse_ymd($raw) {
    $raw = trim((string)$raw);
    if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $raw, $m)) {
        return null;
    }
    if (!checkdate((int)$m[2], (int)$m[3], (int)$m[1])) {
        return null;
    }
    return [(int)$m[1], (int)$m[2], (int)$m[3]];
}

function jalali_from_ymd($gregorian_ymd) {
    $p = jalali_parse_ymd($gregorian_ymd);
    if (!$p) {
        return null;
    }
    return jalali_from_gregorian($p[0], $p[1], $p[2]);
}

function jalali_to_ymd($jy, $jm, $jd) {
    $g = jalali_to_gregorian($jy, $jm, $jd);
    return $g[0] . '-' . jalali_pad($g[1]) . '-' . jalali_pad($g[2]);
}

function jalali_pretty($gregorian_ymd) {
    $j = jalali_from_ymd($gregorian_ymd);
    if (!$j) {
        return '';
    }
    return (int)$j[2] . ' ' . jalali_month_name($j[1]) . ' ' . $j[0];
}

function jalali_today_parts() {
    return jalali_from_gregorian((int)date('Y'), (int)date('n'), (int)date('j'));
}

function jalali_iso_to_gregorian($iso) {
    $iso = trim((string)$iso);
    if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $iso, $m)) {
        return '';
    }
    $jy = (int)$m[1];
    $jm = (int)$m[2];
    $jd = (int)$m[3];
    $dim = jalali_month_days($jy, $jm);
    if ($jm < 1 || $jm > 12 || $jd < 1 || $jd > $dim) {
        return '';
    }
    return jalali_to_ymd($jy, $jm, $jd);
}

function jalali_half_span($jy, $half) {
    $jy = (int)$jy;
    $half = (int)$half === 2 ? 2 : 1;
    $start_m = $half === 2 ? 7 : 1;
    $months = [];
    for ($m = $start_m; $m < $start_m + 6; $m++) {
        $days = jalali_month_days($jy, $m);
        $months[] = [
            'month' => $m,
            'name' => jalali_month_name($m),
            'days' => $days,
            'start' => jalali_to_ymd($jy, $m, 1),
            'end' => jalali_to_ymd($jy, $m, $days),
        ];
    }
    return [
        'jy' => $jy,
        'half' => $half,
        'label' => $half === 2 ? 'مهر تا اسفند' : 'فروردین تا شهریور',
        'months' => $months,
        'start' => $months[0]['start'],
        'end' => $months[5]['end'],
        'days' => array_sum(array_column($months, 'days')),
    ];
}

function jalali_year_span($jy) {
    $jy = (int)$jy;
    $months = [];
    for ($m = 1; $m <= 12; $m++) {
        $days = jalali_month_days($jy, $m);
        $months[] = [
            'month' => $m,
            'name' => jalali_month_name($m),
            'days' => $days,
            'start' => jalali_to_ymd($jy, $m, 1),
            'end' => jalali_to_ymd($jy, $m, $days),
        ];
    }
    return [
        'jy' => $jy,
        'half' => 0,
        'label' => 'فروردین تا اسفند',
        'months' => $months,
        'start' => $months[0]['start'],
        'end' => $months[11]['end'],
        'days' => array_sum(array_column($months, 'days')),
    ];
}

function jalali_to_english_digits($raw) {
    return strtr((string)$raw, [
        '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
        '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
        '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
        '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
    ]);
}

function jalali_parse_any_date($raw) {
    $raw = trim(jalali_to_english_digits($raw));
    if ($raw === '' || $raw === '0000-00-00') {
        return null;
    }
    if (is_numeric($raw)) {
        $n = (float)$raw;
        if ($n > 20000 && $n < 90000) {
            $unix = (int) round(($n - 25569) * 86400);
            return gmdate('Y-m-d', $unix);
        }
    }
    if (preg_match('/^(\d{4})[\/\-\.](\d{1,2})[\/\-\.](\d{1,2})/', $raw, $m)) {
        $y = (int)$m[1];
        $mo = (int)$m[2];
        $d = (int)$m[3];
        if ($y >= 1200 && $y <= 1599) {
            $g = jalali_iso_to_gregorian(sprintf('%04d-%02d-%02d', $y, $mo, $d));
            if ($g === '') {
                throw new Exception('تاریخ شمسی نامعتبر است: ' . $raw);
            }
            return $g;
        }
        if (checkdate($mo, $d, $y)) {
            return sprintf('%04d-%02d-%02d', $y, $mo, $d);
        }
    }
    throw new Exception('تاریخ نامعتبر است: ' . $raw);
}
