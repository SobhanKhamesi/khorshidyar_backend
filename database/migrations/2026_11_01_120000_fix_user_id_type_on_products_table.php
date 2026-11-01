<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * ستون products.user_id به اشتباه bigint ساخته شده بود در حالی که شناسه کاربران uuid است؛
 * در نتیجه uuid هنگام ذخیره به عدد ابتدای آن (مثلاً 9492) کوتاه می‌شد.
 * این مایگریشن نوع ستون را char(36) می‌کند و مقادیر قبلی را به uuid کامل کاربر برمی‌گرداند.
 * قابل اجرای مجدد است (اگر ستون قبلاً تبدیل شده باشد فقط داده‌ها اصلاح می‌شوند).
 */
return new class extends Migration
{
    public function up(): void
    {
        $type = DB::selectOne("
            SELECT DATA_TYPE AS t FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'products' AND COLUMN_NAME = 'user_id'
        ")->t ?? null;

        if ($type !== 'char')
            DB::statement('ALTER TABLE `products` MODIFY `user_id` CHAR(36) NULL');

        // مقدار کوتاه شده همان ارقام ابتدای uuid تا اولین حرف غیر عددی است
        DB::statement("
            UPDATE `products` p
            SET p.`user_id` = (
                SELECT u.`id` FROM `users` u
                WHERE u.`id` LIKE CONCAT(p.`user_id`, '%')
                  AND SUBSTRING(u.`id`, CHAR_LENGTH(p.`user_id`) + 1, 1) NOT REGEXP '[0-9]'
                LIMIT 1
            )
            WHERE p.`user_id` IS NOT NULL AND p.`user_id` NOT LIKE '%-%'
        ");
    }

    public function down(): void
    {
        DB::statement('UPDATE `products` SET `user_id` = NULL');
        DB::statement('ALTER TABLE `products` MODIFY `user_id` BIGINT UNSIGNED NULL');
    }
};
