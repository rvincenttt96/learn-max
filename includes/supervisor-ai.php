<?php
/**
 * Read-only supervisor assistant via Liara (OpenAI-compatible tools).
 * No write tools: cannot create classes or change attendance.
 */

function supervisor_ai_config() {
    static $cfg = null;
    if ($cfg !== null) {
        return $cfg;
    }
    $path = __DIR__ . '/liara-ai-config.php';
    $cfg = is_file($path) ? (require $path) : [];
    if (!is_array($cfg)) {
        $cfg = [];
    }
    return $cfg;
}

function supervisor_ai_json($data) {
    return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function supervisor_ai_norm($value) {
    $value = trim((string)$value);
    if (function_exists('ghazal_normalize_digits')) {
        $value = ghazal_normalize_digits($value);
    } elseif (function_exists('jalali_to_english_digits')) {
        $value = jalali_to_english_digits($value);
    }
    return trim(preg_replace('/\s+/u', ' ', $value));
}

function supervisor_ai_unfold_finglish($text) {
    $t = supervisor_ai_norm($text);
    if ($t === '') {
        return $t;
    }
    if (preg_match('/[A-Za-z]/', $t)) {
        $map = [
            'daneshamoozan' => 'دانش‌آموزان',
            'daneshamozan' => 'دانش‌آموزان',
            'daneshamoozha' => 'دانش‌آموزان',
            'daneshamooz' => 'دانش‌آموز',
            'daneshamoz' => 'دانش‌آموز',
            'bachehaye' => 'بچه‌های',
            'bacheha' => 'بچه‌ها',
            'kelasshaye' => 'کلاس‌های',
            'kelassha' => 'کلاس‌ها',
            'kelass' => 'کلاس',
            'kelas' => 'کلاس',
            'classes' => 'کلاس',
            'class' => 'کلاس',
            'nomarat' => 'نمرات',
            'nomreha' => 'نمرات',
            'nomreh' => 'نمره',
            'nomre' => 'نمره',
            'karnameh' => 'کارنامه',
            'karname' => 'کارنامه',
            'ghableshon' => 'قبلشان',
            'ghablshon' => 'قبلشان',
            'ghablshan' => 'قبلشان',
            'ghabli' => 'قبلی',
            'ghabl' => 'قبل',
            'aghaye' => 'آقای',
            'aghaie' => 'آقای',
            'agha' => 'آقا',
            'khanoom' => 'خانم',
            'khanoum' => 'خانم',
            'khanom' => 'خانم',
            'khanum' => 'خانم',
            'manzooram' => 'منظورم',
            'manzorem' => 'منظورم',
            'lotfan' => 'لطفا',
            'offline' => 'آفلاین',
            'ofline' => 'آفلاین',
            'online' => 'آنلاین',
            'onlin' => 'آنلاین',
            'anline' => 'آنلاین',
            'alocom' => 'الوکام',
            'abzroo' => 'آبزرو',
            'abzro' => 'آبزرو',
            'abzero' => 'آبزرو',
            'arzyabi' => 'ارزیابی',
            'arzyabii' => 'ارزیابی',
            'terms' => 'ترم',
            'term' => 'ترم',
            'quiz' => 'کوییز',
            'link' => 'لینک',
            'bede' => 'بده',
            'bedeh' => 'بده',
            'begoo' => 'بگو',
            'bego' => 'بگو',
            'jari' => 'جاری',
            'please' => 'لطفا',
            'give' => 'بده',
            'tell' => 'بگو',
        ];
        uksort($map, function ($a, $b) {
            return strlen($b) - strlen($a);
        });
        foreach ($map as $en => $fa) {
            $t = preg_replace('/(?<![A-Za-z])' . preg_quote($en, '/') . '(?![A-Za-z])/iu', $fa, $t);
        }
        $t = preg_replace('/(?<![A-Za-z])ro(?![A-Za-z])/iu', 'را', $t);
        $t = preg_replace('/(?<![A-Za-z])be(?![A-Za-z])/iu', 'به', $t);
        $t = preg_replace('/(?<![A-Za-z])man(?![A-Za-z])/iu', 'من', $t);
    }
    $t = str_replace(['انلاین', 'ان‌لاین'], 'آنلاین', $t);
    $t = str_replace(['افلاین', 'اف‌لاین'], 'آفلاین', $t);
    return supervisor_ai_norm($t);
}

function supervisor_ai_strip_honorific($query) {
    $q = supervisor_ai_unfold_finglish($query);
    $q = preg_replace('/^(آقای|اقای|آقا|خانم|mr\.?|mrs\.?|ms\.?|miss|mister)\s+/iu', '', $q);
    $q = preg_replace('/\s+(آقای|اقای|آقا|خانم)$/u', '', $q);
    return supervisor_ai_norm($q);
}

function supervisor_ai_honorific_gender($text) {
    $t = supervisor_ai_unfold_finglish($text);
    if (preg_match('/خانم|\b(ms|mrs|miss)\b/iu', $t)) {
        return 'female';
    }
    if (preg_match('/آقای|اقای|\bآقا\b|\b(mr|mister)\b/iu', $t)) {
        return 'male';
    }
    return '';
}

function supervisor_ai_given_name_gender($full_name) {
    $full = supervisor_ai_norm($full_name);
    if ($full === '') {
        return '';
    }
    $parts = preg_split('/\s+/u', $full);
    $first = (string)($parts[0] ?? '');
    $low = function_exists('mb_strtolower') ? mb_strtolower($first, 'UTF-8') : strtolower($first);
    $fold = supervisor_ai_fold_latin($first);
    $male = [
        'محمد', 'مهدی', 'علی', 'امیر', 'حسین', 'رضا', 'متین', 'عماد', 'اماد', 'محسن',
        'حسن', 'حمید', 'سعید', 'احمد', 'مجید', 'جواد', 'ایمان', 'پویا', 'امیرحسین',
        'mohammad', 'muhammad', 'mehdi', 'ali', 'amir', 'hossein', 'reza', 'matin',
        'emad', 'mohsen', 'hassan', 'hamid', 'saeed', 'ahmad', 'majid', 'javad',
        'iman', 'pouya',
    ];
    $female = [
        'سمیرا', 'زهرا', 'نسترن', 'ساغر', 'مهتا', 'یگانه', 'نازنین', 'آیلار', 'ریحانه',
        'شیما', 'کوثر', 'زینب', 'سوفیا', 'نگین', 'مارال', 'مهسا', 'فاطمه', 'مریم',
        'نرگس', 'سارا', 'الناز', 'هانیه', 'پریسا', 'محدثه', 'فاطمه',
        'samira', 'zahra', 'nastaran', 'saghar', 'mahta', 'yegane', 'yeganeh',
        'nazanin', 'aylar', 'reyhane', 'reyhaneh', 'shima', 'kosar', 'zeinab',
        'sofia', 'sophia', 'negin', 'maral', 'mahsa', 'fatemeh', 'maryam',
        'narges', 'sara', 'elnaz', 'hanieh', 'parisa',
    ];
    foreach ($male as $n) {
        $nl = function_exists('mb_strtolower') ? mb_strtolower($n, 'UTF-8') : strtolower($n);
        if ($nl === $low || ($fold !== '' && supervisor_ai_fold_latin($n) === $fold)) {
            return 'male';
        }
    }
    foreach ($female as $n) {
        $nl = function_exists('mb_strtolower') ? mb_strtolower($n, 'UTF-8') : strtolower($n);
        if ($nl === $low || ($fold !== '' && supervisor_ai_fold_latin($n) === $fold)) {
            return 'female';
        }
    }
    return '';
}

function supervisor_ai_filter_people_by_gender(array $people, $gender) {
    if ($gender !== 'male' && $gender !== 'female') {
        return $people;
    }
    $matched = [];
    foreach ($people as $p) {
        if (($p['role'] ?? '') !== 'teacher') {
            continue;
        }
        if (supervisor_ai_given_name_gender((string)($p['full_name'] ?? '')) === $gender) {
            $matched[] = $p;
        }
    }
    if (!$matched) {
        return $people;
    }
    $out = [];
    foreach ($people as $p) {
        if (($p['role'] ?? '') !== 'teacher') {
            $out[] = $p;
            continue;
        }
        if (supervisor_ai_given_name_gender((string)($p['full_name'] ?? '')) === $gender) {
            $out[] = $p;
        }
    }
    return array_values($out);
}

function supervisor_ai_asks_previous_term($text) {
    $t = supervisor_ai_unfold_finglish($text);
    return (bool)preg_match('/ترم\s*قبل|ترم\s*پیش|previous\s*term|last\s*term/iu', $t);
}

function supervisor_ai_tz() {
    date_default_timezone_set('Asia/Tehran');
}

function supervisor_ai_today() {
    supervisor_ai_tz();
    return date('Y-m-d');
}

function supervisor_ai_pretty_date($gregorian) {
    if (!function_exists('jalali_pretty')) {
        require_once __DIR__ . '/jalali.php';
    }
    $pretty = jalali_pretty($gregorian);
    return $pretty !== '' ? $pretty : $gregorian;
}

function supervisor_ai_pretty_datetime($ts) {
    $ts = trim((string)$ts);
    if ($ts === '') {
        return '';
    }
    $day = supervisor_ai_pretty_date(substr($ts, 0, 10));
    $time = '';
    if (preg_match('/\s(\d{1,2}:\d{2})/', $ts, $m)) {
        $time = $m[1];
    }
    return $time !== '' ? ($day . '، ساعت ' . $time) : $day;
}

function supervisor_ai_weekday_fa($gregorian) {
    supervisor_ai_tz();
    $w = (int)date('w', strtotime($gregorian . ' 12:00:00'));
    $names = ['یکشنبه', 'دوشنبه', 'سه‌شنبه', 'چهارشنبه', 'پنجشنبه', 'جمعه', 'شنبه'];
    return $names[$w] ?? '';
}

function supervisor_ai_schedule_label($schedule_type) {
    $t = strtolower(trim((string)$schedule_type));
    if ($t === 'odd') {
        return 'فرد (یکشنبه/سه‌شنبه/پنجشنبه)';
    }
    if ($t === 'even') {
        return 'زوج (شنبه/دوشنبه/چهارشنبه)';
    }
    return 'نامشخص';
}

function supervisor_ai_date_pack($gregorian, $relative = '') {
    $w = (int)date('w', strtotime($gregorian . ' 12:00:00'));
    if ($w === 5) {
        $kind = 'جمعه';
    } elseif (in_array($w, [6, 1, 3], true)) {
        $kind = 'زوج';
    } else {
        $kind = 'فرد';
    }
    return [
        'gregorian' => $gregorian,
        'jalali' => supervisor_ai_pretty_date($gregorian),
        'weekday' => supervisor_ai_weekday_fa($gregorian),
        'day_kind' => $kind,
        'relative' => $relative,
    ];
}

function supervisor_ai_resolve_date($raw) {
    supervisor_ai_tz();
    if (!function_exists('jalali_parse_any_date')) {
        require_once __DIR__ . '/jalali.php';
    }
    $raw = supervisor_ai_norm($raw);
    $today = date('Y-m-d');
    if ($raw === '' || $raw === 'today' || $raw === 'امروز' || $raw === 'الان') {
        return supervisor_ai_date_pack($today, 'امروز');
    }
    if ($raw === 'yesterday' || $raw === 'دیروز') {
        return supervisor_ai_date_pack(date('Y-m-d', strtotime('-1 day')), 'دیروز');
    }
    if ($raw === 'پریروز') {
        return supervisor_ai_date_pack(date('Y-m-d', strtotime('-2 days')), 'پریروز');
    }
    $week = [
        'شنبه' => 6,
        'یکشنبه' => 0,
        'دوشنبه' => 1,
        'سه‌شنبه' => 2,
        'سه شنبه' => 2,
        'چهارشنبه' => 3,
        'پنجشنبه' => 4,
        'پنج‌شنبه' => 4,
        'جمعه' => 5,
    ];
    if (isset($week[$raw])) {
        $want = $week[$raw];
        $ts = strtotime($today . ' 12:00:00');
        for ($i = 0; $i < 7; $i++) {
            $g = date('Y-m-d', $ts);
            if ((int)date('w', $ts) === $want) {
                $rel = $g === $today ? 'امروز' : '';
                return supervisor_ai_date_pack($g, $rel);
            }
            $ts -= 86400;
        }
    }
    $named = supervisor_ai_parse_month_date($raw);
    if ($named !== '') {
        return supervisor_ai_date_pack($named, '');
    }
    try {
        $g = jalali_parse_any_date($raw);
        if (!$g) {
            return [
                'error' => 'تاریخ نامفهوم است. امروز ' . supervisor_ai_pretty_date($today)
                    . ' است. برای دیروز همان واژهٔ «دیروز» را بفرست، تاریخ را خودت تبدیل نکن.',
            ];
        }
        return supervisor_ai_date_pack($g, '');
    } catch (Exception $e) {
        return [
            'error' => 'تاریخ نامفهوم است. امروز ' . supervisor_ai_pretty_date($today)
                . ' است. برای دیروز همان واژهٔ «دیروز» را بفرست، تاریخ را خودت تبدیل نکن.',
        ];
    }
}

function supervisor_ai_parse_clock($text) {
    $text = supervisor_ai_norm($text);
    if (preg_match('/(?<!\d)([01]?\d|2[0-3])[:.٫]([0-5]\d)(?!\d)/u', $text, $m)) {
        return sprintf('%02d:%02d', (int)$m[1], (int)$m[2]);
    }
    return '';
}

function supervisor_ai_class_clock($class) {
    $raw = trim((string)($class['schedule'] ?? ''));
    if (preg_match('/([01]?\d|2[0-3])[:.٫]([0-5]\d)/u', $raw, $m)) {
        return sprintf('%02d:%02d', (int)$m[1], (int)$m[2]);
    }
    return '';
}

function supervisor_ai_asks_session_n($text) {
    $t = supervisor_ai_norm($text);
    if (preg_match('/آخرین\s*جلسه|جلسه\s*آخر|last\s*session/iu', $t)) {
        return -1;
    }
    if (preg_match('/اولین\s*جلسه|first\s*session/iu', $t)) {
        return 1;
    }
    if (!preg_match('/جلسه\s*(اول|دوم|سوم|چهارم|پنجم|ششم|[0-9]{1,2})\b/u', $t, $m)) {
        return 0;
    }
    $map = [
        'اول' => 1, 'دوم' => 2, 'سوم' => 3, 'چهارم' => 4, 'پنجم' => 5, 'ششم' => 6,
        '1' => 1, '2' => 2, '3' => 3, '4' => 4, '5' => 5, '6' => 6,
    ];
    $k = supervisor_ai_norm($m[1]);
    if (isset($map[$k])) {
        return $map[$k];
    }
    $n = (int)$k;
    return $n > 0 ? $n : 0;
}

function supervisor_ai_asks_missing_phone($text) {
    $t = supervisor_ai_norm($text);
    if (preg_match('/شماره\s*پرونده/u', $t) && !preg_match('/تماس|تلفن|موبایل|phone/iu', $t)) {
        return false;
    }
    if (!preg_match('/شماره(?:\s*تماس)?|تلفن|موبایل|تماس|فون|phone|mobile/iu', $t)) {
        return false;
    }
    return (bool)preg_match('/ندارن|ندارد|نداره|ندارند|نیست|نیستن|ثبت\s*نشده|خالی|بدون|missing|without|no\s*phone/iu', $t);
}

function supervisor_ai_parse_day_filter($raw) {
    $raw = supervisor_ai_norm($raw);
    if ($raw === '') {
        return '';
    }
    if (preg_match('/زوج|even|شنبه|دوشنبه|چهارشنبه/u', $raw)) {
        return 'even';
    }
    if (preg_match('/فرد|odd|یکشنبه|سه‌شنبه|سه\s*شنبه|پنجشنبه|پنج‌شنبه/u', $raw)) {
        return 'odd';
    }
    return '';
}

function supervisor_ai_asks_classes($text) {
    $text = supervisor_ai_unfold_finglish($text);
    return (bool)preg_match('/کلاس|ملاس|class(?:es)?|kelass?/iu', $text);
}

function supervisor_ai_asks_class_link($text) {
    $t = supervisor_ai_unfold_finglish($text);
    return (bool)preg_match('/لینک|link|الوکام|alocom|ورود به\s*(کلاس|اتاق)|آدرس\s*(کلاس|اتاق|جلسه)/iu', $t);
}

function supervisor_ai_parse_class_type($text) {
    $t = supervisor_ai_unfold_finglish($text);
    if (preg_match('/خصوصی|private/iu', $t)) {
        return 'private';
    }
    if (preg_match('/آنلاین/u', $t) || preg_match('/\bonline\b/iu', $t)) {
        return 'online';
    }
    if (preg_match('/آفلاین/u', $t) || preg_match('/\boffline\b/iu', $t)) {
        return 'offline';
    }
    return '';
}

function supervisor_ai_class_type_label($type) {
    $t = strtolower(trim((string)$type));
    if ($t === 'online') {
        return 'آنلاین';
    }
    if ($t === 'offline') {
        return 'آفلاین';
    }
    if ($t === 'private') {
        return 'خصوصی';
    }
    return $t !== '' ? $t : 'نامشخص';
}

function supervisor_ai_asks_today($text) {
    return (bool)preg_match('/امروز|الان|today|now/iu', $text);
}

function supervisor_ai_asks_absences($text) {
    $text = supervisor_ai_norm($text);
    if (supervisor_ai_asks_missing_rollcall($text) || supervisor_ai_asks_missing_session_plan($text)) {
        return false;
    }
    return (bool)preg_match('/غایب|غیبت|absent/iu', $text);
}

function supervisor_ai_asks_missing_rollcall($text) {
    $text = supervisor_ai_norm($text);
    if (!preg_match('/رول[\s‌\-]*کال|رولکال|roll[\s\-]*call|حضور\s*غیاب|حضورغیاب/iu', $text)) {
        return false;
    }
    return (bool)preg_match('/نزد|نزده|نزن|نکرد|نشده|ننوشت|بدون|missing|didn|without|who (?:has )?not/iu', $text);
}

function supervisor_ai_asks_missing_session_plan($text) {
    $text = supervisor_ai_norm($text);
    if (!preg_match('/سشن|session\s*plan|طرح[\s‌]*جلسه|session[\s_\-]*plan/iu', $text)) {
        return false;
    }
    return (bool)preg_match('/ننوشت|ننشوت|نزده|نکرد|نشده|بدون|missing|didn|without|who (?:has )?not/iu', $text);
}

function supervisor_ai_asks_nobody($text) {
    $text = supervisor_ai_norm($text);
    return (bool)preg_match('/هیچ\s*کس|هیچکس|هیچ‌کس|هیچ\s*معلم|هیچ‌معلم|مگر\s+کسی|آیا\s+کسی|واقعاً\s+هیچ/u', $text);
}

function supervisor_ai_asks_who_did($text) {
    $text = supervisor_ai_norm($text);
    if (preg_match('/نزد|نزده|نزن|نکرد|نشده|ننوشت|ننشوت|بدون|نداده|ندادن|ندارن/u', $text)) {
        return false;
    }
    if (supervisor_ai_asks_nobody($text)) {
        return false;
    }
    return (bool)preg_match('/کی\s+.+(نوشته|زده)|چه\s*کسی\s+.+(نوشته|زده)|کدوم.+(نوشته|زده)|چه\s*معلم.+(نوشته|زده)|نوشته‌اند|نوشتن\b|زده‌اند|زده اند/u', $text);
}

function supervisor_ai_gap_answer_mode($text, $topic = '') {
    $text = supervisor_ai_norm($text);
    if (supervisor_ai_asks_nobody($text) || preg_match('/همه\s+(نوشته|زده|ننوشته|نزده)/u', $text)) {
        return 'nobody';
    }
    if (supervisor_ai_asks_who_did($text) || ($topic === 'session_plan' && preg_match('/نوشته|زده/u', $text) && !preg_match('/ننوشت|نزده|نکرد/u', $text))) {
        return 'written';
    }
    return 'missing';
}

function supervisor_ai_asks_those_followup($text) {
    return (bool)preg_match('/اونایی|اون‌هایی|اونا\b|اونها|همونا|همان‌ها|کسایی\s+که|کسانی\s+که|همونا\s+که|همون\s+کسایی/u', $text);
}

function supervisor_ai_asks_teacher_list($text) {
    $text = supervisor_ai_norm($text);
    if (preg_match('/کلاس/u', $text) && !preg_match('/معلم|استاد/u', $text)) {
        return false;
    }
    return (bool)preg_match('/چه\s*معلم|کدوم\s*معلم|کدام\s*معلم|معلم‌هایی|معلما|اساتید|کدام استاد|چه استاد/u', $text);
}

function supervisor_ai_is_date_followup($text) {
    $t = supervisor_ai_norm($text);
    if (preg_match('/^(پس\s+|خب\s+|و\s+|برای\s+|هم\s+)?(دیروز|امروز|پریروز|yesterday|today)(\s+(چطور|چطوری|چی|چه|چیه|چطوره|هم|باشه|نی؟?|شد|بود|چی\s*شد|چطور\s*شد|بگو|هم\s+بگو))?[\s?؟!.]*$/iu', $t)) {
        return true;
    }
    return (bool)preg_match('/^(پس\s+|خب\s+|و\s+)?برای\s+(دیروز|امروز|پریروز)[\s?؟!.]*$/iu', $t);
}

function supervisor_ai_asks_exact_when($text) {
    $t = supervisor_ai_unfold_finglish($text);
    return (bool)preg_match('/تاریخ\s*دقیق|ساعت\s*دقیق|زمان\s*دقیق|دقیقاً?\s*(کی|چه\s*ساعت|چه\s*تاریخ)|ساعتش|تاریخش|exact\s*(date|time)/iu', $t);
}

function supervisor_ai_date_from_text($text) {
    $text = supervisor_ai_norm($text);
    if (preg_match('/پریروز/u', $text)) {
        return 'پریروز';
    }
    if (preg_match('/دیروز|yesterday/iu', $text)) {
        return 'دیروز';
    }
    if (preg_match('/امروز|today/iu', $text) && !preg_match('/تا\s*(الان|امروز|حالا)/u', $text)) {
        return 'امروز';
    }
    return '';
}

function supervisor_ai_jalali_month_number($name) {
    if (!function_exists('jalali_month_names')) {
        require_once __DIR__ . '/jalali.php';
    }
    $name = supervisor_ai_norm($name);
    foreach (jalali_month_names() as $num => $label) {
        if ($label === $name) {
            return (int)$num;
        }
    }
    return 0;
}

function supervisor_ai_date_range_from_text($text) {
    $t = supervisor_ai_unfold_finglish($text);
    $t = supervisor_ai_norm($t);
    if ($t === '' || !preg_match('/از\s+/u', $t)) {
        return null;
    }
    if (!function_exists('jalali_month_names')) {
        require_once __DIR__ . '/jalali.php';
    }
    supervisor_ai_tz();
    list($jy, $jm) = jalali_today_parts();
    $today = date('Y-m-d');
    $months = jalali_month_names();
    $names = implode('|', array_map(function ($n) {
        return preg_quote($n, '/');
    }, $months));

    $from = '';
    $year = $jy;
    if (preg_match('/از\s*(?:اول|ابتدای|ابتداى|۱|1|یک)\s*(' . $names . ')(?:\s+(\d{4}))?/u', $t, $m)) {
        $month = supervisor_ai_jalali_month_number($m[1]);
        if (!empty($m[2])) {
            $year = (int)$m[2];
        }
        if ($month >= 1) {
            $from = jalali_to_ymd($year, $month, 1);
        }
    } elseif (preg_match('/از\s*(' . $names . ')(?:\s+(\d{4}))?\s+تا/u', $t, $m)) {
        $month = supervisor_ai_jalali_month_number($m[1]);
        if (!empty($m[2])) {
            $year = (int)$m[2];
        }
        if ($month >= 1) {
            $from = jalali_to_ymd($year, $month, 1);
        }
    } elseif (preg_match('/از\s*(?:اول|ابتدای|ابتداى)\s*(?:این\s*)?ماه/u', $t)) {
        $from = jalali_to_ymd($jy, $jm, 1);
        $year = $jy;
    }
    if ($from === '') {
        return null;
    }
    $to = $today;
    if (preg_match('/تا\s*(\d{1,2})\s*(' . $names . ')(?:\s+(\d{4}))?/u', $t, $e)) {
        $em = supervisor_ai_jalali_month_number($e[2]);
        $ey = !empty($e[3]) ? (int)$e[3] : $year;
        if ($em >= 1) {
            $g = jalali_iso_to_gregorian(sprintf('%04d-%02d-%02d', $ey, $em, (int)$e[1]));
            if ($g !== '') {
                $to = $g;
            }
        }
    } elseif (preg_match('/تا\s*دیروز/u', $t)) {
        $to = date('Y-m-d', strtotime('-1 day'));
    }
    if ($to < $from) {
        $tmp = $from;
        $from = $to;
        $to = $tmp;
    }
    return [
        'from' => $from,
        'to' => $to,
        'label' => 'از ' . supervisor_ai_pretty_date($from) . ' تا ' . supervisor_ai_pretty_date($to),
    ];
}

function supervisor_ai_asks_date_range($text) {
    return supervisor_ai_date_range_from_text($text) !== null;
}

function supervisor_ai_topic_of($text) {
    $text = supervisor_ai_unfold_finglish($text);
    if (supervisor_ai_asks_missing_rollcall($text)) {
        return 'missing_rollcall';
    }
    if (supervisor_ai_asks_missing_session_plan($text)) {
        return 'missing_session_plan';
    }
    if (supervisor_ai_asks_missing_phone($text)) {
        return 'missing_phone';
    }
    if (function_exists('supervisor_ai_feature_topic')) {
        $feat = supervisor_ai_feature_topic($text);
        if ($feat !== '') {
            return $feat;
        }
    }
    if (supervisor_ai_asks_absences($text)) {
        return 'absences';
    }
    return '';
}

function supervisor_ai_last_user_topic(array $history, $skip_text = '') {
    $skip = supervisor_ai_norm($skip_text);
    for ($i = count($history) - 1; $i >= 0; $i--) {
        if (($history[$i]['role'] ?? '') !== 'user') {
            continue;
        }
        $text = supervisor_ai_norm($history[$i]['content'] ?? '');
        if ($text === '' || ($skip !== '' && $text === $skip)) {
            continue;
        }
        $topic = supervisor_ai_topic_of($text);
        if ($topic !== '') {
            return $topic;
        }
        if (supervisor_ai_is_date_followup($text) || supervisor_ai_asks_those_followup($text) || supervisor_ai_asks_exact_when($text) || supervisor_ai_asks_date_range($text)) {
            continue;
        }
        break;
    }
    return '';
}

function supervisor_ai_last_user_date(array $history, $skip_text = '') {
    $skip = supervisor_ai_norm($skip_text);
    for ($i = count($history) - 1; $i >= 0; $i--) {
        if (($history[$i]['role'] ?? '') !== 'user') {
            continue;
        }
        $text = supervisor_ai_norm($history[$i]['content'] ?? '');
        if ($text === '' || ($skip !== '' && $text === $skip)) {
            continue;
        }
        $d = supervisor_ai_date_from_text($text);
        if ($d !== '') {
            return $d;
        }
        if (supervisor_ai_is_date_followup($text) || supervisor_ai_asks_those_followup($text) || supervisor_ai_asks_exact_when($text) || supervisor_ai_asks_date_range($text)) {
            continue;
        }
        if (supervisor_ai_topic_of($text) !== '') {
            return 'امروز';
        }
        break;
    }
    return '';
}

function supervisor_ai_last_explicit_date(array $history, $skip_text = '') {
    $skip = supervisor_ai_norm($skip_text);
    for ($i = count($history) - 1; $i >= 0; $i--) {
        if (($history[$i]['role'] ?? '') !== 'user') {
            continue;
        }
        $text = supervisor_ai_norm($history[$i]['content'] ?? '');
        if ($text === '' || ($skip !== '' && $text === $skip)) {
            continue;
        }
        $d = supervisor_ai_date_from_text($text);
        if ($d !== '') {
            return $d;
        }
        if (supervisor_ai_is_date_followup($text) || supervisor_ai_asks_those_followup($text) || supervisor_ai_asks_exact_when($text) || supervisor_ai_asks_date_range($text)) {
            continue;
        }
        break;
    }
    return '';
}

function supervisor_ai_resolve_topic_date($user_text, array $history) {
    $current_topic = supervisor_ai_topic_of($user_text);
    $date = supervisor_ai_date_from_text($user_text);
    $range = supervisor_ai_asks_date_range($user_text);
    $follow = supervisor_ai_is_date_followup($user_text)
        || supervisor_ai_asks_those_followup($user_text)
        || supervisor_ai_asks_exact_when($user_text)
        || ($range && $current_topic === '');
    $topic = $current_topic !== '' ? $current_topic : ($follow ? supervisor_ai_last_user_topic($history, $user_text) : '');
    if ($range) {
        return [$topic, $date];
    }
    if ($topic !== '' && $date === '') {
        if ($follow) {
            $date = supervisor_ai_last_explicit_date($history, $user_text);
            if ($date === '') {
                $date = supervisor_ai_last_user_date($history, $user_text);
            }
        } elseif ($current_topic !== '') {
            $date = 'امروز';
        }
        if ($date === '') {
            $date = 'امروز';
        }
    }
    return [$topic, $date];
}

function supervisor_ai_guess_class_query($text) {
    $t = supervisor_ai_unfold_finglish($text);
    if (preg_match('/\b([A-Za-z]{1,10}(?:[\s._-]*[A-Za-z]{0,8})?[\s._-]*\d{1,2}(?:\.[A-Za-z0-9.]+)?)\b/u', $t, $m)) {
        return supervisor_ai_norm($m[1]);
    }
    if (preg_match('/\b(Phonics|Sprouts|Alikhani|Mohseni|Kiani|Aliasghar)\b/iu', $t, $m)) {
        return $m[1];
    }
    return '';
}

function supervisor_ai_today_day_code() {
    $pack = supervisor_ai_date_pack(supervisor_ai_today(), 'امروز');
    if (($pack['day_kind'] ?? '') === 'جمعه') {
        return 'friday';
    }
    return (($pack['day_kind'] ?? '') === 'زوج') ? 'even' : 'odd';
}

function supervisor_ai_effective_day($raw) {
    $code = supervisor_ai_parse_day_filter($raw);
    if ($code !== '') {
        return $code;
    }
    if (supervisor_ai_asks_today($raw)) {
        return supervisor_ai_today_day_code();
    }
    return '';
}

function supervisor_ai_join_name_tokens($query) {
    $t = supervisor_ai_norm($query);
    $t = preg_replace('/قنبر\s+نژاد/u', 'قنبرنژاد', $t);
    $t = preg_replace('/مرتضی\s*پور/u', 'مرتضی‌پور', $t);
    $t = preg_replace('/علی\s+اصغر/u', 'علی اصغر', $t);
    return $t;
}

function supervisor_ai_class_day_code($class) {
    $t = strtolower(trim((string)($class['schedule_type'] ?? '')));
    if ($t === 'even' || $t === 'odd') {
        return $t;
    }
    $days = (string)($class['days'] ?? '');
    if (strpos($days, 'زوج') !== false) {
        return 'even';
    }
    if (strpos($days, 'فرد') !== false) {
        return 'odd';
    }
    return '';
}

function supervisor_ai_person_from_history(array $history) {
    for ($i = count($history) - 1; $i >= 0; $i--) {
        $role = (string)($history[$i]['role'] ?? '');
        $text = supervisor_ai_join_name_tokens($history[$i]['content'] ?? '');
        if ($role === 'user') {
            if (supervisor_ai_is_date_followup($text) || supervisor_ai_asks_those_followup($text)) {
                continue;
            }
            $p = supervisor_ai_guess_person_query($text);
            if ($p !== '') {
                return $p;
            }
        }
        if ($role === 'assistant' && preg_match('/^(.+?)\s+معلم است/u', $text, $m)) {
            $name = supervisor_ai_norm($m[1]);
            if ($name !== '' && !preg_match('/چند معلم/u', $text)) {
                return $name;
            }
        }
    }
    return '';
}

function supervisor_ai_last_user_text(array $history) {
    for ($i = count($history) - 1; $i >= 0; $i--) {
        if (($history[$i]['role'] ?? '') === 'user') {
            return supervisor_ai_norm($history[$i]['content'] ?? '');
        }
    }
    return '';
}

function supervisor_ai_parse_month_date($raw) {
    $raw = supervisor_ai_norm($raw);
    if (!function_exists('jalali_month_names')) {
        require_once __DIR__ . '/jalali.php';
    }
    $months = jalali_month_names();
    $names = implode('|', array_map(function ($n) {
        return preg_quote($n, '/');
    }, $months));
    if (!preg_match('/(\d{1,2})\s*(' . $names . ')\s*(\d{4})/u', $raw, $m)) {
        return '';
    }
    $day = (int)$m[1];
    $year = (int)$m[3];
    $month = 0;
    foreach ($months as $num => $name) {
        if ($name === $m[2]) {
            $month = (int)$num;
            break;
        }
    }
    if ($month < 1 || $year < 1200 || $year > 1599) {
        return '';
    }
    if (!function_exists('jalali_iso_to_gregorian')) {
        require_once __DIR__ . '/jalali.php';
    }
    $iso = sprintf('%04d-%02d-%02d', $year, $month, $day);
    $g = jalali_iso_to_gregorian($iso);
    return $g !== '' ? $g : '';
}

function supervisor_ai_guess_person_query($text) {
    $t = supervisor_ai_strip_honorific(supervisor_ai_join_name_tokens($text));
    if (supervisor_ai_is_date_followup($t) || supervisor_ai_asks_those_followup($t) || supervisor_ai_asks_date_range($t)) {
        return '';
    }
    $t = preg_replace('/[؟?!,.،:;]/u', ' ', $t);
    $stops = [
        'لیست', 'غایبین', 'غایب‌ها', 'غایبها', 'غایب', 'غیبت', 'دیروز', 'امروز', 'پریروز',
        'چندتا', 'چند', 'تا', 'کلاس', 'کلاسها', 'کلاس‌ها', 'کلاسی', 'کلاسهایی', 'کلاس‌هایی',
        'داره', 'دارد', 'دارن', 'دارند', 'داریم', 'دارید', 'هستیم',
        'روزهای', 'روزهاى', 'روز', 'های', 'هاى', 'هایی', 'هایى', 'ها', 'زوج', 'فرد', 'ملاس',
        'هستند', 'است', 'هست', 'را', 'رو', 'بهم', 'بده', 'بگو', 'کن', 'کدوم', 'کدام', 'کی',
        'چه', 'چی', 'که', 'معلم', 'معلمهایی', 'معلم‌هایی', 'دانش‌آموز', 'دانش آموز', 'دانشآموز', 'ترم', 'جاری', 'برگزار',
        'آقا', 'آقای', 'اقای', 'خانم', 'قبل', 'قبلی', 'قبلشان', 'قبلشون', 'منظورم', 'نمرات', 'بود',
        'لینک', 'آنلاین', 'انلاین', 'آفلاین', 'افلاین', 'الوکام', 'خصوصی',
        'استادی', 'فرستاده', 'ارسال', 'شدن', 'دقیق', 'تاریخ', 'ساعت', 'زمان',
        'اول', 'الان', 'حالا', 'ابتدا', 'ابتدای', 'ماه', 'مهر', 'فروردین', 'اردیبهشت',
        'خرداد', 'تیر', 'مرداد', 'شهریور', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند',
        'میشن', 'می‌شن', 'میشه', 'می‌شه', 'برای', 'از', 'با', 'در', 'به', 'این', 'اون',
        'اونایی', 'اون‌هایی', 'اونا', 'اونها', 'کسایی', 'کسانی', 'همان', 'همونا',
        'یک', 'یه', 'لطفا', 'خواهش', 'چطور', 'چطوری', 'خب', 'پس', 'یعنی', 'باشه', 'اوکی',
        'سشن', 'پلن', 'رول', 'کال', 'رولکال', 'رول‌کال', 'طرح', 'جلسه', 'طرح‌جلسه',
        'ننوشت', 'ننوشته', 'ننوشتن', 'ننشوتن', 'ننشوت', 'نزده', 'نزدن', 'نزن', 'نزنه',
        'زده', 'زدن', 'نوشته', 'نوشتن', 'ثبت', 'نشده', 'نکرده', 'نکردن', 'بدون',
        'تکلیف', 'ناقص', 'نسپرده', 'کارنامه', 'نمره', 'کوییز', 'کویز',
        'ارور', 'گراویتی', 'نقاط', 'ضعف', 'قوت', 'آبزرو', 'آبزرور', 'مشاهده', 'فرم',
        'ارزیابی', 'مصاحبه', 'نظرسنجی', 'پُل', 'ای‌ای‌اس',
        'مایند', 'لینگو', 'استادی', 'لایو', 'برگزاری', 'صفحه', 'کجا', 'کجاست',
        'اساتید', 'کاربران', 'کاربر', 'تعداد',
        'پرونده', 'کلاس‌بندی', 'کلاس بندی', 'سلام', 'درود', 'تلفن', 'شماره',
        'تماس', 'اسامی', 'اسم', 'بچه', 'بچه‌ها', 'بچهها', 'دانش‌آموزان', 'دانش آموزان',
        'information', 'student', 'students',
        'تیک', 'برنامه', 'نقشه', 'چیکار', 'میتونی', 'هیچکس', 'هیچ‌کس', 'هیچ',
        'what', 'which', 'who', 'how', 'many', 'much', 'class', 'classes', 'today', 'yesterday',
        'tomorrow', 'have', 'has', 'do', 'does', 'did', 'we', 'our', 'the', 'a', 'an', 'is',
        'are', 'was', 'were', 'there', 'list', 'show', 'give', 'tell', 'me', 'please', 'now',
        'current', 'term', 'even', 'odd', 'teacher', 'student', 'name', 'session', 'plan',
        'plans', 'roll', 'call', 'rollcall', 'attendance', 'missing', 'without',
        'homework', 'assignment', 'quiz', 'report', 'card', 'gravity', 'observation',
        'interview', 'poll', 'eas', 'mind', 'lingo', 'study', 'live', 'where', 'page',
    ];
    foreach ($stops as $s) {
        $t = preg_replace('/(?<!\p{L})' . preg_quote($s, '/') . '(?!\p{L})/iu', ' ', $t);
    }
    $t = supervisor_ai_norm($t);
    if ($t === '') {
        return '';
    }
    if (preg_match('/^\d{3,}$/', $t)) {
        return $t;
    }
    $tokens = preg_split('/\s+/u', $t);
    $tokens = array_values(array_filter($tokens, function ($tok) {
        return $tok !== '';
    }));
    if (count($tokens) < 1 || count($tokens) > 4) {
        return '';
    }
    $nameish = 0;
    foreach ($tokens as $tok) {
        if (supervisor_ai_name_aliases($tok) || preg_match('/^\p{Arabic}{2,}$/u', $tok)) {
            $nameish++;
            continue;
        }
        if (preg_match('/^[A-Za-z][A-Za-z\'-]{2,}$/', $tok) || preg_match('/^[\x{0600}-\x{06FF}\x{200C}]{2,}$/u', $tok)) {
            $nameish++;
            continue;
        }
        return '';
    }
    if ($nameish < 1) {
        return '';
    }
    return implode(' ', $tokens);
}

function supervisor_ai_rewrite_tool_args($name, array $args, $user_text) {
    $user_text = supervisor_ai_norm($user_text);
    if ($name === 'list_today_absences' || $name === 'get_class_session'
        || $name === 'list_missing_rollcall' || $name === 'list_missing_session_plans'
        || $name === 'list_missing_homework' || $name === 'list_homework_gaps') {
        $date = supervisor_ai_norm($args['date'] ?? '');
        if (preg_match('/دیروز/u', $user_text) && !preg_match('/پریروز|امروز/u', $user_text)) {
            if ($date === '' || !preg_match('/^دیروز$/u', $date)) {
                $args['date'] = 'دیروز';
            }
        } elseif (preg_match('/پریروز/u', $user_text) && !preg_match('/امروز/u', $user_text)) {
            if ($date === '' || !preg_match('/^پریروز$/u', $date)) {
                $args['date'] = 'پریروز';
            }
        } elseif (preg_match('/امروز/u', $user_text) && $date === '') {
            $args['date'] = 'امروز';
        }
    }
    if ($name === 'list_classes') {
        $day = supervisor_ai_effective_day($user_text);
        $arg_day = supervisor_ai_parse_day_filter($args['day'] ?? '');
        if ($arg_day === '' && $day !== '' && $day !== 'friday') {
            $args['day'] = $day === 'even' ? 'زوج' : 'فرد';
        }
        $q = supervisor_ai_norm($args['query'] ?? '');
        if ((supervisor_ai_parse_day_filter($q) !== '' || supervisor_ai_asks_today($q) || supervisor_ai_asks_classes($q))
            && supervisor_ai_guess_person_query($q) === '') {
            $args['query'] = '';
        }
    }
    return $args;
}

function supervisor_ai_prefetch_facts(PDO $pdo, $user_text) {
    $user_text = supervisor_ai_norm($user_text);
    if ($user_text === '') {
        return [];
    }
    $facts = [];
    $topic = supervisor_ai_topic_of($user_text);
    $date = supervisor_ai_date_from_text($user_text);
    if ($date === '') {
        $date = 'امروز';
    }
    if ($topic === 'missing_rollcall') {
        $facts['missing_rollcall'] = supervisor_ai_missing_rollcall($pdo, $date);
    } elseif ($topic === 'missing_session_plan') {
        $facts['missing_session_plans'] = supervisor_ai_missing_session_plans($pdo, $date);
    } elseif ($topic === 'absences' || supervisor_ai_asks_absences($user_text)) {
        $facts['absences'] = supervisor_ai_today_absences($pdo, $date);
    }
    $day = supervisor_ai_effective_day($user_text);
    if ($day !== '' && supervisor_ai_asks_classes($user_text)) {
        if ($day === 'friday') {
            $facts['classes'] = [
                'ok' => true,
                'count' => 0,
                'filter' => 'جمعه',
                'classes' => [],
                'summary_fa' => 'امروز جمعه است و کلاسی برگزار نمی‌شود.',
            ];
        } else {
            $facts['classes'] = supervisor_ai_list_classes($pdo, '', $day);
        }
    } elseif (supervisor_ai_asks_classes($user_text) && supervisor_ai_guess_person_query($user_text) === '') {
        $facts['classes'] = supervisor_ai_list_classes($pdo, '', '');
    }
    $person = supervisor_ai_guess_person_query($user_text);
    if ($person !== '') {
        $facts['person'] = supervisor_ai_lookup_person($pdo, $person);
    }
    return $facts;
}

function supervisor_ai_format_absences(array $data) {
    if (!empty($data['error'])) {
        return (string)$data['error'];
    }
    $when = $data['date'] ?? [];
    $head = supervisor_ai_norm(trim(
        (string)($when['relative'] ?? '') . ' — ' . (string)($when['jalali'] ?? '') . ' — ' . (string)($when['weekday'] ?? '')
        . (!empty($data['day_kind']) ? ' — روز ' . $data['day_kind'] : '')
    ));
    $sched = (int)($data['scheduled_count'] ?? 0);
    $n_roll = (int)($data['rollcall_class_count'] ?? 0);
    $with = $data['classes_with_absences'] ?? [];
    $missing = $data['missing_rollcall'] ?? [];
    $not_started = $data['not_started'] ?? [];
    $lines = [$head];
    if ($sched > 0) {
        $lines[0] .= ' — ' . $sched . ' کلاس برنامه‌ریزی‌شده';
    }
    if ($with) {
        $lines[] = 'غایب در ' . count($with) . ' کلاس:';
        foreach ($with as $cls) {
            $names = [];
            foreach (($cls['absentees'] ?? []) as $p) {
                $names[] = trim((string)($p['full_name'] ?? '') . ' ' . (string)($p['file_number'] ?? ''));
            }
            $lines[] = '• ' . (string)($cls['title'] ?? '') . ' (' . (int)($cls['absent_count'] ?? 0) . ' غایب)'
                . ($names ? ': ' . implode('، ', $names) : '');
        }
    } elseif ($n_roll === 0) {
        $lines[] = 'هیچ کلاسی غایب ثبت نکرده؛ چون هنوز هیچ رول‌کالی برای این تاریخ ذخیره نشده است. این با «همه حاضرند» فرق دارد.';
    } else {
        $lines[] = 'هیچ کلاسی غایب ثبت نکرده است (از ' . $n_roll . ' کلاس که رول‌کال زده‌اند، غایبی در دیتابیس نیست).';
    }
    if ($missing) {
        $lines[] = 'رول‌کال نزدهٔ شروع‌شده (' . count($missing) . ' کلاس) — غایب‌شان هنوز معلوم نیست: '
            . supervisor_ai_compact_titles($missing);
    }
    if ($not_started) {
        $lines[] = count($not_started) . ' کلاس هنوز شروع نشده و غایب‌شان بعداً معلوم می‌شود.';
    }
    return implode("\n", $lines);
}

function supervisor_ai_format_class_line($c, $i) {
    return $i . '. ' . (string)($c['title'] ?? '')
        . ' — ' . (string)($c['days'] ?? '')
        . (trim((string)($c['schedule'] ?? '')) !== '' ? ' (ساعت ' . $c['schedule'] . ')' : '')
        . (trim((string)($c['term'] ?? '')) !== '' ? ' — ' . $c['term'] : '');
}

function supervisor_ai_format_person(array $data, $day_code = '') {
    if (!empty($data['error'])) {
        return (string)$data['error'];
    }
    $people = $data['people'] ?? [];
    if (!$people) {
        return 'نه معلم و نه دانش‌آموزی با این نام پیدا نشد.';
    }
    $teachers = [];
    $students = [];
    foreach ($people as $p) {
        if (($p['role'] ?? '') === 'teacher') {
            $teachers[] = $p;
        } else {
            $students[] = $p;
        }
    }
    $want = ($day_code === 'even' || $day_code === 'odd') ? $day_code : '';
    $filter_classes = function (array $p) use ($want) {
        $current = [];
        $older = [];
        foreach (($p['classes'] ?? []) as $c) {
            if ($want !== '' && supervisor_ai_class_day_code($c) !== $want) {
                continue;
            }
            if (!empty($c['current_term'])) {
                $current[] = $c;
            } else {
                $older[] = $c;
            }
        }
        return [$current, $older];
    };
    if (count($teachers) > 1) {
        $lines = ['چند معلم با این نام پیدا شد. اسم کوچک را بگو تا کلاس‌ها را جداگانه بدهم:'];
        foreach ($teachers as $p) {
            list($current, $older) = $filter_classes($p);
            $name = (string)($p['full_name'] ?? '');
            $titles = [];
            foreach ($current as $c) {
                $titles[] = (string)($c['title'] ?? '');
            }
            $line = '• ' . $name . ' — ترم جاری ' . count($current) . ' کلاس';
            if ($titles) {
                $line .= ': ' . implode('، ', $titles);
            }
            if ($older) {
                $line .= '؛ ترم‌های دیگر ' . count($older) . ' کلاس';
            }
            $lines[] = $line;
        }
        return implode("\n", $lines);
    }
    $chunks = [];
    foreach ($teachers as $p) {
        list($current, $older) = $filter_classes($p);
        $name = (string)($p['full_name'] ?? '');
        $lines = [];
        if ($want !== '') {
            $label = $want === 'even' ? 'زوج (شنبه/دوشنبه/چهارشنبه)' : 'فرد (یکشنبه/سه‌شنبه/پنجشنبه)';
            $n = count($current);
            $lines[] = $name . ' در روزهای ' . $label . ' در ترم جاری ' . $n . ' کلاس دارد.';
            if (!$current) {
                $lines[] = 'در ترم جاری در این روزها کلاسی ندارد.';
            } else {
                $i = 1;
                foreach ($current as $c) {
                    $lines[] = supervisor_ai_format_class_line($c, $i);
                    $i++;
                }
            }
            if ($older) {
                $lines[] = 'در ترم‌های دیگر همین روزها:';
                $i = 1;
                foreach ($older as $c) {
                    $lines[] = supervisor_ai_format_class_line($c, $i);
                    $i++;
                }
            }
        } else {
            $n = count($current);
            $m = count($older);
            $lines[] = $name . ' معلم است.';
            $lines[] = 'ترم جاری: ' . $n . ' کلاس';
            $i = 1;
            foreach ($current as $c) {
                $lines[] = supervisor_ai_format_class_line($c, $i);
                $i++;
            }
            if ($m > 0) {
                $lines[] = 'ترم‌های دیگر: ' . $m . ' کلاس';
                $i = 1;
                foreach ($older as $c) {
                    $lines[] = supervisor_ai_format_class_line($c, $i);
                    $i++;
                }
            }
        }
        $chunks[] = implode("\n", $lines);
    }
    if (!$teachers) {
        if (count($students) > 1) {
            $lines = ['چند دانش‌آموز با این نام پیدا شد. شماره پرونده را بگو:'];
            foreach ($students as $p) {
                $lines[] = '• ' . (string)($p['full_name'] ?? '') . ' — پرونده ' . (string)($p['file_number'] ?? '');
            }
            return implode("\n", $lines);
        }
        foreach ($students as $p) {
            $lines = [
                (string)($p['full_name'] ?? '') . ' دانش‌آموز است (پرونده ' . (string)($p['file_number'] ?? '') . ').',
            ];
            $classes = $p['classes'] ?? [];
            if ($classes) {
                $lines[] = 'کلاس‌ها:';
                $i = 1;
                foreach ($classes as $c) {
                    $line = $i . '. ' . (string)($c['title'] ?? '')
                        . (trim((string)($c['teacher'] ?? '')) !== '' ? ' — معلم: ' . $c['teacher'] : '')
                        . (isset($c['absent_count']) ? (' — غایب ' . (int)$c['absent_count']) : '');
                    $lines[] = $line;
                    $i++;
                }
            }
            $chunks[] = implode("\n", $lines);
        }
    }
    if (!$chunks) {
        return 'نه معلم و نه دانش‌آموزی با این نام پیدا نشد.';
    }
    return implode("\n\n", $chunks);
}

function supervisor_ai_format_classes(array $data, $heading = '') {
    if (!empty($data['error'])) {
        return (string)$data['error'];
    }
    if (!empty($data['summary_fa']) && (int)($data['count'] ?? 0) === 0 && ($data['filter'] ?? '') === 'جمعه') {
        return (string)$data['summary_fa'];
    }
    $filter = (string)($data['filter'] ?? 'همه');
    $count = (int)($data['count'] ?? 0);
    $title = $heading !== '' ? $heading : ('تعداد کلاس‌ها (' . $filter . '): ' . $count);
    $lines = [$title];
    $i = 1;
    foreach (($data['classes'] ?? []) as $c) {
        $lines[] = $i . '. ' . (string)($c['title'] ?? '')
            . ' — معلم: ' . (string)($c['teacher'] ?? '')
            . (trim((string)($c['schedule'] ?? '')) !== '' ? ' (ساعت ' . $c['schedule'] . ')' : '')
            . ' — ' . (string)($c['days'] ?? '');
        $i++;
    }
    return implode("\n", $lines);
}

function supervisor_ai_format_today_classes(PDO $pdo) {
    $pack = supervisor_ai_date_pack(supervisor_ai_today(), 'امروز');
    $kind = (string)($pack['day_kind'] ?? '');
    $head = 'کلاس‌های امروز — ' . (string)($pack['jalali'] ?? '') . ' — ' . (string)($pack['weekday'] ?? '');
    if ($kind === 'جمعه') {
        return $head . "\nامروز جمعه است و کلاسی برگزار نمی‌شود.";
    }
    $day = $kind === 'زوج' ? 'even' : 'odd';
    $data = supervisor_ai_list_classes($pdo, '', $day);
    $head .= ' — روز ' . $kind . ' — ' . (int)($data['count'] ?? 0) . ' کلاس';
    return supervisor_ai_format_classes($data, $head);
}

function supervisor_ai_format_class_overview(array $data) {
    if (!empty($data['error'])) {
        return (string)$data['error'];
    }
    if (!empty($data['ambiguous'])) {
        $note = trim((string)($data['note'] ?? ''));
        $lines = [$note !== '' ? $note : 'چند کلاس مشابه است:'];
        foreach (($data['matches'] ?? []) as $i => $c) {
            $line = ($i + 1) . '. ' . (string)($c['title'] ?? '') . ' — ' . (string)($c['teacher'] ?? '');
            if (trim((string)($c['days'] ?? '')) !== '') {
                $line .= ' — ' . $c['days'];
            }
            if (trim((string)($c['schedule'] ?? '')) !== '') {
                $line .= ' (ساعت ' . $c['schedule'] . ')';
            }
            $lines[] = $line;
        }
        return implode("\n", $lines);
    }
    if (!empty($data['people'])) {
        return supervisor_ai_format_person($data);
    }
    $lines = [
        (string)($data['title'] ?? '') . ' — معلم: ' . (string)($data['teacher'] ?? '')
            . ' — ' . (string)($data['days'] ?? '')
            . (trim((string)($data['schedule'] ?? '')) !== '' ? ' (ساعت ' . $data['schedule'] . ')' : ''),
        'تعداد دانش‌آموز: ' . (int)($data['student_count'] ?? 0),
    ];
    $i = 1;
    foreach (($data['students'] ?? []) as $s) {
        $lines[] = $i . '. ' . (string)($s['full_name'] ?? '') . ' — پرونده ' . (string)($s['file_number'] ?? '');
        $i++;
    }
    return implode("\n", $lines);
}

function supervisor_ai_try_direct(PDO $pdo, $user_text, array $history = []) {
    $user_text = supervisor_ai_unfold_finglish(supervisor_ai_join_name_tokens($user_text));
    if ($user_text === '') {
        return null;
    }
    if (function_exists('supervisor_ai_try_scenarios')) {
        return supervisor_ai_try_scenarios($pdo, $user_text, $history);
    }
    return null;
}

function supervisor_ai_name_aliases($token) {
    $t = supervisor_ai_norm($token);
    $map = [
        'مارال' => ['Maral'],
        'قنبرنژاد' => ['Qanbarnejad', 'Ghanbarnejad', 'Qanbarnezhad', 'Ghanbarnezhad', 'Qanbarnejaad'],
        'نژاد' => ['Nejad', 'Nezhad', 'Qanbarnejad', 'Ghanbarnejad'],
        'قنبر' => ['Qanbar', 'Ghanbar'],
        'متین' => ['Matin'],
        'مرتضی‌پور' => ['Mortezapour', 'Mortezapor', 'Mortezapoor'],
        'مرتضاپور' => ['Mortezapour', 'Mortezapor', 'Mortezapoor'],
        'مرتضیپور' => ['Mortezapour', 'Mortezapor', 'Mortezapoor'],
        'مرتضی' => ['Morteza'],
        'اخوان' => ['Akhavan'],
        'نسترن' => ['Nastaran'],
        'ساغر' => ['Saghar'],
        'مهتا' => ['Mahta'],
        'محمد' => ['Mohammad', 'Muhammad'],
        'یگانه' => ['Yegane', 'Yeganeh'],
        'زهرا' => ['Zahra'],
        'نازنین' => ['Nazanin'],
        'آیلار' => ['Aylar'],
        'ریحانه' => ['Reyhane', 'Reyhaneh'],
        'شیما' => ['Shima'],
        'کوثر' => ['Kosar'],
        'زینب' => ['Zeinab', 'Zainab'],
        'سمیرا' => ['Samira'],
        'سوفیا' => ['Sofia', 'Sophia'],
        'اماد' => ['Emad'],
        'نگین' => ['Negin'],
        'محسنی' => ['Mohseni'],
        'کیانی' => ['Kiani'],
        'اباذری' => ['Abazari'],
        'حسنلو' => ['Hassanlou', 'Hasanlou', 'Hassanloo', 'Hasanloo'],
        'جلالیان' => ['Jalalian'],
        'نعمتی' => ['Neamati', 'Nemati'],
        'موسوی' => ['Moosavi', 'Mousavi'],
        'سلمانی' => ['Salmani'],
        'علیفلاح' => ['Alifallah', 'Alifalah'],
        'شگرد' => ['Shegerd', 'Shagerd'],
        'نجاری' => ['Najari'],
        'اسدی' => ['Asadi'],
        'سرسنگی' => ['Sarsangi'],
        'پورفلاح' => ['Pourfallah', 'Pourfalah'],
        'کوتابادی' => ['Kootabadi', 'Kootabadi'],
        'علی اصغر' => ['Aliasghar'],
        'علیشاه' => ['Alikhani'],
    ];
    return $map[$t] ?? [];
}

function supervisor_ai_transliterate($token) {
    $map = [
        'آ' => 'a', 'ا' => 'a', 'أ' => 'a', 'إ' => 'e', 'ب' => 'b', 'پ' => 'p', 'ت' => 't', 'ث' => 's',
        'ج' => 'j', 'چ' => 'ch', 'ح' => 'h', 'خ' => 'kh', 'د' => 'd', 'ذ' => 'z', 'ر' => 'r', 'ز' => 'z',
        'ژ' => 'zh', 'س' => 's', 'ش' => 'sh', 'ص' => 's', 'ض' => 'z', 'ط' => 't', 'ظ' => 'z', 'ع' => 'a',
        'غ' => 'gh', 'ف' => 'f', 'ق' => 'q', 'ک' => 'k', 'ك' => 'k', 'گ' => 'g', 'ل' => 'l', 'م' => 'm',
        'ن' => 'n', 'و' => 'v', 'ه' => 'h', 'ی' => 'i', 'ي' => 'i', 'ئ' => 'i', 'ء' => '', '‌' => '',
    ];
    $out = '';
    $chars = preg_split('//u', $token, -1, PREG_SPLIT_NO_EMPTY);
    foreach ($chars as $ch) {
        if (isset($map[$ch])) {
            $out .= $map[$ch];
        } elseif (preg_match('/^[A-Za-z0-9]$/', $ch)) {
            $out .= $ch;
        }
    }
    return $out;
}

function supervisor_ai_latin_tweaks($word) {
    $word = trim((string)$word);
    if ($word === '' || !preg_match('/[A-Za-z]/', $word)) {
        return [];
    }
    $set = [$word];
    $set[] = str_ireplace('ou', 'oo', $word);
    $set[] = str_ireplace('oo', 'ou', $word);
    $set[] = preg_replace('/lou$/i', 'loo', $word);
    $set[] = preg_replace('/loo$/i', 'lou', $word);
    $set[] = preg_replace('/ss/i', 's', $word);
    $set[] = preg_replace('/nejad$/i', 'nezhad', $word);
    $set[] = preg_replace('/nezhad$/i', 'nejad', $word);
    $set[] = preg_replace('/eh$/i', 'e', $word);
    $set[] = preg_replace('/pour$/i', 'poor', $word);
    $out = [];
    foreach ($set as $v) {
        $v = trim((string)$v);
        if ($v !== '') {
            $out[$v] = true;
        }
    }
    return array_keys($out);
}

function supervisor_ai_like_groups($query) {
    $tokens = preg_split('/\s+/u', supervisor_ai_norm($query));
    $groups = [];
    foreach ($tokens as $tok) {
        if ($tok === '') {
            continue;
        }
        $vars = [$tok];
        $latin = supervisor_ai_transliterate($tok);
        if ($latin !== '' && strcasecmp($latin, $tok) !== 0) {
            $vars[] = $latin;
        }
        foreach (supervisor_ai_name_aliases($tok) as $alias) {
            $vars[] = $alias;
            foreach (supervisor_ai_latin_tweaks($alias) as $tw) {
                $vars[] = $tw;
            }
        }
        foreach (supervisor_ai_latin_tweaks($tok) as $tw) {
            $vars[] = $tw;
        }
        foreach (supervisor_ai_latin_tweaks($latin) as $tw) {
            $vars[] = $tw;
        }
        $groups[] = array_values(array_unique($vars));
    }
    return $groups;
}

function supervisor_ai_fold_latin($s) {
    $s = strtolower(supervisor_ai_norm($s));
    return preg_replace('/[^a-z]/', '', $s);
}

function supervisor_ai_names_close($a, $b) {
    $a = supervisor_ai_fold_latin($a);
    $b = supervisor_ai_fold_latin($b);
    if ($a === '' || $b === '') {
        return false;
    }
    $la = strlen($a);
    $lb = strlen($b);
    if ($la < 4 || $lb < 4) {
        return false;
    }
    if ($a === $b) {
        return true;
    }
    $min = min($la, $lb);
    $max = max($la, $lb);
    if ($min >= 6 && ($max - $min) <= 2 && (strpos($a, $b) !== false || strpos($b, $a) !== false)) {
        return true;
    }
    if ($min >= 5 && ($max - $min) <= 2 && levenshtein($a, $b) <= 2) {
        return true;
    }
    return false;
}

function supervisor_ai_query_needles($query) {
    $needles = [];
    foreach (supervisor_ai_like_groups($query) as $group) {
        foreach ($group as $v) {
            $needles[] = $v;
        }
    }
    return array_values(array_unique($needles));
}

function supervisor_ai_all_staff_candidates(PDO $pdo) {
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    $st = $pdo->query("
        SELECT DISTINCT u.id, u.username, u.full_name, u.role
        FROM users u
        LEFT JOIN classes c ON c.teacher_id = u.id AND c.is_deleted = 0
        WHERE u.is_deleted = 0
          AND (u.role IN ('teacher', 'supervisor') OR c.id IS NOT NULL)
        ORDER BY u.full_name
        LIMIT 200
    ");
    $cache = $st ? $st->fetchAll(PDO::FETCH_ASSOC) : [];
    return $cache;
}

function supervisor_ai_row_matches_name_groups(array $row, array $groups) {
    if (!$groups) {
        return false;
    }
    $full = (string)($row['full_name'] ?? '');
    $parts = preg_split('/\s+/u', supervisor_ai_norm($full));
    $first = $parts ? (string)$parts[0] : '';
    $last = $parts ? (string)$parts[count($parts) - 1] : '';
    foreach ($groups as $group) {
        $matched = false;
        foreach ($group as $n) {
            if ($n === '') {
                continue;
            }
            if (function_exists('mb_stripos')) {
                if (mb_stripos($full, $n) !== false) {
                    $matched = true;
                    break;
                }
            } elseif (stripos($full, $n) !== false) {
                $matched = true;
                break;
            }
            if (supervisor_ai_names_close($last, $n) || supervisor_ai_names_close($first, $n) || supervisor_ai_names_close($full, $n)) {
                $matched = true;
                break;
            }
        }
        if (!$matched) {
            return false;
        }
    }
    return true;
}

function supervisor_ai_fuzzy_staff(PDO $pdo, $query) {
    $query = supervisor_ai_norm($query);
    if ($query === '') {
        return [];
    }
    $groups = supervisor_ai_like_groups($query);
    $hits = [];
    foreach (supervisor_ai_all_staff_candidates($pdo) as $row) {
        if (supervisor_ai_row_matches_name_groups($row, $groups)) {
            $hits[(int)$row['id']] = $row;
        }
    }
    return array_values($hits);
}

function supervisor_ai_is_present_row(array $row) {
    if (function_exists('rollcall_normalize_status')) {
        return rollcall_normalize_status($row['status'] ?? '', $row['is_present'] ?? null) === 'present';
    }
    $status = strtolower(trim((string)($row['status'] ?? '')));
    return ((int)($row['is_present'] ?? 0) === 1) || $status === 'present';
}

function supervisor_ai_search_students(PDO $pdo, $query) {
    $query = supervisor_ai_norm($query);
    if ($query === '') {
        return ['ok' => false, 'error' => 'عبارت جستجو خالی است.'];
    }
    $like = '%' . $query . '%';
    $st = $pdo->prepare("
        SELECT u.id, u.username AS file_number, u.full_name
        FROM users u
        WHERE u.role = 'student' AND u.is_deleted = 0
          AND (u.username = ? OR u.username LIKE ? OR u.full_name LIKE ?)
        ORDER BY (u.username = ?) DESC, CAST(u.username AS UNSIGNED), u.full_name
        LIMIT 8
    ");
    $st->execute([$query, $like, $like, $query]);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);
    $matches = [];
    foreach ($rows as $row) {
        $matches[] = [
            'file_number' => (string)$row['file_number'],
            'full_name' => (string)$row['full_name'],
        ];
    }
    return [
        'ok' => true,
        'count' => count($matches),
        'matches' => $matches,
        'note' => count($matches) === 0
            ? 'دانش‌آموزی با این مشخصات پیدا نشد. اگر معلم است از lookup_person استفاده کن.'
            : (count($matches) === 1 ? 'یک نفر پیدا شد.' : 'چند نفر پیدا شد؛ اگر لازم است با شماره پرونده دقیق‌تر بپرس.'),
    ];
}

function supervisor_ai_search_users_by_role(PDO $pdo, $query, $role) {
    $query = supervisor_ai_norm($query);
    if ($query === '' || !in_array($role, ['student', 'teacher', 'supervisor'], true)) {
        return [];
    }
    $params = [$role, $query, '%' . $query . '%', '%' . $query . '%'];
    $sql = "
        SELECT id, username, full_name, role
        FROM users
        WHERE role = ? AND is_deleted = 0
          AND (
            username = ? OR username LIKE ? OR full_name LIKE ?
    ";
    $groups = supervisor_ai_like_groups($query);
    if (count($groups) >= 2) {
        $sql .= ' OR (1=1';
        foreach ($groups as $vars) {
            $ors = [];
            foreach ($vars as $v) {
                $ors[] = 'full_name LIKE ?';
                $params[] = '%' . $v . '%';
            }
            if ($ors) {
                $sql .= ' AND (' . implode(' OR ', $ors) . ')';
            }
        }
        $sql .= ')';
    } elseif (count($groups) === 1) {
        $ors = [];
        foreach ($groups[0] as $v) {
            $ors[] = 'full_name LIKE ?';
            $params[] = '%' . $v . '%';
        }
        if ($ors) {
            $sql .= ' OR ' . implode(' OR ', $ors);
        }
    }
    $sql .= ') ORDER BY (username = ?) DESC, full_name LIMIT 8';
    $params[] = $query;
    $st = $pdo->prepare($sql);
    $st->execute($params);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

function supervisor_ai_search_instructors(PDO $pdo, $query) {
    $query = supervisor_ai_norm($query);
    if ($query === '') {
        return [];
    }
    $params = [$query, '%' . $query . '%', '%' . $query . '%'];
    $sql = "
        SELECT DISTINCT u.id, u.username, u.full_name, u.role
        FROM users u
        JOIN classes c ON c.teacher_id = u.id AND c.is_deleted = 0
        WHERE u.is_deleted = 0
          AND (u.username = ? OR u.username LIKE ? OR u.full_name LIKE ?
    ";
    $groups = supervisor_ai_like_groups($query);
    if (count($groups) >= 2) {
        $sql .= ' OR (1=1';
        foreach ($groups as $vars) {
            $ors = [];
            foreach ($vars as $v) {
                $ors[] = 'u.full_name LIKE ?';
                $params[] = '%' . $v . '%';
            }
            if ($ors) {
                $sql .= ' AND (' . implode(' OR ', $ors) . ')';
            }
        }
        $sql .= ')';
    } elseif (count($groups) === 1) {
        foreach ($groups[0] as $v) {
            $sql .= ' OR u.full_name LIKE ?';
            $params[] = '%' . $v . '%';
        }
    }
    $sql .= ') ORDER BY u.full_name LIMIT 8';
    $st = $pdo->prepare($sql);
    $st->execute($params);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

function supervisor_ai_teacher_classes(PDO $pdo, $teacher_id) {
    if (!function_exists('term_sql_teaching')) {
        require_once __DIR__ . '/terms.php';
    }
    $st = $pdo->prepare("
        SELECT c.title, c.class_type, c.schedule, c.schedule_type, t.title AS term_title,
               CASE WHEN " . term_sql_teaching('t') . " THEN 1 ELSE 0 END AS is_current
        FROM classes c
        LEFT JOIN terms t ON c.term_id = t.id
        WHERE c.teacher_id = ?
          AND c.is_deleted = 0
          AND IFNULL(c.is_archived, 0) = 0
          AND (t.id IS NULL OR t.is_deleted = 0 OR t.is_deleted IS NULL)
        ORDER BY is_current DESC, c.title
    ");
    $st->execute([(int)$teacher_id]);
    $out = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $out[] = [
            'title' => (string)$row['title'],
            'type' => (string)($row['class_type'] ?? ''),
            'schedule' => (string)($row['schedule'] ?? ''),
            'schedule_type' => strtolower(trim((string)($row['schedule_type'] ?? ''))),
            'days' => supervisor_ai_schedule_label($row['schedule_type'] ?? ''),
            'term' => (string)($row['term_title'] ?? ''),
            'current_term' => ((int)$row['is_current'] === 1),
        ];
    }
    return $out;
}

function supervisor_ai_staff_rows(PDO $pdo, $query) {
    $by_id = [];
    foreach (supervisor_ai_search_instructors($pdo, $query) as $row) {
        $by_id[(int)$row['id']] = $row;
    }
    foreach (supervisor_ai_search_users_by_role($pdo, $query, 'teacher') as $row) {
        $by_id[(int)$row['id']] = $row;
    }
    foreach (supervisor_ai_search_users_by_role($pdo, $query, 'supervisor') as $row) {
        $classes = supervisor_ai_teacher_classes($pdo, (int)$row['id']);
        if ($classes) {
            $by_id[(int)$row['id']] = $row;
        }
    }
    foreach (supervisor_ai_fuzzy_staff($pdo, $query) as $row) {
        $by_id[(int)$row['id']] = $row;
    }
    return array_values($by_id);
}

function supervisor_ai_merge_staff_groups(array $grouped) {
    $items = array_values($grouped);
    $used = [];
    $out = [];
    $n = count($items);
    for ($i = 0; $i < $n; $i++) {
        if (!empty($used[$i])) {
            continue;
        }
        $acc = $items[$i];
        for ($j = $i + 1; $j < $n; $j++) {
            if (!empty($used[$j])) {
                continue;
            }
            if (!supervisor_ai_names_close($acc['full_name'], $items[$j]['full_name'])) {
                continue;
            }
            foreach ($items[$j]['usernames'] as $u => $flag) {
                $acc['usernames'][$u] = true;
            }
            foreach ($items[$j]['classes'] as $ck => $c) {
                $acc['classes'][$ck] = $c;
            }
            if (strlen((string)$items[$j]['full_name']) > strlen((string)$acc['full_name'])) {
                $acc['full_name'] = $items[$j]['full_name'];
            }
            $used[$j] = true;
        }
        $out[] = $acc;
    }
    return $out;
}

function supervisor_ai_lookup_person(PDO $pdo, $query) {
    $gender = supervisor_ai_honorific_gender($query);
    $query = supervisor_ai_strip_honorific(supervisor_ai_join_name_tokens($query));
    if ($query === '') {
        return ['ok' => false, 'error' => 'عبارت جستجو خالی است.'];
    }
    $students = supervisor_ai_search_users_by_role($pdo, $query, 'student');
    $staff = supervisor_ai_staff_rows($pdo, $query);
    $grouped = [];
    foreach ($staff as $row) {
        $name = supervisor_ai_norm($row['full_name'] ?? '');
        $key = function_exists('mb_strtolower') ? mb_strtolower($name, 'UTF-8') : strtolower($name);
        if ($key === '') {
            $key = 'id:' . (int)$row['id'];
        }
        if (!isset($grouped[$key])) {
            $grouped[$key] = [
                'full_name' => $name,
                'usernames' => [],
                'classes' => [],
            ];
        }
        $grouped[$key]['usernames'][(string)$row['username']] = true;
        foreach (supervisor_ai_teacher_classes($pdo, (int)$row['id']) as $c) {
            $ck = $c['title'] . '|' . $c['schedule'] . '|' . $c['days'];
            $grouped[$key]['classes'][$ck] = $c;
        }
    }
    $grouped = supervisor_ai_merge_staff_groups($grouped);
    $people = [];
    foreach ($grouped as $bundle) {
        $classes = array_values($bundle['classes']);
        if (!$classes && count($grouped) > 1) {
            continue;
        }
        $current = 0;
        foreach ($classes as $c) {
            if (!empty($c['current_term'])) {
                $current++;
            }
        }
        $people[] = [
            'role' => 'teacher',
            'role_fa' => 'معلم',
            'username' => implode(', ', array_keys($bundle['usernames'])),
            'full_name' => $bundle['full_name'],
            'current_class_count' => $current,
            'class_count' => count($classes),
            'classes' => $classes,
            'summary_fa' => $bundle['full_name'] . ' معلم است و در ترم جاری ' . $current . ' کلاس دارد.',
        ];
    }
    usort($people, function ($a, $b) {
        $ac = (int)($a['current_class_count'] ?? 0);
        $bc = (int)($b['current_class_count'] ?? 0);
        if ($ac !== $bc) {
            return $bc - $ac;
        }
        return strcmp((string)$a['full_name'], (string)$b['full_name']);
    });
    $want_students = preg_match('/^\d{3,}$/', $query) || preg_match('/دانش/u', $query);
    if ($want_students || !$people) {
        foreach ($students as $row) {
            $people[] = [
                'role' => 'student',
                'role_fa' => 'دانش‌آموز',
                'file_number' => (string)$row['username'],
                'full_name' => (string)$row['full_name'],
            ];
        }
    }
    if (!$people) {
        return [
            'ok' => true,
            'count' => 0,
            'people' => [],
            'note' => 'نه معلم و نه دانش‌آموزی با این نام پیدا شد.',
        ];
    }
    if (count($people) === 1 && $people[0]['role'] === 'student') {
        $overview = supervisor_ai_student_overview($pdo, $people[0]['file_number']);
        $people[0]['classes'] = $overview['classes'] ?? [];
    }
    $teachers_n = 0;
    foreach ($people as $p) {
        if (($p['role'] ?? '') === 'teacher') {
            $teachers_n++;
        }
    }
    if ($gender !== '' && $teachers_n > 1) {
        $people = supervisor_ai_filter_people_by_gender($people, $gender);
        $teachers_n = 0;
        foreach ($people as $p) {
            if (($p['role'] ?? '') === 'teacher') {
                $teachers_n++;
            }
        }
    }
    $note = null;
    if ($teachers_n > 1) {
        $note = 'چند معلم هم‌نام‌خانوادگی است. اگر آقا/خانم مشخص است همان را بگو؛ وگرنه اسم کوچک را بپرس. لیست کامل کلاس‌های همه را پشت‌سرهم نریز.';
    }
    return [
        'ok' => true,
        'count' => count($people),
        'teacher_count' => $teachers_n,
        'people' => $people,
        'note' => $note,
    ];
}

function supervisor_ai_find_student(PDO $pdo, $query) {
    $query = supervisor_ai_norm($query);
    if ($query === '') {
        return null;
    }
    $st = $pdo->prepare("
        SELECT id, username AS file_number, full_name
        FROM users
        WHERE role = 'student' AND is_deleted = 0 AND username = ?
        LIMIT 1
    ");
    $st->execute([$query]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        return $row;
    }
    $like = '%' . $query . '%';
    $st = $pdo->prepare("
        SELECT id, username AS file_number, full_name
        FROM users
        WHERE role = 'student' AND is_deleted = 0
          AND (username LIKE ? OR full_name LIKE ?)
        ORDER BY full_name
        LIMIT 8
    ");
    $st->execute([$like, $like]);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);
    if (count($rows) === 1) {
        return $rows[0];
    }
    return $rows;
}

function supervisor_ai_student_overview(PDO $pdo, $query) {
    $found = supervisor_ai_find_student($pdo, $query);
    if (!$found) {
        return ['ok' => false, 'error' => 'دانش‌آموز پیدا نشد.'];
    }
    if (isset($found[0]) && is_array($found[0])) {
        $matches = [];
        foreach ($found as $row) {
            $matches[] = [
                'file_number' => (string)$row['file_number'],
                'full_name' => (string)$row['full_name'],
            ];
        }
        return [
            'ok' => true,
            'ambiguous' => true,
            'matches' => $matches,
            'note' => 'چند دانش‌آموز هم‌نام است. شماره پرونده را بگو تا جزئیات غیبت بیاید.',
        ];
    }

    $user_id = (int)$found['id'];
    if (!function_exists('term_sql_teaching')) {
        require_once __DIR__ . '/terms.php';
    }
    $st = $pdo->prepare("
        SELECT
            c.id,
            c.title,
            c.class_type,
            c.schedule,
            c.schedule_type,
            u.full_name AS teacher_name,
            t.title AS term_title,
            CASE WHEN " . term_sql_teaching('t') . " THEN 1 ELSE 0 END AS is_current
        FROM class_students cs
        JOIN classes c ON c.id = cs.class_id
        LEFT JOIN terms t ON c.term_id = t.id
        LEFT JOIN users u ON c.teacher_id = u.id
        WHERE cs.user_id = ?
          AND c.is_deleted = 0
          AND IFNULL(c.is_archived, 0) = 0
          AND (t.id IS NULL OR t.is_deleted = 0 OR t.is_deleted IS NULL)
        ORDER BY is_current DESC, t.starts_on DESC, c.title
    ");
    $st->execute([$user_id]);
    $classes = $st->fetchAll(PDO::FETCH_ASSOC);

    $att = $pdo->prepare("
        SELECT class_id, class_date, status, is_present
        FROM attendance
        WHERE user_id = ?
          AND class_date IS NOT NULL
          AND class_date != '0000-00-00'
          AND class_date >= '1900-01-01'
        ORDER BY class_date DESC
    ");
    $att->execute([$user_id]);
    $by_class = [];
    foreach ($att->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $cid = (int)$row['class_id'];
        if (!isset($by_class[$cid])) {
            $by_class[$cid] = ['present' => 0, 'absent' => 0, 'absent_dates' => []];
        }
        if (supervisor_ai_is_present_row($row)) {
            $by_class[$cid]['present']++;
        } else {
            $by_class[$cid]['absent']++;
            if (count($by_class[$cid]['absent_dates']) < 8) {
                $by_class[$cid]['absent_dates'][] = supervisor_ai_pretty_date($row['class_date']);
            }
        }
    }

    $out_classes = [];
    foreach ($classes as $c) {
        $cid = (int)$c['id'];
        $stats = $by_class[$cid] ?? ['present' => 0, 'absent' => 0, 'absent_dates' => []];
        $out_classes[] = [
            'title' => (string)$c['title'],
            'teacher' => (string)($c['teacher_name'] ?? ''),
            'term' => (string)($c['term_title'] ?? ''),
            'type' => (string)($c['class_type'] ?? ''),
            'schedule' => (string)($c['schedule'] ?? ''),
            'days' => supervisor_ai_schedule_label($c['schedule_type'] ?? ''),
            'current_term' => ((int)$c['is_current'] === 1),
            'present_count' => (int)$stats['present'],
            'absent_count' => (int)$stats['absent'],
            'recent_absent_dates' => $stats['absent_dates'],
        ];
    }

    return [
        'ok' => true,
        'file_number' => (string)$found['file_number'],
        'full_name' => (string)$found['full_name'],
        'classes' => $out_classes,
    ];
}

function supervisor_ai_list_classes(PDO $pdo, $query = '', $day = '') {
    if (!function_exists('term_sql_teaching')) {
        require_once __DIR__ . '/terms.php';
    }
    $query = supervisor_ai_norm($query);
    $day_code = supervisor_ai_parse_day_filter($day !== '' ? $day : $query);
    $name_query = $query;
    $query_is_day = ($day_code !== '' && preg_match('/زوج|فرد|even|odd|شنبه|یکشنبه|دوشنبه|سه‌شنبه|سه\s*شنبه|چهارشنبه|پنجشنبه|پنج‌شنبه/u', $query)
        && !preg_match('/[A-Za-z]{2,}|\d{2,}/', $query));
    if ($day_code !== '' && ($day !== '' || $query_is_day || preg_match('/^(زوج|فرد|even|odd)$/iu', $query))) {
        $name_query = '';
    }
    $sql = "
        SELECT c.title, c.class_type, c.schedule, c.schedule_type,
               u.full_name AS teacher_name, t.title AS term_title
        FROM classes c
        JOIN terms t ON c.term_id = t.id
        LEFT JOIN users u ON c.teacher_id = u.id
        WHERE c.is_deleted = 0
          AND IFNULL(c.is_archived, 0) = 0
          AND " . term_sql_teaching('t') . "
    ";
    $params = [];
    if ($day_code !== '') {
        $sql .= " AND c.schedule_type = ?";
        $params[] = $day_code;
    }
    if ($name_query !== '') {
        $ors = ['c.title LIKE ?', 'u.full_name LIKE ?'];
        $like = '%' . $name_query . '%';
        $params[] = $like;
        $params[] = $like;
        foreach (supervisor_ai_query_needles($name_query) as $n) {
            if ($n === '' || strcasecmp($n, $name_query) === 0) {
                continue;
            }
            $ors[] = 'c.title LIKE ?';
            $ors[] = 'u.full_name LIKE ?';
            $params[] = '%' . $n . '%';
            $params[] = '%' . $n . '%';
        }
        $ids = [];
        foreach (supervisor_ai_fuzzy_staff($pdo, $name_query) as $row) {
            $ids[] = (int)$row['id'];
        }
        if ($ids) {
            $ors[] = 'c.teacher_id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')';
            foreach ($ids as $id) {
                $params[] = $id;
            }
        }
        $sql .= ' AND (' . implode(' OR ', $ors) . ')';
    }
    $sql .= " ORDER BY c.schedule_type, c.title LIMIT 120";
    $st = $pdo->prepare($sql);
    $st->execute($params);
    $rows = [];
    $even = 0;
    $odd = 0;
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $code = strtolower(trim((string)($row['schedule_type'] ?? '')));
        if ($code === 'even') {
            $even++;
        } elseif ($code === 'odd') {
            $odd++;
        }
        $rows[] = [
            'title' => (string)$row['title'],
            'teacher' => (string)($row['teacher_name'] ?? ''),
            'term' => (string)($row['term_title'] ?? ''),
            'type' => (string)($row['class_type'] ?? ''),
            'schedule' => (string)($row['schedule'] ?? ''),
            'days' => supervisor_ai_schedule_label($row['schedule_type'] ?? ''),
        ];
    }
    $out = [
        'ok' => true,
        'count' => count($rows),
        'even_count' => $even,
        'odd_count' => $odd,
        'filter' => $day_code === 'even'
            ? 'زوج (شنبه/دوشنبه/چهارشنبه)'
            : ($day_code === 'odd' ? 'فرد (یکشنبه/سه‌شنبه/پنجشنبه)' : 'همه'),
        'classes' => $rows,
    ];
    if ($day_code === 'even' || $day_code === 'odd') {
        $out['note'] = 'عدد count تعداد واقعی همین فیلتر است. از خودت کلاس کم یا زیاد نکن.';
        $out['summary_fa'] = 'در روزهای ' . ($day_code === 'even' ? 'زوج (شنبه/دوشنبه/چهارشنبه)' : 'فرد (یکشنبه/سه‌شنبه/پنجشنبه)')
            . ' ' . count($rows) . ' کلاس برگزار می‌شود.';
    }
    return $out;
}

function supervisor_ai_match_classes(PDO $pdo, $query, $day = '', $clock = '', $type = '') {
    if (!function_exists('term_sql_teaching')) {
        require_once __DIR__ . '/terms.php';
    }
    $query = supervisor_ai_norm($query);
    if ($query === '') {
        return [];
    }
    $st = $pdo->prepare("
        SELECT c.id, c.title, c.schedule, c.schedule_type, c.class_type, c.online_link, u.full_name AS teacher_name
        FROM classes c
        JOIN terms t ON c.term_id = t.id
        LEFT JOIN users u ON c.teacher_id = u.id
        WHERE c.is_deleted = 0
          AND IFNULL(c.is_archived, 0) = 0
          AND " . term_sql_teaching('t') . "
          AND (c.title = ? OR c.title LIKE ?)
        ORDER BY (c.title = ?) DESC, c.title, c.schedule
        LIMIT 16
    ");
    $like = '%' . $query . '%';
    $st->execute([$query, $like, $query]);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);
    $picked = $rows;
    if ($day === 'even' || $day === 'odd') {
        $picked = array_values(array_filter($picked, function ($c) use ($day) {
            return supervisor_ai_class_day_code($c) === $day;
        }));
    }
    if ($clock !== '') {
        $picked = array_values(array_filter($picked, function ($c) use ($clock) {
            return supervisor_ai_class_clock($c) === $clock;
        }));
    }
    if ($type === 'online' || $type === 'offline' || $type === 'private') {
        $typed = array_values(array_filter($picked, function ($c) use ($type) {
            return strtolower(trim((string)($c['class_type'] ?? ''))) === $type;
        }));
        if ($typed) {
            $picked = $typed;
        }
    }
    return $picked ?: [];
}

function supervisor_ai_class_match_row(array $row) {
    return [
        'title' => (string)($row['title'] ?? ''),
        'teacher' => (string)($row['teacher_name'] ?? $row['teacher'] ?? ''),
        'days' => supervisor_ai_schedule_label($row['schedule_type'] ?? ''),
        'schedule' => (string)($row['schedule'] ?? ''),
    ];
}

function supervisor_ai_class_session(PDO $pdo, $class_query, $date = '') {
    $matches = supervisor_ai_match_classes($pdo, $class_query);
    if (!$matches) {
        return ['ok' => false, 'error' => 'کلاسی با این عنوان در ترم جاری پیدا نشد.'];
    }
    if (count($matches) > 1 && supervisor_ai_norm($class_query) !== supervisor_ai_norm($matches[0]['title'])) {
        $list = [];
        foreach ($matches as $row) {
            $list[] = [
                'title' => (string)$row['title'],
                'teacher' => (string)($row['teacher_name'] ?? ''),
            ];
        }
        return [
            'ok' => true,
            'ambiguous' => true,
            'matches' => $list,
            'note' => 'چند کلاس مشابه است. عنوان دقیق را بگو.',
        ];
    }
    $class = $matches[0];
    $class_id = (int)$class['id'];
    $when = supervisor_ai_resolve_date($date);
    if (!empty($when['error'])) {
        return ['ok' => false, 'error' => $when['error']];
    }
    $gregorian = $when['gregorian'];
    if (!function_exists('rollcall_ensure_reason_column')) {
        require_once __DIR__ . '/rollcall.php';
    }
    rollcall_ensure_reason_column($pdo);

    $st = $pdo->prepare("
        SELECT
            u.full_name,
            u.username AS file_number,
            a.status,
            a.is_present,
            a.reason
        FROM users u
        JOIN class_students cs ON u.id = cs.user_id
        LEFT JOIN attendance a
            ON u.id = a.user_id AND a.class_id = ? AND a.class_date = ?
        WHERE cs.class_id = ?
          AND u.role = 'student'
          AND u.is_deleted = 0
        ORDER BY u.full_name
    ");
    $st->execute([$class_id, $gregorian, $class_id]);
    $present = [];
    $absent = [];
    $enrolled = 0;
    $recorded = 0;
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $enrolled++;
        $has = array_key_exists('status', $row) && $row['status'] !== null;
        if (!$has) {
            continue;
        }
        $recorded++;
        $item = [
            'file_number' => (string)$row['file_number'],
            'full_name' => (string)$row['full_name'],
        ];
        $is_present = supervisor_ai_is_present_row($row);
        if (!$is_present && !empty($row['reason'])) {
            $item['reason'] = (string)$row['reason'];
        }
        if ($is_present) {
            $present[] = $item;
        } else {
            $absent[] = $item;
        }
    }
    $absent_out = $absent;
    if (count($absent_out) > 20) {
        $absent_out = array_slice($absent, 0, 20);
    }
    return [
        'ok' => true,
        'class_title' => (string)$class['title'],
        'teacher' => (string)($class['teacher_name'] ?? ''),
        'date' => $when,
        'rollcall_saved' => $recorded > 0,
        'enrolled_count' => $enrolled,
        'present_count' => count($present),
        'absent_count' => count($absent),
        'absentees' => $absent_out,
        'absentees_truncated' => count($absent) > 20,
        'note' => $recorded === 0
            ? 'برای این تاریخ رول‌کال ذخیره نشده است.'
            : null,
    ];
}

function supervisor_ai_today_absences(PDO $pdo, $date = '') {
    if (!function_exists('term_sql_teaching')) {
        require_once __DIR__ . '/terms.php';
    }
    if (!function_exists('rollcall_normalize_status')) {
        require_once __DIR__ . '/rollcall.php';
    }
    $when = supervisor_ai_resolve_date($date);
    if (!empty($when['error'])) {
        return ['ok' => false, 'error' => $when['error']];
    }
    $gregorian = $when['gregorian'];
    $st = $pdo->prepare("
        SELECT c.title, u.full_name, u.username AS file_number, a.status, a.is_present
        FROM attendance a
        JOIN classes c ON c.id = a.class_id
        JOIN terms t ON c.term_id = t.id
        JOIN users u ON u.id = a.user_id
        WHERE a.class_date = ?
          AND c.is_deleted = 0
          AND IFNULL(c.is_archived, 0) = 0
          AND " . term_sql_teaching('t') . "
          AND u.role = 'student'
          AND u.is_deleted = 0
        ORDER BY c.title, u.full_name
    ");
    $st->execute([$gregorian]);
    $by = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $title = (string)$row['title'];
        if (!isset($by[$title])) {
            $by[$title] = ['present' => 0, 'absent' => 0, 'absentees' => []];
        }
        if (supervisor_ai_is_present_row($row)) {
            $by[$title]['present']++;
        } else {
            $by[$title]['absent']++;
            if (count($by[$title]['absentees']) < 12) {
                $by[$title]['absentees'][] = [
                    'file_number' => (string)$row['file_number'],
                    'full_name' => (string)$row['full_name'],
                ];
            }
        }
    }
    $with_absent = [];
    $all_present = [];
    foreach ($by as $title => $info) {
        if ($info['absent'] < 1) {
            $all_present[] = $title;
            continue;
        }
        $with_absent[] = [
            'title' => $title,
            'present_count' => $info['present'],
            'absent_count' => $info['absent'],
            'absentees' => $info['absentees'],
        ];
    }
    $rollcall_n = count($by);
    $note = null;
    $summary = '';
    $label = trim((string)($when['relative'] ?? '') . ' ' . (string)($when['jalali'] ?? ''));
    $label = supervisor_ai_norm($label);
    if ($rollcall_n === 0) {
        $note = 'برای این تاریخ هیچ رول‌کالی در دیتابیس ذخیره نشده است. این با «همه حاضر بودند» فرق دارد.';
        $summary = ($label !== '' ? $label : 'این تاریخ') . ' هیچ رول‌کالی ذخیره نشده است.';
    } elseif (count($with_absent) === 0) {
        $note = 'رول‌کال ذخیره شده و غایبی در این تاریخ ثبت نشده (همهٔ رکوردها حاضر).';
        $summary = ($label !== '' ? $label : 'این تاریخ') . ' رول‌کال دارد و غایبی ثبت نشده است.';
    } else {
        $summary = ($label !== '' ? $label : 'این تاریخ') . ' در ' . count($with_absent) . ' کلاس غایب ثبت شده است.';
    }
    $out = [
        'ok' => true,
        'date' => $when,
        'rollcall_class_count' => $rollcall_n,
        'absent_class_count' => count($with_absent),
        'classes_with_absences' => $with_absent,
        'classes_all_present' => $all_present,
        'note' => $note,
        'summary_fa' => $summary,
        'scheduled_count' => 0,
        'day_kind' => '',
        'missing_rollcall' => [],
        'not_started' => [],
    ];
    $gaps = supervisor_ai_missing_rollcall($pdo, $date);
    if (empty($gaps['error'])) {
        $out['scheduled_count'] = (int)($gaps['scheduled_count'] ?? 0);
        $out['day_kind'] = (string)($gaps['day_kind'] ?? '');
        $out['missing_rollcall'] = $gaps['missing'] ?? [];
        $out['not_started'] = $gaps['not_started'] ?? [];
        $out['rollcall_done'] = $gaps['done'] ?? [];
        if ((int)$out['rollcall_class_count'] === 0 && (int)($gaps['done_count'] ?? 0) > 0) {
            $out['rollcall_class_count'] = (int)$gaps['done_count'];
        }
    }
    return $out;
}

function supervisor_ai_day_code_of_date($gregorian) {
    $pack = supervisor_ai_date_pack($gregorian, '');
    $kind = (string)($pack['day_kind'] ?? '');
    if ($kind === 'جمعه') {
        return 'friday';
    }
    return $kind === 'زوج' ? 'even' : 'odd';
}

function supervisor_ai_teaching_classes_for_day(PDO $pdo, $day_code) {
    if ($day_code !== 'even' && $day_code !== 'odd') {
        return [];
    }
    if (!function_exists('term_sql_teaching')) {
        require_once __DIR__ . '/terms.php';
    }
    $st = $pdo->prepare("
        SELECT c.id, c.title, c.schedule, c.schedule_type, c.class_type, u.full_name AS teacher_name
        FROM classes c
        JOIN terms t ON c.term_id = t.id
        LEFT JOIN users u ON c.teacher_id = u.id
        WHERE c.schedule_type = ?
          AND c.is_deleted = 0
          AND IFNULL(c.is_archived, 0) = 0
          AND " . term_sql_teaching('t') . "
        ORDER BY c.schedule, c.title
    ");
    $st->execute([$day_code]);
    return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function supervisor_ai_class_looks_private(array $row) {
    $type = strtolower(trim((string)($row['class_type'] ?? $row['type'] ?? '')));
    if ($type === 'private') {
        return true;
    }
    $title = (string)($row['title'] ?? '');
    return (bool)preg_match('/(^|[.\s_\-])pv([.\s_\-]|$)|خصوصی/iu', $title);
}

function supervisor_ai_class_started_by($schedule, $gregorian) {
    $today = supervisor_ai_today();
    if ($gregorian < $today) {
        return true;
    }
    if ($gregorian > $today) {
        return false;
    }
    if (!function_exists('class_live_parse_schedule')) {
        $live = __DIR__ . '/class-live.php';
        if (is_file($live)) {
            require_once $live;
        }
    }
    if (!function_exists('class_live_parse_schedule')) {
        return true;
    }
    $ref = strtotime($gregorian . ' 12:00:00');
    $win = class_live_parse_schedule($schedule, $ref);
    if (!$win) {
        return true;
    }
    return time() >= (int)$win[0];
}

function supervisor_ai_gap_item(array $row, $started, array $extra = []) {
    return array_merge([
        'title' => (string)($row['title'] ?? ''),
        'teacher' => (string)($row['teacher_name'] ?? $row['teacher'] ?? ''),
        'schedule' => (string)($row['schedule'] ?? ''),
        'started' => (bool)$started,
        'class_type' => (string)($row['class_type'] ?? $row['type'] ?? ''),
        'private' => supervisor_ai_class_looks_private($row),
    ], $extra);
}

function supervisor_ai_last_session_dates(PDO $pdo, array $class_ids) {
    $ids = [];
    foreach ($class_ids as $id) {
        $id = (int)$id;
        if ($id > 0) {
            $ids[$id] = $id;
        }
    }
    if (!$ids) {
        return [];
    }
    $sql = 'SELECT class_id, MAX(session_date) AS last_date FROM session_plans WHERE class_id IN ('
        . implode(',', $ids) . ') GROUP BY class_id';
    try {
        $st = $pdo->query($sql);
        $rows = $st ? $st->fetchAll(PDO::FETCH_ASSOC) : [];
    } catch (Exception $e) {
        return [];
    }
    $out = [];
    foreach ($rows as $row) {
        $d = substr(trim((string)($row['last_date'] ?? '')), 0, 10);
        if ($d !== '') {
            $out[(int)$row['class_id']] = $d;
        }
    }
    return $out;
}

function supervisor_ai_missing_rollcall(PDO $pdo, $date = '') {
    $when = supervisor_ai_resolve_date($date);
    if (!empty($when['error'])) {
        return ['ok' => false, 'error' => $when['error']];
    }
    $gregorian = $when['gregorian'];
    $code = supervisor_ai_day_code_of_date($gregorian);
    if ($code === 'friday') {
        return [
            'ok' => true,
            'date' => $when,
            'scheduled_count' => 0,
            'done_count' => 0,
            'missing_count' => 0,
            'not_started_count' => 0,
            'missing' => [],
            'not_started' => [],
            'done' => [],
            'summary_fa' => trim((string)($when['relative'] ?? '') . ' ' . (string)($when['jalali'] ?? ''))
                . ' جمعه است و کلاسی برگزار نمی‌شود.',
        ];
    }
    $classes = supervisor_ai_teaching_classes_for_day($pdo, $code);
    $check = $pdo->prepare("SELECT COUNT(*) FROM attendance WHERE class_id = ? AND class_date = ?");
    $done = [];
    $missing = [];
    $not_started = [];
    foreach ($classes as $row) {
        $check->execute([(int)$row['id'], $gregorian]);
        $n = (int)$check->fetchColumn();
        $started = supervisor_ai_class_started_by($row['schedule'] ?? '', $gregorian);
        if ($n > 0) {
            $done[] = supervisor_ai_gap_item($row, true);
            continue;
        }
        if (!$started) {
            $not_started[] = supervisor_ai_gap_item($row, false);
        } else {
            $missing[] = supervisor_ai_gap_item($row, true);
        }
    }
    $label = supervisor_ai_norm(trim((string)($when['relative'] ?? '') . ' ' . (string)($when['jalali'] ?? '')));
    $summary = ($label !== '' ? $label : 'این تاریخ') . ': از ' . count($classes) . ' کلاس برنامه‌ریزی‌شده، '
        . count($done) . ' رول‌کال زده‌اند و ' . count($missing) . ' تا نزده‌اند.';
    if ($not_started) {
        $summary .= ' ' . count($not_started) . ' کلاس هنوز شروع نشده.';
    }
    return [
        'ok' => true,
        'date' => $when,
        'day_kind' => $code === 'even' ? 'زوج' : 'فرد',
        'scheduled_count' => count($classes),
        'done_count' => count($done),
        'missing_count' => count($missing),
        'not_started_count' => count($not_started),
        'missing' => $missing,
        'not_started' => $not_started,
        'done' => $done,
        'note' => 'رول‌کال نزده یعنی برای آن کلاس در این تاریخ هیچ ردیف حضورغیاب ذخیره نشده. با لیست غایبین فرق دارد.',
        'summary_fa' => $summary,
    ];
}

function supervisor_ai_missing_session_plans(PDO $pdo, $date = '') {
    $when = supervisor_ai_resolve_date($date);
    if (!empty($when['error'])) {
        return ['ok' => false, 'error' => $when['error']];
    }
    $gregorian = $when['gregorian'];
    $code = supervisor_ai_day_code_of_date($gregorian);
    if ($code === 'friday') {
        return [
            'ok' => true,
            'date' => $when,
            'scheduled_count' => 0,
            'done_count' => 0,
            'missing_count' => 0,
            'not_started_count' => 0,
            'never_planned_count' => 0,
            'missing' => [],
            'not_started' => [],
            'never_planned' => [],
            'done' => [],
            'summary_fa' => trim((string)($when['relative'] ?? '') . ' ' . (string)($when['jalali'] ?? ''))
                . ' جمعه است و کلاسی برگزار نمی‌شود.',
        ];
    }
    $classes = supervisor_ai_teaching_classes_for_day($pdo, $code);
    $lasts = [];
    $ids = [];
    foreach ($classes as $row) {
        $ids[] = (int)$row['id'];
    }
    $lasts = supervisor_ai_last_session_dates($pdo, $ids);
    $check = $pdo->prepare("SELECT COUNT(*) FROM session_plans WHERE class_id = ? AND DATE(session_date) = ?");
    $done = [];
    $missing = [];
    $not_started = [];
    $never_planned = [];
    foreach ($classes as $row) {
        try {
            $check->execute([(int)$row['id'], $gregorian]);
            $n = (int)$check->fetchColumn();
        } catch (Exception $e) {
            $n = 0;
        }
        $started = supervisor_ai_class_started_by($row['schedule'] ?? '', $gregorian);
        $last = (string)($lasts[(int)$row['id']] ?? '');
        $extra = [];
        if ($last !== '') {
            $extra['last_date'] = $last;
            $extra['last_jalali'] = supervisor_ai_pretty_date($last);
            $extra['last_is_today'] = ($last === $gregorian);
        }
        $item = supervisor_ai_gap_item($row, $started || $n > 0, $extra);
        if ($n > 0) {
            $done[] = $item;
            continue;
        }
        if (!$started) {
            $not_started[] = $item;
            continue;
        }
        if ($last === '') {
            $never_planned[] = $item;
            continue;
        }
        $missing[] = $item;
    }
    $label = supervisor_ai_norm(trim((string)($when['relative'] ?? '') . ' ' . (string)($when['jalali'] ?? '')));
    $summary = ($label !== '' ? $label : 'این تاریخ') . ': از ' . count($classes) . ' کلاس برنامه‌ریزی‌شده، '
        . count($done) . ' سشن‌پلن نوشته‌اند و ' . count($missing) . ' کلاسِ پلن‌نویس برای این تاریخ ننوشته‌اند.';
    if ($never_planned) {
        $summary .= ' ' . count($never_planned) . ' کلاس بدون سابقهٔ سشن‌پلن جدا هستند.';
    }
    if ($not_started) {
        $summary .= ' ' . count($not_started) . ' کلاس هنوز شروع نشده.';
    }
    return [
        'ok' => true,
        'date' => $when,
        'day_kind' => $code === 'even' ? 'زوج' : 'فرد',
        'scheduled_count' => count($classes),
        'done_count' => count($done),
        'missing_count' => count($missing),
        'not_started_count' => count($not_started),
        'never_planned_count' => count($never_planned),
        'missing' => $missing,
        'not_started' => $not_started,
        'never_planned' => $never_planned,
        'done' => $done,
        'note' => 'سشن‌پلن نزده یعنی کلاسی که قبلاً پلن داشته و برای همان تاریخ ردیف ندارد. کلاس بدون سابقهٔ پلن (معمولاً خصوصی) جداست. با غایب فرق دارد.',
        'summary_fa' => $summary,
    ];
}

function supervisor_ai_compact_titles(array $items) {
    $names = [];
    foreach ($items as $c) {
        $title = trim((string)($c['title'] ?? ''));
        if ($title !== '') {
            $names[] = $title;
        }
    }
    return implode('، ', $names);
}

function supervisor_ai_format_gap_lines(array $items, $with_last = false) {
    $lines = [];
    $i = 1;
    foreach ($items as $c) {
        $line = $i . '. ' . (string)($c['title'] ?? '')
            . ' — معلم: ' . (string)($c['teacher'] ?? '');
        if (trim((string)($c['schedule'] ?? '')) !== '') {
            $line .= ' (ساعت ' . $c['schedule'] . ')';
        }
        if ($with_last) {
            $last = trim((string)($c['last_jalali'] ?? ''));
            if ($last !== '' && empty($c['last_is_today'])) {
                $line .= ' — آخرین پلن ' . $last . ' (برای این تاریخ نه)';
            }
        }
        $lines[] = $line;
        $i++;
    }
    return $lines;
}

function supervisor_ai_format_gap_by_teacher(array $items, $with_last = false) {
    $by = [];
    foreach ($items as $c) {
        $teacher = trim((string)($c['teacher'] ?? ''));
        if ($teacher === '') {
            $teacher = 'نامشخص';
        }
        if (!isset($by[$teacher])) {
            $by[$teacher] = [];
        }
        $by[$teacher][] = $c;
    }
    $lines = [];
    $i = 1;
    foreach ($by as $teacher => $cls) {
        $bits = [];
        foreach ($cls as $c) {
            $bit = (string)($c['title'] ?? '');
            if (trim((string)($c['schedule'] ?? '')) !== '') {
                $bit .= ' (ساعت ' . $c['schedule'] . ')';
            }
            if ($with_last) {
                $last = trim((string)($c['last_jalali'] ?? ''));
                if ($last !== '' && empty($c['last_is_today'])) {
                    $bit .= ' — آخرین پلن ' . $last;
                }
            }
            $bits[] = $bit;
        }
        $lines[] = $i . '. ' . $teacher . ' — ' . implode('؛ ', $bits);
        $i++;
    }
    return $lines;
}

function supervisor_ai_format_gap_block(array $items, $by_teacher, $with_last = false) {
    return $by_teacher
        ? supervisor_ai_format_gap_by_teacher($items, $with_last)
        : supervisor_ai_format_gap_lines($items, $with_last);
}

function supervisor_ai_format_never_planned_note(array $never) {
    if (!$never) {
        return [];
    }
    $priv = 0;
    foreach ($never as $c) {
        if (!empty($c['private'])) {
            $priv++;
        }
    }
    $n = count($never);
    $label = ($priv === $n)
        ? $n . ' کلاس خصوصی سشن‌پلن ندارند و از «ننوشتهٔ امروز» جدا هستند: '
        : $n . ' کلاس بدون سابقهٔ سشن‌پلن از «ننوشتهٔ امروز» جدا هستند: ';
    return [$label . supervisor_ai_compact_titles($never)];
}

function supervisor_ai_format_session_plan_day(array $data, $mode = 'missing', $by_teacher = false) {
    if (!empty($data['error'])) {
        return (string)$data['error'];
    }
    $when = $data['date'] ?? [];
    $head = supervisor_ai_norm(trim(
        (string)($when['relative'] ?? '') . ' — ' . (string)($when['jalali'] ?? '') . ' — ' . (string)($when['weekday'] ?? '')
    ));
    if ((int)($data['scheduled_count'] ?? 0) === 0) {
        return $head . "\n" . (string)($data['summary_fa'] ?? 'برای این تاریخ کلاسی برنامه‌ریزی نشده است.');
    }
    $missing = $data['missing'] ?? [];
    $not_started = $data['not_started'] ?? [];
    $never = $data['never_planned'] ?? [];
    $done = $data['done'] ?? [];
    $done_n = count($done);
    $writers = $done_n + count($missing);
    $lines = [$head . ' — روز ' . (string)($data['day_kind'] ?? '')];

    if ($mode === 'written') {
        if ($writers > 0) {
            $lines[] = 'از ' . $writers . ' کلاس شروع‌شدهٔ پلن‌نویس، ' . $done_n . ' تا برای همین تاریخ نوشته‌اند.';
        } else {
            $lines[] = 'هنوز کلاس پلن‌نویسِ شروع‌شده‌ای نیست.';
        }
        if ($done) {
            $lines[] = 'نوشته‌اند (' . $done_n . ($by_teacher ? ' معلم/کلاس' : ' کلاس') . '):';
            $lines = array_merge($lines, supervisor_ai_format_gap_block($done, $by_teacher));
        } else {
            $lines[] = 'هنوز هیچ کلاس پلن‌نویسی برای این تاریخ سشن‌پلن ننوشته است.';
        }
        if ($missing) {
            $lines[] = count($missing) . ' کلاس پلن‌نویس برای این تاریخ ننوشته‌اند.';
        }
        $lines = array_merge($lines, supervisor_ai_format_never_planned_note($never));
        if ($not_started) {
            $lines[] = count($not_started) . ' کلاس هنوز شروع نشده.';
        }
        return implode("\n", $lines);
    }

    if ($mode === 'nobody') {
        if ($done_n === 0 && !$missing) {
            $lines[] = 'هنوز هیچ کلاس پلن‌نویسِ شروع‌شده‌ای نیست که سشن‌پلن امروز را بسنجیم.';
            $lines = array_merge($lines, supervisor_ai_format_never_planned_note($never));
            if ($not_started) {
                $lines[] = count($not_started) . ' کلاس هنوز شروع نشده‌اند.';
            }
            return implode("\n", $lines);
        }
        if ($done_n === 0) {
            $lines[] = 'بله. از کلاس‌های پلن‌نویسِ شروع‌شده هیچ‌کس برای این تاریخ سشن‌پلن ننوشته.';
        } else {
            $lines[] = 'نه. ' . $done_n . ' کلاس برای این تاریخ نوشته‌اند:';
            $lines = array_merge($lines, supervisor_ai_format_gap_block($done, $by_teacher));
        }
        if ($missing) {
            $lines[] = 'پلن‌نویسِ شروع‌شده بدون پلن این تاریخ (' . count($missing) . '):';
            $lines = array_merge($lines, supervisor_ai_format_gap_block($missing, $by_teacher, true));
        }
        $lines = array_merge($lines, supervisor_ai_format_never_planned_note($never));
        if ($not_started) {
            $lines[] = count($not_started) . ' کلاس هنوز شروع نشده و برای امروز جدا حساب می‌شوند.';
        }
        return implode("\n", $lines);
    }

    if ($writers > 0) {
        $lines[] = 'از ' . $writers . ' کلاس شروع‌شده که سشن‌پلن می‌نویسند، '
            . $done_n . ' تا نوشته‌اند و ' . count($missing) . ' تا برای این تاریخ ننوشته‌اند.';
    } else {
        $lines[] = 'هنوز کلاس پلن‌نویسِ شروع‌شده‌ای نیست.';
    }
    if ($missing) {
        $who = $by_teacher ? 'معلم' : 'کلاس';
        $lines[] = 'ننوشتهٔ امروز (' . count($missing) . ' ' . $who . '):';
        $lines = array_merge($lines, supervisor_ai_format_gap_block($missing, $by_teacher, true));
    } elseif ($writers > 0) {
        $lines[] = 'همهٔ کلاس‌های پلن‌نویس که تا الان باید می‌نوشتند، نوشته‌اند.';
    }
    $lines = array_merge($lines, supervisor_ai_format_never_planned_note($never));
    if ($not_started) {
        $lines[] = count($not_started) . ' کلاس هنوز شروع نشده و سشن‌پلن امروزشان بعداً سنجیده می‌شود.';
    }
    return implode("\n", $lines);
}

function supervisor_ai_format_missing_rollcall(array $data) {
    if (!empty($data['error'])) {
        return (string)$data['error'];
    }
    $when = $data['date'] ?? [];
    $head = supervisor_ai_norm(trim(
        (string)($when['relative'] ?? '') . ' — ' . (string)($when['jalali'] ?? '') . ' — ' . (string)($when['weekday'] ?? '')
    ));
    if ((int)($data['scheduled_count'] ?? 0) === 0) {
        return $head . "\n" . (string)($data['summary_fa'] ?? 'برای این تاریخ کلاسی برنامه‌ریزی نشده است.');
    }
    $missing = $data['missing'] ?? [];
    $not_started = $data['not_started'] ?? [];
    $done_n = (int)($data['done_count'] ?? 0);
    $sched = (int)($data['scheduled_count'] ?? 0);
    $lines = [
        $head . ' — روز ' . (string)($data['day_kind'] ?? ''),
        'از ' . $sched . ' کلاس آن روز، ' . $done_n . ' رول‌کال زده‌اند.',
    ];
    if ($missing) {
        $lines[] = 'رول‌کال نزده (' . count($missing) . ' کلاس):';
        $lines = array_merge($lines, supervisor_ai_format_gap_lines($missing));
    } else {
        $lines[] = 'همهٔ کلاس‌هایی که تا الان باید رول‌کال می‌زدند، زده‌اند.';
    }
    if ($not_started) {
        $lines[] = count($not_started) . ' کلاس هنوز شروع نشده و رول‌کالشان بعداً سنجیده می‌شود.';
    }
    return implode("\n", $lines);
}

function supervisor_ai_format_missing_session_plans(array $data) {
    return supervisor_ai_format_session_plan_day($data, 'missing');
}

function supervisor_ai_panel_map() {
    return [
        'note' => 'این نقشهٔ صفحات پنل سوپروایزر است. دادهٔ واقعی را با ابزارهای دیگر از دیتابیس بخوان.',
        'pages' => [
            ['id' => 'dashboard', 'title' => 'Dashboard', 'about' => 'کلاس‌های امروز بر اساس زوج/فرد. زوج=شنبه/دوشنبه/چهارشنبه، فرد=یکشنبه/سه‌شنبه/پنجشنبه. جمعه کلاس ندارد.'],
            ['id' => 'ai-assistance', 'title' => 'AI Assistance', 'about' => 'صفحهٔ تمام‌قد دستیار سوپروایزر در منوی کناری؛ فقط گزارش از پنل. روی بقیهٔ صفحات دکمهٔ شناور همان چت را باز می‌کند.'],
            ['id' => 'eas', 'title' => 'EAS', 'about' => 'کارت کلاس‌ها: تیک ارزیابی (آبزرو)، تیک و تاریخ مصاحبه، نظرسنجی و جلسات. ارزیابی با مصاحبه یکی نیست.'],
            ['id' => 'classes', 'title' => 'Class Portfolio', 'about' => 'لیست کلاس‌های ترم جاری با معلم و برنامه.'],
            ['id' => 'attendance', 'title' => 'Roll Call', 'about' => 'حضور و غیاب هر جلسه؛ غایب و حاضر ذخیره‌شده در جدول attendance.'],
            ['id' => 'report-card', 'title' => 'report card', 'about' => 'کارنامه و گزارش کلاس.'],
            ['id' => 'homework', 'title' => 'Assignment status', 'about' => 'وضعیت تکلیف هر جلسه (complete/incomplete/not_submitted).'],
            ['id' => 'session-plan', 'title' => 'Session plan', 'about' => 'طرح جلسهٔ هر کلاس که معلم نوشته.'],
            ['id' => 'term-plan', 'title' => 'Term Plan', 'about' => 'تسک جلسات ترم: سوپروایزر با اکسل وارد می‌کند و ریمارک می‌نویسد. معلم تیک/ضربدر می‌زند؛ تیک متد می‌خواهد و ضربدر ریمارک معلم.'],
            ['id' => 'study-plan', 'title' => 'Study Plan', 'about' => 'ارسال پیامک برنامهٔ مطالعه برای خانواده.'],
            ['id' => 'error-gravity', 'title' => 'Error Gravity', 'about' => 'خطاهای پرتکرار کلاس.'],
            ['id' => 'observation-forms', 'title' => 'observation forms', 'about' => 'فرم مشاهدهٔ کلاس.'],
            ['id' => 'mind-lingo', 'title' => 'Mind Lingo', 'about' => 'ارزیابی Mind Lingo دانش‌آموزان.'],
            ['id' => 'users', 'title' => "Users' Classification", 'about' => 'نقش کاربران (معلم/دانش‌آموز/سوپروایزر).'],
            ['id' => 'students-info', 'title' => 'Students Information', 'about' => 'پرونده، اسم، فامیل و شماره تماس دانش‌آموز. شماره پرونده همان username است. اگر شماره تماس خالی باشد در لیست بدون‌تلفن می‌آید.'],
            ['id' => 'teachers-info', 'title' => 'Teachers Information', 'about' => 'لیست اساتید و کلاس‌هایشان به‌تفکیک ترم. بعضی معلم‌ها نقش supervisor دارند ولی کلاس درس می‌دهند.'],
            ['id' => 'terms', 'title' => 'Terms & Classes', 'about' => 'ترم‌ها و کلاس‌بندی. ترم در حال تدریس starts_on خالی یا گذشته و بایگانی‌نشده است.'],
            ['id' => 'placement', 'title' => 'Placement', 'about' => 'کلاس‌بندی کارت‌محور ترم جدید؛ مصاحبه روی EAS ذخیره می‌شود.'],
        ],
        'glossary' => [
            'زوج' => 'even = شنبه/دوشنبه/چهارشنبه',
            'فرد' => 'odd = یکشنبه/سه‌شنبه/پنجشنبه',
            'پرونده' => 'شماره دانش‌آموز = users.username',
            'PV' => 'کلاس خصوصی',
            'رول‌کال' => 'حضورغیاب ذخیره‌شده؛ اگر صفر باشد یعنی ثبت نشده نه لزوماً همه حاضر',
            'رول‌کال نزده' => 'کلاس آن روز که هیچ ردیف attendance ندارد. با غایب فرق دارد. ابزار: list_missing_rollcall',
            'سشن‌پلن نزده' => 'کلاس پلن‌نویس که برای همان تاریخ session_plans ندارد. کلاس خصوصی/بدون سابقهٔ پلن جداست. ابزار: list_missing_session_plans',
        ],
    ];
}

function supervisor_ai_list_terms(PDO $pdo) {
    if (!function_exists('term_sql_teaching')) {
        require_once __DIR__ . '/terms.php';
    }
    $st = $pdo->query("
        SELECT t.id, t.title, t.starts_on, t.is_archived,
               CASE WHEN " . term_sql_teaching('t') . " THEN 1 ELSE 0 END AS is_teaching
        FROM terms t
        WHERE t.is_deleted = 0 OR t.is_deleted IS NULL
        ORDER BY t.is_archived ASC, t.starts_on DESC, t.id DESC
        LIMIT 16
    ");
    $rows = $st ? $st->fetchAll(PDO::FETCH_ASSOC) : [];
    $out = [];
    foreach ($rows as $row) {
        $out[] = [
            'title' => (string)$row['title'],
            'starts_on' => supervisor_ai_pretty_date((string)($row['starts_on'] ?? '')) ?: ($row['starts_on'] ?? ''),
            'archived' => !empty($row['is_archived']),
            'teaching' => ((int)$row['is_teaching'] === 1),
        ];
    }
    return ['ok' => true, 'count' => count($out), 'terms' => $out];
}

function supervisor_ai_class_overview(PDO $pdo, $class_query) {
    $matches = supervisor_ai_match_classes($pdo, $class_query);
    if (!$matches) {
        $staff = supervisor_ai_lookup_person($pdo, $class_query);
        if (!empty($staff['people'])) {
            return $staff;
        }
        return ['ok' => false, 'error' => 'کلاسی با این عنوان در ترم جاری پیدا نشد.'];
    }
    if (count($matches) > 1 && supervisor_ai_norm($class_query) !== supervisor_ai_norm($matches[0]['title'])) {
        $list = [];
        foreach ($matches as $row) {
            $list[] = [
                'title' => (string)$row['title'],
                'teacher' => (string)($row['teacher_name'] ?? ''),
            ];
        }
        return ['ok' => true, 'ambiguous' => true, 'matches' => $list, 'note' => 'چند کلاس مشابه است. عنوان دقیق را بگو.'];
    }
    $class = $matches[0];
    $class_id = (int)$class['id'];
    $meta = $pdo->prepare("
        SELECT c.title, c.class_type, c.schedule, c.schedule_type, c.online_link, u.full_name AS teacher_name, t.title AS term_title
        FROM classes c
        LEFT JOIN users u ON c.teacher_id = u.id
        LEFT JOIN terms t ON c.term_id = t.id
        WHERE c.id = ?
        LIMIT 1
    ");
    $meta->execute([$class_id]);
    $info = $meta->fetch(PDO::FETCH_ASSOC) ?: [];
    $st = $pdo->prepare("
        SELECT u.full_name, u.username AS file_number
        FROM class_students cs
        JOIN users u ON u.id = cs.user_id
        WHERE cs.class_id = ? AND u.role = 'student' AND u.is_deleted = 0
        ORDER BY u.full_name
        LIMIT 60
    ");
    $st->execute([$class_id]);
    $students = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $students[] = [
            'full_name' => (string)$row['full_name'],
            'file_number' => (string)$row['file_number'],
        ];
    }
    $plans = [];
    try {
        $sp = $pdo->prepare("
            SELECT session_date, LEFT(content, 280) AS content
            FROM session_plans
            WHERE class_id = ?
            ORDER BY session_date DESC
            LIMIT 3
        ");
        $sp->execute([$class_id]);
        foreach ($sp->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $plans[] = [
                'date' => supervisor_ai_pretty_date($row['session_date']),
                'content' => (string)$row['content'],
            ];
        }
    } catch (Exception $e) {
        $plans = [];
    }
    $hw = ['complete' => 0, 'incomplete' => 0, 'not_submitted' => 0, 'last_date' => null];
    try {
        $hs = $pdo->prepare("
            SELECT homework_date, status, COUNT(*) AS n
            FROM homework_status
            WHERE class_id = ?
            GROUP BY homework_date, status
            ORDER BY homework_date DESC
            LIMIT 12
        ");
        $hs->execute([$class_id]);
        foreach ($hs->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if ($hw['last_date'] === null) {
                $hw['last_date'] = supervisor_ai_pretty_date($row['homework_date']);
            }
            $status = (string)$row['status'];
            if (isset($hw[$status]) && is_int($hw[$status])) {
                $hw[$status] += (int)$row['n'];
            }
        }
    } catch (Exception $e) {
    }
    return [
        'ok' => true,
        'title' => (string)($info['title'] ?? $class['title']),
        'teacher' => (string)($info['teacher_name'] ?? ''),
        'term' => (string)($info['term_title'] ?? ''),
        'type' => (string)($info['class_type'] ?? ''),
        'online_link' => trim((string)($info['online_link'] ?? '')),
        'schedule' => (string)($info['schedule'] ?? ''),
        'days' => supervisor_ai_schedule_label($info['schedule_type'] ?? ''),
        'student_count' => count($students),
        'students' => $students,
        'recent_session_plans' => $plans,
        'homework_summary' => $hw,
    ];
}

function supervisor_ai_search_panel(PDO $pdo, $query) {
    $query = supervisor_ai_norm($query);
    if ($query === '') {
        return ['ok' => true, 'panel' => supervisor_ai_panel_map()];
    }
    return [
        'ok' => true,
        'person' => supervisor_ai_lookup_person($pdo, $query),
        'classes' => supervisor_ai_list_classes($pdo, $query, ''),
        'note' => 'اگر شخص معلم است کلاس‌هایش را از person.people[].classes بخوان. اگر عنوان کلاس است از classes استفاده کن.',
    ];
}

function supervisor_ai_snapshot(PDO $pdo) {
    $pack = supervisor_ai_date_pack(supervisor_ai_today(), 'امروز');
    $all = supervisor_ai_list_classes($pdo, '', '');
    $today_code = supervisor_ai_today_day_code();
    $today = ($today_code === 'friday')
        ? ['count' => 0, 'classes' => []]
        : supervisor_ai_list_classes($pdo, '', $today_code);
    return [
        'ok' => true,
        'today' => $pack,
        'teaching_class_count' => (int)($all['count'] ?? 0),
        'even_count' => (int)($all['even_count'] ?? 0),
        'odd_count' => (int)($all['odd_count'] ?? 0),
        'today_class_count' => (int)($today['count'] ?? 0),
        'panel' => supervisor_ai_panel_map(),
    ];
}

function supervisor_ai_tools() {
    return [
        [
            'type' => 'function',
            'function' => [
                'name' => 'lookup_person',
                'description' => 'Look up a person by Persian or English name. Searches TEACHERS and students. Always use this first when the user names a person (مارال، متین، اخوان، ...).',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'query' => [
                            'type' => 'string',
                            'description' => 'Person name, e.g. مارال قنبرنژاد or Maral',
                        ],
                    ],
                    'required' => ['query'],
                ],
            ],
        ],
        [
            'type' => 'function',
            'function' => [
                'name' => 'search_students',
                'description' => 'Search students only, by name or file number. Use when the user clearly means a student or gives a file number.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'query' => [
                            'type' => 'string',
                            'description' => 'Name or file number',
                        ],
                    ],
                    'required' => ['query'],
                ],
            ],
        ],
        [
            'type' => 'function',
            'function' => [
                'name' => 'get_student_overview',
                'description' => 'Student classes and absence counts. Prefer file_number from lookup_person.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'query' => [
                            'type' => 'string',
                            'description' => 'File number or student name',
                        ],
                    ],
                    'required' => ['query'],
                ],
            ],
        ],
        [
            'type' => 'function',
            'function' => [
                'name' => 'list_classes',
                'description' => 'List current-term classes. For even/odd days pass day=زوج or فرد. Do not filter the returned list yourself; report the tool count.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'query' => [
                            'type' => 'string',
                            'description' => 'Optional class title or teacher name. Empty if only filtering by day.',
                        ],
                        'day' => [
                            'type' => 'string',
                            'description' => 'زوج or فرد or even or odd. Empty for all days.',
                        ],
                    ],
                ],
            ],
        ],
        [
            'type' => 'function',
            'function' => [
                'name' => 'get_class_session',
                'description' => 'Attendance of one class on a date. Pass date as دیروز/امروز, do not convert Jalali yourself.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'class_query' => [
                            'type' => 'string',
                            'description' => 'Class title, e.g. FCE or Sprouts',
                        ],
                        'date' => [
                            'type' => 'string',
                            'description' => 'دیروز or امروز or a Jalali/Gregorian date',
                        ],
                    ],
                    'required' => ['class_query'],
                ],
            ],
        ],
        [
            'type' => 'function',
            'function' => [
                'name' => 'search_panel',
                'description' => 'Search teachers, students, and classes in one call. Use for a person last name, class code, or when unsure. Persian or English.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'query' => [
                            'type' => 'string',
                            'description' => 'Name, last name, file number, or class title. Empty returns the panel map.',
                        ],
                    ],
                ],
            ],
        ],
        [
            'type' => 'function',
            'function' => [
                'name' => 'get_class_overview',
                'description' => 'One class: teacher, schedule, roster, recent session plans, homework summary, online_link (AloCom). For «لینک کلاس» return online_link, not the roster.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'class_query' => [
                            'type' => 'string',
                            'description' => 'Class title, e.g. P1 or UI2',
                        ],
                    ],
                    'required' => ['class_query'],
                ],
            ],
        ],
        [
            'type' => 'function',
            'function' => [
                'name' => 'get_session_plan',
                'description' => 'Session plan of a class. Use session_n=1 for first session. Pass day=odd|even and clock=16:15 when the user names them.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'class_query' => ['type' => 'string'],
                        'date' => ['type' => 'string'],
                        'session_n' => ['type' => 'integer'],
                        'day' => ['type' => 'string'],
                        'clock' => ['type' => 'string'],
                    ],
                    'required' => ['class_query'],
                ],
            ],
        ],
        [
            'type' => 'function',
            'function' => [
                'name' => 'list_students_without_phone',
                'description' => 'Students Information rows whose phone field is empty.',
                'parameters' => ['type' => 'object', 'properties' => new stdClass()],
            ],
        ],
        [
            'type' => 'function',
            'function' => [
                'name' => 'list_terms',
                'description' => 'List educational terms and which one is currently teaching.',
                'parameters' => ['type' => 'object', 'properties' => new stdClass()],
            ],
        ],
        [
            'type' => 'function',
            'function' => [
                'name' => 'explain_panel',
                'description' => 'Map of supervisor panel pages, glossary, and today class counts. Use when the user asks what the panel has or where something lives.',
                'parameters' => ['type' => 'object', 'properties' => new stdClass()],
            ],
        ],
        [
            'type' => 'function',
            'function' => [
                'name' => 'list_today_absences',
                'description' => 'Absentees from SAVED roll calls on a date. This is NOT who skipped taking roll call. For yesterday pass date=دیروز exactly.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'date' => [
                            'type' => 'string',
                            'description' => 'دیروز or امروز or a date string',
                        ],
                    ],
                ],
            ],
        ],
        [
            'type' => 'function',
            'function' => [
                'name' => 'list_missing_rollcall',
                'description' => 'Classes scheduled that day whose teachers did NOT save any attendance. Use for «رول‌کال نزدن / کی رول کال نزده». Different from absentees. Pass date=دیروز for yesterday.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'date' => [
                            'type' => 'string',
                            'description' => 'دیروز or امروز or a date string',
                        ],
                    ],
                ],
            ],
        ],
        [
            'type' => 'function',
            'function' => [
                'name' => 'list_missing_session_plans',
                'description' => 'Session-plan writers who skipped today. Excludes private/never-planned classes. Different from absentees. Pass date=دیروز for yesterday.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'date' => [
                            'type' => 'string',
                            'description' => 'دیروز or امروز or a date string',
                        ],
                    ],
                ],
            ],
        ],
        [
            'type' => 'function',
            'function' => [
                'name' => 'list_missing_homework',
                'description' => 'Classes scheduled that day with no homework_status rows. Teacher did not save homework. Pass date=دیروز for yesterday.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'date' => ['type' => 'string', 'description' => 'دیروز or امروز'],
                    ],
                ],
            ],
        ],
        [
            'type' => 'function',
            'function' => [
                'name' => 'list_homework_gaps',
                'description' => 'Students with incomplete or not_submitted homework on a date. Different from teachers who did not save homework.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'date' => ['type' => 'string'],
                        'class_query' => ['type' => 'string'],
                    ],
                ],
            ],
        ],
        [
            'type' => 'function',
            'function' => [
                'name' => 'list_eas_gaps',
                'description' => 'Current-term classes missing an EAS checkbox. flag=observation is ارزیابی/آبزرو (observation_status only — never interview_date). flag=interview is مصاحبه (interview_status + schedule). flag=poll is نظرسنجی.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'flag' => ['type' => 'string', 'description' => 'observation (ارزیابی/آبزرو), interview (مصاحبه), or poll (نظرسنجی). Never use interview for آبزرو.'],
                    ],
                    'required' => ['flag'],
                ],
            ],
        ],
        [
            'type' => 'function',
            'function' => [
                'name' => 'get_class_feature',
                'description' => 'One class feature: error_gravity, observation_form, mind_lingo, report_card, eas. Pass feature and class title. Previous-term grades of a class roster: feature=report_card (history, not the terms list).',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'feature' => ['type' => 'string'],
                        'class_query' => ['type' => 'string'],
                    ],
                    'required' => ['feature'],
                ],
            ],
        ],
        [
            'type' => 'function',
            'function' => [
                'name' => 'list_live_classes',
                'description' => 'Classes in session right now (green live dot).',
                'parameters' => ['type' => 'object', 'properties' => new stdClass()],
            ],
        ],
        [
            'type' => 'function',
            'function' => [
                'name' => 'panel_counts',
                'description' => 'User role counts, teacher list, or terms. kind=users|teachers|terms.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'kind' => ['type' => 'string'],
                    ],
                ],
            ],
        ],
    ];
}

function supervisor_ai_run_tool(PDO $pdo, $name, array $args, $user_text = '') {
    if ($name === 'search_students') {
        $q = supervisor_ai_norm($args['query'] ?? '');
        if ($q !== '' && !preg_match('/^\d{3,}$/', $q)) {
            $name = 'lookup_person';
        }
    }
    $args = supervisor_ai_rewrite_tool_args($name, $args, $user_text);
    switch ($name) {
        case 'lookup_person':
            return supervisor_ai_lookup_person($pdo, $args['query'] ?? '');
        case 'search_students':
            return supervisor_ai_search_students($pdo, $args['query'] ?? '');
        case 'get_student_overview':
            return supervisor_ai_student_overview($pdo, $args['query'] ?? '');
        case 'list_classes':
            return supervisor_ai_list_classes($pdo, $args['query'] ?? '', $args['day'] ?? '');
        case 'get_class_session':
            return supervisor_ai_class_session($pdo, $args['class_query'] ?? '', $args['date'] ?? '');
        case 'list_today_absences':
            return supervisor_ai_today_absences($pdo, $args['date'] ?? '');
        case 'list_missing_rollcall':
            return supervisor_ai_missing_rollcall($pdo, $args['date'] ?? '');
        case 'list_missing_session_plans':
            return supervisor_ai_missing_session_plans($pdo, $args['date'] ?? '');
        case 'list_missing_homework':
            return supervisor_ai_missing_homework($pdo, $args['date'] ?? '');
        case 'list_homework_gaps':
            return supervisor_ai_homework_status($pdo, $args['date'] ?? '', $args['class_query'] ?? '');
        case 'list_eas_gaps':
            $flag = function_exists('supervisor_ai_eas_normalize_flag')
                ? supervisor_ai_eas_normalize_flag($args['flag'] ?? '')
                : strtolower(trim((string)($args['flag'] ?? '')));
            if (!in_array($flag, ['interview', 'observation', 'poll'], true)) {
                return ['ok' => false, 'error' => 'flag باید observation (ارزیابی/آبزرو) یا interview (مصاحبه) یا poll باشد.'];
            }
            return supervisor_ai_eas_gaps($pdo, $flag);
        case 'get_class_feature':
            $feat = strtolower(trim((string)($args['feature'] ?? '')));
            $cq = $args['class_query'] ?? '';
            if ($feat === 'error_gravity') {
                return supervisor_ai_error_gravity($pdo, $cq, $cq === '');
            }
            if ($feat === 'observation_form') {
                return supervisor_ai_observation_forms($pdo, $cq, $cq === '');
            }
            if ($feat === 'mind_lingo') {
                return supervisor_ai_mind_lingo($pdo, $cq, $cq === '');
            }
            if ($feat === 'report_card') {
                $prev = supervisor_ai_asks_previous_term($user_text);
                return supervisor_ai_report_card($pdo, $cq, $prev);
            }
            if ($feat === 'eas') {
                return $cq !== '' ? supervisor_ai_eas_class($pdo, $cq) : supervisor_ai_eas_gaps($pdo, 'observation');
            }
            return ['ok' => false, 'error' => 'feature نامعتبر است.'];
        case 'list_live_classes':
            return supervisor_ai_live_now($pdo);
        case 'panel_counts':
            $kind = strtolower(trim((string)($args['kind'] ?? 'users')));
            if ($kind === 'teachers') {
                return supervisor_ai_teachers_list($pdo);
            }
            if ($kind === 'terms') {
                $prev = supervisor_ai_asks_previous_term($user_text);
                $grades = (bool)preg_match('/نمره|nomre|کارنامه|quiz|کوییز/iu', supervisor_ai_unfold_finglish($user_text));
                if ($prev || $grades) {
                    $cq = supervisor_ai_guess_class_query($user_text);
                    if ($cq !== '') {
                        return supervisor_ai_report_card($pdo, $cq, $prev);
                    }
                }
                return supervisor_ai_list_terms($pdo);
            }
            return supervisor_ai_users_counts($pdo);
        case 'search_panel':
            return supervisor_ai_search_panel($pdo, $args['query'] ?? '');
        case 'get_class_overview':
            $cq = $args['class_query'] ?? '';
            if (preg_match('/study\s*plan|استادی[\s‌]*پلن/iu', trim((string)$cq))
                || (supervisor_ai_asks_exact_when($user_text) && supervisor_ai_guess_class_query($user_text) === '')) {
                $when = supervisor_ai_date_from_text($user_text);
                return supervisor_ai_study_plan($pdo, $user_text !== '' ? $user_text : 'استادی پلن', $when);
            }
            if (supervisor_ai_asks_class_link($user_text) && function_exists('supervisor_ai_class_link')) {
                return supervisor_ai_class_link($pdo, $cq !== '' ? $cq : supervisor_ai_guess_class_query($user_text), $user_text);
            }
            return supervisor_ai_class_overview($pdo, $cq);
        case 'get_session_plan':
            $bits = [$user_text, $args['day'] ?? '', $args['clock'] ?? ''];
            if (!empty($args['session_n'])) {
                $bits[] = 'جلسه ' . (int)$args['session_n'];
            }
            $extra = supervisor_ai_norm(implode(' ', $bits));
            if (function_exists('supervisor_ai_session_plan_class')) {
                return supervisor_ai_session_plan_class($pdo, $args['class_query'] ?? '', $args['date'] ?? '', $extra);
            }
            return supervisor_ai_class_overview($pdo, $args['class_query'] ?? '');
        case 'list_students_without_phone':
            if (function_exists('supervisor_ai_students_without_phone')) {
                return supervisor_ai_students_without_phone($pdo);
            }
            return ['ok' => false, 'error' => 'لیست شماره تماس در دسترس نیست.'];
        case 'list_terms':
            return supervisor_ai_list_terms($pdo);
        case 'explain_panel':
            return supervisor_ai_snapshot($pdo);
        default:
            return ['ok' => false, 'error' => 'ابزار ناشناخته.'];
    }
}

function supervisor_ai_http(array $payload, array $cfg) {
    $workspace = trim((string)($cfg['workspace_id'] ?? ''));
    $key = trim((string)($cfg['api_key'] ?? ''));
    $base = rtrim((string)($cfg['base_url'] ?? 'https://ai.liara.ir'), '/');
    if ($workspace === '' || $key === '') {
        throw new Exception('سرویس هوش مصنوعی پیکربندی نشده است.');
    }
    $url = $base . '/api/' . rawurlencode($workspace) . '/v1/chat/completions';
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Accept: application/json',
            'Authorization: Bearer ' . $key,
            'Expect:',
        ],
        CURLOPT_POSTFIELDS => supervisor_ai_json($payload),
        CURLOPT_TIMEOUT => 22,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_NOSIGNAL => true,
    ]);
    $raw = curl_exec($ch);
    $errno = curl_errno($ch);
    $err = trim((string)curl_error($ch));
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($errno) {
        if ($errno === 28) {
            throw new Exception('مدل لیارا الان پاسخ نداد (زمان تمام شد). سؤال‌های پنل مثل کلاس، غایب، رول‌کال، سشن‌پلن و اسم معلم را بدون لیارا می‌پرسم.');
        }
        if (in_array($errno, [6, 7, 35], true)) {
            throw new Exception('اتصال به لیارا برقرار نشد.');
        }
        throw new Exception('مدل لیارا الان در دسترس نیست.');
    }
    $decoded = json_decode((string)$raw, true);
    if ($code === 402) {
        throw new Exception('اعتبار لیارا کافی نیست.');
    }
    if ($code < 200 || $code >= 300) {
        $msg = is_array($decoded) ? (string)($decoded['error']['message'] ?? $decoded['message'] ?? $decoded['error'] ?? '') : '';
        throw new Exception($msg !== '' ? $msg : ('خطای لیارا (' . $code . ')'));
    }
    if (!is_array($decoded)) {
        throw new Exception('پاسخ لیارا نامعتبر بود.');
    }
    return $decoded;
}

function supervisor_ai_system_prompt() {
    if (!function_exists('jalali_pretty')) {
        require_once __DIR__ . '/jalali.php';
    }
    $info = supervisor_ai_date_pack(supervisor_ai_today(), 'امروز');
    $today = $info['jalali'] . ' (' . $info['weekday'] . '، روز ' . $info['day_kind'] . ')';
    return trim("
تو دستیار فقط‌خواندنی سوپروایزر آموزشگاه زبان غزل هستی.
نقشهٔ پنل را می‌شناسی. فقط از ابزارها و دیتابیس جواب بده؛ حدس نزن و تاریخ را خودت تبدیل نکن.

صفحات پنل:
- Dashboard: کلاس امروز (زوج/فرد)
- EAS: کارت کلاس و دانش‌آموزان
- Class Portfolio: کلاس‌های ترم
- Roll Call: حضورغیاب
- report card: کارنامه
- Assignment status: تکلیف
- Session plan: طرح جلسه
- Study Plan: پیامک برنامه مطالعه
- Error Gravity / observation forms / Mind Lingo
- Students Information / Teachers Information: پرونده و اساتید
- Terms & Classes: ترم و کلاس‌بندی

قواعد:
- امروز سیستم: {$today}
- شهریور ۳۱ روز دارد. یک مهر منهای یک روز = ۳۱ شهریور.
- دیروز را date=دیروز بفرست. تاریخ را از date.jalali ابزار بگو.
- زوج = شنبه/دوشنبه/چهارشنبه. فرد = یکشنبه/سه‌شنبه/پنجشنبه.
- اگر پرسیدند فلان معلم چه کلاس‌هایی دارد، همهٔ کلاس‌هایش را بگو (ترم جاری و در صورت وجود ترم‌های دیگر). زوج/فرد را فقط وقتی جدا کن که خودشان گفته باشند.
- «آقا / آقای / Mr» یعنی معلم مرد؛ «خانم / Ms» یعنی معلم زن. اگر با این نشانه فقط یک نفر ماند (مثلاً آقای اخوان = محمد اخوان)، همهٔ کلاس‌های همان نفر را بگو و نپرس کدام اخوان.
- اگر چند معلم هم‌نام‌خانوادگی بود و جنسیت مشخص نبود، لیست همهٔ کلاس‌ها را قاطی نکن؛ اسم‌ها و تعداد کلاس را بگو و بپرس کدام.
- نمرهٔ ترم قبلِ بچه‌های یک کلاس = کارنامهٔ ترم قبلی همان دانش‌آموزان در Students Information. کوییز خالی ترم جاری را نده و لیست ترم‌ها را به‌جای نمره نفرست. Finglish: nomre=نمره، term ghabl=ترم قبل، kelass=کلاس، daneshamoozan=دانش‌آموزان.
- لینک کلاس آنلاین / الوکام = فیلد online_link همان کلاس. لیست شاگرد را به‌جای لینک نده. اگر آنلاین خواستند، کلاس آفلاین هم‌عنوان را قاطی نکن. Finglish: anline/online=آنلاین، link=لینک.
- Study Plan / استادی‌پلن ارسال‌شده = لاگ study_plan_sms_log. اگر گفتند امروز، فقط همان روز را بگو و ساعت را هم بنویس. «از اول مهر تا الان» یعنی همان لاگ در آن بازه، نه لیست کلاس و غیبت دانش‌آموز. «تاریخ دقیق» یعنی همان لیست با ساعت، نه کلاسی به اسم Study Plan.
- رول‌کال نزده ≠ غایب. «کی رول‌کال نزده» = list_missing_rollcall. «غایبین کدام کلاس» = list_today_absences و حتماً بگو چند کلاس رول‌کال زده، چند تا نزده، چند تا هنوز شروع نشده. رول‌کال یک کلاس = get_class_session.
- سشن‌پلن ننوشته = list_missing_session_plans. کلاس بدون سابقهٔ پلن (خصوصی) را با «ننوشتهٔ امروز» قاطی نکن. تکلیف ثبت‌نشدهٔ معلم = list_missing_homework. تکلیف نسپردهٔ شاگرد = list_homework_gaps.
- آبزرو / ارزیابی در EAS = تیک observation_status (چشم ارزیابی روی کارت EAS). list_eas_gaps flag=observation. تاریخ/ساعت/نام مصاحبه‌کننده را در این جواب ننویس؛ آن‌ها مصاحبه‌اند.
- فرم مشاهده / observation forms = صفحهٔ جدا. get_class_feature feature=observation_form.
- مصاحبه = interview_status به‌علاوه interview_date/time/interviewer. list_eas_gaps flag=interview. نظرسنجی = flag=poll.
- Error Gravity / Mind Lingo / کارنامه = get_class_feature.
- کلاس لایو الان = list_live_classes. تعداد کاربر = panel_counts.
- پرونده دانش‌آموز = get_student_overview. دانش‌آموزان بدون شماره تماس در Students Information = list_students_without_phone.
- جلسه اول/دوم سشن‌پلن یک کلاس = get_session_plan با عنوان کلاس و در صورت نیاز فرد/زوج و ساعت. چند کلاس هم‌عنوان را قاطی نکن.
- کلاس‌بندی ترم جدید را با explain_panel و list_terms بگو؛ چیزی ثبت نکن.
- «دیروز چطور» یعنی همان سؤال قبلی برای دیروز. اگر سؤال قبلی نبود، قاطی نکن و بپرس غایب است یا رول‌کال نزده.
- هرگز ننویس «هیچ معلمی نزده است»؛ اگر همه زده‌اند بگو همه زده‌اند، اگر نزده‌اند لیست بده.
- برای یک کلاس: get_class_overview. برای ساختار پنل: explain_panel.
- اگر rollcall_class_count صفر بود بگو رول‌کال ذخیره نشده.
- عدد count ابزار را کم نکن.
نمی‌توانی چیزی ثبت یا پاک کنی. جواب فارسی، کوتاه و دقیق.
");
}

if (is_file(__DIR__ . '/supervisor-ai-coverage.php')) {
    require_once __DIR__ . '/supervisor-ai-coverage.php';
}
if (is_file(__DIR__ . '/supervisor-ai-scenarios.php')) {
    require_once __DIR__ . '/supervisor-ai-scenarios.php';
}

function supervisor_ai_chat(PDO $pdo, array $history) {
    $cfg = supervisor_ai_config();
    $model = trim((string)($cfg['model'] ?? 'openai/gpt-4o-mini'));
    if ($model === '') {
        $model = 'openai/gpt-4o-mini';
    }
    $clean = [];
    foreach ($history as $msg) {
        if (!is_array($msg)) {
            continue;
        }
        $role = (string)($msg['role'] ?? '');
        $content = trim((string)($msg['content'] ?? ''));
        if (($role !== 'user' && $role !== 'assistant') || $content === '') {
            continue;
        }
        if (function_exists('mb_substr')) {
            $content = mb_substr($content, 0, 2000, 'UTF-8');
        } else {
            $content = substr($content, 0, 2000);
        }
        $clean[] = ['role' => $role, 'content' => $content];
        if (count($clean) >= 12) {
            $clean = array_slice($clean, -12);
        }
    }
    if (!$clean || $clean[count($clean) - 1]['role'] !== 'user') {
        throw new Exception('پیام کاربر خالی است.');
    }
    if (!function_exists('rollcall_normalize_status')) {
        require_once __DIR__ . '/rollcall.php';
    }
    supervisor_ai_tz();
    if (is_file(__DIR__ . '/digits.php')) {
        require_once __DIR__ . '/digits.php';
    }

    $last_user = supervisor_ai_last_user_text($clean);
    try {
        $direct = supervisor_ai_try_direct($pdo, $last_user, $clean);
        if (is_string($direct) && $direct !== '') {
            return [
                'reply' => $direct,
                'usage' => ['prompt_tokens' => 0, 'completion_tokens' => 0, 'total_cost_toman' => 0],
            ];
        }
    } catch (Throwable $e) {
        return [
            'reply' => 'خواندن پنل برای این سؤال خطا داد. عنوان کلاس یا اسم معلم را دقیق‌تر بپرس.',
            'usage' => ['prompt_tokens' => 0, 'completion_tokens' => 0, 'total_cost_toman' => 0],
        ];
    }

    $facts = supervisor_ai_prefetch_facts($pdo, $last_user);
    $messages = [
        ['role' => 'system', 'content' => supervisor_ai_system_prompt()],
    ];
    if ($facts) {
        $messages[] = [
            'role' => 'system',
            'content' => 'حقایق از دیتابیس. تاریخ و عدد را از اینجا بخوان، خودت حساب نکن:\n' . supervisor_ai_json($facts),
        ];
    }
    $messages = array_merge($messages, $clean);
    $tools = supervisor_ai_tools();
    $usage = ['prompt_tokens' => 0, 'completion_tokens' => 0, 'total_cost_toman' => 0];
    $reply = '';

    for ($i = 0; $i < 6; $i++) {
        $payload = [
            'model' => $model,
            'messages' => $messages,
            'tools' => $tools,
            'tool_choice' => 'auto',
            'temperature' => 0.1,
            'max_tokens' => 1600,
        ];
        $data = supervisor_ai_http($payload, $cfg);
        $u = $data['usage'] ?? [];
        $usage['prompt_tokens'] += (int)($u['prompt_tokens'] ?? 0);
        $usage['completion_tokens'] += (int)($u['completion_tokens'] ?? 0);
        $usage['total_cost_toman'] += (float)($u['total_cost_toman'] ?? 0);

        $choice = $data['choices'][0]['message'] ?? [];
        $tool_calls = $choice['tool_calls'] ?? [];
        $content = trim((string)($choice['content'] ?? ''));
        $finish = (string)($data['choices'][0]['finish_reason'] ?? '');

        if (!empty($tool_calls) && is_array($tool_calls)) {
            $assistant_msg = [
                'role' => 'assistant',
                'content' => $content !== '' ? $content : null,
                'tool_calls' => $tool_calls,
            ];
            $messages[] = $assistant_msg;
            foreach ($tool_calls as $call) {
                $name = (string)($call['function']['name'] ?? '');
                $raw_args = $call['function']['arguments'] ?? '{}';
                $args = is_array($raw_args) ? $raw_args : json_decode((string)$raw_args, true);
                if (!is_array($args)) {
                    $args = [];
                }
                $result = supervisor_ai_run_tool($pdo, $name, $args, $last_user);
                $messages[] = [
                    'role' => 'tool',
                    'tool_call_id' => (string)($call['id'] ?? ''),
                    'content' => supervisor_ai_json($result),
                ];
            }
            continue;
        }

        $reply = $content;
        if ($reply === '' && $finish !== 'tool_calls') {
            $reply = 'جوابی از مدل نیامد.';
        }
        break;
    }

    if ($reply === '') {
        $reply = 'نتوانستم این پرسش را کامل جواب بدهم. لطفاً شماره پرونده یا نام کلاس را دقیق‌تر بگو.';
    }

    return [
        'reply' => $reply,
        'usage' => $usage,
    ];
}
