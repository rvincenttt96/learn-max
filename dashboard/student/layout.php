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
require_role('student'); 

$user_id = $_SESSION['user_id'];

$stmt = $pdo->prepare("SELECT full_name, username, avatar FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user_data = $stmt->fetch();

if (!$user_data) {
    header("Location: ../../index.php?error=کاربر یافت نشد.");
    exit;
}

$display_name = $user_data['full_name'] ?? 'دانش‌آموز';
$avatar_url = $user_data['avatar'] ?? null;
$username = $user_data['username'] ?? '';
if (!isset($title) || $title === '') {
    $title = 'پنل آموزشی';
}
if (!isset($current_page) || $current_page === '') {
    $current_page = basename($_SERVER['PHP_SELF'] ?? '');
}
$lg_nav_aliases = [
    'join-online.php' => 'classes.php',
    'bookstore-order.php' => 'bookstore.php',
    'quiz.php' => 'quizzes.php',
    'assignment.php' => 'assignments.php',
    'psych-take.php' => 'psych-tests.php',
];
if (isset($lg_nav_aliases[$current_page])) {
    $current_page = $lg_nav_aliases[$current_page];
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
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
<style>
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
        padding: 12px 20px;
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
</head>
<body class="lg-app">
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
                    ☰
                </button>
				</div>
				<div class="col text-center" style="font-size:13px; color:#fff;"><?= $title ?></div>
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
             alt="عکس دانش‌آموز" 
             class="rounded-circle" 
             width="80" height="80"
             style="object-fit: cover;">
    </div>
    <div class="text-white mt-1">
        <div class="fw-bold fs-6"><?= htmlspecialchars($display_name) ?></div>
        <small class="opacity-90">Student</small>
    </div>
</div>
           
<div class="sidebar-nav">
    <a href="index.php" class="nav-item <?= $current_page == 'index.php' ? 'active' : '' ?>">
        <span class="nav-icon">
		<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640"><path opacity=".4" fill="currentColor" d="M144.5 266.2L144.5 512C144.5 529.7 158.8 544 176.5 544L240.5 544L240.5 432C240.5 396.7 269.2 368 304.5 368L336.5 368C371.8 368 400.5 396.7 400.5 432L400.5 544L464.5 544C482.2 544 496.5 529.7 496.5 512L496.5 266.2L320.5 101.9L144.5 266.2z"/><path fill="currentColor" d="M309.6 68.3C315.7 62.6 325.3 62.6 331.4 68.3L571.4 292.3C577.9 298.3 578.2 308.5 572.2 314.9C566.2 321.3 556 321.7 549.6 315.7L528.5 296L528.5 512C528.5 547.3 499.8 576 464.5 576L176.5 576C141.2 576 112.5 547.3 112.5 512L112.5 296L91.4 315.7C84.9 321.7 74.8 321.4 68.8 314.9C62.8 308.4 63.1 298.3 69.6 292.3L309.6 68.3zM320.5 101.9L144.5 266.2L144.5 512C144.5 529.7 158.8 544 176.5 544L240.5 544L240.5 432C240.5 396.7 269.2 368 304.5 368L336.5 368C371.8 368 400.5 396.7 400.5 432L400.5 544L464.5 544C482.2 544 496.5 529.7 496.5 512L496.5 266.2L320.5 101.9zM272.5 544L368.5 544L368.5 432C368.5 414.3 354.2 400 336.5 400L304.5 400C286.8 400 272.5 414.3 272.5 432L272.5 544z"/></svg>
		</span> Dashboard
    </a>
    <a href="classes.php" class="nav-item <?= $current_page == 'classes.php' ? 'active' : '' ?>">
        <span class="nav-icon">
						<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640"><!--! Font Awesome Pro 7.1.0 by @fontawesome - https://fontawesome.com License - https://fontawesome.com/license (Commercial License) Copyright 2025 Fonticons, Inc. --><path opacity=".4" fill="currentColor" d="M96 128C96 110.3 110.3 96 128 96L512 96C529.7 96 544 110.3 544 128L544 272C482.1 272 432 322.1 432 384C432 322.1 381.9 272 320 272C258.1 272 208 322.1 208 384C208 322.1 157.9 272 96 272L96 128z"/><path fill="currentColor" d="M512 96L128 96C110.3 96 96 110.3 96 128L96 272C84.9 272 74.1 273.6 64 276.6L64 128C64 92.7 92.7 64 128 64L512 64C547.3 64 576 92.7 576 128L576 276.6C565.9 273.6 555.1 272 544 272L544 128C544 110.3 529.7 96 512 96zM96 416C113.7 416 128 401.7 128 384C128 366.3 113.7 352 96 352C78.3 352 64 366.3 64 384C64 401.7 78.3 416 96 416zM96 320C131.3 320 160 348.7 160 384C160 419.3 131.3 448 96 448C60.7 448 32 419.3 32 384C32 348.7 60.7 320 96 320zM320 416C337.7 416 352 401.7 352 384C352 366.3 337.7 352 320 352C302.3 352 288 366.3 288 384C288 401.7 302.3 416 320 416zM320 320C355.3 320 384 348.7 384 384C384 419.3 355.3 448 320 448C284.7 448 256 419.3 256 384C256 348.7 284.7 320 320 320zM576 384C576 366.3 561.7 352 544 352C526.3 352 512 366.3 512 384C512 401.7 526.3 416 544 416C561.7 416 576 401.7 576 384zM480 384C480 348.7 508.7 320 544 320C579.3 320 608 348.7 608 384C608 419.3 579.3 448 544 448C508.7 448 480 419.3 480 384zM32 544L32 560C32 568.8 24.8 576 16 576C7.2 576 0 568.8 0 560L0 544C0 508.7 28.7 480 64 480L128 480C163.3 480 192 508.7 192 544L192 560C192 568.8 184.8 576 176 576C167.2 576 160 568.8 160 560L160 544C160 526.3 145.7 512 128 512L64 512C46.3 512 32 526.3 32 544zM288 512C270.3 512 256 526.3 256 544L256 560C256 568.8 248.8 576 240 576C231.2 576 224 568.8 224 560L224 544C224 508.7 252.7 480 288 480L352 480C387.3 480 416 508.7 416 544L416 560C416 568.8 408.8 576 400 576C391.2 576 384 568.8 384 560L384 544C384 526.3 369.7 512 352 512L288 512zM480 544L480 560C480 568.8 472.8 576 464 576C455.2 576 448 568.8 448 560L448 544C448 508.7 476.7 480 512 480L576 480C611.3 480 640 508.7 640 544L640 560C640 568.8 632.8 576 624 576C615.2 576 608 568.8 608 560L608 544C608 526.3 593.7 512 576 512L512 512C494.3 512 480 526.3 480 544z"/></svg>
		</span> My Classes
    </a>
    <a href="attendance.php" class="nav-item <?= $current_page == 'attendance.php' ? 'active' : '' ?>">
        <span class="nav-icon">
		<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640"><path opacity=".4" fill="currentColor" d="M48.5 576C57.3 576 64.5 568.8 64.5 560L64.5 544C64.5 464.5 129 400 208.5 400L304.5 400C384 400 448.5 464.5 448.5 544L448.5 560C448.5 568.8 455.7 576 464.5 576L48.5 576zM352.5 192C352.5 245 309.5 288 256.5 288C203.5 288 160.5 245 160.5 192C160.5 139 203.5 96 256.5 96C309.5 96 352.5 139 352.5 192z"/><path fill="currentColor" d="M304.5 368C401.7 368 480.5 446.8 480.5 544L480.5 560C480.5 568.8 473.3 576 464.5 576C455.7 576 448.5 568.8 448.5 560L448.5 544C448.5 464.5 384 400 304.5 400L208.5 400C129 400 64.5 464.5 64.5 544L64.5 560C64.5 568.8 57.3 576 48.5 576C39.7 576 32.5 568.8 32.5 560L32.5 544C32.5 446.8 111.3 368 208.5 368L304.5 368zM595.6 170.2C600.8 163.1 610.8 161.5 617.9 166.7C625 171.9 626.6 181.9 621.4 189L515.8 334.2C513 338 508.7 340.4 504 340.7C499.3 341 494.7 339.3 491.4 335.9L437 279.6C430.9 273.2 431 263.1 437.4 257C443.8 250.9 453.9 251.1 460 257.4L501.2 300L595.6 170.2zM256.5 320C185.8 320 128.5 262.7 128.5 192C128.5 121.3 185.8 64 256.5 64C327.2 64 384.5 121.3 384.5 192C384.5 262.7 327.2 320 256.5 320zM256.5 96C203.5 96 160.5 139 160.5 192C160.5 245 203.5 288 256.5 288C309.5 288 352.5 245 352.5 192C352.5 139 309.5 96 256.5 96z"/></svg>
		</span> Roll Call
    </a>
    <a href="homework.php" class="nav-item <?= $current_page == 'homework.php' ? 'active' : '' ?>">
        <span class="nav-icon">
		<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640"><path opacity=".4" fill="currentColor" d="M96.1 128C96.1 110.3 110.4 96 128.1 96L256.1 96L256.1 192C256.1 227.3 284.8 256 320.1 256L416.1 256L416.1 348L316.5 447.6C300 464.1 289.1 485.4 285.2 508.4C283.2 520.3 281.2 532.2 279.3 544.1L128.1 544C110.4 544 96.1 529.7 96.1 512L96.1 128z"/><path fill="currentColor" d="M256.1 96L128.1 96C110.4 96 96.1 110.3 96.1 128L96.1 512C96.1 529.7 110.4 544 128.1 544L279.3 544L274 576L128.1 576C92.8 576 64.1 547.3 64.1 512L64.1 128C64.1 92.7 92.8 64 128.1 64L261.6 64C278.6 64 294.9 70.7 306.9 82.7L429.3 205.3C441.3 217.3 448 233.6 448 250.6L448 316.1L416 348.1L416 256.1L320 256.1C284.7 256.1 256 227.4 256 192.1L256 96.1zM402.8 224L288.1 109.3L288.1 192C288.1 209.7 302.4 224 320.1 224L402.8 224zM373.2 503.9C368.5 508.6 365.4 514.7 364.3 521.2L355.8 571.9L406.5 563.4C413.1 562.3 419.1 559.2 423.8 554.5L525.9 452.4L475.2 401.7L373.1 503.8zM497.8 379.2L548.5 429.9L569.2 409.2C583.2 395.2 583.2 372.5 569.2 358.5C555.2 344.5 532.5 344.5 518.5 358.5L497.8 379.2zM332.7 516C334.9 502.9 341.1 490.7 350.6 481.3L495.9 336C522.4 309.5 565.3 309.5 591.8 336C618.3 362.5 618.3 405.4 591.8 431.9L446.4 577.2C437 586.6 424.8 592.9 411.7 595.1L337.2 607.5C327.4 609.1 318.6 600.3 320.2 590.5L332.7 516z"/></svg>
		</span> Homework Status
    </a>
    <a href="assignments.php" class="nav-item <?= $current_page == 'assignments.php' ? 'active' : '' ?>">
        <span class="nav-icon">
		<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 384 512"><path opacity=".4" fill="currentColor" d="M64 0C28.7 0 0 28.7 0 64L0 448c0 35.3 28.7 64 64 64l256 0c35.3 0 64-28.7 64-64l0-288-96 0c-17.7 0-32-14.3-32-32L256 0 64 0z"/><path fill="currentColor" d="M256 0l0 96c0 17.7 14.3 32 32 32l96 0L256 0zM136 240c-13.3 0-24 10.7-24 24s10.7 24 24 24l48 0 0 72c0 13.3 10.7 24 24 24s24-10.7 24-24l0-72 48 0c13.3 0 24-10.7 24-24s-10.7-24-24-24l-48 0 0-48c0-13.3-10.7-24-24-24s-24 10.7-24 24l0 48-48 0z"/></svg>
		</span> Assignments
    </a>
    <a href="quizzes.php" class="nav-item <?= $current_page == 'quizzes.php' ? 'active' : '' ?>">
        <span class="nav-icon">
		<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640"><path opacity=".4" fill="currentColor" d="M192 96C156.7 96 128 124.7 128 160L128 512C128 547.3 156.7 576 192 576L448 576C483.3 576 512 547.3 512 512L512 234.5C512 217.5 505.3 201.2 493.3 189.3L370.7 66.7C358.7 54.7 342.4 48 325.5 48L192 48C192 48 192 96 192 96z"/><path fill="currentColor" d="M192 48L325.5 48C342.5 48 358.8 54.7 370.8 66.7L493.3 189.3C505.3 201.3 512 217.6 512 234.6L512 256L416 256C380.7 256 352 227.3 352 192L352 48L192 48zM240 320C231.2 320 224 327.2 224 336C224 344.8 231.2 352 240 352L400 352C408.8 352 416 344.8 416 336C416 327.2 408.8 320 400 320L240 320zM240 416C231.2 416 224 423.2 224 432C224 440.8 231.2 448 240 448L336 448C344.8 448 352 440.8 352 432C352 423.2 344.8 416 336 416L240 416z"/></svg>
		</span> Quizzes
    </a>
    <a href="psych-tests.php" class="nav-item <?= $current_page == 'psych-tests.php' ? 'active' : '' ?>">
        <span class="nav-icon">
		<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640"><path opacity=".4" fill="currentColor" d="M192 96C156.7 96 128 124.7 128 160L128 512C128 547.3 156.7 576 192 576L448 576C483.3 576 512 547.3 512 512L512 234.5C512 217.5 505.3 201.2 493.3 189.3L370.7 66.7C358.7 54.7 342.4 48 325.5 48L192 48C192 48 192 96 192 96z"/><path fill="currentColor" d="M240 320h160c8.8 0 16 7.2 16 16s-7.2 16-16 16H240c-8.8 0-16-7.2-16-16s7.2-16 16-16zm0 96h96c8.8 0 16 7.2 16 16s-7.2 16-16 16h-96c-8.8 0-16-7.2-16-16s7.2-16 16-16z"/></svg>
		</span> Psych Tests
    </a>
    <a href="report-card.php" class="nav-item <?= $current_page == 'report-card.php' ? 'active' : '' ?>">
        <span class="nav-icon">
		<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640"><path opacity=".4" fill="currentColor" d="M160 160C160 142.3 174.3 128 192 128L544 128C561.7 128 576 142.3 576 160L576 448C576 465.7 561.7 480 544 480L322.1 480C307 448.6 280.6 423.6 248.1 410.4C263.1 389.4 271.9 363.7 271.9 336C271.9 270.7 223 216.9 159.9 209L159.9 160zM292.7 292.7C286.5 298.9 286.5 309.1 292.7 315.3L340.7 363.3C343.7 366.3 347.8 368 352 368C356.2 368 360.3 366.3 363.3 363.3L480 246.6L480 304C480 312.8 487.2 320 496 320C504.8 320 512 312.8 512 304L512 208C512 199.2 504.8 192 496 192L400 192C391.2 192 384 199.2 384 208C384 216.8 391.2 224 400 224L457.4 224L352 329.4L315.3 292.7C309.1 286.5 298.9 286.5 292.7 292.7z"/><path fill="currentColor" d="M192 128L544 128C561.7 128 576 142.3 576 160L576 448C576 465.7 561.7 480 544 480L322.1 480C326.9 490.1 330.6 500.8 333 512L544 512C579.3 512 608 483.3 608 448L608 160C608 124.7 579.3 96 544 96L192 96C156.7 96 128 124.7 128 160L128 209C133.2 208.3 138.6 208 144 208C149.4 208 154.8 208.3 160 209L160 160C160 142.3 174.3 128 192 128zM400 192C391.2 192 384 199.2 384 208C384 216.8 391.2 224 400 224L457.4 224L352 329.4L315.3 292.7C309.1 286.5 298.9 286.5 292.7 292.7C286.5 298.9 286.5 309.1 292.7 315.3L340.7 363.3C343.7 366.3 347.8 368 352 368C356.2 368 360.3 366.3 363.3 363.3L480 246.6L480 304C480 312.8 487.2 320 496 320C504.8 320 512 312.8 512 304L512 208C512 199.2 504.8 192 496 192L400 192zM144 288C170.5 288 192 309.5 192 336C192 362.5 170.5 384 144 384C117.5 384 96 362.5 96 336C96 309.5 117.5 288 144 288zM144 416C188.2 416 224 380.2 224 336C224 291.8 188.2 256 144 256C99.8 256 64 291.8 64 336C64 380.2 99.8 416 144 416zM0 544L0 560C0 568.8 7.2 576 16 576C24.8 576 32 568.8 32 560L32 544C32 508.7 60.7 480 96 480L192 480C227.3 480 256 508.7 256 544L256 560C256 568.8 263.2 576 272 576C280.8 576 288 568.8 288 560L288 544C288 491 245 448 192 448L96 448C43 448 0 491 0 544z"/></svg>
		</span> Report Card
    </a>
    <a href="bookstore.php" class="nav-item <?= $current_page == 'bookstore.php' ? 'active' : '' ?>">
        <span class="nav-icon">
		<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640"><path opacity=".4" fill="currentColor" d="M192 96C174.3 96 160 110.3 160 128L160 512C160 529.7 174.3 544 192 544L480 544C497.7 544 512 529.7 512 512L512 128C512 110.3 497.7 96 480 96L192 96z"/><path fill="currentColor" d="M160 128C160 92.7 188.7 64 224 64L480 64C515.3 64 544 92.7 544 128L544 512C544 547.3 515.3 576 480 576L192 576C174.3 576 160 561.7 160 544C160 526.3 174.3 512 192 512L448 512C465.7 512 480 497.7 480 480L480 128C480 110.3 465.7 96 448 96L192 96C174.3 96 160 110.3 160 128zM240 176C240 167.2 247.2 160 256 160L416 160C424.8 160 432 167.2 432 176C432 184.8 424.8 192 416 192L256 192C247.2 192 240 184.8 240 176zM256 256C247.2 256 240 263.2 240 272C240 280.8 247.2 288 256 288L416 288C424.8 288 432 280.8 432 272C432 263.2 424.8 256 416 256L256 256z"/></svg>
		</span> Book Store
    </a>
	<a href="" class="nav-item <?= $current_page == 'communique.php' ? 'active' : '' ?>">
        <span class="nav-icon">
		<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640"><path opacity=".4" fill="currentColor" d="M128 432.6C128 441.1 134.9 448 143.4 448L496.6 448C505.1 448 512 441.1 512 432.6C512 429.1 510.8 425.7 508.6 423L498.5 410.4C465.8 369.5 448 318.7 448 266.3L448 256C448 185.3 390.7 128 320 128C249.3 128 192 185.3 192 256L192 266.3C192 318.7 174.2 369.5 141.4 410.4L131.4 423C129.2 425.7 128 429.1 128 432.6z"/><path fill="currentColor" d="M320 64C311.2 64 304 71.2 304 80L304 96.8C223.1 104.8 160 173 160 256L160 266.3C160 311.4 144.6 355.2 116.5 390.4L106.4 403C99.7 411.4 96 421.9 96 432.6C96 458.8 117.2 480 143.4 480L496.6 480C522.8 480 544 458.8 544 432.6C544 421.8 540.3 411.4 533.6 403L523.5 390.4C495.3 355.2 480 311.4 480 266.3L480 256C480 173 416.9 104.8 336 96.8L336 80C336 71.2 328.8 64 320 64zM320 128C390.7 128 448 185.3 448 256L448 266.3C448 318.7 465.8 369.5 498.6 410.4L508.6 423C510.8 425.7 512 429.1 512 432.6C512 441.1 505.1 448 496.6 448L143.4 448C134.9 448 128 441.1 128 432.6C128 429.1 129.2 425.7 131.4 423L141.5 410.4C174.2 369.5 192 318.7 192 266.3L192 256C192 185.3 249.3 128 320 128zM252.1 528C262 556 288.7 576 320 576C351.3 576 378 556 387.9 528L352 528C344.7 537.7 333.1 544 320 544C306.9 544 295.3 537.7 288 528L252.1 528z"/></svg>
		</span> communique
    </a>
    <a href="profile.php" class="nav-item <?= $current_page == 'profile.php' ? 'active' : '' ?>">
        <span class="nav-icon">
		<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640"><path opacity=".4" fill="currentColor" d="M112 576C120.8 576 128 568.8 128 560L128 544C128 464.5 192.5 400 272 400L368 400C447.5 400 512 464.5 512 544L512 560C512 568.8 519.2 576 528 576L112 576zM416 192C416 245 373 288 320 288C267 288 224 245 224 192C224 139 267 96 320 96C373 96 416 139 416 192z"/><path fill="currentColor" d="M224 192C224 139 267 96 320 96C373 96 416 139 416 192C416 245 373 288 320 288C267 288 224 245 224 192zM448 192C448 121.3 390.7 64 320 64C249.3 64 192 121.3 192 192C192 262.7 249.3 320 320 320C390.7 320 448 262.7 448 192zM128 544C128 464.5 192.5 400 272 400L368 400C447.5 400 512 464.5 512 544L512 560C512 568.8 519.2 576 528 576C536.8 576 544 568.8 544 560L544 544C544 446.8 465.2 368 368 368L272 368C174.8 368 96 446.8 96 544L96 560C96 568.8 103.2 576 112 576C120.8 576 128 568.8 128 560L128 544z"/></svg>
		</span> My Profile
    </a>
    <hr class="my-2 mx-3">
    <a href="../../logout.php" class="nav-item">
        <span class="nav-icon">
		<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640"><path opacity=".4" fill="currentColor" d="M259.3 320L384 444.7L384 368C384 359.2 391.2 352 400 352L528 352C536.8 352 544 344.8 544 336L544 304C544 295.2 536.8 288 528 288L400 288C391.2 288 384 280.8 384 272L384 195.3L259.3 320z"/><path fill="currentColor" d="M375 159L231 303C221.6 312.4 221.6 327.6 231 336.9L375 481C381.9 487.9 392.2 489.9 401.2 486.2C410.2 482.5 416 473.7 416 464L416 384L528 384C554.5 384 576 362.5 576 336L576 304C576 277.5 554.5 256 528 256L416 256L416 176C416 166.3 410.2 157.5 401.2 153.8C392.2 150.1 381.9 152.2 375 159zM384 444.7L259.3 320L384 195.3L384 272C384 280.8 391.2 288 400 288L528 288C536.8 288 544 295.2 544 304L544 336C544 344.8 536.8 352 528 352L400 352C391.2 352 384 359.2 384 368L384 444.7zM240 512L160 512C124.7 512 96 483.3 96 448L96 192C96 156.7 124.7 128 160 128L240 128C248.8 128 256 120.8 256 112C256 103.2 248.8 96 240 96L160 96C107 96 64 139 64 192L64 448C64 501 107 544 160 544L240 544C248.8 544 256 536.8 256 528C256 519.2 248.8 512 240 512z"/></svg>
		</span> Exit
    </a>
</div>
        </div>

<!--منو موبایل-->
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
						<small class="opacity-90">Student</small>
                </div>
            </div>
            
<div class="sidebar-nav">
    <a href="index.php" class="nav-item <?= $current_page == 'index.php' ? 'active' : '' ?>">
        <span class="nav-icon">
		<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640"><path opacity=".4" fill="currentColor" d="M144.5 266.2L144.5 512C144.5 529.7 158.8 544 176.5 544L240.5 544L240.5 432C240.5 396.7 269.2 368 304.5 368L336.5 368C371.8 368 400.5 396.7 400.5 432L400.5 544L464.5 544C482.2 544 496.5 529.7 496.5 512L496.5 266.2L320.5 101.9L144.5 266.2z"/><path fill="currentColor" d="M309.6 68.3C315.7 62.6 325.3 62.6 331.4 68.3L571.4 292.3C577.9 298.3 578.2 308.5 572.2 314.9C566.2 321.3 556 321.7 549.6 315.7L528.5 296L528.5 512C528.5 547.3 499.8 576 464.5 576L176.5 576C141.2 576 112.5 547.3 112.5 512L112.5 296L91.4 315.7C84.9 321.7 74.8 321.4 68.8 314.9C62.8 308.4 63.1 298.3 69.6 292.3L309.6 68.3zM320.5 101.9L144.5 266.2L144.5 512C144.5 529.7 158.8 544 176.5 544L240.5 544L240.5 432C240.5 396.7 269.2 368 304.5 368L336.5 368C371.8 368 400.5 396.7 400.5 432L400.5 544L464.5 544C482.2 544 496.5 529.7 496.5 512L496.5 266.2L320.5 101.9zM272.5 544L368.5 544L368.5 432C368.5 414.3 354.2 400 336.5 400L304.5 400C286.8 400 272.5 414.3 272.5 432L272.5 544z"/></svg>
		</span> Dashboard
    </a>
    <a href="classes.php" class="nav-item <?= $current_page == 'classes.php' ? 'active' : '' ?>">
        <span class="nav-icon">
						<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640"><!--! Font Awesome Pro 7.1.0 by @fontawesome - https://fontawesome.com License - https://fontawesome.com/license (Commercial License) Copyright 2025 Fonticons, Inc. --><path opacity=".4" fill="currentColor" d="M96 128C96 110.3 110.3 96 128 96L512 96C529.7 96 544 110.3 544 128L544 272C482.1 272 432 322.1 432 384C432 322.1 381.9 272 320 272C258.1 272 208 322.1 208 384C208 322.1 157.9 272 96 272L96 128z"/><path fill="currentColor" d="M512 96L128 96C110.3 96 96 110.3 96 128L96 272C84.9 272 74.1 273.6 64 276.6L64 128C64 92.7 92.7 64 128 64L512 64C547.3 64 576 92.7 576 128L576 276.6C565.9 273.6 555.1 272 544 272L544 128C544 110.3 529.7 96 512 96zM96 416C113.7 416 128 401.7 128 384C128 366.3 113.7 352 96 352C78.3 352 64 366.3 64 384C64 401.7 78.3 416 96 416zM96 320C131.3 320 160 348.7 160 384C160 419.3 131.3 448 96 448C60.7 448 32 419.3 32 384C32 348.7 60.7 320 96 320zM320 416C337.7 416 352 401.7 352 384C352 366.3 337.7 352 320 352C302.3 352 288 366.3 288 384C288 401.7 302.3 416 320 416zM320 320C355.3 320 384 348.7 384 384C384 419.3 355.3 448 320 448C284.7 448 256 419.3 256 384C256 348.7 284.7 320 320 320zM576 384C576 366.3 561.7 352 544 352C526.3 352 512 366.3 512 384C512 401.7 526.3 416 544 416C561.7 416 576 401.7 576 384zM480 384C480 348.7 508.7 320 544 320C579.3 320 608 348.7 608 384C608 419.3 579.3 448 544 448C508.7 448 480 419.3 480 384zM32 544L32 560C32 568.8 24.8 576 16 576C7.2 576 0 568.8 0 560L0 544C0 508.7 28.7 480 64 480L128 480C163.3 480 192 508.7 192 544L192 560C192 568.8 184.8 576 176 576C167.2 576 160 568.8 160 560L160 544C160 526.3 145.7 512 128 512L64 512C46.3 512 32 526.3 32 544zM288 512C270.3 512 256 526.3 256 544L256 560C256 568.8 248.8 576 240 576C231.2 576 224 568.8 224 560L224 544C224 508.7 252.7 480 288 480L352 480C387.3 480 416 508.7 416 544L416 560C416 568.8 408.8 576 400 576C391.2 576 384 568.8 384 560L384 544C384 526.3 369.7 512 352 512L288 512zM480 544L480 560C480 568.8 472.8 576 464 576C455.2 576 448 568.8 448 560L448 544C448 508.7 476.7 480 512 480L576 480C611.3 480 640 508.7 640 544L640 560C640 568.8 632.8 576 624 576C615.2 576 608 568.8 608 560L608 544C608 526.3 593.7 512 576 512L512 512C494.3 512 480 526.3 480 544z"/></svg>
		</span> My Classes
    </a>
    <a href="attendance.php" class="nav-item <?= $current_page == 'attendance.php' ? 'active' : '' ?>">
        <span class="nav-icon">
		<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640"><path opacity=".4" fill="currentColor" d="M48.5 576C57.3 576 64.5 568.8 64.5 560L64.5 544C64.5 464.5 129 400 208.5 400L304.5 400C384 400 448.5 464.5 448.5 544L448.5 560C448.5 568.8 455.7 576 464.5 576L48.5 576zM352.5 192C352.5 245 309.5 288 256.5 288C203.5 288 160.5 245 160.5 192C160.5 139 203.5 96 256.5 96C309.5 96 352.5 139 352.5 192z"/><path fill="currentColor" d="M304.5 368C401.7 368 480.5 446.8 480.5 544L480.5 560C480.5 568.8 473.3 576 464.5 576C455.7 576 448.5 568.8 448.5 560L448.5 544C448.5 464.5 384 400 304.5 400L208.5 400C129 400 64.5 464.5 64.5 544L64.5 560C64.5 568.8 57.3 576 48.5 576C39.7 576 32.5 568.8 32.5 560L32.5 544C32.5 446.8 111.3 368 208.5 368L304.5 368zM595.6 170.2C600.8 163.1 610.8 161.5 617.9 166.7C625 171.9 626.6 181.9 621.4 189L515.8 334.2C513 338 508.7 340.4 504 340.7C499.3 341 494.7 339.3 491.4 335.9L437 279.6C430.9 273.2 431 263.1 437.4 257C443.8 250.9 453.9 251.1 460 257.4L501.2 300L595.6 170.2zM256.5 320C185.8 320 128.5 262.7 128.5 192C128.5 121.3 185.8 64 256.5 64C327.2 64 384.5 121.3 384.5 192C384.5 262.7 327.2 320 256.5 320zM256.5 96C203.5 96 160.5 139 160.5 192C160.5 245 203.5 288 256.5 288C309.5 288 352.5 245 352.5 192C352.5 139 309.5 96 256.5 96z"/></svg>
		</span> Attendance
    </a>
    <a href="homework.php" class="nav-item <?= $current_page == 'homework.php' ? 'active' : '' ?>">
        <span class="nav-icon">
		<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640"><path opacity=".4" fill="currentColor" d="M96.1 128C96.1 110.3 110.4 96 128.1 96L256.1 96L256.1 192C256.1 227.3 284.8 256 320.1 256L416.1 256L416.1 348L316.5 447.6C300 464.1 289.1 485.4 285.2 508.4C283.2 520.3 281.2 532.2 279.3 544.1L128.1 544C110.4 544 96.1 529.7 96.1 512L96.1 128z"/><path fill="currentColor" d="M256.1 96L128.1 96C110.4 96 96.1 110.3 96.1 128L96.1 512C96.1 529.7 110.4 544 128.1 544L279.3 544L274 576L128.1 576C92.8 576 64.1 547.3 64.1 512L64.1 128C64.1 92.7 92.8 64 128.1 64L261.6 64C278.6 64 294.9 70.7 306.9 82.7L429.3 205.3C441.3 217.3 448 233.6 448 250.6L448 316.1L416 348.1L416 256.1L320 256.1C284.7 256.1 256 227.4 256 192.1L256 96.1zM402.8 224L288.1 109.3L288.1 192C288.1 209.7 302.4 224 320.1 224L402.8 224zM373.2 503.9C368.5 508.6 365.4 514.7 364.3 521.2L355.8 571.9L406.5 563.4C413.1 562.3 419.1 559.2 423.8 554.5L525.9 452.4L475.2 401.7L373.1 503.8zM497.8 379.2L548.5 429.9L569.2 409.2C583.2 395.2 583.2 372.5 569.2 358.5C555.2 344.5 532.5 344.5 518.5 358.5L497.8 379.2zM332.7 516C334.9 502.9 341.1 490.7 350.6 481.3L495.9 336C522.4 309.5 565.3 309.5 591.8 336C618.3 362.5 618.3 405.4 591.8 431.9L446.4 577.2C437 586.6 424.8 592.9 411.7 595.1L337.2 607.5C327.4 609.1 318.6 600.3 320.2 590.5L332.7 516z"/></svg>
		</span> Homework Status
    </a>
    <a href="assignments.php" class="nav-item <?= $current_page == 'assignments.php' ? 'active' : '' ?>">
        <span class="nav-icon">
		<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 384 512"><path opacity=".4" fill="currentColor" d="M64 0C28.7 0 0 28.7 0 64L0 448c0 35.3 28.7 64 64 64l256 0c35.3 0 64-28.7 64-64l0-288-96 0c-17.7 0-32-14.3-32-32L256 0 64 0z"/><path fill="currentColor" d="M256 0l0 96c0 17.7 14.3 32 32 32l96 0L256 0zM136 240c-13.3 0-24 10.7-24 24s10.7 24 24 24l48 0 0 72c0 13.3 10.7 24 24 24s24-10.7 24-24l0-72 48 0c13.3 0 24-10.7 24-24s-10.7-24-24-24l-48 0 0-48c0-13.3-10.7-24-24-24s-24 10.7-24 24l0 48-48 0z"/></svg>
		</span> Assignments
    </a>
    <a href="quizzes.php" class="nav-item <?= $current_page == 'quizzes.php' ? 'active' : '' ?>">
        <span class="nav-icon">
		<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640"><path opacity=".4" fill="currentColor" d="M192 96C156.7 96 128 124.7 128 160L128 512C128 547.3 156.7 576 192 576L448 576C483.3 576 512 547.3 512 512L512 234.5C512 217.5 505.3 201.2 493.3 189.3L370.7 66.7C358.7 54.7 342.4 48 325.5 48L192 48C192 48 192 96 192 96z"/><path fill="currentColor" d="M192 48L325.5 48C342.5 48 358.8 54.7 370.8 66.7L493.3 189.3C505.3 201.3 512 217.6 512 234.6L512 256L416 256C380.7 256 352 227.3 352 192L352 48L192 48zM240 320C231.2 320 224 327.2 224 336C224 344.8 231.2 352 240 352L400 352C408.8 352 416 344.8 416 336C416 327.2 408.8 320 400 320L240 320zM240 416C231.2 416 224 423.2 224 432C224 440.8 231.2 448 240 448L336 448C344.8 448 352 440.8 352 432C352 423.2 344.8 416 336 416L240 416z"/></svg>
		</span> Quizzes
    </a>
    <a href="psych-tests.php" class="nav-item <?= $current_page == 'psych-tests.php' ? 'active' : '' ?>">
        <span class="nav-icon">
		<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640"><path opacity=".4" fill="currentColor" d="M192 96C156.7 96 128 124.7 128 160L128 512C128 547.3 156.7 576 192 576L448 576C483.3 576 512 547.3 512 512L512 234.5C512 217.5 505.3 201.2 493.3 189.3L370.7 66.7C358.7 54.7 342.4 48 325.5 48L192 48C192 48 192 96 192 96z"/><path fill="currentColor" d="M240 320h160c8.8 0 16 7.2 16 16s-7.2 16-16 16H240c-8.8 0-16-7.2-16-16s7.2-16 16-16zm0 96h96c8.8 0 16 7.2 16 16s-7.2 16-16 16h-96c-8.8 0-16-7.2-16-16s7.2-16 16-16z"/></svg>
		</span> Psych Tests
    </a>
    <a href="report-card.php" class="nav-item <?= $current_page == 'report-card.php' ? 'active' : '' ?>">
        <span class="nav-icon">
		<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640"><path opacity=".4" fill="currentColor" d="M160 160C160 142.3 174.3 128 192 128L544 128C561.7 128 576 142.3 576 160L576 448C576 465.7 561.7 480 544 480L322.1 480C307 448.6 280.6 423.6 248.1 410.4C263.1 389.4 271.9 363.7 271.9 336C271.9 270.7 223 216.9 159.9 209L159.9 160zM292.7 292.7C286.5 298.9 286.5 309.1 292.7 315.3L340.7 363.3C343.7 366.3 347.8 368 352 368C356.2 368 360.3 366.3 363.3 363.3L480 246.6L480 304C480 312.8 487.2 320 496 320C504.8 320 512 312.8 512 304L512 208C512 199.2 504.8 192 496 192L400 192C391.2 192 384 199.2 384 208C384 216.8 391.2 224 400 224L457.4 224L352 329.4L315.3 292.7C309.1 286.5 298.9 286.5 292.7 292.7z"/><path fill="currentColor" d="M192 128L544 128C561.7 128 576 142.3 576 160L576 448C576 465.7 561.7 480 544 480L322.1 480C326.9 490.1 330.6 500.8 333 512L544 512C579.3 512 608 483.3 608 448L608 160C608 124.7 579.3 96 544 96L192 96C156.7 96 128 124.7 128 160L128 209C133.2 208.3 138.6 208 144 208C149.4 208 154.8 208.3 160 209L160 160C160 142.3 174.3 128 192 128zM400 192C391.2 192 384 199.2 384 208C384 216.8 391.2 224 400 224L457.4 224L352 329.4L315.3 292.7C309.1 286.5 298.9 286.5 292.7 292.7C286.5 298.9 286.5 309.1 292.7 315.3L340.7 363.3C343.7 366.3 347.8 368 352 368C356.2 368 360.3 366.3 363.3 363.3L480 246.6L480 304C480 312.8 487.2 320 496 320C504.8 320 512 312.8 512 304L512 208C512 199.2 504.8 192 496 192L400 192zM144 288C170.5 288 192 309.5 192 336C192 362.5 170.5 384 144 384C117.5 384 96 362.5 96 336C96 309.5 117.5 288 144 288zM144 416C188.2 416 224 380.2 224 336C224 291.8 188.2 256 144 256C99.8 256 64 291.8 64 336C64 380.2 99.8 416 144 416zM0 544L0 560C0 568.8 7.2 576 16 576C24.8 576 32 568.8 32 560L32 544C32 508.7 60.7 480 96 480L192 480C227.3 480 256 508.7 256 544L256 560C256 568.8 263.2 576 272 576C280.8 576 288 568.8 288 560L288 544C288 491 245 448 192 448L96 448C43 448 0 491 0 544z"/></svg>
		</span> Report Card
    </a>
    <a href="bookstore.php" class="nav-item <?= $current_page == 'bookstore.php' ? 'active' : '' ?>">
        <span class="nav-icon">
		<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640"><path opacity=".4" fill="currentColor" d="M192 96C174.3 96 160 110.3 160 128L160 512C160 529.7 174.3 544 192 544L480 544C497.7 544 512 529.7 512 512L512 128C512 110.3 497.7 96 480 96L192 96z"/><path fill="currentColor" d="M160 128C160 92.7 188.7 64 224 64L480 64C515.3 64 544 92.7 544 128L544 512C544 547.3 515.3 576 480 576L192 576C174.3 576 160 561.7 160 544C160 526.3 174.3 512 192 512L448 512C465.7 512 480 497.7 480 480L480 128C480 110.3 465.7 96 448 96L192 96C174.3 96 160 110.3 160 128zM240 176C240 167.2 247.2 160 256 160L416 160C424.8 160 432 167.2 432 176C432 184.8 424.8 192 416 192L256 192C247.2 192 240 184.8 240 176zM256 256C247.2 256 240 263.2 240 272C240 280.8 247.2 288 256 288L416 288C424.8 288 432 280.8 432 272C432 263.2 424.8 256 416 256L256 256z"/></svg>
		</span> Book Store
    </a>
	<a href="" class="nav-item <?= $current_page == 'communique.php' ? 'active' : '' ?>">
        <span class="nav-icon">
		<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640"><path opacity=".4" fill="currentColor" d="M128 432.6C128 441.1 134.9 448 143.4 448L496.6 448C505.1 448 512 441.1 512 432.6C512 429.1 510.8 425.7 508.6 423L498.5 410.4C465.8 369.5 448 318.7 448 266.3L448 256C448 185.3 390.7 128 320 128C249.3 128 192 185.3 192 256L192 266.3C192 318.7 174.2 369.5 141.4 410.4L131.4 423C129.2 425.7 128 429.1 128 432.6z"/><path fill="currentColor" d="M320 64C311.2 64 304 71.2 304 80L304 96.8C223.1 104.8 160 173 160 256L160 266.3C160 311.4 144.6 355.2 116.5 390.4L106.4 403C99.7 411.4 96 421.9 96 432.6C96 458.8 117.2 480 143.4 480L496.6 480C522.8 480 544 458.8 544 432.6C544 421.8 540.3 411.4 533.6 403L523.5 390.4C495.3 355.2 480 311.4 480 266.3L480 256C480 173 416.9 104.8 336 96.8L336 80C336 71.2 328.8 64 320 64zM320 128C390.7 128 448 185.3 448 256L448 266.3C448 318.7 465.8 369.5 498.6 410.4L508.6 423C510.8 425.7 512 429.1 512 432.6C512 441.1 505.1 448 496.6 448L143.4 448C134.9 448 128 441.1 128 432.6C128 429.1 129.2 425.7 131.4 423L141.5 410.4C174.2 369.5 192 318.7 192 266.3L192 256C192 185.3 249.3 128 320 128zM252.1 528C262 556 288.7 576 320 576C351.3 576 378 556 387.9 528L352 528C344.7 537.7 333.1 544 320 544C306.9 544 295.3 537.7 288 528L252.1 528z"/></svg>
		</span> communique
    </a>
    <a href="profile.php" class="nav-item <?= $current_page == 'profile.php' ? 'active' : '' ?>">
        <span class="nav-icon">
		<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640"><path opacity=".4" fill="currentColor" d="M112 576C120.8 576 128 568.8 128 560L128 544C128 464.5 192.5 400 272 400L368 400C447.5 400 512 464.5 512 544L512 560C512 568.8 519.2 576 528 576L112 576zM416 192C416 245 373 288 320 288C267 288 224 245 224 192C224 139 267 96 320 96C373 96 416 139 416 192z"/><path fill="currentColor" d="M224 192C224 139 267 96 320 96C373 96 416 139 416 192C416 245 373 288 320 288C267 288 224 245 224 192zM448 192C448 121.3 390.7 64 320 64C249.3 64 192 121.3 192 192C192 262.7 249.3 320 320 320C390.7 320 448 262.7 448 192zM128 544C128 464.5 192.5 400 272 400L368 400C447.5 400 512 464.5 512 544L512 560C512 568.8 519.2 576 528 576C536.8 576 544 568.8 544 560L544 544C544 446.8 465.2 368 368 368L272 368C174.8 368 96 446.8 96 544L96 560C96 568.8 103.2 576 112 576C120.8 576 128 568.8 128 560L128 544z"/></svg>
		</span> My Profile
    </a>
    <hr class="my-2 mx-3">
    <a href="../../logout.php" class="nav-item">
        <span class="nav-icon">
		<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640"><path opacity=".4" fill="currentColor" d="M259.3 320L384 444.7L384 368C384 359.2 391.2 352 400 352L528 352C536.8 352 544 344.8 544 336L544 304C544 295.2 536.8 288 528 288L400 288C391.2 288 384 280.8 384 272L384 195.3L259.3 320z"/><path fill="currentColor" d="M375 159L231 303C221.6 312.4 221.6 327.6 231 336.9L375 481C381.9 487.9 392.2 489.9 401.2 486.2C410.2 482.5 416 473.7 416 464L416 384L528 384C554.5 384 576 362.5 576 336L576 304C576 277.5 554.5 256 528 256L416 256L416 176C416 166.3 410.2 157.5 401.2 153.8C392.2 150.1 381.9 152.2 375 159zM384 444.7L259.3 320L384 195.3L384 272C384 280.8 391.2 288 400 288L528 288C536.8 288 544 295.2 544 304L544 336C544 344.8 536.8 352 528 352L400 352C391.2 352 384 359.2 384 368L384 444.7zM240 512L160 512C124.7 512 96 483.3 96 448L96 192C96 156.7 124.7 128 160 128L240 128C248.8 128 256 120.8 256 112C256 103.2 248.8 96 240 96L160 96C107 96 64 139 64 192L64 448C64 501 107 544 160 544L240 544C248.8 544 256 536.8 256 528C256 519.2 248.8 512 240 512z"/></svg>
		</span> Exit
    </a>
</div>
        </div>
        
        <script src="../../assets/js/bootstrap.min.js"></script>
	<div class="main-content-wrapper lg-stage">
        <div class="main-content student-main-content">
            <?php if (!empty($title)): ?>
                <div class="d-flex justify-content-between align-items-center mb-4">
                   
                </div>
            <?php endif; ?>