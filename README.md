# خورشیدیار — بک‌اند

API بک‌اند سامانه CRM خورشیدیار (فروش، نصب و خدمات پس از فروش) با Laravel 10.
فرانت‌اند پروژه جداگانه است و با توکن Sanctum به این API متصل می‌شود.

## راه‌اندازی

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
```

متغیرهای مهم در `.env`:

| متغیر | توضیح |
|---|---|
| `DB_*` | اتصال MySQL |
| `TREZ_USERNAME`, `TREZ_PASSWORD`, `TREZ_NUMBER` | حساب سامانه پیامک RayganSMS |
| `FRONTEND_URL` | آدرس فرانت‌اند برای لینک نظرسنجی در پیامک‌ها |

## ساختار

- `routes/api.php` — همه مسیرها؛ جز `user/login` و `survey/{token}` بقیه نیاز به `auth:sanctum` دارند.
- `app/Http/Controllers/API` — کنترلرها؛ پاسخ‌ها با `Contracts/ApiController` در قالب `{message, data}` برگردانده می‌شوند.
- `app/Services/SmsService.php` — ارسال پیامک (تکی، گروهی هم‌زمان، و ارسال قالب به مشتری با ثبت در تاریخچه).
- `app/Models` — مدل‌ها. مراحل اتوماسیون فروش در `Automation`:
  درخواست (0) ← پیش‌فاکتور (1) ← مذاکره و قرارداد (2) ← فروش موفق (3)، یا شکست (-1).

## نکات کارایی

فیلدهای محاسباتی `Customer` (`current_automation`، `sum_invoices_price`، `SabtKonande`) و `Product`
(`inventory`، `sold_count`، `SabtKonande`) اگر مدل با scope `withStats()` گرفته شود در همان کوئری اصلی
محاسبه می‌شوند؛ در غیر این صورت برای هر ردیف یک کوئری جدا اجرا می‌شود. در لیست‌ها همیشه از
`Customer::withStats()` / `Product::withStats()` استفاده کنید. روابطی که مشتری یا محصول را از مدل‌های دیگر
بارگذاری می‌کنند (مثل `Automation::customer` و `Order::product`) این scope را از قبل دارند.
