<?php
session_start();
require_once '../../includes/auth.php';
require_once '../../includes/db.php';
require_once '../../includes/terms.php';
require_once '../../includes/quizzes.php';
require_role('teacher');

$title = 'Quiz Marks';
require_once 'layout.php';

quizzes_ensure_schema($pdo);
$quiz_config = quizzes_config();

$stmt = $pdo->prepare("
    SELECT
        c.id,
        c.title,
        c.schedule,
        c.schedule_type,
        c.class_type,
        COUNT(cs.user_id) as student_count
    FROM classes c
    LEFT JOIN class_students cs ON c.id = cs.class_id
    LEFT JOIN terms t ON c.term_id = t.id
    WHERE c.teacher_id = ? 
    AND c.is_deleted = 0
    AND (" . term_sql_teaching('t') . " OR c.term_id IS NULL)
    AND IFNULL(c.is_archived, 0) = 0
    GROUP BY c.id, c.title, c.schedule, c.schedule_type, c.class_type
    ORDER BY c.schedule DESC
");
$stmt->execute([$_SESSION['user_id']]);
$classes = $stmt->fetchAll();

$students = [];
$quiz_data = [];
$selected_class_id = null;
$class_title = '';

if (!empty($_GET['class_id'])) {
    $selected_class_id = (int)$_GET['class_id'];
    quizzes_sync_builder_scores_for_class($pdo, $selected_class_id);

    $stmt = $pdo->prepare("SELECT title FROM classes WHERE id = ?");
    $stmt->execute([$selected_class_id]);
    $class_title = $stmt->fetchColumn();

    $stmt = $pdo->prepare("
        SELECT u.id, u.full_name, u.username AS student_code
        FROM users u
        JOIN class_students cs ON u.id = cs.user_id
        WHERE cs.class_id = ? AND u.role='student' AND u.is_deleted=0
        ORDER BY u.full_name
    ");
    $stmt->execute([$selected_class_id]);
    $students = $stmt->fetchAll();

    $stmt = $pdo->prepare("
        SELECT user_id, " . quizzes_sql_columns('') . "
        FROM student_quizzes
        WHERE class_id = ?
    ");
    $stmt->execute([$selected_class_id]);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $vals = [];
        foreach ($quiz_config as $num => $q) {
            $vals[$num] = $row['quiz_' . $num] ?? null;
        }
        $quiz_data[$row['user_id']] = $vals;
    }
}
?>

<link href="../../assets/css/dashboard-style.css?v=<?= time() ?>" rel="stylesheet">
<link href="../../assets/css/teacher/quiz-scores.css?v=<?= time() ?>" rel="stylesheet">

<style>
.quiz-header-cell {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white !important;
    position: relative;
}
.quiz-header-cell.quiz-bonus-cell {
    background: linear-gradient(135deg, #c9a227 0%, #8a5a12 100%);
}
.quiz-max-score {
    font-size: 0.75rem;
    display: block;
    opacity: 0.9;
}
.quiz-total-cell {
    font-weight: 700;
    text-align: center;
    vertical-align: middle;
    min-width: 4.5rem;
    white-space: nowrap;
}
.class-info-badge {
    font-size: 0.85rem;
    padding: 3px 8px;
    border-radius: 4px;
    margin: 2px;
}
</style>

<?php if (!empty($_SESSION['success_message'])): ?>
    <div class="alert alert-success"><?= $_SESSION['success_message']; unset($_SESSION['success_message']); ?></div>
<?php endif; ?>

<?php if (!empty($_SESSION['error_message'])): ?>
    <div class="alert alert-danger"><?= $_SESSION['error_message']; unset($_SESSION['error_message']); ?></div>
<?php endif; ?>

<?php if (empty($selected_class_id)): ?>
    <div class="card classes-selection-card">
        <div class="card-header text-white">
            <h5 class="mb-0">Quiz marks</h5>
        </div>
        <div class="card-body">
            <?php if (empty($classes)): ?>
                <div class="alert alert-info text-white">شما هنوز کلاسی ندارید.</div>
            <?php else: ?>
                <div class="list-group ">
                    <?php foreach ($classes as $class): ?>
                        <a href="?class_id=<?= $class['id'] ?>" class="list-group-item list-group-item-action">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <strong><?= htmlspecialchars($class['title']) ?><?= class_live_dot($class) ?></strong><br>
                                    <small class="text-muted">
                                        <?= htmlspecialchars($class['schedule'] ?? '-') ?> | 
                                        <?= $class['student_count'] ?> Students
                                    </small>
                                </div>
                                <div>
                                    <?php if ($class['class_type'] == 'online'): ?>
                                        <span class="class-info-badge bg-success">online</span>
                                    <?php else: ?>
                                        <span class="class-info-badge bg-secondary">Inperson</span>
                                    <?php endif; ?>
                                    
                                    <?php if ($class['schedule_type'] == 'odd'): ?>
                                        <span class="class-info-badge bg-info">Sat/Mon/Wed</span>
                                    <?php else: ?>
                                        <span class="class-info-badge bg-warning">Sun/Tue/Thu</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

<?php else: ?>
    <div class="card quiz-card">
        <div class="card-header text-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0">نمرات کلاس: <?= htmlspecialchars($class_title) ?></h5>
            <a href="quiz-scores.php" class="btn btn-sm btn-light">Back to List</a>
        </div>
        <div class="card-body">
            <?php if (empty($students)): ?>
                <div class="alert alert-warning">دانش‌آموزی در این کلاس وجود ندارد.</div>
            <?php else: ?>
                <form method="POST" action="save-quizzes.php">
                    <input type="hidden" name="class_id" value="<?= $selected_class_id ?>">
                    <div class="table-responsive">
                        <table class="table table-bordered quiz-table">
                            <thead>
                                <tr>
                                    <th>Learners name</th>
                                    <th>id</th>
                                    <?php foreach ($quiz_config as $num => $q): ?>
                                        <th class="quiz-header-cell<?= $num === 10 ? ' quiz-bonus-cell' : '' ?>">
                                            <?= htmlspecialchars($q['title']) ?>
                                            <span class="quiz-max-score">(Max: <?= $q['max'] ?>)</span>
                                        </th>
                                    <?php endforeach; ?>
                                    <th>جمع<br><span class="quiz-max-score">(Max: 100)</span></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($students as $student):
                                    $row_vals = $quiz_data[$student['id']] ?? [];
                                    $row_total = quizzes_grade_totals([
                                        'quiz_1' => $row_vals[1] ?? null,
                                        'quiz_2' => $row_vals[2] ?? null,
                                        'quiz_3' => $row_vals[3] ?? null,
                                        'quiz_4' => $row_vals[4] ?? null,
                                        'quiz_5' => $row_vals[5] ?? null,
                                        'quiz_6' => $row_vals[6] ?? null,
                                        'quiz_7' => $row_vals[7] ?? null,
                                        'quiz_8' => $row_vals[8] ?? null,
                                        'quiz_9' => $row_vals[9] ?? null,
                                        'quiz_10' => $row_vals[10] ?? null,
                                    ]);
                                    $shown_total = $row_total['has_quiz_scores'] ? (int)$row_total['class_total'] : '';
                                ?>
                                    <tr>
                                        <td><?= htmlspecialchars($student['full_name']) ?></td>
                                        <td><?= htmlspecialchars($student['student_code']) ?></td>
                                        <?php foreach ($quiz_config as $num => $q):
                                            $val = $row_vals[$num] ?? '';
                                            $val = ($val !== null && $val !== '') ? number_format($val, 0) : '';
                                        ?>
                                            <td>
                                                <input type="number"
                                                       name="quizzes[<?= $student['id'] ?>][<?= $num ?>]"
                                                       value="<?= $val ?>"
                                                       min="0"
                                                       max="<?= $q['max'] ?>"
                                                       step="1"
                                                       class="form-control quiz-input"
                                                       data-student="<?= (int)$student['id'] ?>"
                                                       placeholder="0-<?= $q['max'] ?>"
                                                       title="<?= $num === 10 ? 'نمره اضافه Bonus بین 0 تا 10' : ('نمره باید بین 0 تا ' . $q['max'] . ' باشد') ?>">
                                            </td>
                                        <?php endforeach; ?>
                                        <td class="quiz-total-cell" data-student="<?= (int)$student['id'] ?>"><?= $shown_total === '' ? '—' : $shown_total ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <p class="text-muted small mt-2 mb-0">نمره آزمون Quiz Builder برای کوییز ۱ تا ۷ و دو R/W همین‌جا می‌آید. حضور، فعالیت کلاسی و Bonus را دستی بزنید. Bonus حداکثر ۱۰ نمره اضافه است. اگر جمع از ۱۰۰ بیشتر شود، در کارنامه همان ۱۰۰ ثبت می‌شود.</p>
                    <div class="mt-3">
                        <button type="submit" class="btn btn-primary">Confirm</button>
                        <a href="quiz-scores.php" class="btn btn-secondary">Back</a>
                    </div>
                </form>
                <script>
                (function () {
                    function recap(studentId) {
                        var sum = 0;
                        var any = false;
                        document.querySelectorAll('.quiz-input[data-student="' + studentId + '"]').forEach(function (inp) {
                            var raw = (inp.value || '').trim();
                            if (raw === '') return;
                            any = true;
                            var n = parseInt(raw, 10);
                            if (isNaN(n)) n = 0;
                            var max = parseInt(inp.getAttribute('max') || '0', 10);
                            if (n < 0) n = 0;
                            if (max && n > max) n = max;
                            sum += n;
                        });
                        var cell = document.querySelector('.quiz-total-cell[data-student="' + studentId + '"]');
                        if (cell) cell.textContent = any ? String(Math.min(100, sum)) : '—';
                    }
                    document.querySelectorAll('.quiz-input').forEach(function (inp) {
                        inp.addEventListener('input', function () { recap(inp.getAttribute('data-student')); });
                        inp.addEventListener('change', function () { recap(inp.getAttribute('data-student')); });
                    });
                })();
                </script>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<?php require_once 'layout-end.php'; ?>