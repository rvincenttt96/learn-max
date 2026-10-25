<?php
/**
 * Liara chat wrapper for quiz generation. Longer timeout than supervisor AI.
 */

function quiz_ai_config() {
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

function quiz_ai_json($data) {
    return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function quiz_ai_http(array $payload) {
    $cfg = quiz_ai_config();
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
        CURLOPT_POSTFIELDS => quiz_ai_json($payload),
        CURLOPT_TIMEOUT => 90,
        CURLOPT_CONNECTTIMEOUT => 12,
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
            throw new Exception('ساخت سؤال طول کشید و قطع شد. محدوده صفحه را کوچک‌تر کنید و دوباره بسازید.');
        }
        if (in_array($errno, [6, 7, 35], true)) {
            throw new Exception('اتصال به لیارا برقرار نشد.');
        }
        throw new Exception($err !== '' ? $err : 'مدل لیارا الان در دسترس نیست.');
    }
    $decoded = json_decode((string)$raw, true);
    if ($code === 402) {
        throw new Exception('اعتبار لیارا کافی نیست.');
    }
    if ($code < 200 || $code >= 300) {
        $msg = is_array($decoded) ? (string)($decoded['error']['message'] ?? $decoded['message'] ?? '') : '';
        throw new Exception($msg !== '' ? $msg : ('خطای لیارا (' . $code . ')'));
    }
    if (!is_array($decoded)) {
        throw new Exception('پاسخ لیارا نامعتبر بود.');
    }
    return $decoded;
}

function quiz_ai_content(array $decoded) {
    $text = (string)($decoded['choices'][0]['message']['content'] ?? '');
    $text = trim($text);
    if ($text === '') {
        throw new Exception('مدل متنی برنگرداند.');
    }
    if (preg_match('/```(?:json)?\s*(\{.*\})\s*```/s', $text, $m)) {
        $text = $m[1];
    } elseif (preg_match('/\{.*\}/s', $text, $m)) {
        $text = $m[0];
    }
    $data = json_decode($text, true);
    if (!is_array($data)) {
        throw new Exception('مدل سؤال‌ها را به‌صورت JSON درست برنگرداند. دوباره بسازید.');
    }
    return $data;
}

function quiz_ai_model() {
    $cfg = quiz_ai_config();
    $model = trim((string)($cfg['model'] ?? 'openai/gpt-4o-mini'));
    return $model !== '' ? $model : 'openai/gpt-4o-mini';
}

function quiz_ai_system_prompt(array $tpl, $level_key, $difficulty) {
    $ph = !empty($tpl['has_phonetics']);
    $mix = $tpl['mix'];
    $lines = [];
    foreach ($mix as $k => $n) {
        if ((int)$n > 0) {
            $lines[] = $k . ': ' . (int)$n;
        }
    }
    $mix_txt = implode(', ', $lines);
    $ph_rule = $ph
        ? 'Include phonetics items as specified. Keep IPA simple and classroom-safe.'
        : 'Do NOT include phonetics. This level has no phonetics in Ghazal quizzes.';
    $kind = (string)$tpl['kinds'];
    if ($kind === 'writing') {
        $mark = (int)$tpl['mark'];
        return trim("
You are the quiz writer for Ghazal Language Institute (آموزشگاه زبان غزل).
Create ONE classroom writing TOPIC from the given coursebook excerpt only.
This is not a test paper and not an essay task.
Students will write a few lines about the topic. The teacher marks it later.
Do not invent ideas or words that are not supported by the excerpt.
Keep CEFR band {$tpl['cefr']} for level {$level_key}.
Difficulty: {$difficulty}.
Quiz: {$tpl['label']} (Session {$tpl['session']}, mark {$mark}, {$tpl['minutes']} minutes).

prompt must be English: a short topic title on the first line, then 1–2 sentences like \"Write a few lines about...\".
No multiple-choice, no grammar items, no comprehension questions, no bullet rubric, no word-count, no model answer.

Return ONLY JSON with this shape:
{
  \"passage\": \"\",
  \"listening_script\": \"\",
  \"listening_repetition\": \"\",
  \"questions\": [
    {
      \"id\": \"q-1\",
      \"skill\": \"writing\",
      \"type\": \"writing\",
      \"prompt\": \"Topic title\\nWrite a few lines about ...\",
      \"options\": [],
      \"answer\": \"\",
      \"points\": {$mark}
    }
  ]
}
Rules:
- One topic only.
- No markdown.
");
    }
    $kind_rule = 'Use multiple-choice (4 options) unless the mix requires otherwise.';
    if ($kind === 'mc_fill') {
        $kind_rule = 'Mix multiple-choice (4 options) and fill-in-the-blank. Fill items use a single short answer.';
    } elseif ($kind === 'reading') {
        $kind_rule = 'Write an original reading passage (120–180 words) grounded in the excerpt, then 2 true/false, 2 short-answer, 2 multiple-choice.';
    } elseif ($kind === 'listening') {
        $kind_rule = 'Write a listening SCRIPT (80–140 words) at the CEFR band, then listening questions. Audio is generated later; still return listening_script.';
    }
    $rep = !empty($tpl['listening_repetition'])
        ? 'Also set listening_repetition to one short classroom instruction (Listen and repeat a sentence from the book).'
        : 'Do not include listening_repetition.';

    return trim("
You are the quiz writer for Ghazal Language Institute (آموزشگاه زبان غزل).
Write classroom English quizzes from the given coursebook excerpt only.
Do not invent grammar or vocabulary that is not supported by the excerpt.
Questions, options, and answers MUST be in English.
Keep CEFR band {$tpl['cefr']} for level {$level_key}.
Difficulty: {$difficulty}.
Quiz: {$tpl['label']} (Session {$tpl['session']}, mark {$tpl['mark']}, {$tpl['minutes']} minutes).
Required mix: {$mix_txt}. Target about {$tpl['question_count']} questions.
{$ph_rule}
{$kind_rule}
{$rep}

Return ONLY JSON with this shape:
{
  \"passage\": \"\" ,
  \"listening_script\": \"\",
  \"listening_repetition\": \"\",
  \"questions\": [
    {
      \"id\": \"q-1\",
      \"skill\": \"grammar|vocab|phonetics|reading|listening|writing\",
      \"type\": \"mc|fill|tf|short|writing\",
      \"prompt\": \"...\",
      \"options\": [\"A\",\"B\",\"C\",\"D\"],
      \"answer\": \"...\",
      \"points\": 1
    }
  ]
}
Rules:
- One correct answer. Distractors plausible.
- True/false options are True and False.
- Fill prompts contain a blank like ____ .
- Do not number prompts yourself beyond the id field.
- No markdown.
");
}

function quiz_ai_generate($excerpt, array $tpl, $level_key, $difficulty) {
    $excerpt = trim((string)$excerpt);
    if (function_exists('mb_strlen') ? mb_strlen($excerpt, 'UTF-8') < 80 : strlen($excerpt) < 80) {
        throw new Exception('متن صفحه‌های انتخاب‌شده خیلی کم است. صفحه دیگری از کتاب را بگیرید.');
    }
    $payload = [
        'model' => quiz_ai_model(),
        'temperature' => 0.5,
        'max_tokens' => 3500,
        'messages' => [
            ['role' => 'system', 'content' => quiz_ai_system_prompt($tpl, $level_key, $difficulty)],
            ['role' => 'user', 'content' => "Coursebook excerpt (level {$level_key}):\n\n" . $excerpt],
        ],
    ];
    @set_time_limit(120);
    $decoded = quiz_ai_http($payload);
    $data = quiz_ai_content($decoded);
    return quiz_ai_normalize_payload($data, $tpl);
}

function quiz_ai_replace_one($excerpt, array $tpl, $level_key, $difficulty, array $existing, array $old) {
    $avoid = [];
    foreach ($existing as $q) {
        if (!empty($q['prompt'])) {
            $avoid[] = (string)$q['prompt'];
        }
    }
    $writing = (($tpl['kinds'] ?? '') === 'writing');
    $sys = quiz_ai_system_prompt($tpl, $level_key, $difficulty)
        . ($writing
            ? "\nReplace the writing TOPIC with a different one from the same pages. Return JSON {\"question\": {...same fields...}} only.\nDo not copy the existing prompt."
            : "\nReplace ONE question. Return JSON {\"question\": {...same fields...}} only.\nKeep the same skill and type. Do not copy any existing prompt.");
    $user = "Coursebook excerpt:\n" . $excerpt
        . "\n\nQuestion to replace:\n" . quiz_ai_json($old)
        . "\n\nExisting prompts to avoid:\n" . implode("\n- ", array_slice($avoid, 0, 20));
    $payload = [
        'model' => quiz_ai_model(),
        'temperature' => 0.7,
        'max_tokens' => 900,
        'messages' => [
            ['role' => 'system', 'content' => $sys],
            ['role' => 'user', 'content' => $user],
        ],
    ];
    @set_time_limit(90);
    $decoded = quiz_ai_http($payload);
    $data = quiz_ai_content($decoded);
    $q = $data['question'] ?? $data;
    if (isset($q['questions'][0]) && is_array($q['questions'][0])) {
        $q = $q['questions'][0];
    }
    if (!is_array($q) || trim((string)($q['prompt'] ?? '')) === '') {
        throw new Exception((($tpl['kinds'] ?? '') === 'writing')
            ? 'تاپیک جدید برنگشت. دوباره امتحان کنید.'
            : 'جایگزینی سؤال برنگشت. دوباره امتحان کنید.');
    }
    $q['id'] = (string)($old['id'] ?? $q['id'] ?? 'q-1');
    if (($tpl['kinds'] ?? '') === 'writing') {
        $q['skill'] = 'writing';
        $q['type'] = 'writing';
        $q['options'] = [];
        $q['answer'] = '';
        $q['points'] = (int)($tpl['mark'] ?? 8);
    } else {
        $q['skill'] = (string)($old['skill'] ?? $q['skill'] ?? 'grammar');
        $q['type'] = (string)($old['type'] ?? $q['type'] ?? 'mc');
    }
    return quiz_builder_normalize_question($q, 0);
}

function quiz_ai_normalize_payload(array $data, array $tpl) {
    $questions = [];
    $src = [];
    if (isset($data['questions']) && is_array($data['questions'])) {
        $src = $data['questions'];
    }
    foreach (array_values($src) as $i => $q) {
        if (!is_array($q)) {
            continue;
        }
        $item = quiz_builder_normalize_question($q, $i);
        if ($item['prompt'] === '') {
            continue;
        }
        $questions[] = $item;
    }
    $want = (int)$tpl['question_count'];
    $warning = '';
    if ($want > 0 && count($questions) > $want) {
        $questions = array_slice($questions, 0, $want);
    }
    if ($want > 0 && count($questions) < $want) {
        $warning = 'تعداد سؤال کمتر از قالب غزل است؛ می‌توانید سؤال جایگزین بسازید.';
    }
    if (!$questions) {
        throw new Exception('هیچ سؤالی ساخته نشد.');
    }
    foreach ($questions as $i => &$q) {
        $q['id'] = 'q-' . ($i + 1);
    }
    unset($q);
    $out = [
        'passage' => trim((string)($data['passage'] ?? '')),
        'listening_script' => trim((string)($data['listening_script'] ?? '')),
        'listening_repetition' => trim((string)($data['listening_repetition'] ?? '')),
        'warning' => $warning,
        'questions' => $questions,
    ];
    if (empty($tpl['listening_repetition'])) {
        $out['listening_repetition'] = '';
    }
    if (($tpl['kinds'] ?? '') === 'writing') {
        if (function_exists('quiz_builder_force_writing_topic')) {
            $out = quiz_builder_force_writing_topic($out, $tpl);
        }
        if (empty($out['questions'])) {
            throw new Exception('تاپیک رایتینگ ساخته نشد. دوباره بسازید.');
        }
        $out['warning'] = '';
    }
    return $out;
}

function quiz_ai_rate_ok($bucket_key, $max = 25, $window = 900) {
    $now = time();
    $bucket = $_SESSION[$bucket_key] ?? ['start' => $now, 'n' => 0];
    if ($now - (int)$bucket['start'] > $window) {
        $bucket = ['start' => $now, 'n' => 0];
    }
    $bucket['n'] = (int)$bucket['n'] + 1;
    $_SESSION[$bucket_key] = $bucket;
    return $bucket['n'] <= $max;
}
