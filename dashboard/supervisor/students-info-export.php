<?php
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
require_once '../../includes/students-info.php';
require_role('supervisor');

try {
    $template = !empty($_GET['template']);
    $sheets = $template ? students_info_template_sheets() : students_info_export_sheets($pdo);
    $name = $template
        ? 'students-information-template.xlsx'
        : ('students-information-' . date('Ymd-His') . '.xlsx');
    students_info_send_xlsx($name, $sheets);
} catch (Exception $e) {
    header('Location: students-info.php?error=' . urlencode($e->getMessage()));
    exit;
}
