<?php
/**
 * Calls the panel identity hook so site + panel accounts share the student code.
 */
function ghazal_identity_file_number($code)
{
    $code = trim((string) $code);
    $map = ['۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9',
            '٠'=>'0','١'=>'1','٢'=>'2','٣'=>'3','٤'=>'4','٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9'];
    $code = strtr($code, $map);
    if ($code === '' || !preg_match('/^[0-9]{3,4}$/', $code)) {
        return '';
    }
    return ((int) $code > 0 && (int) $code < 5000) ? $code : '';
}

function ghazal_identity_sync(array $payload, $timeout = 8)
{
    if (!defined('GHAZAL_IDENTITY_URL') || !defined('GHAZAL_IDENTITY_SECRET')) {
        return null;
    }
    $url = GHAZAL_IDENTITY_URL;
    $secret = GHAZAL_IDENTITY_SECRET;
    if ($url === '' || $secret === '') {
        return null;
    }
    $timeout = (int) $timeout;
    if ($timeout < 1) {
        $timeout = 1;
    }
    if ($timeout > 70) {
        $timeout = 70;
    }
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_CONNECTTIMEOUT => min(3, $timeout),
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Accept: application/json',
            'X-Ghazal-Identity: ' . $secret,
        ],
    ]);
    $raw = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);
    if ($raw === false) {
        error_log('ghazal identity: ' . $err);
        return null;
    }
    $json = json_decode($raw, true);
    return is_array($json) ? $json : null;
}

function ghazal_error_ping_url()
{
    if (defined('GHAZAL_ERROR_PING_URL') && GHAZAL_ERROR_PING_URL) {
        return (string) GHAZAL_ERROR_PING_URL;
    }
    if (!defined('GHAZAL_IDENTITY_URL') || GHAZAL_IDENTITY_URL === '') {
        return 'https://panel.ghazalify.com/error-ping.php';
    }
    $url = preg_replace('#/identity-ensure\\.php(?:\\?.*)?$#', '/error-ping.php', (string) GHAZAL_IDENTITY_URL);
    if ($url === (string) GHAZAL_IDENTITY_URL) {
        return 'https://panel.ghazalify.com/error-ping.php';
    }
    return $url;
}

function ghazal_error_report($code, $reason, $extra = '')
{
    $url = ghazal_error_ping_url();
    $secret = defined('GHAZAL_IDENTITY_SECRET') ? (string) GHAZAL_IDENTITY_SECRET : '';
    if ($url === '' || $secret === '') {
        return false;
    }
    $body = json_encode([
        'code' => (string) $code,
        'reason' => (string) $reason,
        'extra' => (string) $extra,
    ], JSON_UNESCAPED_UNICODE);
    $parts = parse_url($url);
    if (empty($parts['host'])) {
        return false;
    }
    $host = $parts['host'];
    $scheme = strtolower((string) ($parts['scheme'] ?? 'https'));
    $port = (int) ($parts['port'] ?? ($scheme === 'https' ? 443 : 80));
    $path = (string) ($parts['path'] ?? '/error-ping.php');
    if ($path === '') {
        $path = '/error-ping.php';
    }
    $target = ($scheme === 'https' ? 'ssl://' : '') . $host . ':' . $port;
    $ctx = stream_context_create([
        'ssl' => [
            'peer_name' => $host,
            'verify_peer' => false,
            'verify_peer_name' => false,
            'SNI_enabled' => true,
        ],
    ]);
    $fp = @stream_socket_client($target, $errno, $errstr, 0.3, STREAM_CLIENT_CONNECT, $ctx);
    if (!$fp) {
        return false;
    }
    stream_set_timeout($fp, 0, 350000);
    $req = 'POST ' . $path . " HTTP/1.1\r\n";
    $req .= 'Host: ' . $host . "\r\n";
    $req .= "Content-Type: application/json\r\n";
    $req .= 'X-Ghazal-Identity: ' . $secret . "\r\n";
    $req .= 'Content-Length: ' . strlen($body) . "\r\n";
    $req .= "Connection: close\r\n\r\n" . $body;
    @fwrite($fp, $req);
    @fclose($fp);
    return true;
}

function ghazal_error_trim($text, $max = 160)
{
    $text = trim(preg_replace('/\\s+/u', ' ', (string) $text));
    if (function_exists('mb_strlen') && mb_strlen($text, 'UTF-8') > $max) {
        return mb_substr($text, 0, $max - 1, 'UTF-8') . '…';
    }
    if (strlen($text) > $max) {
        return substr($text, 0, $max - 1) . '…';
    }
    return $text;
}

function ghazal_acc_register_error_hooks()
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    register_shutdown_function(function () {
        $e = error_get_last();
        if (!$e || !in_array((int) $e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR], true)) {
            return;
        }
        if (function_exists('ghazal_error_report')) {
            ghazal_error_report(
                'DB-CRASH',
                'کرش حسابداری در ' . basename((string) ($e['file'] ?? 'api')),
                ghazal_error_trim($e['message'] ?? '')
            );
        }
    });
}

ghazal_acc_register_error_hooks();

function ghazal_ensure_student_code_column(PDO $pdo)
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    $col = $pdo->query("SHOW COLUMNS FROM students LIKE 'studentCode'")->fetch();
    if (!$col) {
        $pdo->exec("ALTER TABLE students ADD COLUMN studentCode VARCHAR(20) NULL DEFAULT NULL");
        try {
            $pdo->exec("ALTER TABLE students ADD INDEX idx_studentCode (studentCode)");
        } catch (Exception $e) {
            // ignore
        }
    }
}
