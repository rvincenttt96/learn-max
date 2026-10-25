<?php
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
require_once '../../includes/terms.php';
require_once '../../includes/term-plan.php';
require_role('supervisor');

term_plan_ensure_schema($pdo);
term_plan_send_xlsx(term_plan_template_rows($pdo), 'term-plan-template.xlsx');
