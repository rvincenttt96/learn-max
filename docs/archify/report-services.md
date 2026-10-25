# گزارش سرویس‌ها و فلوهای غزال

نقشه تعاملی: [ghazal-services.architecture.html](ghazal-services.architecture.html)

کلید مشترک همه سیستم‌ها **شماره پرونده** است: `users.username` پنل = `students.studentCode` حسابداری = `user_login` وردپرس = `student_code` سفارش کتاب.

```
زبان‌آموز / مدرس / پذیرش
        │ HTTPS
        ▼
 پنل آموزشی (panel.ghazalify.com) ──PDO──► ghazali1_panel
        │
        ├── accounting_pdo ──► ghazali1_Acc
        ├── X-Ghazal-Books ──► books.ghazalify.com ──► MySQL فروشگاه
        ├── X-Ghazal-Identity ──► ghazalify.com (کاربر سایت)
        ├── pnlapi.alocom.co (اتاق آنلاین)
        └── api.iranpayamak.com (پیامک الگو)
```

پذیرش جداگانه از SPA حسابداری (`acccounting.ghazalify.com`) همان `ghazali1_Acc` را می‌خواند و می‌نویسد.

---

## ۱. سرویس‌ها

### پنل آموزشی — `panel.ghazalify.com`

نقش: ورود، ترم و کلاس، حضور، هویت، سفارش کتاب، کرون‌ها.

| مسیر / فایل | کار |
|---|---|
| `index.php` → `login-process.php` | ورود؛ سشن `user_id` / `role` |
| `dashboard/student/` | کلاس‌ها، BookStore، ورود آنلاین |
| `dashboard/teacher/` | حضور، تکلیف، طرح جلسه |
| `dashboard/supervisor/` | ترم، کلاس‌بندی، دانش‌آموزان، رول‌کال قابل ادیت، Study Plan، SMS تأخیر، EAS |
| `dashboard/student/profile.php` | My Profile: نام، شماره تماس، شماره پدر، شماره مادر |
| `identity-ensure.php` | هوک Acc با هدر `X-Ghazal-Identity` |
| `includes/identity-sync.php` | هسته هویت |
| `includes/parent-phones.php` | ستون و اعتبارسنجی شماره پدر/مادر |
| `includes/bookstore.php` | ثبت سفارش پکیج |
| `includes/rollcall.php` | حضور، ادیت سوپروایزر، پیامک غیبت / تأخیر / Study Plan |
| `includes/alocom.php` | اتاق و ترافیک الوکام |
| `includes/sms.php` | `sendFarazSMS` |
| `cron_alocom_rollcall.php` | حضور آنلاین از ترافیک الوکام |
| `cron_rollcall_alert.php` | هشدار نبودن رول‌کال |

ورود واقعی (`includes/db.php`, `includes/auth.php`, `login-process.php`) روی سرور است، نه در گیت.

### حسابداری — `acccounting.ghazalify.com`

نقش: ثبت‌نام، شهریه، رسید، سطوح، حقوق، هزینه، سفارش‌های تأییدشده کتاب. SPA ری‌اکت در `accounting/src/App.tsx`؛ API در `accounting/api.php` با بازنویسی `/api/*`. صفحه Bookstore با overlay جدا (`accounting/bookstore.js`) است.

نقش‌ها: `manager` و `reception`. ورود با جدول `users` خود Acc است، جدا از پنل.

| مسیر | کار |
|---|---|
| `POST /api/login` | ورود پذیرش |
| `GET/POST/PATCH /api/students` | ردیف شهریه هر ترم |
| `POST /api/receipts` | ثبت پرداخت |
| `GET /api/receipts/{studentId}` | تاریخچه رسید |
| `GET/POST /api/levels` | شهریه سطح |
| `GET/POST /api/terms` | ترم Acc |
| `GET /api/panel_classes` | کلاس‌های باز پنل برای ثبت‌نام |
| `GET/POST /api/salaries` و `/api/expenses` | حقوق و هزینه |
| `GET /api/bookstore_orders` | سفارش‌های تأییدشده/خریداری‌شده فروشگاه کتاب |
| `POST /api/bookstore_orders/{id}` | تیک تحویل کتاب همان سفارش |
| `GET /api/bookstore_receipt/{id}` | عکس رسید واریز همان سفارش |

بعد از ساخت دانش‌آموز، API با `ghazal_identity_sync` (`accounting/identity-client.php`) پنل و سایت و الوکام را هم می‌سازد.

چاپ فاکتور در مرورگر است (`printHTML`): شهریه سطح، تخفیف، کتاب، مصاحبه ۲۵۰ هزار، پرداخت‌ها. لوگوی آموزشگاه بالای فاکتور است. رسید جدا در جدول `receipts` است؛ «چاپ فاکتور» صورتحساب کامل است نه فاکتور کتاب تنها.

### فروشگاه کتاب — `books.ghazalify.com`

نقش: سفارش پنل، لیست سفارش، صفحه قیمت‌ها.

| مسیر | کار |
|---|---|
| `POST /api/panel-order.php` | هوک پنل؛ هدر `X-Ghazal-Books` |
| `GET/POST /api/acc-orders.php` | فهرست Acc و ثبت تیک تحویل کتاب |
| `GET /api/acc-receipt.php` | رسید همان سفارش برای Acc |
| `orders.php` | لیست سفارش‌های pending و بعدی |
| `prices.php` | قیمت فروش روی قیمت زنده اینجا بوک |

توکن هوک فقط روی سرور است (`includes/bookstore-config.php` پنل و `config/panel-hook.php` فروشگاه).

### سایت غزال — `ghazalify.com`

وردپرس. `wordpress/ghazal-identity-ensure.php` کاربر سایت می‌سازد: لاگین = شماره پرونده، ایمیل `{code}@students.ghazalify.com`. OTP ورود سایت با فراز، پترن `8G3FF2Yo7M`. خریدار کتاب در سایت حساب پنل نمی‌گیرد.

### الوکام — `pnlapi.alocom.co`

اتاق آنلاین، ثبت عضو، ترافیک ورود. پنل رویداد می‌سازد (`alocom_ensure_class_event`)، لینک را در `classes.online_link` می‌گذارد، و دانش‌آموز را با توکن در رویداد ثبت می‌کند. ورود دانش‌آموز باید از دکمه پنل باشد؛ لینک عمومی رویداد بدون توکن رد می‌شود. رمز الوکام معمولاً همان شماره پرونده است؛ اگر سیستم نپذیرفت `Ghazal` + شماره پرونده.

### فراز / ایران‌پیامک — `api.iranpayamak.com`

فقط ارسال الگوی SMS. `POST /ws/v1/sms/pattern`، خط `90008361`. متن پیام در MySQL غزال ذخیره نمی‌شود.

### اینجا بوک — `injabook.com`

قیمت عمده از WooCommerce Store API: `/wp-json/wc/store/v1/products`. کش حدود ۲۰ دقیقه.

### Mattermost — `co.ghazalify.com`

هشدار غیبت و «رول‌کال ثبت نشده» از کرون و `rollcall_save_and_notify`.

میزبان هر سه سایت و سه دیتابیس: DirectAdmin، کاربر `ghazali1`.

---

## ۲. فلوها

### ورود پنل

1. `index.php` اگر سشن باشد به `dashboard/{role}/` می‌رود.
2. فرم به `login-process.php` می‌رود؛ کاربر از `ghazali1_panel.users` خوانده می‌شود.
3. نقش: `student` | `teacher` | `supervisor`.

### ورود حسابداری

1. SPA به `POST /api/login`.
2. `accounting/api.php` از جدول `users` Acc می‌خواند.
3. سشن جدا از پنل.

### ثبت دانش‌آموز و هویت (`identity_sync_student`)

از پنل (`save-user.php`, Students Information, ایمپورت) یا از Acc (`POST /api/students`).

1. شماره پرونده: موجود، یا `identity_next_code` (کف حدود ۱۱۰۱، زیر ۵۰۰۰).
2. `identity_ensure_panel_student` → `users` پنل؛ یوزرنیم و رمز پیش‌فرض = شماره پرونده.
3. `identity_wp_request` → کاربر وردپرس.
4. `identity_ensure_acc_student` → ردیف Acc روی ترم فعال با `status='pending'` و شهریه صفر.
5. `identity_ensure_alocom_student` → کاربر الوکام.
6. `identity_maybe_welcome_sms` → خوش‌آمد فراز؛ یک‌بار در `student_welcome_sms_log`.

تا وقتی کلاس ندارد: Acc «ثبت اولیه»، شهریه صفر، ورود اتاق آنلاین بسته.

### کلاس‌بندی و شهریه

1. سوپروایزر در `terms.php` / `placement.php` / `save-class.php` یا پذیرش Acc با `panel_class_id`.
2. نوشتن `classes` و `class_students`.
3. `identity_sync_student_classes` → `identity_sync_student_for_class`.
4. شهریه سطح از Acc `levels` با `accounting_match_level`.
5. `identity_rebuild_tuition`:

```
totalPayable = fee سطح − تخفیف + bookPrice + (مصاحبه؟ ۲۵۰٬۰۰۰ : ۰)
status = pending اگر جمع و پرداخت صفر؛ وگرنه paid اگر پرداخت ≥ جمع؛ وگرنه unpaid
```

6. کلاس آنلاین: ساخت رویداد الوکام، ثبت مدرس و زبان‌آموزان، ذخیره لینک. حذف کلاس رویداد را هم پاک می‌کند.
7. ترم پنل با `term_mirror_accounting` ترم هم‌نام Acc می‌سازد (`terms.accounting_term_id`).

هر دانش‌آموز **هر ترم** یک ردیف Acc جدا دارد. پرداخت ترم قبل دست نمی‌خورد.

### حضور حضوری

معلم در `attendance-students.php` ذخیره می‌کند → `save-attendance.php` → `rollcall_save_and_notify` → upsert جدول `attendance`. غیبت تازه → پترن فراز `aX3d0ttYHc` و Mattermost. تکراری با `attendance_sms_log` جلوگیری می‌شود.

سوپروایزر در صفحه Roll Call مودال جلسه را باز می‌کند و وضعیت هر نفر را حاضر ↔ غایب می‌کند (`ajax-update-attendance.php` → `rollcall_supervisor_set_status`). گذار تازه به غایب همان پیامک غیبت را به `users.phone` می‌زند؛ برگشت به حاضر لاگ پیامک آن جلسه را پاک می‌کند.

### Study Plan

صفحه `dashboard/supervisor/study-plan.php` در سایدبار سوپروایزر. ارسال تکی یا گروهی → `study-plan-send.php` → `rollcall_notify_study_plan`. پترن فراز `zy6PWPVSVS` (active) با متغیر `family` (فامیل). مقصد همان `users.phone` است نه شماره پدر/مادر. تکرار در `study_plan_sms_log`.

### حضور آنلاین

کرون از **۳۰ دقیقه بعد از شروع تا پایان جلسه** ترافیک الوکام را با لیست ثبت‌نام‌شده تطبیق می‌دهد و همان `rollcall_save_and_notify` را با `source=alocom` می‌زند. کلاس خصوصی که لینک الوکام دارد هم در همین اسکن است. ذخیره خودکار فقط وقتی است که حداقل یک زبان‌آموز در ترافیک اتاق دیده شود؛ ورود مدرس به‌تنهایی همه را غایب نمی‌کند. اگر آن تیک هنوز شاگردی نباشد، تیک بعدی تا پایان جلسه دوباره امتحان می‌کند (حتی اگر قبلاً همه غایب ذخیره شده باشند). اگر تا دقیقه ۳۰–۴۰ هیچ ردیف حضور نباشد، `cron_rollcall_alert.php` یک‌بار به Mattermost خبر می‌دهد (`rollcall_missing_alerts`).

### پیامک تأخیر

سوپروایزر `ajax-send-late.php` → `rollcall_notify_late` → پترن `BY86nXCqQY` → `attendance_late_sms_log`.

### ورود اتاق آنلاین

`join-online.php`: Acc همان ترم کلاس باید `status=paid` باشد — الان ترم در حال تدریس Fall 1 (کلاس خصوصی مستثنی است). بعد `alocom_enroll_panel_student_in_event` و ریدایرکت لینک توکن‌دار. بدهکاری ترم دیگر، ترم جاری را نمی‌بندد.

سوپروایزرهای الوکام `m1377`، `matin`، `samira`، `saghar`، `maral` روی همه کلاس آنلاین ترم تدریس نقش مدیر رویداد دارند. الوکام این نقش را `teacher` می‌نامد. مدرس کلاس جدا `admin` رویداد است. رویداد با `start_by_admin = 0` ساخته می‌شود.

### سفارش کتاب

1. صفحه `bookstore.php` پنل دانش‌آموز → `bookstore-order.php` → `bookstore_submit_package` با وضعیت `awaiting_receipt`.
2. کل پکیج سطح، یا زیرمجموعه کتاب‌های همان پکیج.
3. تحویل: `pickup` حضوری، یا `post` با آدرس (≥۸ کاراکتر) و کد پستی ۱۰ رقمی؛ هزینه پست از تنظیم فروشگاه (`postage_fee`، لایو ۱۵۰٬۰۰۰ تومان) به جمع اضافه می‌شود.
4. `POST https://books.ghazalify.com/api/panel-order.php` با `X-Ghazal-Books`.
5. فروشگاه در تراکنش: `orders` با `source='panel'` و `status='awaiting_receipt'` + `order_items`.
6. دانش‌آموز رسید را بارگذاری می‌کند → `api/panel-receipt.php` → `status='pending'` و فایل در `uploads/receipts`. شماره کارت واریز از `payment_card` فروشگاه است (لایو `6219861926801571`).
7. پنل کتابفروشی رسید را در جزئیات سفارش می‌بیند. مبلغ روی فیش Acc نمی‌رود.
8. بعد از تأیید دفتر (`approved`) و خرید اینجا (`ordered`)، Acc همان سفارش‌ها را در صفحه Bookstore می‌خواند (`GET /api/bookstore_orders` → `api/acc-orders.php`). تیک دستی کتاب در Registration سر جایش است.

قیمت فروش (`bookstore_sell_price`):

| نوع | قیمت |
|---|---|
| پمفلت و Phonics | ۷۵۰٬۰۰۰ |
| Jingle Bells | ۲۳۰٬۰۰۰ |
| استوری‌بوک | قیمت اینجا × ۳، سقف به مضرب ۱۰ هزار |
| بقیه | قیمت اینجا × ۱٫۳۵، همان گرد کردن |

فاکتور Acc همان صورتحساب شهریه است به‌اضافه خط کتاب؛ فاکتور کتاب جدا نداریم.

### چاپ فاکتور Acc

- «ذخیره و چاپ»: `POST /api/receipts` بعد `printHTML(student, مبلغ همین پرداخت, تاریخ)`.
- «چاپ فاکتور»: `printHTML` با مبلغ ۰؛ رسید جعلی ساخته نمی‌شود.
- اقلام چاپ: شهریه، تخفیف، کتاب، مصاحبه، پرداخت‌شده، مانده. مهر آموزشگاه؛ بدون امضای دانش‌آموز.

### سطل زباله Acc

حذف دانش‌آموز از Acc، پنل را هم با `identity_delete_panel_student` پاک می‌کند.

---

## ۳. پیامک‌ها

| نوع | تابع | پترن پیش‌فرض | متغیر | جدول جلوگیری از تکرار | مقصد |
|---|---|---|---|---|---|
| خوش‌آمد | `identity_maybe_welcome_sms` | `RMej2aBV8v` | `name`, `code` | `student_welcome_sms_log` | `users.phone` |
| غیبت | `rollcall_send_absence_sms` | `aX3d0ttYHc` | `name`, `date` | `attendance_sms_log` | `users.phone` |
| تأخیر | `rollcall_send_late_sms` | `BY86nXCqQY` | نام و تاریخ | `attendance_late_sms_log` | `users.phone` |
| Study Plan | `rollcall_send_study_plan_sms` | `zy6PWPVSVS` | `family` | `study_plan_sms_log` | `users.phone` |
| OTP سایت | پلاگین وردپرس | `8G3FF2Yo7M` | `code` | کش پترن وردپرس حدود ۱۰ دقیقه | شماره ورود سایت |

ستون جدول: نوع، تابع، پترن، متغیر، لاگ تکرار، **مقصد**. شماره پدر و مادر (`father_phone` / `mother_phone`) در هیچ‌کدام از این فلوها استفاده نمی‌شود.

---

## ۴. خارج از دیاگرام

برای خوانایی نقشه، یال مستقیم پنل→Acc و گره‌های الوکام/فراز روی دیاگرام نیستند؛ در کارت‌های همان HTML و در این گزارش آمده‌اند. منبع حقیقت شهریه همچنان `ghazali1_Acc.students` است و پنل با `accounting_pdo` همان جدول را به‌روز می‌کند.
