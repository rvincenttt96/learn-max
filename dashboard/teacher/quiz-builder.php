<?php
$title = 'Quiz Builder';
$current_page = 'quiz-builder.php';
require_once 'layout.php';
require_once '../../includes/db.php';
require_once '../../includes/term-plan.php';
require_once '../../includes/quiz-builder.php';

$teacher_id = (int)$_SESSION['user_id'];
quiz_builder_ensure_schema($pdo);
$classes = quiz_builder_teacher_classes($pdo, $teacher_id);
$selected_id = (int)($_GET['class_id'] ?? 0);
$selected = null;
foreach ($classes as $c) {
    if ((int)$c['id'] === $selected_id) {
        $selected = $c;
        break;
    }
}

$books = [];
$exams = [];
$templates = [];
$level_key = '';
if ($selected) {
    $level_key = (string)($selected['level_key'] ?? '');
    if ($level_key !== '') {
        $books = quiz_builder_books_for_level($pdo, $level_key);
        foreach (quiz_builder_templates() as $no => $_) {
            $templates[] = quiz_builder_template_for_level($no, $level_key);
        }
    }
    foreach (quiz_builder_class_exams($pdo, (int)$selected['id'], $teacher_id) as $row) {
        $exams[] = quiz_builder_public_exam($row);
    }
}

$books_pub = [];
foreach ($books as $book) {
    $books_pub[] = quiz_builder_public_book($book, $level_key);
}
$boot = [
    'ajax' => 'ajax-quiz-builder.php',
    'class_id' => $selected ? (int)$selected['id'] : 0,
    'level_key' => $level_key,
    'books' => $books_pub,
    'templates' => $templates,
    'exams' => $exams,
    'difficulties' => quiz_builder_difficulties(),
];
$need_course = $level_key !== '' ? quiz_builder_course_for_level($level_key) : null;
?>
<link href="../../assets/css/quiz-builder.css?v=qb9" rel="stylesheet">
<div class="qb-page" data-qb-teacher data-boot="<?= quiz_builder_h(json_encode($boot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>">
    <div class="qb-head">
        <div>
            <h1><span>Quiz</span> Builder</h1>
            <p>سوپروایزر PDF دوره را می‌گذارد. شما صفحه و قالب را می‌زنید، بعد «ارسال به کلاس». دانش‌آموز از Quizzes شرکت می‌کند. نمره کلاسی همان Quiz Marks است و اینجا جدا ثبت می‌شود.</p>
        </div>
        <?php if ($selected): ?>
            <a class="qb-btn qb-btn-ghost" href="quiz-builder.php">همه کلاس‌ها</a>
        <?php endif; ?>
    </div>

    <?php if (!$selected): ?>
        <?php if (!$classes): ?>
            <div class="qb-empty">
                <div class="qb-empty-icon">📝</div>
                <h5>کلاسی برای این ترم نیست</h5>
                <p>وقتی کلاسی به شما وصل باشد، ساخت کوییز از اینجا شروع می‌شود.</p>
            </div>
        <?php else: ?>
            <div class="qb-cards">
                <?php foreach ($classes as $class):
                    $badge = function_exists('term_plan_day_badge') ? term_plan_day_badge($class) : ['', ''];
                    $lk = (string)($class['level_key'] ?? '');
                ?>
                    <a class="qb-card" href="quiz-builder.php?class_id=<?= (int)$class['id'] ?>">
                        <div>
                            <h2><?= quiz_builder_h($class['title']) ?><?= function_exists('class_live_dot') ? class_live_dot($class) : '' ?></h2>
                            <div class="qb-card-meta">
                                <?php if ($lk !== ''): ?>
                                    <span class="qb-pill qb-pill-ready"><?= quiz_builder_h($lk) ?></span>
                                <?php else: ?>
                                    <span class="qb-pill qb-pill-failed">سطح نامشخص</span>
                                <?php endif; ?>
                                <?php if (!empty($badge[1])): ?>
                                    <span><?= quiz_builder_h($badge[1]) ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <span class="qb-card-go">ساخت کوییز ←</span>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    <?php else: ?>
        <div class="qb-class-bar">
            <div>
                <strong><?= quiz_builder_h($selected['title']) ?></strong>
                <?php if ($level_key !== ''): ?>
                    <span class="qb-pill qb-pill-ready"><?= quiz_builder_h($level_key) ?></span>
                <?php endif; ?>
            </div>
            <span>فونتیک <?= quiz_builder_has_phonetics($level_key) ? 'فعال است' : 'برای این سطح نیست' ?></span>
        </div>

        <?php if ($level_key === ''): ?>
            <div class="qb-empty">
                <h5>سطح کلاس از عنوان تشخیص داده نشد</h5>
                <p>عنوان کلاس باید شامل کد سطح باشد؛ مثلاً T7، WT12، PI3 یا CPI2. بعد از اصلاح عنوان در کلاس‌بندی، کتاب همان سطح اینجا می‌آید.</p>
            </div>
        <?php else:
            $ready_books = [];
            foreach ($books as $book) {
                if (($book['extract_status'] ?? '') === 'ready') {
                    $ready_books[] = $book;
                }
            }
        ?>
        <?php if (!$books): ?>
            <div class="qb-empty">
                <h5>برای <?= quiz_builder_h($level_key) ?> کتابی نیست</h5>
                <p>
                    <?php if ($need_course): ?>
                        سوپروایزر باید PDF «<?= quiz_builder_h($need_course['label']) ?>» را در Coursebooks بگذارد.
                        برای <?= quiz_builder_h($level_key) ?> همان فایل کافی است
                        (<?= quiz_builder_h(quiz_builder_unit_hint($need_course['key'], $level_key)) ?>).
                    <?php else: ?>
                        سوپروایزر باید PDF این سطح را در Coursebooks آپلود کند.
                    <?php endif; ?>
                </p>
            </div>
        <?php elseif (!$ready_books): ?>
            <div class="qb-empty">
                <h5>متن کتاب <?= quiz_builder_h($level_key) ?> هنوز آماده نیست</h5>
                <p>فایل در کتابخانه سوپروایزر هست ولی استخراج متن کامل نشده. سوپروایزر باید استخراج را دوباره بزند یا PDF متنی بگذارد.</p>
            </div>
        <?php else: ?>
            <form class="qb-builder" id="qbBuildForm">
                <div class="qb-builder-grid">
                    <label class="qb-field">
                        <span>کتاب سطح</span>
                        <select name="book_id" id="qbBook">
                            <?php foreach ($books as $book):
                                if (($book['extract_status'] ?? '') !== 'ready') {
                                    continue;
                                }
                            ?>
                                <option value="<?= (int)$book['id'] ?>" data-pages="<?= (int)$book['page_count'] ?>">
                                    <?php
                                    $pub = quiz_builder_public_book($book, $level_key);
                                    $label = $pub['course_label'] !== '' ? $pub['course_label'] : $pub['original_name'];
                                    if ($pub['unit_hint'] !== '') {
                                        $label .= ' · ' . $pub['unit_hint'];
                                    }
                                    echo quiz_builder_h($label . ' (' . (int)$book['page_count'] . ' صفحه)');
                                    ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label class="qb-field">
                        <span>از صفحه</span>
                        <input type="number" name="page_from" id="qbFrom" min="1" value="1">
                    </label>
                    <label class="qb-field">
                        <span>تا صفحه</span>
                        <input type="number" name="page_to" id="qbTo" min="1" value="8">
                    </label>
                    <label class="qb-field">
                        <span>سختی</span>
                        <select name="difficulty" id="qbDiff">
                            <?php foreach (quiz_builder_difficulties() as $k => $label): ?>
                                <option value="<?= quiz_builder_h($k) ?>"<?= $k === 'medium' ? ' selected' : '' ?>><?= quiz_builder_h($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                </div>
                <div class="qb-templates" id="qbTemplates">
                    <?php foreach ($templates as $tpl): ?>
                        <label class="qb-tpl">
                            <input type="radio" name="template_no" value="<?= (int)$tpl['no'] ?>"<?= (int)$tpl['no'] === 1 ? ' checked' : '' ?>>
                            <div>
                                <strong><?= quiz_builder_h($tpl['label']) ?></strong>
                                <em>S.<?= (int)$tpl['session'] ?> · نمره <?= (int)$tpl['mark'] ?> · <?= (int)$tpl['minutes'] ?> دقیقه · <?= (($tpl['kinds'] ?? '') === 'writing') ? 'تاپیک رایتینگ' : ((int)$tpl['question_count'] . ' سؤال') ?></em>
                                <p><?= quiz_builder_h($tpl['notes']) ?></p>
                            </div>
                        </label>
                    <?php endforeach; ?>
                </div>
                <button type="submit" class="qb-btn qb-btn-red" id="qbGenBtn">ساخت با هوش مصنوعی</button>
                <p class="qb-hint">صفحه را انتخاب کنید؛ برای Quiz 7 از همان صفحات یک تاپیک رایتینگ می‌سازد تا دانش‌آموز چند خط بنویسد. نمره کوییز بعد از تحویل در Quiz Marks می‌آید.</p>
            </form>
        <?php endif; ?>
        <?php endif; ?>

        <div class="qb-alert" id="qbAlert" hidden></div>
        <div class="qb-progress" id="qbProgress" hidden>
            <div class="qb-progress-bar"></div>
            <span id="qbProgressText">در حال ساخت سؤال‌ها از صفحه‌های کتاب…</span>
        </div>

        <section class="qb-exams" id="qbExams">
            <h2>آزمون‌های این کلاس</h2>
            <div id="qbExamList"></div>
            <article class="qb-exam-view" id="qbExamView" hidden></article>
        </section>
    <?php endif; ?>
</div>
<script src="../../assets/js/quiz-builder.js?v=qb4"></script>
<?php require_once 'layout-end.php'; ?>
