<?php
$title = 'EAS آلمانی';
$current_page = 'index.php';
require_once 'layout.php';

$classes = german_load_classes($pdo);
$teachers = german_people($pdo, 'german_teacher');
$students = german_people($pdo, 'german_student');
$fees = german_fees_map($pdo);
$kinds = german_kinds();
$levels = german_levels();

$boot = [
    'page' => 'classes',
    'ajax' => 'ajax.php',
    'receipt' => 'receipt.php',
    'kinds' => $kinds,
    'levels' => $levels,
    'fees' => $fees,
    'teachers' => $teachers,
    'students' => $students,
];
?>
<link href="../../assets/css/jalalidatepicker.min.css" rel="stylesheet">
<script src="../../assets/js/jalalidatepicker.min.js"></script>

<div class="g-header">
    <div class="g-header-copy">
        <h1><span>EAS</span> آلمانی</h1>
        <p>کلاس‌های آلمانی جدا از انگلیسی‌اند. شهریه هر زبان‌آموز = تعداد جلسات × شهریه هر جلسه.</p>
    </div>
    <div class="g-header-actions">
        <div class="g-clock" id="gClock">
            <div class="g-clock-time" id="gClockTime">00<span>:</span>00</div>
            <div class="g-clock-date" id="gClockDate"> </div>
        </div>
        <button type="button" class="g-btn g-btn-primary" id="gAddClassBtn">+ کلاس تازه</button>
    </div>
</div>

<div class="g-toolbar">
    <input type="search" class="g-input" id="gSearch" placeholder="جستجو: کلاس، استاد، زبان‌آموز" autocomplete="off">
    <select class="g-input" id="gKindFilter">
        <option value="all">همه نوع‌ها</option>
        <?php foreach ($kinds as $key => $label): ?>
            <option value="<?= german_h($key) ?>"><?= german_h($label) ?></option>
        <?php endforeach; ?>
    </select>
    <select class="g-input" id="gLevelFilter">
        <option value="all">همه لول‌ها</option>
        <?php foreach ($levels as $level): ?>
            <option value="<?= german_h($level) ?>"><?= german_h($level) ?></option>
        <?php endforeach; ?>
    </select>
</div>

<?php if (!$classes): ?>
    <div class="g-empty" id="gEmpty">
        <h3>هنوز کلاس آلمانی نیست</h3>
        <p>با دکمه «کلاس تازه» اولین کلاس گروهی، خصوصی یا نیمه‌خصوصی را باز کنید. استاد و زبان‌آموز را همین‌جا دستی اضافه کنید.</p>
        <button type="button" class="g-btn g-btn-primary" data-open-class>افزودن کلاس</button>
    </div>
<?php endif; ?>

<div class="g-cards" id="gCards">
<?php foreach ($classes as $class):
    $kind = $class['kind'];
    $day = german_day_badge($class['schedule_type'] ?? '');
    $teacher_name = $class['teacher_name'] ?: 'بدون مدرس';
    $session_count = max(1, (int)$class['session_count']);
    $payload = [
        'id' => (int)$class['id'],
        'title' => $class['title'],
        'level' => $class['level'],
        'kind' => $kind,
        'session_count' => $session_count,
        'fee_per_session' => (int)$class['fee_per_session'],
        'teacher_id' => (int)$class['teacher_id'],
        'schedule' => $class['schedule'],
        'schedule_type' => $class['schedule_type'],
        'student_ids' => array_map(static function ($s) { return (int)$s['student_id']; }, $class['students']),
    ];
    $search = $class['title'] . ' ' . $teacher_name . ' ' . $class['level'] . ' ' . german_kind_label($kind);
    foreach ($class['students'] as $st) {
        $search .= ' ' . $st['full_name'] . ' ' . $st['username'];
    }
?>
    <article class="eas-card g-card"
             data-kind="<?= german_h($kind) ?>"
             data-level="<?= german_h($class['level']) ?>"
             data-classid="<?= (int)$class['id'] ?>"
             data-search="<?= german_h($search) ?>">
        <div class="card-header">
            <div class="class-info">
                <h2><?= german_h($class['title']) ?></h2>
                <div class="teacher">👨‍🏫 <?= german_h($teacher_name) ?><?php if (!empty($class['teacher_username'])): ?> <span class="student-file"><?= german_h($class['teacher_username']) ?></span><?php endif; ?></div>
            </div>
            <div class="meta-info">
                <div class="badges-wrapper">
                    <span class="type-badge <?= german_kind_badge($kind) ?>"><?= german_h(german_kind_label($kind)) ?></span>
                    <span class="type-badge type-level"><?= german_h($class['level']) ?></span>
                    <?php if ($day[1] !== ''): ?>
                        <span class="day-badge <?= $day[0] ?>"><?= german_h($day[1]) ?></span>
                    <?php endif; ?>
                </div>
                <?php if (trim((string)$class['schedule']) !== ''): ?>
                    <span class="time-badge"><?= german_h($class['schedule']) ?></span>
                <?php endif; ?>
            </div>
        </div>

        <div class="g-fee-strip">
            <span><?= $session_count ?> جلسه</span>
            <span>هر جلسه <?= german_money($class['fee_per_session']) ?> تومان</span>
            <span>شهریه کل <?= german_money((int)$class['session_count'] * (int)$class['fee_per_session']) ?> تومان</span>
        </div>

        <div class="sessions-container" data-class-id="<?= (int)$class['id'] ?>">
            <div class="sessions-head">
                <div class="section-title">📅 تشکیل جلسه (<?= $session_count ?> جلسه‌ای)</div>
            </div>
            <div class="sessions-grid">
                <?php for ($i = 1; $i <= $session_count; $i++):
                    $status = (int)($class['session_status'][(string)$i] ?? $class['session_status'][$i] ?? 0);
                    $status_class = $status === 1 ? 'status-tick' : ($status === 2 ? 'status-cross' : '');
                    $content = $status === 1 ? '✔' : ($status === 2 ? '✖' : (string)$i);
                    $saved_date = $class['session_dates'][(string)$i] ?? $class['session_dates'][$i] ?? '';
                ?>
                <div class="session-item">
                    <div class="session-box <?= $status_class ?>" data-num="<?= $i ?>"><?= $content ?></div>
                    <input type="text" data-jdp readonly class="date-input regular-date" placeholder="تاریخ" value="<?= german_h($saved_date) ?>">
                </div>
                <?php endfor; ?>
            </div>
        </div>

        <div>
            <div class="section-title">💳 زبان‌آموزان و شهریه</div>
            <?php if (!$class['students']): ?>
                <span class="student-badge g-muted">هنوز زبان‌آموزی به این کلاس اضافه نشده</span>
            <?php else: ?>
                <div class="g-pay-list">
                <?php foreach ($class['students'] as $st): ?>
                    <div class="g-pay-row">
                        <div class="g-pay-head">
                            <div>
                                <strong><?= german_h($st['full_name']) ?></strong>
                                <span class="student-file"><?= german_h($st['username']) ?></span>
                            </div>
                            <span class="g-status g-status-<?= german_h($st['status_key']) ?>"><?= german_h($st['status_label']) ?></span>
                        </div>
                        <div class="g-pay-meta">
                            <span>کل <?= german_money($st['fee_total']) ?></span>
                            <span>پرداخت <?= german_money($st['paid']) ?></span>
                            <span>مانده <?= german_money($st['debt']) ?></span>
                        </div>
                        <?php if (!empty($st['payments'])): ?>
                        <ul class="g-receipts">
                            <?php foreach ($st['payments'] as $pay): ?>
                            <li>
                                <span><?= german_money($pay['amount']) ?> تومان</span>
                                <span class="g-muted"><?= german_h($pay['created_at']) ?></span>
                                <?php if (!empty($pay['receipt_path'])): ?>
                                    <a href="receipt.php?id=<?= (int)$pay['id'] ?>" target="_blank" rel="noopener">رسید</a>
                                <?php endif; ?>
                                <button type="button" class="g-link-btn" data-del-pay="<?= (int)$pay['id'] ?>">حذف</button>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                        <?php endif; ?>
                        <form class="g-pay-form" data-class-id="<?= (int)$class['id'] ?>" data-student-id="<?= (int)$st['student_id'] ?>">
                            <input type="text" name="amount" class="g-input" inputmode="numeric" placeholder="مبلغ پرداخت" required>
                            <input type="text" name="note" class="g-input" placeholder="یادداشت">
                            <label class="g-file">
                                <span>رسید</span>
                                <input type="file" name="receipt" accept="image/jpeg,image/png,image/webp,application/pdf">
                            </label>
                            <button type="submit" class="g-btn g-btn-save">ثبت پرداخت</button>
                        </form>
                    </div>
                <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="g-card-foot">
            <button type="button" class="g-btn g-btn-ghost" data-edit-class="<?= german_h(json_encode($payload, JSON_UNESCAPED_UNICODE)) ?>">ویرایش کلاس</button>
            <button type="button" class="g-btn g-btn-danger" data-del-class="<?= (int)$class['id'] ?>">حذف کلاس</button>
        </div>
    </article>
<?php endforeach; ?>
</div>

<div class="g-sheet-root" id="classSheet" hidden>
    <div class="g-sheet-backdrop" data-close-sheet></div>
    <div class="g-sheet" role="dialog" aria-modal="true" aria-labelledby="classSheetTitle">
        <div class="g-sheet-head">
            <h2 id="classSheetTitle">کلاس تازه</h2>
            <button type="button" class="g-icon-btn" data-close-sheet aria-label="بستن">×</button>
        </div>
        <form id="classForm" class="g-sheet-body">
            <input type="hidden" name="class_id" id="fClassId" value="0">
            <label class="g-field">
                <span>عنوان کلاس</span>
                <input class="g-input" name="title" id="fTitle" placeholder="مثلاً A1 گروهی عصر">
            </label>
            <div class="g-grid-2">
                <label class="g-field">
                    <span>لول</span>
                    <select class="g-input" name="level" id="fLevel">
                        <?php foreach ($levels as $level): ?>
                            <option value="<?= german_h($level) ?>"><?= german_h($level) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="g-field">
                    <span>نوع کلاس</span>
                    <select class="g-input" name="kind" id="fKind">
                        <?php foreach ($kinds as $key => $label): ?>
                            <option value="<?= german_h($key) ?>"><?= german_h($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </div>
            <div class="g-grid-2">
                <label class="g-field">
                    <span>تعداد جلسات</span>
                    <input class="g-input" type="number" name="session_count" id="fSessions" min="1" max="40" value="10" required>
                </label>
                <label class="g-field" id="manualFeeWrap">
                    <span id="feeLabel">شهریه هر جلسه</span>
                    <input class="g-input" name="fee_per_session" id="fFee" inputmode="numeric" placeholder="برای نیمه‌خصوصی الزامی">
                </label>
            </div>
            <div class="g-fee-preview" id="feePreview"></div>
            <div class="g-grid-2">
                <label class="g-field">
                    <span>روز</span>
                    <select class="g-input" name="schedule_type" id="fDay">
                        <option value="">—</option>
                        <option value="odd">فرد</option>
                        <option value="even">زوج</option>
                    </select>
                </label>
                <label class="g-field">
                    <span>ساعت</span>
                    <input class="g-input" name="schedule" id="fSchedule" placeholder="۱۹:۳۰">
                </label>
            </div>
            <div class="g-field">
                <div class="g-field-row">
                    <span>استاد</span>
                    <button type="button" class="g-link-btn" data-quick="teacher">+ افزودن استاد</button>
                </div>
                <select class="g-input" name="teacher_id" id="fTeacher">
                    <option value="0">بدون مدرس</option>
                    <?php foreach ($teachers as $t): ?>
                        <option value="<?= (int)$t['id'] ?>"><?= german_h($t['full_name']) ?> — <?= german_h($t['username']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="g-field">
                <div class="g-field-row">
                    <span>زبان‌آموزان</span>
                    <button type="button" class="g-link-btn" data-quick="student">+ افزودن زبان‌آموز</button>
                </div>
                <input class="g-input" id="studentPickerSearch" type="search" placeholder="جستجوی زبان‌آموز" autocomplete="off">
                <div class="g-picker" id="studentPicker">
                    <?php if (!$students): ?>
                        <p class="g-muted" data-empty-students>هنوز زبان‌آموز آلمانی ثبت نشده. از دکمه بالا یکی بسازید.</p>
                    <?php endif; ?>
                    <?php foreach ($students as $s): ?>
                        <label class="g-pick" data-name="<?= german_h($s['full_name'] . ' ' . $s['username']) ?>">
                            <input type="checkbox" name="student_ids[]" value="<?= (int)$s['id'] ?>">
                            <span><?= german_h($s['full_name']) ?></span>
                            <span class="student-file"><?= german_h($s['username']) ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="g-sheet-actions">
                <button type="button" class="g-btn g-btn-ghost" data-close-sheet>انصراف</button>
                <button type="submit" class="g-btn g-btn-primary" id="classSaveBtn">ذخیره کلاس</button>
            </div>
        </form>
    </div>
</div>

<div class="g-sheet-root" id="personSheet" hidden>
    <div class="g-sheet-backdrop" data-close-person></div>
    <div class="g-sheet g-sheet-sm" role="dialog" aria-modal="true" aria-labelledby="personSheetTitle">
        <div class="g-sheet-head">
            <h2 id="personSheetTitle">افزودن فرد</h2>
            <button type="button" class="g-icon-btn" data-close-person aria-label="بستن">×</button>
        </div>
        <form id="personForm" class="g-sheet-body">
            <input type="hidden" name="role" id="pRole" value="german_student">
            <label class="g-field">
                <span>نام کامل</span>
                <input class="g-input" name="full_name" id="pName" required>
            </label>
            <label class="g-field">
                <span>تلفن</span>
                <input class="g-input" name="phone" id="pPhone" inputmode="tel">
            </label>
            <p class="g-hint">یوزر و رمز خودکار ساخته می‌شود. این فرد فقط در برنامه آلمانی ذخیره می‌شود.</p>
            <div class="g-sheet-actions">
                <button type="button" class="g-btn g-btn-ghost" data-close-person>انصراف</button>
                <button type="submit" class="g-btn g-btn-primary">ثبت</button>
            </div>
        </form>
    </div>
</div>

<script>window.GERMAN = <?= json_encode($boot, JSON_UNESCAPED_UNICODE) ?>;</script>
<?php require_once 'layout-end.php'; ?>
