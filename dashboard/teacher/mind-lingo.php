<?php
$title = 'Mind Lingo';
$current_page = 'mind-lingo.php';
require_once 'layout.php';
require_once '../../includes/db.php';
require_once '../../includes/terms.php';
require_role('teacher');

$teacher_id = $_SESSION['user_id'];
$selected_class_id = (int)($_GET['class_id'] ?? 0);

// دریافت لیست کلاس‌های این معلم (بدون ترم‌های آرشیو شده و کلاس‌های حذف شده)
$stmt = $pdo->prepare("
    SELECT c.id, c.title 
    FROM classes c
    LEFT JOIN terms t ON c.term_id = t.id
    WHERE c.teacher_id = ? 
    AND c.is_deleted = 0 
    AND (" . term_sql_teaching('t') . " OR c.term_id IS NULL)
    AND IFNULL(c.is_archived, 0) = 0
    ORDER BY c.title
");
$stmt->execute([$teacher_id]);
$classes = $stmt->fetchAll();

$students = [];
// اگر کلاسی انتخاب شده باشد، لیست دانش‌آموزان و نمرات قبلی Mind Lingo را می‌گیریم
if ($selected_class_id) {
    // بررسی مالکیت کلاس
    $check_stmt = $pdo->prepare("SELECT id FROM classes WHERE id = ? AND teacher_id = ?");
    $check_stmt->execute([$selected_class_id, $teacher_id]);
    if ($check_stmt->fetch()) {
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
}
?>

<style>
    .mind-lingo-page .range-wrap {
        position: relative;
        width: 100%;
        padding-top: 1.6rem;
        direction: ltr;
    }
    .mind-lingo-page .form-range {
        width: 100%;
        height: 1.5rem;
    }
    .mind-lingo-page .range-value {
        position: absolute;
        top: -4px;
        left: 50%;
        transform: translateX(-50%);
        background: #d32f2f;
        color: white;
        padding: 2px 10px;
        border-radius: 15px;
        font-weight: bold;
        font-size: 0.9rem;
    }
    .word-counter {
        font-size: 0.85rem;
        color: #ff9a9a;
    }
    .word-counter.text-danger {
        font-weight: bold;
    }
</style>

<div class="mind-lingo-page">
<div class="card mb-4">
    <div class="card-header text-white">
        <h5 class="mb-0">ارزیابی Mind Lingo</h5>
    </div>
    <div class="card-body">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-12 col-md-8">
                <label class="form-label">انتخاب کلاس</label>
                <select name="class_id" class="form-select" onchange="this.form.submit()">
                    <option value="">کلاس را انتخاب کنید</option>
                    <?php foreach ($classes as $cls): ?>
                        <option value="<?= $cls['id'] ?>" <?= $cls['id'] == $selected_class_id ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cls['title']) ?>
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
            <h5 class="mb-0">لیست زبان‌آموزان</h5>
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
                                <th>وضعیت</th>
                                <th>عملیات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($students as $student): ?>
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
                                        <?php if ($student['communication_dev'] !== null): ?>
                                            <span class="badge bg-success">ارزیابی‌شده</span>
                                        <?php else: ?>
                                            <span class="badge bg-warning text-dark">در انتظار</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-danger px-3" onclick='openMindLingoModal(<?= json_encode($student, JSON_UNESCAPED_UNICODE) ?>, <?= (int)$selected_class_id ?>)'>
                                            ارزیابی
                                        </button>
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

<div class="modal fade" id="mindLingoModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">ارزیابی Mind Lingo — <span id="modal-student-name"></span></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="بستن"></button>
            </div>
            <div class="modal-body">
                <form id="mind-lingo-form">
                    <input type="hidden" name="class_id" id="ml-class-id">
                    <input type="hidden" name="student_id" id="ml-student-id">
                    
                    <div class="row g-4">
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-bold">۱. رشد ارتباطی</label>
                            <div class="range-wrap">
                                <span class="range-value" id="val-comm">0</span>
                                <input type="range" class="form-range" name="communication_dev" id="ml-comm" min="0" max="10" step="1" value="0" oninput="updateRange('val-comm', this.value)">
                            </div>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-bold">۲. مشارکت در یادگیری</label>
                            <div class="range-wrap">
                                <span class="range-value" id="val-eng">0</span>
                                <input type="range" class="form-range" name="learning_eng" id="ml-eng" min="0" max="10" step="1" value="0" oninput="updateRange('val-eng', this.value)">
                            </div>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-bold">۳. پیوستگی یادگیری</label>
                            <div class="range-wrap">
                                <span class="range-value" id="val-cons">0</span>
                                <input type="range" class="form-range" name="learning_cons" id="ml-cons" min="0" max="10" step="1" value="0" oninput="updateRange('val-cons', this.value)">
                            </div>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-bold">۴. رشد فردی</label>
                            <div class="range-wrap">
                                <span class="range-value" id="val-growth">0</span>
                                <input type="range" class="form-range" name="personal_growth" id="ml-growth" min="0" max="10" step="1" value="0" oninput="updateRange('val-growth', this.value)">
                            </div>
                        </div>
                        <div class="col-12 mt-4">
                            <label class="form-label fw-bold">۵. یادداشت معلم</label>
                            <textarea class="form-control" name="teacher_note" id="ml-note" rows="5" placeholder="مشاهده خود را بنویسید (حداکثر ۵۰۰ کلمه)" oninput="checkWordCount(this)"></textarea>
                            <div class="d-flex justify-content-start mt-1">
                                <span class="word-counter" id="word-count-display">کلمات: 0 / 500</span>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">انصراف</button>
                <button type="button" class="btn btn-danger" id="save-ml-btn" onclick="saveMindLingo()">ذخیره ارزیابی</button>
            </div>
        </div>
    </div>
</div>
</div>

<script>
// تابع آپدیت عدد روی اسلایدرها
function updateRange(spanId, value) {
    document.getElementById(spanId).innerText = value;
}

// شمارنده کلمات
function checkWordCount(textarea) {
    let text = textarea.value.trim();
    // شمارش کلمات با Regex (جلوگیری از شمارش فاصله‌های خالی)
    let words = text.match(/\S+/g);
    let wordCount = words ? words.length : 0;
    
    let display = document.getElementById('word-count-display');
    display.innerText = `کلمات: ${wordCount} / 500`;
    
    if (wordCount > 500) {
        display.classList.add('text-danger');
        document.getElementById('save-ml-btn').disabled = true;
    } else {
        display.classList.remove('text-danger');
        document.getElementById('save-ml-btn').disabled = false;
    }
}

// باز کردن فرم و جایگذاری مقادیر قبلی (اگر وجود داشته باشد)
function openMindLingoModal(student, classId) {
    document.getElementById('modal-student-name').innerText = student.full_name;
    document.getElementById('ml-class-id').value = classId;
    document.getElementById('ml-student-id').value = student.id;
    
    // جایگذاری مقادیر قبلی یا صفر
    const commVal = student.communication_dev !== null ? student.communication_dev : 0;
    const engVal = student.learning_eng !== null ? student.learning_eng : 0;
    const consVal = student.learning_cons !== null ? student.learning_cons : 0;
    const growthVal = student.personal_growth !== null ? student.personal_growth : 0;
    const noteVal = student.teacher_note !== null ? student.teacher_note : '';

    document.getElementById('ml-comm').value = commVal;
    updateRange('val-comm', commVal);

    document.getElementById('ml-eng').value = engVal;
    updateRange('val-eng', engVal);

    document.getElementById('ml-cons').value = consVal;
    updateRange('val-cons', consVal);

    document.getElementById('ml-growth').value = growthVal;
    updateRange('val-growth', growthVal);

    const noteField = document.getElementById('ml-note');
    noteField.value = noteVal;
    checkWordCount(noteField);

    const modal = new bootstrap.Modal(document.getElementById('mindLingoModal'));
    modal.show();
}

// ذخیره اطلاعات با AJAX
function saveMindLingo() {
    const form = document.getElementById('mind-lingo-form');
    const formData = new FormData(form);
    const btn = document.getElementById('save-ml-btn');
    
    btn.disabled = true;
    btn.innerText = 'در حال ذخیره...';

    fetch('save-mind-lingo.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(data.message);
            location.reload(); // رفرش برای آپدیت شدن استاتوس به Evaluated
        } else {
            alert('Error: ' + data.message);
            btn.disabled = false;
            btn.innerText = 'ذخیره ارزیابی';
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('خطا در برقراری ارتباط با سرور.');
        btn.disabled = false;
        btn.innerText = 'ذخیره ارزیابی';
    });
}
</script>

<?php require_once 'layout-end.php'; ?>