<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../../includes/auth.php';
require_once '../../includes/db.php';
require_once '../../includes/terms.php';
require_role('supervisor'); 

$current_page = 'eas.php';
$title = 'EAS Dashboard';

try {
    $stmt = $pdo->prepare("
        SELECT 
            c.id as main_class_id,
            c.title as class_title,
            c.schedule_type,
            c.class_type,
            c.schedule, 
            c.class_time,
            u_teacher.full_name as teacher_name,
            e.*,
            c.teacher_id,
            c.online_link as class_online_link
        FROM classes c
        JOIN terms t ON c.term_id = t.id
        LEFT JOIN users u_teacher ON c.teacher_id = u_teacher.id
        LEFT JOIN eas_management e ON c.id = e.class_id
        WHERE c.is_deleted = 0 AND " . term_sql_teaching('t') . " AND (c.is_archived = 0 OR c.is_archived IS NULL)
        ORDER BY c.id DESC
    ");
    $stmt->execute();
    $classes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $students_by_class = [];
    $class_ids = [];
    foreach ($classes as $row) {
        $cid = (int)($row['main_class_id'] ?? 0);
        if ($cid > 0) {
            $class_ids[$cid] = $cid;
        }
    }
    if ($class_ids) {
        $ids = array_values($class_ids);
        $place = implode(',', array_fill(0, count($ids), '?'));
        $st_students = $pdo->prepare("
            SELECT u.id, u.full_name, u.username, cs.class_id
            FROM users u
            JOIN class_students cs ON u.id = cs.user_id
            WHERE cs.class_id IN ($place)
              AND u.role = 'student'
              AND IFNULL(u.is_deleted, 0) = 0
            ORDER BY u.full_name
        ");
        $st_students->execute($ids);
        foreach ($st_students->fetchAll(PDO::FETCH_ASSOC) as $student_row) {
            $students_by_class[(int)$student_row['class_id']][] = $student_row;
        }
    }

} catch (PDOException $e) {
    die("<div style='background:#e74c3c; color:white; padding:20px; border-radius:10px; margin:20px; text-align:left; direction:ltr;'>
            <h3>⚠️ Database Error</h3><p>" . $e->getMessage() . "</p>
         </div>");
}

function getDayBadgeClass($type) {
    $type = strtolower($type ?? '');
    if ($type == 'even' || strpos($type, 'زوج') !== false) return ['day-even', 'روزهای زوج', 'even'];
    if ($type == 'odd' || strpos($type, 'فرد') !== false) return ['day-odd', 'روزهای فرد', 'odd'];
    if ($type == 'private' || strpos($type, 'خصوصی') !== false) return ['day-private', 'خصوصی', 'private'];
    return ['day-even', 'نامشخص', 'all']; 
}

require_once 'layout.php';
?>
<link rel="stylesheet" href="../../assets/css/jalalidatepicker.min.css">

<style>
    .dashboard-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; padding-bottom: 20px; border-bottom: 1px solid rgba(255, 255, 255, 0.1); }
    .header-title h1 { font-size: 1.8rem; font-weight: 900; margin-bottom: 5px; display: flex; align-items: center; gap: 10px; font-family: 'YekanBakhFaNum', Tahoma, Arial, sans-serif;}
    .header-title h1 span { color: #e94560; }
    .header-title p { color: #8e8e93; font-size: 0.9rem; }
    
    .live-clock-container { text-align: left; background: rgba(0, 0, 0, 0.2); padding: 10px 20px; border-radius: 12px; border: 1px solid rgba(255, 255, 255, 0.05); box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3); }
    .live-time { font-size: 1.4rem; font-family: 'YekanBakhFaNum', Tahoma, Arial, sans-serif; font-weight: 900; color: #ffffff; letter-spacing: 2px; }
    .live-time span { color: #e94560; animation: blink 1s infinite; }
    .live-date { font-size: 0.8rem; color: #8e8e93; margin-top: 2px; }
    @keyframes blink { 50% { opacity: 0; } }
    
    .controls-section { background: rgba(255, 255, 255, 0.03); border: 1px solid rgba(255, 255, 255, 0.05); padding: 15px 20px; border-radius: 16px; margin-bottom: 15px; display: flex; gap: 20px; align-items: center; flex-wrap: wrap; justify-content: space-between;}
    .filters-left { display: flex; gap: 20px; align-items: center; flex-wrap: wrap; }
    .filter-group { display: flex; align-items: center; gap: 10px; }
    .filter-group label { color: #8e8e93; font-size: 0.85rem; font-weight: 700; }
    .custom-select { background: rgba(0, 0, 0, 0.3); color: #ffffff; border: 1px solid rgba(255, 255, 255, 0.1); padding: 8px 15px; border-radius: 8px; outline: none; cursor: pointer; font-size: 0.85rem; }
    
    .export-excel-btn { background: #217346; color: white; border: none; padding: 8px 15px; border-radius: 8px; font-weight: 700; cursor: pointer; display: flex; align-items: center; gap: 8px; transition: 0.3s; font-size: 0.85rem; border: 1px solid #1e6b41;}
    .export-excel-btn:hover { background: #1e6b41; transform: translateY(-2px); box-shadow: 0 4px 12px rgba(33, 115, 70, 0.4); }

    .time-chips-container { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 25px; align-items: center;}
    .time-chips-container label { color: #8e8e93; font-size: 0.85rem; font-weight: 700; margin-left: 5px;}
    .time-chip { background: rgba(0, 0, 0, 0.3); border: 1px solid rgba(255, 255, 255, 0.1); padding: 6px 16px; border-radius: 20px; color: #8e8e93; font-size: 0.9rem; cursor: pointer; transition: 0.3s; user-select: none; font-family: 'YekanBakhFaNum', Tahoma, Arial, sans-serif;}
    .time-chip:hover { background: rgba(233, 69, 96, 0.2); color: #fff; border-color: rgba(233, 69, 96, 0.5);}
    .time-chip.active { background: #e94560; color: #fff; border-color: #e94560; font-weight: bold; box-shadow: 0 4px 10px rgba(233, 69, 96, 0.3);}

    .cards-container { display: grid; grid-template-columns: repeat(auto-fill, minmax(min(100%, 360px), 1fr)); gap: 25px; min-width: 0; }
    .eas-card { background: rgba(14, 14, 22, 0.72); border-radius: 20px; padding: 25px; box-shadow: 0 8px 32px rgba(0, 0, 0, 0.5); border: 1px solid rgba(127, 29, 29, 0.32); display: flex; flex-direction: column; gap: 20px; transition: transform 0.3s; min-width: 0; max-width: 100%; box-sizing: border-box; backdrop-filter: blur(24px); }
    
    .card-header { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 1px solid rgba(255, 255, 255, 0.05); padding-bottom: 15px; }
    .class-info h2 { font-family: 'YekanBakhFaNum', Tahoma, Arial, sans-serif; font-size: 1.3rem; font-weight: 900; margin-bottom: 5px; color:#fff; display:flex; align-items:center; gap:8px; flex-wrap:wrap;}
    .class-info h2 .class-live-dot.is-on { display: inline-block; flex: 0 0 8px; }
    .class-info .teacher { color: #e94560; font-family: 'YekanBakhFaNum', Tahoma, Arial, sans-serif; font-size: 0.9rem; font-weight: 700; }
    .class-info .teacher.eas-teacher { cursor: pointer; user-select: none; border-bottom: 1px dashed transparent; width: fit-content; }
    .class-info .teacher.eas-teacher:hover { border-bottom-color: rgba(233, 69, 96, 0.7); }
    .eas-join-btn { display: inline-flex; align-items: center; justify-content: center; margin-top: 10px; background: rgba(46, 204, 113, 0.18); color: #2ecc71; border: 1px solid #2ecc71; border-radius: 10px; padding: 7px 14px; font-size: 0.82rem; font-weight: 800; text-decoration: none; width: fit-content; }
    .eas-join-btn:hover { background: #2ecc71; color: #082015; }
    .student-badge.eas-student { cursor: pointer; user-select: none; display: inline-flex; align-items: center; gap: 6px; }
    .student-badge.eas-student:hover { border-color: rgba(233, 69, 96, 0.55); background: rgba(233, 69, 96, 0.12); }
    .student-file { color: #8e8e93; font-family: 'YekanBakhFaNum', Tahoma, Arial, sans-serif; direction: ltr; unicode-bidi: isolate; font-weight: 700; }
    .meta-info { display: flex; flex-direction: column; align-items: flex-end; gap: 8px; }
    
    .badges-wrapper { display: flex; align-items: center; gap: 6px; flex-wrap: wrap; justify-content: flex-end; }
    
    /* استایل‌های جدید برای خوش‌فرم شدن بج‌ها */
    .type-badge, .day-badge, .time-badge {
        white-space: nowrap !important;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        height: 26px;
        line-height: 1;
        box-sizing: border-box;
    }
    
    .type-badge { font-size: 0.75rem; padding: 0 10px; border-radius: 8px; font-weight: bold; }
    .type-online { background: rgba(46, 204, 113, 0.2); color: #2ecc71; border: 1px solid #2ecc71; }
    .type-inperson { background: rgba(142, 142, 147, 0.2); color: #ccc; border: 1px solid #8e8e93; }
    .type-private { background: rgba(155, 89, 182, 0.2); color: #9b59b6; border: 1px solid #9b59b6; }
    
    .time-badge { font-family: 'YekanBakhFaNum', Tahoma, Arial, sans-serif; font-size: 0.85rem; background: rgba(233, 69, 96, 0.2); color: #fff; border: 1px solid #e94560; padding: 0 10px; border-radius: 8px; }
    .day-badge { padding: 0 10px; border-radius: 8px; font-size: 0.75rem; font-weight: 700; }
    .day-even { background: rgba(52, 152, 219, 0.15); color: #3498db; border: 1px solid rgba(52, 152, 219, 0.3); }
    .day-odd { background: rgba(155, 89, 182, 0.15); color: #9b59b6; border: 1px solid rgba(155, 89, 182, 0.3); }
    .day-private { background: rgba(243, 156, 18, 0.15); color: #f39c12; border: 1px solid rgba(243, 156, 18, 0.3); }
    
    .section-title { font-size: 0.8rem; color: #8e8e93; margin-bottom: 10px; font-weight: 700; display: flex; align-items: center; gap: 5px; }
    
    .student-badges { display: flex; flex-wrap: wrap; gap: 8px; }
    .student-badge { background: rgba(255, 255, 255, 0.05); color: #fff; padding: 6px 12px; border-radius: 20px; font-size: 0.8rem; border: 1px solid rgba(255,255,255,0.1);}
    
    .sessions-container { background: rgba(0, 0, 0, 0.2); padding: 15px; border-radius: 12px; border: 1px solid rgba(255, 255, 255, 0.05); position: relative; min-width: 0;}
    .sessions-head { display: flex; justify-content: space-between; align-items: center; gap: 8px; margin-bottom: 15px; }
    .sessions-head .section-title { margin: 0; flex: 1; min-width: 0; }
    .zoom-btn { background: rgba(255,255,255,0.1); border: none; color: #fff; padding: 4px 10px; border-radius: 6px; font-size: 0.75rem; cursor: pointer; transition: 0.2s; flex-shrink: 0;}
    .zoom-btn:hover { background: #e94560; }
    
    .sessions-grid { display: flex; flex-wrap: wrap; gap: 12px; justify-content: flex-start; transition: 0.3s; }
    .session-item { display: flex; flex-direction: column; align-items: center; gap: 5px; }
    .session-box { width: 32px; height: 32px; background: rgba(255, 255, 255, 0.05); border: 1px solid rgba(255, 255, 255, 0.15); border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 0.85rem; font-family: 'YekanBakhFaNum', Tahoma, Arial, sans-serif; color: #8e8e93; cursor: pointer; user-select: none; transition: 0.2s;}
    .session-box:hover { transform: scale(1.1); background: rgba(255,255,255,0.1);}
    
    .session-box.status-tick { background: rgba(46, 204, 113, 0.2); color: #2ecc71; border-color: #2ecc71; }
    .session-box.status-cross { background: rgba(233, 69, 96, 0.2); color: #e94560; border-color: #e94560; }
    
    .makeup-box { background: rgba(243, 156, 18, 0.2) !important; color: #f39c12 !important; border-color: #f39c12 !important; }
    .date-input { width: 60px; background: transparent; border: none; border-bottom: 1px solid rgba(255,255,255,0.2); color: #8e8e93; font-size: 0.65rem; text-align: center; font-family: 'YekanBakhFaNum', Tahoma, Arial, sans-serif; outline: none; transition: 0.2s; cursor: pointer;}
    .date-input:focus { border-color: #e94560; color: #fff;}

    .zoomed-mode { gap: 20px !important; justify-content: center; padding: 20px;}
    .zoomed-mode .session-box { width: 60px; height: 60px; font-size: 1.5rem; border-radius: 14px;}
    .zoomed-mode .date-input { width: 75px; font-size: 0.9rem;}

    .actions-wrapper { display: flex; flex-direction: column; gap: 12px; }
    .action-row { display: flex; gap: 15px; }
    .action-box { flex: 1; background: rgba(0, 0, 0, 0.2); padding: 12px; border-radius: 12px; border: 1px solid rgba(255, 255, 255, 0.05); display: flex; align-items: center; justify-content: space-between; gap: 10px; }
    
    .custom-checkbox { appearance: none; width: 22px; height: 22px; background: rgba(255, 255, 255, 0.1); border-radius: 6px; border: 1px solid rgba(255, 255, 255, 0.2); cursor: pointer; position: relative; margin: 0; transition: 0.2s; flex-shrink: 0;}
    .custom-checkbox:checked { background: #2ecc71; border-color: #2ecc71; }
    .custom-checkbox:checked::after { content: '✔'; position: absolute; color: white; font-size: 13px; top: 50%; left: 50%; transform: translate(-50%, -50%); }
    
    .interview-box { background: rgba(0, 0, 0, 0.2); padding: 15px; border-radius: 12px; border: 1px solid rgba(255, 255, 255, 0.05); display: flex; flex-direction: column; gap: 12px; }
    .interview-inputs { display: flex; gap: 10px; }
    
    .eas-input { width: 100%; background: rgba(0, 0, 0, 0.3); border: 1px solid rgba(255, 255, 255, 0.1); color: #ffffff; padding: 8px 12px; border-radius: 8px; font-size: 0.85rem; outline: none; transition: 0.3s;}
    .eas-input:focus { border-color: #e94560; background: rgba(0, 0, 0, 0.5); }
    
    .eas-textarea { width: 100%; background: rgba(0, 0, 0, 0.3); border: 1px solid rgba(255, 255, 255, 0.1); color: #ffffff; padding: 10px 12px; border-radius: 8px; font-size: 0.85rem; outline: none; resize: vertical; min-height: 80px; transition: 0.3s;}
    .eas-textarea:focus { border-color: #e94560; background: rgba(0, 0, 0, 0.5); }
    
    .text-fields { display: flex; flex-direction: column; gap: 12px; }
    
    jdp-container { z-index: 9999 !important; }

    @media (max-width: 767.98px) {
        .dashboard-header { flex-direction: column; align-items: stretch; gap: 12px; }
        .live-clock-container { text-align: center; }
        .controls-section, .filters-left { flex-direction: column; align-items: stretch; }
        .filter-group, .custom-select, .export-excel-btn { width: 100%; }
        .time-chips-container { overflow-x: auto; flex-wrap: nowrap; -webkit-overflow-scrolling: touch; padding-bottom: 4px; }
        .eas-card { padding: 16px; gap: 14px; }
        .card-header { flex-direction: column; align-items: stretch; gap: 10px; }
        .meta-info { align-items: flex-start; }
        .badges-wrapper { justify-content: flex-start; }
        .sessions-grid { gap: 8px; justify-content: center; }
        .action-row { flex-direction: column; }
        .interview-inputs { flex-direction: column; }
        .date-input { width: 52px; }
        .eas-join-btn { width: 100%; }
    }
</style>

<div class="dashboard-header">
    <div class="header-title">
        <h1><span>EAS</span> مدیریت داخلی</h1>
        <p>Educational Administration System</p>
    </div>
    
    <div class="live-clock-container" id="liveClockContainer">
        <div class="live-time" id="liveTime">00<span>:</span>00</div>
        <div class="live-date" id="liveDate">در حال بارگذاری...</div>
    </div>
</div>

<div class="controls-section">
    <div class="filters-left">
        <div class="filter-group">
            <label>مدرس:</label>
            <select class="custom-select" id="teacherFilter" onchange="filterCards()">
                <option value="all">همه مدرسین</option>
                <?php 
                    $teachers = array_unique(array_column($classes, 'teacher_name'));
                    foreach($teachers as $teacher) {
                        if($teacher) echo "<option value='".htmlspecialchars($teacher, ENT_QUOTES, 'UTF-8')."'>".htmlspecialchars($teacher, ENT_QUOTES, 'UTF-8')."</option>";
                    }
                ?>
            </select>
        </div>
        <div class="filter-group">
            <label>برگزاری:</label>
            <select class="custom-select" id="dayFilter" onchange="filterCards()">
                <option value="all">همه روزها</option>
                <option value="today" style="color: #e94560; font-weight: bold;">امروز</option>
                <option value="even">زوج</option>
                <option value="odd">فرد</option>
                <option value="private">خصوصی</option>
            </select>
        </div>
        <div class="filter-group">
            <label>نوع کلاس:</label>
            <select class="custom-select" id="typeFilter" onchange="filterCards()">
                <option value="all">همه</option>
                <option value="online">آنلاین</option>
                <option value="offline">حضوری</option>
                <option value="private">خصوصی</option>
            </select>
        </div>
    </div>
    
    <div>
        <button class="export-excel-btn" onclick="exportToExcel()">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="8" y1="13" x2="16" y2="13"></line><line x1="8" y1="17" x2="16" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
            خروجی اکسل جامع
        </button>
    </div>
</div>

<div class="time-chips-container" id="timeChipsContainer">
    <label>فیلتر ساعت:</label>
    <span class="time-chip active" data-time="all" onclick="selectTimeFilter(this)">همه کلاس‌ها</span>
    <span class="time-chip" data-time="10" onclick="selectTimeFilter(this)">۱۰ به بعد</span>
    <span class="time-chip" data-time="11" onclick="selectTimeFilter(this)">۱۱ به بعد</span>
    <span class="time-chip" data-time="12" onclick="selectTimeFilter(this)">۱۲ به بعد</span>
    <span class="time-chip" data-time="13" onclick="selectTimeFilter(this)">۱۳ به بعد</span>
    <span class="time-chip" data-time="14" onclick="selectTimeFilter(this)">۱۴ به بعد</span>
    <span class="time-chip" data-time="15" onclick="selectTimeFilter(this)">۱۵ به بعد</span>
    <span class="time-chip" data-time="16" onclick="selectTimeFilter(this)">۱۶ به بعد</span>
    <span class="time-chip" data-time="17" onclick="selectTimeFilter(this)">۱۷ به بعد</span>
    <span class="time-chip" data-time="18" onclick="selectTimeFilter(this)">۱۸ به بعد</span>
</div>

<div class="cards-container" id="cardsContainer">
    <?php foreach ($classes as $class): 
        $badgeInfo = getDayBadgeClass($class['schedule_type']);
        $teacherName = $class['teacher_name'] ?? 'بدون مدرس';
        
        $classTypeKey = strtolower(trim((string)($class['class_type'] ?? '')));
        if ($classTypeKey === '' || in_array($classTypeKey, ['inperson', 'in-person', 'حضوری'], true)) {
            $classTypeKey = 'offline';
        }

        if ($classTypeKey === 'online') {
            $classTypeBadge = "<span class='type-badge type-online'>آنلاین</span>";
        } elseif ($classTypeKey === 'private') {
            $classTypeBadge = "<span class='type-badge type-private'>خصوصی</span>";
        } else {
            $classTypeBadge = "<span class='type-badge type-inperson'>حضوری</span>";
        }

        $dayKey = $badgeInfo[2];
        if ($classTypeKey === 'private' && $dayKey === 'all') {
            $dayKey = 'private';
        }
        
        $total_sessions = ($classTypeKey === 'private') ? 10 : 13;
        
        $students = $students_by_class[(int)$class['main_class_id']] ?? [];
        $online_link = trim((string)($class['class_online_link'] ?? ''));
        $teacher_id = (int)($class['teacher_id'] ?? 0);

        $session_dates = json_decode($class['session_dates_json'] ?? '{}', true);
        $makeup_sessions = json_decode($class['makeup_sessions_json'] ?? '{}', true);
        
        $display_time = !empty($class['schedule']) ? $class['schedule'] : (!empty($class['class_time']) ? $class['class_time'] : 'تنظیم نشده');
    ?>
    
    <div class="eas-card" data-teacher="<?= htmlspecialchars($teacherName, ENT_QUOTES, 'UTF-8') ?>" data-day="<?= htmlspecialchars($dayKey, ENT_QUOTES, 'UTF-8') ?>" data-time="<?= htmlspecialchars($display_time, ENT_QUOTES, 'UTF-8') ?>" data-classtype="<?= htmlspecialchars($classTypeKey, ENT_QUOTES, 'UTF-8') ?>" data-classid="<?= (int)$class['main_class_id'] ?>">
        
        <div class="card-header">
            <div class="class-info">
                <h2><?= htmlspecialchars($class['class_title']) ?><?= class_live_dot([
                    'schedule' => $class['schedule'] ?: ($class['class_time'] ?? ''),
                    'schedule_type' => $class['schedule_type'] ?? '',
                ]) ?></h2>
                <div class="teacher<?= $teacher_id > 0 ? ' eas-teacher' : '' ?>"
                     data-teachername="<?= htmlspecialchars($teacherName, ENT_QUOTES, 'UTF-8') ?>"
                     <?php if ($teacher_id > 0): ?>
                     data-teacher-id="<?= $teacher_id ?>"
                     title="دبل‌کلیک: Teachers Information"
                     ondblclick="openTeacherProfile(<?= $teacher_id ?>)"
                     <?php endif; ?>
                >👨‍🏫 <?= htmlspecialchars($teacherName) ?></div>
                <?php if ($online_link !== ''): ?>
                    <a class="eas-join-btn" href="<?= htmlspecialchars($online_link, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener"><?= $classTypeKey === 'offline' ? 'ورود به رویداد آنلاین' : 'ورود به کلاس' ?></a>
                <?php endif; ?>
            </div>
            <!-- استایل جدید بج‌ها: کنار هم چیده میشن -->
            <div class="meta-info">
                <div class="badges-wrapper">
                    <?= $classTypeBadge ?>
                    <span class="day-badge <?= $badgeInfo[0] ?>" data-daytext="<?= $badgeInfo[1] ?>"><?= $badgeInfo[1] ?></span>
                </div>
                <span class="time-badge"><?= htmlspecialchars($display_time) ?></span>
            </div>
        </div>

        <div>
            <div class="section-title">👥 زبان‌آموزان فعلی</div>
            <div class="student-badges">
                <?php if(empty($students)): ?>
                    <span class="student-badge" style="color: #666; border-color:transparent;">ثبت نشده</span>
                <?php else: ?>
                    <?php foreach($students as $student): ?>
                        <span class="student-badge eas-student"
                              data-student-id="<?= (int)$student['id'] ?>"
                              title="دبل‌کلیک: Students Information"
                              ondblclick="openStudentProfile(<?= (int)$student['id'] ?>)">
                            <?= htmlspecialchars($student['full_name']) ?>
                            <?php if (trim((string)($student['username'] ?? '')) !== ''): ?>
                                <span class="student-file"><?= htmlspecialchars($student['username']) ?></span>
                            <?php endif; ?>
                        </span>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <div class="sessions-container">
            <div class="sessions-head">
                <div class="section-title">📅 تشکیل جلسه (<?= $total_sessions ?> جلسه‌ای)</div>
                <button type="button" class="zoom-btn" onclick="openZoomModal(<?= $class['main_class_id'] ?>)">🔍 بزرگنمایی</button>
            </div>
            
            <div id="placeholder-<?= $class['main_class_id'] ?>">
                <div class="sessions-grid" id="grid-<?= $class['main_class_id'] ?>">
                    <?php 
                        for($i=1; $i<=$total_sessions; $i++) {
                            $session_status = $class['session_'.$i] ?? 0;
                            $status_class = ($session_status == 1) ? 'status-tick' : (($session_status == 2) ? 'status-cross' : '');
                            $content = ($session_status == 1) ? '✔' : (($session_status == 2) ? '✖' : $i);
                            $saved_date = $session_dates['date_'.$i] ?? '';
                            
                            echo "<div class='session-item'>
                                    <div class='session-box {$status_class}' data-num='{$i}' onclick='toggleSession(this)'>{$content}</div>
                                    <input type='text' data-jdp readonly class='date-input regular-date' placeholder='تاریخ' value='{$saved_date}'>
                                  </div>";
                                  
                            if(isset($makeup_sessions['makeup_'.$i])) {
                                echo "<div class='session-item makeup-item'>
                                        <div class='session-box makeup-box' title='جبرانی جلسه {$i}'>M</div>
                                        <input type='text' data-jdp readonly class='date-input makeup-date' data-num='{$i}' value='{$makeup_sessions['makeup_'.$i]}'>
                                      </div>";
                            }
                        }
                    ?>
                </div>
            </div>
        </div>

        <div class="actions-wrapper">
            <div class="action-row">
                <div class="action-box">
                    <span style="font-size: 0.8rem; font-weight:700; color:#8e8e93">📊 نظرسنجی</span>
                    <input type="checkbox" class="custom-checkbox" onchange="saveEasData(<?= $class['main_class_id'] ?>, 'poll_status', this.checked ? 1 : 0)" <?= (isset($class['poll_status']) && $class['poll_status'] == 1) ? 'checked' : '' ?>>
                </div>
                <div class="action-box">
                    <span style="font-size: 0.8rem; font-weight:700; color:#8e8e93">👁 ارزیابی</span>
                    <input type="checkbox" class="custom-checkbox" onchange="saveEasData(<?= $class['main_class_id'] ?>, 'observation_status', this.checked ? 1 : 0)" <?= (isset($class['observation_status']) && $class['observation_status'] == 1) ? 'checked' : '' ?>>
                </div>
            </div>
            <div class="action-row">
                <div class="action-box">
                    <span style="font-size: 0.8rem; font-weight:700; color:#8e8e93">🧮 محاسبه حقوق</span>
                    <input type="checkbox" class="custom-checkbox" onchange="saveEasData(<?= $class['main_class_id'] ?>, 'salary_calculated', this.checked ? 1 : 0)" <?= (isset($class['salary_calculated']) && $class['salary_calculated'] == 1) ? 'checked' : '' ?>>
                </div>
                <div class="action-box">
                    <span style="font-size: 0.8rem; font-weight:700; color:#8e8e93">💳 پرداخت حقوق</span>
                    <input type="checkbox" class="custom-checkbox" onchange="saveEasData(<?= $class['main_class_id'] ?>, 'salary_paid', this.checked ? 1 : 0)" <?= (isset($class['salary_paid']) && $class['salary_paid'] == 1) ? 'checked' : '' ?>>
                </div>
            </div>
        </div>

        <div class="interview-box">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <div class="section-title" style="margin: 0;">🎙 مصاحبه (Interview)</div>
                <label style="display:flex; align-items:center; gap:8px; color:#fff; font-size:0.75rem; margin:0; cursor:pointer;">
                    انجام شد
                    <input type="checkbox" class="custom-checkbox" onchange="saveEasData(<?= $class['main_class_id'] ?>, 'interview_status', this.checked ? 1 : 0)" <?= (isset($class['interview_status']) && $class['interview_status'] == 1) ? 'checked' : '' ?>>
                </label>
            </div>
            <div class="interview-inputs">
                <input type="text" data-jdp readonly class="eas-input interview-date-input" style="flex: 1; text-align: left; direction: ltr;" placeholder="انتخاب تاریخ..." value="<?= htmlspecialchars($class['interview_date'] ?? '') ?>">
                <input type="text" class="eas-input interview-name-input" style="flex: 2;" placeholder="نام مصاحبه‌کننده..." value="<?= htmlspecialchars($class['interviewer_name'] ?? '') ?>" onblur="saveEasData(<?= $class['main_class_id'] ?>, 'interviewer_name', this.value)">
            </div>
        </div>

        <div class="text-fields">
            <div>
                <div class="section-title">⏳ در انتظار ثبت‌نام</div>
                <textarea class="eas-textarea pending-text" onblur="saveEasData(<?= $class['main_class_id'] ?>, 'pending_students', this.value)" placeholder="اسامی افراد در صف انتظار..."><?= htmlspecialchars($class['pending_students'] ?? '') ?></textarea>
            </div>
            <div>
                <div class="section-title">📝 یادداشت مدیر</div>
                <textarea class="eas-textarea admin-note-text" onblur="saveEasData(<?= $class['main_class_id'] ?>, 'admin_note', this.value)" placeholder="نکات مربوط به کلاس..."><?= htmlspecialchars($class['admin_note'] ?? '') ?></textarea>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<div class="modal fade" id="zoomModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content" style="background: rgba(15, 15, 24, 0.96); border: 1px solid rgba(239, 68, 68, 0.35); border-radius: 24px;">
            <div class="modal-header border-0">
                <h5 class="modal-title text-white font-monospace">جزئیات جلسات</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="zoomModalBody" style="padding: 30px;">
            </div>
        </div>
    </div>
</div>

<script src="../../assets/js/jalalidatepicker.min.js"></script>
<script>
    if (window.jalaliDatepicker) {
        jalaliDatepicker.startWatch({ minDate: "attr", maxDate: "attr", time: false });
    }

    document.addEventListener("jdp:change", function (e) {
        const inp = e.target;
        const classId = inp.closest('.eas-card') ? inp.closest('.eas-card').getAttribute('data-classid') : currentZoomClassId;
        if(!classId) return;

        if (inp.classList.contains('regular-date')) {
            saveDateUI(classId);
        } else if (inp.classList.contains('makeup-date')) {
            saveMakeupUI(classId);
        } else if (inp.classList.contains('interview-date-input')) {
            saveEasData(classId, 'interview_date', inp.value);
        }
    });

    function updateLiveClock() {
        const now = new Date();
        let hours = now.getHours().toString().padStart(2, '0');
        let minutes = now.getMinutes().toString().padStart(2, '0');
        const persianDate = now.toLocaleDateString('fa-IR', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
        document.getElementById('liveTime').innerHTML = `${hours}<span>:</span>${minutes}`;
        document.getElementById('liveDate').innerText = persianDate;
    }
    setInterval(updateLiveClock, 1000);
    updateLiveClock();

    function openStudentProfile(id) {
        id = parseInt(id, 10);
        if (!id) return;
        window.location.href = 'students-info-view.php?id=' + id;
    }

    function openTeacherProfile(id) {
        id = parseInt(id, 10);
        if (!id) return;
        window.location.href = 'teachers-info-view.php?id=' + id;
    }

    function saveEasData(classId, field, value) {
        let formData = new FormData();
        formData.append('class_id', classId);
        formData.append('field', field);
        formData.append('value', value);

        fetch('ajax_eas_update.php', { method: 'POST', body: formData });
    }

    let currentTimeFilterVal = 'all';

    function selectTimeFilter(el) {
        document.querySelectorAll('.time-chip').forEach(c => c.classList.remove('active'));
        el.classList.add('active');
        currentTimeFilterVal = el.getAttribute('data-time');
        filterCards();
    }

    function toEnglishDigits(str) {
        return String(str || '').replace(/[۰-۹]/g, function (d) {
            return '۰۱۲۳۴۵۶۷۸۹'.indexOf(d);
        }).replace(/[٠-٩]/g, function (d) {
            return '٠١٢٣٤٥٦٧٨٩'.indexOf(d);
        });
    }

    function parseTimeStr(timeStr) {
        if (!timeStr || timeStr === 'تنظیم نشده') return 9999;
        const s = toEnglishDigits(timeStr);
        const match = s.match(/(\d{1,2})(?::(\d{1,2}))?/);
        if (!match) return 9999;
        const h = parseInt(match[1], 10);
        const m = match[2] ? parseInt(match[2], 10) : 0;
        if (h > 23 || m > 59) return 9999;
        return h * 60 + m;
    }

    function tehranDayOfWeek() {
        const wd = new Intl.DateTimeFormat('en-US', { timeZone: 'Asia/Tehran', weekday: 'short' }).format(new Date());
        return { Sun: 0, Mon: 1, Tue: 2, Wed: 3, Thu: 4, Fri: 5, Sat: 6 }[wd] ?? new Date().getDay();
    }

    function filterCards() {
        const teacherFilter = document.getElementById('teacherFilter').value;
        const dayFilter = document.getElementById('dayFilter').value;
        const typeFilter = document.getElementById('typeFilter').value; 
        const cardsContainer = document.getElementById('cardsContainer');
        const cards = Array.from(document.querySelectorAll('.eas-card'));
        
        const dayOfWeek = tehranDayOfWeek();
        let todayType = 'none';
        if (dayOfWeek === 6 || dayOfWeek === 1 || dayOfWeek === 3) todayType = 'even';
        else if (dayOfWeek === 0 || dayOfWeek === 2 || dayOfWeek === 4) todayType = 'odd';

        let visibleCards = [];

        cards.forEach(card => {
            const cardTeacher = card.getAttribute('data-teacher');
            const cardDay = card.getAttribute('data-day');
            const cardTimeStr = card.getAttribute('data-time');
            const cardType = (card.getAttribute('data-classtype') || 'offline').toLowerCase(); 
            const cardMinutes = parseTimeStr(cardTimeStr);
            
            const matchTeacher = (teacherFilter === 'all' || cardTeacher === teacherFilter);
            
            let matchDay = false;
            if (dayFilter === 'all') {
                matchDay = true;
            } else if (dayFilter === 'today') {
                matchDay = (cardDay === todayType) || (cardType === 'private' && todayType !== 'none');
            } else if (dayFilter === 'private') {
                matchDay = (cardType === 'private' || cardDay === 'private');
            } else {
                matchDay = (cardDay === dayFilter);
            }

            let matchTime = true;
            if (currentTimeFilterVal !== 'all') {
                const filterMinutes = parseInt(currentTimeFilterVal, 10) * 60;
                matchTime = (cardMinutes >= filterMinutes && cardMinutes < 9999);
            }

            const normalizedType = (typeFilter === 'offline' && cardType === '') ? 'offline' : cardType;
            const matchType = (typeFilter === 'all' || normalizedType === typeFilter);

            if (matchTeacher && matchDay && matchTime && matchType) {
                card.style.display = 'flex';
                visibleCards.push({el: card, time: cardMinutes});
            } else {
                card.style.display = 'none';
            }
        });

        if(currentTimeFilterVal !== 'all') {
            visibleCards.sort((a, b) => a.time - b.time);
            visibleCards.forEach(item => cardsContainer.appendChild(item.el));
        }
    }

    function loadXlsx() {
        return new Promise(function (resolve, reject) {
            if (window.XLSX) {
                resolve(window.XLSX);
                return;
            }
            var s = document.createElement('script');
            s.src = '../../assets/js/xlsx.full.min.js';
            s.onload = function () { window.XLSX ? resolve(window.XLSX) : reject(new Error('xlsx')); };
            s.onerror = function () { reject(new Error('xlsx')); };
            document.head.appendChild(s);
        });
    }

    async function exportToExcel() {
        var btn = document.querySelector('.export-excel-btn');
        if (btn) {
            btn.disabled = true;
        }
        try {
            await loadXlsx();
        let exportData = [];
        
        document.querySelectorAll('.eas-card').forEach(card => {
            if (card.style.display === 'none') return;
            
            const className = card.querySelector('.class-info h2').childNodes[0].nodeValue.trim();
            const classType = card.querySelector('.type-badge').innerText.trim();
            const teacher = card.getAttribute('data-teacher');
            const time = card.getAttribute('data-time');
            const dayStr = card.querySelector('.day-badge').innerText.trim();
            
            let studentsArr = [];
            card.querySelectorAll('.student-badge').forEach(badge => {
                let text = badge.innerText.trim();
                if(text !== 'ثبت نشده') studentsArr.push(text);
            });
            const studentsStr = studentsArr.length > 0 ? studentsArr.join('، ') : 'بدون زبان‌آموز';

            const actionBoxes = card.querySelectorAll('.action-box');
            const pollStatus = (actionBoxes[0] && actionBoxes[0].querySelector('.custom-checkbox').checked) ? 'انجام شده' : 'خیر';
            const obsStatus = (actionBoxes[1] && actionBoxes[1].querySelector('.custom-checkbox').checked) ? 'انجام شده' : 'خیر';
            const calcStatus = (actionBoxes[2] && actionBoxes[2].querySelector('.custom-checkbox').checked) ? 'انجام شده' : 'خیر';
            const paidStatus = (actionBoxes[3] && actionBoxes[3].querySelector('.custom-checkbox').checked) ? 'انجام شده' : 'خیر';

            const intBox = card.querySelector('.interview-box');
            const intStatus = intBox.querySelector('.custom-checkbox').checked ? 'انجام شده' : 'خیر';
            const intDate = card.querySelector('.interview-date-input').value.trim();
            const intName = card.querySelector('.interview-name-input').value.trim();

            const pendingStudents = card.querySelector('.pending-text').value.trim();
            const adminNote = card.querySelector('.admin-note-text').value.trim();

            let rowData = {
                "نام کلاس": className,
                "مدرس": teacher,
                "روز برگزاری": dayStr,
                "ساعت": time,
                "نوع کلاس": classType,
                "محاسبه حقوق": calcStatus,
                "پرداخت حقوق": paidStatus,
                "وضعیت نظرسنجی": pollStatus,
                "وضعیت ارزیابی": obsStatus,
                "وضعیت مصاحبه": intStatus,
                "تاریخ مصاحبه": intDate,
                "مصاحبه‌کننده": intName,
                "زبان‌آموزان فعلی": studentsStr,
                "در انتظار ثبت‌نام": pendingStudents,
                "یادداشت مدیر": adminNote
            };

            for (let i = 1; i <= 13; i++) {
                let sessionBox = card.querySelector(`.session-box[data-num="${i}"]`);
                if (sessionBox) {
                    let status = "ثبت نشده";
                    if (sessionBox.classList.contains('status-tick')) status = "برگزار شد";
                    else if (sessionBox.classList.contains('status-cross')) status = "لغو شد";

                    let dateInput = sessionBox.closest('.session-item').querySelector('.regular-date');
                    let dateVal = dateInput ? dateInput.value.trim() : '';

                    let makeupStr = "";
                    let makeupInput = card.querySelector(`.makeup-date[data-num="${i}"]`);
                    if (makeupInput) {
                        makeupStr = makeupInput.value.trim();
                    }

                    let finalStr = status;
                    if (dateVal) finalStr += ` (${dateVal})`;
                    if (makeupStr) finalStr += ` [جبرانی: ${makeupStr}]`;

                    rowData[`جلسه ${i}`] = finalStr;
                }
            }

            exportData.push(rowData);
        });

        if(exportData.length === 0) {
            alert("هیچ کلاسی یافت نشد!");
            return;
        }

        const worksheet = XLSX.utils.json_to_sheet(exportData);
        const workbook = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(workbook, worksheet, "EAS_Classes");
        
        const date = new Date().toLocaleDateString('fa-IR').replace(/\//g, '-');
        XLSX.writeFile(workbook, `Ghazal_EAS_Classes_${date}.xlsx`);
        } catch (err) {
            alert('خروجی اکسل لود نشد. صفحه را تازه کنید و دوباره بزنید.');
        } finally {
            if (btn) btn.disabled = false;
        }
    }

    function saveDateUI(classId) {
        const grid = document.getElementById('grid-' + classId);
        const inputs = grid.querySelectorAll('.regular-date');
        let datesObj = {};
        inputs.forEach((inp, index) => {
            if(inp.value.trim() !== '') datesObj['date_' + (index + 1)] = inp.value;
        });
        saveEasData(classId, 'session_dates_json', JSON.stringify(datesObj));
    }

    function saveMakeupUI(classId) {
        const grid = document.getElementById('grid-' + classId);
        const inputs = grid.querySelectorAll('.makeup-date');
        let obj = {};
        inputs.forEach(inp => {
            if(inp.value.trim() !== '') obj['makeup_' + inp.getAttribute('data-num')] = inp.value;
        });
        saveEasData(classId, 'makeup_sessions_json', JSON.stringify(obj));
    }

    function toggleSession(el) {
        const num = el.getAttribute('data-num');
        const card = el.closest('.eas-card') || document.getElementById('placeholder-' + currentZoomClassId).closest('.eas-card');
        const classId = card.getAttribute('data-classid');
        let newValue = 0;

        if (el.classList.contains('status-tick')) {
            el.classList.remove('status-tick');
            el.classList.add('status-cross');
            el.innerHTML = '✖';
            newValue = 2;
            
            let makeupBox = `
                <div class="session-item makeup-item">
                    <div class="session-box makeup-box" title="جبرانی جلسه ${num}">M</div>
                    <input type="text" data-jdp readonly class="date-input makeup-date" data-num="${num}" placeholder="تاریخ">
                </div>`;
            el.closest('.session-item').insertAdjacentHTML('afterend', makeupBox);
            if (window.jalaliDatepicker) jalaliDatepicker.startWatch(); 
            
        } 
        else if (el.classList.contains('status-cross')) {
            el.classList.remove('status-cross');
            el.innerHTML = num;
            newValue = 0;
            let nextEl = el.closest('.session-item').nextElementSibling;
            if(nextEl && nextEl.classList.contains('makeup-item')) {
                nextEl.remove();
                saveMakeupUI(classId); 
            }
        } 
        else {
            el.classList.add('status-tick');
            el.innerHTML = '✔';
            newValue = 1;
        }

        saveEasData(classId, 'session_' + num, newValue);
    }

    let currentZoomClassId = null;
    function openZoomModal(classId) {
        currentZoomClassId = classId;
        const grid = document.getElementById('grid-' + classId);
        const modalBody = document.getElementById('zoomModalBody');
        
        modalBody.appendChild(grid); 
        grid.classList.add('zoomed-mode'); 
        
        new bootstrap.Modal(document.getElementById('zoomModal')).show();
    }

    document.getElementById('zoomModal').addEventListener('hidden.bs.modal', function () {
        if (currentZoomClassId) {
            const grid = document.getElementById('grid-' + currentZoomClassId);
            grid.classList.remove('zoomed-mode');
            document.getElementById('placeholder-' + currentZoomClassId).appendChild(grid); 
            currentZoomClassId = null;
        }
    });

</script>

<?php require_once 'layout-end.php'; ?>