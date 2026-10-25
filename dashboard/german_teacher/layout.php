<?php
$german_role = 'german_teacher';
$german_nav = [
    ['href' => 'index.php', 'label' => 'کلاس‌های من', 'icon' => 'eas'],
];
if (!isset($title) || $title === '') {
    $title = 'German Teacher';
}
require_once __DIR__ . '/../../includes/german-shell.php';
