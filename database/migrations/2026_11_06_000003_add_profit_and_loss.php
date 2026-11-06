<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * سود و زیان پروژه:
 *  - نوع محصول (کالا/خدمت) و ماهیت خدمت (هزینه/دریافتی)
 *  - قیمت خرید هر قلم فاکتور
 *  - سود ناخالص اولیه‌ی ثابت (snapshot) در لحظه‌ی فروش موفق
 *  - قیمت خرید واحد اقلام سرویس نصب، هزینه‌ها/دریافتی‌های جانبی پروژه و سود نهایی
 *  - دستمزد پیش‌فرض کاربران برای ثبت خودکار دریافتی پروژه
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->unsignedTinyInteger('kind')->default(1)->after('name')->comment('1 کالا، 2 خدمت');
            $table->unsignedTinyInteger('service_nature')->nullable()->after('kind')->comment('فقط خدمت: 1 هزینه، 2 دریافتی');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedBigInteger('purchase_price')->nullable()->after('price')->comment('قیمت خرید واحد (در این فاکتور)');
        });

        Schema::table('automation', function (Blueprint $table) {
            $table->bigInteger('gross_sale_amount')->nullable()->after('final_price')->comment('مبلغ فروش در لحظه‌ی فروش موفق');
            $table->bigInteger('gross_purchase_cost')->nullable()->after('gross_sale_amount')->comment('جمع قیمت خرید اقلام در لحظه‌ی فروش موفق');
            $table->bigInteger('gross_profit')->nullable()->after('gross_purchase_cost')->comment('سود ناخالص اولیه (ثابت)');
            $table->timestamp('gross_profit_at')->nullable()->after('gross_profit');
        });

        Schema::table('installation_service_items', function (Blueprint $table) {
            $table->unsignedBigInteger('unit_purchase_price')->nullable()->after('quantity')->comment('قیمت خرید واحد در این پروژه');
        });

        Schema::table('installation_services', function (Blueprint $table) {
            $table->bigInteger('final_profit')->nullable()->after('completed_at')->comment('سود نهایی پروژه (به‌روز با هر تغییر مالی)');
            $table->json('wage_generated_keys')->nullable()->after('final_profit')->comment('دستمزدهای خودکار ایجاد شده (user_id:work)');
        });

        // هزینه‌ها و دریافتی‌های جانبی پروژه (مثل حمل و نقل، کارشناس فنی، خدمات ارائه شده)
        Schema::create('installation_service_finance_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('installation_service_id');
            // نام پیش‌فرض قید از ۶۴ کاراکتر مجاز MySQL بیشتر است
            $table->foreign('installation_service_id', 'isfi_installation_service_fk')
                ->references('id')->on('installation_services')->cascadeOnDelete();
            $table->foreignUuid('product_id')->nullable()->constrained('products')->nullOnDelete()->comment('خدمت تعریف شده (اختیاری)');
            $table->unsignedTinyInteger('direction')->comment('1 هزینه، 2 دریافتی');
            $table->unsignedTinyInteger('source')->default(1)->comment('1 ثبت دستی، 2 منتقل شده از فاکتور فروش');
            $table->string('title');
            $table->bigInteger('amount');
            $table->text('description')->nullable();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('user_project_earnings', function (Blueprint $table) {
            $table->string('work_type', 20)->nullable()->after('installation_service_id')->comment('project/panel/wiring/commissioning/review');
            $table->boolean('is_auto')->default(false)->after('work_type')->comment('ایجاد خودکار از دستمزد پیش‌فرض');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->json('default_wages')->nullable()->comment('دستمزد پیش‌فرض به تفکیک نوع کار');
        });

        // داده‌های قبلی: قیمت خرید اقلام فاکتور از قیمت فعلی محصول
        DB::statement('UPDATE orders o JOIN products p ON p.id = o.product_id SET o.purchase_price = p.purchase_price WHERE o.purchase_price IS NULL');
        DB::statement('UPDATE installation_service_items i JOIN products p ON p.id = i.product_id SET i.unit_purchase_price = p.purchase_price WHERE i.unit_purchase_price IS NULL');

        // سود ناخالص فروش‌های موفق قبلی
        DB::table('automation')->where('status', 3)->whereNull('gross_profit_at')->orderBy('id')
            ->get(['id', 'final_price'])
            ->each(function ($automation) {
                $cost = (int)DB::table('orders')->where('automation_id', $automation->id)
                    ->sum(DB::raw('`count` * COALESCE(purchase_price, 0)'));
                $sale = (int)($automation->final_price ?? DB::table('orders')->where('automation_id', $automation->id)
                    ->sum(DB::raw('`count` * price')));

                DB::table('automation')->where('id', $automation->id)->update([
                    'gross_sale_amount' => $sale,
                    'gross_purchase_cost' => $cost,
                    'gross_profit' => $sale - $cost,
                    'gross_profit_at' => now(),
                ]);
            });

        // سود نهایی سرویس‌های نصب قبلی
        DB::table('installation_services')->pluck('id')
            ->each(fn($id) => \App\Services\ProjectProfitService::refreshById($id));
    }

    public function down(): void
    {
        Schema::table('users', fn(Blueprint $table) => $table->dropColumn('default_wages'));
        Schema::table('user_project_earnings', fn(Blueprint $table) => $table->dropColumn(['work_type', 'is_auto']));
        Schema::dropIfExists('installation_service_finance_items');
        Schema::table('installation_services', fn(Blueprint $table) => $table->dropColumn(['final_profit', 'wage_generated_keys']));
        Schema::table('installation_service_items', fn(Blueprint $table) => $table->dropColumn('unit_purchase_price'));
        Schema::table('automation', fn(Blueprint $table) => $table->dropColumn(['gross_sale_amount', 'gross_purchase_cost', 'gross_profit', 'gross_profit_at']));
        Schema::table('orders', fn(Blueprint $table) => $table->dropColumn('purchase_price'));
        Schema::table('products', fn(Blueprint $table) => $table->dropColumn(['kind', 'service_nature']));
    }
};
