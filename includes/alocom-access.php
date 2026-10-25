<?php
/**
 * Sync AloCom rooms: no guests, readable username/password, only settled students.
 */

require_once __DIR__ . '/alocom.php';
require_once __DIR__ . '/accounting.php';
require_once __DIR__ . '/alocom-sign-in.php';
require_once __DIR__ . '/terms.php';

function alocom_split_name($full) {
    $full = trim(preg_replace('/\s+/', ' ', (string)$full));
    if ($full === '') {
        return ['Student', 'Ghazal'];
    }
    $parts = explode(' ', $full);
    if (count($parts) === 1) {
        return [$parts[0], 'Ghazal'];
    }
    $surname = array_pop($parts);
    return [implode(' ', $parts), $surname];
}

function alocom_readable_username($panel_username) {
    $u = strtolower(trim((string)$panel_username));
    $u = preg_replace('/[^a-z0-9._-]/', '', $u);
    if ($u === '') {
        $u = 'st' . substr(md5((string)$panel_username), 0, 6);
    }
    return $u;
}

function alocom_readable_password($username) {
    return (string) $username;
}

function alocom_password_candidates($username) {
    $username = (string) $username;
    $out = [$username];
    $with_prefix = 'Ghazal' . $username;
    if ($with_prefix !== $username) {
        $out[] = $with_prefix;
    }
    return $out;
}

function alocom_all_users() {
    if (!empty($GLOBALS['_alocom_users_cache']) && is_array($GLOBALS['_alocom_users_cache'])) {
        return $GLOBALS['_alocom_users_cache'];
    }
    $skip = 0;
    $all = [];
    do {
        $res = alocom_request('POST', '/api/v1/agents/users/index', ['take' => 50, 'skip' => $skip]);
        $rows = $res['data'] ?? [];
        if (!is_array($rows)) {
            break;
        }
        $all = array_merge($all, $rows);
        $skip += 50;
    } while (count($rows) === 50 && $skip < 500);
    $GLOBALS['_alocom_users_cache'] = $all;
    return $all;
}

function alocom_find_user_by_username($username) {
    $want = strtolower($username);
    foreach (alocom_all_users() as $u) {
        if (strtolower((string)($u['username'] ?? '')) === $want) {
            return $u;
        }
    }
    return null;
}

function alocom_user_full_name(array $u) {
    return trim((string)($u['name'] ?? '') . ' ' . (string)($u['surname'] ?? ''));
}

function alocom_hardcoded_staff_usernames() {
    return [
        'aylar', 'f.razavi', 'fatemeh.r', 'ghazal', 'jalali', 'm1377', 'maedeh', 'maedeh2',
        'mahta', 'maral', 'maral2', 'maryam', 'matin', 'matin1234', 'moghimi', 'motalebzadeh',
        'msramezani', 'nastaran', 'nazanin.h', 'partajli', 'saghar', 'samaneh', 'samira',
        'sara.r', 'shima', 'yasaman', 'zahra', 'zohre',
    ];
}

function alocom_leftover_student_usernames() {
    return [
        'ali.d', 'amiralizamani', 'amirhossein', 'amirreza', 'bahar', 'bardia',
        'helia.b', 'helia.gh', 'hosna', 'hossein', 'm.amin', 'parham',
        'reyhane', 'roham', 'sadra', 'sadra.f',
    ];
}

/**
 * Panel teacher/supervisor logins (including numeric codes like 4321)
 * and first-name tokens (kosar) so leftover-closer never treats them as students.
 */
function alocom_panel_staff_usernames(PDO $pdo = null) {
    static $loaded = false;
    static $set = [];
    if ($pdo instanceof PDO) {
        try {
            $st = $pdo->query("
                SELECT username, full_name
                FROM users
                WHERE is_deleted = 0
                  AND role IN ('teacher', 'supervisor')
            ");
            $set = [];
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $u = strtolower(trim((string)($row['username'] ?? '')));
                if ($u !== '') {
                    $set[$u] = true;
                    $readable = alocom_readable_username($u);
                    if ($readable !== '') {
                        $set[$readable] = true;
                    }
                }
                $tokens = alocom_name_tokens($row['full_name'] ?? '');
                $first = strtolower((string)($tokens[0] ?? ''));
                $first = preg_replace('/[^a-z0-9._-]/', '', $first);
                if ($first !== '' && !preg_match('/^[0-9]+$/', $first)) {
                    $set[$first] = true;
                }
            }
            $loaded = true;
        } catch (Exception $e) {
            alocom_log('panel staff usernames: ' . $e->getMessage());
        }
    }
    return $loaded ? $set : [];
}

function alocom_is_panel_staff_username($username) {
    $u = strtolower(trim((string)$username));
    if ($u === '') {
        return false;
    }
    $set = alocom_panel_staff_usernames();
    return $set && isset($set[$u]);
}

function alocom_is_staff_username($username) {
    $u = strtolower(trim((string)$username));
    if ($u === '') {
        return true;
    }
    if (alocom_is_panel_staff_username($u)) {
        return true;
    }
    if (preg_match('/^[0-9]{3,4}$/', $u) && (int)$u > 0 && (int)$u < 5000) {
        return false;
    }
    if (preg_match('/^[0-9]+$/', $u)) {
        return false;
    }
    if (in_array($u, alocom_hardcoded_staff_usernames(), true)) {
        return true;
    }
    if (preg_match('/^(ms|mr|frau|nazanin\.|maedeh)/', $u)) {
        return true;
    }
    if (in_array($u, alocom_leftover_student_usernames(), true)) {
        return false;
    }
    return true;
}

function alocom_username_looks_like_student($username) {
    $u = strtolower(trim((string)$username));
    if ($u === '') {
        return false;
    }
    if (alocom_is_panel_staff_username($u)) {
        return false;
    }
    if (preg_match('/^[0-9]{3,4}$/', $u) && (int)$u > 0 && (int)$u < 5000) {
        return true;
    }
    return in_array($u, alocom_leftover_student_usernames(), true);
}

function alocom_is_private_class(array $class) {
    $type = strtolower(trim((string)($class['class_type'] ?? '')));
    if ($type === 'private') {
        return true;
    }
    $title = (string)($class['title'] ?? '');
    return (bool)preg_match('/\bPV\b|private|خصوصی/iu', $title);
}

function alocom_is_inperson_class(array $class) {
    if (alocom_is_private_class($class)) {
        return false;
    }
    $type = strtolower(trim((string)($class['class_type'] ?? '')));
    if (in_array($type, ['online', 'آنلاین'], true)) {
        return false;
    }
    return in_array($type, ['offline', 'inperson', 'in-person', 'حضوری'], true);
}

/**
 * In-person backup rooms (snow day / no campus) stay open without tuition settlement.
 * Regular online classes still need payment. Private rooms already skip payment.
 */
function alocom_event_skips_payment(array $class) {
    if (alocom_is_private_class($class)) {
        return true;
    }
    if (!alocom_is_inperson_class($class)) {
        return false;
    }
    return alocom_class_needs_event($class);
}

function alocom_class_needs_event(array $class) {
    $type = strtolower(trim((string)($class['class_type'] ?? '')));
    if ($type === 'online' || $type === 'آنلاین') {
        return true;
    }
    if (!alocom_is_private_class($class) && !alocom_is_inperson_class($class)) {
        return false;
    }
    if (!empty($class['create_alocom'])) {
        return true;
    }
    return trim((string)($class['online_link'] ?? '')) !== '';
}

function alocom_find_user_by_name($full) {
    $full = trim((string)$full);
    if ($full === '') {
        return null;
    }
    $want = alocom_normalize_name($full);
    $fuzzy = null;
    foreach (alocom_all_users() as $u) {
        if (alocom_is_staff_username($u['username'] ?? '')) {
            continue;
        }
        $got = alocom_user_full_name($u);
        if ($got === '') {
            continue;
        }
        if (strcasecmp($got, $full) === 0 || alocom_normalize_name($got) === $want) {
            return $u;
        }
        if ($fuzzy === null && alocom_names_similar($full, $got)) {
            $fuzzy = $u;
        }
    }
    return $fuzzy;
}

function alocom_find_user_by_id($user_id) {
    $user_id = (int)$user_id;
    foreach (alocom_all_users() as $u) {
        if ((int)($u['id'] ?? 0) === $user_id) {
            return $u;
        }
    }
    return null;
}

function alocom_locate_student_user(array $student) {
    $username = alocom_readable_username($student['username'] ?? '');
    $existing = $username !== '' ? alocom_find_user_by_username($username) : null;
    if ($existing) {
        return $existing;
    }
    return alocom_find_user_by_name($student['full_name'] ?? '');
}

function alocom_set_password($user_id, $password) {
    alocom_request('PATCH', '/api/v1/agents/users/' . (int)$user_id . '/password-change', [
        'password' => $password,
        'password_confirmation' => $password,
    ]);
}

function alocom_ensure_tables(PDO $pdo) {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS alocom_credentials (
            panel_user_id INT NOT NULL PRIMARY KEY,
            alocom_user_id INT NOT NULL,
            username VARCHAR(100) NOT NULL,
            password_plain VARCHAR(100) NOT NULL,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS alocom_access_state (
            event_id INT NOT NULL,
            alocom_userid INT NOT NULL,
            panel_user_id INT DEFAULT NULL,
            source VARCHAR(20) NOT NULL DEFAULT 'auto',
            enrolled TINYINT(1) NOT NULL DEFAULT 1,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (event_id, alocom_userid)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
}

function alocom_saved_credential(PDO $pdo, $panel_user_id) {
    $stmt = $pdo->prepare('SELECT * FROM alocom_credentials WHERE panel_user_id = ?');
    $stmt->execute([(int)$panel_user_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function alocom_panel_login_for_user(PDO $pdo, $panel_user_id, $panel_username) {
    $username = alocom_readable_username($panel_username);
    $password = alocom_readable_password($username);
    try {
        alocom_ensure_tables($pdo);
        $saved = alocom_saved_credential($pdo, $panel_user_id);
        if ($saved) {
            if (!empty($saved['username'])) {
                $username = (string) $saved['username'];
            }
            if (isset($saved['password_plain']) && (string) $saved['password_plain'] !== '') {
                $password = (string) $saved['password_plain'];
            }
        }
    } catch (Exception $e) {
        alocom_log('panel login display ' . (int) $panel_user_id . ': ' . $e->getMessage());
    }
    return [
        'username' => $username,
        'password' => $password,
    ];
}

function alocom_panel_login_for_student(PDO $pdo, $panel_user_id, $panel_username) {
    return alocom_panel_login_for_user($pdo, $panel_user_id, $panel_username);
}

function alocom_panel_login_for_teacher(PDO $pdo, $panel_user_id, $panel_username) {
    return alocom_panel_login_for_user($pdo, $panel_user_id, $panel_username);
}

function alocom_store_credential(PDO $pdo, $panel_user_id, $alocom_user_id, $username, $password) {
    $stmt = $pdo->prepare("
        INSERT INTO alocom_credentials (panel_user_id, alocom_user_id, username, password_plain)
        VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            alocom_user_id = VALUES(alocom_user_id),
            username = VALUES(username),
            password_plain = VALUES(password_plain)
    ");
    $stmt->execute([(int)$panel_user_id, (int)$alocom_user_id, $username, $password]);
}

function alocom_state_get(PDO $pdo, $event_id) {
    $stmt = $pdo->prepare('SELECT * FROM alocom_access_state WHERE event_id = ?');
    $stmt->execute([(int)$event_id]);
    $out = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $out[(int)$row['alocom_userid']] = $row;
    }
    return $out;
}

function alocom_state_upsert(PDO $pdo, $event_id, $alocom_userid, $source, $enrolled, $panel_user_id = null) {
    $stmt = $pdo->prepare("
        INSERT INTO alocom_access_state (event_id, alocom_userid, panel_user_id, source, enrolled)
        VALUES (?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            panel_user_id = COALESCE(VALUES(panel_user_id), panel_user_id),
            source = IF(source IN ('manual_allow', 'manual_deny') AND VALUES(source) = 'auto', source, VALUES(source)),
            enrolled = VALUES(enrolled)
    ");
    $stmt->execute([(int)$event_id, (int)$alocom_userid, $panel_user_id ? (int)$panel_user_id : null, $source, $enrolled ? 1 : 0]);
}

function alocom_try_set_password($alocom_user_id, array $candidates) {
    $last = null;
    foreach ($candidates as $password) {
        try {
            alocom_set_password($alocom_user_id, $password);
            return $password;
        } catch (Exception $e) {
            $last = $e;
            alocom_log('password try failed for ' . $alocom_user_id . ': ' . $e->getMessage());
        }
    }
    if ($last) {
        throw $last;
    }
    return $candidates[0] ?? '';
}

function alocom_ensure_student_account(PDO $pdo, array $student) {
    $username = alocom_readable_username($student['username'] ?? '');
    $candidates = alocom_password_candidates($username);
    $saved = alocom_saved_credential($pdo, (int)$student['id']);
    $password = $candidates[0];
    list($name, $surname) = alocom_split_name($student['full_name'] ?? '');
    $phone = rollcall_normalize_phone($student['phone'] ?? '');

    $existing = alocom_locate_student_user($student);
    if ($existing && alocom_is_staff_username($existing['username'] ?? '')) {
        $existing = alocom_find_user_by_username($username);
    }

    if ($existing) {
        try {
            $patch = [
                'name' => $name,
                'surname' => $surname,
                'username' => $username,
                'status' => 1,
            ];
            if ($phone !== '') {
                $patch['cellphone'] = $phone;
            }
            alocom_request('PATCH', '/api/v1/agents/users/' . (int)$existing['id'], $patch);
            $existing['username'] = $username;
            $existing['status'] = 1;
            alocom_all_users_invalidate();
        } catch (Exception $e) {
            alocom_log('rename/activate ' . ($existing['username'] ?? '') . ' -> ' . $username . ': ' . $e->getMessage());
            $existing = alocom_find_user_by_username($username) ?: $existing;
        }
        $want_reset = !$saved || !in_array((string)($saved['password_plain'] ?? ''), $candidates, true);
        if ($want_reset) {
            $password = alocom_try_set_password((int)$existing['id'], $candidates);
        } else {
            $password = (string)$saved['password_plain'];
        }
        alocom_store_credential($pdo, (int)$student['id'], (int)$existing['id'], $username, $password);
        return [
            'id' => (int)$existing['id'],
            'username' => $username,
            'password' => $password,
            'created' => false,
        ];
    }

    $last = null;
    $created_ok = false;
    foreach ($candidates as $password) {
        $payload = [
            'name' => $name,
            'surname' => $surname,
            'username' => $username,
            'password' => $password,
            'status' => 1,
        ];
        if ($phone !== '') {
            $payload['cellphone'] = $phone;
        }
        try {
            alocom_request('POST', '/api/v1/agents/users', $payload);
            $created_ok = true;
            break;
        } catch (Exception $e) {
            $last = $e;
            alocom_log('create user ' . $username . ': ' . $e->getMessage());
        }
    }
    if (!$created_ok) {
        throw ($last ?: new Exception('AloCom create user failed'));
    }
    alocom_all_users_invalidate();
    $fresh = alocom_find_user_by_username($username);
    $alocom_id = $fresh ? (int)$fresh['id'] : 0;
    if ($alocom_id) {
        alocom_store_credential($pdo, (int)$student['id'], $alocom_id, $username, $password);
    }
    return [
        'id' => $alocom_id,
        'username' => $username,
        'password' => $password,
        'created' => true,
    ];
}

function alocom_all_users_invalidate() {
    $GLOBALS['_alocom_users_cache'] = null;
}

function alocom_event_is_inactive($status) {
    return $status === 'inactive' || $status === 0 || $status === '0' || $status === false;
}

function alocom_event_patch_payload(array $event_detail, array $overrides = []) {
    $ev = $event_detail['event'] ?? $event_detail;
    if (!is_array($ev)) {
        $ev = [];
    }
    $service_id = 0;
    if (function_exists('alocom_best_service_id_from_detail')) {
        $service_id = (int)alocom_best_service_id_from_detail($event_detail);
    }
    if ($service_id <= 1000) {
        $service_id = (int)($ev['agent_service_id'] ?? ($ev['id_service_agent'] ?? 0));
    }
    if ($service_id <= 1000 && function_exists('alocom_pick_service_id')) {
        try {
            $service_id = (int)alocom_pick_service_id();
        } catch (Exception $e) {
            alocom_log('pick service for event patch: ' . $e->getMessage());
        }
    }
    $payload = [
        'title' => $ev['title'] ?? '',
        'agent_service_id' => $service_id ?: null,
        'id_service_agent' => $ev['id_service_agent'] ?? ($service_id ?: null),
        'slug' => $ev['slug'] ?? '',
        'status' => alocom_event_is_inactive($ev['status'] ?? 1) ? 0 : 1,
        'admin_by_start' => 0,
        'start_by_admin' => 0,
        'guest_access' => 0,
        'guest_count' => 0,
        'guest_code' => [],
        'language' => $ev['language'] ?? 'en',
        'local_record_available' => !empty($ev['local_record_available']) ? 1 : 0,
    ];
    return array_merge($payload, $overrides);
}

function alocom_allow_join_before_admin($event_id, array $event_detail = []) {
    $event_id = (int)$event_id;
    if ($event_id <= 0) {
        return false;
    }
    try {
        $fresh = alocom_event_detail($event_id);
        if (is_array($fresh) && $fresh) {
            $event_detail = $fresh;
        }
    } catch (Exception $e) {
        if (empty($event_detail)) {
            throw $e;
        }
        alocom_log('join-before-admin detail ' . $event_id . ': ' . $e->getMessage());
    }
    $payload = alocom_event_patch_payload($event_detail);
    alocom_request('PATCH', '/api/v1/agents/events/' . $event_id . '/', $payload);
    return true;
}

function alocom_close_guest($event_id, array $event_detail) {
    try {
        $fresh = alocom_event_detail((int)$event_id);
        if (is_array($fresh) && $fresh) {
            $event_detail = $fresh;
        }
    } catch (Exception $e) {
        alocom_log('close guest detail ' . $event_id . ': ' . $e->getMessage());
    }
    $payload = alocom_event_patch_payload($event_detail, [
        'guest_access' => 0,
        'guest_count' => 0,
        'guest_code' => [],
        'admin_by_start' => 0,
        'start_by_admin' => 0,
    ]);
    alocom_request('PATCH', '/api/v1/agents/events/' . (int)$event_id . '/', $payload);
}

function alocom_unlock_join_before_admin_for_gz_events($limit = 80) {
    $out = [];
    foreach (alocom_list_events() as $ev) {
        if (!is_array($ev)) {
            continue;
        }
        $slug = strtolower((string)($ev['slug'] ?? ''));
        $href = strtolower((string)alocom_event_href($ev));
        if (strpos($slug, 'gz-') !== 0 && strpos($href, '/gz-') === false) {
            continue;
        }
        $id = alocom_event_id_from($ev);
        if ($id <= 0) {
            continue;
        }
        try {
            alocom_allow_join_before_admin($id, $ev);
            $detail = alocom_event_detail($id);
            $node = $detail['event'] ?? $detail;
            $out[] = [
                'id' => $id,
                'slug' => $slug !== '' ? $slug : alocom_slug_from_link($href),
                'ok' => true,
                'start_by_admin' => $node['start_by_admin'] ?? ($node['admin_by_start'] ?? null),
            ];
        } catch (Exception $e) {
            $out[] = [
                'id' => $id,
                'slug' => $slug,
                'ok' => false,
                'error' => $e->getMessage(),
            ];
        }
        if (count($out) >= $limit) {
            break;
        }
    }
    return $out;
}

function alocom_find_staff_user(PDO $pdo, $panel_user_id) {
    $panel_user_id = (int)$panel_user_id;
    if ($panel_user_id <= 0) {
        return null;
    }
    alocom_panel_staff_usernames($pdo);
    $st = $pdo->prepare('SELECT id, full_name, username FROM users WHERE id = ? LIMIT 1');
    $st->execute([$panel_user_id]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return null;
    }
    $username = strtolower(trim((string)($row['username'] ?? '')));
    $name = trim((string)($row['full_name'] ?? ''));
    if ($username !== '') {
        $hit = alocom_find_user_by_username($username);
        if ($hit) {
            return $hit;
        }
    }
    $tokens = alocom_name_tokens($name);
    $first = $tokens[0] ?? '';
    if ($first !== '' && $first !== $username) {
        $hit = alocom_find_user_by_username($first);
        if ($hit) {
            return $hit;
        }
    }
    $want = alocom_normalize_name($name);
    $name_hits = [];
    $user_hits = [];
    foreach (alocom_all_users() as $u) {
        $uname = strtolower(trim((string)($u['username'] ?? '')));
        if (!alocom_is_staff_username($uname)) {
            continue;
        }
        $got = alocom_user_full_name($u);
        if ($want !== '' && $got !== '') {
            if (alocom_normalize_name($got) === $want || alocom_names_similar($name, $got)) {
                $name_hits[] = $u;
                continue;
            }
        }
        if ($first !== '' && $uname !== '') {
            $base = preg_replace('/[0-9]+$/', '', $uname);
            if ($uname === $first || $base === $first || strpos($uname, $first) === 0) {
                $user_hits[] = $u;
            }
        }
    }
    if ($name_hits) {
        return $name_hits[0];
    }
    $uniq = [];
    foreach ($user_hits as $u) {
        $uniq[(int)($u['id'] ?? 0)] = $u;
    }
    unset($uniq[0]);
    $uniq = array_values($uniq);
    if (!$uniq) {
        return null;
    }
    if ($first !== '') {
        foreach ($uniq as $u) {
            if (strtolower(trim((string)($u['username'] ?? ''))) === $first) {
                return $u;
            }
        }
    }
    return $uniq[0];
}

function alocom_teacher_event_users(PDO $pdo, $teacher_id) {
    $u = alocom_find_staff_user($pdo, $teacher_id);
    if (!$u) {
        return [];
    }
    $uname = trim((string)($u['username'] ?? ''));
    if ($uname !== '') {
        try {
            alocom_ensure_tables($pdo);
            if (!alocom_saved_credential($pdo, (int)$teacher_id)) {
                alocom_store_credential(
                    $pdo,
                    (int)$teacher_id,
                    (int)$u['id'],
                    $uname,
                    alocom_readable_password($uname)
                );
            }
        } catch (Exception $e) {
            alocom_log('teacher cred store ' . (int)$teacher_id . ': ' . $e->getMessage());
        }
    }
    return [['userid' => (int)$u['id'], 'role' => 'teacher']];
}

function alocom_supervisor_usernames() {
    return ['m1377', 'matin', 'samira', 'saghar', 'maral'];
}

function alocom_supervisor_event_users(PDO $pdo = null) {
    $out = [];
    $seen = [];
    foreach (alocom_supervisor_usernames() as $username) {
        $hit = alocom_find_user_by_username($username);
        if (!$hit) {
            alocom_log('supervisor alocom missing ' . $username);
            continue;
        }
        $uid = (int)($hit['id'] ?? 0);
        if ($uid <= 0 || isset($seen[$uid])) {
            continue;
        }
        $seen[$uid] = true;
        $out[] = ['userid' => $uid, 'role' => 'teacher'];
    }
    return $out;
}

function alocom_sync_supervisor_admins(PDO $pdo, array $events = null) {
    $payload = alocom_supervisor_event_users($pdo);
    if (!$payload) {
        return ['ok' => false, 'events' => 0, 'added' => 0, 'admins' => [], 'classes' => []];
    }
    $stmt = $pdo->query("
        SELECT c.id, c.title, c.online_link, c.schedule, c.class_type, c.term_id
        FROM classes c
        JOIN terms t ON t.id = c.term_id
        WHERE c.is_deleted = 0 AND c.is_archived = 0 AND " . term_sql_teaching('t') . "
          AND (c.class_type = 'online' OR (c.online_link IS NOT NULL AND c.online_link <> ''))
        ORDER BY c.title
    ");
    $classes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if ($events === null) {
        $events = alocom_list_events();
    }
    $n = 0;
    $added = 0;
    $per = [];
    foreach ($classes as $class) {
        $event = alocom_match_event($class, $events);
        $event_id = $event ? alocom_event_id_from($event) : 0;
        if ($event_id <= 0) {
            $per[] = ['class' => $class['title'], 'event_id' => 0, 'added' => 0];
            continue;
        }
        $n++;
        $got = alocom_add_event_users($event_id, $payload);
        $added += $got;
        $per[] = ['class' => $class['title'], 'event_id' => $event_id, 'added' => $got];
    }
    return [
        'ok' => true,
        'events' => $n,
        'added' => $added,
        'admins' => alocom_supervisor_usernames(),
        'classes' => $per,
    ];
}

function alocom_event_role_fallbacks($role) {
    $role = strtolower(trim((string)$role));
    if ($role === 'admin' || $role === 'teacher') {
        return array_values(array_unique([$role ?: 'admin', 'admin', 'teacher']));
    }
    if ($role === 'assistant' || $role === 'moderator') {
        return array_values(array_unique([$role, 'assistant', 'moderator', 'teacher']));
    }
    return ['participant'];
}

function alocom_add_event_users($event_id, array $users) {
    $event_id = (int)$event_id;
    $clean = [];
    $seen = [];
    $rank = ['admin' => 4, 'teacher' => 3, 'assistant' => 2, 'moderator' => 2, 'participant' => 1];
    foreach ($users as $u) {
        if (!is_array($u)) {
            continue;
        }
        $uid = (int)($u['userid'] ?? 0);
        $role = strtolower(trim((string)($u['role'] ?? '')));
        if ($uid <= 0 || $role === '') {
            continue;
        }
        if (!isset($seen[$uid]) || ($rank[$role] ?? 0) > ($rank[$seen[$uid]['role']] ?? 0)) {
            $seen[$uid] = ['userid' => $uid, 'role' => $role];
        }
    }
    $clean = array_values($seen);
    if ($event_id <= 0 || !$clean) {
        return 0;
    }
    try {
        alocom_request('POST', '/api/v1/agents/events/' . $event_id . '/add-users', [
            'users' => $clean,
        ]);
        return count($clean);
    } catch (Exception $e) {
        alocom_log('add-users batch event=' . $event_id . ': ' . $e->getMessage());
    }
    $ok = 0;
    foreach ($clean as $u) {
        $done = false;
        foreach (alocom_event_role_fallbacks($u['role']) as $role) {
            try {
                alocom_request('POST', '/api/v1/agents/events/' . $event_id . '/add-users', [
                    'users' => [['userid' => (int)$u['userid'], 'role' => $role]],
                ]);
                $ok++;
                $done = true;
                break;
            } catch (Exception $e2) {
                alocom_log('add-users user=' . $u['userid'] . ' role=' . $role . ': ' . $e2->getMessage());
            }
        }
        if (!$done) {
            alocom_log('add-users failed user=' . $u['userid'] . ' event=' . $event_id);
        }
    }
    return $ok;
}

function alocom_class_students(PDO $pdo, $class_id) {
    $class_id = (int)$class_id;
    if ($class_id <= 0) {
        return [];
    }
    $st = $pdo->prepare("
        SELECT u.id, u.full_name, u.username, u.phone
        FROM users u
        JOIN class_students cs ON cs.user_id = u.id
        WHERE cs.class_id = ?
          AND u.role = 'student'
          AND u.is_deleted = 0
        ORDER BY u.full_name
    ");
    $st->execute([$class_id]);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Register teacher + class students on the AloCom event.
 * Class membership, not paid-entry; runs even if the term has not started.
 */
function alocom_sync_class_event_roster(PDO $pdo, array $class, array $event = null) {
    if (!alocom_class_needs_event($class)) {
        return ['ok' => true, 'skipped' => 'no alocom room', 'added' => 0, 'students' => 0, 'teacher' => false];
    }
    alocom_ensure_tables($pdo);
    if (!is_array($event) || alocom_event_id_from($event) <= 0) {
        $event = alocom_ensure_class_event($pdo, $class);
    }
    $event_id = alocom_event_id_from($event);
    if ($event_id <= 0) {
        return ['ok' => false, 'error' => 'no event', 'added' => 0, 'students' => 0, 'teacher' => false];
    }

    $payload = [];
    $teacher_ok = false;
    $teacher_users = alocom_teacher_event_users($pdo, (int)($class['teacher_id'] ?? 0));
    if ($teacher_users) {
        $payload = array_merge($payload, $teacher_users);
        $teacher_ok = true;
    } else {
        alocom_log('roster no teacher match class=' . ($class['id'] ?? '') . ' teacher=' . ($class['teacher_id'] ?? ''));
    }
    $payload = array_merge($payload, alocom_supervisor_event_users($pdo));

    $student_ok = 0;
    $student_fail = [];
    foreach (alocom_class_students($pdo, (int)($class['id'] ?? 0)) as $student) {
        try {
            $account = alocom_ensure_student_account($pdo, $student);
            $uid = (int)($account['id'] ?? 0);
            if ($uid <= 0) {
                $student_fail[] = (string)($student['username'] ?? '');
                continue;
            }
            $payload[] = ['userid' => $uid, 'role' => 'participant'];
            alocom_state_upsert($pdo, $event_id, $uid, 'auto', 1, (int)($student['id'] ?? 0) ?: null);
            $student_ok++;
        } catch (Exception $e) {
            $student_fail[] = (string)($student['username'] ?? '');
            alocom_log('roster student ' . ($student['username'] ?? '') . ': ' . $e->getMessage());
        }
    }

    $added = alocom_add_event_users($event_id, $payload);
    alocom_log(
        'roster class=' . ($class['id'] ?? '') . ' event=' . $event_id
        . ' teacher=' . ($teacher_ok ? '1' : '0')
        . ' students=' . $student_ok
        . ' added=' . $added
        . ($student_fail ? (' fail=' . implode(',', $student_fail)) : '')
    );
    return [
        'ok' => $added > 0,
        'event_id' => $event_id,
        'added' => $added,
        'students' => $student_ok,
        'teacher' => $teacher_ok,
        'failed' => $student_fail,
        'link' => trim((string)($event['online_link'] ?? $event['alocom_link'] ?? '')),
    ];
}

function alocom_ensure_class_event(PDO $pdo, array $class) {
    if (!alocom_class_needs_event($class)) {
        return null;
    }
    $class_id = (int)($class['id'] ?? 0);
    $events = alocom_list_events();
    $found = alocom_match_event_by_link($class, $events);
    if ($found) {
        $link = trim((string)($found['alocom_link'] ?? alocom_event_href($found) ?: ($class['online_link'] ?? '')));
        if ($link !== '' && $class_id > 0 && $link !== trim((string)($class['online_link'] ?? ''))) {
            $pdo->prepare('UPDATE classes SET online_link = ? WHERE id = ?')->execute([$link, $class_id]);
        }
        $found['online_link'] = $link;
        $found['alocom_link'] = $link;
        $found['created'] = false;
        $event_id = alocom_event_id_from($found);
        if ($event_id > 0) {
            try {
                alocom_allow_join_before_admin($event_id, $found);
            } catch (Exception $e) {
                alocom_log('allow join before admin existing ' . $event_id . ': ' . $e->getMessage());
            }
        }
        return $found;
    }

    $service_id = alocom_pick_service_id();
    $slug = alocom_event_slug($class);
    $created = null;
    $last = null;
    $try_slug = $slug;
    $try_title = alocom_event_title($class, 0);
    for ($i = 0; $i < 4; $i++) {
        $try_slug = $i === 0 ? $slug : ($slug . '-' . substr(md5((string)microtime(true) . $i), 0, 5));
        $try_title = alocom_event_title($class, $i);
        try {
            $created = alocom_create_event($try_title, $try_slug, $service_id, []);
            break;
        } catch (Exception $e) {
            $last = $e;
            alocom_log('ensure event try title=' . $try_title . ' slug=' . $try_slug . ': ' . $e->getMessage());
        }
    }
    if (!is_array($created)) {
        throw ($last ?: new Exception('ساخت رویداد الوکام ناموفق بود.'));
    }
    $event_id = alocom_event_id_from($created);
    $link = alocom_event_link_from($created);
    $detail = null;
    if ($event_id > 0) {
        try {
            $detail = alocom_event_detail($event_id);
            $node = $detail['event'] ?? $detail['data'] ?? $detail;
            if ($link === '' && is_array($node)) {
                $link = alocom_event_href($node);
            }
        } catch (Exception $e) {
            alocom_log('new event detail: ' . $e->getMessage());
        }
    }
    if ($link === '') {
        $slug_got = trim((string)(($created['event']['slug'] ?? null) ?: ($created['slug'] ?? $try_slug)));
        $link = alocom_public_link_for_slug($slug_got, $events);
    }
    if ($link !== '' && $class_id > 0) {
        $pdo->prepare('UPDATE classes SET online_link = ? WHERE id = ?')->execute([$link, $class_id]);
    }
    if ($event_id > 0) {
        try {
            $detail = is_array($detail) ? $detail : alocom_event_detail($event_id);
            alocom_close_guest($event_id, $detail);
        } catch (Exception $e) {
            alocom_log('close guest new event ' . $event_id . ': ' . $e->getMessage());
        }
        try {
            alocom_add_event_users($event_id, array_merge(
                alocom_teacher_event_users($pdo, (int)($class['teacher_id'] ?? 0)),
                alocom_supervisor_event_users($pdo)
            ));
        } catch (Exception $e) {
            alocom_log('add teacher/supervisors new event ' . $event_id . ': ' . $e->getMessage());
        }
    }
    alocom_log('created event class=' . $class_id . ' event=' . $event_id . ' link=' . $link . ' svc=' . $service_id);
    if ($link === '' && $event_id <= 0) {
        throw new Exception('ساخت رویداد الوکام ناموفق بود.');
    }
    return [
        'id' => $event_id,
        'title' => $try_title,
        'slug' => $created['event']['slug'] ?? $created['slug'] ?? $try_slug,
        'alocom_link' => $link,
        'online_link' => $link,
        'created' => true,
    ];
}

function alocom_event_belongs_to_class(array $class, array $event) {
    $class_id = (int)($class['id'] ?? 0);
    $want_link = alocom_normalize_link($class['online_link'] ?? '');
    $got_link = alocom_normalize_link(alocom_event_href($event));
    if ($want_link !== '' && $got_link !== '' && $want_link === $got_link) {
        return true;
    }
    $want_slug = alocom_slug_from_link($class['online_link'] ?? '');
    $got_slug = strtolower(trim((string)($event['slug'] ?? '')));
    if ($got_slug === '') {
        $got_slug = alocom_slug_from_link(alocom_event_href($event));
    }
    if ($want_slug !== '' && $got_slug !== '' && $want_slug === $got_slug) {
        return true;
    }
    $prefix = $class_id > 0 ? ('gz-' . $class_id . '-') : '';
    if ($prefix !== '' && $got_slug !== '' && strpos($got_slug, $prefix) === 0) {
        return true;
    }
    if ($prefix !== '' && $got_link !== '' && strpos($got_link, $prefix) !== false) {
        return true;
    }
    return false;
}

function alocom_delete_class_event(PDO $pdo, array $class) {
    $type = strtolower(trim((string)($class['class_type'] ?? '')));
    $link = trim((string)($class['online_link'] ?? ''));
    $class_id = (int)($class['id'] ?? 0);
    if ($type !== 'online' && $type !== 'private' && $link === '') {
        return ['ok' => true, 'skipped' => 'not online'];
    }
    $events = alocom_list_events();
    $event = alocom_match_event_by_link($class, $events);
    if (!$event || !alocom_event_belongs_to_class($class, $event)) {
        alocom_log('delete class event none class=' . $class_id . ' title=' . ($class['title'] ?? ''));
        return ['ok' => true, 'skipped' => 'no event'];
    }
    $event_id = alocom_event_id_from($event);
    if ($event_id <= 0) {
        return ['ok' => false, 'error' => 'bad event'];
    }
    alocom_delete_event($event_id);
    try {
        alocom_ensure_tables($pdo);
        $pdo->prepare('DELETE FROM alocom_access_state WHERE event_id = ?')->execute([$event_id]);
    } catch (Exception $e) {
        alocom_log('delete access state event=' . $event_id . ': ' . $e->getMessage());
    }
    if ($class_id > 0) {
        try {
            $pdo->prepare("UPDATE classes SET online_link = '' WHERE id = ?")->execute([$class_id]);
        } catch (Exception $e) {
            alocom_log('clear online_link class=' . $class_id . ': ' . $e->getMessage());
        }
    }
    alocom_log('deleted event class=' . $class_id . ' event=' . $event_id . ' title=' . ($event['title'] ?? ''));
    return [
        'ok' => true,
        'deleted' => true,
        'event_id' => $event_id,
        'title' => (string)($event['title'] ?? ''),
    ];
}

function alocom_enroll_participant($event_id, $user_id) {
    alocom_request('POST', '/api/v1/agents/events/' . (int)$event_id . '/add-users', [
        'users' => [
            ['userid' => (int)$user_id, 'role' => 'participant'],
        ],
    ]);
}

function alocom_enroll_user_with_token($event_id, array $student, $role = 'participant') {
    list($name, $surname) = alocom_split_name($student['full_name'] ?? '');
    $username = alocom_readable_username($student['username'] ?? '');
    $body = [
        'name' => $name !== '' ? $name : 'Student',
        'surname' => $surname !== '' ? $surname : 'Ghazal',
        'username' => $username,
        'role' => $role,
    ];
    $uid = (int)($student['alocom_id'] ?? 0);
    if ($uid <= 0) {
        $maybe = (int)($student['id'] ?? 0);
        if ($maybe > 10000) {
            $uid = $maybe;
        }
    }
    if ($uid > 0) {
        $body['userid'] = $uid;
    }
    return alocom_request('POST', '/api/v1/agents/events/' . (int)$event_id . '/enroll-user-with-token', $body);
}

/**
 * Put a panel student into the AloCom event for that online class.
 * This is class membership, not the paid-entry (account status) sync.
 */
function alocom_enroll_panel_student_in_event(PDO $pdo, array $student, array $class, array $events = null) {
    $type = strtolower(trim((string)($class['class_type'] ?? '')));
    $link = trim((string)($class['online_link'] ?? ''));
    if ($type !== 'online' && $type !== 'private' && $link === '' && empty($class['create_alocom'])) {
        return ['ok' => true, 'skipped' => 'not online'];
    }
    if ($link === '' && $type !== 'online' && $type !== 'private' && empty($class['create_alocom'])) {
        return ['ok' => true, 'skipped' => 'no link'];
    }
    alocom_ensure_tables($pdo);
    try {
        $account = alocom_ensure_student_account($pdo, $student);
    } catch (Exception $e) {
        alocom_log('class-event ensure ' . ($student['username'] ?? '') . ': ' . $e->getMessage());
        return ['ok' => false, 'error' => $e->getMessage()];
    }
    $uid = (int)($account['id'] ?? 0);
    if ($uid <= 0) {
        return ['ok' => false, 'error' => 'no alocom user'];
    }
    $event = null;
    if (is_array($events) && $events) {
        $event = alocom_match_event_by_link($class, $events);
        if (!$event && count($events) === 1 && !empty($events[0]['id'])) {
            $event = alocom_stamp_event_link($events[0]);
        }
    }
    if (!$event) {
        try {
            $event = alocom_match_event_by_link($class, alocom_list_events());
        } catch (Exception $e) {
            alocom_log('class-event list: ' . $e->getMessage());
        }
    }
    if (!$event && alocom_class_needs_event($class)) {
        try {
            $event = alocom_ensure_class_event($pdo, $class);
            if (is_array($event) && trim((string)($event['online_link'] ?? '')) !== '') {
                $class['online_link'] = $event['online_link'];
                $link = $class['online_link'];
            }
        } catch (Exception $e) {
            alocom_log('class-event ensure during enroll: ' . $e->getMessage());
        }
    }
    if (!$event && ($type === 'private' || $link !== '')) {
        $event = alocom_find_event_for_class($class, is_array($events) ? $events : null);
    }
    if (!$event) {
        alocom_log('class-event no match class=' . ($class['id'] ?? '') . ' ' . ($class['title'] ?? '') . ' link=' . $link);
        return ['ok' => false, 'error' => 'no AloCom event'];
    }
    $event_id = (int)($event['id'] ?? 0);
    if ($event_id <= 0) {
        return ['ok' => false, 'error' => 'bad event'];
    }
    $already = false;
    $sign_in = '';
    try {
        $detail = alocom_event_detail($event_id);
        foreach ($detail['enrolledUsers'] ?? [] as $eu) {
            if ((int)($eu['userid'] ?? 0) === $uid) {
                $already = true;
                break;
            }
        }
        // Token enroll only mints a join URL; add-users is what keeps the student on the event roster.
        if (!$already) {
            alocom_enroll_participant($event_id, $uid);
        }
        $token_student = $student;
        $token_student['alocom_id'] = $uid;
        $token_student['username'] = $account['username'] ?? ($student['username'] ?? '');
        try {
            $tok = alocom_enroll_user_with_token($event_id, $token_student);
            $sign_in = alocom_extract_sign_in_url($tok);
            if ($sign_in === '' && is_array($tok)) {
                $token = alocom_node_token($tok);
                if ($token === '' && !empty($tok['data']) && is_array($tok['data'])) {
                    $token = alocom_node_token($tok['data']);
                }
                $base = (string)($event['alocom_link'] ?? $link);
                if ($token !== '' && $base !== '') {
                    $sign_in = alocom_append_event_token(preg_replace('/[?#].*$/', '', $base), $token);
                }
            }
            if ($sign_in !== '' && !alocom_url_is_direct_entry($sign_in)) {
                $sign_in = '';
            }
            if ($sign_in === '' && is_array($tok)) {
                alocom_log('token enroll keys code=' . ($student['username'] ?? '') . ' keys=' . implode(',', array_keys($tok)));
            } elseif ($sign_in !== '') {
                $parts = parse_url($sign_in);
                alocom_log(
                    'token enroll url code=' . ($student['username'] ?? '')
                    . ' host=' . ($parts['host'] ?? '')
                    . ' path=' . ($parts['path'] ?? '')
                    . ' has_query=' . (!empty($parts['query']) ? '1' : '0')
                );
            }
        } catch (Exception $e) {
            alocom_log('token enroll ' . ($student['username'] ?? '') . ': ' . $e->getMessage());
        }
        alocom_state_upsert($pdo, $event_id, $uid, 'auto', 1, (int)($student['id'] ?? 0) ?: null);
    } catch (Exception $e) {
        alocom_log('class-event add ' . ($student['username'] ?? '') . ' event=' . $event_id . ': ' . $e->getMessage());
        return ['ok' => false, 'error' => $e->getMessage(), 'alocom_id' => $uid, 'event_id' => $event_id];
    }
    alocom_log('class-event ' . ($already ? 'already' : 'added') . ' code=' . ($student['username'] ?? '') . ' class=' . ($class['title'] ?? '') . ' event=' . $event_id . ' user=' . $uid);
    return [
        'ok' => true,
        'already' => $already,
        'alocom_id' => $uid,
        'event_id' => $event_id,
        'event' => $event['title'] ?? '',
        'link' => $sign_in !== '' ? $sign_in : ($event['alocom_link'] ?? $link),
        'sign_in' => $sign_in,
    ];
}

function alocom_events_with_enrollments(array $events = null) {
    if (!empty($GLOBALS['_alocom_events_enroll_cache']) && is_array($GLOBALS['_alocom_events_enroll_cache'])) {
        return $GLOBALS['_alocom_events_enroll_cache'];
    }
    $events = $events !== null ? $events : alocom_list_events();
    $out = [];
    foreach ($events as $event) {
        $event_id = (int)($event['id'] ?? 0);
        if ($event_id <= 0) {
            continue;
        }
        try {
            $detail = alocom_event_detail($event_id);
        } catch (Exception $e) {
            continue;
        }
        $out[$event_id] = [
            'event' => $event,
            'detail' => $detail,
        ];
    }
    $GLOBALS['_alocom_events_enroll_cache'] = $out;
    return $out;
}

function alocom_unenroll_user_from_events($user_id, array $events = null) {
    $user_id = (int)$user_id;
    if ($user_id <= 0) {
        return [];
    }
    $removed = [];
    $cache = alocom_events_with_enrollments($events);
    foreach ($cache as $event_id => $row) {
        $hit = false;
        $kept = [];
        foreach ($row['detail']['enrolledUsers'] ?? [] as $eu) {
            if (!alocom_is_staff_role($eu['role'] ?? '') && (int)($eu['userid'] ?? 0) === $user_id) {
                $hit = true;
                continue;
            }
            $kept[] = $eu;
        }
        if (!$hit) {
            continue;
        }
        try {
            alocom_remove_enrollments($event_id, [$user_id]);
            $GLOBALS['_alocom_events_enroll_cache'][$event_id]['detail']['enrolledUsers'] = $kept;
            $removed[] = $row['event']['title'] ?? (string)$event_id;
        } catch (Exception $e) {
            alocom_log('unenroll ' . $user_id . ' from ' . $event_id . ': ' . $e->getMessage());
        }
    }
    return $removed;
}

function alocom_revoke_student_account(PDO $pdo, array $student, array $events = null) {
    $username = alocom_readable_username($student['username'] ?? '');
    $existing = alocom_locate_student_user($student);
    if (!$existing || alocom_is_staff_username($existing['username'] ?? '')) {
        return [
            'id' => 0,
            'username' => $username,
            'password' => '',
            'removed_from' => [],
        ];
    }
    list($name, $surname) = alocom_split_name($student['full_name'] ?? '');
    $phone = rollcall_normalize_phone($student['phone'] ?? '');
    $candidates = alocom_password_candidates($username);
    $saved = alocom_saved_credential($pdo, (int)$student['id']);
    $password = '';
    try {
        $patch = [
            'name' => $name,
            'surname' => $surname,
            'username' => $username !== '' ? $username : (string)($existing['username'] ?? ''),
            'status' => 0,
        ];
        if ($phone !== '') {
            $patch['cellphone'] = $phone;
        }
        alocom_request('PATCH', '/api/v1/agents/users/' . (int)$existing['id'], $patch);
        alocom_all_users_invalidate();
    } catch (Exception $e) {
        alocom_log('revoke rename ' . ($existing['username'] ?? '') . ': ' . $e->getMessage());
        try {
            alocom_set_user_status((int)$existing['id'], false);
        } catch (Exception $e2) {
            alocom_log('revoke status ' . ($existing['username'] ?? '') . ': ' . $e2->getMessage());
        }
    }
    try {
        $want_reset = !$saved || !in_array((string)($saved['password_plain'] ?? ''), $candidates, true);
        if ($want_reset) {
            $password = alocom_try_set_password((int)$existing['id'], $candidates);
        } else {
            $password = (string)$saved['password_plain'];
        }
    } catch (Exception $e) {
        $password = (string)($saved['password_plain'] ?? ($candidates[1] ?? $candidates[0] ?? ''));
        alocom_log('revoke password ' . $username . ': ' . $e->getMessage());
    }
    alocom_store_credential($pdo, (int)$student['id'], (int)$existing['id'], $username !== '' ? $username : (string)$existing['username'], $password);
    // Keep event membership; payment access is account status, not class roster.
    return [
        'id' => (int)$existing['id'],
        'username' => $username,
        'password' => $password,
        'removed_from' => [],
    ];
}

function alocom_repair_leftover_accounts(PDO $pdo, array $events = null) {
    $stmt = $pdo->query("
        SELECT id, full_name, username, phone
        FROM users
        WHERE role = 'student' AND is_deleted = 0
        ORDER BY full_name
    ");
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (!function_exists('identity_student_has_settled_teaching_class')) {
        require_once __DIR__ . '/identity-sync.php';
    }
    $renamed = [];
    $revoked = [];
    foreach ($students as $student) {
        $code = (string)($student['username'] ?? '');
        if (!preg_match('/^[0-9]{3,4}$/', $code) || (int)$code >= 5000) {
            continue;
        }
        $settled = identity_student_has_settled_teaching_class($pdo, $student);
        $teaching = identity_student_active_classes($pdo, (int)$student['id']);
        if (!$settled && $teaching) {
            continue;
        }
        if (!$settled) {
            continue;
        }
        $existing = alocom_locate_student_user($student);
        if (!$existing || alocom_is_staff_username($existing['username'] ?? '')) {
            continue;
        }
        $current = strtolower(trim((string)($existing['username'] ?? '')));
        if ($current === strtolower($code)) {
            continue;
        }
        try {
            if ($settled) {
                alocom_ensure_student_account($pdo, $student);
            } else {
                list($name, $surname) = alocom_split_name($student['full_name'] ?? '');
                alocom_request('PATCH', '/api/v1/agents/users/' . (int)$existing['id'], [
                    'name' => $name,
                    'surname' => $surname,
                    'username' => $code,
                    'status' => (int)($existing['status'] ?? 1),
                ]);
                alocom_all_users_invalidate();
            }
            $renamed[] = $code;
        } catch (Exception $e) {
            alocom_log('repair rename ' . $code . ': ' . $e->getMessage());
        }
    }
    return ['renamed' => $renamed, 'revoked' => $revoked];
}

function alocom_set_user_status($user_id, $active, $invalidate = true) {
    $user_id = (int)$user_id;
    if ($user_id <= 0) {
        return false;
    }
    $existing = alocom_find_user_by_id($user_id);
    $name = trim((string)($existing['name'] ?? ''));
    $surname = trim((string)($existing['surname'] ?? ''));
    if ($name === '') {
        $name = 'Student';
    }
    if ($surname === '') {
        $surname = 'Ghazal';
    }
    $payload = [
        'name' => $name,
        'surname' => $surname,
        'status' => $active ? 1 : 0,
    ];
    $username = trim((string)($existing['username'] ?? ''));
    if ($username !== '') {
        $payload['username'] = $username;
    }
    alocom_request('PATCH', '/api/v1/agents/users/' . $user_id, $payload);
    if ($invalidate) {
        alocom_all_users_invalidate();
    }
    return true;
}

/**
 * Close leftover student AloCom logins that are not Fall 1 paid and not private.
 * Re-open entitled accounts that were left off.
 * Never treat panel teachers/supervisors (including leftover first-name logins) as students.
 */
function alocom_ensure_teacher_accounts_open(PDO $pdo, $dry_run = false) {
    alocom_panel_staff_usernames($pdo);
    $st = $pdo->query("
        SELECT id, full_name, username
        FROM users
        WHERE is_deleted = 0 AND role IN ('teacher', 'supervisor')
        ORDER BY full_name
    ");
    $opened = [];
    $missing = [];
    $already = 0;
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $u = alocom_find_staff_user($pdo, (int)$row['id']);
        if (!$u) {
            $missing[] = (string)($row['username'] ?? $row['id']);
            continue;
        }
        $alo_id = (int)($u['id'] ?? 0);
        $uname = (string)($u['username'] ?? '');
        if ($alo_id <= 0) {
            $missing[] = (string)($row['username'] ?? $row['id']);
            continue;
        }
        if ($uname !== '') {
            try {
                alocom_ensure_tables($pdo);
                if (!alocom_saved_credential($pdo, (int)$row['id'])) {
                    alocom_store_credential(
                        $pdo,
                        (int)$row['id'],
                        $alo_id,
                        $uname,
                        alocom_readable_password($uname)
                    );
                }
            } catch (Exception $e) {
                alocom_log('teacher cred ' . $uname . ': ' . $e->getMessage());
            }
        }
        if ((int)($u['status'] ?? 1) === 1) {
            $already++;
            continue;
        }
        if (!$dry_run) {
            try {
                alocom_set_user_status($alo_id, true, false);
            } catch (Exception $e) {
                alocom_log('teacher on ' . $uname . ': ' . $e->getMessage());
                continue;
            }
        }
        $opened[] = $uname !== '' ? $uname : (string)$alo_id;
        alocom_log('teacher reopen ' . $uname);
    }
    if (!$dry_run) {
        alocom_all_users_invalidate();
    }
    return [
        'opened' => $opened,
        'missing' => $missing,
        'already' => $already,
    ];
}

function alocom_sync_unentitled_student_accounts(PDO $pdo, $dry_run = false) {
    if (!function_exists('identity_student_has_settled_teaching_class')) {
        require_once __DIR__ . '/identity-sync.php';
    }
    alocom_all_users_invalidate();
    alocom_panel_staff_usernames($pdo);
    $stmt = $pdo->query("
        SELECT id, full_name, username, phone
        FROM users
        WHERE role = 'student' AND is_deleted = 0
        ORDER BY full_name
    ");
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $keep_ids = [];
    $close_ids = [];
    $closed = [];
    $opened = [];
    foreach ($students as $student) {
        $code = (string)($student['username'] ?? '');
        if (!preg_match('/^[0-9]{3,8}$/', $code) || (int)$code >= 5000) {
            continue;
        }
        $keep = identity_student_has_settled_teaching_class($pdo, $student);
        $existing = alocom_locate_student_user($student);
        if (!$existing || alocom_is_staff_username($existing['username'] ?? '')) {
            continue;
        }
        $alo_id = (int)($existing['id'] ?? 0);
        if ($alo_id <= 0) {
            continue;
        }
        if ($keep) {
            $keep_ids[$alo_id] = $code;
        } else {
            $close_ids[$alo_id] = $code;
        }
    }
    foreach (alocom_all_users() as $u) {
        $uname = (string)($u['username'] ?? '');
        if (alocom_is_staff_username($uname) || !alocom_username_looks_like_student($uname)) {
            continue;
        }
        $alo_id = (int)($u['id'] ?? 0);
        if ($alo_id <= 0 || isset($keep_ids[$alo_id])) {
            continue;
        }
        if (!isset($close_ids[$alo_id])) {
            $close_ids[$alo_id] = $uname;
        }
    }
    foreach ($keep_ids as $alo_id => $code) {
        $u = alocom_find_user_by_id($alo_id);
        $on = $u && (int)($u['status'] ?? 1) === 1;
        if (!$on) {
            if (!$dry_run) {
                try {
                    alocom_set_user_status($alo_id, true, false);
                } catch (Exception $e) {
                    alocom_log('entitle on ' . $code . ': ' . $e->getMessage());
                    continue;
                }
            }
            $opened[] = $code;
        }
    }
    foreach ($close_ids as $alo_id => $code) {
        if (isset($keep_ids[$alo_id])) {
            continue;
        }
        $u = alocom_find_user_by_id($alo_id);
        if ($u && alocom_is_staff_username($u['username'] ?? '')) {
            continue;
        }
        if (!$dry_run) {
            try {
                alocom_set_user_status($alo_id, false, false);
            } catch (Exception $e) {
                alocom_log('entitle off ' . $code . ': ' . $e->getMessage());
                continue;
            }
        }
        $closed[] = $code;
    }
    if (!$dry_run) {
        alocom_all_users_invalidate();
    }
    return [
        'closed' => array_values(array_unique($closed)),
        'opened' => array_values(array_unique($opened)),
    ];
}

function alocom_remove_enrollments($event_id, array $user_ids) {
    $user_ids = array_values(array_unique(array_filter(array_map('intval', $user_ids))));
    if (!$user_ids) {
        return;
    }
    alocom_request('PATCH', '/api/v1/agents/events/' . (int)$event_id . '/remove-users', [
        'users' => $user_ids,
    ]);
}

/**
 * @return array{classes: array, denied: array, guest_closed: array}
 */
function alocom_sync_class_access(PDO $pdo, $dry_run = false, $notify = false) {
    alocom_ensure_tables($pdo);
    alocom_panel_staff_usernames($pdo);
    $stmt = $pdo->query("
        SELECT c.id, c.title, c.online_link, c.schedule, c.class_type, c.term_id, c.teacher_id
        FROM classes c
        JOIN terms t ON t.id = c.term_id
        WHERE c.is_deleted = 0 AND c.is_archived = 0 AND " . term_sql_teaching('t') . "
          AND (c.class_type = 'online' OR (c.online_link IS NOT NULL AND c.online_link <> ''))
        ORDER BY c.title
    ");
    $classes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $events = alocom_list_events();
    if (!function_exists('identity_student_has_settled_teaching_class')) {
        require_once __DIR__ . '/identity-sync.php';
    }

    $st_students = $pdo->prepare("
        SELECT u.id, u.full_name, u.username, u.phone
        FROM users u
        JOIN class_students cs ON cs.user_id = u.id
        WHERE cs.class_id = ?
          AND (cs.is_deleted = 0 OR cs.is_deleted IS NULL)
          AND u.role = 'student' AND u.is_deleted = 0
        ORDER BY u.full_name
    ");

    $out_classes = [];
    $denied = [];
    $guest_closed = [];

    foreach ($classes as $class) {
        $st_students->execute([$class['id']]);
        $students = $st_students->fetchAll(PDO::FETCH_ASSOC);
        $event = alocom_match_event($class, $events);
        $allowed_accounts = [];
        $roster_accounts = [];
        $class_denied = [];
        $class_allowed = [];

        foreach ($students as $student) {
            if (preg_match('/^[0-9]{3,8}$/', (string)$student['username']) && (int)$student['username'] >= 5000) {
                continue;
            }
            $acc = accounting_match_student_for_access($pdo, $student, $class);
            $settled = $acc && !empty($acc['settled']);
            $private = alocom_is_private_class($class);
            $open_event = alocom_event_skips_payment($class);
            $unknown = !$acc && !$private && !$open_event;
            if ($dry_run) {
                $username = alocom_readable_username($student['username']);
                $saved = alocom_saved_credential($pdo, (int)$student['id']);
                $row = [
                    'full_name' => $student['full_name'],
                    'username' => $saved['username'] ?? $username,
                    'password' => $saved['password_plain'] ?? alocom_readable_password($username),
                    'acc' => $acc['full'] ?? '',
                    'id' => 0,
                ];
                if ($settled || $private || $open_event) {
                    $class_allowed[] = $row;
                } else {
                    $reason = !$acc ? 'در حسابداری پیدا نشد' : 'بدهکار (تسویه نشده)';
                    $class_denied[] = [
                        'class' => $class['title'],
                        'full_name' => $student['full_name'],
                        'username' => $student['username'],
                        'reason' => $reason,
                        'acc' => $acc['full'] ?? '',
                    ];
                    $denied[] = end($class_denied);
                }
                continue;
            }
            if (!$settled && !$private && !$open_event) {
                $reason = $unknown ? 'در حسابداری پیدا نشد' : 'بدهکار (تسویه نشده)';
                $keep = identity_student_has_settled_teaching_class($pdo, $student);
                $existing = alocom_locate_student_user($student);
                $alo_id = (int)($existing['id'] ?? 0);
                try {
                    if ($keep) {
                        $account = alocom_ensure_student_account($pdo, $student);
                        $alo_id = (int)($account['id'] ?? $alo_id);
                    } elseif ($alo_id > 0 && !alocom_is_staff_username($existing['username'] ?? '')) {
                        alocom_set_user_status($alo_id, false);
                    }
                } catch (Exception $e) {
                    alocom_log('deactivate ' . ($student['username'] ?? '') . ': ' . $e->getMessage());
                }
                $row = [
                    'class' => $class['title'],
                    'full_name' => $student['full_name'],
                    'username' => $student['username'],
                    'reason' => $reason,
                    'acc' => $acc['full'] ?? '',
                    'alocom_id' => $alo_id,
                ];
                $denied[] = $row;
                $class_denied[] = $row;
                continue;
            }
            try {
                $account = alocom_ensure_student_account($pdo, $student);
            } catch (Exception $e) {
                alocom_log('ensure account ' . ($student['username'] ?? '') . ': ' . $e->getMessage());
                $class_denied[] = [
                    'class' => $class['title'],
                    'full_name' => $student['full_name'],
                    'username' => $student['username'],
                    'reason' => 'خطای ساخت کاربر الوکام',
                    'acc' => $acc['full'] ?? '',
                ];
                $denied[] = end($class_denied);
                continue;
            }
            $account['full_name'] = $student['full_name'];
            $account['acc'] = $acc['full'] ?? '';
            $account['panel_user_id'] = (int)$student['id'];
            try {
                alocom_set_user_status((int)$account['id'], true);
            } catch (Exception $e) {
                alocom_log('activate ' . ($student['username'] ?? '') . ': ' . $e->getMessage());
            }
            $roster_accounts[] = $account;
            $allowed_accounts[] = $account;
            $class_allowed[] = $account;
        }

        if ($event && !$dry_run) {
            $detail = alocom_event_detail($event['id']);
            $event_id = (int)$event['id'];
            try {
                alocom_close_guest($event_id, $detail);
                $guest_closed[] = $event['title'] ?? $class['title'];
            } catch (Exception $e) {
                alocom_log('guest_access close failed for ' . ($event['title'] ?? '') . ': ' . $e->getMessage());
            }

            $enrolled_ids = [];
            foreach ($detail['enrolledUsers'] ?? [] as $eu) {
                if (alocom_is_staff_role($eu['role'] ?? '')) {
                    continue;
                }
                $uid = (int)($eu['userid'] ?? 0);
                if ($uid) {
                    $enrolled_ids[$uid] = true;
                }
            }

            $state = alocom_state_get($pdo, $event_id);
            $roster_ids = [];
            foreach ($roster_accounts as $account) {
                $uid = (int)($account['id'] ?? 0);
                if (!$uid) {
                    continue;
                }
                $src = $state[$uid]['source'] ?? '';
                if ($src === 'manual_deny') {
                    continue;
                }
                $roster_ids[] = $uid;
                if (empty($enrolled_ids[$uid])) {
                    try {
                        alocom_enroll_participant($event_id, $uid);
                        $enrolled_ids[$uid] = true;
                    } catch (Exception $e) {
                        alocom_log('enroll failed ' . ($account['username'] ?? $uid) . ': ' . $e->getMessage());
                    }
                }
                alocom_state_upsert($pdo, $event_id, $uid, $src ?: 'auto', 1, (int)($account['panel_user_id'] ?? 0) ?: null);
            }

            $to_remove = [];
            foreach (array_keys($enrolled_ids) as $uid) {
                if (in_array($uid, $roster_ids, true)) {
                    continue;
                }
                $alo = alocom_find_user_by_id($uid);
                $uname = (string)($alo['username'] ?? '');
                if (alocom_is_staff_username($uname)) {
                    continue;
                }
                $to_remove[] = $uid;
                alocom_state_upsert($pdo, $event_id, $uid, 'auto', 0, $state[$uid]['panel_user_id'] ?? null);
            }
            if ($to_remove && !alocom_event_skips_payment($class)) {
                try {
                    alocom_remove_enrollments($event_id, $to_remove);
                } catch (Exception $e) {
                    alocom_log('remove enrollments ' . $event_id . ': ' . $e->getMessage());
                }
            }

            $teacher_users = alocom_teacher_event_users($pdo, (int)($class['teacher_id'] ?? 0));
            if ($teacher_users) {
                $teacher_uid = (int)$teacher_users[0]['userid'];
                $have_teacher = false;
                foreach ($detail['enrolledUsers'] ?? [] as $eu) {
                    if ((int)($eu['userid'] ?? 0) === $teacher_uid && alocom_is_staff_role($eu['role'] ?? '')) {
                        $have_teacher = true;
                        break;
                    }
                }
                if (!$have_teacher) {
                    try {
                        alocom_add_event_users($event_id, $teacher_users);
                    } catch (Exception $e) {
                        alocom_log('add teacher class=' . ($class['id'] ?? '') . ': ' . $e->getMessage());
                    }
                }
            }
        }

        $out_classes[] = [
            'class' => $class['title'],
            'event' => $event['title'] ?? '',
            'link' => $event['alocom_link'] ?? $class['online_link'],
            'allowed' => $class_allowed,
            'denied' => $class_denied,
        ];
    }

    $repaired = ['renamed' => [], 'revoked' => []];
    $supervisors = ['ok' => true, 'events' => 0, 'added' => 0];
    $teachers = ['opened' => [], 'missing' => [], 'already' => 0];
    if (!$dry_run) {
        $repaired = alocom_repair_leftover_accounts($pdo, $events);
        $supervisors = alocom_sync_supervisor_admins($pdo, $events);
    }
    $teachers = alocom_ensure_teacher_accounts_open($pdo, $dry_run);
    $leftover = alocom_sync_unentitled_student_accounts($pdo, $dry_run);
    if (!$dry_run) {
        $teachers = alocom_ensure_teacher_accounts_open($pdo, false);
    }

    if ($notify) {
        alocom_notify_credentials($out_classes, $denied);
    }

    return [
        'classes' => $out_classes,
        'denied' => $denied,
        'guest_closed' => $guest_closed,
        'dry_run' => $dry_run,
        'repaired' => $repaired,
        'leftover' => $leftover,
        'supervisors' => $supervisors,
        'teachers' => $teachers,
    ];
}

function alocom_notify_credentials(array $classes, array $denied) {
    $msg = "📋 **لیست ورود کلاس آنلاین (AloCom)**\n";
    $msg .= "مهمان بسته است. ورود فقط با یوزرنیم و پسورد زیر.\n\n";
    foreach ($classes as $c) {
        if (empty($c['allowed'])) {
            continue;
        }
        $msg .= "**" . $c['class'] . "**";
        if (!empty($c['event'])) {
            $msg .= "  ·  `" . $c['event'] . "`";
        }
        $msg .= "\n";
        if (!empty($c['link'])) {
            $msg .= $c['link'] . "\n";
        }
        $n = 1;
        foreach ($c['allowed'] as $s) {
            $parts = alocom_split_name($s['full_name']);
            $msg .= $n . ". " . $parts[0] . " / " . $parts[1]
                . "  ·  `" . $s['username'] . "`  ·  `" . $s['password'] . "`\n";
            $n++;
        }
        $msg .= "\n";
        if (strlen($msg) > 3500) {
            rollcall_send_mattermost($msg);
            $msg = "";
        }
    }
    if ($denied) {
        $msg .= "⛔ **بدون دسترسی (تسویه نشده یا در حسابداری نیست):**\n";
        foreach ($denied as $d) {
            $msg .= "- " . $d['full_name'] . " (" . $d['class'] . ") — " . $d['reason'] . "\n";
        }
    }
    if (trim($msg) !== '') {
        rollcall_send_mattermost($msg);
    }
}
