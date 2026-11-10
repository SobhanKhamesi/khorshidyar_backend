<?php
return [
    'path' => env('WEB_ROOT_PATH'),
    'url'  => env('WEB_ROOT_URL'),
    // فایل‌های استاتیک (css و فونت) در ریشه سایت هستند نه زیر مسیر بک‌اند (/v1)؛
    // اگر WEB_ROOT_URL تنظیم نشده باشد، آدرس نسبی به ریشه دامنه (/assets) استفاده می‌شود
    'assets' => rtrim((string)env('WEB_ROOT_URL', ''), '/') . '/assets',
];
