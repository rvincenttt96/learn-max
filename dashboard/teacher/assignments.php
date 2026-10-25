<?php
$title = 'Assignment';
$current_page = 'assignments.php';
require_once 'layout.php';
require_once '../../includes/db.php';
require_once '../../includes/term-plan.php';
require_once '../../includes/assignments.php';

$teacher_id = (int)$_SESSION['user_id'];
$schema_error = '';
try {
    assignments_ensure_schema($pdo);
} catch (Exception $e) {
    $schema_error = 'ساخت جدول تکلیف الان ممکن نشد. صفحه را تازه کنید.';
}

$classes = [];
if ($schema_error === '') {
    $classes = assignments_teacher_classes($pdo, $teacher_id);
}
$selected_id = (int)($_GET['class_id'] ?? 0);
$selected = null;
foreach ($classes as $c) {
    if ((int)$c['id'] === $selected_id) {
        $selected = $c;
        break;
    }
}

$rows = [];
$student_count = 0;
if ($selected && $schema_error === '') {
    $rows = assignments_class_rows($pdo, (int)$selected['id'], $teacher_id);
    $student_count = assignments_class_student_count($pdo, (int)$selected['id']);
}
$pub = [];
foreach ($rows as $row) {
    $pub[] = assignments_public_teacher($row);
}
$limit = assignments_upload_limit();
$boot = [
    'ajax' => 'ajax-assignments.php',
    'file' => 'assignment-file.php',
    'class_id' => $selected ? (int)$selected['id'] : 0,
    'session_max' => assignments_session_max(),
    'student_count' => $student_count,
    'assignments' => $pub,
    'max_bytes' => $limit,
    'max_label' => assignments_bytes_label($limit),
    'accept' => '.pdf,.jpg,.jpeg,.png,.webp,.gif,.doc,.docx,.zip',
];
?>
<link href="../../assets/css/quiz-builder.css?v=qb7" rel="stylesheet">
<link href="../../assets/css/assignments.css?v=asg1" rel="stylesheet">
<div class="qb-page asg-page" data-asg-teacher data-boot="<?= assignments_h(json_encode($boot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>">
    <div class="qb-head">
        <div>
            <h1><span>Assignment</span></h1>
            <p>برای هر جلسهٔ کلاس تکلیف بگذارید. دانش‌آموز از Assignments می‌بیند و فایل آپلود می‌کند. تیک کامل/ناقص حضورغیاب همان Homework Status است و اینجا عوض نمی‌شود.</p>
        </div>
        <?php if ($selected): ?>
            <a class="qb-btn qb-btn-ghost" href="assignments.php">همه کلاس‌ها</a>
        <?php endif; ?>
    </div>

    <?php if ($schema_error): ?>
        <div class="qb-empty">
            <div class="qb-empty-icon">⏳</div>
            <h5>جدول تکلیف آماده نشد</h5>
            <p><?= assignments_h($schema_error) ?></p>
        </div>
    <?php elseif (!$selected): ?>
        <?php if (!$classes): ?>
            <div class="qb-empty">
                <div class="qb-empty-icon">📝</div>
                <h5>کلاسی برای این ترم نیست</h5>
                <p>وقتی کلاسی به شما وصل باشد، تعریف تکلیف از اینجا شروع می‌شود.</p>
            </div>
        <?php else: ?>
            <div class="qb-cards">
                <?php foreach ($classes as $class):
                    $badge = function_exists('term_plan_day_badge') ? term_plan_day_badge($class) : ['', ''];
                ?>
                    <a class="qb-card" href="assignments.php?class_id=<?= (int)$class['id'] ?>">
                        <div>
                            <h2><?= assignments_h($class['title']) ?><?= function_exists('class_live_dot') ? class_live_dot($class) : '' ?></h2>
                            <div class="qb-card-meta">
                                <?php if (!empty($badge[1])): ?>
                                    <span><?= assignments_h($badge[1]) ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <span class="qb-card-go">تعریف تکلیف ←</span>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    <?php else: ?>
        <div class="qb-class-bar">
            <div>
                <strong><?= assignments_h($selected['title']) ?></strong>
                <span class="qb-pill qb-pill-ready"><?= (int)$student_count ?> دانش‌آموز</span>
            </div>
            <span>جلسه ۱ تا <?= (int)assignments_session_max() ?></span>
        </div>

        <form class="qb-builder asg-form" id="asgForm">
            <input type="hidden" name="id" id="asgId" value="0">
            <div class="asg-form-grid">
                <label class="qb-field">
                    <span>جلسه</span>
                    <select name="session_no" id="asgSession">
                        <?php for ($i = 1; $i <= assignments_session_max(); $i++): ?>
                            <option value="<?= $i ?>">جلسه <?= $i ?></option>
                        <?php endfor; ?>
                    </select>
                </label>
                <label class="qb-field">
                    <span>عنوان</span>
                    <input type="text" name="title" id="asgTitle" maxlength="180" placeholder="مثلاً Writing page 42" required>
                </label>
                <label class="qb-field">
                    <span>مهلت (اختیاری)</span>
                    <input type="date" name="due_at" id="asgDue">
                </label>
                <label class="qb-field">
                    <span>فایل پیوست معلم</span>
                    <input type="file" name="file" id="asgFile" accept=".pdf,.jpg,.jpeg,.png,.webp,.gif,.doc,.docx,.zip">
                </label>
            </div>
            <label class="qb-field asg-body-field">
                <span>شرح تکلیف</span>
                <textarea name="body" id="asgBody" rows="5" placeholder="آنچه دانش‌آموز باید انجام دهد…"></textarea>
            </label>
            <p class="qb-hint" id="asgFileHint"></p>
            <div class="asg-form-actions">
                <button type="submit" class="qb-btn qb-btn-red" id="asgSaveBtn">انتشار برای کلاس</button>
                <button type="button" class="qb-btn qb-btn-ghost" id="asgResetBtn" hidden>انصراف از ویرایش</button>
                <button type="button" class="qb-btn qb-btn-danger" id="asgRemoveFileBtn" hidden>حذف فایل پیوست</button>
            </div>
        </form>

        <div class="qb-alert" id="asgAlert" hidden></div>
        <div class="qb-progress" id="asgProgress" hidden>
            <div class="qb-progress-bar"></div>
            <span id="asgProgressText">در حال ذخیره…</span>
        </div>

        <div class="asg-sessions" id="asgSessions"></div>
        <section class="qb-exams asg-list" id="asgListWrap">
            <h2>تکلیف‌های این کلاس</h2>
            <div id="asgList"></div>
        </section>
        <article class="qb-exam-view asg-detail" id="asgDetail" hidden></article>
    <?php endif; ?>
</div>
<script src="../../assets/js/teacher-assignments.js?v=asg1"></script>
<?php require_once 'layout-end.php'; ?>
