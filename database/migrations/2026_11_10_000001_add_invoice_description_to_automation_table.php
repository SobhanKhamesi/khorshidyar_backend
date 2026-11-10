<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * توضیحات پیش فاکتور جدا از توضیحات درخواست.
 * تا قبل از این، توضیحات پیش فاکتور روی ستون description (توضیحات درخواست) ذخیره می‌شد؛
 * برای اتوماسیون‌هایی که پیش فاکتور دارند، همان متن به توضیحات پیش فاکتور کپی می‌شود تا در پرینت از دست نرود.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('automation', function (Blueprint $table) {
            $table->text('invoice_description')->nullable()->after('description')->comment('توضیحات پیش فاکتور');
        });

        DB::table('automation')
            ->whereNotNull('invoice_number')
            ->whereNotNull('description')
            ->where('description', '<>', '')
            ->update(['invoice_description' => DB::raw('description')]);
    }

    public function down(): void
    {
        Schema::table('automation', function (Blueprint $table) {
            $table->dropColumn('invoice_description');
        });
    }
};
