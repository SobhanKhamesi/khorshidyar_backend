<?php

namespace Database\Seeders;

use App\Models\AutomationContract;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * داده‌های نمونه برای تست اعلان‌ها (یادآوری‌های سیستمی) و نمایش آن‌ها در لیست یادآوری‌ها و زنگوله منوی بالا:
 *  - ۲ فرم اولیه تکمیل شده توسط سرنخ/فرصت که هنوز در کارتابل دیده نشده‌اند
 *  - ۲ قرارداد امضا شده توسط مشتری که هنوز در کارتابل دیده نشده‌اند
 *  - ۲ نظرسنجی خدمات پس از فروش با رضایت «ضعیف» که بررسی نشده‌اند
 *  - چند یادآوری عادی (سررسید امروز، گذشته، آینده و تولد) برای ادمین و کارشناس فروش
 *
 * یادآوری‌های سیستمی با اولین درخواست هر کاربر (زنگوله/منو/لیست یادآوری‌ها) برای او ساخته می‌شوند.
 * اجرا:  php artisan db:seed --class=NotificationDemoSeeder
 * اگر قبلاً اجرا شده باشد (مشتری با شماره 09130000101 وجود داشته باشد) دوباره اجرا نمی‌شود.
 */
class NotificationDemoSeeder extends Seeder
{
    private array $columns = [];

    public function run(): void
    {
        if (DB::table('customers')->where('contact_number', '09130000101')->exists()) {
            $this->command?->warn('داده‌های نمونه اعلان‌ها قبلاً ثبت شده‌اند.');
            return;
        }

        $adminId = DB::table('users')->where('role', User::ADMIN)->value('id');
        $salesId = DB::table('users')->where('username', 'demo.sales')->value('id') ?? $adminId;
        $expertId = DB::table('users')->where('username', 'demo.expert')->value('id');
        $installerId = DB::table('users')->where('username', 'demo.installer1')->value('id');
        $serviceTypeId = DB::table('service_types')->value('id');
        $productId = DB::table('products')->value('id');

        DB::transaction(function () use ($adminId, $salesId, $expertId, $installerId, $serviceTypeId, $productId) {
            $customer = fn(array $data) => $this->insert('customers', $data + ['user_id' => $salesId, 'province' => 'اصفهان', 'city' => 'اصفهان']);

            $lead = $customer(['status' => 0, 'type' => 1, 'name' => 'پریسا نوروزی', 'contact_number' => '09130000101', 'lead_status' => 0,
                'description' => 'از طریق اینستاگرام درخواست مشاوره داده است']);
            $opportunity = $customer(['status' => 1, 'type' => 0, 'name' => 'بهزاد طاهری', 'contact_number' => '09130000102', 'lead_status' => 1]);
            $farhad = $customer(['status' => 3, 'type' => 0, 'name' => 'فرهاد کاظمی', 'contact_number' => '09130000103', 'national_code' => '1279988776',
                'address' => 'اصفهان، خیابان آپادانا اول، کوچه ۷، پلاک ۲۱']);
            $arian = $customer(['status' => 3, 'type' => 2, 'name' => 'شرکت آرین پلاستیک', 'contact_number' => '09130000104', 'national_code' => '1278877665',
                'address' => 'شهرک صنعتی محمودآباد، خیابان ۴، پلاک ۱۸']);
            $zahra = $customer(['status' => 3, 'type' => 1, 'name' => 'زهرا امینی', 'contact_number' => '09130000105',
                'address' => 'اصفهان، خیابان جابر انصاری، کوچه ۳۰']);

            $automation = fn(array $data) => $this->insert('automation', $data + ['user_id' => $salesId, 'expert_id' => $expertId, 'send_sms' => false]);

            // ---------- ۱) فرم‌های اولیه تکمیل شده (اعلان «فرم اولیه») ----------
            foreach ([[$lead, 'پریسا نوروزی', '09130000101', 'برق پشتیبان', 75], [$opportunity, 'بهزاد طاهری', '09130000102', 'درآمدزایی', 25]]
                     as [$customerId, $name, $mobile, $goal, $minutesAgo]) {
                $request = $automation(['status' => 0, 'survey_type' => 3, 'customer_id' => $customerId,
                    'address' => 'اصفهان، خیابان مشتاق دوم', 'description' => 'ثبت شده از فرم اولیه مشتری']);
                $this->insert('automation_customer_surveys', [
                    'automation_id' => $request, 'full_name' => $name, 'national_code' => '127' . rand(1000000, 9999999), 'bill_id' => (string)rand(1000000000, 9999999999),
                    'address' => 'اصفهان، خیابان مشتاق دوم، کوچه ۱۴', 'available_area' => 45, 'install_place' => 'پشت بام', 'building_age' => '۵ تا ۱۰ سال',
                    'ownership' => 'ملکی', 'goal' => $goal, 'property_type' => 'ویلایی', 'surface_material' => json_encode(['ایزوگام']), 'mobile' => $mobile,
                ]);
                $this->insert('customer_intake_forms', ['token' => Str::random(48), 'customer_id' => $customerId, 'automation_id' => $request,
                    'sent_by' => $salesId, 'sms_sent' => true, 'submitted_at' => now()->subMinutes($minutesAgo)]);
            }

            // ---------- ۲) قراردادهای امضا شده (اعلان «امضای قرارداد») ----------
            foreach ([[$farhad, '201', 'فرهاد کاظمی', 312000000, 8, 120], [$arian, '202', 'مهندس سعید آرین', 1450000000, 40, 15]]
                     as [$customerId, $invoice, $signer, $price, $panels, $minutesAgo]) {
                $negotiation = $automation(['status' => 2, 'survey_type' => 1, 'customer_id' => $customerId, 'invoice_number' => $invoice,
                    'negotiator' => 'سارا محمدی', 'final_price' => $price, 'description' => 'قرارداد برای امضا به مشتری ارسال شد']);
                if ($productId)
                    $this->insert('orders', ['automation_id' => $negotiation, 'product_id' => $productId, 'count' => $panels, 'price' => 7900000]);

                $contractId = $this->insert('automation_contracts', ['automation_id' => $negotiation, 'status' => AutomationContract::DRAFT,
                    'fields' => json_encode(['contract_number' => 'KY-' . $invoice])]);
                $contract = AutomationContract::find($contractId);
                $contract->update([
                    'status' => AutomationContract::SIGNED,
                    'snapshot' => $contract->buildData(),
                    'token' => AutomationContract::generateToken(),
                    'approved_by' => $adminId,
                    'approved_at' => now()->subDay(),
                    'sms_sent' => true,
                    'sent_at' => now()->subDay(),
                    'viewed_at' => now()->subHours(3),
                    'signer_name' => $signer,
                    'signature' => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=',
                    'signer_ip' => '127.0.0.1',
                    'signed_at' => now()->subMinutes($minutesAgo),
                    'signed_seen_at' => null,
                ]);
            }

            // ---------- ۳) نظرسنجی‌های ضعیف (اعلان «نظرسنجی ضعیف») ----------
            foreach ([[$zahra, 'نشتی آب از محل اتصال سازه به سقف', 1.0, 'بعد از نصب سقف چکه می‌کند و کسی برای پیگیری تماس نگرفت'],
                         [$farhad, 'خطای ارتباط اینورتر با اپلیکیشن', 2.0, 'مشکل حل نشد و کارشناس زودتر از موعد رفت']]
                     as [$customerId, $problem, $score, $feedback]) {
                $service = $this->insert('after_sales_services', ['customer_id' => $customerId, 'service_type_id' => $serviceTypeId,
                    'responsible_user_id' => $installerId, 'problem' => $problem, 'request_date' => now()->subDays(6)->format('Y-m-d'),
                    'follow_up_date' => now()->subDays(4)->format('Y-m-d'), 'status' => 3, 'source' => 1,
                    'resolution_description' => 'مراجعه و بررسی انجام شد', 'completed_at' => now()->subDays(3)]);
                $this->insert('ratings', ['after_sale_service_id' => $service, 'token' => Str::random(48), 'rating' => $score,
                    'feedback' => $feedback, 'submitted_at' => now()->subHours(2)]);
            }

            // ---------- ۴) یادآوری‌های عادی ----------
            $reminder = fn($title, $date, $type, $customerId, $description, $userId) => $this->insert('reminders', [
                'title' => $title, 'date' => $date->format('Y-m-d'), 'status' => 0, 'type' => $type,
                'customer_id' => $customerId, 'description' => $description, 'user_id' => $userId,
            ]);
            $reminder('پیگیری پرداخت قسط دوم شرکت آرین پلاستیک', now(), 0, $arian, 'سررسید امروز؛ با واحد مالی هماهنگ شود', $adminId);
            $reminder('ارسال گزارش تولید ماهانه برای فرهاد کاظمی', now()->subDays(2), 0, $farhad, 'سررسید گذشته', $adminId);
            $reminder('تولد زهرا امینی', now(), 1, $zahra, 'ارسال پیامک تبریک تولد', $adminId);
            $reminder('جلسه بازدید سایت شرکت آرین پلاستیک', now()->addDays(5), 0, $arian, 'یادآوری آینده؛ تا آن روز در زنگوله نمایش داده نمی‌شود', $adminId);
            if ($salesId !== $adminId)
                $reminder('تماس پیگیری با پریسا نوروزی', now(), 0, $lead, 'هماهنگی زمان بازدید', $salesId);
        });

        $this->command?->info('داده‌های نمونه اعلان‌ها ثبت شد: ۲ فرم اولیه، ۲ قرارداد امضا شده، ۲ نظرسنجی ضعیف و ۵ یادآوری عادی.');
    }

    // درج با شناسه uuid و زمان ثبت (در صورت وجود ستون‌ها)؛ شناسه برگردانده می‌شود
    private function insert(string $table, array $data): string
    {
        $columns = $this->columns[$table] ??= Schema::getColumnListing($table);
        $data = array_intersect_key($data, array_flip($columns));

        if (in_array('id', $columns) && !isset($data['id']))
            $data = ['id' => (string)Str::uuid()] + $data;
        if (in_array('created_at', $columns))
            $data['created_at'] ??= now();
        if (in_array('updated_at', $columns))
            $data['updated_at'] ??= now();

        DB::table($table)->insert($data);

        return $data['id'];
    }
}
