<?php
/**
 * Optional father/mother mobile numbers on the student profile.
 */

function users_ensure_parent_phone_columns(PDO $pdo) {
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    foreach (['father_phone', 'mother_phone'] as $col) {
        $st = $pdo->query("SHOW COLUMNS FROM users LIKE " . $pdo->quote($col));
        if (!$st->fetch()) {
            $pdo->exec("ALTER TABLE users ADD COLUMN `$col` VARCHAR(20) NULL DEFAULT NULL");
        }
    }
}

function users_sanitize_parent_phone($value, $label) {
    $value = trim((string)$value);
    if ($value === '') {
        return '';
    }
    if (!function_exists('rollcall_normalize_phone')) {
        require_once __DIR__ . '/rollcall.php';
    }
    $phone = rollcall_normalize_phone($value);
    if ($phone === '' || !preg_match('/^09\d{9}$/', $phone)) {
        throw new Exception($label . ' معتبر نیست. یک شماره موبایل ۱۱ رقمی مثل 09123456789 وارد کنید.');
    }
    return $phone;
}
