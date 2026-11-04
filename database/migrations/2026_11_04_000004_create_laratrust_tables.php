<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * جداول Laratrust. شناسه نقش/دسترسی/تیم عددی است (مدل‌های Laratrust auto-increment هستند)
 * اما user_id از نوع uuid است چون کلید جدول users uuid است.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('display_name')->nullable();
            $table->string('description')->nullable();
            $table->timestamps();
        });

        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('display_name')->nullable();
            $table->string('description')->nullable();
            $table->timestamps();
        });

        Schema::create('teams', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('display_name')->nullable();
            $table->string('description')->nullable();
            $table->timestamps();
        });

        Schema::create('role_user', function (Blueprint $table) {
            $table->foreignId('role_id')->constrained('roles')->cascadeOnUpdate()->cascadeOnDelete();
            $table->uuid('user_id');
            $table->string('user_type');
            $table->foreignId('team_id')->nullable()->constrained('teams')->cascadeOnUpdate()->cascadeOnDelete();
            $table->unique(['user_id', 'role_id', 'user_type', 'team_id']);
        });

        Schema::create('permission_user', function (Blueprint $table) {
            $table->foreignId('permission_id')->constrained('permissions')->cascadeOnUpdate()->cascadeOnDelete();
            $table->uuid('user_id');
            $table->string('user_type');
            $table->foreignId('team_id')->nullable()->constrained('teams')->cascadeOnUpdate()->cascadeOnDelete();
            $table->unique(['user_id', 'permission_id', 'user_type', 'team_id']);
        });

        Schema::create('permission_role', function (Blueprint $table) {
            $table->foreignId('permission_id')->constrained('permissions')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('role_id')->constrained('roles')->cascadeOnUpdate()->cascadeOnDelete();
            $table->primary(['permission_id', 'role_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('permission_user');
        Schema::dropIfExists('permission_role');
        Schema::dropIfExists('role_user');
        Schema::dropIfExists('teams');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('roles');
    }
};
