<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\API\Contracts\ApiController;
use App\Http\Controllers\API\Contracts\ApiHelper;
use App\Http\Controllers\Controller;
use App\Models\AfterSalesService;
use App\Models\Automation;
use App\Models\Check;
use App\Models\Customer;
use App\Models\InstallationService;
use App\Models\Rating;
use App\Models\Reminder;
use App\Support\Access;
use App\Support\SystemReminders;
use Carbon\Carbon;

class MainController extends Controller
{
    private ApiController $apiController;
    private ApiHelper $apiHelper;

    public function __construct(ApiController $apiController, ApiHelper $apiHelper)
    {
        $this->apiController = $apiController;
        $this->apiHelper = $apiHelper;
    }


    // آیتم‌های زنگوله منوی بالا: چک‌های سررسید شده و یادآوری‌های فعال (شامل اعلان‌های سیستمی)
    public function getExpiredItems()
    {
        SystemReminders::sync();

        $dataChecks = $this->expiredChecksQuery()->get();
        $dataReminder = $this->expiredRemindersQuery()->get();
        $data = [];


        foreach ($dataChecks as $check) {
            $data[] = [
                'type' => 0,
                'date' => $check->created_at,
                'id' => $check->id,
                'title' => 'چک به مبلغ : ' . number_format($check->amount) . ' تومان',
            ];
        }
        foreach ($dataReminder as $reminder) {
            $data[] = [
                'type' => 1,
                'date' => $reminder->created_at,
                'id' => $reminder->id,
                'title' => $reminder->title,
                // نوع یادآوری (عمومی، تولد، فرم اولیه، قرارداد امضا شده، نظرسنجی ضعیف) برای آیکون و مسیر کلیک
                'reminder_type' => (int)$reminder->type,
                'is_system' => $reminder->is_system,
                'jalali_date' => $reminder->jalali_date,
            ];
        }

        $this->apiHelper->sortArray($data, 'date');

        return $this->apiController->respondData($data);

    }

    private function expiredChecksQuery()
    {
        return Check::where('user_id', auth()->id())->whereDate('date', '<=', Carbon::now())->where('status', Check::PENDING);
    }

    private function expiredRemindersQuery()
    {
        return Reminder::where('user_id', auth()->id())->whereDate('date', '<=', Carbon::now())->where('status', Reminder::ACTIVE);
    }

    public function checking()
    {
        // اعلان‌های جدید (فرم اولیه، قرارداد امضا شده، نظرسنجی ضعیف) به‌صورت یادآوری برای کاربر ساخته می‌شوند
        SystemReminders::sync();

        // همه‌ی شمارنده‌های منو بر اساس داده‌هایی است که کاربر به آن‌ها دسترسی دارد (ادمین: همه)
        $automations = fn($status) => Access::scopeAutomations(Automation::withoutEagerLoads()->where('status', $status))->count();
        $darkhastCount = $automations(Automation::REQUEST);
        $pishfactorCount = $automations(Automation::PRE_INVOICE);
        $mozakereCount = $automations(Automation::NEGOTIATION);

        $after_sale_count = Access::scopeAfterSales(AfterSalesService::query())
            ->whereIn("follow_up_date", [Carbon::today(), Carbon::tomorrow()])
            ->whereIn("status", [AfterSalesService::STATUS_PENDING, AfterSalesService::STATUS_IN_PROGRESS])->count();

        // فقط سرویس‌هایی که منتظر اقدام کاربر جاری هستند (تکمیل نهایی شده‌ها شمرده نمی‌شوند)
        $installation_service_count = InstallationService::query()
            ->awaitingActionBy(auth()->user())
            ->count();

        $data = [
            'darkhast_count' => $darkhastCount,
            'pishfactor_count' => $pishfactorCount,
            'mozakere_count' => $mozakereCount,
            'after_sale_count' => $after_sale_count,
            'installation_service_count' => $installation_service_count,
            // فرم‌های اولیه‌ای که مشتری تکمیل کرده و هنوز در کارتابل دیده نشده‌اند
            'intake_form_count' => IntakeFormController::unseenSubmittedCount(),
            // قراردادهایی که مشتری امضا کرده و هنوز در کارتابل دیده نشده‌اند
            'contract_signed_count' => ContractController::unseenSignedCount(),
            // نظرسنجی‌های خدمات پس از فروش با سطح «ضعیف» که دلیل نارضایتی‌شان هنوز بررسی نشده
            'poor_rating_count' => Access::can('after_sales.review_ratings') ? Rating::needsReview()->count() : 0,
            // تعداد آیتم‌های زنگوله (با تغییر آن، فرانت لیست زنگوله را دوباره دریافت می‌کند)
            'notifications_count' => $this->expiredChecksQuery()->count() + $this->expiredRemindersQuery()->count(),
            // نقش و دسترسی‌های فعلی کاربر (تغییرات ادمین در سطوح دسترسی بدون ورود مجدد اعمال می‌شود)
            'role' => Access::role(),
            'permissions' => Access::permissions(),
        ];

        return $this->apiController->respondData($data);
    }
}
