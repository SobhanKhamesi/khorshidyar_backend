<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // مشتریان و سرنخ‌ها
        Schema::create('customers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->tinyInteger('status')->nullable()->index()->comment('مشتری / سرنخ');
            $table->tinyInteger('type')->nullable();
            $table->string('name');
            $table->string('contact_number')->index();
            $table->string('contact_number1')->nullable();
            $table->string('contact_number2')->nullable();
            $table->string('landline_number')->nullable();
            $table->string('province')->nullable();
            $table->string('city')->nullable();
            $table->string('national_code')->nullable();
            $table->string('postal_code')->nullable();
            $table->string('address')->nullable();
            $table->date('birth_date')->nullable();
            $table->date('marriage_date')->nullable();
            $table->string('email')->nullable();
            $table->string('eitaa')->nullable();
            $table->string('whatsapp')->nullable();
            $table->string('telegram')->nullable();
            $table->string('instagram')->nullable();
            $table->string('website')->nullable();
            $table->string('job')->nullable();
            $table->string('disc')->nullable();
            $table->foreignUuid('category_id')->nullable()->constrained('customer_categories')->nullOnDelete();
            $table->string('job_address')->nullable();
            $table->string('lead_status')->nullable();
            $table->string('reject_reason')->nullable();
            $table->text('description')->nullable();
            $table->foreignUuid('user_id')->constrained('users')->comment('ثبت کننده');
            $table->foreignUuid('source_id')->nullable()->constrained('source_categories')->nullOnDelete();
            $table->foreignUuid('referrer_id')->nullable()->constrained('referrers')->nullOnDelete();
            $table->integer('referrer_percent')->nullable();
            $table->timestamps();
        });

        // افراد مرتبط با مشتری
        Schema::create('co_persons', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->boolean('gender');
            $table->string('name');
            $table->string('contact_number')->nullable();
            $table->string('position')->nullable();
            $table->string('disc')->nullable();
            $table->string('whatsapp')->nullable();
            $table->string('email')->nullable();
            $table->text('description')->nullable();
            $table->foreignUuid('user_id')->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('co_persons');
        Schema::dropIfExists('customers');
    }
};
