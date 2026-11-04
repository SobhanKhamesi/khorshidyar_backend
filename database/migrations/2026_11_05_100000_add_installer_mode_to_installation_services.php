<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1: ارجاع کل پروژه به یک نصاب (پیش‌فرض)، 2: انتخاب جداگانه‌ی نصاب پنل / سیم کشی / راه اندازی
        Schema::table('installation_services', function (Blueprint $table) {
            $table->unsignedTinyInteger('installer_mode')->default(1)->after('purchase_source');
        });

        // سرویس‌های قبلی با همان روش نصاب‌های جداگانه ثبت شده‌اند
        DB::table('installation_services')->update(['installer_mode' => 2]);
    }

    public function down(): void
    {
        Schema::table('installation_services', function (Blueprint $table) {
            $table->dropColumn('installer_mode');
        });
    }
};
