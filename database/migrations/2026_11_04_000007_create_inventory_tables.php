<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // انبار
        Schema::create('inventory', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('title')->nullable();
            $table->string('description')->nullable();
            $table->foreignUuid('user_id')->constrained('users');
            $table->timestamps();
        });

        // گروه کالا
        Schema::create('product_categories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('title')->nullable();
            $table->string('description')->nullable();
            $table->foreignUuid('inventory_id')->constrained('inventory')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users');
            $table->timestamps();
        });

        Schema::create('products', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('code')->nullable();
            $table->double('first_inventory')->nullable()->comment('موجودی اولیه');
            $table->unsignedBigInteger('purchase_price')->nullable();
            $table->unsignedBigInteger('selling_price')->nullable();
            $table->string('technical')->nullable();
            $table->string('picture')->nullable();
            $table->text('description')->nullable();
            $table->foreignUuid('unit_id')->nullable()->constrained('units')->nullOnDelete();
            $table->foreignUuid('category_id')->nullable()->constrained('product_categories')->nullOnDelete();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
        Schema::dropIfExists('product_categories');
        Schema::dropIfExists('inventory');
    }
};
