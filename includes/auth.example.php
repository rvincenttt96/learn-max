<?php
/**
 * Copy to includes/auth.php on the server. Do not commit live secrets in auth.php.
 */
if (session_status() !== PHP_SESSION_ACTIVE) {
    ini_set('session.cookie_lifetime', '2592000');
    ini_set('session.gc_maxlifetime', '2592000');
    session_set_cookie_params([
        'lifetime' => 2592000,
        'path' => '/',
        'domain' => '',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

if (empty($_SESSION['logged_in']) && !empty($_COOKIE['remember_token']) && isset($pdo) && $pdo instanceof PDO) {
    $stmt = $pdo->prepare(
        'SELECT id, username, role, full_name FROM users WHERE remember_token = ? AND is_deleted = 0 LIMIT 1'
    );
    $stmt->execute([$_COOKIE['remember_token']]);
    $row = $stmt->fetch();
    if ($row) {
        $_SESSION['logged_in'] = true;
        $_SESSION['user_id'] = $row['id'];
        $_SESSION['username'] = $row['username'];
        $_SESSION['role'] = $row['role'];
        $_SESSION['full_name'] = $row['full_name'] ?? '';
    }
}

function require_role($role): void
{
    $allowed = is_array($role) ? $role : [$role];
    if (empty($_SESSION['logged_in']) || !in_array((string)($_SESSION['role'] ?? ''), $allowed, true)) {
        $login = '/index.php';
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
            http_response_code(401);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'message' => 'نشست منقضی شده است.'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        header('Location: ' . $login);
        exit;
    }
}
