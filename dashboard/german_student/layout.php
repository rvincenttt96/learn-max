<?php
$german_role = 'german_student';
$german_nav = [
    ['href' => 'index.php', 'label' => 'کلاس و شهریه', 'icon' => 'eas'],
];
if (!isset($title) || $title === '') {
    $title = 'German Student';
}
require_once __DIR__ . '/../../includes/german-shell.php';
