<?php
/**
 * Student take-quiz + teacher attempt results.
 * Graded scores are copied into Quiz Marks columns quiz_1..quiz_7.
 */

if (!function_exists('quiz_builder_ensure_schema')) {
    require_once __DIR__ . '/quiz-builder.php';
}
if (!function_exists('term_sql_teaching')) {
    require_once __DIR__ . '/terms.php';
}

function quiz_student_class_sql() {
    return "c.is_deleted = 0 AND IFNULL(c.is_archived, 0) = 0 AND " . term_sql_teaching('t');
}

function quiz_student_ensure_schema(PDO $pdo) {
    if (function_exists('quiz_builder_ensure_attempts_table')) {
        quiz_builder_ensure_attempts_table($pdo);
    }
}

function quiz_student_fail_fast(PDO $pdo) {
    try {
        $pdo->exec('SET SESSION lock_wait_timeout = 3');
    } catch (Exception $e) {
        // ignore
    }
    try {
        $pdo->exec('SET SESSION innodb_lock_wait_timeout = 3');
    } catch (Exception $e) {
        // ignore
    }
}

function quiz_student_in_class(PDO $pdo, $student_id, $class_id) {
    $st = $pdo->prepare("
        SELECT c.id
        FROM classes c
        JOIN class_students cs ON cs.class_id = c.id
        JOIN terms t ON t.id = c.term_id
        WHERE c.id = ? AND cs.user_id = ? AND " . quiz_student_class_sql() . "
        LIMIT 1
    ");
    $st->execute([(int)$class_id, (int)$student_id]);
    return (bool)$st->fetchColumn();
}

function quiz_student_open_exam(PDO $pdo, $exam_id, $student_id) {
    try {
        quiz_student_fail_fast($pdo);
        quiz_student_ensure_schema($pdo);
        $st = $pdo->prepare("
            SELECT e.*, c.title AS class_title
            FROM quiz_exams e
            JOIN classes c ON c.id = e.class_id
            JOIN class_students cs ON cs.class_id = c.id AND cs.user_id = ?
            JOIN terms t ON t.id = c.term_id
            WHERE e.id = ? AND e.status = 'ready' AND " . quiz_student_class_sql() . "
            LIMIT 1
        ");
        $st->execute([(int)$student_id, (int)$exam_id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    } catch (Exception $e) {
        error_log('quiz_student_open_exam: ' . $e->getMessage());
        return null;
    }
}

function quiz_student_list_sql($with_attempts) {
    $select = "
        SELECT e.id, e.class_id, e.teacher_id, e.book_id, e.template_no, e.session_no,
               e.difficulty, e.page_from, e.page_to, e.questions_json, e.status,
               e.created_at, e.updated_at, c.title AS class_title
    ";
    if ($with_attempts) {
        $select .= ",
               a.id AS attempt_id, a.status AS attempt_status, a.score_total, a.score_max,
               a.pending_count, a.submitted_at";
    } else {
        $select .= ",
               NULL AS attempt_id, '' AS attempt_status, NULL AS score_total, NULL AS score_max,
               0 AS pending_count, NULL AS submitted_at";
    }
    $from = "
        FROM quiz_exams e
        JOIN classes c ON c.id = e.class_id
        JOIN class_students cs ON cs.class_id = c.id AND cs.user_id = ?
        JOIN terms t ON t.id = c.term_id
    ";
    if ($with_attempts) {
        $from .= " LEFT JOIN quiz_attempts a ON a.exam_id = e.id AND a.student_id = ?";
    }
    return $select . $from . "
        WHERE e.status = 'ready' AND " . quiz_student_class_sql() . "
        ORDER BY c.title ASC, e.template_no ASC, e.id DESC
    ";
}

function quiz_student_list(PDO $pdo, $student_id) {
    quiz_student_fail_fast($pdo);
    quiz_student_ensure_schema($pdo);
    $sid = (int)$student_id;
    try {
        $st = $pdo->prepare(quiz_student_list_sql(true));
        $st->execute([$sid, $sid]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log('quiz_student_list: ' . $e->getMessage());
        try {
            $st = $pdo->prepare(quiz_student_list_sql(false));
            $st->execute([$sid]);
            return $st->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e2) {
            error_log('quiz_student_list fallback: ' . $e2->getMessage());
            return [];
        }
    }
}

function quiz_student_open_count(PDO $pdo, $student_id) {
    try {
        $n = 0;
        foreach (quiz_student_list($pdo, $student_id) as $row) {
            $st = (string)($row['attempt_status'] ?? '');
            if ($st === '' || $st === 'in_progress') {
                $n++;
            }
        }
        return $n;
    } catch (Exception $e) {
        error_log('quiz_student_open_count: ' . $e->getMessage());
        return 0;
    }
}

function quiz_student_find_attempt(PDO $pdo, $exam_id, $student_id) {
    try {
        $st = $pdo->prepare("SELECT * FROM quiz_attempts WHERE exam_id = ? AND student_id = ? LIMIT 1");
        $st->execute([(int)$exam_id, (int)$student_id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    } catch (Exception $e) {
        error_log('quiz_student_find_attempt: ' . $e->getMessage());
        return null;
    }
}

function quiz_student_find_attempt_id(PDO $pdo, $attempt_id, $teacher_id) {
    $st = $pdo->prepare("
        SELECT a.*, e.teacher_id, e.template_no, e.questions_json, e.class_id AS exam_class_id
        FROM quiz_attempts a
        JOIN quiz_exams e ON e.id = a.exam_id
        WHERE a.id = ? AND e.teacher_id = ?
        LIMIT 1
    ");
    $st->execute([(int)$attempt_id, (int)$teacher_id]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function quiz_student_decode_answers($raw) {
    $data = quiz_builder_decode_payload($raw);
    $src = [];
    if (isset($data['answers']) && is_array($data['answers'])) {
        $src = $data['answers'];
    } elseif (is_array($data)) {
        $src = $data;
    }
    $answers = [];
    foreach ($src as $k => $v) {
        $k = (string)$k;
        if ($k === 'answers' || $k === 'items' || $k === 'teacher_awards' || $k === 'questions') {
            continue;
        }
        if (is_scalar($v)) {
            $answers[$k] = trim((string)$v);
        }
    }
    return $answers;
}

function quiz_student_norm($s) {
    $s = trim((string)$s);
    if (function_exists('mb_strtolower')) {
        $s = mb_strtolower($s, 'UTF-8');
    } else {
        $s = strtolower($s);
    }
    $s = strtr($s, ['’' => "'", '‘' => "'", '`' => "'", '“' => '"', '”' => '"']);
    $s = preg_replace('/\s+/u', ' ', $s);
    $s = preg_replace('/^(the|a|an)\s+/u', '', $s);
    $s = preg_replace('/[^\p{L}\p{N}\s\']+/u', '', $s);
    return trim((string)$s);
}

function quiz_student_expand_key($answer, array $options) {
    $a = trim((string)$answer);
    $map = [
        'A' => 0, 'B' => 1, 'C' => 2, 'D' => 3,
        'a' => 0, 'b' => 1, 'c' => 2, 'd' => 3,
        '1' => 0, '2' => 1, '3' => 2, '4' => 3,
        'true' => true, 'false' => false, 't' => true, 'f' => false,
        'yes' => true, 'no' => false,
    ];
    if (isset($map[$a]) && is_int($map[$a]) && isset($options[$map[$a]])) {
        return (string)$options[$map[$a]];
    }
    if (preg_match('/^[A-D][\)\.:\-]\s*(.+)$/i', $a, $m)) {
        return trim($m[1]);
    }
    return $a;
}

function quiz_student_tf_bool($s) {
    $n = quiz_student_norm($s);
    if (in_array($n, ['true', 't', 'yes', '1'], true)) {
        return 'true';
    }
    if (in_array($n, ['false', 'f', 'no', '0'], true)) {
        return 'false';
    }
    return $n;
}

function quiz_student_answers_match($type, $given, $correct, array $options = []) {
    $given = trim((string)$given);
    $correct = trim((string)$correct);
    if ($correct === '') {
        return $given !== '';
    }
    if ($type === 'tf') {
        return quiz_student_tf_bool($given) !== '' && quiz_student_tf_bool($given) === quiz_student_tf_bool($correct);
    }
    $given_exp = quiz_student_expand_key($given, $options);
    $correct_exp = quiz_student_expand_key($correct, $options);
    $g = quiz_student_norm($given_exp);
    $c = quiz_student_norm($correct_exp);
    if ($g === '' || $c === '') {
        return false;
    }
    if ($g === $c) {
        return true;
    }
    if ($type === 'short' || $type === 'fill') {
        $alts = preg_split('/\s*(\/|,|;|\bor\b)\s*/i', $correct_exp);
        foreach ($alts as $alt) {
            $an = quiz_student_norm($alt);
            if ($an !== '' && $g === $an) {
                return true;
            }
        }
        if ($type === 'short' && strlen($c) >= 4 && (strpos($g, $c) !== false || strpos($c, $g) !== false)) {
            return true;
        }
    }
    return false;
}

function quiz_student_grade(array $questions, array $answers, array $teacher_awards = []) {
    $items = [];
    $raw = 0.0;
    $raw_max = 0.0;
    $pending = 0;
    foreach ($questions as $q) {
        if (!is_array($q)) {
            continue;
        }
        $qid = (string)($q['id'] ?? '');
        if ($qid === '') {
            continue;
        }
        $max = max(1, (int)($q['points'] ?? 1));
        $raw_max += $max;
        $given = isset($answers[$qid]) ? trim((string)$answers[$qid]) : '';
        $type = (string)($q['type'] ?? 'mc');
        $options = isset($q['options']) && is_array($q['options']) ? $q['options'] : [];
        if ($type === 'writing') {
            if (array_key_exists($qid, $teacher_awards) && $teacher_awards[$qid] !== null && $teacher_awards[$qid] !== '') {
                $got = max(0, min($max, (float)$teacher_awards[$qid]));
                $items[$qid] = [
                    'ok' => null,
                    'awarded' => $got,
                    'max' => $max,
                    'pending' => false,
                    'given' => $given,
                    'writing' => true,
                ];
                $raw += $got;
            } else {
                $pending++;
                $items[$qid] = [
                    'ok' => null,
                    'awarded' => null,
                    'max' => $max,
                    'pending' => true,
                    'given' => $given,
                    'writing' => true,
                ];
            }
            continue;
        }
        $ok = quiz_student_answers_match($type, $given, (string)($q['answer'] ?? ''), $options);
        $got = $ok ? $max : 0;
        $raw += $got;
        $items[$qid] = [
            'ok' => $ok,
            'awarded' => $got,
            'max' => $max,
            'pending' => false,
            'given' => $given,
            'writing' => false,
        ];
    }
    return [
        'items' => $items,
        'raw' => $raw,
        'raw_max' => $raw_max,
        'pending' => $pending,
    ];
}

function quiz_student_scale($raw, $raw_max, $mark) {
    $mark = (int)$mark;
    if ($mark < 1) {
        $mark = 1;
    }
    if ((float)$raw_max <= 0) {
        return 0;
    }
    return (int)round(((float)$raw / (float)$raw_max) * $mark);
}

function quiz_student_questions_for_take(array $exam) {
    $pub = quiz_builder_public_exam($exam);
    $out = [];
    foreach ($pub['questions'] as $q) {
        $out[] = [
            'id' => $q['id'],
            'skill' => $q['skill'],
            'type' => $q['type'],
            'prompt' => $q['prompt'],
            'options' => $q['options'],
            'points' => $q['points'],
        ];
    }
    return $out;
}

function quiz_student_limit_seconds(array $exam) {
    $tpl = quiz_builder_template((int)($exam['template_no'] ?? 0));
    $minutes = (int)($tpl['minutes'] ?? 10);
    return max(60, $minutes * 60);
}

function quiz_student_remaining(array $attempt, array $exam) {
    $limit = quiz_student_limit_seconds($exam);
    $start = strtotime((string)($attempt['started_at'] ?? '')) ?: time();
    return max(0, $limit - (time() - $start));
}

function quiz_student_public_card(array $row) {
    $pub = quiz_builder_public_exam($row);
    $attempt_status = (string)($row['attempt_status'] ?? '');
    $writing = !empty($pub['is_writing']);
    $cta = $writing ? 'شروع رایتینگ' : 'شروع آزمون';
    if ($attempt_status === 'in_progress') {
        $cta = $writing ? 'ادامه نوشتن' : 'ادامه آزمون';
    } elseif ($attempt_status === 'submitted' || $attempt_status === 'graded') {
        $cta = 'مشاهده نتیجه';
    }
    return [
        'id' => $pub['id'],
        'class_id' => $pub['class_id'],
        'class_title' => (string)($row['class_title'] ?? ''),
        'template_no' => $pub['template_no'],
        'template_label' => $pub['template_label'],
        'session_no' => $pub['session_no'],
        'mark' => $pub['mark'],
        'minutes' => $pub['minutes'],
        'question_count' => count($pub['questions']),
        'is_writing' => $writing,
        'attempt_status' => $attempt_status,
        'attempt_status_label' => $attempt_status !== '' ? quiz_builder_status_label($attempt_status) : 'جدید',
        'score_total' => $row['score_total'] === null ? null : (float)$row['score_total'],
        'score_max' => $row['score_max'] === null ? null : (float)$row['score_max'],
        'pending_count' => (int)($row['pending_count'] ?? 0),
        'cta' => $cta,
    ];
}

function quiz_student_public_attempt(array $attempt, array $exam, $review = false) {
    $tpl = quiz_builder_template((int)($exam['template_no'] ?? 0));
    $answers = quiz_student_decode_answers($attempt['answers_json'] ?? '');
    $status = (string)($attempt['status'] ?? 'in_progress');
    $done = in_array($status, ['submitted', 'graded'], true);
    $payload = [
        'attempt_id' => (int)$attempt['id'],
        'status' => $status,
        'status_label' => quiz_builder_status_label($status),
        'started_at' => (string)($attempt['started_at'] ?? ''),
        'submitted_at' => (string)($attempt['submitted_at'] ?? ''),
        'remaining_sec' => $done ? 0 : quiz_student_remaining($attempt, $exam),
        'limit_sec' => quiz_student_limit_seconds($exam),
        'answers' => $answers,
        'score_total' => $attempt['score_total'] === null ? null : (float)$attempt['score_total'],
        'score_max' => $attempt['score_max'] === null ? null : (float)$attempt['score_max'],
        'pending_count' => (int)($attempt['pending_count'] ?? 0),
        'mark' => (int)($tpl['mark'] ?? 0),
    ];
    if ($review && $done) {
        $result = quiz_builder_decode_payload($attempt['result_json'] ?? '');
        $items = isset($result['items']) && is_array($result['items']) ? $result['items'] : [];
        $full = quiz_builder_public_exam($exam);
        $qs = [];
        foreach ($full['questions'] as $q) {
            $qid = (string)$q['id'];
            $item = $items[$qid] ?? [];
            $qs[] = [
                'id' => $qid,
                'skill' => $q['skill'],
                'type' => $q['type'],
                'prompt' => $q['prompt'],
                'options' => $q['options'],
                'points' => $q['points'],
                'answer' => $q['answer'],
                'given' => (string)($item['given'] ?? ($answers[$qid] ?? '')),
                'ok' => array_key_exists('ok', $item) ? $item['ok'] : null,
                'awarded' => $item['awarded'] ?? null,
                'pending' => !empty($item['pending']),
                'writing' => !empty($item['writing']) || $q['type'] === 'writing',
            ];
        }
        $payload['questions'] = $qs;
        $payload['passage'] = $full['passage'];
        $payload['listening_script'] = $full['listening_script'];
        $payload['listening_repetition'] = $full['listening_repetition'];
    }
    return $payload;
}

function quiz_student_take_payload(array $exam, array $attempt) {
    $full = quiz_builder_public_exam($exam);
    $done = in_array((string)$attempt['status'], ['submitted', 'graded'], true);
    $out = [
        'exam' => [
            'id' => $full['id'],
            'class_id' => $full['class_id'],
            'class_title' => (string)($exam['class_title'] ?? ''),
            'template_no' => $full['template_no'],
            'template_label' => $full['template_label'],
            'session_no' => $full['session_no'],
            'is_writing' => !empty($full['is_writing']),
            'mark' => $full['mark'],
            'minutes' => $full['minutes'],
            'passage' => $full['passage'],
            'listening_script' => $full['listening_script'],
            'listening_repetition' => $full['listening_repetition'],
            'questions' => $done ? [] : quiz_student_questions_for_take($exam),
        ],
        'attempt' => quiz_student_public_attempt($attempt, $exam, $done),
    ];
    if ($done) {
        $out['exam']['questions'] = $out['attempt']['questions'] ?? [];
    }
    return $out;
}

function quiz_student_upsert_attempt(PDO $pdo, array $exam, $student_id) {
    $existing = quiz_student_find_attempt($pdo, (int)$exam['id'], $student_id);
    if ($existing) {
        return $existing;
    }
    $now = date('Y-m-d H:i:s');
    $pdo->prepare("
        INSERT INTO quiz_attempts (exam_id, class_id, student_id, status, started_at, answers_json)
        VALUES (?, ?, ?, 'in_progress', ?, ?)
    ")->execute([
        (int)$exam['id'],
        (int)$exam['class_id'],
        (int)$student_id,
        $now,
        json_encode(['answers' => new stdClass()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ]);
    return quiz_student_find_attempt($pdo, (int)$exam['id'], $student_id);
}

function quiz_student_encode_answers(array $answers) {
    return json_encode(
        ['answers' => $answers ? $answers : new stdClass()],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
}

function quiz_student_store_answers(PDO $pdo, array $attempt, array $answers) {
    $pdo->prepare("UPDATE quiz_attempts SET answers_json = ? WHERE id = ?")
        ->execute([quiz_student_encode_answers($answers), (int)$attempt['id']]);
}

function quiz_student_apply_grade(PDO $pdo, array $attempt, array $exam, array $answers, array $teacher_awards = []) {
    $full = quiz_builder_public_exam($exam);
    $grade = quiz_student_grade($full['questions'], $answers, $teacher_awards);
    $tpl = quiz_builder_template((int)$exam['template_no']);
    $mark = (int)($tpl['mark'] ?? 10);
    $pending = (int)$grade['pending'];
    $status = $pending > 0 ? 'submitted' : 'graded';
    $score_total = $pending > 0 ? null : quiz_student_scale($grade['raw'], $grade['raw_max'], $mark);
    $result = [
        'items' => $grade['items'],
        'teacher_awards' => $teacher_awards,
        'raw' => $grade['raw'],
        'raw_max' => $grade['raw_max'],
    ];
    $pdo->prepare("
        UPDATE quiz_attempts
        SET status = ?, submitted_at = IFNULL(submitted_at, ?), answers_json = ?, result_json = ?,
            score_raw = ?, score_max_raw = ?, score_total = ?, score_max = ?, pending_count = ?
        WHERE id = ?
    ")->execute([
        $status,
        date('Y-m-d H:i:s'),
        quiz_student_encode_answers($answers),
        json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        $grade['raw'],
        $grade['raw_max'],
        $score_total,
        $mark,
        $pending,
        (int)$attempt['id'],
    ]);
    $row = quiz_student_find_attempt($pdo, (int)$exam['id'], (int)$attempt['student_id']);
    if ($row) {
        quiz_student_push_marks($pdo, $exam, $row);
    }
    return $row;
}

function quiz_student_push_marks(PDO $pdo, array $exam, array $attempt) {
    if ($attempt['score_total'] === null || $attempt['score_total'] === '') {
        return;
    }
    if ((int)($attempt['pending_count'] ?? 0) > 0) {
        return;
    }
    if (!function_exists('quizzes_write_builder_score')) {
        require_once __DIR__ . '/quizzes.php';
    }
    $class_id = (int)($exam['class_id'] ?? $attempt['class_id'] ?? 0);
    try {
        quizzes_write_builder_score(
            $pdo,
            (int)$attempt['student_id'],
            $class_id,
            (int)($exam['template_no'] ?? 0),
            $attempt['score_total']
        );
    } catch (Exception $e) {
        error_log('quiz_student_push_marks: ' . $e->getMessage());
    }
}

function quiz_student_submit(PDO $pdo, array $attempt, array $exam, array $answers) {
    $prev = quiz_builder_decode_payload($attempt['result_json'] ?? '');
    $awards = isset($prev['teacher_awards']) && is_array($prev['teacher_awards']) ? $prev['teacher_awards'] : [];
    return quiz_student_apply_grade($pdo, $attempt, $exam, $answers, $awards);
}

function quiz_student_maybe_expire(PDO $pdo, array $attempt, array $exam) {
    if ((string)($attempt['status'] ?? '') !== 'in_progress') {
        return $attempt;
    }
    if (quiz_student_remaining($attempt, $exam) > 0) {
        return $attempt;
    }
    $answers = quiz_student_decode_answers($attempt['answers_json'] ?? '');
    return quiz_student_submit($pdo, $attempt, $exam, $answers);
}

function quiz_student_sanitize_answers(array $exam, $raw) {
    if (!is_array($raw)) {
        $raw = [];
    }
    $allowed = [];
    foreach (quiz_builder_public_exam($exam)['questions'] as $q) {
        $allowed[(string)$q['id']] = true;
    }
    $out = [];
    foreach ($raw as $k => $v) {
        $k = (string)$k;
        if (!isset($allowed[$k])) {
            continue;
        }
        $out[$k] = trim((string)$v);
        if (function_exists('mb_substr')) {
            $out[$k] = mb_substr($out[$k], 0, 4000, 'UTF-8');
        } else {
            $out[$k] = substr($out[$k], 0, 4000);
        }
    }
    return $out;
}

function quiz_student_class_roster(PDO $pdo, $class_id) {
    $st = $pdo->prepare("
        SELECT u.id, u.full_name, u.username
        FROM users u
        JOIN class_students cs ON cs.user_id = u.id
        WHERE cs.class_id = ?
        ORDER BY u.full_name ASC
    ");
    $st->execute([(int)$class_id]);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

function quiz_student_exam_results(PDO $pdo, array $exam) {
    $roster = quiz_student_class_roster($pdo, (int)$exam['class_id']);
    $st = $pdo->prepare("SELECT * FROM quiz_attempts WHERE exam_id = ?");
    $st->execute([(int)$exam['id']]);
    $by_student = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $by_student[(int)$row['student_id']] = $row;
    }
    $out = [];
    $submitted = 0;
    $sum = 0.0;
    $graded_n = 0;
    foreach ($roster as $stu) {
        $att = $by_student[(int)$stu['id']] ?? null;
        $status = $att ? (string)$att['status'] : '';
        if ($att && in_array($status, ['submitted', 'graded'], true)) {
            $submitted++;
        }
        if ($att && $att['score_total'] !== null) {
            $sum += (float)$att['score_total'];
            $graded_n++;
        }
        $writing = '';
        if ($att) {
            $answers = quiz_student_decode_answers($att['answers_json'] ?? '');
            $full = quiz_builder_public_exam($exam);
            foreach ($full['questions'] as $q) {
                if (($q['type'] ?? '') === 'writing') {
                    $writing = (string)($answers[$q['id']] ?? '');
                    break;
                }
            }
        }
        $out[] = [
            'student_id' => (int)$stu['id'],
            'full_name' => (string)$stu['full_name'],
            'attempt_id' => $att ? (int)$att['id'] : 0,
            'status' => $status,
            'status_label' => $status !== '' ? quiz_builder_status_label($status) : 'شروع نکرده',
            'score_total' => $att && $att['score_total'] !== null ? (float)$att['score_total'] : null,
            'score_max' => $att && $att['score_max'] !== null ? (float)$att['score_max'] : null,
            'pending_count' => $att ? (int)$att['pending_count'] : 0,
            'submitted_at' => $att ? (string)$att['submitted_at'] : '',
            'writing' => $writing,
        ];
    }
    $tpl = quiz_builder_template((int)$exam['template_no']);
    return [
        'students' => $out,
        'total' => count($roster),
        'submitted' => $submitted,
        'average' => $graded_n > 0 ? round($sum / $graded_n, 1) : null,
        'mark' => (int)($tpl['mark'] ?? 0),
        'has_writing' => (($tpl['kinds'] ?? '') === 'writing'),
    ];
}

function quiz_student_grade_writing(PDO $pdo, array $attempt_row, $score) {
    $exam = [
        'id' => (int)$attempt_row['exam_id'],
        'template_no' => (int)$attempt_row['template_no'],
        'questions_json' => $attempt_row['questions_json'],
        'class_id' => (int)$attempt_row['exam_class_id'],
        'status' => 'ready',
        'book_id' => 0,
        'difficulty' => '',
        'page_from' => 0,
        'page_to' => 0,
    ];
    $full = quiz_builder_public_exam($exam);
    $tpl = quiz_builder_template((int)$exam['template_no']);
    $mark = (int)($tpl['mark'] ?? 8);
    $score = max(0, min($mark, (int)round((float)$score)));
    $answers = quiz_student_decode_answers($attempt_row['answers_json'] ?? '');
    $awards = [];
    $writing_ids = [];
    $writing_points = 0;
    foreach ($full['questions'] as $q) {
        if (($q['type'] ?? '') === 'writing') {
            $writing_ids[] = $q['id'];
            $writing_points += max(1, (int)$q['points']);
        }
    }
    if (!$writing_ids) {
        throw new Exception('این آزمون رایتینگ ندارد.');
    }
    if (count($writing_ids) === 1 && $writing_points === 1 && $mark > 1) {
        $pdo->prepare("
            UPDATE quiz_attempts
            SET status = 'graded', pending_count = 0, score_total = ?, score_max = ?
            WHERE id = ?
        ")->execute([$score, $mark, (int)$attempt_row['id']]);
        $result = quiz_builder_decode_payload($attempt_row['result_json'] ?? '');
        $qid = $writing_ids[0];
        if (!isset($result['items']) || !is_array($result['items'])) {
            $result['items'] = [];
        }
        $result['items'][$qid] = [
            'ok' => null,
            'awarded' => $score,
            'max' => $mark,
            'pending' => false,
            'given' => $answers[$qid] ?? '',
            'writing' => true,
        ];
        $result['teacher_awards'] = [$qid => $score];
        $pdo->prepare("UPDATE quiz_attempts SET result_json = ? WHERE id = ?")
            ->execute([json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), (int)$attempt_row['id']]);
        $graded = quiz_student_find_attempt($pdo, (int)$attempt_row['exam_id'], (int)$attempt_row['student_id']);
        if ($graded) {
            quiz_student_push_marks($pdo, $exam, $graded);
        }
        return;
    }
    $share = $writing_points > 0 ? ($score / $mark) * $writing_points : $score;
    foreach ($writing_ids as $qid) {
        $awards[$qid] = $share / max(1, count($writing_ids));
    }
    quiz_student_apply_grade($pdo, $attempt_row, $exam, $answers, $awards);
}
