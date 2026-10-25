<?php
/**
 * New book tracks: Wider World (WT5–WT16) and Connectivity (CB–CADV).
 * Levels copy tuition from the matching T / B / E / PI / I / UI / ADV row.
 * Term plans from the uploaded Word files belong ONLY to WT / CB / CE / CPI /
 * CI / CUI / CADV classes. Old T / B / E / PI / I / UI / ADV classes never
 * receive these templates. source_level is used only to copy tuition.
 */

function term_plan_tracks_version() {
    return '2026-10-17.2';
}

function term_plan_tracks_data() {
    static $data = null;
    if ($data === null) {
        $path = __DIR__ . '/term-plan-tracks-data.php';
        $data = is_file($path) ? require $path : [];
        if (!is_array($data)) {
            $data = [];
        }
    }
    return $data;
}

function term_plan_new_level_map() {
    $map = [];
    for ($i = 5; $i <= 16; $i++) {
        $map['WT' . $i] = 'T' . $i;
    }
    foreach (['B' => 'CB', 'E' => 'CE', 'PI' => 'CPI', 'I' => 'CI', 'UI' => 'CUI', 'ADV' => 'CADV'] as $old => $new) {
        for ($i = 1; $i <= 5; $i++) {
            $map[$new . $i] = $old . $i;
        }
    }
    return $map;
}

function term_plan_new_level_keys() {
    return array_keys(term_plan_new_level_map());
}

function term_plan_level_compact_key($raw) {
    if (function_exists('accounting_level_compact')) {
        return accounting_level_compact($raw);
    }
    $t = trim((string)$raw);
    $t = str_replace(['ي', 'ك', '‌', 'ـ'], ['ی', 'ک', '', ''], $t);
    $t = preg_replace('/[\s\-_.–—]+/u', '', $t);
    return function_exists('mb_strtolower') ? mb_strtolower($t, 'UTF-8') : strtolower($t);
}

function term_plan_new_track_pattern() {
    // Longest prefixes first so CPI/CUI/CADV win over PI/UI/ADV/CI.
    return '/(?<![\p{L}\p{N}])(CADV|CUI|CPI|WT|CB|CE|CI)\s*(1[0-6]|[1-9])(?![\p{L}\p{N}])/iu';
}

function term_plan_legacy_level_pattern() {
    return '/(?<![\p{L}\p{N}])(ADV|UI|PI|B|E|I|T)\s*(1[0-6]|[1-9])(?![\p{L}\p{N}])/iu';
}

function term_plan_detect_level_key($title) {
    $title = trim((string)$title);
    if ($title === '') {
        return '';
    }
    $map = term_plan_new_level_map();
    foreach ([$title, preg_replace('/\s+/u', ' ', $title)] as $hay) {
        if (!preg_match(term_plan_new_track_pattern(), $hay, $m)) {
            continue;
        }
        $key = strtoupper($m[1]) . (int)$m[2];
        if (isset($map[$key])) {
            return $key;
        }
    }
    return '';
}

function term_plan_detect_legacy_level_key($title) {
    if (term_plan_detect_level_key($title) !== '') {
        return '';
    }
    $title = trim((string)$title);
    if ($title === '' || !preg_match(term_plan_legacy_level_pattern(), $title, $m)) {
        return '';
    }
    return strtoupper($m[1]) . (int)$m[2];
}

function term_plan_ensure_tracks_schema(PDO $pdo) {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS term_plan_level_templates (
            id INT AUTO_INCREMENT PRIMARY KEY,
            level_key VARCHAR(16) NOT NULL,
            session_no TINYINT UNSIGNED NOT NULL,
            sort_order INT NOT NULL DEFAULT 0,
            title VARCHAR(500) NOT NULL,
            KEY idx_tp_tpl_level (level_key, session_no)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS term_plan_meta (
            k VARCHAR(64) NOT NULL PRIMARY KEY,
            v VARCHAR(191) NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
}

function term_plan_meta_get(PDO $pdo, $key) {
    $st = $pdo->prepare("SELECT v FROM term_plan_meta WHERE k = ? LIMIT 1");
    $st->execute([(string)$key]);
    $v = $st->fetchColumn();
    return $v === false ? '' : (string)$v;
}

function term_plan_meta_set(PDO $pdo, $key, $value) {
    $st = $pdo->prepare("
        INSERT INTO term_plan_meta (k, v) VALUES (?, ?)
        ON DUPLICATE KEY UPDATE v = ?
    ");
    $val = (string)$value;
    $st->execute([(string)$key, $val, $val]);
}

function term_plan_source_fee_from_index(array $by_compact, $source_name) {
    $want = term_plan_level_compact_key($source_name);
    if ($want !== '' && isset($by_compact[$want])) {
        return (int)$by_compact[$want]['fee'];
    }
    if (!preg_match('/^([A-Za-z]+)(\d+)$/', preg_replace('/\s+/', '', (string)$source_name), $m)) {
        return null;
    }
    $fam = strtoupper($m[1]);
    $n = (int)$m[2];
    $candidates = [];
    foreach ($by_compact as $row) {
        $name = preg_replace('/\s+/', '', (string)($row['name'] ?? ''));
        if (preg_match('/^' . preg_quote($fam, '/') . '(\d+)$/i', $name, $mm)) {
            $candidates[(int)$mm[1]] = (int)$row['fee'];
        }
    }
    if (!$candidates) {
        return null;
    }
    if (isset($candidates[$n])) {
        return $candidates[$n];
    }
    $best = null;
    $best_dist = 1000;
    foreach ($candidates as $num => $fee) {
        $dist = abs($num - $n);
        if ($dist < $best_dist || ($dist === $best_dist && $num < $best)) {
            $best_dist = $dist;
            $best = $num;
        }
    }
    return $best === null ? null : $candidates[$best];
}

function term_plan_seed_accounting_levels() {
    $created = [];
    $skipped = [];
    $missing_fee = [];
    try {
        if (!function_exists('accounting_pdo')) {
            require_once __DIR__ . '/accounting.php';
        }
        $acc = accounting_pdo();
        $rows = $acc->query('SELECT id, name, fee FROM levels')->fetchAll(PDO::FETCH_ASSOC);
        $by_compact = [];
        foreach ($rows as $row) {
            $ck = term_plan_level_compact_key($row['name'] ?? '');
            if ($ck !== '') {
                $by_compact[$ck] = $row;
            }
        }
        $ins = $acc->prepare('INSERT INTO levels (name, fee) VALUES (?, ?)');
        foreach (term_plan_new_level_map() as $new => $old) {
            $ck = term_plan_level_compact_key($new);
            if ($ck !== '' && isset($by_compact[$ck])) {
                $skipped[] = $new;
                continue;
            }
            $fee = term_plan_source_fee_from_index($by_compact, $old);
            if ($fee === null) {
                $missing_fee[] = $new . '←' . $old;
                continue;
            }
            $ins->execute([$new, $fee]);
            $by_compact[$ck] = ['name' => $new, 'fee' => $fee];
            $created[] = $new;
        }
    } catch (Throwable $e) {
        error_log('term_plan seed levels: ' . $e->getMessage());
        return [
            'created' => $created,
            'skipped' => $skipped,
            'missing_fee' => $missing_fee,
            'error' => $e->getMessage(),
        ];
    }
    return [
        'created' => $created,
        'skipped' => $skipped,
        'missing_fee' => $missing_fee,
        'error' => '',
    ];
}

function term_plan_seed_level_templates(PDO $pdo) {
    $data = term_plan_tracks_data();
    $pdo->exec("DELETE FROM term_plan_level_templates");
    $ins = $pdo->prepare("
        INSERT INTO term_plan_level_templates (level_key, session_no, sort_order, title)
        VALUES (?, ?, ?, ?)
    ");
    $n = 0;
    foreach ($data as $level => $block) {
        $sessions = $block['sessions'] ?? [];
        foreach ($sessions as $sno => $titles) {
            $order = 1;
            foreach ((array)$titles as $title) {
                $title = trim((string)$title);
                if ($title === '') {
                    continue;
                }
                $ins->execute([(string)$level, (int)$sno, $order, $title]);
                $order++;
                $n++;
            }
        }
    }
    return $n;
}

function term_plan_apply_level_template(PDO $pdo, $class_id, $title = '', $user_id = 0, array $class = []) {
    term_plan_ensure_schema($pdo);
    $class_id = (int)$class_id;
    if ($class_id < 1) {
        return false;
    }
    if ($title === '') {
        $title = (string)($class['title'] ?? '');
    }
    if ($title === '' || !$class) {
        $st = $pdo->prepare("SELECT id, title, class_type FROM classes WHERE id = ? AND IFNULL(is_deleted, 0) = 0 LIMIT 1");
        $st->execute([$class_id]);
        $found = $st->fetch(PDO::FETCH_ASSOC);
        if (!$found) {
            return false;
        }
        $class = $found;
        if ($title === '') {
            $title = (string)$found['title'];
        }
    }
    $key = term_plan_detect_level_key($title);
    if ($key === '') {
        return false;
    }
    $data = term_plan_tracks_data();
    $sessions = $data[$key]['sessions'] ?? [];
    if (!$sessions) {
        return false;
    }
    $cnt = $pdo->prepare("SELECT COUNT(*) FROM term_plan_tasks WHERE class_id = ? AND source = 'supervisor'");
    $cnt->execute([$class_id]);
    if ((int)$cnt->fetchColumn() > 0) {
        return false;
    }
    $total = term_plan_session_total($class);
    foreach ($sessions as $sno => $titles) {
        $sno = (int)$sno;
        if ($sno < 1 || $sno > $total) {
            continue;
        }
        term_plan_replace_supervisor_tasks($pdo, $class_id, $sno, (array)$titles, (int)$user_id);
    }
    return true;
}

function term_plan_apply_templates_to_open_classes(PDO $pdo, $user_id = 0) {
    if (!function_exists('term_sql_teaching')) {
        require_once __DIR__ . '/terms.php';
    }
    $sql = "
        SELECT c.id, c.title, c.class_type
        FROM classes c
        LEFT JOIN terms t ON c.term_id = t.id
        WHERE IFNULL(c.is_deleted, 0) = 0
          AND (" . term_sql_teaching('t') . " OR c.term_id IS NULL)
          AND IFNULL(c.is_archived, 0) = 0
          AND NOT EXISTS (
              SELECT 1 FROM term_plan_tasks tp
              WHERE tp.class_id = c.id AND tp.source = 'supervisor'
          )
    ";
    try {
        $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return 0;
    }
    $n = 0;
    foreach ($rows as $row) {
        if (term_plan_detect_level_key((string)$row['title']) === '') {
            continue;
        }
        if (term_plan_apply_level_template($pdo, (int)$row['id'], (string)$row['title'], $user_id, $row)) {
            $n++;
        }
    }
    return $n;
}

function term_plan_class_supervisor_titles(PDO $pdo, $class_id) {
    $st = $pdo->prepare("
        SELECT session_no, title
        FROM term_plan_tasks
        WHERE class_id = ? AND source = 'supervisor'
        ORDER BY session_no ASC, sort_order ASC, id ASC
    ");
    $st->execute([(int)$class_id]);
    $out = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $out[(int)$row['session_no']][] = (string)$row['title'];
    }
    return $out;
}

function term_plan_titles_equal_template(array $have, array $template_sessions) {
    if (!$have || !$template_sessions) {
        return false;
    }
    foreach ($template_sessions as $sno => $titles) {
        $want = array_values((array)$titles);
        $got = array_values($have[(int)$sno] ?? []);
        if ($want !== $got) {
            return false;
        }
    }
    foreach ($have as $sno => $titles) {
        if ($titles && !isset($template_sessions[$sno]) && !isset($template_sessions[(int)$sno]) && !isset($template_sessions[(string)$sno])) {
            return false;
        }
    }
    return true;
}

function term_plan_strip_new_plans_from_legacy_classes(PDO $pdo) {
    $data = term_plan_tracks_data();
    $old_to_new = array_flip(term_plan_new_level_map());
    try {
        $rows = $pdo->query("
            SELECT id, title FROM classes
            WHERE IFNULL(is_deleted, 0) = 0
        ")->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return 0;
    }
    $n = 0;
    $id_st = $pdo->prepare("SELECT id FROM term_plan_tasks WHERE class_id = ? AND source = 'supervisor'");
    $del_tasks = $pdo->prepare("DELETE FROM term_plan_tasks WHERE class_id = ? AND source = 'supervisor'");
    foreach ($rows as $row) {
        $legacy = term_plan_detect_legacy_level_key((string)$row['title']);
        if ($legacy === '') {
            continue;
        }
        $new_key = $old_to_new[$legacy] ?? '';
        if ($new_key === '' || empty($data[$new_key]['sessions'])) {
            continue;
        }
        $have = term_plan_class_supervisor_titles($pdo, (int)$row['id']);
        if (!term_plan_titles_equal_template($have, $data[$new_key]['sessions'])) {
            continue;
        }
        $id_st->execute([(int)$row['id']]);
        $ids = array_map('intval', $id_st->fetchAll(PDO::FETCH_COLUMN));
        if ($ids) {
            $pdo->exec('DELETE FROM term_plan_task_checks WHERE task_id IN (' . implode(',', $ids) . ')');
        }
        $del_tasks->execute([(int)$row['id']]);
        $n++;
    }
    return $n;
}

function term_plan_bootstrap_new_tracks(PDO $pdo) {
    static $boot = false;
    if ($boot) {
        return;
    }
    $boot = true;
    try {
        term_plan_ensure_schema($pdo);
        term_plan_ensure_tracks_schema($pdo);
        $ver = term_plan_tracks_version();
        if (term_plan_meta_get($pdo, 'tracks_version') !== $ver) {
            term_plan_seed_level_templates($pdo);
            $levels = term_plan_seed_accounting_levels();
            term_plan_meta_set($pdo, 'tracks_version', $ver);
            term_plan_meta_set($pdo, 'tracks_levels_created', (string)count($levels['created'] ?? []));
            if (!empty($levels['error'])) {
                term_plan_meta_set($pdo, 'tracks_levels_error', substr($levels['error'], 0, 180));
            } else {
                term_plan_meta_set($pdo, 'tracks_levels_error', '');
            }
            if (!empty($levels['missing_fee'])) {
                term_plan_meta_set($pdo, 'tracks_missing_fee', implode(',', $levels['missing_fee']));
            } else {
                term_plan_meta_set($pdo, 'tracks_missing_fee', '');
            }
            term_plan_strip_new_plans_from_legacy_classes($pdo);
        }
        term_plan_apply_templates_to_open_classes($pdo);
    } catch (Throwable $e) {
        error_log('term_plan bootstrap tracks: ' . $e->getMessage());
    }
}

function term_plan_tracks_status(PDO $pdo) {
    $keys = term_plan_new_level_keys();
    $n_tpl = 0;
    try {
        $n_tpl = (int)$pdo->query("SELECT COUNT(*) FROM term_plan_level_templates")->fetchColumn();
    } catch (Exception $e) {
        $n_tpl = 0;
    }
    return [
        'levels' => $keys,
        'level_count' => count($keys),
        'template_rows' => $n_tpl,
        'missing_fee' => term_plan_meta_get($pdo, 'tracks_missing_fee'),
        'levels_error' => term_plan_meta_get($pdo, 'tracks_levels_error'),
    ];
}
