<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('username')->unique();
            $table->string('password');
            $table->tinyInteger('status');
            $table->tinyInteger('role');
            $table->string('profile')->nullable();
            $table->string('emza')->nullable();

        });


        User::create([
            'name' => 'مدیر',
            'username' => 'admin',
            'password' => '123456',
            'status' => User::ADMIN,
            'role' => User::ACTIVE,
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
