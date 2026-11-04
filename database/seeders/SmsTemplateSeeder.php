<?php

namespace Database\Seeders;

use App\Models\CustomerIntakeForm;
use App\Models\SourceSms;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * قالب‌های پیامک که کد با «عنوان» به دنبال آن‌ها می‌گردد.
 * متن‌ها پیش‌فرض هستند و از بخش تنظیمات پیامک قابل ویرایش‌اند؛ عنوان‌ها نباید تغییر کنند.
 */
class SmsTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::where('role', User::ADMIN)->first() ?? User::first();
        if (!$user)
            return;

        $templates = [
            // LeadsController: پس از ثبت سرنخ
            'خوش آمدگویی' => 'به خانواده خورشیدیار خوش آمدید.',
            // AutomationController: پس از فروش موفق
            'تشکر از خرید' => 'از اعتماد و خرید شما سپاسگزاریم.',
            // AutomationController: ارسال لینک فرم بازدید
            'فرم بازدید' => 'لطفاً فرم بازدید را از طریق لینک زیر تکمیل نمایید.',
            // AfterSalesServiceController: ارسال لینک نظرسنجی خدمات پس از فروش
            'نظرسنجی' => 'لطفاً با ثبت نظر خود درباره‌ی خدمات ارائه شده، ما را در بهبود خدمات یاری کنید.',
            // RemindersController: تبریک تولد مشتری
            'تبریک تولد' => 'تولدت مبارک! این روز زیبا رو بهت تبریک می‌گیم و برات سالی پر از سلامتی و شادی آرزومندیم.',
            // IntakeFormController: لینک فرم اولیه ({link} با لینک فرم جایگزین می‌شود)
            CustomerIntakeForm::SMS_TEMPLATE_TITLE => CustomerIntakeForm::DEFAULT_SMS_TEXT,
        ];

        foreach ($templates as $title => $description) {
            SourceSms::firstOrCreate(
                ['title' => $title],
                ['description' => $description, 'user_id' => $user->id]
            );
        }
    }
}
