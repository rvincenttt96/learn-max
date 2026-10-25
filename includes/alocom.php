<?php
/**
 * AloCom API client + online-class attendance matching.
 */

require_once __DIR__ . '/rollcall.php';
require_once __DIR__ . '/terms.php';

function alocom_log($message) {
    $line = date('[Y-m-d H:i:s] ') . $message . "\n";
    @error_log($line, 3, __DIR__ . '/../dashboard/supervisor/cron_alocom_log.txt');
}

function alocom_token() {
    $config = rollcall_config();
    $alocom = $config['alocom'] ?? [];
    $cache = sys_get_temp_dir() . '/ghazalify_alocom_token.json';
    if (is_file($cache)) {
        $cached = json_decode(@file_get_contents($cache), true);
        if (!empty($cached['token']) && !empty($cached['exp']) && time() < ((int)$cached['exp'] - 120)) {
            return $cached['token'];
        }
    }

    $body = json_encode([
        'username' => $alocom['username'] ?? '',
        'password' => $alocom['password'] ?? '',
    ]);
    $ch = curl_init(($alocom['base'] ?? 'https://pnlapi.alocom.co') . '/api/v1/auth/login');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_HTTPHEADER => [
            'Accept: application/json',
            'Content-Type: application/json',
        ],
    ]);
    $raw = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);
    if ($raw === false) {
        if (function_exists('ghazal_error_report')) {
            ghazal_error_report('ALO-LOGIN', 'ورود API الوکام برقرار نشد.', ghazal_error_trim($err));
        }
        throw new Exception('AloCom login failed: ' . $err);
    }
    $data = json_decode($raw, true);
    if (empty($data['token'])) {
        if (function_exists('ghazal_error_report')) {
            ghazal_error_report('ALO-LOGIN', 'ورود API الوکام رد شد.');
        }
        throw new Exception('AloCom login rejected');
    }
    $ttl = (int)($data['expires_in'] ?? 28800);
    if ($ttl < 60) {
        $ttl = 28800;
    }
    @file_put_contents($cache, json_encode([
        'token' => $data['token'],
        'exp' => time() + $ttl,
    ]));
    return $data['token'];
}

function alocom_request($method, $path, $body = null) {
    $config = rollcall_config();
    $base = rtrim($config['alocom']['base'] ?? 'https://pnlapi.alocom.co', '/');
    $ch = curl_init($base . $path);
    $headers = [
        'Accept: application/json',
        'Authorization: Bearer ' . alocom_token(),
    ];
    if ($body !== null) {
        $headers[] = 'Content-Type: application/json';
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_UNESCAPED_UNICODE));
    }
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => strtoupper($method),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 25,
        CURLOPT_HTTPHEADER => $headers,
    ]);
    $raw = curl_exec($ch);
    $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    if ($raw === false) {
        if (function_exists('ghazal_error_report')) {
            ghazal_error_report(
                ghazal_error_alocom_code($path, $err),
                'درخواست الوکام ' . $path . ' ناموفق بود.',
                ghazal_error_trim($err)
            );
        }
        throw new Exception('AloCom request failed: ' . $err);
    }
    $data = json_decode($raw, true);
    if ($http >= 400) {
        $msg = 'HTTP ' . $http;
        if (is_array($data)) {
            if (!empty($data['message'])) {
                $msg = (string)$data['message'];
            }
            if (!empty($data['errors']) && is_array($data['errors'])) {
                $bits = [];
                foreach ($data['errors'] as $key => $val) {
                    $bits[] = $key . ': ' . (is_array($val) ? implode(', ', $val) : (string)$val);
                }
                if ($bits) {
                    $msg .= ' (' . implode('; ', $bits) . ')';
                }
            }
        }
        if (function_exists('ghazal_error_report')) {
            ghazal_error_report(
                ghazal_error_alocom_code($path, $msg),
                'الوکام ' . $path . ' خطا داد.',
                ghazal_error_trim($msg)
            );
        }
        throw new Exception('AloCom ' . $path . ': ' . $msg);
    }
    if (!is_array($data)) {
        if (strtoupper($method) === 'DELETE' && $http >= 200 && $http < 300) {
            return ['ok' => true, 'http' => $http];
        }
        if (function_exists('ghazal_error_report')) {
            ghazal_error_report(
                ghazal_error_alocom_code($path, 'bad json'),
                'پاسخ الوکام ' . $path . ' نامعتبر بود.',
                'HTTP ' . $http
            );
        }
        throw new Exception('AloCom ' . $path . ': HTTP ' . $http . ' پاسخ نامعتبر');
    }
    return $data;
}

function alocom_normalize_link($url) {
    $url = trim((string)$url);
    if ($url === '') {
        return '';
    }
    $parts = parse_url($url);
    $host = strtolower($parts['host'] ?? '');
    $path = rtrim(strtolower($parts['path'] ?? ''), '/');
    if ($host === '' && $path === '') {
        return strtolower(rtrim(preg_replace('/[?#].*$/', '', $url), '/'));
    }
    return $host . $path;
}

function alocom_slug_from_link($url) {
    $path = parse_url(trim((string)$url), PHP_URL_PATH);
    if (!$path) {
        return '';
    }
    $path = trim($path, '/');
    $parts = explode('/', $path);
    return strtolower(end($parts) ?: '');
}

function alocom_decode_math_letters($name) {
    $out = '';
    $len = mb_strlen($name, 'UTF-8');
    for ($i = 0; $i < $len; $i++) {
        $ch = mb_substr($name, $i, 1, 'UTF-8');
        $cp = function_exists('mb_ord') ? mb_ord($ch, 'UTF-8') : null;
        $ascii = null;
        if ($cp) {
            $ranges = [
                [0x1D400, 0x1D419, 65],
                [0x1D41A, 0x1D433, 97],
                [0x1D434, 0x1D44D, 65],
                [0x1D44E, 0x1D467, 97],
                [0x1D468, 0x1D481, 65],
                [0x1D482, 0x1D49B, 97],
                [0x1D4D0, 0x1D4E9, 65],
                [0x1D4EA, 0x1D503, 97],
                [0x1D5D4, 0x1D5ED, 65],
                [0x1D5EE, 0x1D607, 97],
                [0x1D608, 0x1D621, 65],
                [0x1D622, 0x1D63B, 97],
                [0x1D63C, 0x1D655, 65],
                [0x1D656, 0x1D66F, 97],
            ];
            foreach ($ranges as $r) {
                if ($cp >= $r[0] && $cp <= $r[1]) {
                    $ascii = chr($r[2] + ($cp - $r[0]));
                    break;
                }
            }
        }
        $out .= $ascii !== null ? $ascii : $ch;
    }
    return $out;
}

function alocom_romanize_fa($name) {
    $map = [
        'آ' => 'a', 'ا' => 'a', 'أ' => 'a', 'إ' => 'e', 'ب' => 'b', 'پ' => 'p', 'ت' => 't', 'ث' => 's',
        'ج' => 'j', 'چ' => 'ch', 'ح' => 'h', 'خ' => 'kh', 'د' => 'd', 'ذ' => 'z', 'ر' => 'r', 'ز' => 'z',
        'ژ' => 'zh', 'س' => 's', 'ش' => 'sh', 'ص' => 's', 'ض' => 'z', 'ط' => 't', 'ظ' => 'z', 'ع' => 'a',
        'غ' => 'gh', 'ف' => 'f', 'ق' => 'gh', 'ک' => 'k', 'ك' => 'k', 'گ' => 'g', 'ل' => 'l', 'م' => 'm',
        'ن' => 'n', 'و' => 'o', 'ه' => 'h', 'ی' => 'i', 'ي' => 'i', 'ئ' => 'i', 'ء' => '', 'ۀ' => 'h',
        'ة' => 'h',
    ];
    return strtr($name, $map);
}

function alocom_normalize_name($name) {
    $name = trim((string)$name);
    if ($name === '') {
        return '';
    }
    $name = alocom_decode_math_letters($name);
    $name = alocom_romanize_fa($name);
    if (class_exists('Normalizer')) {
        $name = Normalizer::normalize($name, Normalizer::FORM_KC);
    }
    if (function_exists('transliterator_transliterate')) {
        $trans = @transliterator_transliterate('Any-Latin; Latin-ASCII', $name);
        if (is_string($trans) && $trans !== '') {
            $name = $trans;
        }
    }
    $name = mb_strtolower($name, 'UTF-8');
    $name = preg_replace('/\p{M}+/u', '', $name);
    $name = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $name);
    $name = preg_replace('/\s+/u', ' ', $name);
    $name = trim($name);
    $name = preg_replace('/^(ms|mr|mrs|miss|خانم|آقای|اقای)\s+/u', '', $name);
    return trim($name);
}

function alocom_name_tokens($name) {
    $norm = alocom_normalize_name($name);
    if ($norm === '') {
        return [];
    }
    $parts = preg_split('/\s+/', $norm);
    $out = [];
    foreach ($parts as $p) {
        if (mb_strlen($p, 'UTF-8') >= 3) {
            $out[] = $p;
        }
    }
    return $out;
}

function alocom_compact_name($name) {
    return str_replace(' ', '', alocom_normalize_name($name));
}

function alocom_token_counts(array $people, $field = 'full_name') {
    $counts = [];
    foreach ($people as $p) {
        $name = is_array($p) ? ($p[$field] ?? $p['full'] ?? '') : $p;
        foreach (array_unique(alocom_name_tokens($name)) as $t) {
            $counts[$t] = ($counts[$t] ?? 0) + 1;
        }
    }
    return $counts;
}

function alocom_names_similar($a, $b) {
    $na = alocom_normalize_name($a);
    $nb = alocom_normalize_name($b);
    if ($na === '' || $nb === '') {
        return false;
    }
    if ($na === $nb) {
        return true;
    }
    $ca = str_replace(' ', '', $na);
    $cb = str_replace(' ', '', $nb);
    if ($ca === $cb) {
        return true;
    }
    similar_text($ca, $cb, $pct);
    if ($pct >= 82) {
        return true;
    }
    $max = max(strlen($ca), strlen($cb));
    if ($max > 0 && $max <= 24 && levenshtein($ca, $cb) <= 2) {
        return true;
    }
    if (strlen($ca) >= 4 && strlen($cb) >= 4) {
        if (strpos($ca, $cb) !== false || strpos($cb, $ca) !== false) {
            return true;
        }
    }
    return false;
}

function alocom_list_events() {
    $skip = 0;
    $all = [];
    do {
        $res = alocom_request('POST', '/api/v1/agents/events/index', [
            'take' => 50,
            'skip' => $skip,
        ]);
        $rows = $res['data'] ?? [];
        if (!is_array($rows)) {
            break;
        }
        $all = array_merge($all, $rows);
        $count = count($rows);
        $skip += 50;
    } while ($count === 50 && $skip < 400);
    return $all;
}

function alocom_event_detail($event_id) {
    return alocom_request('GET', '/api/v1/agents/events/' . (int)$event_id);
}

function alocom_traffics($from, $to, $event_name = '') {
    $skip = 0;
    $all = [];
    do {
        $body = [
            'take' => 50,
            'skip' => $skip,
            'from' => $from,
            'to' => $to,
        ];
        if ($event_name !== '') {
            $body['eventName'] = $event_name;
        }
        $res = alocom_request('POST', '/api/v1/agents/logs/traffics', $body);
        $rows = $res['data'] ?? [];
        if (!is_array($rows)) {
            break;
        }
        $all = array_merge($all, $rows);
        $total = (int)($res['meta']['total_count'] ?? count($all));
        $skip += 50;
    } while (count($all) < $total && $skip < 500);
    return $all;
}

function alocom_class_is_online_room(array $class) {
    $type = strtolower(trim((string)($class['class_type'] ?? '')));
    if ($type === 'online') {
        return true;
    }
    if ($type === 'private' && trim((string)($class['online_link'] ?? '')) !== '') {
        return true;
    }
    return false;
}

function alocom_parse_schedule($schedule) {
    $schedule = trim((string)$schedule);
    if ($schedule === '' || $schedule === 'تنظیم نشده' || strcasecmp($schedule, 'Not Set') === 0) {
        return null;
    }
    if (function_exists('convertNumbers')) {
        $schedule = convertNumbers($schedule);
    } else {
        if (!function_exists('ghazal_normalize_digits')) {
            require_once __DIR__ . '/digits.php';
        }
        $schedule = ghazal_normalize_digits($schedule);
    }
    $schedule = str_replace(['.', '–', '—'], [':', '-', '-'], $schedule);
    $parts = preg_split('/\s*-\s*/', $schedule);
    $start = trim($parts[0]);
    $end = isset($parts[1]) ? trim($parts[1]) : '';
    if (preg_match('/^\d{1,2}$/', $start)) {
        $start .= ':00';
    }
    if ($end !== '' && preg_match('/^\d{1,2}$/', $end)) {
        $end .= ':00';
    }
    $today = date('Y-m-d');
    $start_ts = strtotime($today . ' ' . $start);
    if (!$start_ts) {
        return null;
    }
    $end_ts = $end !== '' ? strtotime($today . ' ' . $end) : ($start_ts + 90 * 60);
    if (!$end_ts || $end_ts <= $start_ts) {
        $end_ts = $start_ts + 90 * 60;
    }
    return [$start_ts, $end_ts];
}

function alocom_match_event($class, array $events) {
    $want_link = alocom_normalize_link($class['online_link'] ?? '');
    $want_slug = alocom_slug_from_link($class['online_link'] ?? '');
    $want_title = alocom_normalize_name($class['title'] ?? '');

    foreach ($events as $event) {
        $got_link = alocom_normalize_link($event['alocom_link'] ?? '');
        if ($want_link !== '' && $got_link !== '' && $want_link === $got_link) {
            return $event;
        }
        $got_slug = alocom_slug_from_link($event['alocom_link'] ?? '');
        if ($want_slug !== '' && $got_slug !== '' && $want_slug === $got_slug) {
            return $event;
        }
    }
    foreach ($events as $event) {
        $got_title = alocom_normalize_name($event['title'] ?? '');
        if ($want_title !== '' && $got_title !== '' && ($want_title === $got_title || alocom_names_similar($want_title, $got_title))) {
            return $event;
        }
    }
    return null;
}

function alocom_find_event_for_class(array $class, array $events = null) {
    if (is_array($events)) {
        return alocom_match_event($class, $events);
    }
    $skip = 0;
    do {
        $res = alocom_request('POST', '/api/v1/agents/events/index', [
            'take' => 50,
            'skip' => $skip,
        ]);
        $rows = $res['data'] ?? [];
        if (!is_array($rows) || !$rows) {
            break;
        }
        $hit = alocom_match_event($class, $rows);
        if ($hit) {
            return $hit;
        }
        $count = count($rows);
        $skip += 50;
    } while ($count === 50 && $skip < 400);
    return null;
}

function alocom_first_service_id($res) {
    return alocom_best_service_id_from_detail($res);
}

function alocom_best_service_id_from_detail($detail) {
    if (!is_array($detail)) {
        return 0;
    }
    $active = [];
    if (!empty($detail['activeServices']) && is_array($detail['activeServices'])) {
        $active = $detail['activeServices'];
    }
    $node = $detail['event'] ?? $detail['data'] ?? null;
    if (is_array($node) && !empty($node['activeServices']) && is_array($node['activeServices'])) {
        $active = array_merge($active, $node['activeServices']);
    }
    $best = 0;
    $best_cap = -1;
    foreach ($active as $row) {
        if (!is_array($row)) {
            continue;
        }
        $id = (int)($row['agent_service_id'] ?? $row['id_service_agent'] ?? 0);
        if ($id <= 0) {
            continue;
        }
        $cap = (int)($row['max_online_user'] ?? $row['max_event_user'] ?? 0);
        if ($cap > $best_cap) {
            $best = $id;
            $best_cap = $cap;
        }
    }
    if ($best > 0) {
        return $best;
    }
    if (!is_array($node)) {
        $node = $detail;
    }
    return (int)($node['agent_service_id'] ?? $node['id_service_agent'] ?? 0);
}

function alocom_pick_service_id() {
    static $cached = 0;
    if ($cached > 0) {
        return $cached;
    }
    $config = rollcall_config();
    $from_cfg = (int)($config['alocom']['agent_service_id'] ?? 0);
    if ($from_cfg > 1000) {
        return $cached = $from_cfg;
    }
    try {
        foreach (alocom_list_events() as $ev) {
            $eid = (int)($ev['id'] ?? 0);
            if ($eid <= 0) {
                continue;
            }
            try {
                $detail = alocom_event_detail($eid);
            } catch (Exception $e) {
                alocom_log('pick service detail ' . $eid . ': ' . $e->getMessage());
                continue;
            }
            $id = alocom_best_service_id_from_detail($detail);
            if ($id > 0) {
                alocom_log('picked agent_service_id=' . $id . ' from event ' . $eid);
                return $cached = $id;
            }
        }
    } catch (Exception $e) {
        alocom_log('pick service list: ' . $e->getMessage());
    }
    throw new Exception('سرویس الوکام برای ساخت اتاق پیدا نشد.');
}

function alocom_event_slug(array $class) {
    $title = strtolower(trim((string)($class['title'] ?? 'class')));
    $title = preg_replace('/[^a-z0-9]+/', '-', $title);
    $title = trim($title, '-');
    if ($title === '') {
        $title = 'class';
    }
    $id = (int)($class['id'] ?? 0);
    $kind = strtolower(trim((string)($class['class_type'] ?? '')));
    $private = $kind === 'private';
    $inperson = function_exists('alocom_is_inperson_class')
        ? alocom_is_inperson_class($class)
        : in_array($kind, ['offline', 'inperson', 'in-person', 'حضوری'], true);
    $slug = 'gz-' . $id . '-' . ($private ? 'pv-' : ($inperson ? 'ip-' : '')) . $title;
    if (strlen($slug) > 48) {
        $slug = substr($slug, 0, 48);
        $slug = rtrim($slug, '-');
    }
    return $slug;
}

function alocom_event_title(array $class, $attempt = 0) {
    $title = trim((string)($class['title'] ?? ''));
    $id = (int)($class['id'] ?? 0);
    if ($title === '') {
        $title = $id > 0 ? ('Class ' . $id) : 'Class';
    }
    if ($id > 0 && !preg_match('/(?:^|[^\d])' . preg_quote((string)$id, '/') . '(?:[^\d]|$)/', $title)) {
        $title .= ' · ' . $id;
    }
    $kind = strtolower(trim((string)($class['class_type'] ?? '')));
    if ($kind === 'private' && !preg_match('/\bPV\b|خصوصی/u', $title)) {
        $title .= ' · PV';
    }
    $inperson = function_exists('alocom_is_inperson_class')
        ? alocom_is_inperson_class($class)
        : in_array($kind, ['offline', 'inperson', 'in-person', 'حضوری'], true);
    if ($inperson && !preg_match('/حضوری|in[- ]?person|\bIP\b/iu', $title)) {
        $title .= ' · حضوری';
    }
    if ((int)$attempt > 0) {
        $title .= '-' . substr(md5((string)microtime(true) . $attempt), 0, 4);
    }
    if (function_exists('mb_strlen') && mb_strlen($title, 'UTF-8') > 80) {
        $title = mb_substr($title, 0, 80, 'UTF-8');
    } elseif (strlen($title) > 80) {
        $title = substr($title, 0, 80);
    }
    return $title;
}

function alocom_event_link_from($res) {
    if (!is_array($res)) {
        return '';
    }
    foreach (['alocom_link', 'link_alocom', 'eventLink', 'link'] as $key) {
        if (!empty($res[$key]) && is_string($res[$key]) && stripos($res[$key], 'http') === 0) {
            return trim($res[$key]);
        }
    }
    foreach (['data', 'event'] as $wrap) {
        if (!empty($res[$wrap]) && is_array($res[$wrap])) {
            $inner = alocom_event_link_from($res[$wrap]);
            if ($inner !== '') {
                return $inner;
            }
        }
    }
    return '';
}

function alocom_event_href(array $event) {
    $link = alocom_event_link_from($event);
    if ($link !== '') {
        return $link;
    }
    foreach (['alocom_link', 'link_alocom', 'eventLink', 'link'] as $key) {
        $v = trim((string)($event[$key] ?? ''));
        if ($v !== '') {
            return $v;
        }
    }
    return '';
}

function alocom_link_prefix_from_events(array $events) {
    foreach ($events as $ev) {
        if (!is_array($ev)) {
            continue;
        }
        $href = alocom_event_href($ev);
        if ($href !== '' && preg_match('#^(https?://[^/]+/class/[^/]+/)#i', $href, $m)) {
            return $m[1];
        }
    }
    return '';
}

function alocom_public_link_for_slug($slug, array $events = []) {
    $slug = trim((string)$slug, "/ \t\n\r");
    if ($slug === '') {
        return '';
    }
    $prefix = alocom_link_prefix_from_events($events);
    if ($prefix !== '') {
        return $prefix . $slug;
    }
    return '';
}

function alocom_event_id_from($res) {
    if (!is_array($res)) {
        return 0;
    }
    foreach (['id', 'event_id'] as $key) {
        if (!empty($res[$key]) && is_numeric($res[$key])) {
            return (int)$res[$key];
        }
    }
    foreach (['data', 'event'] as $wrap) {
        if (!empty($res[$wrap]) && is_array($res[$wrap])) {
            $id = alocom_event_id_from($res[$wrap]);
            if ($id > 0) {
                return $id;
            }
        }
    }
    return 0;
}

function alocom_stamp_event_link(array $event) {
    $href = alocom_event_href($event);
    if ($href !== '') {
        $event['alocom_link'] = $href;
    }
    return $event;
}

function alocom_match_event_by_link($class, array $events) {
    $want_link = alocom_normalize_link($class['online_link'] ?? '');
    $want_slug = alocom_slug_from_link($class['online_link'] ?? '');
    $class_id = (int)($class['id'] ?? 0);
    $prefix = $class_id > 0 ? ('gz-' . $class_id . '-') : '';
    foreach ($events as $event) {
        if (!is_array($event)) {
            continue;
        }
        $href = alocom_event_href($event);
        $got_link = alocom_normalize_link($href);
        if ($want_link !== '' && $got_link !== '' && $want_link === $got_link) {
            return alocom_stamp_event_link($event);
        }
        $got_slug = alocom_slug_from_link($href);
        if ($want_slug !== '' && $got_slug !== '' && $want_slug === $got_slug) {
            return alocom_stamp_event_link($event);
        }
        $slug = strtolower((string)($event['slug'] ?? ''));
        if ($prefix !== '' && $slug !== '' && strpos($slug, $prefix) === 0) {
            return alocom_stamp_event_link($event);
        }
        $link = strtolower($href);
        if ($prefix !== '' && $link !== '' && strpos($link, $prefix) !== false) {
            return alocom_stamp_event_link($event);
        }
    }
    return null;
}

function alocom_create_event($title, $slug, $service_id, array $users = []) {
    $payload = [
        'title' => $title,
        'slug' => $slug,
        'agent_service_id' => (int)$service_id,
        'id_service_agent' => (int)$service_id,
        'status' => 1,
        'admin_by_start' => 0,
        'start_by_admin' => 0,
        'access_guest' => 0,
        'guest_access' => 0,
        'count_guest' => 0,
        'guest_count' => 0,
    ];
    if ($users) {
        $payload['users'] = $users;
    }
    $last = null;
    foreach (['/api/v1/agents/events', '/api/v1/agents/events/'] as $path) {
        try {
            $res = alocom_request('POST', $path, $payload);
            if (is_array($res)) {
                return $res;
            }
        } catch (Exception $e) {
            $last = $e;
            alocom_log('create event ' . $path . ': ' . $e->getMessage());
        }
    }
    throw ($last ?: new Exception('ساخت رویداد الوکام ناموفق بود.'));
}

function alocom_delete_event($event_id) {
    $event_id = (int)$event_id;
    if ($event_id <= 0) {
        throw new Exception('شناسه رویداد نامعتبر است.');
    }
    $last = null;
    foreach ([
        '/api/v1/agents/events/' . $event_id,
        '/api/v1/agents/events/' . $event_id . '/',
    ] as $path) {
        try {
            return alocom_request('DELETE', $path);
        } catch (Exception $e) {
            $last = $e;
            alocom_log('delete event ' . $path . ': ' . $e->getMessage());
        }
    }
    throw ($last ?: new Exception('حذف رویداد الوکام ناموفق بود.'));
}

function alocom_person_full($row) {
    $first = trim((string)($row['firstName'] ?? $row['name'] ?? ''));
    $last = trim((string)($row['lastName'] ?? $row['surname'] ?? ''));
    return trim($first . ' ' . $last);
}

function alocom_is_staff_role($role) {
    $role = strtolower(trim((string)$role));
    return in_array($role, ['admin', 'teacher', 'moderator', 'owner'], true);
}

function alocom_collect_present(array $traffics, array $enrolled, $window_start, $window_end) {
    $present = [];
    $unmatched_joiners = [];
    $staff_seen = false;

    foreach ($traffics as $row) {
        $login = $row['login_time'] ?? $row['login_date'] ?? '';
        $ts = $login ? strtotime($login) : false;
        if ($ts && ($ts < ($window_start - 15 * 60) || $ts > ($window_end + 20 * 60))) {
            continue;
        }
        $role = strtolower((string)($row['role'] ?? ''));
        if (alocom_is_staff_role($role)) {
            $staff_seen = true;
            continue;
        }
        $full = alocom_person_full($row);
        $username = strtolower(trim((string)($row['username'] ?? '')));
        $key = $username !== '' ? 'u:' . $username : 'n:' . alocom_normalize_name($full);
        $present[$key] = [
            'username' => $username,
            'full' => $full,
            'norm' => alocom_normalize_name($full),
            'phone' => rollcall_normalize_phone($row['phone'] ?? ''),
        ];
    }

    $enrolled_participants = [];
    foreach ($enrolled as $u) {
        if (alocom_is_staff_role($u['role'] ?? '')) {
            continue;
        }
        $full = trim((string)($u['name'] ?? '') . ' ' . (string)($u['surname'] ?? ''));
        $enrolled_participants[] = [
            'userid' => (string)($u['userid'] ?? $u['id'] ?? ''),
            'username' => strtolower(trim((string)($u['username'] ?? ''))),
            'full' => $full,
            'norm' => alocom_normalize_name($full),
        ];
    }

    $enrolled_first = [];
    foreach ($enrolled_participants as $i => $e) {
        $tokens = alocom_name_tokens($e['full']);
        $first = $tokens[0] ?? '';
        if ($first !== '') {
            $enrolled_first[$first][] = $i;
        }
    }

    foreach ($present as &$p) {
        $attached = false;
        foreach ($enrolled_participants as $e) {
            if ($p['username'] !== '' && $e['username'] !== '' && $p['username'] === $e['username']) {
                $p['userid'] = $e['userid'];
                if ($p['norm'] === '') {
                    $p['full'] = $e['full'];
                    $p['norm'] = $e['norm'];
                }
                $attached = true;
                break;
            }
            if ($e['norm'] !== '' && alocom_names_similar($p['full'], $e['full'])) {
                $p['userid'] = $e['userid'];
                $p['enrolled_username'] = $e['username'];
                $p['full'] = $e['full'];
                $p['norm'] = $e['norm'];
                $attached = true;
                break;
            }
        }
        if ($attached) {
            continue;
        }
        $ptokens = alocom_name_tokens($p['full']);
        $pfirst = $ptokens[0] ?? '';
        $pcompact = alocom_compact_name($p['full']);
        if ($pfirst !== '' && count($enrolled_first[$pfirst] ?? []) === 1) {
            $e = $enrolled_participants[$enrolled_first[$pfirst][0]];
            $p['userid'] = $e['userid'];
            $p['enrolled_username'] = $e['username'];
            $p['full'] = $e['full'];
            $p['norm'] = $e['norm'];
            continue;
        }
        foreach ($enrolled_participants as $e) {
            $ecompact = alocom_compact_name($e['full']);
            foreach (alocom_name_tokens($e['full']) as $tok) {
                if (mb_strlen($tok, 'UTF-8') >= 4 && $pcompact !== '' && strpos($pcompact, $tok) !== false) {
                    $p['userid'] = $e['userid'];
                    $p['enrolled_username'] = $e['username'];
                    $p['full'] = $e['full'];
                    $p['norm'] = $e['norm'];
                    break 2;
                }
            }
            if ($ecompact !== '' && $pcompact !== '' && (strpos($pcompact, $ecompact) !== false || strpos($ecompact, $pcompact) !== false)) {
                $p['userid'] = $e['userid'];
                $p['enrolled_username'] = $e['username'];
                $p['full'] = $e['full'];
                $p['norm'] = $e['norm'];
                break;
            }
        }
    }
    unset($p);

    return [
        'present' => array_values($present),
        'enrolled' => $enrolled_participants,
        'staff_seen' => $staff_seen,
        'unmatched_joiners' => $unmatched_joiners,
    ];
}

function alocom_student_is_present(array $student, array $present_people, array $all_students = []) {
    $uname = strtolower(trim((string)($student['username'] ?? '')));
    $full = (string)($student['full_name'] ?? '');
    $phone = rollcall_normalize_phone($student['phone'] ?? '');
    $token_counts = $all_students ? alocom_token_counts($all_students, 'full_name') : [];

    foreach ($present_people as $p) {
        if ($uname !== '' && $p['username'] !== '' && $uname === $p['username']) {
            return true;
        }
        if ($uname !== '' && !empty($p['userid']) && $uname === strtolower((string)$p['userid'])) {
            return true;
        }
        if ($uname !== '' && !empty($p['enrolled_username']) && $uname === $p['enrolled_username']) {
            return true;
        }
        if ($phone !== '' && !empty($p['phone']) && $phone === $p['phone']) {
            return true;
        }
        if ($full !== '' && alocom_names_similar($full, $p['full'] ?? '')) {
            return true;
        }
        $pcompact = alocom_compact_name($p['full'] ?? '');
        $pfirst = (alocom_name_tokens($p['full'] ?? '')[0] ?? '');
        $sfirst = (alocom_name_tokens($full)[0] ?? '');
        if ($pfirst !== '' && $sfirst !== '' && ($token_counts[$sfirst] ?? 1) === 1) {
            if ($pfirst === $sfirst || levenshtein($pfirst, $sfirst) <= 1) {
                return true;
            }
        }
        foreach (alocom_name_tokens($full) as $tok) {
            $unique = ($token_counts[$tok] ?? 1) === 1;
            if (!$unique && mb_strlen($tok, 'UTF-8') < 5) {
                continue;
            }
            if ($pcompact !== '' && strpos($pcompact, $tok) !== false) {
                return true;
            }
        }
    }
    return false;
}

function alocom_unmatched_joiners(array $present_people, array $students) {
    $out = [];
    foreach ($present_people as $p) {
        $matched = false;
        foreach ($students as $s) {
            if (alocom_student_is_present($s, [$p], $students)) {
                $matched = true;
                break;
            }
        }
        if (!$matched) {
            $label = $p['full'] !== '' ? $p['full'] : ($p['username'] ?? '');
            if ($label !== '') {
                $out[] = $label;
            }
        }
    }
    return array_values(array_unique($out));
}

/**
 * Auto-save roll call for due online classes.
 * @return array results
 */
function alocom_sync_due_classes(PDO $pdo, $now = null, $force_class_id = null, $dry_run = false) {
    $lock_path = sys_get_temp_dir() . '/ghazalify_alocom_rollcall.lock';
    $lock = @fopen($lock_path, 'c');
    if ($lock && !$dry_run && !flock($lock, LOCK_EX | LOCK_NB)) {
        return ['skipped' => 'locked'];
    }

    try {
    $now = $now ?: time();
    $today = date('Y-m-d', $now);
    $today_eng_weekday = (int)date('w', $now);
    if ($today_eng_weekday === 5 && $force_class_id === null) {
        return ['skipped' => 'friday'];
    }
    $today_type = in_array($today_eng_weekday, [6, 1, 3], true) ? 'even' : 'odd';

    $sql = "
        SELECT c.id, c.title, c.schedule, c.schedule_type, c.class_type, c.online_link,
               u.full_name AS teacher_name
        FROM classes c
        JOIN terms t ON c.term_id = t.id
        LEFT JOIN users u ON c.teacher_id = u.id
        WHERE c.is_deleted = 0 AND IFNULL(c.is_archived, 0) = 0 AND " . term_sql_teaching('t') . "
          AND (
            c.class_type = 'online'
            OR (c.class_type = 'private' AND IFNULL(c.online_link, '') <> '')
          )
    ";
    $params = [];
    if ($force_class_id) {
        $sql .= " AND c.id = ?";
        $params[] = (int)$force_class_id;
    } else {
        $sql .= " AND c.schedule_type = ?";
        $params[] = $today_type;
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $classes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    alocom_log('due scan type=' . $today_type . ' n=' . count($classes) . ' now=' . date('H:i:s', $now));
    if (!$classes) {
        return ['synced' => []];
    }

    $events = alocom_list_events();
    $jalali = jdate('Y/m/d', $now);
    $results = [];

    foreach ($classes as $class) {
        if (!$force_class_id && !alocom_class_is_online_room($class)) {
            continue;
        }
        $times = alocom_parse_schedule($class['schedule']);
        if (!$times) {
            continue;
        }
        list($start_ts, $end_ts) = $times;
        // Start at T+30 so late joiners are visible. Keep retrying until the
        // session ends; a one-shot 10-minute window missed open classes.
        $window_open = $start_ts + 30 * 60;
        $window_close = $end_ts > $window_open ? $end_ts : ($start_ts + 90 * 60);
        if ($force_class_id === null && ($now < $window_open || $now >= $window_close)) {
            continue;
        }
        alocom_log('due window class=' . $class['id'] . ' ' . $class['title'] . ' start=' . date('H:i', $start_ts) . ' until=' . date('H:i', $window_close));

        $att_counts = $pdo->prepare("
            SELECT COUNT(*) AS total_n,
                   SUM(CASE WHEN status = 'present' OR is_present = 1 THEN 1 ELSE 0 END) AS present_n
            FROM attendance
            WHERE class_id = ? AND class_date = ?
        ");
        $att_counts->execute([$class['id'], $today]);
        $att_row = $att_counts->fetch(PDO::FETCH_ASSOC) ?: [];
        $had_rows = ((int)($att_row['total_n'] ?? 0) > 0);
        $had_present = ((int)($att_row['present_n'] ?? 0) > 0);
        $session_over = ($now >= $window_close);

        $claimed = false;
        if ($force_class_id === null && !$dry_run) {
            // A staff-only / all-absent save must not freeze an open class.
            if (rollcall_alocom_attempt_complete($pdo, $class['id'], $today) && ($had_present || $session_over)) {
                $results[] = ['class_id' => (int)$class['id'], 'title' => $class['title'], 'status' => 'already_attempted'];
                continue;
            }
            $claimed = true;
        }

        try {

        if ($had_rows && ($had_present || $session_over) && !$dry_run) {
            if ($claimed) {
                rollcall_mark_alocom_attempt($pdo, $class['id'], $today, 'already_saved');
            }
            alocom_log('already saved class=' . $class['id'] . ' ' . $class['title'] . ' present=' . (int)($att_row['present_n'] ?? 0));
            $results[] = ['class_id' => (int)$class['id'], 'title' => $class['title'], 'status' => 'already_saved'];
            continue;
        }
        if ($had_rows && $dry_run) {
            // still compute matching below
        }

        $event = alocom_match_event($class, $events);
        if (!$event) {
            alocom_log('No AloCom event for class ' . $class['id'] . ' ' . $class['title']);
            if ($claimed) {
                rollcall_mark_alocom_attempt($pdo, $class['id'], $today, 'no_event');
            }
            $results[] = ['class_id' => (int)$class['id'], 'title' => $class['title'], 'status' => 'no_event'];
            continue;
        }

        $detail = alocom_event_detail($event['id']);
        $enrolled = $detail['enrolledUsers'] ?? [];
        $event_title = (string)($event['title'] ?? '');
        $traffics = alocom_traffics($today, $today, $event_title);
        if (!$traffics && $event_title !== '') {
            $all = alocom_traffics($today, $today, '');
            $want = alocom_normalize_name($event_title);
            $want_id = (string)($event['id'] ?? '');
            $matched = [];
            foreach ($all as $row) {
                $got_title = alocom_normalize_name((string)($row['event_name'] ?? $row['eventName'] ?? $row['title'] ?? ''));
                $got_id = (string)($row['event_id'] ?? $row['eventId'] ?? $row['id_event'] ?? '');
                if (($want !== '' && $got_title !== '' && ($got_title === $want || alocom_names_similar($event_title, (string)($row['event_name'] ?? $row['eventName'] ?? $row['title'] ?? ''))))
                    || ($want_id !== '' && $got_id !== '' && $got_id === $want_id)
                ) {
                    $matched[] = $row;
                }
            }
            alocom_log('traffics fallback class=' . $class['id'] . ' named=0 all=' . count($all) . ' matched=' . count($matched) . ' event=' . $event_title);
            $traffics = $matched;
        }
        $collected = alocom_collect_present($traffics, is_array($enrolled) ? $enrolled : [], $start_ts, $end_ts);

        $st = $pdo->prepare("
            SELECT u.id, u.full_name, u.username, u.phone
            FROM users u
            JOIN class_students cs ON cs.user_id = u.id
            WHERE cs.class_id = ?
              AND (cs.is_deleted = 0 OR cs.is_deleted IS NULL)
              AND u.role = 'student' AND u.is_deleted = 0
        ");
        $st->execute([$class['id']]);
        $students = $st->fetchAll(PDO::FETCH_ASSOC);
        if (!$students) {
            if ($claimed) {
                rollcall_mark_alocom_attempt($pdo, $class['id'], $today, 'no_students');
            }
            alocom_log('No students for class ' . $class['id'] . ' ' . $class['title']);
            $results[] = ['class_id' => (int)$class['id'], 'title' => $class['title'], 'status' => 'no_students'];
            continue;
        }

        if (empty($collected['present'])) {
            alocom_log(
                'No AloCom student joiners for class ' . $class['id'] . ' ' . $class['title']
                . ' event=' . ($event['id'] ?? '') . ' ' . $event_title
                . ' traffics=' . count($traffics)
                . ' staff=' . (!empty($collected['staff_seen']) ? '1' : '0')
                . ' — retry until a student joins or the session ends'
            );
            if ($claimed) {
                $prev = rollcall_alocom_attempt_status($pdo, $class['id'], $today);
                if ($prev !== 'saved' && $prev !== 'already_saved') {
                    rollcall_mark_alocom_attempt($pdo, $class['id'], $today, 'no_joiners');
                }
            }
            $results[] = ['class_id' => (int)$class['id'], 'title' => $class['title'], 'status' => 'no_joiners', 'event' => $event['title'] ?? ''];
            continue;
        }

        $attendance = [];
        $present_names = [];
        $absent_names = [];
        foreach ($students as $student) {
            $is_present = alocom_student_is_present($student, $collected['present'], $students);
            $attendance[$student['id']] = $is_present ? 'present' : 'absent';
            if ($is_present) {
                $present_names[] = $student['full_name'];
            } else {
                $absent_names[] = $student['full_name'];
            }
        }

        $unmatched = alocom_unmatched_joiners($collected['present'], $students);
        $stats = ['saved' => 0, 'sms' => 0, 'absent' => count($absent_names), 'present' => count($present_names)];
        if (!$dry_run) {
            $stats = rollcall_save_and_notify($pdo, $class['id'], $today, $jalali, $attendance, [
                'is_edit' => $had_rows,
                'source' => 'alocom',
                'unmatched' => $unmatched,
            ]);
            if ($claimed) {
                rollcall_mark_alocom_attempt($pdo, $class['id'], $today, 'saved');
            }
            alocom_log('Synced class ' . $class['id'] . ' ' . $class['title'] . ' present=' . $stats['present'] . ' absent=' . $stats['absent']);
        } else {
            $stats['status_note'] = $had_rows ? 'already_saved' : 'dry_run';
        }

        $results[] = [
            'class_id' => (int)$class['id'],
            'title' => $class['title'],
            'event' => $event['title'] ?? '',
            'status' => $dry_run ? 'dry_run' : 'saved',
            'present' => $present_names,
            'absent' => $absent_names,
            'unmatched' => $unmatched,
            'stats' => $stats,
        ];
        } catch (Exception $e) {
            if ($claimed) {
                rollcall_release_alocom_attempt($pdo, $class['id'], $today);
            }
            alocom_log('due class fail ' . $class['id'] . ' ' . $class['title'] . ' ' . $e->getMessage());
            if (function_exists('ghazal_error_from_throwable')) {
                ghazal_error_from_throwable($e);
            }
            $results[] = [
                'class_id' => (int)$class['id'],
                'title' => $class['title'],
                'status' => 'error',
                'error' => $e->getMessage(),
            ];
        }
    }

    return ['synced' => $results];
    } finally {
        if ($lock) {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }
}
