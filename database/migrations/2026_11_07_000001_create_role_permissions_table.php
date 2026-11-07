<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * سطوح دسترسی قابل ویرایش هر نقش (سمت کاربر).
 * نقشی که ردیف ندارد از دسترسی‌های پیش‌فرض App\Support\Permissions استفاده می‌کند.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('role_permissions', function (Blueprint $table) {
            $table->unsignedTinyInteger('role')->primary()->comment('users.role');
            $table->json('permissions')->comment('کلیدهای دسترسی مجاز این نقش');
            $table->uuid('updated_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('role_permissions');
    }
};
