<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * حسابداری: حساب‌های بانکی، فاکتورها، چک‌ها و اقلام فاکتور/اتوماسیون.
 * سوابق مالی (فاکتور و چک) با حذف مشتری حذف نمی‌شوند (restrict).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_accounts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('bank_id')->constrained('banks');
            $table->string('number')->nullable();
            $table->text('description')->nullable();
            $table->foreignUuid('user_id')->constrained('users');
            $table->timestamps();
        });

        Schema::create('accounting', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->tinyInteger('type')->index()->comment('0 پیش فاکتور، 1 پیش فاکتور سرنخ، 2 خرید، 3 فروش، 4 برگشت از خرید، 5 برگشت از فروش');
            $table->integer('number');
            $table->date('date');
            $table->foreignUuid('customer_id')->constrained('customers');
            $table->bigInteger('final_price')->nullable();
            $table->bigInteger('pay_cash')->nullable();
            $table->bigInteger('pay_pos')->nullable();
            $table->foreignUuid('pos_bank_account_id')->nullable()->constrained('bank_accounts');
            $table->text('description')->nullable();
            $table->foreignUuid('user_id')->constrained('users');
            $table->timestamps();
        });

        Schema::create('pre_invoice_status', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('invoice_id')->constrained('accounting')->cascadeOnDelete();
            $table->date('date');
            $table->string('status');
            $table->string('reject_reason')->nullable();
            $table->text('description')->nullable();
            $table->foreignUuid('user_id')->constrained('users');
            $table->timestamps();
        });

        Schema::create('checks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->tinyInteger('type');
            $table->tinyInteger('status');
            $table->date('date');
            $table->bigInteger('amount')->nullable();
            $table->string('series')->nullable();
            $table->string('serial')->nullable();
            $table->text('description')->nullable();
            $table->foreignUuid('customer_id')->nullable()->constrained('customers');
            $table->foreignUuid('bank_id')->nullable()->constrained('banks');
            $table->foreignUuid('bank_account_id')->nullable()->constrained('bank_accounts');
            $table->foreignUuid('invoice_id')->nullable()->constrained('accounting');
            $table->foreignUuid('user_id')->constrained('users');
            $table->timestamps();
        });

        // اقلام فروش؛ یا متعلق به اتوماسیون (automation_id) یا به فاکتور حسابداری (invoice_id)
        Schema::create('orders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('automation_id')->nullable()->constrained('automation')->cascadeOnDelete();
            $table->foreignUuid('invoice_id')->nullable()->constrained('accounting')->cascadeOnDelete();
            $table->foreignUuid('product_id')->constrained('products');
            $table->double('count');
            $table->unsignedBigInteger('price');
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
        Schema::dropIfExists('checks');
        Schema::dropIfExists('pre_invoice_status');
        Schema::dropIfExists('accounting');
        Schema::dropIfExists('bank_accounts');
    }
};
