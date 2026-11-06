<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // بررسی دلیل نارضایتی مشتری در نظرسنجی‌های با سطح «ضعیف»
        Schema::table('ratings', function (Blueprint $table) {
            $table->text('review_description')->nullable()->after('submitted_at')->comment('نتیجه بررسی دلیل نارضایتی');
            $table->foreignUuid('reviewed_by')->nullable()->after('review_description')->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');
        });
    }

    public function down(): void
    {
        Schema::table('ratings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropColumn(['review_description', 'reviewed_at']);
        });
    }
};
