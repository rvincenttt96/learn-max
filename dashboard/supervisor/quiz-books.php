<?php
$title = 'Coursebooks';
$current_page = 'quiz-books.php';
require_once 'layout.php';
require_once '../../includes/db.php';
require_once '../../includes/quiz-builder.php';

quiz_builder_ensure_schema($pdo);
quiz_builder_storage_dir();
try {
    quiz_builder_ingest_sidecars($pdo, (int)($_SESSION['user_id'] ?? 0));
} catch (Throwable $e) {
    error_log('quiz sidecar ingest: ' . $e->getMessage());
}

$courses = quiz_builder_courses();
$groups = [];
foreach ($courses as $course) {
    $groups[$course['group']][] = $course;
}
$books = quiz_builder_all_books($pdo);
$by_course = [];
$legacy = [];
foreach ($books as $book) {
    $ck = (string)($book['course_key'] ?? '');
    if ($ck !== '' && isset($courses[$ck])) {
        $by_course[$ck][] = $book;
    } else {
        $legacy[] = $book;
    }
}
$limit_mb = max(1, (int)round(quiz_builder_upload_limit() / 1048576));
$pending_pdfs = quiz_builder_unregistered_pdfs($pdo);

function quiz_books_size($bytes) {
    $bytes = (int)$bytes;
    if ($bytes < 1024) {
        return $bytes . ' B';
    }
    if ($bytes < 1048576) {
        return round($bytes / 1024) . ' KB';
    }
    return round($bytes / 1048576, 1) . ' MB';
}
?>
<link href="../../assets/css/quiz-builder.css?v=qb7" rel="stylesheet">
<div class="qb-page" data-qb-root data-ajax="ajax-quiz-books.php" data-max-mb="<?= (int)$limit_mb ?>" data-courses="<?= quiz_builder_h(json_encode($courses, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>">
    <div class="qb-head">
        <div>
            <h1><span>Course</span>books</h1>
            <p>سه PDF برای Wider World ۲ تا ۴ (WT5 تا WT16) و شش PDF برای Connectivity Foundation تا ۵. هر دو یونیت یک ترم است؛ لازم نیست هر سطح جدا آپلود شود.</p>
        </div>
        <div class="qb-head-meta">
            <span><?= (int)count($books) ?> فایل</span>
            <span>حد <?= (int)$limit_mb ?> MB</span>
        </div>
    </div>

    <form class="qb-upload" id="qbUploadForm">
        <div class="qb-upload-grid">
            <label class="qb-field">
                <span>کتاب دوره</span>
                <select name="course_key" id="qbCourse" required>
                    <option value="">انتخاب کتاب…</option>
                    <?php foreach ($groups as $group => $items): ?>
                        <optgroup label="<?= quiz_builder_h($group) ?>">
                            <?php foreach ($items as $course): ?>
                                <option value="<?= quiz_builder_h($course['key']) ?>">
                                    <?= quiz_builder_h($course['label']) ?> · <?= quiz_builder_h(quiz_builder_course_levels_label($course)) ?>
                                </option>
                            <?php endforeach; ?>
                        </optgroup>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="qb-field qb-file-field">
                <span>فایل PDF</span>
                <input type="file" name="book" id="qbFile" accept="application/pdf,.pdf" required>
            </label>
            <button type="submit" class="qb-btn qb-btn-red" id="qbUploadBtn">آپلود کتاب</button>
        </div>
        <p class="qb-hint" id="qbCourseHint">فرم اینجا حداکثر <?= (int)$limit_mb ?> مگ است. PDFهای ۱۱۰ مگی را از فایل‌منیجر DirectAdmin بگذارید در <code>public_html/uploads/quiz-books</code> بعد از همین صفحه «ثبت روی سرور» را بزنید.</p>
        <div class="qb-progress" id="qbProgress" hidden>
            <div class="qb-progress-bar"></div>
            <span id="qbProgressText">در حال آپلود فایل…</span>
        </div>
    </form>

    <?php if ($pending_pdfs): ?>
        <section class="qb-upload" id="qbServerFiles">
            <h2>PDF روی سرور، هنوز ثبت نشده</h2>
            <p class="qb-hint">برای هر فایل «ثبت روی سرور» را بزنید. اسم فایل دوره را تشخیص می‌دهد؛ لازم نیست از لیست بالا انتخاب کنید. استخراج بعد از رفرش، تکه‌تکه ادامه می‌شود — تب را باز بگذارید.</p>
            <div class="qb-book-list">
                <?php foreach ($pending_pdfs as $pdf):
                    $guess = quiz_builder_course($pdf['guess']);
                ?>
                    <article class="qb-book" data-filename="<?= quiz_builder_h($pdf['filename']) ?>" data-guess="<?= quiz_builder_h($pdf['guess']) ?>">
                        <div class="qb-book-main">
                            <h3><?= quiz_builder_h($pdf['filename']) ?></h3>
                            <div class="qb-book-meta">
                                <span><?= quiz_builder_h(quiz_books_size($pdf['size'])) ?></span>
                                <?php if ($guess): ?>
                                    <span class="qb-pill qb-pill-ready"><?= quiz_builder_h($guess['label']) ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="qb-book-actions">
                            <button type="button" class="qb-btn qb-btn-red" data-act="register">ثبت روی سرور</button>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <div class="qb-alert" id="qbAlert" hidden></div>

    <?php if (!$books): ?>
        <div class="qb-empty">
            <div class="qb-empty-icon">📚</div>
            <h5>هنوز کتابی در کتابخانه نیست</h5>
            <p>اول Wider World 2 را بگذارید؛ برای WT5 لازم نیست چهار بار آپلود شود. بعد WW3، WW4 و شش جلد Connectivity.</p>
        </div>
    <?php else: ?>
        <?php foreach ($groups as $group => $items):
            $shown = false;
            foreach ($items as $course) {
                if (!empty($by_course[$course['key']])) {
                    $shown = true;
                    break;
                }
            }
            if (!$shown) {
                continue;
            }
        ?>
            <section class="qb-group">
                <h2><?= quiz_builder_h($group) ?></h2>
                <?php foreach ($items as $course):
                    if (empty($by_course[$course['key']])) {
                        continue;
                    }
                ?>
                    <div class="qb-level">
                        <div class="qb-level-name">
                            <?= quiz_builder_h($course['label']) ?>
                            <span class="qb-pill qb-pill-ready"><?= quiz_builder_h(quiz_builder_course_levels_label($course)) ?></span>
                        </div>
                        <p class="qb-hint"><?= quiz_builder_h($course['note']) ?></p>
                        <div class="qb-book-list">
                            <?php foreach ($by_course[$course['key']] as $book):
                                echo quiz_books_card_html($book);
                            endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </section>
        <?php endforeach; ?>
        <?php if ($legacy): ?>
            <section class="qb-group">
                <h2>سایر</h2>
                <div class="qb-book-list">
                    <?php foreach ($legacy as $book) {
                        echo quiz_books_card_html($book);
                    } ?>
                </div>
            </section>
        <?php endif; ?>
    <?php endif; ?>
</div>
<?php
function quiz_books_card_html(array $book) {
    $pub = quiz_builder_public_book($book);
    $st = $pub['extract_status'];
    ob_start();
    ?>
    <article class="qb-book" data-book-id="<?= (int)$book['id'] ?>" data-status="<?= quiz_builder_h($st) ?>">
        <div class="qb-book-main">
            <h3><?= quiz_builder_h($pub['original_name']) ?></h3>
            <div class="qb-book-meta">
                <span class="qb-pill qb-pill-<?= quiz_builder_h($st) ?>"><?= quiz_builder_h($pub['status_label']) ?></span>
                <?php if ($pub['levels_label'] !== ''): ?>
                    <span><?= quiz_builder_h($pub['levels_label']) ?></span>
                <?php endif; ?>
                <span><?= (int)$pub['extract_cursor'] ?> / <?= (int)$pub['page_count'] ?> صفحه</span>
                <span><?= quiz_builder_h(quiz_books_size($pub['file_size'])) ?></span>
                <?php if ((int)$pub['exam_count'] > 0): ?>
                    <span><?= (int)$pub['exam_count'] ?> آزمون معلم</span>
                <?php endif; ?>
            </div>
            <?php if ($pub['extract_error'] !== ''): ?>
                <p class="qb-book-err"><?= quiz_builder_h($pub['extract_error']) ?></p>
            <?php endif; ?>
            <div class="qb-preview" hidden></div>
        </div>
        <div class="qb-book-actions">
            <button type="button" class="qb-btn qb-btn-ghost" data-act="preview">پیش‌نمایش متن</button>
            <?php if ($st !== 'ready'): ?>
                <button type="button" class="qb-btn qb-btn-ghost" data-act="extract">ادامه استخراج</button>
            <?php endif; ?>
            <button type="button" class="qb-btn qb-btn-danger" data-act="delete">حذف</button>
        </div>
    </article>
    <?php
    return ob_get_clean();
}
?>
<script src="../../assets/js/quiz-books.js?v=qb5"></script>
<?php require_once 'layout-end.php'; ?>
