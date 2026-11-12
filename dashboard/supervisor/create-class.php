<?php
$title = 'تعریف کلاس جدید';
require_once '../../includes/db.php';
require_once '../../includes/schedule-days.php';
require_once 'layout.php';
require_role('supervisor');
echo schedule_days_picker_assets();

$term_id = (int)($_GET['term_id'] ?? 0);
if (!$term_id) {
    header("Location: terms.php?error=ترمی انتخاب نشده است.");
    exit;
}

$stmt = $pdo->prepare("SELECT title FROM terms WHERE id = ? AND is_deleted = 0");
$stmt->execute([$term_id]);
$term_title = $stmt->fetchColumn();
if (!$term_title) {
    header("Location: terms.php?error=ترم یافت نشد.");
    exit;
}

$stmt = $pdo->prepare("SELECT id, full_name FROM users WHERE role = 'teacher' AND is_deleted = 0");
$stmt->execute();
$teachers = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT id, full_name, username as student_code FROM users WHERE role = 'student' AND is_deleted = 0");
$stmt->execute();
$students = $stmt->fetchAll();
?>

<link href="../../assets/css/supervisor/supervisor-terms.css?v=<?= time() ?>" rel="stylesheet">

<style>
/* استایل‌های سفارشی برای درست کردن رنگ منوهای کشویی و اینپوت‌ها در تم دارک */
.form-select, .form-control, .input-group-text {
    background-color: #1a1a2e !important;
    color: #ffffff !important;
    border: 1px solid rgba(255, 255, 255, 0.1) !important;
}
.form-select:focus, .form-control:focus {
    background-color: #16213e !important;
    color: #ffffff !important;
    border-color: #1830a8 !important;
    box-shadow: 0 0 0 0.25rem rgba(24, 48, 168, 0.25) !important;
}
.form-select option {
    background-color: #1a1a2e;
    color: #ffffff;
    padding: 10px;
}
.text-muted {
    color: #a0a0a0 !important;
}
</style>

<div class="dashboard-header">
    <h1 class="mb-0">Add a new class - <?= htmlspecialchars($term_title) ?></h1>
</div>

<div class="card term-management-card">
    <div class="card-body">
        <form method="POST" action="save-class.php" id="class-form">
            <input type="hidden" name="term_id" value="<?= $term_id ?>">
            
            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <label class="form-label">Class Name *</label>
                    <input type="text" name="class_title" class="form-control" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Instructor *</label>
                    <select name="teacher_id" class="form-select" required>
                        <option value="">Select...</option>
                        <?php foreach ($teachers as $teacher): ?>
                        <option value="<?= $teacher['id'] ?>"><?= htmlspecialchars($teacher['full_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Class Type *</label>
                    <select name="class_type" class="form-select" required>
                        <option value="online">Online</option>
                        <option value="offline">Inperson</option>
                        <option value="private">Private</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Online class LINK</label>
                    <input type="url" name="online_link" class="form-control" 
                           placeholder="link class">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Class Time</label>
                    <input type="text" name="schedule" class="form-control" 
                           placeholder="Sat 16:00">
                </div>
                <div class="col-md-12">
                    <label class="form-label">Days * (exactly 2)</label>
                    <?php echo schedule_days_picker_markup('schedule_days[]', '', ['id_prefix' => 'create']); ?>
                </div>
            </div>
            
            <div class="mb-4">
                <h5 class="mb-3">Participants Selection</h5>
                
                <!-- فیلد جستجو -->
                <div class="input-group mb-3">
                    <span class="input-group-text">
                        <i data-feather="search"></i>
                    </span>
                    <input type="text" id="student-search" class="form-control" placeholder="Search...">
                </div>
                
                <!-- لیست دانش‌آموزان -->
                <div class="students-select-container">
                    <div class="students-list" id="students-list">
                        <!-- دانش‌آموزان با AJAX لود می‌شن -->
                        <div class="text-center py-4 text-muted">Loading...</div>
                    </div>
                </div>
                
                <!-- دانش‌آموزان انتخاب‌شده -->
                <div class="selected-students mt-3" id="selected-students">
                    <h6 class="mb-2">Selected Learners: <span id="selected-count">0</span></h6>
                    <div class="d-flex flex-wrap gap-2" id="selected-students-list"></div>
                </div>
                
                <!-- فیلدهای مخفی برای فرم -->
                <div id="selected-students-inputs"></div>
            </div>
            
            <div class="mt-4">
                <button type="submit" class="btn btn-primary">Confirm</button>
                <a href="terms.php" class="btn btn-secondary">Return</a>
            </div>
        </form>
    </div>
</div>

<script>
// ============= سیستم انتخاب دانش‌آموز =============
let allStudents = [];
let selectedStudents = new Set();

// لود دانش‌آموزان
function loadStudents() {
    fetch('../../includes/get-students.php')
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            allStudents = data.students;
            renderStudentsList();
            renderSelectedStudents();
        } else {
            document.getElementById('students-list').innerHTML = 
                '<div class="text-center py-4 text-danger">خطا در بارگذاری دانش‌آموزان</div>';
        }
    })
    .catch(error => {
        console.error('Error loading students:', error);
        document.getElementById('students-list').innerHTML = 
            '<div class="text-center py-4 text-danger">خطا در ارتباط با سرور</div>';
    });
}

// رندر لیست دانش‌آموزان
function renderStudentsList() {
    const searchTerm = document.getElementById('student-search').value.toLowerCase();
    const filteredStudents = allStudents.filter(student => 
        student.full_name.toLowerCase().includes(searchTerm) ||
        student.student_code.toLowerCase().includes(searchTerm)
    );
    
    if (filteredStudents.length === 0) {
        document.getElementById('students-list').innerHTML = 
            '<div class="text-center py-4 text-muted">دانش‌آموزی یافت نشد</div>';
        return;
    }
    
    let html = '';
    filteredStudents.forEach(student => {
        const isSelected = selectedStudents.has(student.id.toString());
        html += `
        <div class="student-card ${isSelected ? 'selected' : ''}" 
             onclick="toggleStudentSelection('${student.id}')">
            <div class="select-check"></div>
            <div class="student-name">${student.full_name}</div>
            <div class="student-code">${student.student_code}</div>
        </div>`;
    });
    
    document.getElementById('students-list').innerHTML = html;
}

// رندر دانش‌آموزان انتخاب‌شده
function renderSelectedStudents() {
    const selectedList = document.getElementById('selected-students-list');
    const selectedCount = document.getElementById('selected-count');
    const selectedInputs = document.getElementById('selected-students-inputs');
    
    let tagsHtml = '';
    let inputsHtml = '';
    
    selectedStudents.forEach(studentId => {
        const student = allStudents.find(s => s.id === studentId);
        if (student) {
            tagsHtml += `
            <div class="selected-student-tag">
                ${student.full_name}
                <span class="remove-student" onclick="removeSelectedStudent('${studentId}', event)">×</span>
            </div>`;
            inputsHtml += `<input type="hidden" name="student_ids[]" value="${studentId}">`;
        }
    });
    
    selectedList.innerHTML = tagsHtml;
    selectedCount.textContent = selectedStudents.size;
    selectedInputs.innerHTML = inputsHtml;
}

// تغییر انتخاب دانش‌آموز
function toggleStudentSelection(studentId) {
    if (selectedStudents.has(studentId)) {
        selectedStudents.delete(studentId);
    } else {
        selectedStudents.add(studentId);
    }
    renderStudentsList();
    renderSelectedStudents();
}

// حذف دانش‌آموز انتخاب‌شده
function removeSelectedStudent(studentId, event) {
    event.stopPropagation();
    selectedStudents.delete(studentId);
    renderStudentsList();
    renderSelectedStudents();
}

// جستجوی دانش‌آموزان
document.getElementById('student-search').addEventListener('input', function() {
    renderStudentsList();
});

// شروع لود دانش‌آموزان
loadStudents();

// ارسال فرم با AJAX
document.getElementById('class-form').addEventListener('submit', function(e) {
    e.preventDefault();
    
    // جمع‌آوری دستی داده‌ها
    const formData = new FormData();
    
    // اطلاعات اصلی فرم
    formData.append('term_id', document.querySelector('input[name="term_id"]').value);
    formData.append('class_title', document.querySelector('input[name="class_title"]').value);
    formData.append('teacher_id', document.querySelector('select[name="teacher_id"]').value);
    formData.append('class_type', document.querySelector('select[name="class_type"]').value);
    formData.append('online_link', document.querySelector('input[name="online_link"]').value || '');
    formData.append('schedule', document.querySelector('input[name="schedule"]').value || '');
    document.querySelectorAll('input[name="schedule_days[]"]:checked').forEach(function (el) {
        formData.append('schedule_days[]', el.value);
    });
    
    // دانش‌آموزان انتخاب‌شده
    selectedStudents.forEach(studentId => {
        formData.append('student_ids[]', studentId);
    });
    
    fetch('save-class.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        alert(data.message);
        if (data.success) {
            window.location.href = 'terms.php';
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('خطا در ارتباط با سرور.');
    });
});
</script>

<?php require_once 'layout-end.php'; ?>