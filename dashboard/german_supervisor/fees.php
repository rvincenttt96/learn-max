<?php
$title = 'شهریه هر جلسه';
$current_page = 'fees.php';
require_once 'layout.php';

$fees = german_fees_map($pdo);
$levels = german_levels();
$boot = ['page' => 'fees', 'ajax' => 'ajax.php'];
?>

<div class="g-header">
    <div class="g-header-copy">
        <h1><span>Fees</span> شهریه هر جلسه</h1>
        <p>این مبالغ برای کلاس گروهی و خصوصی استفاده می‌شود. نیمه خصوصی را موقع باز کردن کلاس دستی وارد می‌کنید. ذخیره اینجا کلاس‌های قبلی را عوض نمی‌کند مگر همان کلاس را دوباره ذخیره کنید.</p>
    </div>
</div>

<form class="g-panel" id="feesForm">
    <div class="g-table-wrap">
        <table class="g-table">
            <thead>
                <tr>
                    <th>لول</th>
                    <th>گروهی</th>
                    <th>خصوصی</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($levels as $level):
                $row = $fees[$level] ?? ['group_fee' => 0, 'private_fee' => 0];
            ?>
                <tr>
                    <td><strong><?= german_h($level) ?></strong></td>
                    <td><input class="g-input" name="group_<?= german_h($level) ?>" value="<?= german_h(german_money($row['group_fee'])) ?>" inputmode="numeric"></td>
                    <td><input class="g-input" name="private_<?= german_h($level) ?>" value="<?= german_h(german_money($row['private_fee'])) ?>" inputmode="numeric"></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <p class="g-hint">نیمه‌خصوصی در این جدول نیست؛ شهریه‌اش برای هر کلاس جدا وارد می‌شود.</p>
    <button type="submit" class="g-btn g-btn-primary">ذخیره شهریه‌ها</button>
</form>

<script>window.GERMAN = <?= json_encode($boot, JSON_UNESCAPED_UNICODE) ?>;</script>
<?php require_once 'layout-end.php'; ?>
