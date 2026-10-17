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
        Schema::create('ratings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('after_sale_service_id');

            // توکن یکتا برای دسترسی عمومی مشتری به صفحه نظرسنجی (بدون نیاز به لاگین)
            $table->string('token', 64)->unique()->nullable();

            // امتیاز اعشاری بین 1 تا 5 (مثلاً 4.8)
            $table->decimal('rating', 2, 1)->nullable();
            $table->text('feedback')->nullable();

            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ratings');
    }
};
