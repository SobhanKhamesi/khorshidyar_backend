<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    // سامانه پیامک رایگان اس ام اس
    'raygan_sms' => [
        'url' => env('RAYGAN_SMS_URL', 'https://RayganSMS.com/SendMessageWithUrl.ashx'),
        'username' => env('TREZ_USERNAME'),
        'password' => env('TREZ_PASSWORD'),
        'number' => env('TREZ_NUMBER'),
        'timeout' => env('RAYGAN_SMS_TIMEOUT', 10),
        // شماره‌هایی که اعلان تکمیل فرم اولیه توسط مشتری برایشان پیامک می‌شود (جدا شده با ویرگول)
        'staff_mobiles' => array_values(array_filter(array_map('trim', explode(',', (string)env('STAFF_NOTIFY_MOBILES', ''))))),
    ],

    // آدرس فرانت‌اند (برای لینک‌هایی که در پیامک ارسال می‌شوند)
    'frontend_url' => env('FRONTEND_URL', 'https://khrshdyar.ir'),

];
