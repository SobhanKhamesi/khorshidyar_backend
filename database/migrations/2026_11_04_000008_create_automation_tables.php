<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * اتوماسیون فروش (کارتابل) و جداول جزئیات آن.
 * جداول جزئیات با حذف اتوماسیون به صورت خودکار حذف می‌شوند.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('automation', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->tinyInteger('status')->index()->comment('0 درخواست، 1 پیش فاکتور، 2 مذاکره و قرارداد، 3 فروش موفق، -1 شکست');
            $table->tinyInteger('survey_type')->comment('1 خانگی، 2 صنعتی، 3 فرم اولیه مشتری');
            $table->foreignUuid('user_id')->constrained('users')->comment('ثبت کننده');
            $table->foreignUuid('customer_id')->constrained('customers')->cascadeOnDelete()->comment('مشتری');
            $table->integer('referrer_percent')->nullable()->comment('درصد معرف از این فروش');
            $table->foreignUuid('expert_id')->nullable()->constrained('users')->nullOnDelete()->comment('کارشناس فنی');
            $table->string('address')->comment('آدرس مشتری و اتوماسیون');
            $table->string('invoice_number')->nullable()->comment('شماره فاکتور');
            $table->date('visit_date')->nullable()->comment('تاریخ بازدید');
            $table->string('negotiator')->nullable()->comment('مذاکره کننده');
            $table->boolean('send_sms')->default(false)->comment('پیامک ارسال شود؟');
            $table->string('wage')->nullable()->comment('اجرت');
            $table->unsignedBigInteger('final_price')->nullable()->comment('مبلغ نهایی');
            $table->foreignUuid('failure_id')->nullable()->constrained('failure_categories')->nullOnDelete()->comment('دلیل شکست');
            $table->text('description')->nullable();
            $table->timestamps();
        });

        // یادداشت‌های اتوماسیون (مدل AutomatonComment)
        Schema::create('comment_automation', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('automation_id')->constrained('automation')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users');
            $table->string('content')->nullable();
            $table->timestamps();
        });

        Schema::create('automation_contract_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('automation_id')->constrained('automation')->cascadeOnDelete();
            $table->string('title');
            $table->decimal('amount', 15, 0)->default(0);
            $table->timestamps();
        });

        Schema::create('automation_payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('automation_id')->constrained('automation')->cascadeOnDelete();
            $table->string('method');
            $table->decimal('amount', 15, 0);
            $table->date('date')->nullable();
            $table->timestamps();
        });

        Schema::create('automation_cheques', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('automation_id')->constrained('automation')->cascadeOnDelete();
            $table->string('number');
            $table->string('bank_name')->nullable();
            $table->decimal('amount', 15, 0);
            $table->date('due_date')->nullable();
            $table->timestamps();
        });

        Schema::create('automation_contracts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('automation_id')->unique()->constrained('automation')->cascadeOnDelete();
            $table->string('token', 64)->nullable()->unique()->comment('توکن لینک عمومی قرارداد');
            $table->tinyInteger('status')->default(0)->comment('0 پیش‌نویس، 1 تایید و ارسال شده، 2 امضا شده');
            $table->json('fields')->nullable()->comment('فیلدهای قابل ویرایش قرارداد');
            $table->json('snapshot')->nullable()->comment('متن نهایی قرارداد در لحظه تایید (مبنای امضا)');
            $table->foreignUuid('approved_by')->nullable()->constrained('users')->nullOnDelete();
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

        // فرم اولیه‌ای که خود مشتری تکمیل می‌کند
        Schema::create('automation_customer_surveys', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('automation_id')->unique()->constrained('automation')->cascadeOnDelete();
            $table->string('full_name', 100)->nullable()->comment('نام و نام خانوادگی');
            $table->string('national_code', 10)->nullable()->comment('کد ملی');
            $table->string('bill_id', 20)->nullable()->comment('شناسه قبض برق');
            $table->text('address')->nullable()->comment('آدرس محل نصب');
            $table->decimal('available_area', 12, 2)->nullable()->comment('فضای در دسترس (متر مربع)');
            $table->string('install_place', 50)->nullable()->comment('مکان نصب');
            $table->string('building_age', 50)->nullable()->comment('سن بنا');
            $table->string('ownership', 50)->nullable()->comment('نوع مالکیت');
            $table->string('goal')->nullable()->comment('هدف از نصب نیروگاه');
            $table->string('property_type', 50)->nullable()->comment('مشخصات ملک');
            $table->json('surface_material')->nullable()->comment('جنس سطح');
            $table->string('mobile', 11)->nullable()->comment('شماره موبایل');
            $table->timestamps();
        });

        // لینک فرم اولیه‌ای که برای مشتری پیامک می‌شود
        Schema::create('customer_intake_forms', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('token', 64)->unique();
            $table->foreignUuid('customer_id')->constrained('customers')->cascadeOnDelete()->comment('مشتری یا سرنخ');
            $table->foreignUuid('automation_id')->nullable()->constrained('automation')->nullOnDelete()->comment('درخواست ایجاد شده در کارتابل');
            $table->foreignUuid('sent_by')->nullable()->constrained('users')->nullOnDelete()->comment('کاربر ارسال کننده');
            $table->boolean('sms_sent')->default(false);
            $table->timestamp('submitted_at')->nullable()->comment('زمان تکمیل توسط مشتری');
            $table->timestamp('seen_at')->nullable()->comment('زمان مشاهده در کارتابل');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_intake_forms');
        Schema::dropIfExists('automation_customer_surveys');
        Schema::dropIfExists('automation_contracts');
        Schema::dropIfExists('automation_cheques');
        Schema::dropIfExists('automation_payments');
        Schema::dropIfExists('automation_contract_items');
        Schema::dropIfExists('comment_automation');
        Schema::dropIfExists('automation');
    }
};
