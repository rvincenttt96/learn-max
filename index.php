<?php
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');
header('Content-Type: text/html; charset=UTF-8');

$user = null;
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}
if (!empty($_SESSION['user']) && is_array($_SESSION['user'])) {
    $candidate = [
        'id' => (string) ($_SESSION['user']['id'] ?? ''),
        'username' => (string) ($_SESSION['user']['username'] ?? ''),
        'role' => (string) ($_SESSION['user']['role'] ?? ''),
    ];
    if ($candidate['username'] !== '' && $candidate['role'] !== '') {
        $user = $candidate;
    }
}
if (function_exists('session_write_close')) {
    session_write_close();
}

$html = file_get_contents(__DIR__ . '/index.html');
if ($html === false) {
    http_response_code(500);
    echo 'index missing';
    exit;
}

$bootJson = json_encode(
    ['user' => $user],
    JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
);
if ($bootJson === false) {
    $bootJson = '{"user":null}';
}

$preload = '';
if ($user) {
    $preload =
        '<link rel="stylesheet" href="/assets/index-BzoE7TQo.css?v=acc-yekan2" crossorigin="anonymous">' .
        '<link rel="modulepreload" href="/assets/index-DMmAISFC.js?v=clock-fix1" crossorigin="anonymous">' .
        '<link rel="preload" href="/term-menu.js?v=term-tags-9" as="script">' .
        '<link rel="preload" href="/payroll.js?v=pay-boot4" as="script">' .
        '<link rel="preload" href="/acc-ui.js?v=mobi2" as="script">' .
        '<link rel="preload" href="/bookstore.js?v=books3" as="script">';
}

$html = str_replace('<!-- gz-boot-state -->', '<script>window.__GHAZAL_ACC_BOOT=' . $bootJson . ';</script>', $html);
$html = str_replace('<!-- gz-app-preload -->', $preload, $html);
echo $html;
