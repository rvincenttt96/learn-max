<?php
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
require_once '../../includes/terms.php';
require_once '../../includes/year-program.php';
require_once '../../includes/jalali.php';
require_once '../../includes/simplexlsx.php';
require_role('supervisor');

use Shuchkin\SimpleXLSX;

function calendar_header_key($raw) {
    $h = strtolower(trim(jalali_to_english_digits($raw)));
    $h = str_replace([' ', '-', 'ـ'], '_', $h);
    $map = [
        'year' => 'year', 'yr' => 'year', 'سال' => 'year', 'سال_تحصیلی' => 'year',
        'term' => 'term', 'term_title' => 'term', 'title' => 'term', 'name' => 'term', 'ترم' => 'term', 'نام_ترم' => 'term',
        'start' => 'start', 'starts_on' => 'start', 'start_date' => 'start', 'شروع' => 'start', 'تاریخ_شروع' => 'start',
        'end' => 'end', 'ends_on' => 'end', 'end_date' => 'end', 'پایان' => 'end', 'تاریخ_پایان' => 'end',
    ];
    return $map[$h] ?? '';
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('درخواست نامعتبر.');
    }
    if (!isset($_FILES['excel_file']) || $_FILES['excel_file']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('فایل Excel انتخاب نشده است.');
    }
    $ext = strtolower(pathinfo($_FILES['excel_file']['name'], PATHINFO_EXTENSION));
    if ($ext !== 'xlsx') {
        throw new Exception('فقط فایل .xlsx مجاز است.');
    }
    $xlsx = SimpleXLSX::parse($_FILES['excel_file']['tmp_name']);
    if (!$xlsx) {
        throw new Exception('خطا در خواندن فایل Excel: ' . SimpleXLSX::parseError());
    }
    $rows = $xlsx->rows();
    if (count($rows) < 2) {
        throw new Exception('فایل باید یک ردیف عنوان و حداقل یک ردیف داده داشته باشد.');
    }

    $keys = [];
    foreach ($rows[0] as $i => $cell) {
        $k = calendar_header_key($cell);
        if ($k !== '') {
            $keys[$k] = $i;
        }
    }
    foreach (['year', 'term', 'start', 'end'] as $need) {
        if (!isset($keys[$need])) {
            throw new Exception('ستون‌های لازم: year, term, start, end');
        }
    }

    term_ensure_schema($pdo);
    $open = term_open_rows($pdo);
    $user_id = (int)($_SESSION['user_id'] ?? 0);
    $updated = 0;
    $created = 0;

    for ($r = 1; $r < count($rows); $r++) {
        $row = $rows[$r];
        $year = trim(jalali_to_english_digits((string)($row[$keys['year']] ?? '')));
        $year = preg_replace('/[^\d]/', '', $year);
        $title = trim((string)($row[$keys['term']] ?? ''));
        if ($year === '' && $title === '') {
            continue;
        }
        if ($year === '' || $title === '') {
            throw new Exception('ردیف ' . ($r + 1) . ': سال و نام ترم لازم است.');
        }
        $start = jalali_parse_any_date($row[$keys['start']] ?? '');
        $end = jalali_parse_any_date($row[$keys['end']] ?? '');
        if (!$start) {
            throw new Exception('ردیف ' . ($r + 1) . ': تاریخ شروع نامعتبر است.');
        }
        term_assert_date_range($start, $end);
        $english = term_english_title($title);
        $match = null;
        foreach ($open as $term) {
            $probe = ['title' => $english, 'year' => $year, 'aliases' => [$title, $english]];
            if (ghazal_program_match_term($probe, $term) && preg_replace('/[^\d]/', '', (string)$term['year']) === $year) {
                $match = $term;
                break;
            }
            if (ghazal_program_normalize($term['title']) === ghazal_program_normalize($title)
                && preg_replace('/[^\d]/', '', (string)$term['year']) === $year) {
                $match = $term;
                break;
            }
        }
        if ($match) {
            $pdo->prepare('UPDATE terms SET title = ?, starts_on = ?, ends_on = ? WHERE id = ?')
                ->execute([$english, $start, $end, (int)$match['id']]);
            $fresh = term_load($pdo, (int)$match['id']);
            if ($fresh) {
                try {
                    term_mirror_accounting($pdo, $fresh, 'active');
                } catch (Throwable $e) {
                    error_log('calendar import acc: ' . $e->getMessage());
                }
            }
            $updated++;
        } else {
            $ins = $pdo->prepare("INSERT INTO terms (title, year, starts_on, ends_on, created_by, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
            $ins->execute([$english, $year, $start, $end, $user_id]);
            $fresh = term_load($pdo, (int)$pdo->lastInsertId());
            if ($fresh) {
                try {
                    term_mirror_accounting($pdo, $fresh, 'active');
                } catch (Throwable $e) {
                    error_log('calendar import acc: ' . $e->getMessage());
                }
            }
            $created++;
        }
        $open = term_open_rows($pdo);
    }

    term_promote_due($pdo);
    $msg = 'تقویم آموزشی وارد شد.';
    if ($updated) {
        $msg .= ' به‌روزرسانی: ' . $updated . '.';
    }
    if ($created) {
        $msg .= ' ساخته‌شده: ' . $created . '.';
    }
    header('Location: terms.php?tab=active&success=' . urlencode($msg));
    exit;
} catch (Throwable $e) {
    header('Location: terms.php?tab=active&error=' . urlencode($e->getMessage()));
    exit;
}
