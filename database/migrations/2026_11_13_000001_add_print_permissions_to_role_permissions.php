<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * دسترسی‌های جدید پرینت (sales.print و installation.print).
 * نقش‌هایی که دسترسی‌شان قبلاً توسط ادمین ذخیره شده، پرینت را مثل قبل داشته باشند؛
 * به جز نصاب که دیگر پرینت برگه‌ی نصب را نمی‌بیند.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('role_permissions'))
            return;

        foreach (DB::table('role_permissions')->get() as $row) {
            $permissions = json_decode($row->permissions ?? '[]', true) ?: [];

            if (in_array('sales.access', $permissions, true))
                $permissions[] = 'sales.print';

            $seesInstallation = in_array('installation.view_assigned', $permissions, true)
                || in_array('installation.view_all', $permissions, true);
            if ((int)$row->role !== User::NASSAB && $seesInstallation)
                $permissions[] = 'installation.print';

            DB::table('role_permissions')->where('role', $row->role)
                ->update(['permissions' => json_encode(array_values(array_unique($permissions)))]);
        }

        Cache::forget('role_permissions');
    }

    public function down(): void
    {
        if (!Schema::hasTable('role_permissions'))
            return;

        foreach (DB::table('role_permissions')->get() as $row) {
            $permissions = json_decode($row->permissions ?? '[]', true) ?: [];
            $permissions = array_values(array_diff($permissions, ['sales.print', 'installation.print']));
            DB::table('role_permissions')->where('role', $row->role)->update(['permissions' => json_encode($permissions)]);
        }

        Cache::forget('role_permissions');
    }
};
