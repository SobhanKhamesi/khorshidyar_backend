<?php

use App\Models\SourceSms;
use App\Models\User;
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
        Schema::create('sms_categories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('title');
            $table->text('description')->nullable();
            $table->timestamps();
        });

        // todo sms notes

        SourceSms::create([
            'title' => 'خوش آمدگویی',
            'description' => 'خوش آمدید',
            'user_id' => User::first()->id,
        ]);
        SourceSms::create([
            'title' => 'تشکر از خرید',
            'description' => 'ممنون از خرید شما',
            'user_id' => User::first()->id,
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sms_categories');
    }
};
