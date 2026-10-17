<?php

use App\Models\Bank;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('banks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
        });


        $banks = ['بانک ملّی ایران', 'بانک سپه', 'بانک صنعت و معدن', 'بانک کشاورزی', 'بانک مسکن', 'بانک توسعه صادرات ایران', 'بانک توسعه تعاون', 'بانک اقتصاد نوین', 'بانک پارسیان', 'بانک کار آفرین', 'بانک سامان', 'بانک سینا', 'بانک خاور میانه', 'بانک شهر', 'بانک دی', 'بانک صادرات', 'بانک ملت', 'بانک تجارت', 'بانک رفاه', 'بانک حکمت ایرانیان', 'بانک گردشگری', 'بانک ایران زمین', 'بانک قوامین', 'بانک انصار', 'بانک سرمایه', 'بانک پاسارگاد', 'بانک قرض ‌الحسنه مهر ایران', 'بانک قرض‌الحسنه رسالت', 'پست بانک'];
        foreach ($banks as $bank)
            Bank::create(['name' => $bank]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('banks');
    }
};
