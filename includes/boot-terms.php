<?php
if (!isset($pdo) || !($pdo instanceof PDO)) {
    return;
}
require_once __DIR__ . '/terms.php';
try {
    term_ensure_schema($pdo);
    term_promote_due($pdo);
} catch (Exception $e) {
    error_log('boot-terms: ' . $e->getMessage());
}
