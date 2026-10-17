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
        Schema::create('automation_contract_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUUid('automation_id');
            $table->string('title');
            $table->decimal('amount', 15, 0)->default(0);
            $table->timestamps();
        });

        Schema::create('automation_payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUUid('automation_id');
            $table->string('method');
            $table->decimal('amount', 15, 0);
            $table->date('date')->nullable();
            $table->timestamps();
        });

        Schema::create('automation_cheques', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUUid('automation_id');
            $table->string('number');
            $table->string('bank_name')->nullable();
            $table->decimal('amount', 15, 0);
            $table->date('due_date')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('automation_contract_items');
    }
};
