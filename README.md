# Learn Max — پنل آموزشی

کد کامل پنل آموزشی آموزشگاه زبان **Learn Max** (برنچ `education`).

حسابداری روی برنچ [`accounting`](https://github.com/rvincenttt96/learn-max/tree/accounting) است.

## اجرا

1. PHP 8.1+ و MySQL.
2. فایل‌های نمونه را کپی کنید و رمز را فقط روی سرور بگذارید:
   - `includes/db.example.php` → `includes/db.php`
   - `includes/auth.example.php` → `includes/auth.php`
   - `includes/directadmin-config.example.php` → `includes/directadmin-config.php`
   - `includes/rollcall-config.example.php` → `includes/rollcall-config.php`
   - `includes/bookstore-config.example.php` → `includes/bookstore-config.php`
   - `includes/liara-ai-config.example.php` → `includes/liara-ai-config.php`
3. ریشهٔ وب را همین پوشه بگذارید (`index.php` صفحهٔ ورود است).

رمز، توکن، و فایل‌های `config.php` / `config.json` داخل این ریپوی عمومی نیستند.

## دیتابیس

روی cPanel دو MySQL جدا ساخته شده (هنوز دیپلوی نشده):

- آموزشی: `rzsbtmgo_edu` — اسکیما: `schema/education.sql`
- حسابداری روی برنچ `accounting` است (`rzsbtmgo_acc`).

`includes/db.example.php` را به `includes/db.php` کپی کنید و نام دیتابیس/رمز را فقط روی سرور بگذارید.
