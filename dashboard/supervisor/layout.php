<?php
// error_reporting(E_ALL);
// ini_set('display_errors', 1);
require_once __DIR__ . '/../../includes/auth.php';
require_once '../../includes/db.php';
require_once '../../includes/terms.php';
require_once '../../includes/class-live.php';
if (is_file(__DIR__ . '/../../includes/term-plan.php')) {
    require_once __DIR__ . '/../../includes/term-plan.php';
    try {
        term_plan_ensure_schema($pdo);
    } catch (Throwable $e) {
        error_log('term_plan bootstrap from layout: ' . $e->getMessage());
    }
} 
if (!function_exists('ghazal_error_report') && is_file(__DIR__ . '/../../includes/error-log.php')) {
    require_once __DIR__ . '/../../includes/error-log.php';
}
if (is_file(__DIR__ . '/../../includes/panel-robots.php')) {
    require_once __DIR__ . '/../../includes/panel-robots.php';
}
require_role('supervisor');
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
$wall_class = $wall['class'] ?? 'lg-supervisor-landscape';
if (!isset($current_page) || $current_page === '') {
    $current_page = basename($_SERVER['PHP_SELF'] ?? '');
}
if (!isset($title) || $title === '') {
    $title = 'پنل آموزشی';
}
$lg_nav_aliases = [
    'session-plan-details.php' => 'session-plan.php',
    'term-plan-details.php' => 'term-plan.php',
    'term-plan-import.php' => 'term-plan.php',
    'term-plan-export.php' => 'term-plan.php',
    'term-plan-template.php' => 'term-plan.php',
];
if (isset($lg_nav_aliases[$current_page])) {
    $current_page = $lg_nav_aliases[$current_page];
}
$students_info_active = in_array($current_page, [
    'students-info.php',
    'students-info-view.php',
    'students-info-import.php',
    'students-info-export.php',
], true);
$teachers_info_active = in_array($current_page, [
    'teachers-info.php',
    'teachers-info-view.php',
], true);
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl" class="<?= htmlspecialchars($wall_class) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex, nofollow">
    <link rel="icon" type="image/png" href="../../assets/icons/favicon-32x32.png">
    <link rel="apple-touch-icon" href="../../assets/icons/favicon-32x32.png">
    <title><?= $title ?> - پنل آموزشی</title>
    <link href="/assets/css/bootstrap.rtl.min.css" rel="stylesheet">
    <link href="/assets/css/dashboard-style.css?v=<?= time() ?>" rel="stylesheet">
    <link href="/assets/css/liquid-glass.css?v=lg25" rel="stylesheet">
    <link href="/assets/css/supervisor/supervisor-landscape.css?v=s3" rel="stylesheet">
    <?php if (!empty($wall) && !empty($wall['css'])): ?>
    <link href="<?= htmlspecialchars($wall['css']) ?>" rel="stylesheet">
    <?php endif; ?>
    
<style>
    body, input, button, select, textarea, .form-control {
       
    }
    html, body {
        max-width: 100%;
        overflow-x: hidden;
    }
    :root {
        --sidebar-width: 280px;
    }
    .dashboard-layout {
        display: flex;
        min-height: 100vh;
        width: 100%;
        max-width: 100%;
        background: transparent;
        overflow-x: hidden;
    }
    .main-content {
        padding: 10px;
        transition: padding 0.3s ease;
        min-height: 100vh;
        min-width: 0;
        max-width: 100%;
        overflow-x: hidden;
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
        background: transparent;
        background-image: none !important;
        flex: 1;
        min-width: 0;
        max-width: 100%;
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
        .main-content {
            padding: 12px 10px 28px;
        }
        .dashboard-header {
            flex-wrap: wrap;
            gap: 10px;
        }
        .dashboard-header h1,
        .main-content h1 {
            font-size: clamp(1.2rem, 6vw, 1.7rem);
            line-height: 1.25;
            word-break: break-word;
            overflow-wrap: anywhere;
            white-space: normal;
            max-width: 100%;
        }
        .nav-tabs {
            flex-wrap: wrap;
            overflow: visible;
        }
        .nav-tabs .nav-link {
            white-space: nowrap;
        }
        .card,
        .term-management-card,
        .edu-cal-card {
            max-width: 100%;
            min-width: 0;
        }
        .title-nav {
            font-size: 0.95rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
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
<body class="lg-app <?= htmlspecialchars($wall_class) ?>">
    <div class="lg-orbs" aria-hidden="true">
        <span class="lg-orb lg-orb-a"></span>
        <span class="lg-orb lg-orb-b"></span>
        <span class="lg-orb lg-orb-c"></span>
    </div>
    <?php require_once __DIR__ . '/../../includes/welcome-overlay.php'; ?>
    <nav class="mobile-navbar d-lg-none">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center w-100">
                <div class="col">            
                   <button class="btn btn-menu" id="mobileMenuToggle">
                    ☰
                </button>
                </div>
                <div class="title-nav col text-center"><?= $title ?></div>
                <div class="col "><img class="float-end" width="40" height="40" src="../../assets/icons/android-chrome-192x192.png">
                </div>
            </div>
        </div>
    </nav>

    <div class="dashboard-layout">
        <div class="sidebar d-none d-lg-block" id="sidebar">
            <div class="sidebar-header text-center py-4 px-2">
                <div class="position-relative d-inline-block mb-2">
                    <img src="<?= $avatar_url ? htmlspecialchars($avatar_url) : '../../uploads/avatars/daweid-icon-7797704_640.png' ?>" 
                         alt="عکس سوپروایزر" 
                         class="rounded-circle" 
                         width="80" height="80"
                         style="object-fit: cover;">
                </div>
                <div class="text-white mt-1">
                    <div class="fw-bold fs-6"><?= htmlspecialchars($display_name) ?></div>
                    <small class="opacity-90">Supervisor</small>
                </div>
            </div>
           
            <div class="sidebar-nav">
                <a href="index.php" class="nav-item <?= $current_page == 'index.php' ? 'active' : '' ?>">
                    <span class="nav-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640"><path opacity=".4" fill="currentColor" d="M144.5 266.2L144.5 512C144.5 529.7 158.8 544 176.5 544L240.5 544L240.5 432C240.5 396.7 269.2 368 304.5 368L336.5 368C371.8 368 400.5 396.7 400.5 432L400.5 544L464.5 544C482.2 544 496.5 529.7 496.5 512L496.5 266.2L320.5 101.9L144.5 266.2z"/><path fill="currentColor" d="M309.6 68.3C315.7 62.6 325.3 62.6 331.4 68.3L571.4 292.3C577.9 298.3 578.2 308.5 572.2 314.9C566.2 321.3 556 321.7 549.6 315.7L528.5 296L528.5 512C528.5 547.3 499.8 576 464.5 576L176.5 576C141.2 576 112.5 547.3 112.5 512L112.5 296L91.4 315.7C84.9 321.7 74.8 321.4 68.8 314.9C62.8 308.4 63.1 298.3 69.6 292.3L309.6 68.3zM320.5 101.9L144.5 266.2L144.5 512C144.5 529.7 158.8 544 176.5 544L240.5 544L240.5 432C240.5 396.7 269.2 368 304.5 368L336.5 368C371.8 368 400.5 396.7 400.5 432L400.5 544L464.5 544C482.2 544 496.5 529.7 496.5 512L496.5 266.2L320.5 101.9zM272.5 544L368.5 544L368.5 432C368.5 414.3 354.2 400 336.5 400L304.5 400C286.8 400 272.5 414.3 272.5 432L272.5 544z"/></svg>
                    </span> Dashboard
                </a>
                <a href="ai-assistance.php" class="nav-item <?= $current_page == 'ai-assistance.php' ? 'active' : '' ?>">
                    <span class="nav-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M4 12a8 8 0 0 1 8-8h0a8 8 0 0 1 8 8v2.2a2.8 2.8 0 0 1-2.8 2.8H13l-3.4 3v-3H9.8A5.8 5.8 0 0 1 4 13.2z"/><circle cx="9" cy="12" r="0.9" fill="currentColor" stroke="none"/><circle cx="12" cy="12" r="0.9" fill="currentColor" stroke="none"/><circle cx="15" cy="12" r="0.9" fill="currentColor" stroke="none"/></svg>
                    </span> AI Assistance
                </a>
                <a href="eas.php" class="nav-item <?= $current_page == 'eas.php' ? 'active' : '' ?>">
                    <span class="nav-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 576 512"><path fill="currentColor" d="M64 32C28.7 32 0 60.7 0 96L0 416c0 35.3 28.7 64 64 64l448 0c35.3 0 64-28.7 64-64l0-320c0-35.3-28.7-64-64-64L64 32zm80 256a48 48 0 1 1 0 96 48 48 0 1 1 0-96zm80 40c0-13.3 10.7-24 24-24l192 0c13.3 0 24 10.7 24 24s-10.7 24-24 24l-192 0c-13.3 0-24-10.7-24-24zm0-128c0-13.3 10.7-24 24-24l192 0c13.3 0 24 10.7 24 24s-10.7 24-24 24l-192 0c-13.3 0-24-10.7-24-24zM144 144a48 48 0 1 1 0 96 48 48 0 1 1 0-96z"/></svg>
                    </span> EAS
                </a>
                <a href="classes.php" class="nav-item <?= $current_page == 'classes.php' ? 'active' : '' ?>">
                    <span class="nav-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><path opacity=".4" fill="currentColor" d="M32 96l0 320c0 17.7 14.3 32 32 32l320 0c17.7 0 32-14.3 32-32l0-320c0-17.7-14.3-32-32-32L64 64C46.3 64 32 78.3 32 96zm120 64a24 24 0 1 1 -48 0 24 24 0 1 1 48 0zm0 96a24 24 0 1 1 -48 0 24 24 0 1 1 48 0zm0 96a24 24 0 1 1 -48 0 24 24 0 1 1 48 0zm40-192c0-8.8 7.2-16 16-16l128 0c8.8 0 16 7.2 16 16s-7.2 16-16 16l-128 0c-8.8 0-16-7.2-16-16zm0 96c0-8.8 7.2-16 16-16l128 0c8.8 0 16 7.2 16 16s-7.2 16-16 16l-128 0c-8.8 0-16-7.2-16-16zm0 96c0-8.8 7.2-16 16-16l128 0c8.8 0 16 7.2 16 16s-7.2 16-16 16l-128 0c-8.8 0-16-7.2-16-16z"/><path fill="currentColor" d="M64 64C46.3 64 32 78.3 32 96l0 320c0 17.7 14.3 32 32 32l320 0c17.7 0 32-14.3 32-32l0-320c0-17.7-14.3-32-32-32L64 64zM0 96C0 60.7 28.7 32 64 32l320 0c35.3 0 64 28.7 64 64l0 320c0 35.3-28.7 64-64 64L64 480c-35.3 0-64-28.7-64-64L0 96zm104 64a24 24 0 1 1 48 0 24 24 0 1 1 -48 0zm88 0c0-8.8 7.2-16 16-16l128 0c8.8 0 16 7.2 16 16s-7.2 16-16 16l-128 0c-8.8 0-16-7.2-16-16zm0 96c0-8.8 7.2-16 16-16l128 0c8.8 0 16 7.2 16 16s-7.2 16-16 16l-128 0c-8.8 0-16-7.2-16-16zm0 96c0-8.8 7.2-16 16-16l128 0c8.8 0 16 7.2 16 16s-7.2 16-16 16l-128 0c-8.8 0-16-7.2-16-16zm-64-72a24 24 0 1 1 0-48 24 24 0 1 1 0 48zm-24 72a24 24 0 1 1 48 0 24 24 0 1 1 -48 0z"/></svg>
                    </span> Class Portfolio 
                </a>
                <a href="attendance.php" class="nav-item <?= $current_page == 'attendance.php' ? 'active' : '' ?>">
                    <span class="nav-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640"><path opacity=".4" fill="currentColor" d="M48.5 576C57.3 576 64.5 568.8 64.5 560L64.5 544C64.5 464.5 129 400 208.5 400L304.5 400C384 400 448.5 464.5 448.5 544L448.5 560C448.5 568.8 455.7 576 464.5 576L48.5 576zM352.5 192C352.5 245 309.5 288 256.5 288C203.5 288 160.5 245 160.5 192C160.5 139 203.5 96 256.5 96C309.5 96 352.5 139 352.5 192z"/><path fill="currentColor" d="M304.5 368C401.7 368 480.5 446.8 480.5 544L480.5 560C480.5 568.8 473.3 576 464.5 576C455.7 576 448.5 568.8 448.5 560L448.5 544C448.5 464.5 384 400 304.5 400L208.5 400C129 400 64.5 464.5 64.5 544L64.5 560C64.5 568.8 57.3 576 48.5 576C39.7 576 32.5 568.8 32.5 560L32.5 544C32.5 446.8 111.3 368 208.5 368L304.5 368zM595.6 170.2C600.8 163.1 610.8 161.5 617.9 166.7C625 171.9 626.6 181.9 621.4 189L515.8 334.2C513 338 508.7 340.4 504 340.7C499.3 341 494.7 339.3 491.4 335.9L437 279.6C430.9 273.2 431 263.1 437.4 257C443.8 250.9 453.9 251.1 460 257.4L501.2 300L595.6 170.2zM256.5 320C185.8 320 128.5 262.7 128.5 192C128.5 121.3 185.8 64 256.5 64C327.2 64 384.5 121.3 384.5 192C384.5 262.7 327.2 320 256.5 320zM256.5 96C203.5 96 160.5 139 160.5 192C160.5 245 203.5 288 256.5 288C309.5 288 352.5 245 352.5 192C352.5 139 309.5 96 256.5 96z"/></svg>
                    </span> Roll Call
                </a>
                <a href="report-card.php" class="nav-item <?= $current_page == 'report-card.php' ? 'active' : '' ?>">
                    <span class="nav-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><path opacity=".4" fill="currentColor" d="M32 256l0 144c0 26.5 21.5 48 48 48l368 0 0-80c0 8.8-7.2 16-16 16s-16-7.2-16-16l0-265.6-54.4 76.2c-6 8.4-15.7 13.4-26 13.4l-6.7 0c-5.8 0-11.5-1.6-16.5-4.6L256 153.6 256 368c0 8.8-7.2 16-16 16s-16-7.2-16-16l0-201.6-54.4 76.2c-6 8.4-15.7 13.4-26 13.4L32 256zm96 16c0-8.8 7.2-16 16-16s16 7.2 16 16l0 96c0 8.8-7.2 16-16 16s-16-7.2-16-16l0-96zm192-64c0-8.8 7.2-16 16-16s16 7.2 16 16l0 160c0 8.8-7.2 16-16 16s-16-7.2-16-16l0-160z"/><path fill="currentColor" d="M16 32c8.8 0 16 7.2 16 16l0 352c0 26.5 21.5 48 48 48l416 0c8.8 0 16 7.2 16 16s-7.2 16-16 16L80 480c-44.2 0-80-35.8-80-80L0 48c0-8.8 7.2-16 16-16zM144 256c8.8 0 16 7.2 16 16l0 96c0 8.8-7.2 16-16 16s-16-7.2-16-16l0-96c0-8.8 7.2-16 16-16zM256 144l0 224c0 8.8-7.2 16-16 16s-16-7.2-16-16l0-224c0-8.8 7.2-16 16-16s16 7.2 16 16zm80 48c8.8 0 16 7.2 16 16l0 160c0 8.8-7.2 16-16 16s-16-7.2-16-16l0-160c0-8.8 7.2-16 16-16zM448 80l0 288c0 8.8-7.2 16-16 16s-16-7.2-16-16l0-288c0-8.8 7.2-16 16-16s16 7.2 16 16z"/></svg>
                    </span> report card
                </a>
                <a href="homework.php" class="nav-item <?= $current_page == 'homework.php' ? 'active' : '' ?>">
                    <span class="nav-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><path opacity=".4" fill="currentColor" d="M32 80l0 352c0 8.8 7.2 16 16 16l35.2 0c53.8 0 107 10.4 156.8 30.7l0-378.7-21.4-8.9C175.7 73.2 129.7 64 83.2 64L48 64c-8.8 0-16 7.2-16 16zm48 64c0-8.8 7.2-16 16-16l12.2 0c26 0 51.7 6.1 74.9 17.7 7.9 4 11.1 13.6 7.2 21.5s-13.6 11.1-21.5 7.2C150 164.9 129.3 160 108.2 160L96 160c-8.8 0-16-7.2-16-16zm0 96c0-8.8 7.2-16 16-16l12.2 0c26 0 51.7 6.1 74.9 17.7 7.9 4 11.1 13.6 7.2 21.5s-13.6 11.1-21.5 7.2C150 260.9 129.3 256 108.2 256L96 256c-8.8 0-16-7.2-16-16zm0 96c0-8.8 7.2-16 16-16l12.2 0c26 0 51.7 6.1 74.9 17.7 7.9 4 11.1 13.6 7.2 21.5s-13.6 11.1-21.5 7.2C150 356.9 129.3 352 108.2 352L96 352c-8.8 0-16-7.2-16-16zM272 100l0 378.7C321.8 458.4 375 448 428.8 448l35.2 0c8.8 0 16-7.2 16-16l0-352c0-8.8-7.2-16-16-16l-35.2 0c-46.5 0-92.5 9.2-135.4 27.1L272 100zm49.7 67.2c-4-7.9-.7-17.5 7.2-21.5 23.3-11.6 48.9-17.7 74.9-17.7l12.2 0c8.8 0 16 7.2 16 16s-7.2 16-16 16l-12.2 0c-21 0-41.8 4.9-60.6 14.3-7.9 4-17.5 .7-21.5-7.2zm0 96c-4-7.9-.7-17.5 7.2-21.5 23.3-11.6 48.9-17.7 74.9-17.7l12.2 0c8.8 0 16 7.2 16 16s-7.2 16-16 16l-12.2 0c-21 0-41.8 4.9-60.6 14.3-7.9 4-17.5 .7-21.5-7.2zm0 96c-4-7.9-.7-17.5 7.2-21.5 23.3-11.6 48.9-17.7 74.9-17.7l12.2 0c8.8 0 16 7.2 16 16s-7.2 16-16 16l-12.2 0c-21 0-41.8 4.9-60.6 14.3-7.9 4-17.5 .7-21.5-7.2z"/><path fill="currentColor" d="M218.6 91.1l21.4 8.9 0 378.7C190.2 458.4 137 448 83.2 448L48 448c-8.8 0-16-7.2-16-16L32 80c0-8.8 7.2-16 16-16l35.2 0c46.5 0 92.5 9.2 135.4 27.1zM428.8 448c-53.8 0-107 10.4-156.8 30.7l0-378.7 21.4-8.9C336.3 73.2 382.3 64 428.8 64L464 64c8.8 0 16 7.2 16 16l0 352c0 8.8-7.2 16-16 16l-35.2 0zM256 72L230.9 61.5C184.1 42 133.9 32 83.2 32L48 32C21.5 32 0 53.5 0 80L0 432c0 26.5 21.5 48 48 48l35.2 0c50.7 0 100.9 10 147.7 29.5l12.8 5.3c7.9 3.3 16.7 3.3 24.6 0l12.8-5.3c46.8-19.5 97-29.5 147.7-29.5l35.2 0c26.5 0 48-21.5 48-48l0-352c0-26.5-21.5-48-48-48l-35.2 0c-50.7 0-100.9 10-147.7 29.5L256 72zM96 128c-8.8 0-16 7.2-16 16s7.2 16 16 16l12.2 0c21 0 41.8 4.9 60.6 14.3 7.9 4 17.5 .7 21.5-7.2s.7-17.5-7.2-21.5c-23.3-11.6-48.9-17.7-74.9-17.7L96 128zm307.8 0c-26 0-51.7 6.1-74.9 17.7-7.9 4-11.1 13.6-7.2 21.5s13.6 11.1 21.5 7.2c18.8-9.4 39.6-14.3 60.6-14.3l12.2 0c8.8 0 16-7.2 16-16s-7.2-16-16-16l-12.2 0zM96 224c-8.8 0-16 7.2-16 16s7.2 16 16 16l12.2 0c21 0 41.8 4.9 60.6 14.3 7.9 4 17.5 .7 21.5-7.2s.7-17.5-7.2-21.5c-23.3-11.6-48.9-17.7-74.9-17.7L96 224zm307.8 0c-26 0-51.7 6.1-74.9 17.7-7.9 4-11.1 13.6-7.2 21.5s13.6 11.1 21.5 7.2c18.8-9.4 39.6-14.3 60.6-14.3l12.2 0c8.8 0 16-7.2 16-16s-7.2-16-16-16l-12.2 0zM96 320c-8.8 0-16 7.2-16 16s7.2 16 16 16l12.2 0c21 0 41.8 4.9 60.6 14.3 7.9 4 17.5 .7 21.5-7.2s.7-17.5-7.2-21.5c-23.3-11.6-48.9-17.7-74.9-17.7L96 320zm307.8 0c-26 0-51.7 6.1-74.9 17.7-7.9 4-11.1 13.6-7.2 21.5s13.6 11.1 21.5 7.2c18.8-9.4 39.6-14.3 60.6-14.3l12.2 0c8.8 0 16-7.2 16-16s-7.2-16-16-16l-12.2 0z"/></svg>
                    </span> Assignment status
                </a>
                <a href="session-plan.php" class="nav-item <?= $current_page == 'session-plan.php' ? 'active' : '' ?>">
                    <span class="nav-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 560 560"><path opacity=".4" fill="currentColor" d=""/><path fill="currentColor" d="M448.4 96l-384 0c-17.7 0-32 14.3-32 32l0 256c0 17.7 14.3 32 32 32l197.8 0c-4.1 9-7.1 18.5-8.7 28.3l-.6 3.7-188.5 0c-35.3 0-64-28.7-64-64l0-256c0-35.3 28.7-64 64-64l384 0c35.3 0 64 28.7 64 64l0 76.1c-10.8 0-21.6 1.5-32 4.4l0-80.6c0-17.7-14.3-32-32-32zM96.4 304c0-8.8 7.2-16 16-16l160 0c8.8 0 16 7.2 16 16s-7.2 16-16 16l-160 0c-8.8 0-16-7.2-16-16zm16-112l288 0c8.8 0 16 7.2 16 16s-7.2 16-16 16l-288 0c-8.8 0-16-7.2-16-16s7.2-16 16-16zM341.5 440.1c-4.7 4.7-7.8 10.8-8.9 17.4l-8.5 50.8 50.8-8.5c6.6-1.1 12.6-4.2 17.4-8.9l102.2-102.2-50.7-50.7-102.2 102.2zM466.3 315.3l50.7 50.7 20.7-20.7c14-14 14-36.7 0-50.7s-36.7-14-50.7 0l-20.7 20.7zM301 452.2c2.2-13.2 8.4-25.3 17.9-34.7L464.4 272c26.5-26.5 69.5-26.5 96 0s26.5 69.5 0 96L414.9 513.5c-9.4 9.4-21.6 15.7-34.7 17.9l-74.6 12.4c-.8 .1-1.6 .2-2.4 .2-8.2 0-14.8-6.6-14.8-14.8 0-.8 .1-1.6 .2-2.4L301 452.2z"/></svg>
                    </span> Session plan
                </a>
                <a href="term-plan.php" class="nav-item <?= $current_page == 'term-plan.php' ? 'active' : '' ?>">
                    <span class="nav-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><path opacity=".4" fill="currentColor" d="M32 96c0-35.3 28.7-64 64-64l96 0c35.3 0 64 28.7 64 64l0 96c0 35.3-28.7 64-64 64l-96 0c-35.3 0-64-28.7-64-64L32 96z"/><path fill="currentColor" d="M256 96c0-35.3 28.7-64 64-64l96 0c35.3 0 64 28.7 64 64l0 96c0 35.3-28.7 64-64 64l-96 0c-35.3 0-64-28.7-64-64l0-96zM32 320c0-35.3 28.7-64 64-64l96 0c35.3 0 64 28.7 64 64l0 96c0 35.3-28.7 64-64 64l-96 0c-35.3 0-64-28.7-64-64l0-96zm224 0c0-35.3 28.7-64 64-64l96 0c35.3 0 64 28.7 64 64l0 96c0 35.3-28.7 64-64 64l-96 0c-35.3 0-64-28.7-64-64l0-96z"/></svg>
                    </span> Term Plan
                </a>
                <a href="quiz-books.php" class="nav-item <?= $current_page == 'quiz-books.php' ? 'active' : '' ?>">
                    <span class="nav-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 448 512"><path opacity=".4" fill="currentColor" d="M96 80l288 0 0 352-288 0c-17.7 0-32-14.3-32-32s14.3-32 32-32l256 0 0-32-256 0c-53 0-96 43-96 96L0 96C0 43 43 0 96 0l288 0 0 80-288 0c-17.7 0-32-14.3-32-32s14.3-32 32-32z"/><path fill="currentColor" d="M96 0C43 0 0 43 0 96L0 416c0 53 43 96 96 96l320 0 0-32-320 0c-35.3 0-64-28.7-64-64 0-17.7 14.3-32 32-32l288 0 0-384L96 0z"/></svg>
                    </span> Coursebooks
                </a>
                <a href="study-plan.php" class="nav-item <?= $current_page == 'study-plan.php' ? 'active' : '' ?>">
                    <span class="nav-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 576 512"><path opacity=".4" fill="currentColor" d="M96 0C60.7 0 32 28.7 32 64l0 384c0 35.3 28.7 64 64 64l240 0 0-128c0-35.3 28.7-64 64-64l128 0 0-256c0-35.3-28.7-64-64-64L96 0zM64 64c0-17.7 14.3-32 32-32l96 0 0 96c0 17.7 14.3 32 32 32l96 0 0 32-256 0 0-128zm0 160l256 0 0 32c0 17.7 14.3 32 32 32l32 0 0 192c0 17.7-14.3 32-32 32L96 512c-17.7 0-32-14.3-32-32l0-256z"/><path fill="currentColor" d="M432 288l128 0L400 512l0-128c0-35.3 28.7-64 64-64l-32 0zM128 96c-8.8 0-16 7.2-16 16s7.2 16 16 16l96 0c8.8 0 16-7.2 16-16s-7.2-16-16-16l-96 0zm0 96c-8.8 0-16 7.2-16 16s7.2 16 16 16l224 0c8.8 0 16-7.2 16-16s-7.2-16-16-16l-224 0zm-16 112c0-8.8 7.2-16 16-16l160 0c8.8 0 16 7.2 16 16s-7.2 16-16 16l-160 0c-8.8 0-16-7.2-16-16z"/></svg>
                    </span> Study Plan
                </a>
                <a href="error-gravity.php" class="nav-item <?= $current_page == 'error-gravity.php' ? 'active' : '' ?>">
                    <span class="nav-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><path opacity=".4" fill="currentColor" d="M32 256a224 224 0 1 0 448 0 224 224 0 1 0 -448 0zM280 360a24 24 0 1 1 -48 0 24 24 0 1 1 48 0zM232.5 152.9c-.8-13.5 10-24.9 23.5-24.9s24.3 11.4 23.5 24.9l-7.6 136c-.5 8.5-7.5 15.1-16 15.1s-15.5-6.6-16-15.1l-7.6-136z"/><path fill="currentColor" d="M256 512a256 256 0 1 1 0-512 256 256 0 1 1 0 512zm0-480a224 224 0 1 0 0 448 224 224 0 1 0 0-448zm0 352a24 24 0 1 1 0-48 24 24 0 1 1 0 48zm0-256c13.5 0 24.3 11.4 23.5 24.9l-7.6 136c-.5 8.5-7.5 15.1-16 15.1s-15.5-6.6-16-15.1l-7.6-136c-.8-13.5 10-24.9 23.5-24.9z"/></svg>
                    </span> Error Gravity
                </a>
                <a href="observation-forms.php" class="nav-item <?= $current_page == 'observation-forms.php' ? 'active' : '' ?>">
                    <span class="nav-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><path opacity=".4" fill="currentColor" d="M32 64l0 384c0 17.7 14.3 32 32 32l256 0c17.7 0 32-14.3 32-32l0-256-96 0c-35.3 0-64-28.7-64-64l0-96-128 0C46.3 32 32 46.3 32 64zM96 272c0-8.8 7.2-16 16-16l160 0c8.8 0 16 7.2 16 16s-7.2 16-16 16l-160 0c-8.8 0-16-7.2-16-16zm0 96c0-8.8 7.2-16 16-16l160 0c8.8 0 16 7.2 16 16s-7.2 16-16 16l-160 0c-8.8 0-16-7.2-16-16z"/><path fill="currentColor" d="M64 32l128 0 0 96c0 35.3 28.7 64 64 64l96 0 0 256c0 17.7-14.3 32-32 32L64 480c-17.7 0-32-14.3-32-32L32 64c0-17.7 14.3-32 32-32zM224 45.3L338.7 160 256 160c-17.7 0-32-14.3-32-32l0-82.7zM64 0C28.7 0 0 28.7 0 64L0 448c0 35.3 28.7 64 64 64l256 0c35.3 0 64-28.7 64-64l0-261.5c0-17-6.7-33.3-18.7-45.3L242.7 18.7C230.7 6.7 214.5 0 197.5 0L64 0zm48 256c-8.8 0-16 7.2-16 16s7.2 16 16 16l160 0c8.8 0 16-7.2 16-16s-7.2-16-16-16l-160 0zm0 96c-8.8 0-16 7.2-16 16s7.2 16 16 16l160 0c8.8 0 16-7.2 16-16s-7.2-16-16-16l-160 0z"/></svg>
                    </span> observation forms
                </a>

                <a href="mind-lingo.php" class="nav-item <?= $current_page == 'mind-lingo.php' ? 'active' : '' ?>">
                    <span class="nav-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><path fill="currentColor" d="M184 0c30.9 0 58.1 17.3 72 43.5C270 17.3 297.1 0 328 0c48.6 0 88 39.4 88 88c0 10.9-2 21.3-5.7 31c34.9 14.8 59.7 49 59.7 89c0 36.4-20.4 68.3-50.6 84.1c3.1 10.1 4.6 20.8 4.6 31.9c0 57.4-46.6 104-104 104c-11.8 0-23.1-2-33.6-5.6c-17 19.8-41.9 33.6-69.8 33.6c-28.5 0-54-14.3-71-34.6c-10.4 3.3-21.6 5.2-33.3 5.2c-57.4 0-104-46.6-104-104c0-11.6 1.9-22.8 5.4-33.4C18 317 0 286.7 0 252c0-43.1 27.9-79 66.5-92.4C63 148.9 61 137.6 61 126c0-50.8 41.2-92 92-92c10.7 0 21 1.8 30.6 5.1C194.2 19 216 0 241 0z"/></svg>
                    </span> Mind Lingo
                </a>

                <a href="users.php" class="nav-item <?= $current_page == 'users.php' ? 'active' : '' ?>">
                    <span class="nav-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 576 512"><path opacity=".4" fill="currentColor" d="M16 512c8.8 0 16-7.2 16-16l0-16c0-79.5 64.5-144 144-144l74.9 0c-7.1 20-10.9 41.6-10.9 64 0 41.8 13.4 80.5 36 112L16 512zM320 128a96 96 0 1 1 -192 0 96 96 0 1 1 192 0z"/><path fill="currentColor" d="M320 128a96 96 0 1 0 -192 0 96 96 0 1 0 192 0zM96 128a128 128 0 1 1 256 0 128 128 0 1 1 -256 0zM32 480l0 16c0 8.8-7.2 16-16 16S0 504.8 0 496l0-16c0-97.2 78.8-176 176-176l89.7 0c-5.9 10.1-10.8 20.8-14.8 32L176 336C96.5 336 32 400.5 32 480zm512-80a112 112 0 1 0 -224 0 112 112 0 1 0 224 0zm-256 0a144 144 0 1 1 288 0 144 144 0 1 1 -288 0zm160-64l0 48 48 0c8.8 0 16 7.2 16 16s-7.2 16-16 16l-48 0 0 48c0 8.8-7.2 16-16 16s-16-7.2-16-16l0-48-48 0c-8.8 0-16-7.2-16-16s7.2-16 16-16l48 0 0-48c0-8.8 7.2-16 16-16s16 7.2 16 16z"/></svg>
                    </span> Users' Classification
                </a>
                <a href="students-info.php" class="nav-item <?= !empty($students_info_active) ? 'active' : '' ?>">
                    <span class="nav-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 576 512"><path opacity=".4" fill="currentColor" d="M64 80c-8.8 0-16 7.2-16 16l0 320c0 8.8 7.2 16 16 16l272 0 0-80c0-26.5 21.5-48 48-48l80 0 0-208c0-8.8-7.2-16-16-16L64 80zM96 176c0-8.8 7.2-16 16-16l96 0c8.8 0 16 7.2 16 16s-7.2 16-16 16l-96 0c-8.8 0-16-7.2-16-16zm16 80c-8.8 0-16 7.2-16 16s7.2 16 16 16l224 0c8.8 0 16-7.2 16-16s-7.2-16-16-16l-224 0zm-16 112c0-8.8 7.2-16 16-16l160 0c8.8 0 16 7.2 16 16s-7.2 16-16 16l-160 0c-8.8 0-16-7.2-16-16z"/><path fill="currentColor" d="M48 96c0-26.5 21.5-48 48-48l384 0c26.5 0 48 21.5 48 48l0 237.5c0 17-6.7 33.3-18.7 45.3L413.3 474.7c-12 12-28.3 18.7-45.3 18.7L96 493.4C69.5 493.4 48 471.9 48 445.4L48 96zm32 16l0 333.4c0 8.8 7.2 16 16 16l272 0 0-80c0-26.5 21.5-48 48-48l80 0 0-221.4c0-8.8-7.2-16-16-16L96 96c-8.8 0-16 7.2-16 16zm80 48c-8.8 0-16 7.2-16 16s7.2 16 16 16l96 0c8.8 0 16-7.2 16-16s-7.2-16-16-16l-96 0zm-16 112c0-8.8 7.2-16 16-16l224 0c8.8 0 16 7.2 16 16s-7.2 16-16 16l-224 0c-8.8 0-16-7.2-16-16zm16 80c-8.8 0-16 7.2-16 16s7.2 16 16 16l160 0c8.8 0 16-7.2 16-16s-7.2-16-16-16l-160 0z"/></svg>
                    </span> Students Information
                </a>
                <a href="teachers-info.php" class="nav-item <?= !empty($teachers_info_active) ? 'active' : '' ?>">
                    <span class="nav-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640"><path opacity=".4" fill="currentColor" d="M320 320a112 112 0 1 0 0-224 112 112 0 1 0 0 224z"/><path fill="currentColor" d="M224 352c-70.7 0-128 57.3-128 128l0 32c0 17.7 14.3 32 32 32l384 0c17.7 0 32-14.3 32-32l0-32c0-70.7-57.3-128-128-128l-192 0zM432 208a80 80 0 1 0 0-160 80 80 0 1 0 0 160zm80 96c-17.7 0-34.4 3.7-49.7 10.4 20.2 20.7 32.9 48.8 34.9 79.6 39.3 7.4 70.8 38.1 78.4 78.1 2.1-5.1 3.3-10.7 3.3-16.6l0-23.6c0-53-43-96-96-96l-24.2 0c9.2-15.1 14.5-32.8 14.5-51.6 0-8.8-1.1-17.3-3.2-25.4 14.1-7.1 29.9-11 46.6-11l.4 0zM128 208a80 80 0 1 0 0-160 80 80 0 1 0 0 160zm-31.1 106.4C95 322.3 93.9 330.8 93.9 339.6c0 18.8 5.3 36.5 14.5 51.6L84.2 391.2c-53 0-96 43-96 96l0 23.6c0 5.9 1.2 11.5 3.3 16.6 7.6-40 39.1-70.7 78.4-78.1 2-30.8 14.7-58.9 34.9-79.6C162.4 307.7 145.7 304 128 304l-.4 0c-16.7 0-32.5 3.9-46.6 11z"/></svg>
                    </span> Teachers Information
                </a>
                <a href="terms.php" class="nav-item <?= $current_page == 'terms.php' ? 'active' : '' ?>">
                    <span class="nav-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><path opacity=".4" fill="currentColor" d="M32 80l0 96c0 8.8 7.2 16 16 16l96 0c8.8 0 16-7.2 16-16l0-96c0-8.8-7.2-16-16-16L48 64c-8.8 0-16 7.2-16 16zm0 256l0 96c0 8.8 7.2 16 16 16l96 0c8.8 0 16-7.2 16-16l0-96c0-8.8-7.2-16-16-16l-96 0c-8.8 0-16 7.2-16 16zM288 80l0 96c0 8.8 7.2 16 16 16l96 0c8.8 0 16-7.2 16-16l0-96c0-8.8-7.2-16-16-16l-96 0c-8.8 0-16 7.2-16 16z"/><path fill="currentColor" d="M48 64c-8.8 0-16 7.2-16 16l0 96c0 8.8 7.2 16 16 16l96 0c8.8 0 16-7.2 16-16l0-96c0-8.8-7.2-16-16-16L48 64zM0 80C0 53.5 21.5 32 48 32l96 0c26.5 0 48 21.5 48 48l0 96c0 26.5-21.5 48-48 48l-96 0c-26.5 0-48-21.5-48-48L0 80zM48 320c-8.8 0-16 7.2-16 16l0 96c0 8.8 7.2 16 16 16l96 0c8.8 0 16-7.2 16-16l0-96c0-8.8-7.2-16-16-16l-96 0zM0 336c0-26.5 21.5-48 48-48l96 0c26.5 0 48 21.5 48 48l0 96c0 26.5-21.5 48-48 48l-96 0c-26.5 0-48-21.5-48-48l0-96zM400 64l-96 0c-8.8 0-16 7.2-16 16l0 96c0 8.8 7.2 16 16 16l96 0c8.8 0 16-7.2 16-16l0-96c0-8.8-7.2-16-16-16zM304 32l96 0c26.5 0 48 21.5 48 48l0 96c0 26.5-21.5 48-48 48l-96 0c-26.5 0-48-21.5-48-48l0-96c0-26.5 21.5-48 48-48zm64 272l0 64 64 0c8.8 0 16 7.2 16 16s-7.2 16-16 16l-64 0 0 64c0 8.8-7.2 16-16 16s-16-7.2-16-16l0-64-64 0c-8.8 0-16-7.2-16-16s7.2-16 16-16l64 0 0-64c0-8.8 7.2-16 16-16s16 7.2 16 16z"/></svg>
                    </span> Terms & Classes
                </a>
                <hr class="my-2 mx-3">
                <a href="../../logout.php" class="nav-item">
                    <span class="nav-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640"><path opacity=".4" fill="currentColor" d="M259.3 320L384 444.7L384 368C384 359.2 391.2 352 400 352L528 352C536.8 352 544 344.8 544 336L544 304C544 295.2 536.8 288 528 288L400 288C391.2 288 384 280.8 384 272L384 195.3L259.3 320z"/><path fill="currentColor" d="M375 159L231 303C221.6 312.4 221.6 327.6 231 336.9L375 481C381.9 487.9 392.2 489.9 401.2 486.2C410.2 482.5 416 473.7 416 464L416 384L528 384C554.5 384 576 362.5 576 336L576 304C576 277.5 554.5 256 528 256L416 256L416 176C416 166.3 410.2 157.5 401.2 153.8C392.2 150.1 381.9 152.2 375 159zM384 444.7L259.3 320L384 195.3L384 272C384 280.8 391.2 288 400 288L528 288C536.8 288 544 295.2 544 304L544 336C544 344.8 536.8 352 528 352L400 352C391.2 352 384 359.2 384 368L384 444.7zM240 512L160 512C124.7 512 96 483.3 96 448L96 192C96 156.7 124.7 128 160 128L240 128C248.8 128 256 120.8 256 112C256 103.2 248.8 96 240 96L160 96C107 96 64 139 64 192L64 448C64 501 107 544 160 544L240 544C248.8 544 256 536.8 256 528C256 519.2 248.8 512 240 512z"/></svg>
                    </span> Exit
                </a>
            </div>
        </div>
        
        <div class="mobile-sidebar d-lg-none" id="mobileSidebar">
            <div class="sidebar-header text-center py-4 px-2">
                <div class="position-relative d-inline-block mb-2">
                    <img src="<?= $avatar_url ? htmlspecialchars($avatar_url) : '../../uploads/avatars/daweid-icon-7797704_640.png' ?>" 
                         alt="عکس سوپروایزر" 
                         class="rounded-circle" 
                         width="60" height="60"
                         style="object-fit: cover;">
                </div>
                <div class="text-white mt-1">
                    <div class="fw-bold fs-6"><?= htmlspecialchars($display_name) ?></div>
                    <small class="opacity-90">Supervisor</small>
                </div>
            </div>
            
            <div class="sidebar-nav">
                <a href="index.php" class="nav-item <?= $current_page == 'index.php' ? 'active' : '' ?>">
                    <span class="nav-icon">🏠</span> Dashboard
                </a>
                <a href="ai-assistance.php" class="nav-item <?= $current_page == 'ai-assistance.php' ? 'active' : '' ?>">
                    <span class="nav-icon">💬</span> AI Assistance
                </a>
                <a href="eas.php" class="nav-item <?= $current_page == 'eas.php' ? 'active' : '' ?>">
                    <span class="nav-icon">📋</span> EAS
                </a>
                <a href="classes.php" class="nav-item <?= $current_page == 'classes.php' ? 'active' : '' ?>">
                    <span class="nav-icon">📚</span> Class Portfolio 
                </a>
                <a href="attendance.php" class="nav-item <?= $current_page == 'attendance.php' ? 'active' : '' ?>">
                    <span class="nav-icon">👥</span> Roll Call
                </a>
                <a href="report-card.php" class="nav-item <?= $current_page == 'report-card.php' ? 'active' : '' ?>">
                    <span class="nav-icon">📊</span> report card
                </a>
                <a href="homework.php" class="nav-item <?= $current_page == 'homework.php' ? 'active' : '' ?>">
                    <span class="nav-icon">📝</span> Assignment status
                </a>
                <a href="session-plan.php" class="nav-item <?= $current_page == 'session-plan.php' ? 'active' : '' ?>">
                    <span class="nav-icon">📋</span> Session plan
                </a>
                <a href="term-plan.php" class="nav-item <?= $current_page == 'term-plan.php' ? 'active' : '' ?>">
                    <span class="nav-icon">🗓️</span> Term Plan
                </a>
                <a href="quiz-books.php" class="nav-item <?= $current_page == 'quiz-books.php' ? 'active' : '' ?>">
                    <span class="nav-icon">📚</span> Coursebooks
                </a>
                <a href="study-plan.php" class="nav-item <?= $current_page == 'study-plan.php' ? 'active' : '' ?>">
                    <span class="nav-icon">📚</span> Study Plan
                </a>
                <a href="error-gravity.php" class="nav-item <?= $current_page == 'error-gravity.php' ? 'active' : '' ?>">
                    <span class="nav-icon">⚠️</span> Error Gravity
                </a>
                <a href="observation-forms.php" class="nav-item <?= $current_page == 'observation-forms.php' ? 'active' : '' ?>">
                    <span class="nav-icon">📋</span> observation forms
                </a>

                <a href="mind-lingo.php" class="nav-item <?= $current_page == 'mind-lingo.php' ? 'active' : '' ?>">
                    <span class="nav-icon">🧠</span> Mind Lingo
                </a>

                <a href="users.php" class="nav-item <?= $current_page == 'users.php' ? 'active' : '' ?>">
                    <span class="nav-icon">👥</span> Users' Classification
                </a>
                <a href="students-info.php" class="nav-item <?= !empty($students_info_active) ? 'active' : '' ?>">
                    <span class="nav-icon">🗂️</span> Students Information
                </a>
                <a href="teachers-info.php" class="nav-item <?= !empty($teachers_info_active) ? 'active' : '' ?>">
                    <span class="nav-icon">👩‍🏫</span> Teachers Information
                </a>
                <a href="terms.php" class="nav-item <?= $current_page == 'terms.php' ? 'active' : '' ?>">
                    <span class="nav-icon">🗓️</span> Terms & Class
                </a>
                <hr class="my-2 mx-3">
                <a href="../../logout.php" class="nav-item">
                    <span class="nav-icon">🚪</span> Exit
                </a>
            </div>
        </div>
        
        <script src="/assets/js/bootstrap.min.js"></script>
        <div class="main-content-wrapper">
            <div class="main-content supervisor-main-content">
                <?php if (!empty($title) && ($current_page ?? '') !== 'ai-assistance.php'): ?>
                    <div class="d-flex justify-content-between align-items-center mb-4">
                    </div>
                <?php endif; ?>