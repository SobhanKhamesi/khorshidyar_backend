<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * درصد معرف از این به بعد هنگام ایجاد هر پروژه روی خود پروژه ثبت (ثابت) می‌شود.
 * پروژه‌هایی که قبل از این تغییر ساخته شده‌اند درصدی ندارند؛ درصد فعلی مشتری روی آن‌ها ثبت می‌شود
 * تا ویرایش‌های بعدی درصد مشتری روی آن‌ها اثر نگذارد.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('
            UPDATE `automation` a
            JOIN `customers` c ON c.`id` = a.`customer_id`
            SET a.`referrer_percent` = c.`referrer_percent`
            WHERE a.`referrer_percent` IS NULL
              AND c.`referrer_id` IS NOT NULL
              AND c.`referrer_percent` IS NOT NULL
        ');
    }

    public function down(): void
    {
        // داده‌ای است و قابل بازگشت دقیق نیست
    }
};
