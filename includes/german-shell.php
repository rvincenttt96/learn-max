<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';
if (is_file(__DIR__ . '/panel-robots.php')) {
    require_once __DIR__ . '/panel-robots.php';
}
require_once __DIR__ . '/german.php';
require_once __DIR__ . '/panel-wallpaper.php';

if (!isset($german_role) || !german_is_german_role($german_role)) {
    $german_role = 'german_supervisor';
}
require_role($german_role);
german_ensure_schema($pdo);

$avatar_url = null;
$user_data = [];
if (isset($_SESSION['user_id'])) {
    $stmt = $pdo->prepare("SELECT avatar, full_name, username FROM users WHERE id = ? AND role = ?");
    $stmt->execute([$_SESSION['user_id'], $german_role]);
    $user_data = $stmt->fetch() ?: [];
    if ($user_data) {
        if (!isset($_SESSION['full_name']) && $user_data['full_name']) {
            $_SESSION['full_name'] = $user_data['full_name'];
        }
        if (!isset($_SESSION['username']) && !empty($user_data['username'])) {
            $_SESSION['username'] = $user_data['username'];
        }
        if (!isset($_SESSION['avatar']) && $user_data['avatar']) {
            $_SESSION['avatar'] = $user_data['avatar'];
        }
    }
}

$display_name = $_SESSION['full_name'] ?? 'کاربر';
$avatar_url = $_SESSION['avatar'] ?? null;
$panel_username = trim((string)($user_data['username'] ?? $_SESSION['username'] ?? ''));
$wall = ghazal_panel_wallpaper($panel_username);
$wall_class = $wall['class'] ?? '';
$role_labels = [
    'german_supervisor' => 'German Supervisor',
    'german_teacher' => 'German Teacher',
    'german_student' => 'German Student',
];
$role_label = $role_labels[$german_role] ?? 'آلمانی غزال';
if (!isset($title) || $title === '') {
    $title = $role_label;
}
if (!isset($current_page) || $current_page === '') {
    $current_page = basename($_SERVER['PHP_SELF'] ?? '');
}
if (!isset($german_nav) || !is_array($german_nav)) {
    $german_nav = [
        ['href' => 'index.php', 'label' => 'کلاس‌ها', 'icon' => 'eas'],
    ];
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl" class="german-html<?= $wall_class !== '' ? ' ' . htmlspecialchars($wall_class) : '' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#f5f5f7">
    <meta name="robots" content="noindex, nofollow">
    <link rel="icon" type="image/png" href="../../assets/icons/favicon-32x32.png">
    <title><?= german_h($title) ?> — آلمانی غزال</title>
    <link href="/assets/css/bootstrap.rtl.min.css" rel="stylesheet">
    <link href="/assets/css/dashboard-style.css?v=gs1" rel="stylesheet">
    <link href="/assets/css/liquid-glass.css?v=lg27" rel="stylesheet">
    <link href="/assets/css/german.css?v=g8" rel="stylesheet">
    <link href="/assets/css/german-theme.css?v=t2" rel="stylesheet">
    <?php if (!empty($wall) && !empty($wall['css'])): ?>
    <link href="<?= htmlspecialchars($wall['css']) ?>" rel="stylesheet">
    <?php endif; ?>
    <style>
        :root { --sidebar-width: 280px; }
        html, body.german-app {
            max-width: 100%;
            height: 100%;
        }
        body.german-app {
            overflow: hidden;
        }
        .german-app .dashboard-layout {
            display: flex !important;
            flex-direction: row !important;
            min-height: 100vh;
            min-height: 100dvh;
            width: 100%;
            max-width: 100%;
            background: transparent;
            overflow: hidden !important;
            align-items: stretch;
        }
        .german-app .sidebar {
            display: flex !important;
            flex-direction: column !important;
            width: var(--sidebar-width) !important;
            min-width: var(--sidebar-width) !important;
            max-width: var(--sidebar-width) !important;
            flex: 0 0 var(--sidebar-width) !important;
            z-index: 1000;
            position: sticky;
            top: 0;
            height: 100vh !important;
            height: 100dvh !important;
            max-height: 100dvh !important;
            overflow: hidden !important;
        }
        .german-app .sidebar-nav,
        .german-app .mobile-sidebar .sidebar-nav {
            padding: 16px 0 28px;
            flex: 1 1 auto;
            min-height: 0;
            overflow-y: auto;
            overflow-x: hidden;
            -webkit-overflow-scrolling: touch;
        }
        .german-app .main-content-wrapper {
            flex: 1 1 0% !important;
            width: auto !important;
            min-width: 0 !important;
            max-width: 100%;
            height: 100vh !important;
            height: 100dvh !important;
            overflow-y: auto !important;
            overflow-x: hidden !important;
        }
        .german-app .main-content {
            min-width: 0;
            max-width: 100%;
            padding: 18px 20px 28px;
        }
        .german-app .mobile-sidebar {
            position: fixed;
            top: 0;
            right: -280px;
            left: auto;
            width: 280px;
            height: 100vh;
            height: 100dvh;
            transform: none;
            transition: right 280ms cubic-bezier(0.32, 0.72, 0, 1);
            z-index: 1040;
            overflow-y: auto;
        }
        .german-app .mobile-sidebar.show { right: 0; }
        .german-app .btn-menu {
            background: #fff;
            border: 1px solid rgba(60, 60, 67, 0.12);
            width: 40px;
            height: 40px;
            border-radius: 12px;
            color: #1d1d1f;
            display: flex;
            align-items: center;
            justify-content: center;
            touch-action: manipulation;
            transition: transform 100ms cubic-bezier(0.23, 1, 0.32, 1), background-color 160ms ease;
        }
        .german-app .btn-menu:active { transform: scale(0.97); }
        .german-app .sidebar-backdrop {
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            display: none;
        }
        .german-app .sidebar-backdrop.show { display: block; }
        @media (hover: hover) and (pointer: fine) {
            .german-app .btn-menu:hover { background: #fff1f2; }
        }
        @media (max-width: 991.98px) {
            body.german-app { overflow: auto; height: auto; }
            .german-app .sidebar { display: none !important; }
            .german-app .dashboard-layout {
                flex-direction: column !important;
                overflow: visible !important;
                min-height: auto;
            }
            .german-app .main-content-wrapper {
                height: auto !important;
                min-height: calc(100dvh - 56px) !important;
                overflow: visible !important;
                width: 100% !important;
            }
            .german-app .main-content { padding: 12px 10px 28px; }
        }
        @media (prefers-reduced-motion: reduce) {
            .german-app .mobile-sidebar,
            .german-app .btn-menu { transition: none !important; }
        }
    </style>
</head>
<body class="lg-app german-app<?= $wall_class !== '' ? ' ' . htmlspecialchars($wall_class) : '' ?>">
    <div class="lg-orbs" aria-hidden="true">
        <span class="lg-orb lg-orb-a"></span>
        <span class="lg-orb lg-orb-b"></span>
        <span class="lg-orb lg-orb-c"></span>
        <div class="lg-grid-floor"></div>
    </div>
    <?php if (is_file(__DIR__ . '/welcome-overlay.php')) require_once __DIR__ . '/welcome-overlay.php'; ?>
    <nav class="mobile-navbar d-lg-none">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center w-100">
                <div class="col">
                   <button class="btn btn-menu" id="mobileMenuToggle" type="button" aria-label="منو">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 448 512"><path fill="currentColor" d="M0 80c0-8.8 7.2-16 16-16l416 0c8.8 0 16 7.2 16 16s-7.2 16-16 16L16 96C7.2 96 0 88.8 0 80zM0 256c0-8.8 7.2-16 16-16l416 0c8.8 0 16 7.2 16 16s-7.2 16-16 16L16 272c-8.8 0-16-7.2-16-16zM448 432c0 8.8-7.2 16-16 16L16 448c-8.8 0-16-7.2-16-16s7.2-16 16-16l416 0c8.8 0 16 7.2 16 16z"/></svg>
                </button>
                </div>
                <div class="title-nav col text-center"><?= german_h($title) ?></div>
                <div class="col"><img class="float-end" width="40" height="40" src="../../assets/icons/android-chrome-192x192.png" alt=""></div>
            </div>
        </div>
    </nav>
    <div class="dashboard-layout">
        <div class="sidebar d-none d-lg-block" id="sidebar">
            <div class="sidebar-header text-center py-4 px-2">
                <div class="position-relative d-inline-block mb-2">
                    <img src="<?= $avatar_url ? htmlspecialchars($avatar_url) : '../../uploads/avatars/daweid-icon-7797704_640.png' ?>"
                         alt="" class="rounded-circle" width="80" height="80" style="object-fit:cover;">
                </div>
                <div class="text-white mt-1">
                    <div class="fw-bold fs-6"><?= german_h($display_name) ?></div>
                    <small class="opacity-90"><?= german_h($role_label) ?></small>
                </div>
            </div>
            <div class="sidebar-nav">
                <?php foreach ($german_nav as $item) echo german_nav_item_html($item, $current_page); ?>
                <hr class="my-2 mx-3">
                <?= german_logout_item_html() ?>
            </div>
        </div>
        <div class="mobile-sidebar d-lg-none" id="mobileSidebar">
            <div class="sidebar-header text-center py-4 px-2">
                <div class="position-relative d-inline-block mb-2">
                    <img src="<?= $avatar_url ? htmlspecialchars($avatar_url) : '../../uploads/avatars/daweid-icon-7797704_640.png' ?>"
                         alt="" class="rounded-circle" width="60" height="60" style="object-fit:cover;">
                </div>
                <div class="text-white mt-1">
                    <div class="fw-bold fs-6"><?= german_h($display_name) ?></div>
                    <small class="opacity-90"><?= german_h($role_label) ?></small>
                </div>
            </div>
            <div class="sidebar-nav">
                <?php foreach ($german_nav as $item) echo german_nav_item_html($item, $current_page); ?>
                <hr class="my-2 mx-3">
                <?= german_logout_item_html() ?>
            </div>
        </div>
        <script src="../../assets/js/bootstrap.min.js"></script>
        <div class="main-content-wrapper lg-stage">
            <div class="main-content teacher-main-content german-main">
