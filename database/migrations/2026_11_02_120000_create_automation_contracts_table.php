<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * قرارداد هر اتوماسیون فروش (مرحله مذاکره و قرارداد):
 * پیش‌نویس → تایید ادمین و ارسال لینک برای مشتری → امضای دیجیتال توسط مشتری.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('automation_contracts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('automation_id')->unique();
            $table->string('token', 64)->nullable()->unique()->comment('توکن لینک عمومی قرارداد');
            $table->tinyInteger('status')->default(0)->comment('0 پیش‌نویس، 1 تایید و ارسال شده، 2 امضا شده');
            $table->json('fields')->nullable()->comment('فیلدهای قابل ویرایش قرارداد');
            $table->json('snapshot')->nullable()->comment('متن نهایی قرارداد در لحظه تایید (مبنای امضا)');
            $table->uuid('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->boolean('sms_sent')->default(false);
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('viewed_at')->nullable()->comment('اولین مشاهده توسط مشتری');
            $table->string('signer_name', 100)->nullable();
            $table->longText('signature')->nullable()->comment('تصویر امضا (data URL)');
            $table->string('signer_ip', 45)->nullable();
            $table->timestamp('signed_at')->nullable();
            $table->timestamp('signed_seen_at')->nullable()->comment('مشاهده امضا در کارتابل (برای اعلان)');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('automation_contracts');
    }
};
