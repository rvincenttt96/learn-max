<?php
/**
 * Canonical English term names and the 1405 paper timetable (reference).
 */

function ghazal_program_latin_digits($raw) {
    $map = [
        '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
        '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
        '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
        '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
    ];
    return strtr((string)$raw, $map);
}

function ghazal_program_normalize($title) {
    $t = trim((string)$title);
    $t = str_replace(['ي', 'ك', '‌', 'ـ'], ['ی', 'ک', ' ', ''], $t);
    $t = ghazal_program_latin_digits($t);
    $t = preg_replace('/\s+/u', ' ', $t);
    $t = mb_strtolower($t, 'UTF-8');
    $t = preg_replace('/\biii\b/', '3', $t);
    $t = preg_replace('/\bii\b/', '2', $t);
    $t = preg_replace('/\bi\b/', '1', $t);
    $t = str_replace(['spring', 'summer', 'autumn', 'fall', 'winter'], ['بهار', 'تابستان', 'پاییز', 'پاییز', 'زمستان'], $t);
    $t = preg_replace('/\s+/u', ' ', $t);
    return trim($t);
}

function ghazal_program_english_map() {
    return [
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
    ];
}

function term_english_title($title) {
    $original = trim((string)$title);
    if ($original === '') {
        return '';
    }
    $raw = $original;
    $year = '';
    if (preg_match('/\(([^)]+)\)\s*$/u', $raw, $m)) {
        $year = ghazal_program_latin_digits(trim($m[1]));
        $raw = trim(preg_replace('/\s*\([^)]+\)\s*$/u', '', $raw));
    }
    $raw = preg_replace('/^ترم\s+/u', '', $raw);
    if ($year === '' && preg_match('/\s+([0-9۰-۹٠-٩]{4})$/u', $raw, $m)) {
        $year = ghazal_program_latin_digits($m[1]);
        $raw = trim(preg_replace('/\s+[0-9۰-۹٠-٩]{4}$/u', '', $raw));
    }
    $key = ghazal_program_normalize($raw);
    $map = ghazal_program_english_map();
    $season = [
        'بهار' => 'Spring',
        'تابستان' => 'Summer',
        'پاییز' => 'Fall',
        'زمستان' => 'Winter',
    ];
    $english = $map[$key] ?? ($season[$key] ?? '');
    if ($english === '') {
        return $original;
    }
    if ($year !== '' && !preg_match('/\(' . preg_quote($year, '/') . '\)\s*$/u', $english)) {
        return $english . ' (' . $year . ')';
    }
    return $english;
}

function ghazal_program_raw_1405() {
    return [
        [
            'title' => 'Spring 1',
            'aliases' => ['بهار 1', 'Spring 1', 'Spring I'],
            'starts' => ['1405-02-09', '1405-02-10'],
            'ends' => ['1405-03-07', '1405-03-09'],
        ],
        [
            'title' => 'Spring 3',
            'aliases' => ['بهار 3', 'Spring 3', 'Spring III'],
            'flyer' => ['1405-02-30', '1405-02-31'],
            'enroll' => '1405-03-02',
            'starts' => ['1405-03-10', '1405-03-11'],
            'ends' => ['1405-04-10', '1405-04-11'],
        ],
        [
            'title' => 'Summer 1',
            'aliases' => ['تابستان 1', 'Summer 1', 'Summer I'],
            'flyer' => ['1405-04-01', '1405-04-02'],
            'enroll' => '1405-04-06',
            'starts' => ['1405-04-15', '1405-04-16'],
            'ends' => ['1405-05-14', '1405-05-15'],
        ],
        [
            'title' => 'Summer 2',
            'aliases' => ['تابستان 2', 'Summer 2', 'Summer II'],
            'flyer' => ['1405-05-14', '1405-05-15'],
            'enroll' => '1405-05-17',
            'starts' => ['1405-05-24', '1405-05-25'],
            'ends' => ['1405-06-23', '1405-06-24'],
        ],
        [
            'title' => 'Fall 1',
            'aliases' => ['پاییز 1', 'Fall 1', 'Fall I', 'Autumn 1'],
            'flyer' => ['1405-06-21', '1405-06-22'],
            'enroll' => '1405-06-23',
            'starts' => ['1405-06-30', '1405-06-31'],
            'ends' => ['1405-07-27', '1405-07-28'],
        ],
        [
            'title' => 'Fall 2',
            'aliases' => ['پاییز 2', 'Fall 2', 'Fall II', 'Autumn 2'],
            'flyer' => ['1405-07-20', '1405-07-21'],
            'enroll' => '1405-07-22',
            'starts' => ['1405-07-29', '1405-07-30'],
            'ends' => ['1405-08-27', '1405-08-28'],
        ],
        [
            'title' => 'Fall 3',
            'aliases' => ['پاییز 3', 'Fall 3', 'Fall III', 'Autumn 3'],
            'flyer' => ['1405-08-20', '1405-08-21'],
            'enroll' => '1405-08-23',
            'starts' => ['1405-08-30', '1405-09-01'],
            'ends' => ['1405-09-28', '1405-09-29'],
        ],
        [
            'title' => 'Winter 1',
            'aliases' => ['زمستان 1', 'Winter 1', 'Winter I'],
            'flyer' => ['1405-09-18', '1405-09-19'],
            'enroll' => '1405-09-21',
            'starts' => ['1405-09-30', '1405-10-01'],
            'ends' => ['1405-11-01', '1405-11-03'],
        ],
        [
            'title' => 'Winter 2',
            'aliases' => ['زمستان 2', 'Winter 2', 'Winter II'],
            'flyer' => ['1405-10-29', '1405-10-30'],
            'enroll' => '1405-11-01',
            'starts' => ['1405-11-06', '1405-11-07'],
            'ends' => ['1405-12-05', '1405-12-06'],
        ],
    ];
}

function ghazal_program_keys(array $row) {
    $keys = [ghazal_program_normalize($row['title'] ?? '')];
    foreach ($row['aliases'] ?? [] as $alias) {
        $keys[] = ghazal_program_normalize($alias);
    }
    return array_values(array_unique(array_filter($keys)));
}

function ghazal_program_match_term(array $row, array $term) {
    $have = ghazal_program_normalize($term['title'] ?? '');
    if ($have === '') {
        return false;
    }
    $term_year = preg_replace('/[^\d]/', '', (string)($term['year'] ?? ''));
    $row_year = preg_replace('/[^\d]/', '', (string)($row['year'] ?? '1405'));
    if ($term_year !== '' && $row_year !== '' && $term_year !== $row_year) {
        return false;
    }
    return in_array($have, ghazal_program_keys($row), true);
}

function ghazal_program_items($year = '1405') {
    if (!function_exists('jalali_iso_to_gregorian')) {
        require_once __DIR__ . '/jalali.php';
    }
    $year = preg_replace('/[^\d]/', '', (string)$year);
    if ($year !== '1405') {
        return [];
    }
    $out = [];
    foreach (ghazal_program_raw_1405() as $row) {
        $starts = [];
        foreach ($row['starts'] as $d) {
            $g = jalali_iso_to_gregorian($d);
            if ($g !== '') {
                $starts[] = $g;
            }
        }
        $ends = [];
        foreach ($row['ends'] as $d) {
            $g = jalali_iso_to_gregorian($d);
            if ($g !== '') {
                $ends[] = $g;
            }
        }
        if (!$starts || !$ends) {
            continue;
        }
        sort($starts);
        sort($ends);
        $enroll = '';
        if (!empty($row['enroll'])) {
            $enroll = jalali_iso_to_gregorian($row['enroll']);
        }
        $out[] = [
            'title' => $row['title'],
            'year' => '1405',
            'aliases' => $row['aliases'],
            'starts_on' => $starts[0],
            'ends_on' => $ends[count($ends) - 1],
            'enroll_on' => $enroll,
            'starts_label' => jalali_pretty($starts[0]),
            'ends_label' => jalali_pretty($ends[count($ends) - 1]),
            'enroll_label' => $enroll !== '' ? jalali_pretty($enroll) : '',
            'keys' => ghazal_program_keys($row),
            'jalali_starts' => $row['starts'],
            'jalali_ends' => $row['ends'],
            'jalali_enroll' => $row['enroll'] ?? '',
        ];
    }
    return $out;
}
