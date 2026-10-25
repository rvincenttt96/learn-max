<?php
$title = 'Book Store';
$current_page = 'bookstore.php';
require_once 'layout.php';
require_once '../../includes/bookstore.php';

$catalog = bookstore_priced_catalog();
$postage_fee = bookstore_postage_fee();
$payment = bookstore_payment_info();
$orders = bookstore_student_orders((string)$username);
$draft_code = trim((string)($_GET['draft'] ?? ''));
$receipt_ok = trim((string)($_GET['receipt'] ?? '')) === '1';

function student_bookstore_badge_class(string $status): string
{
    if ($status === 'awaiting_receipt') {
        return 'is-wait';
    }
    if ($status === 'cancelled') {
        return 'is-off';
    }
    return 'is-ok';
}

function student_bookstore_render_order(array $order): void
{
    $status = (string)($order['status'] ?? '');
    $awaiting = $status === 'awaiting_receipt';
    $tracking = (string)($order['tracking_code'] ?? '');
    $items = is_array($order['items'] ?? null) ? $order['items'] : [];
    ?>
    <article class="bookstore-order <?= $awaiting ? 'is-wait' : '' ?>" id="order-<?= htmlspecialchars($tracking, ENT_QUOTES, 'UTF-8') ?>">
        <div class="bookstore-order-head">
            <h3><?= htmlspecialchars((string)($order['title'] ?? 'سفارش کتاب'), ENT_QUOTES, 'UTF-8') ?></h3>
            <span class="bookstore-badge <?= student_bookstore_badge_class($status) ?>"><?= htmlspecialchars(bookstore_student_status_label($status), ENT_QUOTES, 'UTF-8') ?></span>
        </div>
        <p class="bookstore-meta">
            کد: <span><?= htmlspecialchars($tracking !== '' ? $tracking : '—', ENT_QUOTES, 'UTF-8') ?></span><br>
            تحویل: <?= htmlspecialchars((string)($order['delivery_label'] ?? ''), ENT_QUOTES, 'UTF-8') ?><br>
            کتاب‌ها: <span><?= htmlspecialchars((string)($order['sell_total_label'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></span><br>
            هزینه پست: <span><?= htmlspecialchars((string)($order['postage_label'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></span><br>
            جمع واریز: <span><?= htmlspecialchars((string)($order['grand_total_label'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></span>
            <?php if (!empty($order['created_at'])): ?>
                <br><?= htmlspecialchars((string)$order['created_at'], ENT_QUOTES, 'UTF-8') ?>
            <?php endif; ?>
        </p>
        <?php if ($items): ?>
            <ul class="bookstore-items">
                <?php foreach ($items as $item): ?>
                    <li dir="ltr"><?= htmlspecialchars((string)($item['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
        <?php if ($awaiting): ?>
            <form class="bookstore-upload" data-receipt-form method="post" action="bookstore-order.php" enctype="multipart/form-data">
                <input type="hidden" name="action" value="receipt">
                <input type="hidden" name="tracking_code" value="<?= htmlspecialchars($tracking, ENT_QUOTES, 'UTF-8') ?>">
                <label for="receipt-<?= htmlspecialchars($tracking, ENT_QUOTES, 'UTF-8') ?>">عکس یا PDF رسید واریز</label>
                <input class="bookstore-file" id="receipt-<?= htmlspecialchars($tracking, ENT_QUOTES, 'UTF-8') ?>" type="file" name="receipt" accept="image/jpeg,image/png,image/webp,application/pdf" required>
                <button class="bookstore-upload-btn" type="submit">بارگذاری رسید و ثبت سفارش</button>
                <p class="bookstore-status" data-receipt-status hidden></p>
            </form>
        <?php elseif (!empty($order['has_receipt'])): ?>
            <p class="bookstore-hint">رسید واریز ثبت شده و سفارش برای بررسی دفتر آمده است.</p>
        <?php endif; ?>
    </article>
    <?php
}

$pay_note = trim((string)($payment['note'] ?? ''));
if ($pay_note === '') {
    $pay_note = 'بعد از پر کردن فرم، مبلغ را واریز کنید و عکس رسید را در تاریخچه همین صفحه بارگذاری کنید. تا وقتی رسید نیاید سفارش ثبت نمی‌شود.';
}
?>
<link href="../../assets/css/student/student-bookstore.css?v=bs2" rel="stylesheet">

<div class="bookstore-page">
    <?php if ($draft_code !== ''): ?>
        <div class="bookstore-banner">سفارش <?= htmlspecialchars($draft_code, ENT_QUOTES, 'UTF-8') ?> در انتظار رسید است. مبلغ را واریز کنید و رسید را از تاریخچه بارگذاری کنید.</div>
    <?php elseif ($receipt_ok): ?>
        <div class="bookstore-banner">رسید ثبت شد. سفارش برای بررسی دفتر کتاب‌فروشی ارسال شد.</div>
    <?php endif; ?>

    <section class="bookstore-pay">
        <h2>واریز کتاب</h2>
        <p class="bookstore-lead"><?= htmlspecialchars($pay_note, ENT_QUOTES, 'UTF-8') ?></p>
        <dl>
            <?php if (!empty($payment['card'])): ?>
                <dt>شماره کارت</dt>
                <dd dir="ltr"><?php
                    $card_digits = preg_replace('/\D+/', '', (string)$payment['card']);
                    echo htmlspecialchars($card_digits !== '' ? trim(chunk_split($card_digits, 4, ' ')) : (string)$payment['card'], ENT_QUOTES, 'UTF-8');
                ?></dd>
            <?php endif; ?>
            <?php if (!empty($payment['bank'])): ?>
                <dt>بانک</dt>
                <dd><?= htmlspecialchars($payment['bank'], ENT_QUOTES, 'UTF-8') ?></dd>
            <?php endif; ?>
            <?php if (!empty($payment['holder'])): ?>
                <dt>به نام</dt>
                <dd><?= htmlspecialchars($payment['holder'], ENT_QUOTES, 'UTF-8') ?></dd>
            <?php endif; ?>
            <dt>هزینه پست</dt>
            <dd><?= htmlspecialchars($postage_fee > 0 ? bookstore_format_toman($postage_fee) : 'رایگان', ENT_QUOTES, 'UTF-8') ?></dd>
        </dl>
        <?php if (empty($payment['card'])): ?>
            <p class="bookstore-hint">شماره کارت را از آموزشگاه بگیرید. بعد از واریز، رسید را همین‌جا بگذارید.</p>
        <?php endif; ?>
    </section>

    <div class="bookstore-grid">
        <section class="bookstore-panel" aria-labelledby="bookstoreFormTitle">
            <h2 id="bookstoreFormTitle">فرم سفارش</h2>
            <div class="bookstore-steps" aria-hidden="true">
                <span class="bookstore-step is-on" data-step="1">۱. سطح</span>
                <span class="bookstore-step" data-step="2">۲. کتاب‌ها</span>
                <span class="bookstore-step" data-step="3">۳. تحویل</span>
            </div>
            <label for="bookstorePackageSelect">سطح کلاس</label>
            <select id="bookstorePackageSelect">
                <option value="">انتخاب کنید</option>
                <?php foreach ($catalog as $package_code => $package_meta): ?>
                    <option value="<?= htmlspecialchars($package_code, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($package_meta['title'], ENT_QUOTES, 'UTF-8') ?></option>
                <?php endforeach; ?>
            </select>
            <p class="bookstore-hint">بعد از ثبت فرم، سفارش در حالت «در انتظار رسید» می‌ماند. مبلغ کتاب و در صورت ارسال پستی هزینه پست را واریز کنید، بعد رسید را بارگذاری کنید تا سفارش ثبت شود.</p>
            <div class="bookstore-mode" id="bookstoreMode" hidden>
                <button type="button" class="bookstore-mode-btn is-active" data-mode="all" id="bookstoreModeAll">همه کتاب‌ها</button>
                <button type="button" class="bookstore-mode-btn" data-mode="custom" id="bookstoreModeCustom">فقط بعضی‌ها</button>
            </div>
            <div class="bookstore-books" id="bookstoreBooks" hidden>
                <span class="bookstore-books-label">کتاب‌ها و قیمت فروش</span>
                <div id="bookstoreBookList"></div>
            </div>
            <div class="bookstore-total is-soft" id="bookstoreBooksTotal" hidden>
                <span>جمع کتاب‌ها</span>
                <strong id="bookstoreBooksTotalValue">—</strong>
            </div>
            <div class="bookstore-total is-soft" id="bookstorePostage" hidden>
                <span>هزینه پست</span>
                <strong id="bookstorePostageValue">—</strong>
            </div>
            <div class="bookstore-total" id="bookstoreTotal" hidden>
                <span>جمع قابل واریز</span>
                <strong id="bookstoreTotalValue">—</strong>
            </div>
            <div class="bookstore-delivery" id="bookstoreDelivery" hidden>
                <span class="bookstore-books-label">چطور تحویل بگیرید؟</span>
                <div class="bookstore-mode">
                    <button type="button" class="bookstore-mode-btn is-active" data-delivery="pickup" id="bookstoreDeliveryPickup">از آموزشگاه</button>
                    <button type="button" class="bookstore-mode-btn" data-delivery="post" id="bookstoreDeliveryPost">ارسال با پست</button>
                </div>
            </div>
            <div class="bookstore-post-fields" id="bookstorePostFields" hidden>
                <label for="bookstoreAddress">آدرس کامل</label>
                <textarea id="bookstoreAddress" rows="3" placeholder="استان، شهر، خیابان، پلاک، واحد"></textarea>
                <label for="bookstorePostal">کد پستی ده‌رقمی</label>
                <input id="bookstorePostal" class="bookstore-input" dir="ltr" inputmode="numeric" maxlength="10" placeholder="1234567890" autocomplete="postal-code">
            </div>
            <button type="button" class="bookstore-submit" id="bookstoreSubmitBtn" hidden>ثبت فرم سفارش</button>
            <p class="bookstore-status" id="bookstoreStatus" hidden></p>
        </section>

        <section class="bookstore-panel" id="history">
            <h2>تاریخچه سفارش‌ها</h2>
            <div class="bookstore-history" id="bookstoreHistory">
                <?php if (!$orders): ?>
                    <div class="bookstore-empty">هنوز سفارشی ندارید. فرم را پر کنید تا اینجا دیده شود.</div>
                <?php else: ?>
                    <?php foreach ($orders as $order): ?>
                        <?php student_bookstore_render_order($order); ?>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>
    </div>
</div>

<script>
(function () {
    var select = document.getElementById('bookstorePackageSelect');
    var submitBtn = document.getElementById('bookstoreSubmitBtn');
    var statusEl = document.getElementById('bookstoreStatus');
    var modeBox = document.getElementById('bookstoreMode');
    var booksBox = document.getElementById('bookstoreBooks');
    var bookList = document.getElementById('bookstoreBookList');
    var modeAll = document.getElementById('bookstoreModeAll');
    var modeCustom = document.getElementById('bookstoreModeCustom');
    var booksTotalBox = document.getElementById('bookstoreBooksTotal');
    var booksTotalValue = document.getElementById('bookstoreBooksTotalValue');
    var postageBox = document.getElementById('bookstorePostage');
    var postageValue = document.getElementById('bookstorePostageValue');
    var totalBox = document.getElementById('bookstoreTotal');
    var totalValue = document.getElementById('bookstoreTotalValue');
    var pickupBtn = document.getElementById('bookstoreDeliveryPickup');
    var postBtn = document.getElementById('bookstoreDeliveryPost');
    var postFields = document.getElementById('bookstorePostFields');
    var addressEl = document.getElementById('bookstoreAddress');
    var postalEl = document.getElementById('bookstorePostal');
    var deliveryBox = document.getElementById('bookstoreDelivery');
    var catalog = <?= json_encode($catalog, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    var postageFee = <?= (int)$postage_fee ?>;
    var mode = 'all';
    var delivery = 'pickup';
    var root = document.querySelector('.bookstore-page');
    if (!root || !select) return;

    function setStatus(message, kind) {
        if (!statusEl) return;
        statusEl.hidden = !message;
        statusEl.textContent = message || '';
        statusEl.classList.remove('is-ok', 'is-err');
        if (kind) statusEl.classList.add(kind);
    }

    function bookTitle(book) {
        if (typeof book === 'string') return book;
        return (book && book.title) ? book.title : '';
    }

    function formatToman(amount) {
        if (amount === null || amount === undefined || amount === '') return '—';
        return Number(amount).toLocaleString('en-US') + ' تومان';
    }

    function currentBooks() {
        var code = (select && select.value) || '';
        return (catalog[code] && catalog[code].books) ? catalog[code].books : [];
    }

    function setDelivery(next) {
        delivery = next === 'post' ? 'post' : 'pickup';
        if (pickupBtn) pickupBtn.classList.toggle('is-active', delivery === 'pickup');
        if (postBtn) postBtn.classList.toggle('is-active', delivery === 'post');
        if (postFields) postFields.hidden = delivery !== 'post';
        setStep((select && select.value) ? 3 : 1);
        updateTotal();
    }

    function digitsOnly(value) {
        return String(value || '').replace(/[۰-۹٠-٩]/g, function (ch) {
            var map = {
                '۰':'0','۱':'1','۲':'2','۳':'3','۴':'4','۵':'5','۶':'6','۷':'7','۸':'8','۹':'9',
                '٠':'0','١':'1','٢':'2','٣':'3','٤':'4','٥':'5','٦':'6','٧':'7','٨':'8','٩':'9'
            };
            return map[ch] || ch;
        }).replace(/\D+/g, '');
    }

    function selectedRows() {
        var books = currentBooks();
        if (mode !== 'custom' || books.length <= 1) {
            return books.slice();
        }
        var picked = [];
        if (!bookList) return picked;
        bookList.querySelectorAll('input[type="checkbox"]:checked').forEach(function (el) {
            for (var i = 0; i < books.length; i++) {
                if (bookTitle(books[i]) === el.value) {
                    picked.push(books[i]);
                    break;
                }
            }
        });
        return picked;
    }

    function selectedBooks() {
        return selectedRows().map(bookTitle).filter(Boolean);
    }

    function updateTotal() {
        var rows = selectedRows();
        var sum = 0;
        var priced = 0;
        rows.forEach(function (book) {
            if (book && book.sell_price != null) {
                sum += Number(book.sell_price) || 0;
                priced++;
            }
        });
        var postage = delivery === 'post' ? (Number(postageFee) || 0) : 0;
        var postageText = delivery === 'post' ? (postage ? formatToman(postage) : 'رایگان') : 'ندارد (تحویل حضوری)';
        if (booksTotalBox) booksTotalBox.hidden = rows.length === 0;
        if (booksTotalValue) booksTotalValue.textContent = priced ? formatToman(sum) : '—';
        if (postageBox) postageBox.hidden = rows.length === 0;
        if (postageValue) postageValue.textContent = postageText;
        if (totalBox) totalBox.hidden = rows.length === 0;
        if (totalValue) totalValue.textContent = (priced || postage) ? formatToman(sum + postage) : '—';
    }

    function syncChecks() {
        if (!bookList) return;
        var custom = mode === 'custom' && currentBooks().length > 1;
        bookList.querySelectorAll('input[type="checkbox"]').forEach(function (el) {
            el.disabled = !custom;
            if (!custom) el.checked = true;
        });
        updateTotal();
    }

    function setMode(next) {
        mode = next === 'custom' ? 'custom' : 'all';
        if (modeAll) modeAll.classList.toggle('is-active', mode === 'all');
        if (modeCustom) modeCustom.classList.toggle('is-active', mode === 'custom');
        syncChecks();
    }

    function setStep(n) {
        root.querySelectorAll('.bookstore-step').forEach(function (el) {
            el.classList.toggle('is-on', Number(el.getAttribute('data-step')) <= n);
        });
    }

    function renderBooks() {
        var books = currentBooks();
        var hasPkg = !!(select && select.value);
        var canChoose = books.length > 1;
        if (modeBox) modeBox.hidden = !canChoose;
        if (booksBox) booksBox.hidden = !hasPkg || books.length === 0;
        if (deliveryBox) deliveryBox.hidden = !hasPkg;
        if (submitBtn) submitBtn.hidden = !hasPkg;
        if (!hasPkg && postFields) postFields.hidden = true;
        setStep(hasPkg ? (delivery === 'post' ? 3 : 2) : 1);
        if (!canChoose) mode = 'all';
        if (!bookList) return;
        bookList.innerHTML = '';
        books.forEach(function (book) {
            var title = bookTitle(book);
            var label = document.createElement('label');
            label.className = 'bookstore-book';
            var input = document.createElement('input');
            input.type = 'checkbox';
            input.name = 'bookstore-book';
            input.value = title;
            input.checked = true;
            var titleEl = document.createElement('span');
            titleEl.className = 'bookstore-book-title';
            titleEl.dir = 'ltr';
            titleEl.textContent = title;
            var priceEl = document.createElement('span');
            priceEl.className = 'bookstore-book-price';
            priceEl.textContent = (book && book.sell_label) ? book.sell_label : formatToman(book && book.sell_price);
            label.appendChild(titleEl);
            label.appendChild(priceEl);
            label.appendChild(input);
            bookList.appendChild(label);
        });
        setMode(canChoose ? mode : 'all');
        updateTotal();
    }

    if (select) select.addEventListener('change', renderBooks);
    if (modeAll) modeAll.addEventListener('click', function () { setMode('all'); });
    if (modeCustom) modeCustom.addEventListener('click', function () { setMode('custom'); });
    if (pickupBtn) pickupBtn.addEventListener('click', function () { setDelivery('pickup'); });
    if (postBtn) postBtn.addEventListener('click', function () { setDelivery('post'); });
    if (bookList) bookList.addEventListener('change', updateTotal);
    renderBooks();
    setDelivery('pickup');

    if (submitBtn) {
        submitBtn.addEventListener('click', function () {
            var packageCode = (select.value || '').trim();
            if (!packageCode) {
                setStatus('لطفاً یک پکیج انتخاب کنید.', 'is-err');
                select.focus();
                return;
            }
            var books = selectedBooks();
            if (mode === 'custom' && currentBooks().length > 1 && books.length === 0) {
                setStatus('حداقل یک کتاب را انتخاب کنید.', 'is-err');
                return;
            }
            var address = addressEl ? addressEl.value.trim() : '';
            var postal = digitsOnly(postalEl ? postalEl.value : '');
            if (delivery === 'post') {
                if (address.length < 8) {
                    setStatus('برای ارسال پستی، آدرس کامل را وارد کنید.', 'is-err');
                    if (addressEl) addressEl.focus();
                    return;
                }
                if (!/^\d{10}$/.test(postal)) {
                    setStatus('کد پستی باید ۱۰ رقم باشد.', 'is-err');
                    if (postalEl) postalEl.focus();
                    return;
                }
            }
            submitBtn.disabled = true;
            setStatus('در حال ثبت فرم…', '');
            fetch('bookstore-order.php', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    package: packageCode,
                    books: books,
                    delivery_type: delivery,
                    address: delivery === 'post' ? address : '',
                    postal_code: delivery === 'post' ? postal : ''
                })
            }).then(function (res) {
                return res.json().then(function (data) {
                    return { ok: res.ok, data: data || {} };
                }).catch(function () {
                    return { ok: false, data: { error: 'پاسخ نامعتبر بود.' } };
                });
            }).then(function (result) {
                if (result.ok && result.data.ok) {
                    var code = result.data.tracking_code || '';
                    window.location.href = 'bookstore.php?draft=' + encodeURIComponent(code) + '#history';
                    return;
                }
                setStatus(result.data.error || 'ثبت فرم انجام نشد.', 'is-err');
                submitBtn.disabled = false;
            }).catch(function () {
                setStatus('ارتباط با سرور برقرار نشد.', 'is-err');
                submitBtn.disabled = false;
            });
        });
    }

    document.querySelectorAll('[data-receipt-form]').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            var btn = form.querySelector('button[type="submit"]');
            var note = form.querySelector('[data-receipt-status]');
            var data = new FormData(form);
            if (btn) btn.disabled = true;
            if (note) {
                note.hidden = false;
                note.textContent = 'در حال بارگذاری رسید…';
                note.classList.remove('is-ok', 'is-err');
            }
            fetch('bookstore-order.php', {
                method: 'POST',
                credentials: 'same-origin',
                body: data
            }).then(function (res) {
                return res.json().then(function (payload) {
                    return { ok: res.ok, data: payload || {} };
                }).catch(function () {
                    return { ok: false, data: { error: 'پاسخ نامعتبر بود.' } };
                });
            }).then(function (result) {
                if (result.ok && result.data.ok) {
                    window.location.href = 'bookstore.php?receipt=1#history';
                    return;
                }
                if (note) {
                    note.classList.add('is-err');
                    note.textContent = result.data.error || 'بارگذاری رسید انجام نشد.';
                }
                if (btn) btn.disabled = false;
            }).catch(function () {
                if (note) {
                    note.classList.add('is-err');
                    note.textContent = 'ارتباط با سرور برقرار نشد.';
                }
                if (btn) btn.disabled = false;
            });
        });
    });
})();
</script>
<?php require_once 'layout-end.php'; ?>
