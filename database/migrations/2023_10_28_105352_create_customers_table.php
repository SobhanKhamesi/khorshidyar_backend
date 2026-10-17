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
        Schema::create('customers', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->tinyInteger('status')->nullable();
            $table->tinyInteger('type')->nullable();

            $table->string('name');
            $table->string('contact_number');
            $table->string('contact_number1')->nullable();
            $table->string('contact_number2')->nullable();
            $table->string('landline_number')->nullable();

            $table->string('province')->nullable();
            $table->string('city')->nullable();
            $table->string('national_code')->nullable();
            $table->string('postal_code')->nullable();
            $table->string('address')->nullable();

            $table->date('birth_date')->nullable();
            $table->date('marriage_date')->nullable();

            $table->string('email')->nullable();
            $table->string('eitaa')->nullable();
            $table->string('whatsapp')->nullable();
            $table->string('telegram')->nullable();
            $table->string('instagram')->nullable();
            $table->string('website')->nullable();
            $table->string('job')->nullable();
            $table->string('disc')->nullable();
            $table->string('category_id')->nullable();
            $table->string('job_address')->nullable();

            $table->string('lead_status')->nullable();
            $table->string('reject_reason')->nullable();

            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
