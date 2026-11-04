<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * داده‌های پایه‌ی مورد نیاز برنامه. همه‌ی seederها idempotent هستند
     * و اجرای دوباره‌ی `php artisan db:seed` داده‌ی تکراری ایجاد نمی‌کند.
     */
    public function run(): void
    {
        $this->call([
            AdminUserSeeder::class,
            BankSeeder::class,
            UnitSeeder::class,
            SmsTemplateSeeder::class,
        ]);
    }
}
