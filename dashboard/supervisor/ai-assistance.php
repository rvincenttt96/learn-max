<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
$title = 'AI Assistance';
$current_page = 'ai-assistance.php';
require_once 'layout.php';
require_role('supervisor');
$sai_mode = 'page';
?>
<link href="/assets/css/supervisor/supervisor-ai.css?v=a6" rel="stylesheet">
<div class="sai-page-wrap">
    <?php require __DIR__ . '/supervisor-ai-chat.php'; ?>
</div>
<?php require_once 'layout-end.php'; ?>
