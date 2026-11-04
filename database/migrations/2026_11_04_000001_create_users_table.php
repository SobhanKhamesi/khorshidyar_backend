<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('username')->unique();
            $table->string('mobile', 20)->nullable()->comment('برای پیامک ارجاع به نصاب و اعلام شماره نصاب به مشتری');
            $table->string('password');
            $table->tinyInteger('status')->default(0)->comment('0 فعال، 1 غیرفعال');
            $table->tinyInteger('role')->index()->comment('0 مدیر، 1 کارشناس فنی، 2 نصاب، 3 کارشناس فروش');
            $table->string('profile')->nullable();
            $table->string('emza')->nullable()->comment('تصویر امضا');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
