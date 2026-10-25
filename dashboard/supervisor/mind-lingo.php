<?php
$title = 'Mind Lingo';
$current_page = 'mind-lingo.php';
require_once 'layout.php';
require_once '../../includes/db.php';
require_once '../../includes/terms.php';
require_role('supervisor');

$selected_class_id = (int)($_GET['class_id'] ?? 0);

// دریافت لیست تمام کلاس‌های فعال (به همراه نام معلم)
$stmt = $pdo->prepare("
    SELECT c.id, c.title, u.full_name as teacher_name
    FROM classes c
    JOIN users u ON c.teacher_id = u.id
    LEFT JOIN terms t ON c.term_id = t.id
    WHERE c.is_deleted = 0 
    AND (" . term_sql_teaching('t') . " OR c.term_id IS NULL)
    AND IFNULL(c.is_archived, 0) = 0
    ORDER BY c.title
");
$stmt->execute();
$classes = $stmt->fetchAll();

$students = [];
// اگر کلاسی انتخاب شده باشد، لیست دانش‌آموزان و نمرات Mind Lingo را می‌گیریم
if ($selected_class_id) {
    $stmt = $pdo->prepare("
        SELECT u.id, u.full_name, u.username, u.avatar,
               m.communication_dev, m.learning_eng, m.learning_cons, m.personal_growth, m.teacher_note
        FROM users u
        JOIN class_students cs ON u.id = cs.user_id
        LEFT JOIN mind_lingo m ON u.id = m.student_id AND m.class_id = ?
        WHERE cs.class_id = ? AND u.role = 'student' AND u.is_deleted = 0
        ORDER BY u.full_name
    ");
    $stmt->execute([$selected_class_id, $selected_class_id]);
    $students = $stmt->fetchAll();
}
?>

<div class="mind-lingo-page">
<div class="card mb-4">
    <div class="card-header text-white">
        <h5 class="mb-0">Mind Lingo — نمای سوپروایزر</h5>
    </div>
    <div class="card-body">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-12 col-md-8">
                <label class="form-label">انتخاب کلاس</label>
                <select name="class_id" class="form-select" onchange="this.form.submit()">
                    <option value="">کلاس را انتخاب کنید</option>
                    <?php foreach ($classes as $cls): ?>
                        <option value="<?= $cls['id'] ?>" <?= $cls['id'] == $selected_class_id ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cls['title']) ?> — <?= htmlspecialchars($cls['teacher_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>
    </div>
</div>

<?php if ($selected_class_id): ?>
    <div class="card">
        <div class="card-header text-white">
            <h5 class="mb-0">نتایج کلاس</h5>
        </div>
        <div class="card-body">
            <?php if (empty($students)): ?>
                <div class="text-center py-4">
                    <h5>زبان‌آموزی در این کلاس یافت نشد.</h5>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead>
                            <tr>
                                <th>عکس</th>
                                <th>نام کامل</th>
                                <th>کد زبان‌آموز</th>
                                <th>نمره کل (از ۴۰)</th>
                                <th>جزئیات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($students as $student): ?>
                                <?php 
                                    $is_evaluated = ($student['communication_dev'] !== null);
                                    $total_score = $is_evaluated ? 
                                        ((int)$student['communication_dev'] + (int)$student['learning_eng'] + (int)$student['learning_cons'] + (int)$student['personal_growth']) : 0;
                                ?>
                                <tr>
                                    <td>
                                        <?php if (!empty($student['avatar'])): ?>
                                            <img src="<?= htmlspecialchars($student['avatar']) ?>" class="rounded-circle" width="40" height="40" style="object-fit: cover;">
                                        <?php else: ?>
                                            <div class="bg-secondary text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                                <?= mb_substr($student['full_name'], 0, 1) ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="fw-bold"><?= htmlspecialchars($student['full_name']) ?></td>
                                    <td><span class="ltr-num"><?= htmlspecialchars($student['username']) ?></span></td>
                                    <td>
                                        <?php if ($is_evaluated): ?>
                                            <span class="badge bg-success score-badge ltr-num"><?= $total_score ?> / 40</span>
                                        <?php else: ?>
                                            <span class="badge bg-warning text-dark score-badge">در انتظار</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($is_evaluated): ?>
                                            <button type="button" class="btn btn-sm btn-danger px-3 fw-bold" onclick='viewDetails(<?= json_encode($student, JSON_UNESCAPED_UNICODE) ?>)'>
                                                مشاهده
                                            </button>
                                        <?php else: ?>
                                            <button type="button" class="btn btn-sm btn-secondary px-3" disabled>بدون داده</button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<div class="modal fade" id="detailsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">جزئیات ارزیابی — <span id="det-student-name"></span></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="بستن"></button>
            </div>
            <div class="modal-body p-4">
                <div class="row g-4">
                    <div class="col-12 col-md-6">
                        <label class="fw-bold mb-1">رشد ارتباطی</label>
                        <div class="progress">
                            <div id="bar-comm" class="progress-bar bg-danger" role="progressbar" style="width: 0%;">0/10</div>
                        </div>
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="fw-bold mb-1">مشارکت در یادگیری</label>
                        <div class="progress">
                            <div id="bar-eng" class="progress-bar bg-success" role="progressbar" style="width: 0%;">0/10</div>
                        </div>
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="fw-bold mb-1">پیوستگی یادگیری</label>
                        <div class="progress">
                            <div id="bar-cons" class="progress-bar bg-warning text-dark" role="progressbar" style="width: 0%;">0/10</div>
                        </div>
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="fw-bold mb-1">رشد فردی</label>
                        <div class="progress">
                            <div id="bar-growth" class="progress-bar bg-info" role="progressbar" style="width: 0%;">0/10</div>
                        </div>
                    </div>
                    <div class="col-12 mt-4">
                        <label class="fw-bold mb-2">یادداشت معلم</label>
                        <div id="det-note" class="note-box p-3"></div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">بستن</button>
            </div>
        </div>
    </div>
</div>
</div>

<script>
function viewDetails(student) {
    document.getElementById('det-student-name').innerText = student.full_name;
    
    document.getElementById('bar-comm').style.width = (student.communication_dev * 10) + '%';
    document.getElementById('bar-comm').innerText = student.communication_dev + ' / 10';

    document.getElementById('bar-eng').style.width = (student.learning_eng * 10) + '%';
    document.getElementById('bar-eng').innerText = student.learning_eng + ' / 10';

    document.getElementById('bar-cons').style.width = (student.learning_cons * 10) + '%';
    document.getElementById('bar-cons').innerText = student.learning_cons + ' / 10';

    document.getElementById('bar-growth').style.width = (student.personal_growth * 10) + '%';
    document.getElementById('bar-growth').innerText = student.personal_growth + ' / 10';

    const noteBox = document.getElementById('det-note');
    if (student.teacher_note && student.teacher_note.trim() !== '') {
        noteBox.innerText = student.teacher_note;
        noteBox.classList.remove('text-muted', 'fst-italic');
    } else {
        noteBox.innerText = 'یادداشتی توسط معلم ثبت نشده است.';
        noteBox.classList.add('text-muted', 'fst-italic');
    }

    const modal = new bootstrap.Modal(document.getElementById('detailsModal'));
    modal.show();
}
</script>

<?php require_once 'layout-end.php'; ?>