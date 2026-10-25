<?php
/**
 * Quiz Marks columns, including extra-credit Bonus (quiz_10).
 * Class total and report-card final never display above 100.
 */

function quizzes_count() {
    return 10;
}

function quizzes_config() {
    return [
        1 => ['max' => 10, 'title' => 'Quiz 1 (S.4)', 'label' => 'Quiz 1', 'report' => 'کوییز ۱'],
        2 => ['max' => 10, 'title' => 'Quiz 2 (S.6)', 'label' => 'Quiz 2', 'report' => 'کوییز ۲'],
        3 => ['max' => 8, 'title' => 'R/W (S.7)', 'label' => 'R/W 1', 'report' => 'R/W'],
        4 => ['max' => 10, 'title' => 'Quiz 3 (S.8)', 'label' => 'Quiz 3', 'report' => 'کوییز ۳'],
        5 => ['max' => 10, 'title' => 'Quiz 4 (S.11)', 'label' => 'Quiz 4', 'report' => 'کوییز ۴'],
        6 => ['max' => 10, 'title' => 'Quiz 5 (S.13)', 'label' => 'Quiz 5', 'report' => 'کوییز ۵'],
        7 => ['max' => 8, 'title' => 'R/W (S.13)', 'label' => 'R/W 2', 'report' => 'R/W'],
        8 => ['max' => 4, 'title' => 'Presence', 'label' => 'حضور', 'report' => 'حضور'],
        9 => ['max' => 30, 'title' => 'Class Activity', 'label' => 'فعالیت کلاسی', 'report' => 'فعالیت'],
        10 => ['max' => 10, 'title' => 'Bonus', 'label' => 'Bonus', 'report' => 'Bonus'],
    ];
}

function quizzes_labels() {
    $out = [];
    foreach (quizzes_config() as $i => $q) {
        $out[$i] = $q['label'];
    }
    return $out;
}

function quizzes_sql_columns($alias = 'sq') {
    $prefix = $alias === '' ? '' : ($alias . '.');
    $cols = [];
    for ($i = 1; $i <= quizzes_count(); $i++) {
        $cols[] = $prefix . 'quiz_' . $i;
    }
    return implode(', ', $cols);
}

function quizzes_ensure_schema(PDO $pdo) {
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    try {
        $col = $pdo->query("SHOW COLUMNS FROM student_quizzes LIKE 'quiz_10'")->fetch();
        if (!$col) {
            $pdo->exec("ALTER TABLE student_quizzes ADD COLUMN quiz_10 DECIMAL(5,2) NULL DEFAULT NULL AFTER quiz_9");
        }
    } catch (Exception $e) {
        error_log('student_quizzes quiz_10: ' . $e->getMessage());
    }
}

function quizzes_filled($value) {
    return $value !== null && $value !== '';
}

function quizzes_parse_input($raw, $max) {
    $raw = trim((string)$raw);
    if ($raw === '') {
        return null;
    }
    $n = (int)round((float)$raw);
    if ($n < 0) {
        $n = 0;
    }
    $max = (int)$max;
    if ($n > $max) {
        $n = $max;
    }
    return $n;
}

function quizzes_builder_column($template_no) {
    $n = (int)$template_no;
    return ($n >= 1 && $n <= 7) ? $n : 0;
}

function quizzes_write_builder_score(PDO $pdo, $user_id, $class_id, $template_no, $score) {
    $col_n = quizzes_builder_column($template_no);
    $user_id = (int)$user_id;
    $class_id = (int)$class_id;
    if ($col_n < 1 || $user_id < 1 || $class_id < 1) {
        return false;
    }
    $cfg = quizzes_config();
    $max = (int)$cfg[$col_n]['max'];
    $n = quizzes_parse_input((string)$score, $max);
    if ($n === null) {
        return false;
    }
    $col = 'quiz_' . $col_n;
    $pdo->prepare("
        INSERT INTO student_quizzes (user_id, class_id, `$col`)
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE `$col` = VALUES(`$col`)
    ")->execute([$user_id, $class_id, $n]);
    try {
        $pdo->prepare("
            INSERT IGNORE INTO supervisor_scores (user_id, class_id, supervisor_score, is_submitted)
            VALUES (?, ?, NULL, 0)
        ")->execute([$user_id, $class_id]);
    } catch (Exception $e) {
        // report-card row is optional here
    }
    return true;
}

function quizzes_sync_builder_scores_for_class(PDO $pdo, $class_id) {
    $class_id = (int)$class_id;
    if ($class_id < 1) {
        return 0;
    }
    try {
        $st = $pdo->prepare("
            SELECT a.student_id, e.template_no, a.score_total
            FROM quiz_attempts a
            JOIN quiz_exams e ON e.id = a.exam_id
            WHERE e.class_id = ?
              AND a.score_total IS NOT NULL
              AND IFNULL(a.pending_count, 0) = 0
              AND e.template_no BETWEEN 1 AND 7
            ORDER BY a.submitted_at DESC, a.id DESC
        ");
        $st->execute([$class_id]);
        $best = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $sid = (int)$row['student_id'];
            $tpl = (int)$row['template_no'];
            if (isset($best[$sid][$tpl])) {
                continue;
            }
            $best[$sid][$tpl] = $row['score_total'];
        }
        if (!$best) {
            return 0;
        }
        $existing = [];
        $st = $pdo->prepare("SELECT user_id, " . quizzes_sql_columns('') . " FROM student_quizzes WHERE class_id = ?");
        $st->execute([$class_id]);
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $existing[(int)$row['user_id']] = $row;
        }
        $n = 0;
        foreach ($best as $sid => $cols) {
            foreach ($cols as $tpl => $score) {
                $cur = $existing[$sid]['quiz_' . $tpl] ?? null;
                if (quizzes_filled($cur)) {
                    continue;
                }
                if (quizzes_write_builder_score($pdo, $sid, $class_id, $tpl, $score)) {
                    $n++;
                }
            }
        }
        return $n;
    } catch (Exception $e) {
        error_log('quizzes_sync_builder: ' . $e->getMessage());
        return 0;
    }
}

function quizzes_grade_totals(array $grade) {
    $config = quizzes_config();
    $quizzes = [];
    foreach ($config as $i => $q) {
        $key = 'quiz_' . $i;
        $quizzes[$i] = array_key_exists($key, $grade) ? $grade[$key] : null;
    }
    $has = false;
    $raw_total = 0;
    foreach ($quizzes as $v) {
        if (quizzes_filled($v)) {
            $has = true;
            $raw_total += (int)$v;
        }
    }
    $quiz_total = 0;
    for ($i = 1; $i <= 7; $i++) {
        $quiz_total += (int)$quizzes[$i];
    }
    $class_total = min(100, $raw_total);
    $sup = quizzes_filled($grade['supervisor_score'] ?? null)
        ? (int)$grade['supervisor_score']
        : null;
    $final = min(100, (int)round(($class_total + (((int)$sup) * 2)) / 3));
    $ml = 0;
    $has_ml = quizzes_filled($grade['communication_dev'] ?? null);
    if ($has_ml) {
        $ml = (int)$grade['communication_dev'] + (int)$grade['learning_eng']
            + (int)$grade['learning_cons'] + (int)$grade['personal_growth'];
    }
    return [
        'quizzes' => $quizzes,
        'has_quiz_scores' => $has,
        'quiz_total' => $quiz_total,
        'class_total_raw' => $raw_total,
        'class_total' => $class_total,
        'bonus' => (int)$quizzes[10],
        'supervisor_score' => $sup,
        'final_score' => $final,
        'ml_score' => $ml,
        'has_mind_lingo' => $has_ml,
        'presence' => (int)$quizzes[8],
        'activity' => (int)$quizzes[9],
        'is_submitted' => !empty($grade['is_submitted']),
    ];
}
