<?php
/**
 * Fire-and-forget Error Logs → Mattermost.
 * Does not persist these events. Must not block SMS / DB / AloCom work.
 */

function ghazal_error_config() {
    static $config = null;
    if ($config !== null) {
        return $config;
    }
    if (function_exists('rollcall_config')) {
        $config = rollcall_config();
        return $config;
    }
    $path = __DIR__ . '/rollcall-config.php';
    $config = is_file($path) ? require $path : [];
    if (!is_array($config)) {
        $config = [];
    }
    return $config;
}

function ghazal_error_valid_secret($given) {
    $secret = (string)(ghazal_error_config()['identity']['secret'] ?? '');
    if ($secret === '' || (string)$given === '') {
        return false;
    }
    return hash_equals($secret, (string)$given);
}

function ghazal_error_trim($text, $max = 160) {
    $text = trim(preg_replace('/\s+/u', ' ', (string)$text));
    $text = preg_replace('/(password|passwd|secret|token|api[_-]?key)\s*[:=]\s*\S+/iu', '$1=…', $text);
    if (function_exists('mb_strlen') && mb_strlen($text, 'UTF-8') > $max) {
        return mb_substr($text, 0, $max - 1, 'UTF-8') . '…';
    }
    if (strlen($text) > $max) {
        return substr($text, 0, $max - 1) . '…';
    }
    return $text;
}

function ghazal_error_codes() {
    return [
        'SMS-NO-PHONE' => 'شماره موبایل نیست؛ پیامک ارسال نشد',
        'SMS-WRONG' => 'شماره نامعتبر است یا به شخص دیگری می‌خورد',
        'SMS-CREDIT' => 'شارژ / موجودی پنل پیامک تمام شده',
        'SMS-REJECT' => 'فراز ارسال را رد کرد',
        'SMS-PATTERN' => 'پترن پیامک نیست یا هنوز تأیید نشده',
        'SMS-TIMEOUT' => 'پاسخ فراز به‌موقع نیامد',
        'SMS-FAIL' => 'ارسال پیامک ناموفق (سایر)',
        'DB-CONNECT' => 'اتصال به دیتابیس برقرار نشد',
        'DB-QUERY' => 'اجرای کوئری خطا داد',
        'DB-SAVE' => 'ثبت یا به‌روزرسانی در دیتابیس ذخیره نشد',
        'DB-CRASH' => 'کرش در مسیر دیتابیس / پنل',
        'ALO-LOGIN' => 'ورود API الوکام ناموفق',
        'ALO-API' => 'خطای عمومی API الوکام',
        'ALO-USER' => 'ساخت یا تغییر کاربر الوکام',
        'ALO-CLASS' => 'ساخت یا حذف رویداد کلاس',
        'ALO-ENROLL' => 'ثبت یا حذف دانش‌آموز در رویداد',
        'ALO-CRASH' => 'کرش در مسیر الوکام',
    ];
}

function ghazal_error_legend_message() {
    return <<<'TXT'
**کدهای Error Logs**
پیامک: `SMS-NO-PHONE` شماره نیست · `SMS-WRONG` شماره اشتباه/نامعتبر · `SMS-CREDIT` شارژ تمام · `SMS-REJECT` رد فراز · `SMS-PATTERN` پترن · `SMS-TIMEOUT` تایم‌اوت · `SMS-FAIL` سایر
دیتابیس: `DB-CONNECT` اتصال · `DB-QUERY` کوئری · `DB-SAVE` ثبت · `DB-CRASH` کرش
الوکام: `ALO-LOGIN` ورود · `ALO-API` API · `ALO-USER` کاربر · `ALO-CLASS` کلاس/رویداد · `ALO-ENROLL` ثبت در رویداد · `ALO-CRASH` کرش
TXT;
}

function ghazal_error_sms_begin($kind, $who = '', $code = '') {
    $GLOBALS['_gz_sms_ctx'] = [
        'kind' => (string)$kind,
        'who' => (string)$who,
        'code' => (string)$code,
    ];
}

function ghazal_error_sms_end() {
    unset($GLOBALS['_gz_sms_ctx']);
}

function ghazal_error_sms_kind_fa($kind) {
    $map = [
        'welcome' => 'خوش‌آمدگویی',
        'absence' => 'غیبت',
        'late' => 'تأخیر',
        'otp' => 'ورود سایت',
        'other' => 'پیامک',
    ];
    $kind = (string)$kind;
    return $map[$kind] ?? ($kind !== '' ? $kind : 'پیامک');
}

function ghazal_error_sms_classify($http, $api_msg, $curl_err, $phone = '') {
    $blob = strtolower((string)$api_msg . ' ' . (string)$curl_err);
    $phone = preg_replace('/\D+/', '', (string)$phone);
    if ((int)$http === 0 || strpos($blob, 'timed out') !== false || strpos($blob, 'timeout') !== false) {
        return 'SMS-TIMEOUT';
    }
    if (preg_match('/credit|balance|insufficient|شارژ|موجودی|اعتبار/', $blob)) {
        return 'SMS-CREDIT';
    }
    if (preg_match('/pattern|پترن|template/', $blob)) {
        return 'SMS-PATTERN';
    }
    if (
        ($phone !== '' && !preg_match('/^09\d{9}$/', $phone))
        || preg_match('/invalid.*(mobile|recipient|number)|wrong number|not a valid|شماره نامعتبر|گیرنده/', $blob)
    ) {
        return 'SMS-WRONG';
    }
    if ((int)$http >= 400 || preg_match('/reject|denied|forbidden|blacklist/', $blob)) {
        return 'SMS-REJECT';
    }
    return 'SMS-FAIL';
}

function ghazal_error_alocom_code($path, $msg = '') {
    $blob = strtolower((string)$path . ' ' . (string)$msg);
    if (preg_match('/login|auth\/|unauthorized|token rejected/', $blob)) {
        return 'ALO-LOGIN';
    }
    if (preg_match('/add-users|enroll|join|roster|unenroll|remove enroll/', $blob)) {
        return 'ALO-ENROLL';
    }
    if (preg_match('/\/users|password|rename|activate|deactivate|status/', $blob)) {
        return 'ALO-USER';
    }
    if (preg_match('/event|class|room|slug/', $blob)) {
        return 'ALO-CLASS';
    }
    return 'ALO-API';
}

function ghazal_error_classify_path($file, $msg) {
    $file = str_replace('\\', '/', (string)$file);
    $base = strtolower($file . ' ' . (string)$msg);
    if (preg_match('/sms\.php|faraz|iranpayamak|welcome_sms|otp/', $base)) {
        if (preg_match('/timeout|timed out/', $base)) {
            return 'SMS-TIMEOUT';
        }
        return 'SMS-FAIL';
    }
    if (preg_match('/alocom/', $base)) {
        return 'ALO-CRASH';
    }
    if (preg_match('/sqlstate|pdo|mysql|database/', $base)) {
        if (preg_match('/connect|access denied|getaddrinfo|hy000/', $base)) {
            return 'DB-CONNECT';
        }
        return 'DB-QUERY';
    }
    return 'DB-CRASH';
}

function ghazal_error_from_throwable($e) {
    if (!$e instanceof Throwable) {
        return false;
    }
    $msg = $e->getMessage();
    $file = $e->getFile();
    $code = 'DB-CRASH';
    if ($e instanceof PDOException || preg_match('/sqlstate|mysql/i', $msg)) {
        $code = preg_match('/connect|access denied|hy000/i', $msg) ? 'DB-CONNECT' : 'DB-SAVE';
    } elseif (stripos($msg, 'AloCom') !== false || stripos($file, 'alocom') !== false) {
        $code = ghazal_error_alocom_code($file . ' ' . $msg, $msg);
    } elseif (stripos($file, 'sms.php') !== false || preg_match('/faraz|sms/i', $msg)) {
        $code = ghazal_error_sms_classify(0, $msg, '', '');
    } else {
        $code = ghazal_error_classify_path($file, $msg);
    }
    $where = basename((string)$file);
    $line = (int)$e->getLine();
    return ghazal_error_report(
        $code,
        'خطا در ' . $where . ($line > 0 ? ':' . $line : ''),
        ghazal_error_trim($msg)
    );
}

function ghazal_error_sms_fail($phone, $pattern, $http, $decoded, $curl_err = '') {
    $ctx = $GLOBALS['_gz_sms_ctx'] ?? [];
    $kind = ghazal_error_sms_kind_fa($ctx['kind'] ?? 'other');
    $who = trim((string)($ctx['who'] ?? ''));
    $code = trim((string)($ctx['code'] ?? ''));
    $api_msg = '';
    if (is_array($decoded)) {
        $api_msg = (string)($decoded['message'] ?? $decoded['error'] ?? '');
    }
    $err_code = ghazal_error_sms_classify($http, $api_msg . ' ' . $curl_err, $curl_err, $phone);
    $who_bit = $who !== '' ? $who : ($code !== '' ? 'پرونده ' . $code : '');
    $line = $kind . ($who_bit !== '' ? ' — ' . $who_bit : '') . ' ارسال نشد.';
    $extra = $api_msg !== '' ? $api_msg : ($curl_err !== '' ? $curl_err : ('HTTP ' . (int)$http));
    if ($code !== '' && $who === '') {
        $extra = 'کد ' . $code . ' · ' . $extra;
    }
    return ghazal_error_report($err_code, $line, ghazal_error_trim($extra));
}

function ghazal_error_report($code, $reason, $extra = '') {
    static $sent_this_request = 0;
    $code = strtoupper(trim((string)$code));
    $known = ghazal_error_codes();
    if ($code === '' || !isset($known[$code])) {
        $code = 'DB-CRASH';
    }
    $reason = ghazal_error_trim($reason, 180);
    $extra = ghazal_error_trim($extra, 160);
    if ($reason === '') {
        $reason = $known[$code];
    }

    if ($sent_this_request >= 25) {
        return false;
    }

    $hash = sha1($code . '|' . $reason . '|' . $extra);
    $stamp = rtrim(sys_get_temp_dir(), '/') . '/gz_errlog_' . substr($hash, 0, 24);
    $now = time();
    if (is_file($stamp) && ($now - (int)@filemtime($stamp)) < 90) {
        return false;
    }
    @touch($stamp);

    $lines = ['`' . $code . '`  ' . $reason];
    if ($extra !== '' && $extra !== $reason) {
        $lines[] = $extra;
    }
    $message = implode("\n", array_slice($lines, 0, 3));

    $sent_this_request++;
    return ghazal_error_post_mattermost($message);
}

function ghazal_error_post_mattermost($message) {
    $cfg = ghazal_error_config();
    $err = $cfg['error_logs'] ?? [];
    $base = rtrim((string)($err['url'] ?? $cfg['mattermost_url'] ?? 'https://co.ghazalify.com'), '/');
    $token = trim((string)($err['bot_token'] ?? ''));
    $channel = trim((string)($err['channel_id'] ?? ''));
    $webhook = trim((string)($err['webhook'] ?? ''));
    if ($message === '' || $base === '') {
        return false;
    }

    if ($webhook !== '') {
        $payload = [
            'text' => $message,
            'username' => 'Error Logs',
            'icon_url' => 'https://acccounting.ghazalify.com/assets/ghazal-logo.png?v=tab1',
        ];
        if ($channel !== '') {
            $payload['channel'] = $channel;
        }
        return ghazal_error_http_fire(
            $webhook,
            json_encode($payload, JSON_UNESCAPED_UNICODE),
            []
        );
    }
    if ($token !== '' && $channel !== '') {
        $body = json_encode([
            'channel_id' => $channel,
            'message' => $message,
            'props' => [
                'from_webhook' => 'true',
                'override_username' => 'Error Logs',
                'webhook_display_name' => 'Error Logs',
            ],
        ], JSON_UNESCAPED_UNICODE);
        return ghazal_error_http_fire(
            $base . '/api/v4/posts',
            $body,
            ['Authorization: Bearer ' . $token]
        );
    }
    return false;
}

function ghazal_error_http_fire($url, $body, array $extra_headers) {
    $parts = parse_url($url);
    if (empty($parts['host'])) {
        return false;
    }
    $host = $parts['host'];
    $scheme = strtolower((string)($parts['scheme'] ?? 'https'));
    $port = (int)($parts['port'] ?? ($scheme === 'https' ? 443 : 80));
    $path = (string)($parts['path'] ?? '/');
    if ($path === '') {
        $path = '/';
    }
    if (!empty($parts['query'])) {
        $path .= '?' . $parts['query'];
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
    $fp = @stream_socket_client($target, $errno, $errstr, 0.35, STREAM_CLIENT_CONNECT, $ctx);
    if (!$fp) {
        return false;
    }
    stream_set_timeout($fp, 0, 400000);
    $req = 'POST ' . $path . " HTTP/1.1\r\n";
    $req .= 'Host: ' . $host . "\r\n";
    $req .= "Content-Type: application/json\r\n";
    $req .= 'Content-Length: ' . strlen($body) . "\r\n";
    $req .= "Connection: close\r\n";
    foreach ($extra_headers as $h) {
        $h = trim((string)$h);
        if ($h !== '') {
            $req .= $h . "\r\n";
        }
    }
    $req .= "\r\n" . $body;
    @fwrite($fp, $req);
    @fclose($fp);
    return true;
}

function ghazal_error_shutdown() {
    $e = error_get_last();
    if (!$e || !is_array($e)) {
        return;
    }
    $fatal = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
    if (!in_array((int)$e['type'], $fatal, true)) {
        return;
    }
    $file = (string)($e['file'] ?? '');
    $msg = (string)($e['message'] ?? '');
    if (strpos($file, 'error-log.php') !== false) {
        return;
    }
    $code = ghazal_error_classify_path($file, $msg);
    ghazal_error_report(
        $code,
        'کرش در ' . basename($file) . ':' . (int)($e['line'] ?? 0),
        ghazal_error_trim($msg)
    );
}

function ghazal_error_register_hooks() {
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    register_shutdown_function('ghazal_error_shutdown');
}

ghazal_error_register_hooks();
