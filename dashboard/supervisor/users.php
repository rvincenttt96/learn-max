<?php
$title = 'مدیریت کاربران';
require_once 'layout.php';
require_once '../../includes/db.php';
require_role('supervisor');

$teachers_stmt = $pdo->prepare("
    SELECT id, username, full_name, phone, avatar
    FROM users 
    WHERE role = 'teacher' AND is_deleted = 0 
    ORDER BY full_name
");
$teachers_stmt->execute();
$teachers = $teachers_stmt->fetchAll();


$students_stmt = $pdo->prepare("
    SELECT id, username, full_name, phone, avatar
    FROM users 
    WHERE role = 'student' AND is_deleted = 0 
    ORDER BY full_name
");                             
$students_stmt->execute();
$students = $students_stmt->fetchAll();

$consultants = [];
try {
    $consultants_stmt = $pdo->prepare("
        SELECT id, username, full_name, phone, avatar
        FROM users
        WHERE role = 'consultant' AND is_deleted = 0
        ORDER BY full_name
    ");
    $consultants_stmt->execute();
    $consultants = $consultants_stmt->fetchAll();
} catch (PDOException $e) {
    $consultants = [];
}
?>
<?php if (!empty($_GET['success'])): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <?= htmlspecialchars($_GET['success']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if (!empty($_GET['error'])): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <?= htmlspecialchars($_GET['error']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<link href="../../assets/css/supervisor/supervisor-users.css?v=<?= time() ?>" rel="stylesheet">



<!-- فرم ثبت کاربر جدید -->
<div class="card user-management-card mb-4">
    <div class="card-header bg-primary text-white">
        <h5 class="mb-0">Add Users</h5>
		<a href="import-users.php" class="btn btn-outline-light">
    <span class="nav-icon">
	<svg style="width:15px;height15px;" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 384 512"><!--! Font Awesome Pro 7.1.0 by @fontawesome - https://fontawesome.com License - https://fontawesome.com/license (Commercial License) Copyright 2025 Fonticons, Inc. --><path opacity=".4" fill="currentColor" d="M32 64l0 384c0 17.7 14.3 32 32 32l256 0c17.7 0 32-14.3 32-32l0-256-96 0c-35.3 0-64-28.7-64-64l0-96-128 0C46.3 32 32 46.3 32 64zm91.4 217.8c-5.4-7-4.2-17 2.8-22.5s17-4.2 22.5 2.8l43.4 55.8 43.4-55.8c5.4-7 15.5-8.2 22.5-2.8s8.2 15.5 2.8 22.5l-48.4 62.2 48.4 62.2c5.4 7 4.2 17-2.8 22.5s-17 4.2-22.5-2.8l-43.4-55.8-43.4 55.8c-5.4 7-15.5 8.2-22.5 2.8s-8.2-15.5-2.8-22.5l48.4-62.2-48.4-62.2z"/><path fill="currentColor" d="M64 32l128 0 0 96c0 35.3 28.7 64 64 64l96 0 0 256c0 17.7-14.3 32-32 32L64 480c-17.7 0-32-14.3-32-32L32 64c0-17.7 14.3-32 32-32zM224 45.3L338.7 160 256 160c-17.7 0-32-14.3-32-32l0-82.7zM64 0C28.7 0 0 28.7 0 64L0 448c0 35.3 28.7 64 64 64l256 0c35.3 0 64-28.7 64-64l0-261.5c0-17-6.7-33.3-18.7-45.3L242.7 18.7C230.7 6.7 214.5 0 197.5 0L64 0zm84.6 262.2c-5.4-7-15.5-8.2-22.5-2.8s-8.2 15.5-2.8 22.5l48.4 62.2-48.4 62.2c-5.4 7-4.2 17 2.8 22.5s17 4.2 22.5-2.8l43.4-55.8 43.4 55.8c5.4 7 15.5 8.2 22.5 2.8s8.2-15.5 2.8-22.5l-48.4-62.2 48.4-62.2c5.4-7 4.2-17-2.8-22.5s-17-4.2-22.5 2.8l-43.4 55.8-43.4-55.8z"/></svg>
    </span> Import Users
    </a>
    </div>
    <div class="card-body">
        <form method="POST" action="save-user.php" enctype="multipart/form-data" id="user-form">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">شماره پرونده / User Name <span class="text-muted small">(زبان‌آموز: خالی بماند تا خودکار ساخته شود)</span></label>
                    <input type="text" name="username" class="form-control" id="new-username">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Role *</label>
                    <select name="role" class="form-select" required>
                        <option value="teacher">Instructor</option>
                        <option value="student">Learner</option>
                        <option value="supervisor">Supervisor</option>
                        <option value="consultant">Consultant</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Full Name *</label>
                    <input type="text" name="full_name" class="form-control" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Password</label>
                    <input type="password" name="password" class="form-control" id="new-password">
                    <div class="form-text">اگر خالی بماند همان نام کاربری است.</div>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Phone Number</label>
                    <input type="text" name="phone" class="form-control" placeholder="09123456789">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Profile Picture</label>
                    <input type="file" name="avatar" class="form-control" accept="image/*">
                </div>
            </div>
            <div class="mt-3">
                <button type="submit" class="btn btn-primary">Confirm</button>
                <button type="button" class="btn btn-secondary" onclick="resetForm()">Refill</button>
            </div>
        </form>
    </div>
</div>


<div class="card user-management-card mb-4">
    <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
        <h5 class="mb-0">👨‍🏫 Instructors List (<?= count($teachers) ?>)</h5>
        <input type="text" id="teacher-search" class="form-control form-control-sm w-25" placeholder="Let me help...">
    </div>
    <div class="card-body">
        <div class="table-responsive-custom">
            <table class="table table-bordered">
                <thead class="table-light">
                    <tr>
                        <th>No.</th>
                        <th>Avatar</th>
                        <th>User Name</th>
                        <th>Full Name</th>
                        <th>Phone Number</th>
                        <th>Edit</th>
                    </tr>
                </thead>
                <tbody id="teachers-table-body">
                    <?php foreach ($teachers as $index => $teacher): ?>
                    <tr>
                        <td><?= $index + 1 ?></td>
                        <td>
                        <?php if(!empty($teacher['avatar'])): ?>
                            <div class="avatar-hover">
                                <img src="<?= htmlspecialchars($teacher['avatar']) ?>" 
                                    class="user-avatar">
                                <div class="avatar-preview">
                                    <img src="<?= htmlspecialchars($teacher['avatar']) ?>">
                                </div>
                            </div>
                        <?php else: ?>
                            <img src="../../uploads/avatars/daweid-icon-7797704_640.png" class="user-avatar">
                        <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($teacher['username']) ?></td>
                        <td><?= htmlspecialchars($teacher['full_name']) ?></td>
                        <td><?= htmlspecialchars($teacher['phone'] ?? '-') ?></td>
						<td>
							<button type="button" class="btn btn-sm btn-info me-2" 
									onclick="editUser(<?= $teacher['id'] ?>, 'teacher')">
								Edit
							</button>
							<button type="button" class="btn btn-sm btn-warning me-2" 
									onclick="changePassword(<?= $teacher['id'] ?>, '<?= htmlspecialchars($teacher['username']) ?>', 'teacher')">
								Change Password
							</button>
							<button type="button" class="btn btn-sm btn-danger" 
									onclick="deleteUser(<?= $teacher['id'] ?>, '<?= htmlspecialchars($teacher['full_name']) ?>', 'teacher')">
								Delete
							</button>
						</td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<div class="card user-management-card mb-4">
    <div class="card-header bg-warning text-dark d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Consultant List (<?= count($consultants) ?>)</h5>
        <input type="text" id="consultant-search" class="form-control form-control-sm w-25" placeholder="جستجو...">
    </div>
    <div class="card-body">
        <div class="table-responsive-custom">
            <table class="table table-bordered">
                <thead class="table-light">
                    <tr>
                        <th>No.</th>
                        <th>Avatar</th>
                        <th>User Name</th>
                        <th>Full Name</th>
                        <th>Phone Number</th>
                        <th>Edit</th>
                    </tr>
                </thead>
                <tbody id="consultants-table-body">
                    <?php foreach ($consultants as $index => $consultant): ?>
                    <tr>
                        <td><?= $index + 1 ?></td>
                        <td>
                        <?php if(!empty($consultant['avatar'])): ?>
                            <div class="avatar-hover">
                                <img src="<?= htmlspecialchars($consultant['avatar']) ?>"
                                    class="user-avatar">
                                <div class="avatar-preview">
                                    <img src="<?= htmlspecialchars($consultant['avatar']) ?>">
                                </div>
                            </div>
                        <?php else: ?>
                            <img src="../../uploads/avatars/daweid-icon-7797704_640.png" class="user-avatar">
                        <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($consultant['username']) ?></td>
                        <td><?= htmlspecialchars($consultant['full_name']) ?></td>
                        <td><?= htmlspecialchars($consultant['phone'] ?? '-') ?></td>
                        <td>
                            <button type="button" class="btn btn-sm btn-info me-2"
                                    onclick="editUser(<?= $consultant['id'] ?>, 'consultant')">
                                Edit
                            </button>
                            <button type="button" class="btn btn-sm btn-warning me-2"
                                    onclick="changePassword(<?= $consultant['id'] ?>, '<?= htmlspecialchars($consultant['username']) ?>', 'consultant')">
                                Change Password
                            </button>
                            <button type="button" class="btn btn-sm btn-danger"
                                    onclick="deleteUser(<?= $consultant['id'] ?>, '<?= htmlspecialchars($consultant['full_name']) ?>', 'consultant')">
                                Delete
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<!-- لیست دانش‌آموزان -->
<div class="card user-management-card">
    <div class="card-header bg-info text-white d-flex justify-content-between align-items-center">
        <h5 class="mb-0">👧 لیست دانش‌آموزان (<?= count($students) ?>)</h5>
        <input type="text" id="student-search" class="form-control form-control-sm w-25" placeholder="جستجو...">
    </div>
    <div class="card-body">
        <div class="table-responsive-custom">
            <table class="table table-bordered">
                <thead class="table-light">
                    <tr>
                        <th>No.</th>
                        <th>Avatar</th>
                        <th>User Name</th>
                        <th>Full Name</th>
                        <th>Phone Number</th>
                        <th>Edit</th>
                    </tr>
                </thead>
                <tbody id="students-table-body">
                    <?php foreach ($students as $index => $student): ?>
                    <tr>
                        <td><?= $index + 1 ?></td>
                        <td>
                        <?php if(!empty($students['avatar'])): ?>
                            <div class="avatar-hover">
                                <img src="<?= htmlspecialchars($students['avatar']) ?>" 
                                    class="user-avatar">
                                <div class="avatar-preview">
                                    <img src="<?= htmlspecialchars($students['avatar']) ?>">
                                </div>
                            </div>
                        <?php else: ?>
                            <img src="../../uploads/avatars/daweid-icon-7797704_640.png" class="user-avatar">
                        <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($student['username']) ?></td>
                        <td><?= htmlspecialchars($student['full_name']) ?></td>
                        <td><?= htmlspecialchars($student['phone'] ?? '-') ?></td>
						<td>
							<button type="button" class="btn btn-sm btn-info me-2" 
									onclick="editUser(<?= $student['id'] ?>, 'student')">
								Edit
							</button>
							<button type="button" class="btn btn-sm btn-warning me-2" 
									onclick="changePassword(<?= $student['id'] ?>, '<?= htmlspecialchars($student['username']) ?>', 'student')">
								Change Password
							</button>
							<button type="button" class="btn btn-sm btn-danger" 
									onclick="deleteUser(<?= $student['id'] ?>, '<?= htmlspecialchars($student['full_name']) ?>', 'student')">
								Delete
							</button>
						</td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- مودال تغییر رمز عبور -->
<div class="modal fade" id="passwordModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-warning text-white">
                <h5 class="modal-title">تغییر رمز عبور</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="password-form">
                    <input type="hidden" id="user-id" name="user_id">
                    <input type="hidden" id="user-type" name="user_type">
                    <div class="mb-3">
                        <label class="form-label">نام کاربری: <span id="modal-username"></span></label>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">رمز عبور جدید *</label>
                        <input type="password" name="new_password" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">تأیید رمز عبور *</label>
                        <input type="password" name="confirm_password" class="form-control" required>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">لغو</button>
                <button type="button" class="btn btn-warning" onclick="savePassword()">ذخیره</button>
            </div>
        </div>
    </div>
</div>
<!-- مودال ویرایش کاربر -->
<div class="modal fade" id="editUserModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title">ویرایش کاربر</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="edit-user-form" enctype="multipart/form-data">
                    <input type="hidden" id="edit-user-id" name="user_id">
                    <input type="hidden" id="edit-user-type" name="user_type">
                    
                    <div class="row g-3">
						<div class="col-md-6">
							<label class="form-label">نام کاربری *</label>
							<input type="text" id="edit-username" name="username" class="form-control" required>
						</div>
						<div class="col-md-6">
							<label class="form-label">نقش *</label>
							<select id="edit-role" name="role" class="form-select" required>
								<option value="teacher">معلم</option>
								<option value="student">دانش‌آموز</option>
                                <option value="supervisor">سوپروایزر</option>
                                <option value="consultant">مشاور</option>
							</select>
						</div>
						<div class="col-md-6">
							<label class="form-label">نام و نام خانوادگی *</label>
							<input type="text" id="edit-full-name" name="full_name" class="form-control" required>
						</div>
						<div class="col-md-6">
							<label class="form-label">شماره تماس</label>
							<input type="text" id="edit-phone" name="phone" class="form-control" placeholder="09123456789">
						</div>
                        <div class="col-md-6">
                            <label class="form-label">عکس پروفایل</label>
                            <input type="file" id="edit-avatar" name="avatar" class="form-control" accept="image/*">
                            <small class="text-muted">فرمت‌های مجاز: JPG, PNG, WebP</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">پیش‌نمایش عکس فعلی</label>
                            <div id="current-avatar-preview" class="mt-2">
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">لغو</button>
                <button type="button" class="btn btn-info" onclick="saveUserEdit()">ذخیره تغییرات</button>
            </div>
        </div>
    </div>
</div>

<script>
// پاک‌سازی فرم
function resetForm() {
    document.getElementById('user-form').reset();
}

(function () {
    const usernameInput = document.getElementById('new-username');
    const passwordInput = document.getElementById('new-password');
    const roleSelect = document.querySelector('#user-form select[name="role"]');
    function suggestPassword() {
        if (!usernameInput || !passwordInput || !roleSelect) return;
        const isStudent = roleSelect.value === 'student';
        usernameInput.required = !isStudent;
        passwordInput.required = false;
        const code = usernameInput.value.trim();
        if (code !== '' && passwordInput.value === '') {
            passwordInput.value = code;
        }
        if (!isStudent) return;
        if (/^[0-9]{3,8}$/.test(code)) {
            passwordInput.value = code;
        }
    }
    if (usernameInput) usernameInput.addEventListener('input', suggestPassword);
    if (roleSelect) roleSelect.addEventListener('change', suggestPassword);
    suggestPassword();
})();

// ارسال فرم با AJAX
document.getElementById('user-form').addEventListener('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    fetch('save-user.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        alert(data.message);
        if (data.success) {
            location.reload();
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('خطا در ارتباط با سرور.');
    });
});

// باز کردن مودال تغییر رمز عبور
function changePassword(userId, username, userType) {
    document.getElementById('user-id').value = userId;
    document.getElementById('user-type').value = userType;
    document.getElementById('modal-username').textContent = username;
    const modal = new bootstrap.Modal(document.getElementById('passwordModal'));
    modal.show();
}

// ذخیره رمز عبور جدید
function savePassword() {
    const form = document.getElementById('password-form');
    const formData = new FormData(form);
    if (formData.get('new_password') !== formData.get('confirm_password')) {
        alert('رمز عبور و تأیید آن یکسان نیستند!');
        return;
    }
    fetch('save-user.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        alert(data.message);
        if (data.success) {
            bootstrap.Modal.getInstance(document.getElementById('passwordModal')).hide();
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('خطا در ارتباط با سرور.');
    });
}
// حذف کاربر
function deleteUser(userId, fullName, userType) {
    let userTypeName = 'دانش‌آموز';
    if (userType === 'teacher') userTypeName = 'معلم';
    if (userType === 'supervisor') userTypeName = 'سوپروایزر';
    if (userType === 'consultant') userTypeName = 'مشاور';
    
    if (!confirm(`آیا مطمئن هستید که می‌خواهید ${userTypeName} "${fullName}" را حذف کنید؟\nاین عمل غیرقابل بازگشت است.`)) {
        return;
    }
    
    fetch('save-user.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({
            action: 'delete_user',
            user_id: userId,
            user_type: userType
        })
    })
    .then(response => response.json())
    .then(data => {
        alert(data.message);
        if (data.success) {
            location.reload();
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('خطا در حذف کاربر.');
    });
}

// باز کردن مودال ویرایش
function editUser(userId, userType) {
    // دریافت اطلاعات کاربر از سرور
    fetch(`get-user-details.php?user_id=${userId}&user_type=${userType}`)
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // پر کردن فیلدها
            document.getElementById('edit-user-id').value = data.user.id;
            document.getElementById('edit-user-type').value = data.user.role;
            document.getElementById('edit-username').value = data.user.username || '';
            document.getElementById('edit-role').value = data.user.role || '';
            document.getElementById('edit-full-name').value = data.user.full_name || '';
            document.getElementById('edit-phone').value = data.user.phone || '';
            
            // نمایش عکس فعلی
            const avatarPreview = document.getElementById('current-avatar-preview');
            if (data.user.avatar) {
                avatarPreview.innerHTML = `<img src="${data.user.avatar}" alt="عکس فعلی" style="width: 100px; height: 100px; object-fit: cover; border-radius: 8px;">`;
            } else {
                avatarPreview.innerHTML = '<span class="text-muted">عکسی وجود ندارد</span>';
            }
            
            // نمایش مودال
            const modal = new bootstrap.Modal(document.getElementById('editUserModal'));
            modal.show();
        } else {
            alert('خطا: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error loading user data:', error);
        alert('خطا در بارگذاری اطلاعات کاربر.');
    });
}

// ذخیره تغییرات کاربر
function saveUserEdit() {
    const userId = document.getElementById('edit-user-id').value;
    const username = document.getElementById('edit-username').value.trim();
    const fullName = document.getElementById('edit-full-name').value.trim();
    
    // اعتبارسنجی سمت کلاینت
    if (!username || !fullName) {
        alert('لطفاً فیلدهای ستاره‌دار را پر کنید.');
        return;
    }
    
    const formData = new FormData(document.getElementById('edit-user-form'));
    
    fetch('save-user.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        alert(data.message);
        if (data.success) {
            bootstrap.Modal.getInstance(document.getElementById('editUserModal')).hide();
            location.reload();
        }
    })
    .catch(error => {
        console.error('Save Error:', error);
        alert('خطا در ذخیره تغییرات.');
    });
}
// جستجوی هوشمند
document.getElementById('teacher-search').addEventListener('input', function() {
    const searchTerm = this.value.toLowerCase();
    document.querySelectorAll('#teachers-table-body tr').forEach(row => {
        row.style.display = row.textContent.toLowerCase().includes(searchTerm) ? '' : 'none';
    });
});

document.getElementById('student-search').addEventListener('input', function() {
    const searchTerm = this.value.toLowerCase();
    document.querySelectorAll('#students-table-body tr').forEach(row => {
        row.style.display = row.textContent.toLowerCase().includes(searchTerm) ? '' : 'none';
    });
});

document.getElementById('consultant-search').addEventListener('input', function() {
    const searchTerm = this.value.toLowerCase();
    document.querySelectorAll('#consultants-table-body tr').forEach(row => {
        row.style.display = row.textContent.toLowerCase().includes(searchTerm) ? '' : 'none';
    });
});
</script>

<?php require_once 'layout-end.php'; ?>