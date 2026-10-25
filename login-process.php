<?php
require_once 'includes/db.php';
require_once 'includes/digits.php';
require_once 'includes/german.php';

ini_set('session.cookie_lifetime', 2592000);
ini_set('session.gc_maxlifetime', 2592000);
session_set_cookie_params([
    'lifetime' => 2592000,
    'path' => '/',
    'domain' => '',
    'secure' => isset($_SERVER['HTTPS']),
    'httponly' => true,
    'samesite' => 'Lax'
]);

session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: index.php?error=درخواست نامعتبر");
    exit;
}

$username = trim(ghazal_normalize_digits($_POST['username'] ?? ''));
$password = ghazal_normalize_digits($_POST['password'] ?? '');
$remember_me = isset($_POST['remember_me']);

if ($username === '' || $password === '') {
    header("Location: index.php?error=لطفاً نام کاربری و رمز عبور را وارد کنید.");
    exit;
}

try {
    try {
        german_ensure_schema($pdo);
    } catch (Exception $e) {
        error_log('german bootstrap: ' . $e->getMessage());
    }

    $stmt = $pdo->prepare("SELECT id, username, password, role, full_name FROM users WHERE username = ? AND is_deleted = 0");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['logged_in'] = true;
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['full_name'] = $user['full_name'] ?? '';
        $_SESSION['welcome_overlay'] = true;

        if ($remember_me) {
            $token = bin2hex(random_bytes(32));
            $stmt = $pdo->prepare("UPDATE users SET remember_token = ? WHERE id = ?");
            $stmt->execute([$token, $user['id']]);

            setcookie('remember_token', $token, time() + 2592000, '/', '', isset($_SERVER['HTTPS']), true);
        } else {
            setcookie('remember_token', '', time() - 3600, '/', '', isset($_SERVER['HTTPS']), true);
        }

        $home = ghazal_dashboard_home($user['role']);
        if ($home === '') {
            header("Location: index.php?error=نقش کاربری نامعتبر است.");
            exit;
        }
        header("Location: " . $home);
        exit;
    }

    header("Location: index.php?error=نام کاربری یا رمز عبور اشتباه است.");
    exit;
} catch (Exception $e) {
    error_log("Login Error: " . $e->getMessage());
    header("Location: index.php?error=خطا در سیستم. لطفاً بعداً تلاش کنید.");
    exit;
}
