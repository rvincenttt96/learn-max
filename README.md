# Learn Max — پنل حسابداری

کد کامل پنل حسابداری آموزشگاه زبان **Learn Max** (برنچ `accounting`).

آموزشی روی برنچ [`education`](https://github.com/rvincenttt96/learn-max/tree/education) است.

## چیدمان (مثل سرور)

- ریشه: `index.php` / `index.html` / `login.js` / `login.css` / `payroll.js` / …
- API: `api/api.php` با بازنویسی `/api/*`
- فرانت باندل: `assets/index-*.js` و `assets/index-*.css`

## اجرا

1. `api/config.example.php` را به `api/config.php` کپی کنید و اتصال دیتابیس را پر کنید.
2. در صورت نیاز `api/identity-config.example.php` → `api/identity-config.php`.
3. `.htaccess` همین پوشه `/api/*` را به `api/api.php` می‌فرستد.

رمز دیتابیس را commit نکنید.
