<?php
/**
 * Class student roster helpers.
 * Saving a class must not wipe learners unless the editor actually loaded the list.
 */

function class_roster_int_ids($raw) {
    if (!is_array($raw)) {
        return [];
    }
    $out = [];
    foreach ($raw as $id) {
        $id = (int)$id;
        if ($id > 0) {
            $out[$id] = $id;
        }
    }
    return array_values($out);
}

function class_roster_ids(PDO $pdo, $class_id) {
    $class_id = (int)$class_id;
    if ($class_id <= 0) {
        return [];
    }
    try {
        $st = $pdo->prepare("
            SELECT user_id FROM class_students
            WHERE class_id = ?
              AND (is_deleted = 0 OR is_deleted IS NULL)
        ");
        $st->execute([$class_id]);
        return array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
    } catch (PDOException $e) {
        $st = $pdo->prepare('SELECT user_id FROM class_students WHERE class_id = ?');
        $st->execute([$class_id]);
        return array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
    }
}

function class_roster_replace(PDO $pdo, $class_id, array $student_ids) {
    $class_id = (int)$class_id;
    $student_ids = class_roster_int_ids($student_ids);
    $pdo->prepare('DELETE FROM class_students WHERE class_id = ?')->execute([$class_id]);
    if (!$student_ids) {
        return [];
    }
    $ins = $pdo->prepare('INSERT INTO class_students (class_id, user_id) VALUES (?, ?)');
    foreach ($student_ids as $uid) {
        $ins->execute([$class_id, $uid]);
    }
    return $student_ids;
}

function class_roster_should_replace($input) {
    if (!is_array($input) || !array_key_exists('student_ids', $input)) {
        return false;
    }
    if (!empty($input['roster_ready'])) {
        return true;
    }
    $ids = class_roster_int_ids($input['student_ids'] ?? []);
    return $ids !== [];
}

function class_roster_add_ids(array &$ids, $raw) {
    foreach ((array)$raw as $id) {
        $id = (int)$id;
        if ($id > 0) {
            $ids[$id] = $id;
        }
    }
}

function class_roster_history_user_ids(PDO $pdo, $class_id) {
    $class_id = (int)$class_id;
    if ($class_id <= 0) {
        return [];
    }
    $ids = [];
    $queries = [
        'SELECT DISTINCT user_id FROM attendance WHERE class_id = ? AND user_id > 0',
        'SELECT DISTINCT user_id FROM homework_status WHERE class_id = ? AND user_id > 0',
        'SELECT DISTINCT user_id FROM student_quizzes WHERE class_id = ? AND user_id > 0',
        'SELECT DISTINCT user_id FROM supervisor_scores WHERE class_id = ? AND user_id > 0',
        'SELECT DISTINCT student_id FROM quiz_attempts WHERE class_id = ? AND student_id > 0',
        'SELECT DISTINCT a.student_id FROM quiz_attempts a JOIN quiz_exams e ON e.id = a.exam_id WHERE e.class_id = ? AND a.student_id > 0',
        'SELECT DISTINCT student_id FROM assignment_submissions WHERE class_id = ? AND student_id > 0',
        'SELECT DISTINCT s.student_id FROM assignment_submissions s JOIN assignments a ON a.id = s.assignment_id WHERE a.class_id = ? AND s.student_id > 0',
        'SELECT DISTINCT student_id FROM mind_lingo WHERE class_id = ? AND student_id > 0',
        'SELECT DISTINCT user_id FROM attendance_sms_log WHERE class_id = ? AND user_id > 0',
        'SELECT DISTINCT user_id FROM attendance_late_sms_log WHERE class_id = ? AND user_id > 0',
    ];
    foreach ($queries as $sql) {
        try {
            $st = $pdo->prepare($sql);
            $st->execute([$class_id]);
            class_roster_add_ids($ids, $st->fetchAll(PDO::FETCH_COLUMN));
        } catch (PDOException $e) {
            continue;
        }
    }
    if (!$ids) {
        return [];
    }
    $place = implode(',', array_fill(0, count($ids), '?'));
    $params = array_values($ids);
    try {
        $st = $pdo->prepare("
            SELECT id FROM users
            WHERE id IN ($place) AND role = 'student' AND IFNULL(is_deleted, 0) = 0
        ");
        $st->execute($params);
        return array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
    } catch (PDOException $e) {
        $st = $pdo->prepare("
            SELECT id FROM users
            WHERE id IN ($place) AND role = 'student'
        ");
        $st->execute($params);
        return array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
    }
}

function class_roster_restore_if_empty(PDO $pdo, $class_id) {
    $class_id = (int)$class_id;
    $current = class_roster_ids($pdo, $class_id);
    if ($current) {
        return [
            'restored' => false,
            'count' => count($current),
            'ids' => $current,
        ];
    }
    $history = class_roster_history_user_ids($pdo, $class_id);
    if (!$history) {
        return [
            'restored' => false,
            'count' => 0,
            'ids' => [],
        ];
    }
    class_roster_replace($pdo, $class_id, $history);
    return [
        'restored' => true,
        'count' => count($history),
        'ids' => $history,
    ];
}

function class_roster_empty_teaching_ids(PDO $pdo) {
    if (!function_exists('term_sql_teaching')) {
        require_once __DIR__ . '/terms.php';
    }
    try {
        $sql = "
            SELECT c.id
            FROM classes c
            JOIN terms t ON t.id = c.term_id
            LEFT JOIN class_students cs
              ON cs.class_id = c.id
             AND (cs.is_deleted = 0 OR cs.is_deleted IS NULL)
            WHERE IFNULL(c.is_deleted, 0) = 0
              AND IFNULL(c.is_archived, 0) = 0
              AND " . term_sql_teaching('t') . "
            GROUP BY c.id
            HAVING COUNT(cs.user_id) = 0
        ";
        return array_map('intval', $pdo->query($sql)->fetchAll(PDO::FETCH_COLUMN));
    } catch (PDOException $e) {
        $sql = "
            SELECT c.id
            FROM classes c
            JOIN terms t ON t.id = c.term_id
            LEFT JOIN class_students cs ON cs.class_id = c.id
            WHERE IFNULL(c.is_deleted, 0) = 0
              AND IFNULL(c.is_archived, 0) = 0
              AND " . term_sql_teaching('t') . "
            GROUP BY c.id
            HAVING COUNT(cs.user_id) = 0
        ";
        return array_map('intval', $pdo->query($sql)->fetchAll(PDO::FETCH_COLUMN));
    }
}

function class_roster_restore_empty_teaching(PDO $pdo) {
    $out = [];
    try {
        $ids = class_roster_empty_teaching_ids($pdo);
    } catch (Throwable $e) {
        return [];
    }
    foreach ($ids as $class_id) {
        try {
            $result = class_roster_restore_if_empty($pdo, $class_id);
            if (!empty($result['restored'])) {
                $out[] = [
                    'class_id' => $class_id,
                    'count' => (int)$result['count'],
                ];
            }
        } catch (Throwable $e) {
            continue;
        }
    }
    return $out;
}
