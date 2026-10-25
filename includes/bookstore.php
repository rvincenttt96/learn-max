<?php

require_once __DIR__ . '/bookstore-prices.php';

function bookstore_config(): array
{
    static $config = null;
    if ($config === null) {
        $path = __DIR__ . '/bookstore-config.php';
        $config = is_file($path) ? require $path : [];
        if (!is_array($config)) {
            $config = [];
        }
    }
    return $config;
}

function bookstore_catalog(): array
{
    static $catalog = null;
    if ($catalog !== null) {
        return $catalog;
    }

    $fromApi = bookstore_fetch_packages();
    if (is_array($fromApi) && $fromApi !== []) {
        $catalog = $fromApi;
        return $catalog;
    }

    $loaded = require __DIR__ . '/bookstore-packages.php';
    $catalog = [];
    if (!is_array($loaded)) {
        return $catalog;
    }

    foreach ($loaded as $key => $value) {
        if (is_int($key) && is_string($value)) {
            $code = trim($value);
            if ($code !== '') {
                $catalog[$code] = [];
            }
            continue;
        }
        if (!is_string($key)) {
            continue;
        }
        $code = trim($key);
        if ($code === '') {
            continue;
        }
        $books = [];
        if (is_array($value)) {
            foreach ($value as $book) {
                $title = trim((string)$book);
                if ($title !== '' && !in_array($title, $books, true)) {
                    $books[] = $title;
                }
            }
        }
        $catalog[$code] = $books;
    }

    return $catalog;
}

function bookstore_api_url(string $script): string
{
    $config = bookstore_config();
    $map = [
        'packages.php' => 'packages_url',
        'student-orders.php' => 'orders_url',
        'panel-receipt.php' => 'receipt_url',
        'panel-order.php' => 'url',
    ];
    $key = $map[$script] ?? '';
    if ($key !== '') {
        $explicit = trim((string)($config[$key] ?? ''));
        if ($explicit !== '') {
            return $explicit;
        }
    }
    $url = trim((string)($config['url'] ?? ''));
    if ($url !== '' && str_contains($url, 'panel-order.php')) {
        return str_replace('panel-order.php', $script, $url);
    }
    return '';
}

function bookstore_packages_url(): string
{
    return bookstore_api_url('packages.php');
}

function bookstore_http(string $url, array $opts = []): array
{
    $token = (string)(bookstore_config()['token'] ?? '');
    if ($url === '' || $token === '') {
        return ['ok' => false, 'error' => 'اتصال فروشگاه کتاب هنوز تنظیم نشده است.', 'http' => 0, 'json' => null];
    }
    $headers = [
        'Accept: application/json',
        'X-Ghazal-Books: ' . $token,
        'User-Agent: GhazalPanelBookStore/1.0',
    ];
    if (!empty($opts['json'])) {
        $headers[] = 'Content-Type: application/json';
    }
    $ch = curl_init($url);
    $curl = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => (int)($opts['timeout'] ?? 20),
        CURLOPT_CONNECTTIMEOUT => (int)($opts['connect'] ?? 6),
        CURLOPT_HTTPHEADER => $headers,
    ];
    if (($opts['method'] ?? 'GET') === 'POST') {
        $curl[CURLOPT_POST] = true;
        if (!empty($opts['json'])) {
            $curl[CURLOPT_POSTFIELDS] = json_encode($opts['json'], JSON_UNESCAPED_UNICODE);
        } elseif (isset($opts['multipart'])) {
            $curl[CURLOPT_POSTFIELDS] = $opts['multipart'];
        }
    }
    curl_setopt_array($ch, $curl);
    $raw = curl_exec($ch);
    $errno = curl_errno($ch);
    $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($raw === false || $errno) {
        return ['ok' => false, 'error' => 'ارتباط با فروشگاه کتاب برقرار نشد.', 'http' => $http, 'json' => null];
    }
    $json = json_decode((string)$raw, true);
    $ok = is_array($json) && !empty($json['ok']) && $http < 400;
    return [
        'ok' => $ok,
        'error' => is_array($json) ? (string)($json['error'] ?? '') : '',
        'http' => $http,
        'json' => is_array($json) ? $json : null,
    ];
}

function bookstore_normalize_shop_bundle(array $raw): array
{
    $packages = [];
    foreach ((array)($raw['packages'] ?? []) as $code => $books) {
        $code = trim((string)$code);
        if ($code === '') {
            continue;
        }
        $titles = [];
        foreach ((array)$books as $book) {
            $title = trim((string)$book);
            if ($title !== '' && !in_array($title, $titles, true)) {
                $titles[] = $title;
            }
        }
        $packages[$code] = $titles;
    }
    $fee = (int)($raw['postage_fee'] ?? 150000);
    if ($fee < 0) {
        $fee = 0;
    }
    $pay = is_array($raw['payment'] ?? null) ? $raw['payment'] : [];
    return [
        'packages' => $packages,
        'postage_fee' => $fee,
        'payment' => [
            'card' => trim((string)($pay['card'] ?? '')),
            'bank' => trim((string)($pay['bank'] ?? '')),
            'holder' => trim((string)($pay['holder'] ?? '')),
            'note' => trim((string)($pay['note'] ?? '')),
        ],
    ];
}

function bookstore_shop_bundle(): array
{
    static $bundle = null;
    if ($bundle !== null) {
        return $bundle;
    }
    $defaults = [
        'packages' => [],
        'postage_fee' => 150000,
        'payment' => ['card' => '6219861926801571', 'bank' => '', 'holder' => '', 'note' => ''],
    ];
    $cacheFile = rtrim(sys_get_temp_dir(), '/') . '/ghazal_live_packages.json';
    $ttl = 45;
    if (is_file($cacheFile) && (time() - (int)filemtime($cacheFile)) < $ttl) {
        $cached = json_decode((string)file_get_contents($cacheFile), true);
        if (is_array($cached) && isset($cached['packages']) && is_array($cached['packages'])) {
            $bundle = bookstore_normalize_shop_bundle($cached);
            return $bundle;
        }
    }
    $url = bookstore_packages_url();
    if ($url === '' || !str_contains($url, 'packages.php')) {
        $bundle = $defaults;
        return $bundle;
    }
    $hit = bookstore_http($url, ['timeout' => 8, 'connect' => 4]);
    if ($hit['ok'] && isset($hit['json']['packages']) && is_array($hit['json']['packages'])) {
        $payload = [
            'fetched_at' => time(),
            'packages' => $hit['json']['packages'],
            'postage_fee' => (int)($hit['json']['postage_fee'] ?? 150000),
            'payment' => is_array($hit['json']['payment'] ?? null) ? $hit['json']['payment'] : $defaults['payment'],
        ];
        @file_put_contents($cacheFile, json_encode($payload, JSON_UNESCAPED_UNICODE));
        $bundle = bookstore_normalize_shop_bundle($payload);
        return $bundle;
    }
    if (is_file($cacheFile)) {
        $cached = json_decode((string)file_get_contents($cacheFile), true);
        if (is_array($cached) && isset($cached['packages']) && is_array($cached['packages'])) {
            $bundle = bookstore_normalize_shop_bundle($cached);
            return $bundle;
        }
    }
    $bundle = $defaults;
    return $bundle;
}

function bookstore_fetch_packages(): ?array
{
    $packages = bookstore_shop_bundle()['packages'];
    return is_array($packages) && $packages !== [] ? $packages : null;
}

function bookstore_postage_fee(): int
{
    return (int)bookstore_shop_bundle()['postage_fee'];
}

function bookstore_payment_info(): array
{
    return bookstore_shop_bundle()['payment'];
}

function bookstore_student_status_label(string $status): string
{
    switch ($status) {
        case 'awaiting_receipt':
            return 'در انتظار رسید';
        case 'pending':
            return 'ثبت شد — در حال بررسی';
        case 'approved':
            return 'تأیید شد';
        case 'ordered':
            return 'خرید شد';
        case 'cancelled':
            return 'لغو شده';
        default:
            return $status;
    }
}

function bookstore_student_orders(string $studentCode): array
{
    $studentCode = trim($studentCode);
    if ($studentCode === '') {
        return [];
    }
    $url = bookstore_api_url('student-orders.php');
    if ($url === '') {
        return [];
    }
    $sep = str_contains($url, '?') ? '&' : '?';
    $hit = bookstore_http($url . $sep . 'student_code=' . rawurlencode($studentCode), ['timeout' => 12]);
    if (!$hit['ok'] || !isset($hit['json']['orders']) || !is_array($hit['json']['orders'])) {
        return [];
    }
    return $hit['json']['orders'];
}

function bookstore_upload_receipt(array $student, string $tracking, array $file): array
{
    $tracking = trim($tracking);
    $code = trim((string)($student['username'] ?? ''));
    if ($tracking === '' || $code === '') {
        return ['ok' => false, 'error' => 'کد سفارش نامعتبر است.'];
    }
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'error' => 'فایل رسید را انتخاب کنید.'];
    }
    $tmp = (string)($file['tmp_name'] ?? '');
    if ($tmp === '' || !is_uploaded_file($tmp)) {
        return ['ok' => false, 'error' => 'بارگذاری رسید نامعتبر است.'];
    }
    $size = (int)($file['size'] ?? 0);
    if ($size <= 0 || $size > 6 * 1024 * 1024) {
        return ['ok' => false, 'error' => 'حجم رسید باید کمتر از ۶ مگابایت باشد.'];
    }
    $url = bookstore_api_url('panel-receipt.php');
    $mime = 'application/octet-stream';
    if (class_exists('finfo')) {
        $detected = (new finfo(FILEINFO_MIME_TYPE))->file($tmp);
        if (is_string($detected) && $detected !== '') {
            $mime = $detected;
        }
    }
    $name = (string)($file['name'] ?? 'receipt.jpg');
    $hit = bookstore_http($url, [
        'method' => 'POST',
        'multipart' => [
            'tracking_code' => $tracking,
            'student_code' => $code,
            'receipt' => new CURLFile($tmp, $mime, $name),
        ],
        'timeout' => 40,
    ]);
    if (!$hit['ok']) {
        $message = trim((string)$hit['error']);
        return ['ok' => false, 'error' => $message !== '' ? $message : 'بارگذاری رسید انجام نشد.'];
    }
    return [
        'ok' => true,
        'order' => $hit['json']['order'] ?? null,
        'tracking_code' => (string)($hit['json']['tracking_code'] ?? $tracking),
        'status' => (string)($hit['json']['status'] ?? 'pending'),
    ];
}

function bookstore_packages(): array
{
    return array_keys(bookstore_catalog());
}

function bookstore_package_books(string $code): array
{
    $catalog = bookstore_catalog();
    return array_key_exists($code, $catalog) ? $catalog[$code] : [];
}

function bookstore_package_title(string $code): string
{
    return trim($code);
}

function bookstore_order_title(string $package_code, array $selected_books): string
{
    $package_code = trim($package_code);
    $all = bookstore_package_books($package_code);
    if ($selected_books === [] || ($all !== [] && count($selected_books) === count($all))) {
        return bookstore_package_title($package_code);
    }
    return $package_code . ' · ' . implode(', ', $selected_books);
}

function bookstore_public_catalog(): array
{
    $out = [];
    foreach (bookstore_catalog() as $code => $books) {
        $out[$code] = [
            'title' => bookstore_package_title($code),
            'books' => $books,
        ];
    }
    return $out;
}

function bookstore_split_name(string $full_name): array
{
    $full_name = trim(preg_replace('/\s+/u', ' ', $full_name));
    if ($full_name === '') {
        return ['', ''];
    }
    $parts = preg_split('/\s+/u', $full_name);
    $first = (string)array_shift($parts);
    $last = trim(implode(' ', $parts));
    return [$first, $last];
}

function bookstore_submit_package(array $student, string $package_code, array $book_titles = [], array $options = []): array
{
    $catalog = bookstore_catalog();
    if (!array_key_exists($package_code, $catalog)) {
        return ['ok' => false, 'error' => 'پکیج انتخاب‌شده معتبر نیست.'];
    }

    $available = $catalog[$package_code];
    $selected = [];
    foreach ($book_titles as $title) {
        $title = trim((string)$title);
        if ($title !== '' && (in_array($title, $available, true) || $available === [])) {
            if (!in_array($title, $selected, true)) {
                $selected[] = $title;
            }
        }
    }

    if ($available !== []) {
        if ($selected === []) {
            $selected = $available;
        } else {
            $unknown = [];
            foreach ($book_titles as $title) {
                $title = trim((string)$title);
                if ($title !== '' && !in_array($title, $available, true)) {
                    $unknown[] = $title;
                }
            }
            if ($unknown !== [] && $selected === []) {
                return ['ok' => false, 'error' => 'کتاب انتخاب‌شده در این پکیج نیست.'];
            }
        }
    }

    if (!is_array($options)) {
        $options = [];
    }
    $deliveryCheck = bookstore_validate_delivery(
        (string)($options['delivery_type'] ?? 'pickup'),
        (string)($options['address'] ?? ''),
        (string)($options['postal_code'] ?? '')
    );
    if (empty($deliveryCheck['ok'])) {
        return ['ok' => false, 'error' => (string)($deliveryCheck['error'] ?? 'نوع تحویل را مشخص کنید.')];
    }

    $config = bookstore_config();
    $url = trim((string)($config['url'] ?? ''));
    $token = (string)($config['token'] ?? '');
    if ($url === '' || $token === '') {
        return ['ok' => false, 'error' => 'اتصال فروشگاه کتاب هنوز تنظیم نشده است.'];
    }

    $fullName = trim((string)($student['full_name'] ?? ''));
    $username = trim((string)($student['username'] ?? ''));
    if ($fullName === '') {
        $fullName = $username;
    }
    [$first, $last] = bookstore_split_name($fullName);
    if ($first === '') {
        $first = $username !== '' ? $username : 'دانش‌آموز';
    }

    $orderTitle = bookstore_order_title($package_code, $selected);
    $itemRows = [];
    foreach (($selected !== [] ? $selected : [$orderTitle]) as $title) {
        $row = bookstore_price_row($title);
        $itemRows[] = [
            'title' => $title,
            'unit_price' => $row['sell_price'],
        ];
    }
    $sellTotal = bookstore_selected_sell_total($selected !== [] ? $selected : []);
    $postage = $deliveryCheck['type'] === 'post' ? bookstore_postage_fee() : 0;
    $grandTotal = $sellTotal + $postage;

    $payload = [
        'first_name' => $first,
        'last_name' => $last,
        'mobile' => (string)($student['phone'] ?? ''),
        'package_title' => $orderTitle,
        'student_code' => $username,
        'package_code' => $package_code,
        'items' => $itemRows,
        'delivery_type' => $deliveryCheck['type'],
        'address' => $deliveryCheck['address'],
        'postal_code' => $deliveryCheck['postal_code'],
        'sell_total' => $sellTotal,
        'postage_fee' => $postage,
        'status' => 'awaiting_receipt',
    ];

    $hit = bookstore_http($url, [
        'method' => 'POST',
        'json' => $payload,
        'timeout' => 20,
    ]);
    if (!$hit['ok']) {
        $message = trim((string)$hit['error']);
        if ($hit['json'] === null && $hit['http'] === 0) {
            $message = $message !== '' ? $message : 'ارتباط با فروشگاه کتاب برقرار نشد.';
        }
        return ['ok' => false, 'error' => $message !== '' ? $message : 'ثبت سفارش انجام نشد.'];
    }
    $json = $hit['json'] ?? [];

    return [
        'ok' => true,
        'tracking_code' => (string)($json['tracking_code'] ?? ''),
        'term_title' => (string)($json['term_title'] ?? $orderTitle),
        'status' => (string)($json['status'] ?? 'awaiting_receipt'),
        'sell_total' => $sellTotal,
        'sell_total_label' => bookstore_format_toman($sellTotal > 0 ? $sellTotal : null),
        'postage_fee' => $postage,
        'postage_label' => $deliveryCheck['type'] === 'post'
            ? ($postage > 0 ? bookstore_format_toman($postage) : 'رایگان')
            : 'ندارد (تحویل حضوری)',
        'grand_total' => $grandTotal,
        'grand_total_label' => bookstore_format_toman($grandTotal > 0 ? $grandTotal : null),
        'delivery_type' => $deliveryCheck['type'],
        'delivery_label' => bookstore_delivery_label($deliveryCheck['type']),
        'payment' => bookstore_payment_info(),
    ];
}
