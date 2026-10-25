<?php
/**
 * Consultant role: Mind Lingo search + Students Information + private remarks.
 */

function consultant_ensure_schema(PDO $pdo) {
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    consultant_ensure_role_column($pdo);
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS consultant_remarks (
            id INT AUTO_INCREMENT PRIMARY KEY,
            student_id INT NOT NULL,
            consultant_id INT NOT NULL,
            body TEXT NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_cr_student (student_id),
            KEY idx_cr_consultant (consultant_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
}

function consultant_ensure_role_column(PDO $pdo) {
    try {
        $col = $pdo->query("SHOW COLUMNS FROM users LIKE 'role'")->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return;
    }
    if (!$col) {
        return;
    }
    $type = (string)($col['Type'] ?? '');
    if (stripos($type, 'enum') === false || stripos($type, 'consultant') !== false) {
        return;
    }
    if (!preg_match('/^enum\((.*)\)$/i', $type, $m)) {
        return;
    }
    $sql = "ALTER TABLE users MODIFY COLUMN role ENUM({$m[1]},'consultant') NOT NULL";
    $default = $col['Default'] ?? null;
    if ($default !== null && $default !== '') {
        $sql .= ' DEFAULT ' . $pdo->quote($default);
    }
    try {
        $pdo->exec($sql);
    } catch (PDOException $e) {
        error_log('consultant role enum: ' . $e->getMessage());
    }
}

function consultant_schedule_label($schedule_type, $schedule) {
    $when = trim((string)$schedule);
    $kind = '';
    if ($schedule_type === 'odd') {
        $kind = 'فرد';
    } elseif ($schedule_type === 'even') {
        $kind = 'زوج';
    }
    if ($when !== '' && $kind !== '') {
        return $when . ' — ' . $kind;
    }
    return $when !== '' ? $when : $kind;
}

function consultant_h($s) {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function consultant_mind_lingo_students(PDO $pdo) {
    require_once __DIR__ . '/terms.php';
    $sql = "
        SELECT u.id AS student_id, u.full_name, u.username, u.avatar,
               c.id AS class_id, c.title AS class_title, c.schedule, c.schedule_type,
               tch.full_name AS teacher_name,
               m.communication_dev, m.learning_eng, m.learning_cons, m.personal_growth, m.teacher_note
        FROM users u
        JOIN class_students cs ON cs.user_id = u.id
        JOIN classes c ON c.id = cs.class_id
        JOIN terms tm ON tm.id = c.term_id
        LEFT JOIN users tch ON tch.id = c.teacher_id
        LEFT JOIN mind_lingo m ON m.student_id = u.id AND m.class_id = c.id
        WHERE u.role = 'student' AND IFNULL(u.is_deleted, 0) = 0
          AND IFNULL(c.is_deleted, 0) = 0
          AND IFNULL(c.is_archived, 0) = 0
          AND " . term_sql_teaching('tm') . "
        ORDER BY u.full_name, c.title
    ";
    try {
        $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
    $out = [];
    foreach ($rows as $row) {
        $sid = (int)$row['student_id'];
        if (!isset($out[$sid])) {
            $out[$sid] = [
                'id' => $sid,
                'full_name' => $row['full_name'],
                'username' => $row['username'],
                'avatar' => $row['avatar'],
                'classes' => [],
            ];
        }
        $comm = $row['communication_dev'];
        $evaluated = ($comm !== null && $comm !== '');
        $total = $evaluated
            ? ((int)$row['communication_dev'] + (int)$row['learning_eng'] + (int)$row['learning_cons'] + (int)$row['personal_growth'])
            : null;
        $out[$sid]['classes'][] = [
            'class_id' => (int)$row['class_id'],
            'class_title' => $row['class_title'],
            'schedule' => $row['schedule'],
            'schedule_type' => $row['schedule_type'],
            'schedule_label' => consultant_schedule_label($row['schedule_type'], $row['schedule']),
            'teacher_name' => $row['teacher_name'],
            'evaluated' => $evaluated,
            'total' => $total,
            'communication_dev' => $evaluated ? (int)$row['communication_dev'] : null,
            'learning_eng' => $evaluated ? (int)$row['learning_eng'] : null,
            'learning_cons' => $evaluated ? (int)$row['learning_cons'] : null,
            'personal_growth' => $evaluated ? (int)$row['personal_growth'] : null,
            'teacher_note' => (string)($row['teacher_note'] ?? ''),
        ];
    }
    return array_values($out);
}

function consultant_remarks_for_student(PDO $pdo, $student_id) {
    consultant_ensure_schema($pdo);
    $st = $pdo->prepare("
        SELECT r.id, r.body, r.created_at, r.consultant_id, u.full_name AS consultant_name
        FROM consultant_remarks r
        LEFT JOIN users u ON u.id = r.consultant_id
        WHERE r.student_id = ?
        ORDER BY r.created_at DESC, r.id DESC
    ");
    $st->execute([(int)$student_id]);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

function consultant_add_remark(PDO $pdo, $student_id, $consultant_id, $body) {
    consultant_ensure_schema($pdo);
    $body = trim((string)$body);
    if ($body === '') {
        throw new Exception('متن ریمارک را بنویسید.');
    }
    if (mb_strlen($body) > 4000) {
        throw new Exception('ریمارک خیلی بلند است.');
    }
    $st = $pdo->prepare("
        SELECT id FROM users
        WHERE id = ? AND role = 'student' AND IFNULL(is_deleted, 0) = 0
        LIMIT 1
    ");
    $st->execute([(int)$student_id]);
    if (!$st->fetchColumn()) {
        throw new Exception('دانش‌آموز پیدا نشد.');
    }
    $ins = $pdo->prepare("
        INSERT INTO consultant_remarks (student_id, consultant_id, body)
        VALUES (?, ?, ?)
    ");
    $ins->execute([(int)$student_id, (int)$consultant_id, $body]);
    return (int)$pdo->lastInsertId();
}

function consultant_delete_remark(PDO $pdo, $remark_id, $consultant_id) {
    consultant_ensure_schema($pdo);
    $st = $pdo->prepare("DELETE FROM consultant_remarks WHERE id = ? AND consultant_id = ?");
    $st->execute([(int)$remark_id, (int)$consultant_id]);
    return $st->rowCount() > 0;
}

function consultant_remark_when($created_at) {
    $raw = trim((string)$created_at);
    if ($raw === '') {
        return '';
    }
    $ymd = substr($raw, 0, 10);
    $time = strlen($raw) >= 16 ? substr($raw, 11, 5) : '';
    if (function_exists('jalali_pretty')) {
        $pretty = jalali_pretty($ymd);
        return $pretty !== '' ? trim($pretty . ' ' . $time) : $raw;
    }
    require_once __DIR__ . '/jalali.php';
    $pretty = jalali_pretty($ymd);
    return $pretty !== '' ? trim($pretty . ' ' . $time) : $raw;
}
