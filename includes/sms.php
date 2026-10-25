<?php
/**
 * پیامک فراز — همان تابع پنل اعلان‌ها (announcement.ghazalify.com/services.php)
 */
function sendFarazSMS($phone, $patternCode, $variables) {
    $config = function_exists('rollcall_config') ? rollcall_config() : [];
    $sms = $config['sms'] ?? [];
    $apiKey = $sms['api_key'] ?? 'PpgL4mm0OiqyOoDuh0GT6C9pTNI2elZzzrHxXwHrMxQMbNsrD1';
    $senderNumber = $sms['sender'] ?? '90008361';
    $url = $sms['url'] ?? 'https://api.iranpayamak.com/ws/v1/sms/pattern';

    $data = [
        'code' => $patternCode,
        'recipient' => $phone,
        'attributes' => $variables,
        'line_number' => $senderNumber,
        'number_format' => 'english',
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Api-Key: ' . $apiKey,
        'Content-Type: application/json',
        'Accept: application/json',
    ]);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data, JSON_UNESCAPED_UNICODE));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);

    $response = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr = curl_error($ch);
    $curlErrno = curl_errno($ch);
    curl_close($ch);

    $decoded = json_decode((string)$response, true);
    $apiStatus = is_array($decoded) ? (string)($decoded['status'] ?? '') : '';
    $ok = $curlErrno === 0 && $httpCode >= 200 && $httpCode < 300 && ($apiStatus === '' || $apiStatus === 'success');
    if (!$ok && function_exists('ghazal_error_sms_fail')) {
        ghazal_error_sms_fail(
            (string)$phone,
            (string)$patternCode,
            $curlErrno ? 0 : $httpCode,
            is_array($decoded) ? $decoded : ['message' => $curlErr !== '' ? $curlErr : (string)$response],
            $curlErr
        );
    }

    return ['http_code' => $httpCode, 'response' => $decoded, 'curl_error' => $curlErr];
}
