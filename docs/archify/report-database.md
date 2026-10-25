# گزارش دیتابیس و مسیر ذخیره داده

نقشه تعاملی: [ghazal-data.dataflow.html](ghazal-data.dataflow.html)

سه MySQL جدا روی همان میزبان DirectAdmin. کلید اتصال **شماره پرونده** است، نه کلید خارجی بین دیتابیس‌ها.

| دیتابیس | سرویس | منبع حقیقت برای |
|---|---|---|
| `ghazali1_panel` | پنل آموزشی | کاربر، ترم، کلاس، حضور، تکلیف |
| `ghazali1_Acc` | حسابداری + هوک هویت پنل | شهریه هر ترم، رسید، سطح، کتاب روی فیش |
| MySQL فروشگاه (نام در کانفیگ لایو) | `books.ghazalify.com` | سفارش و اقلام |

کانفیگ اتصال (`includes/db.php`, `accounting/config.php`, `bookstore-shop/config/*`) روی سرور است.

---

## ۱. `ghazali1_panel`

اتصال: PDO پنل. دانش‌آموز کاننیکال جدول `users` با `role='student'` است، نه جدول قدیمی `students`.

| جدول | ستون‌های مهم | نوشتن | خواندن |
|---|---|---|---|
| `users` | `id`, `username` (=پرونده), `password`, `full_name`, `phone`, `father_phone`, `mother_phone`, `role`, `is_deleted` | هویت، Students Information، My Profile (`update-profile.php`) | ورود، داشبورد، پیامک فقط از `phone` |
| `terms` | `title`, `year`, `starts_on`, `ends_on`, `accounting_term_id`, `is_archived` | Terms، ایمپورت، بایگانی خودکار | فیلتر تدریس، آینه Acc |
| `classes` | `title`, `term_id`, `class_type`, `online_link`, `schedule`, `teacher_id`, `is_archived` | `save-class.php`, `placement-save.php` | داشبورد، الوکام، حضور |
| `class_students` | `class_id`, `user_id`, `is_deleted` | کلاس‌بندی، `identity_enroll_student_in_class` | لیست کلاس، join-online |
| `attendance` | یکتا `(class_id, user_id, class_date)`, وضعیت حضور | معلم، `rollcall_supervisor_set_status`، کرون الوکام | رول‌کال، سوابق |
| `attendance_sms_log` | کلاس / کاربر / تاریخ | بعد از SMS غیبت | جلوگیری از تکرار |
| `attendance_late_sms_log` | همان شکل | SMS تأخیر | جلوگیری از تکرار |
| `study_plan_sms_log` | `user_id`, `sent_by`, `family`, `phone` | Study Plan | زمان آخرین ارسال |
| `rollcall_missing_alerts` | کلاس / تاریخ | کرون هشدار | یک‌بار Mattermost |
| `student_welcome_sms_log` | `user_id`, `file_number` | خوش‌آمد | یک‌بار ارسال |
| `alocom_credentials` | نگاشت پنل↔الوکام و رمز ذخیره‌شده | `alocom-access` | ورود و ثبت رویداد |
| `alocom_access_state` | وضعیت ثبت در رویداد | همان | همگام‌سازی |
| `eas_management` | جلسه و مصاحبه | placement / EAS | سوپروایزر |

جدول‌های آموزشی دیگر از همان PDO: تکلیف، نمره کوییز، طرح جلسه، Mind Lingo، مشاهده کلاس، ریپورت کارت.

اگر ستونی نباشد PHP آن را `ALTER`/`CREATE` می‌کند (مثلاً `ends_on` با `term_ensure_schema`). تنظیمات عمومی در فایل PHP روی سرور است، نه جدول `settings` پنل.

### نمونه نوشتن هویت

`identity_ensure_panel_student`: `INSERT/UPDATE users` با `username = شماره پرونده` و `password_hash` رمز پیش‌فرض.

### نمونه نوشتن حضور

`rollcall_save_and_notify`: برای هر نفر `INSERT ... ON DUPLICATE KEY UPDATE` روی `attendance`. فقط گذار به غیبت پیامک می‌فرستد.

`rollcall_supervisor_set_status`: ادیت تکی حاضر/غایب همان جدول. پیامک غیبت باز هم به `users.phone`.

ستون‌های پدر/مادر با `users_ensure_parent_phone_columns` ساخته می‌شوند؛ ذخیره در پروفایل دانش‌آموز است نه در Acc.

---

## ۲. `ghazali1_Acc`

اتصال: `accounting_pdo()` از پنل، و PDO جدا در `accounting/api.php`.

**یک نفر در چند ترم چند ردیف `students` دارد.** فیلتر SPA با `termId` است.

| جدول | ستون‌های مهم | نوشتن | خواندن |
|---|---|---|---|
| `students` | `termId`, `studentCode`, نام، `level`, `classType`, `totalPayable`, `amountPaid`, `debt`, `status` (`paid`/`unpaid`/`pending`), `hasBook`, `bookName`, `bookPrice`, تخفیف، مصاحبه | Acc API؛ `identity_ensure_acc_student`؛ `identity_sync_student_for_class`؛ `bookstore_patch_acc_book` | لیست SPA، `printHTML`، کنترل تسویه `join-online` |
| `receipts` | `studentId`, `termId`, `paidAmount`, `date` | `POST /api/receipts` | مودال رسید و تجدید چاپ |
| `terms` | `name`, `status` `active`/`completed` | Acc API و `term_mirror_accounting` | انتخاب ترم |
| `levels` | `name`, `fee` | Acc API | `accounting_match_level` / بازسازی شهریه |
| `salaries`, `expenses` | حقوق و هزینه | Acc API | صفحات SPA |
| `users` | ورود پذیرش (`manager`/`reception`) | ادمین Acc | `POST /api/login` |

### ثبت اولیه از پنل

`identity_ensure_acc_student` اگر ردیف نباشد:

```sql
INSERT INTO students
  (firstName, lastName, level, phone, classType, studentCode,
   totalPayable, amountPaid, debt, status, termId,
   hasBook, bookPrice, hasInterview, hasDiscount, discountPercent, discountAmount, createdAt)
VALUES (?, ?, '', ?, '', ?, 0, 0, 0, 'pending', ?, 0, 0, 0, 0, 0, 0, NOW())
```

### وقتی کلاس می‌خورد

`identity_sync_student_for_class` سطح و نوع کلاس را می‌گذارد، `identity_rebuild_tuition` جمع را می‌سازد، `status` را `unpaid` می‌کند مگر اینکه از قبل تسویه باشد.

```
totalPayable = fee − round(fee × discountPercent/100) + bookPrice + (hasInterview ? 250000 : 0)
debt         = totalPayable − amountPaid
status       = pending | unpaid | paid
```

### تگ کتاب

`bookstore_patch_acc_book` دیگر بعد از سفارش کتاب صدا زده نمی‌شود (پرداخت با رسید واریز است). تابع هنوز در کد هست.

1. `SELECT` همان `students.id`.
2. `bookName` را با ` · ` اضافه می‌کند (حداکثر ۱۸۰ کاراکتر).
3. `bookPrice += sell_total` و `totalPayable += sell_total`.
4. `hasBook = 1`؛ `debt` و `status` دوباره حساب می‌شوند.

فاکتور Acc از همین فیلدها چاپ می‌شود؛ جدول جدا برای فاکتور کتاب نیست.

### رسید

```sql
INSERT INTO receipts (studentId, termId, paidAmount, date) VALUES (?, ?, ?, ?)
```

SPA بعد از ذخیره، `amountPaid` / `debt` / `status` دانش‌آموز را PATCH می‌کند. چاپ بدون ذخیره، ردیف رسید نمی‌سازد.

لیست دانش‌آموزان:

```sql
SELECT id, termId, firstName, lastName, level, phone, classType, studentCode,
       totalPayable, amountPaid, debt, status, createdAt,
       hasBook, bookName, bookPrice, hasInterview, hasDiscount,
       discountPercent, discountAmount,
       CASE WHEN receiptUrl IS NULL OR receiptUrl = '' THEN 0 ELSE 1 END AS hasReceipt
FROM students
WHERE termId = ?
ORDER BY id DESC
```

---

## ۳. MySQL فروشگاه

از `bookstore-shop/includes/bootstrap.php` و هوک `api/panel-order.php`.

| جدول | نقش |
|---|---|
| `orders` | سفارش: `tracking_code`, نام، موبایل، `student_code`, `status` (`awaiting_receipt`/`pending`/…), `source`, `delivery_type`, `address`, `postal_code`, `sell_total`, `postage_fee`, `receipt_path`, `term_title_snapshot` |
| `order_items` | `order_id`, `book_id`, `book_title_snapshot`, `quantity`, `unit_price` |
| `books`, `term_books`, `terms` | کاتالوگ داخلی فروشگاه |
| `settings` | کش قیمت اینجا بوک، `postage_fee` (۱۵۰٬۰۰۰)، `payment_card` (`6219861926801571`) |
| `admins` | ورود ادمین فروشگاه |

سفارش پنل (`create_panel_package_order`):

- `source = 'panel'`
- `status = 'awaiting_receipt'` تا رسید بیاید، بعد `pending`
- `term_id` خالی؛ عنوان پکیج در `term_title_snapshot`
- `delivery_type`: `pickup` یا `post`
- `postage_fee` فقط برای پست؛ رسید در `receipt_path`

قیمت فروش در پنل حساب می‌شود و به صورت `sell_total` و `unit_price` می‌آید، نه از جدول Acc.

---

## ۴. بیرون از این سه MySQL

| داده | کجاست | در غزال چه می‌ماند |
|---|---|---|
| قیمت عمده اینجا بوک | Store API | فایل/settings حدود **۲۰ دقیقه** (`bookstore_inja_price_map`) |
| متن SMS | فراز | فقط لاگ «ارسال شد» (بدون متن) |
| اتاق، توکن، ترافیک خام | الوکام | `online_link` + `alocom_credentials` / `alocom_access_state` |
| کاربر سایت | دیتابیس وردپرس | هیچ، جز پاسخ هوک هویت |
| OTP سایت | فراز + transient وردپرس | کش پترن حدود ۱۰ دقیقه |

---

## ۵. خواندن در برابر نوشتن

| داده | منبع حقیقت | مسیر نوشتن | مسیر خواندن | کش |
|---|---|---|---|---|
| شماره پرونده | `panel.users.username` | `identity_ensure_panel_student` | همه سیستم‌ها با همان کد | ندارد |
| ردیف شهریه | `Acc.students` (هر ترم) | هویت / Acc API / پچ کتاب | `GET /api/students`، تسویه کلاس | ندارد |
| رسید | `Acc.receipts` | `POST /api/receipts` | `GET /api/receipts` | ندارد |
| لیست کلاس | `class_students` | کلاس‌بندی / enroll | داشبورد، حضور | ندارد |
| حضور | `attendance` | `rollcall_save_and_notify` | صفحات حضور و سوابق | ندارد |
| حضور خام آنلاین | ترافیک الوکام | بیرونی | کرون در پنجره T+۳۰…۵۰ | توکن الوکام تا `expires_in` |
| سفارش کتاب | `orders` / `order_items` | `panel-order.php` | `orders.php` | ندارد |
| مبلغ کتاب روی فیش | `Acc.students.hasBook*` دستی در Registration | پذیرش Acc | تگ کهربایی Registration و `printHTML` | ندارد |
| سفارش تأییدشده کتاب | `orders` فروشگاه `approved`/`ordered` | فروشگاه `orders.php` | صفحه Bookstore حسابداری | ندارد |
| قیمت فروش کتاب | فرمول + اینجا بوک | محاسبه‌ای | صفحه دانش‌آموز `bookstore.php`، `prices.php` | ۲۰ دقیقه |
| کاربر سایت | WP `users` | `ghazal-identity-ensure.php` | ورود Digits | ندارد |
| تقویم ترم | `panel.terms.starts_on/ends_on` | Terms / اکسل | هوک `term_meta` برای Acc | ندارد |

---

## ۶. دو نویسنده Acc

دیاگرام مسیر اصلی Acc را از SPA نشان می‌دهد (`accUi → accApi → accDb → invoice`). مسیر دوم از کارت «نوشتن Acc از دو جا»:

1. پذیرش: `POST/PATCH /api/students` و `POST /api/receipts`.
2. پنل: `identity_sync_student_for_class` شهریه سطح را می‌گذارد.

سفارش کتاب دیگر Acc را مستقیم عوض نمی‌کند؛ پرداخت با رسید واریز در فروشگاه کتاب است.
