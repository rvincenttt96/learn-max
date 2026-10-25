<?php
$title = 'Assignment';
$current_page = 'assignment.php';
require_once 'layout.php';
require_once '../../includes/db.php';
require_once '../../includes/assignments.php';
require_role('student');

$student_id = (int)$_SESSION['user_id'];
$id = (int)($_GET['id'] ?? 0);
$item = null;
$page_error = '';
try {
    assignments_ensure_schema($pdo);
    if ($id > 0) {
        $row = assignments_find_student($pdo, $id, $student_id);
        if ($row) {
            $item = assignments_public_student($row);
        }
    }
} catch (Exception $e) {
    error_log('student assignment.php: ' . $e->getMessage());
    $page_error = 'الان این تکلیف در دسترس نیست. صفحه را تازه کنید.';
}

$limit = assignments_upload_limit();
$boot = [
    'ajax' => 'ajax-assignments.php',
    'id' => $item ? (int)$item['id'] : 0,
    'max_bytes' => $limit,
    'max_label' => assignments_bytes_label($limit),
];
$pill = $item ? ($item['status_key'] === 'submitted' ? 'ready' : ($item['status_key'] === 'late' ? 'draft' : ($item['status_key'] === 'overdue' ? 'failed' : 'pending'))) : '';
?>
<link href="../../assets/css/quiz-builder.css?v=qb7" rel="stylesheet">
<link href="../../assets/css/assignments.css?v=asg1" rel="stylesheet">
<div class="qb-page asg-page asg-student-detail" data-asg-student data-boot="<?= assignments_h(json_encode($boot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>">
    <div class="qb-head">
        <div>
            <h1><span>Assignment</span></h1>
            <p>فایل پاسخ را همین‌جا بفرستید. اگر قبلاً آپلود کرده‌اید، فایل جدید جایگزین می‌شود.</p>
        </div>
        <a class="qb-btn qb-btn-ghost" href="assignments.php">همه تکلیف‌ها</a>
    </div>

    <?php if ($page_error): ?>
        <div class="qb-empty">
            <div class="qb-empty-icon">⏳</div>
            <h5>تکلیف لود نشد</h5>
            <p><?= assignments_h($page_error) ?></p>
        </div>
    <?php elseif (!$item): ?>
        <div class="qb-empty">
            <div class="qb-empty-icon">📎</div>
            <h5>این تکلیف برای شما نیست</h5>
            <p>یا حذف شده، یا متعلق به کلاس دیگری است.</p>
        </div>
    <?php else: ?>
        <div class="qb-class-bar">
            <div>
                <strong><?= assignments_h($item['class_title']) ?></strong>
                <span class="qb-pill qb-pill-<?= assignments_h($pill) ?>"><?= assignments_h($item['status_label']) ?></span>
            </div>
            <span>جلسه <?= (int)$item['session_no'] ?></span>
        </div>

        <article class="qb-exam-view asg-prompt">
            <h2><?= assignments_h($item['title']) ?></h2>
            <div class="qb-card-meta">
                <?php if ($item['due_label'] !== ''): ?>
                    <span>مهلت <?= assignments_h($item['due_label']) ?></span>
                <?php else: ?>
                    <span>بدون مهلت مشخص</span>
                <?php endif; ?>
            </div>
            <?php if (trim($item['body']) !== ''): ?>
                <div class="asg-body"><?= nl2br(assignments_h($item['body'])) ?></div>
            <?php else: ?>
                <p class="qb-hint">معلم شرح جداگانه‌ای ننوشته؛ اگر پیوست دارد همان را ببینید.</p>
            <?php endif; ?>
            <?php if ($item['has_teacher_file']): ?>
                <p>
                    <a class="qb-btn" href="assignment-file.php?kind=teacher&amp;id=<?= (int)$item['id'] ?>">
                        دانلود پیوست معلم · <?= assignments_h($item['teacher_file_name']) ?>
                        (<?= assignments_h($item['teacher_file_size_label']) ?>)
                    </a>
                </p>
            <?php endif; ?>
        </article>

        <section class="qb-builder asg-upload" id="asgUploadBox">
            <h2>تحویل شما</h2>
            <div id="asgCurrent" class="asg-current" <?= $item['submitted'] ? '' : 'hidden' ?>>
                <?php if ($item['submitted']): ?>
                    <p>
                        فایل فعلی:
                        <a href="assignment-file.php?kind=submission&amp;id=<?= (int)$item['id'] ?>">
                            <?= assignments_h($item['file_name']) ?>
                        </a>
                        <span><?= assignments_h($item['file_size_label']) ?></span>
                        <span><?= assignments_h($item['submitted_label']) ?></span>
                    </p>
                <?php endif; ?>
            </div>
            <form id="asgUploadForm">
                <label class="qb-field">
                    <span>فایل پاسخ (PDF، تصویر، Word یا ZIP — حداکثر <?= assignments_h(assignments_bytes_label($limit)) ?>)</span>
                    <input type="file" name="file" id="asgFile" accept=".pdf,.jpg,.jpeg,.png,.webp,.gif,.doc,.docx,.zip" required>
                </label>
                <button type="submit" class="qb-btn qb-btn-red" id="asgUploadBtn"><?= $item['submitted'] ? 'جایگزینی فایل' : 'آپلود و تحویل' ?></button>
            </form>
            <div class="qb-alert" id="asgAlert" hidden></div>
            <div class="qb-progress" id="asgProgress" hidden>
                <div class="qb-progress-bar"></div>
                <span id="asgProgressText">در حال آپلود…</span>
            </div>
        </section>
    <?php endif; ?>
</div>
<script src="../../assets/js/student-assignments.js?v=asg1"></script>
<?php require_once 'layout-end.php'; ?>
