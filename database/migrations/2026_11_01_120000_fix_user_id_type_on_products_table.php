<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * ستون products.user_id به اشتباه bigint ساخته شده بود در حالی که شناسه کاربران uuid است؛
 * در نتیجه uuid هنگام ذخیره به عدد ابتدای آن (مثلاً 9492) کوتاه می‌شد.
 * این مایگریشن نوع ستون را char(36) می‌کند و مقادیر قبلی را به uuid کامل کاربر برمی‌گرداند.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE `products` MODIFY `user_id` CHAR(36) NULL');

        // همان تطبیقی که MySQL قبلاً هنگام مقایسه عدد با uuid انجام می‌داد
        DB::statement("
            UPDATE `products` p
            SET p.`user_id` = (
                SELECT u.`id` FROM `users` u
                WHERE CAST(u.`id` AS UNSIGNED) = CAST(p.`user_id` AS UNSIGNED)
                ORDER BY u.`created_at`
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
