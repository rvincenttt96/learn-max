<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once '../../includes/auth.php';
require_once '../../includes/db.php';
require_once '../../includes/terms.php';
require_once '../../includes/year-program.php';
require_once '../../includes/placement.php';
require_role('supervisor');

$current_page = 'placement.php';
$title = 'کلاس‌بندی';

term_ensure_schema($pdo);
term_promote_due($pdo);
placement_ensure_schema($pdo);

$open_terms = term_open_rows($pdo);
$teaching_term = term_pick_teaching($open_terms);
$upcoming_term = term_pick_upcoming($open_terms);

$visible_terms = [];
if ($teaching_term) {
    $visible_terms[] = $teaching_term;
}
if ($upcoming_term && (!$teaching_term || (int)$upcoming_term['id'] !== (int)$teaching_term['id'])) {
    $visible_terms[] = $upcoming_term;
}

$requested_term = (int)($_GET['term_id'] ?? 0);
$active_term = null;
foreach ($visible_terms as $term) {
    if ((int)$term['id'] === $requested_term) {
        $active_term = $term;
        break;
    }
}
if (!$active_term) {
    $active_term = $teaching_term ?: ($upcoming_term ?: null);
}

$teachers = placement_teacher_rows($pdo);
$interviewers = placement_staff_rows($pdo);
$students_catalog = placement_student_catalog($pdo);
$level_names = placement_level_names();
$classes = $active_term ? placement_load_term_classes($pdo, (int)$active_term['id']) : [];
$previous_payload = $active_term ? placement_previous_term_payload($pdo, $active_term) : ['term_id' => 0, 'title' => '', 'classes' => []];
$active_term_title = $active_term ? (function_exists('term_english_title') ? term_english_title($active_term['title'] ?? '') : (string)($active_term['title'] ?? '')) : '';

function placement_h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function placement_type_label($type) {
    $type = strtolower(trim((string)$type));
    if ($type === 'online') {
        return ['online', 'آنلاین', 'type-online'];
    }
    if ($type === 'private') {
        return ['private', 'خصوصی', 'type-private'];
    }
    return ['offline', 'حضوری', 'type-inperson'];
}

function placement_day_label($type) {
    $type = strtolower(trim((string)$type));
    if ($type === 'odd') {
        return ['odd', 'روزهای فرد', 'day-odd'];
    }
    return ['even', 'روزهای زوج', 'day-even'];
}

require_once 'layout.php';
?>

<link type="text/css" rel="stylesheet" href="https://unpkg.com/@majidh1/jalalidatepicker/dist/jalalidatepicker.min.css">
<script type="text/javascript" src="https://unpkg.com/@majidh1/jalalidatepicker/dist/jalalidatepicker.min.js"></script>

<style>
    .plc-header { display:flex; justify-content:space-between; align-items:flex-start; gap:16px; flex-wrap:wrap; margin-bottom:22px; padding-bottom:18px; border-bottom:1px solid rgba(255,255,255,.1); }
    .plc-header h1 { font-size:1.8rem; font-weight:900; margin:0 0 6px; font-family:'YekanBakhFaNum', Tahoma, Arial, sans-serif; display:flex; align-items:center; gap:10px; }
    .plc-header h1 span { color:#e94560; }
    .plc-header p { color:#8e8e93; font-size:.9rem; margin:0; max-width:46rem; }
    .plc-actions { display:flex; gap:10px; flex-wrap:wrap; }
    .plc-btn { border:none; border-radius:10px; padding:9px 16px; font-weight:700; cursor:pointer; font-size:.85rem; text-decoration:none; display:inline-flex; align-items:center; gap:8px; }
    .plc-btn-primary { background:#e94560; color:#fff; }
    .plc-btn-ghost { background:rgba(255,255,255,.06); color:#fff; border:1px solid rgba(255,255,255,.12); }
    .plc-btn:hover { transform:translateY(-1px); }
    .plc-btn-saveall { background:#2ecc71; color:#0b1b12; }
    .plc-btn:disabled { opacity:.55; cursor:not-allowed; transform:none; }
    .plc-source { font-size:.78rem; color:#f3c6ce; background:rgba(233,69,96,.12); border:1px dashed rgba(233,69,96,.35); border-radius:10px; padding:8px 10px; }
    .plc-toolbar-actions { display:flex; gap:8px; flex-wrap:wrap; align-items:center; }
    .plc-tabs { display:flex; gap:10px; flex-wrap:wrap; margin-bottom:16px; }
    .plc-tab { background:rgba(0,0,0,.28); color:#8e8e93; border:1px solid rgba(255,255,255,.1); padding:10px 16px; border-radius:12px; text-decoration:none; font-weight:700; }
    .plc-tab.active { background:#e94560; color:#fff; border-color:#e94560; }
    .plc-tab small { display:block; font-weight:500; opacity:.85; margin-top:2px; }
    .plc-toolbar { background:rgba(255,255,255,.03); border:1px solid rgba(255,255,255,.05); padding:14px 18px; border-radius:16px; margin-bottom:20px; display:flex; gap:14px; align-items:center; flex-wrap:wrap; justify-content:space-between; }
    .plc-search { min-width:220px; flex:1; background:rgba(0,0,0,.3); border:1px solid rgba(255,255,255,.1); color:#fff; padding:8px 12px; border-radius:8px; }
    .plc-cards { display:grid; grid-template-columns:repeat(auto-fill, minmax(min(100%, 360px), 1fr)); gap:22px; }
    .plc-card { background:rgba(14,14,22,.72); border-radius:20px; padding:22px; box-shadow:0 8px 32px rgba(0,0,0,.5); border:1px solid rgba(127,29,29,.32); display:flex; flex-direction:column; gap:16px; backdrop-filter:blur(24px); }
    .plc-card.is-new { border-color:rgba(233,69,96,.55); box-shadow:0 10px 30px rgba(233,69,96,.12); }
    .plc-card-head { display:flex; justify-content:space-between; gap:12px; align-items:flex-start; border-bottom:1px solid rgba(255,255,255,.06); padding-bottom:14px; }
    .plc-level-wrap { flex:1; min-width:0; }
    .plc-title { width:100%; background:transparent; border:none; border-bottom:1px solid rgba(255,255,255,.15); color:#fff; font-family:'YekanBakhFaNum', Tahoma, Arial, sans-serif; font-size:1.15rem; font-weight:900; padding:2px 0; outline:none; }
    .plc-title:focus { border-color:#e94560; }
    .plc-badges { display:flex; gap:6px; flex-wrap:wrap; justify-content:flex-end; align-items:center; }
    .plc-x { background:rgba(233,69,96,.15); border:1px solid rgba(233,69,96,.35); color:#e94560; width:28px; height:28px; border-radius:8px; cursor:pointer; font-size:1.05rem; line-height:1; }
    .plc-discard { background:transparent; border:1px solid rgba(233,69,96,.45); color:#e94560; border-radius:10px; padding:10px 14px; font-weight:700; cursor:pointer; }
    .type-badge, .day-badge { white-space:nowrap; display:inline-flex; align-items:center; height:26px; padding:0 10px; border-radius:8px; font-size:.75rem; font-weight:700; }
    .type-online { background:rgba(46,204,113,.2); color:#2ecc71; border:1px solid #2ecc71; }
    .type-inperson { background:rgba(142,142,147,.2); color:#ccc; border:1px solid #8e8e93; }
    .type-private { background:rgba(155,89,182,.2); color:#9b59b6; border:1px solid #9b59b6; }
    .day-even { background:rgba(52,152,219,.15); color:#3498db; border:1px solid rgba(52,152,219,.3); }
    .day-odd { background:rgba(155,89,182,.15); color:#9b59b6; border:1px solid rgba(155,89,182,.3); }
    .plc-label { font-size:.8rem; color:#8e8e93; margin-bottom:8px; font-weight:700; display:flex; align-items:center; gap:6px; }
    .plc-input, .plc-select { width:100%; background:rgba(0,0,0,.3); border:1px solid rgba(255,255,255,.1); color:#fff; padding:8px 12px; border-radius:8px; font-size:.85rem; outline:none; }
    .plc-input:focus, .plc-select:focus { border-color:#e94560; }
    .plc-input[type="time"] {
        color-scheme: dark;
        direction: ltr;
        min-height: 42px;
        cursor: pointer;
        font-variant-numeric: tabular-nums;
    }
    .plc-input[type="time"]::-webkit-calendar-picker-indicator {
        opacity: 1;
        display: block;
        cursor: pointer;
        filter: invert(1);
        width: 1.15rem;
        height: 1.15rem;
    }
    .plc-grid { display:grid; grid-template-columns:1fr 1fr; gap:10px; }
    .plc-search-wrap { position:relative; }
    .plc-hits { position:absolute; right:0; left:0; top:calc(100% + 4px); background:rgba(15,15,24,.96); border:1px solid rgba(239,68,68,.3); border-radius:16px; max-height:220px; overflow:auto; z-index:20; display:none; }
    .plc-hits.open { display:block; }
    .plc-hit { padding:8px 12px; cursor:pointer; display:flex; justify-content:space-between; gap:8px; font-size:.82rem; }
    .plc-hit:hover { background:rgba(233,69,96,.18); }
    .plc-chips { display:flex; flex-wrap:wrap; gap:8px; min-height:34px; }
    .plc-chip { background:rgba(255,255,255,.05); color:#fff; padding:6px 10px; border-radius:20px; font-size:.8rem; border:1px solid rgba(255,255,255,.1); display:inline-flex; align-items:center; gap:8px; }
    .plc-chip button { background:none; border:none; color:#e94560; cursor:pointer; font-size:1rem; line-height:1; padding:0; }
    .plc-empty-chip { color:#666; border-color:transparent; }
    .interview-box { background:rgba(0,0,0,.2); padding:14px; border-radius:12px; border:1px solid rgba(255,255,255,.05); display:flex; flex-direction:column; gap:10px; }
    .plc-foot { display:flex; justify-content:space-between; align-items:center; gap:10px; flex-wrap:wrap; }
    .plc-foot-actions { display:flex; gap:8px; flex-wrap:wrap; }
    .plc-save { background:#2ecc71; color:#0b1b12; border:none; border-radius:10px; padding:10px 14px; font-weight:800; cursor:pointer; }
    .plc-save:disabled { opacity:.6; cursor:wait; }
    .plc-delete { background:transparent; border:1px solid rgba(233,69,96,.45); color:#e94560; border-radius:10px; padding:10px 14px; font-weight:700; cursor:pointer; }
    .plc-delete:disabled { opacity:.6; cursor:wait; }
    .plc-alocom { font-size:.82rem; padding:10px 12px; border-radius:10px; border:1px dashed rgba(46,204,113,.35); color:#8e8e93; }
    .plc-alocom a { color:#2ecc71; font-weight:700; }
    .plc-alocom.ready { border-style:solid; color:#2ecc71; background:rgba(46,204,113,.08); }
    .plc-alocom.pending { border-color:rgba(233,69,96,.35); color:#e8c4cc; }
    .plc-alocom.err { border-color:#e94560; color:#e94560; }
    .plc-alocom-check { display:flex; align-items:flex-start; gap:10px; margin-bottom:8px; color:#e2e8f0; font-size:.9rem; font-weight:700; cursor:pointer; user-select:none; line-height:1.45; }
    .plc-alocom-check input { width:18px; height:18px; margin-top:2px; flex-shrink:0; accent-color:#9b59b6; }
    .plc-alocom-check small { display:block; font-weight:500; color:#94a3b8; font-size:.78rem; margin-top:2px; }
    .plc-alocom-inperson { display:flex; flex-direction:column; gap:8px; margin-bottom:8px; }
    .plc-alocom-hint { margin:0; font-size:.8rem; line-height:1.55; color:#94a3b8; font-weight:500; }
    .plc-alocom-btn { background:#1f6f4a; color:#f4fff8; border:none; border-radius:10px; padding:10px 14px; font-weight:800; cursor:pointer; }
    .plc-alocom-btn:disabled { opacity:.6; cursor:wait; }
    .plc-status { font-size:.8rem; min-height:1.1em; color:#8e8e93; }
    .plc-status.ok { color:#2ecc71; }
    .plc-status.err { color:#e94560; }
    .plc-empty { background:rgba(14,14,22,.72); border-radius:20px; padding:48px 24px; text-align:center; border:1px dashed rgba(127,29,29,.35); color:#94a3b8; }
    .plc-empty h3 { color:#fff; margin-bottom:8px; }
    jdp-container { z-index:9999 !important; }
    @media (max-width: 700px) {
        .plc-grid { grid-template-columns:1fr; }
        .plc-cards { grid-template-columns:1fr; }
        .plc-header { flex-direction:column; align-items:stretch; }
        .plc-header h1 { font-size:1.35rem; word-break:break-word; }
        .plc-header p { max-width:100%; }
        .plc-search { min-width:0; width:100%; }
        .plc-card, .plc-toolbar, .plc-tabs { min-width:0; max-width:100%; }
        .plc-actions { width:100%; }
        .plc-btn { flex:1; justify-content:center; }
    }
</style>

<div class="plc-header">
    <div>
        <h1><span>Class</span> کلاس‌بندی</h1>
        <p>برای هر کلاس یک کارت: زبان‌آموزان، مدرس، تاریخ و ساعت مصاحبه، مصاحبه‌گر. بالای کارت لول است (مثل FCE 2). کلاس آنلاین اتاق الوکام را خودش می‌سازد و ورود دانش‌آموز تا تسویه شهریه بسته است. کلاس حضوری دکمهٔ ساخت رویداد آنلاین دارد برای روزهایی که آموزشگاه تشکیل نمی‌شود؛ ورود آن رویداد همیشه باز است و به پرداخت وصل نیست. کلاس خصوصی با تیک، اتاق جدا می‌سازد. حذف کلاس، رویداد الوکام همان کلاس را هم پاک می‌کند. برای ترم جدید می‌توانید کلاس‌های ترم قبل را لود کنید، تغییر بدهید و ذخیره کنید. ایمپورت اکسل ترم همچنان از صفحهٔ Terms کار می‌کند.</p>
    </div>
    <div class="plc-actions">
        <a class="plc-btn plc-btn-ghost" href="terms.php">Terms و اکسل</a>
        <a class="plc-btn plc-btn-ghost" href="eas.php">EAS</a>
        <button type="button" class="plc-btn plc-btn-primary" onclick="addNewCard()">+ کلاس تازه</button>
    </div>
</div>

<?php if (!$visible_terms): ?>
    <div class="plc-empty">
        <h3>ترم جاری یا بعدی پیدا نشد</h3>
        <p>اول از صفحهٔ Terms یک ترم بسازید یا تقویم را وارد کنید.</p>
        <a class="plc-btn plc-btn-primary" href="terms.php">رفتن به Terms</a>
    </div>
<?php else: ?>
    <div class="plc-tabs">
        <?php foreach ($visible_terms as $term):
            $is_active = $active_term && (int)$term['id'] === (int)$active_term['id'];
            $kind = term_row_is_upcoming($term) ? 'ثبت‌نام / ترم بعد' : 'در حال تدریس';
            $range = htmlspecialchars(term_display_date($term['starts_on'] ?? '') . ' تا ' . term_display_date($term['ends_on'] ?? ''), ENT_QUOTES, 'UTF-8');
        ?>
            <a class="plc-tab <?= $is_active ? 'active' : '' ?>" href="placement.php?term_id=<?= (int)$term['id'] ?>">
                <?= placement_h(term_english_title($term['title'])) ?>
                <small><?= placement_h($kind) ?> · <?= $range ?></small>
            </a>
        <?php endforeach; ?>
    </div>

    <div class="plc-toolbar">
        <input class="plc-search" id="classSearch" type="search" placeholder="جستجوی لول یا مدرس..." oninput="filterCards()">
        <div class="plc-toolbar-actions">
            <button type="button" class="plc-btn plc-btn-ghost" id="loadPrevBtn" onclick="loadPreviousClasses()" <?= empty($previous_payload['classes']) ? 'disabled' : '' ?>>
                لود کلاس‌های ترم قبل<?= !empty($previous_payload['title']) ? ' (' . placement_h($previous_payload['title']) . ')' : '' ?>
            </button>
            <button type="button" class="plc-btn plc-btn-saveall" id="saveAllNewBtn" onclick="saveAllNewCards()" style="display:none">ذخیره همه برای این ترم</button>
            <div id="classCount" style="color:#8e8e93; font-size:.85rem;">
                <?= count($classes) ?> کلاس در این ترم
            </div>
        </div>
    </div>

    <?php if ($level_names): ?>
    <datalist id="levelList">
        <?php foreach ($level_names as $level_name): ?>
            <option value="<?= placement_h($level_name) ?>"></option>
        <?php endforeach; ?>
    </datalist>
    <?php else: ?>
    <datalist id="levelList"></datalist>
    <?php endif; ?>

    <div class="plc-cards" id="cards">
        <?php if (!$classes): ?>
            <div class="plc-empty" id="emptyState">
                <h3>هنوز کلاسی در این ترم نیست</h3>
                <p>کلاس‌های ترم قبل را لود کنید، تغییر بدهید و ذخیره کنید تا در این ترم ثبت شوند. یا از «کلاس تازه» کارت بسازید.</p>
            </div>
        <?php endif; ?>
        <?php foreach ($classes as $class):
            $type = placement_type_label($class['class_type'] ?? '');
            $day = placement_day_label($class['schedule_type'] ?? '');
            $student_json = placement_h(json_encode($class['students'], JSON_UNESCAPED_UNICODE));
            $online_link = trim((string)($class['online_link'] ?? ''));
            $is_online = $type[0] === 'online';
            $is_private = $type[0] === 'private';
            $is_inperson = !$is_online && !$is_private;
            $alocom_checked = $is_online || (($is_private || $is_inperson) && $online_link !== '');
        ?>
        <article class="plc-card" data-classid="<?= (int)$class['id'] ?>" data-teacher="<?= placement_h($class['teacher_name'] ?? '') ?>">
            <div class="plc-card-head">
                <div class="plc-level-wrap">
                    <div class="plc-label">لول</div>
                    <input class="plc-title js-title" list="levelList" value="<?= placement_h($class['title']) ?>" placeholder="مثلاً FCE 2">
                </div>
                <div class="plc-badges">
                    <span class="type-badge <?= $type[2] ?>"><?= $type[1] ?></span>
                    <span class="day-badge <?= $day[2] ?>"><?= $day[1] ?></span>
                    <button type="button" class="plc-x js-delete-x" title="حذف کلاس">×</button>
                </div>
            </div>

            <div>
                <div class="plc-label">زبان‌آموزان</div>
                <div class="plc-search-wrap">
                    <input class="plc-input js-student-q" type="search" placeholder="جستجو با نام یا شماره پرونده..." data-students="<?= $student_json ?>">
                    <div class="plc-hits"></div>
                </div>
                <div class="plc-chips js-chips" style="margin-top:10px;"></div>
            </div>

            <div>
                <div class="plc-label">مدرس</div>
                <select class="plc-select js-teacher">
                    <option value="">انتخاب مدرس...</option>
                    <?php foreach ($teachers as $teacher): ?>
                        <option value="<?= (int)$teacher['id'] ?>" <?= ((int)$class['teacher_id'] === (int)$teacher['id']) ? 'selected' : '' ?>>
                            <?= placement_h($teacher['full_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="plc-grid">
                <div>
                    <div class="plc-label">نوع کلاس</div>
                    <select class="plc-select js-type">
                        <option value="offline" <?= $type[0] === 'offline' ? 'selected' : '' ?>>حضوری</option>
                        <option value="online" <?= $type[0] === 'online' ? 'selected' : '' ?>>آنلاین</option>
                        <option value="private" <?= $type[0] === 'private' ? 'selected' : '' ?>>خصوصی</option>
                    </select>
                </div>
                <div>
                    <div class="plc-label">روز برگزاری</div>
                    <select class="plc-select js-days">
                        <option value="odd" <?= $day[0] === 'odd' ? 'selected' : '' ?>>فرد (یکشنبه / سه‌شنبه / پنجشنبه)</option>
                        <option value="even" <?= $day[0] === 'even' ? 'selected' : '' ?>>زوج (شنبه / دوشنبه / چهارشنبه)</option>
                    </select>
                </div>
                <div>
                    <div class="plc-label">ساعت کلاس</div>
                    <input type="time" class="plc-input js-schedule" value="<?= placement_h(placement_schedule_for_time_input($class['schedule'] ?? '')) ?>" step="60" dir="ltr">
                </div>
            </div>
            <div class="js-alocom-wrap">
                <label class="plc-alocom-check js-alocom-private-wrap" <?= $is_private ? '' : 'style="display:none"' ?>>
                    <input type="checkbox" class="js-alocom-check" <?= $alocom_checked ? 'checked' : '' ?>>
                    <span>ساخت اتاق الوکام<small>اگر این خصوصی آنلاین برگزار می‌شود. کلاس همچنان خصوصی می‌ماند و با کلاس‌های آنلاین قاطی نمی‌شود.</small></span>
                </label>
                <div class="plc-alocom-inperson js-alocom-inperson-wrap" <?= ($is_inperson && $online_link === '') ? '' : 'style="display:none"' ?>>
                    <p class="plc-alocom-hint">برای روزهایی که کلاس حضوری تشکیل نمی‌شود. ورود این رویداد برای زبان‌آموز همیشه باز است و به تسویه شهریه وصل نیست.</p>
                    <button type="button" class="plc-alocom-btn js-alocom-create">ساخت رویداد آنلاین</button>
                </div>
                <div class="plc-alocom js-alocom <?= $online_link !== '' ? 'ready' : 'pending' ?>" data-link="<?= placement_h($online_link) ?>">
                    <?php if ($online_link !== ''): ?>
                        <?= $is_inperson ? 'رویداد جایگزین حضوری آماده است (ورود بدون تسویه)' : 'اتاق الوکام آماده است' ?> · <a href="<?= placement_h($online_link) ?>" target="_blank" rel="noopener">باز کردن</a>
                    <?php elseif ($is_online || $alocom_checked): ?>
                        با ذخیره، اتاق الوکام ساخته می‌شود
                    <?php elseif ($is_inperson): ?>
                        دکمه را بزنید تا رویداد ساخته شود
                    <?php else: ?>
                        در صورت نیاز تیک را بزنید تا با ذخیره اتاق ساخته شود
                    <?php endif; ?>
                </div>
            </div>

            <div class="interview-box">
                <div class="plc-label" style="margin:0;">وقت مصاحبه</div>
                <div class="plc-grid">
                    <div>
                        <input type="text" data-jdp readonly class="plc-input js-int-date" placeholder="تاریخ مصاحبه" value="<?= placement_h($class['interview_date'] ?? '') ?>">
                    </div>
                    <div>
                        <input type="time" class="plc-input js-int-time" value="<?= placement_h(placement_schedule_for_time_input($class['interview_time'] ?? '')) ?>" step="60" dir="ltr">
                    </div>
                </div>
                <div class="plc-label">مصاحبه‌گر</div>
                <select class="plc-select js-interviewer">
                    <option value="">مصاحبه‌گر...</option>
                    <?php
                    $seen_interviewer = false;
                    foreach ($interviewers as $person):
                        $sel = ((int)$class['interviewer_id'] === (int)$person['id']);
                        if ($sel) {
                            $seen_interviewer = true;
                        }
                        $role_fa = ($person['role'] === 'supervisor') ? 'سوپروایزر' : 'مدرس';
                    ?>
                        <option value="<?= (int)$person['id'] ?>" <?= $sel ? 'selected' : '' ?>>
                            <?= placement_h($person['full_name']) ?> · <?= $role_fa ?>
                        </option>
                    <?php endforeach; ?>
                    <?php if (!$seen_interviewer && trim((string)($class['interviewer_name'] ?? '')) !== ''): ?>
                        <option value="0" data-legacy-name="<?= placement_h($class['interviewer_name']) ?>" selected><?= placement_h($class['interviewer_name']) ?> (ثبت‌شده در EAS)</option>
                    <?php endif; ?>
                </select>
            </div>

            <div class="plc-foot">
                <div class="plc-status js-status"></div>
                <div class="plc-foot-actions">
                    <button type="button" class="plc-delete js-delete">حذف کلاس</button>
                    <button type="button" class="plc-save">ذخیره کارت</button>
                </div>
            </div>
        </article>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<script>
const TERM_ID = <?= (int)($active_term['id'] ?? 0) ?>;
const TERM_TITLE = <?= json_encode($active_term_title, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
const PREVIOUS = <?= json_encode($previous_payload, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
const STUDENTS = <?= json_encode($students_catalog, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
const TEACHERS = <?= json_encode($teachers, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
const INTERVIEWERS = <?= json_encode($interviewers, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;

function escapeHtml(s) {
    return String(s ?? '').replace(/[&<>"']/g, ch => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[ch]));
}

function scheduleTime(raw) {
    let s = String(raw || '').trim();
    const map = {'۰':'0','۱':'1','۲':'2','۳':'3','۴':'4','۵':'5','۶':'6','۷':'7','۸':'8','۹':'9','٠':'0','١':'1','٢':'2','٣':'3','٤':'4','٥':'5','٦':'6','٧':'7','٨':'8','٩':'9'};
    s = s.replace(/[۰-۹٠-٩]/g, ch => map[ch] || ch).replace(/[.–—]/g, ':');
    let m = s.match(/(\d{1,2}):(\d{2})/);
    if (m) {
        const h = Math.min(23, parseInt(m[1], 10));
        const i = Math.min(59, parseInt(m[2], 10));
        return String(h).padStart(2, '0') + ':' + String(i).padStart(2, '0');
    }
    m = s.match(/^(\d{1,2})$/);
    if (m) {
        return String(Math.min(23, parseInt(m[1], 10))).padStart(2, '0') + ':00';
    }
    return '';
}

function openTimePicker(el) {
    if (el && typeof el.showPicker === 'function') {
        try { el.showPicker(); } catch (e) {}
    }
}

function parseChipData(el) {
    try {
        const raw = el.getAttribute('data-students') || '[]';
        const arr = JSON.parse(raw);
        return Array.isArray(arr) ? arr : [];
    } catch (e) {
        return [];
    }
}

function selectedOf(card) {
    const inp = card.querySelector('.js-student-q');
    if (!inp) {
        return [];
    }
    if (!inp._selected) {
        inp._selected = parseChipData(inp);
    }
    return inp._selected;
}

function renderChips(card) {
    const box = card.querySelector('.js-chips');
    const selected = selectedOf(card);
    if (!selected.length) {
        box.innerHTML = '<span class="plc-chip plc-empty-chip">هنوز کسی اضافه نشده</span>';
        return;
    }
    box.innerHTML = selected.map(st => `
        <span class="plc-chip">
            ${escapeHtml(st.full_name)}
            <small style="opacity:.6">${escapeHtml(st.student_code || '')}</small>
            <button type="button" aria-label="حذف" onclick="removeStudent(this.closest('.plc-card'), ${Number(st.id)})">×</button>
        </span>
    `).join('');
}

function removeStudent(card, id) {
    const inp = card.querySelector('.js-student-q');
    inp._selected = selectedOf(card).filter(st => Number(st.id) !== Number(id));
    renderChips(card);
}

function addStudent(card, student) {
    const inp = card.querySelector('.js-student-q');
    const selected = selectedOf(card);
    if (selected.some(st => Number(st.id) === Number(student.id))) {
        return;
    }
    selected.push(student);
    inp._selected = selected;
    inp.value = '';
    card.querySelector('.plc-hits').classList.remove('open');
    renderChips(card);
}

function searchStudents(q) {
    q = String(q || '').trim().toLowerCase();
    if (q.length < 1) return [];
    const out = [];
    for (const st of STUDENTS) {
        const name = String(st.full_name || '').toLowerCase();
        const code = String(st.student_code || '').toLowerCase();
        if (name.includes(q) || code.includes(q)) {
            out.push(st);
            if (out.length >= 12) break;
        }
    }
    return out;
}

function bindCard(card) {
    if (!card || card.dataset.plcBound === '1') {
        return;
    }
    card.dataset.plcBound = '1';
    ensureStatus(card);
    renderChips(card);
    card.querySelectorAll('input[type="time"]').forEach(function (el) {
        el.addEventListener('click', function () { openTimePicker(el); });
        el.addEventListener('focus', function () { openTimePicker(el); });
    });
    const inp = card.querySelector('.js-student-q');
    const hits = card.querySelector('.plc-hits');
    if (inp && hits) {
        inp.addEventListener('input', function () {
            const rows = searchStudents(this.value);
            if (!rows.length) {
                hits.classList.remove('open');
                hits.innerHTML = '';
                return;
            }
            hits.innerHTML = rows.map(st => `
                <div class="plc-hit" data-id="${Number(st.id)}">
                    <span>${escapeHtml(st.full_name)}</span>
                    <span style="opacity:.65">${escapeHtml(st.student_code || '')}</span>
                </div>
            `).join('');
            hits.classList.add('open');
            hits.querySelectorAll('.plc-hit').forEach(hit => {
                hit.addEventListener('click', () => {
                    const id = Number(hit.getAttribute('data-id'));
                    const st = STUDENTS.find(s => Number(s.id) === id);
                    if (st) addStudent(card, st);
                });
            });
        });
        inp.addEventListener('blur', () => setTimeout(() => hits.classList.remove('open'), 180));
    }
    const typeSel = card.querySelector('.js-type');
    if (typeSel) {
        typeSel.addEventListener('change', function () {
            const map = {online:['آنلاین','type-online'], offline:['حضوری','type-inperson'], private:['خصوصی','type-private']};
            const badge = card.querySelector('.type-badge');
            const info = map[this.value] || map.offline;
            if (badge) {
                badge.className = 'type-badge ' + info[1];
                badge.textContent = info[0];
            }
            refreshAlocomNote(card);
        });
    }
    const alocomCheck = card.querySelector('.js-alocom-check');
    if (alocomCheck) {
        alocomCheck.addEventListener('change', function () {
            refreshAlocomNote(card);
        });
    }
    const alocomCreate = card.querySelector('.js-alocom-create');
    if (alocomCreate) {
        alocomCreate.addEventListener('click', function (e) {
            e.preventDefault();
            const check = card.querySelector('.js-alocom-check');
            if (check) check.checked = true;
            refreshAlocomNote(card);
            saveCard(card);
        });
    }
    const daySel = card.querySelector('.js-days');
    if (daySel) {
        daySel.addEventListener('change', function () {
            const badge = card.querySelector('.day-badge');
            if (!badge) return;
            if (this.value === 'odd') {
                badge.className = 'day-badge day-odd';
                badge.textContent = 'روزهای فرد';
            } else {
                badge.className = 'day-badge day-even';
                badge.textContent = 'روزهای زوج';
            }
        });
    }
}

function wantsAlocom(card) {
    const type = card.querySelector('.js-type') ? card.querySelector('.js-type').value : '';
    if (type === 'online') return true;
    if (type !== 'private' && type !== 'offline') return false;
    const check = card.querySelector('.js-alocom-check');
    const note = card.querySelector('.js-alocom');
    const link = note ? String(note.getAttribute('data-link') || '') : '';
    return !!(check && check.checked) || link !== '';
}

function refreshAlocomNote(card, opts) {
    const wrap = card.querySelector('.js-alocom-wrap');
    const note = card.querySelector('.js-alocom');
    const checkWrap = card.querySelector('.js-alocom-private-wrap');
    const inpersonWrap = card.querySelector('.js-alocom-inperson-wrap');
    const check = card.querySelector('.js-alocom-check');
    if (!wrap || !note) return;
    const type = card.querySelector('.js-type').value;
    const link = (opts && opts.link != null) ? String(opts.link) : (note.getAttribute('data-link') || '');
    const warning = (opts && opts.warning) || '';
    wrap.style.display = '';
    if (type === 'online') {
        if (checkWrap) checkWrap.style.display = 'none';
        if (inpersonWrap) inpersonWrap.style.display = 'none';
        if (check) check.checked = true;
    } else if (type === 'private') {
        if (checkWrap) checkWrap.style.display = '';
        if (inpersonWrap) inpersonWrap.style.display = 'none';
        if (link && check) check.checked = true;
    } else {
        if (checkWrap) checkWrap.style.display = 'none';
        if (inpersonWrap) inpersonWrap.style.display = link ? 'none' : '';
        if (link && check) check.checked = true;
    }
    if (warning) {
        note.className = 'plc-alocom err js-alocom';
        note.textContent = warning;
        if (link) note.setAttribute('data-link', link);
        return;
    }
    if (link) {
        note.setAttribute('data-link', link);
        note.className = 'plc-alocom ready js-alocom';
        const ready = type === 'offline'
            ? 'رویداد جایگزین حضوری آماده است (ورود بدون تسویه)'
            : 'اتاق الوکام آماده است';
        note.innerHTML = ready + ' · <a href="' + escapeHtml(link) + '" target="_blank" rel="noopener">باز کردن</a>';
        return;
    }
    note.setAttribute('data-link', '');
    const want = type === 'online' || (check && check.checked);
    note.className = 'plc-alocom pending js-alocom';
    if (type === 'offline') {
        note.textContent = want
            ? 'با ذخیره، رویداد آنلاین ساخته می‌شود. ورود بدون تسویه باز است.'
            : 'دکمه را بزنید تا رویداد جایگزین حضوری ساخته شود';
        return;
    }
    note.textContent = want
        ? 'با ذخیره، اتاق الوکام ساخته می‌شود'
        : 'در صورت نیاز تیک را بزنید تا با ذخیره اتاق ساخته شود';
}

function typeMeta(type) {
    const map = {online: ['آنلاین', 'type-online'], offline: ['حضوری', 'type-inperson'], private: ['خصوصی', 'type-private']};
    return map[type] || map.offline;
}

function dayMeta(day) {
    return day === 'even'
        ? ['روزهای زوج', 'day-even']
        : ['روزهای فرد', 'day-odd'];
}

function teacherOptions(selected) {
    return `<option value="">انتخاب مدرس...</option>` + TEACHERS.map(t =>
        `<option value="${Number(t.id)}" ${Number(t.id)===Number(selected)?'selected':''}>${escapeHtml(t.full_name)}</option>`
    ).join('');
}

function interviewerOptions(selected, legacyName) {
    let html = `<option value="">مصاحبه‌گر...</option>` + INTERVIEWERS.map(p => {
        const role = p.role === 'supervisor' ? 'سوپروایزر' : 'مدرس';
        return `<option value="${Number(p.id)}" ${Number(p.id)===Number(selected)?'selected':''}>${escapeHtml(p.full_name)} · ${role}</option>`;
    }).join('');
    if (legacyName && !Number(selected)) {
        html += `<option value="0" data-legacy-name="${escapeHtml(legacyName)}" selected>${escapeHtml(legacyName)} (ثبت‌شده در EAS)</option>`;
    }
    return html;
}

function buildCard(data) {
    data = data || {};
    const type = String(data.class_type || 'offline');
    const day = String(data.schedule_type || 'odd');
    const tMeta = typeMeta(type);
    const dMeta = dayMeta(day);
    const isNew = !Number(data.id || 0);
    const sourceId = Number(data.source_id || 0);
    const sourceNote = sourceId
        ? `<div class="plc-source js-source">کپی از ${escapeHtml(PREVIOUS.title || 'ترم قبل')} — با ذخیره در ${escapeHtml(TERM_TITLE || 'این ترم')} ثبت می‌شود. اتاق الوکام ترم قبل کپی نمی‌شود.</div>`
        : '';
    const wrap = document.createElement('div');
    wrap.innerHTML = `
        <article class="plc-card ${isNew ? 'is-new' : ''}" data-classid="${Number(data.id || 0)}" data-teacher="${escapeHtml(data.teacher_name || '')}" data-source-id="${sourceId || ''}">
            <div class="plc-card-head">
                <div class="plc-level-wrap">
                    <div class="plc-label">لول</div>
                    <input class="plc-title js-title" list="levelList" placeholder="مثلاً FCE 2" value="${escapeHtml(data.title || '')}">
                </div>
                <div class="plc-badges">
                    <span class="type-badge ${tMeta[1]}">${tMeta[0]}</span>
                    <span class="day-badge ${dMeta[1]}">${dMeta[0]}</span>
                    <button type="button" class="plc-x js-delete-x" title="حذف کارت">×</button>
                </div>
            </div>
            ${sourceNote}
            <div>
                <div class="plc-label">زبان‌آموزان</div>
                <div class="plc-search-wrap">
                    <input class="plc-input js-student-q" type="search" placeholder="جستجو با نام یا شماره پرونده...">
                    <div class="plc-hits"></div>
                </div>
                <div class="plc-chips js-chips" style="margin-top:10px;"></div>
            </div>
            <div>
                <div class="plc-label">مدرس</div>
                <select class="plc-select js-teacher">${teacherOptions(data.teacher_id || '')}</select>
            </div>
            <div class="plc-grid">
                <div>
                    <div class="plc-label">نوع کلاس</div>
                    <select class="plc-select js-type">
                        <option value="offline" ${type==='offline'?'selected':''}>حضوری</option>
                        <option value="online" ${type==='online'?'selected':''}>آنلاین</option>
                        <option value="private" ${type==='private'?'selected':''}>خصوصی</option>
                    </select>
                </div>
                <div>
                    <div class="plc-label">روز برگزاری</div>
                    <select class="plc-select js-days">
                        <option value="odd" ${day!=='even'?'selected':''}>فرد (یکشنبه / سه‌شنبه / پنجشنبه)</option>
                        <option value="even" ${day==='even'?'selected':''}>زوج (شنبه / دوشنبه / چهارشنبه)</option>
                    </select>
                </div>
                <div>
                    <div class="plc-label">ساعت کلاس</div>
                    <input type="time" class="plc-input js-schedule" value="${escapeHtml(scheduleTime(data.schedule || ''))}" step="60" dir="ltr">
                </div>
            </div>
            <div class="js-alocom-wrap">
                <label class="plc-alocom-check js-alocom-private-wrap" ${type==='private' ? '' : 'style="display:none"'}>
                    <input type="checkbox" class="js-alocom-check" ${type==='online' || ((type==='private' || type==='offline') && data.online_link) ? 'checked' : ''}>
                    <span>ساخت اتاق الوکام<small>اگر این خصوصی آنلاین برگزار می‌شود. کلاس همچنان خصوصی می‌ماند و با کلاس‌های آنلاین قاطی نمی‌شود.</small></span>
                </label>
                <div class="plc-alocom-inperson js-alocom-inperson-wrap" ${type==='offline' && !data.online_link ? '' : 'style="display:none"'}>
                    <p class="plc-alocom-hint">برای روزهایی که کلاس حضوری تشکیل نمی‌شود. ورود این رویداد برای زبان‌آموز همیشه باز است و به تسویه شهریه وصل نیست.</p>
                    <button type="button" class="plc-alocom-btn js-alocom-create">ساخت رویداد آنلاین</button>
                </div>
                <div class="plc-alocom pending js-alocom" data-link="${escapeHtml(data.online_link || '')}">${(type==='online' || ((type==='private' || type==='offline') && data.online_link)) ? 'با ذخیره، اتاق الوکام ساخته می‌شود' : (type==='offline' ? 'دکمه را بزنید تا رویداد جایگزین حضوری ساخته شود' : 'در صورت نیاز تیک را بزنید تا با ذخیره اتاق ساخته شود')}</div>
            </div>
            <div class="interview-box">
                <div class="plc-label" style="margin:0;">وقت مصاحبه</div>
                <div class="plc-grid">
                    <div><input type="text" data-jdp readonly class="plc-input js-int-date" placeholder="تاریخ مصاحبه" value="${escapeHtml(data.interview_date || '')}"></div>
                    <div><input type="time" class="plc-input js-int-time" value="${escapeHtml(scheduleTime(data.interview_time || ''))}" step="60" dir="ltr"></div>
                </div>
                <div class="plc-label">مصاحبه‌گر</div>
                <select class="plc-select js-interviewer">${interviewerOptions(data.interviewer_id || '', data.interviewer_name || '')}</select>
            </div>
            <div class="plc-foot">
                <div class="plc-status js-status">${isNew ? (sourceId ? 'از ترم قبل آمده — هنوز در این ترم ذخیره نشده' : 'کارت تازه — هنوز ذخیره نشده') : ''}</div>
                <div class="plc-foot-actions">
                    <button type="button" class="plc-delete js-delete">${isNew ? 'حذف کارت' : 'حذف کلاس'}</button>
                    <button type="button" class="plc-save">ذخیره کارت</button>
                </div>
            </div>
        </article>`;
    const card = wrap.firstElementChild;
    const students = Array.isArray(data.students) ? data.students.filter(st => Number(st && st.id) > 0) : [];
    const inp = card.querySelector('.js-student-q');
    if (inp) {
        inp.setAttribute('data-students', JSON.stringify(students));
        inp._selected = students.map(st => ({
            id: Number(st.id),
            full_name: String(st.full_name || ''),
            student_code: String(st.student_code || '')
        }));
    }
    bindCard(card);
    refreshAlocomNote(card);
    return card;
}

function addNewCard() {
    if (!TERM_ID) {
        alert('ترم فعالی برای کلاس تازه نیست.');
        return;
    }
    const empty = document.getElementById('emptyState');
    if (empty) empty.remove();
    const card = buildCard({});
    document.getElementById('cards').prepend(card);
    if (window.jalaliDatepicker) {
        jalaliDatepicker.startWatch({ minDate: "attr", maxDate: "attr", time: false });
    }
    refreshToolbar();
    card.querySelector('.js-title').focus();
}

function loadedSourceIds() {
    const ids = new Set();
    document.querySelectorAll('#cards .plc-card[data-source-id]').forEach(card => {
        const id = Number(card.getAttribute('data-source-id') || 0);
        if (id > 0) ids.add(id);
    });
    return ids;
}

function loadPreviousClasses() {
    if (!TERM_ID) {
        alert('ترم فعالی انتخاب نشده.');
        return;
    }
    const rows = (PREVIOUS && Array.isArray(PREVIOUS.classes)) ? PREVIOUS.classes : [];
    if (!rows.length) {
        alert('برای ترم قبل کلاسی پیدا نشد.');
        return;
    }
    const prevTitle = PREVIOUS.title || 'ترم قبل';
    if (!confirm('کلاس‌های «' + prevTitle + '» به‌صورت کارت تازه روی «' + (TERM_TITLE || 'این ترم') + '» می‌آیند.\nتا ذخیره نکنید در ترم جدید ثبت نمی‌شوند و کلاس‌های ترم قبل دست نمی‌خورند.')) {
        return;
    }
    const empty = document.getElementById('emptyState');
    if (empty) empty.remove();
    const have = loadedSourceIds();
    const frag = document.createDocumentFragment();
    let added = 0;
    let skipped = 0;
    rows.forEach(cls => {
        const sid = Number(cls.source_id || 0);
        if (sid && have.has(sid)) {
            skipped++;
            return;
        }
        frag.appendChild(buildCard(cls));
        if (sid) have.add(sid);
        added++;
    });
    const box = document.getElementById('cards');
    box.insertBefore(frag, box.firstChild);
    if (window.jalaliDatepicker) {
        jalaliDatepicker.startWatch({ minDate: "attr", maxDate: "attr", time: false });
    }
    refreshToolbar();
    if (!added && skipped) {
        alert('این کلاس‌ها از قبل روی صفحه هستند.');
        return;
    }
    const note = skipped ? (' (' + skipped + ' تا از قبل روی صفحه بود)') : '';
    const first = box.querySelector('.plc-card.is-new .js-status');
    if (first) {
        first.textContent = added + ' کلاس از ' + prevTitle + ' لود شد' + note + '. تغییر بدهید و ذخیره کنید.';
        first.className = 'plc-status js-status ok';
    }
}

async function saveAllNewCards() {
    const cards = Array.from(document.querySelectorAll('#cards .plc-card.is-new'));
    if (!cards.length) {
        alert('کارت تازه‌ای برای ذخیره نیست.');
        return;
    }
    const btn = document.getElementById('saveAllNewBtn');
    if (btn) btn.disabled = true;
    let ok = 0;
    let fail = 0;
    for (const card of cards) {
        const saved = await saveCard(card);
        if (saved) ok++;
        else fail++;
    }
    if (btn) btn.disabled = false;
    refreshToolbar();
    alert(fail ? (ok + ' کلاس ذخیره شد، ' + fail + ' تا ذخیره نشد.') : (ok + ' کلاس برای این ترم ذخیره شد.'));
}

function refreshToolbar() {
    const n = document.querySelectorAll('#cards .plc-card').length;
    const fresh = document.querySelectorAll('#cards .plc-card.is-new').length;
    const count = document.getElementById('classCount');
    if (count) {
        count.textContent = n + ' کلاس در این ترم' + (fresh ? (' · ' + fresh + ' تا هنوز ذخیره نشده') : '');
    }
    const saveAll = document.getElementById('saveAllNewBtn');
    if (saveAll) {
        saveAll.style.display = fresh ? '' : 'none';
    }
}

function findCard(el) {
    if (!el || !el.closest) {
        return null;
    }
    if (el.classList && el.classList.contains('plc-card') && el.querySelector && el.querySelector('.js-title')) {
        return el;
    }
    const article = el.closest('article.plc-card');
    if (article) {
        return article;
    }
    const any = el.closest('.plc-card');
    if (any && any.querySelector && any.querySelector('.js-title')) {
        return any;
    }
    return null;
}

function ensureStatus(card) {
    if (!card) {
        return null;
    }
    let status = card.querySelector('.js-status');
    if (status) {
        return status;
    }
    status = document.createElement('div');
    status.className = 'plc-status js-status';
    const foot = card.querySelector('.plc-foot');
    if (foot) {
        foot.insertBefore(status, foot.firstChild);
    } else {
        card.appendChild(status);
    }
    return status;
}

function setCardStatus(card, kind, text) {
    const status = ensureStatus(card);
    if (!status) {
        return;
    }
    status.className = 'plc-status js-status' + (kind ? ' ' + kind : '');
    status.textContent = text || '';
}

function fieldValue(card, selector) {
    const el = card ? card.querySelector(selector) : null;
    return el ? String(el.value || '').trim() : '';
}

function payloadOf(card) {
    const selected = selectedOf(card);
    const teacherSel = card.querySelector('select.js-teacher');
    let teacherId = teacherSel ? parseInt(teacherSel.value, 10) || 0 : 0;
    if (!teacherId && teacherSel && teacherSel.selectedIndex > 0) {
        teacherId = parseInt(teacherSel.options[teacherSel.selectedIndex].value, 10) || 0;
    }
    const interviewerSel = card.querySelector('select.js-interviewer');
    const interviewerOpt = interviewerSel && interviewerSel.selectedIndex >= 0
        ? interviewerSel.options[interviewerSel.selectedIndex]
        : null;
    return {
        action: Number(card.getAttribute('data-classid') || 0) > 0 ? 'save' : 'create',
        class_id: Number(card.getAttribute('data-classid') || 0),
        term_id: TERM_ID,
        title: fieldValue(card, '.js-title'),
        teacher_id: teacherId,
        class_type: fieldValue(card, 'select.js-type') || 'offline',
        create_alocom: wantsAlocom(card),
        schedule_type: fieldValue(card, 'select.js-days') || 'odd',
        schedule: fieldValue(card, '.js-schedule'),
        student_ids: selected.map(st => Number(st.id)),
        interview_date: fieldValue(card, '.js-int-date'),
        interview_time: fieldValue(card, '.js-int-time'),
        interviewer_id: interviewerSel ? parseInt(interviewerSel.value, 10) || 0 : 0,
        interviewer_name: interviewerOpt ? (interviewerOpt.getAttribute('data-legacy-name') || '').trim() : ''
    };
}

function discardCard(card) {
    deleteCard(card);
}

async function deleteCard(card) {
    card = findCard(card);
    if (!card) {
        return;
    }
    const id = Number(card.getAttribute('data-classid') || 0);
    if (id <= 0) {
        card.remove();
        refreshToolbar();
        return;
    }
    if (!confirm('این کلاس از کلاس‌بندی حذف شود؟ رویداد الوکام آن هم پاک می‌شود.')) {
        return;
    }
    const btn = card.querySelector('.js-delete');
    if (btn) btn.disabled = true;
    setCardStatus(card, '', 'در حال حذف کلاس و رویداد الوکام...');
    const ac = new AbortController();
    const timer = setTimeout(() => ac.abort(), 40000);
    try {
        const res = await fetch('placement-save.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json', 'Accept': 'application/json'},
            credentials: 'same-origin',
            cache: 'no-store',
            signal: ac.signal,
            body: JSON.stringify({action: 'delete', class_id: id, term_id: TERM_ID})
        });
        const text = await res.text();
        let json;
        try { json = JSON.parse(text); } catch (e) { throw new Error(text.slice(0, 180) || 'پاسخ نامعتبر سرور'); }
        if (!json.success) throw new Error(json.message || 'حذف نشد');
        card.remove();
        refreshToolbar();
    } catch (err) {
        const msg = (err && err.name === 'AbortError') ? 'حذف طولانی شد. دوباره بزنید.' : (err.message || 'خطا در حذف');
        setCardStatus(card, 'err', msg);
        if (btn) btn.disabled = false;
    } finally {
        clearTimeout(timer);
    }
}

async function saveCard(card) {
    card = findCard(card);
    if (!card) {
        return false;
    }
    const btn = card.querySelector('.plc-save');
    let data;
    try {
        data = payloadOf(card);
    } catch (e) {
        setCardStatus(card, 'err', 'کارت ناقص است.');
        return false;
    }
    if (!data.title || !data.teacher_id) {
        setCardStatus(card, 'err', 'لول و مدرس را پر کنید.');
        return false;
    }
    if (btn) btn.disabled = true;
    const typeNow = fieldValue(card, 'select.js-type') || 'offline';
    setCardStatus(card, '', wantsAlocom(card)
        ? (typeNow === 'offline' ? 'در حال ساخت رویداد آنلاین جایگزین حضوری...' : 'در حال ذخیره و ساخت اتاق الوکام...')
        : 'در حال ذخیره...');
    const ac = new AbortController();
    const timer = setTimeout(() => ac.abort(), 25000);
    try {
        const res = await fetch('placement-save.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json', 'Accept': 'application/json'},
            credentials: 'same-origin',
            cache: 'no-store',
            signal: ac.signal,
            body: JSON.stringify(data)
        });
        const text = await res.text();
        let json;
        try { json = JSON.parse(text); } catch (e) { throw new Error(text.slice(0, 180) || 'پاسخ نامعتبر سرور'); }
        if (!json.success) throw new Error(json.message || 'ذخیره نشد');
        if (json.class && json.class.id) {
            card.setAttribute('data-classid', json.class.id);
            card.classList.remove('is-new');
            const del = card.querySelector('.js-delete');
            if (del) del.textContent = 'حذف کلاس';
            const source = card.querySelector('.js-source');
            if (source) source.remove();
        }
        const teacherSel = card.querySelector('select.js-teacher');
        card.setAttribute('data-teacher', teacherSel && teacherSel.selectedIndex >= 0 ? teacherSel.options[teacherSel.selectedIndex].text : '');
        const cls = json.class || {};
        refreshAlocomNote(card, {
            link: cls.online_link || '',
            warning: cls.alocom_ok === false ? (cls.alocom_warning || json.message) : ''
        });
        const kind = (cls.alocom_ok === false && wantsAlocom(card)) ? 'err' : 'ok';
        setCardStatus(card, kind, json.message);
        refreshToolbar();
        return true;
    } catch (err) {
        const msg = (err && err.name === 'AbortError') ? 'ذخیره طولانی شد. دوباره بزنید.' : (err.message || 'خطا در ذخیره');
        setCardStatus(card, 'err', msg);
        return false;
    } finally {
        clearTimeout(timer);
        if (btn) btn.disabled = false;
    }
}

function filterCards() {
    const q = (document.getElementById('classSearch').value || '').trim().toLowerCase();
    document.querySelectorAll('#cards .plc-card').forEach(card => {
        const titleEl = card.querySelector('.js-title');
        const title = titleEl ? (titleEl.value || '').toLowerCase() : '';
        const teacher = (card.getAttribute('data-teacher') || '').toLowerCase();
        card.style.display = (!q || title.includes(q) || teacher.includes(q)) ? 'flex' : 'none';
    });
}

document.querySelectorAll('.plc-card').forEach(bindCard);
refreshToolbar();
const cardsRoot = document.getElementById('cards');
if (cardsRoot) {
    cardsRoot.addEventListener('click', function (e) {
        const saveBtn = e.target.closest('.plc-save');
        if (saveBtn && cardsRoot.contains(saveBtn)) {
            e.preventDefault();
            saveCard(findCard(saveBtn));
            return;
        }
        const delBtn = e.target.closest('.js-delete, .js-delete-x');
        if (delBtn && cardsRoot.contains(delBtn)) {
            e.preventDefault();
            deleteCard(findCard(delBtn));
        }
    });
}
if (window.jalaliDatepicker) {
    jalaliDatepicker.startWatch({ minDate: "attr", maxDate: "attr", time: false });
}
</script>

<?php require_once 'layout-end.php'; ?>
