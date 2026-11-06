<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * پروفایل کاربران: کد ملی، چند شماره همراه، تلفن ثابت، آدرس منزل
 * و دریافتی کاربر از هر پروژه (سرویس نصب) به تفکیک سال و ماه با وضعیت تسویه.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('national_code', 10)->nullable()->after('name');
            // ستون mobile همان شماره اول این لیست است (برای پیامک‌ها)
            $table->json('mobiles')->nullable()->after('mobile')->comment('لیست شماره‌های همراه');
            $table->string('phone', 20)->nullable()->after('mobiles')->comment('تلفن ثابت');
            $table->text('address')->nullable()->after('phone')->comment('آدرس منزل');
        });

        DB::table('users')->whereNotNull('mobile')->where('mobile', '!=', '')->get(['id', 'mobile'])
            ->each(fn($user) => DB::table('users')->where('id', $user->id)
                ->update(['mobiles' => json_encode([$user->mobile])]));

        Schema::create('user_project_earnings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('installation_service_id')->constrained('installation_services')->cascadeOnDelete()->comment('پروژه');
            $table->bigInteger('amount')->comment('مبلغ دریافتی (تومان)');
            $table->date('date')->comment('تاریخ (میلادی)؛ سال و ماه شمسی از آن محاسبه می‌شود');
            $table->boolean('is_settled')->default(false);
            $table->date('settled_at')->nullable();
            $table->text('description')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['user_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_project_earnings');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['national_code', 'mobiles', 'phone', 'address']);
        });
    }
};
