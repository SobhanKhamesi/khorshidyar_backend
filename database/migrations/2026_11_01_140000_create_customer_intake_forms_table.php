<?php

use App\Models\CustomerIntakeForm;
use App\Models\SourceSms;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * فرم اولیه‌ای که لینک آن برای مشتری/سرنخ پیامک می‌شود تا خودش تکمیل کند.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_intake_forms', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('token', 64)->unique();
            $table->uuid('customer_id')->index()->comment('مشتری یا سرنخ');
            $table->uuid('automation_id')->nullable()->index()->comment('درخواست ایجاد شده در کارتابل');
            $table->uuid('sent_by')->nullable()->comment('کاربر ارسال کننده');
            $table->json('answers')->nullable()->comment('پاسخ های مشتری');
            $table->boolean('sms_sent')->default(false);
            $table->timestamp('submitted_at')->nullable()->comment('زمان تکمیل توسط مشتری');
            $table->timestamp('seen_at')->nullable()->comment('زمان مشاهده در کارتابل');
            $table->timestamps();
        });

        // قالب پیامک ارسال لینک فرم اولیه؛ از تنظیمات پیامک قابل ویرایش است و {link} با لینک فرم جایگزین می‌شود
        $user = User::first();
        if ($user && !SourceSms::where('title', CustomerIntakeForm::SMS_TEMPLATE_TITLE)->exists()) {
            SourceSms::create([
                'title' => CustomerIntakeForm::SMS_TEMPLATE_TITLE,
                'description' => CustomerIntakeForm::DEFAULT_SMS_TEXT,
                'user_id' => $user->id,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_intake_forms');
        SourceSms::where('title', CustomerIntakeForm::SMS_TEMPLATE_TITLE)->delete();
    }
};
