<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('automation', function (Blueprint $table) {
            $table->drop();
        });

        Schema::create('automation', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->tinyInteger("status")->comment("وضعیت اتوماسیون");
            $table->tinyInteger("survey_type")->comment("نوع کارشناسی");
            $table->foreignId("user_id")->comment("ثبت کننده");
            $table->foreignId("customer_id")->comment("مشتری");
            $table->foreignId("expert_id")->nullable()->comment("کارشناسی فنی");
            $table->string("address")->comment("آدرس مشتری و اتوماسیون");
            $table->string("invoice_number")->comment("شماره فاکتور");
            $table->date("visit_date")->comment("تاریخ بازدید");
            $table->string("negotiator")->nullable()->comment("مذاکره کننده");
            $table->boolean("send_sms")->comment("پیامک ارسال شود؟");
            $table->string("wage")->nullable()->comment("اجرت");
            $table->float("final_price")->nullable()->comment("قیمت نهایی");
            $table->foreignId("failure_id")->nullable()->comment("دلیل شکست");

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {

    }
};
