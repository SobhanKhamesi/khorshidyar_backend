<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * تماس‌ها، پیامک‌ها، یادآوری‌ها، برنامه‌ریزی، گزارش روزانه، هدف‌گذاری و تیکت‌ها.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calls', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->string('subject');
            $table->string('type_call')->nullable();
            $table->string('Deal')->nullable();
            $table->date('date')->nullable();
            $table->time('time')->nullable();
            $table->string('contacted')->nullable();
            $table->string('re_follow_subject')->nullable();
            $table->date('re_follow_date')->nullable();
            $table->time('re_follow_time')->nullable();
            $table->text('description')->nullable();
            $table->foreignUuid('user_id')->constrained('users');
            $table->timestamps();
        });

        Schema::create('sms', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->boolean('send_to_all')->default(false);
            $table->string('receiver_type')->nullable();
            $table->foreignUuid('category_id')->nullable()->constrained('customer_categories')->nullOnDelete()->comment('دسته بندی مشتریان گیرنده');
            $table->text('body');
            $table->integer('count');
            $table->json('receivers');
            $table->foreignUuid('customer_id')->nullable()->constrained('customers')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users');
            $table->timestamps();
        });

        Schema::create('reminders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('title');
            $table->date('date');
            $table->tinyInteger('status')->comment('0 فعال، 1 انجام شده');
            $table->tinyInteger('type')->default(0)->comment('0 عمومی، 1 تولد');
            $table->foreignUuid('customer_id')->nullable()->constrained('customers')->cascadeOnDelete();
            $table->boolean('birthday_sms_sent')->default(false);
            $table->text('description')->nullable();
            $table->foreignUuid('user_id')->constrained('users');
            $table->timestamps();

            $table->index(['type', 'date']);
        });

        Schema::create('planning', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('title');
            $table->date('date');
            $table->time('time_start');
            $table->time('time_end');
            $table->tinyInteger('status');
            $table->text('description')->nullable();
            $table->foreignUuid('user_id')->constrained('users');
            $table->timestamps();
        });

        Schema::create('daily_reports', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->date('date');
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->tinyInteger('status');
            $table->text('description')->nullable();
            $table->foreignUuid('user_id')->constrained('users');
            $table->timestamps();
        });

        Schema::create('daily_report_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('report_id')->constrained('daily_reports')->cascadeOnDelete();
            $table->string('time');
            $table->text('body');
        });

        // هدف‌گذاری ماهانه؛ user = کاربری که برایش هدف تعیین شده، user_id = ثبت کننده
        Schema::create('targeting', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user')->constrained('users');
            $table->integer('calls')->nullable();
            $table->integer('sales')->nullable();
            $table->string('month');
            $table->date('start_date');
            $table->date('end_date');
            $table->text('description')->nullable();
            $table->foreignUuid('user_id')->constrained('users');
            $table->timestamps();
        });

        // تیکت؛ user_id = ثبت کننده، userId = کاربر ارجاع شده
        Schema::create('ticket', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->integer('status')->nullable();
            $table->string('title')->nullable();
            $table->string('descriptionTicket')->nullable();
            $table->string('time')->nullable();
            $table->string('description')->nullable();
            $table->foreignUuid('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users');
            $table->foreignUuid('userId')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('ticket_comment', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('ticket_id')->nullable()->constrained('ticket')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users');
            $table->string('content')->nullable();
            $table->timestamps();
        });

        Schema::create('attachments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('ticket_id')->nullable()->constrained('ticket')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users');
            $table->string('file')->nullable();
            $table->string('description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attachments');
        Schema::dropIfExists('ticket_comment');
        Schema::dropIfExists('ticket');
        Schema::dropIfExists('targeting');
        Schema::dropIfExists('daily_report_items');
        Schema::dropIfExists('daily_reports');
        Schema::dropIfExists('planning');
        Schema::dropIfExists('reminders');
        Schema::dropIfExists('sms');
        Schema::dropIfExists('calls');
    }
};
