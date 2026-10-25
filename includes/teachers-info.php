<?php
/**
 * Supervisor Teachers Information: list and class history by term.
 */

function teachers_info_split_name($full_name) {
    if (function_exists('identity_split_name')) {
        return identity_split_name($full_name);
    }
    $full_name = trim(preg_replace('/\s+/u', ' ', (string)$full_name));
    if ($full_name === '') {
        return ['', ''];
    }
    $parts = preg_split('/\s+/u', $full_name);
    $first = array_shift($parts);
    $last = implode(' ', $parts);
    return [$first, $last];
}

function teachers_info_join_name($first, $last) {
    return trim(preg_replace('/\s+/u', ' ', trim((string)$first) . ' ' . trim((string)$last)));
}

function teachers_info_decorate_row(array $row) {
    list($first, $last) = teachers_info_split_name($row['full_name'] ?? '');
    $row['first_name'] = $first;
    $row['last_name'] = $last;
    $row['username'] = (string)($row['username'] ?? '');
    return $row;
}

function teachers_info_list(PDO $pdo, $q = '') {
    $q = trim((string)$q);
    $sql = "
        SELECT u.id, u.username, u.full_name, u.phone, u.avatar
        FROM users u
        WHERE u.role = 'teacher' AND u.is_deleted = 0
    ";
    $params = [];
    if ($q !== '') {
        $sql .= " AND (u.username LIKE ? OR u.full_name LIKE ? OR u.phone LIKE ?)";
        $like = '%' . $q . '%';
        $params = [$like, $like, $like];
    }
    $sql .= " ORDER BY u.full_name, u.username";
    $st = $pdo->prepare($sql);
    $st->execute($params);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as &$row) {
        $row = teachers_info_decorate_row($row);
    }
    unset($row);
    return $rows;
}

function teachers_info_find(PDO $pdo, $user_id) {
    $st = $pdo->prepare("
        SELECT id, username, full_name, phone, avatar
        FROM users
        WHERE id = ? AND role = 'teacher' AND is_deleted = 0
        LIMIT 1
    ");
    $st->execute([(int)$user_id]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return null;
    }
    return teachers_info_decorate_row($row);
}

function teachers_info_update(PDO $pdo, $user_id, $username, $first_name, $last_name, $phone) {
    $teacher = teachers_info_find($pdo, $user_id);
    if (!$teacher) {
        throw new Exception('استاد پیدا نشد.');
    }
    $username = trim((string)$username);
    $first_name = trim((string)$first_name);
    $last_name = trim((string)$last_name);
    $phone = trim((string)$phone);
    $full_name = teachers_info_join_name($first_name, $last_name);
    if ($full_name === '') {
        throw new Exception('اسم و فامیل را وارد کنید.');
    }
    if ($username === '') {
        $username = $teacher['username'];
    }
    $st = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id != ? LIMIT 1");
    $st->execute([$username, (int)$teacher['id']]);
    if ($st->fetch()) {
        throw new Exception('این نام کاربری قبلاً استفاده شده است.');
    }
    $pdo->prepare("
        UPDATE users
        SET username = ?, full_name = ?, phone = ?
        WHERE id = ? AND role = 'teacher' AND is_deleted = 0
    ")->execute([$username, $full_name, $phone, (int)$teacher['id']]);

    return teachers_info_find($pdo, (int)$teacher['id']);
}

function teachers_info_create(PDO $pdo, $username, $first_name, $last_name, $phone, $password = '') {
    $username = trim((string)$username);
    $first_name = trim((string)$first_name);
    $last_name = trim((string)$last_name);
    $phone = trim((string)$phone);
    $password = (string)$password;
    $full_name = teachers_info_join_name($first_name, $last_name);
    if ($full_name === '') {
        throw new Exception('اسم و فامیل را وارد کنید.');
    }
    if ($username === '') {
        throw new Exception('نام کاربری را وارد کنید.');
    }
    if ($password === '') {
        $password = $username;
    }
    $st = $pdo->prepare("SELECT id, is_deleted, full_name FROM users WHERE username = ? LIMIT 1");
    $st->execute([$username]);
    $existing = $st->fetch(PDO::FETCH_ASSOC);
    if ($existing && (int)$existing['is_deleted'] === 0) {
        $who = trim((string)($existing['full_name'] ?? ''));
        throw new Exception(
            'نام کاربری «' . $username . '» قبلاً برای «'
            . ($who !== '' ? $who : 'کاربر دیگر')
            . '» ثبت شده. یک نام کاربری دیگر بگذارید.'
        );
    }
    $hash = password_hash($password, PASSWORD_DEFAULT);
    if ($existing) {
        $pdo->prepare("
            UPDATE users
            SET full_name = ?, password = ?, role = 'teacher', phone = ?, is_deleted = 0
            WHERE id = ?
        ")->execute([$full_name, $hash, $phone, (int)$existing['id']]);
        $user_id = (int)$existing['id'];
    } else {
        $pdo->prepare("
            INSERT INTO users (full_name, username, password, role, phone, is_deleted)
            VALUES (?, ?, ?, 'teacher', ?, 0)
        ")->execute([$full_name, $username, $hash, $phone]);
        $user_id = (int)$pdo->lastInsertId();
    }
    return teachers_info_find($pdo, $user_id);
}

function teachers_info_delete(PDO $pdo, $user_id) {
    $teacher = teachers_info_find($pdo, $user_id);
    if (!$teacher) {
        throw new Exception('استاد پیدا نشد.');
    }
    $pdo->prepare("UPDATE users SET is_deleted = 1 WHERE id = ? AND role = 'teacher'")
        ->execute([(int)$teacher['id']]);
    return true;
}

function teachers_info_class_type_label($type) {
    $type = strtolower(trim((string)$type));
    if ($type === 'online') {
        return 'آنلاین';
    }
    if ($type === 'private') {
        return 'خصوصی';
    }
    if ($type === 'offline') {
        return 'حضوری';
    }
    return $type !== '' ? $type : '';
}

function teachers_info_classes(PDO $pdo, $teacher_id) {
    $st = $pdo->prepare("
        SELECT
            c.id AS class_id,
            c.title AS class_title,
            c.class_type,
            c.schedule,
            c.schedule_type,
            t.id AS term_id,
            t.title AS term_title,
            t.is_archived,
            t.starts_on,
            t.created_at
        FROM classes c
        LEFT JOIN terms t ON c.term_id = t.id
        WHERE c.teacher_id = ?
          AND c.is_deleted = 0
          AND (t.id IS NULL OR t.is_deleted = 0 OR t.is_deleted IS NULL)
        ORDER BY COALESCE(t.starts_on, t.created_at) DESC, t.id DESC, c.title
    ");
    $st->execute([(int)$teacher_id]);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

function teachers_info_profile_by_term(PDO $pdo, $teacher_id) {
    $rows = teachers_info_classes($pdo, $teacher_id);
    $grouped = [];
    foreach ($rows as $row) {
        $term_id = (int)($row['term_id'] ?? 0);
        $key = $term_id > 0 ? (string)$term_id : 'none';
        if (!isset($grouped[$key])) {
            $grouped[$key] = [
                'term_id' => $term_id,
                'term_title' => trim((string)($row['term_title'] ?? '')) !== ''
                    ? (string)$row['term_title']
                    : 'بدون ترم',
                'starts_on' => $row['starts_on'] ?? null,
                'is_archived' => !empty($row['is_archived']),
                'classes' => [],
            ];
        }
        $grouped[$key]['classes'][] = [
            'class_id' => (int)$row['class_id'],
            'title' => (string)($row['class_title'] ?? ''),
            'class_type' => (string)($row['class_type'] ?? ''),
            'class_type_label' => teachers_info_class_type_label($row['class_type'] ?? ''),
            'schedule' => (string)($row['schedule'] ?? ''),
            'schedule_type' => (string)($row['schedule_type'] ?? ''),
            'is_archived' => !empty($row['is_archived']),
            'starts_on' => (string)($row['starts_on'] ?? ''),
        ];
    }
    return array_values($grouped);
}
