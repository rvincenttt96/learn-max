<?php
if (isset($_GET['ajax']) ||
    (!empty($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false)) {

    require_once '../../includes/db.php';
    require_once '../../includes/auth.php';
    require_once '../../includes/quizzes.php';
    require_role('supervisor');
    header('Content-Type: application/json');
    quizzes_ensure_schema($pdo);

    if (!empty($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false) {
        $input = json_decode(file_get_contents('php://input'), true);
    } else {
        $input = $_POST;
    }

    $action = $input['action'] ?? '';

    try {
        $class_id = (int)($input['class_id'] ?? 0);
        if (!$class_id) {
            throw new Exception('شناسه کلاس نامعتبر است.');
        }

        $stmt = $pdo->prepare("SELECT c.id FROM classes c WHERE c.id = ?");
        $stmt->execute([$class_id]);
        if (!$stmt->fetch()) {
            throw new Exception('دسترسی غیرمجاز.');
        }

        if ($action === 'save_quiz_score') {
            $student_id = (int)($input['student_id'] ?? 0);
            $quiz_num = (int)($input['quiz_num'] ?? 0);
            $score = trim((string)($input['score'] ?? ''));
            $config = quizzes_config();

            if (!$student_id || !isset($config[$quiz_num])) {
                throw new Exception('اطلاعات نامعتبر است.');
            }

            $max_score = (int)$config[$quiz_num]['max'];
            $parsed = quizzes_parse_input($score, $max_score);
            if ($score !== '' && $parsed === null) {
                throw new Exception("نمره باید بین 0 تا $max_score باشد.");
            }

            $quiz_field = 'quiz_' . $quiz_num;
            $stmt = $pdo->prepare("
                INSERT INTO student_quizzes (user_id, class_id, $quiz_field)
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE $quiz_field = ?
            ");
            $stmt->execute([$student_id, $class_id, $parsed, $parsed]);

            echo json_encode(['success' => true]);
            exit;

        } elseif ($action === 'save_supervisor_score') {
            $student_id = (int)($input['student_id'] ?? 0);
            $score = trim((string)($input['score'] ?? ''));

            if (!$student_id) {
                throw new Exception('اطلاعات نامعتبر است.');
            }

            if ($score !== '' && (!is_numeric($score) || $score < 0 || $score > 100)) {
                throw new Exception('نمره سوپروایزر باید بین 0 تا 100 باشد.');
            }

            $stmt = $pdo->prepare("
                INSERT INTO supervisor_scores (user_id, class_id, supervisor_score)
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE supervisor_score = ?
            ");
            $val = $score !== '' ? (float)$score : null;
            $stmt->execute([$student_id, $class_id, $val, $val]);

            echo json_encode(['success' => true]);
            exit;

        } elseif ($action === 'submit_score') {
            $student_id = (int)($input['student_id'] ?? 0);

            if (!$student_id) {
                throw new Exception('اطلاعات نامعتبر است.');
            }

            $stmt = $pdo->prepare("
                SELECT supervisor_score FROM supervisor_scores
                WHERE user_id = ? AND class_id = ?
            ");
            $stmt->execute([$student_id, $class_id]);
            $score_data = $stmt->fetch();

            if (!$score_data || $score_data['supervisor_score'] === null) {
                throw new Exception('لطفاً ابتدا نمره سوپروایزر را وارد کنید.');
            }

            $stmt = $pdo->prepare("
                UPDATE supervisor_scores
                SET is_submitted = 1, submitted_at = NOW()
                WHERE user_id = ? AND class_id = ?
            ");
            $stmt->execute([$student_id, $class_id]);

            echo json_encode(['success' => true]);
            exit;

        } else {
            throw new Exception('عملیات نامعتبر است.');
        }

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

$title = 'کارنامه کلاس';
require_once 'layout.php';
require_once '../../includes/db.php';
require_once '../../includes/quizzes.php';
require_role('supervisor');
quizzes_ensure_schema($pdo);

$class_id = (int)($_GET['class_id'] ?? 0);
if (!$class_id) {
    header("Location: report-card.php");
    exit;
}

$stmt = $pdo->prepare("
    SELECT c.title, u.full_name as teacher_name
    FROM classes c
    JOIN users u ON c.teacher_id = u.id
    WHERE c.id = ?
");
$stmt->execute([$class_id]);
$class_info = $stmt->fetch();

if (!$class_info) {
    header("Location: report-card.php");
    exit;
}

$quiz_config = quizzes_config();
$stmt = $pdo->prepare("
    SELECT
        s.id, s.full_name, s.username,
        " . quizzes_sql_columns('sq') . ",
        ss.supervisor_score, ss.is_submitted
    FROM users s
    JOIN class_students cs ON s.id = cs.user_id
    LEFT JOIN student_quizzes sq ON s.id = sq.user_id AND sq.class_id = ?
    LEFT JOIN supervisor_scores ss ON s.id = ss.user_id AND ss.class_id = ?
    WHERE cs.class_id = ? AND s.role = 'student' AND s.is_deleted = 0
    ORDER BY s.full_name
");
$stmt->execute([$class_id, $class_id, $class_id]);
$students = $stmt->fetchAll();
?>

<link href="../../assets/css/supervisor/supervisor-report-card.css?v=<?= time() ?>" rel="stylesheet">


<?php if (empty($students)): ?>
    <div class="card">
        <div class="card-body text-center py-5">
            <div style="font-size: 3rem; margin-bottom: 20px;">👥</div>
            <h4>دانش‌آموزی در این کلاس یافت نشد</h4>
        </div>
    </div>
<?php else: ?>
    <div class="card">
      <div class="card-header bg-danger d-flex justify-content-between pm-2">
     <h1 class="mb-0">Report card - <?= htmlspecialchars($class_info['title']) ?></h1>
    <a href="javascript:history.back()" class="btn btn-light mt-2">Show me the lists</a>
     </div>
        <div class="card-body">
            <div class="table-responsive" style="overflow-x: auto; -webkit-overflow-scrolling: touch;">
                <table class="table table-bordered table-striped table-hover report-card-table align-middle" style="min-width: 1280px;">
                    <thead class="table-light text-center">
                        <tr>
                            <th style="min-width: 150px;">Learners name</th>
                            <th style="min-width: 80px;">ID</th>
                            <?php foreach ($quiz_config as $num => $q): ?>
                            <th style="min-width: 80px;<?= $num === 10 ? ' background:linear-gradient(135deg,#c9a227,#8a5a12);color:#fff;' : '' ?>">
                                <?= htmlspecialchars($q['title']) ?><br>(<?= (int)$q['max'] ?>)
                            </th>
                            <?php endforeach; ?>
                            <th style="min-width: 60px;">Total<br>(100)</th>
                            <th style="min-width: 100px;">Interview Mark</th>
                            <th style="min-width: 100px;">Confirm</th>
                        </tr>
                    </thead>
                    <tbody class="text-center">
                        <?php foreach ($students as $student):
                            $t = quizzes_grade_totals($student);
                        ?>
                        <tr>
                            <td class="text-start fw-bold text-nowrap"><?= htmlspecialchars($student['full_name']) ?></td>
                            <td><?= htmlspecialchars($student['username']) ?></td>
                            <?php foreach ($quiz_config as $quiz_num => $q):
                                $val = $t['quizzes'][$quiz_num];
                            ?>
                            <td>
                                <input type="number"
                                       class="form-control form-control-sm quiz-input mx-auto text-center"
                                       data-student-id="<?= $student['id'] ?>"
                                       data-quiz-num="<?= $quiz_num ?>"
                                       data-class-id="<?= $class_id ?>"
                                       min="0"
                                       max="<?= (int)$q['max'] ?>"
                                       value="<?= quizzes_filled($val) ? number_format($val, 0) : '' ?>"
                                       style="width: 60px;">
                            </td>
                            <?php endforeach; ?>
                            <td class="total-score fw-bold text-primary fs-5"><?= $t['has_quiz_scores'] ? (int)$t['class_total'] : '—' ?></td>
                            <td>
                                <input type="number"
                                       class="form-control form-control-sm supervisor-input mx-auto text-center"
                                       data-student-id="<?= $student['id'] ?>"
                                       data-class-id="<?= $class_id ?>"
                                       min="0"
                                       max="100"
                                       value="<?= $student['supervisor_score'] !== null ? number_format($student['supervisor_score'], 0) : '' ?>"
                                       style="width: 70px;">
                            </td>
                            <td>
                                <?php if ($student['is_submitted']): ?>
                                    <span class="badge bg-success w-100 py-2">Confirmed</span>
                                <?php else: ?>
                                    <button type="button"
                                            class="btn btn-sm btn-primary submit-score w-100"
                                            data-student-id="<?= $student['id'] ?>"
                                            data-class-id="<?= $class_id ?>">
                                        Confirm
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            </div>
    </div>
<?php endif; ?>

<script>
document.querySelectorAll('.quiz-input').forEach(input => {
    input.addEventListener('change', function() {
        const studentId = this.dataset.studentId;
        const quizNum = this.dataset.quizNum;
        const classId = this.dataset.classId;
        const value = this.value;

        fetch('?ajax=1', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                action: 'save_quiz_score',
                student_id: studentId,
                class_id: classId,
                quiz_num: quizNum,
                score: value
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                updateTotalScore(studentId);
            } else {
                alert('خطا: ' + data.message);
                this.value = '';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('خطا در ارتباط با سرور');
            this.value = '';
        });
    });
});

document.querySelectorAll('.supervisor-input').forEach(input => {
    input.addEventListener('change', function() {
        const studentId = this.dataset.studentId;
        const classId = this.dataset.classId;
        const value = this.value;

        fetch('?ajax=1', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                action: 'save_supervisor_score',
                student_id: studentId,
                class_id: classId,
                score: value
            })
        })
        .then(response => response.json())
        .then(data => {
            if (!data.success) {
                alert('خطا: ' + data.message);
                this.value = '';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('خطا در ارتباط با سرور');
            this.value = '';
        });
    });
});

document.querySelectorAll('.submit-score').forEach(button => {
    button.addEventListener('click', function() {
        const studentId = this.dataset.studentId;
        const classId = this.dataset.classId;

        if (!confirm('آیا مطمئن هستید که می‌خواهید این نمره را برای دانش‌آموز ارسال کنید؟')) {
            return;
        }

        fetch('?ajax=1', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                action: 'submit_score',
                student_id: studentId,
                class_id: classId
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('نمره با موفقیت ارسال شد.');
                location.reload();
            } else {
                alert('خطا: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('خطا در ارتباط با سرور');
        });
    });
});

function updateTotalScore(studentId) {
    let total = 0;
    let any = false;
    document.querySelectorAll(`input.quiz-input[data-student-id="${studentId}"]`).forEach(input => {
        if (input.value !== '') {
            any = true;
            total += parseFloat(input.value) || 0;
        }
    });
    const row = document.querySelector(`input[data-student-id="${studentId}"]`).closest('tr');
    row.querySelector('.total-score').textContent = any ? String(Math.min(100, Math.round(total))) : '—';
}
</script>

<?php require_once 'layout-end.php'; ?>
