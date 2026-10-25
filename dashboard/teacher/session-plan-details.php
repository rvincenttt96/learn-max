<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once '../../includes/db.php';
    require_once '../../includes/jdate.php';

    $class_id = (int)($_POST['class_id'] ?? 0);
    $user_id = (int)($_POST['user_id'] ?? 0);
    $teacher_id = $user_id;

    if (!$class_id || !$teacher_id) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'دسترسی غیرمجاز.']);
        exit;
    }

    $action = $_POST['action'] ?? '';

    $digit_map = [
        '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
        '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
        '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
        '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
    ];

    if ($action === 'save') {
        $session_date = strtr(trim($_POST['session_date'] ?? ''), $digit_map);
        $content = trim($_POST['content'] ?? '');
        $plan_id = (int)($_POST['plan_id'] ?? 0);
        $gregorian_date = $session_date ? jdate_to_gregorian($session_date) : false;

        if (empty($session_date) || empty($content) || !$gregorian_date) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'لطفاً تمامی فیلدها را به‌درستی پر کنید.']);
            exit;
        }

        try {
            if ($plan_id) {
                $own = $pdo->prepare("SELECT id FROM session_plans WHERE id = ? AND class_id = ? AND teacher_id = ?");
                $own->execute([$plan_id, $class_id, $teacher_id]);
                if (!$own->fetch()) {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => false, 'message' => 'برنامه مورد نظر یافت نشد.']);
                    exit;
                }

                $dup = $pdo->prepare("SELECT id FROM session_plans WHERE class_id = ? AND session_date = ? AND id != ?");
                $dup->execute([$class_id, $gregorian_date, $plan_id]);
                if ($dup->fetch()) {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => false, 'message' => 'برای این تاریخ قبلاً برنامه ثبت شده است.']);
                    exit;
                }

                $stmt = $pdo->prepare("
                    UPDATE session_plans
                    SET session_date = ?, content = ?
                    WHERE id = ? AND class_id = ? AND teacher_id = ?
                ");
                $stmt->execute([$gregorian_date, $content, $plan_id, $class_id, $teacher_id]);

                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'message' => 'برنامه جلسه به‌روز شد.']);
                exit;
            }

            $stmt = $pdo->prepare("
                INSERT INTO session_plans (class_id, teacher_id, session_date, content)
                VALUES (?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE content = ?, teacher_id = ?
            ");
            $stmt->execute([$class_id, $teacher_id, $gregorian_date, $content, $content, $teacher_id]);

            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'برنامه جلسه ثبت شد.']);
            exit;
        } catch (Exception $e) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'خطا در ثبت.']);
            exit;
        }
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id) {
            try {
                $stmt = $pdo->prepare("DELETE FROM session_plans WHERE id = ? AND class_id = ? AND teacher_id = ?");
                $stmt->execute([$id, $class_id, $teacher_id]);

                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'message' => 'برنامه جلسه حذف شد.']);
                exit;
            } catch (Exception $e) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'خطا در حذف.']);
                exit;
            }
        }

        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'شناسهٔ برنامه نامعتبر است.']);
        exit;
    }

    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'عملیات نامعتبر.']);
    exit;
}

$title = 'Session plan';
require_once 'layout.php';
require_once '../../includes/db.php';
require_once '../../includes/jdate.php';

$class_id = (int)($_GET['class_id'] ?? 0);
$teacher_id = $_SESSION['user_id'] ?? null;

if (!$class_id || !$teacher_id) {
    header("Location: session-plan.php?error=" . urlencode('دسترسی غیرمجاز.'));
    exit;
}

$stmt = $pdo->prepare("SELECT title FROM classes WHERE id = ? AND teacher_id = ?");
$stmt->execute([$class_id, $teacher_id]);
$class_title = $stmt->fetchColumn();

if (!$class_title) {
    header("Location: session-plan.php?error=" . urlencode('کلاس مورد نظر یافت نشد.'));
    exit;
}

$stmt = $pdo->prepare("
    SELECT id, session_date, content
    FROM session_plans
    WHERE class_id = ? AND teacher_id = ?
    ORDER BY session_date DESC, created_at DESC
");
$stmt->execute([$class_id, $teacher_id]);
$plans = $stmt->fetchAll();

$today = date('Y-m-d');
$today_jalali = jdate_format($today, 'Y/m/d');
$digit_map = [
    '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
    '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
    '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
    '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
];
$today_jalali = strtr($today_jalali, $digit_map);
?>
<link type="text/css" rel="stylesheet" href="../../assets/css/jalalidatepicker.min.css?v=lg13">
<script type="text/javascript" src="../../assets/js/jalalidatepicker.min.js?v=lg13"></script>
<link href="../../assets/css/teacher/session-plan.css?v=lg13" rel="stylesheet">

<div class="card session-details-card">
    <div class="card-header text-white">
        <h5>Session Plan <?= htmlspecialchars($class_title, ENT_QUOTES, 'UTF-8') ?></h5>
    </div>
    <div class="card-body">
        <form id="save-form" method="POST" class="session-form">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="class_id" value="<?= (int)$class_id ?>">
            <input type="hidden" name="user_id" value="<?= (int)$teacher_id ?>">
            <input type="hidden" name="plan_id" id="plan-id-input" value="">
            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <label class="form-label" for="session-date-input">Dates</label>
                    <input type="text" name="session_date" id="session-date-input"
                           value="<?= htmlspecialchars($today_jalali, ENT_QUOTES, 'UTF-8') ?>"
                           data-jdp readonly
                           class="form-control session-date-input"
                           placeholder="انتخاب تاریخ..."
                           required>
                </div>
                <div class="col-md-8">
                    <label class="form-label" for="content-input">Details</label>
                    <textarea name="content" id="content-input" class="form-control session-details-input" rows="3"
                              placeholder="write with details please..." required></textarea>
                </div>
            </div>
            <div class="session-form-actions">
                <button type="button" id="submit-btn" class="btn session-action-btn btn-primary">Confirm</button>
                <button type="button" id="cancel-edit-btn" class="btn session-action-btn btn-secondary d-none">Cancel</button>
                <a href="session-plan.php" class="btn session-action-btn btn-secondary">Back</a>
            </div>
        </form>

        <hr class="my-4">
        <h6 class="admitted-heading mb-3">Admitted Plans</h6>
        <?php if (!empty($plans)): ?>
            <div class="table-responsive">
                <table class="table table-bordered table-striped session-table">
                    <thead>
                        <tr>
                            <th class="col-no">No.</th>
                            <th class="col-date">Dates</th>
                            <th class="col-plan">Plans</th>
                            <th class="col-action">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($plans as $index => $plan): ?>
                        <tr id="plan-<?= (int)$plan['id'] ?>">
                            <td class="no-cell"><?= $index + 1 ?></td>
                            <td class="date-cell"><?= htmlspecialchars(jdate_format($plan['session_date'], 'Y/m/d'), ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="plan-cell"><?= htmlspecialchars($plan['content'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="action-cell">
                                <div class="plan-actions">
                                    <button type="button" class="btn btn-sm edit-btn" data-id="<?= (int)$plan['id'] ?>">Edit</button>
                                    <button type="button" class="btn btn-sm delete-btn" data-id="<?= (int)$plan['id'] ?>">حذف</button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="session-empty">No plans admitted yet.</div>
        <?php endif; ?>
    </div>
</div>

<?php require_once 'layout-end.php'; ?>
<script>
(function () {
    const CLASS_ID = <?= (int)$class_id ?>;
    const USER_ID = <?= (int)$teacher_id ?>;
    const TODAY_J = <?= json_encode($today_jalali, JSON_UNESCAPED_UNICODE) ?>;

    function el(id) {
        return document.getElementById(id);
    }

    let pickerReady = false;

    function toEnDigits(value) {
        const map = {
            '۰': '0', '۱': '1', '۲': '2', '۳': '3', '۴': '4',
            '۵': '5', '۶': '6', '۷': '7', '۸': '8', '۹': '9',
            '٠': '0', '١': '1', '٢': '2', '٣': '3', '٤': '4',
            '٥': '5', '٦': '6', '٧': '7', '٨': '8', '٩': '9'
        };
        return String(value || '').replace(/[۰-۹٠-٩]/g, function (ch) {
            return map[ch] || ch;
        });
    }

    function openDatepicker() {
        const input = el('session-date-input');
        if (!input || !window.jalaliDatepicker || typeof jalaliDatepicker.show !== 'function') {
            return;
        }
        input.value = toEnDigits(input.value);
        jalaliDatepicker.show(input);
    }

    function initDatepicker() {
        if (!window.jalaliDatepicker || typeof jalaliDatepicker.startWatch !== 'function') {
            return;
        }
        try {
            jalaliDatepicker.startWatch({
                minDate: "attr",
                maxDate: "attr",
                time: false,
                hideAfterChange: true,
                autoHide: true,
                autoShow: true,
                showTodayBtn: true,
                showEmptyBtn: false,
                zIndex: 4000
            });
        } catch (err) {}
        const input = el('session-date-input');
        if (input && !pickerReady) {
            pickerReady = true;
            input.value = toEnDigits(input.value);
            input.addEventListener('click', function (event) {
                event.stopPropagation();
                openDatepicker();
            });
            input.addEventListener('focus', openDatepicker);
        }
    }

    function resetForm() {
        const form = el('save-form');
        const submitBtn = el('submit-btn');
        const cancelBtn = el('cancel-edit-btn');
        el('plan-id-input').value = '';
        el('session-date-input').value = TODAY_J;
        el('content-input').value = '';
        submitBtn.textContent = 'Confirm';
        cancelBtn.classList.add('d-none');
        form.classList.remove('is-editing');
        document.querySelectorAll('.session-table tbody tr.is-editing').forEach(function (row) {
            row.classList.remove('is-editing');
        });
    }

    function postForm(formData, onOk) {
        fetch('', { method: 'POST', body: formData })
            .then(function (response) { return response.json(); })
            .then(function (data) {
                if (data.success) {
                    alert(data.message);
                    onOk();
                } else {
                    alert(data.message);
                }
            })
            .catch(function () {
                alert('خطا در ارتباط با سرور.');
            });
    }

    function loadRow(btn) {
        const row = btn.closest('tr');
        if (!row) {
            return;
        }
        const dateCell = row.querySelector('.date-cell');
        const planCell = row.querySelector('.plan-cell');
        el('plan-id-input').value = btn.getAttribute('data-id') || '';
        el('session-date-input').value = toEnDigits((dateCell ? dateCell.textContent : '').trim());
        el('content-input').value = planCell ? planCell.innerText.replace(/\r\n/g, '\n').trim() : '';
        el('submit-btn').textContent = 'Update';
        el('cancel-edit-btn').classList.remove('d-none');
        el('save-form').classList.add('is-editing');
        document.querySelectorAll('.session-table tbody tr.is-editing').forEach(function (item) {
            item.classList.remove('is-editing');
        });
        row.classList.add('is-editing');
        el('save-form').scrollIntoView({ behavior: 'smooth', block: 'start' });
        el('content-input').focus();
    }

    document.addEventListener('click', function (event) {
        const editBtn = event.target.closest('.edit-btn');
        if (editBtn) {
            event.preventDefault();
            loadRow(editBtn);
            return;
        }
        const deleteBtn = event.target.closest('.delete-btn');
        if (deleteBtn) {
            event.preventDefault();
            const id = deleteBtn.getAttribute('data-id');
            if (!id || !confirm('آیا مطمئنید؟')) {
                return;
            }
            const formData = new FormData();
            formData.append('action', 'delete');
            formData.append('id', id);
            formData.append('class_id', CLASS_ID);
            formData.append('user_id', USER_ID);
            postForm(formData, function () {
                window.location.reload();
            });
            return;
        }
        if (event.target.closest('#submit-btn')) {
            event.preventDefault();
            postForm(new FormData(el('save-form')), function () {
                window.location.reload();
            });
            return;
        }
        if (event.target.closest('#cancel-edit-btn')) {
            event.preventDefault();
            resetForm();
        }
    });

    initDatepicker();
    window.addEventListener('load', initDatepicker);
})();
</script>
