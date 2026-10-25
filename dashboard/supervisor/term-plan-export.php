<?php
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
require_once '../../includes/terms.php';
require_once '../../includes/term-plan.php';
require_role('supervisor');

term_plan_ensure_schema($pdo);
$rows = term_plan_export_rows($pdo);
term_plan_send_xlsx(['Term Plan' => $rows], 'term-plan-export.xlsx');
