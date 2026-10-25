<?php
$german_role = 'german_supervisor';
$german_nav = [
    ['href' => 'index.php', 'label' => 'کلاس‌ها', 'icon' => 'eas'],
    ['href' => 'people.php', 'label' => 'اساتید و زبان‌آموزان', 'icon' => 'people'],
    ['href' => 'fees.php', 'label' => 'شهریه هر جلسه', 'icon' => 'fees'],
];
if (!isset($title) || $title === '') {
    $title = 'German Supervisor';
}
require_once __DIR__ . '/../../includes/german-shell.php';
