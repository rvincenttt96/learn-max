<?php
// error_reporting(E_ALL);
// ini_set('display_errors', 1);
$raw_input = file_get_contents('php://input');
$input_data = null;
if ($raw_input && strpos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false) {
    $input_data = json_decode($raw_input, true);
} else {
    $input_data = $_POST;
}
// ================================
// AJAX: حذف تاریخ حضور و غیاب
// ================================
if (!empty($input_data['action']) && $input_data['action'] == 'delete_date') {
    require_once '../../includes/db.php';
    require_once '../../includes/jdate.php';
    
    // تنظیمات header
    header('Content-Type: application/json; charset=utf-8');
    ob_clean(); // ← این خط مهمه
    
    try {
        $class_id = (int)($input_data['class_id'] ?? 0);
        $jalali_date = trim($input_data['date'] ?? '');
        
        if (!$class_id || !$jalali_date) {
            throw new Exception('داده نامعتبر');
        }
        
        $gregorian_date = jdate_to_gregorian($jalali_date);
        if (!$gregorian_date) {
            throw new Exception('تاریخ شمسی نامعتبر');
        }
        
        $stmt = $pdo->prepare("DELETE FROM attendance WHERE class_id = ? AND class_date = ?");
        $result = $stmt->execute([$class_id, $gregorian_date]);
        
        if ($result) {
            ob_clean(); // ← این خط مهمه
            echo json_encode(['success' => true, 'message' => 'تاریخ با موفقیت حذف شد.']);
        } else {
            throw new Exception('خطا در حذف از دیتابیس');
        }
        exit;
        
    } catch (Exception $e) {
        ob_clean(); // ← این خط مهمه
        error_log("Delete Attendance Error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

if (!empty($input_data['action']) && $input_data['action'] === 'send_late') {
    require_once '../../includes/auth.php';
    require_once '../../includes/db.php';
    require_once '../../includes/jdate.php';
    require_once '../../includes/rollcall.php';
    require_role('teacher');
    header('Content-Type: application/json; charset=utf-8');

    $class_id = (int)($input_data['class_id'] ?? 0);
    $user_id = (int)($input_data['user_id'] ?? 0);
    $jalali_date = trim((string)($input_data['date'] ?? ''));
    $teacher_id = (int)($_SESSION['user_id'] ?? 0);

    if ($jalali_date === '') {
        $jalali_date = function_exists('jdate_format') ? jdate_format(date('Y-m-d'), 'Y/m/d') : date('Y/m/d');
    }

    echo json_encode(rollcall_notify_late($pdo, $class_id, $teacher_id, $user_id, $jalali_date), JSON_UNESCAPED_UNICODE);
    exit;
}

if (!empty($_GET['action']) && $_GET['action'] == 'get_data') {
    require_once '../../includes/db.php';
    require_once '../../includes/jdate.php';
    require_once '../../includes/rollcall.php';
    header('Content-Type: application/json');
    
    $class_id = (int)($_GET['class_id'] ?? 0);
    $jalali_date = $_GET['date'] ?? '';
    
    if (!$class_id || !$jalali_date) {
        echo json_encode(['success' => false, 'message' => 'داده نامعتبر']);
        exit;
    }

    $gregorian_date = jdate_to_gregorian($jalali_date);
    
    if (!$gregorian_date) {
        echo json_encode(['success' => false, 'message' => 'تاریخ نامعتبر']);
        exit;
    }

    try {
$stmt = $pdo->prepare("
    SELECT 
        u.id, 
        u.full_name, 
        u.username AS student_code,
        a.status AS raw_status,
        a.is_present
    FROM users u
    JOIN class_students cs ON u.id = cs.user_id
    LEFT JOIN attendance a 
        ON u.id = a.user_id
        AND a.class_id = ?
        AND a.class_date = ?
    WHERE 
        cs.class_id = ?
        AND u.role = 'student'
        AND u.is_deleted = 0
    ORDER BY u.full_name
");
$stmt->execute([$class_id, $gregorian_date, $class_id]);

        $students = [];
        $attendance = [];
        while ($row = $stmt->fetch()) {
            $students[] = [
                'id' => $row['id'],
                'full_name' => $row['full_name'],
                'student_code' => $row['student_code']
            ];
            $attendance[$row['id']] = function_exists('rollcall_normalize_status')
                ? rollcall_normalize_status($row['raw_status'] ?? '', $row['is_present'] ?? null)
                : (($row['raw_status'] ?? '') === 'present' || (int)($row['is_present'] ?? 0) === 1 ? 'present' : 'absent');
        }

        echo json_encode(['success' => true, 'students' => $students, 'attendance' => $attendance]);
        exit;

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'خطا']);
        exit;
    }
}


if (!empty($input_data['action']) && $input_data['action'] == 'save_edit') {
    require_once '../../includes/db.php';
    require_once '../../includes/jdate.php';
    require_once '../../includes/rollcall.php';
    header('Content-Type: application/json');
    
    $class_id = (int)($input_data['class_id'] ?? 0);
    $jalali_date = $input_data['date'] ?? '';
    $attendance = $input_data['attendance'] ?? [];

    if (!$class_id || !$jalali_date) {
        echo json_encode(['success' => false, 'message' => 'داده نامعتبر']);
        exit;
    }

    $gregorian_date = jdate_to_gregorian($jalali_date);
    if (!$gregorian_date) {
        echo json_encode(['success' => false, 'message' => 'تاریخ شمسی نامعتبر']);
        exit;
    }

    try {
        rollcall_save_and_notify($pdo, $class_id, $gregorian_date, $jalali_date, $attendance, [
            'is_edit' => true,
            'source' => 'teacher',
        ]);
        echo json_encode(['success' => true]);
        exit;

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        echo json_encode(['success' => false, 'message' => 'خطا در ذخیره: ' . $e->getMessage()]);
        exit;
    }
}


$title = 'Roll Call';
require_once 'layout.php';
require_once '../../includes/db.php';
require_once '../../includes/jdate.php';

$class_id = (int)($_GET['class_id'] ?? 0);
$day = $_GET['day'] ?? 'saturday';

if (!$class_id) {
    header("Location: attendance.php?error=کلاسی انتخاب نشده است.");
    exit;
}

$stmt = $pdo->prepare("SELECT title FROM classes WHERE id = ?");
$stmt->execute([$class_id]);
$class_title = $stmt->fetchColumn();

$stmt = $pdo->prepare("
    SELECT 
        u.id, 
        u.full_name, 
        u.username AS student_code
    FROM users u
    JOIN class_students cs ON u.id = cs.user_id
    WHERE 
        cs.class_id = ?
        AND u.role = 'student'
        AND u.is_deleted = 0
    ORDER BY u.full_name
");
$stmt->execute([$class_id]);
$students = $stmt->fetchAll();


$stmt = $pdo->prepare("
    SELECT DISTINCT class_date 
    FROM attendance 
    WHERE class_id = ? 
    ORDER BY class_date DESC
");
$stmt->execute([$class_id]);
$dates = $stmt->fetchAll();

$today = date('Y-m-d');
?>


<div class="modal fade" id="editAttendanceModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p id="modal-date-display" class="mb-3 text-white"></p>
                <input type="hidden" id="edit-class-id" value="<?= $class_id ?>">
                <input type="hidden" id="edit-class-date">
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>Learner's name</th>
                                <th>ID</th>
                                <th>Status</th>
                                <th>Assignment</th>
                            </tr>
                        </thead>
                        <tbody id="modal-students-body"></tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
			    <button type="button" class="btn btn-danger" id="delete-attendance-btn">حذف تاریخ</button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-info" id="save-attendance-btn">Save</button>
            </div>
        </div>
    </div>
</div>
<link href="../../assets/css/teacher/attendance.css?v=<?= time() ?>" rel="stylesheet">
<link href="../../assets/css/teacher/homework.css?v=<?= time() ?>" rel="stylesheet">
<div class="card students-card">
    <div class="card-header text-white">
        <h5>Roll Call: <?= htmlspecialchars($class_title) ?></h5>
    </div>
    <div class="card-body">
        <div class="mb-4">
            <h6 class="text-info">Dates</h6>
            <?php if (!empty($dates)): ?>
                <div class="d-flex flex-wrap gap-2 btndate">
                    <?php foreach ($dates as $d): ?>
                        <button type="button" class=" btn btn-sm btn-outline-secondary"
                                data-bs-toggle="modal" data-bs-target="#editAttendanceModal"
                                onclick="setEditDate('<?= jdate_format($d['class_date'], 'Y/m/d') ?>')">
                            <?= jdate_format($d['class_date'], 'Y/m/d') ?>
                        </button>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="text-muted">No roll call admitted</p>
            <?php endif; ?>
        </div>

        <form method="POST" action="save-attendance.php">
            <input type="hidden" name="class_id" value="<?= $class_id ?>">
            <div class="mb-3">
                <label class="form-label">Class Dates</label>
				<input type="text" name="class_date" id="class_date" 
				value="<?= jdate_format($today, 'Y/m/d') ?>" 
				class="form-control" 
				placeholder="روز/ماه/سال شمسی" 
				required>
            </div>

            <?php if (!empty($students)): ?>
                <div class="table-responsive">
                    <table class="table table-bordered attendance-table">
                        <thead class="table-light">
                            <tr>
                                <th>Learner's Name</th>
                                <th>ID</th>
                                <th>Status</th>
                                <th>Assignment</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($students as $s): ?>
                            <tr>
                                <td>
                                    <div class="learner-name-row">
                                        <span><?= htmlspecialchars($s['full_name']) ?></span>
                                        <button type="button" class="btn btn-sm late-sms-btn"
                                                data-user="<?= (int)$s['id'] ?>"
                                                data-name="<?= htmlspecialchars($s['full_name'], ENT_QUOTES, 'UTF-8') ?>">
                                            ثبت تاخیر
                                        </button>
                                    </div>
                                </td>
                                <td><?= htmlspecialchars($s['student_code']) ?></td>
                                <td>
                                    <div class="d-flex gap-2 flex-wrap">
                                        <button type="button" class="btn btn-attendance btn-present active"
                                                data-user="<?= (int)$s['id'] ?>" data-status="present"
                                                onclick="setMainStatus(<?= (int)$s['id'] ?>, 'present')">
                                            Present
                                        </button>
                                        <button type="button" class="btn btn-attendance btn-absent"
                                                data-user="<?= (int)$s['id'] ?>" data-status="absent"
                                                onclick="setMainStatus(<?= (int)$s['id'] ?>, 'absent')">
                                            Absent
                                        </button>
                                    </div>
                                    <input type="hidden" name="attendance[<?= (int)$s['id'] ?>]" data-attendance-user="<?= (int)$s['id'] ?>" value="present">
                                </td>
                                <td class="assignment-cell">
                                    <div class="d-flex gap-2 flex-wrap">
                                        <button type="button" class="btn btn-homework btn-complete active"
                                                data-student-id="<?= (int)$s['id'] ?>"
                                                data-status="complete"
                                                onclick="homework_setMainStatus(<?= (int)$s['id'] ?>, 'complete')">
                                            Completed
                                        </button>
                                        <button type="button" class="btn btn-homework btn-incomplete"
                                                data-student-id="<?= (int)$s['id'] ?>"
                                                data-status="incomplete"
                                                onclick="homework_setMainStatus(<?= (int)$s['id'] ?>, 'incomplete')">
                                            Incomplete
                                        </button>
                                        <button type="button" class="btn btn-homework btn-not-submitted"
                                                data-student-id="<?= (int)$s['id'] ?>"
                                                data-status="not_submitted"
                                                onclick="homework_setMainStatus(<?= (int)$s['id'] ?>, 'not_submitted')">
                                            Not presented
                                        </button>
                                    </div>
                                    <input type="hidden" name="homework[<?= (int)$s['id'] ?>]" data-homework-user="<?= (int)$s['id'] ?>" value="complete">
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <button type="submit" class="btn attendance-action-btn btn-warning mt-3">Roll Call</button>
            <?php endif; ?>
            <a href="attendance.php" class="btn attendance-action-btn btn-secondary mt-3">Back</a>
        </form>
    </div>
</div>

<?php require_once 'layout-end.php'; ?>

<script>
// تنظیم دکمه حذف بعد از باز شدن مودال
function homeworkButtonsHtml(studentId, status, labels) {
    const current = status || 'complete';
    const completeActive = current === 'complete' ? 'active' : '';
    const incompleteActive = current === 'incomplete' ? 'active' : '';
    const notSubmittedActive = current === 'not_submitted' ? 'active' : '';
    return `
        <div class="d-flex gap-2 flex-wrap">
            <button type="button" class="btn btn-homework btn-complete ${completeActive}"
                    data-student="${studentId}" data-status="complete"
                    onclick="homework_updateModalStatus('${studentId}', 'complete')">${labels.complete}</button>
            <button type="button" class="btn btn-homework btn-incomplete ${incompleteActive}"
                    data-student="${studentId}" data-status="incomplete"
                    onclick="homework_updateModalStatus('${studentId}', 'incomplete')">${labels.incomplete}</button>
            <button type="button" class="btn btn-homework btn-not-submitted ${notSubmittedActive}"
                    data-student="${studentId}" data-status="not_submitted"
                    onclick="homework_updateModalStatus('${studentId}', 'not_submitted')">${labels.not_submitted}</button>
        </div>
        <input type="hidden" id="homework-status-${studentId}" value="${current}">`;
}

function postHomework(date, homework) {
    const fd = new FormData();
    fd.append('class_id', '<?= (int)$class_id ?>');
    fd.append('homework_date', date);
    Object.keys(homework).forEach(function (id) {
        fd.append('homework[' + id + ']', homework[id]);
    });
    return fetch('save-homework.php', { method: 'POST', body: fd, redirect: 'manual' });
}

function collectMainHomework() {
    const homework = {};
    document.querySelectorAll('form[action="save-attendance.php"] [data-homework-user]').forEach(function (input) {
        homework[input.getAttribute('data-homework-user')] = input.value;
    });
    return homework;
}

function collectModalHomework() {
    const homework = {};
    document.querySelectorAll('#modal-students-body input[id^="homework-status-"]').forEach(function (input) {
        homework[input.id.replace('homework-status-', '')] = input.value;
    });
    return homework;
}

function setEditDate(date) {
    document.getElementById('modal-date-display').textContent = 'تاریخ: ' + date;
    document.getElementById('edit-class-date').value = date;
    
    document.getElementById('delete-attendance-btn').onclick = () => deleteAttendanceDate(date);

    Promise.all([
        fetch('?action=get_data&class_id=<?= $class_id ?>&date=' + encodeURIComponent(date)).then(r => r.json()),
        fetch('homework-students.php?action=get_data&class_id=<?= $class_id ?>&date=' + encodeURIComponent(date)).then(r => r.json()).catch(() => ({ success: false }))
    ]).then(([data, hwData]) => {
        if (!data.success) {
            return;
        }
        const homeworkMap = (hwData && hwData.success && hwData.homework) ? hwData.homework : {};
        let html = '';
        data.students.forEach(s => {
            const status = (data.attendance[s.id] || 'absent').toLowerCase();
            const presentActive = status === 'present' ? 'active' : '';
            const absentActive = status === 'absent' ? 'active' : '';
            const hwStatus = homeworkMap[s.id] || 'not_submitted';
            html += `
            <tr>
                <td>
                    <div class="learner-name-row">
                        <span>${s.full_name}</span>
                        <button type="button" class="btn btn-sm late-sms-btn"
                                data-user="${s.id}"
                                data-name="${s.full_name}">ثبت تاخیر</button>
                    </div>
                </td>
                <td>${s.student_code}</td>
                <td>
                    <div class="d-flex gap-2 flex-wrap">
                        <button type="button" class="btn btn-attendance btn-present ${presentActive}"
                                data-user="${s.id}" data-status="present"
                                onclick="toggleStatus('${s.id}', 'present')">
                            حاضر
                        </button>
                        <button type="button" class="btn btn-attendance btn-absent ${absentActive}"
                                data-user="${s.id}" data-status="absent"
                                onclick="toggleStatus('${s.id}', 'absent')">
                            غایب
                        </button>
                    </div>
                    <input type="hidden" id="status-${s.id}" value="${status}">
                </td>
                <td class="assignment-cell">
                    ${homeworkButtonsHtml(s.id, hwStatus, {complete: 'کامل', incomplete: 'ناقص', not_submitted: 'عدم ارائه'})}
                </td>
            </tr>`;
        });
        document.getElementById('modal-students-body').innerHTML = html;
        document.getElementById('save-attendance-btn').onclick = () => saveEdit(date);
    });
}

// حذف تاریخ حضور و غیاب
function deleteAttendanceDate(date) {
    if (!confirm(`آیا مطمئن هستید که می‌خواهید تاریخ ${date} را حذف کنید؟\nاین عمل غیرقابل بازگشت است.`)) {
        return;
    }
    
    fetch('', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({
            action: 'delete_date',
            class_id: <?= $class_id ?>,
            date: date
        })
    })
    .then(response => response.json())
    .then(data => {
        alert(data.message);
        if (data.success) {
            bootstrap.Modal.getInstance(document.getElementById('editAttendanceModal')).hide();
            location.reload();
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('خطا در حذف تاریخ.');
    });
}

function toggleStatus(studentId, newStatus) {
    const hidden = document.getElementById('status-' + studentId);
    if (hidden) {
        hidden.value = newStatus;
    }
    document.querySelectorAll(`#modal-students-body .btn-attendance[data-user="${studentId}"]`).forEach(btn => {
        btn.classList.toggle('active', btn.getAttribute('data-status') === newStatus);
    });
}

function setMainStatus(studentId, status) {
    const hiddenInput = document.querySelector(`form[action="save-attendance.php"] [data-attendance-user="${studentId}"]`);
    if (hiddenInput) {
        hiddenInput.value = status;
    }
    document.querySelectorAll(`form[action="save-attendance.php"] .btn-attendance[data-user="${studentId}"]`).forEach(btn => {
        btn.classList.toggle('active', btn.getAttribute('data-status') === status);
    });
}

function homework_setMainStatus(studentId, status) {
    const hiddenInput = document.querySelector(`form[action="save-attendance.php"] [data-homework-user="${studentId}"]`);
    if (hiddenInput) {
        hiddenInput.value = status;
    }
    document.querySelectorAll(`form[action="save-attendance.php"] button[data-student-id="${studentId}"]`).forEach(btn => {
        btn.classList.toggle('active', btn.getAttribute('data-status') === status);
    });
}

function homework_updateModalStatus(studentId, status) {
    const hidden = document.getElementById('homework-status-' + studentId);
    if (hidden) {
        hidden.value = status;
    }
    document.querySelectorAll(`#modal-students-body button[data-student="${studentId}"]`).forEach(btn => {
        btn.classList.toggle('active', btn.getAttribute('data-status') === status);
    });
}

function saveEdit(date) {
    const attendance = {};
    document.querySelectorAll('#modal-students-body input[id^="status-"]').forEach(input => {
        const studentId = input.id.replace('status-', '');
        attendance[studentId] = input.value;
    });
    const homework = collectModalHomework();

    Promise.all([
        fetch('?action=save_edit', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({action: 'save_edit', class_id: <?= $class_id ?>, date, attendance})
        }).then(r => r.json()),
        postHomework(date, homework)
    ])
    .then(([data]) => {
        if (data.success) {
            alert('تغییرات ذخیره شد.');
            bootstrap.Modal.getInstance(document.getElementById('editAttendanceModal')).hide();
        } else {
            alert('خطا: ' + (data.message || 'نا مشخص'));
        }
    })
    .catch(error => {
        console.error('خطا:', error);
        alert('خطا در ارتباط با سرور.');
    });
}

function sendLateSms(btn) {
    const userId = btn.getAttribute('data-user');
    const name = btn.getAttribute('data-name') || 'این دانش‌آموز';
    if (!userId || !confirm('پیامک تاخیر برای «' + name + '» ارسال شود؟')) {
        return;
    }
    const inModal = !!btn.closest('#modal-students-body');
    const dateInput = inModal
        ? document.getElementById('edit-class-date')
        : document.getElementById('class_date');
    const date = dateInput && dateInput.value ? dateInput.value : '';
    btn.disabled = true;
    fetch('', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({
            action: 'send_late',
            class_id: <?= (int)$class_id ?>,
            user_id: Number(userId),
            date: date
        })
    })
    .then(r => r.json())
    .then(data => {
        alert(data.message || (data.ok ? 'ارسال شد.' : 'خطا در ارسال.'));
        if (data.ok) {
            btn.classList.add('is-sent');
            btn.textContent = 'تاخیر ثبت شد';
            const idNum = Number(userId);
            setMainStatus(idNum, 'present');
            if (inModal && typeof toggleStatus === 'function') {
                toggleStatus(String(userId), 'present');
            }
        } else {
            btn.disabled = false;
        }
    })
    .catch(() => {
        alert('خطا در ارتباط با سرور.');
        btn.disabled = false;
    });
}

document.addEventListener('click', function (event) {
    const btn = event.target.closest('.late-sms-btn');
    if (!btn) {
        return;
    }
    event.preventDefault();
    sendLateSms(btn);
});

document.addEventListener('DOMContentLoaded', function() {
    var datePicker = new MdPersianDateTimePicker('class_date', {
        targetTextSelector: '#class_date',
        englishNumber: false,
        dateFormat: 'yyyy/mm/dd'
    });
    const form = document.querySelector('form[action="save-attendance.php"]');
    if (!form) {
        return;
    }
    let submittingRoll = false;
    form.addEventListener('submit', function (event) {
        if (submittingRoll) {
            return;
        }
        event.preventDefault();
        const dateInput = document.getElementById('class_date');
        const date = dateInput && dateInput.value ? dateInput.value : '';
        postHomework(date, collectMainHomework()).finally(function () {
            submittingRoll = true;
            HTMLFormElement.prototype.submit.call(form);
        });
    });
});

</script>

