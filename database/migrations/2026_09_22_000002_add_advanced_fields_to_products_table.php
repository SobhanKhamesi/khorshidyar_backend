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
        Schema::table('products', function (Blueprint $table) {
            $table->foreignUuid('unit_id')->nullable()->after('description');
            $table->foreignUuid('category_id')->nullable()->after('unit_id');
            $table->string('code')->nullable()->after('category_id');
            $table->string('technical')->nullable()->after('code');
            $table->string('picture')->nullable()->after('technical');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['unit_id', 'category_id', 'code', 'technical', 'picture']);
        });
    }
};
