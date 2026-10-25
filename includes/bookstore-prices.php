<?php

function bookstore_story_titles(): array
{
    return [
        'A Day with a Baby',
        'Little Helpers',
        'Where Is It',
        'Doctor Doctor',
        'My Family',
        'Monkeying Around',
        'Silly Squirrel',
        'Just Like Mine',
        'Things That Fly',
        'What Did You Do Yesterday',
        'New Girl in School',
        'Up and Down',
        'Lost',
        'Numbers Numbers',
        'Wonderful Wild Animals',
        'Students in Space',
        'In the Ocean',
        'Tough Task',
        'City Girl',
    ];
}

function bookstore_book_kind(string $title): string
{
    $title = trim($title);
    if ($title === 'Jingle Bells') {
        return 'jingle';
    }
    if ($title === 'Phonics Book' || preg_match('/pamphlet/i', $title)) {
        return 'fixed';
    }
    return in_array($title, bookstore_story_titles(), true) ? 'story' : 'course';
}

function bookstore_fixed_sell_price(string $title): ?int
{
    $kind = bookstore_book_kind($title);
    if ($kind === 'jingle') {
        return 230000;
    }
    if ($kind === 'fixed') {
        return 750000;
    }
    return null;
}

function bookstore_ceil_toman(int $amount): int
{
    if ($amount <= 0) {
        return $amount;
    }
    return (int)(ceil($amount / 10000) * 10000);
}

function bookstore_formula_sell_price(string $title, $inja_price): ?int
{
    $fixed = bookstore_fixed_sell_price($title);
    if ($fixed !== null) {
        return bookstore_ceil_toman($fixed);
    }
    $inja = (int)$inja_price;
    if ($inja <= 0) {
        return null;
    }
    $mult = bookstore_book_kind($title) === 'story' ? 3.0 : 1.35;
    return bookstore_ceil_toman((int)round($inja * $mult));
}

function bookstore_overrides_url(): string
{
    $config = bookstore_config();
    $explicit = trim((string)($config['overrides_url'] ?? ''));
    if ($explicit !== '') {
        return $explicit;
    }
    $url = trim((string)($config['url'] ?? ''));
    if ($url !== '' && str_contains($url, 'panel-order.php')) {
        return str_replace('panel-order.php', 'price-overrides.php', $url);
    }
    return '';
}

function bookstore_fetch_overrides(): ?array
{
    $url = bookstore_overrides_url();
    $token = (string)(bookstore_config()['token'] ?? '');
    if ($url === '' || $token === '' || !str_contains($url, 'price-overrides.php')) {
        return null;
    }
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 8,
        CURLOPT_CONNECTTIMEOUT => 4,
        CURLOPT_HTTPHEADER => [
            'Accept: application/json',
            'X-Ghazal-Books: ' . $token,
            'User-Agent: GhazalPanelBookStore/1.0',
        ],
    ]);
    $raw = curl_exec($ch);
    $errno = curl_errno($ch);
    $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($raw === false || $errno || $http >= 400) {
        return null;
    }
    $json = json_decode((string)$raw, true);
    if (!is_array($json) || empty($json['ok']) || !isset($json['by_title']) || !is_array($json['by_title'])) {
        return null;
    }
    $out = [];
    foreach ($json['by_title'] as $title => $price) {
        $title = trim((string)$title);
        $price = (int)$price;
        if ($title !== '' && $price > 0) {
            $out[$title] = $price;
        }
    }
    return $out;
}

function bookstore_override_map(): array
{
    static $map = null;
    if ($map !== null) {
        return $map;
    }
    $cacheFile = rtrim(sys_get_temp_dir(), '/') . '/ghazal_sell_overrides.json';
    $ttl = 45;
    $useCache = is_file($cacheFile) && (time() - (int)filemtime($cacheFile)) < $ttl;
    if ($useCache) {
        $cached = json_decode((string)file_get_contents($cacheFile), true);
        if (is_array($cached) && isset($cached['by_title']) && is_array($cached['by_title'])) {
            $map = $cached['by_title'];
            return $map;
        }
    }
    $fresh = bookstore_fetch_overrides();
    if ($fresh !== null) {
        @file_put_contents($cacheFile, json_encode([
            'fetched_at' => time(),
            'by_title' => $fresh,
        ], JSON_UNESCAPED_UNICODE));
        $map = $fresh;
        return $map;
    }
    if (is_file($cacheFile)) {
        $cached = json_decode((string)file_get_contents($cacheFile), true);
        if (is_array($cached) && isset($cached['by_title']) && is_array($cached['by_title'])) {
            $map = $cached['by_title'];
            return $map;
        }
    }
    $map = [];
    return $map;
}

function bookstore_sell_price(string $title, $inja_price): ?int
{
    $title = trim($title);
    $overrides = bookstore_override_map();
    if (isset($overrides[$title]) && (int)$overrides[$title] > 0) {
        return bookstore_ceil_toman((int)$overrides[$title]);
    }
    return bookstore_formula_sell_price($title, $inja_price);
}

function bookstore_kind_label(string $kind): string
{
    switch ($kind) {
        case 'jingle':
            return 'قیمت ثابت جینگل بلز';
        case 'fixed':
            return 'قیمت ثابت پمفلت / فونیکس';
        case 'story':
            return 'استوری‌بوک × ۳';
        default:
            return 'اینجا بوک × ۱٫۳۵';
    }
}

function bookstore_format_toman($amount): string
{
    if ($amount === null || $amount === '') {
        return '—';
    }
    return number_format((int)$amount, 0, '.', ',') . ' تومان';
}

function bookstore_delivery_label($type): string
{
    return $type === 'post' ? 'ارسال با پست' : 'تحویل حضوری';
}

function bookstore_inja_title_map(): array
{
    return [
        'Phonics Book' => 11046,
        'Phonics Pamphlet' => null,
        'A Day with a Baby' => null,
        'Little Helpers' => 9466,
        'Where Is It' => 9424,
        'P1 Package' => null,
        'Doctor Doctor' => 9413,
        'My Family' => 9418,
        'Monkeying Around' => 9414,
        'Silly Squirrel' => 9412,
        'Backpack 2' => 10518,
        'Just Like Mine' => 9476,
        'Things That Fly' => 9479,
        'Backpack Pamphlet' => null,
        'Backpack 3' => 10519,
        'What Did You Do Yesterday' => 9478,
        'Backpack 4' => 10520,
        'New Girl in School' => 9474,
        'Up and Down' => 9517,
        "Let's Go 2" => 10946,
        'Picture Dictionary' => 10981,
        'Jingle Bells' => null,
        "Let's Go Pamphlet" => null,
        'Lost' => 9472,
        'Numbers Numbers' => 56446,
        "Let's Go 3" => 10974,
        'Wonderful Wild Animals' => 9475,
        'Students in Space' => 9477,
        "Let's Go 4" => 10976,
        'In the Ocean' => 9483,
        "Let's Go 5" => 10978,
        'Tough Task' => 9482,
        "Let's Go 6" => 10980,
        'City Girl' => 9485,
        'Talk Time 1' => null,
        'Talk Time Pamphlet' => null,
        'Talk Time 2' => null,
        'Talk Time 3' => null,
        'Top Notch Fundamentals A' => 10229,
        'Top Notch Fundamentals B' => 10230,
        'Top Notch Fundamentals Pamphlet' => null,
        'Top Notch 1A' => 10231,
        'Top Notch 1B' => 10232,
        'Top Notch 1 Pamphlet' => null,
        'Phrasal Verbs Intermediate' => 12573,
        'Top Notch 2A' => 10233,
        'Top Notch 2B' => 10234,
        'Top Notch 2 Pamphlet' => null,
        'Top Notch 3A' => 10235,
        'Top Notch 3B' => 10276,
        'Top Notch 3 Pamphlet' => null,
        'Summit 1A' => 10225,
        'Summit 1B' => 10226,
        'Summit 1 Pamphlet' => null,
        'Phrasal Verbs Advanced' => 12572,
        'Summit 2A' => 10227,
        'Summit 2B' => 10228,
        'Summit 2 Pamphlet' => null,
        'Ready for First' => 36244,
        'Ready for CAE' => 36242,
        'Objective Proficiency' => null,
    ];
}

function bookstore_inja_http_products(array $ids): array
{
    $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
    if ($ids === []) {
        return [];
    }
    $url = 'https://injabook.com/wp-json/wc/store/v1/products?' . http_build_query([
        'include' => implode(',', $ids),
        'per_page' => max(count($ids), 1),
        'orderby' => 'include',
    ]);
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 12,
        CURLOPT_CONNECTTIMEOUT => 6,
        CURLOPT_HTTPHEADER => [
            'Accept: application/json',
            'User-Agent: GhazalPanelBookStore/1.0',
        ],
    ]);
    $raw = curl_exec($ch);
    $errno = curl_errno($ch);
    $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($raw === false || $errno || $http >= 400) {
        return [];
    }
    $list = json_decode((string)$raw, true);
    if (!is_array($list)) {
        return [];
    }
    $out = [];
    foreach ($list as $item) {
        if (!is_array($item)) {
            continue;
        }
        $id = (int)($item['id'] ?? 0);
        $price = (int)($item['prices']['price'] ?? 0);
        if ($id > 0 && $price > 0) {
            $out[$id] = $price;
        }
    }
    return $out;
}

function bookstore_inja_price_map(): array
{
    static $map = null;
    if ($map !== null) {
        return $map;
    }
    $cacheFile = rtrim(sys_get_temp_dir(), '/') . '/ghazal_inja_sell_prices.json';
    $ttl = 20 * 60;
    if (is_file($cacheFile) && (time() - (int)filemtime($cacheFile)) < $ttl) {
        $cached = json_decode((string)file_get_contents($cacheFile), true);
        if (is_array($cached) && !empty($cached['by_title'])) {
            $map = $cached['by_title'];
            return $map;
        }
    }

    $ids = [];
    foreach (bookstore_inja_title_map() as $id) {
        if (is_int($id) && $id > 0) {
            $ids[] = $id;
        }
    }
    $products = bookstore_inja_http_products($ids);
    $byTitle = [];
    foreach (bookstore_inja_title_map() as $title => $id) {
        if (is_int($id) && isset($products[$id])) {
            $byTitle[$title] = (int)$products[$id];
        }
    }
    if ($byTitle !== []) {
        @file_put_contents($cacheFile, json_encode([
            'fetched_at' => time(),
            'by_title' => $byTitle,
        ], JSON_UNESCAPED_UNICODE));
    } elseif (is_file($cacheFile)) {
        $cached = json_decode((string)file_get_contents($cacheFile), true);
        if (is_array($cached) && !empty($cached['by_title'])) {
            $map = $cached['by_title'];
            return $map;
        }
    }
    $map = $byTitle;
    return $map;
}

function bookstore_price_row(string $title): array
{
    $inja = bookstore_inja_price_map();
    $injaPrice = $inja[$title] ?? 0;
    $kind = bookstore_book_kind($title);
    $computed = bookstore_formula_sell_price($title, $injaPrice);
    $overrides = bookstore_override_map();
    $isManual = isset($overrides[$title]) && (int)$overrides[$title] > 0;
    $sell = bookstore_sell_price($title, $injaPrice);
    return [
        'title' => $title,
        'kind' => $kind,
        'inja_price' => $injaPrice > 0 ? $injaPrice : null,
        'computed_price' => $computed,
        'is_manual' => $isManual,
        'sell_price' => $sell,
        'sell_label' => bookstore_format_toman($sell),
    ];
}

function bookstore_priced_catalog(): array
{
    $out = [];
    foreach (bookstore_catalog() as $code => $books) {
        $rows = [];
        $total = 0;
        $priced = 0;
        foreach ($books as $title) {
            $row = bookstore_price_row($title);
            $rows[] = $row;
            if ($row['sell_price'] !== null) {
                $total += (int)$row['sell_price'];
                $priced++;
            }
        }
        $out[$code] = [
            'title' => bookstore_package_title($code),
            'books' => $rows,
            'package_total' => $total,
            'package_total_label' => bookstore_format_toman($total > 0 ? $total : null),
            'priced_count' => $priced,
        ];
    }
    return $out;
}

function bookstore_normalize_delivery($type): string
{
    $type = trim((string)$type);
    return $type === 'post' ? 'post' : 'pickup';
}

function bookstore_normalize_postal($code): string
{
    $code = strtr(trim((string)$code), [
        '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
        '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
        '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
        '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
    ]);
    return preg_replace('/\D+/', '', $code) ?? '';
}

function bookstore_validate_delivery(string $type, string $address, string $postal): array
{
    $type = bookstore_normalize_delivery($type);
    $address = trim($address);
    $postal = bookstore_normalize_postal($postal);
    if ($type !== 'post') {
        return ['ok' => true, 'type' => 'pickup', 'address' => '', 'postal_code' => ''];
    }
    if (mb_strlen($address, 'UTF-8') < 8) {
        return ['ok' => false, 'error' => 'برای ارسال پستی، آدرس کامل را وارد کنید.'];
    }
    if (!preg_match('/^\d{10}$/', $postal)) {
        return ['ok' => false, 'error' => 'کد پستی باید ۱۰ رقم باشد.'];
    }
    return ['ok' => true, 'type' => 'post', 'address' => $address, 'postal_code' => $postal];
}

function bookstore_selected_sell_total(array $titles): int
{
    $sum = 0;
    foreach ($titles as $title) {
        $row = bookstore_price_row((string)$title);
        if ($row['sell_price'] !== null) {
            $sum += (int)$row['sell_price'];
        }
    }
    return $sum;
}

function bookstore_apply_accounting(PDO $panel_pdo, array $student, string $book_name, int $sell_total): array
{
    if ($sell_total <= 0) {
        return ['ok' => false, 'skipped' => 'no-price'];
    }
    try {
        require_once __DIR__ . '/identity-sync.php';
        $GLOBALS['identity_panel_pdo'] = $panel_pdo;
        $userId = (int)($student['id'] ?? 0);
        $code = identity_normalize_code($student['username'] ?? '');
        if ($userId <= 0 || $code === '') {
            return ['ok' => false, 'skipped' => 'no-code'];
        }
        $classes = identity_student_active_classes($panel_pdo, $userId);
        $applied = [];
        $seen = [];
        foreach ($classes as $class) {
            identity_sync_student_for_class($panel_pdo, $userId, $class, $student);
            $term = term_load($panel_pdo, (int)($class['term_id'] ?? 0));
            $accTerm = $term ? term_find_accounting_id($term) : '';
            if ($accTerm === '') {
                continue;
            }
            if (isset($seen[$accTerm])) {
                continue;
            }
            $seen[$accTerm] = true;
            $accId = identity_find_acc_student_in_term($code, $accTerm);
            if ($accId <= 0) {
                continue;
            }
            $hit = bookstore_patch_acc_book($accId, $book_name, $sell_total);
            if ($hit) {
                $applied[] = $accId;
            }
        }
        if ($applied === []) {
            $accTerm = identity_active_term_id();
            $accId = $accTerm !== '' ? identity_find_acc_student_in_term($code, $accTerm) : 0;
            if ($accId > 0) {
                bookstore_patch_acc_book($accId, $book_name, $sell_total);
                $applied[] = $accId;
            }
        }
        return ['ok' => $applied !== [], 'acc_ids' => $applied];
    } catch (Throwable $e) {
        error_log('bookstore acc: ' . $e->getMessage());
        return ['ok' => false, 'error' => 'accounting-failed'];
    }
}

function bookstore_patch_acc_book(int $acc_id, string $book_name, int $sell_total): bool
{
    if ($acc_id <= 0 || $sell_total <= 0) {
        return false;
    }
    $acc = accounting_pdo();
    $stmt = $acc->prepare('SELECT id, hasBook, bookName, bookPrice, totalPayable, amountPaid FROM students WHERE id = ? LIMIT 1');
    $stmt->execute([$acc_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return false;
    }
    $prevName = trim((string)($row['bookName'] ?? ''));
    $prevPrice = !empty($row['hasBook']) ? (int)$row['bookPrice'] : 0;
    $book_name = trim($book_name);
    if ($prevName !== '' && $book_name !== '' && mb_stripos($prevName, $book_name, 0, 'UTF-8') === false) {
        $nextName = $prevName . ' · ' . $book_name;
    } elseif ($prevName !== '') {
        $nextName = $prevName;
    } else {
        $nextName = $book_name !== '' ? $book_name : 'کتاب آموزشی';
    }
    if (mb_strlen($nextName, 'UTF-8') > 180) {
        $nextName = mb_substr($nextName, 0, 177, 'UTF-8') . '…';
    }
    $nextPrice = $prevPrice + $sell_total;
    $total = (int)$row['totalPayable'] + $sell_total;
    $paid = (int)$row['amountPaid'];
    $debt = $total - $paid;
    $status = accounting_status_from_amounts($total, $paid);
    $acc->prepare("
        UPDATE students
        SET hasBook = 1,
            bookName = ?,
            bookPrice = ?,
            totalPayable = ?,
            debt = ?,
            status = ?
        WHERE id = ?
    ")->execute([$nextName, $nextPrice, $total, $debt, $status, $acc_id]);
    return true;
}
