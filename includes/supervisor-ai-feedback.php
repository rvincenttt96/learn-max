<?php
/**
 * Like / dislike / report for supervisor AI Assistance.
 * Reports go to the Mattermost AI Training channel.
 */

function supervisor_ai_feedback_config() {
    static $cfg = null;
    if ($cfg !== null) {
        return $cfg;
    }
    $all = [];
    $path = __DIR__ . '/rollcall-config.php';
    if (is_file($path)) {
        $loaded = require $path;
        if (is_array($loaded)) {
            $all = $loaded;
        }
    }
    $ai = is_array($all['ai_training'] ?? null) ? $all['ai_training'] : [];
    $cfg = [
        'url' => rtrim((string)($ai['url'] ?? $all['mattermost_url'] ?? 'https://co.ghazalify.com'), '/'),
        'bot_token' => trim((string)($ai['bot_token'] ?? '')),
        'channel_id' => trim((string)($ai['channel_id'] ?? '')),
    ];
    return $cfg;
}

function supervisor_ai_feedback_clip($text, $max = 3500) {
    $text = trim((string)$text);
    if ($text === '') {
        return '';
    }
    if (function_exists('mb_substr')) {
        if (mb_strlen($text, 'UTF-8') > $max) {
            return mb_substr($text, 0, $max, 'UTF-8') . '…';
        }
        return $text;
    }
    if (strlen($text) > $max) {
        return substr($text, 0, $max) . '…';
    }
    return $text;
}

function supervisor_ai_feedback_hash($question, $answer) {
    return sha1(supervisor_ai_feedback_clip($question, 8000) . "\0" . supervisor_ai_feedback_clip($answer, 8000));
}

function supervisor_ai_feedback_ensure(PDO $pdo) {
    static $done = false;
    if ($done) {
        return;
    }
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS supervisor_ai_votes (
            user_id INT NOT NULL,
            pair_hash CHAR(40) NOT NULL,
            kind ENUM('like','dislike') NOT NULL,
            question TEXT NOT NULL,
            answer TEXT NOT NULL,
            username VARCHAR(64) NOT NULL DEFAULT '',
            full_name VARCHAR(190) NOT NULL DEFAULT '',
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (user_id, pair_hash),
            KEY kind_idx (kind)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS supervisor_ai_reports (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            pair_hash CHAR(40) NOT NULL,
            question TEXT NOT NULL,
            answer TEXT NOT NULL,
            username VARCHAR(64) NOT NULL DEFAULT '',
            full_name VARCHAR(190) NOT NULL DEFAULT '',
            mm_ok TINYINT(1) NOT NULL DEFAULT 0,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY user_idx (user_id, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $done = true;
}

function supervisor_ai_feedback_actor() {
    return [
        'user_id' => (int)($_SESSION['user_id'] ?? 0),
        'username' => trim((string)($_SESSION['username'] ?? '')),
        'full_name' => trim((string)($_SESSION['full_name'] ?? '')),
    ];
}

function supervisor_ai_feedback_stats(PDO $pdo) {
    supervisor_ai_feedback_ensure($pdo);
    $likes = (int)$pdo->query("SELECT COUNT(*) FROM supervisor_ai_votes WHERE kind = 'like'")->fetchColumn();
    $dislikes = (int)$pdo->query("SELECT COUNT(*) FROM supervisor_ai_votes WHERE kind = 'dislike'")->fetchColumn();
    $reports = (int)$pdo->query("SELECT COUNT(*) FROM supervisor_ai_reports")->fetchColumn();
    return [
        'likes' => $likes,
        'dislikes' => $dislikes,
        'reports' => $reports,
    ];
}

function supervisor_ai_feedback_post_mattermost($message) {
    $cfg = supervisor_ai_feedback_config();
    $token = $cfg['bot_token'];
    $channel = $cfg['channel_id'];
    $base = $cfg['url'];
    if ($token === '' || $channel === '' || $base === '' || trim((string)$message) === '') {
        return false;
    }
    $payload = json_encode([
        'channel_id' => $channel,
        'message' => $message,
    ], JSON_UNESCAPED_UNICODE);
    $ch = curl_init($base . '/api/v4/posts');
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $token,
        ],
        CURLOPT_TIMEOUT => 12,
        CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
    ]);
    $resp = curl_exec($ch);
    $errno = curl_errno($ch);
    $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $ok = $errno === 0 && $http >= 200 && $http < 300;
    if (!$ok) {
        error_log('ai training mm fail errno=' . $errno . ' http=' . $http . ' resp=' . substr((string)$resp, 0, 180));
    }
    return $ok;
}

function supervisor_ai_feedback_vote(PDO $pdo, $kind, $question, $answer) {
    $kind = $kind === 'dislike' ? 'dislike' : 'like';
    $actor = supervisor_ai_feedback_actor();
    if ($actor['user_id'] <= 0) {
        throw new Exception('نشست نامعتبر است.');
    }
    $question = supervisor_ai_feedback_clip($question, 8000);
    $answer = supervisor_ai_feedback_clip($answer, 8000);
    if ($question === '' || $answer === '') {
        throw new Exception('اول یک سؤال بپرسید، بعد لایک یا دیس‌لایک بزنید.');
    }
    supervisor_ai_feedback_ensure($pdo);
    $hash = supervisor_ai_feedback_hash($question, $answer);
    $st = $pdo->prepare("
        INSERT INTO supervisor_ai_votes
            (user_id, pair_hash, kind, question, answer, username, full_name)
        VALUES (?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            kind = VALUES(kind),
            question = VALUES(question),
            answer = VALUES(answer),
            username = VALUES(username),
            full_name = VALUES(full_name)
    ");
    $st->execute([
        $actor['user_id'],
        $hash,
        $kind,
        $question,
        $answer,
        $actor['username'],
        $actor['full_name'] !== '' ? $actor['full_name'] : $actor['username'],
    ]);
    return [
        'kind' => $kind,
        'pair_hash' => $hash,
        'stats' => supervisor_ai_feedback_stats($pdo),
    ];
}

function supervisor_ai_feedback_report(PDO $pdo, $question, $answer) {
    $actor = supervisor_ai_feedback_actor();
    if ($actor['user_id'] <= 0) {
        throw new Exception('نشست نامعتبر است.');
    }
    $question = supervisor_ai_feedback_clip($question, 8000);
    $answer = supervisor_ai_feedback_clip($answer, 8000);
    if ($question === '' || $answer === '') {
        throw new Exception('اول یک سؤال بپرسید، بعد گزارش بزنید.');
    }

    $now = time();
    $bucket = $_SESSION['supervisor_ai_report_rate'] ?? ['start' => $now, 'n' => 0];
    if ($now - (int)$bucket['start'] > 900) {
        $bucket = ['start' => $now, 'n' => 0];
    }
    $bucket['n'] = (int)$bucket['n'] + 1;
    $_SESSION['supervisor_ai_report_rate'] = $bucket;
    if ($bucket['n'] > 12) {
        throw new Exception('تعداد گزارش در این چند دقیقه زیاد شد. کمی بعد دوباره امتحان کنید.');
    }

    $name = $actor['full_name'] !== '' ? $actor['full_name'] : 'بدون نام';
    $uname = $actor['username'] !== '' ? $actor['username'] : '—';
    $msg = "**گزارش دستیار هوشمند**\n";
    $msg .= "کاربر: **" . $name . "** (`" . $uname . "`)\n";
    $msg .= "\n**سؤال**\n" . $question . "\n";
    $msg .= "\n**جواب**\n" . $answer;

    $mm_ok = supervisor_ai_feedback_post_mattermost($msg);
    supervisor_ai_feedback_ensure($pdo);
    $st = $pdo->prepare("
        INSERT INTO supervisor_ai_reports
            (user_id, pair_hash, question, answer, username, full_name, mm_ok)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $st->execute([
        $actor['user_id'],
        supervisor_ai_feedback_hash($question, $answer),
        $question,
        $answer,
        $uname,
        $name,
        $mm_ok ? 1 : 0,
    ]);
    if (!$mm_ok) {
        throw new Exception('گزارش ذخیره شد، ولی ارسال به کانال AI Training برقرار نشد.');
    }
    return [
        'ok' => true,
        'stats' => supervisor_ai_feedback_stats($pdo),
    ];
}
