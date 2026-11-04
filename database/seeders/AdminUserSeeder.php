<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class AdminUserSeeder extends Seeder
{
    /**
     * کاربر مدیر اولیه. نام کاربری و رمز از .env خوانده می‌شود (ADMIN_USERNAME / ADMIN_PASSWORD).
     * رمز توسط mutator مدل User هش می‌شود.
     */
    public function run(): void
    {
        $username = env('ADMIN_USERNAME', 'admin');

        if (User::where('username', $username)->exists())
            return;

        User::create([
            'name' => 'مدیر',
            'username' => $username,
            'password' => env('ADMIN_PASSWORD', '123456'),
            'role' => User::ADMIN,
            'status' => User::ACTIVE,
        ]);
    }
}
