<?php
// error_reporting(E_ALL);
// ini_set('display_errors', 1);
require_once '../../includes/auth.php';
require_once '../../includes/db.php';
require_once '../../includes/terms.php';
require_once '../../includes/class-live.php'; 
if (!function_exists('ghazal_error_report') && is_file(__DIR__ . '/../../includes/error-log.php')) {
    require_once __DIR__ . '/../../includes/error-log.php';
}
if (is_file(__DIR__ . '/../../includes/panel-robots.php')) {
    require_once __DIR__ . '/../../includes/panel-robots.php';
}
require_role('consultant');
require_once __DIR__ . '/../../includes/consultant.php';
consultant_ensure_schema($pdo);
require_once __DIR__ . '/../../includes/panel-wallpaper.php';

$avatar_url = null;
$user_data = [];
if (isset($_SESSION['user_id'])) {
    $stmt = $pdo->prepare("SELECT avatar, full_name, username FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
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
$moon_theme = ($wall_class === 'lg-moon-5011');
if (!isset($title) || $title === '') {
    $title = 'پنل آموزشی';
}
if (!isset($current_page) || $current_page === '') {
    $current_page = basename($_SERVER['PHP_SELF'] ?? '');
}
$lg_nav_aliases = [
    'attendance-students.php' => 'attendance.php',
    'save-attendance.php' => 'attendance.php',
    'homework.php' => 'attendance.php',
    'homework-students.php' => 'attendance.php',
    'save-homework.php' => 'attendance.php',
    'session-plan-details.php' => 'session-plan.php',
    'term-plan-details.php' => 'term-plan.php',
    'assignment-file.php' => 'assignments.php',
];
if (isset($lg_nav_aliases[$current_page])) {
    $current_page = $lg_nav_aliases[$current_page];
}
$students_info_active = in_array($current_page, [
    'students-info.php',
    'students-info-view.php',
], true);
$psych_tests_active = in_array($current_page, [
    'psych-tests.php',
    'psych-view.php',
], true);
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl"<?= $wall_class !== '' ? ' class="' . htmlspecialchars($wall_class) . '"' : '' ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <link rel="icon" type="image/png" href="../../assets/icons/favicon-32x32.png">
    <link rel="apple-touch-icon" href="../../assets/icons/favicon-32x32.png">
    <title><?= $title ?> - پنل آموزشی</title>
    <link href="../../assets/css/bootstrap.rtl.min.css" rel="stylesheet">
    <link href="../../assets/css/dashboard-style.css?v=<?= time() ?>" rel="stylesheet">
    <link href="/assets/css/liquid-glass.css?v=lg27" rel="stylesheet">
    <link href="../../assets/css/consultant.css?v=c1" rel="stylesheet">
    <?php if (!empty($wall) && !empty($wall['css'])): ?>
    <link href="<?= htmlspecialchars($wall['css']) ?>" rel="stylesheet">
    <?php endif; ?>

<style>
    body, input, button, select, textarea, .form-control {
       
    }
    :root {
        --sidebar-width: 280px;
    }
    .dashboard-layout {
        display: flex;
        min-height: 100vh;
        background: transparent;
        overflow-x: hidden;
    }
    .main-content {
        padding: 10px;
        transition: padding 0.3s ease;
        min-height: 100vh;
    }
    .sidebar {
        display: flex;
        flex-direction: column;
        width: var(--sidebar-width);
        z-index: 1000;
        position: sticky;
        top: 0;
        height: 100vh;
        max-height: 100dvh;
        overflow: hidden;
        flex-shrink: 0;
    }
    .sidebar-header {
        padding: 24px 20px;
        color: white;
        text-align: center;
    }
    .sidebar-nav {
        padding: 16px 0 28px;
        flex: 1 1 auto;
        min-height: 0;
        overflow-y: auto;
        overflow-x: hidden;
        -webkit-overflow-scrolling: touch;
    }
    .nav-icon svg {
        width: 22px;
        height: 22px;
        display: block;
    }
    .sidebar-nav .nav-item {
        padding: 14px 24px;
        display: flex;
        align-items: center;
        gap: 12px;
        color: #fff;
        text-decoration: none;
        border-right: 3px solid transparent;
        transition: all 0.2s;
        flex-shrink: 0;
    }
    .nav-icon {
        font-size: 1.4rem;
        width: 28px;
        text-align: center;
    }
    
    /* ============= Navbar موبایل ============= */
    .mobile-navbar {
        padding: 12px 0;
        position: sticky;
        top: 0;
        z-index: 1000;
    }
    .btn-menu {
        background: rgba(255,255,255,0.2);
        border: none;
        width: 40px;
        height: 40px;
        border-radius: 8px;
        color: white;
        font-size: 1.2rem;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.2s;
    }
    .btn-menu:hover {
        background: rgba(255,255,255,0.3);
        transform: scale(1.05);
    }
    .navbar-brand {
        margin: 0 auto;
        text-align: center;
        color: white !important;
        font-weight: bold;
        font-size: 1.25rem;
    }
        .main-content-wrapper {
        background-image: none !important;
        background: transparent;

        flex: 1;
        height: 100vh;
        overflow-y: auto;
        overflow-x: hidden;
    }
    /* ============= منوی موبایل overlay ============= */
    .mobile-sidebar {
        position: fixed;
        top: 0;
        right: -280px;
        left: auto;
        width: 280px;
        height: 100vh;
        transform: none;
        transition: right 0.3s ease;
        z-index: 1040;
        overflow-y: auto;
    }
    .mobile-sidebar.show {
        right: 0;
        transform: none;
    }
    .mobile-sidebar .sidebar-header {
        padding: 20px 16px;
    }
    .mobile-sidebar .nav-item {
        padding: 10px 20px;
        font-size: 0.95rem;
    }
    
    /* ============= ماسک پس‌زمینه ============= */
    .sidebar-backdrop {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0,0,0,0.5);
        z-index: 1000;
        display: none;
    }
    .sidebar-backdrop.show {
        display: block;
    }
    
    /* ============= تنظیمات responsive ============= */
    @media (max-width: 991.98px) {    
        .sidebar {
            display: none !important;
        }
    }
    

</style>

<link rel="manifest" href="manifest.json">
<link rel="apple-touch-icon" href="assets/icons/android-chrome-192x192.png">
<meta name="theme-color" content="#08080C">
<script>
    if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('sw.js')
            .then(registration => {
                console.log('SW registered: ', registration.scope);
            })
            .catch(registrationError => {
                console.log('SW registration failed: ', registrationError);
            });
    });
    }
</script>
</head>
<body class="lg-app<?= $wall_class !== '' ? ' ' . htmlspecialchars($wall_class) : '' ?>">
    <div class="lg-orbs" aria-hidden="true">
        <span class="lg-orb lg-orb-a"></span>
        <span class="lg-orb lg-orb-b"></span>
        <span class="lg-orb lg-orb-c"></span>
        <span class="lg-orb lg-orb-d"></span>
        <span class="lg-orb lg-orb-e"></span>
        <div class="lg-grid-floor"></div>
    </div>
    <?php require_once __DIR__ . '/../../includes/welcome-overlay.php'; ?>
    <nav class="mobile-navbar d-lg-none">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center w-100">
                <div class="col">            
                   <button class="btn btn-menu" id="mobileMenuToggle">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 448 512"><path opacity=".4" fill="currentColor" d=""/><path fill="currentColor" d="M0 80c0-8.8 7.2-16 16-16l416 0c8.8 0 16 7.2 16 16s-7.2 16-16 16L16 96C7.2 96 0 88.8 0 80zM0 256c0-8.8 7.2-16 16-16l416 0c8.8 0 16 7.2 16 16s-7.2 16-16 16L16 272c-8.8 0-16-7.2-16-16zM448 432c0 8.8-7.2 16-16 16L16 448c-8.8 0-16-7.2-16-16s7.2-16 16-16l416 0c8.8 0 16 7.2 16 16z"/></svg>
                </button>
                </div>
                <div class="title-nav col text-center"><?= $title ?></div>
                <div class="col "><img class="float-end" width="40" height="40" src="../../assets/icons/android-chrome-192x192.png">
                </div>
            </div>
        </div>
    </nav>
    <div class="dashboard-layout">
        <div class="sidebar" id="sidebar">
<div class="sidebar-header text-center py-4 px-2">
    <div class="position-relative d-inline-block mb-2">
        <img src="<?= $avatar_url ? htmlspecialchars($avatar_url) : '../../uploads/avatars/daweid-icon-7797704_640.png' ?>" 
     alt="عکس مشاور" 
     class="rounded-circle" 
     width="80" height="80"
     style="object-fit: cover;">
    </div>
    <div class="text-white mt-1">
        <div class="fw-bold fs-6"><?= htmlspecialchars($display_name) ?></div>
        <small class="opacity-90">Consultant</small>
    </div>
</div>
                        <div class="sidebar-nav">
                <a href="index.php" class="nav-item <?= $current_page == 'index.php' ? 'active' : '' ?>">
                    <span class="nav-icon">🏠</span> Dashboard
                </a>
                <a href="mind-lingo.php" class="nav-item <?= $current_page == 'mind-lingo.php' ? 'active' : '' ?>">
                    <span class="nav-icon">🧠</span> Mind Lingo
                </a>
                <a href="students-info.php" class="nav-item <?= !empty($students_info_active) ? 'active' : '' ?>">
                    <span class="nav-icon">🗂️</span> Students Information
                </a>
                <a href="psych-tests.php" class="nav-item <?= !empty($psych_tests_active) ? 'active' : '' ?>">
                    <span class="nav-icon">🧩</span> Psych Tests
                </a>
                <hr class="my-2 mx-3">
                <a href="../../logout.php" class="nav-item">
                    <span class="nav-icon">🚪</span> Exit
                </a>
            </div>
        </div>

                <div class="mobile-sidebar d-lg-none" id="mobileSidebar">
            <div class="sidebar-header text-center py-4 px-2">
                <div class="position-relative d-inline-block mb-2">
                    <img src="<?= $avatar_url ? htmlspecialchars($avatar_url) : '../../uploads/avatars/daweid-icon-7797704_640.png' ?>" 
                         alt="عکس مشاور" 
                         class="rounded-circle" 
                         width="60" height="60"
                         style="object-fit: cover;">
                </div>
                <div class="text-white mt-1">
                    <div class="fw-bold fs-6"><?= htmlspecialchars($display_name) ?></div>
                    <small class="opacity-90">Consultant</small>
                </div>
            </div>
            
                        <div class="sidebar-nav">
                <a href="index.php" class="nav-item <?= $current_page == 'index.php' ? 'active' : '' ?>">
                    <span class="nav-icon">🏠</span> Dashboard
                </a>
                <a href="mind-lingo.php" class="nav-item <?= $current_page == 'mind-lingo.php' ? 'active' : '' ?>">
                    <span class="nav-icon">🧠</span> Mind Lingo
                </a>
                <a href="students-info.php" class="nav-item <?= !empty($students_info_active) ? 'active' : '' ?>">
                    <span class="nav-icon">🗂️</span> Students Information
                </a>
                <a href="psych-tests.php" class="nav-item <?= !empty($psych_tests_active) ? 'active' : '' ?>">
                    <span class="nav-icon">🧩</span> Psych Tests
                </a>
                <hr class="my-2 mx-3">
                <a href="../../logout.php" class="nav-item">
                    <span class="nav-icon">🚪</span> Exit
                </a>
            </div>
        </div>

        <script src="../../assets/js/bootstrap.min.js"></script>
    <div class="main-content-wrapper lg-stage">
        <div class="main-content teacher-main-content consultant-main-content">
            <?php if (!empty($title)): ?>
                <div class="d-flex justify-content-between align-items-center mb-4">
                   
                </div>
            <?php endif; ?>