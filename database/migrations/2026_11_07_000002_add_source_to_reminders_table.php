<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * یادآوری‌های سیستمی (اعلان‌ها): فرم اولیه تکمیل شده، قرارداد امضا شده، نظرسنجی ضعیف و ...
 * source_id شناسه رکوردی است که اعلان برای آن ساخته شده؛ برای هر کاربر از هر رکورد فقط یک یادآوری ساخته می‌شود.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('reminders', function (Blueprint $table) {
            $table->uuid('source_id')->nullable()->after('customer_id')
                ->comment('شناسه رکورد مبدا یادآوری سیستمی (فرم اولیه، قرارداد، نظرسنجی)');
            $table->unique(['user_id', 'type', 'source_id']);
        });
    }

    public function down(): void
    {
        Schema::table('reminders', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'type', 'source_id']);
            $table->dropColumn('source_id');
        });
    }
};
