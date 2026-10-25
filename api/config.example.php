<?php
/**
 * Copy to config.php next to api.php on the server. Do not commit config.php.
 */
function getDbConnection(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    $host = getenv("ACC_DB_HOST") ?: "127.0.0.1";
    $name = getenv("ACC_DB_NAME") ?: "learnmax_acc";
    $user = getenv("ACC_DB_USER") ?: "root";
    $pass = getenv("ACC_DB_PASS") ?: "";
    $pdo = new PDO(
        "mysql:host={$host};dbname={$name};charset=utf8mb4",
        $user,
        $pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
    return $pdo;
}

function sendJson($data, $status = 200): void
{
    if (!headers_sent()) {
        http_response_code((int) $status);
        header("Content-Type: application/json; charset=utf-8");
    }
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function getJsonInput(): array
{
    $raw = file_get_contents("php://input");
    $json = json_decode($raw ?: "[]", true);
    return is_array($json) ? $json : [];
}
