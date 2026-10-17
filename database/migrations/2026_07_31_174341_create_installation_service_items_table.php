<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('installation_service_items', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('installation_service_id');

            $table->foreignUuid('product_id');

            $table->decimal('quantity', 10, 2)->default(1);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('installation_service_items');
    }
};
