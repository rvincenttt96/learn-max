<?php
/**
 * Extract coursebook PDF text page-by-page for the quiz builder.
 */

function quiz_pdf_bin($name) {
    $name = (string)$name;
    $candidates = [
        '/usr/bin/' . $name,
        '/usr/local/bin/' . $name,
        '/opt/homebrew/bin/' . $name,
        $name,
    ];
    foreach ($candidates as $bin) {
        if ($bin === $name) {
            $which = @shell_exec('command -v ' . escapeshellarg($name) . ' 2>/dev/null');
            if (is_string($which) && trim($which) !== '') {
                return trim($which);
            }
            continue;
        }
        if (is_file($bin) && is_executable($bin)) {
            return $bin;
        }
    }
    return '';
}

function quiz_pdf_run($cmd, $timeout = 90) {
    $cmd = (string)$cmd . ' 2>/dev/null';
    $out = [];
    $code = 0;
    if (function_exists('proc_open')) {
        $spec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $proc = @proc_open($cmd, $spec, $pipes);
        if (is_resource($proc)) {
            fclose($pipes[0]);
            stream_set_blocking($pipes[1], false);
            stream_set_blocking($pipes[2], false);
            $start = time();
            $buf = '';
            while (true) {
                $buf .= stream_get_contents($pipes[1]);
                stream_get_contents($pipes[2]);
                $st = proc_get_status($proc);
                if (empty($st['running'])) {
                    $buf .= stream_get_contents($pipes[1]);
                    $code = (int)$st['exitcode'];
                    break;
                }
                if ((time() - $start) > $timeout) {
                    proc_terminate($proc);
                    $code = 124;
                    break;
                }
                usleep(80000);
            }
            fclose($pipes[1]);
            fclose($pipes[2]);
            proc_close($proc);
            return [$code, $buf];
        }
    }
    exec($cmd, $out, $code);
    return [$code, implode("\n", $out)];
}

function quiz_pdf_count_from_info($path) {
    $bin = quiz_pdf_bin('pdfinfo');
    if ($bin === '') {
        return 0;
    }
    list($code, $out) = quiz_pdf_run(escapeshellcmd($bin) . ' ' . escapeshellarg($path), 20);
    if ($code !== 0) {
        return 0;
    }
    if (preg_match('/^Pages:\s+(\d+)/mi', $out, $m)) {
        return (int)$m[1];
    }
    return 0;
}

function quiz_pdf_count_from_catalog($path) {
    $path = (string)$path;
    $head = @file_get_contents($path, false, null, 0, 256 * 1024);
    if (is_string($head) && preg_match('/\/Linearized\b.{0,400}\/N\s+(\d+)/s', $head, $m)) {
        $n = (int)$m[1];
        if ($n > 0) {
            return $n;
        }
    }
    $size = (int)@filesize($path);
    if ($size > 64) {
        $fh = @fopen($path, 'rb');
        if ($fh) {
            @fseek($fh, max(0, $size - 512000));
            $tail = fread($fh, 512000);
            fclose($fh);
            if (is_string($tail) && preg_match_all('/\/Count\s+(\d+)/', $tail, $mm) && !empty($mm[1])) {
                $n = (int)max(array_map('intval', $mm[1]));
                if ($n > 0 && $n < 20000) {
                    return $n;
                }
            }
        }
    }
    return 0;
}

function quiz_pdf_count($path) {
    $n = quiz_pdf_count_from_info($path);
    if ($n > 0) {
        return $n;
    }
    $n = quiz_pdf_count_from_catalog($path);
    if ($n > 0) {
        return $n;
    }
    $size = (int)@filesize($path);
    if ($size > 15 * 1048576) {
        return 0;
    }
    $py = quiz_pdf_bin('python3');
    if ($py === '') {
        $py = quiz_pdf_bin('python');
    }
    if ($py !== '') {
        $script = "import sys\n"
            . "p=sys.argv[1]\n"
            . "n=0\n"
            . "try:\n"
            . " from pypdf import PdfReader\n"
            . " n=len(PdfReader(p).pages)\n"
            . "except Exception:\n"
            . " try:\n"
            . "  from PyPDF2 import PdfReader\n"
            . "  n=len(PdfReader(p).pages)\n"
            . " except Exception:\n"
            . "  sys.exit(2)\n"
            . "print(n)\n";
        $tmp = tempnam(sys_get_temp_dir_safe(), 'qbc');
        if ($tmp !== false) {
            file_put_contents($tmp, $script);
            list($code, $out) = quiz_pdf_run(
                escapeshellcmd($py) . ' ' . escapeshellarg($tmp) . ' ' . escapeshellarg($path),
                12
            );
            @unlink($tmp);
            if ($code === 0 && preg_match('/(\d+)/', trim($out), $m)) {
                $n = (int)$m[1];
                if ($n > 0) {
                    return $n;
                }
            }
        }
    }
    if ($size <= 15 * 1048576) {
        $raw = @file_get_contents($path, false, null, 0, 2 * 1024 * 1024);
        if (is_string($raw) && preg_match_all('/\/Type\s*\/Page(?![sA-Z])/m', $raw, $m)) {
            $n = count($m[0]);
            if ($n > 0) {
                return $n;
            }
        }
    }
    return 0;
}

function quiz_pdf_extract_range($path, $from, $to) {
    $from = max(1, (int)$from);
    $to = max($from, (int)$to);
    $pages = quiz_pdf_pdftotext_range($path, $from, $to, 20);
    if ($pages === null || !quiz_pdf_has_text($pages)) {
        $py = quiz_pdf_python_range($path, $from, $to, 20);
        if (is_array($py) && ($pages === null || quiz_pdf_has_text($py))) {
            $pages = $py;
        }
    }
    if (!is_array($pages)) {
        $pages = [];
    }
    for ($i = $from; $i <= $to; $i++) {
        if (!isset($pages[$i])) {
            $pages[$i] = '';
        }
    }
    ksort($pages, SORT_NUMERIC);
    return $pages;
}

function quiz_pdf_pdftotext_range($path, $from = 0, $to = 0, $timeout = 40) {
    $bin = quiz_pdf_bin('pdftotext');
    if ($bin === '') {
        return null;
    }
    $from = (int)$from;
    $to = (int)$to;
    $range = '';
    if ($from > 0) {
        $range .= ' -f ' . $from;
    }
    if ($to >= $from && $to > 0) {
        $range .= ' -l ' . $to;
    }
    $cmd = escapeshellcmd($bin) . $range . ' -layout -enc UTF-8 ' . escapeshellarg($path) . ' -';
    list($code, $out) = quiz_pdf_run($cmd, $timeout);
    if ($code === 124) {
        throw new Exception('خواندن این دسته از صفحه‌ها طول کشید. دوباره ادامه می‌دهیم.');
    }
    if ($code !== 0 && trim($out) === '') {
        return null;
    }
    $parts = preg_split("/\x0c/", (string)$out);
    $map = [];
    $start = $from > 0 ? $from : 1;
    foreach ($parts as $i => $body) {
        $no = $start + $i;
        if ($to > 0 && $no > $to) {
            break;
        }
        $map[$no] = quiz_pdf_clean_text($body);
    }
    return $map;
}

function quiz_pdf_pdftotext($path) {
    return quiz_pdf_pdftotext_range($path, 0, 0, 120);
}

function quiz_pdf_python_range($path, $from = 0, $to = 0, $timeout = 40) {
    $py = quiz_pdf_bin('python3');
    if ($py === '') {
        $py = quiz_pdf_bin('python');
    }
    if ($py === '') {
        return null;
    }
    $script = <<<'PY'
import sys
path = sys.argv[1]
start = int(sys.argv[2]) if len(sys.argv) > 2 else 0
end = int(sys.argv[3]) if len(sys.argv) > 3 else 0
reader = None
try:
    from pypdf import PdfReader
    reader = PdfReader(path)
except Exception:
    try:
        from PyPDF2 import PdfReader
        reader = PdfReader(path)
    except Exception:
        sys.exit(2)
total = len(reader.pages)
print("PAGES", total)
lo = start if start > 0 else 1
hi = end if end > 0 else total
if hi < lo:
    hi = lo
for i in range(lo, min(hi, total) + 1):
    try:
        text = reader.pages[i - 1].extract_text() or ""
    except Exception:
        text = ""
    print("---PAGE %d---" % i)
    sys.stdout.write(text.replace("\r\n", "\n"))
    sys.stdout.write("\n")
PY;
    $tmp = tempnam(sys_get_temp_dir_safe(), 'qbpy');
    if ($tmp === false) {
        return null;
    }
    file_put_contents($tmp, $script);
    $cmd = escapeshellcmd($py) . ' ' . escapeshellarg($tmp) . ' ' . escapeshellarg($path)
        . ' ' . (int)$from . ' ' . (int)$to;
    list($code, $out) = quiz_pdf_run($cmd, $timeout);
    @unlink($tmp);
    if ($code === 124) {
        throw new Exception('خواندن این دسته از صفحه‌ها طول کشید. دوباره ادامه می‌دهیم.');
    }
    if ($code !== 0 && strpos($out, '---PAGE ') === false) {
        return null;
    }
    return quiz_pdf_parse_marked_pages($out);
}

function quiz_pdf_python($path) {
    return quiz_pdf_python_range($path, 0, 0, 120);
}

function sys_get_temp_dir_safe() {
    $t = function_exists('sys_get_temp_dir') ? sys_get_temp_dir() : '/tmp';
    return $t ?: '/tmp';
}

function quiz_pdf_parse_marked_pages($raw) {
    $map = [];
    $parts = preg_split('/^---PAGE\s+(\d+)---\s*$/m', (string)$raw, -1, PREG_SPLIT_DELIM_CAPTURE);
    if (count($parts) < 3) {
        return [];
    }
    for ($i = 1; $i < count($parts); $i += 2) {
        $no = (int)$parts[$i];
        $body = isset($parts[$i + 1]) ? $parts[$i + 1] : '';
        if ($no > 0) {
            $map[$no] = quiz_pdf_clean_text($body);
        }
    }
    return $map;
}

function quiz_pdf_clean_text($text) {
    $text = (string)$text;
    $text = str_replace("\r\n", "\n", $text);
    $text = str_replace("\x00", '', $text);
    $text = preg_replace("/[ \t]+/u", ' ', $text);
    $text = preg_replace("/\n{3,}/u", "\n\n", $text);
    return trim($text);
}

function quiz_pdf_php_fallback($path) {
    $raw = @file_get_contents($path);
    if (!is_string($raw) || $raw === '') {
        return [];
    }
    $count = 0;
    if (preg_match_all('/\/Type\s*\/Page(?![sA-Z])/m', $raw, $m)) {
        $count = count($m[0]);
    }
    $chunks = [];
    if (preg_match_all('/BT(.*?)ET/s', $raw, $blocks)) {
        foreach ($blocks[1] as $block) {
            $piece = quiz_pdf_decode_operators($block);
            if ($piece !== '') {
                $chunks[] = $piece;
            }
        }
    }
    $text = quiz_pdf_clean_text(implode("\n", $chunks));
    if ($text === '') {
        return $count > 0 ? array_fill_keys(range(1, min($count, 1)), '') : [];
    }
    if ($count < 1) {
        $count = 1;
    }
    // Unknown page boundaries: put all text on page 1 and empty remaining pages.
    $map = [1 => $text];
    for ($i = 2; $i <= $count; $i++) {
        $map[$i] = '';
    }
    return $map;
}

function quiz_pdf_decode_operators($block) {
    $out = [];
    if (preg_match_all('/(\((?:\\\\.|[^\\\\)])*\)|<[^>]+>)\s*Tj/s', $block, $m)) {
        foreach ($m[1] as $tok) {
            $out[] = quiz_pdf_decode_string($tok);
        }
    }
    if (preg_match_all('/\[(.*?)\]\s*TJ/s', $block, $m)) {
        foreach ($m[1] as $arr) {
            if (preg_match_all('/(\((?:\\\\.|[^\\\\)])*\)|<[^>]+>)/s', $arr, $mm)) {
                foreach ($mm[1] as $tok) {
                    $out[] = quiz_pdf_decode_string($tok);
                }
            }
        }
    }
    return quiz_pdf_clean_text(implode(' ', $out));
}

function quiz_pdf_decode_string($tok) {
    $tok = trim((string)$tok);
    if ($tok === '') {
        return '';
    }
    if ($tok[0] === '<') {
        $hex = preg_replace('/[^0-9A-Fa-f]/', '', substr($tok, 1, -1));
        if ($hex === '') {
            return '';
        }
        if (strlen($hex) % 2 === 1) {
            $hex .= '0';
        }
        $bin = @hex2bin($hex);
        if ($bin === false) {
            return '';
        }
        if (substr($bin, 0, 2) === "\xfe\xff") {
            $bin = substr($bin, 2);
            if (function_exists('mb_convert_encoding')) {
                $txt = @mb_convert_encoding($bin, 'UTF-8', 'UTF-16BE');
                return is_string($txt) ? $txt : '';
            }
            return $bin;
        }
        return $bin;
    }
    if ($tok[0] === '(') {
        $inner = substr($tok, 1, -1);
        $inner = str_replace(
            ['\\\\', '\\n', '\\r', '\\t', '\\b', '\\f', '\\(', '\\)'],
            ['\\', "\n", "\r", "\t", '', '', '(', ')'],
            $inner
        );
        $inner = preg_replace_callback('/\\\\([0-7]{1,3})/', function ($m) {
            return chr(octdec($m[1]));
        }, $inner);
        return $inner;
    }
    return '';
}

function quiz_pdf_has_text(array $pages) {
    foreach ($pages as $body) {
        if (trim((string)$body) !== '') {
            return true;
        }
    }
    return false;
}

function quiz_pdf_extract($path) {
    $path = (string)$path;
    if (!is_file($path)) {
        throw new Exception('فایل PDF پیدا نشد.');
    }
    $pages = quiz_pdf_pdftotext($path);
    if ($pages === null || !quiz_pdf_has_text($pages)) {
        $py = quiz_pdf_python($path);
        if (is_array($py) && ($pages === null || quiz_pdf_has_text($py))) {
            $pages = $py;
        }
    }
    if ($pages === null || !quiz_pdf_has_text($pages)) {
        $fb = quiz_pdf_php_fallback($path);
        if (is_array($fb) && ($pages === null || quiz_pdf_has_text($fb) || !$pages)) {
            $pages = $fb;
        }
    }
    if (!is_array($pages) || !$pages) {
        throw new Exception('متن کتاب استخراج نشد. اگر فایل اسکن تصویری است، نسخهٔ متنی PDF بگذارید.');
    }
    ksort($pages, SORT_NUMERIC);
    $info_count = quiz_pdf_count_from_info($path);
    $max = (int)max(array_keys($pages));
    if ($info_count > $max) {
        for ($i = $max + 1; $i <= $info_count; $i++) {
            $pages[$i] = '';
        }
        $max = $info_count;
    }
    return $pages;
}

function quiz_pdf_extract_init(PDO $pdo, array $book, $restart = false) {
    if (!function_exists('quiz_builder_book_path')) {
        require_once __DIR__ . '/quiz-builder.php';
    }
    $id = (int)$book['id'];
    $path = quiz_builder_book_path($book);
    if ($path === '' || !is_file($path)) {
        throw new Exception('فایل PDF پیدا نشد.');
    }
    @set_time_limit(20);
    @ignore_user_abort(true);
    $count = (int)($book['page_count'] ?? 0);
    $cursor = (int)($book['extract_cursor'] ?? 0);
    if ($restart || $count < 1 || in_array((string)$book['extract_status'], ['pending', 'failed', 'empty'], true)) {
        $count = quiz_pdf_count($path);
        $cursor = 0;
        $pdo->prepare("DELETE FROM quiz_book_pages WHERE book_id = ?")->execute([$id]);
    }
    if ($count < 1) {
        $pdo->prepare("
            UPDATE quiz_books
            SET extract_status = 'failed', extract_error = ?
            WHERE id = ?
        ")->execute(['تعداد صفحات PDF خوانده نشد.', $id]);
        throw new Exception('تعداد صفحات PDF خوانده نشد.');
    }
    $pdo->prepare("
        UPDATE quiz_books
        SET page_count = ?, extract_cursor = ?, extract_status = 'extracting', extract_error = NULL
        WHERE id = ?
    ")->execute([$count, $cursor, $id]);
    $book['page_count'] = $count;
    $book['extract_cursor'] = $cursor;
    $book['extract_status'] = 'extracting';
    $book['extract_error'] = null;
    return $book;
}

function quiz_pdf_extract_chunk(PDO $pdo, array $book, $batch = 8) {
    if (!function_exists('quiz_builder_book_path')) {
        require_once __DIR__ . '/quiz-builder.php';
    }
    $id = (int)$book['id'];
    $path = quiz_builder_book_path($book);
    $count = max(1, (int)$book['page_count']);
    $cursor = (int)($book['extract_cursor'] ?? 0);
    $from = $cursor + 1;
    if ($from > $count) {
        return quiz_pdf_extract_finish($pdo, $book);
    }
    $to = min($count, $from + max(1, (int)$batch) - 1);
    @set_time_limit(25);
    @ignore_user_abort(true);
    $pages = quiz_pdf_extract_range($path, $from, $to);
    quiz_builder_upsert_pages($pdo, $id, $pages);
    $pdo->prepare("
        UPDATE quiz_books SET extract_cursor = ?, extract_status = 'extracting' WHERE id = ?
    ")->execute([$to, $id]);
    $book['extract_cursor'] = $to;
    $book['extract_status'] = 'extracting';
    if ($to >= $count) {
        return quiz_pdf_extract_finish($pdo, $book);
    }
    return $book;
}

function quiz_pdf_extract_finish(PDO $pdo, array $book) {
    $id = (int)$book['id'];
    $count = max(1, (int)$book['page_count']);
    $with = quiz_builder_pages_with_text($pdo, $id);
    $status = $with > 0 ? 'ready' : 'empty';
    $err = $status === 'empty'
        ? 'صفحه شمرده شد ولی متن خوانا نبود. احتمالاً اسکن تصویری است.'
        : null;
    $pdo->prepare("
        UPDATE quiz_books
        SET extract_cursor = ?, extract_status = ?, extract_error = ?
        WHERE id = ?
    ")->execute([$count, $status, $err, $id]);
    $book['extract_cursor'] = $count;
    $book['extract_status'] = $status;
    $book['extract_error'] = $err;
    return $book;
}

function quiz_pdf_extract_into_book(PDO $pdo, array $book) {
    if (!function_exists('quiz_builder_book_path')) {
        require_once __DIR__ . '/quiz-builder.php';
    }
    $id = (int)$book['id'];
    $path = quiz_builder_book_path($book);
    $pdo->prepare("UPDATE quiz_books SET extract_status = 'extracting', extract_error = NULL WHERE id = ?")
        ->execute([$id]);
    try {
        @set_time_limit(150);
        $pages = quiz_pdf_extract($path);
        $stats = quiz_builder_replace_pages($pdo, $id, $pages);
        $count = max((int)$stats['pages'], (int)max(array_keys($pages)));
        $status = ((int)$stats['with_text'] > 0) ? 'ready' : 'empty';
        $err = $status === 'empty'
            ? 'صفحه شمرده شد ولی متن خوانا نبود. احتمالاً اسکن تصویری است.'
            : null;
        $pdo->prepare("
            UPDATE quiz_books
            SET page_count = ?, extract_status = ?, extract_error = ?
            WHERE id = ?
        ")->execute([$count, $status, $err, $id]);
        $book['page_count'] = $count;
        $book['extract_status'] = $status;
        $book['extract_error'] = $err;
        return $book;
    } catch (Exception $e) {
        $msg = $e->getMessage();
        $clip = function_exists('mb_substr') ? mb_substr($msg, 0, 480, 'UTF-8') : substr($msg, 0, 480);
        $pdo->prepare("
            UPDATE quiz_books SET extract_status = 'failed', extract_error = ? WHERE id = ?
        ")->execute([$clip, $id]);
        throw $e;
    }
}
