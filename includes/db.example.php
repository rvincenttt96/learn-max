<?php
/**
 * Copy to includes/db.php on the server. Do not commit db.php.
 */
$host = getenv('LEARNMAX_DB_HOST') ?: '127.0.0.1';
$name = getenv('LEARNMAX_DB_NAME') ?: 'learnmax';
$user = getenv('LEARNMAX_DB_USER') ?: 'root';
$pass = getenv('LEARNMAX_DB_PASS') ?: '';

$pdo = new PDO(
    "mysql:host={$host};dbname={$name};charset=utf8mb4",
    $user,
    $pass,
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]
);
