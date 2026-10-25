<?php
$title = 'Roll Call';
require_once 'layout.php';
require_once '../../includes/db.php';
require_once '../../includes/terms.php';
require_once '../../includes/jdate.php';
require_role('supervisor');

$tab = $_GET['tab'] ?? 'today';
$tab = in_array($tab, ['today', 'odd', 'even']) ? $tab : 'today';

$today_weekday = jdate('w');
$is_today_odd = in_array($today_weekday, [1, 3, 5]);

if ($tab === 'today') {
    if ($today_weekday == 6) {
        $schedule_condition = '1=0';
    } else {
        $schedule_condition = $is_today_odd ? "c.schedule_type = 'odd'" : "c.schedule_type = 'even'";
    }
} elseif ($tab === 'odd') {
    $schedule_condition = "c.schedule_type = 'odd'";
} else {
    $schedule_condition = "c.schedule_type = 'even'";
}

$stmt = $pdo->prepare("
    SELECT c.id, c.title, c.schedule, c.schedule_type, u.full_name as teacher_name, t.title as term_title
    FROM classes c
    JOIN users u ON c.teacher_id = u.id
    LEFT JOIN terms t ON c.term_id = t.id
    WHERE (" . term_sql_teaching('t') . " OR c.term_id IS NULL)
    AND IFNULL(c.is_archived, 0) = 0
    AND c.is_deleted = 0
    AND ($schedule_condition)
    ORDER BY c.title
");
$stmt->execute();
$classes = $stmt->fetchAll();

$class_sessions = [];
foreach ($classes as $class) {
    $stmt = $pdo->prepare("
        SELECT DISTINCT class_date 
        FROM attendance 
        WHERE class_id = ? 
        AND class_date != '0000-00-00' 
        AND class_date IS NOT NULL
        AND class_date >= '1900-01-01'
        ORDER BY class_date ASC
        LIMIT 14
    ");
    $stmt->execute([$class['id']]);
    $sessions = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    sort($sessions);
    
    while (count($sessions) < 14) {
         $sessions[] = null; 
    }
    
    $class_sessions[$class['id']] = $sessions;
}
?>

<link href="../../assets/css/supervisor/supervisor-attendance.css?v=<?= time() ?>" rel="stylesheet">

<ul class="nav nav-tabs mb-4">
    <li class="nav-item">
        <a class="nav-link  text-white <?= $tab == 'odd' ? 'active' : '' ?>" href="?tab=odd">Sun/Tue/Thu</a>
    </li>
    <li class="nav-item">
        <a class="nav-link text-white <?= $tab == 'even' ? 'active' : '' ?>" href="?tab=even">Sat/Mon/Wed</a>
    </li>
</ul>

<?php if (empty($classes)): ?>
    <div class="card">
        <div class="card-body text-center py-5">
            <div style="font-size: 3rem; margin-bottom: 20px;">
                <?= $tab == 'today' ? '📅' : ($tab == 'odd' ? '🔢' : '🔢') ?>
            </div>
            <h4>
                <?php
                if ($tab == 'today') {
                    echo $today_weekday == 6 ? 'امروز جمعه است' : 'کلاسی برای امروز یافت نشد';
                } else {
                    echo 'کلاسی یافت نشد';
                }
                ?>
            </h4>
            <p class="text-light">
                <?php
                if ($tab == 'today') {
                    if ($today_weekday == 6) {
                        echo 'جمعه روز تعطیل است.';
                    } else {
                        echo 'کلاسی برای روز ' . ($is_today_odd ? 'فرد' : 'زوج') . ' تعریف نشده است.';
                    }
                } else {
                    echo 'برای مشاهده حضور و غیاب، کلاسی تعریف کنید.';
                }
                ?>
            </p>
        </div>
    </div>
<?php else: ?>
<div class="card">
        <div class="attendance-table-container">
            <table class="attendance-table">
                <thead>
                    <tr>
                        <th>Classes</th>
                        <th>Instructors</th>
                        <?php for ($i = 1; $i <= 14; $i++): ?>
                            <th>S. <?= $i ?></th>
                        <?php endfor; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($classes as $class): ?>
                    <tr>
                        <td class="class-info-cell">
                            <div class="class-title"><?= htmlspecialchars($class['title']) ?><?= class_live_dot($class) ?></div>
                            <div class="teacher-name"><?= htmlspecialchars($class['teacher_name']) ?></div>
                        </td>
                        <td class="class-info-cell">
                            <div class="teacher-name"><?= htmlspecialchars($class['teacher_name']) ?></div>
                        </td>
                        <?php for ($i = 0; $i < 14; $i++): ?>
                            <?php 
                            $date = $class_sessions[$class['id']][$i];
                            if ($date): 
                                $stmt = $pdo->prepare("
                                    SELECT 
                                        SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present_count,
                                        COUNT(*) as total_count
                                    FROM attendance 
                                    WHERE class_id = ? AND class_date = ?
                                ");
                                $stmt->execute([$class['id'], $date]);
                                $stats = $stmt->fetch();
                                
                                if ($stats && $stats['total_count'] > 0) {
                                    $present = (int)$stats['present_count'];
                                    $total = (int)$stats['total_count'];
                                    $absent = $total - $present;
                                    
                                    if ($present == $total) {
                                        $status_class = 'present';
                                        $text_class = 'text-present';
                                    } elseif ($absent == $total) {
                                        $status_class = 'absent';
                                        $text_class = 'text-absent';
                                    } else {
                                        $status_class = 'partial';
                                        $text_class = 'text-partial';
                                    }
                                } else {
                                    $status_class = 'empty';
                                    $text_class = 'text-empty';
                                    $present = 0;
                                    $total = 0;
                                }
                            ?>
                            <td>
                                <div class="session-cell <?= $status_class ?>"
                                     data-class-id="<?= (int)$class['id'] ?>"
                                     data-date="<?= htmlspecialchars($date, ENT_QUOTES, 'UTF-8') ?>"
                                     onclick="showAttendanceDetails(<?= (int)$class['id'] ?>, '<?= htmlspecialchars($date, ENT_QUOTES, 'UTF-8') ?>')">
                                    <span class="session-date"><?= jdate('m/d', strtotime($date)) ?></span>
                                    <?php if ($total > 0): ?>
                                        <span class="session-stats <?= $text_class ?>"><?= $present ?>/<?= $total ?></span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <?php else: ?>
                            <td>
                                <div class="session-cell empty-session">
                                    <span class="session-date">-</span>
                                </div>
                            </td>
                            <?php endif; ?>
                        <?php endfor; ?>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<div class="modal fade" id="attendanceModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content glass-modal">
            <div class="modal-header glass-header">
                <h5 class="modal-title">جزئیات حضور و غیاب</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body glass-body">
                <div id="attendance-details-content">
                    <div class="text-center py-4">
                        <div class="spinner-border text-light" role="status">
                            <span class="visually-hidden">در حال بارگذاری...</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function rcEscapeHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, function (ch) {
        return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[ch];
    });
}
function rcJsString(value) {
    return String(value ?? '').replace(/\\/g, '\\\\').replace(/'/g, "\\'");
}
function rcFlash(ok, message) {
    const messageDiv = document.createElement('div');
    messageDiv.className = ok ? 'alert alert-success' : 'alert alert-danger';
    messageDiv.textContent = message || (ok ? 'ذخیره شد.' : 'خطا');
    messageDiv.style.margin = '10px 0';
    const modalBody = document.querySelector('#attendanceModal .modal-body');
    if (!modalBody) return;
    modalBody.insertBefore(messageDiv, modalBody.firstChild);
    setTimeout(function () {
        if (messageDiv.parentNode) messageDiv.parentNode.removeChild(messageDiv);
    }, 3500);
}
function applySessionStats(classId, date, present, total) {
    const cell = document.querySelector('.session-cell[data-class-id="' + classId + '"][data-date="' + date + '"]');
    if (!cell) return;
    cell.classList.remove('present', 'absent', 'partial', 'empty');
    let statusClass = 'empty';
    let textClass = 'text-empty';
    present = Number(present || 0);
    total = Number(total || 0);
    if (total > 0) {
        if (present === total) {
            statusClass = 'present';
            textClass = 'text-present';
        } else if (present === 0) {
            statusClass = 'absent';
            textClass = 'text-absent';
        } else {
            statusClass = 'partial';
            textClass = 'text-partial';
        }
    }
    cell.classList.add(statusClass);
    let stats = cell.querySelector('.session-stats');
    if (total > 0) {
        if (!stats) {
            stats = document.createElement('span');
            stats.className = 'session-stats';
            cell.appendChild(stats);
        }
        stats.textContent = present + '/' + total;
        stats.className = 'session-stats ' + textClass;
    } else if (stats) {
        stats.remove();
    }
}

function showAttendanceDetails(classId, date) {
    fetch('get-attendance-details.php?class_id=' + encodeURIComponent(classId) + '&date=' + encodeURIComponent(date))
    .then(function (response) { return response.json(); })
    .then(function (data) {
        if (data.success) {
            if (typeof data.present !== 'undefined') {
                applySessionStats(classId, date, data.present, data.total);
            }
            let html = `
            <div class="attendance-header mb-4 p-3 bg-primary bg-opacity-10 rounded">
                <h6 class="mb-1">کلاس: ${rcEscapeHtml(data.class_info.title)}</h6>
                <p class="mb-0">تاریخ: ${rcEscapeHtml(data.jalali_date)}</p>
                <p class="mb-0 mt-2 small">وضعیت غایب و حاضر را از همین جدول تغییر بدهید.</p>
            </div>
            <div class="table-responsive">
                <table class="table table-bordered">
                    <thead class="table-light">
                        <tr class="text-dark">
                            <th>نام دانش‌آموز</th>
                            <th>کد دانش‌آموز</th>
                            <th>وضعیت</th>
                            <th>پیگیری</th>
                        </tr>
                    </thead>
                    <tbody>`;

            (data.students || []).forEach(function (student) {
                const isPresent = student.status === 'present';
                const statusClass = isPresent ? 'text-success' : 'text-danger';
                const statusText = isPresent ? 'حاضر' : 'غایب';
                const safeName = rcJsString(student.full_name);
                const reason = String(student.reason || '').trim();

                html += `
                <tr>
                    <td>
                        <div class="learner-name-row">
                            <span>${rcEscapeHtml(student.full_name)}</span>
                            <button type="button" class="btn btn-sm late-sms-btn"
                                    onclick="sendLateSms(${Number(student.id)}, ${Number(classId)}, '${rcJsString(date)}', '${safeName}')">ثبت تاخیر</button>
                        </div>
                    </td>
                    <td>${rcEscapeHtml(student.student_code)}</td>
                    <td>
                        <div class="rc-status-cell">
                            <span class="${statusClass}">${statusText}</span>
                            ${isPresent
                                ? `<button type="button" class="btn btn-sm btn-outline-danger"
                                        onclick="setAttendanceStatus(${Number(student.id)}, ${Number(classId)}, '${rcJsString(date)}', 'absent', '${safeName}')">تغییر به غایب</button>`
                                : `<button type="button" class="btn btn-sm btn-success"
                                        onclick="setAttendanceStatus(${Number(student.id)}, ${Number(classId)}, '${rcJsString(date)}', 'present', '${safeName}')">تغییر به حاضر</button>`
                            }
                        </div>
                    </td>
                    <td>`;

                if (!isPresent) {
                    html += `<div class="rc-follow-actions">`;
                    if (reason) {
                        html += `<small class="text-warning d-block">📝 ${rcEscapeHtml(reason)}</small>`;
                    }
                    html += `
                        <button type="button" class="btn btn-sm ${reason ? 'btn-outline-warning' : 'btn-warning'}"
                                onclick="followUpAbsent(${Number(student.id)}, ${Number(classId)}, '${rcJsString(date)}')">
                            ${reason ? 'ویرایش علت' : 'پیگیری'}
                        </button>
                    </div>`;
                } else {
                    html += '<span class="text-muted">-</span>';
                }

                html += `
                    </td>
                </tr>`;
            });

            html += `
                    </tbody>
                </table>
            </div>`;

            document.getElementById('attendance-details-content').innerHTML = html;
        } else {
            document.getElementById('attendance-details-content').innerHTML =
                '<p class="text-center text-danger py-3">' + rcEscapeHtml(data.message || 'خطا در بارگذاری اطلاعات') + '</p>';
        }
    })
    .catch(function (error) {
        console.error('Error:', error);
        document.getElementById('attendance-details-content').innerHTML =
            '<p class="text-center text-danger py-3">خطا در ارتباط با سرور</p>';
    });

    const modal = new bootstrap.Modal(document.getElementById('attendanceModal'));
    modal.show();
}

function setAttendanceStatus(studentId, classId, date, status, name) {
    const label = name || 'این دانش‌آموز';
    const goingPresent = status === 'present';
    const confirmText = goingPresent
        ? 'وضعیت «' + label + '» به حاضر تغییر کند؟'
        : 'وضعیت «' + label + '» به غایب تغییر کند؟';
    if (!confirm(confirmText)) {
        return;
    }

    let reason = null;
    if (!goingPresent) {
        const typed = prompt('علت غیبت را وارد کنید (اختیاری):');
        if (typed === null) {
            return;
        }
        reason = String(typed).trim();
    }

    fetch('ajax-update-attendance.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({
            student_id: studentId,
            class_id: classId,
            date: date,
            status: status,
            reason: reason
        })
    })
    .then(function (response) { return response.json(); })
    .then(function (data) {
        rcFlash(!!(data.ok || data.success), data.message);
        if (data.ok || data.success) {
            applySessionStats(classId, date, data.present, data.total);
            setTimeout(function () { showAttendanceDetails(classId, date); }, 400);
        }
    })
    .catch(function (error) {
        console.error('Error:', error);
        rcFlash(false, 'خطا در تغییر وضعیت');
    });
}

function sendLateSms(studentId, classId, date, name) {
    const label = name || 'این دانش‌آموز';
    if (!confirm('پیامک تاخیر برای «' + label + '» ارسال شود؟')) {
        return;
    }
    fetch('ajax-send-late.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({
            student_id: studentId,
            class_id: classId,
            date: date
        })
    })
    .then(response => response.json())
    .then(data => {
        const messageDiv = document.createElement('div');
        messageDiv.className = (data.ok || data.success) ? 'alert alert-success' : 'alert alert-danger';
        messageDiv.textContent = data.message || ((data.ok || data.success) ? 'پیامک تاخیر ارسال شد.' : 'خطا در ارسال پیامک تاخیر');
        messageDiv.style.margin = '10px 0';
        const modalBody = document.querySelector('#attendanceModal .modal-body');
        modalBody.insertBefore(messageDiv, modalBody.firstChild);
        setTimeout(() => {
            if (messageDiv.parentNode) {
                messageDiv.parentNode.removeChild(messageDiv);
            }
        }, 4000);
        if (data.ok || data.success) {
            setTimeout(() => showAttendanceDetails(classId, date), 800);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('خطا در ارتباط با سرور.');
    });
}

// پیگیری دانش‌آموز غایب (ذخیره علت و ارسال پیامک خودکار)
function followUpAbsent(studentId, classId, date) {
    const reason = prompt('علت غیبت را وارد کنید:');
    if (reason && reason.trim()) {
        // تغییر فایل مقصد به فایلی که برای پیامک ساختیم
        fetch('ajax-followup-absence.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                student_id: studentId,
                class_id: classId,
                date: date,
                reason: reason.trim()
            })
        })
        .then(response => response.json())
        .then(data => {
            const messageDiv = document.createElement('div');
            messageDiv.className = data.success ? 'alert alert-success' : 'alert alert-danger';
            messageDiv.textContent = data.message;
            messageDiv.style.margin = '10px 0';
            
            const modalBody = document.querySelector('#attendanceModal .modal-body');
            modalBody.insertBefore(messageDiv, modalBody.firstChild);
            
            setTimeout(() => {
                if (messageDiv.parentNode) {
                    messageDiv.parentNode.removeChild(messageDiv);
                }
            }, 3000);
            
            if (data.success) {
                applySessionStats(classId, date, data.present, data.total);
                setTimeout(() => {
                    showAttendanceDetails(classId, date);
                }, 600);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            const messageDiv = document.createElement('div');
            messageDiv.className = 'alert alert-danger';
            messageDiv.textContent = 'خطا در ارتباط با سرور پیامک';
            messageDiv.style.margin = '10px 0';
            const modalBody = document.querySelector('#attendanceModal .modal-body');
            modalBody.insertBefore(messageDiv, modalBody.firstChild);
        });
    }
}

function markAsPresent(studentId, classId, date) {
    setAttendanceStatus(studentId, classId, date, 'present');
}

// حل مشکل لایه سیاه
document.addEventListener('hidden.bs.modal', function (event) {
    document.querySelectorAll('.modal-backdrop').forEach(el => el.remove());
});
</script>

<?php require_once 'layout-end.php'; ?>