<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * سرویس نصب، خدمات پس از فروش، نظرسنجی و نوبت‌های نصاب.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('installation_services', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->tinyInteger('purchase_source')->comment('1 خرید از ما (internal)، 2 خرید از جای دیگر (external)');
            $table->unsignedTinyInteger('installer_mode')->default(1)->comment('1 ارجاع کل پروژه به یک نصاب، 2 نصاب جداگانه پنل/سیم کشی/راه اندازی');
            $table->foreignUuid('automation_id')->nullable()->constrained('automation')->nullOnDelete()->comment('فقط برای purchase_source = internal');
            $table->string('address')->nullable();
            $table->string('contact_number')->nullable();
            $table->text('description')->nullable();
            $table->date('survey_date')->nullable()->comment('تاریخ بازدید (میلادی)');
            $table->foreignUuid('technical_expert_id')->nullable()->constrained('users')->nullOnDelete()->comment('کارشناس فنی بررسی کننده');
            $table->tinyInteger('status')->comment('1 موقت، 2 نهایی');
            $table->unsignedTinyInteger('stage')->default(0)->index()->comment('0 پیش‌نویس، 1 ارجاع به نصاب، 2 ثبت نصاب، 3 بررسی کارشناس، 4 رفع عیب، 5 تایید، 6 تکمیل نهایی');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('installation_service_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('installation_service_id')->constrained('installation_services')->cascadeOnDelete();
            $table->foreignUuid('product_id')->constrained('products');
            $table->unsignedTinyInteger('type')->default(1)->comment('1 اقلام پیش‌نیاز (مدیر)، 2 اقلام مصرف شده (نصاب)');
            $table->decimal('quantity', 10, 2)->default(1);
            $table->timestamps();
        });

        // تاریخچه‌ی گردش کار سرویس نصب
        Schema::create('installation_service_histories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('installation_service_id')->constrained('installation_services')->cascadeOnDelete();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedTinyInteger('stage');
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('after_sales_services', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignUuid('installation_service_id')->nullable()->constrained('installation_services')->nullOnDelete();
            $table->foreignUuid('service_type_id')->nullable()->constrained('service_types')->nullOnDelete();
            $table->foreignUuid('responsible_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('problem');
            $table->date('request_date')->nullable();
            $table->date('follow_up_date')->nullable();
            $table->unsignedTinyInteger('status')->default(1)->index()->comment('1 در انتظار، 2 در حال پیگیری، 3 تکمیل شده، 4 لغو شده');
            $table->unsignedTinyInteger('source')->default(1)->comment('1 ثبت دستی، 2 یادآوری خودکار پس از سرویس نصب');
            $table->text('resolution_description')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        // نظرسنجی مشتری از خدمات پس از فروش
        Schema::create('ratings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('after_sale_service_id')->constrained('after_sales_services')->cascadeOnDelete();
            $table->string('token', 64)->nullable()->unique()->comment('توکن دسترسی عمومی مشتری به صفحه نظرسنجی');
            $table->decimal('rating', 2, 1)->nullable()->comment('امتیاز 1 تا 5');
            $table->text('feedback')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();
        });

        Schema::create('installer_appointments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->comment('نصاب');
            $table->foreignUuid('customer_id')->nullable()->constrained('customers')->cascadeOnDelete();
            $table->foreignUuid('automation_id')->nullable()->constrained('automation')->nullOnDelete();
            $table->foreignUuid('installation_service_id')->nullable()->constrained('installation_services')->cascadeOnDelete();
            $table->foreignUuid('after_sale_service_id')->nullable()->constrained('after_sales_services')->cascadeOnDelete();
            $table->unsignedTinyInteger('type')->comment('1 پنل، 2 سیم‌کشی، 3 راه‌اندازی، 4 پس از فروش');
            $table->date('date');
            $table->time('start_time');
            $table->time('end_time');
            $table->unsignedTinyInteger('status')->default(1)->comment('1 رزرو شده، 2 انجام شده، 3 لغو شده');
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('installer_appointments');
        Schema::dropIfExists('ratings');
        Schema::dropIfExists('after_sales_services');
        Schema::dropIfExists('installation_service_histories');
        Schema::dropIfExists('installation_service_items');
        Schema::dropIfExists('installation_services');
    }
};
