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
        Schema::table('comment_automation', function (Blueprint $table) {
            $table->dropColumn('invoice_id');
            $table->foreignUUid('automation_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('comment_automation', function (Blueprint $table) {
            $table->renameColumn('automation_id', 'invoice_id');
        });
    }
};
