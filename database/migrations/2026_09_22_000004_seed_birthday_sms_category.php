<?php

use App\Models\SourceSms;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (SourceSms::where('title', 'تبریک تولد')->exists()) {
            return;
        }

        $user = User::first();
        if (!$user) {
            return;
        }

        SourceSms::create([
            'title' => 'تبریک تولد',
            'description' => 'تولدت مبارک! این روز زیبا رو بهت تبریک می‌گیم و برات سالی پر از سلامتی و شادی آرزومندیم.',
            'user_id' => $user->id,
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        SourceSms::where('title', 'تبریک تولد')->delete();
    }
};
