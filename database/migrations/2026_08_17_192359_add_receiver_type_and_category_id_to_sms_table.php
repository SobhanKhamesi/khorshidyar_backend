<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sms', function (Blueprint $table) {
            $table->string('receiver_type')->nullable()->after('send_to_all');
            $table->foreignUuid('category_id')->nullable()->after('receiver_type');
        });
    }

    public function down(): void
    {
        Schema::table('sms', function (Blueprint $table) {
            $table->dropColumn('category_id');
            $table->dropColumn('receiver_type');
        });
    }
};
