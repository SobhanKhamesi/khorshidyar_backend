<?php

namespace App\Services;

use App\Models\InstallationService;
use App\Models\InstallationServiceFinanceItem;
use App\Models\InstallationServiceItem;
use App\Models\InstallerAppointment;
use App\Models\Product;
use App\Models\User;
use App\Models\UserProjectEarning;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * سود و زیان پروژه (سرویس نصب).
 *
 * سود نهایی = مبلغ فروش (مبلغ نهایی فاکتور؛ فقط خرید از ما)
 *           + دریافتی‌های جانبی (خدمات ارائه شده)
 *           − هزینه‌ی واقعی اقلام مصرف شده (مقدار مصرف × قیمت خرید واحد)
 *           − دستمزدها (دریافتی افراد از پروژه در پروفایل)
 *           − هزینه‌های جانبی
 *           − سهم معرف (فقط خرید از ما)
 *
 * سود ناخالص اولیه روی خود فروش (automation) ثابت ذخیره شده و این محاسبات آن را تغییر نمی‌دهند.
 */
class ProjectProfitService
{
    // ================= محاسبه =================

    // سود نهایی در ستون final_profit نگهداری می‌شود تا در لیست‌ها و گزارش‌ها بدون محاسبه‌ی مجدد قابل استفاده باشد
    public static function refreshById(?string $serviceId): ?int
    {
        // همیشه از داده‌ی تازه‌ی دیتابیس محاسبه می‌شود (نه رابطه‌های بارگذاری شده‌ی قبلی)
        $service = $serviceId ? InstallationService::withoutEagerLoads()->find($serviceId) : null;
        if (!$service)
            return null;

        $profit = self::breakdown($service)['final_profit'];

        // بدون رویداد مدل تا حلقه‌ی به‌روزرسانی ایجاد نشود
        DB::table('installation_services')->where('id', $service->id)->update(['final_profit' => $profit]);

        return $profit;
    }

    public static function refresh(InstallationService $service): void
    {
        $service->final_profit = self::refreshById($service->id);
        $service->syncOriginalAttribute('final_profit');
    }

    public static function breakdown(InstallationService $service): array
    {
        $service->loadMissing([
            'automation' => fn($q) => $q->withoutEagerLoads(),
            'usedItems.product', 'requiredItems.product',
            'financeItems.product', 'financeItems.user',
            'earnings.user' => fn($q) => $q->select('id', 'name', 'role'),
        ]);

        $automation = $service->purchase_source == InstallationService::INTERNAL ? $service->automation : null;

        // تا وقتی نصاب اقلام مصرفی را ثبت نکرده، اقلام پیش‌نیاز مبنای برآورد هستند
        $itemsBasis = $service->usedItems->isNotEmpty() ? 'used' : ($service->requiredItems->isNotEmpty() ? 'required' : 'none');
        $items = ($itemsBasis === 'used' ? $service->usedItems : $service->requiredItems)
            ->map(fn(InstallationServiceItem $item) => [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'product_name' => $item->product?->name,
                'unit' => $item->product?->unit?->title,
                'quantity' => $item->quantity,
                'unit_purchase_price' => (int)$item->unit_purchase_price,
                'total' => $item->total_cost,
            ])->values();

        $finance = $service->financeItems->map(fn(InstallationServiceFinanceItem $f) => [
            'id' => $f->id,
            'product_id' => $f->product_id,
            'product_name' => $f->product?->name,
            'direction' => $f->direction,
            'direction_label' => $f->direction_label,
            'source' => $f->source,
            'title' => $f->title,
            'amount' => $f->amount,
            'description' => $f->description,
            'user_name' => $f->user?->name,
        ]);
        $incomes = $finance->where('direction', InstallationServiceFinanceItem::INCOME)->values();
        $costs = $finance->where('direction', InstallationServiceFinanceItem::COST)->values();

        $wages = $service->earnings->map(fn(UserProjectEarning $e) => [
            'id' => $e->id,
            'user_id' => $e->user_id,
            'user_name' => $e->user?->name,
            'work_type' => $e->work_type,
            'work_label' => $e->work_label,
            'is_auto' => $e->is_auto,
            'amount' => $e->amount,
            'jalali_date' => $e->jalali_date,
            'is_settled' => $e->is_settled,
            'jalali_settled_at' => $e->jalali_settled_at,
            'description' => $e->description,
        ])->values();

        $saleAmount = $automation ? (int)$automation->final_price : 0;
        $incomeTotal = (int)$incomes->sum('amount');
        $itemsCost = (int)$items->sum('total');
        $wagesTotal = (int)$wages->sum('amount');
        $costsTotal = (int)$costs->sum('amount');
        $referrerPercent = $automation?->referrer_percent;
        $referrerAmount = $automation && $referrerPercent ? (int)round($saleAmount * $referrerPercent / 100) : 0;

        $revenue = $saleAmount + $incomeTotal;
        $afterItems = $revenue - $itemsCost;

        return [
            'is_internal' => (bool)$automation,
            'is_final' => $service->stage === InstallationService::STAGE_COMPLETED,
            'gross' => $automation ? [
                'sale_amount' => $automation->gross_sale_amount,
                'purchase_cost' => $automation->gross_purchase_cost,
                'profit' => $automation->gross_profit,
                'invoice_number' => $automation->invoice_number,
            ] : null,
            'sale_amount' => $saleAmount,
            'incomes' => $incomes,
            'income_total' => $incomeTotal,
            'revenue_total' => $revenue,
            'items_basis' => $itemsBasis,
            'items' => $items,
            'items_cost' => $itemsCost,
            'profit_after_items' => $afterItems,
            'wages' => $wages,
            'wages_total' => $wagesTotal,
            'costs' => $costs,
            'costs_total' => $costsTotal,
            'referrer_percent' => $referrerPercent,
            'referrer_amount' => $referrerAmount,
            'final_profit' => $afterItems - $wagesTotal - $costsTotal - $referrerAmount,
        ];
    }

    // ================= قیمت خرید اقلام =================

    /**
     * قیمت خرید واحد هر کالا در این پروژه: اول قیمت خرید همان کالا در فاکتور فروش، در غیر این صورت قیمت خرید فعلی محصول.
     * @return array<string,int> product_id => unit price
     */
    public static function unitPrices(InstallationService $service, array $productIds): array
    {
        $productIds = array_values(array_unique(array_filter($productIds)));
        if (!$productIds)
            return [];

        $prices = Product::withoutEagerLoads()->whereIn('id', $productIds)->pluck('purchase_price', 'id')
            ->map(fn($p) => (int)$p)->all();

        if ($service->purchase_source == InstallationService::INTERNAL && $service->automation_id) {
            DB::table('orders')->where('automation_id', $service->automation_id)
                ->whereIn('product_id', $productIds)->whereNotNull('purchase_price')
                ->get(['product_id', 'purchase_price'])
                ->each(function ($order) use (&$prices) {
                    $prices[$order->product_id] = (int)$order->purchase_price;
                });
        }

        return $prices;
    }

    // ================= انتقال خدمات فاکتور =================

    /**
     * خدماتی از فاکتور فروش که برای ما هزینه دارند (قیمت خرید > 0، مثل حمل و نقل) به‌عنوان هزینه‌ی جانبی پروژه منتقل می‌شوند.
     * کالاهای فاکتور به‌عنوان اقلام پیش‌نیاز در فرم ارجاع پیشنهاد می‌شوند. مبلغ فروش همه‌ی اقلام در مبلغ نهایی فاکتور است.
     */
    public static function importInvoiceServices(InstallationService $service): void
    {
        $service->financeItems()->where('source', InstallationServiceFinanceItem::SOURCE_INVOICE)->get()->each->delete();

        if ($service->purchase_source != InstallationService::INTERNAL || !$service->automation_id)
            return;

        DB::table('orders')
            ->join('products', 'products.id', '=', 'orders.product_id')
            ->where('orders.automation_id', $service->automation_id)
            ->where('products.kind', Product::KIND_SERVICE)
            ->where('orders.purchase_price', '>', 0)
            ->get(['orders.product_id', 'orders.count', 'orders.purchase_price', 'products.name'])
            ->each(fn($order) => InstallationServiceFinanceItem::create([
                'installation_service_id' => $service->id,
                'product_id' => $order->product_id,
                'direction' => InstallationServiceFinanceItem::COST,
                'source' => InstallationServiceFinanceItem::SOURCE_INVOICE,
                'title' => $order->name,
                'amount' => (int)round((float)$order->count * (int)$order->purchase_price),
                'description' => 'منتقل شده از فاکتور فروش',
                'user_id' => auth()->id(),
            ]));
    }

    // ================= دستمزد خودکار =================

    /**
     * هر کسی که در پروژه کار کرده (نصاب‌ها و کارشناس فنی بررسی کننده) و در تنظیمات دستمزد پیش‌فرض
     * برای آن نوع کار دارد، یک دریافتی (دستمزد) از این پروژه می‌گیرد. مدیر می‌تواند آن را ویرایش یا حذف کند؛
     * دستمزد حذف شده دوباره ساخته نمی‌شود. اگر فردی از پروژه کنار گذاشته شود، دستمزد خودکار و تسویه نشده‌ی او حذف می‌شود.
     */
    public static function syncWages(InstallationService $service): void
    {
        if ($service->stage === InstallationService::STAGE_DRAFT)
            return;

        $participants = self::participants($service);
        $generated = collect($service->wage_generated_keys ?? []);

        // کنار گذاشته شده‌ها
        foreach ($generated->diff($participants->keys()) as $key) {
            [$userId, $work] = explode(':', $key, 2);
            UserProjectEarning::where('installation_service_id', $service->id)
                ->where('user_id', $userId)->where('work_type', $work)
                ->where('is_auto', true)->where('is_settled', false)
                ->get()->each->delete();
            $generated = $generated->reject(fn($k) => $k === $key);
        }

        // افراد جدید
        $users = User::whereIn('id', $participants->pluck('user_id')->unique())->get()->keyBy('id');
        foreach ($participants as $key => $participant) {
            if ($generated->contains($key))
                continue;

            $amount = $users->get($participant['user_id'])?->defaultWageFor($participant['work']) ?? 0;
            if ($amount <= 0)
                continue;

            UserProjectEarning::create([
                'user_id' => $participant['user_id'],
                'installation_service_id' => $service->id,
                'work_type' => $participant['work'],
                'is_auto' => true,
                'amount' => $amount,
                'date' => now()->format('Y-m-d'),
                'is_settled' => false,
                'description' => 'دستمزد ' . UserProjectEarning::WORK_LABELS[$participant['work']],
                'created_by' => auth()->id(),
            ]);
            $generated->push($key);
        }

        $keys = $generated->values()->all();
        DB::table('installation_services')->where('id', $service->id)->update(['wage_generated_keys' => json_encode($keys)]);
        $service->wage_generated_keys = $keys;
        $service->syncOriginalAttribute('wage_generated_keys');
    }

    /**
     * @return Collection<string, array{user_id:string, work:string}> کلید: user_id:work
     */
    private static function participants(InstallationService $service): Collection
    {
        $roleWork = [
            InstallationService::ROLE_PANEL => UserProjectEarning::WORK_PANEL,
            InstallationService::ROLE_WIRING => UserProjectEarning::WORK_WIRING,
            InstallationService::ROLE_COMMISSIONING => UserProjectEarning::WORK_COMMISSIONING,
        ];

        $list = InstallerAppointment::withoutEagerLoads()
            ->where('installation_service_id', $service->id)
            ->where('status', '!=', InstallerAppointment::CANCELED)
            ->whereIn('type', array_keys($roleWork))
            ->get(['user_id', 'type'])
            ->map(fn($a) => [
                'user_id' => $a->user_id,
                // در حالت یک نصاب، کل پروژه در نوبت پنل ثبت می‌شود
                'work' => $service->installer_mode === InstallationService::MODE_SINGLE
                    ? UserProjectEarning::WORK_PROJECT
                    : $roleWork[$a->type],
            ]);

        if ($service->technical_expert_id)
            $list->push(['user_id' => $service->technical_expert_id, 'work' => UserProjectEarning::WORK_REVIEW]);

        return $list->keyBy(fn($p) => $p['user_id'] . ':' . $p['work']);
    }
}
