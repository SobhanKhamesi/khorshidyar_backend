<?php

namespace Database\Seeders;

use App\Models\User;
use Hekmatinasser\Verta\Verta;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * داده‌های نمونه (نزدیک به واقعیت) برای تست همه‌ی بخش‌های سامانه؛ از هر بخش ۴ تا ۵ نمونه.
 * فقط برای محیط توسعه/تست:  php artisan db:seed --class=DemoDataSeeder
 * اگر داده‌های نمونه قبلاً ثبت شده باشند (کاربر demo.expert وجود داشته باشد) دوباره اجرا نمی‌شود.
 * گذرواژه‌ی همه‌ی کاربران نمونه: 123456
 */
class DemoDataSeeder extends Seeder
{
    private array $columns = [];
    private string $adminId;

    public function run(): void
    {
        if (DB::table('users')->where('username', 'demo.expert')->exists()) {
            $this->command?->warn('داده‌های نمونه قبلاً ثبت شده‌اند.');
            return;
        }

        $this->call([AdminUserSeeder::class, BankSeeder::class, UnitSeeder::class, SmsTemplateSeeder::class]);
        $this->adminId = DB::table('users')->where('role', User::ADMIN)->value('id');

        DB::transaction(function () {
            $users = $this->users();
            $lookups = $this->lookups();
            $products = $this->products($lookups);
            $customers = $this->customers($lookups);
            $automations = $this->automations($users, $customers, $products, $lookups);
            $services = $this->installationServices($users, $customers, $automations, $products);
            $this->afterSales($users, $customers, $services, $lookups);
            $this->earnings($users, $services);
            $this->crm($users, $customers, $lookups);
        });

        $this->command?->info('داده‌های نمونه ثبت شد. کاربران: demo.expert / demo.installer1 / demo.installer2 / demo.sales (گذرواژه 123456)');
    }

    // ------------------------------------------------------------------ کاربران

    private function users(): array
    {
        $make = fn(string $username, string $name, int $role, array $mobiles, string $nationalCode, string $address) => $this->insert('users', [
            'name' => $name,
            'username' => $username,
            'national_code' => $nationalCode,
            'mobile' => $mobiles[0],
            'mobiles' => json_encode($mobiles),
            'phone' => '0313' . rand(2000000, 6999999),
            'address' => $address,
            'password' => Hash::make('123456'),
            'status' => User::ACTIVE,
            'role' => $role,
        ]);

        return [
            'expert' => $make('demo.expert', 'رضا کریمی', User::KARSHENAS_FANI, ['09131234567'], '1270458963', 'اصفهان، خیابان چهارباغ بالا، کوچه ۱۲، پلاک ۸'),
            'installer1' => $make('demo.installer1', 'حسین احمدی', User::NASSAB, ['09139876543', '09104567890'], '1291234567', 'اصفهان، شاهین شهر، خیابان عطار، پلاک ۴۵'),
            'installer2' => $make('demo.installer2', 'مجید رستمی', User::NASSAB, ['09135557788'], '1288765432', 'اصفهان، خمینی شهر، بلوار منتظری، کوچه لاله'),
            'sales' => $make('demo.sales', 'سارا محمدی', User::KARSHENAS_FOROSH, ['09121112233'], '0012345678', 'اصفهان، خیابان نظر شرقی، مجتمع سپید، واحد ۶'),
        ];
    }

    // ------------------------------------------------------------------ جداول پایه

    private function lookups(): array
    {
        $titled = function (string $table, array $items, bool $withUser = true) {
            $ids = [];
            foreach ($items as $key => $item) {
                $data = is_array($item) ? $item : ['title' => $item];
                if ($withUser)
                    $data['user_id'] = $this->adminId;
                $ids[$key] = $this->insert($table, $data);
            }
            return $ids;
        };

        $bankIds = DB::table('banks')->pluck('id', 'name');

        return [
            'customer_categories' => $titled('customer_categories', [
                'home' => ['title' => 'مشتریان خانگی', 'description' => 'ویلا و آپارتمان'],
                'industrial' => ['title' => 'مشتریان صنعتی', 'description' => 'کارخانه و کارگاه'],
                'agri' => ['title' => 'کشاورزی', 'description' => 'پمپ آب و گلخانه'],
            ]),
            'sources' => $titled('source_categories', [
                'instagram' => ['title' => 'اینستاگرام', 'description' => 'تبلیغات صفحه اینستاگرام'],
                'friends' => ['title' => 'معرفی دوستان', 'description' => null],
                'banner' => ['title' => 'بنر تبلیغاتی', 'description' => 'بنرهای سطح شهر'],
                'site' => ['title' => 'سایت', 'description' => 'فرم تماس سایت'],
            ]),
            'failures' => $titled('failure_categories', [
                'price' => ['title' => 'قیمت بالا', 'description' => 'مشتری قیمت را بالا دانست'],
                'rival' => ['title' => 'انتخاب رقیب', 'description' => 'قرارداد با شرکت دیگر'],
                'loan' => ['title' => 'عدم دریافت وام', 'description' => 'وام بانکی تایید نشد'],
            ]),
            'service_types' => $titled('service_types', [
                'inverter' => ['title' => 'تعمیر اینورتر', 'is_active' => true],
                'wash' => ['title' => 'شستشوی پنل‌ها', 'is_active' => true],
                'drop' => ['title' => 'بررسی افت تولید', 'is_active' => true],
                'battery' => ['title' => 'تعویض باتری', 'is_active' => true],
            ], false),
            'referrers' => $titled('referrers', [
                'ahmad' => ['name' => 'احمد صادقی', 'description' => 'نماینده فروش شاهین شهر'],
                'zahra' => ['name' => 'زهرا نوری', 'description' => 'همکار بازاریابی'],
            ], false),
            'bank_accounts' => $titled('bank_accounts', [
                'mellat' => ['bank_id' => $bankIds->first(), 'number' => '6104337812345678', 'description' => 'حساب جاری شرکت'],
                'melli' => ['bank_id' => $bankIds->skip(1)->first() ?? $bankIds->first(), 'number' => '6037997512349876', 'description' => 'حساب کارتخوان'],
            ]),
            'banks' => $bankIds,
        ];
    }

    private function products(array $lookups): array
    {
        $inventory = $this->insert('inventory', ['title' => 'انبار مرکزی', 'description' => 'اصفهان، شهرک صنعتی محمودآباد', 'user_id' => $this->adminId]);
        $cat = fn($title) => $this->insert('product_categories', ['title' => $title, 'inventory_id' => $inventory, 'user_id' => $this->adminId]);
        $panel = $cat('پنل خورشیدی');
        $inverter = $cat('اینورتر');
        $battery = $cat('باتری');
        $accessory = $cat('سازه و کابل');

        $units = DB::table('units')->pluck('id', 'title');
        $unit = fn($title) => $units[$title] ?? $units->first();

        $product = fn($name, $code, $stock, $buy, $sell, $technical, $category, $unitTitle) => $this->insert('products', [
            'name' => $name, 'code' => $code, 'first_inventory' => $stock, 'purchase_price' => $buy, 'selling_price' => $sell,
            'technical' => $technical, 'category_id' => $category, 'unit_id' => $unit($unitTitle), 'user_id' => $this->adminId,
        ]);

        return [
            'panel' => $product('پنل خورشیدی مونوکریستال ۵۸۰ وات', 'PV-580', 120, 6800000, 7900000, '580W Mono PERC Half-Cell', $panel, 'عدد'),
            'inverter' => $product('اینورتر هیبرید ۵ کیلووات', 'INV-5K', 15, 52000000, 61000000, '5kW Hybrid MPPT 48V', $inverter, 'عدد'),
            'inverter10' => $product('اینورتر آنگرید ۱۰ کیلووات', 'INV-10K', 8, 98000000, 115000000, '10kW On-Grid 3 Phase', $inverter, 'عدد'),
            'battery' => $product('باتری لیتیومی ۵ کیلووات ساعت', 'BAT-5', 20, 78000000, 89000000, 'LiFePO4 51.2V 100Ah', $battery, 'عدد'),
            'cable' => $product('کابل سولار ۶ میلی‌متر', 'CBL-6', 2000, 95000, 125000, 'PV1-F 1x6mm', $accessory, 'متر'),
        ];
    }

    // ------------------------------------------------------------------ مشتریان و سرنخ‌ها

    private function customers(array $lookups): array
    {
        $cats = $lookups['customer_categories'];
        $src = $lookups['sources'];
        $make = fn(array $data) => $this->insert('customers', $data + ['user_id' => $this->adminId, 'province' => 'اصفهان']);

        $customers = [
            'karimi' => $make(['status' => 3, 'type' => 0, 'name' => 'محمد رضایی', 'contact_number' => '09131002030', 'city' => 'اصفهان',
                'national_code' => '1271122334', 'address' => 'اصفهان، خیابان سعادت آباد، کوچه نسترن، پلاک ۱۴', 'birth_date' => $this->j('1365/08/16'),
                'job' => 'پزشک', 'category_id' => $cats['home'], 'source_id' => $src['instagram'], 'description' => 'ویلای دو طبقه، علاقه‌مند به برق پشتیبان']),
            'nasr' => $make(['status' => 3, 'type' => 2, 'name' => 'صنایع غذایی نصر سپاهان', 'contact_number' => '09133004050', 'landline_number' => '03136654321',
                'city' => 'اصفهان', 'address' => 'شهرک صنعتی جی، خیابان ۱۲، پلاک ۴۰', 'job' => 'تولید مواد غذایی', 'category_id' => $cats['industrial'],
                'source_id' => $src['site'], 'referrer_id' => $lookups['referrers']['ahmad'], 'referrer_percent' => 3, 'description' => 'قطعی برق باعث توقف خط تولید می‌شود']),
            'hosseini' => $make(['status' => 3, 'type' => 1, 'name' => 'مریم حسینی', 'contact_number' => '09135006070', 'city' => 'شاهین شهر',
                'address' => 'شاهین شهر، خیابان فردوسی، فرعی ۵ شرقی، پلاک ۱۱', 'birth_date' => $this->j('1370/02/03'), 'job' => 'معلم',
                'category_id' => $cats['home'], 'source_id' => $src['friends']]),
            'baghi' => $make(['status' => 3, 'type' => 0, 'name' => 'علی باغی', 'contact_number' => '09137008090', 'city' => 'نجف آباد',
                'address' => 'نجف آباد، جاده قهدریجان، باغ ۲۷', 'job' => 'کشاورز', 'category_id' => $cats['agri'], 'source_id' => $src['banner'],
                'description' => 'پمپ آب چاه کشاورزی ۱۰ اسب بخار']),
            'tavakoli' => $make(['status' => 3, 'type' => 0, 'name' => 'امیر توکلی', 'contact_number' => '09132223344', 'city' => 'اصفهان',
                'address' => 'اصفهان، خیابان هزار جریب، کوچه ۸، پلاک ۳', 'job' => 'مهندس عمران', 'category_id' => $cats['home'], 'source_id' => $src['site']]),
            // سرنخ‌ها
            'lead1' => $make(['status' => 0, 'type' => 0, 'name' => 'حمید صالحی', 'contact_number' => '09139990011', 'city' => 'اصفهان',
                'lead_status' => 0, 'source_id' => $src['instagram'], 'description' => 'درخواست مشاوره برای نیروگاه ۵ کیلووات']),
            'lead2' => $make(['status' => 0, 'type' => 1, 'name' => 'نرگس مرادی', 'contact_number' => '09134445566', 'city' => 'کاشان',
                'lead_status' => 1, 'source_id' => $src['site'], 'description' => 'منتظر بررسی شرایط وام']),
            'lead3' => $make(['status' => 0, 'type' => 0, 'name' => 'کاظم یزدانی', 'contact_number' => '09136667788', 'city' => 'مبارکه',
                'lead_status' => 2, 'reject_reason' => 'قیمت بالا', 'source_id' => $src['banner']]),
            'lead4' => $make(['status' => 0, 'type' => 2, 'name' => 'شرکت کاشی پارس', 'contact_number' => '09131113355', 'city' => 'یزد',
                'lead_status' => 3, 'source_id' => $src['friends']]),
            // فرصت‌ها
            'opp1' => $make(['status' => 1, 'type' => 0, 'name' => 'رامین شریفی', 'contact_number' => '09138889900', 'city' => 'اصفهان',
                'lead_status' => 0, 'source_id' => $src['instagram'], 'description' => 'آماده دریافت پیش فاکتور']),
            'opp2' => $make(['status' => 1, 'type' => 2, 'name' => 'گلخانه سبز زاینده', 'contact_number' => '09137771122', 'city' => 'فلاورجان',
                'lead_status' => 1, 'category_id' => $cats['agri'], 'source_id' => $src['site']]),
        ];

        $this->insert('co_persons', ['customer_id' => $customers['nasr'], 'gender' => 1, 'name' => 'مهندس جواد نصر', 'contact_number' => '09133004051',
            'position' => 'مدیر کارخانه', 'user_id' => $this->adminId]);
        $this->insert('co_persons', ['customer_id' => $customers['nasr'], 'gender' => 0, 'name' => 'خانم الهام کاظمی', 'contact_number' => '09133004052',
            'position' => 'مسئول خرید', 'user_id' => $this->adminId]);

        return $customers;
    }

    // ------------------------------------------------------------------ اتوماسیون فروش

    private function automations(array $users, array $customers, array $products, array $lookups): array
    {
        $make = fn(array $data) => $this->insert('automation', $data + ['user_id' => $users['sales'], 'send_sms' => false]);
        $order = fn($automation, $product, $count, $price) => $this->insert('orders', [
            'automation_id' => $automation, 'product_id' => $products[$product], 'count' => $count, 'price' => $price,
        ]);
        $customerAddress = fn($key) => DB::table('customers')->where('id', $customers[$key])->value('address');

        // ۱) درخواست جدید با فرم بازدید خانگی
        $request = $make(['status' => 0, 'survey_type' => 1, 'customer_id' => $customers['tavakoli'], 'expert_id' => $users['expert'],
            'address' => $customerAddress('tavakoli'), 'visit_date' => $this->j('1405/08/18'), 'description' => 'بازدید برای نیروگاه ۵ کیلووات روی پشت بام']);
        $homeSurvey = $this->insert('automation_home_surveys', [
            'automation_id' => $request, 'goals' => json_encode(['برق پشتیبان', 'کاهش میزان مصرف']),
            'meter_single_selected' => true, 'meter_single_count' => 1, 'meter_single_amp' => 25, 'bill_id' => '7845123690124',
            'install_location_type' => 'plant_to_inverter', 'install_location_distance' => 15, 'has_internal_panel' => true,
            'connection_point' => 'تابلو برق اصلی واحد', 'cable_length' => 30, 'panel_location_note' => 'پشت بام، بدون سایه',
            'consumer_fridge_selected' => true, 'consumer_fridge_count' => 2, 'consumer_lighting_selected' => true, 'consumer_lighting_watt' => 400,
            'consumer_tv_selected' => true, 'consumer_gas_cooler_selected' => true, 'outage_items' => json_encode(['یخچال', 'روشنایی', 'تلویزیون']),
            'night_duration' => 4, 'system_type' => 'هیبرید', 'main_decision_maker' => 'خود مشتری', 'has_limitation' => false,
        ]);
        $this->insert('automation_home_gas_coolers', ['home_survey_id' => $homeSurvey, 'size' => '24', 'inverter' => true, 'count' => 1]);

        // ۲) پیش فاکتور (فرم بازدید صنعتی)
        $preInvoice = $make(['status' => 1, 'survey_type' => 2, 'customer_id' => $customers['nasr'], 'expert_id' => $users['expert'],
            'address' => $customerAddress('nasr'), 'invoice_number' => '101', 'visit_date' => $this->j('1405/08/05'),
            'referrer_percent' => 3, 'final_price' => 1320000000, 'description' => 'نیروگاه ۳۰ کیلووات برای خط بسته‌بندی']);
        $industrial = $this->insert('automation_industrial_surveys', [
            'automation_id' => $preInvoice, 'goals' => json_encode(['جبران قطعی برق', 'جلوگیری از توقف تولید']),
            'meter_type' => 'دیماندی', 'meter_amp' => 150, 'bill_id' => '9512367840025', 'branch_capacity' => 120,
            'has_dedicated_transformer' => true, 'outage_per_week' => 3, 'outage_hours_each' => 2, 'outage_time' => '۱۲ تا ۱۴',
            'outage_effects' => json_encode(['توقف خط تولید', 'خرابی محصول']), 'must_stay_on_equipment' => 'سردخانه و دستگاه بسته‌بندی',
            'damage_at_moment' => 50000000, 'damage_per_hour' => 20000000, 'night_backup_duration' => '۲ ساعت',
            'install_location' => 'سقف سوله', 'has_diesel_generator' => true, 'diesel_capacity' => 60, 'diesel_fuel_consumption' => 15,
            'diesel_working_hours' => 10, 'system_preference' => 'هیبرید', 'payment_type' => 'اقساطی', 'final_decision_maker' => 'مدیرعامل',
        ]);
        $this->insert('automation_industrial_main_consumers', ['industrial_survey_id' => $industrial, 'name' => 'کمپرسور سردخانه', 'power' => 15, 'type' => 'سه فاز', 'starter_type' => 'ستاره مثلث']);
        $this->insert('automation_industrial_main_consumers', ['industrial_survey_id' => $industrial, 'name' => 'دستگاه بسته‌بندی', 'power' => 7, 'type' => 'سه فاز', 'starter_type' => 'اینورتر']);
        $order($preInvoice, 'panel', 52, 7900000);
        $order($preInvoice, 'inverter10', 3, 115000000);
        $order($preInvoice, 'cable', 300, 125000);

        // ۳) مذاکره و قرارداد
        $negotiation = $make(['status' => 2, 'survey_type' => 1, 'customer_id' => $customers['hosseini'], 'expert_id' => $users['expert'],
            'address' => $customerAddress('hosseini'), 'invoice_number' => '102', 'visit_date' => $this->j('1405/07/28'),
            'negotiator' => 'سارا محمدی', 'final_price' => 265000000, 'description' => 'در حال مذاکره روی شرایط اقساط']);
        $this->insert('automation_home_surveys', ['automation_id' => $negotiation, 'goals' => json_encode(['برق پشتیبان']),
            'meter_single_selected' => true, 'meter_single_count' => 1, 'meter_single_amp' => 32, 'system_type' => 'آفگرید', 'main_decision_maker' => 'همسر']);
        $order($negotiation, 'panel', 8, 7900000);
        $order($negotiation, 'inverter', 1, 61000000);
        $order($negotiation, 'battery', 1, 89000000);
        $order($negotiation, 'cable', 40, 125000);
        $this->insert('automation_contract_items', ['automation_id' => $negotiation, 'title' => 'هزینه نصب و راه‌اندازی', 'amount' => 25000000]);
        $this->insert('automation_contract_items', ['automation_id' => $negotiation, 'title' => 'سازه گالوانیزه', 'amount' => 18000000]);
        $this->insert('automation_payments', ['automation_id' => $negotiation, 'method' => 'واریز به حساب', 'amount' => 100000000, 'date' => $this->j('1405/08/10')]);
        $this->insert('automation_cheques', ['automation_id' => $negotiation, 'number' => '452136', 'bank_name' => 'ملت', 'amount' => 82500000, 'due_date' => $this->j('1405/09/10')]);
        $this->insert('automation_cheques', ['automation_id' => $negotiation, 'number' => '452137', 'bank_name' => 'ملت', 'amount' => 82500000, 'due_date' => $this->j('1405/10/10')]);

        // ۴ و ۵) فروش موفق
        $success1 = $make(['status' => 3, 'survey_type' => 1, 'customer_id' => $customers['karimi'], 'expert_id' => $users['expert'],
            'address' => $customerAddress('karimi'), 'invoice_number' => '103', 'visit_date' => $this->j('1405/06/20'),
            'negotiator' => 'سارا محمدی', 'final_price' => 248000000, 'wage' => '25000000', 'description' => 'نیروگاه هیبرید ۵ کیلووات']);
        $order($success1, 'panel', 8, 7900000);
        $order($success1, 'inverter', 1, 61000000);
        $order($success1, 'battery', 1, 89000000);
        $this->insert('automation_payments', ['automation_id' => $success1, 'method' => 'نقدی', 'amount' => 148000000, 'date' => $this->j('1405/06/25')]);
        $this->insert('automation_payments', ['automation_id' => $success1, 'method' => 'کارتخوان', 'amount' => 100000000, 'date' => $this->j('1405/07/02')]);

        $success2 = $make(['status' => 3, 'survey_type' => 1, 'customer_id' => $customers['baghi'], 'expert_id' => $users['expert'],
            'address' => $customerAddress('baghi'), 'invoice_number' => '104', 'visit_date' => $this->j('1405/07/05'),
            'final_price' => 410000000, 'description' => 'نیروگاه ۱۰ کیلووات برای پمپ آب کشاورزی']);
        $order($success2, 'panel', 18, 7900000);
        $order($success2, 'inverter10', 1, 115000000);
        $order($success2, 'cable', 120, 125000);
        $this->insert('automation_payments', ['automation_id' => $success2, 'method' => 'واریز به حساب', 'amount' => 410000000, 'date' => $this->j('1405/07/12')]);

        // ۶) فروش ناموفق
        $failed = $make(['status' => -1, 'survey_type' => 1, 'customer_id' => $customers['tavakoli'], 'address' => $customerAddress('tavakoli'),
            'invoice_number' => '105', 'final_price' => 190000000, 'failure_id' => $lookups['failures']['rival'], 'description' => 'با شرکت دیگری قرارداد بست']);
        $order($failed, 'panel', 6, 7900000);
        $order($failed, 'inverter', 1, 61000000);

        // ۷) درخواست ثبت شده از فرم اولیه‌ی مشتری (تکمیل شده و هنوز در کارتابل دیده نشده → آلارم)
        $fromCustomer = $make(['status' => 0, 'survey_type' => 3, 'customer_id' => $customers['lead1'], 'address' => 'اصفهان، خیابان بزرگمهر، کوچه ۲۲',
            'description' => 'ثبت شده از فرم اولیه مشتری']);
        $this->insert('automation_customer_surveys', [
            'automation_id' => $fromCustomer, 'full_name' => 'حمید صالحی', 'national_code' => '1273344556', 'bill_id' => '6612398450012',
            'address' => 'اصفهان، خیابان بزرگمهر، کوچه ۲۲، پلاک ۵', 'available_area' => 60, 'install_place' => 'پشت بام', 'building_age' => '۵ تا ۱۰ سال',
            'ownership' => 'ملکی', 'goal' => 'درآمدزایی', 'property_type' => 'ویلایی', 'surface_material' => json_encode(['ایزوگام']), 'mobile' => '09139990011',
        ]);
        $this->insert('customer_intake_forms', ['token' => Str::random(48), 'customer_id' => $customers['lead1'], 'automation_id' => $fromCustomer,
            'sent_by' => $users['sales'], 'sms_sent' => true, 'submitted_at' => now()->subHours(3)]);

        foreach ([[$preInvoice, 'پیش فاکتور برای مدیر کارخانه ایمیل شد'], [$negotiation, 'مشتری درخواست تخفیف ۵ درصد دارد'],
                     [$success1, 'نصب با رضایت کامل انجام شد'], [$request, 'هماهنگی بازدید برای شنبه ساعت ۱۰']] as [$automation, $content]) {
            $this->insert('comment_automation', ['automation_id' => $automation, 'user_id' => $users['sales'], 'content' => $content]);
        }

        return compact('request', 'preInvoice', 'negotiation', 'success1', 'success2', 'failed', 'fromCustomer');
    }

    // ------------------------------------------------------------------ سرویس نصب

    private function installationServices(array $users, array $customers, array $automations, array $products): array
    {
        $customerOf = fn($key) => DB::table('customers')->where('id', $customers[$key])->first();
        $make = function (array $data, string $customerKey, string $createdAt) use ($customerOf) {
            $customer = $customerOf($customerKey);
            return $this->insert('installation_services', $data + [
                    'customer_id' => $customer->id, 'address' => $customer->address, 'contact_number' => $customer->contact_number,
                ], $createdAt);
        };
        $appointment = fn($service, $customerKey, $user, $type, $date, $start, $end, $status) => $this->insert('installer_appointments', [
            'user_id' => $user, 'customer_id' => $customers[$customerKey], 'installation_service_id' => $service, 'type' => $type,
            'date' => $this->j($date), 'start_time' => $start, 'end_time' => $end, 'status' => $status,
        ]);
        $item = fn($service, $product, $qty, $type) => $this->insert('installation_service_items', [
            'installation_service_id' => $service, 'product_id' => $products[$product], 'quantity' => $qty, 'type' => $type,
        ]);
        $history = function ($service, array $stages) use ($users) {
            foreach ($stages as [$stage, $user, $description, $date])
                $this->insert('installation_service_histories', [
                    'installation_service_id' => $service, 'user_id' => $user, 'stage' => $stage, 'description' => $description,
                ], $this->j($date) . ' 10:00:00');
        };

        // تکمیل نهایی (فروش موفق ۱) - یک نصاب برای کل پروژه
        $completed = $make(['purchase_source' => 1, 'installer_mode' => 1, 'automation_id' => $automations['success1'], 'status' => 2, 'stage' => 6,
            'technical_expert_id' => $users['expert'], 'completed_at' => $this->j('1405/07/20') . ' 16:00:00',
            'description' => 'نصب روی پشت بام، اتصال به تابلو اصلی'], 'karimi', $this->j('1405/07/08') . ' 09:00:00');
        $appointment($completed, 'karimi', $users['installer1'], 1, '1405/07/15', '08:00', '16:00', 2);
        $item($completed, 'panel', 8, 1);
        $item($completed, 'inverter', 1, 1);
        $item($completed, 'panel', 8, 2);
        $item($completed, 'cable', 35, 2);
        $history($completed, [[1, $this->adminId, 'ارجاع به نصاب', '1405/07/08'], [2, $users['installer1'], 'نصب انجام شد', '1405/07/15'],
            [3, $this->adminId, 'ارسال برای بررسی کارشناس', '1405/07/16'], [5, $users['expert'], 'مورد تایید است', '1405/07/18'],
            [6, $this->adminId, 'تکمیل نهایی', '1405/07/20']]);

        // در حال نصب (فروش موفق ۲) - نصاب‌های جداگانه
        $assigned = $make(['purchase_source' => 1, 'installer_mode' => 2, 'automation_id' => $automations['success2'], 'status' => 2, 'stage' => 1,
            'technical_expert_id' => $users['expert'], 'description' => 'نصب پنل روی سازه زمینی کنار چاه'], 'baghi', $this->j('1405/08/10') . ' 11:00:00');
        $appointment($assigned, 'baghi', $users['installer1'], 1, '1405/08/17', '07:30', '15:00', 1);
        $appointment($assigned, 'baghi', $users['installer2'], 2, '1405/08/18', '08:00', '13:00', 1);
        $appointment($assigned, 'baghi', $users['installer2'], 3, '1405/08/19', '09:00', '11:00', 1);
        $item($assigned, 'panel', 18, 1);
        $item($assigned, 'inverter10', 1, 1);
        $history($assigned, [[1, $this->adminId, 'ارجاع به نصاب‌ها', '1405/08/10']]);

        // در حال بررسی کارشناس (خرید از جای دیگر)
        $review = $make(['purchase_source' => 2, 'installer_mode' => 1, 'status' => 2, 'stage' => 3, 'technical_expert_id' => $users['expert'],
            'description' => 'تجهیزات توسط مشتری خریداری شده، فقط نصب'], 'tavakoli', $this->j('1405/07/25') . ' 10:00:00');
        $appointment($review, 'tavakoli', $users['installer2'], 1, '1405/08/02', '08:00', '14:00', 2);
        $item($review, 'cable', 25, 2);
        $history($review, [[1, $this->adminId, 'ارجاع به نصاب', '1405/07/25'], [2, $users['installer2'], 'نصب انجام شد', '1405/08/02'],
            [3, $this->adminId, 'ارسال برای بررسی کارشناس', '1405/08/04']]);

        // تکمیل نهایی دیگر (برای خدمات پس از فروش و دریافتی نصاب ۲)
        $completed2 = $make(['purchase_source' => 2, 'installer_mode' => 1, 'status' => 2, 'stage' => 6, 'technical_expert_id' => $users['expert'],
            'completed_at' => $this->j('1405/06/10') . ' 15:00:00', 'description' => 'نیروگاه ۳ کیلووات آفگرید'], 'hosseini', $this->j('1405/05/28') . ' 09:00:00');
        $appointment($completed2, 'hosseini', $users['installer2'], 1, '1405/06/03', '08:00', '15:00', 2);
        $history($completed2, [[1, $this->adminId, 'ارجاع به نصاب', '1405/05/28'], [6, $this->adminId, 'تکمیل نهایی', '1405/06/10']]);

        // پیش‌نویس
        $draft = $make(['purchase_source' => 2, 'installer_mode' => 1, 'status' => 1, 'stage' => 0,
            'description' => 'منتظر تعیین نصاب'], 'nasr', $this->j('1405/08/14') . ' 12:00:00');
        $item($draft, 'panel', 20, 1);

        return compact('completed', 'assigned', 'review', 'completed2', 'draft');
    }

    // ------------------------------------------------------------------ خدمات پس از فروش و نظرسنجی

    private function afterSales(array $users, array $customers, array $services, array $lookups): void
    {
        $types = $lookups['service_types'];
        $make = fn(array $data) => $this->insert('after_sales_services', $data);
        $rating = fn($service, $score, $feedback, $date) => $this->insert('ratings', [
            'after_sale_service_id' => $service, 'token' => Str::random(48), 'rating' => $score, 'feedback' => $feedback,
            'submitted_at' => $score === null ? null : $this->j($date) . ' 18:30:00',
        ]);

        // تکمیل شده + نظرسنجی عالی
        $s1 = $make(['customer_id' => $customers['karimi'], 'installation_service_id' => $services['completed'], 'service_type_id' => $types['wash'],
            'responsible_user_id' => $users['installer1'], 'problem' => 'شستشوی دوره‌ای پنل‌ها', 'request_date' => $this->j('1405/08/01'),
            'follow_up_date' => $this->j('1405/08/05'), 'status' => 3, 'source' => 1, 'resolution_description' => 'پنل‌ها شسته شد، تولید ۱۲٪ افزایش یافت',
            'completed_at' => $this->j('1405/08/05') . ' 14:00:00']);
        $rating($s1, 5.0, 'خیلی منظم و به‌موقع بودند، ممنون', '1405/08/06');

        // تکمیل شده + نظرسنجی ضعیف (آلارم نارضایتی)
        $s2 = $make(['customer_id' => $customers['hosseini'], 'installation_service_id' => $services['completed2'], 'service_type_id' => $types['inverter'],
            'responsible_user_id' => $users['installer2'], 'problem' => 'اینورتر خطای اضافه ولتاژ می‌دهد و خاموش می‌شود', 'request_date' => $this->j('1405/08/03'),
            'follow_up_date' => $this->j('1405/08/07'), 'status' => 3, 'source' => 1, 'resolution_description' => 'تنظیمات اینورتر اصلاح شد',
            'completed_at' => $this->j('1405/08/08') . ' 12:00:00']);
        $rating($s2, 1.5, 'دو روز دیرتر از زمان اعلام شده آمدند و مشکل هنوز گاهی تکرار می‌شود', '1405/08/09');

        // تکمیل شده + نظرسنجی متوسط
        $s3 = $make(['customer_id' => $customers['karimi'], 'installation_service_id' => $services['completed'], 'service_type_id' => $types['drop'],
            'responsible_user_id' => $users['installer1'], 'problem' => 'افت تولید در روزهای ابری', 'request_date' => $this->j('1405/07/22'),
            'follow_up_date' => $this->j('1405/07/24'), 'status' => 3, 'source' => 2, 'resolution_description' => 'بررسی شد؛ افت طبیعی به دلیل شرایط جوی است',
            'completed_at' => $this->j('1405/07/24') . ' 11:00:00']);
        $rating($s3, 3.0, 'توضیحات خوب بود ولی انتظار راه‌حل داشتم', '1405/07/25');

        // در انتظار پیگیری با تاریخ مراجعه امروز (هشدار پیگیری فوری)
        $s4 = $make(['customer_id' => $customers['baghi'], 'service_type_id' => $types['battery'], 'responsible_user_id' => $users['installer2'],
            'problem' => 'باتری شارژ نگه نمی‌دارد', 'request_date' => $this->j('1405/08/13'), 'follow_up_date' => now()->format('Y-m-d'),
            'status' => 1, 'source' => 1]);

        // در حال پیگیری - نظرسنجی ارسال شده ولی هنوز تکمیل نشده
        $s5 = $make(['customer_id' => $customers['nasr'], 'service_type_id' => $types['inverter'], 'responsible_user_id' => $users['expert'],
            'problem' => 'نمایشگر اینورتر روشن نمی‌شود', 'request_date' => $this->j('1405/08/12'), 'follow_up_date' => $this->j('1405/08/20'),
            'status' => 2, 'source' => 1]);
        $rating($s5, null, null, null);
    }

    // ------------------------------------------------------------------ دریافتی پروژه‌ها (پروفایل کاربر)

    private function earnings(array $users, array $services): void
    {
        $make = fn($user, $service, $amount, $date, $settledAt, $description) => $this->insert('user_project_earnings', [
            'user_id' => $user, 'installation_service_id' => $service, 'amount' => $amount, 'date' => $this->j($date),
            'is_settled' => $settledAt !== null, 'settled_at' => $settledAt ? $this->j($settledAt) : null,
            'description' => $description, 'created_by' => $this->adminId,
        ]);

        $make($users['installer1'], $services['completed'], 18000000, '1405/07/20', '1405/07/30', 'دستمزد نصب نیروگاه ۵ کیلووات');
        $make($users['installer1'], $services['assigned'], 9000000, '1405/08/10', null, 'پیش‌پرداخت نصب پنل');
        $make($users['installer2'], $services['completed2'], 12000000, '1405/06/10', '1405/06/20', 'دستمزد نصب');
        $make($users['installer2'], $services['review'], 14000000, '1405/08/04', null, 'دستمزد نصب (منتظر تایید کارشناس)');
        $make($users['expert'], $services['completed'], 4000000, '1405/07/18', '1405/07/30', 'هزینه بازدید و بررسی فنی');
    }

    // ------------------------------------------------------------------ تماس‌ها، یادآوری، برنامه‌ریزی، گزارش، تیکت، چک، پیامک

    private function crm(array $users, array $customers, array $lookups): void
    {
        $call = fn($customer, $subject, $type, $date, $time, $reSubject, $reDate, $description, $user) => $this->insert('calls', [
            'customer_id' => $customers[$customer], 'subject' => $subject, 'type_call' => $type, 'date' => $this->j($date), 'time' => $time,
            'contacted' => DB::table('customers')->where('id', $customers[$customer])->value('name'),
            're_follow_subject' => $reSubject, 're_follow_date' => $reDate ? $this->j($reDate) : null, 're_follow_time' => $reDate ? '10:00' : null,
            'description' => $description, 'user_id' => $user,
        ]);
        $call('lead1', 'مشاوره اولیه نیروگاه خانگی', 'تماس ورودی', '1405/08/12', '11:20', 'هماهنگی جهت ملاقات حضوری', '1405/08/17', 'لینک فرم اولیه ارسال شد', $users['sales']);
        $call('lead2', 'استعلام شرایط وام', 'تماس خروجی', '1405/08/13', '15:05', 'پیگیری وضعیت روند کاری', '1405/08/20', 'منتظر پاسخ بانک', $users['sales']);
        $call('opp1', 'ارسال پیش فاکتور', 'تماس خروجی', '1405/08/14', '10:40', 'پیش فاکتور بررسی شد؟', '1405/08/16', null, $users['sales']);
        $call('nasr', 'پیگیری پیش فاکتور صنعتی', 'تماس خروجی', '1405/08/11', '09:30', 'استعلام انجام شد؟', '1405/08/18', 'مدیر کارخانه در سفر است', $this->adminId);
        $call('lead3', 'تماس پیگیری', 'تماس خروجی', '1405/08/09', '17:00', null, null, 'به دلیل قیمت منصرف شد', $users['sales']);

        $reminder = fn($title, $date, $status, $type, $customer, $description, $user) => $this->insert('reminders', [
            'title' => $title, 'date' => $this->j($date), 'status' => $status, 'type' => $type,
            'customer_id' => $customer ? $customers[$customer] : null, 'description' => $description, 'user_id' => $user,
        ]);
        $reminder('تماس با کارخانه نصر برای قرارداد', '1405/08/15', 0, 0, 'nasr', 'نتیجه جلسه هیئت مدیره', $this->adminId);
        $reminder('تولد محمد رضایی', '1405/08/16', 0, 1, 'karimi', 'ارسال پیامک تبریک تولد', $this->adminId);
        $reminder('تمدید بیمه تجهیزات انبار', '1405/08/25', 0, 0, null, null, $this->adminId);
        $reminder('ارسال کاتالوگ برای رامین شریفی', '1405/08/10', 1, 0, 'opp1', null, $users['sales']);

        foreach ([['جلسه هفتگی تیم فروش', '1405/08/17', '09:00', '10:00', 0], ['بازدید پروژه نجف آباد', '1405/08/18', '08:00', '12:00', 0],
                     ['آموزش نصابان جدید', '1405/08/21', '14:00', '17:00', 0], ['بررسی موجودی انبار', '1405/08/12', '10:00', '11:30', 1]] as [$title, $date, $start, $end, $status]) {
            $this->insert('planning', ['title' => $title, 'date' => $this->j($date), 'time_start' => $start, 'time_end' => $end, 'status' => $status, 'user_id' => $this->adminId]);
        }

        foreach ([[$users['sales'], '1405/08/13', [['09:00', 'تماس با ۸ سرنخ جدید اینستاگرام'], ['11:30', 'ارسال ۲ پیش فاکتور'], ['15:00', 'جلسه با مشتری صنعتی']]],
                     [$users['expert'], '1405/08/14', [['08:30', 'بازدید پروژه شاهین شهر'], ['13:00', 'بررسی نصب پروژه توکلی']]]] as [$user, $date, $items]) {
            $report = $this->insert('daily_reports', ['date' => $this->j($date), 'start_time' => '08:00', 'end_time' => '17:00', 'status' => 1,
                'description' => 'گزارش کار روزانه', 'user_id' => $user]);
            foreach ($items as [$time, $body])
                $this->insert('daily_report_items', ['report_id' => $report, 'time' => $time, 'body' => $body]);
        }

        $this->insert('targeting', ['user' => $users['sales'], 'calls' => 60, 'sales' => 4, 'month' => 'آبان 1405',
            'start_date' => $this->j('1405/08/01'), 'end_date' => $this->j('1405/08/30'), 'description' => 'هدف فروش ماه آبان', 'user_id' => $this->adminId]);
        $this->insert('targeting', ['user' => $users['sales'], 'calls' => 50, 'sales' => 3, 'month' => 'مهر 1405',
            'start_date' => $this->j('1405/07/01'), 'end_date' => $this->j('1405/07/30'), 'description' => null, 'user_id' => $this->adminId]);

        foreach ([['پیگیری مدارک وام مشتری', 'lead2', 0, $users['sales'], 'مدارک شناسایی و قبض برق دریافت شود'],
                     ['هماهنگی تحویل اینورتر', 'nasr', 2, $users['expert'], 'اینورتر از انبار به محل پروژه منتقل شود'],
                     ['تنظیم قرارداد خانم حسینی', 'hosseini', 1, $users['sales'], 'قرارداد آماده و ارسال شد']] as [$title, $customer, $status, $assignee, $description]) {
            $ticket = $this->insert('ticket', ['status' => $status, 'title' => $title, 'descriptionTicket' => $description, 'time' => '1405/08/20',
                'description' => $description, 'customer_id' => $customers[$customer], 'user_id' => $this->adminId, 'userId' => $assignee]);
            $this->insert('ticket_comment', ['ticket_id' => $ticket, 'user_id' => $assignee, 'content' => 'در حال انجام است']);
        }

        $bankIds = $lookups['banks']->values();
        foreach ([[1, 0, '1405/08/15', 82500000, 'nasr', 'چک قسط اول پروژه'], [1, 1, '1405/07/10', 50000000, 'karimi', 'پاس شد'],
                     [0, 0, '1405/09/01', 120000000, null, 'خرید پنل از تامین کننده'], [1, 2, '1405/07/20', 30000000, 'hosseini', 'برگشت خورد - کسری موجودی']]
                 as [$type, $status, $date, $amount, $customer, $description]) {
            $this->insert('checks', ['type' => $type, 'status' => $status, 'date' => $this->j($date), 'amount' => $amount,
                'series' => (string)rand(10, 99), 'serial' => (string)rand(100000, 999999), 'description' => $description,
                'customer_id' => $customer ? $customers[$customer] : null, 'bank_id' => $bankIds[rand(0, max(0, $bankIds->count() - 1))] ?? null,
                'bank_account_id' => $type === 0 ? $lookups['bank_accounts']['mellat'] : null, 'user_id' => $this->adminId]);
        }

        foreach ([['customers', 'مشتری گرامی، جشنواره پاییزه خورشیدیار با ۱۰٪ تخفیف نصب آغاز شد.', 5, ['09131002030', '09135006070', '09137008090', '09132223344', '09133004050']],
                     ['leads', "از قطعی برق خسته شدید؟\nبرای مشاوره رایگان با ما تماس بگیرید.", 4, ['09139990011', '09134445566', '09136667788', '09131113355']],
                     ['specific', "حسین احمدی عزیز\nیک کار برای مشتری «علی باغی» در کارتابل خدمات پس از فروش برای شما ثبت شده است.", 1, ['09139876543']]]
                 as [$type, $body, $count, $receivers]) {
            $this->insert('sms', ['send_to_all' => $type !== 'specific', 'receiver_type' => $type, 'body' => $body, 'count' => $count,
                'receivers' => json_encode($receivers), 'user_id' => $this->adminId]);
        }
    }

    // ------------------------------------------------------------------ کمکی

    // تاریخ شمسی به میلادی (Y-m-d)
    private function j(string $jalali): string
    {
        return Verta::parse($jalali)->datetime()->format('Y-m-d');
    }

    // درج با شناسه uuid و زمان ثبت (در صورت وجود ستون‌ها)؛ شناسه برگردانده می‌شود
    private function insert(string $table, array $data, ?string $createdAt = null): string
    {
        $columns = $this->columns[$table] ??= Schema::getColumnListing($table);
        $id = (string)Str::uuid();

        if (in_array('id', $columns) && !isset($data['id']))
            $data = ['id' => $id] + $data;
        $time = $createdAt ? \Carbon\Carbon::parse($createdAt) : now();
        if (in_array('created_at', $columns))
            $data['created_at'] ??= $time;
        if (in_array('updated_at', $columns))
            $data['updated_at'] ??= $time;

        DB::table($table)->insert($data);

        return $data['id'] ?? $id;
    }
}
