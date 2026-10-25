<?php
/**
 * German program — isolated from English students, teachers, and classes.
 * Roles: german_supervisor, german_teacher, german_student
 * Tables: german_* only. Never writes to classes / class_students / eas_management.
 */

function german_roles() {
    return ['german_supervisor', 'german_teacher', 'german_student'];
}

function german_levels() {
    return ['A1', 'A2', 'B1', 'B2'];
}

function german_kinds() {
    return [
        'group' => 'گروهی',
        'private' => 'خصوصی',
        'semi_private' => 'نیمه خصوصی',
    ];
}

function german_h($s) {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function german_money($n) {
    return number_format((int)$n, 0, '.', ',');
}

function german_nav_icon($key) {
    $icons = [
        'eas' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 576 512" aria-hidden="true"><path fill="currentColor" d="M64 32C28.7 32 0 60.7 0 96L0 416c0 35.3 28.7 64 64 64l448 0c35.3 0 64-28.7 64-64l0-320c0-35.3-28.7-64-64-64L64 32zm80 256a48 48 0 1 1 0 96 48 48 0 1 1 0-96zm80 40c0-13.3 10.7-24 24-24l192 0c13.3 0 24 10.7 24 24s-10.7 24-24 24l-192 0c-13.3 0-24-10.7-24-24zm0-128c0-13.3 10.7-24 24-24l192 0c13.3 0 24 10.7 24 24s-10.7 24-24 24l-192 0c-13.3 0-24-10.7-24-24zM144 144a48 48 0 1 1 0 96 48 48 0 1 1 0-96z"/></svg>',
        'people' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640" aria-hidden="true"><path opacity=".4" fill="currentColor" d="M320 320a112 112 0 1 0 0-224 112 112 0 1 0 0 224z"/><path fill="currentColor" d="M224 352c-70.7 0-128 57.3-128 128l0 32c0 17.7 14.3 32 32 32l384 0c17.7 0 32-14.3 32-32l0-32c0-70.7-57.3-128-128-128l-192 0zM432 208a80 80 0 1 0 0-160 80 80 0 1 0 0 160zm80 96c-17.7 0-34.4 3.7-49.7 10.4 20.2 20.7 32.9 48.8 34.9 79.6 39.3 7.4 70.8 38.1 78.4 78.1 2.1-5.1 3.3-10.7 3.3-16.6l0-23.6c0-53-43-96-96-96l-24.2 0c9.2-15.1 14.5-32.8 14.5-51.6 0-8.8-1.1-17.3-3.2-25.4 14.1-7.1 29.9-11 46.6-11l.4 0zM128 208a80 80 0 1 0 0-160 80 80 0 1 0 0 160zm-31.1 106.4C95 322.3 93.9 330.8 93.9 339.6c0 18.8 5.3 36.5 14.5 51.6L84.2 391.2c-53 0-96 43-96 96l0 23.6c0 5.9 1.2 11.5 3.3 16.6 7.6-40 39.1-70.7 78.4-78.1 2-30.8 14.7-58.9 34.9-79.6C162.4 307.7 145.7 304 128 304l-.4 0c-16.7 0-32.5 3.9-46.6 11z"/></svg>',
        'fees' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 576 512" aria-hidden="true"><path opacity=".4" fill="currentColor" d="M64 96C28.7 96 0 124.7 0 160L0 352c0 35.3 28.7 64 64 64l448 0c35.3 0 64-28.7 64-64l0-192c0-35.3-28.7-64-64-64L64 96zM288 320a64 64 0 1 0 0-128 64 64 0 1 0 0 128z"/><path fill="currentColor" d="M0 160C0 124.7 28.7 96 64 96l448 0c35.3 0 64 28.7 64 64l0 192c0 35.3-28.7 64-64 64L64 416c-35.3 0-64-28.7-64-64L0 160zM64 128c-17.7 0-32 14.3-32 32l0 192c0 17.7 14.3 32 32 32l448 0c17.7 0 32-14.3 32-32l0-192c0-17.7-14.3-32-32-32L64 128zM288 320a64 64 0 1 0 0-128 64 64 0 1 0 0 128zM112 192a32 32 0 1 0 0-64 32 32 0 1 0 0 64zm352 192a32 32 0 1 0 0-64 32 32 0 1 0 0 64z"/></svg>',
        'logout' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640" aria-hidden="true"><path opacity=".4" fill="currentColor" d="M259.3 320L384 444.7L384 368C384 359.2 391.2 352 400 352L528 352C536.8 352 544 344.8 544 336L544 304C544 295.2 536.8 288 528 288L400 288C391.2 288 384 280.8 384 272L384 195.3L259.3 320z"/><path fill="currentColor" d="M375 159L231 303C221.6 312.4 221.6 327.6 231 336.9L375 481C381.9 487.9 392.2 489.9 401.2 486.2C410.2 482.5 416 473.7 416 464L416 384L528 384C554.5 384 576 362.5 576 336L576 304C576 277.5 554.5 256 528 256L416 256L416 176C416 166.3 410.2 157.5 401.2 153.8C392.2 150.1 381.9 152.2 375 159zM384 444.7L259.3 320L384 195.3L384 272C384 280.8 391.2 288 400 288L528 288C536.8 288 544 295.2 544 304L544 336C544 344.8 536.8 352 528 352L400 352C391.2 352 384 359.2 384 368L384 444.7zM240 512L160 512C124.7 512 96 483.3 96 448L96 192C96 156.7 124.7 128 160 128L240 128C248.8 128 256 120.8 256 112C256 103.2 248.8 96 240 96L160 96C107 96 64 139 64 192L64 448C64 501 107 544 160 544L240 544C248.8 544 256 536.8 256 528C256 519.2 248.8 512 240 512z"/></svg>',
    ];
    $key = (string)$key;
    return $icons[$key] ?? $icons['eas'];
}

function german_nav_item_html(array $item, $current_page) {
    $href = (string)($item['href'] ?? 'index.php');
    $label = (string)($item['label'] ?? '');
    $icon = (string)($item['icon'] ?? 'eas');
    $active = basename($href) === (string)$current_page ? ' active' : '';
    return '<a href="' . german_h($href) . '" class="nav-item' . $active . '">'
        . '<span class="nav-icon">' . german_nav_icon($icon) . '</span> '
        . german_h($label)
        . '</a>';
}

function german_logout_item_html() {
    return '<a href="../../logout.php" class="nav-item">'
        . '<span class="nav-icon">' . german_nav_icon('logout') . '</span> Exit'
        . '</a>';
}

function german_ensure_schema(PDO $pdo) {
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    german_ensure_role_column($pdo);
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS german_fees (
            level VARCHAR(8) NOT NULL PRIMARY KEY,
            group_fee INT NOT NULL DEFAULT 0,
            private_fee INT NOT NULL DEFAULT 0,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS german_classes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(190) NOT NULL,
            level VARCHAR(8) NOT NULL,
            kind VARCHAR(20) NOT NULL,
            session_count INT NOT NULL DEFAULT 10,
            fee_per_session INT NOT NULL DEFAULT 0,
            teacher_id INT NOT NULL DEFAULT 0,
            schedule VARCHAR(80) NOT NULL DEFAULT '',
            schedule_type VARCHAR(20) NOT NULL DEFAULT '',
            session_status_json TEXT NULL,
            session_dates_json TEXT NULL,
            note TEXT NULL,
            created_by INT NOT NULL DEFAULT 0,
            is_deleted TINYINT(1) NOT NULL DEFAULT 0,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_gc_teacher (teacher_id),
            KEY idx_gc_level (level)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS german_class_students (
            id INT AUTO_INCREMENT PRIMARY KEY,
            class_id INT NOT NULL,
            student_id INT NOT NULL,
            fee_total INT NOT NULL DEFAULT 0,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_gcs (class_id, student_id),
            KEY idx_gcs_student (student_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS german_payments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            class_id INT NOT NULL,
            student_id INT NOT NULL,
            amount INT NOT NULL DEFAULT 0,
            receipt_path VARCHAR(255) NOT NULL DEFAULT '',
            receipt_name VARCHAR(190) NOT NULL DEFAULT '',
            note VARCHAR(255) NOT NULL DEFAULT '',
            created_by INT NOT NULL DEFAULT 0,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_gp_class_student (class_id, student_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    try {
        german_seed_fees($pdo);
        german_seed_supervisor($pdo);
    } catch (PDOException $e) {
        error_log('german seed: ' . $e->getMessage());
    }
}

function german_ensure_role_column(PDO $pdo) {
    try {
        $col = $pdo->query("SHOW COLUMNS FROM users LIKE 'role'")->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return;
    }
    if (!$col) {
        return;
    }
    $type = (string)($col['Type'] ?? '');
    if (stripos($type, 'enum') === false) {
        return;
    }
    $needed = german_roles();
    $missing = [];
    foreach ($needed as $role) {
        if (stripos($type, "'" . $role . "'") === false) {
            $missing[] = $role;
        }
    }
    if (!$missing) {
        return;
    }
    if (!preg_match('/^enum\((.*)\)$/i', $type, $m)) {
        return;
    }
    $extra = '';
    foreach ($missing as $role) {
        $extra .= ",'" . str_replace("'", '', $role) . "'";
    }
    $sql = "ALTER TABLE users MODIFY COLUMN role ENUM({$m[1]}{$extra}) NOT NULL";
    $default = $col['Default'] ?? null;
    if ($default !== null && $default !== '') {
        $sql .= ' DEFAULT ' . $pdo->quote($default);
    }
    try {
        $pdo->exec($sql);
    } catch (PDOException $e) {
        error_log('german role enum: ' . $e->getMessage());
    }
}

function german_seed_fees(PDO $pdo) {
    $defaults = [
        'A1' => [246000, 930000],
        'A2' => [246000, 930000],
        'B1' => [285000, 1150000],
        'B2' => [285000, 1150000],
    ];
    $stmt = $pdo->prepare("INSERT IGNORE INTO german_fees (level, group_fee, private_fee) VALUES (?, ?, ?)");
    foreach ($defaults as $level => $fees) {
        $stmt->execute([$level, $fees[0], $fees[1]]);
    }
}

function german_seed_supervisor(PDO $pdo) {
    $username = '7974';
    $name = 'Helia Partajli';
    $stmt = $pdo->prepare("SELECT id, role, is_deleted, full_name FROM users WHERE username = ? LIMIT 1");
    $stmt->execute([$username]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        $hash = password_hash('7974', PASSWORD_DEFAULT);
        $ins = $pdo->prepare("
            INSERT INTO users (username, password, full_name, role, is_deleted, created_at)
            VALUES (?, ?, ?, 'german_supervisor', 0, NOW())
        ");
        $ins->execute([$username, $hash, $name]);
        return;
    }
    $existing_role = (string)($row['role'] ?? '');
    if ($existing_role === 'german_supervisor') {
        $pdo->prepare("UPDATE users SET full_name = ?, is_deleted = 0 WHERE id = ?")
            ->execute([$name, (int)$row['id']]);
        return;
    }
    $existing_name = trim((string)($row['full_name'] ?? ''));
    $same_person = mb_strtolower($existing_name) === mb_strtolower($name);
    if ($same_person && $existing_role === 'supervisor') {
        $hash = password_hash('7974', PASSWORD_DEFAULT);
        $pdo->prepare("
            UPDATE users
            SET password = ?, full_name = ?, role = 'german_supervisor', is_deleted = 0
            WHERE id = ? AND username = ? AND role = 'supervisor'
        ")->execute([$hash, $name, (int)$row['id'], $username]);
        return;
    }
    error_log('german seed skipped: username 7974 is role ' . $existing_role);
}

function ghazal_dashboard_home($role) {
    $homes = [
        'student' => 'dashboard/student/index.php',
        'teacher' => 'dashboard/teacher/index.php',
        'supervisor' => 'dashboard/supervisor/index.php',
        'consultant' => 'dashboard/consultant/index.php',
        'german_supervisor' => 'dashboard/german_supervisor/index.php',
        'german_teacher' => 'dashboard/german_teacher/index.php',
        'german_student' => 'dashboard/german_student/index.php',
    ];
    return $homes[(string)$role] ?? '';
}

function german_kind_badge($kind) {
    if ($kind === 'private') {
        return 'type-private';
    }
    if ($kind === 'semi_private') {
        return 'type-semi';
    }
    return 'type-group';
}

function german_day_badge($type) {
    if ($type === 'odd') {
        return ['day-odd', 'روزهای فرد'];
    }
    if ($type === 'even') {
        return ['day-even', 'روزهای زوج'];
    }
    return ['', ''];
}

function german_is_german_role($role) {
    return in_array((string)$role, german_roles(), true);
}

function german_kind_label($kind) {
    $map = german_kinds();
    return $map[$kind] ?? $kind;
}

function german_fees_map(PDO $pdo) {
    $rows = $pdo->query("SELECT level, group_fee, private_fee FROM german_fees")->fetchAll(PDO::FETCH_ASSOC);
    $out = [];
    foreach ($rows as $row) {
        $out[$row['level']] = $row;
    }
    return $out;
}

function german_fee_per_session(array $fees_map, $level, $kind, $manual = 0) {
    if ($kind === 'semi_private') {
        return max(0, (int)$manual);
    }
    $row = $fees_map[$level] ?? null;
    if (!$row) {
        return 0;
    }
    if ($kind === 'private') {
        return (int)$row['private_fee'];
    }
    return (int)$row['group_fee'];
}

function german_people(PDO $pdo, $role) {
    if (!in_array($role, ['german_teacher', 'german_student'], true)) {
        return [];
    }
    $stmt = $pdo->prepare("
        SELECT id, username, full_name, phone
        FROM users
        WHERE role = ? AND IFNULL(is_deleted, 0) = 0
        ORDER BY full_name, username
    ");
    $stmt->execute([$role]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function german_allocate_username(PDO $pdo) {
    $used = [];
    $stmt = $pdo->query("SELECT username FROM users WHERE username REGEXP '^[0-9]{3,8}$'");
    foreach ($stmt as $row) {
        $used[(string)$row['username']] = true;
    }
    $n = 8000;
    $guard = 0;
    while (isset($used[(string)$n]) && $guard < 20000) {
        $n++;
        $guard++;
    }
    if (isset($used[(string)$n])) {
        throw new Exception('نتوانستیم نام کاربری یکتا بسازیم.');
    }
    return (string)$n;
}

function german_create_person(PDO $pdo, $role, $full_name, $username, $password, $phone = '') {
    if (!in_array($role, ['german_teacher', 'german_student'], true)) {
        throw new Exception('نقش نامعتبر است.');
    }
    $full_name = trim((string)$full_name);
    $username = trim((string)$username);
    $password = (string)$password;
    $phone = trim((string)$phone);
    if ($full_name === '') {
        throw new Exception('نام الزامی است.');
    }
    if ($username === '') {
        $username = german_allocate_username($pdo);
    }
    if ($password === '') {
        $password = $username;
    }
    $check = $pdo->prepare("SELECT id, role, is_deleted FROM users WHERE username = ? LIMIT 1");
    $check->execute([$username]);
    $existing = $check->fetch(PDO::FETCH_ASSOC);
    $hash = password_hash($password, PASSWORD_DEFAULT);
    if ($existing) {
        $existing_role = (string)($existing['role'] ?? '');
        $deleted = (int)($existing['is_deleted'] ?? 0) === 1;
        if (!$deleted) {
            if ($existing_role === $role) {
                throw new Exception('این نام کاربری قبلاً ثبت شده است.');
            }
            throw new Exception('این نام کاربری در برنامه انگلیسی یا نقش دیگری استفاده شده و قابل استفاده اینجا نیست.');
        }
        if (!german_is_german_role($existing_role)) {
            throw new Exception('این نام کاربری متعلق به برنامه انگلیسی است و حتی پس از حذف هم اینجا قابل استفاده نیست.');
        }
        $pdo->prepare("
            UPDATE users
            SET password = ?, full_name = ?, phone = ?, role = ?, is_deleted = 0, created_at = NOW()
            WHERE id = ?
        ")->execute([$hash, $full_name, $phone, $role, (int)$existing['id']]);
        return [
            'id' => (int)$existing['id'],
            'role' => $role,
            'full_name' => $full_name,
            'username' => $username,
            'password' => $password,
            'phone' => $phone,
        ];
    }
    $pdo->prepare("
        INSERT INTO users (username, password, full_name, phone, role, is_deleted, created_at)
        VALUES (?, ?, ?, ?, ?, 0, NOW())
    ")->execute([$username, $hash, $full_name, $phone, $role]);
    return [
        'id' => (int)$pdo->lastInsertId(),
        'role' => $role,
        'full_name' => $full_name,
        'username' => $username,
        'password' => $password,
        'phone' => $phone,
    ];
}

function german_assert_person(PDO $pdo, $user_id, $role) {
    $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ? AND role = ? AND IFNULL(is_deleted, 0) = 0");
    $stmt->execute([(int)$user_id, $role]);
    if (!$stmt->fetch()) {
        throw new Exception('کاربر آلمانی یافت نشد.');
    }
}

function german_class_row(PDO $pdo, $class_id) {
    $stmt = $pdo->prepare("SELECT * FROM german_classes WHERE id = ? AND is_deleted = 0");
    $stmt->execute([(int)$class_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        throw new Exception('کلاس یافت نشد.');
    }
    return $row;
}

function german_refresh_enrollment_fees(PDO $pdo, $class_id) {
    $class = german_class_row($pdo, $class_id);
    $total = (int)$class['session_count'] * (int)$class['fee_per_session'];
    $pdo->prepare("UPDATE german_class_students SET fee_total = ? WHERE class_id = ?")
        ->execute([$total, (int)$class_id]);
    return $total;
}

function german_enroll(PDO $pdo, $class_id, $student_id) {
    german_assert_person($pdo, $student_id, 'german_student');
    $class = german_class_row($pdo, $class_id);
    $total = (int)$class['session_count'] * (int)$class['fee_per_session'];
    $pdo->prepare("
        INSERT INTO german_class_students (class_id, student_id, fee_total)
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE fee_total = VALUES(fee_total)
    ")->execute([(int)$class_id, (int)$student_id, $total]);
}

function german_paid_sum(PDO $pdo, $class_id, $student_id) {
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM german_payments WHERE class_id = ? AND student_id = ?");
    $stmt->execute([(int)$class_id, (int)$student_id]);
    return (int)$stmt->fetchColumn();
}

function german_status($total, $paid) {
    $total = (int)$total;
    $paid = (int)$paid;
    if ($total <= 0 && $paid <= 0) {
        return ['draft', 'ثبت اولیه'];
    }
    if ($paid >= $total) {
        return ['settled', 'تسویه'];
    }
    if ($paid > 0) {
        return ['partial', 'نیمه‌پرداخت'];
    }
    return ['unpaid', 'بدهکار'];
}

function german_load_classes(PDO $pdo) {
    $sql = "
        SELECT c.*, t.full_name AS teacher_name, t.username AS teacher_username
        FROM german_classes c
        LEFT JOIN users t ON t.id = c.teacher_id AND t.role = 'german_teacher'
        WHERE c.is_deleted = 0
        ORDER BY c.id DESC
    ";
    $classes = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    if (!$classes) {
        return [];
    }
    $ids = array_map(static function ($c) { return (int)$c['id']; }, $classes);
    $in = implode(',', array_fill(0, count($ids), '?'));
    $st = $pdo->prepare("
        SELECT cs.class_id, cs.student_id, cs.fee_total, u.full_name, u.username
        FROM german_class_students cs
        JOIN users u ON u.id = cs.student_id AND u.role = 'german_student' AND IFNULL(u.is_deleted, 0) = 0
        WHERE cs.class_id IN ($in)
        ORDER BY u.full_name
    ");
    $st->execute($ids);
    $pay = $pdo->prepare("
        SELECT id, class_id, student_id, amount, receipt_path, receipt_name, created_at
        FROM german_payments
        WHERE class_id IN ($in)
        ORDER BY id DESC
    ");
    $pay->execute($ids);
    $pays = [];
    $paid_sum = [];
    foreach ($pay->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $key = (int)$row['class_id'] . ':' . (int)$row['student_id'];
        $pays[$key][] = $row;
        $paid_sum[$key] = ($paid_sum[$key] ?? 0) + (int)$row['amount'];
    }
    $by_class = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $cid = (int)$row['class_id'];
        $key = $cid . ':' . (int)$row['student_id'];
        $paid = (int)($paid_sum[$key] ?? 0);
        $status = german_status((int)$row['fee_total'], $paid);
        $row['paid'] = $paid;
        $row['debt'] = max(0, (int)$row['fee_total'] - $paid);
        $row['status_key'] = $status[0];
        $row['status_label'] = $status[1];
        $row['payments'] = $pays[$key] ?? [];
        $by_class[$cid][] = $row;
    }
    foreach ($classes as &$class) {
        $cid = (int)$class['id'];
        $class['students'] = $by_class[$cid] ?? [];
        $class['session_status'] = json_decode($class['session_status_json'] ?? '{}', true) ?: [];
        $class['session_dates'] = json_decode($class['session_dates_json'] ?? '{}', true) ?: [];
    }
    unset($class);
    return $classes;
}

function german_receipt_dir() {
    $dir = dirname(__DIR__) . '/uploads/german-receipts';
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    return $dir;
}

function german_save_receipt($file) {
    if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        return ['', ''];
    }
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new Exception('آپلود رسید ناموفق بود.');
    }
    if ((int)($file['size'] ?? 0) > 4 * 1024 * 1024) {
        throw new Exception('حجم رسید حداکثر ۴ مگابایت است.');
    }
    $ext = strtolower(pathinfo((string)$file['name'], PATHINFO_EXTENSION));
    $ok = ['jpg' => 1, 'jpeg' => 1, 'png' => 1, 'webp' => 1, 'pdf' => 1];
    if (!isset($ok[$ext])) {
        throw new Exception('فرمت رسید باید تصویر یا PDF باشد.');
    }
    $name = 'g' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $dest = german_receipt_dir() . '/' . $name;
    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        throw new Exception('ذخیره رسید انجام نشد.');
    }
    return ['german-receipts/' . $name, (string)$file['name']];
}

function german_receipt_abs($rel) {
    $rel = (string)$rel;
    if (!preg_match('#^german-receipts/[A-Za-z0-9._-]+$#', $rel)) {
        return '';
    }
    $path = dirname(__DIR__) . '/uploads/' . $rel;
    return is_file($path) ? $path : '';
}

function german_receipt_mime($path) {
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    $map = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'webp' => 'image/webp',
        'pdf' => 'application/pdf',
    ];
    return $map[$ext] ?? 'application/octet-stream';
}

function german_can_view_payment(PDO $pdo, array $payment, $viewer_id, $viewer_role) {
    $viewer_id = (int)$viewer_id;
    if ($viewer_role === 'german_supervisor') {
        return true;
    }
    if ($viewer_role === 'german_student' && (int)$payment['student_id'] === $viewer_id) {
        return true;
    }
    if ($viewer_role === 'german_teacher') {
        $st = $pdo->prepare("SELECT id FROM german_classes WHERE id = ? AND teacher_id = ? AND is_deleted = 0");
        $st->execute([(int)$payment['class_id'], $viewer_id]);
        return (bool)$st->fetchColumn();
    }
    return false;
}

function german_output_receipt(PDO $pdo, $payment_id, $viewer_id, $viewer_role) {
    $st = $pdo->prepare("
        SELECT p.*
        FROM german_payments p
        JOIN german_classes c ON c.id = p.class_id AND c.is_deleted = 0
        WHERE p.id = ?
        LIMIT 1
    ");
    $st->execute([(int)$payment_id]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (!$row || !german_can_view_payment($pdo, $row, $viewer_id, $viewer_role)) {
        http_response_code(404);
        echo 'رسید یافت نشد.';
        exit;
    }
    $abs = german_receipt_abs($row['receipt_path'] ?? '');
    if ($abs === '') {
        http_response_code(404);
        echo 'فایل رسید موجود نیست.';
        exit;
    }
    $download = (string)($row['receipt_name'] ?: basename($abs));
    $download = preg_replace('/[\r\n"]+/', '', $download);
    header('X-Content-Type-Options: nosniff');
    header('Content-Type: ' . german_receipt_mime($abs));
    header('Content-Length: ' . filesize($abs));
    header('Content-Disposition: inline; filename="' . $download . '"');
    readfile($abs);
    exit;
}

function german_classes_for(PDO $pdo, $role, $user_id) {
    $all = german_load_classes($pdo);
    $user_id = (int)$user_id;
    if ($role === 'german_teacher') {
        return array_values(array_filter($all, static function ($c) use ($user_id) {
            return (int)$c['teacher_id'] === $user_id;
        }));
    }
    if ($role === 'german_student') {
        $out = [];
        foreach ($all as $class) {
            foreach ($class['students'] as $student) {
                if ((int)$student['student_id'] === $user_id) {
                    $class['me'] = $student;
                    $class['students'] = [$student];
                    $out[] = $class;
                    break;
                }
            }
        }
        return $out;
    }
    return $all;
}
