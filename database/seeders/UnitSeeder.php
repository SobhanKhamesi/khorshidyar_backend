<?php

namespace Database\Seeders;

use App\Models\Unit;
use Illuminate\Database\Seeder;

class UnitSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['عدد', 'لیتر', 'کیلو', 'متر'] as $title)
            Unit::firstOrCreate(['title' => $title]);
    }
}
