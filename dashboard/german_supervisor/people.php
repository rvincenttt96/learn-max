<?php
$title = 'اساتید و زبان‌آموزان آلمانی';
$current_page = 'people.php';
require_once 'layout.php';

$teachers = german_people($pdo, 'german_teacher');
$students = german_people($pdo, 'german_student');
$boot = ['page' => 'people', 'ajax' => 'ajax.php'];
?>

<div class="g-header">
    <div class="g-header-copy">
        <h1><span>People</span> اساتید و زبان‌آموزان</h1>
        <p>این‌ها فقط برنامه آلمانی‌اند. با شاگرد و معلم انگلیسی قاطی نمی‌شوند.</p>
    </div>
</div>

<div class="g-people-grid">
    <section class="g-panel">
        <div class="g-panel-head">
            <h2>افزودن استاد</h2>
        </div>
        <form class="g-stack" data-person-form="german_teacher" autocomplete="off">
            <label class="g-field">
                <span>نام کامل</span>
                <input class="g-input" name="full_name" required maxlength="120" autocomplete="name">
            </label>
            <label class="g-field">
                <span>تلفن</span>
                <input class="g-input" name="phone" inputmode="tel" maxlength="20" autocomplete="tel">
            </label>
            <p class="g-hint">یوزر و رمز ورود به‌صورت خودکار ساخته می‌شود و بعد از ثبت یک‌بار نشان داده می‌شود.</p>
            <button type="submit" class="g-btn g-btn-primary">ثبت استاد</button>
        </form>
    </section>
    <section class="g-panel">
        <div class="g-panel-head">
            <h2>افزودن زبان‌آموز</h2>
        </div>
        <form class="g-stack" data-person-form="german_student" autocomplete="off">
            <label class="g-field">
                <span>نام کامل</span>
                <input class="g-input" name="full_name" required maxlength="120" autocomplete="name">
            </label>
            <label class="g-field">
                <span>تلفن</span>
                <input class="g-input" name="phone" inputmode="tel" maxlength="20" autocomplete="tel">
            </label>
            <p class="g-hint">یوزر و رمز ورود به‌صورت خودکار ساخته می‌شود و بعد از ثبت یک‌بار نشان داده می‌شود.</p>
            <button type="submit" class="g-btn g-btn-primary">ثبت زبان‌آموز</button>
        </form>
    </section>
</div>

<div class="g-people-grid">
    <section class="g-panel">
        <div class="g-panel-head">
            <h2>اساتید <span class="g-count"><?= count($teachers) ?></span></h2>
            <?php if ($teachers): ?>
            <input type="search" class="g-input g-input-sm" data-filter-list="teacherList" placeholder="جستجو" enterkeyhint="search">
            <?php endif; ?>
        </div>
        <?php if (!$teachers): ?>
            <div class="g-empty g-empty-sm">
                <h3>استادی نیست</h3>
                <p class="mb-0">از فرم بالا اولین استاد آلمانی را بسازید.</p>
            </div>
        <?php else: ?>
        <ul class="g-list" id="teacherList">
            <?php foreach ($teachers as $t): ?>
            <li data-search="<?= german_h($t['full_name'] . ' ' . $t['username'] . ' ' . $t['phone']) ?>">
                <div class="g-list-copy">
                    <strong><?= german_h($t['full_name']) ?></strong>
                    <div class="g-muted"><?= german_h($t['username']) ?><?php if ($t['phone']): ?> · <?= german_h($t['phone']) ?><?php endif; ?></div>
                </div>
                <div class="g-list-actions">
                    <form class="g-inline-form" data-reset-pass data-user-id="<?= (int)$t['id'] ?>" data-role="german_teacher">
                        <input class="g-input g-input-sm" name="password" placeholder="رمز جدید" maxlength="80" autocomplete="new-password">
                        <button type="submit" class="g-btn g-btn-ghost">رمز</button>
                    </form>
                    <button type="button" class="g-btn g-btn-danger" data-del-person="<?= (int)$t['id'] ?>" data-role="german_teacher">حذف</button>
                </div>
            </li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>
    </section>
    <section class="g-panel">
        <div class="g-panel-head">
            <h2>زبان‌آموزان <span class="g-count"><?= count($students) ?></span></h2>
            <?php if ($students): ?>
            <input type="search" class="g-input g-input-sm" data-filter-list="studentList" placeholder="جستجو" enterkeyhint="search">
            <?php endif; ?>
        </div>
        <?php if (!$students): ?>
            <div class="g-empty g-empty-sm">
                <h3>زبان‌آموزی نیست</h3>
                <p class="mb-0">از فرم بالا اولین زبان‌آموز آلمانی را بسازید.</p>
            </div>
        <?php else: ?>
        <ul class="g-list" id="studentList">
            <?php foreach ($students as $s): ?>
            <li data-search="<?= german_h($s['full_name'] . ' ' . $s['username'] . ' ' . $s['phone']) ?>">
                <div class="g-list-copy">
                    <strong><?= german_h($s['full_name']) ?></strong>
                    <div class="g-muted"><?= german_h($s['username']) ?><?php if ($s['phone']): ?> · <?= german_h($s['phone']) ?><?php endif; ?></div>
                </div>
                <div class="g-list-actions">
                    <form class="g-inline-form" data-reset-pass data-user-id="<?= (int)$s['id'] ?>" data-role="german_student">
                        <input class="g-input g-input-sm" name="password" placeholder="رمز جدید" maxlength="80" autocomplete="new-password">
                        <button type="submit" class="g-btn g-btn-ghost">رمز</button>
                    </form>
                    <button type="button" class="g-btn g-btn-danger" data-del-person="<?= (int)$s['id'] ?>" data-role="german_student">حذف</button>
                </div>
            </li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>
    </section>
</div>

<script>window.GERMAN = <?= json_encode($boot, JSON_UNESCAPED_UNICODE) ?>;</script>
<?php require_once 'layout-end.php'; ?>
