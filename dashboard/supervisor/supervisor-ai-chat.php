<?php
if (empty($_SESSION['supervisor_ai_csrf'])) {
    $_SESSION['supervisor_ai_csrf'] = bin2hex(random_bytes(16));
}
$sai_csrf = (string)$_SESSION['supervisor_ai_csrf'];
$sai_mode = ($sai_mode ?? 'dock') === 'page' ? 'page' : 'dock';
$sai_class = 'sai-root' . ($sai_mode === 'page' ? ' is-page is-open' : '');
$sai_stats = ['likes' => 0, 'dislikes' => 0, 'reports' => 0];
try {
    if (!function_exists('supervisor_ai_feedback_stats')) {
        require_once __DIR__ . '/../../includes/supervisor-ai-feedback.php';
    }
    if (isset($pdo) && $pdo instanceof PDO) {
        $sai_stats = supervisor_ai_feedback_stats($pdo);
    }
} catch (Exception $e) {
    $sai_stats = ['likes' => 0, 'dislikes' => 0, 'reports' => 0];
}
?>
<div id="sai-root" class="<?= $sai_class ?>" data-csrf="<?= htmlspecialchars($sai_csrf) ?>" data-mode="<?= $sai_mode ?>">
    <?php if ($sai_mode !== 'page'): ?>
    <button type="button" class="sai-toggle" aria-expanded="false" title="AI Assistance">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
            <path d="M4 12a8 8 0 0 1 8-8h0a8 8 0 0 1 8 8v2.2a2.8 2.8 0 0 1-2.8 2.8H13l-3.4 3v-3H9.8A5.8 5.8 0 0 1 4 13.2z"/>
            <circle cx="9" cy="12" r="0.9" fill="currentColor" stroke="none"/>
            <circle cx="12" cy="12" r="0.9" fill="currentColor" stroke="none"/>
            <circle cx="15" cy="12" r="0.9" fill="currentColor" stroke="none"/>
        </svg>
    </button>
    <?php endif; ?>
    <section class="sai-panel" aria-label="AI Assistance">
        <div class="sai-head">
            <div class="sai-head-row">
                <div>
                    <h2>AI Assistance</h2>
                    <p>فقط گزارش از پنل؛ ثبت یا حذف ندارد.</p>
                </div>
                <div class="sai-stats" id="sai-stats" aria-label="آمار لایک و دیس‌لایک">
                    <span class="sai-stat is-like">لایک <b data-k="likes"><?= (int)$sai_stats['likes'] ?></b></span>
                    <span class="sai-stat is-dislike">دیس‌لایک <b data-k="dislikes"><?= (int)$sai_stats['dislikes'] ?></b></span>
                </div>
            </div>
        </div>
        <div class="sai-log">
            <div class="sai-bubble is-bot">از کلاس، غایب، رول‌کال نزده، سشن‌پلن، تکلیف، EAS و اسم معلم بپرس.</div>
        </div>
        <div class="sai-chips">
            <button type="button" class="sai-chip" data-q="چه کلاس‌هایی امروز داریم؟">کلاس‌های امروز</button>
            <button type="button" class="sai-chip" data-q="غایب‌های امروز کدام کلاس‌ها هستند؟">غایب‌های امروز</button>
            <button type="button" class="sai-chip" data-q="چه معلم‌هایی امروز رول‌کال نزده‌اند؟">رول‌کال نزده</button>
            <button type="button" class="sai-chip" data-q="چه معلم‌هایی امروز سشن‌پلن ننوشته‌اند؟">سشن‌پلن نزده</button>
            <button type="button" class="sai-chip" data-q="چه معلم‌هایی امروز تکلیف ثبت نکرده‌اند؟">تکلیف نزده</button>
            <button type="button" class="sai-chip" data-q="کدام کلاس‌ها آبزرو نشده‌اند؟">آبزرو نشده</button>
            <button type="button" class="sai-chip" data-q="کدوم کلاس‌ها Error Gravity ندارن؟">Error Gravity</button>
            <button type="button" class="sai-chip" data-q="الان کدام کلاس‌ها در حال برگزاری هستند؟">کلاس لایو</button>
            <button type="button" class="sai-chip" data-q="استادی پلن کجاست؟">صفحه Study Plan</button>
        </div>
        <form class="sai-form">
            <textarea rows="1" placeholder="مثلاً: سارا محمدی چند غیبت داشته؟" required></textarea>
            <button type="submit" class="sai-send">بپرس</button>
        </form>
    </section>
</div>
