<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/german.php';
require_role('german_supervisor');
german_ensure_schema($pdo);
german_output_receipt($pdo, (int)($_GET['id'] ?? 0), (int)($_SESSION['user_id'] ?? 0), 'german_supervisor');
