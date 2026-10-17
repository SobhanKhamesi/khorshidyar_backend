<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('after_sales_services', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('customer_id');
            // اختیاری: اگر این خدمت پس از فروش مرتبط با یک سرویس نصب خاص باشد
            // (یا به صورت خودکار سه روز پس از تکمیل سرویس نصب ساخته شده باشد)
            $table->foreignUuid('installation_service_id')->nullable();

            $table->foreignUuid('service_type_id')->nullable();

            $table->foreignUuid('responsible_user_id')->nullable();

            $table->text('problem');

            $table->date('request_date')->nullable();
            $table->date('follow_up_date')->nullable();

            // 1: در انتظار, 2: در حال پیگیری, 3: تکمیل شده, 4: لغو شده
            $table->unsignedTinyInteger('status')->default(1);

            // 1: ثبت دستی توسط کاربر, 2: یادآوری خودکار (سه روز پس از سرویس نصب)
            $table->unsignedTinyInteger('source')->default(1);

            $table->text('resolution_description')->nullable();
            $table->timestamp('completed_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('after_sales_services');
    }
};
