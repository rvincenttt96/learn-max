<?php
$wb_preview = defined('WB_PREVIEW') && WB_PREVIEW;
if (!$wb_preview) {
    if (empty($_SESSION['welcome_overlay'])) {
        return;
    }
    unset($_SESSION['welcome_overlay']);
}
$wb_name = trim((string)($_SESSION['full_name'] ?? ''));
if ($wb_name === '') {
    $wb_name = trim((string)($_SESSION['username'] ?? ''));
}
$wb_parts = preg_split('/\s+/u', $wb_name, 2);
$wb_first = $wb_parts[0] ?? '';
$wb_is_rtl = $wb_first !== '' && (bool) preg_match('/\p{Arabic}/u', $wb_first);
$wb_letters = [];
if ($wb_first !== '' && !$wb_is_rtl) {
    $wb_letters = preg_split('//u', $wb_first, -1, PREG_SPLIT_NO_EMPTY);
}
?>
<style>
.wb-overlay {
    --wb-bg: #08080C;
    --wb-red: #dc2626;
    --wb-rose: #e11d48;
    --wb-pink: #fb7185;
    --wb-cyan: #38bdf8;
    --wb-violet: #a78bfa;
    --wb-text: #e2e8f0;
    position: fixed;
    inset: 0;
    z-index: 99999;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    direction: ltr;
    text-align: center;
    color: var(--wb-text);
    cursor: pointer;
    background:
        radial-gradient(ellipse 70% 55% at 50% 38%, rgba(225, 29, 72, 0.22), transparent 58%),
        radial-gradient(ellipse 50% 40% at 88% 8%, rgba(167, 139, 250, 0.18), transparent 54%),
        radial-gradient(ellipse 46% 36% at 8% 92%, rgba(56, 189, 248, 0.14), transparent 52%),
        #08080C;
    font-family: 'YekanBakhFaNum', Tahoma, Arial, sans-serif;
}
html.wb-lock,
html.wb-lock body {
    overflow: hidden !important;
}
.wb-overlay.is-leaving {
    animation: wbVanish 0.85s cubic-bezier(.22, 1, .36, 1) forwards;
    pointer-events: none;
}
@keyframes wbVanish {
    to { opacity: 0; filter: blur(22px) saturate(40%); visibility: hidden; }
}
.wb-overlay canvas {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    pointer-events: none;
}
.wb-stage {
    position: absolute;
    inset: 0;
    pointer-events: none;
    overflow: hidden;
}
.wb-orb {
    position: absolute;
    border-radius: 50%;
    filter: blur(70px);
    will-change: transform;
    opacity: 0.9;
}
.wb-orb-a {
    top: -12%;
    right: -8%;
    width: min(52vw, 520px);
    height: min(52vw, 520px);
    background: rgba(225, 29, 72, 0.38);
    animation: wbDrift 16s ease-in-out infinite;
}
.wb-orb-b {
    bottom: -18%;
    left: -12%;
    width: min(58vw, 560px);
    height: min(58vw, 560px);
    background: rgba(159, 18, 57, 0.42);
    animation: wbDrift 22s ease-in-out infinite reverse;
}
.wb-orb-c {
    top: 28%;
    left: 38%;
    width: 240px;
    height: 240px;
    background: rgba(192, 38, 211, 0.22);
    animation: wbDrift 26s ease-in-out infinite;
}
.wb-orb-d {
    top: 12%;
    left: 8%;
    width: 280px;
    height: 280px;
    background: rgba(14, 165, 233, 0.18);
    animation: wbDrift 20s ease-in-out infinite;
}
.wb-orb-e {
    top: 6%;
    right: 24%;
    width: 220px;
    height: 220px;
    background: rgba(251, 191, 36, 0.12);
    animation: wbDrift 24s ease-in-out infinite reverse;
}
@keyframes wbDrift {
    0%, 100% { transform: translate(0, 0) scale(1); }
    50% { transform: translate(-32px, 22px) scale(1.08); }
}
.wb-floor {
    position: absolute;
    left: -25%;
    right: -25%;
    bottom: -34%;
    height: 78%;
    transform: perspective(1100px) rotateX(64deg);
    background-image:
        linear-gradient(rgba(125, 211, 252, 0.22) 1px, transparent 1px),
        linear-gradient(90deg, rgba(244, 63, 94, 0.16) 1px, transparent 1px);
    background-size: 52px 52px;
    mask-image: linear-gradient(to top, rgba(0, 0, 0, 0.72), transparent 82%);
    -webkit-mask-image: linear-gradient(to top, rgba(0, 0, 0, 0.72), transparent 82%);
    animation: wbGrid 28s linear infinite;
}
@keyframes wbGrid {
    to { background-position: 52px 52px, 52px 0; }
}
.wb-noise {
    position: absolute;
    inset: 0;
    opacity: 0.05;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='140' height='140'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='.85' numOctaves='2' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='140' height='140' filter='url(%23n)' opacity='.55'/%3E%3C/svg%3E");
}
.wb-scan {
    position: absolute;
    inset-inline: 0;
    height: 18%;
    background: linear-gradient(180deg, transparent, rgba(251, 113, 133, 0.08), transparent);
    animation: wbScan 5.4s ease-in-out infinite;
    mix-blend-mode: screen;
}
@keyframes wbScan {
    0% { top: -18%; opacity: 0; }
    18% { opacity: 1; }
    100% { top: 110%; opacity: 0; }
}
.wb-portal {
    position: absolute;
    inset: 0;
    display: grid;
    place-items: center;
    pointer-events: none;
}
.wb-ring {
    position: absolute;
    border-radius: 50%;
    box-sizing: border-box;
}
.wb-ring-a {
    width: min(78vw, 540px);
    height: min(78vw, 540px);
    border: 1px solid rgba(244, 63, 94, 0.28);
    box-shadow: 0 0 48px rgba(225, 29, 72, 0.18), inset 0 0 48px rgba(56, 189, 248, 0.06);
    animation: wbSpin 22s linear infinite;
}
.wb-ring-a::before,
.wb-ring-a::after {
    content: "";
    position: absolute;
    border-radius: 50%;
    border: 1px dashed rgba(167, 139, 250, 0.28);
}
.wb-ring-a::before { inset: 12%; }
.wb-ring-a::after {
    inset: 24%;
    border-style: solid;
    border-color: rgba(56, 189, 248, 0.18);
}
.wb-ring-b {
    width: min(56vw, 380px);
    height: min(56vw, 380px);
    border: 1px solid transparent;
    background:
        conic-gradient(from 0deg, transparent 0 58%, #fb7185, #e11d48, #38bdf8, transparent 78%) border-box;
    -webkit-mask: linear-gradient(#000 0 0) padding-box, linear-gradient(#000 0 0);
    -webkit-mask-composite: xor;
    mask: linear-gradient(#000 0 0) padding-box, linear-gradient(#000 0 0);
    mask-composite: exclude;
    border-width: 2px;
    animation: wbSpin 8s linear infinite reverse;
    filter: drop-shadow(0 0 12px rgba(244, 63, 94, 0.55));
}
.wb-ring-c {
    width: min(34vw, 230px);
    height: min(34vw, 230px);
    border: 1px solid rgba(251, 113, 133, 0.22);
    box-shadow: 0 0 40px rgba(244, 63, 94, 0.2);
    animation: wbPulse 3.2s ease-in-out infinite;
}
.wb-core {
    width: min(22vw, 150px);
    height: min(22vw, 150px);
    border-radius: 50%;
    background: radial-gradient(circle, rgba(251, 113, 133, 0.35), rgba(220, 38, 38, 0.08) 48%, transparent 70%);
    filter: blur(8px);
    animation: wbPulse 2.6s ease-in-out infinite;
}
@keyframes wbSpin { to { transform: rotate(360deg); } }
@keyframes wbPulse {
    0%, 100% { transform: scale(1); opacity: 0.7; }
    50% { transform: scale(1.08); opacity: 1; }
}
.wb-blob {
    position: absolute;
    width: min(70vw, 420px);
    height: min(70vw, 420px);
    background:
        radial-gradient(circle at 32% 28%, rgba(251, 113, 133, 0.38), transparent 46%),
        radial-gradient(circle at 70% 62%, rgba(56, 189, 248, 0.16), transparent 42%),
        radial-gradient(circle at 48% 50%, rgba(190, 18, 60, 0.22), transparent 58%);
    filter: blur(18px);
    border-radius: 42% 58% 40% 60% / 45% 42% 58% 55%;
    animation: wbBlob 9s ease-in-out infinite;
    opacity: 0.9;
}
@keyframes wbBlob {
    0%, 100% { border-radius: 42% 58% 40% 60% / 45% 42% 58% 55%; transform: rotate(0deg) scale(1); }
    50% { border-radius: 58% 42% 62% 38% / 52% 60% 40% 48%; transform: rotate(16deg) scale(1.08); }
}
.wb-glass {
    position: relative;
    z-index: 2;
    width: min(92vw, 460px);
    padding: 36px 28px 32px;
    border-radius: 28px;
    background: rgba(14, 14, 22, 0.58);
    backdrop-filter: blur(36px) saturate(180%);
    -webkit-backdrop-filter: blur(36px) saturate(180%);
    border: 1px solid rgba(255, 255, 255, 0.1);
    box-shadow:
        0 0 0 1px rgba(239, 68, 68, 0.22),
        0 0 48px rgba(220, 38, 38, 0.22),
        0 28px 80px rgba(0, 0, 0, 0.55),
        inset 0 1px 0 rgba(255, 255, 255, 0.16);
    overflow: hidden;
    animation: wbGlassIn 0.9s cubic-bezier(.19, 1, .22, 1) both;
}
@keyframes wbGlassIn {
    from { opacity: 0; transform: translateY(18px) scale(0.94); filter: blur(12px); }
    to { opacity: 1; transform: none; filter: none; }
}
.wb-glass::before {
    content: "";
    position: absolute;
    inset: -40% -20% auto;
    height: 70%;
    background: linear-gradient(180deg, rgba(255, 255, 255, 0.16), transparent);
    transform: rotate(-8deg);
    pointer-events: none;
}
.wb-sheen {
    position: absolute;
    inset: 0;
    background: linear-gradient(115deg, transparent 32%, rgba(255, 255, 255, 0.16) 48%, transparent 64%);
    transform: translateX(-120%);
    animation: wbSheen 3.4s ease-in-out 0.8s infinite;
    pointer-events: none;
}
@keyframes wbSheen {
    0%, 55% { transform: translateX(-120%); }
    100% { transform: translateX(120%); }
}
.wb-kicker {
    font-size: 0.72rem;
    letter-spacing: 0.38em;
    color: rgba(251, 113, 133, 0.72);
    text-transform: uppercase;
    margin-bottom: 10px;
    opacity: 0;
    animation: wbFadeUp 0.7s cubic-bezier(.19, 1, .22, 1) 0.15s forwards;
}
.wb-hello {
    font-size: clamp(2.6rem, 9vw, 4.6rem);
    font-weight: 800;
    letter-spacing: 0.12em;
    line-height: 1;
    margin: 0;
    color: #fff;
    -webkit-text-fill-color: #fff;
    text-shadow: 0 0 22px rgba(244, 63, 94, 0.55), 0 0 48px rgba(225, 29, 72, 0.28);
    opacity: 0;
    transform: translateY(22px) scale(0.96);
    animation: wbLetter 0.8s cubic-bezier(.19, 1, .22, 1) 0.12s forwards;
}
@keyframes wbLetter {
    to { opacity: 1; transform: none; }
}
.wb-namewrap {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: clamp(10px, 3vw, 20px);
    margin: 18px auto 14px;
    min-height: 2.1em;
}
.wb-orn {
    width: 8px;
    height: 8px;
    flex: 0 0 8px;
    border-radius: 1px;
    transform: rotate(45deg) scale(0);
    background: linear-gradient(135deg, #fb7185, #e11d48);
    box-shadow: 0 0 14px rgba(244, 63, 94, 0.7);
    opacity: 0;
    animation: wbOrn 0.7s cubic-bezier(.22, 1, .36, 1) 0.95s forwards;
}
@keyframes wbOrn {
    to { opacity: 1; transform: rotate(45deg) scale(1); }
}
.wb-firstname {
    font-size: clamp(1.7rem, 6.4vw, 2.8rem);
    font-weight: 800;
    line-height: 1.3;
    max-width: 100%;
    overflow-wrap: anywhere;
    color: #fecdd3;
    -webkit-text-fill-color: #fecdd3;
    background: linear-gradient(90deg, #e11d48, #fecdd3 28%, #fff 50%, #fb7185 78%, #be123c);
    background-size: 220% 100%;
    -webkit-background-clip: text;
    background-clip: text;
    filter: drop-shadow(0 0 16px rgba(244, 63, 94, 0.35));
    opacity: 0;
    transform: translateY(16px) scale(0.94);
    animation: wbNameIn 0.9s cubic-bezier(.19, 1, .22, 1) 0.95s forwards, wbShimmer 3s ease-in-out 2s infinite;
}
.wb-firstname.is-latin {
    letter-spacing: 0.12em;
    text-transform: uppercase;
    font-weight: 700;
    background: none;
    color: #fecdd3;
    -webkit-text-fill-color: #fecdd3;
    animation: wbNameIn 0.9s cubic-bezier(.19, 1, .22, 1) 0.95s forwards;
}
.wb-firstname.is-latin span {
    display: inline-block;
    opacity: 0;
    color: #fecdd3;
    -webkit-text-fill-color: #fecdd3;
    transform: translateY(16px);
    animation: wbLetter 0.55s cubic-bezier(.19, 1, .22, 1) forwards;
}
.wb-firstname.is-latin span:nth-child(1) { animation-delay: 1s; }
.wb-firstname.is-latin span:nth-child(2) { animation-delay: 1.1s; }
.wb-firstname.is-latin span:nth-child(3) { animation-delay: 1.2s; }
.wb-firstname.is-latin span:nth-child(4) { animation-delay: 1.3s; }
.wb-firstname.is-latin span:nth-child(5) { animation-delay: 1.4s; }
.wb-firstname.is-latin span:nth-child(6) { animation-delay: 1.5s; }
.wb-firstname.is-latin span:nth-child(7) { animation-delay: 1.6s; }
.wb-firstname.is-latin span:nth-child(8) { animation-delay: 1.7s; }
.wb-firstname.is-latin span:nth-child(9) { animation-delay: 1.8s; }
.wb-firstname.is-latin span:nth-child(10) { animation-delay: 1.9s; }
.wb-firstname.is-latin span:nth-child(n+11) { animation-delay: 2s; }
@keyframes wbNameIn {
    to { opacity: 1; transform: none; }
}
.wb-welcome {
    font-size: clamp(1.35rem, 4.6vw, 2.05rem);
    font-weight: 700;
    letter-spacing: 0.08em;
    background: linear-gradient(90deg, #9f1239, #fda4af 22%, #fff 50%, #fb7185 78%, #e11d48);
    background-size: 220% 100%;
    -webkit-background-clip: text;
    background-clip: text;
    color: transparent;
    opacity: 0;
    transform: translateY(16px);
    animation: wbWelcome 1s cubic-bezier(.19, 1, .22, 1) 1.7s forwards, wbShimmer 3s ease-in-out 2.8s infinite;
}
@keyframes wbWelcome {
    to { opacity: 1; transform: none; }
}
@keyframes wbShimmer {
    0%, 100% { background-position: 0% 50%; }
    50% { background-position: 100% 50%; }
}
.wb-mark {
    width: 92px;
    height: 2px;
    margin: 18px auto 0;
    border-radius: 99px;
    background: linear-gradient(90deg, transparent, #38bdf8, #fb7185, #e11d48, transparent);
    box-shadow: 0 0 12px rgba(244, 63, 94, 0.55);
    transform: scaleX(0);
    animation: wbMark 0.8s cubic-bezier(.22, 1, .36, 1) 2.15s forwards;
}
@keyframes wbMark {
    to { transform: scaleX(1); }
}
.wb-skip {
    position: absolute;
    bottom: max(22px, env(safe-area-inset-bottom));
    left: 50%;
    transform: translateX(-50%);
    z-index: 3;
    font-size: 0.78rem;
    letter-spacing: 0.18em;
    color: rgba(226, 232, 240, 0.42);
    opacity: 0;
    animation: wbWelcome 0.9s ease 2.6s forwards;
    white-space: nowrap;
}
@keyframes wbFadeUp {
    from { opacity: 0; transform: translateY(8px); }
    to { opacity: 1; transform: none; }
}
@media (max-width: 576px) {
    .wb-glass {
        padding: 28px 18px 72px;
        border-radius: 24px;
    }
    .wb-hello {
        font-size: clamp(2.2rem, 12vw, 3.1rem);
        letter-spacing: 0.1em;
    }
    .wb-firstname {
        font-size: clamp(1.45rem, 8vw, 2rem);
    }
    .wb-welcome {
        font-size: clamp(1.15rem, 5.6vw, 1.5rem);
    }
    .wb-ring-a {
        width: min(92vw, 340px);
        height: min(92vw, 340px);
    }
}
@media (prefers-reduced-motion: reduce) {
    .wb-overlay, .wb-overlay * {
        animation-duration: 0.01ms !important;
        animation-iteration-count: 1 !important;
        transition-duration: 0.01ms !important;
    }
    .wb-hello, .wb-welcome, .wb-firstname, .wb-firstname span, .wb-orn, .wb-mark, .wb-skip, .wb-kicker, .wb-glass {
        opacity: 1 !important;
        transform: none !important;
        filter: none !important;
    }
}
</style>
<div class="wb-overlay" id="wbOverlay" aria-hidden="true"<?= $wb_preview ? ' data-loop="1"' : '' ?>>
    <canvas id="wbCanvas"></canvas>
    <div class="wb-stage" aria-hidden="true">
        <span class="wb-orb wb-orb-a"></span>
        <span class="wb-orb wb-orb-b"></span>
        <span class="wb-orb wb-orb-c"></span>
        <span class="wb-orb wb-orb-d"></span>
        <span class="wb-orb wb-orb-e"></span>
        <div class="wb-floor"></div>
        <div class="wb-noise"></div>
        <div class="wb-scan"></div>
    </div>
    <div class="wb-portal" aria-hidden="true">
        <div class="wb-blob"></div>
        <div class="wb-ring wb-ring-a"></div>
        <div class="wb-ring wb-ring-b"></div>
        <div class="wb-ring wb-ring-c"></div>
        <div class="wb-core"></div>
    </div>
    <div class="wb-glass">
        <div class="wb-sheen"></div>
        <div class="wb-kicker">GHAZAL PANEL</div>
        <div class="wb-hello">Hello</div>
        <?php if ($wb_first !== ''): ?>
        <div class="wb-namewrap">
            <span class="wb-orn" aria-hidden="true"></span>
            <div class="wb-firstname<?= $wb_is_rtl ? '' : ' is-latin' ?>" dir="<?= $wb_is_rtl ? 'rtl' : 'ltr' ?>">
                <?php if ($wb_letters): ?>
                    <?php foreach ($wb_letters as $ch): ?>
                        <span><?= htmlspecialchars($ch, ENT_QUOTES, 'UTF-8') ?></span>
                    <?php endforeach; ?>
                <?php else: ?>
                    <?= htmlspecialchars($wb_first, ENT_QUOTES, 'UTF-8') ?>
                <?php endif; ?>
            </div>
            <span class="wb-orn" aria-hidden="true"></span>
        </div>
        <?php endif; ?>
        <div class="wb-welcome">Welcome Back</div>
        <div class="wb-mark"></div>
    </div>
    <div class="wb-skip">Tap to enter</div>
</div>
<script>
(function () {
    var overlay = document.getElementById('wbOverlay');
    var canvas = document.getElementById('wbCanvas');
    if (!overlay || !canvas) return;
    document.documentElement.classList.add('wb-lock');
    var ctx = canvas.getContext('2d');
    var particles = [];
    var links = [];
    var shoots = [];
    var running = true;
    var loop = overlay.getAttribute('data-loop') === '1';
    var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    function resize() {
        canvas.width = window.innerWidth;
        canvas.height = window.innerHeight;
    }
    resize();
    window.addEventListener('resize', resize);

    var count = Math.min(window.innerWidth < 576 ? 42 : 110, Math.floor(window.innerWidth / 9));
    var hues = ['251,113,133', '225,29,72', '56,189,248', '167,139,250', '226,232,240'];
    for (var i = 0; i < count; i++) {
        particles.push({
            x: Math.random() * canvas.width,
            y: Math.random() * canvas.height,
            r: Math.random() * 1.7 + 0.35,
            s: Math.random() * 0.35 + 0.08,
            a: Math.random() * 0.45 + 0.16,
            tw: Math.random() * Math.PI * 2,
            hue: hues[i % hues.length]
        });
    }

    function spawnShoot() {
        shoots.push({
            x: Math.random() * canvas.width,
            y: Math.random() * canvas.height * 0.45,
            vx: 7 + Math.random() * 6,
            vy: 3 + Math.random() * 3,
            life: 1,
            hue: Math.random() > 0.5 ? '251,113,133' : '56,189,248'
        });
    }

    var lastShoot = 0;
    function tick(ts) {
        if (!running) return;
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        links.length = 0;
        for (var i = 0; i < particles.length; i++) {
            var p = particles[i];
            p.y -= p.s;
            p.x += Math.sin((p.y + i) * 0.01) * 0.22;
            p.tw += 0.03;
            if (p.y < -8) {
                p.y = canvas.height + 8;
                p.x = Math.random() * canvas.width;
            }
            var alpha = p.a * (0.55 + 0.45 * Math.sin(p.tw));
            ctx.beginPath();
            ctx.fillStyle = 'rgba(' + p.hue + ',' + alpha + ')';
            ctx.arc(p.x, p.y, p.r, 0, Math.PI * 2);
            ctx.fill();
            if (p.r > 1.2) {
                var added = 0;
                for (var j = i + 1; j < particles.length && added < 3; j++) {
                    var q = particles[j];
                    if (q.r < 1.2) continue;
                    var dx = p.x - q.x;
                    var dy = p.y - q.y;
                    var d2 = dx * dx + dy * dy;
                    if (d2 < 100 * 100) {
                        links.push(p, q, 1 - Math.sqrt(d2) / 100);
                        added++;
                    }
                }
            }
        }
        ctx.lineWidth = 0.7;
        for (var k = 0; k < links.length; k += 3) {
            var a = links[k];
            var b = links[k + 1];
            var w = links[k + 2];
            ctx.strokeStyle = 'rgba(251,113,133,' + (0.12 * w) + ')';
            ctx.beginPath();
            ctx.moveTo(a.x, a.y);
            ctx.lineTo(b.x, b.y);
            ctx.stroke();
        }
        if (!reduce && ts - lastShoot > 2200) {
            spawnShoot();
            lastShoot = ts;
        }
        for (var s = shoots.length - 1; s >= 0; s--) {
            var sh = shoots[s];
            sh.x += sh.vx;
            sh.y += sh.vy;
            sh.life -= 0.018;
            if (sh.life <= 0) {
                shoots.splice(s, 1);
                continue;
            }
            ctx.strokeStyle = 'rgba(' + sh.hue + ',' + sh.life + ')';
            ctx.lineWidth = 1.4;
            ctx.beginPath();
            ctx.moveTo(sh.x, sh.y);
            ctx.lineTo(sh.x - sh.vx * 6, sh.y - sh.vy * 6);
            ctx.stroke();
        }
        requestAnimationFrame(tick);
    }
    requestAnimationFrame(tick);

    function closeOverlay() {
        if (!running) return;
        running = false;
        overlay.classList.add('is-leaving');
        document.documentElement.classList.remove('wb-lock');
        setTimeout(function () {
            if (loop) {
                location.reload();
                return;
            }
            if (overlay && overlay.parentNode) overlay.parentNode.removeChild(overlay);
        }, 900);
    }

    if (!loop) setTimeout(closeOverlay, 5200);
    overlay.addEventListener('click', closeOverlay);
    overlay.addEventListener('touchstart', closeOverlay, { passive: true });
    window.addEventListener('keydown', function onKey(e) {
        if (e.key === 'Escape' || e.key === 'Enter' || e.key === ' ') closeOverlay();
    });
})();
</script>
