<?php
/**
 * Psychology tests (NEO-60 + Big Five) for students; scored results for consultants.
 */

require_once __DIR__ . '/psych-tests-data.php';

function psych_tests_h($s) {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function psych_tests_keys() {
    return ['neo60', 'bigfive'];
}

function psych_tests_defs() {
    static $defs = null;
    if ($defs !== null) {
        return $defs;
    }
    $defs = [
        'neo60' => [
            'key' => 'neo60',
            'title' => 'آزمون شخصیت NEO-FFI',
            'short' => 'NEO-60',
            'subtitle' => 'شصت گویه — پنج عامل شخصیت',
            'item_count' => 60,
            'items' => psych_tests_neo60_items(),
            'divisor' => 12,
            'reverse' => [
                1, 16, 31, 46,
                12, 27, 42, 57,
                3, 8, 18, 23, 33, 38, 48,
                9, 14, 24, 29, 39, 44, 49, 54,
                15, 30, 45, 55,
            ],
            'factors' => [
                'N' => [1, 6, 11, 16, 21, 26, 31, 36, 41, 46, 51, 56],
                'E' => [2, 7, 12, 17, 22, 27, 32, 37, 42, 47, 52, 57],
                'O' => [3, 8, 13, 18, 23, 28, 33, 38, 43, 48, 53, 58],
                'A' => [4, 9, 14, 19, 24, 29, 34, 39, 44, 49, 54, 59],
                'C' => [5, 10, 15, 20, 25, 30, 35, 40, 45, 50, 55, 60],
            ],
        ],
        'bigfive' => [
            'key' => 'bigfive',
            'title' => 'آزمون تشخیص تیپ شخصیتی BigFive',
            'short' => 'Big Five',
            'subtitle' => 'شصت‌وپنج گویه — پنج عامل شخصیت',
            'item_count' => 65,
            'items' => psych_tests_bigfive_items(),
            'divisor' => 13,
            'reverse' => [],
            'factors' => [
                'E' => range(1, 13),
                'A' => range(14, 26),
                'C' => range(27, 39),
                'N' => range(40, 52),
                'O' => range(53, 65),
            ],
        ],
    ];
    return $defs;
}

function psych_tests_factor_meta() {
    return [
        'N' => ['code' => 'N', 'fa' => 'روان‌رنجوری', 'en' => 'Neuroticism'],
        'E' => ['code' => 'E', 'fa' => 'برون‌گرایی', 'en' => 'Extraversion'],
        'O' => ['code' => 'O', 'fa' => 'گشودگی به تجربه', 'en' => 'Openness'],
        'A' => ['code' => 'A', 'fa' => 'توافق‌پذیری', 'en' => 'Agreeableness'],
        'C' => ['code' => 'C', 'fa' => 'وظیفه‌شناسی', 'en' => 'Conscientiousness'],
    ];
}

function psych_tests_factor_order() {
    return ['N', 'E', 'O', 'A', 'C'];
}

function psych_tests_def($key) {
    $defs = psych_tests_defs();
    if (!isset($defs[$key])) {
        throw new Exception('آزمون پیدا نشد.');
    }
    return $defs[$key];
}

function psych_tests_normalize_answers($raw) {
    if (is_string($raw)) {
        $raw = json_decode($raw, true);
    }
    if (!is_array($raw)) {
        return [];
    }
    $out = [];
    foreach ($raw as $k => $v) {
        $n = (int)$k;
        $val = (int)$v;
        if ($n > 0 && $val >= 1 && $val <= 5) {
            $out[$n] = $val;
        }
    }
    ksort($out, SORT_NUMERIC);
    return $out;
}

function psych_tests_item_score($test_key, $item_no, $choice) {
    $def = psych_tests_def($test_key);
    $choice = (int)$choice;
    $item_no = (int)$item_no;
    if ($choice < 1 || $choice > 5) {
        throw new Exception('گزینه نامعتبر است.');
    }
    $reverse = array_fill_keys($def['reverse'], true);
    if (isset($reverse[$item_no])) {
        return 6 - $choice;
    }
    return $choice;
}

function psych_tests_score($test_key, array $answers) {
    $def = psych_tests_def($test_key);
    $n = (int)$def['item_count'];
    $answers = psych_tests_normalize_answers($answers);
    $missing = [];
    for ($i = 1; $i <= $n; $i++) {
        if (!isset($answers[$i])) {
            $missing[] = $i;
        }
    }
    if ($missing) {
        throw new Exception('همه سوال‌ها باید پاسخ داده شوند.');
    }
    $item_scores = [];
    for ($i = 1; $i <= $n; $i++) {
        $item_scores[$i] = psych_tests_item_score($test_key, $i, $answers[$i]);
    }
    $factors = [];
    foreach ($def['factors'] as $code => $items) {
        $sum = 0;
        foreach ($items as $i) {
            $sum += $item_scores[$i];
        }
        $count = count($items);
        $mean = $count ? round($sum / $count, 2) : 0.0;
        $factors[$code] = [
            'sum' => $sum,
            'count' => $count,
            'mean' => $mean,
        ];
    }
    return [
        'test_key' => $test_key,
        'item_scores' => $item_scores,
        'factors' => $factors,
    ];
}

function psych_tests_ensure_schema(PDO $pdo) {
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS psych_test_attempts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            student_id INT NOT NULL,
            test_key VARCHAR(16) NOT NULL,
            status VARCHAR(16) NOT NULL DEFAULT 'draft',
            answers_json MEDIUMTEXT NOT NULL,
            scores_json MEDIUMTEXT NULL,
            submitted_at DATETIME NULL,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_pta_student_test (student_id, test_key),
            KEY idx_pta_student (student_id),
            KEY idx_pta_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
}

function psych_tests_get(PDO $pdo, $student_id, $test_key) {
    psych_tests_ensure_schema($pdo);
    psych_tests_def($test_key);
    $st = $pdo->prepare("
        SELECT id, student_id, test_key, status, answers_json, scores_json, submitted_at, updated_at
        FROM psych_test_attempts
        WHERE student_id = ? AND test_key = ?
        LIMIT 1
    ");
    $st->execute([(int)$student_id, $test_key]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function psych_tests_for_student(PDO $pdo, $student_id) {
    psych_tests_ensure_schema($pdo);
    $st = $pdo->prepare("
        SELECT id, student_id, test_key, status, answers_json, scores_json, submitted_at, updated_at
        FROM psych_test_attempts
        WHERE student_id = ?
    ");
    $st->execute([(int)$student_id]);
    $out = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $out[$row['test_key']] = $row;
    }
    return $out;
}

function psych_tests_save_draft(PDO $pdo, $student_id, $test_key, array $answers) {
    psych_tests_ensure_schema($pdo);
    $def = psych_tests_def($test_key);
    $answers = psych_tests_normalize_answers($answers);
    foreach ($answers as $k => $v) {
        if ($k < 1 || $k > (int)$def['item_count']) {
            unset($answers[$k]);
        }
    }
    $json = json_encode($answers, JSON_UNESCAPED_UNICODE);
    $existing = psych_tests_get($pdo, $student_id, $test_key);
    if ($existing) {
        $st = $pdo->prepare("
            UPDATE psych_test_attempts
            SET answers_json = ?, status = 'draft'
            WHERE id = ?
        ");
        $st->execute([$json, (int)$existing['id']]);
        return (int)$existing['id'];
    }
    $st = $pdo->prepare("
        INSERT INTO psych_test_attempts (student_id, test_key, status, answers_json)
        VALUES (?, ?, 'draft', ?)
    ");
    $st->execute([(int)$student_id, $test_key, $json]);
    return (int)$pdo->lastInsertId();
}

function psych_tests_submit(PDO $pdo, $student_id, $test_key, array $answers) {
    psych_tests_ensure_schema($pdo);
    $answers = psych_tests_normalize_answers($answers);
    $scores = psych_tests_score($test_key, $answers);
    $ans_json = json_encode($answers, JSON_UNESCAPED_UNICODE);
    $score_json = json_encode($scores, JSON_UNESCAPED_UNICODE);
    $existing = psych_tests_get($pdo, $student_id, $test_key);
    if ($existing) {
        $st = $pdo->prepare("
            UPDATE psych_test_attempts
            SET status = 'submitted',
                answers_json = ?,
                scores_json = ?,
                submitted_at = NOW()
            WHERE id = ?
        ");
        $st->execute([$ans_json, $score_json, (int)$existing['id']]);
        $id = (int)$existing['id'];
    } else {
        $st = $pdo->prepare("
            INSERT INTO psych_test_attempts
                (student_id, test_key, status, answers_json, scores_json, submitted_at)
            VALUES (?, ?, 'submitted', ?, ?, NOW())
        ");
        $st->execute([(int)$student_id, $test_key, $ans_json, $score_json]);
        $id = (int)$pdo->lastInsertId();
    }
    $row = psych_tests_get($pdo, $student_id, $test_key);
    $row['id'] = $id;
    return $row;
}

function psych_tests_decode_scores($row) {
    if (!$row || empty($row['scores_json'])) {
        return null;
    }
    $scores = json_decode($row['scores_json'], true);
    return is_array($scores) ? $scores : null;
}

function psych_tests_student_cards(PDO $pdo, $student_id) {
    $rows = psych_tests_for_student($pdo, $student_id);
    $cards = [];
    foreach (psych_tests_defs() as $key => $def) {
        $row = $rows[$key] ?? null;
        $answers = $row ? psych_tests_normalize_answers($row['answers_json'] ?? []) : [];
        $answered = count($answers);
        $status = 'missing';
        if ($row) {
            $status = (string)$row['status'];
        }
        $cards[] = [
            'key' => $key,
            'title' => $def['title'],
            'short' => $def['short'],
            'subtitle' => $def['subtitle'],
            'item_count' => $def['item_count'],
            'answered' => $answered,
            'status' => $status,
            'has_scores' => $row && !empty($row['scores_json']),
            'submitted_at' => $row['submitted_at'] ?? null,
            'updated_at' => $row['updated_at'] ?? null,
        ];
    }
    return $cards;
}

function psych_tests_consultant_students(PDO $pdo) {
    psych_tests_ensure_schema($pdo);
    $sql = "
        SELECT u.id, u.username, u.full_name, u.avatar,
               neo.status AS neo_status, neo.submitted_at AS neo_at, neo.scores_json AS neo_scores,
               bf.status AS bf_status, bf.submitted_at AS bf_at, bf.scores_json AS bf_scores
        FROM users u
        LEFT JOIN psych_test_attempts neo
            ON neo.student_id = u.id AND neo.test_key = 'neo60'
        LEFT JOIN psych_test_attempts bf
            ON bf.student_id = u.id AND bf.test_key = 'bigfive'
        WHERE u.role = 'student' AND IFNULL(u.is_deleted, 0) = 0
        ORDER BY CAST(u.username AS UNSIGNED), u.username, u.full_name
    ";
    try {
        return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log('psych_tests_consultant_students: ' . $e->getMessage());
        return [];
    }
}

function psych_tests_when($raw) {
    $raw = trim((string)$raw);
    if ($raw === '') {
        return '';
    }
    if (function_exists('consultant_remark_when')) {
        return consultant_remark_when($raw);
    }
    if (is_file(__DIR__ . '/jalali.php')) {
        require_once __DIR__ . '/jalali.php';
    }
    if (function_exists('consultant_remark_when')) {
        return consultant_remark_when($raw);
    }
    if (function_exists('jalali_pretty')) {
        $ymd = substr($raw, 0, 10);
        $time = strlen($raw) >= 16 ? substr($raw, 11, 5) : '';
        $pretty = jalali_pretty($ymd);
        return $pretty !== '' ? trim($pretty . ' ' . $time) : $raw;
    }
    return $raw;
}

function psych_tests_public_student_boot($def, $row) {
    $answers = $row ? psych_tests_normalize_answers($row['answers_json'] ?? []) : [];
    return [
        'ajax' => 'ajax-psych.php',
        'test_key' => $def['key'],
        'title' => $def['title'],
        'item_count' => $def['item_count'],
        'labels' => psych_tests_likert_labels(),
        'answers' => $answers,
        'status' => $row ? (string)$row['status'] : 'missing',
        'has_scores' => $row && !empty($row['scores_json']),
    ];
}

function psych_tests_status_fa($status, $has_scores = false) {
    if ($status === 'submitted') {
        return 'انجام شده';
    }
    if ($status === 'draft' && $has_scores) {
        return 'در حال تکرار';
    }
    if ($status === 'draft') {
        return 'ناتمام';
    }
    return 'شروع نشده';
}

function psych_tests_mean_width($mean) {
    $mean = (float)$mean;
    if ($mean < 1) {
        $mean = 1;
    }
    if ($mean > 5) {
        $mean = 5;
    }
    return (int)round((($mean - 1) / 4) * 100);
}

function psych_tests_render_result($row, $with_items = true, $fallback_key = '') {
    if (!$row) {
        $title = '';
        if ($fallback_key !== '') {
            try {
                $title = psych_tests_def($fallback_key)['title'];
            } catch (Exception $e) {
                $title = '';
            }
        }
        $head = $title !== '' ? psych_tests_h($title) : 'آزمون روانشناسی';
        return '<div class="pt-result"><h5>' . $head . '</h5><p class="mb-0">هنوز ثبت نشده. وقتی دانش‌آموز آزمون را تمام کند، نمره عوامل اینجا می‌آید.</p></div>';
    }
    $def = psych_tests_def($row['test_key']);
    $scores = psych_tests_decode_scores($row);
    $meta = psych_tests_factor_meta();
    $html = '<div class="pt-result">';
    $html .= '<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">';
    $html .= '<h5 class="mb-0">' . psych_tests_h($def['title']) . '</h5>';
    $html .= '<span class="pt-pill ' . (($row['status'] ?? '') === 'submitted' ? 'done' : 'draft') . '">';
    $html .= psych_tests_h(psych_tests_status_fa($row['status'] ?? '', !empty($row['scores_json'])));
    $html .= '</span></div>';
    if (!empty($row['submitted_at'])) {
        $html .= '<p class="pt-lock mb-3">ثبت: ' . psych_tests_h(psych_tests_when($row['submitted_at'])) . '</p>';
    }
    if (!$scores || empty($scores['factors'])) {
        $html .= '<p class="mb-0">پاسخ‌ها هنوز کامل نشده‌اند؛ نمره‌ای برای نمایش نیست.</p></div>';
        return $html;
    }
    foreach (psych_tests_factor_order() as $code) {
        if (!isset($scores['factors'][$code])) {
            continue;
        }
        $f = $scores['factors'][$code];
        $label = $meta[$code]['fa'] . ' (' . $code . ')';
        $mean = number_format((float)$f['mean'], 2);
        $width = psych_tests_mean_width($f['mean']);
        $html .= '<div class="pt-factor">';
        $html .= '<div class="pt-factor-row"><span>' . psych_tests_h($label) . '</span><strong>' . psych_tests_h($mean) . ' / 5</strong></div>';
        $html .= '<div class="pt-bar" aria-hidden="true"><i style="width:' . $width . '%"></i></div>';
        $html .= '<div class="pt-lock mt-1">جمع ' . (int)$f['sum'] . ' از ' . (int)$f['count'] . ' سوال، تقسیم بر ' . (int)$f['count'] . '</div>';
        $html .= '</div>';
    }
    if ($with_items && !empty($scores['item_scores'])) {
        $answers = psych_tests_normalize_answers($row['answers_json'] ?? []);
        $html .= '<details class="pt-items"><summary>پاسخ سوال‌به‌سوال</summary>';
        $html .= '<div class="table-responsive"><table class="table table-sm si-table"><thead><tr><th>سوال</th><th>گزینه</th><th>نمره</th></tr></thead><tbody>';
        foreach ($def['items'] as $n => $text) {
            $choice = $answers[$n] ?? '—';
            $scored = $scores['item_scores'][$n] ?? '—';
            $html .= '<tr><td>' . (int)$n . ' — ' . psych_tests_h($text) . '</td>';
            $html .= '<td>' . psych_tests_h((string)$choice) . '</td>';
            $html .= '<td>' . psych_tests_h((string)$scored) . '</td></tr>';
        }
        $html .= '</tbody></table></div></details>';
    }
    $html .= '</div>';
    return $html;
}
